<?php
session_start();
// Ajuste de rutas relativas para subir a la raíz desde modules/tesoreria/
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

$rol = $_SESSION['rol'];
verificar_permisos(['Administrador', 'Tesoreria']);

// 1. OBTENER REGLAS DE CONFIGURACIÓN
try {
    $stmt_conf = $pdo->query("SELECT limite_meses_mora, cuotas_por_ciclo FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt_conf->fetch(PDO::FETCH_ASSOC);
    
    $limite_permitido = $config['limite_meses_mora'] ?? 2;
    $total_cuotas_config = $config['cuotas_por_ciclo'] ?? 10; 

    $mes_actual = date('n');
    // Lógica: Se esperan cuotas de Marzo (3) a Diciembre (12)
    $cuotas_esperadas = ($mes_actual >= 3) ? ($mes_actual - 2) : 0; 
} catch (PDOException $e) {
    die("Error al cargar configuración: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Mora | Sintek Premium</title>
    <link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .kpi-card { border: none; border-radius: 12px; transition: all 0.3s; border-left: 5px solid transparent; }
        .status-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .table-danger-light { background-color: #fff1f2 !important; }
        .text-xs { font-size: 0.7rem; font-weight: bold; }
        .btn-whatsapp { color: #fff; background-color: #25d366; border-color: #25d366; }
        .btn-whatsapp:hover { color: #fff; background-color: #128c7e; }
        .concepto-detalle { font-size: 0.65rem; color: #3b82f6; font-weight: bold; display: block; line-height: 1.2; margin-top: 2px; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4 align-items-end g-3">
            <div class="col-md-5">
                <h3 class="fw-bold text-dark mb-1"><i class="fas fa-hand-holding-usd me-2 text-danger"></i>Seguimiento de Morosidad</h3>
                <p class="text-secondary small mb-0">Gestión de cobranza basada en el límite de <strong><?php echo $limite_permitido; ?> meses</strong>.</p>
            </div>
            
            <div class="col-md-7">
                <div class="row g-2">
                    <div class="col-sm-4">
                        <div class="card kpi-card bg-white p-2 border-primary shadow-sm">
                            <small class="text-muted text-uppercase fw-bold text-xs">Cuotas Ciclo</small>
                            <h4 class="mb-0 fw-bold"><?php echo $cuotas_esperadas; ?> <small class="text-muted fw-normal fs-6">/ <?php echo $total_cuotas_config; ?></small></h4>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card kpi-card bg-white p-2 border-warning shadow-sm">
                            <small class="text-muted text-uppercase fw-bold text-xs">Estado Global</small>
                            <h4 class="mb-0 fw-bold fs-5 text-warning">Monitoreo Activo</h4>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="card kpi-card bg-danger text-white p-2 shadow-sm">
                            <small class="text-uppercase fw-bold text-xs" style="opacity: 0.8;">Criterio Mora</small>
                            <h5 class="mb-0 fw-bold">> <?php echo $limite_permitido; ?> meses</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-custom bg-white">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaMoraServer" class="table table-hover align-middle w-100">
                        <thead>
                            <tr class="text-secondary border-bottom">
                                <th class="text-xs">ALUMNO / CARRERA</th>
                                <th class="text-xs">DNI</th>
                                <th class="text-xs text-center">PAGOS</th>
                                <th class="text-xs text-center">DEUDA</th>
                                <th class="text-xs text-center">ÚLT. MOVIMIENTO</th>
                                <th class="text-xs text-center">ESTADO</th>
                                <th class="text-xs text-end">GESTIÓN</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../vistas/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaMoraServer').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "<?= BASE_URL; ?>tesoreria/ajax_mora",
                    "type": "GET",
                    "error": function (xhr, error, code) {
                        console.log("Error en AJAX:", xhr.responseText); // Para ver errores de PHP en consola
                    }
                },
                "columns": [
                    { "data": "alumno" },
                    { "data": "dni" },
                    { "data": "pagas" },
                    { "data": "meses_deuda" },
                    { "data": "ultimo_pago" },
                    { "data": "estado_badge" },
                    { "data": "acciones" }
                ],
                "columnDefs": [
                    { "className": "text-center", "targets": [2, 3, 4, 5] },
                    { "className": "text-end", "targets": 6 },
                    { "orderable": false, "targets": [4, 5, 6] }
                ],
                "order": [[0, "asc"]],
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                "createdRow": function(row, data, dataIndex) {
                    // Si el motor detecta mora crítica (true), pintamos la fila
                    if (data.mora_critica === true || data.mora_critica === 1) {
                        $(row).addClass('table-danger-light');
                    }
                }
            });
        });
    </script>
</body>
</html>