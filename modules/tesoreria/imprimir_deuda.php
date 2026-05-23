<?php
session_start();
// Agregamos esto temporalmente para ver si hay algún otro error de ruta
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesoreria']);

$uuid_alumno = $_GET['uuid'] ?? null;
$anio_actual = date('Y');

if (!$uuid_alumno) {
    die("Identificador de alumno no proporcionado.");
}

try {
    // 1. Obtener Configuración (Aseguramos traer la columna de WhatsApp si existe)
    // Nota: Si la columna se llama diferente en tu tabla, ajústala aquí.
    $stmt_conf = $pdo->query("SELECT cbu_institucion, alias_institucion, titular_cuenta, telefono, email FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt_conf->fetch();

    // 2. Obtener datos del alumno mediante UUID
    $stmt = $pdo->prepare("SELECT a.id_alumno, a.nombre, a.apellido, a.dni, a.telefono, a.beca_mensualidad, c.nombre_carrera, cp.monto_sugerido 
                           FROM alumnos a 
                           LEFT JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno 
                           LEFT JOIN carreras c ON ac.id_carreras = c.id_carrera
                           LEFT JOIN conceptos_pago cp ON (cp.id_carrera = c.id_carrera OR cp.id_carrera = 0) 
                                AND cp.categoria = 'Mensualidad' AND cp.activo = 1
                           WHERE a.uuid_alumno = ?");
    $stmt->execute([$uuid_alumno]);
    $alumno = $stmt->fetch();

    if (!$alumno) { die("Alumno no encontrado."); }

    $id_alumno = $alumno['id_alumno'];
    $tel_limpio = preg_replace('/[^0-9]/', '', $alumno['telefono'] ?? '');

    // 3. Lógica de Deuda
    $mes_actual = (int)date('n');
    $cuotas_esperadas = ($mes_actual >= 3) ? ($mes_actual - 2) : 0;

    $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd 
                             INNER JOIN facturas f ON fd.id_factura = f.id_factura 
                             INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                             WHERE f.id_alumno = ? AND fd.anio_lectivo = ? AND f.estado = 'Pagado' AND cp.categoria = 'Mensualidad'");
    $stmt_p->execute([$id_alumno, $anio_actual]);
    $pagas = (int)$stmt_p->fetchColumn();

    $deuda_meses = ($cuotas_esperadas > $pagas) ? ($cuotas_esperadas - $pagas) : 0;
    
    // Aplicamos beca
    $monto_base = (float)($alumno['monto_sugerido'] ?? 0);
    $beca = (int)($alumno['beca_mensualidad'] ?? 0);
    $monto_con_beca = $monto_base - ($monto_base * ($beca / 100));
    
    $total_a_pagar = $deuda_meses * $monto_con_beca;
    $nombre_completo = $alumno['apellido'] . ", " . $alumno['nombre'];

    // 4. Mensajes y QR
    $mensaje_digital = "✅ *ESTADO DE CUENTA - SINTEK*\n\n";
    $mensaje_digital .= "Alumno: *{$nombre_completo}*\n";
    $mensaje_digital .= "Deuda: *{$deuda_meses} meses*\n";
    $mensaje_digital .= "Total a regularizar: *$" . number_format($total_a_pagar, 2, ',', '.') . "*\n\n";
    $mensaje_digital .= "--- DATOS PARA TRANSFERENCIA ---\n";
    $mensaje_digital .= "Titular: {$config['titular_cuenta']}\n";
    $mensaje_digital .= "CBU: {$config['cbu_institucion']}\n";
    $mensaje_digital .= "Alias: *{$config['alias_institucion']}*\n";
    $mensaje_digital .= "--------------------\n\n";
    $mensaje_digital .= "Por favor, reenvíe el comprobante por este medio.";

    $url_whatsapp = "https://wa.me/54" . $tel_limpio . "?text=" . urlencode($mensaje_digital);
    
    // CORRECCIÓN AQUÍ: Usamos el teléfono de la configuración que sí tenemos ($config['telefono'])
    $tel_inst = preg_replace('/[^0-9]/', '', $config['telefono'] ?? '');
    $url_qr = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode("https://wa.me/".$tel_inst."?text=Hola, envío comprobante de pago de " . $nombre_completo); 

} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Deuda - <?= htmlspecialchars($alumno['apellido']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f4f4f4; padding: 20px; }
        .documento { max-width: 700px; margin: auto; background: white; padding: 40px; border: 1px solid #ddd; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #003366; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .seccion-pago { background: #eef2f7; padding: 20px; border-radius: 8px; display: flex; align-items: center; margin-top: 20px; }
        .qr-container { margin-left: 20px; text-align: center; border-left: 1px solid #ccc; padding-left: 20px; }
        .btn { padding: 10px 20px; border-radius: 5px; text-decoration: none; display: inline-block; font-weight: bold; }
        .btn-print { background: #333; color: white; }
        .btn-wa { background: #25d366; color: white; margin-left: 10px; }
        @media print { .no-print { display: none; } .documento { box-shadow: none; border: none; margin: 0; width: 100%; } }
    </style>
</head>
<body>

    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <a href="javascript:window.print()" class="btn btn-print"><i class="fas fa-print"></i> Imprimir</a>
        <a href="<?= $url_whatsapp ?>" target="_blank" class="btn btn-wa"><i class="fab fa-whatsapp"></i> Enviar al Alumno</a>
    </div>

    <div class="documento">
        <div class="header">
            <div>
                <h2 style="margin:0; color: #003366;">ESTADO DE CUENTA</h2>
                <small>Ciclo Lectivo <?= $anio_actual ?></small>
            </div>
            <div style="text-align: right;">
                <strong>Fecha:</strong> <?= date('d/m/Y') ?>
            </div>
        </div>

        <p><strong>Alumno:</strong> <?= $nombre_completo ?></p>
        <p><strong>DNI:</strong> <?= $alumno['dni'] ?> | <strong>Carrera:</strong> <?= $alumno['nombre_carrera'] ?></p>

        <table style="width:100%; border-collapse: collapse; margin: 20px 0;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Detalle</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center;">Meses</th>
                    <th style="border: 1px solid #ddd; padding: 12px; text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px;">Cuotas Mensuales Adeudadas</td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: center;"><?= $deuda_meses ?></td>
                    <td style="border: 1px solid #ddd; padding: 12px; text-align: right; font-weight: bold;">$<?= number_format($total_a_pagar, 2, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="seccion-pago">
            <div style="flex: 1;">
                <h4 style="margin: 0 0 10px 0;">Información de Pago</h4>
                <p style="margin: 5px 0;"><strong>Titular:</strong> <?= $config['titular_cuenta'] ?></p>
                <p style="margin: 5px 0;"><strong>CBU:</strong> <?= $config['cbu_institucion'] ?></p>
                <p style="margin: 5px 0;"><strong>Alias:</strong> <span style="border: 1px dashed #333; padding: 2px 5px;"><?= $config['alias_institucion'] ?></span></p>
            </div>
            <div class="qr-container">
                <img src="<?= $url_qr ?>" alt="QR">
                <small style="display:block; font-size: 9px; margin-top: 5px; color: #666;">Escanear para enviar<br>comprobante</small>
            </div>
        </div>

        <div style="margin-top: 30px; font-size: 11px; color: #777; border-top: 1px solid #eee; padding-top: 10px;">
            Este documento es una liquidación informativa. El recibo oficial se emite al procesar el pago.
        </div>
    </div>

</body>
</html>