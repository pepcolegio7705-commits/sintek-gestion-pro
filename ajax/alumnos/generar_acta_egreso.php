<?php
// ajax/alumnos/generar_acta_egreso.php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. CARGA DE DEPENDENCIAS
require('../../fpdf/fpdf.php'); 
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validación de seguridad de sesión
verificar_permisos(['Administrador', 'Secretaría']);

// Captura de UUID para ruta amigable
$uuid = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['uuid'] ?? ''));
if (!$uuid) { die("Identificador (UUID) no proporcionado."); }

// Función de conversión para FPDF (ISO-8859-1 para tildes y Ñ)
function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

try {
    // 2. OBTENER DATOS DEL ALUMNO
    $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido, dni, legajo, libro_matriz, folio_matriz, fecha_egreso 
                           FROM alumnos WHERE uuid_alumno = ? LIMIT 1");
    $stmt->execute([$uuid]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) { die("Alumno no encontrado."); }
    $id_al = $alumno['id_alumno'];

    // --- BLOQUEO DE SEGURIDAD: VALIDACIÓN DE DEUDA ---
    $stmt_d = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE id_alumno = ? AND estado = 'Pendiente'");
    $stmt_d->execute([$id_al]);
    if ($stmt_d->fetchColumn() > 0) {
        die("BLOQUEO DE TESORERÍA: El acta no puede generarse por deudas pendientes.");
    }

    // 3. OBTENER LA CARRERA
    $sql_carrera = "SELECT c.id_carrera, c.nombre_carrera, ac.cohorte 
                    FROM alumnos_carreras ac
                    JOIN carreras c ON ac.id_carreras = c.id_carrera
                    WHERE ac.id_alumno = ? LIMIT 1"; 
    $stmt_c = $pdo->prepare($sql_carrera);
    $stmt_c->execute([$id_al]);
    $carrera = $stmt_c->fetch(PDO::FETCH_ASSOC);

    if (!$carrera) { die("Carrera no vinculada."); }

    // --- BLOQUEO DE SEGURIDAD: VALIDACIÓN ACADÉMICA ---
    // Verificamos que realmente tenga todas las materias aprobadas (Mejor nota >= 7)
    $stmt_plan = $pdo->prepare("SELECT COUNT(*) FROM espacios_curriculares WHERE id_carrera = ? AND activo = 1");
    $stmt_plan->execute([$carrera['id_carrera']]);
    $total_plan = $stmt_plan->fetchColumn();

    $sql_check_aprob = "SELECT COUNT(DISTINCT id_espacio) FROM (
                            SELECT id_espacio FROM cursadas_notas WHERE id_alumno = ? AND nota_final_cursada >= 7
                            UNION 
                            SELECT m.id_espacio FROM examenes_notas en JOIN mesas_examenes m ON en.id_mesa = m.id_mesa WHERE en.id_alumno = ? AND en.nota_final >= 7
                        ) as aprobadas";
    $stmt_check = $pdo->prepare($sql_check_aprob);
    $stmt_check->execute([$id_al, $id_al]);
    $total_aprobadas = $stmt_check->fetchColumn();

    if ($total_aprobadas < $total_plan && $total_plan > 0) {
        die("ERROR ACADÉMICO: El alumno aún posee materias pendientes en el plan de estudios.");
    }

    $fecha_graduacion = (!$alumno['fecha_egreso'] || $alumno['fecha_egreso'] == '0000-00-00') ? date('Y-m-d') : $alumno['fecha_egreso'];

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// 4. CONFIGURACIÓN DEL PDF (L = Landscape)
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

// MARCO ORNAMENTAL
$pdf->SetLineWidth(1.2);
$pdf->Rect(10, 10, 277, 190); 
$pdf->SetLineWidth(0.4);
$pdf->Rect(13, 13, 271, 184); 

// ENCABEZADO
$logo = '../../assets/img/logo.png';
if (file_exists($logo)) { $pdf->Image($logo, 135, 20, 26); }

$pdf->SetY(52);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'C');
$pdf->SetFont('Times', 'BI', 24); 
$pdf->Cell(0, 12, convertir("'RAÍCES DEL SABER'"), 0, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(60, 60, 60);
$pdf->Cell(0, 10, convertir("ACTA DE EGRESO ACADÉMICO"), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);

// CUERPO DEL TEXTO
$pdf->SetY(95); 
$pdf->SetFont('Arial', '', 14);
$pdf->SetLeftMargin(30);
$pdf->SetRightMargin(30);

$pdf->Write(9, convertir("La Dirección del Instituto Superior 'Raíces del Saber' certifica que el/la alumno/a "));
$pdf->SetFont('Arial', 'B', 16);
$pdf->Write(9, convertir(mb_strtoupper($alumno['apellido']) . ", " . $alumno['nombre']));

$pdf->SetFont('Arial', '', 14);
$pdf->Write(9, convertir(", con Documento Nacional de Identidad N° "));
$pdf->SetFont('Arial', 'B', 14);
$pdf->Write(9, number_format((float)$alumno['dni'], 0, '', '.'));

$pdf->SetFont('Arial', '', 14);
$pdf->Write(9, convertir(", habiendo aprobado la totalidad de las obligaciones académicas y requisitos del Plan de Estudios vigente, ha egresado de la carrera:"));

$pdf->Ln(22); 
$pdf->SetX(30);
$pdf->SetFont('Times', 'BI', 26); 
$pdf->Cell(237, 12, convertir($carrera['nombre_carrera']), 0, 1, 'C');

$pdf->Ln(4); 
$pdf->SetX(30);
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(80, 80, 80); 
$pdf->Cell(237, 8, convertir("Cohorte: " . ($carrera['cohorte'] ?: '---') . "  |  Fecha de Egreso Oficial: " . date('d/m/Y', strtotime($fecha_graduacion))), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0); 

// PIE DE REGISTRO
$pdf->SetY(158);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, convertir("Registrado en Libro Matriz: " . ($alumno['libro_matriz'] ?: '---') . " - Folio: " . ($alumno['folio_matriz'] ?: '---') . " - Legajo: " . ($alumno['legajo'] ?: '---')), 0, 1, 'C');

$pdf->SetY(172);
$meses = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
$fecha_hoy = "Dado en la ciudad de Rawson, Provincia de Chubut, a los " . date('d') . " días del mes de " . $meses[(int)date('m')] . " de " . date('Y') . ".";
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, convertir($fecha_hoy), 0, 1, 'C');

// FIRMAS
$pdf->SetY(190);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(110, 5, "__________________________", 0, 0, 'C');
$pdf->Cell(127, 5, "__________________________", 0, 1, 'C');
$pdf->Cell(110, 5, convertir("Secretaría Académica"), 0, 0, 'C');
$pdf->Cell(127, 5, convertir("Dirección Institucional"), 0, 1, 'C');

// SEGURIDAD
$pdf->SetY(198);
$pdf->SetFont('Courier', 'I', 7);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 5, "VERIFICACIÓN ELECTRÓNICA UUID: " . strtoupper($uuid), 0, 1, 'C');

$pdf->Output('I', 'Acta_Egreso_' . $alumno['dni'] . '.pdf');