<?php
// calificaciones_serverside.php corregido

session_start();
require 'conexion.php'; 

header('Content-Type: application/json');

if (!isset($pdo)) {
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']));
}

$dni_buscado = isset($_POST['dni']) ? trim($_POST['dni']) : '';

if (empty($dni_buscado)) {
    die(json_encode(['success' => true, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'alumno' => null]));
}

// 1. Obtener datos del alumno
$sql_alumno = "SELECT id_alumno, nombre, apellido, dni, activo FROM alumnos WHERE dni = :dni";
$stmt_alumno = $pdo->prepare($sql_alumno);
$stmt_alumno->bindParam(':dni', $dni_buscado, PDO::PARAM_INT);
$stmt_alumno->execute();
$alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die(json_encode(['success' => false, 'message' => 'Alumno no encontrado.']));
}

$id_alumno = $alumno['id_alumno'];
$es_activo = ($alumno['activo'] == 1);

// 2. Definición de las columnas para DataTables
$columns = array(
    array('db' => 'r.nombre_carrera', 'dt' => 0, 'field' => 'nombre_carrera'),
    array('db' => 'e.nombre_espacio', 'dt' => 1, 'field' => 'nombre_espacio'),
    array('db' => 'e.anio_cursada',   'dt' => 2, 'field' => 'anio_cursada'),
    array('db' => 'c.fecha_registro', 'dt' => 3, 'field' => 'fecha_registro'),
    array('db' => 'c.nota_final',     'dt' => 4, 'field' => 'nota_final'),
    array('db' => 'c.condicion',      'dt' => 5, 'field' => 'condicion', 'formatter' => function($d, $row) {
        // --- CORRECCIÓN AQUÍ: Leemos el texto directo de la base de datos ---
        $condicion = strtoupper($d); 
        $clase_badge = 'badge-secondary';

        if ($condicion === 'APROBADO') {
            $clase_badge = 'badge-success';
        } elseif ($condicion === 'REGULAR') {
            $clase_badge = 'badge-warning';
        } elseif ($condicion === 'DESAPROBADO') {
            $clase_badge = 'badge-danger';
        }
        
        return '<span class="badge ' . $clase_badge . '">' . $condicion . '</span>';
    }),
    array('db' => 'c.id_calificacion', 'dt' => 6, 'field' => 'id_calificacion', 'formatter' => function($d, $row) use ($es_activo) {
        if (!$es_activo) {
            return '<span class="text-muted small"><i class="fas fa-lock"></i> Bloqueado</span>';
        }

        // De acuerdo al SELECT: c.id_calificacion(0), c.nota_final(1), e.nombre_espacio(3)
        $nota_actual = $row[1];
        $nombre_espacio = htmlspecialchars($row[3]);
        
        $botones = '<button onclick="abrirModalEdicion(' . $d . ', \'' . $nota_actual . '\', \'' . $nombre_espacio . '\')" class="btn btn-sm btn-warning mr-1" title="Modificar Nota"><i class="fa fa-edit"></i></button>';
        $botones .= '<button onclick="eliminarNota(' . $d . ', \'' . $nombre_espacio . '\')" class="btn btn-sm btn-danger" title="Eliminar Nota"><i class="fa fa-trash"></i></button>';
        
        return $botones;
    }),
);

// Consulta base SQL (El orden de los campos aquí es vital para los formatters)
$sql = "SELECT 
            c.id_calificacion, 
            c.nota_final,
            c.fecha_registro,
            e.nombre_espacio,
            e.anio_cursada,
            r.nombre_carrera,
            c.condicion
        FROM calificaciones c
        INNER JOIN espacios_curriculares e ON c.id_espacio = e.id_espacio
        INNER JOIN carreras r ON e.id_carrera = r.id_carrera";

// ... (Resto del código de paginación y búsqueda se mantiene igual) ...

$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search_value = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
$order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 0;
$order_dir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'asc';
$order_by_field = $columns[$order_column_index]['db'];

$search_query = "";
$search_params = array();
if (!empty($search_value)) {
    $search_terms = array();
    $param_counter = 0;
    foreach ($columns as $column) {
        if ($column['dt'] < 5) {
            $param_name = ':search' . $param_counter;
            $search_terms[] = $column['db'] . " LIKE " . $param_name; 
            $search_params[$param_name] = '%' . $search_value . '%'; 
            $param_counter++;
        }
    }
    if (!empty($search_terms)) $search_query = " AND (" . implode(" OR ", $search_terms) . ")";
}

$where_clause = " WHERE c.id_alumno = :id_alumno" . $search_query;

$sql_total = "SELECT COUNT(c.id_calificacion) FROM calificaciones c WHERE c.id_alumno = :id_alumno";
$stmt_total = $pdo->prepare($sql_total);
$stmt_total->execute([':id_alumno' => $id_alumno]);
$recordsTotal = $stmt_total->fetchColumn();
$recordsFiltered = $recordsTotal;

if (!empty($search_query)) {
    $sql_count_filtered = "SELECT COUNT(c.id_calificacion) FROM calificaciones c INNER JOIN espacios_curriculares e ON c.id_espacio = e.id_espacio INNER JOIN carreras r ON e.id_carrera = r.id_carrera" . $where_clause;
    $stmt_filtered_total = $pdo->prepare($sql_count_filtered);
    $stmt_filtered_total->bindValue(':id_alumno', $id_alumno, PDO::PARAM_INT);
    foreach ($search_params as $p_name => $p_value) $stmt_filtered_total->bindValue($p_name, $p_value, PDO::PARAM_STR);
    $stmt_filtered_total->execute();
    $recordsFiltered = $stmt_filtered_total->fetchColumn();
}

$sql_filter = $sql . $where_clause . " ORDER BY " . $order_by_field . " " . $order_dir . " LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql_filter);
$stmt->bindValue(':limit', $length, PDO::PARAM_INT);
$stmt->bindValue(':offset', $start, PDO::PARAM_INT);
$stmt->bindValue(':id_alumno', $id_alumno, PDO::PARAM_INT);
foreach ($search_params as $p_name => $p_value) $stmt->bindValue($p_name, $p_value, PDO::PARAM_STR);
$stmt->execute();

$data = array();
// Mapeo corregido según el SELECT anterior
// Carrera(5), Espacio(3), Año(4), Fecha(2), Nota(1), Condicion(6), ID(0)
$sql_index_map = [0=>5, 1=>3, 2=>4, 3=>2, 4=>1, 5=>6, 6=>0];

while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $row_data = array();
    foreach ($columns as $column) {
        $col_dt_index = $column['dt']; 
        $sql_data_index = $sql_index_map[$col_dt_index];
        $val = $row[$sql_data_index] ?? '';
        
        if (isset($column['formatter'])) {
            $row_data[] = call_user_func($column['formatter'], $val, $row);
        } else {
            $row_data[] = htmlspecialchars($val);
        }
    }
    $data[] = $row_data;
}

echo json_encode(array(
    "success" => true,
    "draw" => $draw,
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsFiltered,
    "data" => $data,
    "alumno" => $alumno
));