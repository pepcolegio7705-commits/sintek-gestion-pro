<?php
    session_start();
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);
    $rol = $_SESSION['rol']; 

    $uuid_espacio = $_GET['uuid_espacio'] ?? null;
    $fecha = $_GET['fecha'] ?? date('Y-m-d');

    if (!$uuid_espacio) {
        header('Location: ' . BASE_URL . 'asistencias-gestion/inicio');
        exit;
    }

    try {
        // 1. Obtener datos del Espacio y Carrera
        $sql_e = "SELECT e.id_espacio, e.nombre_espacio, c.id_carrera, c.nombre_carrera 
                  FROM espacios_curriculares e
                  JOIN carreras c ON e.id_carrera = c.id_carrera
                  WHERE e.uuid_espacio = ?";
        $stmt_e = $pdo->prepare($sql_e);
        $stmt_e->execute([$uuid_espacio]);
        $espacio = $stmt_e->fetch(PDO::FETCH_ASSOC);

        if (!$espacio) die("Espacio curricular no encontrado.");
        
        $id_espacio = $espacio['id_espacio'];
        $id_carrera = $espacio['id_carrera'];

        // 2. Verificar si la asistencia YA FUE CARGADA
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM asistencias_clases WHERE id_espacio = ? AND fecha = ?");
        $stmt_check->execute([$id_espacio, $fecha]);
        $ya_existe = ($stmt_check->fetchColumn() > 0);

        // Lógica de permisos de edición
        $es_hoy = ($fecha == date('Y-m-d'));
        // El profesor puede pedir habilitar si es hoy. Admin/Secretaría siempre pueden.
        $puede_editar_hoy = ($ya_existe && $rol == 'Profesor' && $es_hoy);
        $solo_lectura = ($ya_existe && !in_array($rol, ['Administrador', 'Secretaría']));

        // 3. Obtener alumnos y sus estados previos (usando marcadores únicos para evitar errores HY093)
        $sql_alu = "SELECT a.id_alumno, a.dni, a.apellido, a.nombre,
                        (SELECT estado FROM asistencias_clases 
                            WHERE id_alumno = a.id_alumno 
                            AND id_espacio = :id_e1 
                            AND fecha = :f1 LIMIT 1) as asistencia_previa,
                        (SELECT observacion FROM asistencias_clases 
                            WHERE id_alumno = a.id_alumno 
                            AND id_espacio = :id_e2 
                            AND fecha = :f2 LIMIT 1) as observacion_previa
                    FROM alumnos a
                    INNER JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno
                    WHERE ac.id_carreras = :id_c 
                    AND a.activo = 1
                    ORDER BY a.apellido, a.nombre ASC";

        $stmt_alu = $pdo->prepare($sql_alu);
        $stmt_alu->execute([
            ':id_e1' => $id_espacio,
            ':f1'    => $fecha,
            ':id_e2' => $id_espacio,
            ':f2'    => $fecha,
            ':id_c'  => $id_carrera
        ]);
        $alumnos = $stmt_alu->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        die("Error crítico: " . $e->getMessage());
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Asistencia | Sintek</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .header-asistencia {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white; border-radius: 12px; padding: 20px;
        }
        .table-asistencia thead th { background: #f8f9fa; color: #475569; font-size: 0.8rem; text-transform: uppercase; }
        .btn-check:checked + .btn-outline-success { background-color: #198754; color: white; }
        .btn-check:checked + .btn-outline-warning { background-color: #f59e0b; color: white; border-color: #f59e0b; }
        .btn-check:checked + .btn-outline-info { background-color: #0ea5e9; color: white; border-color: #0ea5e9; }
        .btn-check:checked + .btn-outline-danger { background-color: #dc3545; color: white; }
        .disabled-row { opacity: 0.7; pointer-events: none; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid px-lg-5 mt-4 mb-5">
        <div class="header-asistencia shadow-sm mb-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($espacio['nombre_espacio']) ?></h4>
                    <p class="mb-0 opacity-75 small text-uppercase fw-bold"><?= htmlspecialchars($espacio['nombre_carrera']) ?></p>
                </div>
                <div class="col-md-5 text-md-end border-start border-white border-opacity-25">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> <?= date('d/m/Y', strtotime($fecha)) ?></h5>
                    <?php if ($ya_existe): ?>
                        <span class="badge bg-warning text-dark rounded-pill"><i class="fas fa-lock me-1"></i> ASISTENCIA CERRADA</span>
                    <?php else: ?>
                        <span class="badge bg-success rounded-pill"><i class="fas fa-unlock me-1"></i> ESPERANDO CARGA</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($solo_lectura): ?>
            <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-info-circle me-2"></i> <strong>Modo Consulta:</strong> La asistencia ya fue registrada.
                </div>
                <?php if ($puede_editar_hoy): ?>
                    <button type="button" id="btnHabilitarEdicion" class="btn btn-sm btn-dark rounded-pill px-3">
                        <i class="fas fa-edit me-1"></i> Corregir hoy
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form id="formAsistencia">
            <input type="hidden" name="uuid_espacio" value="<?= $uuid_espacio ?>">
            <input type="hidden" name="fecha" value="<?= $fecha ?>">

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 table-asistencia">
                            <thead>
                                <tr>
                                    <th class="ps-4">DNI</th>
                                    <th>Alumno</th>
                                    <th class="text-center">Estado</th>
                                    <th>Observación / Justificativo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos as $alu): 
                                    $estado = $alu['asistencia_previa']; 
                                ?>
                                <tr class="<?= $solo_lectura ? 'disabled-row' : '' ?>">
                                    <td class="ps-4 text-muted small"><?= $alu['dni'] ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($alu['apellido'] . ", " . $alu['nombre']) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            
                                            <input type="radio" class="btn-check" name="asistencia[<?= $alu['id_alumno'] ?>]" 
                                                id="p_<?= $alu['id_alumno'] ?>" value="P" 
                                                <?= ($estado == 'PRESENTE' || is_null($estado)) ? 'checked' : '' ?> <?= $solo_lectura ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-success" for="p_<?= $alu['id_alumno'] ?>">P</label>

                                            <input type="radio" class="btn-check" name="asistencia[<?= $alu['id_alumno'] ?>]" 
                                                id="t_<?= $alu['id_alumno'] ?>" value="T" 
                                                <?= ($estado == 'TARDE') ? 'checked' : '' ?> <?= $solo_lectura ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-warning" for="t_<?= $alu['id_alumno'] ?>">T</label>

                                            <input type="radio" class="btn-check" name="asistencia[<?= $alu['id_alumno'] ?>]" 
                                                id="aj_<?= $alu['id_alumno'] ?>" value="AJ" 
                                                <?= ($estado == 'JUSTIFICADO') ? 'checked' : '' ?> <?= $solo_lectura ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-info" for="aj_<?= $alu['id_alumno'] ?>">AJ</label>

                                            <input type="radio" class="btn-check" name="asistencia[<?= $alu['id_alumno'] ?>]" 
                                                id="a_<?= $alu['id_alumno'] ?>" value="A" 
                                                <?= ($estado == 'AUSENTE') ? 'checked' : '' ?> <?= $solo_lectura ? 'disabled' : '' ?>>
                                            <label class="btn btn-outline-danger" for="a_<?= $alu['id_alumno'] ?>">A</label>
                                            
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="observacion[<?= $alu['id_alumno'] ?>]" 
                                            class="form-control form-control-sm border-0 bg-light" 
                                            placeholder="Nota o motivo..." 
                                            value="<?= htmlspecialchars($alu['observacion_previa'] ?? '') ?>"
                                            <?= $solo_lectura ? 'readonly' : '' ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white p-4 border-0 border-top text-end" id="footerAcciones">
                    <a href="<?= BASE_URL ?>gestion-asistencias/inicio" class="btn btn-light rounded-pill px-4 me-2">Volver</a>
                    <?php if (!$solo_lectura): ?>
                        <button type="submit" class="btn btn-primary rounded-pill px-5 shadow fw-bold">
                            <i class="fas fa-save me-2"></i> <?= $ya_existe ? 'ACTUALIZAR ASISTENCIA' : 'GUARDAR ASISTENCIA' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Función para habilitar edición en caliente
        $('#btnHabilitarEdicion').on('click', function() {
            $('.disabled-row').removeClass('disabled-row');
            $('input[type="radio"]').prop('disabled', false);
            $('input[type="text"]').prop('readonly', false);
            
            if($('#btnSubmitEdit').length === 0) {
                $('#footerAcciones').append(`
                    <button type="submit" id="btnSubmitEdit" class="btn btn-warning rounded-pill px-5 shadow fw-bold">
                        <i class="fas fa-save me-2"></i> GUARDAR CAMBIOS
                    </button>
                `);
            }
            $(this).parent().fadeOut();
        });

        $('#formAsistencia').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            
            Swal.fire({
                title: '¿Confirmar registro?',
                text: "Se guardarán los estados de asistencia para esta fecha.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Revisar'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Guardando...');
                    $.ajax({
                        url: '<?= BASE_URL ?>api/asistencias/guardar',
                        type: 'POST',
                        data: $(this).serialize(),
                        dataType: 'json',
                        success: function(res) {
                            if(res.success) {
                                Swal.fire('¡Éxito!', res.message, 'success').then(() => {
                                    window.location.href = '<?= BASE_URL ?>gestion-asistencias/inicio';
                                });
                            } else {
                                Swal.fire('Error', res.message, 'error');
                                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i> GUARDAR');
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>