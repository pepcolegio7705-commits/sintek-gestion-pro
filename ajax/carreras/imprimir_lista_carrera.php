<?php
/**
 * REPORTE PDF: LISTADO OFICIAL POR CARRERA (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/carreras/imprimir_lista_carrera.php
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');
require '../../core/conexion.php';
require_once '../../core/seguridad.php';
require '../../fpdf/fpdf.php';

// Verificamos permisos
verificar_permisos(['Administrador', 'Secretaría']);

// 1. CAPTURA SEGURA DEL UUID
$uuid_param = $_GET['uuid'] ?? '';
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid_param));

if (empty($uuid)) die("Identificador de carrera no proporcionado.");

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// 2. OBTENER DATOS DE LA CARRERA (Usando el UUID)
$stmt_car = $pdo->prepare("SELECT id_carrera, nombre_carrera FROM carreras WHERE uuid_carrera = ? AND activo = 1");
$stmt_car->execute([$uuid]);
$carrera_data = $stmt_car->fetch(PDO::FETCH_ASSOC);

if (!$carrera_data) die("Carrera no encontrada o inactiva.");

$id_carrera = $carrera_data['id_carrera'];
$nombre_carrera = $carrera_data['nombre_carrera'];

// 3. OBTENER ALUMNOS ACTIVOS (Usando el ID interno obtenido del UUID)
$sql = "SELECT DISTINCT a.dni, a.apellido, a.nombre, a.email, a.telefono 
        FROM alumnos a 
        JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno 
        WHERE ac.id_carreras = ? AND a.activo = 1 
        ORDER BY a.apellido ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_carrera]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    function Header() {
        global $nombre_carrera;
        if (file_exists('../../img/logo.png')) $this->Image('../../img/logo.png', 10, 8, 22);
        
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
        $this->Cell(150, 6, convertir("LISTADO DE ALUMNOS ACTIVOS POR CARRERA"), 0, 1, 'L');
        $this->Ln(5);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 8, convertir(" CARRERA: " . $nombre_carrera), 1, 1, 'L', true);
        $this->Ln(5);
        
        // Encabezados de tabla
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(200, 200, 200);
        $this->Cell(10, 8, 'N', 1, 0, 'C', true);
        $this->Cell(70, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
        $this->Cell(25, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(50, 8, 'EMAIL', 1, 0, 'C', true);
        $this->Cell(35, 8, 'TELEFONO', 1, 1, 'C', true);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir("Página ") . $this->PageNo() . "/{nb} - Generado el " . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

if (empty($alumnos)) {
    $pdf->Cell(0, 10, convertir("No hay alumnos activos registrados en esta carrera."), 0, 1, 'C');
} else {
    $n = 1;
    foreach ($alumnos as $a) {
        // Control de salto de página automático
        if($pdf->GetY() > 270) $pdf->AddPage();
        
        $pdf->Cell(10, 7, $n++, 1, 0, 'C');
        $pdf->Cell(70, 7, convertir($a['apellido'] . ", " . $a['nombre']), 1, 0, 'L');
        $pdf->Cell(25, 7, $a['dni'], 1, 0, 'C');
        $pdf->Cell(50, 7, convertir($a['email']), 1, 0, 'L');
        $pdf->Cell(35, 7, $a['telefono'], 1, 1, 'C');
    }
}

// Nombre del archivo de salida usando el nombre de la carrera (limpio)
$nombre_archivo = 'Lista_'.str_replace(' ', '_', $nombre_carrera).'.pdf';
$pdf->Output('I', $nombre_archivo);