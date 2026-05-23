<?php
// inscripcion_examen_rapida.php

require 'conexion.php'; // Asegúrate de que este archivo contenga tu objeto $pdo de conexión
date_default_timezone_set('America/Argentina/Buenos_Aires');

// --- Función para obtener datos o registrar inscripción (llamada por AJAX) ---
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    try {
        if ($_POST['action'] === 'buscar_alumno') {
            
            $dni = trim($_POST['dni']);
            
            if (!is_numeric($dni) || strlen($dni) < 5) {
                throw new Exception("DNI no válido.");
            }

            // Consulta para obtener alumno y carrera
            $sql_alumno = "SELECT 
                                a.id_alumno, a.nombre, a.apellido, a.dni,
                                c.id_carrera, c.nombre_carrera
                            FROM alumnos a
                            INNER JOIN carreras c ON a.id_carrera = c.id_carrera
                            WHERE a.dni = :dni";
            
            $stmt = $pdo->prepare($sql_alumno);
            $stmt->bindParam(':dni', $dni);
            $stmt->execute();
            $alumno_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$alumno_data) {
                throw new Exception("El DNI no corresponde a ningún alumno registrado.");
            }

            // Buscar materias disponibles para esa carrera
            $sql_materias = "SELECT id_espacio, nombre_espacio 
                             FROM espacios_curriculares 
                             WHERE id_carrera = :id_carrera
                             ORDER BY nombre_espacio ASC";
            
            $stmt_materias = $pdo->prepare($sql_materias);
            $stmt_materias->bindParam(':id_carrera', $alumno_data['id_carrera'], PDO::PARAM_INT);
            $stmt_materias->execute();
            $materias_disponibles = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['alumno'] = $alumno_data;
            $response['materias'] = $materias_disponibles;

        } elseif ($_POST['action'] === 'registrar_inscripcion') {
            
            $id_alumno = (int)$_POST['id_alumno'];
            $id_carrera = (int)$_POST['id_carrera'];
            $id_espacio = (int)$_POST['id_espacio'];
            $fecha_inscripcion = date('Y-m-d H:i:s'); 
            
            // Opcional: Verificar duplicados aquí antes de insertar
            
            $sql_insert = "INSERT INTO inscripciones_examen 
                           (id_alumno, id_carrera, id_espacio, fecha_inscripcion) 
                           VALUES (:id_alumno, :id_carrera, :id_espacio, :fecha)";
                           
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->bindParam(':id_alumno', $id_alumno, PDO::PARAM_INT);
            $stmt_insert->bindParam(':id_carrera', $id_carrera, PDO::PARAM_INT);
            $stmt_insert->bindParam(':id_espacio', $id_espacio, PDO::PARAM_INT);
            $stmt_insert->bindParam(':fecha', $fecha_inscripcion);
            
            if ($stmt_insert->execute()) {
                 $response['success'] = true;
                 $response['message'] = '¡Inscripción registrada con éxito!';
                 $response['nombre_materia'] = $_POST['nombre_materia']; // Devuelve el nombre para el mensaje
            } else {
                 throw new Exception("No se pudo completar el registro en la base de datos.");
            }

        } else {
            throw new Exception("Acción no reconocida.");
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit; // Terminar ejecución PHP después de la respuesta AJAX
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción a Exámenes | Escuela N° 752</title>
    <link rel="stylesheet" href="../fontawesome-free-5.15.4-web/css/all.min.css">
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/sweetalert2.min.css">
    
    <style>
    /* ---------------------------------------------------- */
    /* Estilos de Fondo y Centrado (Institucional) */
    /* ---------------------------------------------------- */
    body {
        /* Fondo azul oscuro institucional */
        background: linear-gradient(135deg, #003366 0%, #001a33 100%); 
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        color: #fff; /* Texto blanco en el body */
        font-family: Arial, sans-serif;
        padding-bottom: 70px;
    }

    /* ---------------------------------------------------- */
    /* Contenedor Principal (Tarjeta Flotante) */
    /* ---------------------------------------------------- */
    .scanner-container {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        padding: 40px;
        width: 90%;
        max-width: 600px;
        text-align: center;
        color: #333; /* Texto oscuro dentro de la tarjeta */
        transition: transform 0.3s ease-in-out;
        margin-top: auto; 
        margin-bottom: auto;
    }
    
    .scanner-header {
        color: #003366; 
        font-weight: 700;
    }

    /* ---------------------------------------------------- */
    /* Campo de DNI Grande y Enfocado */
    /* ---------------------------------------------------- */
    #dniInput {
        height: 85px; 
        font-size: 3rem; 
        text-align: center;
        font-weight: bold;
        letter-spacing: 2px;
    }
    
    /* ---------------------------------------------------- */
    /* Caja de Estado (Feedback) */
    /* ---------------------------------------------------- */
    .status-box {
        margin-top: 30px;
        padding: 25px;
        border-radius: 8px;
        text-align: left;
        border: 1px solid #ddd;
        background-color: #f4f4f4; 
    }
    .status-header {
        font-size: 1.6rem;
        font-weight: bold;
        border-bottom: 2px solid #ccc;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }
    .status-box p {
        margin-bottom: 5px;
        font-size: 1.1rem;
    }
    
    /* ---------------------------------------------------- */
    /* Footer (Pie de Página) */
    /* ---------------------------------------------------- */
    .footer {
        width: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        color: rgba(255, 255, 255, 0.8);
        text-align: center;
        padding: 15px 0;
        position: fixed;
        bottom: 0;
        z-index: 100;
    }
    /* Estilo específico para el botón de inscripción */
    #btnInscribir {
        transition: background-color 0.3s;
    }
    </style>
</head>
<body>

<div class="scanner-container mb-5 mt-3">
    <h1 class="scanner-header mb-2">
        <i class="fas fa-file-signature"></i> INSCRIPCIÓN A EXÁMENES
    </h1>
    <h5 class="text-secondary mb-4">Escuela N° 752 - Rawson</h5>
    
    <div id="paso1_busqueda">
        <p class="lead">Paso 1: Ingrese DNI del alumno. Presione **ENTER**</p>
        <input type="text" id="dniInput" class="form-control" placeholder="DNI" autofocus autocomplete="off">
    </div>
    
    <div id="paso2_inscripcion" style="display:none;">
        <h3 class="text-primary mb-3">Paso 2: Seleccione el Espacio</h3>
        <div class="alert alert-info text-left">
            <strong>Alumno:</strong> <span id="info_nombre"></span><br>
            <strong>Carrera:</strong> <span id="info_carrera"></span>
        </div>
        <div class="form-group text-left">
            <label for="selectMateria">Materia a Inscribirse:</label>
            <select class="form-control" id="selectMateria" required>
                </select>
        </div>
        <button id="btnInscribir" class="btn btn-success btn-block mt-4"><i class="fas fa-check-circle"></i> Confirmar Inscripción</button>
        <button id="btnCancelar" class="btn btn-secondary btn-block mt-2"><i class="fas fa-times-circle"></i> Cancelar y Nueva Búsqueda</button>
    </div>
    
    <div id="statusBox" class="status-box shadow-sm">
        <div class="status-header text-muted">
            <i class="fas fa-search"></i> Esperando ingreso de DNI...
        </div>
        <p class="mb-0 mt-2" id="lastScan"></p>
    </div>

</div>

<div class="footer mt-5">
    Desarrollado por **Wiener, Jorge - Programador Universitario de Aplicaciones** - Todos los derechos reservados <?php echo date("Y"); ?>.
</div>


<script src="../js/jquery-3.5.1.min.js"></script>
<script src="../js/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    
    const $dniInput = $('#dniInput');
    const $statusHeader = $('#statusBox').find('.status-header');
    const $lastScan = $('#lastScan');
    const $paso1 = $('#paso1_busqueda');
    const $paso2 = $('#paso2_inscripcion');
    const $selectMateria = $('#selectMateria');
    
    let alumnoData = {}; // Global para guardar datos del alumno

    $dniInput.focus();

    // 1. Capturar la pulsación de ENTER en el campo DNI
    $dniInput.on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); 
            buscarAlumno();
        }
    });

    // 2. Evento para cancelar la inscripción
    $('#btnCancelar').on('click', function() {
        resetForm();
    });

    // 3. Función principal de búsqueda AJAX
    function buscarAlumno() {
        const dni = $dniInput.val().trim();
        
        if (dni.length < 5) {
            Swal.fire('Atención', 'Por favor ingrese un DNI válido.', 'warning');
            $dniInput.val('').focus();
            return;
        }
        
        $dniInput.prop('disabled', true);
        $statusHeader.html('<i class="fas fa-sync-alt fa-spin"></i> Buscando alumno...');

        $.ajax({
            url: 'index.php', // El mismo archivo
            type: 'POST',
            dataType: 'json',
            data: { action: 'buscar_alumno', dni: dni },
            success: function(response) {
                if (response.success) {
                    alumnoData = response.alumno;
                    cargarPaso2(response.materias);
                } else {
                    Swal.fire('Alumno No Encontrado', response.message, 'error');
                    updateStatusBox('error', response.message);
                    resetInput();
                }
            },
            error: function() {
                Swal.fire('Error de Conexión', 'No se pudo contactar al servidor.', 'error');
                updateStatusBox('error', 'Error de conexión con el servidor.');
                resetInput();
            }
        });
    }
    
    // 4. Muestra la información del alumno y las materias
    function cargarPaso2(materias) {
        // Cargar información básica
        $('#info_nombre').text(alumnoData.apellido + ', ' + alumnoData.nombre + ' (' + alumnoData.dni + ')');
        $('#info_carrera').text(alumnoData.nombre_carrera);
        
        // Llenar el select de materias
        $selectMateria.empty();
        $selectMateria.append('<option value="" disabled selected>-- Seleccionar Espacio --</option>');
        if (materias.length === 0) {
            $selectMateria.append('<option value="" disabled>No hay materias disponibles para esta carrera.</option>');
            $('#btnInscribir').prop('disabled', true).text('No hay Materias');
        } else {
            $.each(materias, function(i, materia) {
                $selectMateria.append($('<option>', { 
                    value: materia.id_espacio,
                    text: materia.nombre_espacio
                }));
            });
            $('#btnInscribir').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Confirmar Inscripción');
        }
        
        // Cambiar la vista
        $paso1.hide();
        $paso2.show();
        updateStatusBox('info', 'Seleccione la materia y confirme la inscripción.');
        $dniInput.prop('disabled', false).val(''); // Liberar input por si se cancela
        $selectMateria.focus();
    }
    
    // 5. Evento para confirmar la inscripción
    $('#btnInscribir').on('click', function() {
        const id_espacio = $selectMateria.val();
        const nombre_materia = $selectMateria.find('option:selected').text();

        if (!id_espacio) {
            Swal.fire('Atención', 'Debe seleccionar una materia.', 'warning');
            return;
        }

        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Registrando...');
        
        $.ajax({
            url: 'index.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'registrar_inscripcion',
                id_alumno: alumnoData.id_alumno,
                id_carrera: alumnoData.id_carrera,
                id_espacio: id_espacio,
                nombre_materia: nombre_materia // Campo extra para el feedback
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Inscripción Exitosa!',
                        text: `Alumno inscripto a: ${response.nombre_materia}.`,
                        timer: 5000,
                        showConfirmButton: false
                    });
                    updateStatusBox('success', `Inscripción registrada a ${response.nombre_materia}.`);
                } else {
                    Swal.fire('Error de Registro', response.message, 'error');
                    updateStatusBox('error', response.message);
                }
                resetForm();
            },
            error: function() {
                Swal.fire('Error de Conexión', 'No se pudo registrar la inscripción.', 'error');
                updateStatusBox('error', 'Error de conexión.');
                resetForm();
            }
        });
    });

    // 6. Funciones de utilidades
    function resetInput() {
        $dniInput.val('');
        $dniInput.prop('disabled', false).focus();
    }
    
    function resetForm() {
        alumnoData = {};
        $paso2.hide();
        $paso1.show();
        $dniInput.val('');
        $statusHeader.removeClass('text-success text-danger text-info').addClass('text-muted').html('<i class="fas fa-search"></i> Esperando ingreso de DNI...');
        $lastScan.empty();
        resetInput();
    }

    function updateStatusBox(type, message) {
        let icon = '';
        let headerText = '';
        let headerClass = 'text-muted';
        
        switch(type) {
            case 'success':
                icon = '<i class="fas fa-check-circle"></i>';
                headerText = 'Registro Completo';
                headerClass = 'text-success';
                break;
            case 'error':
                icon = '<i class="fas fa-times-circle"></i>';
                headerText = 'Error en el Proceso';
                headerClass = 'text-danger';
                break;
            case 'info':
                icon = '<i class="fas fa-info-circle"></i>';
                headerText = 'Información del Alumno';
                headerClass = 'text-primary';
                break;
            default:
                icon = '<i class="fas fa-search"></i>';
                headerText = 'Esperando ingreso de DNI...';
                break;
        }
        
        $statusHeader.removeClass('text-muted text-success text-danger text-primary').addClass(headerClass).html(`${icon} ${headerText}`);
        $lastScan.html(`<p><strong>Mensaje:</strong> ${message}</p>`);
    }

});
</script>
</body>
</html>