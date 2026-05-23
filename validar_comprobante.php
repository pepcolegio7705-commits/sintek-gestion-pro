<?php
require 'conexion.php';
$tipo = $_GET['tipo'] ?? '';
$valor = $_GET['valor'] ?? '';

$columna = ($tipo === 'nro_cheque') ? 'nro_cheque' : 'nro_transferencia';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM liquidaciones_haberes WHERE $columna = ? AND estado != 'Anulado'");
$stmt->execute([$valor]);
echo json_encode(['existe' => $stmt->fetchColumn() > 0]);