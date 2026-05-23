<?php
session_start();
// 1. Ajuste de ruta para llegar al core (subimos 2 niveles)
require_once '../../core/conexion.php';

// Verificación de seguridad básica para el AJAX
if (!isset($_SESSION['rol'])) {
    exit(json_encode(['error' => 'No autorizado']));
}

// Parámetros de DataTables
$draw            = $_POST['draw'] ?? 0;
$row             = $_POST['start'] ?? 0;
$rowperpage      = $_POST['length'] ?? 10; 
$columnIndex     = $_POST['order'][0]['column'] ?? 0; 
$columnSortOrder = $_POST['order'][0]['dir'] ?? 'desc'; 
$searchValue     = $_POST['search']['value'] ?? ''; 

// --- CORRECCIÓN CRÍTICA: TRADUCCIÓN DE UUID A ID REAL ---
$uuid_mesa = $_POST['id_mesa'] ?? '';
$id_mesa_real = null;

if ($uuid_mesa != '') {
    // Buscamos el ID numérico que corresponde a ese UUID
    $stmt_check = $pdo->prepare("SELECT id_mesa FROM mesas_examenes WHERE uuid_mesa = ? LIMIT 1");
    $stmt_check->execute([$uuid_mesa]);
    $id_mesa_real = $stmt_check->fetchColumn();
}
// -------------------------------------------------------

// Construcción de la consulta de búsqueda
$searchQuery = " ";
if($searchValue != ''){
    $searchQuery = " AND (a.nombre LIKE :nombre OR a.apellido LIKE :apellido OR a.dni LIKE :dni OR e.nombre_espacio LIKE :espacio OR c.nombre_carrera LIKE :carrera) ";
}

// Si se encontró un ID real para la mesa seleccionada, lo agregamos al filtro
if($id_mesa_real){
    $searchQuery .= " AND i.id_mesa = :id_mesa_real ";
}

try {
    // 1. TOTAL DE REGISTROS (Solo alumnos activos y MESAS ABIERTAS)
    $sql_total = "SELECT COUNT(*) AS allcount 
                  FROM inscripciones_examen i 
                  JOIN alumnos a ON i.id_alumno = a.id_alumno 
                  JOIN mesas_examenes m ON i.id_mesa = m.id_mesa
                  WHERE a.activo = 1 AND m.estado = 'Abierta'";
    $stmt_total = $pdo->prepare($sql_total);
    $stmt_total->execute();
    $totalRecords = $stmt_total->fetchColumn();

    // 2. TOTAL DE REGISTROS CON FILTRO
    $sql_filter = "SELECT COUNT(*) AS allcount 
                   FROM inscripciones_examen i
                   JOIN alumnos a ON i.id_alumno = a.id_alumno
                   JOIN mesas_examenes m ON i.id_mesa = m.id_mesa
                   JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                   JOIN carreras c ON m.id_carrera = c.id_carrera
                   WHERE a.activo = 1 AND m.estado = 'Abierta' ".$searchQuery; 
    
    $stmt_filter = $pdo->prepare($sql_filter);

    if($searchValue != ''){
        $stmt_filter->bindValue(':nombre', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_filter->bindValue(':apellido', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_filter->bindValue(':dni', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_filter->bindValue(':espacio', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_filter->bindValue(':carrera', '%'.$searchValue.'%', PDO::PARAM_STR);
    }
    
    if($id_mesa_real){
        $stmt_filter->bindValue(':id_mesa_real', $id_mesa_real, PDO::PARAM_INT);
    }
    
    $stmt_filter->execute();
    $totalRecordwithFilter = $stmt_filter->fetchColumn();

    // 3. OBTENER LOS DATOS
    $columnMapping = [
        1 => 'i.fecha_inscripcion',
        2 => 'a.dni',
        3 => 'a.apellido',
        4 => 'c.nombre_carrera',
        5 => 'e.nombre_espacio',
        6 => 'i.condicion'
    ];
    $orderBy = $columnMapping[$columnIndex] ?? 'i.id_inscripcion';

    $sql_data = "SELECT i.id_inscripcion, i.fecha_inscripcion, i.condicion, a.dni, a.nombre, a.apellido, 
                        c.nombre_carrera, e.nombre_espacio, m.llamado
                 FROM inscripciones_examen i
                 JOIN alumnos a ON i.id_alumno = a.id_alumno
                 JOIN mesas_examenes m ON i.id_mesa = m.id_mesa
                 JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                 JOIN carreras c ON m.id_carrera = c.id_carrera
                 WHERE a.activo = 1 AND m.estado = 'Abierta' ".$searchQuery." 
                 ORDER BY ".$orderBy." ".$columnSortOrder." 
                 LIMIT :limit OFFSET :offset";

    $stmt_data = $pdo->prepare($sql_data);

    if($searchValue != ''){
        $stmt_data->bindValue(':nombre', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_data->bindValue(':apellido', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_data->bindValue(':dni', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_data->bindValue(':espacio', '%'.$searchValue.'%', PDO::PARAM_STR);
        $stmt_data->bindValue(':carrera', '%'.$searchValue.'%', PDO::PARAM_STR);
    }
    
    if($id_mesa_real){
        $stmt_data->bindValue(':id_mesa_real', $id_mesa_real, PDO::PARAM_INT);
    }
    
    $stmt_data->bindValue(':limit', (int)$rowperpage, PDO::PARAM_INT);
    $stmt_data->bindValue(':offset', (int)$row, PDO::PARAM_INT);
    $stmt_data->execute();

    $aaData = array();
    while ($row_data = $stmt_data->fetch(PDO::FETCH_ASSOC)) {
        $nombre_completo = $row_data['apellido'] . ", " . $row_data['nombre'];
        $fecha_insc = date('d/m/Y H:i', strtotime($row_data['fecha_inscripcion']));
        $condicion_texto = strtoupper($row_data['condicion']);

        $aaData[] = array(
            $row_data['id_inscripcion'], 
            $fecha_insc,                 
            $row_data['dni'],            
            $nombre_completo,            
            $row_data['nombre_carrera'], 
            $row_data['nombre_espacio'] . " (" . $row_data['llamado'] . ")", 
            $condicion_texto,            
            null                         
        );
    }

    $response = array(
        "draw" => intval($draw),
        "iTotalRecords" => $totalRecords,
        "iTotalDisplayRecords" => $totalRecordwithFilter,
        "aaData" => $aaData
    );

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error en ServerSide Inscripciones: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}