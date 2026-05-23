<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesoreria']);
$rol = $_SESSION['rol'];

// 1. CAPTURA POR UUID (Seguridad)
$uuid_alumno = $_GET['uuid'] ?? null;
if (!$uuid_alumno) { header("Location: alumnos_mora.php"); exit; }

$nombre_meses = [
    1 => "Enero", 2 => "Febrero", 3 => "Marzo", 4 => "Abril", 
    5 => "Mayo", 6 => "Junio", 7 => "Julio", 8 => "Agosto", 
    9 => "Septiembre", 10 => "Octubre", 11 => "Noviembre", 12 => "Diciembre"
];

// 2. DATOS DEL ALUMNO
$stmt = $pdo->prepare("SELECT * FROM alumnos WHERE uuid_alumno = ?");
$stmt->execute([$uuid_alumno]);
$alumno = $stmt->fetch();

if (!$alumno) { die("Alumno no encontrado."); }
$id_alumno = $alumno['id_alumno'];

// 3. CONFIGURACIÓN DE TESORERÍA
$conf = $pdo->query("SELECT ciclo_lectivo_actual, cuotas_por_ciclo, titular_cuenta, cbu_institucion, alias_institucion FROM configuracion_tesoreria WHERE id_config_teso = 1")->fetch();
$ciclo_activo = $conf['ciclo_lectivo_actual'] ?? date('Y');
$total_cuotas_definidas = $conf['cuotas_por_ciclo'] ?? 11;

