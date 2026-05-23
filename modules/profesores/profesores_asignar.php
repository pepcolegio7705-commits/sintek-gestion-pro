<?php
/**
 * LÓGICA DE INICIALIZACIÓN: ASIGNAR ESPACIOS (VERSIÓN BLINDADA UUID)
 */
session_start();

require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; // Necesario para registrar_log_seguridad
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);
$rol = $_SESSION['rol'];

// 1. CAPTURA DEL UUID (Prioridad GET del .htaccess)
$uuid = $_GET['uuid'] ?? null;

// Paracaídas: Si no viene por GET (WAMP/htaccess issue), lo extraemos de la URL
if (!$uuid || strlen($uuid) < 10) {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); 
    $segments = explode('/', trim($request_uri, '/'));
    $uuid = end($segments);
}

// Limpieza de seguridad: Solo caracteres de UUID
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower($uuid));

if (!$uuid) {
    header("Location: " . BASE_URL . "profesores/gestion"); 
    exit;
}

// 2. EL PUENTE: Buscamos los datos del profesor usando el UUID
$stmt_profesor = $pdo->prepare("SELECT id_profesor, legajo, dni, nombre, apellido FROM profesores WHERE uuid_profesor = :uuid LIMIT 1");
$stmt_profesor->execute([':uuid' => $uuid]);
$profesor = $stmt_profesor->fetch(PDO::FETCH_ASSOC);

// Si no existe el UUID o el docente fue eliminado
if (!$profesor) { 
    registrar_log_seguridad($pdo, 'URL_MANIPULADA_ASIGNACION', "Intento de asignar materias a UUID inexistente: $uuid");
    header("Location: " . BASE_URL . "profesores/gestion"); 
    exit; 
}

// Guardamos el ID real para las consultas relacionales internas
$id_profesor_real = $profesor['id_profesor'];

// 3. Traer TODAS las carreras y TODOS los espacios con JOIN
$sql_materias = "SELECT c.id_carrera, c.nombre_carrera, e.id_espacio, e.nombre_espacio, e.anio_cursada 
                 FROM carreras c
                 LEFT JOIN espacios_curriculares e ON c.id_carrera = e.id_carrera
                 WHERE c.activo = 1
                 ORDER BY c.nombre_carrera ASC, e.anio_cursada ASC, e.nombre_espacio ASC";
$todas_las_materias = $pdo->query($sql_materias)->fetchAll(PDO::FETCH_ASSOC);

// 4. Organizar datos para la visualización por pestañas
$carreras = [];
$espacios_por_carrera = [];

foreach ($todas_las_materias as $fila) {
    $id_c = $fila['id_carrera'];
    if (!isset($carreras[$id_c])) {
        $carreras[$id_c] = ['id_carrera' => $id_c, 'nombre_carrera' => $fila['nombre_carrera']];
    }
    if ($fila['id_espacio']) {
        $espacios_por_carrera[$id_c]['nombre'] = $fila['nombre_carrera'];
        $espacios_por_carrera[$id_c]['espacios'][] = [
            'id_espacio' => $fila['id_espacio'],
            'nombre_espacio' => $fila['nombre_espacio'],
            'anio_cursada' => $fila['anio_cursada']
        ];
    }
}

// 5. Cargar asignaciones actuales (Usando el ID real numérico)
$sql_asig = "SELECT id_espacio, horas_catedra FROM profesores_espacios WHERE id_profesor = :id";
$stmt_asig = $pdo->prepare($sql_asig);
$stmt_asig->execute([':id' => $id_profesor_real]);
$asignaciones_raw = $stmt_asig->fetchAll(PDO::FETCH_ASSOC);

