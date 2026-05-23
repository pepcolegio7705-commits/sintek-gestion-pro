<?php
session_start();
require 'conexion.php';
require 'fpdf/fpdf.php'; 

if (!isset($_SESSION['loggedin'])) {
    exit("Acceso denegado");
}

if (!isset($_GET['id'])) {
    exit("ID de inscripción no proporcionado");
}

$id_inscripcion = $_GET['id'];

$sql = "SELECT i.id_inscripcion, i.fecha_inscripcion, i.condicion, 
               a.nombre, a.apellido, a.dni, 
               c.nombre_carrera, 
               e.nombre_espacio, 
               m.fecha_examen, m.llamado
        FROM inscripciones_examen i
        JOIN alumnos a ON i.id_alumno = a.id_alumno
        JOIN mesas_examenes m ON i.id_mesa = m.id_mesa
        JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
        JOIN carreras c ON m.id_carrera = c.id_carrera
        WHERE i.id_inscripcion = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_inscripcion]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    exit("No se encontró la inscripción solicitada.");
}

// Función auxiliar para evitar el error de Deprecated
function txt($texto) {
    // Convierte de UTF-8 a ISO-8859-1 para FPDF
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        // Usamos la función txt() para los acentos y eñes
        $this->Cell(0, 10, mb_convert_encoding('COLEGIO N° 752 - "RAQUEL CHATTAH DE BEC"', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, mb_convert_encoding('Rawson - Provincia del Chubut', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, mb_convert_encoding('CONSTANCIA DE INSCRIPCIÓN A EXAMEN', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-30);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, mb_convert_encoding('Este documento es un comprobante oficial de inscripción.', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Cell(0, 5, mb_convert_encoding('Página ', 'ISO-8859-1', 'UTF-8') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Cuadro de datos del Alumno
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, mb_convert_encoding(' DATOS DEL ALUMNO', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(50, 8, 'Apellido y Nombre:', 0, 0);
$pdf->Cell(0, 8, mb_convert_encoding($data['apellido'] . ', ' . $data['nombre'], 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Cell(50, 8, 'DNI:', 0, 0);
$pdf->Cell(0, 8, $data['dni'], 0, 1);
$pdf->Cell(50, 8, 'Carrera:', 0, 0);
$pdf->Cell(0, 8, mb_convert_encoding($data['nombre_carrera'], 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Ln(5);

// Cuadro de datos de la Mesa
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, mb_convert_encoding(' DATOS DE LA MESA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(50, 8, 'Materia:', 0, 0);
$pdf->Cell(0, 8, mb_convert_encoding($data['nombre_espacio'], 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Cell(50, 8, 'Fecha de Examen:', 0, 0);
$pdf->Cell(0, 8, date('d/m/Y', strtotime($data['fecha_examen'])), 0, 1);
$pdf->Cell(50, 8, 'Llamado:', 0, 0);
$pdf->Cell(0, 8, mb_convert_encoding($data['llamado'], 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Cell(50, 8, mb_convert_encoding('Condición:', 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(0, 8, mb_convert_encoding($data['condicion'], 'ISO-8859-1', 'UTF-8'), 0, 1);
$pdf->Ln(10);

// Cuadro de Control
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, mb_convert_encoding(' INFORMACIÓN DE REGISTRO', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 8, mb_convert_encoding('Fecha de Inscripción:', 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(0, 8, date('d/m/Y H:i', strtotime($data['fecha_inscripcion'])) . ' hs', 0, 1);
$pdf->Cell(50, 8, mb_convert_encoding('Código Interno:', 'ISO-8859-1', 'UTF-8'), 0, 0);
$pdf->Cell(0, 8, 'INS-' . str_pad($data['id_inscripcion'], 6, '0', STR_PAD_LEFT), 0, 1);

$pdf->Ln(20);

// Espacio para sellos/firmas
$pdf->Cell(95, 10, '', 0, 0);
$pdf->Cell(95, 10, '------------------------------------------', 0, 1, 'C');
$pdf->Cell(95, 5, '', 0, 0);
$pdf->Cell(95, 5, mb_convert_encoding('Sello y Firma Secretaría', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

$pdf->Output('I', 'Comprobante_' . $data['dni'] . '.pdf');