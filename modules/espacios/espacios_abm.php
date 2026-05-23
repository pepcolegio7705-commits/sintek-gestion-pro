<?php
session_start();
// Subimos dos niveles para llegar a core
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol'];
$mensaje = [];
$espacio_editar = null;

$uuid_get = $_GET['uuid'] ?? '';
$uuid_get = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid_get));

// 1. Obtener la lista de Carreras
$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras where activo = 1 ORDER BY nombre_carrera")->fetchAll(PDO::FETCH_ASSOC);

// 2. FUNCIÓN CRUD
// --- 2. PROCESAMIENTO CRUD (POST) ---
// --- 2. CONTROLADOR CRUD (POST y DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capturamos el UUID del post (si viene vacío, es un Alta)
    $uuid_post = $_POST['uuid_espacio'] ?? ''; 
    $codigo = strtoupper(trim($_POST['codigo'])); 
    $nombre_espacio = trim($_POST['nombre_espacio']);
    $id_carrera = (int)$_POST['id_carrera'];
    $anio_cursada = (int)$_POST['anio_cursada'];
    $tipo_cursada = $_POST['tipo_cursada']; 
    $carga_horaria = (int)$_POST['carga_horaria'];

    try {
        if (empty($uuid_post)) { 
            // --- LÓGICA DE ALTA (NUEVO REGISTRO) ---
            $new_uuid = bin2hex(random_bytes(16));
            $new_uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($new_uuid, 4));

            $sql = "INSERT INTO espacios_curriculares (uuid_espacio, codigo, nombre_espacio, id_carrera, anio_cursada, tipo_cursada, carga_horaria, activo) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_uuid, $codigo, $nombre_espacio, $id_carrera, $anio_cursada, $tipo_cursada, $carga_horaria]);
            
            $mensaje = ['icon' => 'success', 'title' => '¡Creado!', 'text' => 'El espacio curricular se registró correctamente.'];
        } else { 
            // --- LÓGICA DE MODIFICACIÓN (EDICIÓN) ---
            $sql = "UPDATE espacios_curriculares 
                    SET codigo = ?, nombre_espacio = ?, id_carrera = ?, anio_cursada = ?, tipo_cursada = ?, carga_horaria = ? 
                    WHERE uuid_espacio = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo, $nombre_espacio, $id_carrera, $anio_cursada, $tipo_cursada, $carga_horaria, $uuid_post]);
            
            $mensaje = ['icon' => 'success', 'title' => 'Actualizado', 'text' => 'Los datos han sido modificados con éxito.'];
        }
    } catch (PDOException $e) {
        $error_text = (strpos($e->getMessage(), 'Duplicate entry') !== false) 
            ? 'El código o nombre ya existe para esta carrera.' 
            : 'Error en la base de datos: ' . $e->getMessage();
            
        $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $error_text];
    }

} elseif (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['uuid'])) {
    
    // 1. Capturamos y saneamos el UUID de la URL
    $uuid_del = preg_replace('/[^a-z0-9-]/', '', $_GET['uuid']);

    try {
        // 2. Buscamos el id_espacio numérico que corresponde a ese UUID
        $stmt_info = $pdo->prepare("SELECT id_espacio, nombre_espacio FROM espacios_curriculares WHERE uuid_espacio = ?");
        $stmt_info->execute([$uuid_del]);
        $info_espacio = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($info_espacio) {
            $id_interno = $info_espacio['id_espacio'];
            $nombre_materia = $info_espacio['nombre_espacio'];

            // 3. Chequeamos si existen alumnos inscriptos usando el ID interno
            $check_vinculos = $pdo->prepare("SELECT COUNT(*) FROM inscripciones_espacios WHERE id_espacio = ?");
            $check_vinculos->execute([$id_interno]);
            $cantidad_inscriptos = $check_vinculos->fetchColumn();

            if ($cantidad_inscriptos > 0) {
                // Bloqueamos la desactivación por integridad referencial
                $mensaje = [
                    'icon'  => 'error', 
                    'title' => 'Acción Bloqueada', 
                    'text'  => "No se puede desactivar '$nombre_materia' porque existen $cantidad_inscriptos alumnos inscriptos actualmente."
                ];
            } else {
                // Procedemos a la desactivación lógica
                $stmt = $pdo->prepare("UPDATE espacios_curriculares SET activo = 0 WHERE id_espacio = ?");
                $stmt->execute([$id_interno]);
                
                $mensaje = [
                    'icon'  => 'success', 
                    'title' => 'Desactivado', 
                    'text'  => 'El espacio curricular ha sido desactivado con éxito.'
                ];
            }
        } else {
            $mensaje = ['icon' => 'warning', 'title' => 'Error', 'text' => 'No se encontró el registro solicitado.'];
        }
    } catch (PDOException $e) {
        $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
    }
}

