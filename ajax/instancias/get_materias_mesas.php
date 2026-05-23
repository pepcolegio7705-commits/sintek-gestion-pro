<?php
session_start();
require_once '../../core/conexion.php';

header('Content-Type: application/json');

// Buscamos por el parámetro que envía el JS (ahora es el UUID)
if (isset($_GET['uuid_carrera'])) {
    $uuid = $_GET['uuid_carrera'];
    try {
        // Hacemos el JOIN o búsqueda directa si la tabla espacios tiene el uuid_carrera
        // O buscamos el ID interno primero para filtrar los espacios
        $sql = "SELECT e.id_espacio, e.nombre_espacio 
                FROM espacios_curriculares e
                JOIN carreras c ON e.id_carrera = c.id_carrera
                WHERE c.uuid_carrera = ? AND e.activo = 1 
                ORDER BY e.nombre_espacio ASC";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid]);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($materias);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error en la base de datos']);
    }
} else {
    echo json_encode([]);
}
exit;