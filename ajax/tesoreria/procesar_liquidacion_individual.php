<?php
/**
 * PROCESADOR DE LIQUIDACIÓN INDIVIDUAL (VERSIÓN FINAL BLINDADA)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';

error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Método no permitido."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. VALIDAR TOKEN Y SESIÓN
    $token = $_POST['token_op'] ?? '';
    $params_raw = desencriptar_url($token);
    if (!$params_raw) throw new Exception("Sesión de liquidación inválida o expirada.");

    list($id_persona, $tipo_persona, $mes, $anio) = explode('|', $params_raw);
    $usuario_operador = $_SESSION['nombre_usuario'] ?? 'Sistema';

    // --- BLOQUE DE SEGURIDAD: CONTROL DE DUPLICADOS ---
    $stmt_check = $pdo->prepare("SELECT id_liquidacion FROM liquidaciones_haberes 
                                 WHERE id_persona = ? AND tipo_persona = ? 
                                 AND mes_liquidado = ? AND anio_liquidado = ? 
                                 AND estado != 'Anulado' LIMIT 1");
    $stmt_check->execute([$id_persona, $tipo_persona, $mes, $anio]);
    
    if ($stmt_check->fetch()) {
        throw new Exception("Ya existe una liquidación registrada para este agente en el período $mes/$anio.");
    }
    // --------------------------------------------------

    // 2. CONFIGURACIÓN Y DATOS DEL AGENTE
    $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $conf = $stmt_conf->fetch(PDO::FETCH_ASSOC);

    $tabla = ($tipo_persona === 'Profesor') ? 'profesores' : 'personal_staff';
    $id_col = ($tipo_persona === 'Profesor') ? 'id_profesor' : 'id_staff';
    
    $stmt_a = $pdo->prepare("SELECT p.*, a.sueldo_base_area, a.id_area FROM $tabla p INNER JOIN areas a ON p.id_area = a.id_area WHERE p.$id_col = ?");
    $stmt_a->execute([$id_persona]);
    $p = $stmt_a->fetch(PDO::FETCH_ASSOC);

    if (!$p) throw new Exception("Agente no localizado en la base de datos.");

    // --- MOTOR DE CÁLCULO UNIFICADO ---
    $total_horas = 0;
    if ($tipo_persona === 'Profesor') {
        $stmt_h = $pdo->prepare("SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = ?");
        $stmt_h->execute([$id_persona]);
        $total_horas = (float)$stmt_h->fetchColumn();
        $basico = $total_horas * (float)($conf['valor_hora_catedra'] ?? 0);
    } else {
        $basico = (float)($p['sueldo_base_area'] ?? 0);
    }

    $es_monotributo = ($p['id_area'] == 4);
    $monto_ant = 0; $monto_zona = 0; $monto_pres = 0; $monto_hijos = 0;

    if (!$es_monotributo) {
        if (!empty($p['fecha_ingreso']) && $p['fecha_ingreso'] != '0000-00-00') {
            $anios_ant = (new DateTime($p['fecha_ingreso']))->diff(new DateTime())->y;
            $monto_ant = ($basico * ((float)($conf['porcentaje_antiguedad_anual'] ?? 2) * $anios_ant)) / 100;
        }
        $monto_zona = ($basico * (float)($conf['porcentaje_zona_patagonica'] ?? 40)) / 100;
        if (($p['cobra_presentismo'] ?? 0) == 1) {
            $monto_pres = (($basico + $monto_ant) * (float)($conf['porcentaje_presentismo'] ?? 10)) / 100;
        }
        $monto_hijos = (int)($p['hijos_verificados'] ?? 0) * (float)($conf['monto_asignacion_hijo'] ?? 0);
    }

    $stmt_bono = $pdo->prepare("SELECT SUM(monto) FROM bonos_extraordinarios WHERE mes_periodo = ? AND anio_periodo = ? AND (alcance_destino = ? OR alcance_destino = 'Todos') AND estado = 1");
    $stmt_bono->execute([$mes, $anio, $tipo_persona]);
    $monto_bono = (float)$stmt_bono->fetchColumn();

    $total_rem = $basico + $monto_ant + $monto_zona + $monto_pres;
    $bruto = $total_rem + $monto_hijos + $monto_bono;

    $ret_ley = (!$es_monotributo) ? ($total_rem * (11 + 3) / 100) + (float)($conf['monto_seguro_vida'] ?? 0) : 0;

    // 3. RETENCIONES DINÁMICAS (Sindicatos / Seguros "Todos")
    $total_terceros_agente = 0;
    $entidades_ids = [];
    if (!empty($p['id_entidad_sindicato'])) $entidades_ids[] = $p['id_entidad_sindicato'];
    if (!empty($p['id_entidad_seguro'])) $entidades_ids[] = $p['id_entidad_seguro'];

    $sql_ent = "SELECT * FROM entidades_pagos_terceros WHERE estado = 1 AND (categoria_afectada = 'Todos'";
    if (!empty($entidades_ids)) {
        $in_query = str_repeat('?,', count($entidades_ids) - 1) . '?';
        $sql_ent .= " OR id_entidad IN ($in_query)";
    }
    $sql_ent .= ")";
    $stmt_ent = $pdo->prepare($sql_ent);
    $stmt_ent->execute($entidades_ids);
    $entidades_a_liquidar = $stmt_ent->fetchAll(PDO::FETCH_ASSOC);

    foreach ($entidades_a_liquidar as $ent) {
        $total_terceros_agente += ($total_rem * (float)$ent['porcentaje_retencion']) / 100;
    }

    // 4. RETENCIONES JUDICIALES
    $stmt_j = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
    $stmt_j->execute([$id_persona, $tipo_persona]);
    $judiciales = $stmt_j->fetchAll(PDO::FETCH_ASSOC);
    $ret_jud_total = 0;
    foreach($judiciales as $rj) {
        $ret_jud_total += ($rj['tipo_calculo'] == 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
    }

    $total_descuentos = $ret_ley + $total_terceros_agente + $ret_jud_total;
    $neto = $bruto - $total_descuentos;

    // 5. PREPARAR DATOS DE PAGO (SOLUCIÓN AL ERROR NOT NULL)
    $metodo_pago = ($_POST['metodo_desembolso'] == 'Efectivo') ? 1 : ($_POST['metodo_desembolso'] == 'Cheque' ? 2 : 3);
    
    $nro_cheque = ($metodo_pago == 2) ? ($_POST['ref_pago'] ?? '') : '';
    $nro_transf = ($metodo_pago == 3) ? ($_POST['ref_pago'] ?? '') : '';
    $banco_emi = ($metodo_pago != 1) ? ($_POST['banco_emisor'] ?? 'BANCO INSTITUCIÓN') : 'EFECTIVO';
    $obs = $_POST['observaciones'] ?? '';

    // 6. INSERTAR CABECERA
    $sql_ins = "INSERT INTO liquidaciones_haberes (
                uuid_liquidacion, id_persona, tipo_persona, mes_liquidado, anio_liquidado, 
                total_horas_catedra, valor_hora_aplicado, monto_bruto, monto_antiguedad_aplicado, 
                monto_asignacion_hijos, monto_zona_patagonica, monto_presentismo, monto_sindicato, 
                monto_bonos_extraordinarios, monto_retenciones_ley, monto_retenciones_judiciales, 
                monto_retenciones, monto_neto, id_modo_pago, nro_cheque, nro_transferencia, 
                banco_emisor, estado, usuario_registro, fecha_pago, observaciones
              ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Procesado', ?, NOW(), ?)";
    
    $stmt_ins = $pdo->prepare($sql_ins);
    $stmt_ins->execute([
        $id_persona, $tipo_persona, $mes, $anio, $total_horas, (float)($conf['valor_hora_catedra'] ?? 0),
        $bruto, $monto_ant, $monto_hijos, $monto_zona, $monto_pres, $total_terceros_agente, 
        $monto_bono, $ret_ley, $ret_jud_total, $total_descuentos, $neto, 
        $metodo_pago, $nro_cheque, $nro_transf, $banco_emi, $usuario_operador, $obs
    ]);

    $id_liq = $pdo->lastInsertId();

    // 7. DETALLE DE TERCEROS (Sindicatos/Seguros)
    foreach ($entidades_a_liquidar as $ent) {
        $monto_entidad = ($total_rem * (float)$ent['porcentaje_retencion']) / 100;
        if ($monto_entidad > 0) {
            $sql_ter_e = "INSERT INTO liquidaciones_terceros 
                (uuid_pago, id_liquidacion_origen, id_persona, tipo_persona, beneficiario_nombre, cuit_beneficiario, cbu_destino, monto, concepto, estado, fecha_creacion) 
                VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())";
            $pdo->prepare($sql_ter_e)->execute([
                $id_liq, $id_persona, $tipo_persona, $ent['nombre_entidad'], $ent['cuit_entidad'], $ent['cbu_destino'], $monto_entidad, "Aporte/Seguro: " . $ent['nombre_entidad']
            ]);
        }
    }

    // 8. DETALLE DE TERCEROS (Judiciales)
    foreach ($judiciales as $rj) {
        $monto_rj_ind = ($rj['tipo_calculo'] == 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
        if ($monto_rj_ind > 0) {
            $sql_ter = "INSERT INTO liquidaciones_terceros 
                (uuid_pago, id_liquidacion_origen, id_persona, tipo_persona, beneficiario_nombre, cuit_beneficiario, cbu_destino, monto, concepto, estado, fecha_creacion) 
                VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())";
            $pdo->prepare($sql_ter)->execute([
                $id_liq, $id_persona, $tipo_persona, $rj['beneficiario_nombre'], $rj['cuit_beneficiario'], $rj['cbu_destino'], $monto_rj_ind, "Retención Judicial: " . $rj['nro_expediente']
            ]);
        }
    }

    // 9. AFECTAR CAJA (Gastos)
    if (isset($_POST['afectar_caja']) && $_POST['afectar_caja'] === 'on') {
        $id_cat_gasto = ($tipo_persona === 'Profesor') ? ($conf['id_cat_gasto_profesores'] ?? 2) : ($conf['id_cat_gasto_staff'] ?? 1);
        $desc_gasto = "Liquidación Individual: " . $p['apellido'] . " - Período $mes/$anio";
        
        $sql_g = "INSERT INTO gastos (id_cat_gasto, monto, fecha_gasto, descripcion, estado, usuario_registro) 
                  VALUES (?, ?, NOW(), ?, 'Pagado', ?)";
        $pdo->prepare($sql_g)->execute([$id_cat_gasto, $neto, $desc_gasto, $usuario_operador]);
        $id_gasto = $pdo->lastInsertId();
        
        $pdo->prepare("UPDATE liquidaciones_haberes SET id_gasto_vinculado = ? WHERE id_liquidacion = ?")->execute([$id_gasto, $id_liq]);
    }

    $pdo->commit();
    $token_seguro = encriptar_url($id_liq); 
    echo json_encode([
        "success" => true, 
        "id_liquidacion" => $id_liq, 
        "token" => $token_seguro
    ]);

} catch (Exception $e) {
    // Si algo falló, deshacemos los cambios en la DB
    if ($pdo->inTransaction()) $pdo->rollBack();

    // RESPUESTA DE ERROR (Solo se envía si algo salió mal)
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}