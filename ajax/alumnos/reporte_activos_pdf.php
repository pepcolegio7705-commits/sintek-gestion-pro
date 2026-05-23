<?php
/**
 * REPORTE PDF: PADRÓN DE ALUMNOS ACTIVOS - SINTEK
 * Ubicación: /ajax/alumnos/reporte_activos_pdf.php
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('memory_limit', '512M'); 
set_time_limit(300); 

// 1. Cargamos el núcleo (subimos dos niveles)
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// 2. Cargamos FPDF desde assets
require_once '../../fpdf/fpdf.php';

// Verificación de acceso
verificar_permisos(['Administrador', 'Secretaría']);

class PDF extends FPDF {
    // Función de conversión compatible con PHP 8
    function txt($texto) {
        if ($texto === null || $texto === '') return '';
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    function Header() {
        // Logo (Ruta ajustada a la raíz desde ajax/alumnos/)
        if(file_exists('../../assets/img/logo.png')) { 
            $this->Image('../../assets/img/logo.png', 10, 8, 22); 
        }

        $this->SetFont('Arial', 'B', 12);
        // Usamos la constante NOM_INST definida en config.php
        $this->Cell(0, 8, $this->txt(NOM_INST), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, $this->txt('"SISTEMA GESTIÓN ACADÉMICA"'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->Ln(2);
        $this->Cell(0, 10, $this->txt('PADRÓN DE ALUMNOS ACTIVOS'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, $this->txt('Fecha de emisión: ') . date('d/m/Y H:i'), 0, 1, 'R');
        $this->Ln(3);
        
        // Encabezado de Tabla
        $this->SetFillColor(0, 51, 102); // Azul Institucional
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(20, 8, 'Legajo', 1, 0, 'C', true);
        $this->Cell(30, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(110, 8, $this->txt('Apellido y Nombre'), 1, 0, 'C', true);
        $this->Cell(30, 8, $this->txt('Situación'), 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, $this->txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Crear PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 9);

// CONSULTA FILTRADA SOLO POR ACTIVOS
try {
    $query = "SELECT legajo, dni, apellido, nombre FROM alumnos WHERE activo = 1 ORDER BY apellido ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();

    $fill = false;
    $pdf->SetFillColor(245, 245, 245); // Color para filas intercaladas

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nombreCompleto = $pdf->txt($row['apellido'] . ', ' . $row['nombre']);
        $pdf->Cell(20, 7, $row['legajo'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 7, $row['dni'], 1, 0, 'C', $fill);
        $pdf->Cell(110, 7, $nombreCompleto, 1, 0, 'L', $fill);
        $pdf->Cell(30, 7, 'ACTIVO', 1, 1, 'C', $fill);
        $fill = !$fill;
    }

    if (ob_get_length()) ob_clean();
    $pdf->Output('I', 'Alumnos_Activos.pdf');
    exit;

} catch (PDOException $e) {
    exit("Error al generar el reporte: " . $e->getMessage());
}