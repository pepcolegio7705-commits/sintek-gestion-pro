<?php
// 1. Iniciar buffer de salida para evitar el error "Some data has already been output"
ob_start(); 
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once '../../core/conexion.php';
require_once '../../fpdf/fpdf.php';

$uuid = $_GET['uuid'] ?? null;
if (!$uuid) die("Error: Identificador de mesa no proporcionado.");

/**
 * Función Maestra para Caracteres Especiales (PHP 8 compatible)
 * Convierte de UTF-8 (BD) a ISO-8859-1 (FPDF)
 */
function txt($t) {
    if ($t === null || $t === '') return '';
    // Usamos mb_convert_encoding que es más robusto en PHP 8
    return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}

function notaLetras($n) {
    if ($n === "" || $n === null || $n == 0) return "---";
    $letras = [
        0=>'CERO', 1=>'UNO', 2=>'DOS', 3=>'TRES', 4=>'CUATRO', 
        5=>'CINCO', 6=>'SEIS', 7=>'SIETE', 8=>'OCHO', 9=>'NUEVE', 10=>'DIEZ'
    ];
    return $letras[(int)$n] ?? '---';
}

try {
    $inst = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $sql_m = "SELECT m.*, e.nombre_espacio, c.nombre_carrera, e.anio_cursada
              FROM mesas_examenes m
              JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
              JOIN carreras c ON m.id_carrera = c.id_carrera
              WHERE m.uuid_mesa = ?";
    $stmt_m = $pdo->prepare($sql_m);
    $stmt_m->execute([$uuid]);
    $mesa = $stmt_m->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) die("Error: La mesa no existe.");

    $sql_n = "SELECT a.apellido, a.nombre, a.dni, en.nota_final, en.resultado, en.libro, en.folio
              FROM inscripciones_examen i
              JOIN alumnos a ON i.id_alumno = a.id_alumno
              LEFT JOIN examenes_notas en ON (a.id_alumno = en.id_alumno AND en.id_mesa = i.id_mesa)
              WHERE i.id_mesa = ?
              ORDER BY a.apellido ASC";
    $stmt_n = $pdo->prepare($sql_n);
    $stmt_n->execute([$mesa['id_mesa']]);
    $alumnos = $stmt_n->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

class PDF_Final extends FPDF {
    function Header() {
        global $inst;
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 20);
        }
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(32, 10);
        // Aplicamos txt() al nombre de la institución
        $this->Cell(0, 6, txt($inst['nombre_institucion']), 0, 1, 'L');
        $this->SetFont('Arial', 'I', 8);
        $this->SetX(32);
        $this->Cell(0, 4, txt($inst['localidad'] . " - CUIT: " . $inst['cuit']), 0, 1, 'L');
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, txt("ACTA DE EXAMEN DEFINITIVA"), 'B', 1, 'C');
        $this->Ln(5);
    }
}

$pdf = new PDF_Final();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- INFORMACIÓN DE CABECERA ---
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, "CARRERA:", 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, txt($mesa['nombre_carrera']), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, "MATERIA:", 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(110, 7, txt($mesa['nombre_espacio']), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(15, 7, txt("AÑO:"), 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, $mesa['anio_cursada'] . txt("° Año"), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, "FECHA:", 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(40, 7, date('d/m/Y', strtotime($mesa['fecha_examen'])), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(30, 7, "LIBRO/FOLIO:", 0, 0, 'L', true);
$pdf->SetFont('Arial', '', 10);
$libro_folio = (!empty($alumnos) && !empty($alumnos[0]['libro'])) ? $alumnos[0]['libro'] . " / " . $alumnos[0]['folio'] : "--- / ---";
$pdf->Cell(0, 7, $libro_folio, 0, 1);
$pdf->Ln(5);

// --- TABLA ---
$pdf->SetFillColor(30, 41, 59);
$pdf->SetTextColor(255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(10, 8, "N", 1, 0, 'C', true);
$pdf->Cell(80, 8, txt("APELLIDO Y NOMBRE"), 1, 0, 'C', true);
$pdf->Cell(25, 8, "DNI", 1, 0, 'C', true);
$pdf->Cell(45, 8, txt("CALIFICACIÓN"), 1, 0, 'C', true);
$pdf->Cell(0, 8, "RESULTADO", 1, 1, 'C', true);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 9);
foreach ($alumnos as $i => $alum) {
    if ($pdf->GetY() > 240) $pdf->AddPage();
    
    $pdf->Cell(10, 8, ($i + 1), 1, 0, 'C');
    // Nombre del alumno con tildes corregidas
    $pdf->Cell(80, 8, txt($alum['apellido'] . ", " . $alum['nombre']), 1, 0, 'L');
    $pdf->Cell(25, 8, $alum['dni'], 1, 0, 'C');
    
    $nota_str = ($alum['nota_final'] > 0) ? $alum['nota_final'] . " (" . txt(notaLetras($alum['nota_final'])) . ")" : "---";
    $pdf->Cell(45, 8, $nota_str, 1, 0, 'C');
    
    $resultado = $alum['resultado'] ?? 'AUSENTE';
    $pdf->Cell(0, 8, strtoupper(txt($resultado)), 1, 1, 'C');
}

// --- FIRMAS ---
$pdf->Ln(25);
$y_firmas = $pdf->GetY();
$pdf->Line(15, $y_firmas, 65, $y_firmas); 
$pdf->Line(80, $y_firmas, 130, $y_firmas); 
$pdf->Line(145, $y_firmas, 195, $y_firmas); 
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY(15, $y_firmas + 2); $pdf->Cell(50, 4, "Presidente", 0, 0, 'C');
$pdf->SetXY(80, $y_firmas + 2); $pdf->Cell(50, 4, "Vocal 1", 0, 0, 'C');
$pdf->SetXY(145, $y_firmas + 2); $pdf->Cell(50, 4, "Vocal 2", 0, 0, 'C');

// Limpiamos el buffer y enviamos el PDF
ob_end_clean(); 
$pdf->Output('I', "Acta_Final.pdf");