<?php
session_start();

// 1. Ajuste de rutas: Subimos dos niveles para llegar al core
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../fpdf/fpdf.php'; 

// 2. Seguridad
verificar_permisos(['Administrador', 'Tesoreria', 'Secretaría']);

/**
 * 3. Captura de Parámetros
 * El ID viene de la URL amigable ($1) y el tipo también viene del .htaccess
 */
$id_staff = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tipo     = $_GET['tipo'] ?? 'Staff'; // Por defecto Staff

if ($id_staff === 0) {
    die("Error: Identificador de personal no válido.");
}

// Función auxiliar para tildes y Ñ (siempre útil en certificaciones)
function convertir($texto) {
    return mb_convert_encoding($texto ?? '', 'ISO-8859-1', 'UTF-8');
}

// 4. Consulta de datos (Ejemplo para que ya lo tengas)
$sql = "SELECT * FROM personal_staff WHERE id_staff = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_staff]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) {
    die("No se encontraron datos para generar la certificación.");
}

$id = $_GET['id'];
$tipo = $_GET['tipo'];
$anio = date('Y');

// 1. Datos Institucionales desde tabla configuracion
$stmt_c = $pdo->query("SELECT * FROM configuracion LIMIT 1");
$conf = $stmt_c->fetch(PDO::FETCH_ASSOC);

// 2. Datos Agente (Unificado para obtener CUIL)
$tabla = ($tipo == 'Profesor') ? 'profesores' : 'personal_staff';
$pk = ($tipo == 'Profesor') ? 'id_profesor' : 'id_staff';
$stmtA = $pdo->prepare("SELECT * FROM $tabla WHERE $pk = ?");
$stmtA->execute([$id]);
$agente = $stmtA->fetch(PDO::FETCH_ASSOC);

// 3. Pagos del Año con desglose de retenciones
$stmtP = $pdo->prepare("SELECT lh.*, ll.fecha_autorizacion 
    FROM liquidaciones_haberes lh 
    JOIN lotes_liquidaciones ll ON lh.id_lote = ll.id_lote 
    WHERE lh.id_persona = ? AND lh.tipo_persona = ? AND lh.estado = 'Pagado' AND lh.anio_liquidado = ? 
    ORDER BY lh.mes_liquidado ASC");
$stmtP->execute([$id, $tipo, $anio]);
$pagos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

function txt($t) { return mb_convert_encoding($t ?? '', 'ISO-8859-1', 'UTF-8'); }

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetTitle(txt("Certificación de Haberes - " . $agente['apellido']));
$pdf->AddPage();

// --- ENCABEZADO FORMAL ---
if (file_exists('img/logo.png')) {
    $pdf->Image('img/logo.png', 10, 10, 25);
}
$pdf->SetXY(40, 12);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 6, txt($conf['nombre_institucion']), 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->SetX(40);
$pdf->Cell(0, 4, txt($conf['domicilio'] . " | CUIT: " . $conf['cuit']), 0, 1);
$pdf->SetX(40);
// Usamos email_contacto según tu código previo
$pdf->Cell(0, 4, txt("Tel: " . $conf['telefono'] . " | Email: " . $conf['email_contacto']), 0, 1);
$pdf->Line(10, 35, 200, 35);

$pdf->Ln(25);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, txt('CERTIFICACIÓN DE HABERES'), 0, 1, 'C');
$pdf->Ln(10);

// --- CUERPO CON CUIL ---
$pdf->SetFont('Arial', '', 11);
$cuerpo = "La Dirección de " . $conf['nombre_institucion'] . " certifica que el/la Sr/a. " . 
          strtoupper($agente['apellido'] . " " . $agente['nombre']) . ", DNI N° " . $agente['dni'] . 
          ", C.U.I.L. N° " . ($agente['cuil'] ?: 'S/D') . ", se desempeña como " . strtoupper($tipo) . 
          " desde el " . date('d/m/Y', strtotime($agente['fecha_ingreso'])) . 
          ". A continuación se detallan las remuneraciones percibidas en el presente ciclo lectivo $anio:";

$pdf->MultiCell(0, 7, txt($cuerpo), 0, 'J');
$pdf->Ln(10);

// --- TABLA DETALLADA ---
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

// --- FECHA Y CIERRE ---
$pdf->Ln(20);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 10, txt("Rawson, Chubut, a los " . date('d') . " días del mes de " . $meses[date('n')] . " de " . date('Y') . "."), 0, 1);

$pdf->Ln(25);
$pdf->Line(130, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetX(130); 
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(60, 5, 'Firma y Sello Direccion', 0, 1, 'C');

$pdf->Output('I', 'Certificado_Haberes_'.$agente['dni'].'.pdf');