// 4. CARRERAS DEL ALUMNO
$stmt_car = $pdo->prepare("SELECT c.id_carrera, c.nombre_carrera, ac.cohorte, cp.monto_sugerido 
                            FROM alumnos_carreras ac 
                            JOIN carreras c ON ac.id_carreras = c.id_carrera 
                            LEFT JOIN conceptos_pago cp ON cp.id_carrera = c.id_carrera AND cp.categoria = 'Mensualidad' AND cp.activo = 1
                            WHERE ac.id_alumno = ?");
$stmt_car->execute([$id_alumno]);
$carreras_alumno = $stmt_car->fetchAll();

// 5. HISTORIAL DETALLADO
$sql_historial = "SELECT f.fecha_emision, fd.monto_cobrado, mp.nombre_modo, 
                         cp.nombre_concepto, cp.categoria, fd.mes_correspondiente, 
                         fd.anio_lectivo, f.nro_transaccion, c.nombre_carrera, f.uuid_factura
                  FROM facturas f
                  JOIN factura_detalle fd ON f.id_factura = fd.id_factura
                  JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                  JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
                  LEFT JOIN carreras c ON cp.id_carrera = c.id_carrera
                  WHERE f.id_alumno = :id_hist AND f.estado = 'Pagado'
                  ORDER BY f.fecha_emision DESC";
$stmt_h = $pdo->prepare($sql_historial);
$stmt_h->execute([':id_hist' => $id_alumno]);
$historial = $stmt_h->fetchAll();

// 6. TOTAL HISTÓRICO
$stmt_t = $pdo->prepare("SELECT SUM(total) FROM facturas WHERE id_alumno = :id_total AND estado = 'Pagado'");
$stmt_t->execute([':id_total' => $id_alumno]);
$total_historico = $stmt_t->fetchColumn() ?? 0;

// --- LÓGICA DE DEUDA PARA WHATSAPP ---
$mes_actual = (int)date('n'); 
$nombre_completo = $alumno['apellido'] . ", " . $alumno['nombre'];
$total_deuda_global = 0;
$cant_meses_deuda = 0;

foreach($carreras_alumno as $c) {
    $st_m = $pdo->prepare("SELECT fd.mes_correspondiente FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto WHERE f.id_alumno = ? AND cp.id_carrera = ? AND cp.categoria = 'Mensualidad' AND f.estado = 'Pagado' AND fd.anio_lectivo = ?");
    $st_m->execute([$id_alumno, $c['id_carrera'], $ciclo_activo]);
    $pagados = $st_m->fetchAll(PDO::FETCH_COLUMN);
    
    // De Febrero (2) a Mayo (5)
    for ($m = 2; $m <= $mes_actual; $m++) {
        if (!in_array($m, $pagados)) {
            $cant_meses_deuda++;
            $total_deuda_global += ($c['monto_sugerido'] ?? 0);
        }
    }
}

$mensaje_wa = "✅ *ESTADO DE CUENTA DIGITAL - SINTEK*\n\n";
$mensaje_wa .= "Alumno: *{$nombre_completo}*\n";
$mensaje_wa .= "Total Deuda: *$" . number_format($total_deuda_global, 2, ',', '.') . "*\n\n";
$mensaje_wa .= "--- DATOS DE PAGO ---\n";
$mensaje_wa .= "Alias: *{$conf['alias_institucion']}*\n";
$mensaje_wa .= "CBU: {$conf['cbu_institucion']}\n";
$mensaje_wa .= "--------------------\n\n";
$mensaje_wa .= "Por favor, adjunte el comprobante una vez realizado el pago.";

$tel_limpio = preg_replace('/[^0-9]/', '', $alumno['telefono'] ?? '');
$url_whatsapp = "https://wa.me/54" . $tel_limpio . "?text=" . urlencode($mensaje_wa);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha Financiera | <?= htmlspecialchars($alumno['apellido']) ?></title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .card-carrera { border-top: 5px solid #0d6efd; border-radius: 12px; }
        .info-contacto { font-size: 0.85rem; color: #64748b; line-height: 1.6; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>
    <div class="container py-4">
        
        <div class="d-flex justify-content-between mb-4 no-print">
            <a href="<?= BASE_URL ?>tesoreria/mora" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-arrow-left me-1"></i> Volver</a>
            <a href="<?= BASE_URL ?>tesoreria/ficha-pdf/<?= $uuid_alumno ?>" target="_blank" class="btn btn-danger shadow-sm"><i class="fas fa-file-pdf me-1"></i> Exportar Historial</a>
        </div>

        <div class="card shadow-sm border-0 p-4">
            <div class="row mb-4 border-bottom pb-3 align-items-center">
                <div class="col-md-6">
                    <h2 class="fw-bold text-primary mb-0">ESTADO DE CUENTA</h2>
                    <h4 class="mb-1 text-dark"><?= htmlspecialchars($nombre_completo) ?></h4>
                    <div class="info-contacto">
                        <i class="fas fa-id-card me-1"></i> DNI: <?= $alumno['dni'] ?> | <i class="fas fa-user-tag me-1"></i> Legajo: <?= $alumno['legajo'] ?><br>
                        <i class="fas fa-phone me-1"></i> <strong>Tel:</strong> <?= $alumno['telefono'] ?? 'S/D' ?>
                    </div>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <div class="p-3 bg-light rounded border">
                        <small class="text-muted fw-bold d-block text-uppercase">Total Acumulado</small>
                        <h3 class="fw-bold mb-0 text-dark">$ <?= number_format($total_historico, 2, ',', '.') ?></h3>
                    </div>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <a href="<?= $url_whatsapp; ?>" target="_blank" class="btn btn-success w-100 shadow-sm py-3" style="background-color: #25D366; border-color: #128C7E; font-weight: bold;">
                        <i class="fab fa-whatsapp me-2"></i> Notificar Deuda
                    </a>
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-secondary"><i class="fas fa-calendar-check me-2"></i>Estado de Cuotas - Ciclo <?= $ciclo_activo ?></h5>
            <div class="row mb-4">
                <?php foreach($carreras_alumno as $c): ?>
                    <?php
                    $st_m = $pdo->prepare("SELECT fd.mes_correspondiente FROM factura_detalle fd JOIN facturas f ON fd.id_factura = f.id_factura JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto WHERE f.id_alumno = ? AND cp.id_carrera = ? AND cp.categoria = 'Mensualidad' AND f.estado = 'Pagado' AND fd.anio_lectivo = ? ORDER BY fd.mes_correspondiente ASC");
                    $st_m->execute([$id_alumno, $c['id_carrera'], $ciclo_activo]);
                    $pagados = $st_m->fetchAll(PDO::FETCH_COLUMN);
                    $meses_deuda_display = [];
                    for ($m = 2; $m <= $mes_actual; $m++) {
                        if (!in_array($m, $pagados)) { $meses_deuda_display[] = $nombre_meses[$m]; }
                    }
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="card card-carrera h-100 shadow-sm border-0 bg-white">
                            <div class="card-body">
                                <h6 class="fw-bold text-uppercase text-primary small mb-2"><?= $c['nombre_carrera'] ?></h6>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted small">Cuotas pagadas:</span>
                                    <span class="h5 mb-0 fw-bold"><?= count($pagados) ?> / <?= $total_cuotas_definidas ?></span>
                                </div>
                                <?php if(!empty($meses_deuda_display)): ?>
                                    <div class="alert alert-danger py-2 border-0 mb-0">
                                        <small class="d-block fw-bold mb-1 text-uppercase" style="font-size:0.65rem;">Pendientes:</small>
                                        <?php foreach($meses_deuda_display as $md): ?>
                                            <span class="badge bg-danger mb-1"><?= $md ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-success py-2 border-0 mb-0 small">
                                        <i class="fas fa-check-circle me-1"></i> Al día hasta <?= $nombre_meses[$mes_actual] ?>.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h5 class="fw-bold mb-3 text-secondary"><i class="fas fa-history me-2"></i>Historial Detallado</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Fecha</th>
                            <th>Carrera</th> 
                            <th>Concepto</th>
                            <th class="text-center">Modo</th>
                            <th class="text-end">Monto</th>
                            <th class="text-center no-print">Acción</th>
                        </tr>
                    </thead>
                   <tbody class="small">
                        <?php foreach($historial as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($h['fecha_emision'])) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($h['nombre_carrera'] ?? 'GENERAL') ?></td>
                            <td>
                                <span class="fw-bold"><?= htmlspecialchars($h['nombre_concepto']) ?></span>
                                <?php if($h['categoria'] == 'Mensualidad'): ?>
                                    <span class="badge bg-light text-dark border ms-1"><?= $nombre_meses[(int)$h['mes_correspondiente']] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center small"><?= $h['nombre_modo'] ?></td>
                            <td class="text-end fw-bold text-dark">$ <?= number_format($h['monto_cobrado'], 2, ',', '.') ?></td>
                            <td class="text-center no-print">
                                <a href="<?= BASE_URL ?>tesoreria/imprimir/<?= $h['uuid_factura'] ?>" target="_blank" class="text-danger">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>