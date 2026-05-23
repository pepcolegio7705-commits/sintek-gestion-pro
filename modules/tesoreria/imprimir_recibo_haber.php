<?php
/**
 * GENERADOR DE RECIBOS DE HABERES PRO - SINTEK GESTIÓN
 * Formato: Oficio / Legal - Seguridad: Firma Digital (Hash) + QR
 * PHP 8.2 Compatible
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../fpdf/fpdf.php';
require_once '../../phpqrcode/qrlib.php';

// 1. SEGURIDAD Y DESENCRIPTACIÓN
$token = $_GET['id'] ?? '';
$id_liquidacion = desencriptar_url($token);

if (!$id_liquidacion || !is_numeric($id_liquidacion)) {
    die("Acceso denegado: Firma digital inválida.");
}

$formato = $_GET['f'] ?? 'A4'; 

try {
    // 2. DATOS DE LA INSTITUCIÓN
    $stmt_inst = $pdo->query("SELECT * FROM configuracion WHERE id_config = 1");
    $inst = $stmt_inst->fetch(PDO::FETCH_ASSOC);

    // 3. DATOS DE LIQUIDACIÓN (Uso de COALESCE para asegurar carga de datos personales)
    $sql_cab = "SELECT l.*, 
                COALESCE(p.apellido, s.apellido, '') as apellido, 
                COALESCE(p.nombre, s.nombre, '') as nombre, 
                COALESCE(p.dni, s.dni, '') as dni, 
                COALESCE(p.cuil, s.cuil, '') as cuil, 
                COALESCE(p.legajo, s.legajo, '') as legajo, 
                COALESCE(p.fecha_ingreso, s.fecha_ingreso, '') as fecha_ingreso,
                a.nombre_area
                FROM liquidaciones_haberes l
                LEFT JOIN profesores p ON (l.id_persona = p.id_profesor AND l.tipo_persona = 'Profesor')
                LEFT JOIN personal_staff s ON (l.id_persona = s.id_staff AND l.tipo_persona = 'Staff')
                LEFT JOIN areas a ON (COALESCE(p.id_area, s.id_area) = a.id_area)
                WHERE l.id_liquidacion = ?";

    $stmt = $pdo->prepare($sql_cab);
    $stmt->execute([$id_liquidacion]);
    $liq = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$liq) throw new Exception("No se encontró la liquidación.");

    // 4. DESGLOSE DE TERCEROS
    $stmt_ter = $pdo->prepare("SELECT * FROM liquidaciones_terceros WHERE id_liquidacion_origen = ?");
    $stmt_ter->execute([$id_liquidacion]);
    $terceros = $stmt_ter->fetchAll(PDO::FETCH_ASSOC);

    // 5. GENERAR QR Y HASH DE SEGURIDAD
    $clave_secreta = "Sintek_Secret_Key_2026"; // Sal para el hash
    $huella_digital = hash('sha256', $liq['uuid_liquidacion'] . $liq['monto_neto'] . $clave_secreta);

    $info_qr = "ID:" . $liq['uuid_liquidacion'] . "|NETO:$" . $liq['monto_neto'] . "|HASH:" . substr($huella_digital, 0, 8);
    ob_start();
    QRcode::png($info_qr, null, QR_ECLEVEL_L, 3);
    $img_qr = ob_get_contents();
    ob_end_clean();

} catch (Exception $e) { die("Error: " . $e->getMessage()); }

// --- FUNCIÓN: MONTO A LETRAS ---
function montoALetras($numero) {
    $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
    $enteros = floor($numero);
    $centavos = round(($numero - $enteros) * 100);
    $texto = $formatter->format($enteros);
    return mb_strtoupper($texto) . " CON " . str_pad($centavos, 2, "0", STR_PAD_LEFT) . "/100";
}

class PDF extends FPDF {
    private function decode($txt) { 
        return mb_convert_encoding($txt ?? '', 'ISO-8859-1', 'UTF-8'); 
    }

    function SetDash($black=null, $white=null) {
        if($black !== null) $s = sprintf('[%.3F %.3F] 0 d', $black*$this->k, $white*$this->k);
        else $s = '[] 0 d';
        $this->_out($s);
    }

    function Recibo($y, $tipo_copia, $liq, $terceros, $img_qr, $inst, $huella) {
        $this->SetY($y);
        $this->Rect(10, $y, 190, 138); // Incrementamos levemente para el hash
        
        // --- CABECERA ---
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 12, $y + 3, 18);
        }
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(10, $y + 3);
        $this->Cell(190, 5, $this->decode(mb_strtoupper($inst['nombre_institucion'])), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 7);
        $this->Cell(190, 3, $this->decode($inst['domicilio'] . " - " . $inst['localidad']), 0, 1, 'C');
        $this->Cell(190, 3, $this->decode("Tel: " . $inst['telefono'] . " | CUIT: " . $inst['cuit']), 0, 1, 'C');

        $this->SetXY(175, $y + 3);
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(20, 5, $tipo_copia, 1, 0, 'C', true);

        // --- DATOS AGENTE (REFORZADO) ---
        $this->SetXY(10, $y + 18);
        $this->SetFillColor(245, 245, 245);
        $this->SetFont('Arial', 'B', 8);
        
        $nom = trim(($liq['apellido'] ?? '') . ", " . ($liq['nombre'] ?? ''));
        $this->Cell(110, 6, $this->decode(" APELLIDO Y NOMBRE: " . ($nom != "," ? $nom : "SIN DATOS")), 1, 0, 'L', true);
        $this->Cell(80, 6, " CUIL: " . ($liq['cuil'] ?: 'S/D'), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(40, 5, " LEGAJO: " . ($liq['legajo'] ?: 'S/D'), 1);
        $f_ing = (!empty($liq['fecha_ingreso']) && $liq['fecha_ingreso'] != '0000-00-00') ? date('d/m/Y', strtotime($liq['fecha_ingreso'])) : '--';
        $this->Cell(50, 5, " INGRESO: " . $f_ing, 1);
        $this->Cell(50, 5, " AREA: " . $this->decode($liq['nombre_area'] ?? 'GENERAL'), 1);
        $this->Cell(50, 5, " PERIODO: " . str_pad($liq['mes_liquidado'], 2, "0", STR_PAD_LEFT) . "/" . $liq['anio_liquidado'], 1, 1);

        // --- TABLA DE CONCEPTOS ---
        $this->Ln(2);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(15, 6, "COD", 1, 0, 'C', true);
        $this->Cell(105, 6, "CONCEPTO", 1, 0, 'C', true);
        $this->Cell(35, 6, "HABERES", 1, 0, 'C', true);
        $this->Cell(35, 6, "DESCUENTOS", 1, 1, 'C', true);

        $this->SetFont('Arial', '', 8);
        $basico = $liq['monto_bruto'] - ($liq['monto_antiguedad_aplicado'] + $liq['monto_presentismo'] + $liq['monto_zona_patagonica'] + $liq['monto_asignacion_hijos'] + $liq['monto_bonos_extraordinarios']);
        
        $this->Fila("001", "Sueldo Basico / Horas Catedra", $basico, 0);
        if($liq['monto_antiguedad_aplicado'] > 0) $this->Fila("002", "Antiguedad", $liq['monto_antiguedad_aplicado'], 0);
        if($liq['monto_zona_patagonica'] > 0) $this->Fila("003", "Zona Patagonica", $liq['monto_zona_patagonica'], 0);
        if($liq['monto_presentismo'] > 0) $this->Fila("004", "Presentismo", $liq['monto_presentismo'], 0);
        if($liq['monto_bonos_extraordinarios'] > 0) $this->Fila("005", "Bonos / Gratificaciones", $liq['monto_bonos_extraordinarios'], 0);
        if($liq['monto_asignacion_hijos'] > 0) $this->Fila("006", "Asignaciones Familiares", $liq['monto_asignacion_hijos'], 0);
        
        $this->Fila("100", "Aportes de Ley (Jub/OS/Seg)", 0, $liq['monto_retenciones_ley']);
        foreach($terceros as $t) { $this->Fila("200", $t['concepto'], 0, $t['monto']); }

        // --- TOTALES Y NETO ---
        $this->SetY($y + 102);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(120, 7, "TOTALES: ", 0, 0, 'R');
        $this->Cell(35, 7, "$ " . number_format($liq['monto_bruto'], 2, ',', '.'), 1, 0, 'R', true);
        $this->Cell(35, 7, "$ " . number_format($liq['monto_retenciones'], 2, ',', '.'), 1, 1, 'R', true);
        
        $this->SetY($y + 110);
        $this->SetFillColor(210, 255, 210);
        $this->Cell(100, 10, "NETO PERCIBIDO: ", 0, 0, 'R');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(90, 10, "$ " . number_format($liq['monto_neto'], 2, ',', '.'), 1, 1, 'C', true);

        // MONTO EN LETRAS
        $this->SetY($y + 120);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(190, 4, $this->decode("SON: " . montoALetras($liq['monto_neto'])), 0, 1, 'R');

        // QR, FIRMAS Y HASH
        $this->Image('data:image/png;base64,' . base64_encode($img_qr), 12, $y + 124, 11, 11, 'png');
        $this->SetXY(24, $y + 124);
        $this->SetFont('Arial', '', 6);
        $this->MultiCell(70, 2.5, $this->decode("Comprobante oficial de haberes. La firma del agente certifica la recepción del neto indicado. El código QR y el Hash aseguran la integridad del documento."));

        $this->Line(110, $y + 130, 145, $y + 130);
        $this->SetXY(110, $y + 131);
        $this->Cell(35, 4, "Firma Empleador", 0, 0, 'C');
        $this->Line(155, $y + 130, 190, $y + 130);
        $this->SetXY(155, $y + 131);
        $this->Cell(35, 4, "Firma Agente", 0, 0, 'C');

        // PIE DE SEGURIDAD (HASH)
        $this->SetY($y + 134);
        $this->SetFont('Courier', 'I', 6);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(190, 4, "FIRMA DIGITAL (HASH): " . $huella, 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    function Fila($cod, $concepto, $h, $d) {
        $this->Cell(15, 5, $cod, 0, 0, 'C');
        $this->Cell(105, 5, $this->decode($concepto), 0, 0, 'L');
        $this->Cell(35, 5, ($h > 0 ? number_format($h, 2, ',', '.') : ""), 0, 0, 'R');
        $this->Cell(35, 5, ($d > 0 ? number_format($d, 2, ',', '.') : ""), 0, 1, 'R');
    }
}

// 6. GENERACIÓN DEL ARCHIVO (OFICIO / LEGAL)
$pdf = new PDF('P', 'mm', 'Legal');
$pdf->SetAutoPageBreak(false);
$pdf->SetTitle('Recibo de Haberes - ' . $liq['apellido']);
$pdf->AddPage();

if ($formato === 'T') {
    $pdf->Recibo(8, "ORIGINAL", $liq, $terceros, $img_qr, $inst, $huella_digital);
    
    // Troquelado central
    $pdf->SetDash(1, 1);
    $pdf->Line(0, 177, 210, 177);
    $pdf->SetDash();
    
    $pdf->Recibo(185, "DUPLICADO", $liq, $terceros, $img_qr, $inst, $huella_digital);
} else {
    $pdf->Recibo(10, "ORIGINAL", $liq, $terceros, $img_qr, $inst, $huella_digital);
}

$pdf->Output('I', 'Recibo_' . ($liq['legajo'] ?: 'Haberes') . '.pdf');