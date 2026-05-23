<?php
require_once '../../core/conexion.php';
$nombre = $_POST['nombre'] ?? '';
if ($nombre) {
    $stmt = $pdo->prepare("INSERT INTO gastos_categorias (nombre_categoria, estado) VALUES (?, 1)");
    if ($stmt->execute([$nombre])) echo json_encode(['success' => true]);
}