<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $motivo = $_POST['motivo'];
    $usuario = $_SESSION['nombre_usuario'] ?? 'Sistema';

    try {
        $stmt = $pdo->prepare("UPDATE gastos SET 
                                estado = 'Anulado', 
                                motivo_anulacion = ?, 
                                usuario_anulo = ?, 
                                fecha_anulacion = NOW() 
                                WHERE id_gasto = ?");
        
        if ($stmt->execute([$motivo, $usuario, $id])) {
            echo json_encode(['status' => 'success', 'message' => 'El gasto ha sido anulado correctamente.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el registro.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}