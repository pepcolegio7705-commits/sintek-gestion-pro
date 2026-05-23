<?php
/**
 * REPORTE PDF: LISTADO DE ALUMNOS POR MATERIA (CURSANTES)
 * Acceso vía UUID: materias/lista/[uuid]
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 
require_once '../../fpdf/fpdf.php'; 

// Verificamos permisos
verificar_permisos(['Administrador', 'Secretaría']);

// 1. Capturamos el UUID desde la URL amigable
$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    die("Error: Identificador de materia no proporcionado.");
}

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// 2. Obtener datos del espacio y su carrera usando el UUID
$sql_e = "SELECT e.id_espacio, e.nombre_espacio, e.anio_cursada, c.nombre_carrera 
          FROM espacios_curriculares e 
          JOIN carreras c ON e.id_carrera = c.id_carrera 
          WHERE e.uuid_espacio = ? AND e.activo = 1";
$stmt_e = $pdo->prepare($sql_e);
$stmt_e->execute([$uuid]);
$info = $stmt_e->fetch(PDO::FETCH_ASSOC);

if (!$info) die("Error: Materia no encontrada o inactiva.");

$id_espacio = $info['id_espacio']; // Lo necesitamos para la consulta de alumnos

// 3. Obtener alumnos inscriptos que NO tengan la materia aprobada (nota < 7)
$sql = "SELECT a.apellido, a.nombre, a.dni 
        FROM alumnos a 
        JOIN inscripciones_espacios ie ON a.id_alumno = ie.id_alumno 
        WHERE ie.id_espacio = ? 
        AND a.activo = 1 
        AND NOT EXISTS (
            SELECT 1 FROM calificaciones cal 
            WHERE cal.id_alumno = a.id_alumno 
            AND cal.id_espacio = ie.id_espacio 
            AND cal.nota_final >= 7
        )
        ORDER BY a.apellido ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_espacio]);
$alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    function Header() {
        global $info;
        
        // Logo
        $ruta_logo = '../../assets/img/logo.png'; // Verifica que esta ruta sea la correcta
        if (file_exists($ruta_logo)) {
            $this->Image($ruta_logo, 10, 8, 22);
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
        $this->Cell(150, 6, convertir("LISTADO DE ALUMNOS POR MATERIA (CURSANTES)"), 0, 1, 'L');
        $this->Ln(8);
        
        // Cuadro de Información
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(30, 7, 'CARRERA:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 7, convertir($info['nombre_carrera']), 1, 1);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 7, 'MATERIA:', 1, 0, 'L', true);
        $this->SetFont('Arial', '', 9);
        $this->Cell(110, 7, convertir($info['nombre_espacio']), 1, 0);
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(20, 7, convertir('AÑO:'), 1, 0, 'L', true);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 7, $info['anio_cursada'] . convertir('° Año'), 1, 1);
        $this->Ln(5);

        // Encabezado de Tabla
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(220, 220, 220);
        $this->Cell(10, 8, 'N', 1, 0, 'C', true);
        $this->Cell(90, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
        $this->Cell(30, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(0, 8, 'OBSERVACIONES / FIRMA', 1, 1, 'C', true);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir("Página ") . $this->PageNo() . "/{nb} - Generado el: " . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

if (empty($alumnos)) {
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 11);
    $pdf->Cell(0, 10, convertir("No se registran alumnos inscriptos cursando esta materia."), 0, 1, 'C');
} else {
    $n = 1;
    foreach ($alumnos as $a) {
        $pdf->Cell(10, 8, $n++, 1, 0, 'C');
        $pdf->Cell(90, 8, convertir($a['apellido'] . ", " . $a['nombre']), 1, 0, 'L');
        $pdf->Cell(30, 8, $a['dni'], 1, 0, 'C');
        $pdf->Cell(0, 8, '', 1, 1); // Espacio para firma
    }
}

$pdf->Output('I', 'Lista_Cursantes_' . str_replace(' ', '_', $info['nombre_espacio']) . '.pdf');