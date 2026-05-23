<?php
session_start();
require 'conexion.php';

// Mantenemos el JSON limpio
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

$id_lote  = $_POST['id'] ?? null;
$accion   = $_POST['accion'] ?? '';
$password = $_POST['password'] ?? '';
$usuario_admin = $_SESSION['nombre_usuario'] ?? null;

if (!$id_lote || !$usuario_admin) {
    echo json_encode(['success' => false, 'error' => 'Sesión expirada o datos incompletos.']);
    exit;
}

try {
    // 1. VALIDACIÓN DE IDENTIDAD (Aplica para Cerrar y Rechazar)
    $stmt_u = $pdo->prepare("SELECT password FROM usuarios WHERE nombre_usuario = ?");
    $stmt_u->execute([$usuario_admin]);
    $user = $stmt_u->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception("La contraseña de administrador es incorrecta.");
    }

    // --- ACCIÓN: CERRAR LOTE (GENERAR TXT Y PAGAR) ---
    if ($accion === 'cerrar') {
        $pdo->beginTransaction();

        $stmt_lote = $pdo->prepare("SELECT * FROM lotes_liquidaciones WHERE id_lote = ? AND estado = 'Pendiente'");
        $stmt_lote->execute([$id_lote]);
        $lote = $stmt_lote->fetch();
        if (!$lote) throw new Exception("El lote no existe o ya ha sido procesado.");

        $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
        $conf = $stmt_conf->fetch();

        // --- GENERACIÓN DE ARCHIVO GT (BANCO NACIÓN) ---
        $lineas_txt = [];
        $cbu_escuela = preg_replace('/\D/', '', $conf['cbu_institucion']);
        $cuit_escuela = preg_replace('/\D/', '', $conf['cuit_institucion']);
        $fecha_acred = date('Ymd', strtotime('+1 day')); 

        // Registro Tipo 1: Cabecera
        $lineas_txt[] = "1" . str_pad($cuit_escuela, 11, "0", STR_PAD_LEFT) . substr($cbu_escuela, 0, 4) . substr($cbu_escuela, 8, 14) . "0" . $fecha_acred . str_pad("", 154, " ");

        $stmt_liq = $pdo->prepare("SELECT * FROM liquidaciones_haberes WHERE id_lote = ?");
        $stmt_liq->execute([$id_lote]);
        $total_reg = 0;

        foreach ($stmt_liq->fetchAll() as $liq) {
            $tabla = ($liq['tipo_persona'] == 'Profesor') ? 'profesores' : 'personal_staff';
            $id_col = ($liq['tipo_persona'] == 'Profesor') ? 'id_profesor' : 'id_staff';
            $p = $pdo->prepare("SELECT nombre, apellido, dni, cbu, legajo FROM $tabla WHERE $id_col = ?");
            $p->execute([$liq['id_persona']]);
            $pers = $p->fetch();

            $lineas_txt[] = generarFilaTipo2(preg_replace('/\D/','',$pers['cbu']), $liq['monto_neto'], '1', $pers['apellido']." ".$pers['nombre'], $pers['legajo'] ?: $pers['dni']);
            $total_reg++;

            if ($liq['monto_retenciones_judiciales'] > 0) {
                $stmt_j = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
                $stmt_j->execute([$liq['id_persona'], $liq['tipo_persona']]);
                foreach ($stmt_j->fetchAll() as $rj) {
                    $monto_rj = ($rj['tipo_calculo'] == 'Porcentaje') ? ($liq['monto_bruto'] * $rj['valor'] / 100) : $rj['valor'];
                    $referencia_jud = str_replace('EXP:', '', $rj['nro_expediente']);
                    $lineas_txt[] = generarFilaTipo2(preg_replace('/\D/','',$rj['cbu_destino']), $monto_rj, 'A', $rj['beneficiario_nombre'], "EXP:".$referencia_jud);
                    $total_reg++;
                }
            }
        }

        $lineas_txt[] = "3" . str_pad($total_reg, 10, "0", STR_PAD_LEFT) . str_pad(number_format($lote['monto_total_neto'], 2, '', ''), 20, "0", STR_PAD_LEFT) . str_pad("", 169, " ");

        $txt_final = implode("\r\n", $lineas_txt);
        $hash_control = hash('sha256', $txt_final . $usuario_admin . "SAL_SECRETA_INSTITUTO_801");

        $pdo->prepare("UPDATE lotes_liquidaciones SET estado = 'Procesado', fecha_autorizacion = NOW(), usuario_autorizo = ?, hash_control = ? WHERE id_lote = ?")
            ->execute([$usuario_admin, $hash_control, $id_lote]);

        $pdo->prepare("UPDATE liquidaciones_haberes SET estado = 'Pagado' WHERE id_lote = ?")->execute([$id_lote]);
        
        $pdo->prepare("UPDATE gastos g JOIN liquidaciones_haberes lh ON g.id_referencia_pago = lh.id_liquidacion SET g.estado = 'Pagado' WHERE lh.id_lote = ?")
            ->execute([$id_lote]);

        $pdo->commit();
        echo json_encode(['success' => true, 'msg' => 'Lote autorizado y TXT generado.', 'txt_content' => base64_encode($txt_final)]);

    } 
    // --- ACCIÓN: RECHAZAR LOTE (LOG Y LIBERACIÓN) ---
    elseif ($accion === 'rechazar') {
        $motivo = $_POST['motivo'] ?? 'No especificado por el operador';
        $pdo->beginTransaction();
        
        // 1. ANULAR GASTOS
        $sql_gastos = "UPDATE gastos 
                       SET estado = 'Anulado', 
                           descripcion = CONCAT(descripcion, ' (LOTE RECHAZADO)') 
                       WHERE id_referencia_pago IN (
                           SELECT id_liquidacion FROM liquidaciones_haberes WHERE id_lote = ?
                       )";
        $pdo->prepare($sql_gastos)->execute([$id_lote]);
        
        // 2. ANULAR LIQUIDACIONES (Libera agentes)
        $sql_liq = "UPDATE liquidaciones_haberes SET estado = 'Anulado' WHERE id_lote = ?";
        $pdo->prepare($sql_liq)->execute([$id_lote]);
        
        // 3. ACTUALIZAR EL LOTE
        $sql_lote = "UPDATE lotes_liquidaciones 
                     SET estado = 'Rechazado', 
                         motivo_rechazo = ?, 
                         fecha_rechazo = NOW() 
                     WHERE id_lote = ?";
        $pdo->prepare($sql_lote)->execute([$motivo, $id_lote]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'msg' => 'Lote rechazado correctamente.']);

    } 
    // --- ACCIÓN: DESCARGAR EXISTENTE ---
    elseif ($accion === 'descargar_existente') {
        $stmt = $pdo->prepare("SELECT * FROM lotes_liquidaciones WHERE id_lote = ? AND estado = 'Procesado'");
        $stmt->execute([$id_lote]);
        $lote = $stmt->fetch();
        if (!$lote) throw new Exception("Lote no procesado.");

        $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
        $conf = $stmt_conf->fetch();

        $lineas_txt = [];
        $cbu_escuela = preg_replace('/\D/', '', $conf['cbu_institucion']);
        $cuit_escuela = preg_replace('/\D/', '', $conf['cuit_institucion']);
        $fecha_acred = date('Ymd', strtotime($lote['fecha_autorizacion'])); 

        $lineas_txt[] = "1" . str_pad($cuit_escuela, 11, "0", STR_PAD_LEFT) . substr($cbu_escuela, 0, 4) . substr($cbu_escuela, 8, 14) . "0" . $fecha_acred . str_pad("", 154, " ");

        $stmt_liq = $pdo->prepare("SELECT * FROM liquidaciones_haberes WHERE id_lote = ?");
        $stmt_liq->execute([$id_lote]);
        $total_reg = 0;

        foreach ($stmt_liq->fetchAll() as $liq) {
            $tabla = ($liq['tipo_persona'] == 'Profesor') ? 'profesores' : 'personal_staff';
            $id_col = ($liq['tipo_persona'] == 'Profesor') ? 'id_profesor' : 'id_staff';
            $p = $pdo->prepare("SELECT nombre, apellido, dni, cbu, legajo FROM $tabla WHERE $id_col = ?");
            $p->execute([$liq['id_persona']]);
            $pers = $p->fetch();

            $lineas_txt[] = generarFilaTipo2(preg_replace('/\D/','',$pers['cbu']), $liq['monto_neto'], '1', $pers['apellido']." ".$pers['nombre'], $pers['legajo'] ?: $pers['dni']);
            $total_reg++;

            if ($liq['monto_retenciones_judiciales'] > 0) {
                $stmt_j = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
                $stmt_j->execute([$liq['id_persona'], $liq['tipo_persona']]);
                foreach ($stmt_j->fetchAll() as $rj) {
                    $monto_rj = ($rj['tipo_calculo'] == 'Porcentaje') ? ($liq['monto_bruto'] * $rj['valor'] / 100) : $rj['valor'];
                    $referencia_jud = str_replace('EXP:', '', $rj['nro_expediente']);
                    $lineas_txt[] = generarFilaTipo2(preg_replace('/\D/','',$rj['cbu_destino']), $monto_rj, 'A', $rj['beneficiario_nombre'], "EXP:".$referencia_jud);
                    $total_reg++;
                }
            }
        }
        $lineas_txt[] = "3" . str_pad($total_reg, 10, "0", STR_PAD_LEFT) . str_pad(number_format($lote['monto_total_neto'], 2, '', ''), 20, "0", STR_PAD_LEFT) . str_pad("", 169, " ");

        echo json_encode(['success' => true, 'txt_content' => base64_encode(implode("\r\n", $lineas_txt))]);
    }
    elseif ($accion === 'anular_procesado') {
        $motivo = $_POST['motivo'] ?? 'Rechazo bancario informado por tesorería';
        $pdo->beginTransaction();

        // 1. Verificar que el lote esté en estado 'Procesado' para poder anularlo
        $stmt_check = $pdo->prepare("SELECT estado FROM lotes_liquidaciones WHERE id_lote = ?");
        $stmt_check->execute([$id_lote]);
        $lote_actual = $stmt_check->fetch();

        if (!$lote_actual || $lote_actual['estado'] !== 'Procesado') {
            throw new Exception("Solo se pueden anular lotes que ya han sido marcados como 'Procesado'.");
        }

        // 2. ANULAR GASTOS ASOCIADOS (Reversa contable)
        $sql_gastos = "UPDATE gastos 
                       SET estado = 'Anulado', 
                           descripcion = CONCAT(descripcion, ' (ANULADO POR RECHAZO BANCARIO LOTE #$id_lote)') 
                       WHERE id_referencia_pago IN (
                           SELECT id_liquidacion FROM liquidaciones_haberes WHERE id_lote = ?
                       )";
        $pdo->prepare($sql_gastos)->execute([$id_lote]);

        // 3. ANULAR LIQUIDACIONES (Esto libera a los agentes en el filtro de 'Pendientes')
        $sql_liq = "UPDATE liquidaciones_haberes 
                    SET estado = 'Anulado',
                        id_lote = NULL 
                    WHERE id_lote = ?";
        $pdo->prepare($sql_liq)->execute([$id_lote]);

        // 4. ACTUALIZAR EL LOTE A ESTADO 'ANULADO'
        $sql_lote = "UPDATE lotes_liquidaciones 
                     SET estado = 'Anulado', 
                         motivo_rechazo = ?, 
                         fecha_rechazo = NOW() 
                     WHERE id_lote = ?";
        $pdo->prepare($sql_lote)->execute(["ANULACIÓN PROCESADO: " . $motivo, $id_lote]);

        // 5. REGISTRO EN LOG DE AUDITORÍA ESPECIAL
        $sql_audit = "INSERT INTO logs_autorizaciones (id_usuario_autorizo, modulo, accion, motivo, detalles, fecha_hora) 
                      SELECT id_usuario, 'Tesoreria', 'Anulacion Lote Procesado', ?, ?, NOW() 
                      FROM usuarios WHERE nombre_usuario = ?";
        $detalles_audit = "Lote #$id_lote revertido. Los fondos y agentes fueron liberados.";
        $pdo->prepare($sql_audit)->execute([$motivo, $detalles_audit, $usuario_admin]);

        $pdo->commit();
        echo json_encode(['success' => true, 'msg' => 'El lote procesado ha sido anulado. Los agentes ya aparecen nuevamente como pendientes para liquidar.']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generarFilaTipo2($cbu, $monto, $clase, $nombre, $ref) {
    return "2" . substr($cbu,0,8) . substr($cbu,8,14) . str_pad(number_format($monto, 2, '', ''), 10, "0", STR_PAD_LEFT) . "SUE" . $clase . str_pad(substr(strtoupper($nombre),0,20), 20, " ") . str_pad("", 56, " ") . str_pad(substr($ref,0,15), 15, " ") . str_pad("", 71, " ");
}