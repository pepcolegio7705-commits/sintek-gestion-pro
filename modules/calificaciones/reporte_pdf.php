<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../fpdf/fpdf.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$uuid = $_GET['uuid'] ?? null;
$ciclo = $_GET['ciclo'] ?? date('Y');

if (!$uuid) die("Acceso no autorizado.");

/**
 * 1. OBTENCIÓN DE DATOS (Configuración y Notas)
 */
try {
    // Datos de la Institución
    $conf = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Consulta de Notas con Carrera
    $sql = "SELECT cn.*, e.nombre_espacio, c.nombre_condicion, a.nombre, a.apellido, a.dni, car.nombre_carrera
            FROM cursadas_notas cn
            JOIN alumnos a ON cn.id_alumno = a.id_alumno
            JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
            JOIN carreras car ON e.id_carrera = car.id_carrera
            LEFT JOIN condiciones_alumno c ON cn.id_condicion = c.id_condicion
            WHERE a.uuid_alumno = ? AND cn.ciclo_lectivo = ?
            ORDER BY car.nombre_carrera, e.nombre_espacio ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uuid, $ciclo]);
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}

if (!$notas) die("No se encontraron registros para el ciclo " . $ciclo);

/**
 * 2. FUNCIÓN COMPATIBLE PHP 8 (Reemplaza utf8_decode)
 */
function txt($texto) {
    if (!$texto) return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

/**
 * 3. CLASE PDF PERSONALIZADA
 */
class PDF extends FPDF {
    private $inst;

    public function setInst($data) { $this->inst = $data; }

    function Header() {
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 25); 
        }

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(30); 
        $this->Cell(0, 7, txt($this->inst['nombre_institucion'] ?? 'INSTITUCIÓN'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(30);
        $this->Cell(0, 4, txt("CUIT: " . $this->inst['cuit'] . " | " . $this->inst['domicilio'] . " - " . $this->inst['localidad']), 0, 1, 'L');
        $this->Cell(30);
        $this->Cell(0, 4, txt("Tel: " . $this->inst['telefono'] . " | Email: " . $this->inst['email_contacto']), 0, 1, 'L');
        
        $this->Ln(10);
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, txt('Generado por Sistema de Gestión - ') . date('d/m/Y H:i'), 0, 0, 'L');
        $this->Cell(0, 10, txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

// Inicializar PDF
$pdf = new PDF();
$pdf->setInst($conf); // Pasamos los datos de la tabla configuracion
$pdf->AliasNbPages();
$pdf->AddPage();

// --- Título del Reporte ---
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, txt("ESTADO ACADÉMICO INDIVIDUAL"), 0, 1, 'C');
$pdf->Ln(2);

// --- Información del Alumno ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 7, txt("  DATOS DEL ESTUDIANTE"), 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 7, txt("Apellido y Nombre:"), 0, 0);
$pdf->Cell(95, 7, txt($notas[0]['apellido'] . ", " . $notas[0]['nombre']), 0, 0);
$pdf->Cell(20, 7, "DNI:", 0, 0);
$pdf->Cell(0, 7, $notas[0]['dni'], 0, 1);
$pdf->Cell(35, 7, "Ciclo Lectivo:", 0, 0);
$pdf->Cell(0, 7, $ciclo, 0, 1);
$pdf->Ln(5);

/**
 * 4. RENDERIZADO DE NOTAS POR CARRERA
 */
$carrera_actual = "";

foreach ($notas as $n) {
    // Si la carrera cambia, dibujamos un nuevo encabezado de carrera
    if ($carrera_actual != $n['nombre_carrera']) {
        $carrera_actual = $n['nombre_carrera'];
        
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 51, 102);
        $pdf->Cell(0, 8, txt("CARRERA: " . $carrera_actual), 0, 1, 'L');
        $pdf->SetTextColor(0);

        // Encabezado de tabla
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(0, 51, 102);
        $pdf->SetTextColor(255);
        $pdf->Cell(85, 7, 'ASIGNATURA', 1, 0, 'C', true);
        $pdf->Cell(12, 7, 'P1', 1, 0, 'C', true);
        $pdf->Cell(12, 7, 'P2', 1, 0, 'C', true);
        $pdf->Cell(12, 7, 'REC', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'FINAL', 1, 0, 'C', true);
        $pdf->Cell(15, 7, 'ASIST', 1, 0, 'C', true);
        $pdf->Cell(39, 7, 'ESTADO', 1, 1, 'C', true);
        $pdf->SetTextColor(0);
    }

    // Datos de la fila
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(85, 6, txt($n['nombre_espacio']), 1);
    $pdf->Cell(12, 6, ($n['parcial_1'] ?? '-'), 1, 0, 'C');
    $pdf->Cell(12, 6, ($n['parcial_2'] ?? '-'), 1, 0, 'C');
    $pdf->Cell(12, 6, ($n['recuperatorio'] ?? '-'), 1, 0, 'C');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(15, 6, ($n['nota_final_cursada'] ?? '-'), 1, 0, 'C');
    $pdf->SetFont('Arial', '', 8);
    
    $pdf->Cell(15, 6, $n['asistencia'] . '%', 1, 0, 'C');
    $pdf->Cell(39, 6, txt($n['nombre_condicion'] ?? 'CURSANDO'), 1, 1, 'C');
}

/**
 * 5. BLOQUE DE FIRMAS
 */
$pdf->Ln(25);
$pdf->SetFont('Arial', '', 8);

// Firma Secretario
$pdf->Cell(60, 0, '', 'T', 0, 'C');
$pdf->Cell(70, 0, '', 0, 0, 'C');
$pdf->Cell(60, 0, '', 'T', 1, 'C');

$pdf->Cell(60, 5, txt($conf['secretario']), 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0, 'C');
$pdf->Cell(60, 5, txt($conf['rector']), 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(60, 4, txt("Secretario Académico"), 0, 0, 'C');
$pdf->Cell(70, 4, '', 0, 0, 'C');
$pdf->Cell(60, 4, txt("Rectoría / Dirección"), 0, 1, 'C');

$pdf->Output('I', 'Reporte_' . $notas[0]['dni'] . '.pdf');