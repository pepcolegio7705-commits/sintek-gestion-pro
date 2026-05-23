<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

error_reporting(E_ALL);
ini_set('display_errors', 1); // Activamos errores para detectar fallos en el INSERT

header('Content-Type: application/json');

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Error de seguridad: Token CSRF inválido.']);
    exit;
}

$ids = $_POST['ids'] ?? [];
$seleccion_total = (isset($_POST['seleccion_total']) && $_POST['seleccion_total'] === 'true');
$mes = (int)$_POST['mes'];
$anio = (int)$_POST['anio'];
$categoria_web = $_POST['tipo'] ?? ''; 
$id_usuario_sesion = $_SESSION['id_usuario'];

try {
    // 1. MAPEACIÓN DE TABLA Y ENUM
    $partes_cat = explode('_', $categoria_web);
    $prefijo_cat = $partes_cat[0]; 
    $modo_pago = $partes_cat[1] ?? '';

    if ($prefijo_cat === 'Profesor') {
        $tipo_persona_db = 'Profesor';
        $tabla_db = 'profesores';
        $id_col_db = 'id_profesor';
    } else {
        $tipo_persona_db = 'Staff'; 
        $tabla_db = 'personal_staff';
        $id_col_db = 'id_staff';
    }

    $pdo->beginTransaction();

    // 2. CREAR LOTE MAESTRO
    $tipo_lote_db = ($modo_pago === 'Individual') ? 'Efectivo_Cheque' : 'Bancario';
    $sql_lote = "INSERT INTO lotes_liquidaciones (uuid_lote, tipo_lote, mes_periodo, anio_periodo, estado, fecha_creacion) 
                 VALUES (UUID(), ?, ?, ?, 'Pendiente', NOW())";
    $stmt_lote = $pdo->prepare($sql_lote);
    $stmt_lote->execute([$tipo_lote_db, $mes, $anio]);
    $id_lote_generado = $pdo->lastInsertId();

    $total_acumulado = 0;
    $contador_exitos = 0;

    // 3. OBTENER CONFIGURACIÓN PARA CÁLCULOS
    $conf = $pdo->query("SELECT * FROM configuracion_tesoreria LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    foreach ($ids as $id_agente) {
        $id_agente = (int)$id_agente;

        // Buscar datos del agente
        $stmt_a = $pdo->prepare("SELECT * FROM $tabla_db WHERE $id_col_db = ?");
        $stmt_a->execute([$id_agente]);
        $agente = $stmt_a->fetch(PDO::FETCH_ASSOC);
        if (!$agente) continue;

        // --- SECCIÓN: CÁLCULOS DE HABERES (SIMPLIFICADO) ---
        $bruto = 0;
        if ($tipo_persona_db === 'Profesor') {
            $stmt_h = $pdo->prepare("SELECT SUM(horas_catedra) FROM profesores_espacios WHERE id_profesor = ?");
            $stmt_h->execute([$id_agente]);
            $horas = (float)$stmt_h->fetchColumn();
            $bruto = $horas * (float)$conf['valor_hora_catedra'];
        } else {
            // Buscamos el sueldo base del área
            $stmt_area = $pdo->prepare("SELECT sueldo_base_area FROM areas WHERE id_area = ?");
            $stmt_area->execute([$agente['id_area']]);
            $bruto = (float)$stmt_area->fetchColumn();
        }

        // --- SECCIÓN: RETENCIONES JUDICIALES (BÚSQUEDA) ---
        $stmt_j = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = ? AND activo = 1");
        $stmt_j->execute([$id_agente, $tipo_persona_db]);
        $retenciones_agente = $stmt_j->fetchAll(PDO::FETCH_ASSOC);

        $total_ret_judiciales = 0;
        foreach ($retenciones_agente as $rj) {
            $monto_rj = ($rj['tipo_calculo'] === 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
            $total_ret_judiciales += $monto_rj;
        }

        $neto_final = $bruto - $total_ret_judiciales;

        // --- 4. SECCIÓN: INSERT EN liquidaciones_haberes ---
        $sql_hab = "INSERT INTO liquidaciones_haberes (
                        uuid_liquidacion, id_persona, tipo_persona, mes_liquidado, anio_liquidado, 
                        monto_bruto, monto_retenciones_judiciales, monto_neto, id_lote, 
                        id_modo_pago, estado, usuario_registro, fecha_registro
                    ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?, NOW())";
        
        $id_modo_pago = ($modo_pago === 'Masivo') ? 2 : 1; // 2: Banco, 1: Efectivo

        $stmt_hab = $pdo->prepare($sql_hab);
        $res_hab = $stmt_hab->execute([
            $id_agente, $tipo_persona_db, $mes, $anio, 
            $bruto, $total_ret_judiciales, $neto_final, $id_lote_generado,
            $id_modo_pago, $id_usuario_sesion
        ]);

        if (!$res_hab) {
            throw new Exception("Fallo al insertar Haberes para Agente ID: $id_agente");
        }

        $id_liq_maestra = $pdo->lastInsertId();

        // --- 5. SECCIÓN: INSERT EN liquidaciones_terceros ---
        foreach ($retenciones_agente as $rj) {
            $monto_tercero = ($rj['tipo_calculo'] === 'Porcentaje') ? ($bruto * (float)$rj['valor'] / 100) : (float)$rj['valor'];
            
            if ($monto_tercero > 0) {
                $sql_ter = "INSERT INTO liquidaciones_terceros (
                                uuid_pago, id_lote, id_liquidacion_origen, id_persona, tipo_persona,
                                beneficiario_nombre, cbu_destino, monto, concepto, estado, fecha_creacion
                            ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', NOW())";

                $concepto_txt = "Ret.Jud. Exp: " . $rj['nro_expediente'] . " - Agente: " . $agente['apellido'];

                $stmt_ter = $pdo->prepare($sql_ter);
                $stmt_ter->execute([
                    $id_lote_generado, $id_liq_maestra, $id_agente, $tipo_persona_db,
                    $rj['beneficiario_nombre'], $rj['cbu_destino'], $monto_tercero, $concepto_txt
                ]);
            }
        }

        $total_acumulado += $neto_final;
        $contador_exitos++;
    }

    // 6. ACTUALIZAR TOTALES DEL LOTE
    $pdo->prepare("UPDATE lotes_liquidaciones SET total_monto = ?, cantidad_agentes = ? WHERE id_lote = ?")
        ->execute([$total_acumulado, $contador_exitos, $id_lote_generado]);

    $pdo->commit();
    echo json_encode(['success' => true, 'agentes' => $contador_exitos, 'total' => number_format($total_acumulado, 2, ',', '.')]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}