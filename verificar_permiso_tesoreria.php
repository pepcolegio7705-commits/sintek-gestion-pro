<?php
session_start();
require 'conexion.php';
header('Content-Type: application/json');

// Captura de datos del POST
$user   = $_POST['user'] ?? '';
$pass   = $_POST['pass'] ?? '';
$motivo = $_POST['motivo'] ?? '';
$mes    = $_POST['mes_solicitado'] ?? '';
$anio   = $_POST['anio_solicitado'] ?? '';

try {
    // 1. Verificar Usuario con id_rol = 5 (Jefe Tesorería)
    $stmt = $pdo->prepare("SELECT id_usuario, password FROM usuarios WHERE nombre_usuario = ? AND id_rol = 5");
    $stmt->execute([$user]);
    $u = $stmt->fetch();

    // 2. Validar password
    // Usamos password_verify asumiendo que las claves están hasheadas
    if ($u && password_verify($pass, $u['password'])) {
        
        // 3. REGISTRO DE AUDITORÍA EN LA TABLA DE LOGS
        $sql_log = "INSERT INTO logs_autorizaciones (id_usuario_autorizo, modulo, accion, motivo, detalles, fecha_hora) 
                    VALUES (?, 'Liquidacion Masiva', 'Apertura Periodo Especial', ?, ?, NOW())";
        
        $operador = $_SESSION['nombre_usuario'] ?? 'Operador Desconocido';
        $detalles = "Periodo Solicitado: $mes/$anio. Solicitado por: $operador";
        
        $stmt_log = $pdo->prepare($sql_log);
        $stmt_log->execute([$u['id_usuario'], $motivo, $detalles]);

        echo json_encode(['success' => true]);
    } else {
        // Respuesta clara para el modal
        echo json_encode([
            'success' => false, 
            'message' => 'Credenciales inválidas o el usuario no cuenta con nivel de Jefe Tesorería.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}