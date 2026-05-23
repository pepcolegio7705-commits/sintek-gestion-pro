<?php
    session_start();
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php'; 

    verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);
    $rol = $_SESSION['rol'];

    $uuid_carrera = $_GET['carrera'] ?? '';
    $uuid_espacio = $_GET['espacio'] ?? '';

    if (empty($uuid_carrera) || empty($uuid_espacio)) {
        die("Error: Parámetros de seguridad insuficientes.");
    }

    try {
        // 1. Obtener info del encabezado y IDs internos
        $info_sql = "SELECT c.id_carrera, c.nombre_carrera, e.id_espacio, e.nombre_espacio, e.anio_cursada 
                    FROM espacios_curriculares e 
                    JOIN carreras c ON e.id_carrera = c.id_carrera 
                    WHERE e.uuid_espacio = ? AND c.uuid_carrera = ?";
        
        $stmt_info = $pdo->prepare($info_sql);
        $stmt_info->execute([$uuid_espacio, $uuid_carrera]);
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if (!$info) die("Error: Espacio o carrera no válidos.");

        $id_espacio = $info['id_espacio'];
        $ciclo_actual = date('Y');

        // 2. Verificar Estado de la Planilla (Control de apertura/cierre)
        $stmt_check = $pdo->prepare("SELECT estado FROM control_planillas WHERE id_espacio = ? AND ciclo_lectivo = ?");
        $stmt_check->execute([$id_espacio, $ciclo_actual]);
        $estado_planilla = $stmt_check->fetchColumn() ?: 'Abierta';

        // Lógica de Permisos de Edición
        $puede_editar = ($rol === 'Administrador') || ($rol === 'Profesor' && $estado_planilla === 'Abierta');
        $readonly = !$puede_editar ? 'disabled' : '';

        // 3. Obtener alumnos desde cursadas_notas
        $alumnos_sql = "SELECT a.id_alumno, a.apellido, a.nombre, a.dni, 
                               cn.parcial_1, cn.parcial_2, cn.recuperatorio, cn.nota_final_cursada, cn.asistencia, cn.id_condicion
                        FROM alumnos a
                        INNER JOIN cursadas_notas cn ON a.id_alumno = cn.id_alumno
                        WHERE cn.id_espacio = ? AND cn.ciclo_lectivo = ?
                        ORDER BY a.apellido, a.nombre";

        $stmt_alumnos = $pdo->prepare($alumnos_sql);
        $stmt_alumnos->execute([$id_espacio, $ciclo_actual]);
        $alumnos = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);

        $condiciones = $pdo->query("SELECT * FROM condiciones_alumno ORDER BY id_condicion")->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Error de sistema: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla Cursada - <?= htmlspecialchars($info['nombre_espacio']) ?></title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary-dark: #003366; --accent: #007bff; }
        body { background-color: #f4f7f6; }
        .table thead th { background-color: var(--primary-dark); color: white; font-size: 0.75rem; vertical-align: middle; }
        .input-nota { width: 70px; text-align: center; border-radius: 5px; border: 1px solid #ddd; padding: 5px; font-weight: bold; background-color: #fff; }
        .input-nota:disabled { background-color: #e9ecef; color: #6c757d; }
        .input-asistencia { width: 65px; text-align: center; border-radius: 5px; border: 1px solid #ddd; }
        .fila-alumno:hover { background-color: #fdfdfe !important; }
        .status-badge { font-size: 0.7rem; padding: 5px 10px; border-radius: 20px; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0 fw-bold text-primary">
                            <?= htmlspecialchars($info['nombre_espacio']) ?> 
                            <?php if($estado_planilla === 'Cerrada'): ?>
                                <span class="badge bg-danger status-badge ms-2"><i class="fas fa-lock me-1"></i> PLANILLA CERRADA</span>
                            <?php else: ?>
                                <span class="badge bg-success status-badge ms-2"><i class="fas fa-unlock me-1"></i> ABIERTA</span>
                            <?php endif; ?>
                        </h5>
                        <small class="text-muted"><?= htmlspecialchars($info['nombre_carrera']) ?> | Ciclo <?= $ciclo_actual ?></small>
                    </div>
                    <div class="col-md-6 text-end">
                        <?php if ($estado_planilla === 'Cerrada' && $rol === 'Administrador'): ?>
                            <button type="button" onclick="gestionarPlanilla('reabrir')" class="btn btn-warning fw-bold btn-sm shadow-sm me-2">
                                <i class="fas fa-key me-2"></i>REABRIR PLANILLA
                            </button>
                        <?php endif; ?>

                        <?php if ($puede_editar): ?>
                            <button type="button" onclick="guardarPlanilla()" class="btn btn-primary fw-bold btn-sm shadow-sm me-2">
                                <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
                            </button>
                            <button type="button" onclick="gestionarPlanilla('cerrar')" class="btn btn-danger fw-bold btn-sm shadow-sm">
                                <i class="fas fa-folder-minus me-2"></i>FINALIZAR Y CERRAR
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <form id="formPlanilla">
                    <input type="hidden" name="id_espacio" value="<?= $id_espacio ?>">
                    <input type="hidden" name="ciclo_lectivo" value="<?= $ciclo_actual ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead>
                                <tr>
                                    <th class="text-start ps-4">ESTUDIANTE</th>
                                    <th width="100">1° PARCIAL</th>
                                    <th width="100">2° PARCIAL</th>
                                    <th width="100">RECUP.</th>
                                    <th width="100">FINAL</th>
                                    <th width="100">ASIST. %</th>
                                    <th width="200">CONDICIÓN FINAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($alumnos): foreach ($alumnos as $al): ?>
                                <tr class="fila-alumno">
                                    <td class="text-start ps-4">
                                        <div class="fw-bold text-uppercase small"><?= htmlspecialchars($al['apellido'].", ".$al['nombre']) ?></div>
                                        <div class="text-muted small" style="font-size:0.7rem;">DNI: <?= $al['dni'] ?></div>
                                    </td>
                                    <td><input type="number" step="0.01" name="alum[<?= $al['id_alumno'] ?>][p1]" value="<?= $al['parcial_1'] ?>" class="input-nota" <?= $readonly ?>></td>
                                    <td><input type="number" step="0.01" name="alum[<?= $al['id_alumno'] ?>][p2]" value="<?= $al['parcial_2'] ?>" class="input-nota" <?= $readonly ?>></td>
                                    <td><input type="number" step="0.01" name="alum[<?= $al['id_alumno'] ?>][rec]" value="<?= $al['recuperatorio'] ?>" class="input-nota" <?= $readonly ?>></td>
                                    <td>
                                        <input type="number" step="0.01" 
                                            name="alum[<?= $al['id_alumno'] ?>][final]" 
                                            value="<?= $al['nota_final_cursada'] ?>" 
                                            class="input-nota nota-final-dinamica" 
                                            data-alumno="<?= $al['id_alumno'] ?>"
                                            oninput="calcularCondicionAutomatica(this)"
                                            <?= $readonly ?>>
                                    </td>
                                    <td><input type="number" name="alum[<?= $al['id_alumno'] ?>][asist]" value="<?= $al['asistencia'] ?>" class="input-asistencia" min="0" max="100" <?= $readonly ?>></td>
                                    <td>
                                        <select name="alum[<?= $al['id_alumno'] ?>][cond]" 
                                                id="cond-<?= $al['id_alumno'] ?>" 
                                                class="form-select form-select-sm fw-bold selector-condicion" 
                                                <?= $readonly ?>>
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach($condiciones as $c): ?>
                                                <option value="<?= $c['id_condicion'] ?>" 
                                                    <?php 
                                                        // IMPORTANTE: Comparamos el valor de la base de datos ($al) 
                                                        // con el ID de la opción actual ($c)
                                                        if ($al['id_condicion'] == $c['id_condicion']) {
                                                            echo 'selected';
                                                        }
                                                    ?>>
                                                    <?= htmlspecialchars($c['nombre_condicion']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="p-5 text-muted">No hay alumnos inscriptos en este ciclo.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function guardarPlanilla() {
            Swal.fire({ title: 'Guardando...', didOpen: () => { Swal.showLoading(); } });
            $.post('<?= BASE_URL ?>calificaciones/procesar-cursada', $('#formPlanilla').serialize(), function(r) {
                Swal.fire(r.status === 'success' ? '¡Éxito!' : 'Error', r.message, r.status);
            }, 'json');
        }

        function gestionarPlanilla(accion) {
            let config = {
                title: accion === 'cerrar' ? '¿Cerrar Planilla?' : '¿Reabrir Planilla?',
                text: accion === 'cerrar' ? 'Esto bloqueará la edición de notas para el docente.' : 'Se habilitará nuevamente la carga para el docente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar'
            };

            Swal.fire(config).then((result) => {
                if (result.isConfirmed) {
                    $.post('<?= BASE_URL ?>calificaciones/control-planilla', { 
                        action: accion, 
                        id_espacio: <?= $id_espacio ?>,
                        ciclo: <?= $ciclo_actual ?>
                    }, function(r) {
                        if(r.status === 'success') {
                            location.reload();
                        } else {
                            Swal.fire('Error', r.message, 'error');
                        }
                    }, 'json');
                }
            });
        }

        // Navegación con Enter
        $('.input-nota, .input-asistencia').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                let inputs = $(this).closest('form').find(':input:visible:not([disabled])');
                inputs.eq( inputs.index(this) + 1 ).focus();
            }
        });

        function calcularCondicionAutomatica(input) {
            const nota = parseFloat(input.value);
            const idAlumno = $(input).data('alumno');
            const selectorCondicion = $(`#cond-${idAlumno}`);

            if (isNaN(nota) || input.value === '') {
                input.style.color = 'black';
                selectorCondicion.val(''); // Sin selección
                return;
            }

            if (nota >= 7) {
                // PROMOCIONADO
                input.style.color = '#28a745'; // Verde
                selectorCondicion.val('3');    
            } else if (nota === 6) {
                // REGULAR
                input.style.color = '#007bff'; // Azul
                selectorCondicion.val('2');    
            } else {
                // LIBRE (Menos de 6)
                input.style.color = '#dc3545'; // Rojo
                selectorCondicion.val('4');    
            }
        }
    </script>
</body>
</html>