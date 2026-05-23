<?php
session_start();
// Si por alguna razón no hay sesión, mandamos al login
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso Denegado | Sistema Escolar</title>
    <link rel="stylesheet" href="./bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .error-container {
            margin-top: 80px;
        }
        .card-denied {
            border: none;
            border-radius: 15px;
        }
        .icon-shield {
            color: #dc3545; /* Rojo peligro */
            font-size: 6rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'vistas/nav.php'; ?>

    <div class="container error-container">
        <div class="row justify-content-center">
            <div class="col-md-7 text-center">
                <div class="card shadow-lg card-denied">
                    <div class="card-body p-5">
                        <i class="fas fa-shield-alt icon-shield"></i>
                        <h1 class="display-5 fw-bold text-dark">Acceso Restringido</h1>
                        <p class="lead text-secondary">
                            Lo sentimos, **<?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>**, 
                            tu perfil de **<?php echo htmlspecialchars($rol); ?>** no tiene los permisos suficientes 
                            para visualizar este módulo.
                        </p>
                        <hr class="my-4">
                        <p class="small text-muted mb-4">
                            Si necesitas acceder a esta sección, solicita la autorización correspondiente al Administrador del sistema.
                        </p>
                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                            <a href="dashboard.php" class="btn btn-primary btn-lg px-4 gap-3 shadow">
                                <i class="fas fa-home"></i> Volver al Inicio
                            </a>
                            <button onclick="window.history.back();" class="btn btn-outline-secondary btn-lg px-4">
                                <i class="fas fa-arrow-left"></i> Regresar
                            </button>
                        </div>
                    </div>
                </div>
                <p class="mt-4 text-muted small">&copy; 2026 Sistema de Control Institucional - Escuela N° 752</p>
            </div>
        </div>
    </div>

    <?php include 'vistas/footer.php'; ?>
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>