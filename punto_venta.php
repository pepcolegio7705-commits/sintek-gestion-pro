<?php
    session_start();
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    require 'conexion.php';
    require 'seguridad.php';
    
    $rol = $_SESSION['rol'];
    verificar_permisos(['Administrador', 'Tesoreria']);

    // 1. OBTENER ID DEL ALUMNO Y CARRERA SELECCIONADA
    $id_alumno = $_GET['id_alumno'] ?? null;
    $id_carrera_seleccionada = $_GET['id_carrera'] ?? null; // Nueva variable

    if (!$id_alumno) {
        header("Location: alumnos_mora.php");
        exit;
    }

    // 2. OBTENER TODAS LAS CARRERAS DEL ALUMNO (Para el selector)
    $stmt_todas = $pdo->prepare("SELECT c.id_carrera, c.nombre_carrera 
                             FROM alumnos_carreras ac 
                             JOIN carreras c ON ac.id_carreras = c.id_carrera 
                             WHERE ac.id_alumno = ? 
                             ORDER BY ac.cohorte DESC"); // Ordenamos por cohorte más reciente
    $stmt_todas->execute([$id_alumno]);
    $carreras_alumno = $stmt_todas->fetchAll();

    // Si no se seleccionó carrera y tiene varias, la lógica tomará la primera por defecto
    if (!$id_carrera_seleccionada && !empty($carreras_alumno)) {
        $id_carrera_seleccionada = $carreras_alumno[0]['id_carrera'];
    }

    // 3. OBTENER DATOS ESPECÍFICOS DEL ALUMNO Y LA CARRERA ELEGIDA
    $stmt = $pdo->prepare("SELECT a.*, c.nombre_carrera 
                            FROM alumnos a 
                            LEFT JOIN carreras c ON c.id_carrera = :id_car
                            WHERE a.id_alumno = :id_al");
    $stmt->execute([':id_car' => $id_carrera_seleccionada, ':id_al' => $id_alumno]);
    $datos_alumno = $stmt->fetch();

    if (!$datos_alumno) { die("Alumno no encontrado."); }

    $b_cuota = (int)($datos_alumno['beca_mensualidad'] ?? 0);
    $b_matri = (int)($datos_alumno['beca_matricula'] ?? 0);
    $anio_actual = date('Y');
    $mes_actual_sistema = (int)date('n'); // 5 para Mayo 2026

    // 4. VALIDACIÓN DE MATRÍCULA PAGADA (Específica por Carrera)
    $stmt_matri = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd 
                                INNER JOIN facturas f ON fd.id_factura = f.id_factura
                                INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                                WHERE f.id_alumno = ? AND cp.categoria = 'Matricula' 
                                AND cp.id_carrera = ? AND f.estado = 'Pagado' AND fd.anio_lectivo = ?");
    $stmt_matri->execute([$id_alumno, $id_carrera_seleccionada, $anio_actual]);
    $matricula_pagada = $stmt_matri->fetchColumn() > 0;

    // 5. MESES YA PAGADOS (Específicos por Carrera)
    $stmt_p = $pdo->prepare("SELECT fd.mes_correspondiente FROM factura_detalle fd
                             INNER JOIN facturas f ON fd.id_factura = f.id_factura
                             INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                             WHERE f.id_alumno = ? AND cp.id_carrera = ? AND fd.anio_lectivo = ? 
                             AND f.estado = 'Pagado' AND cp.categoria = 'Mensualidad'");
    $stmt_p->execute([$id_alumno, $id_carrera_seleccionada, $anio_actual]);
    $meses_pagados = array_map('intval', $stmt_p->fetchAll(PDO::FETCH_COLUMN));

    // 5. DETERMINAR EL MES SUGERIDO (El primero que no esté pagado)
$mes_sugerido = 1; // Empezamos revisando desde Enero (1)
for ($m = 1; $m <= 12; $m++) { 
    if (!in_array($m, $meses_pagados)) {
        // Encontramos el primer mes que debe
        $mes_sugerido = $m;
        break; 
    }
}

// Si por algún motivo ya pagó los 12 meses, sugerimos el mes actual para otros conceptos
if (count($meses_pagados) >= 12) {
    $mes_sugerido = (int)date('n');
}

   // 6. CONCEPTOS FILTRADOS POR CARRERA Y ESTADO DE MATRÍCULA
    // Si $matricula_pagada es true, filtramos para que NO aparezca el concepto 'Matricula'
    $sql_conceptos = "SELECT * FROM conceptos_pago 
                    WHERE activo = 1 
                    AND (id_carrera = ? OR id_carrera IS NULL OR id_carrera = 0)";

    if ($matricula_pagada) {
        $sql_conceptos .= " AND categoria != 'Matricula'";
    }

    $sql_conceptos .= " ORDER BY categoria DESC";

    $stmt_c = $pdo->prepare($sql_conceptos);
    $stmt_c->execute([$id_carrera_seleccionada]);
    $conceptos = $stmt_c->fetchAll();

   // 7. PROCESAR EL COBRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_cobrar'])) {
    $id_concepto = $_POST['id_concepto'];
    $id_modo = $_POST['id_modo_pago'];
    
    // CAPTURA DIRECTA: Si viene en el POST, se limpia y se usa.
    $nro_transaccion = (isset($_POST['nro_transaccion']) && !empty($_POST['nro_transaccion'])) 
                       ? trim($_POST['nro_transaccion']) 
                       : null;

    $mes = (int)$_POST['mes_correspondiente'];
    $anio = (int)$_POST['anio_lectivo'];
    $monto_final_db = floatval($_POST['monto_final']);
    $usuario_actual = $_SESSION['nombre_usuario'] ?? 'Sistema';

    try {
        $pdo->beginTransaction();
        $nro_factura = "REC-" . date('Ymd') . "-" . strtoupper(substr(md5(uniqid()), 0, 4));

        // INSERT EN FACTURAS (Asegúrate de que los nombres de las columnas sean exactos)
        $sql_f = "INSERT INTO facturas (id_alumno, nro_factura, fecha_emision, total, id_modo_pago, nro_transaccion, estado, usuario_emisor) 
                  VALUES (?, ?, NOW(), ?, ?, ?, 'Pagado', ?)";
        $stmt_f = $pdo->prepare($sql_f);
        $stmt_f->execute([$id_alumno, $nro_factura, $monto_final_db, $id_modo, $nro_transaccion, $usuario_actual]);
        
        $id_f = $pdo->lastInsertId();

        // INSERT EN DETALLE
        $stmt_d = $pdo->prepare("INSERT INTO factura_detalle (id_factura, id_concepto, monto_cobrado, mes_correspondiente, anio_lectivo) 
                                 VALUES (?, ?, ?, ?, ?)");
        $stmt_d->execute([$id_f, $id_concepto, $monto_final_db, $mes, $anio]);

        $pdo->commit();
        header("Location: punto_venta.php?id_alumno=$id_alumno&id_carrera=$id_carrera_seleccionada&success=1&id_f=$id_f");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}

    // 6.5 OBTENER MODOS DE PAGO ACTIVOS
    $stmt_modos = $pdo->prepare("SELECT id_modo, nombre_modo, detalles_pago FROM modos_pago WHERE activo = 1");
    $stmt_modos->execute();
    $modos = $stmt_modos->fetchAll(PDO::FETCH_ASSOC);

    // Verificación de seguridad para evitar el Warning si la tabla está vacía
    if (!$modos) { $modos = []; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Caja | POS - Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --azul-inst: #003366; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-pos { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .student-info { background: #eef2f7; border-radius: 12px; padding: 20px; border-left: 6px solid var(--azul-inst); }
        .form-label { font-size: 0.75rem; font-weight: 800; color: #444; text-transform: uppercase; }
        .btn-pay { background: var(--azul-inst); color: white; padding: 15px; font-weight: bold; border: none; transition: 0.3s; }
        .btn-pay:hover { background: #002244; transform: translateY(-2px); color: white; }
    </style>
</head>
<body>
    <?php include 'vistas/nav.php'; ?>
    <div class="container py-4">
        <div class="d-flex justify-content-between mb-3">
            <a href="alumnos_mora.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-arrow-left me-2"></i> Volver</a>
            
            <?php if(count($carreras_alumno) > 1): ?>
                <div class="d-flex align-items-center bg-white p-2 rounded border shadow-sm">
                    <label class="me-2 fw-bold small text-primary mb-0">CARRERA:</label>
                    <select class="form-select form-select-sm" onchange="location.href='punto_venta.php?id_alumno=<?=$id_alumno?>&id_carrera='+this.value">
                        <?php foreach($carreras_alumno as $ca): ?>
                            <option value="<?= $ca['id_carrera'] ?>" <?= $id_carrera_seleccionada == $ca['id_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ca['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if(!$matricula_pagada): ?>
                    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                        <div><strong>Matrícula Pendiente:</strong> No se registra pago de matrícula para <b><?= $datos_alumno['nombre_carrera'] ?></b> en <?= $anio_actual ?>.</div>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-cash-register me-2"></i> CAJA: <?= htmlspecialchars($datos_alumno['nombre_carrera']) ?></h5>
                    </div>
                    
                    <div class="card-body p-4 bg-white">
                        <div class="student-info mb-4 p-3 bg-light rounded border-start border-4 border-primary">
                            <h4 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($datos_alumno['apellido'] . ", " . $datos_alumno['nombre']) ?></h4>
                            <span class="badge bg-secondary">DNI: <?= $datos_alumno['dni'] ?></span>
                            <span class="badge bg-success">Becas: Cuota <?= $b_cuota ?>% | Matrícula <?= $b_matri ?>%</span>
                        </div>

                        <form method="POST" id="formCobro">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold small">Pagos realizados (<?=$anio_actual?>):</label>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php 
                                        $meses_n = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                                        for($m=1; $m<=12; $m++): 
                                            $pago = in_array($m, $meses_pagados);
                                        ?>
                                            <span class="badge <?= $pago ? 'bg-success' : 'bg-white text-muted border' ?>" style="width: 40px;"><?= $meses_n[$m] ?></span>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Concepto</label>
                                    <select name="id_concepto" id="id_concepto" class="form-select form-select-lg" required>
                                        <option value="">-- Seleccione --</option>
                                        <?php foreach($conceptos as $c): ?>
                                            <option value="<?= $c['id_concepto'] ?>" data-monto="<?= $c['monto_sugerido'] ?>" data-categoria="<?= $c['categoria'] ?>">
                                                <?= htmlspecialchars($c['nombre_concepto']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Importe Final ($)</label>
                                    <input type="number" step="0.01" name="monto_final" id="monto_final" class="form-control form-control-lg fw-bold" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Mes a pagar</label>
                                    <select name="mes_correspondiente" id="mes_correspondiente" class="form-select form-select-lg">
                                        <?php for($i=1; $i<=12; $i++): ?>
                                            <option value="<?= $i ?>" <?= $mes_sugerido == $i ? 'selected' : '' ?>>
                                                <?= $meses_n[$i] ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold">Modo de Pago</label>
                                    <div class="btn-group w-100">
                                        <?php 
                                        foreach($modos as $idx => $m): 
                                            // Lógica de referencia por ID (2=Transferencia, 3=Tarjeta) o por nombre
                                            $es_digital = ($m['id_modo'] == 2 || $m['id_modo'] == 3);
                                        ?>
                                            <input type="radio" class="btn-check" name="id_modo_pago" id="m_<?= $m['id_modo'] ?>" 
                                                   value="<?= $m['id_modo'] ?>" <?= $idx==0?'checked':'' ?>
                                                   data-referencia="<?= $es_digital ? 'true' : 'false' ?>">
                                            <label class="btn btn-outline-primary py-3" for="m_<?= $m['id_modo'] ?>"><?= $m['nombre_modo'] ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="col-12" id="div_referencia" style="display:none;">
                                    <div class="p-3 border rounded" style="background-color: #fff3cd; border-color: #ffeeba !important;">
                                        <label class="form-label fw-bold text-dark">
                                            <i class="fas fa-university me-1"></i> REFERENCIA BANCARIA / NRO. OPERACIÓN
                                        </label>
                                        <input type="text" name="nro_transaccion" id="nro_transaccion" 
                                            class="form-control form-control-lg border-warning" 
                                            placeholder="Ej: Código de transferencia o cupón de tarjeta">
                                        <small class="text-muted">Este número es vital para la conciliación de cuentas bancarias.</small>
                                    </div>
                                </div>

                                <div class="col-12" id="div_referencia" style="display:none;">
                                    <div class="p-3 bg-warning bg-opacity-10 border border-warning rounded">
                                        <label class="form-label fw-bold text-dark"><i class="fas fa-hashtag me-1"></i> Número de Operación / Transacción</label>
                                        
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <div class="form-check form-switch p-3 border rounded bg-light">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="check_confirmacion">
                                        <label class="form-check-label fw-bold text-primary" for="check_confirmacion">
                                            <i class="fas fa-check-double me-1"></i> Confirmar que los datos son correctos
                                        </label>
                                        <div class="form-text">Active esta casilla para habilitar el botón de registro.</div>
                                    </div>
                                </div>
                                <input type="hidden" name="anio_lectivo" value="<?= $anio_actual ?>">

                                <div class="col-12 mt-3">
                                    <button type="submit" id="btn_cobrar" class="btn btn-primary w-100 btn-lg shadow">
                                        <i class="fas fa-save me-2"></i> CONFIRMAR Y REGISTRAR PAGO
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. DATA E INICIALIZACIÓN
    const mesesYaPagados = <?= json_encode($meses_pagados) ?>;
    const becaCuota = <?= (int)$b_cuota ?>;
    const becaMatricula = <?= (int)$b_matri ?>;
    const matriculaYaPagada = <?= $matricula_pagada ? 'true' : 'false' ?>;

    // 2. VISIBILIDAD DE REFERENCIA BANCARIA
    const toggleReferencia = () => {
        const selected = document.querySelector('input[name="id_modo_pago"]:checked');
        const divRef = document.getElementById('div_referencia');
        const inputRef = document.getElementById('nro_transaccion');
        
        if (selected && selected.getAttribute('data-referencia') === 'true') {
            divRef.style.display = 'block';
        } else {
            divRef.style.display = 'none';
            inputRef.value = '';
        }
    };

    // 3. LÓGICA DEL INTERRUPTOR (CHECKBOX)
    const gestionarBotonCobro = () => {
        const check = document.getElementById('check_confirmacion');
        const btn = document.getElementById('btn_cobrar');
        const mes = parseInt(document.getElementById('mes_correspondiente').value);
        const combo = document.getElementById('id_concepto');
        const opt = combo.options[combo.selectedIndex];

        if (check.checked) {
            // Si el check está marcado, validamos que el mes no sea duplicado antes de prender el botón
            if (opt && opt.getAttribute('data-categoria') === 'Mensualidad' && mesesYaPagados.includes(mes)) {
                Swal.fire({ icon: 'error', title: 'Atención', text: 'No puede confirmar: Este mes ya está pagado.' });
                check.checked = false; // Desmarcamos
                btn.disabled = true;
            } else if (!opt || opt.value === "") {
                Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe elegir un concepto antes de confirmar.' });
                check.checked = false;
                btn.disabled = true;
            } else {
                // TODO OK: Activamos botón
                btn.disabled = false;
                btn.classList.remove('btn-secondary');
                btn.classList.add('btn-primary');
            }
        } else {
            // Check desmarcado: Botón muerto
            btn.disabled = true;
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
    };

    // 4. CÁLCULO DINÁMICO
    document.getElementById('id_concepto').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if(!opt.value) return;
        
        const montoBase = parseFloat(opt.getAttribute('data-monto'));
        const cat = opt.getAttribute('data-categoria');
        let beca = (cat === 'Mensualidad') ? becaCuota : (cat === 'Matricula' ? becaMatricula : 0);
        
        let final = montoBase - (montoBase * beca / 100);
        document.getElementById('monto_final').value = final.toFixed(2);

        if(beca > 0){
            document.getElementById('wrapper_descuento').style.display = 'block';
            document.getElementById('txt_etiqueta_beca').innerText = `Aplica Beca ${beca}%:`;
            document.getElementById('txt_monto_descontado').innerText = `- $${(montoBase * beca / 100).toFixed(2)}`;
        } else {
            document.getElementById('wrapper_descuento').style.display = 'none';
        }
        
        // Si cambia el concepto, reseteamos el check para obligar a re-verificar
        document.getElementById('check_confirmacion').checked = false;
        gestionarBotonCobro();
    });

    // 5. LISTENERS
    document.addEventListener('change', function(e) {
        if(e.target && e.target.name === 'id_modo_pago') toggleReferencia();
        if(e.target && e.target.id === 'check_confirmacion') gestionarBotonCobro();
        if(e.target && e.target.id === 'mes_correspondiente') {
            document.getElementById('check_confirmacion').checked = false;
            gestionarBotonCobro();
        }
    });

    // 6. INICIALIZACIÓN
    document.addEventListener("DOMContentLoaded", function() {
        toggleReferencia();
        gestionarBotonCobro(); // Empezar con botón desactivado
        
        const params = new URLSearchParams(window.location.search);
        if (params.has('success')) {
            const idFactura = params.get('id_f');
            Swal.fire({
                title: '¡Cobro Exitoso!',
                text: '¿Desea imprimir el recibo?',
                icon: 'success',
                showCancelButton: true,
                confirmButtonText: 'Imprimir',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#003366'
            }).then((res) => {
                if (res.isConfirmed) window.open('imprimir_recibo.php?id=' + idFactura, '_blank');
                window.location.href = 'punto_venta.php?id_alumno=<?= $id_alumno ?>&id_carrera=<?= $id_carrera_seleccionada ?>';
            });
        }
    });

    // 7. ENVÍO FINAL
    document.getElementById('formCobro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selected = document.querySelector('input[name="id_modo_pago"]:checked');
        const inputRef = document.getElementById('nro_transaccion');
        const nroRef = inputRef.value.trim();

        if (selected && selected.getAttribute('data-referencia') === 'true' && nroRef === "") {
            Swal.fire('Atención', 'Debe ingresar el Nro de Transacción.', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Registrar cobro?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, registrar',
            confirmButtonColor: '#003366'
        }).then((result) => { 
            if (result.isConfirmed) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; 
                hidden.name = 'btn_cobrar'; 
                hidden.value = '1';
                this.appendChild(hidden);
                this.submit();
            } 
        });
    });
</script>
</body>
</html>