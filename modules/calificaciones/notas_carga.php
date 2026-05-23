<?php
session_start();
require_once '../../core/conexion.php'; 
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

$rol = $_SESSION['rol']; 
$usuario = $_SESSION['nombre_usuario'] ?? ''; 

try {
    // 1. Modificamos la consulta para incluir uuid_carrera
    if ($rol === 'Administrador' || $rol === 'Secretaría') {
        $sql_carreras = "SELECT id_carrera, uuid_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera ASC";
        $stmt = $pdo->prepare($sql_carreras);
        $stmt->execute();
    } else {
        $sql_carreras = "SELECT DISTINCT c.id_carrera, c.uuid_carrera, c.nombre_carrera 
                         FROM carreras c
                         INNER JOIN espacios_curriculares e ON c.id_carrera = e.id_carrera
                         INNER JOIN profesores_espacios pe ON e.id_espacio = pe.id_espacio
                         INNER JOIN profesores p ON pe.id_profesor = p.id_profesor
                         WHERE p.nombre_usuario = ? AND c.activo = 1
                         ORDER BY c.nombre_carrera ASC";
        $stmt = $pdo->prepare($sql_carreras);
        $stmt->execute([$usuario]);
    }
    $carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error en selección de notas: " . $e->getMessage());
    die("Error al cargar los datos. Por favor, contacte al soporte.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carga de Notas | Sintek Gestión</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .card { border-radius: 15px; border: none; }
        .header-gradient { 
            background: linear-gradient(45deg, #003366, #0052a3); 
            color: white; 
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body>
<?php include '../../vistas/nav.php'; ?>

<div class="container" style="margin-top: 50px;"> 
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg">
                <div class="card-header header-gradient py-3">
                    <h4 class="mb-0"><i class="fas fa-file-signature me-2"></i> Registro de Calificaciones</h4>
                </div>
                <div class="card-body p-4">
                    <form id="formSeleccion">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">1. Carrera / Oferta Académica</label>
                                <select name="id_carrera" id="carrera" class="form-select" required>
                                    <option value="">Seleccione una carrera...</option>
                                    <?php foreach($carreras as $c): ?>
                                        <option value="<?= $c['id_carrera'] ?>" data-uuid="<?= $c['uuid_carrera'] ?>">
                                            <?= htmlspecialchars($c['nombre_carrera']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">2. Espacio Curricular / Materia</label>
                                <select name="id_espacio" id="espacio" class="form-select" required disabled>
                                    <option value="">Primero elija una carrera...</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-5">
                            <button type="submit" id="btnGenerar" class="btn btn-success btn-lg px-5 shadow-sm" disabled>
                                <i class="fas fa-table me-2"></i> Abrir Planilla de Carga
                            </button>
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
        // 1. Carga dinámica de materias
        $('#carrera').on('change', function() {
            const id_carrera = $(this).val();
            const $espacio = $('#espacio');
            const $btn = $('#btnGenerar');

            if(id_carrera) {
                $espacio.html('<option>Cargando materias...</option>').prop('disabled', true);
                $btn.prop('disabled', true);

                $.post('<?= BASE_URL ?>calificaciones/get-espacios', {id_carrera: id_carrera}, function(data) {
                    $espacio.html(data).prop('disabled', false);
                }).fail(function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudieron obtener las materias.',
                        confirmButtonColor: '#003366'
                    });
                    $espacio.html('<option value="">Error al cargar</option>');
                });
            } else {
                $espacio.prop('disabled', true).html('<option value="">Primero elija una carrera...</option>');
                $btn.prop('disabled', true);
            }
        });

        // 2. Habilitar botón
        $('#espacio').on('change', function() {
            $('#btnGenerar').prop('disabled', !$(this).val());
        });

        // 3. REDIRECCIÓN AMIGABLE POR UUID (Soluciona tu error)
        $('#formSeleccion').on('submit', function(e) {
            e.preventDefault();

            // Busca el data-uuid en la opción que está seleccionada actualmente
            const uuidCarrera = $('#carrera option:selected').attr('data-uuid');
            const uuidEspacio = $('#espacio option:selected').attr('data-uuid');

            console.log("Carrera UUID:", uuidCarrera); // Para que revises en F12 si llega
            console.log("Espacio UUID:", uuidEspacio);

            if (uuidCarrera && uuidEspacio) {
                Swal.fire({
                    title: 'Generando Planilla',
                    text: 'Preparando listado...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                window.location.href = `<?= BASE_URL ?>calificaciones/planilla/${uuidCarrera}/${uuidEspacio}`;
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Parámetros incompletos',
                    text: 'No se pudieron detectar los identificadores de seguridad. Por favor, refresque la página (F5) e intente de nuevo.',
                    confirmButtonColor: '#003366'
                });
            }
        });
    });
</script>

<?php include '../../vistas/footer.php'; ?>
</body>
</html>