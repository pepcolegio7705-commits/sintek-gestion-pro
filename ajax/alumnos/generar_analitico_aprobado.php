<?php
// ajax/alumnos/generar_analitico_aprobado.php
date_default_timezone_set('America/Argentina/Buenos_Aires');

require('../../fpdf/fpdf.php'); 
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_GET['uuid'])) { 
    die("Identificador (UUID) no proporcionado."); 
}

$uuid = preg_replace('/[^a-z0-9-]/', '', (string)$_GET['uuid']);

function convertir($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

// 1. OBTENER DATOS DEL ALUMNO
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido, dni, legajo, libro_matriz, folio_matriz FROM alumnos WHERE uuid_alumno = ? LIMIT 1");
$stmt->execute([$uuid]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) { die("Alumno no encontrado."); }
$id_alumno = $alumno['id_alumno'];

/**
 * 2. SQL COMPLEJO: UNIFICA CURSADAS CERRADAS Y EXÁMENES, LUEGO TOMA LA MEJOR NOTA >= 7
 */
$sql_notas = "SELECT 
                t.nombre_carrera, 
                t.id_carrera,
                t.nombre_espacio, 
                MAX(t.nota) as mejor_nota, 
                MAX(t.fecha_registro) as fecha_mejor_nota,
                t.id_mesa,
                t.llamado,
                t.cohorte
              FROM (
                -- Parte de Cursadas (Solo cerradas)
                SELECT 
                    car.nombre_carrera, car.id_carrera, e.nombre_espacio, e.id_espacio,
                    cn.nota_final_cursada as nota, cn.fecha_registro, NULL as id_mesa, NULL as llamado, ac.cohorte
                FROM cursadas_notas cn
                JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
                JOIN carreras car ON cn.id_carrera = car.id_carrera
                JOIN control_planillas cp ON (cp.id_espacio = cn.id_espacio AND cp.ciclo_lectivo = cn.ciclo_lectivo)
                LEFT JOIN alumnos_carreras ac ON (ac.id_alumno = cn.id_alumno AND ac.id_carreras = cn.id_carrera)
                WHERE cn.id_alumno = ? AND cp.estado = 'Cerrada' AND cn.nota_final_cursada >= 7

                UNION ALL

                -- Parte de Exámenes
                SELECT 
                    car.nombre_carrera, car.id_carrera, e.nombre_espacio, e.id_espacio,
                    en.nota_final as nota, en.fecha_registro, en.id_mesa, m.llamado, ac.cohorte
                FROM examenes_notas en
                JOIN mesas_examenes m ON en.id_mesa = m.id_mesa
                JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                JOIN carreras car ON m.id_carrera = car.id_carrera
                LEFT JOIN alumnos_carreras ac ON (ac.id_alumno = en.id_alumno AND ac.id_carreras = car.id_carrera)
                WHERE en.id_alumno = ? AND en.nota_final >= 7
              ) AS t
              GROUP BY t.id_carrera, t.id_espacio
              ORDER BY t.nombre_carrera ASC, t.nombre_espacio ASC";

$stmt_notas = $pdo->prepare($sql_notas);
$stmt_notas->execute([$id_alumno, $id_alumno]);
$notas = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

// 3. CLASE PDF
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
        $this->Cell(150, 7, convertir('CERTIFICADO DE MATERIAS APROBADAS'), 0, 1, 'L');
        $this->Ln(12);
    }
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir('Página ') . $this->PageNo() . '/{nb} - Sistema de Gestión Académica', 0, 0, 'C');
    }
}

