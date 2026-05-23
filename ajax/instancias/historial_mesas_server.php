<?php
session_start();
require_once '../../core/conexion.php';

// Parámetros de DataTables
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$row = isset($_POST['start']) ? intval($_POST['start']) : 0;
$rowperpage = isset($_POST['length']) ? intval($_POST['length']) : 10;

// Captura de filtros
$f_carrera_uuid = $_POST['f_carrera'] ?? ''; 
$f_materia_id   = $_POST['f_materia'] ?? ''; 
$f_desde        = $_POST['f_desde'] ?? '';
$f_hasta        = $_POST['f_hasta'] ?? '';

// 1. Construcción de la cláusula WHERE dinámica
$where = " WHERE m.estado = 'Cerrada' ";
$params = [];

if ($f_carrera_uuid != '') {
    $where .= " AND c.uuid_carrera = :uuid_carrera ";
    $params[':uuid_carrera'] = $f_carrera_uuid;
}

if ($f_materia_id != '') {
    $where .= " AND m.id_espacio = :id_materia ";
    $params[':id_materia'] = (int)$f_materia_id;
}

if ($f_desde != '' && $f_hasta != '') {
    $where .= " AND m.fecha_examen BETWEEN :desde AND :hasta ";
    $params[':desde'] = $f_desde;
    $params[':hasta'] = $f_hasta;
}

try {
    // 2. Contar registros totales
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM mesas_examenes WHERE estado = 'Cerrada'")->fetchColumn();

    // 3. Contar registros con filtros aplicados
    $sqlFilter = "SELECT COUNT(*) 
                  FROM mesas_examenes m 
                  JOIN carreras c ON m.id_carrera = c.id_carrera 
                  $where";
    $stmtFilter = $pdo->prepare($sqlFilter);
    $stmtFilter->execute($params);
    $totalRecordwithFilter = $stmtFilter->fetchColumn();

    // 4. Obtener los datos finales (AGREGAMOS m.uuid_mesa)
    $sqlData = "SELECT m.id_mesa, m.uuid_mesa, m.fecha_examen, m.llamado, e.nombre_espacio, c.nombre_carrera,
                   m.fecha_inicio_inscripcion, m.fecha_fin_inscripcion,
                   (SELECT libro FROM examenes_notas WHERE id_mesa = m.id_mesa AND libro IS NOT NULL AND libro != '' LIMIT 1) as libro,
                   (SELECT folio FROM examenes_notas WHERE id_mesa = m.id_mesa AND folio IS NOT NULL AND folio != '' LIMIT 1) as folio
            FROM mesas_examenes m 
            JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio 
            JOIN carreras c ON m.id_carrera = c.id_carrera 
            $where
            ORDER BY m.fecha_examen DESC 
            LIMIT :limit OFFSET :offset";

    $stmtData = $pdo->prepare($sqlData);
    
    foreach ($params as $key => $val) {
        $stmtData->bindValue($key, $val);
    }
    
    $stmtData->bindValue(':limit', (int)$rowperpage, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', (int)$row, PDO::PARAM_INT);
    $stmtData->execute();

    $aaData = [];
    while ($row_data = $stmtData->fetch(PDO::FETCH_ASSOC)) {
        $f_inicio = $row_data['fecha_inicio_inscripcion'] ? date('d/m/Y', strtotime($row_data['fecha_inicio_inscripcion'])) : '-';
        $f_fin    = $row_data['fecha_fin_inscripcion'] ? date('d/m/Y', strtotime($row_data['fecha_fin_inscripcion'])) : '-';

        $aaData[] = [
            date('d/m/Y', strtotime($row_data['fecha_examen'])), // [0]
            $row_data['nombre_carrera'],                         // [1]
            $row_data['nombre_espacio'],                         // [2]
            $row_data['llamado'] . "°",                          // [3]
            ($row_data['libro'] ?: '-') . " / " . ($row_data['folio'] ?: '-'), // [4] Libro/Folio más limpio
            $row_data['uuid_mesa'],                              // [5] UUID para los botones
            $f_inicio,                                           // [6]
            $f_fin                                               // [7]
        ];
    }

    echo json_encode([
        "draw" => intval($draw),
        "iTotalRecords" => $totalRecords,
        "iTotalDisplayRecords" => $totalRecordwithFilter,
        "aaData" => $aaData
    ]);

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}