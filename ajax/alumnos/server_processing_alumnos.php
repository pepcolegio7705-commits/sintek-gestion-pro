<?php
/**
 * PROCESADOR AJAX PARA DATATABLES DE ALUMNOS - SINTEK (VERSIÓN FINAL SEGURA)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// 1. VALIDACIÓN DE ACCESO
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado.']));
}

$table = 'alumnos';
$primaryKey = 'id_alumno';

// 2. DEFINICIÓN DE COLUMNAS
$columns = array(
    array( 'db' => 'id_alumno', 'dt' => 0 ),
    array( 'db' => 'legajo',    'dt' => 1 ),
    array( 'db' => 'dni',       'dt' => 2 ),
    array( 'db' => 'nombre',    'dt' => 3 ),
    array( 'db' => 'apellido',  'dt' => 4, 'formatter' => function($d) {
        return htmlspecialchars($d);
    }),
    array( 'db' => 'id_alumno', 'dt' => 5, 'formatter' => function($d, $row) {
        $faltantes = [];
        if (empty($row['pdf_dni'])) $faltantes[] = "DNI";
        if (empty($row['pdf_titulo'])) $faltantes[] = "Título";
        if (empty($row['pdf_aptitud'])) $faltantes[] = "Aptitud";
        return !empty($faltantes) ? 
            '<span class="text-warning" title="Falta: '.implode(", ", $faltantes).'" style="cursor:help;">⚠️</span>' : 
            '<span class="text-success">✅</span>';
    }),
    array( 'db' => 'id_alumno', 'dt' => 6, 'formatter' => function($d, $row) {
        $btns = '<div class="btn-group">';
        $docs = [['pdf_dni', 'fa-id-card'], ['pdf_titulo', 'fa-graduation-cap'], ['pdf_aptitud', 'fa-heartbeat']];
        foreach ($docs as $doc) {
            if (!empty($row[$doc[0]])) {
                $btns .= '<a href="'.BASE_URL.'uploads/legajos/'.$row[$doc[0]].'" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas '.$doc[1].'"></i></a>';
            }
        }
        return $btns . '</div>';
    }),
    array( 'db' => 'uuid_alumno', 'dt' => 7, 'formatter' => function($d, $row) {
        $nombre_js = addslashes(htmlspecialchars($row['apellido'] . ' ' . $row['nombre']));
        return '<div class="btn-group">
                    <a href="'.BASE_URL.'alumnos/ficha/'.$d.'" target="_blank" class="btn btn-sm btn-info text-white"><i class="fas fa-file-pdf"></i></a>
                    <a href="'.BASE_URL.'alumnos/editar/'.$d.'" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                    <button type="button" onclick="confirmarEliminacion(\''.$d.'\', \''.$nombre_js.'\')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                </div>';
    }),
    array( 'db' => 'uuid_alumno', 'dt' => 8 ), // UUID para uso del JS
    array( 'db' => 'pdf_dni',     'dt' => null ), // Auxiliares
    array( 'db' => 'pdf_titulo',  'dt' => null ),
    array( 'db' => 'pdf_aptitud', 'dt' => null )
);

// 3. CAPTURA DE VARIABLES POST (DataTables)
$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$searchValue = $_POST['search']['value'] ?? '';

// 4. CÁLCULO DE TOTALES
$recordsTotal = $pdo->query("SELECT COUNT({$primaryKey}) FROM {$table} WHERE activo = 1")->fetchColumn();

// 5. CONSTRUCCIÓN DE LA CONSULTA (WHERE / ORDER / LIMIT)
$where = " WHERE activo = 1 ";
$params = [];

if (!empty($searchValue)) {
    $where .= " AND (legajo LIKE ? OR dni LIKE ? OR nombre LIKE ? OR apellido LIKE ?)";
    $p = "%$searchValue%";
    $params = [$p, $p, $p, $p];
}

$recordsFiltered = $recordsTotal;
if (!empty($searchValue)) {
    $stmt_f = $pdo->prepare("SELECT COUNT({$primaryKey}) FROM {$table} {$where}");
    $stmt_f->execute($params);
    $recordsFiltered = $stmt_f->fetchColumn();
}

$order = " ORDER BY apellido ASC "; // Orden por defecto
$limit = " LIMIT {$start}, {$length} ";

// 6. EJECUCIÓN FINAL
$sql = "SELECT id_alumno, legajo, dni, nombre, apellido, pdf_dni, pdf_titulo, pdf_aptitud, uuid_alumno FROM {$table} {$where} {$order} {$limit}";



try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($results as $row) {
        $rowData = [];
        foreach ($columns as $column) {
            if ($column['dt'] !== null) {
                $val = $row[$column['db']] ?? '';
                if (isset($column['formatter'])) {
                    $val = call_user_func($column['formatter'], $val, $row);
                }
                $rowData[] = $val;
            }
        }
        $data[] = $rowData;
    }

    $output = [
        "draw"            => $draw,
        "recordsTotal"    => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data"            => $data
    ];

    header('Content-Type: application/json');
    echo json_encode($output);

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}