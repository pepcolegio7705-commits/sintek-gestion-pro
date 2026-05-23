<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol'];
$ciclo_actual = date('Y');

// Obtenemos carreras para el filtro inicial
$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Gestión Académica</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-dark: #003366; }
        body { background-color: #f8f9fa; }
        .search-card { border-radius: 15px; border: none; border-top: 5px solid var(--primary-dark); }
        .table-container { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .badge-condicion { font-size: 0.75rem; padding: 5px 10px; border-radius: 50px; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="fw-bold text-dark"><i class="fas fa-chart-line me-2 text-primary"></i>Gestión de Cursadas y Notas</h3>
            </div>
        </div>

        <div class="card search-card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 border-end">
                        <label class="form-label fw-bold"><i class="fas fa-user me-1"></i> Por Alumno (DNI)</label>
                        <div class="input-group">
                            <input type="text" id="filtro_dni" class="form-control" placeholder="DNI sin puntos">
                            <button class="btn btn-primary" onclick="buscarPorAlumno()"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Carrera</label>
                        <select id="id_carrera" class="form-select">
                            <option value="">Seleccione Carrera</option>
                            <?php foreach($carreras as $c): ?>
                                <option value="<?= $c['id_carrera'] ?>"><?= htmlspecialchars($c['nombre_carrera']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Espacio Curricular</label>
                        <select id="id_espacio" class="form-select" disabled>
                            <option value="">Seleccione Materia</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold">Ciclo</label>
                        <input type="number" id="ciclo_lectivo" class="form-control" value="<?= $ciclo_actual ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-dark w-100" onclick="buscarPorMateria()"><i class="fas fa-filter"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div id="contenedor_reporte" class="table-container shadow-sm" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 id="titulo_reporte" class="mb-0 fw-bold text-primary"></h5>
                <button class="btn btn-success btn-sm" onclick="exportarExcel()"><i class="fas fa-file-excel me-1"></i> Exportar</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr id="cabecera_tabla"></tr>
                    </thead>
                    <tbody id="cuerpo_tabla"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Carga dinámica de espacios (Misma lógica que el ABM)
        $('#id_carrera').change(function() {
            const idC = $(this).val();
            if(!idC) return;
            $.post('<?= BASE_URL ?>gestion-academica/api', { action: 'get_espacios', id_carrera: idC }, function(r) {
                $('#id_espacio').html('<option value="">Seleccione Materia</option>').prop('disabled', false);
                r.forEach(e => {
                    $('#id_espacio').append(`<option value="${e.id_espacio}">${e.nombre_espacio}</option>`);
                });
            }, 'json');
        });

        function buscarPorMateria() {
            const data = {
                action: 'reporte_materia',
                id_espacio: $('#id_espacio').val(),
                ciclo: $('#ciclo_lectivo').val()
            };
            if(!data.id_espacio) return Swal.fire('Error', 'Seleccione una materia', 'warning');
            renderReporte(data);
        }

        function buscarPorAlumno() {
            const dni = $('#filtro_dni').val();
            if(!dni) return Swal.fire('Error', 'Ingrese DNI', 'warning');
            renderReporte({ action: 'reporte_alumno', dni: dni, ciclo: $('#ciclo_lectivo').val() });
        }

        function renderReporte(params) {
            $('#contenedor_reporte').hide();
            Swal.fire({ title: 'Cargando datos...', didOpen: () => Swal.showLoading() });

            $.post('<?= BASE_URL ?>gestion-academica/api', params, function(r) {
                Swal.close();
                if(r.success) {
                    $('#titulo_reporte').text(r.titulo);
                    $('#cabecera_tabla').html(r.html_cabecera);
                    $('#cuerpo_tabla').html(r.html_cuerpo);
                    
                    // BOTÓN PARA REPORTE POR ALUMNO
                    if(params.action === 'reporte_alumno' && r.uuid_alumno) {
                        const btnPdf = `
                            <button class="btn btn-danger btn-sm ms-3" onclick="generarPDFAlumno('${r.uuid_alumno}')">
                                <i class="fas fa-file-pdf me-1"></i> Informe Alumno
                            </button>`;
                        $('#titulo_reporte').append(btnPdf);
                    }

                    // BOTÓN PARA REPORTE POR MATERIA (NUEVO)
                    if(params.action === 'reporte_materia' && r.uuid_espacio) {
                        const btnPdfMat = `
                            <button class="btn btn-primary btn-sm ms-3" onclick="generarPDFMateria('${r.uuid_espacio}')">
                                <i class="fas fa-print me-1"></i> Imprimir Planilla Materia
                            </button>`;
                        $('#titulo_reporte').append(btnPdfMat);
                    }

                    $('#contenedor_reporte').fadeIn();
                } else {
                    Swal.fire('Sin resultados', r.message, 'info');
                }
            }, 'json');
        }

        // Funciones para abrir los PDFs
        function generarPDFAlumno(uuid) {
            const ciclo = $('#ciclo_lectivo').val();
            window.open(`<?= BASE_URL ?>reporte/alumno/${uuid}?ciclo=${ciclo}`, '_blank');
        }

        function generarPDFMateria(uuid) {
            const ciclo = $('#ciclo_lectivo').val();
            window.open(`<?= BASE_URL ?>reporte/materia/${uuid}?ciclo=${ciclo}`, '_blank');
        }

        function editarFila(id) {
            $.post('<?= BASE_URL ?>gestion-academica/api', { action: 'get_nota_individual', id: id }, function(r) {
                if(!r.success) return Swal.fire('Error', 'No se encontraron los datos', 'error');

                const nota = r.data;

                Swal.fire({
                    title: 'Editar Calificaciones',
                    html: `
                        <div class="text-start">
                            <p class="small text-muted mb-3">Alumno: <strong>${nota.apellido}, ${nota.nombre}</strong><br>Materia: ${nota.nombre_espacio}</p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">1° Parcial</label>
                                    <input type="number" id="swal_p1" class="form-control" value="${nota.parcial_1 || ''}" step="0.01">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">2° Parcial</label>
                                    <input type="number" id="swal_p2" class="form-control" value="${nota.parcial_2 || ''}" step="0.01">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Recuperatorio</label>
                                    <input type="number" id="swal_rec" class="form-control" value="${nota.recuperatorio || ''}" step="0.01">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">Asistencia %</label>
                                    <input type="number" id="swal_asist" class="form-control" value="${nota.asistencia || ''}" max="100">
                                </div>
                                <div class="col-12 mt-3 text-center">
                                    <label class="form-label small fw-bold text-primary">Nota Final Cursada</label>
                                    <input type="number" id="swal_final" class="form-control border-primary fw-bold text-center" style="font-size: 1.2rem;" value="${nota.nota_final_cursada || ''}" step="0.01">
                                    <div id="feedback_condicion" class="mt-2 fw-bold"></div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-save me-2"></i>Guardar Cambios',
                    cancelButtonText: 'Cancelar',
                    didOpen: () => {
                        const inputFinal = Swal.getPopup().querySelector('#swal_final');
                        const feedback = Swal.getPopup().querySelector('#feedback_condicion');
                        
                        const calcular = () => {
                            const val = parseFloat(inputFinal.value);
                            if (isNaN(val)) {
                                feedback.innerHTML = '';
                            } else if (val >= 7) {
                                feedback.innerHTML = '<span class="text-success"><i class="fas fa-star me-1"></i> PROMOCIONADO</span>';
                            } else if (val >= 6) {
                                feedback.innerHTML = '<span class="text-primary"><i class="fas fa-check-circle me-1"></i> REGULAR</span>';
                            } else {
                                feedback.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> LIBRE</span>';
                            }
                        };
                        
                        inputFinal.addEventListener('input', calcular);
                        calcular(); // Ejecutar al abrir por si ya tiene nota
                    },
                    preConfirm: () => {
                        return {
                            id: id,
                            p1: $('#swal_p1').val(),
                            p2: $('#swal_p2').val(),
                            rec: $('#swal_rec').val(),
                            asist: $('#swal_asist').val(),
                            n_final: $('#swal_final').val()
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        actualizarNotaBase(result.value);
                    }
                });
            }, 'json');
        }

        function actualizarNotaBase(datos) {
            $.post('<?= BASE_URL ?>gestion-academica/api', { 
                action: 'update_nota_individual', 
                ...datos 
            }, function(r) {
                if(r.success) {
                    Swal.fire('¡Actualizado!', r.message, 'success');
                    if($('#filtro_dni').val()) buscarPorAlumno(); else buscarPorMateria();
                } else {
                    Swal.fire('Error', r.message, 'error');
                }
            }, 'json');
        }

        function borrarNota(id) {
            Swal.fire({
                title: '¿Limpiar registros?',
                text: "Se borrarán todas las notas y la condición de esta materia.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, borrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('<?= BASE_URL ?>gestion-academica/api', { action: 'borrar_nota', id: id }, function(r) {
                        if(r.success) {
                            Swal.fire('Borrado', r.message, 'success');
                            if($('#filtro_dni').val()) buscarPorAlumno(); else buscarPorMateria();
                        }
                    }, 'json');
                }
            });
        }
    </script>
</body>
</html>