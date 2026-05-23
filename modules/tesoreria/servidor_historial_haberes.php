<?php
require 'conexion.php';

// Parámetros de DataTables
$requestData = $_REQUEST;

// 1. Captura de Filtros (Desde el ABM o desde los inputs manuales)
$id_profe_filtro = isset($requestData['id_filtro_profe']) ? (int)$requestData['id_filtro_profe'] : 0;
$dni_filtro      = isset($requestData['dni']) ? trim($requestData['dni']) : '';
$mes_filtro      = isset($requestData['mes']) ? (int)$requestData['mes'] : 0;
$anio_filtro     = isset($requestData['anio']) ? (int)$requestData['anio'] : 0;
$estado_filtro   = isset($requestData['estado']) ? trim($requestData['estado']) : '';

$columns = array(
    0 => 'l.id_liquidacion',
    1 => 'l.fecha_pago',
    2 => 'p.apellido',
    3 => 'l.mes_liquidado',
    4 => 'l.monto_neto',
    5 => 'l.estado'
);

// 2. Consulta base con JOIN
$sql = "SELECT l.*, p.nombre, p.apellido, p.dni FROM liquidaciones_haberes l 
        JOIN profesores p ON l.id_profesor = p.id_profesor WHERE 1=1";

// --- APLICACIÓN DE FILTROS LÓGICOS ---

// Si viene ID desde ABM, tiene prioridad
if ($id_profe_filtro > 0) {
    $sql .= " AND l.id_profesor = $id_profe_filtro";
}

// Si se ingresó un DNI manual
if (!empty($dni_filtro)) {
    $sql .= " AND p.dni = '$dni_filtro'";
}

// Filtros de Período
if ($mes_filtro > 0) {
    $sql .= " AND l.mes_liquidado = $mes_filtro";
}
if ($anio_filtro > 0) {
    $sql .= " AND l.anio_liquidado = $anio_filtro";
}

// Filtro de Estado (Pagado/Anulado)
if (!empty($estado_filtro)) {
    $sql .= " AND l.estado = '$estado_filtro'";
}

// Filtro de Búsqueda General (Cajón de búsqueda de DataTable)
if (!empty($requestData['search']['value'])) {
    $search = $requestData['search']['value'];
    $sql .= " AND (p.apellido LIKE '%$search%' 
                OR p.nombre LIKE '%$search%' 
                OR p.dni LIKE '%$search%' 
                OR l.id_liquidacion LIKE '%$search%')";
}

// 3. Obtener totales para la paginación
$totalData = $pdo->query("SELECT COUNT(*) FROM liquidaciones_haberes")->fetchColumn();

// Calculamos cuántos registros quedaron tras los filtros
$sql_count = str_replace("l.*, p.nombre, p.apellido, p.dni", "COUNT(*)", $sql);
$totalFiltered = $pdo->query($sql_count)->fetchColumn();

// 4. Orden y Paginación
$sql .= " ORDER BY " . $columns[$requestData['order'][0]['column']] . " " . $requestData['order'][0]['dir'];
$sql .= " LIMIT " . $requestData['start'] . " ," . $requestData['length'] . " ";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$data = array();
$meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nestedData = array();
    $nestedData['id_liquidacion'] = $row["id_liquidacion"];
    $nestedData['fecha_pago'] = date('d/m/Y H:i', strtotime($row["fecha_pago"]));
    $nestedData['docente'] = "<strong>" . $row["apellido"] . ", " . $row["nombre"] . "</strong><br><small class='text-muted'>DNI: ".$row['dni']."</small>";
    $nestedData['periodo'] = $meses[$row["mes_liquidado"]] . " / " . $row["anio_liquidado"];
    $nestedData['monto_neto'] = "$ " . number_format($row["monto_neto"], 2, ',', '.');
    
    $badge = $row['estado'] == 'Pagado' ? 'success' : 'danger';
    $nestedData['estado'] = "<span class='badge bg-$badge'>".$row['estado']."</span>";

    $btnAnular = ($row['estado'] == 'Pagado') ? 
        "<button onclick='anularLiquidacion({$row['id_liquidacion']})' class='btn btn-sm btn-outline-danger' title='Anular Pago'><i class='fas fa-trash'></i></button>" : "";

    $nestedData['acciones'] = "
        <div class='btn-group'>
            <a href='generar_recibo_fpdf.php?id={$row['id_liquidacion']}&reprint=1' target='_blank' class='btn btn-sm btn-outline-primary' title='Reimprimir'><i class='fas fa-print'></i></a>
            $btnAnular
        </div>";
    
    $data[] = $nestedData;
}

$json_data = array(
    "draw"            => intval($requestData['draw']),
    "recordsTotal"    => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
);

echo json_encode($json_data);