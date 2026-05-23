<?php
/**
 * PROCESADOR AJAX: LISTADO DE ALUMNOS POR CARRERA (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/carreras/get_alumnos_por_carrera.php
 */

session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Control de acceso
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    header('Content-Type: application/json');
    echo json_encode(["data" => [], "error" => "Acceso no autorizado"]);
    exit;
}

if (ob_get_length()) ob_clean(); 

// 1. CAPTURA DEL UUID (En lugar de id_carrera)
$uuid = $_GET['uuid'] ?? '';
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid));

if (empty($uuid)) {
    header('Content-Type: application/json');
    echo json_encode(["data" => []]);
    exit;
}

try {
    // 2. BUSQUEDA DE ALUMNOS FILTRANDO POR UUID DE CARRERA
    // Usamos un JOIN con la tabla carreras para filtrar por el identificador seguro
    $sql = "SELECT 
                a.dni, 
                a.apellido, 
                a.nombre, 
                MIN(ie.fecha_inscripcion) as fecha_minima
            FROM alumnos_carreras ac 
            JOIN alumnos a ON ac.id_alumno = a.id_alumno
            JOIN carreras c ON ac.id_carreras = c.id_carrera
            LEFT JOIN inscripciones_espacios ie ON a.id_alumno = ie.id_alumno
            WHERE c.uuid_carrera = ? AND a.activo = 1
            GROUP BY a.id_alumno
            ORDER BY a.apellido ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uuid]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($alumnos as $al) {
        $estado = '<span class="badge bg-success">Activo</span>'; // Ajustado a Bootstrap 5 (bg-success)
        
        $fecha_mostrar = "Sin fecha";
        if (!empty($al['fecha_minima'])) {
            $fecha_mostrar = date("d/m/Y", strtotime($al['fecha_minima']));
        }
                  
        $data[] = [
            (string)$al['dni'],
            "<strong>" . htmlspecialchars($al['apellido'] . ", " . $al['nombre']) . "</strong>",
            $estado,
            $fecha_mostrar
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(["data" => $data]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(["data" => [], "error" => $e->getMessage()]);
}
exit;