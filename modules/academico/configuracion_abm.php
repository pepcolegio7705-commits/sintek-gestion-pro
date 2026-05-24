<?php
    session_start();

    require_once '../../core/conexion.php';
    require_once '../../core/funciones.php';
    require_once '../../core/seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría']);
    $rol = $_SESSION['rol']; 

    $mensaje = "";

    // 1. PROCESAR ACTUALIZACIÓN
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "UPDATE configuracion SET 
                nombre_institucion = :nom, 
                domicilio = :dom, 
                telefono = :tel, 
                email_contacto = :email, 
                rector = :rec,
                vicedirector = :vice, 
                secretario = :sec 
                WHERE id_config = 1";
        
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute([
            'nom'   => $_POST['nombre_institucion'],
            'dom'   => $_POST['domicilio'],
            'tel'   => $_POST['telefono'],
            'email' => $_POST['email_contacto'],
            'rec'   => $_POST['rector'],
            'vice'  => $_POST['vicedirector'], 
            'sec'   => $_POST['secretaria']
        ]);

        if ($resultado) {
            $mensaje = "<div class='alert alert-success shadow-sm animate__animated animate__fadeIn'>
                            <i class='fas fa-check-circle me-2'></i>Configuración actualizada con éxito.
                        </div>";
        } else {
            $mensaje = "<div class='alert alert-danger shadow-sm'>Error al actualizar los datos.</div>";
        }
    }

    // 2. OBTENER DATOS ACTUALES (Se ejecuta SIEMPRE para evitar el "Undefined variable $conf")
    $query_conf = $pdo->query("SELECT * FROM configuracion LIMIT 1");
    $conf = $query_conf->fetch(PDO::FETCH_ASSOC);

    // Fallback por si la tabla está vacía
    if (!$conf) {
        $conf = [
            'nombre_institucion' => '',
            'domicilio' => '',
            'telefono' => '',
            'email_contacto' => '',
            'rector' => '',
            'vicedirector' => '',
            'secretario' => ''
        ];
    }
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i> Configuración de Identidad Institucional</h5>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $mensaje; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Nombre de la Institución</label>
                                    <input type="text" name="nombre_institucion" class="form-control" value="<?php echo htmlspecialchars($conf['nombre_institucion']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Domicilio</label>
                                    <input type="text" name="domicilio" class="form-control" value="<?php echo htmlspecialchars($conf['domicilio']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Teléfono de Contacto</label>
                                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($conf['telefono']); ?>">
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Email Institucional</label>
                                    <input type="email" name="email_contacto" class="form-control" value="<?php echo htmlspecialchars($conf['email_contacto']); ?>">
                                </div>
                                <hr>
                                <h6 class="text-muted mb-3">Cuerpo Directivo</h6>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Rectora / Director/a</label>
                                    <input type="text" name="rector" class="form-control" value="<?php echo htmlspecialchars($conf['rector']); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Vicedirector</label>
                                    <input type="text" name="vicedirector" class="form-control" value="<?php echo htmlspecialchars($conf['vicedirector']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Secretaría</label>
                                    <input type="text" name="secretaria" class="form-control" value="<?php echo htmlspecialchars($conf['secretario']); ?>">
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4 text-muted small">
                    Sistema gestionado por <strong><?php echo AUTOR_SISTEMA; ?></strong>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../vistas/footer.php'; ?>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>