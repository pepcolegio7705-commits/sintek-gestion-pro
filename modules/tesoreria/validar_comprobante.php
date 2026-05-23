<?php
// IMPORTANTE: Solo requerimos la conexión. 
// No incluimos seguridad.php porque el QR debe ser escaneable por personal desde afuera.
require_once '../../core/conexion.php';

$uuid = $_GET['uuid'] ?? null;

if (!$uuid) {
    die("Error: Identificador de comprobante ausente.");
}

// Consulta blindada para verificar la factura
$stmt = $pdo->prepare("SELECT f.*, a.nombre, a.apellido, a.dni, a.uuid_alumno, mp.nombre_modo 
                       FROM facturas f 
                       INNER JOIN alumnos a ON f.id_alumno = a.id_alumno 
                       INNER JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
                       WHERE f.uuid_factura = ?");
$stmt->execute([$uuid]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);

// Si existe, traemos el detalle (opcional, para dar más veracidad)
$detalles = [];
if ($f) {
    $stmt_d = $pdo->prepare("SELECT fd.*, cp.nombre_concepto, c.nombre_carrera 
                             FROM factura_detalle fd
                             INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
                             LEFT JOIN carreras c ON cp.id_carrera = c.id_carrera
                             WHERE fd.id_factura = ?");
    $stmt_d->execute([$f['id_factura']]);
    $detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Comprobante | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-validador { border: none; border-radius: 20px; overflow: hidden; }
        .status-header { padding: 40px 20px; }
        .data-row { border-bottom: 1px solid #eee; padding: 12px 0; }
        .data-row:last-child { border-bottom: none; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            
            <?php if ($f && $f['estado'] === 'Pagado'): ?>
                <div class="card card-validador shadow-lg">
                    <div class="status-header bg-success text-white text-center">
                        <i class="fas fa-check-circle fa-5x mb-3 animate__animated animate__bounceIn"></i>
                        <h2 class="fw-bold">PAGO VERIFICADO</h2>
                        <p class="mb-0 opacity-75">El comprobante es auténtico</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="data-row">
                            <small class="text-muted d-block text-uppercase">Alumno</small>
                            <span class="fw-bold fs-5 text-dark"><?= $f['apellido'] . ", " . $f['nombre'] ?></span>
                        </div>
                        <div class="data-row">
                            <small class="text-muted d-block text-uppercase">DNI</small>
                            <span class="fw-bold"><?= $f['dni'] ?></span>
                        </div>
                        <div class="data-row">
                            <small class="text-muted d-block text-uppercase">Concepto Registrado</small>
                            <?php foreach($detalles as $d): ?>
                                <span class="d-block fw-bold text-primary">
                                    <?= $d['nombre_concepto'] ?> 
                                    <?= $d['mes_correspondiente'] ? "({$d['mes_correspondiente']}/{$d['anio_lectivo']})" : "" ?>
                                </span>
                                <small class="text-muted"><?= $d['nombre_carrera'] ?></small>
                            <?php endforeach; ?>
                        </div>
                        <div class="data-row">
                            <small class="text-muted d-block text-uppercase">Fecha de Pago</small>
                            <span class="fw-bold"><?= date('d/m/Y H:i', strtotime($f['fecha_emision'])) ?> hs</span>
                        </div>
                        <div class="data-row">
                            <small class="text-muted d-block text-uppercase">Modo de Pago / Nro Operación</small>
                            <span class="fw-bold"><?= $f['nombre_modo'] ?></span>
                            <?= $f['nro_transaccion'] ? "<br><span class='badge bg-light text-dark border'>#{$f['nro_transaccion']}</span>" : "" ?>
                        </div>
                        <div class="mt-4 p-3 bg-success bg-opacity-10 border border-success rounded-3 text-center">
                            <h3 class="fw-bold text-success mb-0">$ <?= number_format($f['total'], 2, ',', '.') ?></h3>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card card-validador shadow-lg">
                    <div class="status-header bg-danger text-white text-center">
                        <i class="fas fa-times-circle fa-5x mb-3"></i>
                        <h2 class="fw-bold">NO VERIFICADO</h2>
                        <p class="mb-0 opacity-75">Comprobante inexistente o anulado</p>
                    </div>
                    <div class="card-body p-5 text-center">
                        <p class="text-muted">El código escaneado no coincide con ningún registro de pago activo en nuestra base de datos institucional.</p>
                        <hr>
                        <small class="text-muted">Si considera que esto es un error, por favor contacte a Tesorería con el recibo físico.</small>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <img src="../img/logo.png" width="80" class="opacity-50 mb-2">
                <p class="small text-muted">Sintek Gestión Pro &copy; <?= date('Y') ?></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>