<?php
session_start();

require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría', 'Tesoreria']);

if (!isset($_SESSION['loggedin'])) {
    http_response_code(403);
    exit("Acceso denegado.");
}

$table = 'areas';
$primaryKey = 'id_area';

// 3. DEFINICIÓN DE COLUMNAS
$columns = array(
    array( 'db' => 'id_area',         'dt' => 0 ),
    array( 'db' => 'nombre_area',     'dt' => 1 ),
    array( 'db' => 'descripcion',     'dt' => 2 ),
    // Cambiamos el formateador para que use el UUID en los botones
    array( 'db' => 'uuid_area', 'dt' => 3, 'formatter' => function( $d, $row ) {
        $nombre_area = $row['nombre_area']; 
        $uuid = $d; // El valor de 'db' que es uuid_area
        
        $botones = '<div class="btn-group">
                        <a href="' . BASE_URL . 'areas/editar/' . $uuid . '" class="btn btn-sm btn-info text-white">
                            <i class="fas fa-edit"></i>
                        </a>';
        $botones .= '<button onclick="confirmarEliminacion(\'' . $uuid . '\', \'' . addslashes($nombre_area) . '\')" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i>
                    </button>
                    </div>';
        return $botones;
    }),
    array( 'db' => 'sueldo_base_area', 'dt' => 4 ),
    array( 'db' => 'uuid_area',        'dt' => 5 ) // Enviamos el UUID también al índice 5 por seguridad
);

// Variables de DataTables POST
$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

$recordsTotal = $pdo->query("SELECT COUNT({$primaryKey}) FROM {$table}")->fetchColumn();

$where = '';
$params = [];
if (!empty($searchValue)) {
    $where = ' WHERE (nombre_area LIKE ? OR descripcion LIKE ?)';
    $params[] = '%' . $searchValue . '%';
    $params[] = '%' . $searchValue . '%';
}

$order = ' ORDER BY nombre_area ASC';
// ... (mantenemos tu lógica de ordenación dinámica) ...

$limit = " LIMIT {$start}, {$length}";

// IMPORTANTE: Agregamos uuid_area a la consulta SQL
$sql = "SELECT id_area, nombre_area, descripcion, sueldo_base_area, uuid_area FROM {$table} {$where} {$order} {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$recordsFiltered = $recordsTotal;
if (!empty($where)) {
    $stmt_f = $pdo->prepare("SELECT COUNT({$primaryKey}) FROM {$table} {$where}");
    $stmt_f->execute($params);
    $recordsFiltered = $stmt_f->fetchColumn();
}

$output = array(
    "draw"            => $draw,
    "recordsTotal"    => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data"            => array()
);

foreach ($data as $row) {
    $rowData = array();
    foreach ($columns as $column) {
        $db_name = $column['db'];
        // Usamos el nombre de la columna para obtener el valor de $row
        $value = isset($row[$db_name]) ? $row[$db_name] : '';
        
        if (isset($column['formatter'])) {
            $value = call_user_func($column['formatter'], $value, $row);
        }
        $rowData[] = $value;
    }
    $output['data'][] = $rowData;
}

header('Content-Type: application/json');
echo json_encode($output);