$horas_asignadas = [];
$asignaciones_activas = [];
foreach ($asignaciones_raw as $asig) {
    $horas_asignadas[$asig['id_espacio']] = $asig['horas_catedra'];
    $asignaciones_activas[] = $asig['id_espacio'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Espacios - <?= htmlspecialchars($profesor['apellido']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .nav-link.active { font-weight: bold; background-color: #f8f9fa !important; border-bottom: 3px solid #0d6efd !important; }
        .materia-container { transition: all 0.2s; border: 1px solid #dee2e6; margin-bottom: 5px; border-radius: 8px; }
        .materia-container:hover { border-color: #0d6efd; background-color: #f1f8ff; }
        .badge-carrera { background-color: #0d6efd; color: white; padding: 5px 10px; border-radius: 4px 0 0 4px; display: inline-block; }
        .tab-content { min-height: 400px; }
        /* Mejora visual para inputs de horas */
        .input-horas:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
    </style>
</head>

<body class="bg-light">

<?php include '../../vistas/nav.php'; ?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>profesores/gestion">Gestión Docente</a></li>
                    <li class="breadcrumb-item active">Asignar Espacios</li>
                </ol>
            </nav>
            <h2 class="mb-0"><i class="fas fa-chalkboard-teacher text-primary"></i> Asignar Espacios Curriculares</h2>
        </div>
        <a href="<?= BASE_URL ?>profesores/gestion" class="btn btn-outline-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="alert alert-white border shadow-sm d-flex align-items-center" role="alert">
        <div class="flex-shrink-0 me-3">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="fas fa-user-check fa-lg"></i>
            </div>
        </div>
        <div>
            <strong>Docente:</strong> <?= htmlspecialchars($profesor['apellido'] . ', ' . $profesor['nombre']); ?> 
            <span class="badge bg-light text-dark border ms-2">Legajo: <?= $profesor['legajo'] ?></span>
            <br>
            <small class="text-muted">Seleccione los espacios y defina la carga horaria semanal. Los cambios se guardan automáticamente.</small>
        </div>
    </div>
    
    <div class="card shadow-sm border-0 overflow-hidden">
        <div class="card-body p-0">
            <?php if (empty($carreras)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                    <p>No hay carreras registradas o activas en el sistema.</p>
                </div>
            <?php else: ?>
                <div class="row g-0">
                    <div class="col-md-3 border-end bg-white">
                        <div class="nav flex-column nav-pills" id="carreraTab" role="tablist">
                            <?php $first = true; foreach ($carreras as $carrera): $id_c = $carrera['id_carrera']; ?>
                                <button class="nav-link text-start p-3 border-bottom rounded-0 <?php echo $first ? 'active' : ''; ?>" 
                                        data-bs-toggle="pill" data-bs-target="#carrera-<?php echo $id_c; ?>" type="button">
                                    <i class="fas fa-graduation-cap me-2 opacity-75"></i> 
                                    <span class="small"><?php echo htmlspecialchars($carrera['nombre_carrera']); ?></span>
                                </button>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>

                    <div class="col-md-9 bg-white">
                        <div class="tab-content p-4" id="carreraTabContent">
                            <?php $first = true; foreach ($espacios_por_carrera as $id_c_actual => $data): ?>
                                <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="carrera-<?php echo $id_c_actual; ?>">
                                    <div class="d-flex align-items-center mb-4 border-bottom pb-2">
                                        <h5 class="mb-0 text-dark"><?php echo htmlspecialchars($data['nombre']); ?></h5>
                                    </div>
                                    
                                    <div class="row">
                                        <?php 
                                        $current_year = 0;
                                        foreach ($data['espacios'] as $espacio): 
                                            if ($espacio['anio_cursada'] != $current_year):
                                                $current_year = $espacio['anio_cursada'];
                                        ?>
                                            <div class="col-12 mt-3 mb-2">
                                                <div class="d-flex align-items-center">
                                                    <hr class="flex-grow-1">
                                                    <span class="mx-3 badge rounded-pill bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                                        <?php echo $current_year; ?>° AÑO
                                                    </span>
                                                    <hr class="flex-grow-1">
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="col-md-12">
                                            <div class="materia-container p-3 d-flex align-items-center justify-content-between">
                                                <div class="form-check mb-0">
                                                    <input class="form-check-input check-asignacion" type="checkbox" 
                                                           value="<?= $espacio['id_espacio']; ?>" 
                                                           id="espacio_<?= $espacio['id_espacio']; ?>" 
                                                           data-carrera="<?= htmlspecialchars($data['nombre']); ?>"
                                                           <?= in_array($espacio['id_espacio'], $asignaciones_activas) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label fw-bold cursor-pointer" for="espacio_<?= $espacio['id_espacio']; ?>">
                                                        <?= htmlspecialchars($espacio['nombre_espacio']); ?>
                                                    </label>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <div class="input-group input-group-sm" style="width: 130px;">
                                                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-clock text-muted"></i></span>
                                                        <input type="number" class="form-control input-horas border-start-0" 
                                                               placeholder="Hs" id="horas_<?= $espacio['id_espacio']; ?>"
                                                               value="<?= $horas_asignadas[$espacio['id_espacio']] ?? 0; ?>" 
                                                               <?= !in_array($espacio['id_espacio'], $asignaciones_activas) ? 'disabled' : ''; ?>
                                                               min="0" max="40">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4 shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
            <h6 class="mb-0 text-uppercase fw-bold" style="letter-spacing: 1px;">
                <i class="fas fa-list-ul me-2"></i> Resumen de Carga Horaria Consolidada
            </h6>
            <div class="d-flex align-items-center">
                <span class="me-2 small opacity-75">Suma Total:</span>
                <span class="badge bg-primary fs-6" id="total-horas-global">0 hs</span>
            </div>
        </div>
        <div class="card-body bg-white">
            <div id="resumen-asignaciones" class="d-flex flex-wrap gap-2"></div>
            <div id="resumen-vacio" class="text-center py-4 text-muted border border-dashed rounded">
                <i class="fas fa-clipboard-list fa-2x mb-2 opacity-25"></i>
                <p class="small mb-0">No se han seleccionado materias para este docente.</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $(document).ready(function() {
        // 1. CONSTANTES DE IDENTIDAD (Usamos el UUID que viene del PHP)
        const PROFESOR_UUID = "<?= $uuid ?>"; 

        // Función para actualizar el panel inferior de resumen
        function actualizarResumen() {
            const resumen = $('#resumen-asignaciones');
            resumen.empty();
            const seleccionados = $('.check-asignacion:checked');
            let totalHoras = 0;
            
            if (seleccionados.length === 0) {
                $('#resumen-vacio').show();
                $('#total-horas-global').text('Total: 0 hs');
            } else {
                $('#resumen-vacio').hide();
                seleccionados.each(function() {
                    const id = $(this).val();
                    const carrera = $(this).data('carrera');
                    const nombreMateria = $(this).closest('.materia-container').find('label').text().trim();
                    const horas = parseInt($(`#horas_${id}`).val()) || 0;
                    totalHoras += horas;
                    
                    resumen.append(`
                        <div class="m-1 border rounded bg-white d-flex align-items-center shadow-sm" style="font-size: 0.8rem;">
                            <span class="badge-carrera small text-uppercase">${carrera}</span>
                            <span class="px-2 py-1 text-dark">${nombreMateria} <strong>(${horas} hs)</strong></span>
                        </div>
                    `);
                });
                $('#total-horas-global').text(`Total: ${totalHoras} hs`);
            }
        }
                                                
        // 2. FUNCIÓN DE COMUNICACIÓN CON EL SERVIDOR
        function sincronizarServidor(id_espacio, horas, action) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer)
                    toast.addEventListener('mouseleave', Swal.resumeTimer)
                }
            });

            $.ajax({
                // Llamada a la URL amigable configurada en .htaccess
                url: "<?= rtrim(BASE_URL, '/') ?>/profesores/guardarAsignacion/" + PROFESOR_UUID,
                type: 'POST',
                dataType: 'json',
                data: {
                    // No enviamos ID numérico, el PHP lo deducirá del UUID de la URL
                    id_espacio: id_espacio,
                    horas_catedra: horas,
                    action: action
                },
                success: function(r) {
                    if (r.success) {
                        Toast.fire({
                            icon: 'success',
                            title: action === 'asignar' ? 'Materia vinculada' : 'Materia desvinculada'
                        });
                        actualizarResumen();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Operación denegada',
                            text: r.message
                        });
                        // Si hubo error, recargamos para revertir cambios visuales
                        setTimeout(() => location.reload(), 2000);
                    }
                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo sincronizar con el servidor.'
                    });
                }
            });
        }

        // 3. LISTENERS DE EVENTOS
        $('.check-asignacion').on('change', function() {
            const id = $(this).val();
            const inputHoras = $(`#horas_${id}`);
            const is_checked = $(this).is(':checked');
            
            inputHoras.prop('disabled', !is_checked);
            if(!is_checked) inputHoras.val(0);
            
            sincronizarServidor(id, inputHoras.val(), is_checked ? 'asignar' : 'desasignar');
        });

        // Debounce para evitar saturar el servidor mientras se escribe el número de horas
        let debounceTimer;
        $('.input-horas').on('input', function() {
            const id = $(this).attr('id').replace('horas_', '');
            const checkbox = $(`#espacio_${id}`);
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                if (checkbox.is(':checked')) {
                    sincronizarServidor(id, $(this).val(), 'asignar');
                }
            }, 600);
        });

        // Inicializar resumen al cargar
        actualizarResumen();
    });
</script>
</body>
</html>