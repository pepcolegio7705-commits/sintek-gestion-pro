<?php
session_start();
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

$rol = $_SESSION['rol'];
verificar_permisos(['Administrador', 'Tesoreria']);
$meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];
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
<body class="bg-light">

<?php include '../../vistas/nav.php'; ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4"><i class="fas fa-vault me-2 text-primary"></i> Control de Lotes de Liquidación</h2>
        <span class="badge bg-white text-dark shadow-sm p-2">Usuario: <?= $_SESSION['nombre_usuario'] ?></span>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaLotes" class="table table-hover align-middle w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>ID Lote</th>
                            <th>Fecha Creación</th>
                            <th>Período</th>
                            <th>Tipo</th>
                            <th>Agentes</th>
                            <th>Monto Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-users me-2"></i> Detalle de Agentes - Lote #<span id="detalle_id_lote"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="motivo_alerta" class="alert alert-danger" style="display:none;">
                    <strong>Motivo del Rechazo:</strong> <span id="texto_motivo_rechazo"></span>
                </div>
                <div class="table-responsive">
                    <table id="tablaDetalleAgentes" class="table table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>Agente</th>
                                <th>DNI</th>
                                <th>Categoría</th>
                                <th class="text-end">Bruto</th>
                                <th class="text-end">Retenciones</th>
                                <th class="text-end">Neto</th>
                                <th>CBU</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-users me-2"></i> Detalle de Agentes - Lote #<span id="detalle_id_lote"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="motivo_alerta" class="alert alert-danger" style="display:none;">
                    <strong>Motivo del Rechazo:</strong> <span id="texto_motivo_rechazo"></span>
                </div>
                <div class="table-responsive">
                    <table id="tablaLotes" class="table table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th>Agente</th>
                                <th>DNI</th>
                                <th>Categoría</th>
                                <th class="text-end">Bruto</th>
                                <th class="text-end">Retenciones</th>
                                <th class="text-end">Neto</th>
                                <th>CBU</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let loteIdActual = null;
let accionActual = null;
let tablaDetalle = null; // Variable para la instancia del detalle

