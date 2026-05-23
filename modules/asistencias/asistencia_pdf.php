<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../fpdf/fpdf.php'; 

verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

// Función de compatibilidad PHP 8 (mb_convert_encoding)
function txt($texto) {
    if ($texto === null) return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

$uuid_espacio = $_GET['uuid_espacio'] ?? '';
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

try {
    // 1. Datos de la Institución
    $inst = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // 2. Info de Carrera y Espacio
    $info_sql = "SELECT e.id_espacio, e.nombre_espacio, c.nombre_carrera 
                 FROM espacios_curriculares e 
                 JOIN carreras c ON e.id_carrera = c.id_carrera 
                 WHERE e.uuid_espacio = ?";
    $stmt_info = $pdo->prepare($info_sql);
    $stmt_info->execute([$uuid_espacio]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info) die("Error: Espacio no encontrado.");

    $id_espacio = $info['id_espacio'];
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
    $meses_nombres = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];

    // 3. Obtener Alumnos
    $alumnos_sql = "SELECT a.id_alumno, a.apellido, a.nombre FROM alumnos a
                    INNER JOIN inscripciones_espacios i ON a.id_alumno = i.id_alumno
                    WHERE i.id_espacio = ? ORDER BY a.apellido, a.nombre";
    $stmt_al = $pdo->prepare($alumnos_sql);
    $stmt_al->execute([$id_espacio]);
    $alumnos = $stmt_al->fetchAll(PDO::FETCH_ASSOC);

    // 4. Obtener Asistencias
    $asist_sql = "SELECT id_alumno, DAY(fecha) as dia, estado FROM asistencias_clases 
                  WHERE id_espacio = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?";
    $stmt_asist = $pdo->prepare($asist_sql);
    $stmt_asist->execute([$id_espacio, $mes, $anio]);
    $asistencias_raw = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);
    
    $matriz_asist = [];
    foreach($asistencias_raw as $r) {
        $matriz_asist[$r['id_alumno']][$r['dia']] = $r['estado'];
    }

} catch (Exception $e) { die("Error de sistema: " . $e->getMessage()); }

class PDF extends FPDF {
    protected $inst;

    function setInst($datos) { $this->inst = $datos; }

    function Header() {
        global $info, $meses_nombres, $mes, $anio;
        
        // Logo
        if(file_exists('../../assets/img/logo.png')) {
            $this->Image('../../assets/img/logo.png', 10, 10, 25);
        }
        
        // Datos de la Institución (Alineados junto al logo)
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(28); // Espacio para el logo
        $this->Cell(0, 7, txt($this->inst['nombre_institucion'] ?? 'INSTITUCIÓN'), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(28);
        $this->Cell(0, 4, txt("CUIT: " . ($this->inst['cuit'] ?? '00-00000000-0') . " | " . ($this->inst['domicilio'] ?? '') . " - " . ($this->inst['localidad'] ?? '')), 0, 1, 'L');
        $this->Cell(28);
        $this->Cell(0, 4, txt("Tel: " . ($this->inst['telefono'] ?? '') . " | Email: " . ($this->inst['email_contacto'] ?? '')), 0, 1, 'L');
        
        $this->Ln(5);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 287, $this->GetY()); // Línea divisoria
        $this->Ln(3);

        // Título del Reporte y Datos Académicos
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, txt('SÁBANA DE ASISTENCIA MENSUAL'), 0, 1, 'C');
        $this->Ln(2);

        $this->SetFillColor(245, 245, 245);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(130, 7, txt(' CARRERA: ' . $info['nombre_carrera']), 1, 0, 'L', true);
        $this->Cell(147, 7, txt(' MATERIA: ' . $info['nombre_espacio'] . " | " . $meses_nombres[$mes] . " " . $anio), 1, 1, 'L', true);
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, txt('Sintek Pro Gestión Académica - Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Inicialización
$pdf = new PDF('L', 'mm', 'A4');
$pdf->setInst($inst); // Pasamos los datos de configuración
$pdf->AliasNbPages();
$pdf->AddPage();

// Configuración de anchos
$w_nombre = 60;
$w_dia = 6.4; 
$w_resumen = 9;

// Encabezado de Tabla
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell($w_nombre, 7, 'ESTUDIANTE', 1, 0, 'C');
for ($i = 1; $i <= $dias_en_mes; $i++) {
    $pdf->Cell($w_dia, 7, $i, 1, 0, 'C');
}
$pdf->Cell($w_resumen, 7, 'P', 1, 0, 'C');
$pdf->Cell($w_resumen, 7, 'A', 1, 1, 'C');

// Cuerpo de Tabla
$pdf->SetFont('Arial', '', 7);
foreach ($alumnos as $al) {
    // Nombre truncado para que no rompa la celda
    $nombre_completo = txt($al['apellido'] . ', ' . $al['nombre']);
    $pdf->Cell($w_nombre, 6, (strlen($nombre_completo) > 38) ? substr($nombre_completo, 0, 35) . '...' : $nombre_completo, 1, 0, 'L');
    
    $p = 0; $a = 0;
    for ($i = 1; $i <= $dias_en_mes; $i++) {
        $mark = '';
        $estado = $matriz_asist[$al['id_alumno']][$i] ?? '';
        
        if ($estado == 'PRESENTE' || $estado == 'TARDE') { $mark = 'P'; $p++; }
        elseif ($estado == 'AUSENTE') { $mark = 'A'; $a++; }
        elseif ($estado == 'JUSTIFICADO') { $mark = 'J'; $p++; }
        
        $pdf->Cell($w_dia, 6, $mark, 1, 0, 'C');
    }
    
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell($w_resumen, 6, $p, 1, 0, 'C');
    $pdf->Cell($w_resumen, 6, $a, 1, 1, 'C');
    $pdf->SetFont('Arial', '', 7);
}

$pdf->Output('I', 'Sabana_Asistencia_'.$mes.'_'.$anio.'.pdf');