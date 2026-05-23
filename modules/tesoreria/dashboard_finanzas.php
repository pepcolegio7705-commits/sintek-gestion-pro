<?php
session_start();
require '../../core/conexion.php';
require '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesoreria']);
$rol = $_SESSION['rol'];

// Filtros de fecha
$mes_actual = isset($_GET['m']) ? (int)$_GET['m'] : date('m');
$anio_actual = isset($_GET['a']) ? (int)$_GET['a'] : date('Y');

// --- 1. RECAUDACIÓN REAL (Ingresos por Facturas) ---
$stmt_caja = $pdo->prepare("SELECT SUM(total) as total_mes FROM facturas 
                            WHERE estado = 'Pagado' AND MONTH(fecha_emision) = :mes AND YEAR(fecha_emision) = :anio");
$stmt_caja->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$recaudacion_mes = $stmt_caja->fetchColumn() ?? 0;

// --- 2. EGRESOS OPERATIVOS (Tabla Gastos) ---
$stmt_gastos = $pdo->prepare("SELECT SUM(monto) as total FROM gastos 
                              WHERE estado = 'Pagado' AND MONTH(fecha_gasto) = :mes AND YEAR(fecha_gasto) = :anio");
$stmt_gastos->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$total_gastos_mes = $stmt_gastos->fetchColumn() ?? 0;

// --- 3. EGRESOS SALARIALES (Profesores y Staff) CORREGIDO A 'Procesado' ---
$stmt_sueldos = $pdo->prepare("SELECT SUM(monto_neto) as total_sueldos FROM liquidaciones_haberes 
                               WHERE estado = 'Procesado' 
                               AND mes_liquidado = :mes AND anio_liquidado = :anio");
$stmt_sueldos->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$total_sueldos_mes = $stmt_sueldos->fetchColumn() ?? 0;

// --- 4. PASIVO LABORAL (Sueldos Pendientes de Pago) ---
$stmt_deuda_sueldos = $pdo->prepare("SELECT SUM(monto_neto) as deuda FROM liquidaciones_haberes 
                                     WHERE estado = 'Pendiente' AND mes_liquidado = :mes AND anio_liquidado = :anio");
$stmt_deuda_sueldos->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$deuda_sueldos_mes = $stmt_deuda_sueldos->fetchColumn() ?? 0;

// --- 5. LÍQUIDO REAL (Caja actual disponible) ---
$egresos_totales = $total_gastos_mes + $total_sueldos_mes;
$liquido_real = $recaudacion_mes - $egresos_totales;

// --- 6. BECAS Y BRUTO ---
$stmt_becas_det = $pdo->prepare("SELECT SUM(fd.monto_descuento) as total_beca FROM factura_detalle fd
                                 JOIN facturas f ON fd.id_factura = f.id_factura
                                 WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :mes AND YEAR(f.fecha_emision) = :anio
                                 AND fd.monto_descuento > 0");
$stmt_becas_det->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$total_ayuda_becas = $stmt_becas_det->fetchColumn() ?? 0;
$bruto_institucional = $recaudacion_mes + $total_ayuda_becas;

// --- 7. MORA CRÍTICA Y CONFIGURACIONES ---
$stmt_conf = $pdo->query("SELECT limite_meses_mora, ciclo_lectivo_actual, cuotas_por_ciclo, valor_cuota_referencia FROM configuracion_tesoreria LIMIT 1");
$config_teso = $stmt_conf->fetch(PDO::FETCH_ASSOC);
$limite_mora = $config_teso['limite_meses_mora'] ?? 3;
$ciclo_activo = $config_teso['ciclo_lectivo_actual'] ?? date('Y');
$valor_ref = $config_teso['valor_cuota_referencia'] ?? 0;
$cuotas_totales = $config_teso['cuotas_por_ciclo'] ?? 10;

// Top 5 Morosos Críticos
$stmt_top = $pdo->prepare("SELECT a.id_alumno, a.nombre, a.apellido, a.dni, (:mes_actual - COUNT(DISTINCT fd.mes_correspondiente)) as meses_deuda 
                           FROM alumnos a 
                           LEFT JOIN facturas f ON a.id_alumno = f.id_alumno AND f.estado = 'Pagado' 
                           LEFT JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
                           LEFT JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto AND cp.categoria = 'Mensualidad' 
                           WHERE a.activo = 1 AND fd.anio_lectivo = :anio 
                           GROUP BY a.id_alumno HAVING meses_deuda > :limite ORDER BY meses_deuda DESC LIMIT 5");
$stmt_top->execute([':mes_actual' => $mes_actual, ':limite' => $limite_mora, ':anio' => $anio_actual]);
$top_morosos = $stmt_top->fetchAll(PDO::FETCH_ASSOC);

// --- 8. DATOS PARA GRÁFICOS ---
$stmt_carr = $pdo->prepare("SELECT c.nombre_carrera, SUM(fd.monto_cobrado) as total_carrera FROM factura_detalle fd 
                            JOIN facturas f ON fd.id_factura = f.id_factura 
                            JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto 
                            JOIN carreras c ON cp.id_carrera = c.id_carrera 
                            WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :mes AND YEAR(f.fecha_emision) = :anio 
                            GROUP BY c.id_carrera");
$stmt_carr->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$res_carreras = $stmt_carr->fetchAll(PDO::FETCH_ASSOC);

$stmt_salud = $pdo->prepare("SELECT cp.categoria, SUM(fd.monto_cobrado) as total_cobrado, SUM(fd.monto_descuento) as total_becas 
                             FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura 
                             JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto 
                             WHERE f.estado = 'Pagado' AND MONTH(f.fecha_emision) = :mes AND YEAR(f.fecha_emision) = :anio 
                             GROUP BY cp.categoria");
$stmt_salud->execute([':mes' => $mes_actual, ':anio' => $anio_actual]);
$res_salud = $stmt_salud->fetchAll(PDO::FETCH_ASSOC);

$labels_salud = array_column($res_salud, 'categoria');
$data_ingresos = array_column($res_salud, 'total_cobrado');
$data_becas = array_column($res_salud, 'total_becas');

// --- 9. ÚLTIMOS MOVIMIENTOS ---
$stmt_recientes = $pdo->query("SELECT f.nro_factura, a.apellido, f.total, mp.nombre_modo FROM facturas f JOIN alumnos a ON f.id_alumno = a.id_alumno JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo WHERE f.estado = 'Pagado' ORDER BY f.fecha_emision DESC LIMIT 5");
$recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

// Últimos movimientos de sueldos CORREGIDO A 'Procesado' y JOIN optimizado
$sql_ultimos_sueldos = "SELECT lh.id_liquidacion, lh.monto_neto, lh.tipo_persona, 
                        COALESCE(p.apellido, s.apellido, 'S/D') as apellido,
                        COALESCE(p.nombre, s.nombre, '') as nombre
                        FROM liquidaciones_haberes lh 
                        LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor') 
                        LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona != 'Profesor') 
                        WHERE lh.estado = 'Procesado' 
                        ORDER BY lh.id_liquidacion DESC LIMIT 5";
$stmt_ultimos_sueldos = $pdo->query($sql_ultimos_sueldos);
$ultimos_sueldos = $stmt_ultimos_sueldos->fetchAll(PDO::FETCH_ASSOC);

// --- 10. CÁLCULOS ANUALES (Con estado 'Procesado' para sueldos) ---
$recaudacion_anio = $pdo->query("SELECT SUM(total) FROM facturas WHERE estado = 'Pagado' AND YEAR(fecha_emision) = $anio_actual")->fetchColumn() ?? 0;
$total_gastos_anio = $pdo->query("SELECT SUM(monto) FROM gastos WHERE estado = 'Pagado' AND YEAR(fecha_gasto) = $anio_actual")->fetchColumn() ?? 0;
$total_sueldos_anio = $pdo->query("SELECT SUM(monto_neto) FROM liquidaciones_haberes WHERE estado = 'Procesado' AND anio_liquidado = $anio_actual")->fetchColumn() ?? 0;
$total_becas_anio = $pdo->query("SELECT SUM(fd.monto_descuento) FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura WHERE f.estado = 'Pagado' AND YEAR(f.fecha_emision) = $anio_actual")->fetchColumn() ?? 0;

$bruto_anual = $recaudacion_anio + $total_becas_anio;
$liquido_anual = $recaudacion_anio - ($total_gastos_anio + $total_sueldos_anio);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Financiero | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .kpi-card { border: none; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.3s ease; height: 100%; }
        .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .main-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; }
        .card-title-custom { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid px-lg-5 py-4">
        <div class="row mb-4">
            <div class="col-md-7">
                <h2 class="fw-bold text-dark"><i class="fas fa-chart-pie me-2 text-primary"></i>Panel Financiero</h2>
            </div>
            <div class="col-md-5 d-flex justify-content-md-end align-items-center">
                <form method="GET" class="d-flex gap-2 bg-white p-2 rounded shadow-sm w-100">
                    <select name="m" class="form-select form-select-sm">
                        <?php for ($i = 1; $i <= 12; $i++) {
                            echo "<option value='$i' " . ($i == $mes_actual ? 'selected' : '') . ">" . date("F", mktime(0, 0, 0, $i, 10)) . "</option>";
                        } ?>
                    </select>
                   <select name="a" class="form-select form-select-sm">
                        <?php 
                        $anio_ini = date('Y') - 1;
                        for($i = $anio_ini; $i <= $anio_ini + 2; $i++): ?>
                            <option value="<?= $i ?>" <?= ($i == $anio_actual) ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm px-3">Filtrar</button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card kpi-card p-3 border-start border-success border-5">
                    <p class="card-title-custom text-success mb-1">Ingresos (Caja)</p>
                    <div class="main-value">$<?php echo number_format($recaudacion_mes, 0, ',', '.'); ?></div>
                    <small class="text-muted">Facturación Cobrada</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-danger border-5">
                    <p class="card-title-custom text-danger mb-1">Egresos (Sueldos)</p>
                    <div class="main-value">$<?php echo number_format($total_sueldos_mes, 0, ',', '.'); ?></div>
                    <small class="text-muted">Nómina Procesada</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-warning border-5">
                    <p class="card-title-custom text-warning mb-1">Egresos (Gastos)</p>
                    <div class="main-value">$<?php echo number_format($total_gastos_mes, 0, ',', '.'); ?></div>
                    <small class="text-muted">Operativos / Compras</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card p-3 border-start border-primary border-5" style="background-color: #f0f9ff;">
                    <p class="card-title-custom text-primary mb-1">Líquido Real</p>
                    <div class="main-value text-primary">$<?php echo number_format($liquido_real, 0, ',', '.'); ?></div>
                    <small class="text-muted">Disponibilidad Neta</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-danger border-5 bg-danger bg-opacity-10">
                    <p class="card-title-custom text-danger mb-1">Pasivo Laboral</p>
                    <div class="main-value text-danger">$<?php echo number_format($deuda_sueldos_mes, 0, ',', '.'); ?></div>
                    <small class="text-danger fw-bold"><i class="fas fa-exclamation-circle"></i> Sueldos Pendientes</small>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card kpi-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-success mb-0"><i class="fas fa-arrow-down me-2"></i>Últimos Ingresos</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light"><tr><th>Nro</th><th>Alumno</th><th>Método</th><th class="text-end pe-3">Monto</th></tr></thead>
                            <tbody>
                                <?php foreach ($recientes as $r): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?php echo $r['nro_factura']; ?></td>
                                    <td><?php echo $r['apellido']; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo $r['nombre_modo']; ?></span></td>
                                    <td class="text-end pe-3 fw-bold text-success">+$<?php echo number_format($r['total'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card kpi-card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-danger mb-0"><i class="fas fa-arrow-up me-2"></i>Últimas Liquidaciones Procesadas</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light"><tr><th>ID Liq.</th><th>Agente</th><th>Tipo</th><th class="text-end pe-3">Neto Pagado</th></tr></thead>
                            <tbody>
                                <?php foreach ($ultimos_sueldos as $s): ?>
                                <tr>
                                    <td class="ps-3 fw-bold">#<?php echo str_pad($s['id_liquidacion'], 5, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $s['apellido'] . ', ' . $s['nombre']; ?></td>
                                    <td><span class="badge <?php echo $s['tipo_persona'] == 'Staff' ? 'bg-primary' : 'bg-secondary'; ?>"><?php echo $s['tipo_persona']; ?></span></td>
                                    <td class="text-end pe-3 fw-bold text-danger">-$<?php echo number_format($s['monto_neto'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12"><h6 class="text-muted fw-bold text-uppercase" style="font-size: 0.75rem;"><i class="fas fa-calendar-check me-1"></i> Resumen Acumulado Año <?= $anio_actual ?></h6></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-primary border-5 bg-white">
                    <p class="card-title-custom text-primary mb-1">Bruto Anual</p>
                    <div class="main-value" style="font-size: 1.2rem;">$<?php echo number_format($bruto_anual, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-info border-5 bg-white">
                    <p class="card-title-custom text-info mb-1">Becas (Inversión)</p>
                    <div class="main-value" style="font-size: 1.2rem;">$<?php echo number_format($total_becas_anio, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card kpi-card p-3 border-start border-success border-5 bg-white">
                    <p class="card-title-custom text-success mb-1">Recaudación Real</p>
                    <div class="main-value" style="font-size: 1.2rem;">$<?php echo number_format($recaudacion_anio, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card p-3 border-start border-danger border-5 bg-white">
                    <p class="card-title-custom text-danger mb-1">Egresos Totales (Gastos+Nómina)</p>
                    <div class="main-value" style="font-size: 1.2rem;">$<?php echo number_format($total_gastos_anio + $total_sueldos_anio, 0, ',', '.'); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kpi-card p-3 border-start border-dark border-5 bg-light">
                    <p class="card-title-custom text-dark mb-1">Saldo Caja Acumulado</p>
                    <div class="main-value text-dark">$<?php echo number_format($liquido_anual, 0, ',', '.'); ?></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card kpi-card p-4">
                    <h6 class="fw-bold text-secondary mb-4">Eficiencia: Ingreso Real vs. Inversión en Becas</h6>
                    <div style="height: 300px;"><canvas id="chartSaludFinanciera"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card kpi-card p-4">
                    <h6 class="fw-bold text-secondary mb-4 text-center">Participación por Carrera</h6>
                    <div style="height: 300px;"><canvas id="chartCarreras"></canvas></div>
                </div>
            </div>
        </div>

    </div>

    <?php include '../../vistas/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // GRÁFICO 1: Ingresos vs Becas
        new Chart(document.getElementById('chartSaludFinanciera'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels_salud); ?>,
                datasets: [
                    { label: 'Ingreso Real (Caja)', data: <?php echo json_encode($data_ingresos); ?>, backgroundColor: '#10b981', borderRadius: 5 },
                    { label: 'Inversión en Becas', data: <?php echo json_encode($data_becas); ?>, backgroundColor: '#3b82f6', borderRadius: 5 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
        });

        // GRÁFICO 2: Carreras
        new Chart(document.getElementById('chartCarreras'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($res_carreras, 'nombre_carrera')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($res_carreras, 'total_carrera')); ?>,
                    backgroundColor: ['#003366', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    hoverOffset: 15
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
        });
    </script>
</body>
</html>