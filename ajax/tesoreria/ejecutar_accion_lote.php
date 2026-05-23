<?php
/**
 * ACCIÓN: EJECUTAR PROCESO O ANULACIÓN DE LOTE
 */
session_start();
require_once __DIR__ . '/../../core/conexion.php';

header('Content-Type: application/json');

$id_lote = $_POST['id_lote'] ?? 0;
$accion  = $_POST['accion'] ?? ''; 
$user    = $_POST['user'] ?? '';
$pass    = $_POST['pass'] ?? '';
$motivo  = $_POST['motivo'] ?? '';

try {
    // 1. Validar Credenciales
    $stmt = $pdo->prepare("SELECT id_usuario, password FROM usuarios WHERE nombre_usuario = ? AND id_rol = 1 AND estado = 1");
    $stmt->execute([$user]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($pass, $admin['password'])) {
        throw new Exception("Credenciales de autorización inválidas.");
    }

    // 2. Iniciar Transacción ÚNICA
    $pdo->beginTransaction();

    if ($accion === 'PROCESAR') {
        
        // A. Cambiar estado del Lote
        $sqlLote = "UPDATE lotes_liquidaciones SET 
                    estado = 'Procesado', 
                    id_user_autoriza = ?, 
                    fecha_autorizacion = NOW() 
                    WHERE id_lote = ? AND estado = 'Pendiente'";
        $stmtLote = $pdo->prepare($sqlLote);
        $stmtLote->execute([$admin['id_usuario'], $id_lote]);

        if ($stmtLote->rowCount() === 0) {
            throw new Exception("El lote no existe o ya ha sido procesado/anulado.");
        }

        // B. Actualizar liquidaciones de agentes a 'Pagado'
        $pdo->prepare("UPDATE liquidaciones_haberes SET estado = 'Pagado' WHERE id_lote = ?")
            ->execute([$id_lote]);
            
        // C. Actualizar pagos a terceros a 'Pagado'
        $pdo->prepare("UPDATE liquidaciones_terceros SET estado = 'Pagado' WHERE id_lote = ?")
            ->execute([$id_lote]);

        $message = "Lote autorizado con éxito. Las liquidaciones y retenciones ahora figuran como PAGADAS.";

    } elseif ($accion === 'ANULAR') {
        
        // A. Cambiar estado del Lote y registrar motivo
        $sqlAnular = "UPDATE lotes_liquidaciones SET 
                      estado = 'Anulado', 
                      id_user_autoriza = ?, 
                      fecha_autorizacion = NOW(),
                      motivo_anulacion = ?
                      WHERE id_lote = ?";
        $pdo->prepare($sqlAnular)->execute([$admin['id_usuario'], $motivo, $id_lote]);

        // B. Anular liquidaciones individuales
        $pdo->prepare("UPDATE liquidaciones_haberes SET estado = 'Anulado' WHERE id_lote = ?")
            ->execute([$id_lote]);

        // C. Anular pagos a terceros
        $pdo->prepare("UPDATE liquidaciones_terceros SET estado = 'Anulado' WHERE id_lote = ?")
            ->execute([$id_lote]);

        $message = "El lote ha sido anulado y los agentes están disponibles nuevamente.";
    } else {
        throw new Exception("Acción no reconocida.");
    }

    // 3. Confirmar cambios y enviar respuesta
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    // Si algo falló, deshacemos todo lo que estuviera a medias
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}