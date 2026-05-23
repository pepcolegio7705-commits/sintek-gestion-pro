<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesorería']);
$rol = $_SESSION['rol'];

// Consulta para el KPI lateral (Gasto del mes actual)
$mes_actual = date('m');
$anio_actual = date('Y');
$stmt_resumen = $pdo->prepare("SELECT SUM(monto) as total FROM gastos WHERE estado != 'Anulado' AND MONTH(fecha_gasto) = :m AND YEAR(fecha_gasto) = :a");
$stmt_resumen->execute([':m' => $mes_actual, ':a' => $anio_actual]);
$gasto_total_mes = $stmt_resumen->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Generar Token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Egresos | Sintek Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.025em; }
        .input-group-text { background-color: #f8fafc; border-right: none; color: #94a3b8; }
        .form-control, .form-select { border-left: none; background-color: #f8fafc; border-radius: 8px; }
        .btn-primary { background-color: #2563eb; border: none; font-weight: 600; transition: all 0.2s; }
        .btn-primary:hover { background-color: #1d4ed8; transform: translateY(-1px); }
        .stats-card { background: #fff; border-left: 5px solid #ef4444; }
        .table thead th { font-size: 0.75rem; text-transform: uppercase; background-color: #f8fafc; color: #64748b; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-slate-800 mb-0"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Libro de Egresos</h3>
                <p class="text-muted small mb-0">Control contable de salidas de caja y haberes.</p>
            </div>
            <a href="<?= BASE_URL ?>tesoreria/dashboard" class="btn btn-outline-secondary rounded-pill btn-sm px-3">
                <i class="fas fa-chart-line me-1"></i> Dashboard
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4">
                    <h5 class="fw-bold mb-4">Registrar Nuevo Movimiento</h5>
                    <form id="formGasto" action="procesar_gasto.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Fecha de Comprobante</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="fecha_gasto" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Categoría / Rubro</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-tags text-primary"></i>
                                    </span>
                                    <select class="form-select border-start-0" name="id_cat_gasto" id="id_cat_gasto" required>
                                        <option value="">-- Seleccione un rubro --</option>
                                        <?php
                                        // Consultamos las categorías directamente de la base de datos
                                        $stmt_cats = $pdo->query("SELECT id_cat_gasto, nombre_categoria 
                                                                FROM gastos_categorias 
                                                                ORDER BY nombre_categoria ASC");
                                        while ($cat = $stmt_cats->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$cat['id_cat_gasto']}'>{$cat['nombre_categoria']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCategoria" title="Nueva Categoría">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-1 text-muted small">Seleccione el rubro para imputar el gasto correctamente.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Importe Total</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" name="monto" step="0.01" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Medio de Pago</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-university"></i></span>
                                    <select name="id_modo_pago" class="form-select" required>
                                        <option value="1">Efectivo / Caja Chica</option>
                                        <option value="2">Transferencia / Lote Bancario</option>
                                        <option value="3">Cheque Propio</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Concepto / Descripción</label>
                                <textarea name="descripcion" class="form-control" rows="2" placeholder="Describa el motivo del egreso..." required></textarea>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch p-3 border rounded bg-light">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" id="check_confirm">
                                    <label class="form-check-label fw-bold text-primary" for="check_confirm">Declaro que los datos ingresados son correctos</label>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" id="btn_submit" class="btn btn-primary w-100 py-2 shadow-sm" disabled>
                                    <i class="fas fa-save me-2"></i>ASENTAR GASTO EN CONTABILIDAD
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card stats-card p-4 mb-4">
                    <span class="text-muted small fw-bold text-uppercase">Egresos Totales (Mes Actual)</span>
                    <h2 class="fw-bold text-danger mt-1">$ <?= number_format($gasto_total_mes, 2, ',', '.'); ?></h2>
                    <div class="progress mt-2" style="height: 6px;">
                        <div class="progress-bar bg-danger" style="width: 70%"></div>
                    </div>
                </div>

                <div class="card bg-dark text-white p-4">
                    <h6 class="text-primary fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>Integración Automática</h6>
                    <p class="small text-secondary mb-0">
                        Los gastos marcados con <span class="badge bg-primary">HABERES</span> son generados automáticamente desde el módulo de Liquidación Masiva. No intente anularlos manualmente sin revisar el lote original.
                    </p>
                </div>
            </div>
        </div>

        <div class="card mt-4 p-4">
            <h5 class="fw-bold mb-4">Historial de Movimientos</h5>
            <div class="table-responsive">
                <table id="tablaGastos" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Rubro</th>
                            <th>Descripción</th>
                            <th class="text-end">Monto</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="small"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCategoria" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Nuevo Rubro de Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nombre de la Categoría</label>
                    <input type="text" id="nombre_cat" class="form-control" placeholder="Ej: Insumos, Mantenimiento...">
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4" onclick="guardarCategoria()">Guardar Rubro</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // Inicialización de Tabla con tu Ajax Server Side
            const tabla = $('#tablaGastos').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": "<?= BASE_URL ?>tesoreria/ajax-server-gastos", 
                "order": [[0, "desc"]],
                "columns": [
                    { "data": "fecha_gasto" },
                    { "data": "nombre_categoria" },
                    { "data": "descripcion" },
                    { "data": "monto", "className": "text-end fw-bold" },
                    { "data": "estado", "className": "text-center" },
                    { "data": "acciones", "className": "text-center" }
                ],
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
            });

            cargarCategorias();

            $('#check_confirm').on('change', function() {
                $('#btn_submit').prop('disabled', !$(this).is(':checked'));
            });
        });

        function cargarCategorias() {
            $.get('<?= BASE_URL ?>tesoreria/obtener-categorias-gastos', function(data) {
                let select = $('#id_cat_gasto');
                select.empty().append('<option value="">Seleccione rubro...</option>');
                data.forEach(cat => {
                    select.append(`<option value="${cat.id_cat_gasto}">${cat.nombre_categoria}</option>`);
                });
            }, 'json');
        }

        function guardarCategoria() {
            const nombre = $('#nombre_cat').val().trim();
            if (!nombre) return;

            $.post('<?= BASE_URL ?>tesoreria/guardar-categoria-gasto', { nombre: nombre }, function(res) {
                if (res.success) {
                    Swal.fire("Éxito", "Rubro añadido", "success");
                    $('#modalCategoria').modal('hide');
                    $('#nombre_cat').val("");
                    cargarCategorias();
                }
            }, 'json');
        }
    </script>
</body>
</html>