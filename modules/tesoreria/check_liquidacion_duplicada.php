<?php
require 'conexion.php';
$id_p = (int)$_POST['id_p'];
$mes = (int)$_POST['mes'];
$anio = (int)$_POST['anio'];

$stmt = $pdo->prepare("SELECT id_liquidacion FROM liquidaciones_haberes WHERE id_profesor = ? AND mes_liquidado = ? AND anio_liquidado = ? AND estado <> 'Anulado'");
$stmt->execute([$id_p, $mes, $anio]);
$meses_n = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

echo json_encode([
    'existe' => (bool)$stmt->fetch(),
    'mes_nombre' => $meses_n[$mes]
]);