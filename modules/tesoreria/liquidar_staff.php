<?php
    session_start();
    require 'conexion.php';
    require 'seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría']);
    $rol = $_SESSION['rol'];

    // 1. CONFIGURACIÓN Y PARÁMETROS
    $stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt_conf->fetch();

    $id_staff = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id_staff === 0) { header("Location: staff_lista.php"); exit; }

    // 2. DATOS DEL PERSONAL Y SU ÁREA
    $sql_staff = "SELECT s.*, a.nombre_area 
                FROM personal_staff s 
                JOIN areas a ON s.id_area = a.id_area 
                WHERE s.id_staff = :id";
    $stmt = $pdo->prepare($sql_staff);
    $stmt->execute([':id' => $id_staff]);
    $st = $stmt->fetch();

    if (!$st) { header("Location: staff_lista.php"); exit; }

    // 3. RETENCIONES JUDICIALES (Tipo Persona: Staff)
    $stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff' AND activo = 1");
    $stmt_ret->execute([$id_staff]);
    $retenciones_judiciales = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);

    // --- LÓGICA DE CÁLCULO DIFERENCIADA ---
    // Usamos mb_stripos para buscar "monotributo" de forma segura
    $es_monotributista = (mb_stripos($st['nombre_area'], 'Monotributistas') !== false);

    $sueldo_basico = (float)$st['sueldo_base'];
    $monto_antiguedad = 0;
    $monto_hijos = 0;
    $porcentaje_antig_total = 0;

    // Variables de tiempo para antigüedad
    $hoy = new DateTime();
    $fecha_ingreso = new DateTime($st['fecha_ingreso']);
    $anios_antiguedad = $hoy->diff($fecha_ingreso)->y;

    // Deducciones
    $monto_jubilacion = 0;
    $monto_obra_social = 0;
    $p_jub = 0;
    $p_os = 0;

    if (!$es_monotributista) {
        // Si NO es monotributista (Administración/Maestranza), calculamos adicionales y ley
        $porcentaje_antig_anual = $config['porcentaje_antiguedad_anual'] ?: 0;
        $porcentaje_antig_total = $porcentaje_antig_anual * $anios_antiguedad;
        $monto_antiguedad = ($sueldo_basico * $porcentaje_antig_total) / 100;

        $monto_por_hijo = $config['monto_asignacion_hijo'] ?: 0;
        $monto_hijos = ($st['hijos_verificados'] == 1) ? ((int)$st['cantidad_hijos'] * $monto_por_hijo) : 0;
        
        // Base para Ley: Sueldo + Antigüedad
        $base_ley = $sueldo_basico + $monto_antiguedad;
        
        $p_jub = $config['porcentaje_jubilacion'] ?: 0;
        $p_os = $config['porcentaje_obra_social'] ?: 0;
        $monto_jubilacion = ($base_ley * $p_jub) / 100;
        $monto_obra_social = ($base_ley * $p_os) / 100;
    }

    $total_bruto = $sueldo_basico + $monto_antiguedad + $monto_hijos;
    $total_ley = $monto_jubilacion + $monto_obra_social;

    // Retenciones Judiciales (Aplican siempre que existan)
    $total_judicial = 0;
    foreach($retenciones_judiciales as $rj) {
        $total_judicial += ($rj['tipo_calculo'] == 'Porcentaje') ? ($total_bruto * $rj['valor'] / 100) : $rj['valor'];
    }

    $total_neto = $total_bruto - $total_ley - $total_judicial;
    $meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación Staff - <?= htmlspecialchars($st['apellido']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 12px; overflow: hidden; }
        .header-liq { background: #1e293b; color: white; padding: 25px; }
        .item-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #edf2f7; }
        .item-row:last-child { border-bottom: none; }
        .text-monto { font-family: 'Courier New', Courier, monospace; font-weight: bold; }
        .neto-box { background: #ecfdf5; border: 2px solid #10b981; border-radius: 10px; padding: 20px; }
        .btn-procesar { background: #10b981; border: none; padding: 15px; font-weight: bold; transition: 0.3s; }
        .btn-procesar:hover { background: #059669; transform: translateY(-2px); }
        .wrapper-liquidacion {
        margin-top: 20px; /* Separación extra del Nav */}
    </style>
</head>
<body>
<?php include 'vistas/nav.php'; ?>
<div class="container-fluid py-4">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <form id="formLiquidacion">
                    <input type="hidden" name="id_persona" value="<?= $id_staff ?>">
                    <input type="hidden" name="tipo_persona" value="Staff">
                    <input type="hidden" name="total_horas_catedra" value="0"> <input type="hidden" name="valor_hora_aplicado" value="0">
                    <input type="hidden" name="monto_bruto" value="<?= $total_bruto ?>">
                    <input type="hidden" name="monto_antiguedad_aplicado" value="<?= $monto_antiguedad ?>">
                    <input type="hidden" name="monto_asignacion_hijos" value="<?= $monto_hijos ?>">
                    <input type="hidden" name="monto_jubilacion" value="<?= $monto_jubilacion ?>">
                    <input type="hidden" name="monto_obra_social" value="<?= $monto_obra_social ?>">
                    <input type="hidden" name="monto_retenciones_ley" value="<?= $total_ley ?>">
                    <input type="hidden" name="monto_retenciones_judiciales" value="<?= $total_judicial ?>">
                    <input type="hidden" name="monto_neto" value="<?= $total_neto ?>">

                    <div class="card shadow border-0 rounded-3">
                        <div class="header-liq">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0"><?= htmlspecialchars($st['apellido'] . " " . $st['nombre']) ?></h2>
                                    <small class="opacity-75">ÁREA: <?= strtoupper($st['nombre_area']) ?> | DNI: <?= $st['dni'] ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark"><?= $es_monotributista ? 'MONOTRIBUTISTA' : 'PLANTA PERMANENTE' ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success">ESTADO: ACTIVO</span><br>
                                    <small>Antigüedad: <?= $anios_antiguedad ?> años</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-md-7 border-end">
                                    <h5 class="text-primary fw-bold">1. Conceptos Remunerativos</h5>
                                    <hr>
                                    <div class="item-row">
                                        <span>Sueldo Básico Mensual</span>
                                        <span class="fw-bold">$ <?= number_format($sueldo_basico, 2) ?></span>
                                    </div>
                                    <?php if(!$es_monotributista): ?>
                                        <div class="item-row">
                                            <span>Antigüedad (<?= $porcentaje_antig_total ?>%)</span>
                                            <span>$ <?= number_format($monto_antiguedad, 2) ?></span>
                                        </div>
                                        <div class="item-row">
                                            <span>Asignación Hijos</span>
                                            <span>$ <?= number_format($monto_hijos, 2) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="item-row fw-bold bg-light px-2 mt-2">
                                        <span>TOTAL BRUTO</span>
                                        <span>$ <?= number_format($total_bruto, 2) ?></span>
                                    </div>

                                    <h5 class="text-danger fw-bold mt-4">2. Deducciones (Retenciones)</h5>
                                    <hr>
                                    <?php if(!$es_monotributista): ?>
                                        <div class="item-row">
                                            <span>Jubilación (<?= $p_jub ?>%)</span>
                                            <span class="text-danger">- $ <?= number_format($monto_jubilacion, 2) ?></span>
                                        </div>
                                        <div class="item-row">
                                            <span>Obra Social (<?= $p_os ?>%)</span>
                                            <span class="text-danger">- $ <?= number_format($monto_obra_social, 2) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small my-2"><i>No aplican retenciones de ley para personal monotributista.</i></div>
                                    <?php endif; ?>
                                    
                                    <?php foreach($retenciones_judiciales as $rj): ?>
                                        <div class="item-row text-danger small">
                                            <span><i class="fas fa-gavel"></i> <?= $rj['beneficiario_nombre'] ?></span>
                                            <span>- $ <?= number_format(($rj['tipo_calculo'] == 'Porcentaje' ? ($total_bruto * $rj['valor'] / 100) : $rj['valor']), 2) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="col-md-5 ps-md-4">
                                    <h5 class="fw-bold mb-3">3. Datos del Pago</h5>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Período a Liquidar</label>
                                        <div class="input-group">
                                            <select name="mes_liquidado" class="form-select">
                                                <?php foreach($meses as $num => $nombre): ?>
                                                    <option value="<?= $num ?>" <?= ($num == date('n')) ? 'selected' : '' ?>><?= $nombre ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" name="anio_liquidado" class="form-control" value="<?= date('Y') ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="small fw-bold">Modo de Pago</label>
                                        <select name="id_modo_pago" class="form-select" id="modo_pago">
                                            <option value="1">Efectivo</option>
                                            <option value="2">Cheque</option>
                                            <option value="3">Transferencia</option>
                                        </select>
                                    </div>

                                    <div id="zona_cheque" style="display:none;" class="mb-3 p-2 bg-warning bg-opacity-10 border border-warning rounded">
                                        <input type="text" name="nro_cheque" class="form-control mb-1" placeholder="Nro de Cheque">
                                        <input type="text" name="banco_emisor" class="form-control" placeholder="Banco">
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-primary"><i class="fas fa-comment-alt me-1"></i> Observaciones de Liquidación</label>
                                        <textarea name="observaciones" class="form-control" rows="3" 
                                                placeholder="Ej: Bono por presentismo, ajuste de mes anterior, etc."></textarea>
                                        <div class="form-text">Este texto aparecerá en el historial de remuneraciones.</div>
                                    </div>
                                    <div class="neto-box text-center shadow-sm">
                                        <span class="text-muted small fw-bold">NETO A PERCIBIR</span>
                                        <div class="h2 fw-bold text-success mb-0">$ <?= number_format($total_neto, 2, ',', '.') ?></div>
                                    </div>

                                    <button type="button" id="btnConfirmar" class="btn btn-procesar w-100 text-white mt-3 shadow">
                                        <i class="fas fa-check-circle me-2"></i> CONFIRMAR LIQUIDACIÓN
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        // --- 1. FUNCIÓN DE COMPROBACIÓN DE DUPLICADOS ---
        function verificarPeriodo() {
            let mes = $('select[name="mes_liquidado"]').val();
            let anio = $('input[name="anio_liquidado"]').val();
            let id_p = $('input[name="id_persona"]').val();
            let tipo = 'Staff';

            if (mes && anio && id_p) {
                $.post('check_liquidacion_repetida.php', { 
                    id_p: id_p, 
                    mes: mes, 
                    anio: anio, 
                    tipo: tipo 
                }, function(data) {
                    if (data.existe) {
                        Swal.fire({ 
                            title: '¡Ya liquidado!', 
                            text: `Este periodo ya existe para este personal.`, 
                            icon: 'warning',
                            confirmButtonColor: '#f59e0b'
                        });
                        $('#btnConfirmar').prop('disabled', true).addClass('btn-secondary').html('<i class="fas fa-ban"></i> YA LIQUIDADO');
                    } else {
                        $('#btnConfirmar').prop('disabled', false).removeClass('btn-secondary').html('<i class="fas fa-check-circle me-2"></i> CONFIRMAR LIQUIDACIÓN');
                    }
                }, 'json');
            }
        }

        verificarPeriodo();
        $('select[name="mes_liquidado"], input[name="anio_liquidado"]').change(verificarPeriodo);

        // --- 2. MOSTRAR/OCULTAR ZONA DE CHEQUE ---
        // Asegúrate que el <select> tenga id="modo_pago" y el div id="zona_cheque"
        $('#modo_pago').on('change', function() {
            if ($(this).val() == '2') {
                $('#zona_cheque').slideDown();
            } else {
                $('#zona_cheque').slideUp();
            }
        });

        // --- 3. PROCESAMIENTO CON DOBLE CONFIRMACIÓN ---
        $('#btnConfirmar').click(function() {
            Swal.fire({
                title: '¿Confirmar Pago?',
                text: "Se registrará el egreso en caja y se guardará el histórico de haberes.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Sí, registrar pago',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    // Pantalla de carga
                    Swal.fire({
                        title: 'Procesando...',
                        html: 'Registrando liquidación en el sistema.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    // Envío por AJAX
                    $.ajax({
                        url: 'procesar_pago_haberes.php',
                        type: 'POST',
                        data: $('#formLiquidacion').serialize(),
                        success: function(response) {
                            // Limpiamos la respuesta por si hay espacios
                            let id_liq = response.trim();
                            
                            // SEGUNDA CONFIRMACIÓN: ¿Desea imprimir?
                            Swal.fire({
                                icon: 'success',
                                title: '¡Liquidación Exitosa!',
                                text: 'El pago ha sido registrado correctamente.',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#6e7881',
                                confirmButtonText: '<i class="fas fa-print"></i> Imprimir Recibo',
                                cancelButtonText: 'Volver al listado'
                            }).then((res) => {
                                if (res.isConfirmed) {
                                    // Abrir recibo en pestaña nueva con el ID retornado
                                    window.open('generar_recibo_fpdf.php?id=' + id_liq, '_blank');
                                }
                                // Redirigir siempre al listado después de la acción
                                window.location.href = 'staff_lista.php';
                            });
                        },
                        error: function(xhr) {
                            Swal.fire('Error', 'No se pudo procesar: ' + xhr.responseText, 'error');
                        }
                    });
                }
            });
        });
    });
</script>
<?php include 'vistas/footer.php'; ?>
</body>
</html>