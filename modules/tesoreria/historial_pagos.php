<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesoreria']);
$rol = $_SESSION['rol']; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Pagos | Sintek</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <style>
        .table-dark-custom { background-color: #003366 !important; color: white; }
        .card-kpi { transition: transform 0.2s; border: none; }
        .card-kpi:hover { transform: translateY(-5px); }
    </style>
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid mt-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-primary fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Historial de Cobros</h2>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="card shadow-sm card-kpi border-start border-primary border-5 p-3">
                    <p class="text-muted small fw-bold mb-1 text-uppercase">Bruto Facturado</p>
                    <h4 class="fw-bold mb-0" id="val_bruto">$ 0,00</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm card-kpi border-start border-success border-5 p-3">
                    <p class="text-muted small fw-bold mb-1 text-uppercase">Total Cobrado</p>
                    <h4 class="fw-bold text-success mb-0" id="val_pagado">$ 0,00</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm card-kpi border-start border-danger border-5 p-3">
                    <p class="text-muted small fw-bold mb-1 text-uppercase">Total Anulado</p>
                    <h4 class="fw-bold text-danger mb-0" id="val_anulado">$ 0,00</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm card-kpi border-start border-warning border-5 p-3">
                    <p class="text-muted small fw-bold mb-1 text-uppercase">Efectividad de Cobro</p>
                    <h4 class="fw-bold mb-0" id="val_indice">0%</h4>
                    <div class="progress mt-2" style="height: 6px;">
                        <div id="progreso_cobro" class="progress-bar bg-warning" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 shadow-sm border-0">
            <div class="card-body bg-white rounded">
                <form id="formFiltros" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">RANGO DE FECHAS</label>
                        <div class="input-group input-group-sm">
                            <input type="date" id="fecha_desde" class="form-control">
                            <input type="date" id="fecha_hasta" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">CONCEPTO</label>
                        <select id="filtro_concepto" class="form-select form-select-sm">
                            <option value="">-- Todos --</option>
                            <option value="Matricula">Matrícula</option>
                            <option value="Mensualidad">Cuota Mensual</option>
                            <option value="Derecho de Examen">Derecho de Examen</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">APELLIDO ALUMNO</label>
                        <input type="text" id="filtro_apellido" class="form-control form-control-sm" placeholder="Buscar por apellido...">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="btnFiltrar" class="btn btn-primary btn-sm w-100 shadow-sm">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <button type="button" id="btnLimpiar" class="btn btn-outline-secondary btn-sm ms-2">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="tablaPagos" class="table table-hover align-middle w-100 mb-0">
                        <thead class="table-dark-custom">
                            <tr>
                                <th>Fecha</th>
                                <th>Recibo</th>
                                <th>DNI</th>
                                <th>Alumno</th>
                                <th>Carrera</th>
                                <th>Concepto</th>
                                <th>Total</th>
                                <th>Modo</th>
                                <th>Ref.</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalMotivo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalle de Anulación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-1">MOTIVO DECLARADO:</p>
                    <p id="txt_motivo" class="fw-bold text-dark fs-5"></p>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <p class="text-muted small mb-0">OPERADOR:</p>
                            <span id="txt_usuario" class="badge bg-secondary"></span>
                        </div>
                        <div class="col-6 text-end">
                            <p class="text-muted small mb-0">FECHA ANULACIÓN:</p>
                            <span id="txt_fecha" class="fw-bold"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    var tabla;

    $(document).ready(function() {
        tabla = $('#tablaPagos').DataTable({
            "processing": true,
            "serverSide": true,
            "order": [[0, "desc"]],
            "ajax": {
                "url": "<?= BASE_URL ?>tesoreria/fetch_historial",
                "type": "POST",
                "data": function(d) {
                    d.f_desde = $('#fecha_desde').val();
                    d.f_hasta = $('#fecha_hasta').val();
                    d.f_concepto = $('#filtro_concepto').val();
                    d.f_apellido = $('#filtro_apellido').val();
                },
                "dataSrc": function(json) {
                    if(json.totales) {
                        $('#val_bruto').text('$ ' + json.totales.bruto);
                        $('#val_pagado').text('$ ' + json.totales.pagado);
                        $('#val_anulado').text('$ ' + json.totales.anulado);
                        $('#val_indice').text(json.totales.indice + '%');
                        $('#progreso_cobro').css('width', json.totales.porcentaje_raw + '%');
                    }
                    return json.data;
                }
            },
            "columns": [
                { "data": "fecha_emision" },
                { "data": "nro_factura" },
                { "data": "dni" },
                { "data": "alumno" },
                { "data": "nombre_carrera" },
                { "data": "concepto" },
                { "data": "total" },
                { "data": "modo_pago" },
                { "data": "nro_transaccion" },
                { "data": "estado" },
                { "data": "acciones", "orderable": false }
            ],
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json" }
        });

        $('#btnFiltrar').on('click', function() { tabla.ajax.reload(); });
        $('#btnLimpiar').on('click', function() { $('#formFiltros')[0].reset(); tabla.ajax.reload(); });

        $(document).on('click', '.btn-ver-motivo', function() {
            $('#txt_motivo').text($(this).data('motivo'));
            $('#txt_usuario').text($(this).data('usuario'));
            $('#txt_fecha').text($(this).data('fecha'));
            new bootstrap.Modal('#modalMotivo').show();
        });
    });

    /**
     * Función para anular recibo
     * Esta función dispara el controlador que anula la factura y 
     * ELIMINA la inscripción académica relacionada.
     */
    function confirmarAnulacion(uuid, nro) {
        Swal.fire({
            title: '¿Anular Recibo ' + nro + '?',
            html: "Se registrará la anulación financiera y se <b>ELIMINARÁ</b> la inscripción al examen asociada.",
            icon: 'warning',
            input: 'textarea', // Cambiado a textarea para mejor visibilidad del motivo
            inputPlaceholder: 'Escriba el motivo de la anulación aquí...',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-check"></i> Confirmar Anulación',
            cancelButtonText: 'Cancelar',
            preConfirm: (motivo) => {
                if (!motivo) { 
                    Swal.showValidationMessage('El motivo es obligatorio para auditar la baja'); 
                }
                return motivo;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= BASE_URL ?>tesoreria/anular-h',
                    type: 'POST',
                    data: { 
                        uuid: uuid, 
                        motivo: result.value 
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: '¡Éxito!',
                                text: response.message,
                                icon: 'success'
                            });
                            tabla.ajax.reload(null, false); // Recarga manteniendo la posición de la tabla
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo procesar la solicitud en el servidor.', 'error');
                    }
                });
            }
        });
    }
    </script>
</body>
</html>