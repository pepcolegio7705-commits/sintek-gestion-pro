<?php
session_start();
require_once '../../core/conexion.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Acceso denegado.']));
}

$table = 'alumnos';

// 1. DEFINICIÓN DE COLUMNAS
$columns = array(
    array( 'db' => 'dni', 'dt' => 0 ), 
    array( 'db' => 'apellido', 'dt' => 1, 'formatter' => function($d, $row) {
        $nombre_completo = '<span class="fw-bold text-dark">' . htmlspecialchars($row['apellido']) . '</span>, ' . htmlspecialchars($row['nombre']);
        $carreras = $row['carreras_vinculadas'];

        if (!empty($carreras)) {
            // BADGE VERDE SI TIENE CARRERA
            $badge = '<br><small class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.7rem;">
                        <i class="fas fa-graduation-cap me-1"></i> ' . htmlspecialchars($carreras) . '
                      </small>';
        } else {
            // BADGE ROJO SI NO TIENE
            $badge = '<br><small class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.7rem;">
                        <i class="fas fa-exclamation-triangle me-1"></i> SIN CARRERA VINCULADA
                      </small>';
        }
        return $nombre_completo . $badge;
    }),
    array( 'db' => 'estado_academico', 'dt' => 2, 'formatter' => function($d) {
        $color = ($d == 'Activo' || empty($d)) ? 'success' : 'secondary';
        $texto = empty($d) ? 'Activo' : $d;
        return '<span class="badge bg-' . $color . '">' . $texto . '</span>'; 
    }),
    array( 'db' => 'uuid_alumno', 'dt' => 3 ),
    // Columnas auxiliares para el formatter (no se muestran directamente)
    array( 'db' => 'nombre', 'dt' => null ),
    array( 'db' => 'carreras_vinculadas', 'dt' => null )
);

// --- LÓGICA DE FILTRADO ---
$whereBase = " WHERE activo = 1 ";
$searchValue = $_POST['search']['value'] ?? '';
$where = $whereBase;
$params = [];

if (!empty($searchValue)) {
    $where .= " AND (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ?)";
    $p = "%$searchValue%";
    $params = [$p, $p, $p];
}

// Totales para paginación
$recordsTotal = $pdo->query("SELECT COUNT(*) FROM {$table} {$whereBase}")->fetchColumn();
$stmt_f = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$where}");
$stmt_f->execute($params);
$recordsFiltered = $stmt_f->fetchColumn();

// --- CONSULTA PRINCIPAL CON SUBCONSULTA DE CARRERAS ---
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

$sql = "SELECT a.dni, a.apellido, a.nombre, a.uuid_alumno, 
               (SELECT GROUP_CONCAT(c.nombre_carrera SEPARATOR ' | ') 
                FROM alumnos_carreras ac 
                JOIN carreras c ON ac.id_carreras = c.id_carrera 
                WHERE ac.id_alumno = a.id_alumno) as carreras_vinculadas
        FROM {$table} a 
        {$where} 
        ORDER BY a.apellido ASC, a.nombre ASC 
        LIMIT {$start}, {$length}";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = [
        "draw" => intval($_POST['draw'] ?? 0),
        "recordsTotal" => (int)$recordsTotal,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => []
    ];

    foreach ($data as $row) {
        $rowData = [];
        foreach ($columns as $column) {
            if ($column['dt'] !== null) {
                $value = $row[$column['db']] ?? null; 
                if (isset($column['formatter'])) {
                    $value = call_user_func($column['formatter'], $value, $row);
                }
                $rowData[] = $value;
            }
        }
        $output['data'][] = $rowData;
    }

    header('Content-Type: application/json');
    // Cálculo de contadores para los KPIs
    $sql_kpi = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN EXISTS (SELECT 1 FROM alumnos_carreras ac WHERE ac.id_alumno = a.id_alumno) THEN 1 ELSE 0 END) as vinculados
                FROM {$table} a 
                WHERE a.activo = 1";
    $kpi = $pdo->query($sql_kpi)->fetch(PDO::FETCH_ASSOC);

    $total = (int)$kpi['total'];
    $vinculados = (int)$kpi['vinculados'];
    $sin_carrera = $total - $vinculados;

    $output["kpis"] = [
        "total" => $total,
        "vinculados" => $vinculados,
        "sin_carrera" => $sin_carrera
    ];

    header('Content-Type: application/json');
    echo json_encode($output);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}