<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
require_once '../../core/funciones.php'; // Necesario para BASE_URL y otras utilidades

// Verificamos permisos
verificar_permisos(['Administrador', 'Tesorero']);

/**
 * LÓGICA DE URL AMIGABLE:
 * El .htaccess envía el parámetro 'uuid'.
 */
$uuid_agente = $_GET['uuid'] ?? null;
$tipo_agente = 'Profesor'; // Este archivo es específico para Profesores

if (!$uuid_agente) {
    die("Acceso denegado. UUID de profesor no especificado.");
}

try {
    // Buscamos al profesor por su UUID para obtener su ID interno y datos
    $stmt = $pdo->prepare("SELECT id_profesor, apellido, nombre, dni, cuil, fecha_ingreso, legajo 
                           FROM profesores WHERE uuid_profesor = ?");
    $stmt->execute([$uuid_agente]);
    $agente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agente) {
        throw new Exception("El profesor solicitado no existe en la base de datos.");
    }

    $id_real_agente = $agente['id_profesor'];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Haberes | <?= htmlspecialchars($agente['apellido']) ?></title>
    <link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .card-header-agente { background: #1e293b; color: white; border-radius: 12px !important; }
        .stat-card { border: none; border-radius: 12px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: #f8fafc; }
    </style>
</head>
<body class="bg-light">

<?php include '../../vistas/nav.php'; ?>

<div class="container py-4">
    <div class="card card-header-agente shadow-sm mb-4 border-0">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <i class="fas fa-chalkboard-teacher fa-lg"></i>
                        </div>
                        <div>
                            <h3 class="mb-0 fw-bold"><?= mb_strtoupper($agente['apellido']) ?>, <?= $agente['nombre'] ?></h3>
                            <span class="badge bg-info-subtle text-info">PROFESOR - Legajo: <?= $agente['legajo'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                    <p class="mb-2 small opacity-75">
                        <i class="fas fa-fingerprint me-1"></i> DNI: <?= $agente['dni'] ?> | 
                        <i class="fas fa-id-card-alt me-1"></i> CUIL: <?= $agente['cuil'] ?? 'S/D' ?>
                    </p>
                    <a href="<?= BASE_URL; ?>profesores_abm" class="btn btn-outline-light btn-sm px-3">
                        <i class="fas fa-arrow-left me-1"></i> Volver al Listado
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-success-subtle rounded-3 me-3 text-success"><i class="fas fa-sack-dollar fa-2x"></i></div>
                    <div>
                        <small class="text-muted fw-bold d-block">TOTAL NETO (AÑO ACTUAL)</small>
                        <h3 class="mb-0 fw-bold text-success" id="total_anual">$ 0,00</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-primary-subtle rounded-3 me-3 text-primary"><i class="fas fa-chart-line fa-2x"></i></div>
                    <div>
                        <small class="text-muted fw-bold d-block">PROMEDIO MENSUAL</small>
                        <h3 class="mb-0 fw-bold text-primary" id="promedio_mensual">$ 0,00</h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm p-3">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-dark-subtle rounded-3 me-3 text-dark"><i class="fas fa-file-invoice-dollar fa-2x"></i></div>
                    <div>
                        <small class="text-muted fw-bold d-block">RECIBOS EMITIDOS</small>
                        <h3 class="mb-0 fw-bold" id="cant_recibos">0</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-white">
                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>Historial de Haberes - Profesores</h5>
            </div>
            <div class="table-responsive p-3">
                <table id="tablaRecibos" class="table table-hover w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th class="text-end">Bruto</th>
                            <th class="text-end text-danger">Descuentos</th>
                            <th class="text-end fw-bold">Neto Cobrado</th>
                            <th>Lote / Fecha Pago</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    const BASE_URL = '<?= BASE_URL; ?>';
    
    $('#tablaRecibos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            // CAMBIO AQUÍ: Apuntamos al nuevo archivo específico
            "url": BASE_URL + "ajax/tesoreria/obtener_recibos_profesor.php",
            "type": "POST",
            "data": { 
                id: "<?= $id_real_agente ?>"
            }
        },
        "columns": [
            { "data": "periodo" },
            { "data": "bruto", "className": "text-end" },
            { "data": "descuentos", "className": "text-end text-danger" },
            { "data": "neto", "className": "text-end fw-bold" },
            { "data": "info_pago" },
            { "data": "acciones", "className": "text-center" }
        ],
        "order": [[0, "desc"]],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
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