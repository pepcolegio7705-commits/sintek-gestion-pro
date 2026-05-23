<?php
/**
 * ACCIÓN: ANULAR BONO EXTRAORDINARIO
 * Ruta física: ajax/tesoreria/anular_bono.php
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Validar Token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Error de seguridad: Token inválido.']);
        exit;
    }

    // 2. Validar que venga el UUID
    $uuid = $_POST['uuid'] ?? '';

    if (empty($uuid)) {
        echo json_encode(['success' => false, 'error' => 'No se especificó el bono a anular.']);
        exit;
    }

    try {
        // 3. Ejecutar la anulación
        $sql = "UPDATE bonos_extraordinarios 
                SET estado = 0 
                WHERE uuid_bono = ? AND estado = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uuid]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'El bono no existe o ya fue anulado anteriormente.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
}