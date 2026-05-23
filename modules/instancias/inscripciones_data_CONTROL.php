<?php
require_once '../../core/conexion.php';

// Capturamos parámetros de DataTables
$draw = $_POST['draw'] ?? 1;
$row = $_POST['start'] ?? 0;
$rowperpage = $_POST['length'] ?? 10; 
$columnIndex = $_POST['order'][0]['column'] ?? 1; 
$columnSortOrder = $_POST['order'][0]['dir'] ?? 'desc'; 
$searchValue = $_POST['search']['value'] ?? ''; 
$id_mesa = $_POST['id_mesa'] ?? '';

// Construir búsqueda
$searchQuery = " ";
if($searchValue != ''){
    $searchQuery = " AND (a.nombre LIKE :n OR a.apellido LIKE :ap OR a.dni LIKE :d OR e.nombre_espacio LIKE :m) ";
}
if($id_mesa != ''){
    $searchQuery .= " AND m.id_mesa = :id_mesa ";
}

// 1. Contar total sin filtros
$totalRecords = $pdo->query("SELECT COUNT(*) FROM inscripciones_examen")->fetchColumn();

// 2. Contar con filtros
$sql_f = "SELECT COUNT(*) FROM inscripciones_examen ie 
          JOIN alumnos a ON ie.id_alumno = a.id_alumno 
          JOIN mesas_examenes m ON ie.id_mesa = m.id_mesa 
          JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio 
          WHERE 1 ".$searchQuery;
$stmt_f = $pdo->prepare($sql_f);
if($searchValue != ''){
    $stmt_f->bindValue(':n', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_f->bindValue(':ap', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_f->bindValue(':d', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_f->bindValue(':m', '%'.$searchValue.'%', PDO::PARAM_STR);
}
if($id_mesa != '') $stmt_f->bindValue(':id_mesa', $id_mesa, PDO::PARAM_INT);
$stmt_f->execute();
$totalRecordwithFilter = $stmt_f->fetchColumn();

// 3. Obtener los datos
$sql_d = "SELECT ie.id_inscripcion, ie.fecha_inscripcion, a.dni, a.nombre, a.apellido, 
                 c.nombre_carrera, e.nombre_espacio, ie.condicion
          FROM inscripciones_examen ie
          JOIN alumnos a ON ie.id_alumno = a.id_alumno
          JOIN mesas_examenes m ON ie.id_mesa = m.id_mesa
          JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
          JOIN carreras c ON m.id_carrera = c.id_carrera
          WHERE 1 ".$searchQuery." 
          ORDER BY ie.fecha_inscripcion ".$columnSortOrder." 
          LIMIT :limit, :offset";

$stmt_d = $pdo->prepare($sql_d);
if($searchValue != ''){
    $stmt_d->bindValue(':n', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_d->bindValue(':ap', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_d->bindValue(':d', '%'.$searchValue.'%', PDO::PARAM_STR);
    $stmt_d->bindValue(':m', '%'.$searchValue.'%', PDO::PARAM_STR);
}
if($id_mesa != '') $stmt_d->bindValue(':id_mesa', $id_mesa, PDO::PARAM_INT);
$stmt_d->bindValue(':limit', (int)$row, PDO::PARAM_INT);
$stmt_d->bindValue(':offset', (int)$rowperpage, PDO::PARAM_INT);
$stmt_d->execute();

$aaData = [];
while ($r = $stmt_d->fetch(PDO::FETCH_ASSOC)) {
    $aaData[] = [
        $r['id_inscripcion'],
        date('d/m/Y H:i', strtotime($r['fecha_inscripcion'])),
        $r['dni'],
        $r['apellido'] . ", " . $r['nombre'],
        $r['nombre_carrera'],
        $r['nombre_espacio'],
        $r['condicion']
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "iTotalRecords" => $totalRecords,
    "iTotalDisplayRecords" => $totalRecordwithFilter,
    "aaData" => $aaData
]);