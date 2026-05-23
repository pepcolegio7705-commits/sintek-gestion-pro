<?php
require 'conexion.php';

$mes = date('m');
$anio = date('Y');

echo "<h2>Auditoría de Cálculos - $mes/$anio</h2>";

// 1. INGRESOS REALES (Lo que entró de verdad)
$sql_ing = "SELECT COUNT(*) as cant, SUM(monto_cobrado) as total FROM factura_detalle WHERE MONTH(fecha_pagado) = $mes AND YEAR(fecha_pagado) = $anio";
$ing = $pdo->query($sql_ing)->fetch();
echo "<b>Ingresos Recaudados:</b> $" . number_format($ing['total'], 2) . " ({$ing['cant']} transacciones)<br>";

// 2. GASTOS ACTIVOS (Lo que salió de verdad)
$sql_gas = "SELECT COUNT(*) as cant, SUM(monto) as total FROM gastos WHERE MONTH(fecha_gasto) = $mes AND YEAR(fecha_gasto) = $anio AND estado = 'Pagado'";
$gas = $pdo->query($sql_gas)->fetch();
echo "<b>Egresos (Pagados):</b> $" . number_format($gas['total'], 2) . " ({$gas['cant']} comprobantes)<br>";

// 3. GASTOS ANULADOS (Lo que NO debería restarse)
$sql_anu = "SELECT COUNT(*) as cant, SUM(monto) as total FROM gastos WHERE MONTH(fecha_gasto) = $mes AND YEAR(fecha_gasto) = $anio AND estado = 'Anulado'";
$anu = $pdo->query($sql_anu)->fetch();
echo "<b>Egresos (Anulados):</b> $" . number_format($anu['total'], 2) . " (No afectan al líquido)<br>";

// 4. BECAS (El dinero que 'perdonaste')
$sql_bec = "SELECT COUNT(*) as cant, SUM(beca_mensualidad + beca_matricula) as total FROM alumnos WHERE estado = 'Activo'";
$bec = $pdo->query($sql_bec)->fetch();
echo "<b>Becas Otorgadas:</b> $" . number_format($bec['total'], 2) . " (Costo social)<br>";

echo "<hr>";
$liquido = $ing['total'] - $gas['total'];
echo "<h3>LÍQUIDO REAL EN CAJA: $" . number_format($liquido, 2) . "</h3>";