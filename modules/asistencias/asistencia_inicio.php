<?php
session_start();
// Ajustamos las rutas a tu estándar
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

// Verificamos permisos (Añadí Profesor porque ellos son los que más usan esto)
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

$rol = $_SESSION['rol']; 
$id_usuario = $_SESSION['id_usuario']; // Usamos ID de usuario para mayor precisión

// Lógica de selección de carreras usando UUID para seguridad
try {
    if ($rol == "Administrador" || $rol == "Secretaría" || $rol == "Profesor") {
        // Traemos el uuid_carrera para pasarlo por URL amigable después
        $sql = "SELECT uuid_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera";
        $stmt = $pdo->query($sql);
    } else {
        // Si es profesor, solo ve las carreras donde tiene materias asignadas
        $sql = "SELECT DISTINCT c.uuid_carrera, c.nombre_carrera 
                FROM carreras c
                INNER JOIN espacios_curriculares e ON c.id_carrera = e.id_carrera
                INNER JOIN profesores_espacios pe ON e.id_espacio = pe.id_espacio
                WHERE pe.id_profesor = (SELECT id_profesor FROM profesores WHERE id_usuario = ?) 
                AND c.activo = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_usuario]);
    }
    $carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    die("Error de conexión: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asistencia Clases | Sintek Gestión</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 15px; }
        /* Estilo Sintek: Degradado oscuro */
        .header-sintek { 
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%); 
            color: white; 
            border-radius: 15px 15px 0 0; 
            padding: 25px;
        }
        .form-label { font-weight: 600; color: #334155; font-size: 0.9rem; }
        .btn-sintek { 
            background-color: #0dcaf0; 
            color: white; 
            border: none; 
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-sintek:hover { background-color: #0baccc; color: white; transform: translateY(-2px); }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container mt-5">
        <?php if (isset($_GET['msg']) && $_GET['msg'] == 'ok'): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('¡Éxito!', 'La asistencia se ha guardado correctamente.', 'success');
                });
            </script>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="header-sintek">
                        <h4 class="mb-0"><i class="fas fa-calendar-check me-2"></i> Registro de Asistencia Diaria</h4>
                        <p class="mb-0 opacity-75 small">Seleccione los parámetros para abrir la planilla de alumnos.</p>
                    </div>
                    <div class="card-body p-4">
                        <form action="asistencia-planilla.php" method="GET">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label text-uppercase small">Fecha de Clase</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-calendar-day text-muted"></i></span>
                                        <input type="date" name="fecha" class="form-control border-start-0 ps-0" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label text-uppercase small">Carrera</label>
                                    <select name="carrera_uuid" id="carrera" class="form-select shadow-sm" required>
                                        <option value="">Seleccione carrera...</option>
                                        <?php foreach($carreras as $c): ?>
                                            <option value="<?= $c['uuid_carrera'] ?>"><?= $c['nombre_carrera'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label text-uppercase small">Espacio Curricular</label>
                                    <select name="espacio_uuid" id="espacio" class="form-select shadow-sm" required disabled>
                                        <option value="">Esperando carrera...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-5">
                                <button type="button" id="btnReporteMensual" class="btn btn-outline-secondary rounded-pill px-4">
                                    <i class="fas fa-table me-2"></i> Reporte Mensual
                                </button>
                                
                                <button type="submit" class="btn btn-sintek btn-lg rounded-pill px-5 shadow">
                                    <i class="fas fa-edit me-2"></i> ABRIR PLANILLA
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
            // 1. Carga dinámica de espacios
            $('#carrera').on('change', function() {
                var uuid = $(this).val();
                var $espacioSel = $('#espacio');

                if(uuid) {
                    $espacioSel.html('<option>Cargando materias...</option>').prop('disabled', true);
                    $.post('<?= BASE_URL ?>api/asistencias/get-espacios', { uuid_carrera: uuid }, function(data) {
                        $espacioSel.html(data).prop('disabled', false);
                    }).fail(function() {
                        $espacioSel.html('<option value="">Error al cargar</option>');
                        Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                    });
                } else {
                    $espacioSel.html('<option value="">Seleccione carrera primero</option>').prop('disabled', true);
                }
            });

            // 2. Lógica para el REPORTE MENSUAL (Sábana)
            $('#btnReporteMensual').on('click', function(e) {
                e.preventDefault();
                
                const uuidCarrera = $('#carrera').val(); 
                const uuidEspacio = $('#espacio').val(); 
                const fechaFull = $('input[name="fecha"]').val();
                
                if(!uuidCarrera || !uuidEspacio) {
                    return Swal.fire('Atención', 'Seleccione carrera y materia', 'warning');
                }

                const dateParts = fechaFull.split("-"); 
                const anio = dateParts[0];
                const mes = parseInt(dateParts[1]);

                // CAMBIO AQUÍ: Agregamos la ruta física real 'modules/asistencias/'
                // Usamos BASE_URL para que siempre empiece desde http://localhost/sintek-gestion-pro/
                const urlFisica = '<?= BASE_URL ?>' + `modules/asistencias/asistencia_sabana.php?uuid_carrera=${uuidCarrera}&uuid_espacio=${uuidEspacio}&mes=${mes}&anio=${anio}`;
                
                window.location.href = urlFisica;
            });

            // 3. Lógica para la PLANILLA DIARIA (Submit del Formulario)
            $('form').on('submit', function(e) {
                e.preventDefault();
                const uuidEspacio = $('#espacio').val();
                const fechaFull = $('input[name="fecha"]').val();

                if(!uuidEspacio) {
                    return Swal.fire('Atención', 'Seleccione una materia para abrir la planilla', 'warning');
                }

                // REDIRECCIÓN AMIGABLE PARA PLANILLA
                window.location.href = `<?= BASE_URL ?>asistencias-gestion/planilla/${uuidEspacio}/${fechaFull}`;
            });
        });
    </script>
    
    <?php include '../../vistas/footer.php'; ?>
</body>
</html>