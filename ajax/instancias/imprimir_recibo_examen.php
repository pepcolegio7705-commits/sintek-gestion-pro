<?php
session_start();
// Ajuste de rutas: Subimos 2 niveles para llegar al core y librerías
require_once '../../core/conexion.php'; 
require_once '../../core/seguridad.php';

// Verificamos que tenga permisos de acceso a Tesorería o Secretaría
verificar_permisos(['Administrador', 'Secretaría', 'Tesorería']);
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Librerías
require_once '../../fpdf/fpdf.php'; 
require_once '../../phpqrcode/qrlib.php';

class PDF_Recibo extends FPDF {
    function SetDash($black=null, $white=null) {
        if($black!==null) $s=sprintf('[%.3F %.3F] 0 d',$black*$this->k,$white*$this->k);
        else $s='[] 0 d';
        $this->_out($s);
    }

    // Función para marcas de agua (Texto rotado)
    function RotatedText($x, $y, $txt, $angle) {
        $this->SetFont('Arial', 'B', 50);
        $this->SetTextColor(255, 200, 200); // Color rojo/rosado muy claro
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', 
            cos(deg2rad($angle)), sin(deg2rad($angle)), 
            -sin(deg2rad($angle)), cos(deg2rad($angle)), 
            $x*$this->k, ($this->h-$y)*$this->k, 
            -$x*$this->k, -($this->h-$y)*$this->k));
        $this->Text($x, $y, $txt);
        $this->_out('Q');
    }
}

// 1. Obtener Datos (Soporta ID numérico o UUID)
$id_factura = $_GET['id'] ?? null;
$uuid = $_GET['uuid'] ?? null;
$es_reimpresion = isset($_GET['tipo']) && strpos($_GET['tipo'], 'reimpresion') !== false;

$sql = "SELECT f.*, a.nombre, a.apellido, a.dni, a.uuid_alumno, mp.nombre_modo, u.nombre_usuario
        FROM facturas f
        INNER JOIN alumnos a ON f.id_alumno = a.id_alumno
        INNER JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
        LEFT JOIN usuarios u ON f.usuario_emisor = u.id_usuario ";

if ($uuid) {
    $sql .= " WHERE f.uuid_factura = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uuid]);
} else {
    $sql .= " WHERE f.id_factura = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_factura]);
}

$f = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$f) die("Error: El comprobante no existe.");

// 2. Obtener Detalle
$stmt_d = $pdo->prepare("SELECT fd.*, cp.nombre_concepto, c.nombre_carrera, 
                                e.nombre_espacio, m.llamado, m.fecha_examen as fecha_mesa
                         FROM factura_detalle fd
                         INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                         LEFT JOIN mesas_examenes m ON fd.id_referencia_mesa = m.id_mesa
                         LEFT JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                         LEFT JOIN carreras c ON m.id_carrera = c.id_carrera
                         WHERE fd.id_factura = ?");
$stmt_d->execute([$f['id_factura']]);
$detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);

$conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1")->fetch(PDO::FETCH_ASSOC);

function txt($t) { 
    if ($t === null) return '';
    return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8'); 
}

