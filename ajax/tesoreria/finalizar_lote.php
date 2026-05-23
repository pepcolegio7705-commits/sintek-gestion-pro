<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

$id_lote = (int)($_POST['id_lote'] ?? 0);
$accion  = $_POST['accion'] ?? ''; 
$user    = $_POST['user'] ?? '';
$pass    = $_POST['pass'] ?? '';
$motivo  = trim($_POST['motivo'] ?? '');

try {
    // 1. VALIDACIÓN DE IDENTIDAD
    $stmt = $pdo->prepare("SELECT id_usuario, password, id_rol FROM usuarios WHERE nombre_usuario = ? AND estado = 1");
    $stmt->execute([$user]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($pass, $u['password']) || $u['id_rol'] != 1) {
        throw new Exception("Credenciales de administrador inválidas o nivel de acceso insuficiente.");
    }

    $pdo->beginTransaction();

    // 2. BLOQUEO Y CHEQUEO DEL LOTE
    $stmtL = $pdo->prepare("SELECT estado, total_monto FROM lotes_liquidaciones WHERE id_lote = ? FOR UPDATE");
    $stmtL->execute([$id_lote]);
    $loteInfo = $stmtL->fetch();

    if (!$loteInfo) throw new Exception("El lote especificado no existe.");
    if ($loteInfo['estado'] !== 'Pendiente') throw new Exception("Operación cancelada: El lote ya se encuentra " . $loteInfo['estado']);

    $nuevo_estado = ($accion === 'confirmar') ? 'Procesado' : 'Anulado';

    // 3. VALIDACIÓN DE INTEGRIDAD (CORREGIDA PARA STAFF)
    if ($accion === 'confirmar') {
        $stmtSum = $pdo->prepare("SELECT SUM(monto_neto) FROM liquidaciones_haberes WHERE id_lote = ?");
        $stmtSum->execute([$id_lote]);
        $totalReal = (float)$stmtSum->fetchColumn();

        // REDONDEO A 2 DECIMALES PARA EVITAR ERRORES DE PRECISIÓN FLOTANTE
        $totalRealRedondeado = round($totalReal, 2);
        $totalLoteRedondeado = round((float)$loteInfo['total_monto'], 2);

        if (abs($totalRealRedondeado - $totalLoteRedondeado) > 0.01) {
            throw new Exception("Error de integridad: El detalle ($totalRealRedondeado) no coincide con el total del lote ($totalLoteRedondeado).");
        }
    } else {
        if (strlen($motivo) < 10) throw new Exception("Debe ingresar un motivo válido (mín. 10 caracteres).");
    }

    // 4. GENERAR HISTORIAL (FOTOGRAFÍA INMUTABLE)
    $sql_historial = "INSERT INTO historial_liquidaciones 
        (id_lote, id_persona, tipo_persona, apellido_nombre_hist, dni_hist, cbu_hist, monto_bruto, monto_retenciones, monto_neto, estado_final_lote)
        
        SELECT 
            lh.id_lote, lh.id_persona, lh.tipo_persona, 
            CONCAT(COALESCE(p.apellido, s.apellido, 'S/D'), ', ', COALESCE(p.nombre, s.nombre, 'S/D')),
            COALESCE(p.dni, s.dni, '0'), COALESCE(p.cbu, s.cbu, ''),
            lh.monto_bruto, lh.monto_retenciones, lh.monto_neto, ?
        FROM liquidaciones_haberes lh
        LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor')
        LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona = 'Staff')
        WHERE lh.id_lote = ?

        UNION ALL

        SELECT 
            lt.id_lote, 0, 'Tercero', 
            lt.beneficiario_nombre, 
            lt.cuit_beneficiario, 
            lt.cbu_destino,
            lt.monto, 0, lt.monto, ?
        FROM liquidaciones_terceros lt
        WHERE lt.id_lote = ?";

    $pdo->prepare($sql_historial)->execute([$nuevo_estado, $id_lote, $nuevo_estado, $id_lote]);

    // 5. ACTUALIZACIÓN DE ESTADOS Y GASTOS
    if ($accion === 'confirmar') {
        $pdo->prepare("UPDATE liquidaciones_haberes SET estado = 'Procesado' WHERE id_lote = ?")->execute([$id_lote]);
        $pdo->prepare("UPDATE liquidaciones_terceros SET estado = 'Procesado' WHERE id_lote = ?")->execute([$id_lote]);

        $stmtG = $pdo->prepare("SELECT id_gasto_vinculado FROM liquidaciones_haberes WHERE id_lote = ? LIMIT 1");
        $stmtG->execute([$id_lote]);
        $id_gasto = $stmtG->fetchColumn();

        if ($id_gasto) {
            $pdo->prepare("UPDATE gastos SET estado = 'Pagado' WHERE id_gasto = ?")->execute([$id_gasto]);
        }
    } else {
        // Anulación: Limpieza y liberación
        $stmtG = $pdo->prepare("SELECT id_gasto_vinculado FROM liquidaciones_haberes WHERE id_lote = ? LIMIT 1");
        $stmtG->execute([$id_lote]);
        $id_gasto = $stmtG->fetchColumn();

        if ($id_gasto) {
            $pdo->prepare("UPDATE gastos SET estado = 'Anulado', motivo_anulacion = ? WHERE id_gasto = ?")
                ->execute(["Lote de haberes #$id_lote anulado.", $id_gasto]);
        }

        $pdo->prepare("DELETE FROM liquidaciones_haberes WHERE id_lote = ?")->execute([$id_lote]);
        $pdo->prepare("DELETE FROM liquidaciones_terceros WHERE id_lote = ?")->execute([$id_lote]);
    }

    // Actualizamos cabecera del lote
    $pdo->prepare("UPDATE lotes_liquidaciones SET estado = ?, observaciones = ?, id_user_autoriza = ?, fecha_autorizacion = NOW() WHERE id_lote = ?")
        ->execute([$nuevo_estado, $motivo, $u['id_usuario'], $id_lote]);

    // 6. LOG DE AUDITORÍA
    $ip = $_SERVER['REMOTE_ADDR'];
    $detalles_log = "Lote #$id_lote marcado como $nuevo_estado. Monto: {$loteInfo['total_monto']}.";
    $pdo->prepare("INSERT INTO log_tesoreria (uuid_log, fecha_hora, id_usuario, operacion, detalles, ip_address) VALUES (UUID(), NOW(), ?, ?, ?, ?)")
        ->execute([$u['id_usuario'], ($accion === 'confirmar' ? 'CONFIRMACION_PAGO' : 'ANULACION_LOTE'), $detalles_log, $ip]);

    // 7. GENERACIÓN DEL TXT (Solo confirmar)
    $txtBase64 = null; $nombreArchivo = "";
    if ($accion === 'confirmar') {
        require_once 'motor_generador_txt.php'; 
        $txtContenido = generarContenidoTXT($id_lote, $pdo);
        $txtBase64 = base64_encode($txtContenido);
        $nombreArchivo = "ORDEN_PAGO_LOTE_{$id_lote}_" . date('Ymd') . ".txt";
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'mensaje' => "El lote ha sido $nuevo_estado correctamente.", 'archivo' => $txtBase64, 'nombre_archivo' => $nombreArchivo]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}