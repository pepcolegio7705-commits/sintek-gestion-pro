<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador','Tesorero']);
$rol = $_SESSION['rol'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Lotes de Liquidación | Sintek</title>
    <link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; }
        .card { border: none; border-radius: 15px; }
        .badge-pendiente { background-color: #fef3c7; color: #92400e; }
        .badge-procesado { background-color: #dcfce7; color: #166534; }
        .badge-anulado { background-color: #fee2e2; color: #991b1b; }
        .table th { font-size: 0.75rem; text-transform: uppercase; background-color: #f8fafc; }
        .badge-grupo { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; font-weight: 600; text-transform: uppercase; font-size: 0.7rem; padding: 5px 10px; }
        
        /* Estilos para la tabla anidada */
        td.dt-control { cursor: pointer; text-align: center; }
        tr.shown td.dt-control i { transform: rotate(90deg); color: #dc3545 !important; }
        .subtable-container { background-color: #ffffff; padding: 20px; border: 1px solid #dee2e6; border-radius: 10px; box-shadow: inset 0 0 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>

<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid py-4">
    <div class="card shadow border-0 mb-3">
        <div class="card-body py-3">
            <div class="row align-items-center g-3">
                <div class="col-md-4">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-list-ol me-2 text-primary"></i>Gestión de Lotes</h5>
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white fw-bold small text-muted">MES</span>
                        <select id="filtro_mes" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach($meses as $m => $n): ?>
                                <option value="<?= $m ?>" <?= ($m == date('n')) ? 'selected' : '' ?>><?= $n ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white fw-bold small text-muted">AÑO</span>
                        <input type="number" id="filtro_anio" class="form-control" value="<?= date('Y') ?>">
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <button id="btnRefrescarLotes" class="btn btn-primary btn-sm px-4 fw-bold">
                        <i class="fas fa-sync-alt me-1"></i> CONSULTAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-body">
            <table id="tablaLotes" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th width="30"></th>
                        <th>ID Lote</th>
                        <th>Periodo</th>
                        <th>Grupo / Área</th> 
                        <th>Tipo</th>
                        <th>Agentes</th>
                        <th>Total Neto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAutorizar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div id="headerAuth" class="modal-header text-white">
                <h5 class="modal-title" id="txtTituloModal">Confirmar Acción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="auth_id_lote">
                <input type="hidden" id="auth_accion">
                
                <div class="alert alert-warning mb-3 small" id="infoAlerta">
                    <i class="fas fa-exclamation-triangle me-2"></i> Esta acción es irreversible y quedará registrada en el log de auditoría.
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="small fw-bold">Usuario Administrador</label>
                        <input type="text" id="auth_user" class="form-control form-control-sm" placeholder="Nombre de usuario">
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold">Contraseña de Confirmación</label>
                        <input type="password" id="auth_pass" class="form-control form-control-sm" placeholder="••••••••">
                    </div>
                    <div class="col-12" id="divMotivo" style="display:none;">
                        <label class="small fw-bold text-danger">Motivo de la Anulación (Mín. 10 caracteres)</label>
                        <textarea id="auth_motivo" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnEjecutar" class="btn btn-primary btn-sm px-4 fw-bold">PROCESAR</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>
    let tablaLotes;
    const BASE_URL = '<?= BASE_URL; ?>';
    // Capturamos el rol del usuario desde PHP para la lógica de botones
    const USER_ROL = '<?= $_SESSION['rol']; ?>';

    $(document).ready(function() {
        // 1. INICIALIZACIÓN TABLA PADRE
        tablaLotes = $('#tablaLotes').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": BASE_URL + 'tesoreria/obtener-listado-lotes',
                "type": "POST",
                "data": function(d) {
                    d.mes = $('#filtro_mes').val();
                    d.anio = $('#filtro_anio').val();
                }
            },
            "columns": [
                {
                    "className": 'dt-control',
                    "orderable": false,
                    "data": null,
                    "defaultContent": '<i class="fas fa-chevron-right text-muted"></i>'
                },
                { "data": "id_lote" },
                { "data": "periodo" },
                { 
                    "data": "nombre_area",
                    "render": function(data) {
                        return `<span class="badge badge-grupo">${data ? data : 'General'}</span>`;
                    }
                },
                { "data": "tipo" },
                { "data": "agentes", "className": "text-center" },
                { "data": "total", "className": "text-end fw-bold" },
                { "data": "estado", "className": "text-center" },
                { 
                    "data": null, 
                    "orderable": false, 
                    "className": "text-center",
                    "render": function(data, type, row) {
                        let html = row.acciones; // Mantener botones de Ver/Anular que vengan del server

                        // LÓGICA PARA LOTES YA PROCESADOS
                        if (row.estado === 'Procesado' && (USER_ROL === 'Administrador' || USER_ROL === 'Tesorero')) {
                            const esCopia = row.descargado_txt == 1;
                            const claseBtn = esCopia ? 'btn-outline-secondary' : 'btn-success';
                            const texto = esCopia ? ' Re-descargar (Copia)' : ' Descargar TXT';
                            const icono = esCopia ? 'fas fa-copy' : 'fas fa-file-download';

                            // 1. BOTÓN DE DESCARGA (El que ya teníamos)
                            html += ` <button class="btn btn-sm ${claseBtn}" onclick="autorizarDescarga(${row.id_lote})" title="${texto}">
                                        <i class="${icono}"></i>
                                    </button>`;
                            // Usamos btn-outline-warning para que resalte sin ser tan agresivo como el rojo de anulación
                            html += ` <button class="btn btn-sm btn-outline-warning ms-1" onclick="prepararAccion(${row.id_lote}, 'rechazo')" title="Informar Rechazo del Banco">
                                        <i class="fas fa-university"></i>
                                    </button>`;
                        }
                        return html;
                    }
                }
            ],
            "order": [[1, "desc"]],
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
        });

        // 2. LÓGICA DE EXPANSIÓN
        $('#tablaLotes tbody').on('click', 'td.dt-control', function() {
            var tr = $(this).closest('tr');
            var row = tablaLotes.row(tr);

            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
            } else {
                var idLote = row.data().id_lote;
                row.child(formatChildRow(idLote)).show();
                initSubTable(idLote);
                tr.addClass('shown');
            }
        });

        $('#btnRefrescarLotes').click(function() {
            tablaLotes.ajax.reload();
        });

        // 3. EVENTO PROCESAR ACCIÓN (CONFIRMAR/ANULAR)
        $('#btnEjecutar').click(function() {
            const data = {
                id_lote: $('#auth_id_lote').val(),
                accion: $('#auth_accion').val(),
                user: $('#auth_user').val(),
                pass: $('#auth_pass').val(),
                motivo: $('#auth_motivo').val()
            };

            // 1. Validaciones básicas de campos vacíos
            if(!data.user || !data.pass) {
                Swal.fire('Atención', 'Debe completar usuario y contraseña.', 'warning');
                return;
            }

            // 2. Validación de motivo para Anulación O Rechazo Bancario
            if((data.accion === 'anular' || data.accion === 'rechazo') && data.motivo.length < 10) {
                Swal.fire('Atención', 'Indique un motivo válido (mín. 10 caracteres) para procesar esta acción.', 'warning');
                return;
            }

            // 3. Determinar la URL según la acción
            // Si es rechazo, va a su script específico; si no, al de finalizar (confirmar/anular)
            let url_destino = (data.accion === 'rechazo') 
                ? BASE_URL + 'tesoreria/rechazar-lote-banco' 
                : BASE_URL + 'tesoreria/finalizar-lote';

            Swal.fire({ 
                title: 'Procesando operación...', 
                allowOutsideClick: false, 
                didOpen: () => { Swal.showLoading(); } 
            });

            $.post(url_destino, data, function(res) {
                if(res.success) {
                    Swal.fire('¡Éxito!', res.mensaje, 'success');
                    $('#modalAutorizar').modal('hide');
                    
                    // LÓGICA DE DESCARGA AUTOMÁTICA (Solo aplica si viene un archivo de finalizar-lote)
                    if(res.archivo) {
                        const blob = new Blob([atob(res.archivo)], { type: 'text/plain' });
                        const link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = res.nombre_archivo;
                        
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        setTimeout(() => window.URL.revokeObjectURL(link.href), 100);
                    }
                    
                    // Recargamos la tabla principal para reflejar el nuevo estado ('Procesado', 'Anulado' o 'Rechazo Banco')
                    tablaLotes.ajax.reload(null, false);
                    
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            }, 'json').fail(function() {
                Swal.fire('Error Crítico', 'No se pudo conectar con el servidor para procesar la operación.', 'error');
            });
        });
    });

    /**
     * FUNCIÓN DE DESCARGA CON DOBLE VALIDACIÓN (STEP-UP AUTH)
     * Se activa al hacer clic en el botón de la tabla
     */
    function autorizarDescarga(idLote) {
        Swal.fire({
            title: 'Verificación de Identidad',
            text: 'Por seguridad, re-ingrese sus credenciales para autorizar la descarga.',
            icon: 'lock',
            html: `
                <div class="mb-2">
                    <input type="text" id="swal_u" class="swal2-input" placeholder="Usuario" style="width: 80%">
                </div>
                <div>
                    <input type="password" id="swal_p" class="swal2-input" placeholder="Contraseña" style="width: 80%">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Validar y Descargar',
            confirmButtonColor: '#28a745',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const u = Swal.getPopup().querySelector('#swal_u').value;
                const p = Swal.getPopup().querySelector('#swal_p').value;
                if (!u || !p) {
                    Swal.showValidationMessage(`Complete ambos campos para continuar`);
                }
                return { user: u, pass: p };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // CREACIÓN DE FORMULARIO POST SEGURO
                // Usamos un formulario oculto para que los datos no viajen en la URL (GET)
                const form = document.createElement('form');
                form.method = 'POST';
                // IMPORTANTE: Esta URL debe coincidir con tu .htaccess o ruta directa
                form.action = BASE_URL + 'tesoreria/descargar-txt/' + idLote;

                // Estos nombres deben ser EXACTAMENTE iguales en el $_POST del PHP
                const inputUser = document.createElement('input');
                inputUser.type = 'hidden';
                inputUser.name = 'confirm_user'; 
                inputUser.value = result.value.user;
                
                const inputPass = document.createElement('input');
                inputPass.type = 'hidden';
                inputPass.name = 'confirm_pass';
                inputPass.value = result.value.pass;

                form.appendChild(inputUser);
                form.appendChild(inputPass);
                document.body.appendChild(form);
                
                // Ejecutamos el envío
                form.submit();
                
                // Limpiamos el DOM
                document.body.removeChild(form);
                
                // REFRESCADO DE TABLA
                // Esperamos 1.5 segundos para dar tiempo a que el PHP procese el UPDATE de 'descargado_txt'
                setTimeout(() => { 
                    if (typeof tablaLotes !== 'undefined') {
                        tablaLotes.ajax.reload(null, false); 
                    }
                }, 1500);
            }
        });
    }

    function formatChildRow(id) {
        return `<div class="subtable-container">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0 small"><i class="fas fa-users-cog me-2"></i>Agentes del Lote #${id}</h6>
                        <div class="d-flex gap-2">
                            <input type="text" id="bus_nom_${id}" class="form-control form-control-sm" style="width:150px" placeholder="Apellido...">
                            <input type="text" id="bus_dni_${id}" class="form-control form-control-sm" style="width:120px" placeholder="DNI...">
                            <button class="btn btn-primary btn-sm" onclick="recargarSubTabla(${id})"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                    <table id="subtable_${id}" class="table table-sm table-bordered w-100 shadow-sm bg-white">
                        <thead class="table-light">
                            <tr>
                                <th>Agente / Beneficiario</th>
                                <th>DNI</th>
                                <th class="text-end">Bruto</th>
                                <th class="text-end">Retenciones</th>
                                <th class="text-end">Neto a Cobrar</th>
                            </tr>
                        </thead>
                    </table>
                </div>`;
    }

    function recargarSubTabla(id) {
        if ($.fn.DataTable.isDataTable(`#subtable_${id}`)) {
            $(`#subtable_${id}`).DataTable().ajax.reload();
        }
    }

    function initSubTable(id) {
        $(`#subtable_${id}`).DataTable({
            "processing": true,
            "serverSide": true,
            "searching": false,
            "pageLength": 5,
            "ajax": {
                "url": BASE_URL + 'ajax/tesoreria/obtener-detalle-lote-nested',
                "type": "POST",
                "data": function(d) {
                    return {
                        id_lote: id,
                        draw: d.draw,
                        start: d.start,
                        length: d.length,
                        apellido: $(`#bus_nom_${id}`).val(),
                        dni: $(`#bus_dni_${id}`).val()
                    };
                }
            },
            "columns": [
                { "data": "agente" },
                { "data": "dni" },
                { "data": "bruto", "className": "text-end" },
                { "data": "ret", "className": "text-end text-danger" },
                { "data": "neto", "className": "text-end fw-bold text-success" }
            ],
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
        });
    }

    function prepararAccion(idLote, accion) {
        $('#auth_id_lote').val(idLote);
        $('#auth_accion').val(accion);
        $('#auth_user, #auth_pass, #auth_motivo').val('');
        
        if(accion === 'confirmar') {
            $('#headerAuth').removeClass('bg-danger').addClass('bg-success');
            $('#txtTituloModal').html('<i class="fas fa-check-circle me-2"></i>Confirmar Pago de Lote');
            $('#btnEjecutar').removeClass('btn-danger').addClass('btn-success').text('Confirmar Lote');
            $('#divMotivo').hide();
        } else if(accion === 'rechazo') {
            $('#headerAuth').removeClass('bg-success bg-danger').addClass('bg-warning');
            $('#txtTituloModal').html('<i class="fas fa-university me-2"></i>Informar Rechazo Bancario');
            $('#btnEjecutar').removeClass('btn-success btn-danger').addClass('btn-warning').text('Registrar Rechazo');
            $('#divMotivo').show();
            $('#infoAlerta').html('<i class="fas fa-info-circle me-2"></i> Esto liberará a los agentes para ser liquidados nuevamente en un nuevo lote.');
        } else {
            $('#headerAuth').removeClass('bg-success').addClass('bg-danger');
            $('#txtTituloModal').html('<i class="fas fa-times-circle me-2"></i>Anular Lote de Liquidación');
            $('#btnEjecutar').removeClass('btn-success').addClass('btn-danger').text('Anular Lote');
            $('#divMotivo').show();
        }
        $('#modalAutorizar').modal('show');
    }
</script>   

<?php include '../../vistas/footer.php'; ?>
</body>
</html>