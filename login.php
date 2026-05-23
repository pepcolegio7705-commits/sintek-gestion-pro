<?php
// 1. Cargamos el núcleo (esto ya inicia la sesión y conecta a la DB)
require_once 'core/conexion.php';
require_once 'core/funciones.php';

// Redirigir si ya está logueado usando la nueva lógica
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === TRUE) {
    header('Location: dashboard');
    exit;
}

$mostrar_alerta_error = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario  = trim($_POST['nombre_usuario']);
    $password = $_POST['password'];

    // Consulta preparada con PDO (Seguridad contra SQL Injection)
    $sql = "SELECT u.id_usuario, u.password, u.nombre_usuario, u.Apellido, r.nombre_rol 
            FROM usuarios u 
            JOIN roles r ON u.id_rol = r.id_rol 
            WHERE u.nombre_usuario = :usuario LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario' => $usuario]);
    $usuario_db = $stmt->fetch();

    if ($usuario_db && password_verify($password, $usuario_db['password'])) {
        // Regenerar ID de sesión por seguridad (evita fijación de sesión)
        session_regenerate_id(true);

        $_SESSION['loggedin']       = TRUE;
        $_SESSION['id_usuario']     = $usuario_db['id_usuario'];
        $_SESSION['nombre_usuario'] = $usuario_db['nombre_usuario'];
        $_SESSION['Apellido']       = $usuario_db['Apellido'];
        $_SESSION['rol']            = $usuario_db['nombre_rol'];
        
        // --- PROCESOS AUTOMÁTICOS POST-LOGIN ---
        // Ejecutamos el mantenimiento de mesas que estaba en tu conexión anterior
        realizar_mantenimiento_mesas($pdo);

        header('Location: dashboard');
        exit;
    } else {
        $mostrar_alerta_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | <?= NOM_INST; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #002b52 0%, #001224 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: #003366;
            font-size: 40px;
            border: 2px solid #e9ecef;
        }
        .login-header h2 {
            color: #003366;
            font-weight: 800;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .inst-name {
            color: #6c757d;
            font-size: 0.95rem;
            margin-bottom: 30px;
            display: block;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
        }
        .btn-login {
            background: #003366;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
        }
        .btn-login:hover {
            background: #004a94;
            transform: translateY(-2px);
            color: white;
        }
        .sintek-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 0.8rem;
            color: #adb5bd;
        }
        .sintek-link {
            color: #003366;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="login-card text-center shadow-lg">
        <div class="brand-logo">
            <?php if (defined('LOGO_INST') && LOGO_INST != ''): ?>
                <img src="<?= BASE_URL; ?>img/logo.png" alt="Logo <?= NOM_INST; ?>" style="width: 60px;">
            <?php else: ?>
                <i class="fas fa-university"></i>
            <?php endif; ?>
        </div>

        <div class="login-header">
            <h2>Gestión Académica</h2>
            <span class="inst-name"><?= NOM_INST; ?></span>
        </div>

        <form action="login" method="POST" class="text-start">
            <div class="mb-3">
                <label class="form-label fw-bold small"><i class="fas fa-user me-1"></i> Usuario</label>
                <input type="text" name="nombre_usuario" class="form-control" required placeholder="Ingrese su usuario" autofocus>
            </div>
            
            <div class="mb-4">
                <label class="form-label fw-bold small"><i class="fas fa-lock me-1"></i> Contraseña</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            
            <button type="submit" class="btn btn-login w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
            </button>
        </form>

        <div class="sintek-footer">
            Potenciado por <br>
            <a href="<?= WEB_AUTOR; ?>" target="_blank" class="sintek-link">
                <i class="fas fa-rocket me-1"></i> <?= AUTOR_SISTEMA; ?>
            </a>
        </div>
    </div>

    <?php if ($mostrar_alerta_error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Acceso Denegado',
            text: 'Usuario o contraseña incorrectos.',
            confirmButtonColor: '#003366'
        });
    </script>
    <?php endif; ?>

</body>
</html>