<?php
session_start();
require 'conexion.php'; 
require 'seguridad.php'; 

verificar_permisos(['Administrador']);
$rol = $_SESSION['rol'];

$mensaje = [];
$usuario_editar = null;

// Lógica de Roles (Tu SQL original)
$sql_roles = "SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol";
$roles_disponibles = $pdo->query($sql_roles)->fetchAll(PDO::FETCH_ASSOC);

// --- LÓGICA CRUD --- (Sin cambios en tu lógica original)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || !verificar_token_csrf($_POST['csrf_token'])) {
        registrar_log_seguridad($pdo, 'CSRF_DETECTADO', 'Intento de POST sin token válido en usuarios_abm');
        header('Location: ' . BASE_URL . 'dashboard?error=csrf');
        exit;
    }

    $id_usuario = isset($_POST['id_usuario']) ? (int)$_POST['id_usuario'] : 0;
    $nombre_usuario = trim($_POST['nombre_usuario']);
    $password_form = $_POST['password']; 
    $email = trim($_POST['email']);
    
    // El nombre del input sigue siendo 'apellido_nombre' para mayor claridad, 
    // pero lo guardaremos en la columna 'Apellido'
    $apellido_nombre = trim($_POST['apellido_nombre']);
    
    $id_rol = (int)$_POST['id_rol'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $password_hash = !empty($password_form) ? password_hash($password_form, PASSWORD_DEFAULT) : null;

    try {
        if ($id_usuario == 0) { // ALTA
            if (empty($password_hash)) { throw new Exception("La clave es obligatoria."); }
            
            // CORRECCIÓN: Usamos la columna 'Apellido'
            $sql = "INSERT INTO usuarios (nombre_usuario, password, Apellido, email, id_rol) 
                    VALUES (:nombre_usuario, :password_hash, :apellido, :email, :id_rol)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':password_hash' => $password_hash, 
                ':nombre_usuario' => $nombre_usuario, 
                ':apellido'       => $apellido_nombre, // Valor del input
                ':email'          => $email, 
                ':id_rol'         => $id_rol
            ]);
            $mensaje = ['icon' => 'success', 'title' => '¡Usuario Creado!', 'text' => 'Registrado correctamente.'];
        } else { // MODIFICACIÓN
            
            // CORRECCIÓN: Usamos la columna 'Apellido'
            $sql = "UPDATE usuarios SET nombre_usuario = :nombre_usuario, Apellido = :apellido, email = :email, id_rol = :id_rol";
            
            if (!empty($password_hash)) { $sql .= ", password = :password_hash"; }
            $sql .= " WHERE id_usuario = :id";
            
            $stmt = $pdo->prepare($sql);
            $params = [
                ':nombre_usuario' => $nombre_usuario, 
                ':apellido'       => $apellido_nombre, // Valor del input
                ':email'          => $email, 
                ':id_rol'         => $id_rol, 
                ':id'             => $id_usuario
            ];
            if (!empty($password_hash)) { $params[':password_hash'] = $password_hash; }
            $stmt->execute($params);
            $mensaje = ['icon' => 'success', 'title' => '¡Actualizado!', 'text' => 'Datos actualizados.'];
        }
    } catch (PDOException $e) {
        $mensaje = ['icon' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
    }
}

// CARGA PARA EDICIÓN
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id_usuario = (int)$_GET['id'];
    // CORRECCIÓN: Seleccionamos 'Apellido'
    $sql = "SELECT id_usuario, nombre_usuario, email, id_rol, Apellido FROM usuarios WHERE id_usuario = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_usuario]);
    $usuario_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Confirmación de eliminación con SweetAlert2
