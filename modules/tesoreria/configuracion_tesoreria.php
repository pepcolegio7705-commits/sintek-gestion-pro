<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Seguridad de acceso
verificar_permisos(['Administrador']);
$rol = $_SESSION['rol'];

// --- SEGURIDAD: Gestión de Token CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = "";

// ---------------------------------------------------------
// 1. PROCESAR ACCIONES DE TRAMOS DE ANTIGÜEDAD (AJAX)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion_tramo'])) {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'error' => 'Validación de seguridad fallida']);
        exit;
    }

    try {
        if ($_POST['accion_tramo'] === 'agregar') {
            $sql = "INSERT INTO configuracion_antiguedad_tramos (anios_desde, anios_hasta, porcentaje_aplicado) VALUES (?, ?, ?)";
            $pdo->prepare($sql)->execute([$_POST['desde'], $_POST['hasta'], $_POST['porc']]);
            echo json_encode(['success' => true]);
        } elseif ($_POST['accion_tramo'] === 'eliminar') {
            $sql = "DELETE FROM configuracion_antiguedad_tramos WHERE id_tramo = ?";
            $pdo->prepare($sql)->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------
// 2. PROCESAR ACTUALIZACIÓN GENERAL (POST)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_actualizar'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de seguridad: Validación de formulario fallida.");
    }

    try {
        $cbu      = preg_replace('/[^0-9]/', '', $_POST['cbu'] ?? '');
        $cuit     = preg_replace('/[^0-9]/', '', $_POST['cuit_institucion'] ?? '');
        $convenio = preg_replace('/[^0-9]/', '', $_POST['codigo_convenio'] ?? '');

        $sql_update = "UPDATE configuracion_tesoreria SET 
            limite_meses_mora = :limite,
            bloquear_inscripcion_sin_matricula = :bloq_ins,
            bloquear_examen_con_deuda = :bloq_exa,
            matricula_activa = :mat_activa,
            porcentaje_beca_max = :beca_max,
            cbu_institucion = :cbu,
            alias_institucion = :alias,
            titular_cuenta = :titular,
            cuit_institucion = :cuit,
            codigo_convenio = :convenio,
            tipo_archivo_banco = :t_banco,
            ciclo_lectivo_actual = :ciclo,
            cuotas_por_ciclo = :cuotas,
            valor_cuota_referencia = :v_cuota,
            valor_hora_catedra = :v_hora,
            porcentaje_jubilacion = :jub,
            porcentaje_obra_social = :os,
            tope_imponible_ley = :tope_ley,
            monto_asignacion_hijo = :m_hijo,
            monto_seguro_vida = :m_seguro,
            porcentaje_antiguedad_anual = :p_antig,
            porcentaje_zona_patagonica = :p_zona,
            porcentaje_presentismo = :p_pres,
            porcentaje_sindicato = :p_sind
            WHERE id_config_teso = 1";
        
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute([
            ':limite'   => (int)($_POST['limite_meses_mora'] ?? 0),
            ':bloq_ins' => isset($_POST['bloq_ins']) ? 1 : 0,
            ':bloq_exa' => isset($_POST['bloq_exa']) ? 1 : 0,
            ':mat_activa'=> isset($_POST['mat_activa']) ? 1 : 0,
            ':beca_max' => (int)($_POST['beca_max'] ?? 0),
            ':cbu'      => $cbu,
            ':alias'    => strip_tags(trim($_POST['alias'] ?? '')),
            ':titular'  => strip_tags(trim($_POST['titular'] ?? '')),
            ':cuit'     => $cuit,
            ':convenio' => $convenio,
            ':t_banco'  => $_POST['tipo_archivo_banco'] ?? 'BNA',
            ':ciclo'    => (int)($_POST['ciclo_lectivo'] ?? date('Y')),
            ':cuotas'   => (int)($_POST['cuotas_cycle'] ?? 10),
            ':v_cuota'  => (float)($_POST['valor_cuota'] ?? 0),
            ':v_hora'   => (float)($_POST['valor_hora_catedra'] ?? 0),
            ':jub'      => (float)($_POST['p_jubilacion'] ?? 0),
            ':os'       => (float)($_POST['p_obra_social'] ?? 0),
            ':tope_ley' => (float)($_POST['tope_imponible_ley'] ?? 0),
            ':m_hijo'   => (float)($_POST['m_hijo'] ?? 0),
            ':m_seguro' => (float)($_POST['m_seguro_vida'] ?? 0),
            ':p_antig'  => (float)($_POST['p_antiguedad'] ?? 0),
            ':p_zona'   => (float)($_POST['p_zona'] ?? 0),
            ':p_pres'   => (float)($_POST['p_presentismo'] ?? 0),
            ':p_sind'   => (float)($_POST['p_sindicato'] ?? 0)
        ]);

        $mensaje = "<div class='alert alert-success border-0 shadow-sm'><i class='fas fa-check-circle me-2'></i>Configuración institucional actualizada con éxito.</div>";
    } catch (Exception $e) {
        $mensaje = "<div class='alert alert-danger shadow-sm'><strong>Error:</strong> " . $e->getMessage() . "</div>";
    }
}

