<?php
/**
 * SERVERSIDE: OBTENER PENDIENTES DE LIQUIDACIÓN (CON TOKEN DE SEGURIDAD)
 */
error_reporting(E_ALL);
ini_set('display_errors', 0); 
session_start();
require_once __DIR__ . '/../../core/conexion.php';
require_once __DIR__ . '/../../core/funciones.php'; // <--- IMPORTANTE: Aquí deben estar encriptar_url() y la URL_KEY

header('Content-Type: application/json');

try {
    if (isset($_POST['bloquear_carga']) && $_POST['bloquear_carga'] === 'true') {
        echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
        exit;
    }

    $draw   = $_POST['draw'] ?? 1;
    $start  = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $tipo_full = $_POST['tipo'] ?? '';

    if (empty($tipo_full)) {
        echo json_encode(["draw" => (int)$draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
        exit;
    }

    $partes = explode('_', $tipo_full);
    $prefijo = $partes[0]; 
    $es_masivo = (isset($partes[1]) && $partes[1] === 'Masivo');

    $mes   = (int)($_POST['mes'] ?? date('n'));
    $anio  = (int)($_POST['anio'] ?? date('Y'));
    
    $f_apellido = trim($_POST['apellido'] ?? '');
    $f_nombre   = trim($_POST['nombre'] ?? '');
    $f_dni      = trim($_POST['dni'] ?? '');

    $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $conf = $stmt_conf->fetch(PDO::FETCH_ASSOC) ?: [];

    $tabla = ($prefijo === 'Profesor') ? 'profesores' : 'personal_staff';
    $id_col = ($prefijo === 'Profesor') ? 'id_profesor' : 'id_staff';
    $tipo_persona_enum = ($prefijo === 'Profesor') ? 'Profesor' : 'Staff';

    // Condición de Banco (CBU)
    if ($es_masivo) {
        $banco_cond = " AND p.pago_banco = 1 AND p.cbu IS NOT NULL AND LENGTH(p.cbu) = 22";
    } else {
        $banco_cond = " AND (p.pago_banco = 0 OR p.cbu IS NULL OR p.cbu = '' OR LENGTH(p.cbu) < 22)";
    }
    
    // Filtro por Área según prefijo
    $area_cond = "";
    if ($prefijo === 'Monotributo') {
        $area_cond = " AND p.id_area = 4";
    } elseif ($prefijo === 'Staff') {
        $area_cond = " AND p.id_area IN (2, 3)";
    } elseif ($prefijo === 'Profesor') {
        $area_cond = " AND p.id_area IN (5, 6)";
    }

    $search_cond = "";
    $params = [':tipo_p' => $tipo_persona_enum, ':mes' => $mes, ':anio' => $anio];

    if (!empty($f_apellido)) { $search_cond .= " AND p.apellido LIKE :f_ape"; $params[':f_ape'] = "%$f_apellido%"; }
    if (!empty($f_nombre)) { $search_cond .= " AND p.nombre LIKE :f_nom"; $params[':f_nom'] = "%$f_nombre%"; }
    if (!empty($f_dni)) { $search_cond .= " AND p.dni LIKE :f_dni"; $params[':f_dni'] = "%$f_dni%"; }

    $base_query = "FROM $tabla p 
                INNER JOIN areas a ON p.id_area = a.id_area
                WHERE p.activo = 1 
                $banco_cond 
                $area_cond
                $search_cond
                AND NOT EXISTS (
                    SELECT 1 FROM liquidaciones_haberes lh 
                    WHERE lh.id_persona = p.$id_col 
                    AND lh.tipo_persona = :tipo_p 
                    AND lh.mes_liquidado = :mes 
                    AND lh.anio_liquidado = :anio 
                    AND lh.estado IN ('Pendiente', 'Procesado')
                )";

    $stmt_count = $pdo->prepare("SELECT COUNT(*) $base_query");
    $stmt_count->execute($params);
    $totalCount = (int)$stmt_count->fetchColumn();

    $sql_data = "SELECT p.*, a.sueldo_base_area, a.nombre_area $base_query ORDER BY p.apellido ASC LIMIT $start, $length";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute($params);
    $agentes = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    $dataset = [];
    foreach ($agentes as $p) {
        $id_agente_actual = $p[$id_col];
        
        // --- MOTOR DE CÁLCULO SIMPLIFICADO PARA VISTA PREVIA ---
        if ($prefijo === 'Profesor') {
            $stmt_h = $pdo->prepare("SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = ?");
            $stmt_h->execute([$id_agente_actual]);
            $horas = (float)$stmt_h->fetchColumn();
            $basico = $horas * (float)($conf['valor_hora_catedra'] ?? 0);
        } else {
            $basico = (float)($p['sueldo_base_area'] ?? 0);
        }

        $monto_ant = 0; $monto_zona = 0; $monto_pres = 0;
        if ($prefijo !== 'Monotributo') {
            if (!empty($p['fecha_ingreso'])) {
                $anios_ant = (new DateTime())->diff(new DateTime($p['fecha_ingreso']))->y;
                $monto_ant = ($basico * ((float)($conf['porcentaje_antiguedad_anual'] ?? 2) * $anios_ant)) / 100;
            }
            $monto_zona = ($basico * (float)($conf['porcentaje_zona_patagonica'] ?? 40)) / 100;
            if (($p['cobra_presentismo'] ?? 0) == 1) {
                $monto_pres = (($basico + $monto_ant) * (float)($conf['porcentaje_presentismo'] ?? 10)) / 100;
            }
        }

        $total_remunerativo = $basico + $monto_ant + $monto_zona + $monto_pres;
        $monto_hijos = (int)($p['hijos_verificados'] ?? 0) * (float)($conf['monto_asignacion_hijo'] ?? 0);
        
        $stmt_bono = $pdo->prepare("SELECT SUM(monto) FROM bonos_extraordinarios WHERE mes_periodo = ? AND anio_periodo = ? AND (alcance_destino = ? OR alcance_destino = 'Todos') AND estado = 1");
        $stmt_bono->execute([$mes, $anio, $prefijo]);
        $monto_bono = (float)$stmt_bono->fetchColumn();

        $bruto = $total_remunerativo + $monto_hijos + $monto_bono;

        $ret_ley = 0;
        if ($prefijo !== 'Monotributo') {
            $ret_ley = ($total_remunerativo * ((float)($conf['porcentaje_jubilacion'] ?? 11) + (float)($conf['porcentaje_obra_social'] ?? 3)) / 100) + (float)($conf['monto_seguro_vida'] ?? 0);
        }

        $ret_sindicato = 0;
        if (!empty($p['id_entidad_sindicato'])) {
            $stmt_sind = $pdo->prepare("SELECT porcentaje_retencion FROM entidades_pagos_terceros WHERE id_entidad = ?");
            $stmt_sind->execute([$p['id_entidad_sindicato']]);
            $porc_sind = (float)$stmt_sind->fetchColumn();
            $ret_sindicato = ($total_remunerativo * $porc_sind / 100);
        }

        $ret_jud = 0;
        $stmt_j = $pdo->prepare("SELECT tipo_calculo, valor FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
        $stmt_j->execute([$id_agente_actual, $tipo_persona_enum]);
        foreach($stmt_j->fetchAll(PDO::FETCH_ASSOC) as $rj) {
            $ret_jud += ($rj['tipo_calculo'] === 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
        }

        $total_descuentos = $ret_ley + $ret_sindicato + $ret_jud;
        $neto = $bruto - $total_descuentos;

        // --- LÓGICA DE SEGURIDAD: GENERACIÓN DE TOKEN PARA URL AMIGABLE ---
        // Empaquetamos: ID_Agente | Tipo_Persona | Mes_Filtro | Año_Filtro
        $raw_data = $id_agente_actual . "|" . $tipo_persona_enum . "|" . $mes . "|" . $anio;
        $token_pago = encriptar_url($raw_data);

        $dataset[] = [
            "checkbox"    => '<input type="checkbox" class="checkItem form-check-input" value="'.$id_agente_actual.'" data-neto="'.$neto.'">',
            "agente"      => '<strong>'.$p['apellido'].', '.$p['nombre'].'</strong><br><small class="text-muted">'.$p['nombre_area'].' ('.$p['dni'].')</small>',
            "bruto"       => '$ '.number_format($bruto, 2, ',', '.'),
            "bonos"       => '$ '.number_format($monto_bono, 2, ',', '.'),
            "adicionales" => '<div class="small text-muted">Ant: $'.number_format($monto_ant,0).'<br>Zona: $'.number_format($monto_zona,0).'</div>',
            "asig"        => '$ '.number_format($monto_hijos, 2, ',', '.'),
            "ret"         => '<span title="Ley+Sind+Otros">$ '.number_format($total_descuentos, 2, ',', '.').'</span>',
            "neto"        => '<span class="text-success fw-bold">$ '.number_format($neto, 2, ',', '.').'</span>',
            "id_agente"   => $id_agente_actual,
            "tipo_agente" => $tipo_persona_enum,
            "token_pago"  => $token_pago, // Enviamos el token encriptado al JS
            "estado_bna"  => $es_masivo ? '<span class="badge bg-success">LOTE BNA</span>' : '<span class="badge bg-warning text-dark">MANUAL</span>'
        ];
    }

    echo json_encode(["draw" => (int)$draw, "recordsTotal" => (int)$totalCount, "recordsFiltered" => (int)$totalCount, "data" => $dataset]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}