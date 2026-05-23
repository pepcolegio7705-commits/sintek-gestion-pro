<?php
/**
 * PROCESADOR DE LIQUIDACIÓN MASIVA - VERSIÓN PRECISIÓN TOTAL
 * INCLUYE: Redondeo centesimal en cada operación para integridad de lotes.
 */
session_start();
require_once '../../core/conexion.php'; 

error_reporting(E_ALL);
ini_set('display_errors', 0); 
header('Content-Type: application/json');

$ids = $_POST['ids'] ?? [];
$id_banco_elegido = $_POST['id_banco'] ?? null; 
$seleccion_total = (isset($_POST['seleccion_total']) && $_POST['seleccion_total'] === 'true');
$mes = (int)($_POST['mes'] ?? date('n'));
$anio = (int)($_POST['anio'] ?? date('Y'));

$tipo_raw = $_POST['tipo'] ?? '';
$partes = explode('_', $tipo_raw);
$categoria = $partes[0] ?? 'Staff'; 
$tipo_personal = ($categoria === 'Monotributo') ? 'Staff' : $categoria; 
$es_masivo_filtro = (isset($partes[1]) && $partes[1] === 'Masivo') ? 1 : 0;

$usuario_operador = $_SESSION['nombre_usuario'] ?? 'Sistema';

