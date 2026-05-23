<?php
session_start();
// Ajustamos las rutas a tu estándar
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

// Verificamos permisos (Añadí Profesor porque ellos son los que más usan esto)
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

$rol = $_SESSION['rol']; 
$id_usuario = $_SESSION['id_usuario']; // Usamos ID de usuario para mayor precisión

$fecha_hoy = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Permanencia | Sintek</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">

<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid mt-4">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h3 class="fw-bold mb-0 text-dark">
                <i class="fas fa-user-clock text-primary me-2"></i>Permanencia Institucional
            </h3>
            <button type="button" id="btnExportarPDF" class="btn btn-danger shadow-sm">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF Institucional
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form id="formFiltros" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold">Desde</label>
                    <input type="date" class="form-control" id="fecha_inicio" value="<?= $fecha_hoy ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Hasta</label>
                    <input type="date" class="form-control" id="fecha_fin" value="<?= $fecha_hoy ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Rol / Área</label>
                    <select id="tipo_persona" class="form-select">
                        <option value="">Todos los Roles</option>
                        <option value="Alumno">Alumnos</option>
                        <option value="Profesor">Docentes</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="btnFiltrar" class="btn btn-primary w-100">
                        <i class="fas fa-sync-alt me-1"></i> Filtrar Datos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table id="tablaPermanencia" class="table table-hover w-100">
                <thead class="table-dark">
                    <tr>
                        <th>DNI</th>
                        <th>Nombre Completo</th>
                        <th>Rol/Área</th>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th class="text-center">Permanencia</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var tablaPermanencia = $('#tablaPermanencia').DataTable({
        "processing": true, 
        "serverSide": true, 
        "ajax": {
            "url": "<?= BASE_URL; ?>api/asistencias/data-permanencia",
            "type": "POST",
            "data": function (d) {
                d.fecha_inicio = $('#fecha_inicio').val();
                d.fecha_fin = $('#fecha_fin').val();
                d.tipo_persona = $('#tipo_persona').val();
            }
        },
        "columns": [
            { "data": 0 }, // DNI_persona
            { "data": 1 }, // Nombre_completo
            { "data": 2 }, // Tipo_persona (Rol/Área)
            { "data": 3 }, // Fecha
            { 
                "data": 4, // HORA ENTRADA
                "render": function(data) {
                    // Puerta abierta indicando ingreso
                    return `<i class="fas fa-door-open text-success me-2"></i><span class="fw-bold">${data}</span>`;
                }
            }, 
            { 
                "data": 5, // HORA SALIDA
                "render": function(data) {
                    if (!data || data === '---' || data === '--:--') {
                        return `<span class="text-muted">--:--</span>`;
                    }
                    // Hombrecito saliendo por la derecha
                    return `<i class="fas fa-sign-out-alt text-danger me-2"></i><span class="fw-bold">${data}</span>`;
                }
            },
            { 
                "data": 6, // TIEMPO TOTAL / PERMANENCIA
                "orderable": false, 
                "className": "text-center fw-bold",
                "render": function (data) {
                    // Si el PHP envió "En Tránsito", hombrecito caminando con animación
                    if (data === "En Tránsito") {
                        return `<span style="color: #f39c12 !important;">
                                    <i class="fas fa-walking fa-bounce me-2"></i>${data}
                                </span>`;
                    }
                    // Si ya finalizó, hombrecito con tilde de verificado
                    return `<span class="text-primary">
                                <i class="fas fa-user-check me-2"></i>${data}
                            </span>`;
                }
            }
        ],
        "order": [[3, "desc"], [4, "desc"]], // Ordenar por fecha y luego por hora de entrada
        "language": {
            "url": "<?= BASE_URL; ?>datatables/Spanish.json"
        }
    });
    
    $('#btnFiltrar').click(function() {
        tablaPermanencia.ajax.reload();
    });

    $('#btnExportarPDF').click(function() {
        const fi = $('#fecha_inicio').val();
        const ff = $('#fecha_fin').val();
        const tp = $('#tipo_persona').val();
        // Redirección amigable con constante
        window.open("<?= BASE_URL; ?>reporte/permanencia-pdf?fi=" + fi + "&ff=" + ff + "&tp=" + tp, '_blank');
    });
});
</script>

<?php include '../../vistas/footer.php'; ?>
</body>
</html>