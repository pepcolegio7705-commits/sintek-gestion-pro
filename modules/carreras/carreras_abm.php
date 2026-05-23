<?php
/**
 * MÓDULO: GESTIÓN DE CARRERAS (VERSIÓN SEGURA UUID)
 * Ubicación: /modules/carreras/carreras_abm.php
 */

session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$rol = $_SESSION['rol'];
$mensaje = [];
$carrera_editar = null;

// --- 1. CAPTURA SEGURA DEL UUID (PHP 8.1+ COMPATIBLE) ---
$uuid_param = $_GET['uuid'] ?? '';
$uuid_get = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid_param));

// Rutas de archivos
$upload_dir_fisico = '../../uploads/carreras/'; 
$db_path_prefix = 'uploads/carreras/'; 

if (!is_dir($upload_dir_fisico)) { 
    mkdir($upload_dir_fisico, 0777, true); 
}

// Logo para reportes
$logoPath = '../../img/logo.png'; 
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/' . pathinfo($logoPath, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($logoPath));
}

// --- 2. PROCESAMIENTO CRUD (POST) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uuid_post = $_POST['uuid_carrera'] ?? ''; // Identificador seguro
    $nombre_carrera = strtoupper(trim($_POST['nombre_carrera']));
    $duracion_anios = (int)$_POST['duracion_anios'];
    $carga_horaria = trim($_POST['carga_horaria']);
    
    $plan_estudio_db = $_POST['plan_actual'] ?? '';
    $resolucion_db = $_POST['resolucion_actual'] ?? '';
    $error_archivo = false;

    if (empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => 'Archivos demasiado grandes.'];
        $error_archivo = true;
    }

    $archivos = [
        'plan_estudio' => ['pref' => 'plan_', 'db' => &$plan_estudio_db],
        'resolucion' => ['pref' => 'res_', 'db' => &$resolucion_db]
    ];

    foreach ($archivos as $name => &$cfg) {
        if (!$error_archivo && isset($_FILES[$name]) && $_FILES[$name]['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES[$name]['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = $cfg['pref'] . time() . "_" . uniqid() . ".pdf";
                if (move_uploaded_file($_FILES[$name]['tmp_name'], $upload_dir_fisico . $filename)) {
                    if (!empty($cfg['db']) && file_exists('../../' . $cfg['db'])) { @unlink('../../' . $cfg['db']); }
                    $cfg['db'] = $db_path_prefix . $filename;
                }
            } else {
                $mensaje = ['icon' => 'warning', 'title' => 'Formato', 'text' => 'Solo PDF.'];
                $error_archivo = true;
            }
        }
    }

    if (!$error_archivo) {
        try {
            if (empty($uuid_post)) {
                $new_uuid = bin2hex(random_bytes(16));
                $new_uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($new_uuid, 4));
                $stmt = $pdo->prepare("INSERT INTO carreras (uuid_carrera, nombre_carrera, duracion_anios, carga_horaria, plan_estudio, resolucion, activo) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$new_uuid, $nombre_carrera, $duracion_anios, $carga_horaria, $plan_estudio_db, $resolucion_db]);
                $mensaje = ['icon' => 'success', 'title' => '¡Éxito!', 'text' => 'Carrera registrada.'];
            } else {
                $stmt = $pdo->prepare("UPDATE carreras SET nombre_carrera=?, duracion_anios=?, carga_horaria=?, plan_estudio=?, resolucion=? WHERE uuid_carrera=?");
                $stmt->execute([$nombre_carrera, $duracion_anios, $carga_horaria, $plan_estudio_db, $resolucion_db, $uuid_post]);
                $mensaje = ['icon' => 'success', 'title' => 'Actualizado', 'text' => 'Información actualizada.'];
            }
        } catch (PDOException $e) {
            $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getCode() == 23000 ? 'La carrera ya existe.' : $e->getMessage()];
        }
    }
}

