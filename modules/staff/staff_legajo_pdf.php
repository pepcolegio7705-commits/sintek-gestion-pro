<?php
/**
 * REPORTE: FICHA INTEGRAL DE LEGAJO (STAFF) - VERSIÓN UUID
 */
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

require_once '../../core/conexion.php'; 
require_once '../../core/seguridad.php'; 
require_once '../../fpdf/fpdf.php'; 

/**
 * Función de conversión optimizada para FPDF
 */
function convertir($texto) {
    $t = $texto ?? '';
    return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}

// Verificación de Seguridad
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    die("Acceso no autorizado.");
}

verificar_permisos(['Administrador', 'Secretaría']);

// 1. CAPTURA DEL UUID (Desde el .htaccess)
$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $uuid = end($segments);
}

// Limpieza básica
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower($uuid));

if (empty($uuid)) die("Error: Identificador no proporcionado.");

// 2. BUSQUEDA DEL AGENTE (Traducción UUID -> Datos)
$stmt_staff = $pdo->prepare("SELECT s.*, a.nombre_area 
                             FROM personal_staff s 
                             LEFT JOIN areas a ON s.id_area = a.id_area 
                             WHERE s.uuid_staff = :uuid LIMIT 1");
$stmt_staff->execute([':uuid' => $uuid]);
$s = $stmt_staff->fetch(PDO::FETCH_ASSOC);

if (!$s) die("Personal no encontrado o identificador inválido.");

$id_staff_real = $s['id_staff']; // Puente interno para retenciones

// 3. CONSULTA DE RETENCIONES (Usando el ID real)
$stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff' AND activo = 1");
$stmt_ret->execute([$id_staff_real]);
$retenciones = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

// --- CLASE FPDF PERSONALIZADA ---
class PDF extends FPDF {
    function Header() {
        // Ajustamos ruta de logo relativa a modules/staff/
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 22);
        }
        $this->SetXY(35, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(150, 6, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 6, convertir("'RAÍCES DEL SABER'"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetTextColor(100);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(150, 5, convertir('FICHA INTEGRAL DEL LEGAJO - PERSONAL ADMINISTRATIVO / STAFF'), 0, 1, 'L');
        $this->Ln(10);
    }

    function SectionTitle($label) {
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0, 51, 102);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, '  ' . convertir($label), 0, 1, 'L', true);
        $this->SetTextColor(0);
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, convertir('Legajo Personal Staff - Sintek Premium - Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// --- CONSTRUCCIÓN DEL DOCUMENTO ---
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// I. Datos Personales
$pdf->SectionTitle('I. DATOS DEL AGENTE');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Nombre:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, convertir($s['apellido'] . ', ' . $s['nombre']), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'DNI / CUIL:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, $s['dni'] . ' / ' . ($s['cuil'] ?: '---'), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Nacionalidad:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, convertir($s['nacionalidad']), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Contacto:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, $s['telefono'] . ' | ' . $s['email_personal'], 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, convertir('Dirección:'), 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(0, 6, convertir($s['direccion']), 0, 1);
$pdf->Ln(4);

// II. Información Laboral
$pdf->SectionTitle('II. SITUACIÓN DE REVISTA Y ESCALAFÓN');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Legajo Nro:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(60, 6, $s['legajo'], 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Área:', 0); $pdf->SetFont('Arial', 'B', 9); $pdf->Cell(0, 6, convertir($s['nombre_area']), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Fecha Ingreso:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(60, 6, date('d/m/Y', strtotime($s['fecha_ingreso'])), 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Contratación:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(0, 6, str_replace('_', ' ', $s['tipo_contratacion']), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Sueldo Base:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(60, 6, '$ ' . number_format($s['sueldo_base'], 2, ',', '.'), 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Estado:', 0); 
if ($s['activo'] == 1) { $pdf->SetTextColor(0,100,0); $pdf->Cell(0, 6, 'ACTIVO', 0, 1); }
else { $pdf->SetTextColor(200,0,0); $pdf->Cell(0, 6, 'INACTIVO / BAJA', 0, 1); }
$pdf->SetTextColor(0);
$pdf->Ln(4);

// III. Información Financiera
$pdf->SectionTitle('III. INFORMACIÓN FINANCIERA');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'CBU:', 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(60, 6, ($s['cbu'] ?: 'FALTA CARGAR CBU'), 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Banco:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(0, 6, ($s['banco'] ?: '---'), 0, 1);
$pdf->Ln(4);

// IV. Retenciones Judiciales
$pdf->SectionTitle('IV. OFICIOS Y EMBARGOS');
if (empty($retenciones)) {
    $pdf->SetFont('Arial', 'I', 9); $pdf->Cell(0, 6, 'No se registran retenciones judiciales.', 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    $pdf->Cell(45, 6, 'Expediente', 1, 0, 'C', true);
    $pdf->Cell(70, 6, 'Beneficiario', 1, 0, 'C', true);
    $pdf->Cell(40, 6, 'Tipo Calculo', 1, 0, 'C', true);
    $pdf->Cell(35, 6, 'Valor', 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 8);
    foreach ($retenciones as $r) {
        $pdf->Cell(45, 6, $r['nro_expediente'], 1);
        $pdf->Cell(70, 6, convertir($r['beneficiario_nombre']), 1);
        $pdf->Cell(40, 6, $r['tipo_calculo'], 1, 0, 'C');
        $simbolo = ($r['tipo_calculo'] == 'Porcentaje') ? ' %' : ' $';
        $pdf->Cell(35, 6, $r['valor'] . $simbolo, 1, 1, 'R');
    }
}
$pdf->Ln(4);

// V. Legajo Digital
$pdf->SectionTitle('V. CARGA FAMILIAR Y LEGAJO DIGITAL');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'Cantidad de Hijos:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(40, 6, $s['cantidad_hijos'], 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'DDJJ Verificada:', 0);
if ($s['hijos_verificados']) { $pdf->SetTextColor(0,100,0); $pdf->Cell(0, 6, 'SI', 0, 1); }
else { $pdf->SetTextColor(200,0,0); $pdf->Cell(0, 6, 'NO', 0, 1); }
$pdf->SetTextColor(0);
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(0, 5, 'DOCUMENTACION PRESENTADA EN FORMATO DIGITAL:', 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(40, 5, '[ ' . ($s['ruta_pdf_dni'] ? 'X' : ' ') . ' ] DNI Escaneado', 0);
$pdf->Cell(40, 5, '[ ' . ($s['ruta_pdf_cv'] ? 'X' : ' ') . ' ] C.V.', 0);
$pdf->Cell(40, 5, '[ ' . ($s['ruta_pdf_titulo'] ? 'X' : ' ') . ' ] Titulo', 0);
$pdf->Cell(40, 5, '[ ' . ($s['ruta_pdf_hijos'] ? 'X' : ' ') . ' ] DDJJ Hijos', 0, 1);

// Firma
$pdf->Ln(20);
$pdf->Line(130, $pdf->GetY(), 190, $pdf->GetY());
$pdf->SetX(130);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(60, 5, 'Firma y Sello Administrativo', 0, 0, 'C');

$pdf->Output('I', 'Ficha_Staff_' . $s['apellido'] . '.pdf');