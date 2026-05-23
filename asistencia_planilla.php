<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

// 1. Verificación de seguridad
if (!isset($_SESSION['loggedin'])) { header('Location: login.php'); exit; }

$rol = $_SESSION['rol']; 

// 2. Captura de parámetros
$id_espacio = isset($_GET['id_espacio']) ? (int)$_GET['id_espacio'] : 0;
$id_carrera = isset($_GET['id_carrera']) ? (int)$_GET['id_carrera'] : 0;
$fecha      = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if ($id_espacio === 0) { die("Error: Espacio curricular no especificado."); }

try {
    // 3. Obtener información del encabezado
    $info_sql = "SELECT e.nombre_espacio, c.nombre_carrera 
                 FROM espacios_curriculares e 
                 JOIN carreras c ON e.id_carrera = c.id_carrera 
                 WHERE e.id_espacio = ?";
    $stmt_info = $pdo->prepare($info_sql);
    $stmt_info->execute([$id_espacio]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    // 4. Obtener alumnos Y asistencia existente
    $sql = "SELECT a.id_alumno, a.apellido, a.nombre, a.dni, 
                   ast.estado, ast.observacion
            FROM alumnos a 
            INNER JOIN inscripciones_espacios i ON a.id_alumno = i.id_alumno 
            LEFT JOIN asistencias_clases ast ON (a.id_alumno = ast.id_alumno 
                                                AND ast.id_espacio = ? 
                                                AND ast.fecha = ?)
            WHERE i.id_espacio = ? AND a.activo = 1
            ORDER BY a.apellido ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_espacio, $fecha, $id_espacio]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $es_edicion = false;
    foreach ($alumnos as $al) {
        if ($al['estado'] !== null) {
            $es_edicion = true;
            break;
        }
    }

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla de Asistencia | SEC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; padding-bottom: 80px; }
        .card { border-radius: 15px; border: none; }
        .table thead th { background-color: #0dcaf0; color: white; border: none; font-size: 0.9rem; }
        /* Estilo para los radio buttons tipo botón en BS5 */
        .btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
        .btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
        .btn-check:checked + .btn-outline-warning { background-color: #ffc107; color: white; }
        .row-edit { border-left: 5px solid #0dcaf0; }
    </style>
</head>
<body>
    <?php include 'vistas/nav.php'; ?>

    <div class="container mt-4">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <h3 class="fw-bold text-dark mb-1">Asistencia de Alumnos</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item text-info fw-bold"><?= htmlspecialchars($info['nombre_carrera']) ?></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($info['nombre_espacio']) ?></li>
                    </ol>
                </nav>
                <span class="badge bg-light text-dark border mt-2">
                    <i class="fas fa-calendar-day me-1 text-primary"></i> <?= date('d/m/Y', strtotime($fecha)) ?>
                </span>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="asistencia_inicio.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> Volver
                </a>
            </div>
        </div>

        <?php if ($es_edicion): ?>
            <div class="alert alert-info d-flex align-items-center shadow-sm row-edit border-0" role="alert">
                <i class="fas fa-info-circle me-3 fa-lg"></i>
                <div>
                    <strong>Modo Edición:</strong> Ya existen registros para esta fecha. Los cambios sobrescribirán los datos anteriores.
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm overflow-hidden">
            <form action="asistencia_procesar.php" method="POST">
                <input type="hidden" name="id_espacio" value="<?= $id_espacio ?>">
                <input type="hidden" name="id_carrera" value="<?= $id_carrera ?>">
                <input type="hidden" name="fecha" value="<?= $fecha ?>">
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-center">
                                <th class="text-start ps-4">Apellido y Nombre</th>
                                <th style="width: 250px;">Estado de Asistencia</th>
                                <th class="pe-4">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $al): 
                                $id_al = $al['id_alumno'];
                                $estado_actual = $al['estado'] ?? 'PRESENTE'; 
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold"><?= htmlspecialchars($al['apellido'] . ", " . $al['nombre']) ?></div>
                                    <div class="text-muted small">DNI: <?= $al['dni'] ?></div>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group w-100" role="group" aria-label="Selector asistencia">
                                        <input type="radio" class="btn-check" name="asistencia[<?= $id_al ?>]" id="p_<?= $id_al ?>" value="PRESENTE" autocomplete="off" <?= ($estado_actual == 'PRESENTE') ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success btn-sm" for="p_<?= $id_al ?>">P</label>

                                        <input type="radio" class="btn-check" name="asistencia[<?= $id_al ?>]" id="a_<?= $id_al ?>" value="AUSENTE" autocomplete="off" <?= ($estado_actual == 'AUSENTE') ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger btn-sm" for="a_<?= $id_al ?>">A</label>

                                        <input type="radio" class="btn-check" name="asistencia[<?= $id_al ?>]" id="t_<?= $id_al ?>" value="TARDE" autocomplete="off" <?= ($estado_actual == 'TARDE') ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-warning btn-sm" for="t_<?= $id_al ?>">T</label>
                                    </div>
                                </td>
                                <td class="pe-4">
                                    <input type="text" name="obs[<?= $id_al ?>]" 
                                           class="form-control form-control-sm border-0 bg-light" 
                                           placeholder="Nota opcional..." 
                                           value="<?= htmlspecialchars($al['observacion'] ?? '') ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-white border-top-0 p-4">
                    <button type="submit" class="btn btn-info btn-lg w-100 shadow-sm text-white fw-bold">
                        <i class="fas fa-save me-2"></i> 
                        <?= $es_edicion ? 'Actualizar Registros' : 'Guardar Asistencia' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php include 'vistas/footer.php'; ?>
</body>
</html>