try {
    // 1. OBTENCIÓN DE IDs (Lógica de filtrado)
    if ($seleccion_total) {
        $tabla_db = ($tipo_personal == 'Profesor') ? 'profesores' : 'personal_staff';
        $id_col_db = ($tipo_personal == 'Profesor') ? 'id_profesor' : 'id_staff';
        
        $apellido = $_POST['apellido'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $dni = $_POST['dni'] ?? '';

        $banco_cond = ($es_masivo_filtro) ? " AND p.pago_banco = 1 AND LENGTH(p.cbu) = 22" : "";
        $area_cond = "";
        if ($categoria == 'Monotributo') { $area_cond = " AND p.id_area = 4"; }
        elseif ($categoria == 'Staff') { $area_cond = " AND p.id_area IN (1, 2, 3)"; }
        elseif ($categoria == 'Profesor') { $area_cond = " AND p.id_area IN (5, 6)"; }

        $query_ids = "SELECT p.$id_col_db FROM $tabla_db p 
                    WHERE p.activo = 1 $banco_cond $area_cond
                    AND NOT EXISTS (
                        SELECT 1 FROM liquidaciones_haberes lh 
                        WHERE lh.id_persona = p.$id_col_db 
                        AND lh.tipo_persona = :tipo_p 
                        AND lh.mes_liquidado = :mes 
                        AND lh.anio_liquidado = :anio 
                        AND lh.estado != 'Anulado'
                    )";

        $params_busqueda = [':tipo_p' => $tipo_personal, ':mes' => $mes, ':anio' => $anio];
        if (!empty($apellido)) { $query_ids .= " AND p.apellido LIKE :ape"; $params_busqueda[':ape'] = "%$apellido%"; }
        if (!empty($nombre))   { $query_ids .= " AND p.nombre LIKE :nom"; $params_busqueda[':nom'] = "%$nombre%"; }
        if (!empty($dni))      { $query_ids .= " AND p.dni LIKE :dni"; $params_busqueda[':dni'] = "%$dni%"; }

        $stmt_ids = $pdo->prepare($query_ids);
        $stmt_ids->execute($params_busqueda);
        $ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($ids)) throw new Exception("No hay agentes para procesar.");

    $pdo->beginTransaction();

    // 2. CONFIGURACIÓN DE PARÁMETROS
    $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $conf = $stmt_conf->fetch(PDO::FETCH_ASSOC);

    $banco_nombre = "EFECTIVO/CHEQUE";
    if ($id_banco_elegido) {
        $stmt_bn = $pdo->prepare("SELECT nombre_banco FROM bancos_config WHERE id_banco = ?");
        $stmt_bn->execute([$id_banco_elegido]);
        $banco_nombre = $stmt_bn->fetchColumn() ?: "BANCO NO DEFINIDO";
    }
    
    $valor_hora_ref = (float)($conf['valor_hora_catedra'] ?? 0);
    $porc_zona      = (float)($conf['porcentaje_zona_patagonica'] ?? 0);
    $porc_pres      = (float)($conf['porcentaje_presentismo'] ?? 0);
    $porc_jub        = (float)($conf['porcentaje_jubilacion'] ?? 0);
    $porc_os         = (float)($conf['porcentaje_obra_social'] ?? 0);
    $monto_seg_ley   = (float)($conf['monto_seguro_vida'] ?? 0);
    $monto_hijo_ref = (float)($conf['monto_asignacion_hijo'] ?? 0);

    $escala_antiguedad = $pdo->query("SELECT * FROM configuracion_antiguedad_tramos ORDER BY anios_desde ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 3. CREACIÓN DEL LOTE (Se inicializa con total 0)
    $sql_lote = "INSERT INTO lotes_liquidaciones (uuid_lote, tipo_lote, id_banco, mes_periodo, anio_periodo, estado, total_monto, fecha_creacion) VALUES (UUID(), ?, ?, ?, ?, 'Pendiente', 0, NOW())";
    $stmt_lote = $pdo->prepare($sql_lote);
    $stmt_lote->execute([($es_masivo_filtro ? 'Bancario' : 'Efectivo_Cheque'), $id_banco_elegido, $mes, $anio]);
    $id_lote = $pdo->lastInsertId();

    $total_lote_acumulado = 0;
    $agentes_procesados = 0;

    foreach ($ids as $id_persona) {
        $id_persona = (int)$id_persona;
        $tabla = ($tipo_personal == 'Profesor') ? 'profesores' : 'personal_staff';
        $id_col = ($tipo_personal == 'Profesor') ? 'id_profesor' : 'id_staff';
        
        $stmt_p = $pdo->prepare("SELECT * FROM $tabla WHERE $id_col = ?");
        $stmt_p->execute([$id_persona]);
        $p = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if (!$p) continue;

        $total_horas = 0;
        if ($tipo_personal == 'Profesor') {
            $stmt_h = $pdo->prepare("SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = ?");
            $stmt_h->execute([$id_persona]);
            $total_horas = (float)$stmt_h->fetchColumn();
            if ($total_horas <= 0) continue; 
            $basico = round($total_horas * $valor_hora_ref, 2);
        } else {
            $stmt_area = $pdo->prepare("SELECT sueldo_base_area FROM areas WHERE id_area = ?");
            $stmt_area->execute([$p['id_area']]);
            $basico = round((float)$stmt_area->fetchColumn(), 2);
        }

        $es_monotributo = ($categoria === 'Monotributo');
        $monto_ant = 0; $monto_zona = 0; $monto_pres = 0; $monto_hijos = 0;

        if (!$es_monotributo) {
            // Antigüedad
            if (!empty($p['fecha_ingreso']) && $p['fecha_ingreso'] != '0000-00-00') {
                $anios_cumplidos = (new DateTime($p['fecha_ingreso']))->diff(new DateTime())->y;
                $porc_aplicar_ant = 0;
                foreach ($escala_antiguedad as $tramo) {
                    if ($anios_cumplidos >= $tramo['anios_desde'] && $anios_cumplidos <= $tramo['anios_hasta']) {
                        $porc_aplicar_ant = (float)$tramo['porcentaje_aplicado'];
                        break;
                    }
                }
                $monto_ant = round(($basico * $porc_aplicar_ant) / 100, 2);
            }
            $monto_zona = round(($basico * $porc_zona) / 100, 2);
            if (($p['cobra_presentismo'] ?? 0) == 1) {
                $monto_pres = round((($basico + $monto_ant) * $porc_pres) / 100, 2);
            }
            $monto_hijos = round((int)($p['hijos_verificados'] ?? 0) * $monto_hijo_ref, 2);
        }

        // Bonos
        $stmt_bono = $pdo->prepare("SELECT SUM(monto) FROM bonos_extraordinarios WHERE mes_periodo = ? AND anio_periodo = ? AND (alcance_destino = ? OR alcance_destino = 'Todos') AND estado = 1");
        $stmt_bono->execute([$mes, $anio, $categoria]);
        $monto_bono = round((float)$stmt_bono->fetchColumn(), 2);

        $total_remunerativo = round($basico + $monto_ant + $monto_zona + $monto_pres, 2);
        $bruto = round($total_remunerativo + $monto_hijos + $monto_bono, 2);

        $ret_ley = 0; 
        $total_terceros_agente = 0;
        $entidades_a_liquidar = [];

        if (!$es_monotributo) {
            $ret_ley = round(($total_remunerativo * ($porc_jub + $porc_os) / 100) + $monto_seg_ley, 2);
            
            // Entidades (Sindicatos/Seguros)
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
                $total_terceros_agente += round(($total_remunerativo * (float)$ent['porcentaje_retencion']) / 100, 2);
            }
        }

        // Judiciales
        $stmt_j = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
        $stmt_j->execute([$id_persona, $tipo_personal]);
        $judiciales = $stmt_j->fetchAll(PDO::FETCH_ASSOC);
        $ret_jud_total = 0;
        foreach($judiciales as $rj) {
            $m_rj = ($rj['tipo_calculo'] == 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
            $ret_jud_total += round($m_rj, 2);
        }

        $total_descuentos = round($ret_ley + $total_terceros_agente + $ret_jud_total, 2);
        $neto_agente = round($bruto - $total_descuentos, 2);

        // Guardar Liquidación Individual
        $sql_ins = "INSERT INTO liquidaciones_haberes (uuid_liquidacion, id_persona, tipo_persona, mes_liquidado, anio_liquidado, total_horas_catedra, valor_hora_aplicado, monto_bruto, monto_antiguedad_aplicado, monto_asignacion_hijos, monto_zona_patagonica, monto_presentismo, monto_sindicato, monto_bonos_extraordinarios, monto_retenciones_ley, monto_retenciones_judiciales, monto_retenciones, monto_neto, id_lote, id_modo_pago, banco_emisor, estado, usuario_registro, fecha_pago) 
                    VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, NOW())";

        $pdo->prepare($sql_ins)->execute([
            $id_persona, $tipo_personal, $mes, $anio, $total_horas, $valor_hora_ref, $bruto, $monto_ant, $monto_hijos, $monto_zona, $monto_pres, $total_terceros_agente, $monto_bono, $ret_ley, $ret_jud_total, $total_descuentos, $neto_agente, $id_lote, ($es_masivo_filtro ? 2 : 1), $banco_nombre, $usuario_operador
        ]);

        $id_liq = $pdo->lastInsertId();

        // Guardar Detalle Terceros (Entidades)
        foreach ($entidades_a_liquidar as $ent) {
            $monto_entidad = round(($total_remunerativo * (float)$ent['porcentaje_retencion']) / 100, 2);
            if ($monto_entidad > 0) {
                $sql_ter_e = "INSERT INTO liquidaciones_terceros (uuid_pago, id_lote, id_liquidacion_origen, id_persona, tipo_persona, beneficiario_nombre, cuit_beneficiario, cbu_destino, monto, concepto, estado, fecha_creacion) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())";
                $pdo->prepare($sql_ter_e)->execute([$id_lote, $id_liq, $id_persona, $tipo_personal, $ent['nombre_entidad'], $ent['cuit_entidad'], $ent['cbu_destino'], $monto_entidad, "Aporte/Seguro: " . $ent['nombre_entidad']]);
            }
        }

        // Guardar Detalle Terceros (Judiciales)
        foreach ($judiciales as $rj) {
            $monto_rj_ind = round((($rj['tipo_calculo'] == 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor']), 2);
            if ($monto_rj_ind > 0) {
                $sql_ter = "INSERT INTO liquidaciones_terceros (uuid_pago, id_lote, id_liquidacion_origen, id_persona, tipo_persona, beneficiario_nombre, cuit_beneficiario, cbu_destino, monto, concepto, estado, fecha_creacion) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())";
                $pdo->prepare($sql_ter)->execute([$id_lote, $id_liq, $id_persona, $tipo_personal, $rj['beneficiario_nombre'], $rj['cuit_beneficiario'], $rj['cbu_destino'], $monto_rj_ind, "Retención Judicial: " . $rj['nro_expediente']]);
            }
        }

        // ACUMULACIÓN EXACTA (Ya redondeada)
        $total_lote_acumulado += $neto_agente;
        $agentes_procesados++;
    }

    // REGISTRO FINAL DE GASTO
    $total_lote_final = round($total_lote_acumulado, 2);
    $id_cat_gasto = ($categoria === 'Profesor') ? ($conf['id_cat_gasto_profesores'] ?? 2) : ($conf['id_cat_gasto_staff'] ?? 1);
    
    $sql_gasto = "INSERT INTO gastos (id_cat_gasto, descripcion, monto, fecha_gasto, id_modo_pago, estado, usuario_registro, id_referencia_pago) VALUES (?, ?, ?, NOW(), ?, 'Pendiente', ?, ?)";
    $desc_gasto = "Liquidación Masiva $categoria - $mes/$anio - Lote #$id_lote";
    $stmt_gasto = $pdo->prepare($sql_gasto);
    $stmt_gasto->execute([$id_cat_gasto, $desc_gasto, $total_lote_final, ($es_masivo_filtro ? 2 : 1), $usuario_operador, $id_lote]);
    $id_gasto_final = $pdo->lastInsertId();

    // Actualizar Lote y vincular Gasto
    $pdo->prepare("UPDATE liquidaciones_haberes SET id_gasto_vinculado = ? WHERE id_lote = ?")->execute([$id_gasto_final, $id_lote]);
    $pdo->prepare("UPDATE lotes_liquidaciones SET cantidad_agentes = ?, total_monto = ? WHERE id_lote = ?")->execute([$agentes_procesados, $total_lote_final, $id_lote]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'agentes' => $agentes_procesados, 'total' => number_format($total_lote_final, 2, ',', '.')]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}