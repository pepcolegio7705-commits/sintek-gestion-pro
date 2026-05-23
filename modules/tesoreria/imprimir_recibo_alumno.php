<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría', 'Tesoreria']);
require_once '../../fpdf/fpdf.php'; 
require_once '../../phpqrcode/qrlib.php';

function u($txt) {
    return mb_convert_encoding($txt ?? '', 'ISO-8859-1', 'UTF-8');
}

class PDF_Sintek extends FPDF {
    function Watermark($txt, $color = 'gris') {
        $this->SetFont('Arial', 'B', 45);
        // Si es anulado, usamos un tono rojizo muy suave, si es copia un gris tenue
        if($color == 'rojo') {
            $this->SetTextColor(255, 200, 200); 
        } else {
            $this->SetTextColor(245, 245, 245); 
        }
        
        $this->RotatedText(35, 115, $txt, 45);
        $this->SetTextColor(0); 
    }

    function RotatedText($x, $y, $txt, $angle) {
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', 
            cos(deg2rad($angle)), sin(deg2rad($angle)), -sin(deg2rad($angle)), cos(deg2rad($angle)), 
            $x*$this->k, ($this->h-$y)*$this->k, -$x*$this->k, -($this->h-$y)*$this->k));
        $this->Text($x, $y, $txt);
        $this->_out('Q');
    }
}

$uuid = $_GET['uuid'] ?? null;
$es_reimpresion = (isset($_GET['tipo']) && $_GET['tipo'] === 'reimpresion');

$sql = "SELECT f.*, a.nombre, a.apellido, a.dni, mp.nombre_modo,
               (SELECT c.nombre_carrera FROM alumnos_carreras ac 
                JOIN carreras c ON ac.id_carreras = c.id_carrera 
                WHERE ac.id_alumno = f.id_alumno LIMIT 1) as carrera
        FROM facturas f
        INNER JOIN alumnos a ON f.id_alumno = a.id_alumno
        INNER JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
        WHERE f.uuid_factura = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uuid]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) die("Error: Comprobante no encontrado.");

// LÓGICA DE MARCA DE AGUA
$texto_marca = "";
$color_marca = "gris";

if ($f['estado'] === 'Anulado') {
    $texto_marca = "RECIBO ANULADO";
    $color_marca = "rojo";
} elseif ($es_reimpresion) {
    $texto_marca = "COPIA DE RECIBO";
    $color_marca = "gris";
}

$stmt_d = $pdo->prepare("SELECT fd.*, cp.nombre_concepto FROM factura_detalle fd
                         INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                         WHERE fd.id_factura = ?");
$stmt_d->execute([$f['id_factura']]);
$detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

function dibujarRecibo($pdf, $f, $detalles, $y_off, $tipo_doc, $texto_marca, $color_marca) {
    // Si hay texto para la marca de agua, se dibuja primero
    if (!empty($texto_marca)) {
        $pdf->Watermark($texto_marca, $color_marca);
    }

    if (file_exists('../../img/logo.png')) {
        $pdf->Image('../../img/logo.png', 10, 8 + $y_off, 28);
    }

    $pdf->SetXY(42, 10 + $y_off);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(90, 7, u("INSTITUTO 'RAÍCES DEL SABER'"), 0, 0);
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(65, 7, u($tipo_doc), 1, 1, 'C');
    
    $pdf->SetXY(42, 16 + $y_off);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, u("GESTIÓN ACADÉMICA SINTEK - COMPROBANTE OFICIAL"), 0, 1);
    
    $pdf->SetXY(10, 32 + $y_off);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(100, 5, u("RECIBO: ") . $f['nro_factura'], 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(90, 5, u("EMISIÓN: ") . date('d/m/Y H:i', strtotime($f['fecha_emision'])), 0, 1, 'R');
    
    $pdf->Line(10, 39 + $y_off, 200, 39 + $y_off);

    $pdf->SetY(42 + $y_off);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(245, 245, 245); 
    $pdf->Cell(190, 6, u(" DATOS DEL ALUMNO / CARRERA"), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 8, u(" " . $f['apellido'] . ", " . $f['nombre'] . " (DNI: " . $f['dni'] . ")"), 'LR', 1);
    $pdf->Cell(190, 8, u(" CARRERA: " . ($f['carrera'] ?? 'CURSOS LIBRES / INSTITUCIONAL')), 'LRB', 1);

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(235, 235, 235);
    $pdf->Cell(90, 7, u("CONCEPTO"), 1, 0, 'C', true);
    $pdf->Cell(40, 7, u("PERÍODO"), 1, 0, 'C', true);
    $pdf->Cell(30, 7, u("MODO"), 1, 0, 'C', true);
    $pdf->Cell(30, 7, u("SUBTOTAL"), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    foreach ($detalles as $d) {
        $pdf->Cell(90, 8, u(" " . $d['nombre_concepto']), 'LRB', 0);
        $pdf->Cell(40, 8, u($d['ciclo_lectivo'] ?? date('Y')), 'RB', 0, 'C');
        $pdf->Cell(30, 8, u($f['nombre_modo']), 'RB', 0, 'C');
        $pdf->Cell(30, 8, "$ " . number_format($d['monto_cobrado'], 2, ',', '.'), 'RB', 1, 'R');
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(160, 10, u("TOTAL PAGADO "), 1, 0, 'R');
    $pdf->Cell(30, 10, "$ " . number_format($f['total'], 2, ',', '.'), 1, 1, 'R');

    $pdf->Ln(6);
    $y_q = $pdf->GetY();
    $hash = strtoupper(substr(hash('sha256', $f['uuid_factura']), 0, 16));
    
    $qr_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_' . $f['id_factura'] . '.png';
    QRcode::png("VERIF: $hash | RECIBO: " . $f['nro_factura'] . " | ESTADO: " . $f['estado'], $qr_file, QR_ECLEVEL_L, 3);
    if (file_exists($qr_file)) {
        $pdf->Image($qr_file, 10, $y_q, 20);
        unlink($qr_file);
    }

    $pdf->SetXY(34, $y_q + 2);
    $pdf->SetFont('Courier', 'B', 8);
    $pdf->Cell(0, 4, u("HASH DE VALIDACIÓN: ") . $hash, 0, 1);
    $pdf->SetX(34);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(0, 4, "UUID: " . $f['uuid_factura'], 0, 1);
    
    // Si está anulado, agregamos una nota pequeña al pie
    if($f['estado'] === 'Anulado') {
        $pdf->SetX(34);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(200, 0, 0);
        $pdf->Cell(0, 4, u("ESTE COMPROBANTE NO TIENE VALIDEZ LEGAL POR ANULACIÓN"), 0, 1);
        $pdf->SetTextColor(0);
    }
}

$pdf = new PDF_Sintek('P', 'mm', 'A4');
$pdf->AddPage();

dibujarRecibo($pdf, $f, $detalles, 0, "ORIGINAL - CLIENTE", $texto_marca, $color_marca);

$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(0, 148, 210, 148);

dibujarRecibo($pdf, $f, $detalles, 145, "COPIA - ADMINISTRACIÓN", $texto_marca, $color_marca);

$pdf->Output('I', 'Recibo_' . $f['nro_factura'] . '.pdf');