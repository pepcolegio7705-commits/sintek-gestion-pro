<?php
session_start();
// 1. Cargamos el núcleo
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

// Solo permitimos a Administradores y Secretarias
verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol']; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Alumnos | SINTEK</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; border-radius: 10px; }
        .btn-pdf { width: 32px; height: 32px; padding: 0; line-height: 32px; text-align: center; }
        .table thead { background-color: #212529; color: white; }
        .badge-activo { background-color: #198754; }
        .badge-inactivo { background-color: #dc3545; }
    </style>
</head>
<body>
  <?php include '../../vistas/nav.php'; ?>

<div class="container-fluid py-4 px-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-start border-4 border-secondary">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-archive text-secondary me-2"></i> Histórico General</h2>
                        <p class="text-muted mb-0">Gestión de expedientes: Consulte, edite documentos o reactive alumnos inactivos.</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark text-uppercase p-2">Perfil: <?= $rol ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaHistorico" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th> 
                            <th>Legajo</th>
                            <th>DNI</th>
                            <th>Apellido y Nombre</th>
                            <th>Libro/Folio</th>
                            <th class="text-center">Documentación</th>
                            <th>Inscripción</th> 
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    var tabla = $('#tablaHistorico').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
           "url": "<?= BASE_URL; ?>ajax/alumnos/alumnos_historico_server",
            "type": "POST"
        },
        "columns": [
            { "data": 0 }, // ID (interno)
            { "data": 4 }, // Legajo
            { "data": 1 }, // DNI
            { "data": 2 }, // Apellido y Nombre
            { 
                "data": null,
                "render": function(data, type, row) {
                    return `<span class="small text-muted">L:</span> <b>${row[5] || '--'}</b> <br> <span class="small text-muted">F:</span> <b>${row[6] || '--'}</b>`;
                }
            },
            { 
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    let pathLegajos = "<?= BASE_URL; ?>uploads/legajos/";

                    let btnDni = row[7] 
                        ? `<a href="${pathLegajos}${row[7]}" target="_blank" class="btn btn-sm btn-outline-primary btn-pdf" title="DNI"><i class="fas fa-id-card"></i></a>` 
                        : `<button class="btn btn-sm btn-light text-muted btn-pdf" disabled><i class="fas fa-id-card"></i></button>`;

                    let btnTitulo = row[8] 
                        ? `<a href="${pathLegajos}${row[8]}" target="_blank" class="btn btn-sm btn-outline-info btn-pdf" title="Título"><i class="fas fa-graduation-cap"></i></a>` 
                        : `<button class="btn btn-sm btn-light text-muted btn-pdf" disabled><i class="fas fa-graduation-cap"></i></button>`;

                    let btnAptitud = row[9] 
                        ? `<a href="${pathLegajos}${row[9]}" target="_blank" class="btn btn-sm btn-outline-success btn-pdf" title="Aptitud"><i class="fas fa-file-medical"></i></a>` 
                        : `<button class="btn btn-sm btn-light text-muted btn-pdf" disabled><i class="fas fa-file-medical"></i></button>`;

                    return `<div class="d-flex justify-content-center gap-1">${btnDni} ${btnTitulo} ${btnAptitud}</div>`;
                }
            },
            { 
                data: 10, render: function(data) {
                    if (!data) return '<span class="text-muted">---</span>';
                    return data.split(' ')[0].split('-').reverse().join('/');
                }
            },
            { 
                data: 3, className: "text-center", 
                render: function(data) {
                    let badgeClass = (data == 1) ? 'bg-success' : 'bg-danger'; 
                    let text = (data == 1) ? 'ACTIVO' : 'INACTIVO'; 
                    return `<span class="badge rounded-pill ${badgeClass}">${text}</span>`;
                }
            },
            { 
                data: null, 
                className: "text-center", 
                orderable: false, 
                render: function(data, type, row) { 
                    // CLAVE: Asumimos que row[11] es el UUID_ALUMNO enviado por el server
                    let uuid = row[11];
                    let btnEstadoClase = (row[3] == 1) ? 'btn-outline-danger' : 'btn-outline-success'; 
                    let btnEstadoIcon = (row[3] == 1) ? 'fa-user-slash' : 'fa-user-check'; 
                    let btnEstadoTitle = (row[3] == 1) ? 'Dar de Baja' : 'Activar Alumno';
                        
                    return ` <div class="btn-group shadow-sm"> 
                        <button class="btn btn-sm btn-primary btn-edit" data-uuid="${uuid}" title="Editar Legajo"> 
                            <i class="fas fa-edit"></i> 
                        </button> 
                        <button class="btn btn-sm ${btnEstadoClase} btn-estado" data-uuid="${uuid}" data-estado="${row[3]}" title="${btnEstadoTitle}"> 
                            <i class="fas ${btnEstadoIcon}"></i> 
                        </button> 
                        <button class="btn btn-sm btn-dark btn-print" data-uuid="${uuid}" title="Ver Expediente"> 
                            <i class="fas fa-print"></i> Expediente 
                        </button> 
                    </div>`;
                }
            }
        ], 
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json" },
        order: [[3, "asc"]]
    });

    // --- MANEJO DE EVENTOS MEDIANTE UUID ---

    $('#tablaHistorico').on('click', '.btn-edit', function() {
        let uuid = $(this).data('uuid');
        window.location.href = `<?= BASE_URL; ?>alumnos/editar/${uuid}`;
    });

    $('#tablaHistorico').on('click', '.btn-print', function() {
        let uuid = $(this).data('uuid');
        // Abrimos el expediente en pestaña nueva para no perder el listado
        window.open(`<?= BASE_URL; ?>alumnos/expediente/${uuid}`, '_blank');
    });

    $('#tablaHistorico').on('click', '.btn-estado', function() {
        let uuid = $(this).data('uuid'); 
        let estadoActual = $(this).data('estado'); 
        let nuevoEstado = (estadoActual == 1) ? 0 : 1;
        let accion = (nuevoEstado == 1) ? 'ACTIVAR' : 'DESACTIVAR';

        Swal.fire({
            title: `¿Desea ${accion} al alumno?`,
            text: "Esta acción cambiará la visibilidad del alumno en el sistema activo.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviamos UUID al Handler amigable
                $.post(`<?= BASE_URL; ?>alumnos/toggle_estado`, { 
                    action: 'toggle_activo', 
                    uuid_alumno: uuid, 
                    estado: nuevoEstado 
                }, function(r) {
                    if(r.success) {
                        tabla.ajax.reload(null, false); 
                        Swal.fire('¡Éxito!', r.message, 'success');
                    } else {
                        Swal.fire('Error', r.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>