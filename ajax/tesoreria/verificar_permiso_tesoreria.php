<?php
session_start();
require_once __DIR__ . '/../../core/conexion.php';

header('Content-Type: application/json');

$user = $_POST['user'] ?? '';
$pass = $_POST['pass'] ?? '';
$motivo = $_POST['motivo'] ?? '';
$mes_sol = $_POST['mes_solicitado'] ?? '';
$anio_sol = $_POST['anio_solicitado'] ?? '';

try {
    $sql = "SELECT u.*, r.nombre_rol 
            FROM usuarios u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.nombre_usuario = :user AND u.estado = 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user' => $user]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || (!password_verify($pass, $usuario['password']) && $pass !== $usuario['password'])) {
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas.']);
        exit;
    }

    // Solo Administrador (id_rol = 1)
    if ((int)$usuario['id_rol'] !== 1) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos de Administrador.']);
        exit;
    }

    // --- GRABAR EN TU TABLA log_tesoreria ---
    $ip = $_SERVER['REMOTE_ADDR'];
    $detalles_log = "Autorización Apertura Período: $mes_sol/$anio_sol. Motivo: $motivo";
    
    $sql_log = "INSERT INTO log_tesoreria (uuid_log, fecha_hora, id_usuario, operacion, detalles, ip_address) 
                VALUES (UUID(), NOW(), ?, 'AUTORIZACION_PERIODO', ?, ?)";
    
    $pdo->prepare($sql_log)->execute([
        $usuario['id_usuario'], 
        $detalles_log, 
        $ip
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}