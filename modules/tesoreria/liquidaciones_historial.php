<?php
    session_start();
    require 'conexion.php';
    require 'seguridad.php';
    verificar_permisos(['Administrador', 'Secretaría']);
    $rol = $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial Server-Side | Sintek Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'vistas/nav.php'; ?>
    <div class="container py-4">
       <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold"><i class="fas fa-history text-primary"></i> Historial Haberes</h2>
        <?php if(isset($_GET['id_profesor']) || isset($_GET['dni'])): ?>
            <a href="liquidaciones_historial.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-users me-1"></i> Ver todos los docentes
            </a>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0 mb-4 bg-white p-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="label-custom small fw-bold text-muted">DOCUMENTO (DNI)</label>
                <input type="text" id="filtro_dni" class="form-control border-primary shadow-sm" 
                    placeholder="Ingrese DNI..." 
                    value="<?= $_GET['dni'] ?? '' ?>">
            </div>

            <div class="col-md-2">
                <label class="label-custom small fw-bold text-muted">MES</label>
                <select id="filtro_mes" class="form-select border-primary shadow-sm">
                    <option value="">Mes...</option>
                    <?php 
                    $meses_n = ["", "Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                    for($i=1; $i<=12; $i++) echo "<option value='$i'>$meses_n[$i]</option>"; 
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="label-custom small fw-bold text-muted">AÑO</label>
                <input type="number" id="filtro_anio" class="form-control border-primary shadow-sm" value="<?= date('Y') ?>">
            </div>

            <div class="col-md-3">
                <label class="label-custom small fw-bold text-muted">ESTADO</label>
                <select id="filtro_estado" class="form-select border-primary shadow-sm">
                    <option value="">Todos</option>
                    <option value="Pagado">Pagado</option>
                    <option value="Anulado">Anulado</option>
                </select>
            </div>

            <div class="col-md-1">
                <button type="button" onclick="recargarTabla()" class="btn btn-primary w-100 shadow-sm fw-bold" title="Filtrar Resultados">
                    <i class="fas fa-filter"></i>
                </button>
            </div>

            <div class="col-md-1">
                <button type="button" onclick="exportarPDF()" class="btn btn-danger w-100 shadow-sm fw-bold" title="Exportar Reporte Mensual">
                    <i class="fas fa-file-pdf"></i>
                </button>
            </div>
            <div class="col-md-1">
                <button type="button" onclick="limpiarFiltros()" class="btn btn-outline-secondary flex-fill shadow-sm" title="Limpiar">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 p-3">
        <table id="tablaLiquidaciones" class="table table-striped table-hover w-100">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha Pago</th>
                    <th>Docente</th>
                    <th>Período</th>
                    <th>Monto Neto</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    var tabla;
      var tabla;
        $(document).ready(function() {
            tabla = $('#tablaLiquidaciones').DataTable({
                "processing": true,
                "serverSide": true,
                "ajax": {
                    "url": "servidor_historial_haberes.php",
                    "type": "POST",
                    "data": function (d) {
                        // Capturamos el ID de la URL si venimos del ABM
                        let idUrl = new URLSearchParams(window.location.search).get('id_profesor');
                        
                        d.id_filtro_profe = idUrl;
                        d.dni = $('#filtro_dni').val(); // Filtro por DNI manual
                        d.mes = $('#filtro_mes').val();
                        d.anio = $('#filtro_anio').val();
                        d.estado = $('#filtro_estado').val();
                    }
                },
                "order": [[ 0, "desc" ]], 
                "columns": [
                    { "data": "id_liquidacion" },
                    { "data": "fecha_pago" },
                    { "data": "docente" },
                    { "data": "periodo" },
                    { "data": "monto_neto" },
                    { "data": "estado" },
                    { "data": "acciones", "orderable": false }
                ],
                "language": { "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json" }
            });
        });

        function recargarTabla() {
            tabla.ajax.reload();
        }

    function anularLiquidacion(id) {
        Swal.fire({
            title: '¿Anular Liquidación?',
            text: "Se revertirá el gasto en caja.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'anular_pago_haberes.php?id=' + id;
            }
        });
    }

    function exportarPDF() {
        let mes = $('#filtro_mes').val();
        let anio = $('#filtro_anio').val();
        let estado = $('#filtro_estado').val();
        let dni = $('#filtro_dni').val(); 
        
        // Capturamos el ID de la URL si es que venimos del ABM
        const urlParams = new URLSearchParams(window.location.search);
        let idProfe = urlParams.get('id_profesor') || ""; 

        if(mes === "" || anio === "") {
            Swal.fire('Atención', 'Seleccione Mes y Año para generar el reporte.', 'info');
            return;
        }

        // Si el DNI está vacío y no hay ID de profesor, avisamos que imprimirá TODO
        if(dni === "" && idProfe === "") {
            Swal.fire({
                title: '¿Imprimir Planilla Completa?',
                text: "No hay un docente seleccionado. Se generará el reporte de todos los pagos del mes.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, imprimir todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarEnvioPDF(mes, anio, estado, dni, idProfe);
                }
            });
        } else {
            ejecutarEnvioPDF(mes, anio, estado, dni, idProfe);
        }
    }

    function ejecutarEnvioPDF(m, a, e, d, idp) {
        let url = `reporte_mensual_haberes.php?mes=${m}&anio=${a}&estado=${e}&dni=${d}&id_profesor=${idp}`;
        window.open(url, '_blank');
    }
    
    function limpiarFiltros() {
        $('#filtro_dni').val('');
        $('#filtro_mes').val(<?= date('n') ?>);
        $('#filtro_anio').val(<?= date('Y') ?>);
        $('#filtro_estado').val('Pagado');
        
        // Si había un id_profesor en la URL, limpiamos la URL sin recargar para ver todos
        const url = new URL(window.location);
        url.searchParams.delete('id_profesor');
        window.history.pushState({}, '', url);

        recargarTabla();
    }
</script>
</body>
</html>