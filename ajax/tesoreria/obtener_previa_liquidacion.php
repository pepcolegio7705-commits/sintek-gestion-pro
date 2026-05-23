<?php
/**
 * AJAX: VISTA PREVIA DE LIQUIDACIÓN (RECÁLCULO SEGURO CON DESGLOSE DETALLADO)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';

header('Content-Type: application/json');

try {
    // 1. Recepción y validación del Token
    $token = $_POST['token'] ?? '';
    $params_raw = desencriptar_url($token);

    if (!$params_raw) {
        throw new Exception("Error de integridad: El token de seguridad no es válido.");
    }

    // Extraemos: ID | TIPO | MES | ANIO
    list($id_persona, $tipo_persona, $mes, $anio) = explode('|', $params_raw);

    // 2. Obtener Configuración de Tesorería Vigente
    $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $conf = $stmt_conf->fetch(PDO::FETCH_ASSOC);

    // 3. Consultar Datos del Agente (incluyendo afiliaciones a entidades)
    $tabla = ($tipo_persona === 'Profesor') ? 'profesores' : 'personal_staff';
    $id_col = ($tipo_persona === 'Profesor') ? 'id_profesor' : 'id_staff';

    $sql_agente = "SELECT p.*, a.sueldo_base_area, a.id_area 
                   FROM $tabla p 
                   INNER JOIN areas a ON p.id_area = a.id_area 
                   WHERE p.$id_col = ?";
    $stmt_a = $pdo->prepare($sql_agente);
    $stmt_a->execute([$id_persona]);
    $p = $stmt_a->fetch(PDO::FETCH_ASSOC);

    if (!$p) throw new Exception("Agente no encontrado.");

    // --- MOTOR DE CÁLCULO ---
    
    // A. Sueldo Básico
    if ($tipo_persona === 'Profesor') {
        $stmt_h = $pdo->prepare("SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = ?");
        $stmt_h->execute([$id_persona]);
        $unidades = (float)$stmt_h->fetchColumn();
        $basico = $unidades * (float)($conf['valor_hora_catedra'] ?? 0);
        $lbl_basico = "Horas Cátedra ($unidades)";
    } else {
        $basico = (float)($p['sueldo_base_area'] ?? 0);
        $lbl_basico = "Sueldo Básico Categoría";
    }

    // B. Adicionales (Solo si no es Monotributo / Area 4)
    $monto_ant = 0; $monto_zona = 0; $monto_pres = 0; $anios_ant = 0;
    if ($p['id_area'] != 4) {
        if (!empty($p['fecha_ingreso'])) {
            $anios_ant = (new DateTime())->diff(new DateTime($p['fecha_ingreso']))->y;
            $porc_ant_anual = (float)($conf['porcentaje_antiguedad_anual'] ?? 2);
            $monto_ant = ($basico * ($porc_ant_anual * $anios_ant)) / 100;
        }
        $monto_zona = ($basico * (float)($conf['porcentaje_zona_patagonica'] ?? 40)) / 100;
        if (($p['cobra_presentismo'] ?? 0) == 1) {
            $monto_pres = (($basico + $monto_ant) * (float)($conf['porcentaje_presentismo'] ?? 10)) / 100;
        }
    }

    $total_remunerativo = $basico + $monto_ant + $monto_zona + $monto_pres;

    // C. No Remunerativos
    $monto_hijos = (int)($p['hijos_verificados'] ?? 0) * (float)($conf['monto_asignacion_hijo'] ?? 0);
    
    $stmt_bono = $pdo->prepare("SELECT SUM(monto) as total FROM bonos_extraordinarios 
                                WHERE mes_periodo = ? AND anio_periodo = ? 
                                AND (alcance_destino = ? OR alcance_destino = 'Todos') AND estado = 1");
    $stmt_bono->execute([$mes, $anio, $tipo_persona]);
    $monto_bono = (float)$stmt_bono->fetchColumn();

    $total_bruto = $total_remunerativo + $monto_hijos + $monto_bono;

    // D. Deducciones de Ley
    $monto_jub = 0; $monto_os = 0; $monto_seguro = 0;
    if ($p['id_area'] != 4) {
        $monto_jub = ($total_remunerativo * (float)($conf['porcentaje_jubilacion'] ?? 11)) / 100;
        $monto_os  = ($total_remunerativo * (float)($conf['porcentaje_obra_social'] ?? 3)) / 100;
        $monto_seguro = (float)($conf['monto_seguro_vida'] ?? 0);
    }

    // E. Retenciones de Terceros (Sindicatos / Seguros Voluntarios / Seguros Colectivos)
    $total_entidades = 0;
    $html_entidades = "";
    
    // 1. Identificamos IDs individuales del agente
    $entidades_adheridas = [];
    if (!empty($p['id_entidad_sindicato'])) $entidades_adheridas[] = $p['id_entidad_sindicato'];
    if (!empty($p['id_entidad_seguro'])) $entidades_adheridas[] = $p['id_entidad_seguro'];

    // 2. Buscamos entidades individuales + las que afectan a "Todos"
    $query_entidades = "SELECT nombre_entidad, porcentaje_retencion, tipo_entidad 
                        FROM entidades_pagos_terceros 
                        WHERE estado = 1 AND (categoria_afectada = 'Todos' ";
    
    // Si tiene adheridas, las sumamos al OR
    if (!empty($entidades_adheridas)) {
        $placeholders = str_repeat('?,', count($entidades_adheridas) - 1) . '?';
        $query_entidades .= " OR id_entidad IN ($placeholders)";
    }
    $query_entidades .= ")";

    $stmt_e = $pdo->prepare($query_entidades);
    // Ejecutamos pasando solo los IDs si existen
    $stmt_e->execute(!empty($entidades_adheridas) ? $entidades_adheridas : []);
    $res_e = $stmt_e->fetchAll(PDO::FETCH_ASSOC);

    if ($res_e) {
        $html_entidades .= "<div class='mt-3 mb-1 small fw-bold text-muted text-uppercase'>Entidades y Seguros</div>";
        foreach ($res_e as $ent) {
            // Calculamos sobre el Total Remunerativo
            $monto_e = ($total_remunerativo * (float)$ent['porcentaje_retencion']) / 100;
            $total_entidades += $monto_e;
            
            $html_entidades .= "<div class='monto-row ps-2'>
                                    <span class='small'>• {$ent['nombre_entidad']} ({$ent['porcentaje_retencion']}%)</span> 
                                    <span class='text-danger small'>- $ ".number_format($monto_e,2,',','.')."</span>
                                </div>";
        }
    }

    // F. Retenciones Judiciales (Desglose por Expediente)
    $total_judiciales = 0;
    $html_judiciales = "";
    $stmt_j = $pdo->prepare("SELECT beneficiario_nombre, nro_expediente, tipo_calculo, valor 
                             FROM retenciones_judiciales 
                             WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
    $stmt_j->execute([$id_persona, $tipo_persona]);
    $res_j = $stmt_j->fetchAll(PDO::FETCH_ASSOC);

    if ($res_j) {
        $html_judiciales .= "<div class='mt-3 mb-1 small fw-bold text-muted text-uppercase'>Retenciones Judiciales</div>";
        foreach($res_j as $rj) {
            $monto_j = ($rj['tipo_calculo'] === 'Porcentaje') ? ($total_bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
            $total_judiciales += $monto_j;
            $html_judiciales .= "<div class='monto-row ps-2'>
                                    <span class='small text-truncate' style='max-width:250px;' title='Exp: {$rj['nro_expediente']}'>• Exp: {$rj['nro_expediente']} - {$rj['beneficiario_nombre']}</span> 
                                    <span class='text-danger small'>- $ ".number_format($monto_j,2,',','.')."</span>
                                 </div>";
        }
    }

    $neto = $total_bruto - ($monto_jub + $monto_os + $monto_seguro) - $total_entidades - $total_judiciales;

    // --- CONSTRUCCIÓN DE HTML PARA LA INTERFAZ ---
    
    $html_haberes = "
        <div class='monto-row'><span>$lbl_basico</span> <strong>$ ".number_format($basico,2,',','.')."</strong></div>
        <div class='monto-row'><span>Antigüedad ($anios_ant años)</span> <strong>$ ".number_format($monto_ant,2,',','.')."</strong></div>
        <div class='monto-row'><span>Zona Patagónica (40%)</span> <strong>$ ".number_format($monto_zona,2,',','.')."</strong></div>";
    
    if($monto_pres > 0) {
        $html_haberes .= "<div class='monto-row'><span>Presentismo</span> <strong>$ ".number_format($monto_pres,2,',','.')."</strong></div>";
    }

    $html_haberes .= "
        <div class='monto-row fw-bold text-dark border-top pt-2 mt-2'><span>Total Remunerativo</span> <span>$ ".number_format($total_remunerativo,2,',','.')."</span></div>
        <div class='monto-row text-success small'><span>Asignación Familiar</span> <span>$ ".number_format($monto_hijos,2,',','.')."</span></div>";

    if($monto_bono > 0) {
        $html_haberes .= "<div class='monto-row text-primary small'><span>Bonos Extraordinarios</span> <span>$ ".number_format($monto_bono,2,',','.')."</span></div>";
    }

    // Sección de Descuentos
    $html_descuentos = "
        <div class='monto-row'><span>Jubilación (11%)</span> <span class='text-danger'>- $ ".number_format($monto_jub,2,',','.')."</span></div>
        <div class='monto-row'><span>Obra Social (3%)</span> <span class='text-danger'>- $ ".number_format($monto_os,2,',','.')."</span></div>
        <div class='monto-row'><span>Seguro de Vida Oblig.</span> <span class='text-danger'>- $ ".number_format($monto_seguro,2,',','.')."</span></div>";

    // Agregar desgloses dinámicos
    $html_descuentos .= $html_entidades;
    $html_descuentos .= $html_judiciales;

    echo json_encode([
        "success" => true,
        "html_haberes" => $html_haberes,
        "html_descuentos" => $html_descuentos,
        "neto_formateado" => "$ " . number_format($neto, 2, ',', '.')
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}