$(document).ready(function() {
    const tabla = $('#tablaLotes').DataTable({
        "processing": true,
        "serverSide": true,
        "order": [[0, "desc"]],
        "ajax": {
            "url": "<?= BASE_URL ?>tesoreria/obtener-lotes",
            "type": "POST"
        },
        "columns": [
            { "data": "id_lote" },
            { "data": "fecha_procesamiento" },
            { "data": "periodo" },
            { "data": "tipo_personal" },
            { "data": "cantidad_agentes" },
            { "data": "monto_total_neto" },
            { "data": "estado" },
            { "data": "acciones", "orderable": false }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
    });

    // --- NUEVO: EVENTO PARA VER DETALLE DE AGENTES ---
    $('#tablaLotes').on('click', '.btn-ver-detalle', function() {
        const id = $(this).data('id');
        const motivo = $(this).data('motivo');
        
        $('#detalle_id_lote').text(id);
        
        // Mostrar motivo si el lote fue rechazado
        if (motivo) {
            $('#texto_motivo_rechazo').text(motivo);
            $('#motivo_alerta').show();
        } else {
            $('#motivo_alerta').hide();
        }

        // Destruir instancia previa si existe para evitar conflictos de inicialización
        if (tablaDetalle) {
            tablaDetalle.destroy();
        }

        tablaDetalle = $('#tablaDetalleAgentes').DataTable({
            "ajax": {
                "url": "obtener_detalle_lote.php",
                "type": "POST",
                "data": { id_lote: id }
            },
            "columns": [
                { "render": (d, t, row) => `<b>${row.apellido}, ${row.nombre}</b>` },
                { "data": "dni" },
                { "render": (d, t, row) => `<span class="badge bg-secondary">${row.categoria}</span>` },
                { "data": "monto_bruto", "className": "text-end" },
                { "data": "monto_retenciones", "className": "text-end text-danger" },
                { "data": "monto_neto", "className": "text-end fw-bold text-success" },
                { "data": "cbu" }
            ],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
        });

        $('#modalDetalleLote').modal('show');
    });

    // 1. BOTÓN AUTORIZAR
    $('#tablaLotes').on('click', '.btn-autorizar', function() {
        loteIdActual = $(this).data('id');
        accionActual = 'cerrar';
        let total = $(this).data('total');

        $('#tituloModal').text('Autorizar Salida de Fondos');
        $('#infoLote').html(`¿Desea <b>AUTORIZAR</b> el Lote <b>#${loteIdActual}</b>?<br>Monto Total: <b class="text-success">${total}</b>`);
        $('#divMotivo').hide(); 
        $('#btnConfirmarCierre').removeClass('btn-danger btn-primary').addClass('btn-success').text('AUTORIZAR Y GENERAR TXT');
        abrirModalClave();
    });

    // 2. BOTÓN RECHAZAR
    $('#tablaLotes').on('click', '.btn-rechazar', function() {
        loteIdActual = $(this).data('id');
        accionActual = 'rechazar';

        $('#tituloModal').text('Rechazar Lote de Liquidación');
        $('#infoLote').html(`¿Está seguro que desea <b>RECHAZAR</b> el Lote <b>#${loteIdActual}</b>?<br><small class="text-muted">Se anularán las liquidaciones y los agentes quedarán libres para liquidar nuevamente.</small>`);
        $('#divMotivo').show(); 
        $('#motivo_rechazo').val('');
        $('#btnConfirmarCierre').removeClass('btn-success btn-primary').addClass('btn-danger').text('CONFIRMAR RECHAZO');
        abrirModalClave();
    });

    // 3. BOTÓN DESCARGAR
    $('#tablaLotes').on('click', '.btn-descargar', function() {
        loteIdActual = $(this).data('id');
        accionActual = 'descargar_existente';

        $('#tituloModal').text('Regenerar Archivo Bancario');
        $('#infoLote').html(`¿Desea volver a descargar el archivo del Lote <b>#${loteIdActual}</b>?`);
        $('#divMotivo').hide(); 
        $('#btnConfirmarCierre').removeClass('btn-success btn-danger').addClass('btn-primary').text('DESCARGAR ARCHIVO');
        abrirModalClave();
    });

    $('#tablaLotes').on('click', '.btn-anular', function() {
        loteIdActual = $(this).data('id');
        accionActual = 'anular_procesado';

        $('#tituloModal').text('Informar Rechazo Bancario (Anulación)');
        $('#infoLote').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                <strong>ATENCIÓN:</strong> Esta acción anulará un lote que ya fue marcado como PROCESADO. 
                Las liquidaciones volverán a estado PENDIENTE y se liberarán los agentes.
            </div>
            Lote <b>#${loteIdActual}</b>
        `);
        
        $('#divMotivo').show(); 
        $('#motivo_rechazo').val('');
        $('#btnConfirmarCierre').removeClass('btn-success btn-danger btn-primary').addClass('btn-dark').text('CONFIRMAR RECHAZO BANCARIO');
        abrirModalClave();
    });

    function abrirModalClave() {
        $('#confirm_pass').val('');
        $('#modalAutorizar').modal('show');
    }

    // 4. CONFIRMACIÓN FINAL
    $('#btnConfirmarCierre').click(function() {
        let pass = $('#confirm_pass').val();
        let motivo = $('#motivo_rechazo').val();

        if(!pass) {
            Swal.fire('Atención', 'Debe ingresar su clave para continuar.', 'warning');
            return;
        }

        if(accionActual === 'rechazar' && !motivo.trim()) {
            Swal.fire('Atención', 'Debe especificar el motivo del rechazo para auditoría.', 'warning');
            return;
        }

        procesarAccionLote(loteIdActual, accionActual, pass, motivo);
    });

    function procesarAccionLote(id, accion, pass, motivo) {
        Swal.fire({ 
            title: 'Procesando...', 
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); } 
        });
        
        $.ajax({
            url: 'acciones_lote.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                id: id, 
                accion: accion, 
                password: pass, 
                motivo: motivo 
            },
            success: function(res) {
                if(res.success) {
                    $('#modalAutorizar').modal('hide');
                    
                    if(res.txt_content) {
                        let blob = new Blob([atob(res.txt_content)], { type: 'text/plain' });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = "GT_PAGOS_LOTE_" + id + ".txt";
                        link.click();
                    }
                    
                    Swal.fire('Éxito', res.msg, 'success');
                    tabla.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Fallo crítico en el servidor.', 'error');
            }
        });
    }
});
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>