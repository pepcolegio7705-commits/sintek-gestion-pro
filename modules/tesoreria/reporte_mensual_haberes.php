<?php
require('fpdf/fpdf.php');
require('conexion.php');

function fix_text($text) {
    return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
}

$meses_n = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : 0;
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$estado_filtro = $_GET['estado'] ?? '';
$dni = $_GET['dni'] ?? ''; 
$id_p = isset($_GET['id_profesor']) ? (int)$_GET['id_profesor'] : 0;

if ($mes == 0 || $anio == 0) {
    die("Debe seleccionar Mes y Año.");
}

// Consulta SQL con JOIN a modos_pago
$sql = "SELECT l.*, p.apellido, p.nombre, p.dni, m.nombre_modo 
        FROM liquidaciones_haberes l
        JOIN profesores p ON l.id_profesor = p.id_profesor
        LEFT JOIN modos_pago m ON l.id_modo_pago = m.id_modo
        WHERE l.mes_liquidado = :m AND l.anio_liquidado = :a";

if ($estado_filtro != '') { $sql .= " AND l.estado = :e"; }
if ($id_p > 0) { $sql .= " AND l.id_profesor = :idp"; } 
elseif ($dni != '') { $sql .= " AND p.dni = :dni"; }

$sql .= " ORDER BY p.apellido ASC";

$stmt = $pdo->prepare($sql);
$params = [':m' => $mes, ':a' => $anio];
if ($estado_filtro != '') { $params[':e'] = $estado_filtro; }
if ($id_p > 0) { $params[':idp'] = $id_p; }
elseif ($dni != '') { $params[':dni'] = $dni; }

$stmt->execute($params);
$liquidaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    function Header() {
        global $dni, $id_p, $meses_n, $mes, $anio, $estado_filtro;
        $this->SetFont('Arial', 'B', 14);
        $titulo = (!empty($dni) || $id_p > 0) ? 'DETALLE DE HABERES POR DOCENTE' : 'RESUMEN MENSUAL DE LIQUIDACIONES';
        $this->Cell(0, 10, fix_text($titulo), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 10);
        $texto_periodo = "Periodo: " . ($meses_n[$mes] ?? 'N/A') . " / " . $anio;
        if($estado_filtro) $texto_periodo .= " - Filtro: " . $estado_filtro;
        $this->Cell(0, 5, fix_text($texto_periodo), 0, 1, 'C');
        $this->Ln(5);
        
        // --- CABECERA DE TABLA RE-AJUSTADA (Total 190mm) ---
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(12, 8, 'ID', 1, 0, 'C', true);       // 12
        $this->Cell(58, 8, 'Docente', 1, 0, 'L', true);  // 70
        $this->Cell(22, 8, 'DNI', 1, 0, 'C', true);      // 92
        $this->Cell(25, 8, 'Fecha', 1, 0, 'C', true);    // 117
        $this->Cell(25, 8, 'Modo', 1, 0, 'C', true);     // 142
        $this->Cell(23, 8, 'Estado', 1, 0, 'C', true);   // 165
        $this->Cell(25, 8, 'Neto', 1, 1, 'R', true);     // 190
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

if (count($liquidaciones) == 0) {
    $pdf->Cell(0, 10, fix_text('No se encontraron registros.'), 1, 1, 'C');
} else {
    $total_real = 0;
    foreach ($liquidaciones as $l) {
        $es_anulado = ($l['estado'] == 'Anulado');
        
        if ($es_anulado) {
            $pdf->SetTextColor(160, 160, 160); // Gris para anulados
        } else {
            $pdf->SetTextColor(0, 0, 0);
            $total_real += $l['monto_neto'];
        }

        $pdf->Cell(12, 7, $l['id_liquidacion'], 1, 0, 'C');
        $pdf->Cell(58, 7, fix_text($l['apellido'] . ", " . $l['nombre']), 1, 0, 'L');
        $pdf->Cell(22, 7, $l['dni'], 1, 0, 'C');
        $pdf->Cell(25, 7, date('d/m/Y', strtotime($l['fecha_pago'])), 1, 0, 'C');
        $pdf->Cell(25, 7, fix_text($l['nombre_modo'] ?? 'N/A'), 1, 0, 'C');
        $pdf->Cell(23, 7, fix_text($l['estado']), 1, 0, 'C');
        $pdf->Cell(25, 7, number_format($l['monto_neto'], 2, ',', '.'), 1, 1, 'R');
    }
    
    // Fila de Totales
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(165, 10, fix_text('TOTAL NETO EFECTIVO (Solo Pagados):'), 1, 0, 'R');
    $pdf->SetFillColor(220, 255, 220); // Verde suave
    $pdf->Cell(25, 10, "$ " . number_format($total_real, 2, ',', '.'), 1, 1, 'R', true);
    
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(0, 5, fix_text("* Los montos en gris (Anulados) no se computan en el total efectivo."), 0, 1, 'L');
}

$pdf->Output('I', "Reporte_Haberes_{$mes}_{$anio}.pdf");