<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; 
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Seguridad fallida']); exit;
    }

    try {
        $sql = "INSERT INTO bonos_extraordinarios (uuid_bono, descripcion, monto, alcance_destino, mes_periodo, anio_periodo, estado, id_usuario_creador) 
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)";
        
        $pdo->prepare($sql)->execute([
            generar_uuid_v4(), 
            strip_tags($_POST['descripcion']), 
            (float)$_POST['monto'], 
            $_POST['alcance_destino'], 
            (int)$_POST['mes'], 
            (int)$_POST['anio'], 
            $_SESSION['id_usuario']
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['success' => false, 'error' => $e->getMessage()]); }
}