<?php
/**
 * REPORTE: CERTIFICACIÓN DE SERVICIOS STAFF (CON JOIN DE ÁREAS)
 * Ubicación: /modules/staff/certificacion_staff_pdf.php
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

require_once '../../fpdf/fpdf.php';
require_once '../../phpqrcode/qrlib.php';

// 1. CAPTURA Y LIMPIEZA DE UUID
$uuid = $_GET['uuid'] ?? '';
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid));

if (empty($uuid)) die("Identificador no proporcionado.");

// 2. BUSQUEDA DEL PERSONAL CON JOIN A LA TABLA AREAS
$sql = "SELECT ps.*, a.nombre_area 
        FROM personal_staff ps
        LEFT JOIN areas a ON ps.id_area = a.id_area
        WHERE ps.uuid_staff = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$uuid]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) die("Personal no encontrado.");

// 3. DATOS INSTITUCIONALES
$conf = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$localidad_inst = !empty($conf['localidad']) ? $conf['localidad'] : "Rawson, Chubut";

// 4. CÁLCULO DE ANTIGÜEDAD
$f_ingreso = new DateTime($p['fecha_ingreso']);
$hoy = new DateTime();
$diff = $f_ingreso->diff($hoy);
$texto_antiguedad = $diff->y . " años y " . $diff->m . " meses";

function txt($t) { return mb_convert_encoding($t ?? '', 'ISO-8859-1', 'UTF-8'); }

class PDF extends FPDF {
    function Header() {
        if (file_exists('../../img/logo.png')) $this->Image('../../img/logo.png', 15, 10, 25);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(45, 12);
        $this->Cell(0, 7, txt($GLOBALS['conf']['nombre_institucion']), 0, 1);
        $this->SetFont('Arial', '', 8);
        $this->SetX(45);
        $this->Cell(0, 4, txt($GLOBALS['conf']['cuit'] . " | " . $GLOBALS['conf']['domicilio']), 0, 1);
        $this->Line(10, 40, 200, 40);
        $this->Ln(25);
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->SetMargins(20, 20, 20);
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 15, txt('CERTIFICACIÓN DE SERVICIOS'), 0, 1, 'C');
$pdf->Ln(10);

$pdf->SetFont('Arial', '', 12);
$meses_es = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];

// Redacción usando el nombre de la columna 'nombre_area' obtenida mediante el JOIN
$nombre_area = !empty($p['nombre_area']) ? $p['nombre_area'] : "Servicios Generales";

$cuerpo = "La Dirección del " . $conf['nombre_institucion'] . " certifica que el/la Sr./Sra. " . 
          $p['apellido'] . ", " . $p['nombre'] . ", DNI N° " . $p['dni'] . ", CUIL N° " . ($p['cuil'] ?? '---') . 
          ", desempeña funciones en el área de " . txt($nombre_area) . " de esta institución.\n\n" .
          "El/la agente ingresó el día " . date('d/m/Y', strtotime($p['fecha_ingreso'])) . 
          ", contando a la fecha con una antigüedad ininterrumpida de " . $texto_antiguedad . ".\n\n" .
          "A pedido de la parte interesada y para ser presentado ante las autoridades que lo requieran, se extiende la presente en la ciudad de " . 
          txt($localidad_inst) . ", el día " . date('d') . " de " . txt($meses_es[date('n')]) . " de " . date('Y') . ".";

$pdf->MultiCell(0, 10, txt($cuerpo), 0, 'J');

// Tabla Técnica (Actualizada con el Área)
$pdf->Ln(15);
$pdf->SetFillColor(240);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 8, 'FECHA INGRESO', 1, 0, 'C', true);
$pdf->Cell(60, 8, txt('ANTIGÜEDAD'), 1, 0, 'C', true);
$pdf->Cell(60, 8, txt('ÁREA / DEPARTAMENTO'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 8, date('d/m/Y', strtotime($p['fecha_ingreso'])), 1, 0, 'C');
$pdf->Cell(60, 8, txt($texto_antiguedad), 1, 0, 'C');
$pdf->Cell(60, 8, txt($nombre_area), 1, 1, 'C');

// Firmas y QR
$pdf->Ln(45);
$pdf->Line(115, $pdf->GetY(), 185, $pdf->GetY());
$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(70, 5, txt('Firma y Sello de la Autoridad'), 0, 1, 'C');

$qr_file = 'qr_staff_'.$p['uuid_staff'].'.png';
QRcode::png(BASE_URL."staff/certificacion/".$p['uuid_staff'], $qr_file);
$pdf->Image($qr_file, 15, 255, 22, 22);
if(file_exists($qr_file)) unlink($qr_file);

$pdf->SetXY(40, 260);
$pdf->SetFont('Arial', 'I', 7);
$pdf->MultiCell(100, 4, txt("Documento de carácter oficial emitido por sistema. La autenticidad puede verificarse mediante el código QR."), 0, 'L');

$pdf->Output('I', 'Certificacion_Staff_'.$p['apellido'].'.pdf');