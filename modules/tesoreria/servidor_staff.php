<?php
require 'conexion.php';

// Columnas para ordenamiento
$columns = ['s.id_staff', 's.dni', 's.apellido', 'a.nombre_area', 's.activo'];

// Consulta con subconsulta para el conteo de embargos
$query = "SELECT s.*, a.nombre_area, 
          (SELECT COUNT(*) FROM retenciones_judiciales r 
           WHERE r.id_persona = s.id_staff AND r.tipo_persona = 'Staff' AND r.activo = 1) as total_retenciones
          FROM personal_staff s 
          LEFT JOIN areas a ON s.id_area = a.id_area 
          WHERE 1=1";

// Buscador global
if (!empty($_POST['search']['value'])) {
    $search = $_POST['search']['value'];
    $query .= " AND (s.dni LIKE '%$search%' OR s.apellido LIKE '%$search%' OR s.nombre LIKE '%$search%')";
}

// Totales
$totalData = $pdo->query("SELECT COUNT(*) FROM personal_staff")->fetchColumn();
$totalFiltered = $totalData; // Simplificado para este ejemplo

// Orden
if (isset($_POST['order'])) {
    $query .= " ORDER BY " . $columns[$_POST['order'][0]['column']] . " " . $_POST['order'][0]['dir'];
} else {
    $query .= " ORDER BY s.apellido ASC";
}

// Paginación
if (isset($_POST['start']) && $_POST['length'] != -1) {
    $query .= " LIMIT " . intval($_POST['start']) . " ," . intval($_POST['length']);
}

$stmt = $pdo->prepare($query);
$stmt->execute();
$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nestedData = [];
    
    // Mapeo exacto para los índices de tu JS:
    $nestedData[0]  = $row['id_staff'];          // data[0]
    $nestedData[1]  = $row['dni'];               // data[1]
    $nestedData[2]  = $row['apellido'] . ", " . $row['nombre']; // data[2]
    $nestedData[3]  = $row['nombre_area'] ?? 'Sin Área';        // data[3]
    $nestedData[4]  = $row['activo'];            // data[4]
    $nestedData[5]  = $row['cbu'];               // data[5]
    $nestedData[6]  = $row['banco'];             // data[6]
    $nestedData[7]  = $row['cantidad_hijos'];    // data[7]
    $nestedData[8]  = $row['hijos_verificados']; // data[8]
    $nestedData[9]  = $row['total_retenciones']; // data[9]
    $nestedData[10] = $row['id_area'];           // data[10]

    $data[] = $nestedData;
}

echo json_encode([
    "draw"            => intval($_POST['draw'] ?? 0),
    "recordsTotal"    => intval($totalData),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
]);