<?php
session_start();
require '../../core/conexion.php';
require '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol']; 

// Parámetros de configuración (Aseguramos traer la config de tesorería también)
$stmt_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
$config_teso = $stmt_conf->fetch();

$meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidación Masiva Pro - Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        .bg-soft-success { background-color: #ecfdf5; }
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; background-color: #f8fafc; }
        .row-disabled { background-color: #f1f5f9 !important; color: #64748b !important; }
        body { background-color: #f1f5f9; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .card { border-radius: 12px; border: none; }
        .select-checkbox { cursor: pointer; transform: scale(1.2); }
        .form-select, .form-control { border-radius: 8px; border: 1px solid #e2e8f0; }
        .btn { border-radius: 8px; font-weight: 600; }
        .badge-total { font-size: 1rem; padding: 10px 20px; border-radius: 50px; }
    </style>
</head>
<body>

<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-body p-3">
                    <div class="row align-items-end g-3">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Categoría</label>
                            <select id="filtro_tipo" class="form-select shadow-sm">
                                <option value="">-- Seleccionar --</option>
                                <optgroup label="Liquidación Masiva (CBU)">
                                    <option value="Profesor_Masivo">Profesores (Banco)</option>
                                    <option value="Staff_Masivo">Personal Staff (Banco)</option>
                                    <option value="Monotributo_Masivo">Monotributistas (Banco)</option>
                                </optgroup>
                                <optgroup label="Liquidación Individual (Manual)">
                                    <option value="Profesor_Individual">Profesores (Ventanilla)</option>
                                    <option value="Staff_Individual">Personal Staff (Ventanilla)</option>
                                    <option value="Monotributo_Individual">Monotributistas (Ventanilla)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-2" id="wrapper_banco" style="display:none;">
                            <label class="form-label small fw-bold text-primary text-uppercase"><i class="fas fa-university me-1"></i> Banco Pagador</label>
                            <select id="id_banco_liquidacion" class="form-select border-primary shadow-sm">
                                <option value="">-- Seleccionar Layout --</option>
                                <?php
                                // Cargamos solo los bancos activos de nuestra nueva tabla de configuración
                                $stmt_bancos = $pdo->query("SELECT id_banco, nombre_banco FROM bancos_config WHERE estado = 1 ORDER BY nombre_banco");
                                while($b = $stmt_bancos->fetch()) {
                                    echo "<option value='{$b['id_banco']}'>{$b['nombre_banco']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Apellido</label>
                            <input type="text" id="filtro_apellido" class="form-control shadow-sm" placeholder="Buscar apellido...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">Nombre</label>
                            <input type="text" id="filtro_nombre" class="form-control shadow-sm" placeholder="Buscar nombre...">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted text-uppercase">DNI</label>
                            <input type="text" id="filtro_dni" class="form-control shadow-sm" placeholder="Sin puntos">
                        </div>

                        <div class="col-md-1">
                            <label class="form-label small fw-bold text-muted text-uppercase">Mes</label>
                            <select id="filtro_mes" class="form-select shadow-sm">
                                <?php foreach($meses as $m => $n): ?>
                                    <option value="<?= $m ?>" <?= ($m == date('n')) ? 'selected' : '' ?>><?= $n ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label small fw-bold text-muted text-uppercase">Año</label>
                            <input type="number" id="filtro_anio" class="form-control shadow-sm" value="<?= date('Y') ?>">
                        </div>

                        <div class="col-md-2 text-end">
                            <button id="btnRefrescar" class="btn btn-primary w-100 shadow-sm">
                                <i class="fas fa-search me-2"></i> CONSULTAR
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow border-0">
                <div class="card-header bg-white p-3 d-flex justify-content-between align-items-center border-bottom">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-calculator me-2 text-primary"></i> Liquidación de Haberes
                    </h5>
                    <div id="wrapper-total" class="d-flex align-items-center gap-3" style="display:none;">
                        <div class="badge-total bg-soft-success text-success border border-success border-opacity-25">
                            NETO SELECCIONADO: <span id="monto-total-previa" class="fw-bold">$ 0,00</span>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <table id="tablaPendientes" class="table table-hover align-middle mb-0 w-100">
                        <thead>
                            <tr>
                                <th width="40" class="ps-3"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                <th>Agente / Legajo</th>
                                <th class="text-end">Básico / Bruto</th>
                                <th class="text-end">Adicionales</th>
                                <th class="text-end">Asig. Fam</th>
                                <th class="text-end text-danger">Retenciones</th>
                                <th class="text-end fw-bold">Neto Estimado</th>
                                <th class="text-center">Estado / Acción</th>
                            </tr>
                        </thead>
                    </table>
                </div>

                <div class="card-footer bg-white p-3 border-top" style="display:none;" id="footer-masivo">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i> <span id="contador-seleccion" class="fw-bold">0</span> agentes seleccionados para procesamiento por lote.
                        </div>
                        <div>
                            <a href="<?= BASE_URL; ?>tesoreria/lotes-pendientes" class="btn btn-light border me-2">Ver Lotes</a>
                            <button id="btnProcesar" class="btn btn-success fw-bold px-4 shadow" disabled>
                                <i class="fas fa-check-double me-2"></i> GENERAR LOTE DE LIQUIDACIÓN
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAutorizacion" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-lock me-2"></i>Control de Período</h5>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Está intentando operar en un mes diferente al actual. Se requiere autorización de nivel administrativo para abrir el período.</p>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Usuario Administrador</label>
                    <input type="text" id="auth_user" class="form-control form-control-lg bg-light" placeholder="Usuario">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Contraseña</label>
                    <input type="password" id="auth_pass" class="form-control form-control-lg bg-light" placeholder="••••••••">
                </div>
                <div class="mb-0">
                    <label class="form-label small fw-bold text-danger">Motivo del ajuste</label>
                    <textarea id="auth_motivo" class="form-control" rows="2" placeholder="Especifique por qué liquida fuera de término..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-link text-muted" onclick="cancelarCambioFecha()">Cancelar</button>
                <button type="button" class="btn btn-danger px-4" onclick="verificarPermisoEspecial()">Autorizar Acceso</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let tabla;
    let seleccionados = new Set();
    let primeraCarga = true; 
    let seleccionTotal = false; 

    // Variables de Seguridad de Período
    let mesActualSys = new Date().getMonth() + 1;
    let anioActualSys = new Date().getFullYear();
    let autorizadoEspecial = false;
    let valorTemporalMes = $('#filtro_mes').val();
    let valorTemporalAnio = $('#filtro_anio').val();
    const BASE_URL = '<?= BASE_URL; ?>'; 
    const USER_ROL = '<?= $_SESSION['rol'] ?? ""; ?>';

    $(document).ready(function() {
        initDataTable();

        // Evento Refrescar / Consultar
        $('#btnRefrescar').click(function() {
            const tipo = $('#filtro_tipo').val();
            if (tipo === "") {
                Swal.fire('Atención', 'Seleccione una categoría de liquidación', 'warning');
                return;
            }
            primeraCarga = false;
            resetSeleccion();
            tabla.ajax.reload();
            actualizarUI();
        });

        // Cambio de Filtro Tipo (Limpieza automática y ajuste de UI)
        $('#filtro_tipo').change(function() {
            resetSeleccion();
            const tipoSeleccionado = $(this).val(); // Definimos la variable correctamente

            // Control de visibilidad del selector de Banco
            if (tipoSeleccionado.includes("_Masivo")) {
                $('#wrapper_banco').fadeIn();
            } else {
                $('#wrapper_banco').fadeOut();
                $('#id_banco_liquidacion').val('');
            }

            if (tipoSeleccionado === "") {
                tabla.clear().draw();
            } else {
                primeraCarga = false;
                tabla.ajax.reload();
            }
            actualizarUI();
        });

        // --- SEGURIDAD DE PERÍODO ---
        $('#filtro_mes, #filtro_anio').on('focus', function() {
            valorTemporalMes = $('#filtro_mes').val();
            valorTemporalAnio = $('#filtro_anio').val();
        });

        $('#filtro_mes, #filtro_anio').change(function() {
            let mSel = $('#filtro_mes').val();
            let aSel = $('#filtro_anio').val();

            if (!autorizadoEspecial && (mSel != mesActualSys || aSel != anioActualSys)) {
                $('#modalAutorizacion').modal('show');
            } else {
                if ($('#filtro_tipo').val() !== "") {
                    resetSeleccion();
                    tabla.ajax.reload();
                    actualizarUI();
                }
            }
        });

        // Buscador dinámico con Delay
        let timerBusqueda;
        $('#filtro_apellido, #filtro_nombre, #filtro_dni').on('keyup', function() {
            if ($('#filtro_tipo').val() === "") return;
            clearTimeout(timerBusqueda);
            timerBusqueda = setTimeout(function() {
                resetSeleccion();
                tabla.ajax.reload();
                actualizarUI();
            }, 500);
        });

        // Checkbox Individual
        $('#tablaPendientes').on('change', '.checkItem', function() {
            const id = $(this).val();
            if ($(this).is(':checked')) {
                seleccionados.add(id);
            } else {
                seleccionados.delete(id);
                if(seleccionTotal) {
                    seleccionTotal = false;
                    $('#checkAll').prop('checked', false);
                }
            }
            actualizarUI();
        });

        // Check All (Selección del Universo)
        $('#checkAll').change(function() {
            const isChecked = $(this).prop('checked');
            seleccionTotal = isChecked; 
            $('.checkItem:enabled').prop('checked', isChecked);
            
            if (isChecked) {
                const totalVisible = tabla.page.info().recordsDisplay;
                if (totalVisible > 0) {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'info',
                        title: `Universo de ${totalVisible} agentes seleccionado.`,
                        showConfirmButton: false, timer: 2000
                    });
                }
            } else {
                seleccionados.clear();
            }
            actualizarUI();
        });

        $('#btnProcesar').click(function() {
            confirmarLiquidacion();
        });
    });

    function resetSeleccion() {
        seleccionados.clear();
        seleccionTotal = false;
        $('#checkAll').prop('checked', false);
    }

    function initDataTable() {
        tabla = $('#tablaPendientes').DataTable({
            "processing": true,
            "serverSide": true,
            "pageLength": 10,
            "deferLoading": 0,
            "ajax": {
                "url": "<?= BASE_URL; ?>tesoreria/obtener-pendientes",
                "type": "POST",
                "data": function(d) {
                    d.tipo = $('#filtro_tipo').val();
                    d.mes = $('#filtro_mes').val();
                    d.anio = $('#filtro_anio').val();
                    d.apellido = $('#filtro_apellido').val();
                    d.nombre = $('#filtro_nombre').val();
                    d.dni = $('#filtro_dni').val();
                    d.bloquear_carga = primeraCarga;
                }
            },
            "columns": [
                { 
                    "data": "checkbox", 
                    "orderable": false,
                    "render": function(data, type, row) {
                        const esMasivo = $('#filtro_tipo').val().includes("_Masivo");
                        return esMasivo ? data : ''; 
                    }
                },
                { "data": "agente" },
                { "data": "bruto", "className": "text-end" },
                { "data": "adicionales", "className": "text-end" },
                { "data": "asig", "className": "text-end" },
                { "data": "ret", "className": "text-end text-danger" },
                { "data": "neto", "className": "text-end fw-bold text-success" },
                { 
                    "data": "estado_bna", 
                    "className": "text-center",
                    "render": function(data, type, row) {
                        const path = (typeof BASE_URL !== 'undefined') ? BASE_URL : '/';
                        const esMasivo = $('#filtro_tipo').val().includes("_Masivo");
                        
                        // Si no es masivo y el servidor nos envió el token encriptado
                        if (!esMasivo && row.token_pago) {
                            // URL limpia: tesoreria/liquidar/TOKEN_LARGO_Y_SEGURO
                            const urlSegura = `${path}tesoreria/liquidar/${row.token_pago}`;
                            
                            return `<a href="${urlSegura}" class="btn btn-sm btn-primary shadow-sm px-3">
                                        <i class="fas fa-edit me-1"></i> Liquidar
                                    </a>`;
                        }
                        return data; 
                    }
                }
            ],
            "drawCallback": function(settings) {
                const tipo = $('#filtro_tipo').val();
                const esMasivo = tipo.includes("_Masivo");
                
                // Forzamos la visibilidad de la columna 0 (checkboxes) y los paneles
                if (esMasivo && tipo !== "") {
                    $('#wrapper-total, #footer-masivo').show();
                    $('#checkAll').closest('th').show(); // Asegura que el TH del checkAll se vea
                    $('#checkAll').show().prop('disabled', false);
                    tabla.column(0).visible(true);
                } else {
                    $('#wrapper-total, #footer-masivo').hide();
                    $('#checkAll').hide();
                    tabla.column(0).visible(false);
                }

                // Mantener estado de selección al paginar
                if (seleccionTotal) {
                    $('.checkItem:enabled').prop('checked', true);
                } else {
                    $('.checkItem').each(function() {
                        if (seleccionados.has($(this).val())) $(this).prop('checked', true);
                    });
                }
            },
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
        });
    }

    function actualizarUI() {
        const info = tabla.page.info();
        const tipo = $('#filtro_tipo').val();
        const esMasivo = tipo.includes("_Masivo");
        
        if (!esMasivo) return;

        let totalMontoVisible = 0;
        let numSeleccionados = seleccionTotal ? info.recordsDisplay : seleccionados.size;

        $('#contador-seleccion').text(numSeleccionados);

        $('.checkItem:checked').each(function() {
            let valor = $(this).closest('tr').find('.text-success').text()
                        .replace(/[$. ]/g, '').replace(',', '.');
            totalMontoVisible += parseFloat(valor) || 0;
        });

        if (seleccionTotal && info.recordsDisplay > info.length) {
            $('#monto-total-previa').html(`<small class="text-muted fw-normal">Estimando universo...</small>`);
        } else {
            $('#monto-total-previa').text('$ ' + totalMontoVisible.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        }
        
        $('#btnProcesar').prop('disabled', numSeleccionados === 0);
    }

    function confirmarLiquidacion() {
        const cant = $('#contador-seleccion').text();
        const idBanco = $('#id_banco_liquidacion').val();
        const tipo = $('#filtro_tipo').val();

        if (tipo.includes("_Masivo") && !idBanco) {
            Swal.fire('Atención', 'Debe seleccionar el Banco Pagador para generar el lote y el archivo TXT.', 'warning');
            return;
        }

        const montoHtml = seleccionTotal ? 
            `<p class="text-primary mt-2">El monto final será calculado por el servidor según los tramos de antigüedad cargados.</p>` : 
            `<h4 class="text-success mt-2">Total: ${$('#monto-total-previa').text()}</h4>`;

        Swal.fire({
            title: '¿Confirmar Liquidación Masiva?',
            html: `Se procesarán <b>${cant}</b> agentes.<br>${montoHtml}`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            confirmButtonText: 'Sí, generar lote',
            cancelButtonText: 'Revisar'
        }).then((result) => {
            if (result.isConfirmed) {
                const idsParaEnviar = Array.from(seleccionados);
                ejecutarAjaxMasivo(idsParaEnviar);
            }
        });
    }

    function ejecutarAjaxMasivo(ids) {
        Swal.fire({ 
            title: 'Generando Lote...', 
            text: 'Calculando haberes y adicionales por tramos',
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading(); } 
        });

        $.ajax({
            url: '<?= BASE_URL; ?>tesoreria/procesar-liquidacion-masiva',
            type: 'POST',
            dataType: 'json',
            data: { 
                ids: ids,
                id_banco: $('#id_banco_liquidacion').val(), 
                seleccion_total: seleccionTotal, 
                mes: $('#filtro_mes').val(), 
                anio: $('#filtro_anio').val(),
                tipo: $('#filtro_tipo').val(), 
                apellido: $('#filtro_apellido').val(),
                nombre: $('#filtro_nombre').val(), 
                dni: $('#filtro_dni').val()
            },
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Lote Generado con Éxito!',
                        html: `<b>Agentes procesados:</b> ${res.agentes}<br><b>Monto Total Neto:</b> $ ${res.total}`,
                        confirmButtonText: 'Ir a Lotes Pendientes'
                    }).then(() => {
                        resetSeleccion();
                        tabla.ajax.reload();
                        actualizarUI();
                    });
                } else {
                    Swal.fire('Error en el proceso', res.error, 'error');
                }
            },
            error: function(xhr) { 
                Swal.fire('Error Crítico', 'No se pudo completar la operación en el servidor.', 'error'); 
            }
        });
    }

    function cancelarCambioFecha() {
        $('#filtro_mes').val(valorTemporalMes);
        $('#filtro_anio').val(valorTemporalAnio);
        $('#modalAutorizacion').modal('hide');
    }

    function verificarPermisoEspecial() {
        const user = $('#auth_user').val();
        const pass = $('#auth_pass').val();
        const motivo = $('#auth_motivo').val();

        if (!user || !pass || motivo.length < 10) {
            Swal.fire('Atención', 'Complete las credenciales y el motivo (mín. 10 caracteres).', 'warning');
            return;
        }

        $.post('<?= BASE_URL; ?>tesoreria/autorizar-periodo', { 
            user: user, pass: pass, motivo: motivo,
            mes_solicitado: $('#filtro_mes').val(),
            anio_solicitado: $('#filtro_anio').val()
        }, function(res) {
            if (res.success) {
                autorizadoEspecial = true; 
                $('#modalAutorizacion').modal('hide');
                resetSeleccion();
                tabla.ajax.reload();
                actualizarUI();
                Swal.fire('Autorizado', 'Período abierto para liquidación.', 'success');
            } else {
                Swal.fire('Error', res.message || 'Credenciales inválidas', 'error');
                $('#auth_pass').val('');
            }
        }, 'json');
    }
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>