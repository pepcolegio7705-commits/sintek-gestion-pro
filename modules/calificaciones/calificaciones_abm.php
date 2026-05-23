<?php
session_start();
// Rutas relativas corregidas según tu estructura de carpetas
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol'];
$mensaje = []; 

// Función de negocio unificada
function determinarCondicion($nota) {
    if ($nota >= 7.00) return 'APROBADO';
    if ($nota >= 4.00) return 'REGULAR';
    return 'DESAPROBADO';
}

// PROCESAMIENTO DEL POST (Solo para UPDATE, el INSERT va por AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_calificacion']) && (int)$_POST['id_calificacion'] > 0) {
    $id_calificacion = (int)$_POST['id_calificacion'];
    $nota_final = round(floatval($_POST['nota_final']), 2);
    
    try {
        $sql = "UPDATE calificaciones SET 
                nota_final = :nota, 
                condicion = :condicion,
                libro = :libro,
                folio = :folio,
                observacion = :obs
                WHERE id_calificacion = :idcalif";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nota'      => $nota_final,
            ':condicion' => determinarCondicion($nota_final),
            ':libro'     => trim($_POST['libro'] ?? ''),
            ':folio'     => trim($_POST['folio'] ?? ''),
            ':obs'       => trim($_POST['observacion'] ?? ''),
            ':idcalif'   => $id_calificacion
        ]);
        $mensaje = ['icon' => 'success', 'title' => '¡Nota Actualizada!', 'text' => 'Los cambios se guardaron correctamente.'];
    } catch (PDOException $e) {
        $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
    }
}

// LÓGICA DE EDICIÓN (Búsqueda por UUID para seguridad en la URL)
$calificacion_editar = null;
$nombre_alumno_editar = '';
$dni_alumno_editar = '';

