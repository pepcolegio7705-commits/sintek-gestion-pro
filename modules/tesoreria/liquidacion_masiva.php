<?php
session_start();
require 'conexion.php';
require 'seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol']; 

// Obtenemos la configuración de tesorería para mostrar los valores actuales en el formulario
$stmt = $pdo->query("SELECT valor_hora_catedra, ciclo_lectivo_actual FROM configuracion_tesoreria LIMIT 1");
$config = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Liquidación Masiva | Sintek Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-step { border-left: 5px solid #0d6efd; }
        .valor-referencia { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body class="bg-light">
    <?php include 'vistas/nav.php'; ?>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h2 class="fw-bold"><i class="fas fa-layer-group text-primary"></i> Procesador de Haberes Masivo</h2>
                <p class="text-muted">Generación de liquidaciones, recibos y archivo bancario TXT.</p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 card-step">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3"><i class="fas fa-filter me-2"></i>1. Parámetros</h5>
                        
                        <form id="formLiquidacion">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase">Área a Liquidar</label>
                                <select class="form-select border-primary shadow-sm" id="id_area_filtro" name="id_area_filtro">
                                    <option value="TODOS">-- Todas las Áreas --</option>
                                    <?php 
                                    $stmt_areas = $pdo->query("SELECT id_area, nombre_area FROM areas ORDER BY nombre_area ASC");
                                    while($area = $stmt_areas->fetch()) {
                                        echo "<option value='{$area['id_area']}'>{$area['nombre_area']}</option>";
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Se incluirá personal de Staff y Profesores que pertenezcan al área.</small>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">MES</label>
                                    <select class="form-select border-primary" id="mes_pago" name="mes_pago">
                                        <?php
                                        $meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                                        for($i=1; $i<=12; $i++) {
                                            $sel = ($i == date('n')) ? 'selected' : '';
                                            echo "<option value='$i' $sel>$meses[$i]</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small fw-bold">AÑO</label>
                                    <input type="number" class="form-control border-primary" id="anio_pago" name="anio_pago" value="<?= $config['ciclo_lectivo_actual'] ?>">
                                </div>
                            </div>

                            <div class="p-2 bg-light rounded mb-3 border">
                                <span class="valor-referencia">
                                    <i class="fas fa-info-circle"></i> Valor Hora Cátedra: 
                                    <strong>$<?= number_format($config['valor_hora_catedra'], 2) ?></strong>
                                </span>
                            </div>

                            <button type="button" onclick="previsualizarLiquidacion()" class="btn btn-primary w-100 fw-bold py-2">
                                <i class="fas fa-search me-2"></i> PREVISUALIZAR
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title fw-bold mb-0"><i class="fas fa-list-check me-2"></i>2. Revisión de Totales</h5>
                    </div>
                    <div class="card-body">
                        <div id="resultado_previsualizacion" class="text-center py-5">
                            <i class="fas fa-calculator fa-3x text-light mb-3"></i>
                            <p class="text-muted">Configure los parámetros y haga clic en previsualizar para calcular los haberes antes de procesar.</p>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-none" id="footer_procesar">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small">Total Neto Estimado:</span><br>
                                <h4 class="fw-bold text-success mb-0" id="monto_total_estimado">$ 0.00</h4>
                            </div>
                            <button type="button" onclick="ejecutarLiquidacionFinal()" class="btn btn-danger btn-lg px-4 fw-bold">
                                <i class="fas fa-check-double me-2"></i> PROCESAR Y GENERAR TXT
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function previsualizarLiquidacion() {
            let data = $('#formLiquidacion').serialize();
            
            $('#resultado_previsualizacion').html('<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Calculando haberes...</p>');
            $('#footer_procesar').addClass('d-none');

            $.ajax({
                url: 'ajax_previsualizar_haberes.php',
                type: 'POST',
                data: data,
                success: function(response) {
                    $('#resultado_previsualizacion').html(response.html);
                    $('#monto_total_estimado').text(response.total_neto);
                    $('#footer_procesar').removeClass('d-none');
                }
            });
        }

        function ejecutarLiquidacionFinal() {
            Swal.fire({
                title: '¿Confirmar Liquidación?',
                text: "Se generarán los recibos, se guardará el historial y se creará el archivo TXT para el Banco. Esta acción impactará en el módulo de Tesorería.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'SÍ, PROCESAR TODO',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Aquí llamaremos al CONTROLADOR final
                    Swal.fire('Procesando...', 'No cierre la ventana mientras se generan los archivos.', 'info');
                }
            });
        }
    </script>
    <?php include 'vistas/footer.php'; ?>
</body>
</html>