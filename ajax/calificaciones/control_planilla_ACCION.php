<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

// Verificamos permisos mínimos
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $id_espacio = (int)($_POST['id_espacio'] ?? 0);
    $ciclo = (int)($_POST['ciclo'] ?? 0);
    $id_usuario = $_SESSION['id_usuario']; // Para auditoría
    $rol = $_SESSION['rol'];

    if (!$id_espacio || !$ciclo) {
        echo json_encode(['status' => 'error', 'message' => 'Datos incompletos.']);
        exit;
    }

    // Validación de seguridad: Solo Admin reabre
    if ($action === 'reabrir' && $rol !== 'Administrador') {
        echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para reabrir planillas.']);
        exit;
    }

    try {
        $nuevo_estado = ($action === 'cerrar') ? 'Cerrada' : 'Abierta';
        $fecha_accion = ($action === 'cerrar') ? date('Y-m-d H:i:s') : null;

        // Usamos INSERT ... ON DUPLICATE KEY UPDATE para que funcione la primera vez y las siguientes
        $sql = "INSERT INTO control_planillas (id_espacio, ciclo_lectivo, estado, fecha_cierre, usuario_cierre) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                estado = VALUES(estado), 
                fecha_cierre = VALUES(fecha_cierre), 
                usuario_cierre = VALUES(usuario_cierre)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_espacio, $ciclo, $nuevo_estado, $fecha_accion, $id_usuario]);

        $msj = ($action === 'cerrar') ? 'Planilla finalizada y bloqueada.' : 'Planilla reabierta con éxito.';
        echo json_encode(['status' => 'success', 'message' => $msj]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error de BD: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}