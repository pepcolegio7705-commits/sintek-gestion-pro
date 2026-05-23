<?php
session_start();
require 'conexion.php';
require 'seguridad.php';
require 'fpdf/fpdf.php';

verificar_permisos(['Administrador']);

// 1. OBTENER CONFIGURACIÓN DE MORA
$stmt_conf = $pdo->query("SELECT limite_meses_mora FROM configuracion_tesoreria LIMIT 1");
$config = $stmt_conf->fetch();
$limite_mora = $config['limite_meses_mora'] ?? 3;
$mes_actual = date('m');

// 2. CONSULTA DE ALUMNOS MOROSOS (Solo los que superan el límite)
$sql = "SELECT 
            a.id_alumno, a.apellido, a.nombre, a.dni, a.email, a.telefono,
            GROUP_CONCAT(DISTINCT c.nombre_carrera SEPARATOR ' / ') as carreras,
            (:mes_actual - COUNT(DISTINCT fd.mes_correspondiente)) as meses_deuda
        FROM alumnos a
        JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno
        JOIN carreras c ON ac.id_carreras = c.id_carrera
        LEFT JOIN facturas f ON a.id_alumno = f.id_alumno AND f.estado = 'Pagado'
        LEFT JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
            AND fd.id_concepto IN (SELECT id_concepto FROM conceptos_pago WHERE categoria = 'Mensualidad')
            AND fd.anio_lectivo = YEAR(CURDATE())
        WHERE a.activo = 1
        GROUP BY a.id_alumno
        HAVING meses_deuda >= :limite
        ORDER BY meses_deuda DESC, a.apellido ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([':mes_actual' => $mes_actual, ':limite' => $limite_mora]);
$morosos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. GENERACIÓN DEL PDF
class PDF_Mora extends FPDF {
    function Header() {
        $fecha_generacion = date('d/m/Y H:i'); // Fecha y hora actual
        
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, mb_convert_encoding('INFORME DE MOROSIDAD CRÍTICA', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        
        // --- NUEVA EXPLICACIÓN DE FECHA ---
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $texto_fecha = "Estado de cuenta consolidado al: " . $fecha_generacion;
        $this->Cell(0, 5, mb_convert_encoding($texto_fecha, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        
        $nota_validez = "Nota: Este informe refleja las deudas registradas en sistema hasta la fecha mencionada. Pagos realizados en las últimas 24hs podrían no verse reflejados.";
        $this->SetFont('Arial', 'I', 7);
        $this->Cell(0, 5, mb_convert_encoding($nota_validez, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        
        $this->Ln(5);
        
        // Encabezados de tabla (Volvemos a colores normales)
        $this->SetFillColor(200, 0, 0); 
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(55, 8, 'APELLIDO Y NOMBRE', 1, 0, 'C', true);
        $this->Cell(20, 8, 'DNI', 1, 0, 'C', true);
        $this->Cell(15, 8, 'MESES', 1, 0, 'C', true);
        $this->Cell(60, 8, 'CARRERAS', 1, 0, 'C', true);
        $this->Cell(40, 8, 'CONTACTO', 1, 1, 'C', true);
        $this->SetTextColor(0);
    }
    
    // Pie de página para reforzar la validez en cada hoja
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150);
        $this->Cell(0, 10, mb_convert_encoding('Sintek Premium - Reporte de Auditoría Interna - Generado el ' . date('d/m/Y H:i'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $this->Cell(0, 10, mb_convert_encoding('Página ', 'ISO-8859-1', 'UTF-8') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

$pdf = new PDF_Mora('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

foreach ($morosos as $m) {
    // Calculamos altura dinámica por si las carreras son muchas
    $x = $pdf->GetX();
    $y = $pdf->GetY();
    $push_right = 0;

    $pdf->Cell(55, 10, mb_convert_encoding($m['apellido'].', '.$m['nombre'], 'ISO-8859-1', 'UTF-8'), 1);
    $pdf->Cell(20, 10, $m['dni'], 1, 0, 'C');
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(15, 10, $m['meses_deuda'], 1, 0, 'C');
    $pdf->SetFont('Arial', '', 7);
    
    // Multicell para carreras (ajuste de texto)
    $currX = $pdf->GetX();
    $currY = $pdf->GetY();
    $pdf->MultiCell(60, 5, mb_convert_encoding($m['carreras'], 'ISO-8859-1', 'UTF-8'), 1, 'L');
    
    $pdf->SetXY($currX + 60, $currY);
    $contacto = $m['telefono'] . "\n" . $m['email'];
    $pdf->MultiCell(40, 5, $contacto, 1, 'L');
    
    // Línea de separación para que el siguiente no se encime si hubo multicell
    if ($pdf->GetY() < $currY + 10) $pdf->SetY($currY + 10);
}

if (count($morosos) == 0) {
    $pdf->Cell(0, 10, 'No se registran alumnos en mora critica.', 1, 1, 'C');
}

$pdf->Output('I', 'Reporte_Morosidad.pdf');