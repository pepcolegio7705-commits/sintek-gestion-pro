<?php
session_start();
// Ajuste de rutas: Subimos 2 niveles para llegar al core
require_once '../../core/conexion.php'; 
require_once '../../core/seguridad.php'; 

if (!defined('NOM_INST')) {
    define('NOM_INST', 'Sistema Académico');
}

verificar_permisos(['Administrador', 'Secretaría']);
$rol = $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Mesas Cerradas | <?php echo NOM_INST; ?></title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 15px; }
        .bg-dark-blue { background-color: #1e293b !important; }
        .table thead th { border: none; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
        .btn-action { border-radius: 8px; transition: all 0.3s; }
        .btn-action:hover { transform: translateY(-2px); }
        .text-date { font-size: 0.85rem; color: #64748b; }
    </style>
</head>
<body>
<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid px-lg-5 mt-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="fw-bold text-secondary mb-1">
                <i class="fas fa-archive me-2"></i> Historial de Mesas Cerradas
            </h2>
            <p class="text-muted mb-0">Archivo digital de actas finales y registros históricos de exámenes.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="<?= BASE_URL ?>instancias/gestion-inscripciones" class="btn btn-outline-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-arrow-left me-2"></i> Volver a Inscripciones
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3"><i class="fas fa-filter me-2"></i> Filtros de Búsqueda</h5>
            <form id="formFiltros" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Carrera</label>
                    <select id="filtroCarrera" class="form-select form-select-sm">
                        <option value="">Todas las carreras</option>
                        <?php
                            $stmtC = $pdo->query("SELECT uuid_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC");
                            while ($c = $stmtC->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$c['uuid_carrera']}'>{$c['nombre_carrera']}</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Materia</label>
                    <select id="filtroMateria" class="form-select form-select-sm" disabled>
                        <option value="">Seleccione una carrera...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Inscripción Desde</label>
                    <input type="date" id="fechaDesde" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Inscripción Hasta</label>
                    <input type="date" id="fechaHasta" class="form-control form-control-sm">
                </div>
                <div class="col-12 text-end">
                    <button type="button" id="btnLimpiar" class="btn btn-sm btn-light border">Limpiar Filtros</button>
                    <button type="button" id="btnFiltrar" class="btn btn-sm btn-primary px-4">Aplicar Filtros</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tablaHistorial" class="table table-hover align-middle" style="width:100%">
                    <thead class="bg-dark-blue text-white">
                        <tr>
                            <th>Fecha Examen</th>
                            <th>Carrera</th>
                            <th>Materia</th>
                            <th class="text-center">Llamado</th>
                            <th>Inscripción (Inicio/Fin)</th>
                            <th class="text-center">Libro/Folio</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        // 1. Inicialización de DataTable
        var table = $('#tablaHistorial').DataTable({
            "processing": true,
            "serverSide": true,
            "searching": false, // Desactivamos la búsqueda global para usar tus filtros manuales
            "ajax": {
                "url": "<?= BASE_URL ?>instancias/historial-data",
                "type": "POST",
                "data": function (d) {
                    // Capturamos los valores justo antes de enviar la petición
                    d.f_carrera = $('#filtroCarrera').val();
                    d.f_materia = $('#filtroMateria').val();
                    d.f_desde   = $('#fechaDesde').val();
                    d.f_hasta   = $('#fechaHasta').val();
                }
            },
            "order": [[0, "desc"]],
           "columns": [
                { "data": 0, "width": "10%" }, // Fecha Examen
                { "data": 1, "width": "20%" }, // Carrera
                { "data": 2, "width": "20%", "className": "fw-bold" }, // Materia
                { "data": 3, "className": "text-center", "width": "10%" }, // Llamado
                { 
                    "data": null, 
                    "width": "15%",
                    "render": function(data, type, row) {
                        // Mostramos Inicio [6] y Fin [7] de inscripción
                        return `<div class="text-date">
                                    <i class="far fa-calendar-check text-success me-1"></i>${row[6]}<br>
                                    <i class="far fa-calendar-times text-danger me-1"></i>${row[7]}
                                </div>`;
                    }
                },
                { "data": 4, "className": "text-center fw-bold text-primary", "width": "10%" }, // Libro/Folio (Índice 4 en PHP)
                { 
                    "data": null,
                    "className": "text-center",
                    "width": "15%",
                    "orderable": false,
                    "render": function(data, type, row) {
                        // Extraemos el UUID del índice 5 que envía tu historial_mesas_server.php
                        const uuidMesa = row[5]; 
                        
                        return `
                            <div class="btn-group shadow-sm">
                                <a href="<?= BASE_URL ?>mesas/calificaciones/${row[5]}" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>reporte/acta-final/${row[5]}" target="_blank" class="btn btn-sm btn-dark">
                                    <i class="fas fa-print"></i>
                                </a>
                            </div>`;
                    }
                }
            ],
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json" }
        });

        // 2. Lógica del Botón "Aplicar Filtros" (ESTO FALTABA)
        $('#btnFiltrar').on('click', function(e) {
            e.preventDefault();
            table.draw(); // Esto dispara la petición AJAX con los nuevos valores de 'd'
        });

        // 3. Lógica de Selects Dependientes
        $('#filtroCarrera').on('change', function() {
            const uuidCarrera = $(this).val(); // Ahora captura el UUID
            const $selectMateria = $('#filtroMateria');

            if (uuidCarrera) {
                $selectMateria.html('<option>Cargando...</option>').prop('disabled', true);

                // Usamos la ruta amigable definida en el .htaccess
                fetch(`<?= BASE_URL ?>instancias/get-materias-historial?uuid_carrera=${uuidCarrera}`)
                    .then(response => response.json())
                    .then(data => {
                        $selectMateria.empty().append('<option value="">Todas las materias</option>');
                        
                        data.forEach(materia => {
                            // Nota: Aquí enviamos el id_espacio como value para el filtro del DataTable
                            $selectMateria.append(`<option value="${materia.id_espacio}">${materia.nombre_espacio}</option>`);
                        });
                        
                        $selectMateria.prop('disabled', false);
                        table.draw(); // Refresca el DataTable
                    })
                    .catch(error => {
                        console.error('Error al cargar materias:', error);
                        $selectMateria.html('<option value="">Error</option>');
                    });
            } else {
                $selectMateria.empty().append('<option value="">Seleccione una carrera...</option>').prop('disabled', true);
                table.draw();
            }
        });

        // 4. Actualizar tabla automáticamente al cambiar Materia o Fechas (Opcional)
        $('#filtroMateria, #fechaDesde, #fechaHasta').on('change', function() {
            table.draw();
        });

        // 5. Botón Limpiar (Corregido para recargar tabla)
        $('#btnLimpiar').on('click', function() {
            $('#formFiltros')[0].reset();
            $('#filtroMateria').prop('disabled', true).empty().append('<option value="">Seleccione una carrera...</option>');
            table.draw(); // Recarga la tabla sin filtros
        });
    });
</script>

<?php include '../../vistas/footer.php'; ?>
</body>
</html>