<?php
    session_start();
    require 'conexion.php';
    require 'seguridad.php';

    verificar_permisos(['Administrador']);
    $rol = $_SESSION['rol'];

    // Consulta rápida para el resumen lateral (Mes actual)
    $mes_actual = date('m');
    $anio_actual = date('Y');
    $stmt_resumen = $pdo->prepare("SELECT SUM(monto) as total FROM gastos WHERE estado = 'Pagado' AND MONTH(fecha_gasto) = :m AND YEAR(fecha_gasto) = :a");
    $stmt_resumen->execute([':m' => $mes_actual, ':a' => $anio_actual]);
    $gasto_total_mes = $stmt_resumen->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Gastos | Sintek Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        body { background-color: #f8fafc; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; }
        .form-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        .input-group-text { background-color: #f1f5f9; border-right: none; }
        .form-control, .form-select { border-left: none; background-color: #f1f5f9; }
        .form-control:focus, .form-select:focus { background-color: #fff; box-shadow: none; border-color: #3b82f6; }
        .btn-save { background: linear-gradient(45deg, #2563eb, #1d4ed8); border: none; padding: 10px 25px; border-radius: 10px; transition: 0.3s; }
        .btn-save:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .sidebar-stats { background: #fff; border-radius: 15px; padding: 20px; }
    </style>
</head>
<body>
    <?php include 'vistas/nav.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold text-dark"><i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Gestión de Egresos</h2>
                <p class="text-muted">Registra y clasifica los gastos operativos de la institución.</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="dashboard_finanzas.php" class="btn btn-outline-secondary rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> Volver al Panel
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm p-4">
                    <h5 class="fw-bold mb-4">Nuevo Registro de Gasto</h5>
                    <form id="formGasto" action="procesar_gasto.php" method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fecha del Gasto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-day text-muted"></i></span>
                                    <input type="date" name="fecha_gasto" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Categoría / Rubro</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-tags text-muted"></i></span>
                                    <select class="form-select" name="id_cat_gasto" id="id_cat_gasto" required>
                                        </select>
                                    <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCategoria">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Monto del Pago</label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" name="monto" step="0.01" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Caja de Salida</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-wallet text-muted"></i></span>
                                    <select name="id_modo_pago" class="form-select" required>
                                        <?php 
                                        $stmt_modos = $pdo->query("SELECT * FROM modos_pago");
                                        while($m = $stmt_modos->fetch()){
                                            echo "<option value='{$m['id_modo']}'>{$m['nombre_modo']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Descripción Detallada</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-align-left text-muted"></i></span>
                                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Ej: Pago de servicios - Internet Enero" required></textarea>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <div class="form-check form-switch p-3 border rounded bg-light">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" id="check_confirmar_gasto">
                                    <label class="form-check-label fw-bold text-primary" for="check_confirmar_gasto">
                                        Confirmar datos correctos
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 mt-3">
                                <button type="submit" id="btn_registrar_gasto" class="btn btn-primary btn-save w-100 fw-bold" disabled>
                                    <i class="fas fa-check-circle me-2"></i>CONFIRMAR Y REGISTRAR GASTO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="sidebar-stats shadow-sm mb-4 border-top border-danger border-5">
                    <h6 class="text-muted fw-bold small text-uppercase mb-3">Resumen de Salidas (Mes)</h6>
                    <h2 class="fw-bold text-danger mb-1">$<?php echo number_format($gasto_total_mes, 2, ',', '.'); ?></h2>
                    <p class="text-muted small">Total acumulado en egresos efectivos.</p>
                </div>

                <div class="card shadow-sm border-0 bg-dark text-white p-4">
                    <h6 class="mb-3 fw-bold text-primary"><i class="fas fa-lightbulb me-2"></i>Tip de Gestión</h6>
                    <p class="small text-secondary mb-0">
                        Categorizar correctamente los gastos permite al Dashboard Financiero mostrar el flujo de caja real.
                    </p>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4 p-4">
            <h5 class="fw-bold mb-4">Historial General de Egresos</h5>
            <div class="table-responsive">
                <table id="tablaGastos" class="table table-hover w-100 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Categoría</th>
                            <th>Descripción</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="small"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Nueva Categoría de Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Nombre del Rubro</label>
                    <input type="text" id="nombre_nueva_cat" class="form-control" placeholder="Ej: Insumos de Oficina">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-modal="hide">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="guardarCategoria()">Guardar Rubro</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAnularGasto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-start border-danger border-5">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger"><i class="fas fa-ban me-2"></i>Anular Registro de Gasto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="id_gasto_anular">
                    <label class="form-label fw-bold">Motivo de la Anulación (Obligatorio)</label>
                    <textarea id="motivo_anulacion_gasto" class="form-control" rows="3" placeholder="Ej: Error en el monto, factura duplicada..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger fw-bold" onclick="procesarAnulacionGasto()">Confirmar Anulación</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'vistas/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            // 1. Inicializar DataTable ServerSide
            const tabla = $('#tablaGastos').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": "ajax_server_gastos.php", 
                "order": [[0, "desc"]],
                "columns": [
                    { "data": "fecha_gasto" },
                    { "data": "nombre_categoria" },
                    { "data": "descripcion" },
                    { "data": "monto" },
                    { "data": "estado" },
                    { "data": "acciones", "className": "text-center" }
                ],
                "language": { "url": "//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json" }
            });

            // 2. Cargar categorías inicialmente
            cargarCategorias();

            // 3. Lógica del Interruptor de Seguridad
            $('#check_confirmar_gasto').on('change', function() {
                $('#btn_registrar_gasto').prop('disabled', !$(this).is(':checked'));
            });

            // 4. Manejo de alertas por URL
            const status = new URLSearchParams(window.location.search).get('status');
            if (status === 'success') Swal.fire('¡Registrado!', 'El gasto se guardó con éxito', 'success');
            if (status === 'anulado') Swal.fire('Anulado', 'El egreso ha sido cancelado', 'info');
        });

        function cargarCategorias() {
            $.ajax({
                url: 'obtener_categorias.php',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    let select = $('#id_cat_gasto');
                    select.empty().append('<option value="">Seleccione rubro...</option>');
                    data.forEach(cat => {
                        select.append(`<option value="${cat.id_cat_gasto}">${cat.nombre_categoria}</option>`);
                    });
                }
            });
        }

        function guardarCategoria() {
            const nombre = $('#nombre_nueva_cat').val().trim();
            if (!nombre) return Swal.fire("Atención", "Escriba un nombre para el rubro", "warning");

            $.ajax({
                url: 'guardar_categoria.php',
                type: 'POST',
                data: { nombre: nombre },
                success: function(res) {
                    if (res.trim() === "ok") {
                        Swal.fire("Éxito", "Categoría añadida", "success");
                        $('#modalCategoria').modal('hide');
                        $('#nombre_nueva_cat').val("");
                        cargarCategorias();
                    } else {
                        Swal.fire("Error", res, "error");
                    }
                }
            });
        }

        // Abre el modal y prepara el ID
        function confirmarAnulacion(id) {
            $('#id_gasto_anular').val(id);
            $('#motivo_anulacion_gasto').val('');
            new bootstrap.Modal(document.getElementById('modalAnularGasto')).show();
        }

        // Procesa la anulación vía AJAX
        function procesarAnulacionGasto() {
            const id = $('#id_gasto_anular').val();
            const motivo = $('#motivo_anulacion_gasto').val().trim();

            if (motivo.length < 5) {
                return Swal.fire("Atención", "Debe especificar un motivo válido (mín. 5 caracteres)", "warning");
            }

            $.ajax({
                url: 'anular_gasto.php',
                type: 'POST',
                data: { id: id, motivo: motivo },
                dataType: 'json',
                success: function(res) {
                    if (res.status === "success") {
                        Swal.fire("Anulado", res.message, "success").then(() => {
                            location.reload(); // Recargamos para actualizar el KPI de "Resumen de Salidas"
                        });
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                }
            });
        }

        // Activa tooltips cada vez que la tabla se redibuja (por búsqueda o paginación)
        $('#tablaGastos').on('draw.dt', function () {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
</body>
</html>