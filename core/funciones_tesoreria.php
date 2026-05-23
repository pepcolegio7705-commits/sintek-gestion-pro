<?php
/**
 * Motor de Validación Financiera para Inscripciones
 * Verifica bloqueo por matrícula y límite de meses de mora.
 */
function validarInscripcion($id_alumno, $pdo) {
    try {
        // 1. Obtener la configuración de tesorería
        $conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1")->fetch(PDO::FETCH_ASSOC);
        if (!$conf) return ['status' => true]; // Si no hay config, no bloqueamos

        $anio_actual = (int)date('Y');

        // --- VALIDACIÓN A: MATRÍCULA ---
        // Se activa si la matrícula es obligatoria según la configuración
        if ($conf['matricula_activa'] == 1 && $conf['bloquear_inscripcion_sin_matricula'] == 1) {
            $stmt_mat = $pdo->prepare("
                SELECT COUNT(fd.id_detalle) 
                FROM factura_detalle fd
                INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                INNER JOIN facturas f ON fd.id_factura = f.id_factura
                WHERE f.id_alumno = ? 
                AND cp.categoria = 'Matricula' 
                AND fd.anio_lectivo = ?
                AND f.estado = 'Pagado'
            ");
            $stmt_mat->execute([$id_alumno, $anio_actual]);
            $pago_matricula = (int)$stmt_mat->fetchColumn();

            if ($pago_matricula == 0) {
                return [
                    'status' => false, 
                    'tipo' => 'matricula',
                    'msj' => "<strong>Requisito de Matrícula:</strong> El alumno debe abonar la matrícula del ciclo $anio_actual para inscribirse."
                ];
            }
        }

        // --- VALIDACIÓN B: MORA HISTÓRICA ---
        // Obtenemos la carrera más reciente para calcular su deuda
        $stmt_c = $pdo->prepare("SELECT id_carreras, cohorte FROM alumnos_carreras WHERE id_alumno = ? ORDER BY cohorte DESC LIMIT 1");
        $stmt_c->execute([$id_alumno]);
        $carrera = $stmt_c->fetch(PDO::FETCH_ASSOC);
        
        // Si el alumno no tiene carrera previa, es un ingresante nuevo (No tiene mora)
        if (!$carrera) return ['status' => true];

        $id_carrera = $carrera['id_carreras'];
        $cohorte = (int)$carrera['cohorte'];
        $mes_actual = (int)date('n');
        $mes_inicio_ciclo = 3; // Marzo

        // Calculamos cuántas cuotas debería haber pagado desde que inició (cohorte)
        $total_meses_obligatorios = 0;
        for ($anio = $cohorte; $anio <= $anio_actual; $anio++) {
            if ($anio < $anio_actual) {
                $total_meses_obligatorios += 10; // Ciclos cerrados (Marzo a Diciembre)
            } else {
                // Ciclo actual: solo hasta el mes presente
                if ($mes_actual >= $mes_inicio_ciclo) {
                    $total_meses_obligatorios += ($mes_actual - $mes_inicio_ciclo + 1);
                }
            }
        }

        // Contamos cuántas cuotas de 'Mensualidad' tiene pagadas en esa carrera
        $stmt_pagos = $pdo->prepare("
            SELECT COUNT(fd.id_detalle) 
            FROM factura_detalle fd
            INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
            INNER JOIN facturas f ON fd.id_factura = f.id_factura
            WHERE f.id_alumno = ? 
            AND cp.id_carrera = ? 
            AND cp.categoria = 'Mensualidad'
            AND f.estado = 'Pagado'
        ");
        $stmt_pagos->execute([$id_alumno, $id_carrera]);
        $total_pagados = (int)$stmt_pagos->fetchColumn();

        $deuda_historica = $total_meses_obligatorios - $total_pagados;

        // Comprobamos si supera el límite de mora permitido
        if ($deuda_historica > (int)$conf['limite_meses_mora']) {
            return [
                'status' => false, 
                'tipo' => 'mora',
                'msj' => "<strong>Bloqueo por Mora:</strong> El alumno adeuda $deuda_historica meses. El límite máximo de mora permitido para inscripciones es de " . $conf['limite_meses_mora'] . " meses."
            ];
        }

        return ['status' => true];

    } catch (Exception $e) {
        return ['status' => false, 'msj' => "Error de validación: " . $e->getMessage()];
    }
}