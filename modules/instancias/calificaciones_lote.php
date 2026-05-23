<?php
    session_start();
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php'; 

    verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);
    $rol = $_SESSION['rol'];

    // Validamos que llegue el UUID
    if (!isset($_GET['uuid_mesa'])) {
        header('Location: ' . BASE_URL . 'instancias/gestion-inscripciones');
        exit;
    }

    $uuid_mesa = $_GET['uuid_mesa'];

    // 1. Buscamos la mesa por su UUID para obtener los datos y el ID interno
    $sql_mesa = "SELECT m.*, e.nombre_espacio, c.nombre_carrera 
                FROM mesas_examenes m
                JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
                JOIN carreras c ON m.id_carrera = c.id_carrera
                WHERE m.uuid_mesa = ?"; // Usamos la columna uuid
    $stmt_mesa = $pdo->prepare($sql_mesa);
    $stmt_mesa->execute([$uuid_mesa]);
    $mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);

    if (!$mesa) exit("Mesa de examen no válida o inexistente.");

    $id_mesa = $mesa['id_mesa']; // ID interno para las consultas de alumnos
    $mesa_cerrada = ($mesa['estado'] === 'Cerrada');

    // 2. Obtener alumnos inscriptos (usando el ID interno que ya recuperamos)
    $sql = "SELECT 
            a.id_alumno, a.dni, a.apellido, a.nombre, 
            i.condicion AS condicion_inscripcion, 
            c.nota_final, c.libro, c.folio, c.observacion
        FROM inscripciones_examen i
        JOIN alumnos a ON i.id_alumno = a.id_alumno
        LEFT JOIN calificaciones c ON (a.id_alumno = c.id_alumno AND c.id_mesa = :id_mesa)
        WHERE i.id_mesa = :id_mesa_filtro
        ORDER BY a.apellido, a.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_mesa' => $id_mesa, ':id_mesa_filtro' => $id_mesa]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga de Notas por Lote | Sintek Gestión</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 12px; }
        .nota-input { font-weight: bold; text-align: center; max-width: 100px; margin: 0 auto; border-radius: 8px; }
        .header-mesa { background: linear-gradient(135deg, #1e293b 0%, #334155 100%); color: white; border-radius: 12px; padding: 25px; }
        .table thead th { background-color: #f8f9fa; color: #334155; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
    </style>
</head>
<body>

<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid px-lg-5 mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold mb-0"><i class="fas fa-edit text-primary me-2"></i> Carga de Notas (Acta Volante)</h2>
                    <p class="text-muted mb-0">Complete las calificaciones finales del acta seleccionada.</p>
                </div>
                <a href="<?= BASE_URL ?>instancias/generar-acta/<?= $uuid_mesa ?>" target="_blank" class="btn btn-dark rounded-pill px-4 shadow-sm"><i class="fas fa-print me-1"></i> Imprimir Acta</a>
            </div>
            
            <div class="header-mesa shadow-sm mb-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <span class="text-uppercase small opacity-75">Espacio Curricular / Materia</span>
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($mesa['nombre_espacio']); ?></h4>
                        <p class="mb-0 opacity-75"><?= htmlspecialchars($mesa['nombre_carrera']); ?></p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0 border-start border-white border-opacity-25">
                        <div class="mb-1"><strong>Fecha Examen:</strong> <?= date('d/m/Y', strtotime($mesa['fecha_examen'])); ?></div>
                        <div class="mb-1"><strong>Instancia:</strong> <?= $mesa['llamado']; ?>° Llamado</div>
                        <?php if ($mesa_cerrada): ?>
                            <span class="badge bg-warning text-dark rounded-pill shadow-sm"><i class="fas fa-lock me-1"></i> ACTA CERRADA</span>
                        <?php else: ?>
                            <span class="badge bg-success rounded-pill shadow-sm"><i class="fas fa-unlock me-1"></i> ACTA EN CARGA</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (count($alumnos) > 0): ?>
            <form id="formLote">
                <input type="hidden" name="id_mesa" value="<?= $id_mesa; ?>">
                <input type="hidden" name="id_espacio" value="<?= $mesa['id_espacio']; ?>">
                <input type="hidden" name="id_carrera" value="<?= $mesa['id_carrera']; ?>">

                <div class="card shadow-sm mb-4 border-start border-primary border-5">
                    <div class="card-body">
                        <h6 class="fw-bold text-primary mb-3 text-uppercase small"><i class="fas fa-book-open me-2"></i>Datos del Libro Matriz</h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Libro N°:</label>
                                <input type="text" name="libro" class="form-control" placeholder="Ej: 10" 
                                       value="<?= htmlspecialchars($alumnos[0]['libro'] ?? ''); ?>"
                                       <?= $mesa_cerrada ? 'readonly' : 'required'; ?>>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Folio N°:</label>
                                <input type="text" name="folio" class="form-control" placeholder="Ej: 245" 
                                       value="<?= htmlspecialchars($alumnos[0]['folio'] ?? ''); ?>"
                                       <?= $mesa_cerrada ? 'readonly' : 'required'; ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Observación del Acta:</label>
                                <input type="text" name="observacion_general" class="form-control" placeholder="Ninguna..."
                                       value="<?= htmlspecialchars($alumnos[0]['observacion'] ?? ''); ?>"
                                       <?= $mesa_cerrada ? 'readonly' : ''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">DNI</th>
                                        <th>Apellido y Nombre</th>
                                        <th class="text-center">Condición</th>
                                        <th class="text-center">Calificación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($alumnos as $alum): ?>
                                    <tr>
                                        <td class="ps-4 text-muted small"><?= htmlspecialchars($alum['dni']); ?></td>
                                        <td><div class="fw-bold"><?= htmlspecialchars($alum['apellido'] . ", " . $alum['nombre']); ?></div></td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border"><?= strtoupper($alum['condicion_inscripcion']); ?></span>
                                            <input type="hidden" name="condicion[<?= $alum['id_alumno']; ?>]" value="<?= $alum['condicion_inscripcion']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" max="10" 
                                                name="notas[<?= $alum['id_alumno']; ?>]" 
                                                class="form-control nota-input shadow-sm" 
                                                value="<?= $alum['nota_final'] ?? ''; ?>" 
                                                <?= $mesa_cerrada ? 'readonly' : ''; ?>>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white p-4 text-end">
                        <a href="<?= BASE_URL ?>instancias/gestion-inscripciones" class="btn btn-outline-secondary rounded-pill px-4 me-2">Cancelar</a>
                        <?php if (!$mesa_cerrada): ?>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 shadow" id="btnGuardar">
                                <i class="fas fa-save me-1"></i> Guardar Acta Definitiva
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <?php else: ?>
                <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    $('#formLote').submit(function(e) {
        e.preventDefault();
        
        Swal.fire({
            title: '¿Confirmar Cierre de Acta?',
            text: "Se registrarán las notas y la mesa pasará al historial. No podrá editarse después.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Revisar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= BASE_URL ?>instancias/guardar-lote-examen',
                    type: 'POST',
                    data: $('#formLote').serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('¡Éxito!', res.message, 'success').then(() => {
                                window.location.href = '<?= BASE_URL ?>instancias/gestion-inscripciones';
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
</script>
</body>
</html>