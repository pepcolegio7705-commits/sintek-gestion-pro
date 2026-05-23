<?php
/**
 * GENERADOR DE CERTIFICACIÓN DE HABERES (VERSIÓN BLINDADA UUID)
 */
session_start();

// Ajusta las rutas de inclusión según tu estructura de carpetas
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../core/funciones.php'; // Para money_fmt o logs si los usas

verificar_permisos(['Administrador', 'Tesoreria', 'Secretaría']);
$rol = $_SESSION['rol'];

// Importamos FPDF - Asegúrate de que la ruta sea correcta
require '../../fpdf/fpdf.php'; 

// 1. CAPTURA DEL UUID (Desde la URL amigable vía .htaccess)
$uuid = $_GET['uuid'] ?? null;

// Paracaídas de rescate manual del UUID si el .htaccess falla en local
if (!$uuid) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $uuid = end($segments);
}

// Limpieza del UUID
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower($uuid));

if (!$uuid || strlen($uuid) < 30) {
    die("Identificador de agente no válido.");
}

try {
    // 2. EL PUENTE: Traducir UUID a ID real y detectar Tipo
    $stmt_find = $pdo->prepare("
        SELECT id_profesor AS id_real, 'Profesor' AS tipo, apellido, nombre, dni, cuil, fecha_ingreso FROM profesores WHERE uuid_profesor = ? 
        UNION ALL 
        SELECT id_staff AS id_real, 'Staff' AS tipo, apellido, nombre, dni, cuil, fecha_ingreso FROM personal_staff WHERE uuid_staff = ?
    ");
    $stmt_find->execute([$uuid, $uuid]);
    $agente = $stmt_find->fetch(PDO::FETCH_ASSOC);

    if (!$agente) {
        registrar_log_seguridad($pdo, 'PDF_CERT_UUID_INVALIDO', "Intento de PDF con UUID inexistente: $uuid");
        die("Agente no encontrado.");
    }

    $id_persona = $agente['id_real'];
    $tipo_persona = $agente['tipo'];
    $anio = date('Y');

    // 3. DATOS INSTITUCIONALES
    $stmt_c = $pdo->query("SELECT * FROM configuracion LIMIT 1");
    $conf = $stmt_c->fetch(PDO::FETCH_ASSOC);

    // 4. PAGOS DEL AÑO (Con JOIN a lotes para asegurar fecha)
    $stmtP = $pdo->prepare("
        SELECT lh.*, ll.fecha_autorizacion 
        FROM liquidaciones_haberes lh 
        JOIN lotes_liquidaciones ll ON lh.id_lote = ll.id_lote 
        WHERE lh.id_persona = ? AND lh.tipo_persona = ? AND lh.estado = 'Pagado' AND lh.anio_liquidado = ? 
        ORDER BY lh.mes_liquidado ASC
    ");
    $stmtP->execute([$id_persona, $tipo_persona, $anio]);
    $pagos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error al procesar la certificación: " . $e->getMessage());
}

// --- GENERACIÓN DEL PDF ---

function txt($t) { return mb_convert_encoding($t ?? '', 'ISO-8859-1', 'UTF-8'); }

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetTitle(txt("Certificación de Haberes - " . $agente['apellido']));
$pdf->AddPage();

// --- ENCABEZADO ---
// Ajusta la ruta de la imagen según tu proyecto
if (file_exists('../../assets/img/logo.png')) {
    $pdf->Image('../../assets/img/logo.png', 10, 10, 25);
}

$pdf->SetXY(40, 12);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, txt($conf['nombre_institucion']), 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->SetX(40);
$pdf->Cell(0, 4, txt($conf['domicilio'] . " | CUIT: " . $conf['cuit']), 0, 1);
$pdf->SetX(40);
$pdf->Cell(0, 4, txt("Tel: " . $conf['telefono'] . " | Email: " . $conf['email_contacto']), 0, 1);
$pdf->Line(10, 35, 200, 35);

$pdf->Ln(25);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, txt('CERTIFICACIÓN DE HABERES'), 0, 1, 'C');
$pdf->Ln(10);

// --- CUERPO ---
$pdf->SetFont('Arial', '', 11);
$cuerpo = "La Dirección de " . $conf['nombre_institucion'] . " certifica que el/la Sr/a. " . 
          strtoupper($agente['apellido'] . " " . $agente['nombre']) . ", DNI N° " . $agente['dni'] . 
          ", C.U.I.L. N° " . ($agente['cuil'] ?: 'S/D') . ", se desempeña como " . strtoupper($tipo_persona) . 
          " desde el " . date('d/m/Y', strtotime($agente['fecha_ingreso'])) . 
          ". A continuación se detallan las remuneraciones percibidas en el presente ciclo lectivo $anio:";

$pdf->MultiCell(0, 7, txt($cuerpo), 0, 'J');
$pdf->Ln(10);

// --- TABLA ---

$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 8, 'MES', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'BRUTO', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'RET. LEY', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'RET. JUDICIAL', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'NETO', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$total_neto = 0;
$meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];

foreach ($pagos as $p) {
    $pdf->Cell(30, 7, txt($meses[$p['mes_liquidado']]), 1, 0, 'L');
    $pdf->Cell(40, 7, "$ ".number_format($p['monto_bruto'], 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(40, 7, "$ ".number_format($p['monto_retenciones_ley'], 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(40, 7, "$ ".number_format($p['monto_retenciones_judiciales'], 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(40, 7, "$ ".number_format($p['monto_neto'], 2, ',', '.'), 1, 1, 'R');
    $total_neto += $p['monto_neto'];
}

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(150, 8, 'TOTAL ACUMULADO NETO PERCIBIDO ', 1, 0, 'R', true);
$pdf->Cell(40, 8, "$ ".number_format($total_neto, 2, ',', '.'), 1, 1, 'R', true);

// --- CIERRE ---
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 10, txt("Rawson, Chubut, a los " . date('d') . " días del mes de " . $meses[date('n')] . " de " . date('Y') . "."), 0, 1);

$pdf->Ln(25);
$pdf->Line(130, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetX(130); 
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, 'Firma y Sello Direccion', 0, 1, 'C');

$pdf->Output('I', 'Certificado_Haberes_'.$agente['dni'].'.pdf');