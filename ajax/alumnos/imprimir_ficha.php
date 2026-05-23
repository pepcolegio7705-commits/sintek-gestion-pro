<?php
/**
 * GENERADOR DE PDF: FICHA DE ALUMNO PROFESIONAL - SINTEK
 * Ubicación: /ajax/alumnos/imprimir_ficha.php
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. Cargamos el núcleo
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../core/funciones.php'; // Para registrar_log_seguridad

// 2. Cargamos FPDF
require_once '../../fpdf/fpdf.php'; 

// Verificación de acceso
if (!isset($_SESSION['loggedin'])) {
    exit("Acceso denegado");
}

// 3. Captura del UUID (desde la URL amigable configurada en .htaccess)
$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    exit("Identificador (UUID) no proporcionado");
}

/**
 * Función de conversión para PHP 8 y FPDF (ISO-8859-1)
 */
function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// 4. Búsqueda por UUID
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE uuid_alumno = ? LIMIT 1");
$stmt->execute([$uuid]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$a) {
    // Si no existe, registramos el incidente en logs_seguridad
    registrar_log_seguridad($pdo, 'PDF_INEXISTENTE', "Intento de acceder a ficha con UUID inválido: $uuid");
    exit("Alumno no encontrado");
}

// --- CLASE EXTENDIDA DE FPDF ---
class PDF extends FPDF {
    function Header() {
        // Título principal
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 10, convertir('FICHA DE INSCRIPCIÓN Y LEGAJO'), 0, 1, 'C');
        
        // Subtítulo
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, convertir('Sistema de Gestión Institucional - Sintek Premium'), 0, 1, 'C');
        $this->Ln(8);
    }
    
    function SectionTitle($label, $icon = '') {
        $this->SetFillColor(52, 58, 64);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, convertir("  " . $label), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, convertir('Página ' . $this->PageNo() . '/{nb} - Ficha generada el: ' . date('d/m/Y H:i')), 0, 0, 'C');
    }
}

// --- GENERACIÓN DEL DOCUMENTO ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// --- SECCIÓN 1: DATOS PERSONALES Y MATRIZ ---
$pdf->SectionTitle('1. DATOS PERSONALES Y ACADÉMICOS');

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Legajo: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(40, 7, $a['legajo'], 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(30, 7, 'Libro Matriz: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(30, 7, convertir($a['libro_matriz'] ?? '---'), 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(30, 7, 'Folio Matriz: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(30, 7, convertir($a['folio_matriz'] ?? '---'), 0, 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Apellido: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, convertir($a['apellido']), 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Nombre: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, convertir($a['nombre']), 0, 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'DNI: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, $a['dni'], 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'CUIL: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, $a['cuil'] ?? '---', 0, 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Estado Civil: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, convertir($a['estado_civil'] ?? '---'), 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Telefono: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, $a['telefono'] ?? '---', 0, 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Email: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, $a['email'] ?? '---', 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Localidad: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(70, 7, convertir($a['localidad'] . ' - ' . $a['direccion']), 0, 1);
$pdf->Ln(5);

// --- SECCIÓN 2: INFORMACIÓN SOCIO-LABORAL ---
$pdf->SectionTitle('2. INFORMACIÓN SOCIO-LABORAL');
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 7, convertir('Situación Laboral: '), 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(55, 7, convertir($a['situacion_laboral'] ?? '---'), 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(40, 7, 'Lugar de Trabajo: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(55, 7, convertir($a['lugar_trabajo'] ?? '---'), 0, 1);

$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(25, 7, 'Hijos: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(35, 7, $a['hijos'] ?? '0', 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(35, 7, 'Pers. a cargo: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(35, 7, $a['personas_a_cargo'] ?? '0', 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(30, 7, 'Obra Social: ', 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(30, 7, convertir($a['obra_social'] ?? '---'), 0, 1);
$pdf->Ln(5);

// --- SECCIÓN 3: INFORMACIÓN ECONÓMICA (BECAS) ---
$pdf->SectionTitle('3. BENEFICIOS Y BECAS');
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(60, 7, convertir('Porcentaje Beca Mensualidad: '), 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(35, 7, ($a['beca_mensualidad'] ?? '0') . ' %', 0);
$pdf->SetFont('Arial', 'B', 10); $pdf->Cell(60, 7, convertir('Porcentaje Beca Matrícula: '), 0);
$pdf->SetFont('Arial', '', 10); $pdf->Cell(35, 7, ($a['beca_matricula'] ?? '0') . ' %', 0, 1);
$pdf->Ln(5);

// --- SECCIÓN 4: ESTADO DEL LEGAJO (DOCUMENTACIÓN) ---
$pdf->SectionTitle('4. ESTADO DE DOCUMENTACIÓN DIGITAL');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(63, 7, 'Documento Nacional Identidad', 0);
$pdf->Cell(63, 7, 'Titulo Secundario/Superior', 0);
$pdf->Cell(64, 7, 'Certificado Aptitud Fisica', 0, 1);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(63, 7, (!empty($a['pdf_dni']) ? '[ SI - PRESENTADO ]' : '[ NO - PENDIENTE ]'), 0);
$pdf->Cell(63, 7, (!empty($a['pdf_titulo']) ? '[ SI - PRESENTADO ]' : '[ NO - PENDIENTE ]'), 0);
$pdf->Cell(64, 7, (!empty($a['pdf_aptitud']) ? '[ SI - PRESENTADO ]' : '[ NO - PENDIENTE ]'), 0, 1);

$pdf->Ln(15);

// --- SECCIÓN FIRMAS ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 40, '', 'B', 0); // Línea para firma 1
$pdf->Cell(5, 40, '', 0, 0);   // Espacio
$pdf->Cell(90, 40, '', 'B', 1); // Línea para firma 2

$pdf->Cell(95, 8, 'Firma del Alumno / Tutor', 0, 0, 'C');
$pdf->Cell(5, 8, '', 0, 0);
$pdf->Cell(90, 8, 'Aclaracion y Sello Institucional', 0, 0, 'C');

// Salida del PDF
$pdf->Output('I', 'Ficha_' . $a['dni'] . '.pdf');