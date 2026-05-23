<?php
session_start();
require('../../fpdf/fpdf.php'); // Asegúrate de que la ruta a FPDF sea correcta
require '../../core/conexion.php';
require '../../core/seguridad.php';

// Verificar permisos
verificar_permisos(['Administrador', 'Tesoreria']);

// 1. CAPTURA POR UUID (Seguridad)
$uuid_alumno = $_GET['uuid'] ?? null;
if (!$uuid_alumno) die("Identificador de alumno no proporcionado.");

/**
 * Función de ayuda para caracteres especiales ISO
 */
function e($texto) {
    return mb_convert_encoding($texto ?? '', 'ISO-8859-1', 'UTF-8');
}

// 2. OBTENCIÓN DE DATOS DEL ALUMNO
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE uuid_alumno = ?");
$stmt->execute([$uuid_alumno]);
$alumno = $stmt->fetch();

if (!$alumno) die("Alumno no encontrado.");
$id_alumno = $alumno['id_alumno'];

// 3. CONFIGURACIÓN DE TESORERÍA DINÁMICA
$conf = $pdo->query("SELECT ciclo_lectivo_actual, cuotas_por_ciclo FROM configuracion_tesoreria WHERE id_config_teso = 1")->fetch();
$ciclo_actual = $conf['ciclo_lectivo_actual'] ?? date('Y');
$total_cuotas_definidas = $conf['cuotas_por_ciclo'] ?? 11;

// 4. TOTAL HISTÓRICO ACUMULADO
$stmt_t = $pdo->prepare("SELECT SUM(total) FROM facturas WHERE id_alumno = ? AND estado = 'Pagado'");
$stmt_t->execute([$id_alumno]);
$total_historico = $stmt_t->fetchColumn() ?? 0;

// 5. CONSULTA DE HISTORIAL (JOIN CON CARRERAS)
$sql_h = "SELECT f.fecha_emision, fd.monto_cobrado, cp.nombre_concepto, cp.categoria, 
                  fd.mes_correspondiente, fd.anio_lectivo, mp.nombre_modo, c.nombre_carrera
           FROM facturas f
           JOIN factura_detalle fd ON f.id_factura = fd.id_factura
           JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
           JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
           LEFT JOIN carreras c ON cp.id_carrera = c.id_carrera
           WHERE f.id_alumno = ? AND f.estado = 'Pagado'
           ORDER BY f.fecha_emision DESC";
$stmt_h = $pdo->prepare($sql_h);
$stmt_h->execute([$id_alumno]);
$historial = $stmt_h->fetchAll();