// 3. Datos para edición
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['uuid'])) {
    $uuid_editar = preg_replace('/[^a-z0-9-]/', '', $_GET['uuid']); // Limpieza de seguridad
    
    $stmt = $pdo->prepare("SELECT * FROM espacios_curriculares WHERE uuid_espacio = ? AND activo = 1");
    $stmt->execute([$uuid_editar]);
    $espacio_editar = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si no encuentra la materia con ese UUID, podrías redirigir o mostrar error
    if (!$espacio_editar) {
        $mensaje = ['icon' => 'error', 'title' => 'No encontrado', 'text' => 'El espacio curricular solicitado no existe.'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Espacios | Sistema</title>
   <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 10px; }
        .form-label { font-weight: 600; color: #495057; font-size: 0.9rem; }
        .btn-custom { border-radius: 8px; font-weight: 500; padding: 8px 20px; }
        .table thead { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>
    
    <div class="container-fluid mt-4 px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i>Espacios Curriculares</h2>
        </div>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 fw-bold text-<?= $espacio_editar ? 'success' : 'primary' ?>">
                    <i class="fas fa-<?= $espacio_editar ? 'pen-to-square' : 'plus-circle' ?> me-1"></i>
                    <?= $espacio_editar ? 'Modificar Espacio' : 'Registrar Nuevo Espacio' ?>
                </h6>
            </div>
            <div class="card-body p-4">
               <form method="POST" action="<?= BASE_URL; ?>materias/gestion" id="formEspacios">
                    <input type="hidden" name="uuid_espacio" value="<?= $espacio_editar['uuid_espacio'] ?? ''; ?>">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Código</label>
                            <input type="text" name="codigo" class="form-control" placeholder="Ej: MAT101" required 
                                value="<?= $espacio_editar ? htmlspecialchars($espacio_editar['codigo']) : ''; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Nombre de la Materia</label>
                            <input type="text" name="nombre_espacio" class="form-control" required
                                value="<?= $espacio_editar ? htmlspecialchars($espacio_editar['nombre_espacio']) : ''; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Carrera Perteneciente</label>
                            <select name="id_carrera" class="form-select form-control" required>
                                <option value="" disabled selected>Seleccione carrera...</option>
                                <?php foreach ($carreras as $c): ?>
                                    <option value="<?= $c['id_carrera']; ?>" <?= ($espacio_editar && $espacio_editar['id_carrera'] == $c['id_carrera']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($c['nombre_carrera']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Año de Cursada</label>
                            <select name="anio_cursada" class="form-control" required>
                                <?php for($i=1; $i<=6; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($espacio_editar && $espacio_editar['anio_cursada'] == $i) ? 'selected' : ''; ?>><?= $i ?>° Año</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Régimen</label>
                            <select name="tipo_cursada" class="form-control" required>
                                <option value="1° Cuatrimestre" <?= ($espacio_editar && $espacio_editar['tipo_cursada'] == '1° Cuatrimestre') ? 'selected' : ''; ?>>1° Cuatrimestre</option>
                                <option value="2° Cuatrimestre" <?= ($espacio_editar && $espacio_editar['tipo_cursada'] == '2° Cuatrimestre') ? 'selected' : ''; ?>>2° Cuatrimestre</option>
                                <option value="Anual" <?= ($espacio_editar && $espacio_editar['tipo_cursada'] == 'Anual') ? 'selected' : ''; ?>>Anual</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Carga Horaria (Horas)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="number" name="carga_horaria" class="form-control" required
                                    value="<?= $espacio_editar ? $espacio_editar['carga_horaria'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-<?= $espacio_editar ? 'success' : 'primary' ?> btn-custom">
                            <i class="fas fa-save me-1"></i> <?= $espacio_editar ? 'Actualizar Registro' : 'Guardar Espacio' ?>
                        </button>
                        <?php if ($espacio_editar): ?>
                            <a href="<?= BASE_URL; ?>materias/gestion" class="btn btn-light btn-custom">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <div class="row align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label text-primary"><i class="fas fa-filter me-1"></i> Filtrar Vista por Carrera:</label>
                    <select id="filtroCarrera" class="form-select border-primary">
                        <option value="">-- Todas las Carreras --</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= $c['id_carrera']; ?>"><?= htmlspecialchars($c['nombre_carrera']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tablaEspacios" class="table table-hover align-middle" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Materia</th>
                            <th>Carrera</th>
                            <th>Año</th>
                            <th>Régimen</th>
                            <th>Hs.</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>
| 
    <script>
        const BASE_URL = "<?php echo BASE_URL; ?>";
    </script>
    <script>
        $(document).ready(function() {
        
        const tabla = $('#tablaEspacios').DataTable({
            "processing": true,
            "serverSide": true, 
            "ajax": { 
                "url": "<?= BASE_URL; ?>materias/data_server",
                "type": "POST", // Asegúrate de que coincida con lo que espera tu PHP
                "data": function(d) {
                    d.id_carrera_filtro = $('#filtroCarrera').val();
                }
            },
            "columns": [
                { "data": 0 }, 
                { "data": 1, "className": "fw-bold" }, 
                { "data": 2 }, 
                { "data": 3, "className": "text-center" }, 
                { "data": 4 }, 
                { "data": 5, "className": "text-center" }, 
                { "data": 6, "orderable": false, "className": "text-center" }
            ],
            // CORRECCIÓN: Ruta absoluta para el idioma
            "language": { "url": "<?= BASE_URL; ?>datatables/Spanish.json" },
            "drawCallback": function() {
                $('.btn-delete').on('click', function(e) {
                    e.preventDefault();
                    const id = $(this).data('id');
                    const nombre = $(this).data('nombre');
                    confirmarEliminacion(id, nombre);
                });
            }
        });

        $('#filtroCarrera').on('change', () => tabla.ajax.reload());

            // 3. MENSAJES DE RESPUESTA (SWEETALERT PHP)
            <?php if (!empty($mensaje)): ?>
                Swal.fire({
                    icon: '<?= $mensaje['icon'] ?>',
                    title: '<?= $mensaje['title'] ?>',
                    // Comillas dobles y addslashes para que nombres como 'Marketing' no rompan el JS
                    text: "<?= addslashes($mensaje['text']) ?>", 
                    timer: <?= $mensaje['icon'] === 'success' ? '2500' : 'null' ?>,
                    showConfirmButton: <?= $mensaje['icon'] === 'success' ? 'false' : 'true' ?>,
                    confirmButtonText: 'Entendido'
                }).then(() => {
                    // ESTO ES CLAVE: Redirigimos siempre a la URL base para limpiar el ?action=delete
                    window.location.href = '<?= BASE_URL; ?>materias/gestion';
                });
            <?php endif; ?>
    });

    // --- 5. FUNCIÓN ELIMINAR (DESACTIVAR) ---
    function confirmarEliminacion(uuid, nombre) {
        // Verificamos que el UUID no llegue vacío
        if (!uuid) {
            Swal.fire('Error', 'No se pudo obtener el identificador del espacio.', 'error');
            return;
        }

        Swal.fire({
            title: "¿Desactivar Espacio Curricular?",
            text: "La materia '" + nombre + "' se marcará como inactiva. Se verificará que no existan alumnos inscriptos.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#dc3545", // Rojo Bootstrap
            cancelButtonColor: "#6c757d",
            cancelButtonText: "Cancelar",
            confirmButtonText: "Sí, desactivar",
            reverseButtons: true
        }).then((result) => { 
            if (result.isConfirmed) { 
                // Redirección amigable usando el parámetro uuid
                // Esto impactará en tu bloque de "Eliminación Lógica" del PHP
                window.location.href = "<?= BASE_URL; ?>materias/gestion?action=delete&uuid=" + uuid; 
            } 
        });
    }

    // --- INTERCEPTAR ENVÍO DEL FORMULARIO PARA EDICIÓN ---
    $('#formEspacios').on('submit', function(e) {
        // Obtenemos el valor del UUID oculto
        const uuidVal = $('input[name="uuid_espacio"]').val();
        
        // Si el UUID tiene contenido, el usuario está EDITANDO
        if (uuidVal !== '' && uuidVal !== undefined) {
            e.preventDefault(); // Detenemos el envío automático del formulario
            const form = this;  // Guardamos la referencia al formulario

            Swal.fire({
                title: '¿Confirmar modificaciones?',
                text: "Se realizarán cambios permanentes en el registro de este espacio curricular.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // Verde Success
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, modificar',
                cancelButtonText: 'Revisar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Si el usuario acepta, enviamos el formulario manualmente al PHP
                    form.submit();
                }
            });
        } 
        // Si el UUID está vacío, es un ALTA nueva y se envía directamente sin preguntar
    });
</script>
    <?php include '../../vistas/footer.php'; ?>
</body>
</html>