$js_sweetalert_confirmacion = '
function confirmarEliminacion(id, nombre) {
    Swal.fire({
        title: "¿Estás seguro?",
        html: `Vas a eliminar al usuario: <strong>${nombre}</strong>.`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => { if (result.isConfirmed) { window.location.href = "usuarios_abm.php?action=delete&id=" + id; } });
}';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card-header { border-radius: 12px 12px 0 0 !important; font-weight: 700; padding: 1rem 1.5rem; }
        .form-control, .form-select { padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid #dee2e6; }
        .form-control:focus { box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15); }
        .table thead th { background-color: #f1f4f9; color: #495057; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; border: none; }
        .btn-action { padding: 0.5rem 1.2rem; border-radius: 8px; font-weight: 600; }
        .page-title { color: #212529; font-weight: 800; letter-spacing: -0.5px; }
    </style>
</head>
<body>
    <?php include 'vistas/nav.php'; ?>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 page-title text-uppercase">
                    <i class="fas fa-users-cog me-2 text-primary"></i>Gestión de Usuarios
                </h1>
                <p class="text-muted small">Panel exclusivo para la administración de accesos al sistema.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header <?php echo $usuario_editar ? 'bg-warning text-dark' : 'bg-primary text-white'; ?>">
                        <i class="fas <?php echo $usuario_editar ? 'fa-user-edit' : 'fa-user-plus'; ?> me-2"></i>
                        <?php echo $usuario_editar ? 'Editar Usuario' : 'Nuevo Usuario'; ?>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="usuarios_abm.php">
                            <input type="hidden" name="csrf_token" value="<?php echo generar_token_csrf(); ?>">
                            <input type="hidden" name="id_usuario" value="<?php echo $usuario_editar ? $usuario_editar['id_usuario'] : 0; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Nombre de Usuario</label>
                                <input type="text" class="form-control" name="nombre_usuario" value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['nombre_usuario']) : ''; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['email']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Apellido y Nombre</label>
                                <input type="text" class="form-control" name="apellido_nombre" 
                                    value="<?php echo $usuario_editar ? htmlspecialchars($usuario_editar['Apellido']) : ''; ?>" 
                                    required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Clave de Acceso</label>
                                <input type="password" class="form-control" name="password" 
                                       placeholder="<?php echo $usuario_editar ? 'Dejar vacío para mantener' : 'Clave requerida'; ?>" 
                                       <?php echo $usuario_editar ? '' : 'required'; ?>>
                                <?php if($usuario_editar): ?>
                                    <div class="form-text text-warning small"><i class="fas fa-info-circle"></i> Solo llenar si desea cambiar la clave.</div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small fw-bold text-uppercase">Asignar Rol</label>
                                <select name="id_rol" class="form-select" required>
                                    <option value="" disabled selected>Seleccione...</option>
                                    <?php foreach ($roles_disponibles as $rol_opcion): ?>
                                        <option value="<?php echo $rol_opcion['id_rol']; ?>"
                                            <?php if ($usuario_editar && $usuario_editar['id_rol'] == $rol_opcion['id_rol']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($rol_opcion['nombre_rol']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn <?php echo $usuario_editar ? 'btn-warning' : 'btn-primary'; ?> btn-action">
                                    <i class="fas fa-save me-2"></i><?php echo $usuario_editar ? 'Actualizar Datos' : 'Registrar Usuario'; ?>
                                </button>
                                <?php if ($usuario_editar): ?>
                                    <a href="usuarios_abm.php" class="btn btn-light btn-action text-muted">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card p-3">
                    <h5 class="fw-bold mb-4 px-2"><i class="fas fa-list me-2"></i>Cuentas Registradas</h5>
                    <div class="table-responsive">
                        <table id="tablaUsuarios" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Usuario</th>
                                    <th>Apellido y Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="js/sweetalert2@11"></script>
    <script>
        <?php echo $js_sweetalert_confirmacion; ?>

        $(document).ready(function() {
           $('#tablaUsuarios').DataTable({
                "processing": true, 
                "serverSide": true, 
                "ajax": { "url": "server_processing_usuarios.php", "type": "POST" },
                "columns": [
                    { "data": 0 }, // ID
                    { "data": 1 }, // Usuario
                    { "data": 2 }, // Apellido y Nombre (Nueva)
                    { "data": 3 }, // Email
                    { "data": 4 }, // Rol
                    { "data": 5, "orderable": false, "className": "text-center" } // Acciones
                ],
                "language": { "url": "datatables/Spanish.json" }
            });

            <?php if (!empty($mensaje)): ?>
                Swal.fire({
                    icon: '<?php echo $mensaje['icon']; ?>',
                    title: '<?php echo $mensaje['title']; ?>',
                    text: '<?php echo $mensaje['text']; ?>',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => { window.location.href = 'usuarios_abm.php'; });
            <?php endif; ?>
        });
    </script>
    <?php include 'vistas/footer.php'; ?>
</body>
</html>