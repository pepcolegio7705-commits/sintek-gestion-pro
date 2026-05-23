<?php
/**
 * SERVERSIDE PROCESSING: PROFESORES (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/profesores/serverside_processing_profesores.php
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($pdo)) {
    die(json_encode(['error' => 'Error de conexión.']));
}

// 1. Definición de columnas (Aseguramos el orden para el JS)
// Agregamos p.uuid_profesor al final para no alterar los índices existentes que usa tu JS
$columns = array(
    array('db' => 'p.id_profesor',      'dt' => 0),
    array('db' => 'p.legajo',           'dt' => 1),
    array('db' => 'p.dni',              'dt' => 2),
    array('db' => 'p.nombre',           'dt' => 3),
    array('db' => 'p.apellido',         'dt' => 4),
    array('db' => 'a.nombre_area',      'dt' => 5),
    array('db' => 'p.cuil',             'dt' => 6),
    array('db' => 'p.fecha_nacimiento', 'dt' => 7),
    array('db' => 'p.sexo',             'dt' => 8),
    array('db' => 'p.estado_civil',     'dt' => 9),
    array('db' => 'p.domicilio',        'dt' => 10),
    array('db' => 'p.localidad',        'dt' => 11),
    array('db' => 'p.telefono',         'dt' => 12),
    array('db' => 'p.email',            'dt' => 13),
    array('db' => 'p.titulo_principal', 'dt' => 14),
    array('db' => 'p.activo',           'dt' => 15),
    array('db' => 'p.cantidad_hijos',   'dt' => 16),
    array('db' => 'p.hijos_verificados','dt' => 17),
    array('db' => 'p.ruta_pdf_dni',     'dt' => 18),
    array('db' => 'p.ruta_pdf_cv',      'dt' => 19),
    array('db' => 'p.ruta_pdf_titulo',  'dt' => 20),
    array('db' => 'p.nombre_usuario',   'dt' => 21),
    array('db' => 'p.ruta_pdf_hijos',   'dt' => 22),
    array('db' => 'p.cbu',              'dt' => 23),
    array('db' => 'p.banco',            'dt' => 24),
    array('db' => 'total_retenciones',  'dt' => 25),
    array('db' => 'p.uuid_profesor',    'dt' => 26)
);

// 2. Construcción de la Consulta
$sql = "FROM profesores p INNER JOIN areas a ON p.id_area = a.id_area";
$select = "SELECT p.id_profesor, p.legajo, p.dni, p.nombre, p.apellido, a.nombre_area, 
                  p.cuil, p.fecha_nacimiento, p.sexo, p.estado_civil, p.domicilio, 
                  p.localidad, p.telefono, p.email, p.titulo_principal, p.activo, 
                  p.cantidad_hijos, p.hijos_verificados, p.ruta_pdf_dni, p.ruta_pdf_cv, 
                  p.ruta_pdf_titulo, p.nombre_usuario, p.ruta_pdf_hijos, p.cbu, p.banco,
                  (SELECT COUNT(*) FROM retenciones_judiciales r WHERE r.id_persona = p.id_profesor AND r.tipo_persona = 'Profesor' AND r.activo = 1) as total_retenciones,
                  p.uuid_profesor ";

// Parámetros Datatables
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

//Búsqueda en la tabla input search
$where = "";
$params = [];
if (!empty($search)) {
    $where = " WHERE (p.nombre LIKE :s1 OR p.apellido LIKE :s2 OR p.dni LIKE :s3 OR p.legajo LIKE :s4 OR p.nombre_usuario LIKE :s5)";
    $valSearch = "%$search%";
    $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = $params[':s5'] = $valSearch;
}

// Conteo de registros
$recordsTotal = $pdo->query("SELECT COUNT(id_profesor) FROM profesores")->fetchColumn();
$stmtFilter = $pdo->prepare("SELECT COUNT(p.id_profesor) $sql $where");
$stmtFilter->execute($params);
$recordsFiltered = $stmtFilter->fetchColumn();

// Ordenamiento
$orderBy = "p.legajo";
$orderDir = "DESC";
if (isset($_POST['order'][0]['column'])) {
    $colIdx = intval($_POST['order'][0]['column']);
    // Buscamos el nombre de la columna DB basado en el dt enviado por Datatables
    foreach($columns as $col) {
        if($col['dt'] == $colIdx) {
            $orderBy = $col['db'];
            break;
        }
    }
    $orderDir = $_POST['order'][0]['dir'] === 'asc' ? 'ASC' : 'DESC';
}

$query = "$select $sql $where ORDER BY $orderBy $orderDir LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->bindValue(':limit', $length, PDO::PARAM_INT);
$stmt->bindValue(':offset', $start, PDO::PARAM_INT);
$stmt->execute();

// Usamos FETCH_NUM para que coincida con el acceso por índices data[0], data[1], etc. en tu JS
$data = $stmt->fetchAll(PDO::FETCH_NUM); 

header('Content-Type: application/json');
echo json_encode([
    "draw" => $draw,
    "recordsTotal" => (int)$recordsTotal,
    "recordsFiltered" => (int)$recordsFiltered,
    "data" => $data
]);