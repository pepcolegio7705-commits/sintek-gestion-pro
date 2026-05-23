<?php
    session_start();
    require 'conexion.php'; 
    require 'seguridad.php'; 
    require_once 'funciones_tesoreria.php'; // Motor de validación financiera

    // Solo Administradores y Secretarias
    verificar_permisos(['Administrador', 'Secretaría']);
    $mensaje = [];
    $rol = $_SESSION['rol'];

    // 1. CARGA INICIAL: Carreras
   $carreras_disponibles = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera")->fetchAll(PDO::FETCH_ASSOC);

    // 2. PROCESAMIENTO DE PETICIONES (POST)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        // --- ACCIÓN A: QUITAR MATERIA (Vía AJAX) ---
        if ($_POST['action'] == 'quitar_materia') {
            header('Content-Type: application/json');
            try {
                $id_ins = (int)$_POST['id_inscripcion'];
                $stmt = $pdo->prepare("DELETE FROM inscripciones_espacios WHERE id_inscripcion = ?");
                $stmt->execute([$id_ins]);
                echo json_encode(['status' => 'success', 'message' => 'Materia dada de baja']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit; 
        }

        // --- ACCIÓN B: INSCRIBIR MATERIAS (Vía Formulario) ---
        if ($_POST['action'] == 'inscribir_materias') {
            $id_alumno = (int)$_POST['id_alumno_insc'];
            $id_carrera = (int)$_POST['id_carrera_insc']; 
            $cohorte = (int)$_POST['cohorte']; 
            $materias = $_POST['materias'] ?? [];

            if ($id_alumno > 0 && $id_carrera > 0 && !empty($materias)) {
                try {
                    // --- NUEVA VALIDACIÓN DE TESORERÍA INTEGRADA ---
                    // Esta función ahora usa 'anio_lectivo' y chequea mora histórica
                    $validacion = validarInscripcion($id_alumno, $pdo); 
                    
                    if (!$validacion['status']) {
                        throw new Exception($validacion['msj']);
                    }

                    $pdo->beginTransaction();

                    // A. VINCULAR ALUMNO CON CARRERA (ON DUPLICATE para no repetir)
                    $stmtCarrera = $pdo->prepare("INSERT INTO alumnos_carreras (id_alumno, id_carreras, cohorte) 
                                                VALUES (?, ?, ?) 
                                                ON DUPLICATE KEY UPDATE cohorte = VALUES(cohorte)");
                    $stmtCarrera->execute([$id_alumno, $id_carrera, $cohorte]);

                    // B. INSCRIBIR MATERIAS (INSERT IGNORE evita duplicados)
                    $stmtMaterias = $pdo->prepare("INSERT IGNORE INTO inscripciones_espacios (id_alumno, id_espacio) VALUES (?, ?)");
                    
                    $ultimo_id_espacio = 0;
                    foreach ($materias as $id_espacio) {
                        $id_e = (int)$id_espacio;
                        $stmtMaterias->execute([$id_alumno, $id_e]);
                        $ultimo_id_espacio = $id_e; 
                    }

                    // C. COMPATIBILIDAD (Legacy)
                    if ($ultimo_id_espacio > 0) {
                        $stmtLegacy = $pdo->prepare("UPDATE alumnos SET id_espacio = ? WHERE id_alumno = ?");
                        $stmtLegacy->execute([$ultimo_id_espacio, $id_alumno]);
                    }

                    $pdo->commit();
                    $mensaje = ['icon' => 'success', 'title' => '¡Éxito!', 'text' => "Inscripción en cohorte $cohorte procesada correctamente."];
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $mensaje = ['icon' => 'error', 'title' => 'Bloqueo de Inscripción', 'text' => $e->getMessage()];
                }
            } else {
                $mensaje = ['icon' => 'warning', 'title' => 'Atención', 'text' => "Debe seleccionar carrera y al menos una materia."];
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión Académica | Inscripciones</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .modal-header { background-color: #0d6efd; color: white; border-bottom: none; }
        .lista-materias { 
            max-height: 400px; 
            overflow-y: auto; 
            border: 1px solid #dee2e6; 
            padding: 15px; 
            border-radius: 8px; 
            background: #ffffff; 
        }
        .card-table { border: none; border-radius: 12px; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
    </style>
</head>
<body>
    <?php include 'vistas/nav.php'; ?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col">
            <h2 class="fw-bold text-dark"><i class="fas fa-user-edit text-primary me-2"></i>Gestión de Inscripciones</h2>
            <p class="text-muted small">Administre las materias y cohortes de los alumnos.</p>
        </div>
    </div>
    
    <div class="card card-table p-3">
        <div class="table-responsive">
            <table id="tablaInscripciones" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>DNI</th>
                        <th>Alumno</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestion" tabindex="-1" aria-labelledby="modalGestionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formInscripcion" method="POST">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="modalGestionLabel">
                        <i class="fas fa-graduation-cap me-2"></i>Inscripciones: <span id="nombreAlumnoLabel" class="badge bg-light text-primary ms-2"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="id_alumno_insc" id="id_alumno_insc">
                    <input type="hidden" name="id_carrera_insc" id="id_carrera_insc">
                    <input type="hidden" name="action" value="inscribir_materias">

                    <div class="row g-4">
                        <div class="col-md-6 border-end">
                            <h6 class="text-primary fw-bold mb-3"><i class="fas fa-plus-circle me-1"></i> Nueva Asignación</h6>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Carrera</label>
                                    <select id="selectCarrera" class="form-select shadow-sm" required>
                                        <option value="">-- Seleccione Carrera --</option>
                                        <?php foreach($carreras_disponibles as $c): ?>
                                            <option value="<?= $c['id_carrera'] ?>"><?= $c['nombre_carrera'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Cohorte</label>
                                    <select name="cohorte" class="form-select shadow-sm" required>
                                        <?php 
                                            $anio_actual = date('Y');
                                            for($i = $anio_actual; $i >= $anio_actual - 5; $i--): 
                                        ?>
                                            <option value="<?= $i ?>"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="divMaterias" style="display:none;">
                                <label class="form-label small fw-bold mt-2">Seleccionar Espacios Curriculares:</label>
                                <div id="listaCheckMaterias" class="lista-materias shadow-sm mb-3"></div>
                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                                    <i class="fas fa-save me-1"></i> Registrar Inscripción
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-secondary fw-bold mb-3"><i class="fas fa-list-check me-1"></i> Materias en Curso</h6>
                            <div id="materiasActuales" class="lista-materias border-0 bg-light">
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="js/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        var tabla = $('#tablaInscripciones').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": { "url": "server_processing_alumnos.php", "type": "POST" },
            "columnDefs": [
                {
                    "targets": 3,
                    "render": function(data, type, row) {
                        return '<button type="button" class="btn btn-primary btn-sm rounded-pill px-3" onclick="abrirGestion(' + row[0] + ')"><i class="fas fa-cog me-1"></i> Gestionar</button>';
                    },
                    "className": "text-center"
                }
            ],
            "columns": [
                { "data": 2 },
                { "render": function(data, type, row) { return '<span class="fw-bold">' + row[4] + '</span>, ' + row[3]; } },
                { "render": function(data, type, row) { 
                    let color = row[5] == 'Activo' ? 'success' : 'secondary';
                    return '<span class="badge bg-' + color + '">' + row[5] + '</span>'; 
                }},
                { "data": null }
            ],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json" }
        });

        $('#selectCarrera').change(function() {
            const idCarrera = $(this).val();
            const idAlumno = $('#id_alumno_insc').val();
            $('#id_carrera_insc').val(idCarrera);

            if(idCarrera) {
                $.post('get_materias_por_carrera.php', { id_carrera: idCarrera, id_alumno: idAlumno }, function(resp) {
                    $('#listaCheckMaterias').html(resp);
                    $('#divMaterias').fadeIn();
                });
            } else {
                $('#divMaterias').hide();
            }
        });

        <?php if (!empty($mensaje)): ?>
            Swal.fire({ 
                icon: <?= json_encode($mensaje['icon']); ?>, 
                title: <?= json_encode($mensaje['title']); ?>, 
                text: <?= json_encode($mensaje['text']); ?>,
                confirmButtonColor: '#0d6efd'
            });
        <?php endif; ?>
    });

    function abrirGestion(id) {
        var table = $('#tablaInscripciones').DataTable();
        var rowData = table.rows().data().toArray().find(i => i[0] == id);
        
        if (rowData) {
            $('#id_alumno_insc').val(id);
            $('#nombreAlumnoLabel').text(rowData[4] + ", " + rowData[3]);
            
            // 1. Limpiar estados previos y mostrar "cargando" si quieres
            $('#alerta-tesoreria').remove();
            $('#selectCarrera').prop('disabled', true); // Bloqueamos preventivamente
            $('#formInscripcion button[type="submit"]').hide();

            // 2. Consultar al puente AJAX que creamos arriba
            $.getJSON('ajax_check_deuda.php?id_alumno=' + id, function(respuesta) {
                if (respuesta.status === false) {
                    // Si el status es false, mostramos el error en ROJO
                    let alertHtml = `
                        <div id="alerta-tesoreria" class="alert alert-danger border-0 shadow-sm mb-4 animate__animated animate__fadeIn">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                                <div>
                                    <strong class="d-block">Inscripción Bloqueada</strong>
                                    ${respuesta.msj}
                                </div>
                            </div>
                        </div>`;
                    $('#formInscripcion .modal-body').prepend(alertHtml);
                    
                    // Mantenemos bloqueado
                    $('#selectCarrera').prop('disabled', true);
                    $('#formInscripcion button[type="submit"]').hide(); 
                } else {
                    // Si está al día, habilitamos todo
                    $('#selectCarrera').prop('disabled', false);
                    $('#formInscripcion button[type="submit"]').show();
                }
            }).fail(function() {
                console.error("Error al validar tesorería");
                $('#selectCarrera').prop('disabled', false); // Por seguridad, si falla el ajax permitimos, o bloqueamos según prefieras
            });

            $('#selectCarrera').val('');
            $('#divMaterias').hide();
            cargarListaInscriptas(id);
            var myModal = new bootstrap.Modal(document.getElementById('modalGestion'));
            myModal.show();
        }
    }

    function cargarListaInscriptas(id) {
        $('#materiasActuales').html('<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></div>');
        $('#materiasActuales').load('get_materias_alumno.php?id=' + id);
    }

    function quitarMateria(idInscripcion) {
        Swal.fire({
            title: '¿Dar de baja materia?',
            text: "Esta acción eliminará la inscripción del alumno en este espacio.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, dar de baja',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('inscripciones.php', { action: 'quitar_materia', id_inscripcion: idInscripcion }, function() {
                    cargarListaInscriptas($('#id_alumno_insc').val());
                    $('#selectCarrera').trigger('change');
                });
            }
        });
    }
</script>
<?php include 'vistas/footer.php'; ?>
</body>
</html>