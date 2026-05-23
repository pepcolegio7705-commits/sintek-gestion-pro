<?php
// ajax/alumnos/generar_analitico.php
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Ajuste de rutas para FPDF y Core
require('../../fpdf/fpdf.php'); 
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

// Solo permitimos a Administradores y Secretarias
verificar_permisos(['Administrador', 'Secretaría']);

// Capturamos el UUID
if (!isset($_GET['uuid'])) {
    die("Identificador de alumno no proporcionado.");
}

$uuid = preg_replace('/[^a-z0-9-]/', '', (string)$_GET['uuid']);

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// 1. Obtener datos del alumno usando el UUID
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido, dni, legajo, libro_matriz, folio_matriz FROM alumnos WHERE uuid_alumno = ?");
$stmt->execute([$uuid]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    die("Alumno no encontrado o identificador inválido.");
}

$id_alumno = $alumno['id_alumno'];

// 2. Obtener historial unificado CON FILTRO DE PLANILLA CERRADA
$sql_notas = "
    (SELECT 
        e.nombre_espacio, 'Cursado' as instancia, cn.nota_final_cursada as nota, 
        cn.fecha_registro, car.nombre_carrera, ac.cohorte, NULL as llamado
    FROM cursadas_notas cn
    JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
    JOIN carreras car ON cn.id_carrera = car.id_carrera
    JOIN control_planillas cp ON (cp.id_espacio = cn.id_espacio AND cp.ciclo_lectivo = cn.ciclo_lectivo)
    LEFT JOIN alumnos_carreras ac ON (ac.id_alumno = cn.id_alumno AND ac.id_carreras = cn.id_carrera)
    WHERE cn.id_alumno = ? AND cp.estado = 'Cerrada')
    
    UNION ALL

    (SELECT 
        e.nombre_espacio, 'Examen' as instancia, en.nota_final as nota, 
        en.fecha_registro, car.nombre_carrera, ac.cohorte, m.llamado
    FROM examenes_notas en
    JOIN mesas_examenes m ON en.id_mesa = m.id_mesa
    JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
    JOIN carreras car ON m.id_carrera = car.id_carrera
    LEFT JOIN alumnos_carreras ac ON (ac.id_alumno = en.id_alumno AND ac.id_carreras = car.id_carrera)
    WHERE en.id_alumno = ?)
    
    ORDER BY nombre_carrera ASC, fecha_registro ASC";

