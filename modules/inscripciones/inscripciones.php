<?php
    session_start();
    require_once '../../core/conexion.php'; 
    require_once '../../core/seguridad.php'; 
    require_once '../../core/funciones_tesoreria.php'; 

    verificar_permisos(['Administrador', 'Secretaría']);
    $mensaje = [];
    $rol = $_SESSION['rol'];

    // 1. CARGA INICIAL: Solo para el modal de vinculación inicial (Swal)
    $carreras_disponibles = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera")->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        
        // --- ACCIÓN A: QUITAR MATERIA ---
        if ($_POST['action'] == 'quitar_materia') {
            header('Content-Type: application/json');
            try {
                $id_ins = (int)$_POST['id_inscripcion'];
                
                // 1. Buscamos qué materia y alumno es antes de borrar
                $stmt_info = $pdo->prepare("SELECT id_alumno, id_espacio FROM inscripciones_espacios WHERE id_inscripcion = ?");
                $stmt_info->execute([$id_ins]);
                $info = $stmt_info->fetch();

                if ($info) {
                    $pdo->beginTransaction();
                    // 2. Borramos la inscripción
                    $pdo->prepare("DELETE FROM inscripciones_espacios WHERE id_inscripcion = ?")->execute([$id_ins]);
                    
                    // 3. Borramos la planilla de notas (para que no quede basura)
                    $pdo->prepare("DELETE FROM cursadas_notas WHERE id_alumno = ? AND id_espacio = ? AND ciclo_lectivo = ?")
                        ->execute([$info['id_alumno'], $info['id_espacio'], date('Y')]);
                    
                    $pdo->commit();
                    echo json_encode(['status' => 'success', 'message' => 'Inscripción dada de baja.']);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }

        // --- ACCIÓN B: INSCRIBIR MATERIAS Y GENERAR PLANILLA DE NOTAS ---
        if ($_POST['action'] == 'inscribir_materias') {
            header('Content-Type: application/json');
            
            $uuid_alumno = $_POST['uuid_alumno_insc'];
            $id_carrera = (int)$_POST['id_carrera_insc']; 
            $cohorte = (int)$_POST['cohorte']; 
            $materias = $_POST['materias'] ?? [];
            $ciclo_actual = date('Y');

            try {
                $stmt_id = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE uuid_alumno = ?");
                $stmt_id->execute([$uuid_alumno]);
                $id_alumno = $stmt_id->fetchColumn();

                if (!$id_alumno) throw new Exception("Alumno no identificado.");

                $validacion = validarInscripcion($id_alumno, $pdo); 
                if (!$validacion['status']) throw new Exception($validacion['msj']);
                if (empty($materias)) throw new Exception("Debe seleccionar al menos una materia.");

                $pdo->beginTransaction();

                // Asegurar que existe el vínculo con la carrera (id_carreras plural según tu tabla)
                $stmtCarrera = $pdo->prepare("INSERT IGNORE INTO alumnos_carreras (id_alumno, id_carreras, cohorte, fecha_insc) 
                                             VALUES (?, ?, ?, NOW())");
                $stmtCarrera->execute([$id_alumno, $id_carrera, $cohorte]);

                // Preparar inserción de materias e inserción de PLANILLA DE NOTAS
                $stmtMaterias = $pdo->prepare("INSERT IGNORE INTO inscripciones_espacios (id_alumno, id_espacio) VALUES (?, ?)");
                
                // Nueva tabla para cursada regular
                $stmtPlanilla = $pdo->prepare("INSERT IGNORE INTO cursadas_notas (id_alumno, id_carrera, id_espacio, ciclo_lectivo) 
                                               VALUES (?, ?, ?, ?)");

                foreach ($materias as $id_espacio) {
                    $stmtMaterias->execute([$id_alumno, (int)$id_espacio]);
                    $stmtPlanilla->execute([$id_alumno, $id_carrera, (int)$id_espacio, $ciclo_actual]);
                }

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => "Inscripción procesada y planilla de cursada generada."]);
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }

        // --- ACCIÓN C: VINCULAR CARRERA ---
        if ($_POST['action'] == 'vincular_carrera') {
            header('Content-Type: application/json');
            try {
                $uuid_alumno = $_POST['uuid_alumno'];
                $id_carrera = (int)$_POST['id_carrera'];
                $cohorte = (int)$_POST['cohorte'];

                $stmt_id = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE uuid_alumno = ?");
                $stmt_id->execute([$uuid_alumno]);
                $id_alumno = $stmt_id->fetchColumn();

                if (!$id_alumno) throw new Exception("Alumno no identificado.");

                $check = $pdo->prepare("SELECT fecha_insc FROM alumnos_carreras WHERE id_alumno = ? AND id_carreras = ?");
                $check->execute([$id_alumno, $id_carrera]);
                $existe = $check->fetch();

                if ($existe) {
                    throw new Exception("El alumno ya se encuentra inscripto en esta carrera.");
                }

                $stmt = $pdo->prepare("INSERT INTO alumnos_carreras (id_alumno, id_carreras, cohorte, fecha_insc) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$id_alumno, $id_carrera, $cohorte]);

                echo json_encode(['status' => 'success', 'message' => 'Matriculación exitosa.']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
            exit;
        }
    }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inscripciones | Sintek Gestión</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .modal-header { background-color: #003366; color: white; }
        .lista-materias { max-height: 350px; overflow-y: auto; border: 1px solid #eee; padding: 10px; background: #fff; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container mt-4">
        <div class="card border-0 shadow-sm p-4">
            <h2 class="fw-bold"><i class="fas fa-edit text-primary me-2"></i> Gestión de Inscripciones</h2>
            
            <div class="row mb-4 g-3">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm border-start border-primary border-5">
                        <div class="card-body p-3">
                            <p class="text-muted small fw-bold mb-1 text-uppercase">Total de Alumnos</p>
                            <h4 class="fw-bold mb-0" id="total_alumnos">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm border-start border-success border-5">
                        <div class="card-body p-3">
                            <p class="text-muted small fw-bold mb-1 text-uppercase">Vinculados a Carreras</p>
                            <h4 class="fw-bold text-success mb-0" id="total_vinculados">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm border-start border-danger border-5">
                        <div class="card-body p-3">
                            <p class="text-muted small fw-bold mb-1 text-uppercase">Sin Carrera (Pendientes)</p>
                            <h4 class="fw-bold text-danger mb-0" id="total_sin_carrera">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <hr>
            <table id="tablaInscripciones" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Alumno</th>
                        <th>Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalGestion" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formInscripcion">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-user-graduate me-2"></i> Inscribir: <span id="nombreAlumnoLabel"></span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="uuid_alumno_insc" id="uuid_alumno_insc">
                        <input type="hidden" name="id_carrera_insc" id="id_carrera_insc">
                        <input type="hidden" name="action" value="inscribir_materias">

                        <div id="contenedor-alerta"></div> 
                        <div class="row">
                            <div class="col-md-6 border-end">
                                <label class="fw-bold small">Carrera</label>
                                <select id="selectCarrera" class="form-select mb-3" required disabled>
                                    <option value="">Cargando carreras vinculadas...</option>
                                </select>

                                <label class="fw-bold small">Cohorte</label>
                                <select name="cohorte" class="form-select mb-3">
                                    <?php for($i=date('Y'); $i>=date('Y')-2; $i--) echo "<option value='$i'>$i</option>"; ?>
                                </select>

                                <div id="divMaterias" style="display:none;">
                                    <label class="fw-bold small">Materias Disponibles</label>
                                    <div id="listaCheckMaterias" class="lista-materias mb-3"></div>
                                    <button type="submit" class="btn btn-primary w-100 fw-bold">REGISTRAR INSCRIPCIÓN</button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold small text-muted">INSCRIPCIONES ACTUALES</label>
                                <div id="materiasActuales" class="lista-materias bg-light border-0"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            var tabla = $('#tablaInscripciones').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": { "url": "<?= BASE_URL ?>inscripciones/data-alumnos", "type": "POST" },
                "columns": [
                    { "data": 0 }, 
                    { "data": 1 }, 
                    { "data": 2 }, 
                    { 
                        "data": 3, 
                        "render": function(data, type, row) {
                            return `
                                <div class="btn-group shadow-sm">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="abrirVincular('${data}')">
                                        <i class="fas fa-link"></i> Matricular
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="abrirGestion('${data}')">
                                        <i class="fas fa-cog"></i> Gestionar
                                    </button>
                                </div>`;
                        },
                        "className": "text-center"
                    }
                ],
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json" },
                "drawCallback": function(settings) {
                    var json = settings.json;
                    if (json && json.kpis) {
                        $('#total_alumnos').text(json.kpis.total);
                        $('#total_vinculados').text(json.kpis.vinculados);
                        $('#total_sin_carrera').text(json.kpis.sin_carrera);
                    }
                }
            });

            // Usamos delegación de eventos para elementos cargados por .load()
            $(document).on('click', '.btn-quitar-materia', function(e) {
                e.preventDefault();
                
                const idInscripcion = $(this).data('id');
                const nombreMateria = $(this).data('nombre');
                const uuid = $('#uuid_alumno_insc').val();
                const idCarrera = $('#selectCarrera').val();

                Swal.fire({
                    title: '¿Quitar asignatura?',
                    html: `Se dará de baja la inscripción de:<br><strong>${nombreMateria}</strong>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar cargando
                        Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                        $.post('<?= BASE_URL ?>inscripciones/gestionar', { 
                            action: 'quitar_materia', 
                            id_inscripcion: idInscripcion 
                        }, function(response) {
                            if (response.status === 'success') {
                                // 1. Refrescar el panel derecho (Materias Actuales)
                                $('#materiasActuales').load('<?= BASE_URL ?>inscripciones/materias-alumno/' + uuid);
                                
                                // 2. Refrescar el panel izquierdo (Materias Disponibles) para que vuelva a aparecer
                                if(idCarrera) {
                                    $.post('<?= BASE_URL ?>inscripciones/materias-disponibles', { 
                                        id_carrera: idCarrera, 
                                        uuid_alumno: uuid 
                                    }, function(resp) {
                                        $('#listaCheckMaterias').html(resp);
                                    });
                                }

                                // 3. Refrescar la tabla principal (por si cambian los KPIs)
                                $('#tablaInscripciones').DataTable().ajax.reload(null, false);

                                Swal.fire('¡Eliminado!', response.message, 'success');
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        }, 'json').fail(function() {
                            Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                        });
                    }
                });
            });
        });

        function abrirGestion(uuid) {
            var table = $('#tablaInscripciones').DataTable();
            var rowData = table.rows().data().toArray().find(i => i[3] === uuid);
            
            if (rowData) {
                $('#uuid_alumno_insc').val(uuid);
                let nombreLimpio = rowData[1].replace(/<[^>]*>?/gm, '');
                $('#nombreAlumnoLabel').text(nombreLimpio);
                
                // Reset Interfaz
                $('#contenedor-alerta').html('');
                $('#selectCarrera').empty().append('<option value="">Seleccione Carrera...</option>').prop('disabled', true);
                $('#divMaterias').hide();
                $('#id_carrera_insc').val('');

                // Validación Tesorería + Carga de Carreras del Alumno
                $.getJSON('<?= BASE_URL ?>inscripciones/verificar-estado/' + uuid)
                    .done(function(resp) {
                        if (resp.status === true) {
                            $('#selectCarrera').prop('disabled', false);
                            if(resp.carreras && resp.carreras.length > 0) {
                                resp.carreras.forEach(c => {
                                    $('#selectCarrera').append(`<option value="${c.id_carrera}">${c.nombre_carrera}</option>`);
                                });
                                if(resp.carreras.length === 1) $('#selectCarrera').val(resp.carreras[0].id_carrera).trigger('change');
                            } else {
                                $('#contenedor-alerta').html('<div class="alert alert-warning">El alumno no tiene carreras vinculadas.</div>');
                            }
                        } else {
                            $('#contenedor-alerta').html(`<div class="alert alert-danger"><strong>Bloqueo:</strong> ${resp.msj}</div>`);
                        }
                    });

                $('#materiasActuales').load('<?= BASE_URL ?>inscripciones/materias-alumno/' + uuid);
                new bootstrap.Modal(document.getElementById('modalGestion')).show();
            }
        }

        $('#selectCarrera').change(function() {
            const idCarrera = $(this).val();
            $('#id_carrera_insc').val(idCarrera); // Seteamos el ID oculto para el POST
            if(idCarrera) {
                $.post('<?= BASE_URL ?>inscripciones/materias-disponibles', { 
                    id_carrera: idCarrera, 
                    uuid_alumno: $('#uuid_alumno_insc').val() 
                }, function(resp) {
                    $('#listaCheckMaterias').html(resp);
                    $('#divMaterias').show();
                });
            }
        });

        function abrirVincular(uuid) {
            // Lógica de SweetAlert para vincular (Usa $carreras_disponibles de PHP)
            let opciones = <?= json_encode($carreras_disponibles) ?>;
            Swal.fire({
                title: 'Vincular a Carrera',
                html: `
                    <select id="sw-carrera" class="form-select mb-3">
                        <option value="">Seleccione...</option>
                        ${opciones.map(o => `<option value="${o.id_carrera}">${o.nombre_carrera}</option>`).join('')}
                    </select>
                    <input type="number" id="sw-cohorte" class="form-control" value="<?= date('Y') ?>">`,
                showCancelButton: true,
                preConfirm: () => {
                    const c = document.getElementById('sw-carrera').value;
                    const h = document.getElementById('sw-cohorte').value;
                    if(!c || !h) return Swal.showValidationMessage('Complete los campos');
                    return { id_carrera: c, cohorte: h };
                }
            }).then(res => {
                if(res.isConfirmed) {
                    $.post('<?= BASE_URL ?>inscripciones/gestionar', {
                        action: 'vincular_carrera',
                        uuid_alumno: uuid,
                        id_carrera: res.value.id_carrera,
                        cohorte: res.value.cohorte
                    }, function(r) {
                        Swal.fire(r.status === 'success' ? 'Éxito' : 'Error', r.message, r.status);
                        $('#tablaInscripciones').DataTable().ajax.reload(null, false);
                    }, 'json');
                }
            });
        }

        $('#formInscripcion').on('submit', function(e) {
            e.preventDefault();
            if ($('input[name="materias[]"]:checked').length === 0) return Swal.fire('Atención', 'Seleccione materias', 'warning');

            Swal.fire({
                title: '¿Confirmar?',
                text: "Se generará la planilla de notas de cursada.",
                icon: 'question',
                showCancelButton: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('<?= BASE_URL ?>inscripciones/gestionar', $(this).serialize(), function(resp) {
                        Swal.fire(resp.status === 'success' ? 'Éxito' : 'Error', resp.message, resp.status);
                        if(resp.status === 'success') {
                            $('#modalGestion').modal('hide');
                            $('#tablaInscripciones').DataTable().ajax.reload(null, false);
                        }
                    }, 'json');
                }
            });
        });
    </script>
</body>
</html>