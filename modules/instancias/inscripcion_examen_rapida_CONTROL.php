<?php
session_start();
// Ajustamos rutas al estándar (subiendo niveles si es necesario)
require_once '../../core/conexion.php'; 
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol'];
if (!defined('NOM_INST')) define('NOM_INST', 'Sistema Académico');
date_default_timezone_set('America/Argentina/Buenos_Aires');

// --- PROCESAMIENTO AJAX ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'buscar_alumno') {
            $dni = trim($_POST['dni']);
            
            // 1. Buscar Alumno (Ahora trayendo UUID)
            $sql_alumno = "SELECT id_alumno, uuid_alumno, nombre, apellido, dni, activo FROM alumnos WHERE dni = :dni";
            $stmt = $pdo->prepare($sql_alumno);
            $stmt->execute([':dni' => $dni]);
            $alumno_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$alumno_data) throw new Exception("DNI no válido.");
            if ($alumno_data['activo'] == 0) throw new Exception("El alumno se encuentra INACTIVO.");
            if ($alumno_data['activo'] == 2) throw new Exception("El alumno es EGRESADO.");

            $id_alumno = $alumno_data['id_alumno'];

            // --- CHEQUEO DE TESORERÍA (Matrícula y Cuotas) ---
            $conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1")->fetch();
            $ciclo_activo = $conf['ciclo_lectivo_actual'] ?? date('Y');
            $max_mora = (int)($conf['limite_meses_mora'] ?? 2);
            $mes_actual = (int)date('n');

            // A. Matrícula
            if ($conf['bloquear_inscripcion_sin_matricula'] == 1) {
                $st_mat = $pdo->prepare("SELECT 1 FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto WHERE f.id_alumno = ? AND cp.categoria = 'Matrícula' AND fd.anio_lectivo = ? AND f.estado = 'Pagado'");
                $st_mat->execute([$id_alumno, $ciclo_activo]);
                if ($st_mat->rowCount() == 0) throw new Exception("BLOQUEO: No registra pago de Matrícula $ciclo_activo.");
            }

            // B. Cuotas (Morosidad)
            if ($conf['bloquear_examen_con_deuda'] == 1) {
                $st_pagos = $pdo->prepare("SELECT fd.mes_correspondiente FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto WHERE f.id_alumno = ? AND cp.categoria = 'Mensualidad' AND f.estado = 'Pagado' AND fd.anio_lectivo = ?");
                $st_pagos->execute([$id_alumno, $ciclo_activo]);
                $pagados = $st_pagos->fetchAll(PDO::FETCH_COLUMN);

                $deuda_meses = 0;
                for ($m = 3; $m <= $mes_actual; $m++) { // Suponiendo inicio en Marzo
                    if (!in_array($m, $pagados)) $deuda_meses++;
                }
                if ($deuda_meses > $max_mora) throw new Exception("MOROSIDAD: Registra $deuda_meses meses de deuda.");
            }

            // 2. Obtener mesas de acuerdo a la Carrera del Alumno
            // Relación: alumnos_carreras (id_carreras) -> espacios_curriculares (id_carrera)
            $sql_mesas = "SELECT m.id_mesa, e.nombre_espacio, m.llamado, m.fecha_examen, c.nombre_carrera,
                                 cp.id_concepto, cp.monto_sugerido
                          FROM mesas_examenes m
                          INNER JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                          INNER JOIN carreras c ON e.id_carrera = c.id_carrera
                          INNER JOIN alumnos_carreras ac ON (c.id_carrera = ac.id_carreras AND ac.id_alumno = ?)
                          LEFT JOIN conceptos_pago cp ON (cp.id_carrera = c.id_carrera AND cp.categoria = 'Derecho de Examen' AND cp.activo = 1)
                          WHERE m.estado = 'Abierta' 
                          AND CURDATE() BETWEEN m.fecha_inicio_inscripcion AND m.fecha_fin_inscripcion
                          ORDER BY c.nombre_carrera ASC, e.nombre_espacio ASC";

            $stmt_m = $pdo->prepare($sql_mesas);
            $stmt_m->execute([$id_alumno]);
            $materias = $stmt_m->fetchAll(PDO::FETCH_ASSOC);

            if (empty($materias)) throw new Exception("No hay mesas abiertas para la carrera de este alumno.");

            $response = ['success' => true, 'alumno' => $alumno_data, 'materias' => $materias];

        } elseif ($_POST['action'] === 'verificar_inscripcion') {
            $id_alumno = (int)$_POST['id_alumno'];
            $id_mesa = (int)$_POST['id_mesa'];
            
            $st_dup = $pdo->prepare("SELECT 1 FROM inscripciones_examen WHERE id_alumno = ? AND id_mesa = ?");
            $st_dup->execute([$id_alumno, $id_mesa]);
            
            $st_pago = $pdo->prepare("SELECT 1 FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura WHERE f.id_alumno = ? AND fd.id_referencia_mesa = ? AND f.estado = 'Pagado'");
            $st_pago->execute([$id_alumno, $id_mesa]);

            $response = ['success' => true, 'duplicado' => ($st_dup->rowCount() > 0), 'pagado' => ($st_pago->rowCount() > 0)];

        } elseif ($_POST['action'] === 'registrar_pago_e_inscripcion') {
            $pdo->beginTransaction();
            $id_alumno = (int)$_POST['id_alumno'];
            $id_mesa = (int)$_POST['id_mesa'];
            $id_concepto = (int)$_POST['id_concepto'];
            $id_modo = (int)$_POST['id_modo'];
            $monto = (float)$_POST['monto'];
            $cond = $_POST['condicion'];
            $trans = !empty($_POST['nro_transaccion']) ? trim($_POST['nro_transaccion']) : null;

            $nro_fac = "EXM-" . time();
            $sql_f = "INSERT INTO facturas (id_alumno, id_modo_pago, fecha_emision, nro_factura, total, estado, usuario_emisor, nro_transaccion, uuid_factura) VALUES (?, ?, NOW(), ?, ?, 'Pagado', ?, ?, UUID())";
            $pdo->prepare($sql_f)->execute([$id_alumno, $id_modo, $nro_fac, $monto, $_SESSION['id_usuario'], $trans]);
            $id_factura = $pdo->lastInsertId();

            $sql_fd = "INSERT INTO factura_detalle (id_factura, id_concepto, monto_cobrado, monto_descuento, mes_correspondiente, anio_lectivo, id_referencia_mesa, estado) VALUES (?, ?, ?, 0, ?, ?, ?, 'Liquidado')";
            $pdo->prepare($sql_fd)->execute([$id_factura, $id_concepto, $monto, date('n'), date('Y'), $id_mesa]);

            $sql_ins = "INSERT INTO inscripciones_examen (id_alumno, id_mesa, condicion, fecha_inscripcion, asistencia) VALUES (?, ?, ?, NOW(), 'Pendiente')";
            $pdo->prepare($sql_ins)->execute([$id_alumno, $id_mesa, $cond]);
            $pdo->commit();
            $response = ['success' => true, 'message' => 'Cobro e inscripción exitosa.', 'id_factura' => $id_factura];

        } elseif ($_POST['action'] === 'registrar_inscripcion') {
            $sql_ins = "INSERT INTO inscripciones_examen (id_alumno, id_mesa, condicion, fecha_inscripcion, asistencia) VALUES (?, ?, ?, NOW(), 'Pendiente')";
            if ($pdo->prepare($sql_ins)->execute([(int)$_POST['id_alumno'], (int)$_POST['id_mesa'], $_POST['condicion']])) {
                $response = ['success' => true, 'message' => 'Inscripción académica exitosa (Asistencia: Pendiente).'];
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response); exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscripción Rápida | Sintek</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; }
        .card-main { max-width: 650px; margin: 30px auto; border-radius: 15px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .header-ui { background: #1e293b; color: white; padding: 25px; text-align: center; border-radius: 15px 15px 0 0; }
        #dniInput { font-size: 2.2rem; text-align: center; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>
    <div class="container">
        <div class="card card-main">
            <div class="header-ui">
                <h3 class="mb-0">Gestión de Examen Rápida</h3>
            </div>
            <div class="card-body p-4">
                <div id="paso1">
                    <input type="number" id="dniInput" class="form-control mb-3" placeholder="DNI del Alumno" autofocus>
                    <button id="btnBuscar" class="btn btn-primary w-100 p-3 fw-bold shadow-sm">BUSCAR ALUMNO</button>
                </div>

                <div id="paso2" style="display:none;">
                    <div class="alert alert-info py-2 fw-bold" id="disp_nombre"></div>
                    <div class="mb-3">
                        <label class="fw-bold small">MESA DE EXAMEN</label>
                        <select id="selMesa" class="form-select form-select-lg"></select>
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small">CONDICIÓN</label>
                        <select id="selCondicion" class="form-select">
                            <option value="Regular">REGULAR</option>
                            <option value="Libre">LIBRE</option>
                        </select>
                    </div>
                    <button id="btnConfirmar" class="btn btn-success w-100 p-3 fw-bold shadow">
                        CONFIRMAR INSCRIPCIÓN
                    </button>
                    <button onclick="location.reload()" class="btn btn-link w-100 text-muted mt-2">Reiniciar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCobro" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning py-3">
                    <h5 class="modal-title fw-bold">Pendiente de Pago</h5>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="fw-bold small">MODO DE PAGO</label>
                        <select id="payModo" class="form-select">
                            <?php 
                            $modos = $pdo->query("SELECT id_modo, nombre_modo FROM modos_pago")->fetchAll();
                            foreach($modos as $m) echo "<option value='{$m['id_modo']}'>{$m['nombre_modo']}</option>";
                            ?>
                        </select>
                    </div>
                    <div id="divTransaccion" class="mb-3" style="display:none;">
                        <input type="text" id="payTransaccion" class="form-control" placeholder="Nro Operación / Comprobante">
                    </div>
                    <div class="mb-4">
                        <label class="fw-bold small">MONTO DERECHO EXAMEN</label>
                        <input type="number" id="payMonto" class="form-control fw-bold fs-4" readonly>
                        <input type="hidden" id="payConcepto">
                    </div>
                    <button id="btnProcesarPago" class="btn btn-dark w-100 p-3 fw-bold">REGISTRAR PAGO E INSCRIBIR</button>
                    <button type="button" class="btn btn-link w-100 text-muted mt-2" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    $(document).ready(function() {
        let alu = {};

        $('#btnBuscar').click(function() {
            let dni = $('#dniInput').val();
            if(!dni) return;
            $(this).prop('disabled', true).text('Validando...');

            $.post(window.location.href, {action: 'buscar_alumno', dni: dni}, function(r) {
                $('#btnBuscar').prop('disabled', false).text('BUSCAR ALUMNO');
                if(r.success) {
                    alu = r.alumno;
                    $('#disp_nombre').text(alu.apellido + ', ' + alu.nombre);
                    // LÍNEA AGREGADA PARA MOSTRAR EL DNI
                    $('#disp_nombre').after(`<div class="text-muted small" id="disp_dni">DNI: ${alu.dni}</div>`);
                    let select = $('#selMesa').empty().append('<option value="" disabled selected>Elegir mesa...</option>');
                    
                    r.materias.forEach(m => {
                        select.append(`<option value="${m.id_mesa}" data-monto="${m.monto_sugerido}" data-concepto="${m.id_concepto}">
                            ${m.nombre_espacio} (${m.nombre_carrera})
                        </option>`);
                    });
                    $('#paso1').hide(); $('#paso2').fadeIn();
                } else {
                    Swal.fire('Error', r.message, 'error');
                }
            }, 'json');
        });

        $('#btnConfirmar').click(function() {
            let sel = $('#selMesa option:selected');
            if(!sel.val()) return Swal.fire('Error', 'Selecciona una mesa.', 'warning');

            $.post(window.location.href, {
                action: 'verificar_inscripcion', 
                id_alumno: alu.id_alumno, 
                id_mesa: sel.val()
            }, function(v) {
                if(v.duplicado) return Swal.fire('Atención', 'Ya está inscripto en esta mesa.', 'info');
                
                if(!v.pagado) {
                    $('#payMonto').val(sel.data('monto'));
                    $('#payConcepto').val(sel.data('concepto'));
                    $('#modalCobro').modal('show');
                } else {
                    ejecutarInscripcion(sel.val(), $('#selCondicion').val());
                }
            }, 'json');
        });

        $('#payModo').change(function() {
            $('#divTransaccion').toggle($(this).val() == "2" || $(this).val() == "3");
        });

        $('#btnProcesarPago').click(function() {
            let btn = $(this);
            let trans = $('#payTransaccion').val();
            let modo = $('#payModo').val();

            // Validación básica antes de enviar
            if((modo == "2" || modo == "3") && trans.trim() === "") {
                return Swal.fire('Dato requerido', 'Ingrese el nro. de comprobante para este medio de pago.', 'warning');
            }

            btn.prop('disabled', true).text('Procesando...');

            $.post(window.location.href, {
                action: 'registrar_pago_e_inscripcion',
                id_alumno: alu.id_alumno,
                id_mesa: $('#selMesa').val(),
                id_concepto: $('#payConcepto').val(),
                id_modo: modo,
                monto: $('#payMonto').val(),
                condicion: $('#selCondicion').val(),
                nro_transaccion: trans
            }, function(res) {
                if(res.success) {
                    $('#modalCobro').modal('hide');
                    
                    // PREGUNTA SI DESEA IMPRIMIR
                    Swal.fire({
                        title: '¡Inscripción Exitosa!',
                        text: res.message + " ¿Desea imprimir el comprobante de pago ahora?",
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '<i class="fas fa-print"></i> Sí, imprimir',
                        cancelButtonText: 'No, finalizar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Abrir recibo
                            window.open('<?= BASE_URL ?>ajax/instancias/imprimir_recibo_examen.php?id=' + res.id_factura, '_blank');
                            location.reload();
                        } else {
                            location.reload();
                        }
                    });

                } else {
                    btn.prop('disabled', false).text('REGISTRAR PAGO E INSCRIBIR');
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json').fail(function() {
                btn.prop('disabled', false).text('REGISTRAR PAGO E INSCRIBIR');
                Swal.fire('Error Crítico', 'No se pudo conectar con el servidor.', 'error');
            });
        });

        function ejecutarInscripcion(idMesa, cond) {
            $.post(window.location.href, {
                action: 'registrar_inscripcion', id_alumno: alu.id_alumno, id_mesa: idMesa, condicion: cond
            }, function(res) {
                if(res.success) Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
            }, 'json');
        }
    });
    </script>
</body>
</html>