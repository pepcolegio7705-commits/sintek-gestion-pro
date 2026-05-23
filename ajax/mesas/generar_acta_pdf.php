<?php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once '../../core/conexion.php';
require_once '../../fpdf/fpdf.php';

// 1. Validar UUID
$uuid_mesa = $_GET['uuid'] ?? null;
if (!$uuid_mesa) die("Mesa no proporcionada.");

/**
 * Función compatible con PHP 8 para evitar utf8_decode()
 */
function txt($texto) {
    if ($texto === null || $texto === '') return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

function notaEnLetras($n) {
    if ($n === "" || $n === null || $n == 0) return "---";
    $letras = [0=>'Cero', 1=>'Uno', 2=>'Dos', 3=>'Tres', 4=>'Cuatro', 5=>'Cinco', 6=>'Seis', 7=>'Siete', 8=>'Ocho', 9=>'Nueve', 10=>'Diez'];
    return $letras[(int)$n] ?? '';
}

try {
    // 2. Datos de la Institución
    $conf = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // 3. Obtener datos de la mesa por UUID
    $sql_mesa = "SELECT m.*, e.nombre_espacio, c.nombre_carrera, e.anio_cursada
                 FROM mesas_examenes m
                 JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                 JOIN carreras c ON m.id_carrera = c.id_carrera
                 WHERE m.uuid_mesa = ?";
    $stmt_mesa = $pdo->prepare($sql_mesa);
    $stmt_mesa->execute([$uuid_mesa]);
    $mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) die("Mesa no encontrada.");
    $id_mesa = $mesa['id_mesa'];

    // 4. Obtener Alumnos (Priorizar si ya hay notas cargadas, sino inscriptos)
    $sql_notas = "SELECT a.apellido, a.nombre, a.dni, c.nota_final, i.condicion, c.libro, c.folio
                  FROM inscripciones_examen i
                  JOIN alumnos a ON i.id_alumno = a.id_alumno
                  LEFT JOIN calificaciones c ON (a.id_alumno = c.id_alumno AND c.id_mesa = i.id_mesa)
                  WHERE i.id_mesa = ?
                  ORDER BY a.apellido ASC";
    $stmt_notas = $pdo->prepare($sql_notas);
    $stmt_notas->execute([$id_mesa]);
    $alumnos = $stmt_notas->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

class PDF extends FPDF {
    private $inst;

    public function setInst($data) { $this->inst = $data; }

    function Header() {
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 22);
        }
        $this->SetXY(35, 10);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 51, 102);
        $this->Cell(150, 6, txt($this->inst['nombre_institucion']), 0, 1, 'L');
        
        $this->SetX(35);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(150, 5, txt("CUE: " . $this->inst['cuit'] . " - " . $this->inst['localidad']), 0, 1, 'L');

        $this->SetX(35);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(150, 8, txt('ACTA VOLANTE DE EXÁMENES'), 0, 1, 'L');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, txt("Página ") . $this->PageNo() . "/{nb} - " . txt($this->inst['nombre_institucion']), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->setInst($conf);
$pdf->AliasNbPages();
$pdf->AddPage();

// --- CUADRO INFORMATIVO ---
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 9);

$pdf->Cell(30, 7, 'CARRERA:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 7, txt($mesa['nombre_carrera']), 1, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 7, 'ESPACIO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(110, 7, txt($mesa['nombre_espacio']), 1, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(20, 7, txt('AÑO:'), 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 7, $mesa['anio_cursada'] . txt('° Año'), 1, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 7, 'FECHA:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(30, 7, date('d/m/Y', strtotime($mesa['fecha_examen'])), 1, 0, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 7, 'LLAMADO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(60, 7, txt($mesa['llamado'] . " Llamado"), 1, 0, 'C'); 

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(25, 7, 'LIBRO/FOLIO:', 1, 0, 'L', true);
$pdf->SetFont('Arial', '', 9);
// Si ya hay una nota cargada, mostrar el libro/folio de la primera fila
$libro_folio = (!empty($alumnos) && isset($alumnos[0]['libro'])) ? ($alumnos[0]['libro'] . ' / ' . $alumnos[0]['folio']) : '....... / .......';
$pdf->Cell(0, 7, $libro_folio, 1, 1, 'C');

$pdf->Ln(5);

// --- TABLA DE ALUMNOS ---
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(10, 8, txt('N°'), 1, 0, 'C', true);
$pdf->Cell(75, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'DNI', 1, 0, 'C', true);
$pdf->Cell(45, 8, 'CALIF. (Num y Letras)', 1, 0, 'C', true);
$pdf->Cell(0, 8, txt('CONDICIÓN'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$count = 1;
foreach ($alumnos as $alum) {
    if($pdf->GetY() > 260) $pdf->AddPage();
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(75, 8, txt($alum['apellido'] . ', ' . $alum['nombre']), 1, 0, 'L');
    $pdf->Cell(25, 8, $alum['dni'], 1, 0, 'C');
    
    // Si la nota está vacía o es 0, dejamos el espacio para completar a mano
    if (empty($alum['nota_final'])) {
        $pdf->Cell(45, 8, '................................', 1, 0, 'C'); 
    } else {
        $texto_nota = $alum['nota_final'] . ' (' . notaEnLetras($alum['nota_final']) . ')';
        $pdf->Cell(45, 8, txt($texto_nota), 1, 0, 'C');
    }
    $pdf->Cell(0, 8, strtoupper(txt($alum['condicion'])), 1, 1, 'C');
}

// Rellenar con filas vacías si hay pocos alumnos para completar la hoja
$filas_restantes = 15 - count($alumnos);
for ($j = 0; $j < $filas_restantes; $j++) {
    if($pdf->GetY() > 260) $pdf->AddPage();
    $pdf->Cell(10, 8, $count++, 1, 0, 'C');
    $pdf->Cell(75, 8, '', 1, 0, 'L');
    $pdf->Cell(25, 8, '', 1, 0, 'C');
    $pdf->Cell(45, 8, '', 1, 0, 'C');
    $pdf->Cell(0, 8, '', 1, 1, 'C');
}

$pdf->Ln(15);
if($pdf->GetY() > 240) $pdf->AddPage(); 
$y_firmas = $pdf->GetY() + 10;
$pdf->Line(15, $y_firmas, 65, $y_firmas); 
$pdf->Line(80, $y_firmas, 130, $y_firmas); 
$pdf->Line(145, $y_firmas, 195, $y_firmas); 
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY(15, $y_firmas + 2); $pdf->Cell(50, 4, 'Presidente', 0, 0, 'C');
$pdf->SetXY(80, $y_firmas + 2); $pdf->Cell(50, 4, 'Vocal 1', 0, 0, 'C');
$pdf->SetXY(145, $y_firmas + 2); $pdf->Cell(50, 4, 'Vocal 2', 0, 0, 'C');

$pdf->Output('I', 'Acta_Mesa_' . $id_mesa . '.pdf');