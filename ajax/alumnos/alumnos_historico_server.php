<?php
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$row = isset($_POST['start']) ? intval($_POST['start']) : 0;
$rowperpage = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = $_POST['search']['value'] ?? '';

// 1. Construcción de la consulta de búsqueda ampliada
$searchQuery = " ";
if($searchValue != ''){
   $searchQuery = " AND (dni LIKE :dni_search 
                          OR nombre LIKE :nombre_search 
                          OR apellido LIKE :apellido_search 
                          OR legajo LIKE :legajo_search
                          OR libro_matriz LIKE :libro_search
                          OR folio_matriz LIKE :folio_search) ";
}

// 2. Total de registros sin filtrar
$stmt = $pdo->prepare("SELECT COUNT(*) AS allcount FROM alumnos");
$stmt->execute();
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['allcount'];

// 3. Total de registros con filtro
$stmt = $pdo->prepare("SELECT COUNT(*) AS allcount FROM alumnos WHERE 1 ".$searchQuery);
if($searchValue != '') {
    $searchTerm = '%'.$searchValue.'%';
    $stmt->bindValue(':dni_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':nombre_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':apellido_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':legajo_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':libro_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':folio_search', $searchTerm, PDO::PARAM_STR);
}
$stmt->execute();
$totalRecordwithFilter = $stmt->fetch(PDO::FETCH_ASSOC)['allcount'];

// 4. Obtener los registros paginados incluyendo uuid_alumno
// AGREGAMOS uuid_alumno al final de la selección
$sql = "SELECT id_alumno, dni, nombre, apellido, activo, legajo, libro_matriz, folio_matriz, 
                pdf_dni, pdf_titulo, pdf_aptitud, fecha_inscripcion, uuid_alumno 
        FROM alumnos 
        WHERE 1 ".$searchQuery." 
        ORDER BY apellido ASC, nombre ASC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

if($searchValue != '') {
    $searchTerm = '%'.$searchValue.'%';
    $stmt->bindValue(':dni_search', $searchTerm, PDO::PARAM_STR);
    $searchTerm_nom = '%'.$searchValue.'%';
    $stmt->bindValue(':nombre_search', $searchTerm_nom, PDO::PARAM_STR);
    $stmt->bindValue(':apellido_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':legajo_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':libro_search', $searchTerm, PDO::PARAM_STR);
    $stmt->bindValue(':folio_search', $searchTerm, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', (int)$rowperpage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$row, PDO::PARAM_INT);
$stmt->execute();

$data = array();
while ($row_data = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // IMPORTANTE: Respetamos las posiciones que el JS del Histórico espera
    $data[] = array(
        $row_data['id_alumno'],                                           // [0]
        $row_data['dni'],                                                 // [1]
        htmlspecialchars($row_data['apellido'].", ".$row_data['nombre']),  // [2]
        $row_data['activo'],                                              // [3]
        $row_data['legajo'],                                              // [4]
        $row_data['libro_matriz'],                                        // [5]
        $row_data['folio_matriz'],                                        // [6]
        $row_data['pdf_dni'],                                             // [7]
        $row_data['pdf_titulo'],                                          // [8]
        $row_data['pdf_aptitud'],                                         // [9]
        $row_data['fecha_inscripcion'],                                   // [10]
        $row_data['uuid_alumno']                                          // [11] -> LLAVE MAESTRA PARA JS
    );
}

// 5. Respuesta en formato JSON
$response = array(
    "draw" => intval($draw),
    "iTotalRecords" => (int)$totalRecords,
    "iTotalDisplayRecords" => (int)$totalRecordwithFilter,
    "aaData" => $data
);

header('Content-Type: application/json');
echo json_encode($response);