// ---------------------------------------------------------
// 3. CARGAR DATOS PARA MOSTRAR
// ---------------------------------------------------------
try {
    $stmt = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    $tramos = $pdo->query("SELECT * FROM configuracion_antiguedad_tramos ORDER BY anios_desde ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de lectura: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración Tesorería | Sintek</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <style>
        .form-section-title { font-size: 0.85rem; font-weight: 800; color: #4e73df; text-transform: uppercase; letter-spacing: 1.5px; }
        .card { border: none; border-radius: 12px; }
        .bg-haberes { background-color: #f8fff9; border: 1px solid #d1e7dd; }
        .bg-antiguedad { background-color: #fffaf0; border: 1px solid #ffeeba; }
        .bg-bna { background-color: #f4f6ff; border-left: 5px solid #4e73df; }
    </style>
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-university me-2 text-info"></i>Panel de Control Financiero</h5>
                        <span class="badge bg-info text-dark">Ciclo <?= $config['ciclo_lectivo_actual'] ?></span>
                    </div>
                    <div class="card-body p-4">
                        <?= $mensaje ?>

                        <form method="POST" action="<?= BASE_URL; ?>tesoreria/configuracion">
                            <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                            <h6 class="form-section-title mb-4 border-bottom pb-2">Políticas Académicas y Mora</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">Meses mora permitidos</label>
                                    <input type="number" name="limite_meses_mora" class="form-control" value="<?= $config['limite_meses_mora'] ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold small">Tope Beca (%)</label>
                                    <input type="number" name="beca_max" class="form-control" value="<?= $config['porcentaje_beca_max'] ?>">
                                </div>
                                <div class="col-md-6 d-flex align-items-center pt-3">
                                    <div class="form-check form-switch me-3">
                                        <input class="form-check-input" type="checkbox" name="mat_activa" id="mat_activa" <?= $config['matricula_activa'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small fw-bold" for="mat_activa">Matrícula Activa</label>
                                    </div>
                                    <div class="form-check form-switch me-3">
                                        <input class="form-check-input" type="checkbox" name="bloq_ins" id="ins" <?= $config['bloquear_inscripcion_sin_matricula'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="ins">Bloq. Inscr.</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="bloq_exa" id="exa" <?= $config['bloquear_examen_con_deuda'] ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="exa">Bloq. Examen</label>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-3 bg-haberes shadow-sm mb-4">
                                <h6 class="form-section-title mb-3 text-success">Cálculo de Haberes y Ley</h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Valor Hora Cátedra ($)</label>
                                        <input type="number" step="0.01" name="valor_hora_catedra" class="form-control" value="<?= $config['valor_hora_catedra'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Tope Imponible Ley ($)</label>
                                        <input type="number" step="0.01" name="tope_imponible_ley" class="form-control" value="<?= $config['tope_imponible_ley'] ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Jubilación (%)</label>
                                        <input type="number" step="0.01" name="p_jubilacion" class="form-control" value="<?= $config['porcentaje_jubilacion'] ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Obra Social (%)</label>
                                        <input type="number" step="0.01" name="p_obra_social" class="form-control" value="<?= $config['porcentaje_obra_social'] ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small fw-bold">Zona Patag. (%)</label>
                                        <input type="number" step="0.01" name="p_zona" class="form-control" value="<?= $config['porcentaje_zona_patagonica'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Asignación Hijo ($)</label>
                                        <input type="number" step="0.01" name="m_hijo" class="form-control" value="<?= $config['monto_asignacion_hijo'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Sindicato (%)</label>
                                        <input type="number" step="0.01" name="p_sindicato" class="form-control" value="<?= $config['porcentaje_sindicato'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold">Presentismo (%)</label>
                                        <input type="number" step="0.01" name="p_presentismo" class="form-control" value="<?= $config['porcentaje_presentismo'] ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small fw-bold text-muted">Antigüedad Manual (Ref)</label>
                                        <input type="number" step="0.01" name="p_antiguedad" class="form-control" value="<?= $config['porcentaje_antiguedad_anual'] ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-3 bg-antiguedad mb-4 shadow-sm border border-warning">
                                <h6 class="form-section-title mb-3 text-warning"><i class="fas fa-chart-line me-2"></i>Rangos de Antigüedad Dinámicos</h6>
                                <div class="row g-2 mb-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="small fw-bold">Años (Desde)</label>
                                        <input type="number" id="t_desde" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small fw-bold">Años (Hasta)</label>
                                        <input type="number" id="t_hasta" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small fw-bold">Porcentaje (%)</label>
                                        <input type="number" step="0.01" id="t_porc" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" onclick="agregarTramo()" class="btn btn-warning btn-sm w-100 fw-bold shadow-sm">Añadir Rango</button>
                                    </div>
                                </div>
                                <table class="table table-sm table-hover bg-white mb-0 border">
                                    <thead class="table-warning">
                                        <tr>
                                            <th>Rango</th>
                                            <th class="text-center">% Aplicado</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(empty($tramos)): ?>
                                            <tr><td colspan="3" class="text-center text-muted py-2">No hay tramos configurados.</td></tr>
                                        <?php else: foreach($tramos as $t): ?>
                                            <tr>
                                                <td class="ps-3">De <?= $t['anios_desde'] ?> a <?= $t['anios_hasta'] ?> años</td>
                                                <td class="text-center fw-bold text-primary"><?= $t['porcentaje_aplicado'] ?>%</td>
                                                <td class="text-center">
                                                    <button type="button" onclick="eliminarTramo(<?= $t['id_tramo'] ?>)" class="btn btn-link text-danger p-0"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h6 class="form-section-title mb-3">Entorno Bancario e Institucional</h6>
                            <div class="row g-3 p-3 bg-bna rounded-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Titular de la Cuenta</label>
                                    <input type="text" name="titular" class="form-control" value="<?= $config['titular_cuenta'] ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Alias Institucional</label>
                                    <input type="text" name="alias" class="form-control" value="<?= $config['alias_institucion'] ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">CUIT Institución</label>
                                    <input type="text" name="cuit_institucion" class="form-control" value="<?= $config['cuit_institucion'] ?>" maxlength="11">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">CBU Principal (22 dígitos)</label>
                                    <input type="text" name="cbu" class="form-control" value="<?= $config['cbu_institucion'] ?>" maxlength="22">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Formato Exportación</label>
                                    <select name="tipo_archivo_banco" class="form-select">
                                        <option value="BNA" <?= $config['tipo_archivo_banco'] == 'BNA' ? 'selected' : '' ?>>Banco Nación</option>
                                        <option value="CHUBUT" <?= $config['tipo_archivo_banco'] == 'CHUBUT' ? 'selected' : '' ?>>Banco del Chubut</option>
                                        <option value="INTB" <?= $config['tipo_archivo_banco'] == 'INTB' ? 'selected' : '' ?>>Interbanking</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Cod. Convenio</label>
                                    <input type="text" name="codigo_convenio" class="form-control" value="<?= $config['codigo_convenio'] ?>">
                                </div>
                            </div>

                            <h6 class="form-section-title mb-3 text-secondary">Parámetros de Ciclo Lectivo</h6>
                            <div class="row g-3 p-3 bg-light rounded-3 border mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Ciclo Lectivo</label>
                                    <input type="number" name="ciclo_lectivo" class="form-control" value="<?= $config['ciclo_lectivo_actual'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Cuotas por Ciclo</label>
                                    <input type="number" name="cuotas_cycle" class="form-control" value="<?= $config['cuotas_por_ciclo'] ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Valor Cuota Referencia ($)</label>
                                    <input type="number" step="0.01" name="valor_cuota" class="form-control" value="<?= $config['valor_cuota_referencia'] ?>">
                                </div>
                            </div>

                            <button type="submit" name="btn_actualizar" class="btn btn-primary btn-lg w-100 shadow">
                                <i class="fas fa-save me-2"></i>Aplicar Cambios en Configuración
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    function agregarTramo() {
        const desde = $('#t_desde').val();
        const hasta = $('#t_hasta').val();
        const porc = $('#t_porc').val();
        const token = $('#csrf_token').val();

        if(!desde || !hasta || !porc) {
            Swal.fire('Atención', 'Todos los campos del tramo son obligatorios.', 'warning');
            return;
        }

        $.post('', { accion_tramo: 'agregar', desde, hasta, porc, csrf_token: token }, function(res) {
            if(res.success) location.reload();
            else Swal.fire('Error', res.error, 'error');
        }, 'json');
    }

    function eliminarTramo(id) {
        Swal.fire({
            title: '¿Eliminar rango?',
            text: "Esta acción modificará los futuros cálculos de antigüedad.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                const token = $('#csrf_token').val();
                $.post('', { accion_tramo: 'eliminar', id, csrf_token: token }, function(res) {
                    if(res.success) location.reload();
                }, 'json');
            }
        });
    }
    </script>
    <?php include '../../vistas/footer.php'; ?>
</body>
</html>