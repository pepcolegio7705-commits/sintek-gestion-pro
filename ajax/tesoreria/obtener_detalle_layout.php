<?php
session_start();
require_once '../../core/conexion.php';

$id_banco = (int)($_GET['id'] ?? 0);

if (!$id_banco) {
    echo json_encode(['success' => false, 'error' => 'ID no válido']);
    exit;
}

try {
    // 1. Obtener cabecera
    $stmtB = $pdo->prepare("SELECT * FROM bancos_config WHERE id_banco = ?");
    $stmtB->execute([$id_banco]);
    $banco = $stmtB->fetch(PDO::FETCH_ASSOC);

    if (!$banco) throw new Exception("Banco no encontrado.");

    // 2. Obtener columnas
    $stmtC = $pdo->prepare("SELECT * FROM bancos_layout_columnas WHERE id_banco = ? ORDER BY orden ASC");
    $stmtC->execute([$id_banco]);
    $columnas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'banco' => $banco,
        'columnas' => $columnas
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}