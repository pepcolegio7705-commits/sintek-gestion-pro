<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

// 1. Control de acceso
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado.']));
}

$table = 'espacios_curriculares';
$primaryKey = 'id_espacio'; 

// ----------------------------------------------------
// Mapeo de columnas corregido con UUID y Aliases Seguros
// ----------------------------------------------------
$columns = array(
    array( 'db' => 'a.codigo',           'dt' => 0 ), 
    array( 'db' => 'a.nombre_espacio',   'dt' => 1 ),
    array( 'db' => 'c.nombre_carrera',   'dt' => 2 ), 
    array( 'db' => 'a.anio_cursada',     'dt' => 3, 'formatter' => function($d){ return $d . "° Año"; } ),
    array( 'db' => 'a.tipo_cursada',     'dt' => 4 ), 
    array( 'db' => 'a.carga_horaria',    'dt' => 5, 'formatter' => function($d){ return $d . " hs"; } ),
    
    // USAMOS EL UUID PARA LAS ACCIONES
    // Busca la columna de las acciones (índice 6) y reemplaza el formatter:
    array( 'db' => 'a.uuid_espacio', 'dt' => 6, 'formatter' => function( $d, $row ) {
        // Escapamos comillas simples y dobles del nombre de la materia
        $nombre_js = addslashes(htmlspecialchars($row['nombre_espacio_raw'], ENT_QUOTES, 'UTF-8'));
        $uuid = $d; 
        
        $botones = '<div class="btn-group shadow-sm" role="group">';
        
        // Editar e Imprimir (esto está bien)
        $botones .= '<a href="' . BASE_URL . 'materias/editar/' . $uuid . '" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>';
        $botones .= '<a href="' . BASE_URL . 'materias/lista/' . $uuid . '" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-print"></i></a>';

        // ELIMINAR: Nota las comillas \' escapadas para que el JS reciba un String
        $botones .= '<button type="button" onclick="confirmarEliminacion(\'' . $uuid . '\', \'' . $nombre_js . '\')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>';

        $botones .= '</div>';
        return $botones;
    }, 'alias' => 'uuid_espacio' ),
    array( 'db' => 'a.id_carrera', 'dt' => null, 'alias' => 'id_carrera' ),
    array( 'db' => 'a.nombre_espacio', 'dt' => null, 'alias' => 'nombre_espacio_raw' )
);

// ----------------------------------------------------
// 2. Procesamiento de parámetros
// ----------------------------------------------------
$from = "{$table} a JOIN carreras c ON a.id_carrera = c.id_carrera";

$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = $_POST['search']['value'] ?? '';
$orderColumnIndex = $_POST['order'][0]['column'] ?? 1;
$orderDir = $_POST['order'][0]['dir'] ?? 'asc';
$id_carrera_filtro = $_POST['id_carrera_filtro'] ?? '';

// 3. WHERE
$where = ' WHERE a.activo = 1 ';
$params = [];

if (!empty($id_carrera_filtro)) {
    $where .= " AND a.id_carrera = ? ";
    $params[] = $id_carrera_filtro;
}

if (!empty($searchValue)) {
    $where .= ' AND (a.codigo LIKE ? OR a.nombre_espacio LIKE ? OR c.nombre_carrera LIKE ?)';
    $params = array_merge($params, ["%$searchValue%", "%$searchValue%", "%$searchValue%"]);
}

// 4. SQL - CONSTRUCCIÓN SEGURA DE COLUMNAS (Soluciona el Error de Alias)
$select_statements = [];
foreach ($columns as $column) {
    if (isset($column['db'])) {
        $alias_part = (isset($column['alias']) && !empty($column['alias'])) ? ' AS ' . $column['alias'] : '';
        $select_statements[] = $column['db'] . $alias_part;
    }
}
$select_statements_str = implode(', ', array_unique($select_statements));

$orderColumnName = $columns[$orderColumnIndex]['db'] ?? 'a.nombre_espacio'; 
$sql = "SELECT SQL_CALC_FOUND_ROWS {$select_statements_str} FROM {$from} {$where} ORDER BY {$orderColumnName} {$orderDir} LIMIT {$start}, {$length}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recordsFiltered = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    $recordsTotal = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE activo = 1")->fetchColumn();
    
    $output = [
        "draw" => $draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => []
    ];

    foreach ($data as $row) {
        $rowData = [];
        foreach ($columns as $column) {
            if (isset($column['dt']) && $column['dt'] !== null) {
                // Lógica de recuperación de valor: Alias -> Nombre Limpio -> Vacío
                $key = $column['alias'] ?? null;
                if (!$key) {
                    $parts = explode('.', $column['db']);
                    $key = end($parts);
                }

                $value = $row[$key] ?? '';
                
                if (isset($column['formatter'])) {
                    $value = $column['formatter']($value, $row);
                }
                $rowData[] = $value;
            }
        }
        $output['data'][] = $rowData;
    }
    
    header('Content-Type: application/json');
    echo json_encode($output);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}