function imprimirPromedioAprobado($pdf, $suma, $cantidad) {
    $promedio = ($cantidad > 0) ? number_format((float)($suma / $cantidad), 2, ',', '.') : '0,00';
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(145, 8, convertir("PROMEDIO DE MATERIAS APROBADAS (S/APLAZOS): "), 1, 0, 'R', true);
    $pdf->Cell(45, 8, $promedio, 1, 1, 'C', true);
    $pdf->Ln(5);
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Cuadro Datos Alumno
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(235, 235, 235);
$pdf->Cell(0, 8, convertir("TITULAR DEL BENEFICIO Y REFERENCIAS DE ARCHIVO"), 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(190, 9, convertir(" Apellido y Nombre: " . $alumno['apellido'] . ", " . $alumno['nombre']), 1, 1);
$pdf->Cell(47.5, 9, " DNI: " . number_format((float)($alumno['dni'] ?? 0), 0, '', '.'), 1, 0);
$pdf->Cell(47.5, 9, " Legajo: " . ($alumno['legajo'] ?: '---'), 1, 0);
$pdf->Cell(47.5, 9, " Libro: " . ($alumno['libro_matriz'] ?: '---'), 1, 0);
$pdf->Cell(47.5, 9, " Folio: " . ($alumno['folio_matriz'] ?: '---'), 1, 1);
$pdf->Ln(6);

$carrera_actual = "";
$suma_aprobadas = 0;
$cant_aprobadas = 0;

if(empty($notas)) {
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(190, 10, convertir("No se registran materias aprobadas (con actas cerradas) a la fecha."), 1, 1, 'C');
} else {
    foreach ($notas as $index => $n) {
        if ($carrera_actual != $n['nombre_carrera'] && $carrera_actual != "") {
            imprimirPromedioAprobado($pdf, $suma_aprobadas, $cant_aprobadas);
            $suma_aprobadas = 0; $cant_aprobadas = 0;
        }

        if ($carrera_actual != $n['nombre_carrera']) {
            $carrera_actual = $n['nombre_carrera'];
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(245, 245, 245);
            $coh_val = ($n['cohorte']) ?: "---";
            $pdf->Cell(190, 7, convertir(" CARRERA: " . $carrera_actual . " | COHORTE: " . $coh_val), 1, 1, 'L', true);
            
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell(85, 8, convertir("Espacio Curricular"), 1, 0, 'C', true);
            $pdf->Cell(35, 8, convertir("Instancia"), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir("Fecha"), 1, 0, 'C', true);
            $pdf->Cell(25, 8, convertir("Cond."), 1, 0, 'C', true);
            $pdf->Cell(20, 8, convertir("Nota"), 1, 1, 'C', true);
        }

        $pdf->SetFont('Arial', '', 8);
        $nota = floatval($n['mejor_nota'] ?? 0);
        $suma_aprobadas += $nota;
        $cant_aprobadas++;

        $pdf->Cell(85, 7, convertir(substr($n['nombre_espacio'], 0, 55)), 1);
        $inst = is_null($n['id_mesa']) ? "Cursado" : "Ex. " . $n['llamado'];
        $pdf->Cell(35, 7, convertir($inst), 1, 0, 'C');
        $pdf->Cell(25, 7, date('d/m/Y', strtotime($n['fecha_mejor_nota'])), 1, 0, 'C');
        $pdf->Cell(25, 7, "APROBADO", 1, 0, 'C');
        
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 7, number_format((float)$nota, 0), 1, 1, 'C');
        $pdf->SetFont('Arial', '', 8);

        if ($index == count($notas) - 1) {
            imprimirPromedioAprobado($pdf, $suma_aprobadas, $cant_aprobadas);
        }
    }
}

// Texto de cierre
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 10);
$meses = ["", "enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
$fecha_f = date('d') . " de " . $meses[(int)date('m')] . " de " . date('Y');

$pdf->MultiCell(0, 7, convertir("Se extiende el presente certificado a pedido del interesado, para ser presentado ante quien corresponda, en la ciudad de Rawson, a los " . $fecha_f . "."), 0, 'L');

$pdf->Ln(20);
if($pdf->GetY() > 240) $pdf->AddPage(); 

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 10, "__________________________", 0, 1, 'R');
$pdf->Cell(0, 5, convertir("Firma y Sello Autorizado"), 0, 1, 'R');
$pdf->Cell(0, 5, convertir("'Raíces del Saber'"), 0, 1, 'R');

$pdf->Output('I', 'Aprobadas_' . ($alumno['dni'] ?? '0') . '.pdf');