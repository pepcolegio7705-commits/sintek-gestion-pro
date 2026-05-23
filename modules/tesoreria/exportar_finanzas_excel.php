<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

$mes = $_GET['m'] ?? date('m');
$anio = $_GET['a'] ?? date('Y');
$meses_n = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// 1. OBTENER TOTALES PARA LOS KPIs
$stmt_caja = $pdo->prepare("SELECT SUM(total) FROM facturas WHERE estado = 'Pagado' AND MONTH(fecha_emision) = :m AND YEAR(fecha_emision) = :a");
$stmt_caja->execute([':m' => $mes, ':a' => $anio]);
$recaudacion = $stmt_caja->fetchColumn() ?? 0;

$stmt_becas = $pdo->prepare("SELECT SUM(monto_descuento) FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :m AND YEAR(f.fecha_emision) = :a");
$stmt_becas->execute([':m' => $mes, ':a' => $anio]);
$total_becas = $stmt_becas->fetchColumn() ?? 0;

$stmt_gastos_total = $pdo->prepare("SELECT SUM(monto) FROM gastos WHERE estado = 'Pagado' AND MONTH(fecha_gasto) = :m AND YEAR(fecha_gasto) = :a");
$stmt_gastos_total->execute([':m' => $mes, ':a' => $anio]);
$total_gastos = $stmt_gastos_total->fetchColumn() ?? 0;

