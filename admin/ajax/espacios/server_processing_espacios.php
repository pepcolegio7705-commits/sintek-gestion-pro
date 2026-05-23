<?php
/**
 * PROCESADOR AJAX: GESTIÓN DE ESPACIOS CURRICULARES - SINTEK
 * Ubicación: /ajax/espacios/server_processing_espacios.php
 */

session_start();

// 1. Cargamos el núcleo (Subimos dos niveles: ajax/ y espacios/)
// Verifica que la ruta física sea esta. Si tu estructura es distinta, ajusta los ../
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

// Control de acceso - Aseguramos que coincida con tus roles
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado.']);
    exit;
}

$table = 'espacios_curriculares';
$primaryKey = 'id_espacio'; 

// ----------------------------------------------------
// Mapeo de columnas para DataTables
// ----------------------------------------------------
$columns = array(
    array( 'db' => 'a.codigo',           'dt' => 0 ), 
    array( 'db' => 'a.nombre_espacio',   'dt' => 1 ),
    array( 'db' => 'c.nombre_carrera',   'dt' => 2 ), 
    array( 'db' => 'a.anio_cursada',     'dt' => 3 ),
    array( 'db' => 'a.tipo_cursada',     'dt' => 4 ), 
    array( 'db' => 'a.carga_horaria',    'dt' => 5 ),
    array( 'db' => 'a.id_espacio',       'dt' => 6, 'formatter' => function( $d, $row ) {
        // IMPORTANTE: Usamos htmlspecialchars para evitar errores de JS con comillas
        $nombre = htmlspecialchars($row['nombre_espacio_raw'], ENT_QUOTES, 'UTF-8');
        $id_carrera = $row['id_carrera'];
        
        $botones = '<div class="btn-group shadow-sm" role="group">';
        
        // Editar: URL Amigable configurada en .htaccess
        $botones .= '<a href="' . BASE_URL . 'materias/editar/' . $d . '" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fas fa-edit"></i></a> ';
        
        // Imprimir: Apuntamos al archivo físico en ajax
        $botones .= '<a href="' . BASE_URL . 'ajax/espacios/imprimir_lista_espacio?id_espacio=' . $d . '&id_carrera=' . $id_carrera . '" target="_blank" class="btn btn-sm btn-outline-dark" title="Imprimir Listado"><i class="fas fa-print"></i></a> ';
        
        // Eliminar: Función JS
        $botones .= '<button onclick="confirmarEliminacion(' . $d . ', \'' . $nombre . '\')" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>';
        
        $botones .= '</div>';
        return $botones;
    }, 'alias' => 'id_espacio' ),
    
    // Alias auxiliares para que el formatter tenga los datos sin mostrarlos en columnas
    array( 'db' => 'a.id_carrera',      'dt' => null, 'alias' => 'id_carrera' ),
    array( 'db' => 'a.nombre_espacio',  'dt' => null, 'alias' => 'nombre_espacio_raw' )
);

// --- LÓGICA DE FILTRADO Y CONSULTA ---
$from = "{$table} a JOIN carreras c ON a.id_carrera = c.id_carrera";

$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = $_POST['search']['value'] ?? '';

// Filtro por carrera enviado desde el JS
$id_carrera_filtro = $_POST['id_carrera_filtro'] ?? '';

$where = ' WHERE a.activo = 1 ';
$params = [];

if (!empty($id_carrera_filtro)) {
    $where .= " AND a.id_carrera = ? ";
    $params[] = $id_carrera_filtro;
}

if (!empty($searchValue)) {
    $where .= ' AND (a.codigo LIKE ? OR a.nombre_espacio LIKE ? OR c.nombre_carrera LIKE ?)';
    $p = "%$searchValue%";
    $params[] = $p; $params[] = $p; $params[] = $p;
}

// Conteo total y filtrado
$sql_total = "SELECT COUNT(a.{$primaryKey}) FROM {$table} a WHERE a.activo = 1";
$recordsTotal = $pdo->query($sql_total)->fetchColumn();

// SQL Final con SQL_CALC_FOUND_ROWS para optimizar el conteo filtrado
$sql = "SELECT SQL_CALC_FOUND_ROWS a.id_espacio, a.codigo, a.nombre_espacio, c.nombre_carrera, a.anio_cursada, a.tipo_cursada, a.carga_horaria, a.id_carrera 
        FROM {$from} {$where} 
        ORDER BY a.nombre_espacio ASC 
        LIMIT {$start}, {$length}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recordsFiltered = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    
    $output = [
        "draw" => $draw,
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => []
    ];

    foreach ($data as $row) {
        $rowData = [];
        foreach ($columns as $column) {
            if (isset($column['dt']) && $column['dt'] !== NULL) {
                // Buscamos el valor en el row. Si tiene alias, lo usamos.
                $key = $column['alias'] ?? str_replace('a.', '', $column['db']);
                $key = str_replace('c.', '', $key);
                
                $value = $row[$key] ?? null; 
                
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