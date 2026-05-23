<?php
require_once '../../core/conexion.php';

$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    echo json_encode(['status' => false, 'msj' => 'UUID no proporcionado']);
    exit;
}

try {
    // 1. Obtener ID y verificar deuda (Lógica simplificada de ejemplo)
    $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE uuid_alumno = ?");
    $stmt->execute([$uuid]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        echo json_encode(['status' => false, 'msj' => 'Alumno no encontrado']);
        exit;
    }

    // --- AQUÍ LA LÓGICA DE DEUDA QUE YA TENGAS ---
    // Supongamos que $deuda es false si está al día
    $estaAlDia = true; 

    $carreras = [];
    if ($estaAlDia) {
        // 2. BUSCAR CARRERAS EN LA TABLA INTERMEDIA (Lo que mencionaste)
        $sqlCarreras = "SELECT c.id_carrera, c.nombre_carrera 
                        FROM alumnos_carreras ac
                        JOIN carreras c ON ac.id_carreras = c.id_carrera 
                        WHERE ac.id_alumno = ?";
        $stmtC = $pdo->prepare($sqlCarreras);
        $stmtC->execute([$alumno['id_alumno']]);
        $carreras = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. RESPUESTA JSON (Vital para el .getJSON del JS)
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $estaAlDia,
        'msj' => $estaAlDia ? 'OK' : 'El alumno posee deuda pendiente',
        'carreras' => $carreras // Este array lo recorre el JS con .forEach
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'msj' => $e->getMessage()]);
}