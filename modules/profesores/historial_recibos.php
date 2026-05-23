<?php
/**
 * HISTORIAL DE HABERES - VERSIÓN BLINDADA UUID
 * Ubicación: /modules/profesores/historial_recibos.php
 */
session_start();

require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);
$rol = $_SESSION['rol']; 

// 1. CAPTURA DE UUID (Soporte híbrido por si el .htaccess falla en inyectar el GET)
$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    // Intento de rescate manual de la URL
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', rtrim($path, '/'));
    $uuid = end($segments);
}

// Limpieza básica
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower($uuid));

if (!$uuid || strlen($uuid) < 30) {
    registrar_log_seguridad($pdo, 'ACCESO_HISTORIAL_SIN_UUID', 'Intento de acceso al historial sin identificador válido.');
    die("Acceso denegado. El identificador de agente no es válido.");
}

// 2. BUSQUEDA DEL AGENTE POR UUID
$tipo_agente = 'Profesor';
$stmt = $pdo->prepare("SELECT id_profesor as id_int, apellido, nombre, dni, cuil, fecha_ingreso FROM profesores WHERE uuid_profesor = ? LIMIT 1");
$stmt->execute([$uuid]);
$agente = $stmt->fetch();

if (!$agente) {
    $tipo_agente = 'Staff';
    $stmt = $pdo->prepare("SELECT id_staff as id_int, apellido, nombre, dni, cuil, fecha_ingreso FROM personal_staff WHERE uuid_staff = ? LIMIT 1");
    $stmt->execute([$uuid]);
    $agente = $stmt->fetch();
}

if (!$agente) {
    registrar_log_seguridad($pdo, 'HISTORIAL_UUID_INVALIDO', "Intento de acceso a historial inexistente: $uuid");
    die("El agente solicitado no existe.");
}

registrar_log_seguridad($pdo, 'CONSULTA_HISTORIAL_PAGOS', "Se consultó el historial de haberes de: " . $agente['apellido'] . " (" . $tipo_agente . ")");

// Normalizamos el BASE_URL para evitar problemas de dobles barras
$ajax_url = rtrim(BASE_URL, '/') . '/profesores/recibos/' . $uuid;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial - <?= htmlspecialchars($agente['apellido']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<?php include '../../vistas/nav.php'; ?>

<div class="container py-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-dark text-white rounded">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-0"><?= strtoupper(htmlspecialchars($agente['apellido'])) ?>, <?= htmlspecialchars($agente['nombre']) ?></h3>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-id-card me-1"></i> DNI: <?= $agente['dni'] ?> | 
                        <i class="fas fa-university me-1"></i> CUIL: <?= $agente['cuil'] ?? 'S/D' ?> |
                        <i class="fas fa-calendar-alt me-1"></i> Ingreso: <?= ($agente['fecha_ingreso']) ? date('d/m/Y', strtotime($agente['fecha_ingreso'])) : '---' ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-primary fs-6"><?= $tipo_agente ?></span>
                    <a href="<?= rtrim(BASE_URL, '/') ?>/profesores/certificacion/<?= $uuid ?>" 
                       target="_blank" class="btn btn-danger btn-sm ms-2">
                        <i class="fas fa-file-contract me-1"></i> Certificación Anual
                    </a>
                    <a href="<?= BASE_URL ?>profesores/gestion" class="btn btn-outline-light btn-sm ms-2">Volver</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted fw-bold">TOTAL NETO (AÑO ACTUAL)</small>
                <h2 class="text-success" id="total_anual">$ 0,00</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted fw-bold">PROMEDIO MENSUAL</small>
                <h2 class="text-primary" id="promedio_mensual">$ 0,00</h2>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center p-3">
                <small class="text-muted fw-bold">CANTIDAD RECIBOS</small>
                <h2 class="text-dark" id="cant_recibos">0</h2>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table id="tablaRecibos" class="table table-hover w-100 align-middle">
                <thead class="bg-light">
                    <tr>
                        <th>Periodo</th>
                        <th>Bruto</th>
                        <th>Descuentos</th>
                        <th>Neto Cobrado</th>
                        <th>Lote/Fecha Pago</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaRecibos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "<?= BASE_URL ?>profesores/recibos/<?= $uuid ?>",
            "type": "POST"
        },
        "columns": [
            { "data": "periodo" },
            { "data": "bruto" },
            { "data": "descuentos" },
            { "data": "neto" },
            { "data": "info_pago" },
            { "data": "acciones", "className": "text-center" }
        ],
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        "drawCallback": function(settings) {
            if(settings.json && settings.json.stats) {
                $('#total_anual').text(settings.json.stats.total);
                $('#promedio_mensual').text(settings.json.stats.promedio);
                $('#cant_recibos').text(settings.json.stats.cantidad);
            }
        }
    });
});
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>