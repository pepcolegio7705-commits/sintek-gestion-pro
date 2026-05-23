<?php
// formulario_inscripcion_blanco.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require 'fpdf/fpdf.php';

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
}

class PDF extends FPDF {
    function Header() {
        // 1. LOGO INSTITUCIONAL (img/logo.png)
        // Parámetros: Ruta, X, Y, Ancho (Alto se ajusta proporcional)
        if (file_exists('img/logo.png')) {
            $this->Image('img/logo.png', 12, 10, 28);
        }

        // 2. RECUADRO PARA FOTO 4X4 (Esquina derecha)
        $this->Rect(170, 10, 25, 32); 
        $this->SetXY(170, 42);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(25, 3, 'FOTO 4X4', 0, 0, 'C');

        // 3. TEXTOS DE CABECERA (Centrados entre Logo y Foto)
        $this->SetXY(42, 12);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(125, 7, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'C');
        
        $this->SetX(42);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 51, 102); // Azul institucional
        $this->Cell(125, 10, convertir("'RAÍCES DEL SABER'"), 0, 1, 'C');
        
        $this->SetX(42);
        $this->SetTextColor(0, 0, 0); 
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(125, 7, convertir('Solicitud de Inscripción - Ciclo Lectivo 2026'), 0, 1, 'C');
    }

    function SectionTitle($label) {
        $this->Ln(3);
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, ' ' . convertir($label), 0, 1, 'L', true);
        $this->Ln(1);
    }

    function Campo($label, $anchoLabel) {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($anchoLabel, 10, convertir($label), 0, 0); 
        $this->SetFont('Arial', '', 9);
        // Ajustamos los puntos para que no se pasen del margen con el nuevo padding
        $this->Cell(0, 10, '................................................................................................................................', 0, 1);
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(false); 

// --- SECCIÓN 1: DATOS ADMINISTRATIVOS ---
$pdf->SetY(48); 
$pdf->SectionTitle('USO EXCLUSIVO DE LA INSTITUCIÓN');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(63, 10, 'ID ALUMNO: _______________', 0, 0);
$pdf->Cell(63, 10, 'Nro. LEGAJO: _______________', 0, 0);
$pdf->Cell(0, 10, 'ESTADO: [  ] ACTIVO  [  ] INACTIVO', 0, 1);

// --- SECCIÓN 2: DATOS PERSONALES ---
$pdf->SectionTitle('1. DATOS PERSONALES DEL ALUMNO');
$pdf->Campo('Apellido/s:', 35);
$pdf->Campo('Nombre/s:', 35);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(35, 10, 'DNI:', 0, 0); 
$pdf->Cell(60, 10, '..........................................', 0, 0);
$pdf->Cell(20, 10, 'CUIL:', 0, 0);
$pdf->Cell(0, 10, '..........................................', 0, 1);

$pdf->Campo('Direccion:', 35);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(35, 10, 'Localidad:', 0, 0);
$pdf->Cell(60, 10, '..........................................', 0, 0);
$pdf->Cell(20, 10, 'Pais:', 0, 0);
$pdf->Cell(0, 10, '..........................................', 0, 1);

$pdf->Campo('Email:', 35);
$pdf->Campo('Telefono:', 35);

// --- SECCIÓN 3: SITUACIÓN SOCIO-LABORAL ---
$pdf->SectionTitle('2. INFORMACIÓN SOCIO-LABORAL Y SALUD');
$pdf->Campo('Situacion Laboral:', 45);
$pdf->Campo('Lugar de Trabajo:', 45);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(45, 10, 'Hijos (Cant.):', 0, 0);
$pdf->Cell(50, 10, '..........................', 0, 0);
$pdf->Cell(45, 10, 'Personas a Cargo:', 0, 0);
$pdf->Cell(0, 10, '..........................', 0, 1);
$pdf->Campo('Obra Social:', 45);

// --- SECCIÓN 4: ACADÉMICA ---
$pdf->SectionTitle('3. INSCRIPCIÓN ACADÉMICA');
$pdf->Campo('Carrera a la que aspira:', 45);
$pdf->Campo('Espacio / Materia:', 45);

// --- SECCIÓN 5: FIRMAS ---
$pdf->Ln(2);
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 5, convertir('Declaro que los datos aquí consignados son exactos y me comprometo a cumplir con el reglamento de la institución.'), 0, 'C');

$pdf->Ln(6);
$y_firma = $pdf->GetY();
$pdf->Line(20, $y_firma, 85, $y_firma); 
$pdf->Line(125, $y_firma, 190, $y_firma); 

$pdf->SetXY(20, $y_firma + 1);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(65, 5, 'Firma del Alumno', 0, 0, 'C');
$pdf->SetXY(125, $y_firma + 1);
$pdf->Cell(65, 5, convertir('Aclaración y DNI'), 0, 1, 'C');

$pdf->SetY(-18);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 10, 'Fecha de recepcion: ........ / ........ / 20........', 0, 1, 'R');

$pdf->Output('I', 'Formulario_Inscripcion_Raices.pdf');