// --- 3. ELIMINACIÓN LÓGICA (UUID) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($uuid_get)) {
    // 1. Buscamos el ID interno
    $stmt_id = $pdo->prepare("SELECT id_carrera, nombre_carrera FROM carreras WHERE uuid_carrera = ?");
    $stmt_id->execute([$uuid_get]);
    $c_del = $stmt_id->fetch(PDO::FETCH_ASSOC);

    if ($c_del) {
        $id_interno = $c_del['id_carrera'];

        // 2. Validaciones de integridad
        $cant_m = $pdo->prepare("SELECT COUNT(*) FROM espacios_curriculares WHERE id_carrera = ? AND activo = 1"); 
        $cant_m->execute([$id_interno]);
        $total_m = $cant_m->fetchColumn();

        $cant_a = $pdo->prepare("SELECT COUNT(*) FROM alumnos_carreras WHERE id_carreras = ?"); 
        $cant_a->execute([$id_interno]);
        $total_a = $cant_a->fetchColumn();

        if ($total_m > 0 || $total_a > 0) {
            $mensaje = [
                'icon' => 'error', 
                'title' => 'No se puede eliminar', 
                'text' => "La carrera '{$c_del['nombre_carrera']}' tiene {$total_m} materias y {$total_a} alumnos vinculados."
            ];
        } else {
            // 3. Procedemos a desactivar
            $pdo->prepare("UPDATE carreras SET activo = 0 WHERE uuid_carrera = ?")->execute([$uuid_get]);
            $mensaje = [
                'icon' => 'success', 
                'title' => 'Eliminado', 
                'text' => 'La carrera ha sido desactivada correctamente.'
            ];
        }
    }
}