function dibujarRecibo($pdf, $f, $detalles, $y_off, $tipo_copia, $conf, $reimp) {
    
    // MARCAS DE AGUA SEGÚN ESTADO
    if ($f['estado'] === 'Anulado') {
        $pdf->RotatedText(35, 85 + $y_off, "A N U L A D O", 45);
    } elseif ($reimp) {
        $pdf->RotatedText(35, 85 + $y_off, "REIMPRESION", 45);
    }

    $pdf->SetDash();
    $pdf->SetTextColor(0);
    
    // Logo
    if (file_exists('../../assets/img/logo.png')) {
        $pdf->Image('../../assets/img/logo.png', 10, 10 + $y_off, 22);
    }
    
    // Encabezado
    $pdf->SetXY(35, 12 + $y_off);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(100, 6, txt("INSTITUTO 'RAÍCES DEL SABER'"), 0, 0, 'L');
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(65, 7, txt($tipo_copia), 1, 1, 'C');

    $pdf->SetXY(35, 18 + $y_off);
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, txt("CUIT: " . $conf['cuit_institucion'] . " | Gestión Académica Sintek"), 0, 1);
    
    $pdf->SetXY(140, 25 + $y_off);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(60, 7, txt("RECIBO: " . $f['nro_factura']), 0, 1, 'R');
    $pdf->SetX(140);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 5, txt("Fecha: " . date('d/m/Y H:i', strtotime($f['fecha_emision']))), 0, 1, 'R');

    $pdf->Line(10, 38 + $y_off, 200, 38 + $y_off);

    // Datos Alumno
    $pdf->SetY(42 + $y_off);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(190, 6, txt(' DATOS DEL ALUMNO Y EXAMEN'), 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(110, 8, txt(' ALUMNO: ' . $f['apellido'] . ', ' . $f['nombre']), 'L', 0);
    $pdf->Cell(80, 8, txt(' DNI: ' . $f['dni']), 'R', 1);
    $pdf->Cell(190, 8, txt(' CARRERA: ' . ($detalles[0]['nombre_carrera'] ?? 'N/A')), 'LRB', 1);

    // Tabla
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(80, 7, txt('MATERIA / ESPACIO'), 1, 0, 'C', true);
    $pdf->Cell(20, 7, txt('LLAMADO'), 1, 0, 'C', true);
    $pdf->Cell(30, 7, txt('FECHA MESA'), 1, 0, 'C', true);
    $pdf->Cell(30, 7, txt('MODO'), 1, 0, 'C', true);
    $pdf->Cell(30, 7, txt('SUBTOTAL'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    foreach ($detalles as $d) {
        $pdf->Cell(80, 8, txt(" " . $d['nombre_espacio']), 'LRB', 0);
        $pdf->Cell(20, 8, txt($d['llamado'] . "°"), 'RB', 0, 'C');
        $f_mesa = (!empty($d['fecha_mesa'])) ? date('d/m/Y', strtotime($d['fecha_mesa'])) : '--';
        $pdf->Cell(30, 8, $f_mesa, 'RB', 0, 'C');
        $pdf->Cell(30, 8, txt($f['nombre_modo']), 'RB', 0, 'C');
        $pdf->Cell(30, 8, "$ " . number_format($d['monto_cobrado'], 2, ',', '.'), 'RB', 1, 'R');
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(160, 10, txt('TOTAL ABONADO '), 1, 0, 'R');
    $pdf->Cell(30, 10, "$ " . number_format($f['total'], 2, ',', '.'), 1, 1, 'R');

    // QR dinámico y temporal
    $pdf->Ln(5);
    $y_qr = $pdf->GetY();
    $qr_content = "FACT: " . $f['nro_factura'] . " | DNI: " . $f['dni'];
    $qr_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qr_tmp_' . $f['id_factura'] . '.png';
    QRcode::png($qr_content, $qr_file, QR_ECLEVEL_L, 3);
    if (file_exists($qr_file)) {
        $pdf->Image($qr_file, 10, $y_qr, 20);
        unlink($qr_file);
    }

    $pdf->SetXY(32, $y_qr + 2);
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->SetTextColor(100);
    $nota = "Este recibo es el único comprobante válido para ingresar al examen.\nUsuario: " . ($f['nombre_usuario'] ?? 'Sistema') . " | ID: " . $f['id_factura'];
    if($f['estado'] === 'Anulado') $nota = "COMPROBANTE ANULADO: " . $f['motivo_anulacion'];
    
    $pdf->MultiCell(100, 3.5, txt($nota), 0, 'L');

    $pdf->Line(150, $y_qr + 15, 195, $y_qr + 15);
    $pdf->SetXY(150, $y_qr + 16);
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell(45, 4, txt('Sello y Firma Receptor'), 0, 0, 'C');
}

$pdf = new PDF_Recibo('P', 'mm', 'A4');
$pdf->AddPage();

dibujarRecibo($pdf, $f, $detalles, 0, "ORIGINAL - COMPROBANTE DE EXAMEN", $conf, $es_reimpresion);

$pdf->SetDash(1,1);
$pdf->Line(0, 148, 210, 148);

dibujarRecibo($pdf, $f, $detalles, 148, "COPIA - REGISTRO DE CAJA", $conf, $es_reimpresion);

$pdf->Output('I', 'Recibo_Examen_' . $f['nro_factura'] . '.pdf');