<?php
// ajax/instancias/generar_acta_pdf.php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Ajuste de rutas para llegar al core desde ajax/instancias/
require_once '../../core/conexion.php';
require_once '../../fpdf/fpdf.php'; 

if (!isset($_GET['uuid_mesa'])) {
    die("Identificador de mesa no proporcionado.");
}

$uuid_mesa = $_GET['uuid_mesa'];

/**
 * Función corregida para evitar el error de Deprecated: utf8_decode()
 * Utiliza mb_convert_encoding para compatibilidad con PHP 8.2+
 */
function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    } else {
        // Opción de respaldo si mbstring no está activo
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    }
}

// 1. Obtener datos de la mesa por UUID
$sql_mesa = "SELECT m.*, e.nombre_espacio, e.anio_cursada, c.nombre_carrera 
             FROM mesas_examenes m
             JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
             JOIN carreras c ON m.id_carrera = c.id_carrera
             WHERE m.uuid_mesa = ?";
$stmt_mesa = $pdo->prepare($sql_mesa);
$stmt_mesa->execute([$uuid_mesa]);
$mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

if (!$mesa) die("Mesa no encontrada.");

$id_mesa = $mesa['id_mesa']; 

// 2. Obtener alumnos con calificaciones (Historial)
$sql_notas = "SELECT a.apellido, a.nombre, a.dni, c.nota_final, c.condicion, c.libro, c.folio
              FROM calificaciones c
              JOIN alumnos a ON c.id_alumno = a.id_alumno
              WHERE c.id_mesa = ?
              ORDER BY a.apellido ASC";
$stmt_notas = $pdo->prepare($sql_notas);
$stmt_notas->execute([$id_mesa]);
$alumnos = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

$es_acta_vacia = false;
if (empty($alumnos)) {
    // Si no hay notas, traemos a los inscriptos (Acta Volante Abierta)
    $sql_inscriptos = "SELECT a.apellido, a.nombre, a.dni, i.condicion 
                       FROM inscripciones_examen i
                       JOIN alumnos a ON i.id_alumno = a.id_alumno
                       WHERE i.id_mesa = ?
                       ORDER BY a.apellido ASC";
    $stmt_inscr = $pdo->prepare($sql_inscriptos);
    $stmt_inscr->execute([$id_mesa]);
    $alumnos = $stmt_inscr->fetchAll(PDO::FETCH_ASSOC);
    $es_acta_vacia = true; 
}

function notaEnLetras($n) {
    if ($n === "" || $n === null || $n == 0) return "---";
    $letras = [0=>'Cero', 1=>'Uno', 2=>'Dos', 3=>'Tres', 4=>'Cuatro', 5=>'Cinco', 6=>'Seis', 7=>'Siete', 8=>'Ocho', 9=>'Nueve', 10=>'Diez'];
    return $letras[(int)$n] ?? $n;
}

class PDF extends FPDF {
    function Header() {
        if (file_exists('../../assets/img/logo.png')) {
            $this->Image('../../assets/img/logo.png', 10, 8, 22);
        }
        $this->SetXY(35, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(150, 6, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(150, 7, convertir("'RAÍCES DEL SABER'"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(150, 6, convertir('ACTA VOLANTE DE EXÁMENES'), 0, 1, 'L');
        $this->Ln(10);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir("Página ") . $this->PageNo() . "/{nb} - Sintek Gestión", 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- CUADRO INFORMATIVO ---
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 9);

$pdf->Cell(30, 7, 'CARRERA:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 7, convertir($mesa['nombre_carrera']), 1, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 7, 'ESPACIO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(110, 7, convertir($mesa['nombre_espacio']), 1, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 7, convertir('AÑO:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 7, ($mesa['anio_cursada']) . convertir('° Año'), 1, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 7, 'FECHA:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(30, 7, date('d/m/Y', strtotime($mesa['fecha_examen'])), 1, 0, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 7, 'LLAMADO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(65, 7, convertir($mesa['llamado']), 1, 0, 'L'); 

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 7, 'LIBRO/FOLIO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$libro_folio = (!$es_acta_vacia && !empty($alumnos)) ? ($alumnos[0]['libro'] . ' / ' . $alumnos[0]['folio']) : '....... / .......';
$pdf->Cell(0, 7, $libro_folio, 1, 1, 'C');

$pdf->Ln(5);

// --- TABLA DE ALUMNOS ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(10, 8, convertir('N°'), 1, 0, 'C', true);
$pdf->Cell(75, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'DNI', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'CALIF. (Num y Letras)', 1, 0, 'C', true);
$pdf->Cell(0, 8, convertir('CONDICIÓN'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$count = 1;
foreach ($alumnos as $alum) {
    if($pdf->GetY() > 250) $pdf->AddPage();
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(75, 8, convertir($alum['apellido'] . ', ' . $alum['nombre']), 1, 0, 'L');
    $pdf->Cell(25, 8, $alum['dni'], 1, 0, 'C');
    
    if ($es_acta_vacia) {
        $pdf->Cell(45, 8, '', 1, 0, 'C'); 
        $pdf->Cell(0, 8, strtoupper(convertir($alum['condicion'] ?? 'REGULAR')), 1, 1, 'C');
    } else {
        $texto_nota = $alum['nota_final'] . ' (' . notaEnLetras($alum['nota_final']) . ')';
        $pdf->Cell(45, 8, convertir($texto_nota), 1, 0, 'C');
        $pdf->Cell(0, 8, convertir($alum['condicion']), 1, 1, 'C');
    }
}

// Rellenar filas vacías hasta 15 para completar el acta
$fill = 15 - count($alumnos);
for ($i = 0; $i < $fill; $i++) {
    if($pdf->GetY() > 250) $pdf->AddPage();
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(75, 8, '', 1, 0, 'L');
    $pdf->Cell(25, 8, '', 1, 0, 'C');
    $pdf->Cell(45, 8, '', 1, 0, 'C');
    $pdf->Cell(0, 8, '', 1, 1, 'C');
}

$pdf->Ln(15);
if($pdf->GetY() > 240) $pdf->AddPage(); 
$y_firmas = $pdf->GetY() + 10;
$pdf->Line(15, $y_firmas, 65, $y_firmas); 
$pdf->Line(80, $y_firmas, 130, $y_firmas); 
$pdf->Line(145, $y_firmas, 195, $y_firmas); 
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY(15, $y_firmas + 2); $pdf->Cell(50, 4, 'Presidente', 0, 0, 'C');
$pdf->SetXY(80, $y_firmas + 2); $pdf->Cell(50, 4, 'Vocal 1', 0, 0, 'C');
$pdf->SetXY(145, $y_firmas + 2); $pdf->Cell(50, 4, 'Vocal 2', 0, 0, 'C');

$pdf->Output('I', 'Acta_Examen_' . $uuid_mesa . '.pdf');