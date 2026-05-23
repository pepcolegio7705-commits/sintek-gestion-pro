<?php
    session_start();
    header("Cache-Control: no-cache, must-revalidate");
    header("Pragma: no-cache");
    
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php';
    
    $rol = $_SESSION['rol'];
    verificar_permisos(['Administrador', 'Tesoreria']);

    // 1. OBTENER ALUMNO POR UUID Y CARRERA (Por UUID también)
    $uuid_alumno = $_GET['uuid'] ?? null;
    $uuid_carrera_url = $_GET['carrera'] ?? null; 

    if (!$uuid_alumno) {
        header("Location: alumnos_mora.php");
        exit;
    }

    // Datos básicos del alumno
    $stmt_al = $pdo->prepare("SELECT id_alumno, nombre, apellido, dni, beca_mensualidad, beca_matricula FROM alumnos WHERE uuid_alumno = ?");
    $stmt_al->execute([$uuid_alumno]);
    $datos_alumno = $stmt_al->fetch();

    if (!$datos_alumno) { die("Alumno no encontrado."); }
    $id_alumno = $datos_alumno['id_alumno'];

    // 2. OBTENER TODAS LAS CARRERAS (Traemos id y uuid)
    $stmt_todas = $pdo->prepare("SELECT c.id_carrera, c.uuid_carrera, c.nombre_carrera 
                                 FROM alumnos_carreras ac 
                                 JOIN carreras c ON ac.id_carreras = c.id_carrera 
                                 WHERE ac.id_alumno = ? ORDER BY ac.cohorte DESC");
    $stmt_todas->execute([$id_alumno]);
    $carreras_alumno = $stmt_todas->fetchAll();

    // 3. DETERMINAR CARRERA SELECCIONADA (Traducción UUID -> ID interno)
    $id_carrera_seleccionada = null;
    $uuid_carrera_actual = null;

    if ($uuid_carrera_url) {
        foreach ($carreras_alumno as $ca) {
            if ($ca['uuid_carrera'] === $uuid_carrera_url) {
                $id_carrera_seleccionada = $ca['id_carrera'];
                $uuid_carrera_actual = $ca['uuid_carrera'];
                break;
            }
        }
    }

    // Si no hay selección o es inválida, usamos la primera carrera
    if (!$id_carrera_seleccionada && !empty($carreras_alumno)) {
        $id_carrera_seleccionada = $carreras_alumno[0]['id_carrera'];
        $uuid_carrera_actual = $carreras_alumno[0]['uuid_carrera'];
    }

    $stmt_car = $pdo->prepare("SELECT nombre_carrera FROM carreras WHERE id_carrera = ?");
    $stmt_car->execute([$id_carrera_seleccionada]);
    $nombre_carrera_actual = $stmt_car->fetchColumn();

    $b_cuota = (int)$datos_alumno['beca_mensualidad'];
    $b_matri = (int)$datos_alumno['beca_matricula'];
    $anio_actual = date('Y');

    // 4. PROCESAR COBRO (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_cobrar'])) {
        $id_concepto = $_POST['id_concepto'];
        $id_modo = $_POST['id_modo_pago'];
        $nro_transaccion = !empty($_POST['nro_transaccion']) ? trim($_POST['nro_transaccion']) : null;
        $mes = (int)$_POST['mes_correspondiente'];
        $anio = (int)$_POST['anio_lectivo'];
        $monto_final = floatval($_POST['monto_final']);

        try {
            $pdo->beginTransaction();
            $uuid_factura = bin2hex(random_bytes(16));
            $nro_factura = "REC-" . date('Ymd') . "-" . strtoupper(substr(md5(uniqid()), 0, 4));

            $sql_f = "INSERT INTO facturas (uuid_factura, id_alumno, nro_factura, fecha_emision, total, id_modo_pago, nro_transaccion, estado, usuario_emisor) 
                      VALUES (?, ?, ?, NOW(), ?, ?, ?, 'Pagado', ?)";
            $stmt_f = $pdo->prepare($sql_f);
            $stmt_f->execute([$uuid_factura, $id_alumno, $nro_factura, $monto_final, $id_modo, $nro_transaccion, $_SESSION['nombre_usuario']]);
            $id_f = $pdo->lastInsertId();

            $stmt_d = $pdo->prepare("INSERT INTO factura_detalle (id_factura, id_concepto, monto_cobrado, mes_correspondiente, anio_lectivo, estado) 
                                     VALUES (?, ?, ?, ?, ?, 'Pagado')");
            $stmt_d->execute([$id_f, $id_concepto, $monto_final, $mes, $anio]);

            $pdo->commit();
            header("Location: " . BASE_URL . "tesoreria/cobrar/$uuid_alumno?success=1&recibo=$uuid_factura&carrera=$uuid_carrera_actual");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            header("Location: punto_venta.php?uuid=$uuid_alumno&carrera=$uuid_carrera_actual&error=" . urlencode($e->getMessage()));
            exit;
        }
    }

    // 5. VALIDACIONES PARA LA VISTA
    $stmt_matri = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd 
                                INNER JOIN facturas f ON fd.id_factura = f.id_factura
                                INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                                WHERE f.id_alumno = ? AND cp.categoria = 'Matricula' 
                                AND cp.id_carrera = ? AND f.estado = 'Pagado' AND fd.anio_lectivo = ?");
    $stmt_matri->execute([$id_alumno, $id_carrera_seleccionada, $anio_actual]);
    $matricula_pagada = $stmt_matri->fetchColumn() > 0;

    $stmt_p = $pdo->prepare("SELECT fd.mes_correspondiente FROM factura_detalle fd
                                 INNER JOIN facturas f ON fd.id_factura = f.id_factura
                                 INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                                 WHERE f.id_alumno = ? AND cp.id_carrera = ? AND fd.anio_lectivo = ? 
                                 AND f.estado = 'Pagado' AND cp.categoria = 'Mensualidad'");
    $stmt_p->execute([$id_alumno, $id_carrera_seleccionada, $anio_actual]);
    $meses_pagados = array_map('intval', $stmt_p->fetchAll(PDO::FETCH_COLUMN));

    $mes_sugerido = 1;
    for ($m = 1; $m <= 12; $m++) { if (!in_array($m, $meses_pagados)) { $mes_sugerido = $m; break; } }

    $sql_c = "SELECT * FROM conceptos_pago WHERE activo = 1 AND (id_carrera = ? OR id_carrera IS NULL OR id_carrera = 0) " . ($matricula_pagada ? "AND categoria != 'Matricula'" : "") . " ORDER BY categoria DESC";
    $stmt_c = $pdo->prepare($sql_c);
    $stmt_c->execute([$id_carrera_seleccionada]);
    $conceptos = $stmt_c->fetchAll();

    $modos = $pdo->query("SELECT id_modo, nombre_modo FROM modos_pago WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Caja POS | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --azul: #003366; }
        body { background-color: #f8f9fa; }
        .student-card { background: white; border-radius: 12px; border-left: 6px solid var(--azul); }
        .mes-pill { width: 42px; text-align: center; font-size: 0.75rem; padding: 4px 0; border-radius: 4px; border: 1px solid #ddd; }
        .bg-pagado { background-color: #198754 !important; color: white !important; border-color: #198754 !important; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="<?= BASE_URL ?>tesoreria/mora" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chevron-left me-1"></i> Alumnos en Mora</a>
            
            <?php if(count($carreras_alumno) > 1): ?>
                <div class="bg-white p-2 rounded border shadow-sm d-flex align-items-center">
                    <label class="small fw-bold text-primary me-2 mb-0">CAMBIAR CARRERA:</label>
                    <select class="form-select form-select-sm" onchange="location.href='<?= BASE_URL ?>tesoreria/cobrar/<?= $uuid_alumno ?>?carrera='+this.value">
                        <?php foreach($carreras_alumno as $ca): ?>
                            <option value="<?= $ca['uuid_carrera'] ?>" <?= $uuid_carrera_actual == $ca['uuid_carrera'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ca['nombre_carrera']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                
                <div class="student-card p-4 shadow-sm mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($datos_alumno['apellido'] . ", " . $datos_alumno['nombre']) ?></h3>
                            <p class="text-muted mb-2">DNI: <?= $datos_alumno['dni'] ?> | <span class="badge bg-primary"><?= $nombre_carrera_actual ?></span></p>
                            
                            <div class="d-flex gap-2">
                                <span class="badge <?= $b_matri > 0 ? 'bg-warning text-dark' : 'bg-light text-muted border' ?>">Beca Matrícula: <?= $b_matri ?>%</span>
                                <span class="badge <?= $b_cuota > 0 ? 'bg-info text-dark' : 'bg-light text-muted border' ?>">Beca Cuota: <?= $b_cuota ?>%</span>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <small class="text-muted d-block">AÑO LECTIVO</small>
                            <h2 class="fw-bold text-secondary"><?= $anio_actual ?></h2>
                        </div>
                    </div>
                </div>

                <?php if(!$matricula_pagada): ?>
                    <div class="alert alert-danger border-0 shadow-sm mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i> <strong>Atención:</strong> Matrícula no abonada para este ciclo.
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" id="formCobro">
                            <div class="mb-4">
                                <label class="form-label small fw-bold text-muted text-uppercase">Historial Mensualidades (<?= $anio_actual ?>)</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php 
                                    $meses_n = ["", "ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT", "NOV", "DIC"];
                                    for($m=1; $m<=12; $m++): 
                                        $pago = in_array($m, $meses_pagados);
                                    ?>
                                        <div class="mes-pill <?= $pago ? 'bg-pagado' : 'bg-white text-muted' ?>"><?= $meses_n[$m] ?></div>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-7">
                                    <label class="form-label fw-bold">CONCEPTO</label>
                                    <select name="id_concepto" id="id_concepto" class="form-select form-select-lg shadow-sm" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach($conceptos as $c): ?>
                                            <option value="<?= $c['id_concepto'] ?>" data-monto="<?= $c['monto_sugerido'] ?>" data-categoria="<?= $c['categoria'] ?>">
                                                <?= htmlspecialchars($c['nombre_concepto']) ?> ($<?= number_format($c['monto_sugerido'],0) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">MES</label>
                                    <select name="mes_correspondiente" id="mes_correspondiente" class="form-select form-select-lg shadow-sm">
                                        <?php for($i=1; $i<=12; $i++): ?>
                                            <option value="<?= $i ?>" <?= $mes_sugerido == $i ? 'selected' : '' ?>><?= $meses_n[$i] ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 text-center">
                                    <div class="p-3 bg-light rounded border">
                                        <label class="form-label d-block text-muted small fw-bold">IMPORTE FINAL</label>
                                        <input type="number" step="0.01" name="monto_final" id="monto_final" class="form-control form-control-lg text-center fw-bold border-primary" required>
                                        <div id="info_beca" class="mt-2 text-success fw-bold small" style="display:none;"></div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">MODO DE PAGO</label>
                                    <div class="row g-2">
                                        <?php foreach($modos as $idx => $m): ?>
                                            <div class="col-6">
                                                <input type="radio" class="btn-check" name="id_modo_pago" id="m_<?= $m['id_modo'] ?>" value="<?= $m['id_modo'] ?>" <?= $idx==0?'checked':'' ?> data-ref="<?= ($m['id_modo'] != 1) ? '1' : '0' ?>">
                                                <label class="btn btn-outline-primary w-100 py-2" for="m_<?= $m['id_modo'] ?>"><?= $m['nombre_modo'] ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-12" id="div_ref" style="display:none;">
                                    <input type="text" name="nro_transaccion" id="nro_transaccion" class="form-control form-control-lg border-warning shadow-sm" placeholder="Nro de Operación / Referencia Bancaria">
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch p-3 bg-light border rounded">
                                        <input class="form-check-input ms-0 me-3" type="checkbox" id="check_ok">
                                        <label class="form-check-label fw-bold text-primary" for="check_ok">Confirmar que los datos son correctos</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" id="btn_submit" class="btn btn-primary w-100 py-3 fw-bold shadow" disabled>
                                        <i class="fas fa-cash-register me-2"></i> REGISTRAR PAGO
                                    </button>
                                </div>
                            </div>
                            
                            <input type="hidden" name="anio_lectivo" value="<?= $anio_actual ?>">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const bCuota = <?= $b_cuota ?>;
        const bMatri = <?= $b_matri ?>;
        const mesesPagos = <?= json_encode($meses_pagados) ?>;

        // 1. Cálculo de Beca
        document.getElementById('id_concepto').addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if(!opt.value) return;
            const monto = parseFloat(opt.dataset.monto);
            const beca = (opt.dataset.categoria === 'Mensualidad') ? bCuota : (opt.dataset.categoria === 'Matricula' ? bMatri : 0);
            const final = monto - (monto * (beca / 100));
            document.getElementById('monto_final').value = final.toFixed(2);
            document.getElementById('info_beca').style.display = (beca > 0) ? 'block' : 'none';
            document.getElementById('info_beca').innerText = `Beca aplicada: ${beca}%`;
        });

        // 2. Referencia bancaria
        document.querySelectorAll('input[name="id_modo_pago"]').forEach(r => {
            r.addEventListener('change', function() {
                document.getElementById('div_ref').style.display = (this.dataset.ref === '1') ? 'block' : 'none';
            });
        });

        // 3. Bloqueo de meses pagados
        document.getElementById('check_ok').addEventListener('change', function() {
            const mes = parseInt(document.getElementById('mes_correspondiente').value);
            const cat = document.getElementById('id_concepto').options[document.getElementById('id_concepto').selectedIndex].dataset.categoria;
            if(this.checked && cat === 'Mensualidad' && mesesPagos.includes(mes)) {
                Swal.fire('Atención', 'Este mes ya fue abonado.', 'warning');
                this.checked = false; return;
            }
            document.getElementById('btn_submit').disabled = !this.checked;
        });

        // 4. DOBLE CONFIRMACIÓN
        document.getElementById('formCobro').addEventListener('submit', function(e) {
            e.preventDefault();
            const concepto = document.getElementById('id_concepto').options[document.getElementById('id_concepto').selectedIndex].text;
            const monto = document.getElementById('monto_final').value;

            Swal.fire({
                title: '¿Registrar factura?',
                html: `Vas a cobrar: <b>${concepto}</b><br>Monto: <b>$${monto}</b>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#003366',
                confirmButtonText: 'Sí, registrar'
            }).then((res) => {
                if (res.isConfirmed) {
                    const btn = document.createElement('input'); btn.type='hidden'; btn.name='btn_cobrar'; btn.value='1';
                    this.appendChild(btn);
                    this.submit();
                }
            });
        });

        // 5. ÉXITO E IMPRESIÓN
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            
            if (params.has('success')) {
                Swal.fire({
                    title: '¡Pago Exitoso!',
                    text: 'El cobro se registró correctamente.',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-print"></i> Imprimir Recibo',
                    cancelButtonText: 'Cerrar',
                    confirmButtonColor: '#003366'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // El UUID debe venir en el parámetro 'recibo' de la URL
                        const uuidFactura = params.get('recibo');
                        
                        if(uuidFactura) {
                            // Abrimos la URL amigable que definimos en el .htaccess
                            window.open('<?= BASE_URL ?>tesoreria/imprimir/' + uuidFactura, '_blank');
                        } else {
                            Swal.fire('Error', 'No se encontró el identificador del recibo.', 'error');
                        }
                    }
                    // Limpiamos la URL
                    window.history.replaceState({}, document.title, "<?= BASE_URL ?>tesoreria/cobrar/<?= $uuid_alumno ?>?carrera=<?= $uuid_carrera_actual ?>");
                });
            }
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>