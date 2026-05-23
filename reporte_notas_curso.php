<?php
require('fpdf/fpdf.php');
require('conexion.php');
date_default_timezone_set('America/Argentina/Buenos_Aires');

$id_espacio = isset($_GET['id_espacio']) ? (int)$_GET['id_espacio'] : 0;
$ciclo = isset($_GET['ciclo']) ? (int)$_GET['ciclo'] : date("Y");

// 1. Obtener datos del Espacio y Carrera para el encabezado
$stmtHeader = $pdo->prepare("SELECT e.nombre_espacio, c.nombre_carrera 
                             FROM espacios_curriculares e 
                             JOIN carreras c ON e.id_carrera = c.id_carrera 
                             WHERE e.id_espacio = ?");
$stmtHeader->execute([$id_espacio]);
$info = $stmtHeader->fetch(PDO::FETCH_ASSOC);

if (!$info) { die("Espacio curricular no encontrado."); }

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class PDF extends FPDF {
    function Header() {
        // Logo
        if (file_exists('img/logo.png')) {
            $this->Image('img/logo.png', 10, 8, 25);
        }
        
        // Encabezado institucional (Estilo Analítico)
        $this->SetXY(38, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0); 
        $this->Cell(150, 7, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'L');
        $this->SetX(38);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 8, convertir("'RAÍCES DEL SABER'"), 0, 1, 'L');
        $this->SetX(38);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(150, 7, convertir('PLANILLA GENERAL DE CALIFICACIONES'), 0, 1, 'L');
        $this->Ln(15);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir('Página ') . $this->PageNo() . '/{nb} - Sistema de Gestión Académica', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- CUADRO DE REFERENCIA DEL CURSO ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(235, 235, 235);
$pdf->Cell(0, 8, convertir("DATOS DEL CURSO Y CICLO LECTIVO"), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(140, 9, convertir(" Carrera: " . $info['nombre_carrera']), 1, 0);
$pdf->Cell(50, 9, convertir(" Ciclo Lectivo: " . $ciclo), 1, 1);
$pdf->Cell(190, 9, convertir(" Espacio Curricular: " . $info['nombre_espacio']), 1, 1);
$pdf->Ln(8);

// --- TABLA DE ALUMNOS ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(25, 8, "DNI", 1, 0, 'C', true);
$pdf->Cell(75, 8, "Apellido y Nombre", 1, 0, 'C', true);
$pdf->Cell(30, 8, "Condicion", 1, 0, 'C', true);
$pdf->Cell(15, 8, "Nota", 1, 0, 'C', true);
$pdf->Cell(25, 8, "Libro/Folio", 1, 0, 'C', true);
$pdf->Cell(20, 8, "Fecha", 1, 1, 'C', true);

// Consulta SQL idéntica a la del sistema para consistencia
$sql = "SELECT a.dni, a.apellido, a.nombre, c.nota_final, c.condicion, c.fecha_registro, a.libro_matriz, a.folio_matriz
        FROM inscripciones_espacios ie
        INNER JOIN alumnos a ON ie.id_alumno = a.id_alumno
        LEFT JOIN calificaciones c ON (c.id_alumno = a.id_alumno AND c.id_espacio = ie.id_espacio)
        WHERE ie.id_espacio = ? AND YEAR(ie.fecha_inscripcion) = ? AND a.activo = 1
        ORDER BY a.apellido ASC, a.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_espacio, $ciclo]);

$pdf->SetFont('Arial', '', 8);
while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Evitar que la tabla se rompa si hay muchos alumnos (salto de página automático)
    if($pdf->GetY() > 260) $pdf->AddPage();

    $pdf->Cell(25, 7, $r['dni'], 1, 0, 'C');
    $pdf->Cell(75, 7, convertir(substr($r['apellido'] . ", " . $r['nombre'], 0, 40)), 1, 0, 'L');
    
    $cond = $r['condicion'] ? strtoupper($r['condicion']) : 'SIN CARGAR';
    $pdf->Cell(30, 7, convertir($cond), 1, 0, 'C');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(15, 7, ($r['nota_final'] ?? '-'), 1, 0, 'C');
    $pdf->SetFont('Arial', '', 8);
    
    $libFol = ($r['libro_matriz'] ? $r['libro_matriz'] : '-') . " / " . ($r['folio_matriz'] ? $r['folio_matriz'] : '-');
    $pdf->Cell(25, 7, $libFol, 1, 0, 'C');
    
    $fecha = ($r['fecha_registro'] && $r['fecha_registro'] != '0000-00-00') ? date('d/m/Y', strtotime($r['fecha_registro'])) : '-';
    $pdf->Cell(20, 7, $fecha, 1, 1, 'C');
}

$pdf->Ln(15);
$pdf->SetFont('Arial', 'I', 9);
$pdf->Cell(0, 10, convertir("Documento generado el: " . date('d/m/Y H:i') . " hs."), 0, 1, 'L');

$pdf->Output('I', 'Planilla_Notas_' . $ciclo . '.pdf');