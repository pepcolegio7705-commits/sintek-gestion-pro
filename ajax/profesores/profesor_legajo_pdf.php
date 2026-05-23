<?php
/**
 * GENERACIÓN DE LEGAJO PDF (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/profesores/profesor_legajo_pdf.php
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');
session_start();

require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; // Contiene registrar_log_seguridad y limpiar_cadena
require_once '../../core/seguridad.php';
require_once '../../fpdf/fpdf.php'; 

// Verificamos permisos antes de cualquier otra acción
verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    die("Acceso no autorizado");
}

// 1. CAPTURA DEL UUID (Vía .htaccess: uuid=$1)
$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    registrar_log_seguridad($pdo, 'PDF_ACCESO_SIN_ID', 'Se intentó generar un legajo sin proporcionar un identificador.');
    die("Error: Identificador de profesor ausente.");
}

// 2. BUSQUEDA INTEGRAL DEL PROFESOR
// Buscamos por UUID para ocultar los IDs incrementales
$stmt_prof = $pdo->prepare("SELECT p.*, a.nombre_area 
                             FROM profesores p 
                             LEFT JOIN areas a ON p.id_area = a.id_area 
                             WHERE p.uuid_profesor = :uuid LIMIT 1");
$stmt_prof->execute([':uuid' => $uuid]);
$p = $stmt_prof->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    // Si el UUID no existe, registramos el posible intento de escaneo/manipulación
    registrar_log_seguridad($pdo, 'PDF_UUID_INVALIDO', "Intento de acceso a legajo inexistente con UUID: $uuid");
    die("Error: El legajo solicitado no existe.");
}

// Obtenemos el ID interno para las consultas relacionales
$id_profesor = $p['id_profesor'];

// 3. REGISTRO DE AUDITORÍA (Trazabilidad)
registrar_log_seguridad($pdo, 'GENERAR_REPORTE_PDF', "Generación de Ficha Integral de Legajo: " . $p['apellido'] . " (DNI: " . $p['dni'] . ")");

// 4. CARGA DE DATOS RELACIONADOS
// Retenciones Judiciales
$stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Profesor' AND activo = 1");
$stmt_ret->execute([$id_profesor]);
$retenciones = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

// Asignaciones Académicas
$materias = [];
if ($p['activo'] == 1) {
    $sql_asig = "SELECT e.nombre_espacio, e.anio_cursada, c.nombre_carrera, pe.horas_catedra 
                 FROM profesores_espacios pe
                 JOIN espacios_curriculares e ON pe.id_espacio = e.id_espacio
                 JOIN carreras c ON e.id_carrera = c.id_carrera
                 WHERE pe.id_profesor = :id
                 ORDER BY c.nombre_carrera, e.anio_cursada, e.nombre_espacio";
    $stmt_asig = $pdo->prepare($sql_asig);
    $stmt_asig->execute([':id' => $id_profesor]);
    $materias = $stmt_asig->fetchAll(PDO::FETCH_ASSOC);
}

// --- CLASE PDF PERSONALIZADA ---
class PDF extends FPDF {
    function Header() {
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 22);
        }
        $this->SetXY(35, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(150, 6, limpiar_cadena("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 6, limpiar_cadena("'RAÍCES DEL SABER'"), 0, 1, 'L');
        $this->SetX(35);
        $this->SetTextColor(100);
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(150, 5, limpiar_cadena('FICHA INTEGRAL DEL LEGAJO DOCENTE'), 0, 1, 'L');
        $this->Ln(10);
    }

    function SectionTitle($label) {
        $this->SetFillColor(240, 240, 240);
        $this->SetTextColor(0, 51, 102);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 7, '   ' . limpiar_cadena($label), 0, 1, 'L', true);
        $this->SetTextColor(0);
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, limpiar_cadena('Ficha de Legajo - Sintek Premium - Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- I. IDENTIDAD ---
$pdf->SectionTitle('I. IDENTIDAD Y CONTACTO');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Nombre:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, limpiar_cadena($p['apellido'] . ', ' . $p['nombre']), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'DNI / CUIL:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, $p['dni'] . ' / ' . ($p['cuil'] ?: '---'), 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Legajo:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, $p['legajo'], 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Contacto:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(100, 6, $p['telefono'] . ' | ' . $p['email'], 0, 1);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, limpiar_cadena('Dirección:'), 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(0, 6, limpiar_cadena($p['domicilio'] . ' (' . $p['localidad'] . ')'), 0, 1);
$pdf->Ln(4);

// --- II. BANCO ---
$pdf->SectionTitle('II. INFORMACIÓN FINANCIERA Y BANCARIA');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'CBU Sueldo:', 0); $pdf->SetFont('Arial', '', 10); $pdf->Cell(65, 6, ($p['cbu'] ?: 'FALTA CARGAR CBU'), 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(35, 6, 'Banco:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(0, 6, ($p['banco'] ?: '---'), 0, 1);
$pdf->Ln(4);

// --- III. RETENCIONES ---
$pdf->SectionTitle('III. OFICIOS Y RETENCIONES JUDICIALES');
if (empty($retenciones)) {
    $pdf->SetFont('Arial', 'I', 9); $pdf->Cell(0, 6, 'Sin retenciones judiciales vigentes.', 0, 1);
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
        $pdf->Cell(70, 6, limpiar_cadena($r['beneficiario_nombre']), 1);
        $pdf->Cell(40, 6, $r['tipo_calculo'], 1, 0, 'C');
        $simbolo = ($r['tipo_calculo'] == 'Porcentaje') ? ' %' : ' $';
        $pdf->Cell(35, 6, $r['valor'] . $simbolo, 1, 1, 'R');
    }
}
$pdf->Ln(4);

// --- IV. FAMILIA ---
$pdf->SectionTitle('IV. SITUACIÓN FAMILIAR Y DOCUMENTACIÓN');
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'Cantidad de Hijos:', 0); $pdf->SetFont('Arial', '', 9); $pdf->Cell(40, 6, $p['cantidad_hijos'], 0);
$pdf->SetFont('Arial', 'B', 9); $pdf->Cell(45, 6, 'DDJJ Hijos Verificada:', 0);
if ($p['hijos_verificados']) { $pdf->SetTextColor(0,100,0); $pdf->Cell(0, 6, 'SI', 0, 1); }
else { $pdf->SetTextColor(200,0,0); $pdf->Cell(0, 6, 'NO (Pendiente)', 0, 1); }
$pdf->SetTextColor(0);

$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(0, 5, 'CHECKLIST DIGITAL:', 0, 1);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(45, 5, '[ ' . ($p['ruta_pdf_dni'] ? 'X' : ' ') . ' ] DNI Escaneado', 0);
$pdf->Cell(45, 5, '[ ' . ($p['ruta_pdf_cv'] ? 'X' : ' ') . ' ] Curriculum Vitae', 0);
$pdf->Cell(45, 5, '[ ' . ($p['ruta_pdf_titulo'] ? 'X' : ' ') . ' ] Titulo Habilitante', 0);
$pdf->Cell(45, 5, '[ ' . ($p['ruta_pdf_hijos'] ? 'X' : ' ') . ' ] DDJJ Salario Fam.', 0, 1);
$pdf->Ln(4);

// --- V. ASIGNACIONES ---
$pdf->SectionTitle('V. ESPACIOS CURRICULARES Y CARGA HORARIA');
if (empty($materias)) {
    $pdf->SetFont('Arial', 'I', 9); $pdf->Cell(0, 6, 'No se registran asignaciones vigentes.', 0, 1);
} else {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(220, 220, 220);
    $w_car = 60; $w_mat = 75; $w_anio = 20; $w_hs = 35;

    $pdf->Cell($w_car, 7, 'Carrera', 1, 0, 'C', true);
    $pdf->Cell($w_mat, 7, 'Materia / Espacio Curricular', 1, 0, 'C', true);
    $pdf->Cell($w_anio, 7, limpiar_cadena('Año'), 1, 0, 'C', true);
    $pdf->Cell($w_hs, 7, 'Horas Cat.', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 8);
    $total_hs = 0;

    foreach ($materias as $m) {
        $x = $pdf->GetX(); $y = $pdf->GetY();
        $pdf->MultiCell($w_car, 6, limpiar_cadena($m['nombre_carrera']), 1, 'L');
        $y_despues_car = $pdf->GetY();
        $alto_fila = $y_despues_car - $y;

        $pdf->SetXY($x + $w_car, $y);
        $pdf->MultiCell($w_mat, 6, limpiar_cadena($m['nombre_espacio']), 1, 'L');
        $y_despues_mat = $pdf->GetY();
        if (($y_despues_mat - $y) > $alto_fila) { $alto_fila = $y_despues_mat - $y; }

        $pdf->SetXY($x, $y);
        $pdf->Cell($w_car, $alto_fila, '', 1, 0); 
        $pdf->Cell($w_mat, $alto_fila, '', 1, 0);

        $pdf->SetXY($x + $w_car + $w_mat, $y);
        $pdf->Cell($w_anio, $alto_fila, $m['anio_cursada'] . 'o', 1, 0, 'C');
        $pdf->Cell($w_hs, $alto_fila, $m['horas_catedra'], 1, 1, 'C');
        $total_hs += (float)$m['horas_catedra'];
        $pdf->SetY($y + $alto_fila);
    }
    
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($w_car + $w_mat + $w_anio, 7, 'TOTAL CARGA HORARIA SEMANAL: ', 0, 0, 'R');
    $pdf->Cell($w_hs, 7, $total_hs . ' HS', 1, 1, 'C', true);
}

// 5. SALIDA DEL PDF
$pdf->Output('I', 'Legajo_Docente_' . $p['apellido'] . '.pdf');