$stmt = $pdo->prepare($sql_notas);
$stmt->execute([$id_alumno, $id_alumno]);
$notas = $stmt->fetchAll(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    function Header() {
        $logo = '../../assets/img/logo.png';
        if (file_exists($logo)) {
            $this->Image($logo, 10, 8, 25);
        }
        $this->SetXY(38, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(150, 7, convertir("INSTITUTO SUPERIOR DE FORMACIÓN TÉCNICA Y DOCENTE"), 0, 1, 'L');
        $this->SetX(38);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 8, convertir("'RAÍCES DEL SABER'"), 0, 1, 'L');
        $this->SetX(38);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(150, 7, convertir('ANALÍTICO HISTÓRICO DE CALIFICACIONES'), 0, 1, 'L');
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $fecha_gen = date('d/m/Y H:i');
        $this->Cell(0, 10, convertir("Generado el: $fecha_gen - Página ") . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

function imprimirPromedios($pdf, $sg, $cg, $sa, $ca) {
    $prom_gral = ($cg > 0) ? number_format((float)($sg / $cg), 2, ',', '.') : '0,00';
    $prom_aprob = ($ca > 0) ? number_format((float)($sa / $ca), 2, ',', '.') : '0,00';

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(145, 7, convertir("PROMEDIO GENERAL (CON APLAZOS):"), 1, 0, 'R', true);
    $pdf->Cell(45, 7, $prom_gral, 1, 1, 'C', true);
    
    $pdf->Cell(145, 7, convertir("PROMEDIO MATERIAS APROBADAS (SIN APLAZOS, NOTA >= 7):"), 1, 0, 'R', true);
    $pdf->Cell(45, 7, $prom_aprob, 1, 1, 'C', true);
    $pdf->Ln(4);
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// --- CUADRO DE DATOS DEL ALUMNO ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(235, 235, 235);
$pdf->Cell(0, 8, convertir("DATOS DEL ALUMNO Y REFERENCIAS DE ARCHIVO"), 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 9, convertir(" Apellido y Nombre: " . $alumno['apellido'] . ", " . $alumno['nombre']), 1, 1);
$pdf->Cell(47.5, 9, " DNI: " . number_format((float)($alumno['dni'] ?? 0), 0, '', '.'), 1, 0);
$pdf->Cell(47.5, 9, " Legajo: " . ($alumno['legajo'] ?: '---'), 1, 0);
$pdf->Cell(47.5, 9, " Libro: " . ($alumno['libro_matriz'] ?: '---'), 1, 0);
$pdf->Cell(47.5, 9, " Folio: " . ($alumno['folio_matriz'] ?: '---'), 1, 1);
$pdf->Ln(5);

$carrera_actual = "";
$suma_aprobadas = 0; $cant_aprobadas = 0;
$suma_general = 0;   $cant_general = 0;

if(empty($notas)) {
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(190, 10, convertir("No registra calificaciones oficiales (cerradas) hasta la fecha."), 1, 1, 'C');
} else {
    foreach ($notas as $index => $n) {
        if ($carrera_actual != $n['nombre_carrera'] && $carrera_actual != "") {
            imprimirPromedios($pdf, $suma_general, $cant_general, $suma_aprobadas, $cant_aprobadas);
            $suma_aprobadas = 0; $cant_aprobadas = 0;
            $suma_general = 0;   $cant_general = 0;
        }

        if ($carrera_actual != $n['nombre_carrera']) {
            $carrera_actual = $n['nombre_carrera'];
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            
            $cohorte_texto = ($n['cohorte']) ? $n['cohorte'] : "No registrada";
            $titulo_carrera = " CARRERA: " . $carrera_actual . " | COHORTE: " . $cohorte_texto;
            
            $pdf->Cell(190, 8, convertir($titulo_carrera), 1, 1, 'L', true);
            
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(210, 210, 210);
            $pdf->Cell(85, 8, convertir("Espacio Curricular"), 1, 0, 'C', true);
            $pdf->Cell(35, 8, convertir("Instancia"), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir("Fecha"), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir("Cond."), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir("Nota"), 1, 1, 'C', true);
        }

        $pdf->SetFont('Arial', '', 8);
        $nota = floatval($n['nota'] ?? 0);
        $suma_general += $nota;
        $cant_general++;
        
        if ($nota >= 7) {
            $suma_aprobadas += $nota; $cant_aprobadas++;
            $condicion = "APROBADO";
        } elseif ($nota >= 4 && $nota < 7) {
            $condicion = "REGULAR";
        } else {
            $condicion = "DESAPROBADO";
        }

        $pdf->Cell(85, 8, convertir(substr($n['nombre_espacio'], 0, 55)), 1);
        $instancia_texto = ($n['instancia'] == 'Examen') ? "Ex. " . $n['llamado'] : "Cursado";
        $pdf->Cell(35, 8, convertir($instancia_texto), 1, 0, 'C');
        $pdf->Cell(25, 8, date('d/m/Y', strtotime($n['fecha_registro'])), 1, 0, 'C');
        $pdf->Cell(25, 8, $condicion, 1, 0, 'C');
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 8, number_format((float)$nota, 0), 1, 1, 'C');
        $pdf->SetFont('Arial', '', 8);

        if ($index == count($notas) - 1) {
            imprimirPromedios($pdf, $suma_general, $cant_general, $suma_aprobadas, $cant_aprobadas);
        }
    }
}

$pdf->Ln(15);
if($pdf->GetY() > 240) { $pdf->AddPage(); }

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 10, "__________________________", 0, 1, 'R');
$pdf->Cell(0, 5, convertir("Firma y Sello Secretaría"), 0, 1, 'R');
$pdf->Cell(0, 5, convertir("'Raíces del Saber'"), 0, 1, 'R');

$pdf->Output('I', 'Analitico_' . ($alumno['dni'] ?? '0') . '.pdf');