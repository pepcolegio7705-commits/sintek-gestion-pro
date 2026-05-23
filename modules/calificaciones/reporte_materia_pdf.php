<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../fpdf/fpdf.php'; 

verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

$uuid_espacio = $_GET['uuid'] ?? null;
$ciclo = $_GET['ciclo'] ?? date('Y');

if (!$uuid_espacio) die("Materia no especificada.");

try {
    // 1. DATOS DE LA INSTITUCIÓN (Tabla configuracion)
    $conf = $pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // 2. INFO DEL ESPACIO CURRICULAR Y CARRERA
    $sql_info = "SELECT e.nombre_espacio, e.anio_cursada, c.nombre_carrera 
                 FROM espacios_curriculares e 
                 JOIN carreras c ON e.id_carrera = c.id_carrera 
                 WHERE e.uuid_espacio = ?";
    $stmt_info = $pdo->prepare($sql_info);
    $stmt_info->execute([$uuid_espacio]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info) die("Espacio curricular no encontrado.");

    // 3. LISTADO DE ALUMNOS Y NOTAS
    $sql_lista = "SELECT cn.*, a.apellido, a.nombre, a.dni, cond.nombre_condicion
                  FROM cursadas_notas cn
                  JOIN alumnos a ON cn.id_alumno = a.id_alumno
                  JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
                  LEFT JOIN condiciones_alumno cond ON cn.id_condicion = cond.id_condicion
                  WHERE e.uuid_espacio = ? AND cn.ciclo_lectivo = ?
                  ORDER BY a.apellido, a.nombre ASC";
    
    $stmt_lista = $pdo->prepare($sql_lista);
    $stmt_lista->execute([$uuid_espacio, $ciclo]);
    $alumnos = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/**
 * Función compatible con PHP 8 para evitar utf8_decode()
 */
function txt($texto) {
    if (!$texto) return '';
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class PDF extends FPDF {
    private $inst;
    private $materia;

    public function setData($c, $m) { 
        $this->inst = $c; 
        $this->materia = $m;
    }

    function Header() {
        // Logo
        if (file_exists('../../img/logo.png')) {
            $this->Image('../../img/logo.png', 10, 8, 28); 
        }

        // Encabezado con datos de tabla configuracion
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(32); 
        $this->Cell(0, 8, txt($this->inst['nombre_institucion']), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 8);
        $this->Cell(32);
        $this->Cell(0, 4, txt("CUIT: " . $this->inst['cuit'] . " | " . $this->inst['domicilio'] . " - " . $this->inst['localidad']), 0, 1, 'L');
        $this->Cell(32);
        $this->Cell(0, 4, txt("Tel: " . $this->inst['telefono'] . " | E-mail: " . $this->inst['email_contacto']), 0, 1, 'L');
        
        $this->Ln(8);

        // Título del documento resaltado
        $this->SetFillColor(0, 51, 102);
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, txt("ACTA VOLANTE DE CALIFICACIONES - CICLO " . $_GET['ciclo']), 0, 1, 'C', true);
        
        // Detalles de la Materia
        $this->Ln(2);
        $this->SetTextColor(0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, txt("CARRERA: " . $this->materia['nombre_carrera']), "B", 1, 'L');
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(110, 7, txt("ESPACIO: " . $this->materia['nombre_espacio']), 0, 0, 'L');
        $this->Cell(40, 7, txt("CURSO: " . $this->materia['anio_cursada'] . "° Año"), 0, 0, 'L');
        $this->Cell(0, 7, txt("FECHA: " . date('d/m/Y')), 0, 1, 'R');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Arial', '', 8);
        
        // Líneas para firmas
        $this->Cell(60, 0, '', 'T', 0, 'C');
        $this->Cell(70, 0, '', 0, 0, 'C');
        $this->Cell(60, 0, '', 'T', 1, 'C');
        
        $this->Cell(60, 5, txt("Firma del Docente"), 0, 0, 'C');
        $this->Cell(70, 5, '', 0, 0, 'C');
        // Nombre del secretario desde configuración
        $this->Cell(60, 5, txt($this->inst['secretario'] . " (Secretaría)"), 0, 1, 'C');

        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 10, txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Configuración de página horizontal (L) si hay muchas columnas, 
// o vertical (P) si prefieres estándar. Usaremos P para mantener consistencia.
$pdf = new PDF('P', 'mm', 'A4');
$pdf->setData($conf, $info);
$pdf->AliasNbPages();
$pdf->AddPage();

// Encabezado de Tabla (Ajustado para incluir Observaciones)
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(0, 51, 102);
$pdf->SetTextColor(255);

// Anchos de celdas: 10+55+20+10+10+10+12+12+25+26 = 190mm (ancho disponible A4)
$pdf->Cell(10, 8, txt('N°'), 1, 0, 'C', true);
$pdf->Cell(55, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
$pdf->Cell(20, 8, 'DNI', 1, 0, 'C', true);
$pdf->Cell(10, 8, 'P1', 1, 0, 'C', true);
$pdf->Cell(10, 8, 'P2', 1, 0, 'C', true);
$pdf->Cell(10, 8, 'REC', 1, 0, 'C', true);
$pdf->Cell(12, 8, 'FINAL', 1, 0, 'C', true);
$pdf->Cell(12, 8, 'ASIST', 1, 0, 'C', true);
$pdf->Cell(25, 8, 'CONDICION', 1, 0, 'C', true);
$pdf->Cell(26, 8, 'OBSERV.', 1, 1, 'C', true);

$pdf->SetTextColor(0);
$pdf->SetFont('Arial', '', 8);

$i = 1;
foreach ($alumnos as $al) {
    $pdf->Cell(10, 7, $i++, 1, 0, 'C');
    $pdf->Cell(55, 7, txt($al['apellido'] . ", " . $al['nombre']), 1);
    $pdf->Cell(20, 7, $al['dni'], 1, 0, 'C');
    $pdf->Cell(10, 7, ($al['parcial_1'] ?? '-'), 1, 0, 'C');
    $pdf->Cell(10, 7, ($al['parcial_2'] ?? '-'), 1, 0, 'C');
    $pdf->Cell(10, 7, ($al['recuperatorio'] ?? '-'), 1, 0, 'C');
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(12, 7, ($al['nota_final_cursada'] ?? '-'), 1, 0, 'C');
    $pdf->SetFont('Arial', '', 8);
    
    $pdf->Cell(12, 7, $al['asistencia'] . '%', 1, 0, 'C');
    $pdf->Cell(25, 7, txt($al['nombre_condicion'] ?? 'CURSANDO'), 1, 0, 'C');
    $pdf->Cell(26, 7, '', 1, 1); // Columna vacía para observaciones manuales
}

$pdf->Output('I', 'Planilla_' . str_replace(' ', '_', $info['nombre_espacio']) . '.pdf');