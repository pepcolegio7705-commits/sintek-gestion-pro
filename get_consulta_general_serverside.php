<?php
require 'conexion.php';
header('Content-Type: application/json');

// Desactivar errores para no corromper el JSON de salida
error_reporting(0);
ini_set('display_errors', 0);

$id_espacio = isset($_POST['id_espacio']) ? (int)$_POST['id_espacio'] : 0;
$ciclo = isset($_POST['ciclo']) ? (int)$_POST['ciclo'] : (int)date("Y");

// Parámetros DataTables
$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

if ($id_espacio === 0) {
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

try {
    // Definimos la base de la consulta
    $fromClause = " FROM inscripciones_espacios ie
                    INNER JOIN alumnos a ON ie.id_alumno = a.id_alumno
                    LEFT JOIN calificaciones c ON (c.id_alumno = a.id_alumno AND c.id_espacio = ie.id_espacio)";
    
    // Filtros base
    $whereBase = " WHERE ie.id_espacio = :id_espacio 
                   AND a.activo = 1 
                   AND YEAR(ie.fecha_inscripcion) = :ciclo";

    // Lógica de búsqueda
    $searchSQL = "";
    if ($search !== '') {
        $searchSQL = " AND (a.dni LIKE :search OR a.apellido LIKE :search OR a.nombre LIKE :search)";
    }

    // 1. Conteo Total
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) $fromClause $whereBase");
    $stmtTotal->execute(['id_espacio' => $id_espacio, 'ciclo' => $ciclo]);
    $recordsTotal = (int)$stmtTotal->fetchColumn();

    // 2. Conteo Filtrado
    // 2. Conteo Filtrado
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) $fromClause $whereBase $searchSQL");
    $paramsFiltered = [
        'id_espacio' => $id_espacio, 
        'ciclo'      => $ciclo
    ];
    // Solo vinculamos :search si realmente hay una búsqueda
    if ($search !== '') {
        $paramsFiltered['search'] = "%$search%";
    }
    $stmtFiltered->execute($paramsFiltered);
    $recordsFiltered = (int)$stmtFiltered->fetchColumn();

    // 3. Obtención de Datos
    $sqlData = "SELECT a.dni, a.apellido, a.nombre, 
                    c.nota_final, c.condicion, c.fecha_registro, 
                    a.libro_matriz as libro, a.folio_matriz as folio 
                $fromClause $whereBase $searchSQL 
                ORDER BY a.apellido ASC, a.nombre ASC 
                LIMIT :limit OFFSET :offset";

    $stmtData = $pdo->prepare($sqlData);
    // Vinculamos parámetros fijos
    $stmtData->bindValue(':id_espacio', $id_espacio, PDO::PARAM_INT);
    $stmtData->bindValue(':ciclo', $ciclo, PDO::PARAM_INT);
    $stmtData->bindValue(':limit', $length, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $start, PDO::PARAM_INT);

    // CLAVE: Vinculamos :search SOLO si el SQL lo contiene
    if ($search !== '') {
        $stmtData->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }

    $stmtData->execute();

    $aaData = [];
    while ($r = $stmtData->fetch(PDO::FETCH_ASSOC)) {
        $aaData[] = [
            "dni"       => $r['dni'],
            "alumno"    => strtoupper($r['apellido'] . ", " . $r['nombre']),
            "nota"      => $r['nota_final'] ?? '-',
            "condicion" => $r['condicion'] ? strtoupper($r['condicion']) : 'SIN CARGAR',
            "fecha"     => ($r['fecha_registro'] && $r['fecha_registro'] != '0000-00-00') ? date('d/m/Y', strtotime($r['fecha_registro'])) : '-',
            "libro"     => $r['libro'] ?? '-',
            "folio"     => $r['folio'] ?? '-'
        ];
    }

    // El objeto de retorno debe tener la clave "data" para coincidir con tu JS
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $aaData 
    ]);

} catch (PDOException $e) {
    // En caso de error, devolver un JSON válido para que DataTables no lance un alert genérico
    echo json_encode(["error" => $e->getMessage(), "data" => []]);
}