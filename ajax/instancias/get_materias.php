<?php
session_start();
// Ajustamos la ruta para llegar a la conexión (subimos 2 niveles)
require_once '../../core/conexion.php';

// Validamos que la petición sea POST y tenga el ID de carrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_carrera'])) {
    header('Content-Type: application/json');
    
    $id_carrera = (int)$_POST['id_carrera'];

    try {
        // Preparamos la consulta
        $stmt = $pdo->prepare("SELECT id_espacio, nombre_espacio 
                               FROM espacios_curriculares 
                               WHERE id_carrera = ? AND activo = 1 
                               ORDER BY nombre_espacio ASC");
        $stmt->execute([$id_carrera]);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Devolvemos los datos en formato JSON
        echo json_encode($materias);
        exit;
        
    } catch (PDOException $e) {
        // En caso de error, devolvemos un código de error HTTP y el mensaje
        http_response_code(500);
        echo json_encode(['error' => 'Error al consultar la base de datos']);
        exit;
    }
} else {
    // Si intentan acceder directamente sin los parámetros correctos
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}