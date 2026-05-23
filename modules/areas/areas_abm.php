<?php
    session_start();

    // 1. AJUSTE DE RUTAS (Subimos dos niveles para llegar al core) [cite: 1]
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php';
    require_once '../../core/funciones.php';

    // Verificación de seguridad por rol
    verificar_permisos(['Administrador', 'Tesoreria', 'Secretaría']);

    $rol = $_SESSION['rol']; 
    $mensaje = []; 

    // --- BLOQUE DE SEGURIDAD: CAPTURA Y VALIDACIÓN DE UUID ---
    $uuid_externo = $_GET['uuid'] ?? null;
    $area_editar = null;

    if ($uuid_externo) {
        $stmt_check = $pdo->prepare("SELECT * FROM areas WHERE uuid_area = :uuid LIMIT 1");
        $stmt_check->execute([':uuid' => $uuid_externo]);
        $area_editar = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$area_editar && !isset($_GET['action'])) {
            // REGISTRAMOS EL INTENTO FALLIDO
            registrar_log_seguridad($pdo, 'URL_MANIPULADA', "Intento de acceso a UUID inexistente: $uuid_externo en módulo Áreas");
            
            header("Location: " . BASE_URL . "areas/gestion?error=seguridad");
            exit;
        }
    }

    // --- PROCESAMIENTO POST (INSERT / UPDATE) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nombre = trim($_POST['nombre_area']);
        $descripcion = trim($_POST['descripcion']);
        $sueldo_base = filter_var($_POST['sueldo_base_area'], FILTER_VALIDATE_FLOAT) ?: 0;
        $id_area = isset($_POST['id_area']) ? (int)$_POST['id_area'] : 0;
        
        if ($id_area == 0) { 
            try {
                // El UUID se genera automáticamente en la DB o mediante una función en PHP
                $sql = "INSERT INTO areas (uuid_area, nombre_area, descripcion, sueldo_base_area) 
                        VALUES (UUID(), :nombre, :descripcion, :sueldo)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':sueldo' => $sueldo_base]);
                $mensaje = ['icon' => 'success', 'title' => '¡Área Creada!', 'text' => 'El área se ha registrado con un identificador único seguro.'];
            } catch (PDOException $e) {
                $mensaje = ['icon' => 'error', 'title' => 'Error al crear', 'text' => 'Error: ' . $e->getMessage()];
            }
        } else { 
            try {
                // Update usando el ID interno pero validado por la sesión previa
                $sql = "UPDATE areas SET nombre_area = :nombre, descripcion = :descripcion, sueldo_base_area = :sueldo WHERE id_area = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':sueldo' => $sueldo_base, ':id' => $id_area]);
                $mensaje = ['icon' => 'success', 'title' => '¡Área Modificada!', 'text' => 'El área se ha actualizado correctamente.'];
                $area_editar = null; // Limpiamos para volver al modo creación
            } catch (PDOException $e) {
                 $mensaje = ['icon' => 'error', 'title' => 'Error al modificar', 'text' => 'Error: ' . $e->getMessage()];
            }
        }
    } 

   // --- PROCESAMIENTO DELETE ---
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['uuid'])) {
        $uuid_borrar = $_GET['uuid'];

        try {
            $stmt_find = $pdo->prepare("SELECT id_area FROM areas WHERE uuid_area = ? LIMIT 1");
            $stmt_find->execute([$uuid_borrar]);
            $area_found = $stmt_find->fetch(PDO::FETCH_ASSOC);

            if ($area_found) {
                $id_val = $area_found['id_area'];

                // Verificamos integridad
                $stmt_staff = $pdo->prepare("SELECT COUNT(*) FROM personal_staff WHERE id_area = ?");
                $stmt_staff->execute([$id_val]);
                $en_uso_staff = $stmt_staff->fetchColumn();

                $stmt_prof = $pdo->prepare("SELECT COUNT(*) FROM profesores WHERE id_area = ?");
                $stmt_prof->execute([$id_val]);
                $en_uso_prof = $stmt_prof->fetchColumn();

                if ($en_uso_staff > 0 || $en_uso_prof > 0) {
                    // REDIRECCIÓN POR INTEGRIDAD: Limpia la URL y avisa que no se puede borrar
                    header("Location: " . BASE_URL . "areas/gestion?error=integridad");
                    exit;
                } else {
                    // PROCEDER AL BORRADO
                    $stmt_del = $pdo->prepare("DELETE FROM areas WHERE uuid_area = ?");
                    $stmt_del->execute([$uuid_borrar]);
                    
                    // REDIRECCIÓN POR ÉXITO
                    header("Location: " . BASE_URL . "areas/gestion?delete=success");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
        }
    }

    // --- DETECTOR DE MENSAJES POST-REDIRECCIÓN ---
    if (isset($_GET['error']) && $_GET['error'] == 'integridad') {
        $mensaje = [
            'icon' => 'warning',
            'title' => 'Operación Denegada',
            'text' => 'No es posible eliminar el área porque tiene personal o profesores vinculados.'
        ];
    }

    if (isset($_GET['delete']) && $_GET['delete'] == 'success') {
        $mensaje = [
            'icon' => 'success',
            'title' => '¡Área Eliminada!',
            'text' => 'El registro ha sido borrado correctamente.'
        ];
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Áreas Pro | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script><?php echo $js_sweetalert_confirmacion; ?></script>
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container py-4">
        <h2 class="mb-4"><i class="fas fa-shield-alt text-primary"></i> Gestión de Áreas Seguras</h2>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-dark text-white fw-bold">
                <?php echo $area_editar ? '<i class="fas fa-edit text-warning"></i> Editar Área (UUID: '.substr($uuid_externo, 0, 8).'...)' : '<i class="fas fa-plus-circle"></i> Nueva Área'; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL; ?>areas/gestion">
                    <input type="hidden" name="id_area" value="<?php echo $area_editar ? htmlspecialchars($area_editar['id_area']) : 0; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Nombre del Área (*):</label>
                            <input type="text" class="form-control" name="nombre_area" value="<?php echo $area_editar ? htmlspecialchars($area_editar['nombre_area']) : ''; ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-success">Sueldo Base ($):</label>
                            <div class="input-group">
                                <span class="input-group-text bg-success text-white">$</span>
                                <input type="number" step="0.01" class="form-control border-success" name="sueldo_base_area" value="<?php echo $area_editar ? htmlspecialchars($area_editar['sueldo_base_area']) : '0.00'; ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Descripción Técnica:</label>
                            <textarea class="form-control" name="descripcion" rows="2"><?php echo $area_editar ? htmlspecialchars($area_editar['descripcion']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-<?php echo $area_editar ? 'warning' : 'primary'; ?> px-4 fw-bold">
                            <i class="fas fa-save me-1"></i> Guardar Cambios
                        </button>
                        <?php if ($area_editar): ?>
                            <a href="<?= BASE_URL; ?>areas/gestion" class="btn btn-secondary">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaAreas" class="table table-hover table-striped align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Interno</th>
                                <th>Nombre del Área</th>
                                <th>Sueldo Base</th>
                                <th>Descripción</th>
                                <th class="text-center">Acciones Seguras</th>
                            </tr>
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
            // Detector de manipulación de ID/UUID
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('error') === 'seguridad') {
                Swal.fire({
                    title: '¡Intento de Acceso No Autorizado!',
                    text: 'El sistema ha detectado una alteración en la URL. El incidente ha sido reportado.',
                    icon: 'error',
                    confirmButtonColor: '#d33'
                }).then(() => {
                    window.history.replaceState({}, document.title, "<?= BASE_URL; ?>areas/gestion");
                });
            }

            $('#tablaAreas').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": { 
                    "url": "<?= BASE_URL; ?>areas/lista", 
                    "type": "POST" 
                },
                "columns": [
                    { "data": 0 },
                    { "data": 1 },
                    { 
                        "data": 4, 
                        "render": function(data) { 
                            return '<strong>$ ' + parseFloat(data).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '</strong>'; 
                        }
                    },
                    { "data": 2 },
                    { 
                        "data": 5, // Aquí el server-side debe enviar el UUID_AREA
                        "orderable": false, 
                        "className": "text-center",
                        "render": function(data, type, row) {
                            return `
                                <div class="btn-group">
                                    <a href="<?= BASE_URL; ?>areas/editar/${data}" class="btn btn-sm btn-info text-white"><i class="fas fa-edit"></i></a>
                                    <button onclick="confirmarEliminacion('${data}', '${row[1]}')" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </div>
                            `;
                        }
                    }
                ],
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json" },
            });

            <?php if (!empty($mensaje)): ?>
            Swal.fire({
                icon: '<?= $mensaje['icon'] ?>',
                title: '<?= $mensaje['title'] ?>',
                text: '<?= $mensaje['text'] ?>'
            });
            <?php endif; ?>

        });

        // 1. LA FUNCIÓN DEBE IR AFUERA (Global)
        function confirmarEliminacion(uuid, nombre) {
            Swal.fire({
                title: "¿Estás seguro?",
                html: `Vas a eliminar el área: <strong>${nombre}</strong>.<br><small class="text-danger">El sistema verificará si hay personal vinculado antes de borrar.</small>`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirección a la ruta amigable
                    window.location.href = "<?= BASE_URL; ?>areas/eliminar/" + uuid;
                }
            });
        }
    </script>
    <?php include '../../vistas/footer.php'; ?>
</body>
</html>