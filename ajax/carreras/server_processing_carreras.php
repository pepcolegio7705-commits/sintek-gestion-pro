<?php
/**
 * PROCESADOR DATATABLES: GESTIÓN DE CARRERAS (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/carreras/server_processing_carreras.php
 */

require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

// Control de acceso
if (!isset($_SESSION['loggedin'])) {
    http_response_code(403);
    exit("Acceso denegado.");
}

$table = 'carreras';
$primaryKey = 'id_carrera';

// --- CONFIGURACIÓN DE COLUMNAS ---
$columns = array(
    array( 'db' => 'id_carrera',       'dt' => 0, 'alias' => 'id_carrera' ),
    array( 'db' => 'nombre_carrera',   'dt' => 1, 'alias' => 'nombre_carrera' ),
    array( 'db' => 'duracion_anios',   'dt' => 2, 'alias' => 'duracion_anios', 'formatter' => function($d){ return $d . " años"; } ),
    
    // Columna de acciones (Índice 3) - USAMOS EL UUID PARA SEGURIDAD
    array('db' => 'uuid_carrera', 'dt' => 3, 'alias' => 'acciones', 'formatter' => function( $d, $row ) {
        // CORRECCIÓN: Escapamos comillas y caracteres especiales para que el JS no falle
        $nombre_limpio = addslashes(htmlspecialchars($row['nombre_carrera'], ENT_QUOTES, 'UTF-8')); 
        $uuid = $d; 
        
        $botones = '<div class="btn-group" role="group">';
        
        // --- BOTONES PARA ARCHIVOS PDF (PLAN Y RESOLUCIÓN) ---

        // 1. Plan de Estudio
        if (!empty($row['plan_estudio'])) {
            $ruta_plan = BASE_URL . $row['plan_estudio']; 
            $botones .= '<a href="' . $ruta_plan . '" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver Plan de Estudio"><i class="fas fa-file-pdf"></i> Plan</a> ';
        }

        // 2. Resolución
        if (!empty($row['resolucion'])) {
            $ruta_res = BASE_URL . $row['resolucion'];
            $botones .= '<a href="' . $ruta_res . '" target="_blank" class="btn btn-sm btn-outline-primary" title="Ver Resolución"><i class="fas fa-file-contract"></i> Res.</a> ';
        }

        // 3. Ver Alumnos (Azul) - Pasa el UUID
        $botones .= '<button type="button" onclick="verAlumnos(\'' . $uuid . '\', \'' . $nombre_limpio . '\')" class="btn btn-sm btn-primary" title="Ver Alumnos"><i class="fa fa-users"></i></button>';
        
        // 4. Editar (Celeste) - URL Amigable con UUID
        $botones .= '<a href="' . BASE_URL . 'carreras/editar/' . $uuid . '" class="btn btn-sm btn-info text-white" title="Editar Carrera"><i class="fa fa-edit"></i></a> ';
        
        // 5. Imprimir Listado (Dark) - Pasa el UUID
        $botones .= '<a href="' . BASE_URL . 'carreras/imprimir/' . $uuid . '" target="_blank" class="btn btn-sm btn-outline-dark" title="Imprimir Listado"><i class="fa fa-print"></i></a>';
        
        // 6. Eliminar (Rojo) - Pasa el UUID
        $botones .= '<button type="button" onclick="confirmarEliminacion(\'' . $uuid . '\', \'' . $nombre_limpio . '\')" class="btn btn-sm btn-danger" title="Desactivar Carrera"><i class="fa fa-trash"></i></button>';
        
        $botones .= '</div>';
        return $botones;
    }),

    // Columnas técnicas para el SELECT (no se muestran pero se necesitan en el $row)
    array( 'db' => 'uuid_carrera', 'dt' => null, 'alias' => 'uuid_carrera' ), 
    array( 'db' => 'plan_estudio', 'dt' => null, 'alias' => 'plan_estudio' ),
    array( 'db' => 'resolucion',   'dt' => null, 'alias' => 'resolucion' )
);

// --- LÓGICA DE PROCESAMIENTO (Slightly optimized) ---
$from = "{$table}"; 
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

$where = ' WHERE activo = 1 '; 
$params = [];

if (!empty($searchValue)) {
    $where .= ' AND (nombre_carrera LIKE ? OR duracion_anios LIKE ?)';
    $params[] = '%' . $searchValue . '%';
    $params[] = '%' . $searchValue . '%';
}

// Ordenación
$order = " ORDER BY id_carrera DESC"; // Orden por defecto
if (isset($_POST['order'])) {
    $columnIdx = intval($_POST['order'][0]['column']);
    $dir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
    if ($columnIdx < 3) { 
        $dbColumn = $columns[$columnIdx]['db'];
        $order = " ORDER BY {$dbColumn} {$dir}";
    }
}

// Selección de campos únicos
$select_fields = [];
foreach ($columns as $column) {
    if (isset($column['db'])) {
        $select_fields[] = $column['db'] . (isset($column['alias']) ? ' AS ' . $column['alias'] : '');
    }
}

// Ejecución
$sql = "SELECT " . implode(', ', array_unique($select_fields)) . " FROM {$from}" . $where . $order . " LIMIT {$start}, {$length}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Conteos
$recordsTotal = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE activo = 1")->fetchColumn();
$recordsFiltered = (!empty($searchValue)) ? $pdo->prepare("SELECT COUNT(*) FROM {$from}" . $where)->execute($params) ? $stmt->fetchColumn() : $recordsTotal : $recordsTotal;

// Formato de salida
$output = ["draw" => $draw, "recordsTotal" => (int)$recordsTotal, "recordsFiltered" => (int)$recordsFiltered, "data" => []];

foreach ($data as $row) {
    $rowData = [];
    foreach ($columns as $column) {
        if ($column['dt'] !== null) {
            $key = $column['alias'] ?? $column['db'];
            $value = $row[$key];
            if (isset($column['formatter'])) { $value = call_user_func($column['formatter'], $value, $row); }
            $rowData[] = $value;
        }
    }
    $output['data'][] = $rowData;
}

header('Content-Type: application/json');
echo json_encode($output);