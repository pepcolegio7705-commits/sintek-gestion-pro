<?php
    session_start();
    require './core/conexion.php';
    require './core/seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría','Profesor']);

    $rol = $_SESSION['rol'];
    $id_usuario_sesion = $_SESSION['id_usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta General de Notas | Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 12px; }
        .form-label { font-weight: 600; color: #495057; }
        /* Badges personalizados para que coincidan con el diseño previo */
        .bg-aprobado-soft { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .bg-regular-soft { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .bg-desaprobado-soft { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .nota-badge { font-weight: bold; min-width: 100px; display: inline-block; padding: 6px; border-radius: 6px; }
    </style>
</head>
<body>

<?php include 'vistas/nav.php'; ?>

<div class="container-fluid px-lg-5 mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold"><i class="fas fa-search-plus text-primary me-2"></i> Consulta General por Curso</h2>
            <p class="text-muted">Filtre por ciclo, carrera y espacio para visualizar el listado de calificaciones.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Ciclo Lectivo</label>
                    <select id="selectCiclo" class="form-select shadow-sm">
                        <?php
                            $anioActual = date("Y");
                            for($i = $anioActual; $i >= 2020; $i--) {
                                echo "<option value='$i'>$i</option>";
                            }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Carrera</label>
                    <select id="selectCarrera" class="form-select shadow-sm">
                        <option value="">Seleccione Carrera</option>
                        <?php
                        $stmt = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera");
                        while($r = $stmt->fetch()) echo "<option value='{$r['id_carrera']}'>{$r['nombre_carrera']}</option>";
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Espacio Curricular</label>
                    <select id="selectEspacio" class="form-select shadow-sm" disabled>
                        <option value="">Seleccione carrera...</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button id="btnConsultar" class="btn btn-primary px-3 rounded-pill flex-grow-1 shadow-sm">
                        <i class="fas fa-sync-alt me-1"></i> Ver Notas
                    </button>
                    <button id="btnPDF" class="btn btn-danger px-3 rounded-pill shadow-sm" disabled>
                        <i class="fas fa-file-pdf me-1"></i> PDF
                    </button>
                    <button id="btnLimpiar" class="btn btn-outline-secondary px-3 rounded-pill shadow-sm">
                        <i class="fas fa-eraser me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="contenedorTabla" class="card shadow-sm d-none animate__animated animate__fadeIn">
        <div class="card-body p-0">
            <div class="table-responsive p-4">
                <table id="tablaGeneral" class="table table-hover align-middle border-top" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>DNI</th>
                            <th>Alumno</th>
                            <th class="text-center">Nota</th>
                            <th class="text-center">Condición</th>
                            <th class="text-center">Fecha</th>
                            <th class="text-center">Libro</th>
                            <th class="text-center">Folio</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    let tablaGeneralDT;

    $('#btnConsultar').click(function() {
        let idEspacio = $('#selectEspacio').val();
        if(!idEspacio) {
            Swal.fire('Atención', 'Por favor, seleccione un espacio curricular.', 'warning');
            return;
        }

        $('#contenedorTabla').removeClass('d-none');

        if ($.fn.DataTable.isDataTable('#tablaGeneral')) {
            $('#tablaGeneral').DataTable().destroy();
        }

        tablaGeneralDT = $('#tablaGeneral').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "get_consulta_general_serverside.php",
                "type": "POST",
                "data": function(d) {
                    d.id_espacio = $('#selectEspacio').val();
                    d.ciclo = $('#selectCiclo').val();
                }
            },
            "columns": [
                { "data": "dni" },
                { "data": "alumno" },
                { "data": "nota", "className": "text-center fw-bold" },
                { "data": "condicion", "className": "text-center" },
                { "data": "fecha", "className": "text-center" },
                { "data": "libro", "className": "text-center" },
                { "data": "folio", "className": "text-center" }
            ],
            "createdRow": function(row, data, dataIndex) {
                let celdaCondicion = $('td', row).eq(3);
                let valor = data.condicion.toUpperCase();
                let claseColor = 'bg-secondary';
                
                // Lógica estética para los estados
                if (valor === 'APROBADO') claseColor = 'bg-aprobado-soft';
                else if (valor === 'REGULAR') claseColor = 'bg-regular-soft';
                else if (valor === 'DESAPROBADO') claseColor = 'bg-desaprobado-soft';

                celdaCondicion.html('<span class="nota-badge ' + claseColor + '">' + valor + '</span>');
            },
            "language": {
                "url": "https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json"
            },
            "pageLength": 25
        });
        $('#btnPDF').prop('disabled', false);
    });

    $('#selectCarrera').change(function() {
        let id = $(this).val();
        if(id) {
            $.post('get_materias.php', {id_carrera: id}, function(data) {
                $('#selectEspacio').html(data).prop('disabled', false);
            });
        } else {
            $('#selectEspacio').html('<option value="">Seleccione carrera...</option>').prop('disabled', true);
        }
    });

    $('#btnPDF').click(function() {
        let idEspacio = $('#selectEspacio').val();
        let ciclo = $('#selectCiclo').val();
        if(idEspacio && ciclo) {
            window.open(`reporte_notas_curso.php?id_espacio=${idEspacio}&ciclo=${ciclo}`, '_blank');
        }
    });

    $('#btnLimpiar').click(function() {
        $('#selectCarrera').val('');
        $('#selectEspacio').html('<option value="">Seleccione carrera...</option>').prop('disabled', true);
        $('#contenedorTabla').addClass('d-none');
        $('#btnPDF').prop('disabled', true);
        if ($.fn.DataTable.isDataTable('#tablaGeneral')) {
            $('#tablaGeneral').DataTable().clear().destroy();
            $('#tablaGeneral tbody').empty();
        }
    });
});
</script>

<?php include 'vistas/footer.php'; ?>
</body>
</html>