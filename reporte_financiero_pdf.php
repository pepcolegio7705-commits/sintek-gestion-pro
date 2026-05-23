<?php
session_start();
require 'conexion.php';
require 'seguridad.php';
require 'fpdf/fpdf.php';

verificar_permisos(['Administrador']);

// 1. PARÁMETROS Y LOGICA DE DATOS (REUTILIZADA DEL DASHBOARD)
$mes_actual = isset($_GET['m']) ? $_GET['m'] : date('m');
$anio_actual = isset($_GET['a']) ? $_GET['a'] : date('Y');
$meses_nombres = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 
                  7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];

// --- CONSULTAS MENSUALES ---
$stmt = $pdo->prepare("SELECT SUM(total) FROM facturas WHERE estado = 'Pagado' AND MONTH(fecha_emision) = :m AND YEAR(fecha_emision) = :a");
$stmt->execute([':m' => $mes_actual, ':a' => $anio_actual]);
$recaudacion_mes = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(monto) FROM gastos WHERE estado = 'Pagado' AND MONTH(fecha_gasto) = :m AND YEAR(fecha_gasto) = :a");
$stmt->execute([':m' => $mes_actual, ':a' => $anio_actual]);
$egresos_mes = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(fd.monto_descuento) FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :m AND YEAR(f.fecha_emision) = :a");
$stmt->execute([':m' => $mes_actual, ':a' => $anio_actual]);
$becas_mes = $stmt->fetchColumn() ?? 0;

// --- CONSULTAS ANUALES ---
$stmt = $pdo->prepare("SELECT SUM(total) FROM facturas WHERE estado = 'Pagado' AND YEAR(fecha_emision) = :a");
$stmt->execute([':a' => $anio_actual]);
$recaudacion_anio = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(monto) FROM gastos WHERE estado = 'Pagado' AND YEAR(fecha_gasto) = :a");
$stmt->execute([':a' => $anio_actual]);
$egresos_anio = $stmt->fetchColumn() ?? 0;

$stmt = $pdo->prepare("SELECT SUM(fd.monto_descuento) FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura WHERE f.estado = 'Pagado' AND YEAR(f.fecha_emision) = :a");
$stmt->execute([':a' => $anio_actual]);
$becas_anio = $stmt->fetchColumn() ?? 0;

// 2. CLASE PDF PERSONALIZADA
class PDF extends FPDF {
    function Header() {
        if (file_exists('img/logo.png')) $this->Image('img/logo.png', 10, 8, 25);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'REPORTE FINANCIERO INSTITUCIONAL', 0, 0, 'C');
        $this->Ln(20);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, mb_convert_encoding('Página ', 'ISO-8859-1', 'UTF-8') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Cuadro Informativo
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 10, mb_convert_encoding("Período: " . $meses_nombres[$mes_actual] . " " . $anio_actual, 'ISO-8859-1', 'UTF-8'), 1, 1, 'C', true);
$pdf->Ln(5);

// 3. TABLA COMPARATIVA (MES VS ANUAL)
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 10, "CONCEPTO", 1, 0, 'C', true);
$pdf->Cell(60, 10, "MES ACTUAL", 1, 0, 'C', true);
$pdf->Cell(60, 10, "ACUMULADO ANUAL", 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);

// Filas de datos
$pdf->Cell(70, 10, "Bruto (Real + Becas)", 1, 0, 'L');
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_mes + $becas_mes, 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_anio + $becas_anio, 2, ',', '.'), 1, 1, 'R');

$pdf->Cell(70, 10, "Becas Otorgadas", 1, 0, 'L');
$pdf->Cell(60, 10, "- $ " . number_format($becas_mes, 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(60, 10, "- $ " . number_format($becas_anio, 2, ',', '.'), 1, 1, 'R');

$pdf->Cell(70, 10, mb_convert_encoding("Recaudación Real (Ingresos)", 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_mes, 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_anio, 2, ',', '.'), 1, 1, 'R');

$pdf->Cell(70, 10, "Egresos (Gastos)", 1, 0, 'L');
$pdf->Cell(60, 10, "- $ " . number_format($egresos_mes, 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(60, 10, "- $ " . number_format($egresos_anio, 2, ',', '.'), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(230, 240, 255);
$pdf->Cell(70, 10, "SALDO LÍQUIDO", 1, 0, 'L', true);
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_mes - $egresos_mes, 2, ',', '.'), 1, 0, 'R', true);
$pdf->Cell(60, 10, "$ " . number_format($recaudacion_anio - $egresos_anio, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(10);

// 4. DESGLOSE POR CARRERAS (MENSUAL)
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, mb_convert_encoding("Distribución de Ingresos por Carrera (Mes Actual)", 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200);
$pdf->Cell(130, 8, "CARRERA", 1, 0, 'C', true);
$pdf->Cell(60, 8, "MONTO RECAUDADO", 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$sql_carr = "SELECT c.nombre_carrera, SUM(fd.monto_cobrado) as total FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto JOIN carreras c ON cp.id_carrera = c.id_carrera WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :m AND YEAR(f.fecha_emision) = :a GROUP BY c.id_carrera";
$stmt_c = $pdo->prepare($sql_carr);
$stmt_c->execute([':m' => $mes_actual, ':a' => $anio_actual]);

while($c = $stmt_c->fetch()) {
    $pdf->Cell(130, 8, mb_convert_encoding($c['nombre_carrera'], 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
    $pdf->Cell(60, 8, "$ " . number_format($c['total'], 2, ',', '.'), 1, 1, 'R');
}

$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 9);
$pdf->MultiCell(0, 5, mb_convert_encoding("Este documento es un reporte interno de auditoría financiera generado el " . date('d/m/Y H:i') . " por " . $_SESSION['nombre_usuario'] . ".", 'ISO-8859-1', 'UTF-8'));

$pdf->Output('I', 'Reporte_Financiero_' . $meses_nombres[$mes_actual] . '.pdf');