<?php
/**
 * REPORTE: CERTIFICACIÓN DE SERVICIOS DOCENTE
 * Ubicación: /modules/profesores/certificacion_pdf.php
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

// 2. BUSQUEDA DEL DOCENTE
$stmt = $pdo->prepare("SELECT * FROM profesores WHERE uuid_profesor = ? LIMIT 1");
$stmt->execute([$uuid]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) die("Docente no encontrado.");

// 3. DATOS INSTITUCIONALES Y CONFIGURACIÓN
$conf = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// 4. CÁLCULO DE ANTIGÜEDAD (Años y Meses)
$f_ingreso = new DateTime($p['fecha_ingreso']);
$hoy = new DateTime();
$diff = $f_ingreso->diff($hoy);
$texto_antiguedad = $diff->y . " años y " . $diff->m . " meses";

// 5. OBTENCIÓN DE ÚLTIMAS HORAS CÁTEDRA LIQUIDADAS
$stmt_h = $pdo->prepare("SELECT total_horas_catedra FROM liquidaciones_haberes WHERE id_persona = ? AND tipo_persona = 'Profesor' ORDER BY id_liquidacion DESC LIMIT 1");
$stmt_h->execute([$p['id_profesor']]);
$horas = $stmt_h->fetchColumn() ?: 0;

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

// Título
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 15, txt('CERTIFICACIÓN DE SERVICIOS'), 0, 1, 'C');
$pdf->Ln(10);

// Cuerpo
$pdf->SetFont('Arial', '', 12);
$meses_es = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];

$cuerpo = "La Dirección del " . $conf['nombre_institucion'] . " certifica por medio de la presente que el/la Sr./Sra. " . 
          $p['apellido'] . ", " . $p['nombre'] . ", DNI N° " . $p['dni'] . ", CUIL N° " . ($p['cuil'] ?? '---') . 
          ", forma parte del cuerpo docente de esta institución.\n\n" .
          "El/la mencionado/a agente ingresó el día " . date('d/m/Y', strtotime($p['fecha_ingreso'])) . 
          ", registrando a la fecha una antigüedad de " . $texto_antiguedad . ".\n\n" .
          "Se deja constancia que actualmente desempeña una carga horaria de " . $horas . " Horas Cátedra.\n\n" .
          "A pedido de la parte interesada y para ser presentado ante quien corresponda, se extiende la presente en " . 
          $conf['localidad'] . ", el día " . date('d') . " de " . txt($meses_es[date('n')]) . " de " . date('Y') . ".";

$pdf->MultiCell(0, 10, txt($cuerpo), 0, 'J');

// Tabla Técnica
$pdf->Ln(15);
$pdf->SetFillColor(240);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 8, 'INGRESO', 1, 0, 'C', true);
$pdf->Cell(60, 8, txt('ANTIGÜEDAD'), 1, 0, 'C', true);
$pdf->Cell(60, 8, 'CARGA HORARIA', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 8, date('d/m/Y', strtotime($p['fecha_ingreso'])), 1, 0, 'C');
$pdf->Cell(60, 8, txt($texto_antiguedad), 1, 0, 'C');
$pdf->Cell(60, 8, $horas . " Horas", 1, 1, 'C');

// Firmas
$pdf->Ln(45);
$pdf->Line(115, $pdf->GetY(), 185, $pdf->GetY());
$pdf->SetX(115);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(70, 5, txt('Firma y Sello de la Autoridad'), 0, 1, 'C');

// QR de Validación
$qr_file = 'qr_cert_'.$p['uuid_profesor'].'.png';
QRcode::png(BASE_URL."profesores/certificacion/".$p['uuid_profesor'], $qr_file);
$pdf->Image($qr_file, 15, 255, 22, 22);
if(file_exists($qr_file)) unlink($qr_file);

$pdf->SetXY(40, 260);
$pdf->SetFont('Arial', 'I', 7);
$pdf->MultiCell(100, 4, txt("Documento validado digitalmente. La autenticidad puede ser verificada escaneando el código QR."), 0, 'L');

$pdf->Output('I', 'Certificacion_'.$p['apellido'].'.pdf');