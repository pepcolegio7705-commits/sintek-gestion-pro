<?php
/**
 * ANULACIÓN DE GASTO REGISTRADO
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

try {
    $id_gasto = (int)$_POST['id'];
    $motivo   = strip_tags(trim($_POST['motivo']));
    $usuario  = $_SESSION['nombre_usuario'] ?? 'Admin';

    if (empty($motivo)) throw new Exception("El motivo de anulación es obligatorio.");

    $pdo->beginTransaction();

    // Actualizamos el estado y guardamos quién y por qué anuló
    $sql = "UPDATE gastos SET 
                estado = 'Anulado', 
                motivo_anulacion = ?, 
                usuario_anulo = ?, 
                fecha_anulacion = NOW() 
            WHERE id_gasto = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$motivo, $usuario, $id_gasto]);

    // Opcional: Si el gasto estaba vinculado a una liquidación, podrías marcarla también
    // Pero es mejor manejarlo como registros independientes para mantener el rastro.

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Gasto anulado correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}