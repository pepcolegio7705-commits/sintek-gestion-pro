<?php
require 'conexion.php';

$id_alumno = (int)$_GET['id'];
$anio_actual = date('Y');

try {
    // 1. Obtener Datos Bancarios de la Institución
    $stmt_conf = $pdo->query("SELECT cbu_institucion, alias_institucion, titular_cuenta, telefono, email, limite_meses_mora FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt_conf->fetch();

    // 2. Obtener datos del alumno y el monto de su cuota
    $stmt = $pdo->prepare("SELECT a.nombre, a.apellido, a.dni, a.telefono, c.nombre_carrera, cp.monto_sugerido 
                           FROM alumnos a 
                           LEFT JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno 
                           LEFT JOIN carreras c ON ac.id_carreras = c.id_carrera
                           LEFT JOIN conceptos_pago cp ON cp.id_carrera = c.id_carrera AND cp.categoria = 'Mensualidad' AND cp.activo = 1
                           WHERE a.id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch();

    // --- CORRECCIÓN 1: Definir $tel_limpio con el teléfono del ALUMNO ---
    $tel_limpio = preg_replace('/[^0-9]/', '', $alumno['telefono'] ?? '');

    // 3. Lógica de Deuda
    $mes_actual = date('n');
    $cuotas_esperadas = ($mes_actual >= 3) ? ($mes_actual - 2) : 0;

    $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd 
                             INNER JOIN facturas f ON fd.id_factura = f.id_factura 
                             WHERE f.id_alumno = ? AND fd.anio_lectivo = ? AND f.estado = 'Pagado'");
    $stmt_p->execute([$id_alumno, $anio_actual]);
    $pagas = $stmt_p->fetchColumn();

    $deuda_meses = ($cuotas_esperadas > $pagas) ? ($cuotas_esperadas - $pagas) : 0;
    $total_a_pagar = $deuda_meses * ($alumno['monto_sugerido'] ?? 0);
    $nombre_completo = $alumno['apellido'] . ", " . $alumno['nombre'];

    // 4. QR de WhatsApp (para que el alumno escriba a la institución)
    $msg_qr = "Hola, adjunto comprobante de pago de " . $nombre_completo . " por un total de $" . number_format($total_a_pagar, 2, ',', '.');
    $url_qr = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://wa.me/5492804671799?text=" . $msg_qr); 

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// --- CORRECCIÓN 2: Saltos de línea con PHP_EOL o \n en comillas dobles ---
$mensaje_digital = "✅ *CONSTANCIA DE DEUDA DIGITAL*\n\n";
$mensaje_digital .= "Alumno: *{$nombre_completo}*\n";
$mensaje_digital .= "Total: *$" . number_format($total_a_pagar, 2, ',', '.') . "* ({$deuda_meses} meses)\n\n";
$mensaje_digital .= "--- DATOS DE PAGO ---\n";
$mensaje_digital .= "Titular: {$config['titular_cuenta']}\n";
$mensaje_digital .= "CBU: {$config['cbu_institucion']}\n";
$mensaje_digital .= "Alias: {$config['alias_institucion']}\n";
$mensaje_digital .= "--------------------\n\n";
$mensaje_digital .= "Por favor, una vez realizado el pago, adjunte el comprobante por este medio.";

// --- CORRECCIÓN 3: Verificar el prefijo del país ---
// Si el teléfono ya viene con 54, hay que tener cuidado de no duplicarlo.
$url_whatsapp = "https://wa.me/54" . $tel_limpio . "?text=" . urlencode($mensaje_digital);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Constancia de Deuda - <?php echo $alumno['apellido']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; color: #333; }
        .documento { max-width: 700px; margin: 20px auto; border: 1px solid #eee; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.05); position: relative; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .datos-alumno { margin-bottom: 30px; }
        .tabla-deuda { w-100; border-collapse: collapse; margin-bottom: 30px; width: 100%; }
        .tabla-deuda th, .tabla-deuda td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .tabla-deuda th { background-color: #f8f9fa; }
        .seccion-pago { background: #f1f5f9; padding: 20px; border-radius: 8px; display: flex; }
        .datos-bancos { flex: 1; }
        .qr-container { text-align: center; padding-left: 20px; border-left: 1px solid #cbd5e1; }
        .qr-container img { width: 120px; }
        .qr-label { font-size: 10px; display: block; margin-top: 5px; color: #64748b; }
        .footer { margin-top: 40px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
        @media print { .no-print { display: none; } .documento { border: none; box-shadow: none; } }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px;">
            <i class="fas fa-print"></i> Imprimir Documento
        </button>
        <a href="<?php echo $url_whatsapp; ?>" target="_blank" style="padding: 10px 20px; cursor: pointer; background: #25d366; color: #fff; border: none; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-flex; align-items: center;">
        <i class="fab fa-whatsapp" style="margin-right: 8px; font-size: 1.2rem;"></i> Compartir a WhatsApp
    </a>
    </div>

    <div class="documento">
        <div class="header">
            <div>
                <h1 style="margin:0;">ESTADO DE CUENTA</h1>
                <small>Ciclo Lectivo <?php echo $anio_actual; ?></small>
            </div>
            <div style="text-align: right;">
                <strong>Fecha:</strong> <?php echo date('d/m/Y'); ?><br>
                <strong>Vence:</strong> <?php echo date('t/m/Y'); ?>
            </div>
        </div>

        <div class="datos-alumno">
            <p><strong>Alumno:</strong> <?php echo $nombre_completo; ?></p>
            <p><strong>DNI:</strong> <?php echo $alumno['dni']; ?> | <strong>Carrera:</strong> <?php echo $alumno['nombre_carrera']; ?></p>
        </div>

        <table class="tabla-deuda">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Cantidad</th>
                    <th>Unitario</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Cuotas Mensuales Pendientes</td>
                    <td><?php echo $deuda_meses; ?></td>
                    <td>$<?php echo number_format($alumno['monto_sugerido'], 2, ',', '.'); ?></td>
                    <td style="text-align: right;"><strong>$<?php echo number_format($total_a_pagar, 2, ',', '.'); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="seccion-pago">
            <div class="datos-bancos">
                <h3 style="margin-top:0; color: #1e293b;">Información de Pago</h3>
                <p style="margin: 5px 0;"><strong>Titular:</strong> <?php echo $config['titular_cuenta']; ?></p>
                <p style="margin: 5px 0;"><strong>CBU:</strong> <?php echo $config['cbu_institucion']; ?></p>
                <p style="margin: 5px 0;"><strong>Alias:</strong> <span style="background: #fff; padding: 2px 5px; border: 1px dashed #333;"><?php echo $config['alias_institucion']; ?></span></p>
                <p style="margin: 5px 0;"><strong>Teléfono:</strong> <?php echo $config['telefono']; ?></p>
                <p style="margin: 5px 0;"><strong>Email:</strong> <?php echo $config['email']; ?></p>
            </div>
            <div class="qr-container">
                <img src="<?php echo $url_qr; ?>" alt="QR WhatsApp">
                <span class="qr-label">Escanee para enviar<br>comprobante</span>
            </div>
        </div>

        <div class="footer">
            Este documento es una liquidación informativa. El recibo oficial será emitido una vez que el pago sea procesado por el departamento administrativo.
        </div>
    </div>

</body>
</html>