<?php
session_start();
require 'conexion.php'; 

// Control de acceso (Solo Administrador)
if (!isset($_SESSION['loggedin']) || $_SESSION['rol'] !== 'Administrador') {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado. Solo Administradores.']));
}

// ----------------------------------------------------
// DATATABLES SCRIPT (SERVER-SIDE)
// ----------------------------------------------------

$table = 'usuarios';
$primaryKey = 'id_usuario';

// Mapeo de columnas: ¡Total de 6 columnas ahora (índices 0 a 5)!
$columns = array(
    array( 'db' => 'u.id_usuario',      'dt' => 0, 'alias' => 'id_usuario' ),
    array( 'db' => 'u.nombre_usuario',  'dt' => 1, 'alias' => 'nombre_usuario' ),
    // NUEVA COLUMNA: Apellido (Nombre Real)
    array( 'db' => 'u.Apellido',        'dt' => 2, 'alias' => 'Apellido' ),
    array( 'db' => 'u.email',           'dt' => 3, 'alias' => 'email' ),
    array( 'db' => 'r.nombre_rol',      'dt' => 4, 'alias' => 'nombre_rol' ),
    
    // Índice 5: Acciones (Editar/Eliminar)
    array( 'db' => 'u.id_usuario',      'dt' => 5, 'alias' => 'acciones', 'formatter' => function( $d, $row ) {
        $usuario_login = $row['nombre_usuario']; 
        $botones = '<a href="usuarios_abm.php?action=edit&id=' . $d . '" class="btn btn-sm btn-info me-1">Editar</a>';
        $botones .= '<button onclick="confirmarEliminacion(' . $d . ', \'' . addslashes($usuario_login) . '\')" class="btn btn-sm btn-danger">Eliminar</button>';
        return $botones;
    })
);

// Tablas a incluir en el JOIN y el alias base
$from = "{$table} u JOIN roles r ON u.id_rol = r.id_rol"; 

// Variables de DataTables POST
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Columnas extra necesarias para el formatter (u.Apellido ya está en $columns)
$extra_cols = ['u.nombre_usuario']; 

// ----------------------------------------
// 1. CÁLCULO DEL TOTAL DE REGISTROS (SIN FILTRO)
// ----------------------------------------
$sql_total = "SELECT COUNT(u.{$primaryKey}) FROM {$table} u";
$stmt_total = $pdo->query($sql_total);
$recordsTotal = $stmt_total->fetchColumn();

// ----------------------------------------
// 2. CONSTRUCCIÓN DE LA CONSULTA (FILTRO, ORDENACIÓN Y PAGINACIÓN)
// ----------------------------------------
$where = '';
$params = [];

// Filtro de búsqueda global: Agregamos u.Apellido a la búsqueda
if (!empty($searchValue)) {
    $where = ' WHERE ';
    $searchParts = [];
    $searchableColumns = ['u.nombre_usuario', 'u.Apellido', 'u.email', 'r.nombre_rol'];
    
    foreach ($searchableColumns as $column) {
        $searchParts[] = $column . ' LIKE ?';
        $params[] = '%' . $searchValue . '%';
    }
    $where .= implode(' OR ', $searchParts);
}

// Ordenación
$order = '';
if (isset($_POST['order'])) {
    $columnIdx = intval($_POST['order'][0]['column']);
    $dir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    
    if (isset($columns[$columnIdx]) && $columnIdx < 5) { 
        $dbColumn = $columns[$columnIdx]['db'];
        $columnAlias = isset($columns[$columnIdx]['alias']) ? $columns[$columnIdx]['alias'] : $dbColumn;
        $order = " ORDER BY {$columnAlias} {$dir}";
    }
}

// Límite (Paginación)
$limit = " LIMIT {$start}, {$length}";

// Prepara la lista de columnas seleccionadas con sus alias
$select_statements = [];
foreach ($columns as $column) {
    if (isset($column['db'])) {
        $select_statements[] = $column['db'] . (isset($column['alias']) ? ' AS ' . $column['alias'] : '');
    }
}
$select_statements = array_unique(array_merge($select_statements, $extra_cols));

// Consulta final
$sql = "SELECT " . implode(', ', $select_statements)
       . " FROM {$from}" . $where . $order . $limit;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ----------------------------------------
    // 3. CÁLCULO DEL TOTAL DE REGISTROS (CON FILTRO)
    // ----------------------------------------
    $recordsFiltered = $recordsTotal; 
    if (!empty($where)) {
        $sql_filtered = "SELECT COUNT(u.{$primaryKey}) FROM {$from}" . $where;
        $stmt_filtered = $pdo->prepare($sql_filtered);
        $stmt_filtered->execute($params);
        $recordsFiltered = $stmt_filtered->fetchColumn();
    }

    // ----------------------------------------
    // 4. FORMATO DE SALIDA (JSON)
    // ----------------------------------------
    $output = array(
        "draw" => $draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => array()
    );

    foreach ($data as $row) {
        $rowData = [];
        foreach ($columns as $column) {
            $key = isset($column['alias']) ? $column['alias'] : $column['db'];
            $value = isset($row[$key]) ? $row[$key] : null; 
            
            if (isset($column['formatter'])) {
                $value = call_user_func($column['formatter'], $value, $row);
            }
            $rowData[] = $value;
        }
        $output['data'][] = $rowData;
    }
    
    header('Content-Type: application/json');
    echo json_encode($output);
    exit;

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Error de Base de Datos: " . $e->getMessage()
    ]);
    exit;
}