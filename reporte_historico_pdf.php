<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
// 1. Ajustes para procesos masivos
ini_set('memory_limit', '512M'); 
set_time_limit(300);             

require('fpdf/fpdf.php');
require('conexion.php');

class PDF extends FPDF {
    
    // Función auxiliar para evitar el error de Deprecated en utf8_decode
    function txt($texto) {
        // Verificamos que mb_convert_encoding exista para evitar errores
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    function Header() {
        // Logo Institucional
        if(file_exists('img/logo.png')) {
            $this->Image('img/logo.png', 10, 8, 22);
        }
        
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 8, $this->txt('INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 8, $this->txt('"RAÍCES DEL SABER"'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->Ln(2);
        $this->Cell(0, 10, $this->txt('LISTADO HISTÓRICO GENERAL DE ALUMNOS'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, $this->txt('Fecha de emisión: ') . date('d/m/Y H:i'), 0, 1, 'R');
        $this->Ln(3);
        
        // Cabecera de la tabla
        $this->SetFillColor(0, 51, 102); 
        $this->SetTextColor(255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 10);
        
        $this->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(30, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(115, 8, $this->txt('Apellido y Nombre'), 1, 0, 'C', true);
        $this->Cell(30, 8, 'Estado', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $footerText = $this->txt('Página ') . $this->PageNo() . '/{nb} - ' . $this->txt('Generado por Sistema Raíces del Saber');
        $this->Cell(0, 10, $footerText, 0, 0, 'C');
    }
}

// 2. Ejecución del PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20); 
$pdf->AddPage();
$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 9);

// 3. Consulta optimizada
$query = "SELECT id_alumno, dni, apellido, nombre, activo FROM alumnos ORDER BY apellido ASC";
$stmt = $pdo->prepare($query);
$stmt->execute();

$fill = false; 
$pdf->SetFillColor(245, 245, 245);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $estado = ($row['activo'] == 1) ? 'ACTIVO' : 'INACTIVO';
    
    // CORRECCIÓN: Usamos $pdf->txt() porque estamos fuera de la clase
    $nombreCompleto = $pdf->txt($row['apellido'] . ', ' . $row['nombre']);
    
    $pdf->Cell(15, 7, $row['id_alumno'], 1, 0, 'C', $fill);
    $pdf->Cell(30, 7, $row['dni'], 1, 0, 'C', $fill);
    $pdf->Cell(115, 7, $nombreCompleto, 1, 0, 'L', $fill);
    $pdf->Cell(30, 7, $estado, 1, 1, 'C', $fill);
    
    $fill = !$fill; 
}

// 4. Limpiar buffer de salida
if (ob_get_length()) ob_clean();

$pdf->Output('I', 'Historico_Alumnos_Completo.pdf');
exit;