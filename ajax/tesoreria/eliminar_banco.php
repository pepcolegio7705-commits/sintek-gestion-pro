<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

$id_banco = (int)($_POST['id'] ?? 0);

if (!$id_banco) {
    echo json_encode(['success' => false, 'error' => 'ID de banco no válido.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verificar si existen lotes relacionados
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM lotes_liquidaciones WHERE id_banco = ?");
    $stmtCheck->execute([$id_banco]);
    $cantidadLotes = $stmtCheck->fetchColumn();

    if ($cantidadLotes > 0) {
        // EXISTEN LOTES: No borramos nada, solo desactivamos el banco padre
        $stmt = $pdo->prepare("UPDATE bancos_config SET estado = 0 WHERE id_banco = ?");
        $stmt->execute([$id_banco]);
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'mensaje' => 'El banco tiene historial de liquidaciones. Se ha desactivado pero se conserva su configuración por integridad.'
        ]);
    } else {
        // NO EXISTEN LOTES: Borrado explícito y manual
        
        // Primero borramos todas las columnas relacionadas
        $delColumnas = $pdo->prepare("DELETE FROM bancos_layout_columnas WHERE id_banco = ?");
        $delColumnas->execute([$id_banco]);

        // Luego borramos el banco
        $delBanco = $pdo->prepare("DELETE FROM bancos_config WHERE id_banco = ?");
        $delBanco->execute([$id_banco]);

        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'mensaje' => 'Se ha eliminado el banco y toda su configuración de columnas exitosamente.'
        ]);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'error' => 'Error al procesar la eliminación: ' . $e->getMessage()
    ]);
}