// --- CLASE PDF PERSONALIZADA ---
class PDF extends FPDF {
    function Header() {
        if (file_exists('../../assets/img/logo.png')) { // Ruta corregida al logo
            $this->Image('../../assets/img/logo.png', 10, 8, 25);
        }
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(40); 
        $this->Cell(110, 10, e('ESTADO DE CUENTA FINANCIERO'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(40);
        $this->Cell(110, 5, e('Sistema de Gestión Sintek Premium'), 0, 1, 'C');
        $this->Ln(10);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, e('Fecha de impresión: ') . date('d/m/Y H:i') . ' - Página ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function getNombreMes($n) {
        $meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        return isset($meses[$n]) ? $meses[$n] : "N/A";
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// SECCIÓN 1: DATOS DEL ALUMNO
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(0, 7, e(" INFORMACIÓN DEL ALUMNO"), 0, 1, 'L', true);
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(35, 6, e('Apellido y Nombre: '), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, e($alumno['apellido'] . ', ' . $alumno['nombre']), 0, 1);
$pdf->Cell(35, 6, 'DNI / Legajo:', 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 6, $alumno['dni'] . ' / ' . ($alumno['legajo'] ?? 'S/D'), 0, 1);
$pdf->Ln(5);

// SECCIÓN 2: ESTADO DE OBLIGACIONES
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, e(" RESUMEN DE OBLIGACIONES POR CARRERA (Ciclo $ciclo_actual)"), 0, 1, 'L', true);
$pdf->Ln(2);

$stmt_car = $pdo->prepare("SELECT c.id_carrera, c.nombre_carrera FROM alumnos_carreras ac JOIN carreras c ON ac.id_carreras = c.id_carrera WHERE ac.id_alumno = ?");
$stmt_car->execute([$id_alumno]);
$carreras = $stmt_car->fetchAll();

foreach($carreras as $c) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(0, 51, 102); 
    $pdf->Cell(0, 6, e(">> " . $c['nombre_carrera']), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetFont('Arial', '', 8);
    $categorias = ['Matricula', 'Mensualidad'];
    foreach($categorias as $cat) {
        $st_c = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto WHERE f.id_alumno = ? AND cp.id_carrera = ? AND cp.categoria = ? AND f.estado = 'Pagado' AND fd.anio_lectivo = ?");
        $st_c->execute([$id_alumno, $c['id_carrera'], $cat, $ciclo_actual]);
        $count = $st_c->fetchColumn();

        if($cat == 'Mensualidad') {
            $txt_estado = "$count de $total_cuotas_definidas cuotas pagadas";
        } else {
            $txt_estado = ($count > 0) ? "PAGADO" : "PENDIENTE";
        }

        $pdf->Cell(45, 5, e("   " . $cat . ": "), 0, 0);
        $pdf->Cell(0, 5, e($txt_estado), 0, 1);
    }
    $pdf->Ln(2);
}

// SECCIÓN 3: TABLA DE HISTORIAL DETALLADO
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, e(" DETALLE CRONOLÓGICO DE PAGOS"), 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(220, 220, 220);
$pdf->Cell(20, 7, 'FECHA', 1, 0, 'C', true);
$pdf->Cell(45, 7, 'CARRERA', 1, 0, 'C', true);
$pdf->Cell(60, 7, 'CONCEPTO / PERIODO', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'MODO', 1, 0, 'C', true);
$pdf->Cell(15, 7, e('AÑO'), 1, 0, 'C', true);
$pdf->Cell(25, 7, 'MONTO', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 7);
foreach($historial as $h) {
    $concepto = $h['nombre_concepto'];
    if($h['categoria'] == 'Mensualidad' && $h['mes_correspondiente'] > 0) {
        $concepto .= " - " . $pdf->getNombreMes($h['mes_correspondiente']);
    }
    
    $nombre_carrera = !empty($h['nombre_carrera']) ? $h['nombre_carrera'] : 'INSTITUCIONAL';

    $pdf->Cell(20, 6, date('d/m/Y', strtotime($h['fecha_emision'])), 1, 0, 'C');
    $pdf->Cell(45, 6, e(substr($nombre_carrera, 0, 32)), 1, 0, 'L'); 
    $pdf->Cell(60, 6, e(substr($concepto, 0, 40)), 1, 0, 'L');
    $pdf->Cell(25, 6, e($h['nombre_modo']), 1, 0, 'C');
    $pdf->Cell(15, 6, $h['anio_lectivo'], 1, 0, 'C');
    $pdf->Cell(25, 6, '$ ' . number_format($h['monto_cobrado'], 2, ',', '.'), 1, 1, 'R');
}

// SECCIÓN 4: TOTAL ACUMULADO
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(165, 7, e('INVERSIÓN TOTAL ACUMULADA:'), 0, 0, 'R');
$pdf->SetFillColor(255, 255, 200); 
$pdf->Cell(25, 7, '$ ' . number_format($total_historico, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(20);
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 5, e("Este documento tiene carácter de declaración informativa de pagos realizados. No reemplaza a los recibos oficiales emitidos en cada transacción."), 0, 'C');

$pdf->Output('I', 'Ficha_Financiera_' . $alumno['dni'] . '.pdf');