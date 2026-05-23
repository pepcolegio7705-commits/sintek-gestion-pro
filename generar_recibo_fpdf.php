<?php
require('fpdf/fpdf.php'); 
require('conexion.php');
require 'seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

$id_liquidacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reimpresion_solicitada = isset($_GET['reprint']) && $_GET['reprint'] == 1;

// 1. DETECTAR TIPO DE PERSONA
$stmt_tipo = $pdo->prepare("SELECT tipo_persona, id_persona FROM liquidaciones_haberes WHERE id_liquidacion = ?");
$stmt_tipo->execute([$id_liquidacion]);
$liq_info = $stmt_tipo->fetch();

if (!$liq_info) die("Liquidación no encontrada.");

$tipo = $liq_info['tipo_persona'];
$tabla_origen = ($tipo == 'Staff') ? 'personal_staff' : 'profesores';
$columna_id = ($tipo == 'Staff') ? 'id_staff' : 'id_profesor';

// 2. OBTENER DATOS COMPLETOS
$sql = "SELECT l.*, 
               p.nombre as p_nombre, p.apellido as p_apellido, p.cuil, p.legajo,
               c.nombre_institucion, c.domicilio, c.telefono, c.email_contacto,
               ct.porcentaje_jubilacion, ct.porcentaje_obra_social -- Traemos porcentajes de la config
        FROM liquidaciones_haberes l
        JOIN $tabla_origen p ON l.id_persona = p.$columna_id
        CROSS JOIN configuracion c 
        CROSS JOIN configuracion_tesoreria ct
        WHERE l.id_liquidacion = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id_liquidacion]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("Error al vincular datos.");

// 3. OBTENER EXPEDIENTES JUDICIALES (Para el desglose)
$stmt_exp = $pdo->prepare("SELECT beneficiario_nombre, nro_expediente, valor, tipo_calculo 
                           FROM retenciones_judiciales 
                           WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
$stmt_exp->execute([$data['id_persona'], $tipo]);
$expedientes = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);

function decode_text($text) {
    return mb_convert_encoding($text ?? '', 'ISO-8859-1', 'UTF-8');
}

class PDF extends FPDF {
    function RotatedText($x, $y, $txt, $angle) {
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', cos(deg2rad($angle)), sin(deg2rad($angle)), -sin(deg2rad($angle)), cos(deg2rad($angle)), $x, $y, -$x, -$y));
        $this->Text($x, $y, $txt);
        $this->_out('Q');
    }

