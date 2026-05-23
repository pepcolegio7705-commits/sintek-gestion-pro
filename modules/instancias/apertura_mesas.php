<?php
    session_start();
    require_once '../../core/conexion.php'; 
    require_once '../../core/seguridad.php'; 

    verificar_permisos(['Administrador', 'Secretaría']);
    $rol = $_SESSION['rol'];

    if (!defined('NOM_INST')) define('NOM_INST', 'Sistema Académico');

    // --- ACCIÓN: Cerrar Mesa ---
    if (isset($_GET['cerrar_id'])) {
        $id_cerrar = (int)$_GET['cerrar_id'];
        try {
            $stmt_close = $pdo->prepare("UPDATE mesas_examenes SET estado = 'Cerrada' WHERE id_mesa = ?");
            $stmt_close->execute([$id_cerrar]);
            header("Location: " . BASE_URL . "instancias/apertura?msj=closed");
            exit;
        } catch (Exception $e) {
            header("Location: " . BASE_URL . "instancias/apertura?msj=error");
            exit;
        }
    }

    // --- ACCIÓN: Guardar Mesa (CON VALIDACIÓN DE UUID) ---
    $mensaje_swal = "";
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnGuardarMesa'])) {
        try {
            // SQL explicito con la función UUID() de MySQL
            $sql = "INSERT INTO mesas_examenes (
                        uuid_mesa, 
                        id_carrera, 
                        id_espacio, 
                        llamado, 
                        ciclo_lectivo, 
                        fecha_inicio_inscripcion, 
                        fecha_fin_inscripcion, 
                        fecha_examen, 
                        hora_examen, 
                        estado
                    ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, 'Abierta')";
            
            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute([
                $_POST['id_carrera'],
                $_POST['id_espacio'],
                $_POST['llamado'],
                $_POST['ciclo_lectivo'],
                $_POST['fecha_inicio_inscripcion'],
                $_POST['fecha_fin_inscripcion'],
                $_POST['fecha_examen'],
                $_POST['hora_examen']
            ]);
            
            if($res) {
                header("Location: " . BASE_URL . "instancias/apertura?msj=success");
                exit;
            } else {
                throw new Exception("Error en la ejecución de la consulta.");
            }
            
        } catch (Exception $e) {
            $mensaje_swal = "Swal.fire('Error', 'No se pudo abrir la mesa: " . addslashes($e->getMessage()) . "', 'error');";
        }
    }

    // Mensajes de Feedback
    if(isset($_GET['msj'])){
        if($_GET['msj'] === 'success') $mensaje_swal = "Swal.fire('¡Éxito!', 'La mesa se ha habilitado y generado su identificador único.', 'success');";
        if($_GET['msj'] === 'closed') $mensaje_swal = "Swal.fire('Mesa Cerrada', 'La mesa ha sido movida al archivo histórico.', 'info');";
        if($_GET['msj'] === 'error') $mensaje_swal = "Swal.fire('Error', 'Ocurrió un problema con la operación.', 'error');";
    }

    // Consultas para la vista
    $carreras = $pdo->query("SELECT * FROM carreras WHERE activo = 1 ORDER BY nombre_carrera ASC")->fetchAll(PDO::FETCH_ASSOC);

    $sql_mesas = "SELECT m.*, c.nombre_carrera, e.nombre_espacio 
                FROM mesas_examenes m
                LEFT JOIN carreras c ON m.id_carrera = c.id_carrera
                LEFT JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                WHERE m.estado = 'Abierta'
                ORDER BY m.fecha_examen ASC";
    $mesas_activas = $pdo->query($sql_mesas)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apertura de Mesas | <?php echo NOM_INST; ?></title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --secondary-color: #858796; }
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .card-header { background-color: #fff; border-bottom: 1px solid #eff2f7; padding: 1.25rem; }
        .form-label { font-weight: 600; color: #4e5e6a; font-size: 0.85rem; text-uppercase; }
        .table thead { background-color: #f8f9fc; }
        .table thead th { text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #4e73df; border: none; }
        .periodo-box { background: #eef2ff; padding: 5px 10px; border-radius: 6px; font-size: 0.85rem; color: #4338ca; border: 1px solid #e0e7ff; }
        .btn-cerrar { transition: all 0.2s; border-radius: 8px; }
        .btn-cerrar:hover { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    </style>
</head>
<body>

<?php include '../../vistas/nav.php'; ?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header d-flex align-items-center">
                    <div class="bg-primary text-white p-2 rounded-3 me-3">
                        <i class="fas fa-calendar-plus fa-lg"></i>
                    </div>
                    <h5 class="mb-0 fw-bold">Configurar Apertura de Mesa</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="<?= BASE_URL ?>instancias/apertura">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Carrera Profesional</label>
                                <select name="id_carrera" id="id_carrera" class="form-select border-2" required>
                                    <option value="">-- Seleccione Carrera --</option>
                                    <?php foreach ($carreras as $c): ?>
                                        <option value="<?php echo $c['id_carrera']; ?>"><?php echo htmlspecialchars($c['nombre_carrera']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Materia / Espacio Curricular</label>
                                <select name="id_espacio" id="id_espacio" class="form-select border-2" required disabled>
                                    <option value="">-- Primero seleccione carrera --</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Turno o Llamado</label>
                                <input type="text" name="llamado" class="form-control" placeholder="Ej: Diciembre - 1er Llamado" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ciclo</label>
                                <input type="number" name="ciclo_lectivo" class="form-control text-center fw-bold" value="<?php echo date('Y'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha del Examen</label>
                                <input type="date" name="fecha_examen" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Horario</label>
                                <input type="time" name="hora_examen" class="form-control" required>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="p-3 border rounded-3 bg-light">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <span class="fw-bold text-muted small"><i class="fas fa-users-cog me-2"></i> PERÍODO DE INSCRIPCIÓN</span>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white text-success fw-bold">DESDE</span>
                                                <input type="date" name="fecha_inicio_inscripcion" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white text-danger fw-bold">HASTA</span>
                                                <input type="date" name="fecha_fin_inscripcion" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-end mt-4">
                            <button type="submit" name="btnGuardarMesa" class="btn btn-primary px-5 py-2 fw-bold shadow">
                                <i class="fas fa-check-circle me-2"></i>Habilitar Mesa de Examen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-stream me-2 text-primary"></i>Mesas con Inscripción Abierta</h5>
            <span class="badge bg-primary rounded-pill"><?php echo count($mesas_activas); ?> activas</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-center">
                            <th class="text-start ps-4">Materia / Carrera</th>
                            <th>Fecha / Hora</th>
                            <th>Inscripción</th>
                            <th>Turno</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($mesas_activas)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay mesas abiertas actualmente.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($mesas_activas as $m): ?>
                        <tr class="text-center">
                            <td class="text-start ps-4">
                                <div class="fw-bold"><?php echo htmlspecialchars($m['nombre_espacio']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($m['nombre_carrera']); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo date('d/m/Y', strtotime($m['fecha_examen'])); ?></div>
                                <div class="small text-muted"><?php echo $m['hora_examen']; ?> hs</div>
                            </td>
                            <td>
                                <div class="periodo-box">
                                    <?php echo date('d/m', strtotime($m['fecha_inicio_inscripcion'])); ?> al 
                                    <?php echo date('d/m', strtotime($m['fecha_fin_inscripcion'])); ?>
                                </div>
                            </td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($m['llamado']); ?></span></td>
                            <td>
                                <button class="btn btn-outline-warning btn-sm fw-bold btn-cerrar" 
                                        onclick="confirmarCerrar(<?php echo $m['id_mesa']; ?>, '<?php echo addslashes($m['nombre_espacio']); ?>')">
                                    <i class="fas fa-lock me-1"></i> CERRAR
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // Ejecutar mensaje SweetAlert si existe
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msj')) {
            // Ejecutamos el SweetAlert que viene preparado desde el PHP
            <?php echo $mensaje_swal; ?>

            // Limpiamos la URL para que al presionar F5 no vuelva a salir
            if (window.history.replaceState) {
                const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState({path: cleanUrl}, '', cleanUrl);
            }
        }

        // Carga dinámica de materias usando la ruta amigable
        $('#id_carrera').change(function() {
            const idCarrera = $(this).val();
            const $materiaSelect = $('#id_espacio');
            
            if (idCarrera) {
                // Estado visual de carga
                $materiaSelect.html('<option>Cargando materias...</option>').prop('disabled', true);

                // Petición a la ruta amigable definida: instancias/get-materias
                $.post('<?= BASE_URL ?>instancias/get-materias', { id_carrera: idCarrera }, function(data) {
                    let options = '<option value="">-- Seleccione Materia --</option>';
                    
                    // Recorremos el JSON recibido
                    data.forEach(m => {
                        options += `<option value="${m.id_espacio}">${m.nombre_espacio}</option>`;
                    });

                    $materiaSelect.html(options).prop('disabled', false);
                }).fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudieron cargar las materias correspondientes.'
                    });
                    $materiaSelect.html('<option value="">Error al cargar</option>');
                });
            } else {
                $materiaSelect.html('<option value="">-- Primero seleccione carrera --</option>').prop('disabled', true);
            }
        });
    });

    function confirmarCerrar(id, nombre) {
        Swal.fire({
            title: '¿Cerrar Mesa?',
            html: `La mesa de <b>${nombre}</b> dejará de recibir inscripciones online.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `<?= BASE_URL ?>instancias/apertura?cerrar_id=${id}`;
            }
        });
    }
</script>

<?php include '../../vistas/footer.php'; ?>
</body>
</html>