$bruto = $recaudacion + $total_becas;
$liquido = $recaudacion - $total_gastos;

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Dashboard_Financiero_{$mes}_{$anio}.xls");
?>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style>
    .bg-header { background-color: #003366; color: white; font-weight: bold; }
    .bg-subheader { background-color: #f2f2f2; font-weight: bold; }
    .text-red { color: #dc3545; }
    .text-green { color: #198754; font-weight: bold; }
</style>

<table border="0">
    <tr>
        <td colspan="7" style="font-size: 20px; font-weight: bold; color: #003366;">DASHBOARD FINANCIERO - <?php echo strtoupper($meses_n[(int)$mes]) . " " . $anio; ?></td>
    </tr>
    <tr><td></td></tr>
    
    <tr align="center">
        <td style="border: 1px solid #000; background-color: #0d6efd; color: white;"><b>BRUTO INST.</b></td>
        <td style="border: 1px solid #000; background-color: #0dcaf0; color: white;"><b>BECAS TOTAL</b></td>
        <td style="border: 1px solid #000; background-color: #198754; color: white;"><b>RECAUDACIÓN REAL</b></td>
        <td style="border: 1px solid #000; background-color: #dc3545; color: white;"><b>EGRESOS (GASTOS)</b></td>
        <td style="border: 1px solid #000; background-color: #000; color: white;"><b>LÍQUIDO NETO</b></td>
    </tr>
    <tr align="center" style="font-size: 14px;">
        <td style="border: 1px solid #000;">$ <?php echo number_format($bruto, 2, ',', '.'); ?></td>
        <td style="border: 1px solid #000;">$ <?php echo number_format($total_becas, 2, ',', '.'); ?></td>
        <td style="border: 1px solid #000;">$ <?php echo number_format($recaudacion, 2, ',', '.'); ?></td>
        <td style="border: 1px solid #000;">$ <?php echo number_format($total_gastos, 2, ',', '.'); ?></td>
        <td style="border: 1px solid #000; font-weight: bold;">$ <?php echo number_format($liquido, 2, ',', '.'); ?></td>
    </tr>
    
    <tr><td></td></tr>

    <tr class="bg-header">
        <th colspan="7" align="left">1. DETALLE DE INGRESOS (RECAUDACIÓN)</th>
    </tr>
    <tr class="bg-subheader">
        <th style="border: 1px solid #ccc;">Fecha</th>
        <th style="border: 1px solid #ccc;">Alumno</th>
        <th style="border: 1px solid #ccc;">Concepto</th>
        <th style="border: 1px solid #ccc;">P. Sugerido</th>
        <th style="border: 1px solid #ccc;">Beca</th>
        <th style="border: 1px solid #ccc;">Monto Cobrado</th>
        <th style="border: 1px solid #ccc;">Método</th>
    </tr>
    <?php
    $stmt_det = $pdo->prepare("SELECT f.fecha_emision, a.apellido, a.nombre, cp.nombre_concepto, cp.monto_sugerido, fd.monto_descuento, fd.monto_cobrado, mp.nombre_modo
                               FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura
                               JOIN alumnos a ON f.id_alumno = a.id_alumno
                               JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                               JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
                               WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :m AND YEAR(f.fecha_emision) = :a");
    $stmt_det->execute([':m' => $mes, ':a' => $anio]);
    while($r = $stmt_det->fetch()): ?>
    <tr>
        <td style="border: 1px solid #eee;"><?php echo date('d/m/Y', strtotime($r['fecha_emision'])); ?></td>
        <td style="border: 1px solid #eee;"><?php echo $r['apellido'].", ".$r['nombre']; ?></td>
        <td style="border: 1px solid #eee;"><?php echo $r['nombre_concepto']; ?></td>
        <td style="border: 1px solid #eee;">$ <?php echo number_format($r['monto_sugerido'], 2, ',', '.'); ?></td>
        <td style="border: 1px solid #eee;" class="text-red">$ <?php echo number_format($r['monto_descuento'], 2, ',', '.'); ?></td>
        <td style="border: 1px solid #eee;" class="text-green">$ <?php echo number_format($r['monto_cobrado'], 2, ',', '.'); ?></td>
        <td style="border: 1px solid #eee;"><?php echo $r['nombre_modo']; ?></td>
    </tr>
    <?php endwhile; ?>

    <tr><td></td></tr>

    <tr class="bg-header" style="background-color: #dc3545;">
        <th colspan="7" align="left">2. DESGLOSE DE EGRESOS (GASTOS)</th>
    </tr>
    <tr class="bg-subheader">
        <th style="border: 1px solid #ccc;">Fecha</th>
        <th colspan="3" style="border: 1px solid #ccc;">Descripción del Gasto</th>
        <th style="border: 1px solid #ccc;">Categoría</th>
        <th style="border: 1px solid #ccc;">Monto Pagado</th>
        <th style="border: 1px solid #ccc;">Método</th>
    </tr>
    <?php
    // Usamos la tabla gastos que confirmaste
    $stmt_g = $pdo->prepare("SELECT g.fecha_gasto, g.descripcion, g.monto, mp.nombre_modo, cg.nombre_categoria 
                             FROM gastos g 
                             LEFT JOIN modos_pago mp ON g.id_modo_pago = mp.id_modo
                             LEFT JOIN gastos_categorias cg ON g.id_cat_gasto = cg.id_cat_gasto
                             WHERE g.estado = 'Pagado' AND MONTH(g.fecha_gasto) = :m AND YEAR(g.fecha_gasto) = :a
                             ORDER BY g.fecha_gasto ASC");
    $stmt_g->execute([':m' => $mes, ':a' => $anio]);
    while($g = $stmt_g->fetch()): ?>
    <tr>
        <td style="border: 1px solid #eee;"><?php echo date('d/m/Y', strtotime($g['fecha_gasto'])); ?></td>
        <td colspan="3" style="border: 1px solid #eee;"><?php echo $g['descripcion']; ?></td>
        <td style="border: 1px solid #eee;"><?php echo $g['nombre_categoria'] ?? 'General'; ?></td>
        <td style="border: 1px solid #eee; font-weight: bold; color: #dc3545;">$ <?php echo number_format($g['monto'], 2, ',', '.'); ?></td>
        <td style="border: 1px solid #eee;"><?php echo $g['nombre_modo'] ?? 'Efectivo'; ?></td>
    </tr>
    <?php endwhile; ?>
    
    <tr style="background-color: #fdd; font-weight: bold;">
        <td colspan="5" align="right">TOTAL GASTOS:</td>
        <td style="border: 1px solid #000;">$ <?php echo number_format($total_gastos, 2, ',', '.'); ?></td>
        <td></td>
    </tr>
</table>