    function DibujarRecibo($y_offset, $data, $expedientes, $tipo_copia, $reimpresion) {
        $this->SetY($y_offset);
        
        // Marcas de Agua
        if ($data['estado'] == 'Anulado') {
            $this->SetFont('Arial', 'B', 50); $this->SetTextColor(255, 200, 200);
            $this->RotatedText(50, $y_offset + 60, "ANULADO", 35);
        } elseif ($reimpresion) {
            $this->SetFont('Arial', 'B', 30); $this->SetTextColor(235, 235, 235);
            $this->RotatedText(35, $y_offset + 55, "REIMPRESION - COPIA", 35);
        }
        $this->SetTextColor(0, 0, 0);

        // Encabezado
        $ruta_logo = 'img/logo.png';
        if(file_exists($ruta_logo)) $this->Image($ruta_logo, 10, $y_offset, 22);

        $this->SetX(35); $this->SetFont('Arial', 'B', 12);
        $this->Cell(100, 6, decode_text($data['nombre_institucion']), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(60, 6, decode_text($tipo_copia), 1, 1, 'C');
        
        $this->SetX(35); $this->SetFont('Arial', '', 7);
        $info_contacto = $data['domicilio'] . " | Tel: " . $data['telefono'];
        $this->Cell(100, 4, decode_text($info_contacto), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(60, 4, "Recibo: 0001-" . str_pad($data['id_liquidacion'], 8, "0", STR_PAD_LEFT), 0, 1, 'C');

        $this->Ln(8);

        // Tabla Datos Agente
        $this->SetFillColor(245, 245, 245); $this->SetFont('Arial', 'B', 8);
        $this->Cell(95, 6, "Apellido y Nombre", 1, 0, 'L', true);
        $this->Cell(35, 6, "CUIL", 1, 0, 'L', true);
        $this->Cell(30, 6, "Legajo", 1, 0, 'L', true);
        $this->Cell(30, 6, "Periodo", 1, 1, 'L', true);

        $this->SetFont('Arial', '', 9);
        $this->Cell(95, 7, decode_text($data['p_apellido'] . ", " . $data['p_nombre']), 1, 0, 'L');
        $this->Cell(35, 7, $data['cuil'], 1, 0, 'L');
        $this->Cell(30, 7, $data['legajo'], 1, 0, 'L');
        $this->Cell(30, 7, $data['mes_liquidado'] . "/" . $data['anio_liquidado'], 1, 1, 'L');

        $this->Ln(3);

        // Encabezado Detalle
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(110, 6, "Concepto", 1, 0, 'L', true);
        $this->Cell(40, 6, "Haberes", 1, 0, 'C', true);
        $this->Cell(40, 6, "Descuentos", 1, 1, 'C', true);

        $this->SetFont('Arial', '', 8.5);

        // --- HABERES ---
        $basico = $data['monto_bruto'] - $data['monto_antiguedad_aplicado'] - $data['monto_asignacion_hijos'];
        $desc_b = ($data['tipo_persona'] == 'Staff') ? "Sueldo Basico Mensual" : "Sueldo Basico (" . $data['total_horas_catedra'] . " hs)";
        $this->Cell(110, 6, decode_text($desc_b), 1, 0, 'L');
        $this->Cell(40, 6, number_format($basico, 2, ',', '.'), 1, 0, 'R');
        $this->Cell(40, 6, "", 1, 1, 'R');

        if($data['monto_antiguedad_aplicado'] > 0){
            $this->Cell(110, 6, decode_text("Adicional Antigüedad"), 1, 0, 'L');
            $this->Cell(40, 6, number_format($data['monto_antiguedad_aplicado'], 2, ',', '.'), 1, 0, 'R');
            $this->Cell(40, 6, "", 1, 1, 'R');
        }

        if($data['monto_asignacion_hijos'] > 0){
            $this->Cell(110, 6, decode_text("Asignacion Familiar por Hijo"), 1, 0, 'L');
            $this->Cell(40, 6, number_format($data['monto_asignacion_hijos'], 2, ',', '.'), 1, 0, 'R');
            $this->Cell(40, 6, "", 1, 1, 'R');
        }

        // --- DESCUENTOS DESGLOSADOS ---
        if($data['monto_retenciones_ley'] > 0){
            // Cálculo proporcional para desglose visual (Jubilación 11%, Obra Social 3% u otros)
            $p_jub = $data['porcentaje_jubilacion'] ?? 11;
            $p_os = $data['porcentaje_obra_social'] ?? 3;
            $total_p = $p_jub + $p_os;
            
            $val_jub = ($data['monto_retenciones_ley'] * $p_jub) / $total_p;
            $val_os = ($data['monto_retenciones_ley'] * $p_os) / $total_p;

            $this->Cell(110, 6, decode_text("Aporte Jubilatorio ($p_jub%)"), 1, 0, 'L');
            $this->Cell(40, 6, "", 1, 0, 'R');
            $this->Cell(40, 6, number_format($val_jub, 2, ',', '.'), 1, 1, 'R');

            $this->Cell(110, 6, decode_text("Aporte Obra Social ($p_os%)"), 1, 0, 'L');
            $this->Cell(40, 6, "", 1, 0, 'R');
            $this->Cell(40, 6, number_format($val_os, 2, ',', '.'), 1, 1, 'R');
        }

        // --- RETENCIONES JUDICIALES CON EXPEDIENTE ---
        if($data['monto_retenciones_judiciales'] > 0){
            foreach($expedientes as $exp){
                $monto_exp = ($exp['tipo_calculo'] == 'Porcentaje') ? ($data['monto_bruto'] * $exp['valor'] / 100) : $exp['valor'];
                
                $txt_exp = "Ret. Judicial - Expte: " . $exp['nro_expediente'] . " (" . $exp['beneficiario_nombre'] . ")";
                $this->Cell(110, 6, decode_text($txt_exp), 1, 0, 'L');
                $this->Cell(40, 6, "", 1, 0, 'R');
                $this->Cell(40, 6, number_format($monto_exp, 2, ',', '.'), 1, 1, 'R');
            }
        }

        // Totales
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(110, 7, "TOTALES", 1, 0, 'R', true);
        $this->Cell(40, 7, number_format($data['monto_bruto'], 2, ',', '.'), 1, 0, 'R', true);
        $this->Cell(40, 7, number_format($data['monto_retenciones'], 2, ',', '.'), 1, 1, 'R', true);

        $this->SetFont('Arial', 'B', 11);
        $this->Cell(150, 10, "NETO A PERCIBIR", 1, 0, 'R', true);
        $this->Cell(40, 10, "$ " . number_format($data['monto_neto'], 2, ',', '.'), 1, 1, 'C', true);

        // Firmas
        $this->Ln(15);
        $y_f = $this->GetY();
        $this->Line(30, $y_f, 80, $y_f); $this->Line(130, $y_f, 180, $y_f);
        $this->SetFont('Arial', '', 7);
        $this->Text(43, $y_f + 4, "Firma Empleador"); $this->Text(145, $y_f + 4, "Firma Empleado");
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$pdf->DibujarRecibo(10, $data, $expedientes, "ORIGINAL", $reimpresion_solicitada);
$pdf->SetDrawColor(180, 180, 180); $pdf->Line(0, 148, 210, 148);
$pdf->DibujarRecibo(155, $data, $expedientes, "DUPLICADO", $reimpresion_solicitada);

$pdf->Output('I', 'Recibo_' . $data['p_apellido'] . '.pdf');