<?php
    session_start();
    // 1. Ajuste de rutas: Subimos 2 niveles para llegar al core
    require_once '../../core/conexion.php'; 
    require_once '../../core/seguridad.php'; 

    // Verificación de permisos para acceso a la vista
    verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);
    $rol = $_SESSION['rol'];

    // Definimos si el usuario puede eliminar (Solo Admin y Secretaría según tu nueva pauta)
    $puede_eliminar = ($rol === 'Administrador' || $rol === 'Secretaría');

    // JS para SweetAlert con manejo de motivo de anulación
    $js_sweetalert_confirmacion = '
        function confirmarAccion(id, nombre) {
            Swal.fire({
                title: "¿Estás seguro?",
                html: `Vas a eliminar la inscripción de: <br><strong>${nombre}</strong><br><br><small class="text-danger">Aviso: El pago asociado se anulará.</small>`,
                icon: "warning",
                input: "textarea",
                inputPlaceholder: "Escribe aquí el motivo de la anulación (obligatorio)...",
                showCancelButton: true,
                confirmButtonColor: "#dc3545",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "<i class=\'fas fa-trash\'></i> Confirmar Anulación",
                cancelButtonText: "Cancelar",
                reverseButtons: true,
                preConfirm: (motivo) => {
                    if (!motivo) {
                        Swal.showValidationMessage("Debes ingresar un motivo para continuar");
                    }
                    return motivo;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    eliminarInscripcion(id, result.value);
                }
            });
        }';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Inscripciones | Sintek Gestión</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f7f6; padding-bottom: 50px; }
        .card { border: none; border-radius: 12px; }
        .btn-xs { padding: .25rem .5rem; font-size: .75rem; border-radius: 6px; }
        .table thead th { background-color: #f8f9fa; color: #334155; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        .sticky-filter { position: sticky; top: 70px; z-index: 100; }
    </style>
</head>
<body>
<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid px-lg-5 mt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-0"><i class="fas fa-file-signature text-primary me-2"></i> Gestión de Inscripciones</h2>
            <p class="text-muted mb-0">Administre las inscripciones a mesas de examen abiertas.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>instancias/historial" class="btn btn-outline-secondary rounded-pill shadow-sm">
                <i class="fas fa-history me-1"></i> Mesas Cerradas
            </a>
            <a href="<?= BASE_URL ?>instancias/inscripcion-rapida" class="btn btn-success rounded-pill shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> Nueva Inscripción
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4 sticky-filter">
        <div class="card-body">
            <form id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label small fw-bold">Seleccionar Mesa de Examen:</label>
                    <select class="form-select shadow-sm" id="id_mesa" name="id_mesa">
                        <option value="">-- Ver todas las inscripciones activas --</option>
                        <?php
                        $sql = "SELECT m.uuid_mesa, e.nombre_espacio, m.llamado, m.fecha_examen, c.nombre_carrera 
                                FROM mesas_examenes m 
                                JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio 
                                JOIN carreras c ON m.id_carrera = c.id_carrera
                                WHERE m.estado = 'Abierta'
                                ORDER BY m.fecha_examen DESC";
                        foreach ($pdo->query($sql) as $row) {
                            $f = date('d/m/Y', strtotime($row['fecha_examen']));
                            echo "<option value='{$row['uuid_mesa']}'>{$row['nombre_carrera']} | {$row['nombre_espacio']} ({$row['llamado']} - {$f})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="button" id="btnAplicarFiltros" class="btn btn-primary w-100 rounded-pill">
                        <i class="fas fa-search me-1"></i> Buscar
                    </button>
                    <button type="button" id="btnCargarNotasDinamico" class="btn btn-outline-primary rounded-pill w-100">
                        <i class="fas fa-edit me-1"></i> Cargar Notas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaInscripciones" class="table table-hover align-middle" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha Insc.</th>
                            <th>DNI</th>
                            <th>Alumno</th>
                            <th>Carrera</th>
                            <th>Materia</th>
                            <th>Condición</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    var tablaInscripciones = $('#tablaInscripciones').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "<?= BASE_URL ?>instancias/inscripciones-data",
            "type": "POST",
            "data": function (d) {
                d.id_mesa = $('#id_mesa').val();
            }
        },
        "columns": [
            { "data": 0, "visible": false },
            { "data": 1 },
            { "data": 2 },
            { "data": 3, "className": "fw-bold" },
            { "data": 4, "className": "small" },
            { "data": 5, "className": "small fw-medium" },
            { 
                "data": 6, 
                "className": "text-center",
                "render": function(data) {
                    let valor = data ? data.toUpperCase() : 'S/D';
                    let clase = (valor === 'REGULAR') ? 'bg-warning text-dark' : (valor === 'LIBRE' ? 'bg-danger text-white' : 'bg-secondary');
                    return `<span class="badge rounded-pill ${clase} shadow-sm px-3">${valor}</span>`;
                }
            }, 
            { 
                "data": null, 
                "orderable": false,
                "className": "text-center",
                "render": function(data, type, row) {
                    let puedeEliminar = <?= json_encode($puede_eliminar) ?>;
                    if (puedeEliminar) {
                        return `<button class="btn btn-outline-danger btn-xs btn-eliminar shadow-sm" data-id="${row[0]}" data-nombre="${row[3]}">
                                    <i class="fas fa-trash-alt"></i>
                                </button>`;
                    }
                    return `<i class="fas fa-lock text-muted"></i>`;
                }
            }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json" }
    });

    $('#btnAplicarFiltros').click(function() {
        tablaInscripciones.ajax.reload();
    });

    $('#btnCargarNotasDinamico').click(function() {
        var uuidMesa = $('#id_mesa').val();
        if(!uuidMesa) {
            Swal.fire('Atención', 'Seleccione una Mesa de la lista para cargar sus notas.', 'info');
        } else {
            window.location.href = '<?= BASE_URL ?>mesas/calificaciones/' + uuidMesa;
        }
    });

    $('#tablaInscripciones').on('click', '.btn-eliminar', function() {
        confirmarAccion($(this).data('id'), $(this).data('nombre'));
    });

    window.eliminarInscripcion = function(id, motivo) {
        $.post('<?= BASE_URL ?>modules/instancias/acciones_inscripcion_CONTROL.php', { 
            action: 'eliminar', 
            id_inscripcion: id,
            motivo: motivo 
        }, function(r) {
            if(r.success) {
                Swal.fire('¡Eliminado!', r.message, 'success');
                tablaInscripciones.ajax.reload();
            } else {
                Swal.fire('Error', r.message, 'error');
            }
        }, 'json');
    };
});

<?= $js_sweetalert_confirmacion; ?>
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>