<?php
    session_start();
    require 'conexion.php'; 
    require 'seguridad.php';

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
        header('Location: login.php');
        exit;
    }
    $rol = $_SESSION['rol'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Calificaciones | Sistema de Gestión</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f7f6; }
        .card { border: none; border-radius: 12px; }
        
        /* Clases para las filas según requerimiento */
        .fila-desaprobado { background-color: #f8d7da !important; color: #842029 !important; } /* Rojo claro */
        .fila-regular { background-color: #fff3cd !important; color: #664d03 !important; }     /* Amarillo claro */
        .fila-aprobado { background-color: #d1e7dd !important; color: #0f5132 !important; }    /* Verde claro */
        
        /* Notas y condiciones en negrita */
        .nota-negrita { font-weight: bold !important; font-size: 1.1em; }
        .condicion-negrita { font-weight: 800 !important; }
    </style>
</head>
<body>

<?php include 'vistas/nav.php'; ?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2 class="fw-bold"><i class="fas fa-graduation-cap text-info me-2"></i> Historial Académico</h2>
            <p class="text-muted">Consulta y gestión de calificaciones por alumno.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body py-4">
            <form id="formConsultaNotas" class="row g-3 align-items-center">
                <div class="col-md-auto">
                    <label for="dniAlumno" class="fw-bold">DNI del Alumno:</label>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="number" class="form-control border-start-0" id="dniAlumno" name="dni" required placeholder="Ej: 30123456">
                    </div>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary px-4 rounded-pill">Buscar Calificaciones</button>
                </div>
            </form>
        </div>
    </div>

    <div id="resultadoConsulta" class="card shadow-sm d-none">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-secondary" id="nombreAlumnoTitulo">Resultados</h5>
            <button id="btnImprimir" class="btn btn-outline-warning btn-sm fw-bold" onclick="imprimirReporte()">
                <i class="fas fa-print me-1"></i> Imprimir Reporte
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tablaCalificacionesDT" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Carrera</th>
                            <th>Espacio Curricular</th>
                            <th class="text-center">Año</th>
                            <th class="text-center">Fecha Reg.</th>
                            <th class="text-center">Nota</th>
                            <th class="text-center">Condición</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div id="sinResultados" class="alert alert-warning m-3 d-none" role="alert">
                <i class="fas fa-info-circle me-2"></i> Este alumno no tiene calificaciones registradas.
            </div>
        </div>
    </div>
    
    <div id="alertaNoEncontrado" class="alert alert-danger d-none mt-3 border-0 shadow-sm" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> El DNI ingresado no corresponde a ningún alumno registrado.
    </div>
</div>

<div class="modal fade" id="modalModificarNota" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i> Modificar Nota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formModificarNota">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted">Espacio Curricular:</label>
                        <div id="espacioCurricularNombre" class="fw-bold fs-5"></div>
                    </div>
                    <input type="hidden" id="editIdCalificacion" name="id_calificacion">
                    <div class="mb-3">
                        <label for="editNotaFinal" class="form-label">Nueva Calificación Final:</label>
                        <input type="number" step="0.1" min="1" max="10" class="form-control form-control-lg text-center fw-bold" id="editNotaFinal" name="nota_final" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let tablaCalificacionesDT;
    let currentAlumno = {};

    function abrirModalEdicion(id_calificacion, nota_actual, nombre_espacio) {
        $('#editIdCalificacion').val(id_calificacion);
        $('#editNotaFinal').val(nota_actual);
        $('#espacioCurricularNombre').text(nombre_espacio);
        var myModal = new bootstrap.Modal(document.getElementById('modalModificarNota'));
        myModal.show();
    }

    function eliminarNota(id_calificacion, nombre_espacio) {
        Swal.fire({
            title: '¿Eliminar calificación?',
            text: "Se borrará la nota de " + nombre_espacio + ". Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('calificaciones_actions_handler.php', { action: 'eliminar', id_calificacion: id_calificacion }, function(res) {
                    if (res.success) {
                        Swal.fire('¡Eliminado!', res.message, 'success');
                        tablaCalificacionesDT.ajax.reload();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    function imprimirReporte() {
        if (!currentAlumno.dni) return;
        const win = window.open('', '_blank');
        let tablaContent = '';
        tablaCalificacionesDT.rows({ search: 'applied' }).data().each(function(row) {
            tablaContent += "<tr><td>"+row[0]+"</td><td>"+row[1]+"</td><td style='text-align:center'>"+row[2]+"</td><td style='text-align:center'>"+row[3]+"</td><td style='text-align:center'><b>"+row[4]+"</b></td><td>"+row[5]+"</td></tr>";
        });

        win.document.write('<html><head><title>Reporte Académico</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"></head><body>');
        win.document.write('<div class="container mt-4"><h4>COLEGIO N° 752 - RAWSON</h4><hr>');
        win.document.write('<p><b>Alumno:</b> '+currentAlumno.apellido+', '+currentAlumno.nombre+' | <b>DNI:</b> '+currentAlumno.dni+'</p>');
        win.document.write('<table class="table table-bordered"><thead><tr><th>Carrera</th><th>Espacio</th><th>Año</th><th>Fecha</th><th>Nota</th><th>Condición</th></tr></thead><tbody>'+tablaContent+'</tbody></table></div>');
        win.document.write('<script>window.onload = function(){ window.print(); window.close(); };<\/script></body></html>');
        win.document.close();
    }

    $(document).ready(function() {
        tablaCalificacionesDT = $('#tablaCalificacionesDT').DataTable({
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json" },
            "serverSide": true,
            "ajax": {
                "url": "calificaciones_serverside.php",
                "type": "POST",
                "data": function(d) { 
                    d.dni = $('#dniAlumno').val();
                    d.action = 'get_calificaciones';
                },
                "dataSrc": function(json) {
                    $('#alertaNoEncontrado, #sinResultados').addClass('d-none');
                    if (!json.success) {
                        $('#alertaNoEncontrado').removeClass('d-none');
                        $('#resultadoConsulta').addClass('d-none');
                        return [];
                    }
                    currentAlumno = json.alumno;
                    $('#nombreAlumnoTitulo').text(json.alumno.apellido + ", " + json.alumno.nombre + " (DNI: " + json.alumno.dni + ")");
                    $('#resultadoConsulta').removeClass('d-none');
                    if(json.data.length === 0) $('#sinResultados').removeClass('d-none');
                    return json.data;
                }
            },
            "columns": [
                { "data": 0 }, { "data": 1 }, 
                { "data": 2, "className": "text-center" }, 
                { "data": 3, "className": "text-center" },
                { "data": 4, "className": "text-center nota-negrita" }, // Nota Negrita
                { "data": 5, "className": "text-center condicion-negrita" }, // Condición Negrita
                { "data": 6, "className": "text-center", "orderable": false }
            ],
            "createdRow": function(row, data, dataIndex) {
                const nota = parseFloat(data[4]);
                $(row).removeClass('fila-desaprobado fila-regular fila-aprobado');

                if (nota <= 5) {
                    $(row).addClass('fila-desaprobado');
                    $('td', row).eq(5).html('<span class="text-danger">DESAPROBADO</span>');
                } else if (nota == 6) {
                    $(row).addClass('fila-regular');
                    $('td', row).eq(5).html('<span class="text-warning">REGULAR</span>');
                } else if (nota >= 7) {
                    $(row).addClass('fila-aprobado');
                    $('td', row).eq(5).html('<span class="text-success">APROBADO</span>');
                }
            }
        });

        $('#formConsultaNotas').on('submit', function(e) {
            e.preventDefault();
            tablaCalificacionesDT.ajax.reload();
        });

        $('#formModificarNota').on('submit', function(e) {
            e.preventDefault();
            $.post('calificaciones_actions_handler.php', {
                action: 'modificar',
                id_calificacion: $('#editIdCalificacion').val(),
                nota_final: $('#editNotaFinal').val()
            }, function(res) {
                if (res.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalModificarNota')).hide();
                    Swal.fire('Éxito', res.message, 'success');
                    tablaCalificacionesDT.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }, 'json');
        });
    });
</script>
<?php include 'vistas/footer.php'; ?>
</body>
</html>