if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['uuid'])) {
    $uuid = $_GET['uuid'];
    $sql = "SELECT c.*, a.dni, a.nombre, a.apellido 
            FROM calificaciones c
            JOIN alumnos a ON c.id_alumno = a.id_alumno
            WHERE c.uuid_calificacion = :uuid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uuid' => $uuid]);
    $calificacion_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($calificacion_editar) {
        $dni_alumno_editar = $calificacion_editar['dni'];
        $nombre_alumno_editar = htmlspecialchars($calificacion_editar['apellido'] . ', ' . $calificacion_editar['nombre']);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificación Individual | Gestión Académica</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f9; }
        .header-custom { background: linear-gradient(135deg, #003366, #00509d); color: white; padding: 1.5rem; border-radius: 15px 15px 0 0; }
        .header-edit { background: linear-gradient(135deg, #1d976c, #93f9b9); }
        .main-card { border: none; border-radius: 15px; }
        .form-control:focus { border-color: #003366; box-shadow: none; }
        .btn-action { padding: 0.8rem 2rem; border-radius: 10px; font-weight: bold; }
    </style>
</head>
<body>
<?php include '../../vistas/nav.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            <div class="card main-card shadow-lg mb-4">
                <div class="header-custom <?= $calificacion_editar ? 'header-edit' : ''; ?>">
                    <h5 class="mb-0">
                        <i class="fas <?= $calificacion_editar ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i>
                        <?= $calificacion_editar ? 'Modificar Acta - UUID: ' . substr($calificacion_editar['uuid_calificacion'],0,8) : 'Nueva Calificación Individual'; ?>
                    </h5>
                </div>
                <div class="card-body p-4 p-md-5 bg-white">
                    <form method="POST" action="" id="formCalificacion">
                        <input type="hidden" name="id_calificacion" id="id_calificacion" value="<?= $calificacion_editar ? $calificacion_editar['id_calificacion'] : 0; ?>">
                        <input type="hidden" name="id_alumno_oculto" id="id_alumno_oculto" value="<?= $calificacion_editar ? $calificacion_editar['id_alumno'] : ''; ?>">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">DNI del Alumno</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="dni_alumno" name="dni_alumno" required placeholder="Sin puntos" 
                                        value="<?= $dni_alumno_editar; ?>" <?= $calificacion_editar ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-dark w-100 btn-action" id="btnBuscarAlumno" <?= $calificacion_editar ? 'disabled' : ''; ?>>
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Estudiante</label>
                                <input type="text" class="form-control bg-light fw-bold text-primary" id="nombre_alumno_display" readonly 
                                    value="<?= $nombre_alumno_editar; ?>">
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Carrera</label>
                                <select id="id_carrera" name="id_carrera" class="form-select" required disabled>
                                    <option value="" disabled selected>-- Seleccione --</option>
                                    <?php if ($calificacion_editar): ?>
                                        <option value="<?= $calificacion_editar['id_carrera']; ?>" selected>Carrera actual</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Materia</label>
                                <select id="id_espacio" name="id_espacio" class="form-select" required disabled>
                                    <option value="" disabled selected>-- Seleccione --</option>
                                    <?php if ($calificacion_editar): ?>
                                        <option value="<?= $calificacion_editar['id_espacio']; ?>" selected>Materia actual</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-danger">Nota Final</label>
                                <input type="number" step="0.01" min="0" max="10" class="form-control fw-bold border-danger" id="nota_final" name="nota_final" required
                                    value="<?= $calificacion_editar ? $calificacion_editar['nota_final'] : ''; ?>" <?= $calificacion_editar ? '' : 'disabled'; ?>>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Libro</label>
                                <input type="text" class="form-control" id="libro" name="libro" value="<?= $calificacion_editar ? $calificacion_editar['libro'] : ''; ?>" <?= $calificacion_editar ? '' : 'disabled'; ?>>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-bold">Folio</label>
                                <input type="text" class="form-control" id="folio" name="folio" value="<?= $calificacion_editar ? $calificacion_editar['folio'] : ''; ?>" <?= $calificacion_editar ? '' : 'disabled'; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Observación</label>
                                <input type="text" class="form-control" id="observacion" name="observacion" value="<?= $calificacion_editar ? $calificacion_editar['observacion'] : ''; ?>" <?= $calificacion_editar ? '' : 'disabled'; ?>>
                            </div>
                        </div>

                        <div class="mt-5 d-flex gap-2">
                            <button type="submit" class="btn btn-<?= $calificacion_editar ? 'success' : 'primary'; ?> btn-action" id="btnSubmitForm" <?= $calificacion_editar ? '' : 'disabled'; ?>>
                                <i class="fas fa-save me-2"></i> <?= $calificacion_editar ? 'Actualizar Nota' : 'Registrar Nota'; ?>
                            </button>
                            <?php if ($calificacion_editar): ?>
                                <a href="gestion_calificaciones.php" class="btn btn-secondary btn-action">Volver</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        var isEditMode = $('#id_calificacion').val() > 0;

        function toggleForm(enable) {
            $('#id_carrera, #id_espacio, #nota_final, #libro, #folio, #observacion, #btnSubmitForm').prop('disabled', !enable);
        }

        // Búsqueda AJAX de Alumno
        $('#btnBuscarAlumno').click(function() {
            var dni = $('#dni_alumno').val().trim();
            if (dni === '') return Swal.fire('DNI Requerido', 'Ingrese el documento.', 'warning');
            
            toggleForm(false);
            $('#nombre_alumno_display').val('Buscando...');

            $.post('ajax_calificaciones.php', { action: 'buscar_alumno', dni: dni }, function(response) {
                if (response.success) {
                    $('#id_alumno_oculto').val(response.alumno.id_alumno);
                    $('#nombre_alumno_display').val(response.alumno.nombre_completo);
                    $('#id_carrera').html('<option value="" disabled selected>Seleccione Carrera</option>').prop('disabled', false);
                    $.each(response.carreras, function(i, c) {
                        $('#id_carrera').append($('<option>', { value: c.id_carrera, text: c.nombre_carrera }));
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                    $('#nombre_alumno_display').val('');
                }
            }, 'json');
        });

        // Carga AJAX de Espacios
        $('#id_carrera').change(function() {
            $.post('ajax_calificaciones.php', { 
                action: 'cargar_espacios', 
                id_carrera: $(this).val(), 
                id_alumno: $('#id_alumno_oculto').val() 
            }, function(response) {
                $('#id_espacio').html('<option value="" disabled selected>Seleccione Espacio</option>').prop('disabled', false);
                if (response.success) {
                    $.each(response.espacios, function(i, esp) {
                        $('#id_espacio').append($('<option>', { 
                            value: esp.id_espacio, text: esp.nombre_espacio, 'data-aprobado': esp.ya_aprobado 
                        }));
                    });
                }
            }, 'json');
        });

        // Validación de aprobación y Estado de Planilla
        $('#id_espacio').change(function() {
            var yaAprobado = $(this).find('option:selected').data('aprobado');
            if (!isEditMode && yaAprobado > 0) {
                Swal.fire('Restricción', 'El alumno ya aprobó esta materia.', 'info');
                toggleForm(false);
                $('#id_carrera, #id_espacio').prop('disabled', false);
            } else {
                // Verificar si la planilla está abierta
                $.post('ajax_calificaciones.php', { 
                    action: 'verificar_planilla', 
                    id_espacio: $(this).val() 
                }, function(r) {
                    if(!r.abierta && '<?= $rol ?>' !== 'Administrador') {
                        Swal.fire('Planilla Cerrada', 'Solo secretaría/administración puede cargar notas ahora.', 'warning');
                        $('#btnSubmitForm').prop('disabled', true);
                    } else {
                        $('#nota_final, #libro, #folio, #observacion, #btnSubmitForm').prop('disabled', false);
                    }
                }, 'json');
            }
        });

        // Envío por AJAX si es nuevo registro
        $('#formCalificacion').submit(function(e) {
            if (isEditMode) return; // Si es edición, que procese el POST de PHP
            e.preventDefault(); 
            
            $.post('ajax_calificaciones.php', $(this).serialize() + '&action=registrar_calificacion', function(r) {
                if (r.success) {
                    Swal.fire('¡Éxito!', r.message, 'success').then(() => { window.location.href = 'gestion_calificaciones.php'; });
                } else {
                    Swal.fire('Error', r.message, 'error');
                }
            }, 'json');
        });

        <?php if (!empty($mensaje)): ?>
            Swal.fire({ icon: '<?= $mensaje['icon']; ?>', title: '<?= $mensaje['title']; ?>', text: '<?= $mensaje['text']; ?>' });
        <?php endif; ?>
    });
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>