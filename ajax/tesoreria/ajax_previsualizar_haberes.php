<?php
require 'conexion.php';
require 'seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

// 1. Captura de parámetros
$id_area_filtro = $_POST['id_area_filtro'] ?? 'TODOS';
$mes            = $_POST['mes_pago'] ?? date('n');
$anio           = $_POST['anio_pago'] ?? date('Y');

// 2. Obtener Configuración de Tesorería
$stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
$config = $stmt_conf->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    die(json_encode(['html' => '<div class="alert alert-danger">Error: No se encontró la configuración de tesorería.</div>', 'total_neto' => '$ 0.00']));
}

$resultados = [];
$total_neto_general = 0;

// Filtro de Área dinámico
$filtro_sql = ($id_area_filtro !== 'TODOS') ? " AND id_area = " . (int)$id_area_filtro : "";

// --- SECCIÓN A: PROFESORES ---
$sql_profes = "SELECT p.*, 
              (SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = p.id_profesor) as total_horas
              FROM profesores p 
              WHERE p.activo = 1 $filtro_sql"; 

$profes = $pdo->query($sql_profes)->fetchAll(PDO::FETCH_ASSOC);

foreach ($profes as $p) {
    $horas_base = (float)($p['total_horas'] ?? 0);
    if ($horas_base <= 0) continue; 

    $sueldo_basico = $horas_base * (float)$config['valor_hora_catedra'];
    $fecha_ing = new DateTime($p['fecha_ingreso']);
    $anios_antig = (new DateTime())->diff($fecha_ing)->y;
    $porcentaje_antig = ((float)$config['porcentaje_antiguedad_anual'] ?? 0) * $anios_antig;
    $monto_antiguedad = ($sueldo_basico * $porcentaje_antig) / 100;
    $monto_hijos = ($p['hijos_verificados'] == 1) ? (int)$p['cantidad_hijos'] * (float)$config['monto_asignacion_hijo'] : 0;

    $total_bruto = $sueldo_basico + $monto_antiguedad + $monto_hijos;
    $jub = ($total_bruto * (float)$config['porcentaje_jubilacion']) / 100;
    $os  = ($total_bruto * (float)$config['porcentaje_obra_social']) / 100;

    $neto = $total_bruto - $jub - $os;
    
    $resultados[] = [
        'id'        => $p['id_profesor'],
        'nombre'    => $p['apellido'] . ", " . $p['nombre'],
        'tipo_ref'  => 'PROFESOR',
        'detalle'   => 'Docente (' . $horas_base . ' hs)',
        'horas'     => $horas_base,
        'valor_h'   => $config['valor_hora_catedra'],
        'bruto'     => $total_bruto,
        'bonif'     => ($monto_antiguedad + $monto_hijos),
        'retencion' => ($jub + $os),
        'neto'      => $neto
    ];
    $total_neto_general += $neto;
}

// --- SECCIÓN B: PERSONAL STAFF ---
$sql_staff = "SELECT s.* FROM personal_staff s WHERE s.activo = 1 $filtro_sql"; 
$staff = $pdo->query($sql_staff)->fetchAll(PDO::FETCH_ASSOC);

foreach ($staff as $s) {
    $sueldo_basico = (float)$s['sueldo_base'];
    if ($sueldo_basico <= 0) continue;

    $fecha_ing = new DateTime($s['fecha_ingreso']);
    $anios_antig = (new DateTime())->diff($fecha_ing)->y;
    $porcentaje_antig = ((float)$config['porcentaje_antiguedad_anual'] ?? 0) * $anios_antig;
    $monto_antiguedad = ($sueldo_basico * $porcentaje_antig) / 100;
    $monto_hijos = ((int)$s['cantidad_hijos'] ?? 0) * (float)$config['monto_asignacion_hijo'];
    
    $total_bruto = $sueldo_basico + $monto_antiguedad + $monto_hijos;
    $jub = ($total_bruto * (float)$config['porcentaje_jubilacion']) / 100;
    $os  = ($total_bruto * (float)$config['porcentaje_obra_social']) / 100;

    $neto = $total_bruto - $jub - $os;

    $resultados[] = [
        'id'        => $s['id_staff'],
        'nombre'    => $s['apellido'] . ", " . $s['nombre'],
        'tipo_ref'  => 'STAFF',
        'detalle'   => 'Personal Staff / Fijo',
        'horas'     => 0,
        'valor_h'   => 0,
        'bruto'     => $total_bruto,
        'bonif'     => ($monto_antiguedad + $monto_hijos),
        'retencion' => ($jub + $os),
        'neto'      => $neto
    ];
    $total_neto_general += $neto;
}

// --- 3. GENERACIÓN DEL HTML (TABLA EDITABLE) ---
if (empty($resultados)) {
    $html = '<div class="alert alert-warning text-center my-4">No se encontró personal activo para esta área.</div>';
} else {
    $html = '<div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-dark">
                        <tr>
                            <th>Personal</th>
                            <th>Monto Base</th>
                            <th width="150">Extras/Bonos (+)</th>
                            <th width="150">Descuentos (-)</th>
                            <th class="text-end">Neto Estimado</th>
                        </tr>
                    </thead>
                    <tbody>';

    foreach ($resultados as $r) {
        $prefix = ($r['tipo_ref'] == 'PROFESOR') ? "p_" : "s_";
        $input_id = $prefix . $r['id'];

        $html .= "<tr>
                    <td>
                        <input type='hidden' name='lista_ids[]' value='{$input_id}'>
                        <input type='hidden' name='tipo_{$input_id}' value='{$r['tipo_ref']}'>
                        <input type='hidden' name='bruto_base_{$input_id}' value='{$r['bruto']}'>
                        <input type='hidden' name='horas_{$input_id}' value='{$r['horas']}'>
                        <input type='hidden' name='valor_h_{$input_id}' value='{$r['valor_h']}'>
                        <input type='hidden' name='bonif_base_{$input_id}' value='{$r['bonif']}'>
                        <input type='hidden' name='retenc_base_{$input_id}' value='{$r['retencion']}'>
                        
                        <span class='fw-bold'>{$r['nombre']}</span><br>
                        <small class='text-muted'>{$r['detalle']}</small>
                    </td>
                    <td class='text-muted'>$ " . number_format($r['neto'], 2, ',', '.') . "</td>
                    <td><input type='number' step='0.01' name='extra_{$input_id}' class='form-control form-control-sm border-primary' value='0'></td>
                    <td><input type='number' step='0.01' name='desc_{$input_id}' class='form-control form-control-sm border-danger' value='0'></td>
                    <td class='text-end fw-bold text-primary'>$ " . number_format($r['neto'], 2, ',', '.') . "</td>
                  </tr>";
    }

    $html .= '</tbody></table></div>';
}

header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'total_neto' => "$ " . number_format($total_neto_general, 2, ',', '.')
]);