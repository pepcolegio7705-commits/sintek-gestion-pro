<?php
require('conexion.php');
require 'seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // --- 1. CAPTURA DE DATOS BÁSICOS ---
        $id_persona   = $_POST['id_persona'] ?? null;
        $tipo_persona = $_POST['tipo_persona'] ?? 'Profesor';
        $mes          = $_POST['mes_liquidado'] ?? date('n');
        $anio         = $_POST['anio_liquidado'] ?? date('Y');

        // --- 2. INICIALIZACIÓN DE VARIABLES (Evita el Warning: Undefined variable) ---
        $nro_cheque = null;
        $nro_transf = null;
        $banco      = null;

        // --- 3. CAPTURA DE MONTOS ---
        $bruto       = (float)($_POST['monto_bruto'] ?? 0);
        $antiguedad  = (float)($_POST['monto_antiguedad_aplicado'] ?? 0);
        $zona        = (float)($_POST['monto_zona_patagonica'] ?? 0);
        $presentismo = (float)($_POST['monto_presentismo'] ?? 0);
        $hijos       = (float)($_POST['monto_asignacion_hijos'] ?? 0);
        $sindicato   = (float)($_POST['monto_sindicato'] ?? 0);
        $bonos       = (float)($_POST['monto_bonos_extraordinarios'] ?? 0);
        $ret_ley     = (float)($_POST['monto_retenciones_ley'] ?? 0);
        $ret_jud     = (float)($_POST['monto_retenciones_judiciales'] ?? 0);
        $total_ret   = $ret_ley + $sindicato + $ret_jud; 
        $neto        = (float)($_POST['monto_neto'] ?? 0);

        // --- 4. LÓGICA DE MODO DE PAGO ---
        $modo_pago = $_POST['id_modo_pago'] ?? 1;

        if ($modo_pago == "2") { // Cheque
            $nro_cheque = $_POST['nro_cheque'] ?? null;
            $banco      = $_POST['banco_emisor_cheque'] ?? null;
        } elseif ($modo_pago == "3") { // Transferencia
            $nro_transf = $_POST['nro_transferencia'] ?? null;
            $banco      = $_POST['banco_emisor_transf'] ?? null;
        }

        $usuario       = $_SESSION['nombre_usuario'] ?? 'Sistema';
        $observaciones = $_POST['observaciones'] ?? '';

        // --- 5. INSERTAR EN liquidaciones_haberes ---
        $sql_liq = "INSERT INTO liquidaciones_haberes (
            id_persona, tipo_persona, mes_liquidado, anio_liquidado, 
            total_horas_catedra, valor_hora_aplicado, monto_bruto, 
            monto_antiguedad_aplicado, monto_asignacion_hijos, 
            monto_zona_patagonica, monto_presentismo, monto_sindicato, monto_bonos_extraordinarios,
            monto_retenciones_ley, monto_retenciones_judiciales, monto_retenciones, 
            monto_neto, id_modo_pago, nro_cheque, nro_transferencia, banco_emisor, 
            estado, fecha_pago, usuario_registro, observaciones, id_gasto_vinculado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pagado', NOW(), ?, ?, 4)";

        $stmt_liq = $pdo->prepare($sql_liq);
        $stmt_liq->execute([
            $id_persona, $tipo_persona, $mes, $anio,
            $_POST['total_horas_catedra'] ?? 0, $_POST['valor_hora_aplicado'] ?? 0, $bruto,
            $antiguedad, $hijos, $zona, $presentismo, $sindicato, $bonos,
            $ret_ley, $ret_jud, $total_ret, $neto, 
            $modo_pago, $nro_cheque, $nro_transf, $banco, $usuario, $observaciones
        ]);

        $id_liquidacion = $pdo->lastInsertId();

        // --- 6. REGISTRO EN GASTOS (TESORERÍA) ---
        $stmt_cat = $pdo->prepare("SELECT id_cat_gasto FROM gastos_categorias WHERE nombre_categoria LIKE '%Sueldo%' LIMIT 1");
        $stmt_cat->execute();
        $id_categoria = $stmt_cat->fetchColumn() ?: 1; 

        $detalle_gasto = "Liq. Haberes $tipo_persona - Período $mes/$anio - Legajo $id_persona";
        $sql_gasto = "INSERT INTO gastos (id_cat_gasto, descripcion, monto, fecha_gasto, id_modo_pago, estado, usuario_registro, id_referencia_pago) VALUES (?, ?, ?, NOW(), ?, 'Pagado', ?, ?)";
        $pdo->prepare($sql_gasto)->execute([$id_categoria, $detalle_gasto, $neto, $modo_pago, $usuario, $id_liquidacion]);

        $pdo->commit();
        echo $id_liquidacion; 

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}