<?php
// ajax/asistencias/generar_reporte_pdf.php
session_start();
require_once '../../core/conexion.php';
require_once '../../fpdf/fpdf.php'; // Ajusta la ruta a tu librería

// Función de limpieza de texto que ya utilizas
function txt($s) {
    return mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
}

// 1. CAPTURA DE FILTROS
$fi = $_GET['fi'] ?? date('Y-m-d');
$ff = $_GET['ff'] ?? date('Y-m-d');
$tp = $_GET['tp'] ?? '';

// 2. OBTENER DATOS DE LA INSTITUCIÓN
try {
    $stmt_inst = $pdo->query("SELECT * FROM configuracion LIMIT 1");
    $datos_inst = $stmt_inst->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $datos_inst = [];
}

// 3. EXTENSIÓN DE LA CLASE FPDF
class PDF extends FPDF {
    protected $inst;
    protected $filtros;

    function setInst($datos) { $this->inst = $datos; }
    function setFiltros($f) { $this->filtros = $f; }

    function Header() {
        // Logo
        if(file_exists('../../assets/img/logo.png')) {
            $this->Image('../../assets/img/logo.png', 10, 10, 22);
        }
        
        // Datos de la Institución
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(25); 
        $this->Cell(0, 7, txt($this->inst['nombre_institucion'] ?? 'INSTITUCIÓN'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(25);
        $this->Cell(0, 4, txt("CUIT: " . ($this->inst['cuit'] ?? '00-00000000-0') . " | " . ($this->inst['domicilio'] ?? '') . " - " . ($this->inst['localidad'] ?? '')), 0, 1, 'L');
        $this->Cell(25);
        $this->Cell(0, 4, txt("Tel: " . ($this->inst['telefono'] ?? '') . " | Email: " . ($this->inst['email_contacto'] ?? '')), 0, 1, 'L');
        
        $this->Ln(5);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY()); // Línea para A4 Vertical
        $this->Ln(3);

        // Título del Reporte
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 6, txt('INFORME DE PERMANENCIA INSTITUCIONAL'), 0, 1, 'C');
        
        // Subtítulo con el Rango de Fechas
        $this->SetFont('Arial', '', 9);
        $fecha_info = "Desde: " . date('d/m/Y', strtotime($this->filtros['fi'])) . " Hasta: " . date('d/m/Y', strtotime($this->filtros['ff']));
        if(!empty($this->filtros['tp'])) $fecha_info .= " | Filtro: " . $this->filtros['tp'];
        $this->Cell(0, 5, txt($fecha_info), 0, 1, 'C');
        $this->Ln(4);

        // Encabezado de Tabla
        $this->SetFillColor(0, 51, 102); // Azul Escuela
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(20, 8, txt('DNI'), 1, 0, 'C', true);
        $this->Cell(65, 8, txt('NOMBRE COMPLETO'), 1, 0, 'C', true);
        $this->Cell(30, 8, txt('ROL/ÁREA'), 1, 0, 'C', true);
        $this->Cell(22, 8, txt('FECHA'), 1, 0, 'C', true);
        $this->Cell(18, 8, txt('ENTRADA'), 1, 0, 'C', true);
        $this->Cell(18, 8, txt('SALIDA'), 1, 0, 'C', true);
        $this->Cell(17, 8, txt('TIEMPO'), 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, txt('Sintek Pro Gestión Académica - Página ') . $this->PageNo() . '/{nb} - Generado el ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
}

// 4. CONSULTA DE DATOS (Mismo criterio que la vista)
$where = "WHERE fecha BETWEEN :fi AND :ff";
$params = [':fi' => $fi, ':ff' => $ff];
if(!empty($tp)) { $where .= " AND tipo_persona = :tp"; $params[':tp'] = $tp; }

$sql = "SELECT 
            dni_persona, nombre_completo, tipo_persona, fecha,
            MIN(hora_registro) as entrada,
            MAX(hora_registro) as salida,
            TIMEDIFF(MAX(hora_registro), MIN(hora_registro)) as permanencia
        FROM asistencias
        $where
        GROUP BY dni_persona, fecha, nombre_completo, tipo_persona
        ORDER BY fecha DESC, entrada DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. GENERACIÓN DEL PDF
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setInst($datos_inst);
$pdf->setFiltros(['fi' => $fi, 'ff' => $ff, 'tp' => $tp]);
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

if(count($rows) > 0) {
    foreach($rows as $row) {
        $pdf->Cell(20, 7, $row['dni_persona'], 1, 0, 'C');
        $pdf->Cell(65, 7, txt($row['nombre_completo']), 1, 0, 'L');
        $pdf->Cell(30, 7, txt($row['tipo_persona']), 1, 0, 'C');
        $pdf->Cell(22, 7, date('d/m/Y', strtotime($row['fecha'])), 1, 0, 'C');
        $pdf->Cell(18, 7, $row['entrada'], 1, 0, 'C');
        
        // Si la entrada es igual a la salida, significa que no marcó salida aún
        $salida = ($row['entrada'] == $row['salida']) ? '--:--' : $row['salida'];
        $tiempo = ($row['entrada'] == $row['salida']) ? '...' : $row['permanencia'];
        
        $pdf->Cell(18, 7, $salida, 1, 0, 'C');
        $pdf->Cell(17, 7, $tiempo, 1, 1, 'C');
    }
} else {
    $pdf->Cell(0, 10, txt('No se encontraron registros para el periodo seleccionado.'), 1, 1, 'C');
}

$pdf->Output('I', "Permanencia_$fi" . "_al_" . "$ff.pdf");