// --- 4. DATOS PARA EDICIÓN (SOLO SI LA ACCIÓN ES EDIT) ---
if (!empty($uuid_get) && isset($_GET['action']) && $_GET['action'] == 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM carreras WHERE uuid_carrera = ? AND activo = 1");
    $stmt->execute([$uuid_get]);
    $carrera_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si intentan editar algo que no existe o no está activo
    if (!$carrera_editar) {
        header("Location: " . BASE_URL . "carreras/gestion");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Carreras | Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 12px; border: none; }
        .btn-action { border-radius: 8px; padding: 10px 20px; font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>
    <div class="container-fluid px-4 mt-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 <?= $carrera_editar ? 'bg-warning' : 'bg-primary text-white' ?>">
                <h5 class="m-0 fw-bold">
                    <i class="fas <?= $carrera_editar ? 'fa-pen-to-square' : 'fa-graduation-cap' ?> me-2"></i>
                    <?= $carrera_editar ? 'Editando: ' . htmlspecialchars($carrera_editar['nombre_carrera']) : 'Nueva Carrera Universitaria' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <form id="formCarrera" action="<?= BASE_URL; ?>carreras/gestion" method="POST" enctype="multipart/form-data" class="row g-3">                    
                    <input type="hidden" name="uuid_carrera" value="<?= $carrera_editar['uuid_carrera'] ?? '' ?>">
                    <input type="hidden" name="plan_actual" value="<?= $carrera_editar['plan_estudio'] ?? '' ?>">
                    <input type="hidden" name="resolucion_actual" value="<?= $carrera_editar['resolucion'] ?? '' ?>">

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nombre Oficial de la Carrera</label>
                        <input type="text" name="nombre_carrera" class="form-control" required value="<?= $carrera_editar['nombre_carrera'] ?? '' ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Duración</label>
                        <div class="input-group">
                            <input type="number" name="duracion_anios" class="form-control" required min="1" max="8" value="<?= $carrera_editar['duracion_anios'] ?? '' ?>">
                            <span class="input-group-text">Años</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Carga Horaria Total</label>
                        <input type="text" name="carga_horaria" class="form-control" value="<?= $carrera_editar['carga_horaria'] ?? '' ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Plan de Estudio (PDF)</label>
                        <input type="file" name="plan_estudio" class="form-control" accept=".pdf">
                        <?php if(!empty($carrera_editar['plan_estudio'])): ?>
                            <small class="text-success"><a href="<?= BASE_URL . $carrera_editar['plan_estudio'] ?>" target="_blank"><i class="fas fa-file-pdf"></i> Ver PDF Actual</a></small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Resolución Ministerial (PDF)</label>
                        <input type="file" name="resolucion" class="form-control" accept=".pdf">
                        <?php if(!empty($carrera_editar['resolucion'])): ?>
                            <small class="text-success"><a href="<?= BASE_URL . $carrera_editar['resolucion'] ?>" target="_blank"><i class="fas fa-file-contract"></i> Ver Resolución</a></small>
                        <?php endif; ?>
                    </div>

                    <div class="col-12 mt-4 pt-3 border-top d-flex gap-2">
                        <button type="submit" class="btn btn-<?= $carrera_editar ? 'warning' : 'primary' ?> btn-action">
                            <i class="fas fa-save me-2"></i> <?= $carrera_editar ? 'Guardar Cambios' : 'Registrar Carrera' ?>
                        </button>
                        <?php if ($carrera_editar): ?>
                            <a href="<?= BASE_URL; ?>carreras/gestion" class="btn btn-outline-secondary btn-action">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">Carreras Activas</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaCarreras" class="table table-hover w-100 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th>Nombre de Carrera</th>
                                <th>Duración</th>
                                <th width="15%" class="text-center">Operaciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAlumnos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="tituloModalAlumnos">Inscriptos</h5>
                    <div id="contadorAlumnos" class="ms-auto badge bg-primary fs-6"></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table id="tablaAlumnosCarrera" class="table table-striped w-100">
                        <thead>
                            <tr><th>DNI</th><th>Alumno</th><th>Estado</th><th>Fecha Insc.</th></tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        // 1. INICIALIZACIÓN DE DATATABLES (SERVER-SIDE)
        const tablaPrincipal = $('#tablaCarreras').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "<?= BASE_URL; ?>carreras/data_server",
                "type": "POST"
            },
            "columns": [
                { "data": 0 }, // ID
                { "data": 1, "className": "fw-bold" }, // Nombre Carrera
                { "data": 2 }, // Duración
                { "data": 3, "orderable": false, "className": "text-center" } // Acciones (UUID)
            ],
            "language": {
                "url": "<?= BASE_URL; ?>datatables/Spanish.json"
            },
            "order": [[0, "desc"]] // Ordenar por ID descendente por defecto
        });

        // 2. INTERCEPTAR ENVÍO DEL FORMULARIO (CONFIRMACIÓN)
        $('#formCarrera').on('submit', function(e) {
            e.preventDefault(); 
            
            const form = this;
            const uuidVal = $('input[name="uuid_carrera"]').val();
            const isEdit = uuidVal !== '' && uuidVal !== undefined; 
            
            if (isEdit) {
                Swal.fire({
                    title: '¿Confirmar actualización?',
                    text: "Se modificarán los datos de la carrera en el sistema.",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, actualizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit(); 
                    }
                });
            } else {
                // Para altas nuevas guardamos directo
                form.submit();
            }
        });

        <?php if (!empty($mensaje)): ?>
            Swal.fire({
                icon: '<?= $mensaje['icon'] ?>',
                title: '<?= $mensaje['title'] ?>',
                // Usamos comillas dobles y addslashes para que nombres como LICENCIATURA no rompan el JS
                text: "<?= addslashes($mensaje['text']) ?>", 
                timer: <?= $mensaje['icon'] === 'success' ? '2500' : 'null' ?>,
                showConfirmButton: <?= $mensaje['icon'] === 'success' ? 'false' : 'true' ?>,
                confirmButtonText: 'Entendido'
            }).then(() => {
                // REDIRECCIÓN LIMPIA: Quita el ?action=delete&uuid=... y recarga la tabla desde cero
                window.location.href = '<?= BASE_URL; ?>carreras/gestion';
            });
        <?php endif; ?>
    });

    // 4. FUNCIÓN VER ALUMNOS (MODAL)
    function verAlumnos(uuid, nombreCarrera) {
        $('#tituloModalAlumnos').text('Carrera: ' + nombreCarrera);
        $('#modalAlumnos').modal('show');
        const logo = "<?= $logoBase64 ?>";
        
        if ($.fn.DataTable.isDataTable('#tablaAlumnosCarrera')) { 
            $('#tablaAlumnosCarrera').DataTable().destroy(); 
        }

        $('#tablaAlumnosCarrera').DataTable({
            "ajax": { 
                "url": "<?= BASE_URL; ?>carreras/get_alumnos", 
                "data": { uuid: uuid } 
            },
            "language": { "url": "<?= BASE_URL; ?>datatables/Spanish.json" },
            "dom": 'Bfrtip',
            "buttons": [{
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> Reporte PDF',
                className: 'btn btn-danger btn-sm mb-3',
                customize: function (doc) {
                    doc.content.splice(0, 1, 
                        { image: logo, width: 60, alignment: 'center', margin: [0, 0, 0, 10] },
                        { text: 'INSTITUTO RAÍCES DEL SABER', fontSize: 16, bold: true, alignment: 'center' },
                        { text: 'Listado de Alumnos - ' + nombreCarrera, alignment: 'center', margin: [0, 0, 0, 20] }
                    );
                }
            }],
            "initComplete": function(s, json) { 
                $('#contadorAlumnos').text('Total: ' + (json.data ? json.data.length : 0)); 
            }
        });
    }

    // 5. FUNCIÓN ELIMINAR (DESACTIVAR)
   function confirmarEliminacion(uuid, nombre) {
        Swal.fire({
            title: "¿Desactivar Carrera?",
            text: "Se verificará que '" + nombre + "' no tenga vínculos activos.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonText: "Cancelar",
            confirmButtonText: "Sí, desactivar"
        }).then((result) => {
            if (result.isConfirmed) { 
                // Concatenamos el uuid de JS a la URL de redirección
                window.location.href = "<?= BASE_URL; ?>carreras/gestion?action=delete&uuid=" + uuid; 
            }
        });
    }
    
</script>
</body>
</html>