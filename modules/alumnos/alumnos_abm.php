<?php
    /**
     * MÓDULO DE GESTIÓN DE LEGAJOS BLINDADO - SINTEK Premium
     * Estándar de seguridad: UUID v4 + Auditoría Forense
     */

    require_once '../../core/conexion.php';
    require_once '../../core/funciones.php'; // Vital para generar_uuid_v4() y registrar_log_seguridad()
    require_once '../../core/seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría']);

    $rol = $_SESSION['rol']; 
    $mensaje = [];
    if (isset($_SESSION['mensaje_alerta'])) {
        $mensaje = $_SESSION['mensaje_alerta'];
    }

    $alumno_editar = null;

    // --- 1. BLOQUE DE SEGURIDAD: CARGA POR UUID ---
    $uuid_externo = $_GET['uuid'] ?? null;

    if ($uuid_externo) {
        $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE uuid_alumno = :uuid LIMIT 1");
        $stmt->execute([':uuid' => $uuid_externo]);
        $alumno_editar = $stmt->fetch(PDO::FETCH_ASSOC);

        // REBOTE DE SEGURIDAD: Si el UUID es inventado y no es un delete
        if (!$alumno_editar && !isset($_GET['action'])) {
            registrar_log_seguridad($pdo, 'INTRUSION_URL', "Intento de acceso a UUID inexistente en Alumnos: $uuid_externo");
            header("Location: " . BASE_URL . "alumnos/gestion?error=seguridad");
            exit;
        }
    }

    // --- 2. LÓGICA CRUD (POST) ---
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id_alumno = (int)($_POST['id_alumno'] ?? 0);
        $dni = trim($_POST['dni']);
        $legajo = trim($_POST['legajo']);
        
        // Validación de duplicados (excluyendo al actual)
        $check = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE (dni = ? OR legajo = ?) AND id_alumno != ?");
        $check->execute([$dni, $legajo, $id_alumno]);
        
        if ($check->fetch()) {
            $mensaje = ['icon' => 'warning', 'title' => 'Datos Duplicados', 'text' => "El DNI o Legajo ya pertenece a otro alumno."];
        } else {
            // Preparación de archivos PDF
            $campos_pdf = ['pdf_dni', 'pdf_titulo', 'pdf_aptitud'];
            $nombres_archivos = [];
            $max_size = 2 * 1024 * 1024; 
            $error_archivo = false;

            foreach ($campos_pdf as $campo) {
                if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES[$campo]['size'] > $max_size) {
                        $mensaje = ['icon' => 'error', 'title' => 'Archivo excedido', 'text' => "El archivo $campo supera los 2MB."];
                        $error_archivo = true; break;
                    }
                    $ext = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        // Nombre de archivo profesional usando DNI y timestamp
                        $nuevo_nombre = $campo . "_" . $dni . "_" . time() . ".pdf";
                        $path_upload = "../../uploads/legajos/";
                        if (!is_dir($path_upload)) { mkdir($path_upload, 0777, true); }
                        move_uploaded_file($_FILES[$campo]['tmp_name'], $path_upload . $nuevo_nombre);
                        $nombres_archivos[$campo] = $nuevo_nombre;
                    }
                } else {
                    $nombres_archivos[$campo] = $_POST[$campo . "_actual"] ?? null;
                }
            }

            if (!$error_archivo) {
                try {
                    // Datos comunes para INSERT/UPDATE
                    $datos_db = [
                        ':leg' => $legajo, ':dni' => $dni, ':cuil' => trim($_POST['cuil']),
                        ':nom' => trim($_POST['nombre']), ':ape' => trim($_POST['apellido']),
                        ':em'  => trim($_POST['email']), ':tel' => trim($_POST['telefono']),
                        ':dir' => trim($_POST['direccion']), ':loc' => trim($_POST['localidad']),
                        ':pais'=> trim($_POST['pais']), ':ec' => trim($_POST['estado_civil']),
                        ':sl'  => trim($_POST['situacion_laboral']), ':lt' => trim($_POST['lugar_trabajo']),
                        ':h'   => (int)$_POST['hijos'], ':os' => trim($_POST['obra_social']),
                        ':pac' => (int)$_POST['personas_a_cargo'], ':act' => isset($_POST['activo']) ? 1 : 0,
                        ':lm'  => trim($_POST['libro_matriz']), ':fm' => trim($_POST['folio_matriz']),
                        ':bmens'=> (int)($_POST['beca_mensualidad'] ?? 0), ':bmat'=> (int)($_POST['beca_matricula'] ?? 0),
                        ':pdni' => $nombres_archivos['pdf_dni'], ':ptit' => $nombres_archivos['pdf_titulo'], ':papt' => $nombres_archivos['pdf_aptitud']
                    ];

                    if ($id_alumno == 0) {
                        // INSERT con generación de UUID
                        $nuevo_uuid = generar_uuid_v4();
                        $sql = "INSERT INTO alumnos (uuid_alumno, legajo, dni, cuil, nombre, apellido, email, telefono, direccion, localidad, pais, estado_civil, situacion_laboral, lugar_trabajo, hijos, obra_social, personas_a_cargo, activo, libro_matriz, folio_matriz, beca_mensualidad, beca_matricula, pdf_dni, pdf_titulo, pdf_aptitud, fecha_inscripcion) 
                                VALUES (:uuid, :leg, :dni, :cuil, :nom, :ape, :em, :tel, :dir, :loc, :pais, :ec, :sl, :lt, :h, :os, :pac, :act, :lm, :fm, :bmens, :bmat, :pdni, :ptit, :papt, NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_merge([':uuid' => $nuevo_uuid], $datos_db));
                        $exito_msg = "Alumno creado con identificador seguro.";
                    } else {
                        // UPDATE
                        $sql = "UPDATE alumnos SET legajo=:leg, dni=:dni, cuil=:cuil, nombre=:nom, apellido=:ape, email=:em, telefono=:tel, direccion=:dir, localidad=:loc, pais=:pais, estado_civil=:ec, situacion_laboral=:sl, lugar_trabajo=:lt, hijos=:h, obra_social=:os, personas_a_cargo=:pac, activo=:act, libro_matriz=:lm, folio_matriz=:fm, beca_mensualidad=:bmens, beca_matricula=:bmat, pdf_dni=:pdni, pdf_titulo=:ptit, pdf_aptitud=:papt 
                                WHERE id_alumno=:id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(array_merge([':id' => $id_alumno], $datos_db));
                        $exito_msg = "Legajo actualizado correctamente.";
                    }
                    
                    $_SESSION['mensaje_alerta'] = ['icon' => 'success', 'title' => '¡Hecho!', 'text' => $exito_msg];
                    header("Location: " . BASE_URL . "alumnos/gestion");
                    exit;

                } catch (PDOException $e) {
                    $mensaje = ['icon' => 'error', 'title' => 'Error de BD', 'text' => $e->getMessage()];
                }
            }
        }
    } 

    // --- 3. PROCESAMIENTO DELETE (BORRADO LÓGICO + INTEGRIDAD ACADÉMICA) ---
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && $uuid_externo) {
        try {
            if ($alumno_editar) {
                $id_v = $alumno_editar['id_alumno'];
                
                // 1. Integridad Financiera: ¿Tiene facturas?
                $stmt_fac = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE id_alumno = ?");
                $stmt_fac->execute([$id_v]);
                $tiene_facturas = $stmt_fac->fetchColumn();

                // 2. Integridad Académica A: ¿Está inscripto en carreras?
                $stmt_car = $pdo->prepare("SELECT COUNT(*) FROM alumnos_carreras WHERE id_alumno = ?");
                $stmt_car->execute([$id_v]);
                $tiene_carreras = $stmt_car->fetchColumn();

                // 3. Integridad Académica B: ¿Tiene espacio curricular asignado?
                $tiene_espacio = (!empty($alumno_editar['id_espacio']) && $alumno_editar['id_espacio'] != 0);

                // EVALUACIÓN DE BLOQUEO
                if ($tiene_facturas > 0 || $tiene_carreras > 0 || $tiene_espacio) {
                    // Definimos el motivo específico para el log y el mensaje
                    $motivos = [];
                    if ($tiene_facturas > 0) $motivos[] = "contables";
                    if ($tiene_carreras > 0) $motivos[] = "de carrera (inscripciones)";
                    if ($tiene_espacio)      $motivos[] = "de espacios curriculares";
                    
                    $string_motivos = implode(", ", $motivos);
                    
                    // Registramos el intento de borrado bloqueado en logs de seguridad
                    registrar_log_seguridad($pdo, 'BAJA_DENEGADA', "Intento de baja del alumno ID $id_v bloqueado por registros $string_motivos");

                    header("Location: " . BASE_URL . "alumnos/gestion?error=integridad&causa=" . urlencode($string_motivos));
                    exit;
                } else {
                    // PROCEDER A LA DESACTIVACIÓN (Borrado Lógico para Estadísticas)
                    $stmt = $pdo->prepare("UPDATE alumnos SET activo = 0 WHERE uuid_alumno = ?");
                    $stmt->execute([$uuid_externo]);
                    
                    // Log de éxito
                    registrar_log_seguridad($pdo, 'ALUMNO_DESACTIVADO', "Baja lógica exitosa del alumno ID $id_v");

                    $_SESSION['mensaje_alerta'] = ['icon' => 'success', 'title' => 'Baja Realizada', 'text' => 'El legajo ha sido desactivado pero conservado para registros estadísticos.'];
                    header("Location: " . BASE_URL . "alumnos/gestion?delete=success");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $mensaje = ['icon' => 'error', 'title' => 'Error Crítico', 'text' => 'Error de integridad en base de datos.'];
        }
    }

    // --- CARGAR MENSAJES POST-REDIRECCIÓN ---
    if (isset($_GET['error']) && $_GET['error'] == 'integridad') {
        $causa = isset($_GET['causa']) ? $_GET['causa'] : 'registros vinculados';
        $mensaje = [
            'icon' => 'warning', 
            'title' => 'No se puede eliminar', 
            'text' => "El alumno no puede ser dado de baja porque posee registros $causa. Por favor, desvincule al alumno de estas áreas antes de intentar nuevamente."
        ];
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Legajo Alumnos | <?= NOM_INST ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f4f6f9; padding-left: 250px; } /* Ajuste para el Sidebar */
        .nav-tabs .nav-link { color: #495057; }
        .nav-tabs .nav-link.active { font-weight: bold; color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .tab-content { border: 1px solid #dee2e6; border-top: none; padding: 25px; background: #fff; border-radius: 0 0 8px 8px; }
        .card { border-radius: 8px; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="text-primary"><i class="fas fa-user-graduate"></i> Gestión de Legajos</h2>
        <div class="btn-group shadow-sm">
            <a href="<?= BASE_URL; ?>alumnos/reporte/activos" target="_blank" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fas fa-print"></i> Activos
            </a>
            <a href="<?= BASE_URL; ?>formulario_inscripcion_blanco" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-file-download"></i> Formulario Papel
            </a>
        </div>
    </div>
    
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-folder-open me-2"></i>
            <?= $alumno_editar ? 'Modificar Alumno: ' . htmlspecialchars($alumno_editar['apellido'] . ', ' . $alumno_editar['nombre']) : 'Registrar Nuevo Alumno'; ?>
        </div>
        <div class="card-body">
            <form id="formAlumno" method="POST" action="<?= BASE_URL; ?>alumnos/gestion" enctype="multipart/form-data">
                <input type="hidden" name="id_alumno" id="id_alumno" value="<?= $alumno_editar['id_alumno'] ?? 0; ?>">
                
                <ul class="nav nav-tabs" id="alumnoTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#basicos" type="button">Datos Personales</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#social" type="button">Socio-Laboral</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#economico" type="button">Económico</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#documentos" type="button">Documentación (PDF)</button>
                    </li>
                </ul>

                <div class="tab-content shadow-sm">
                    <div class="tab-pane fade show active" id="basicos">
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Legajo</label>
                                <input type="text" name="legajo" class="form-control" value="<?= $alumno_editar['legajo'] ?? ''; ?>" required id="inputLegajo">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label text-primary fw-bold">Libro Matriz</label>
                                <input type="text" name="libro_matriz" class="form-control" value="<?= $alumno_editar['libro_matriz'] ?? ''; ?>" placeholder="Ej: 01">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label text-primary fw-bold">Folio Matriz</label>
                                <input type="text" name="folio_matriz" class="form-control" value="<?= $alumno_editar['folio_matriz'] ?? ''; ?>" placeholder="Ej: 150">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">DNI</label>
                                <input type="text" name="dni" class="form-control" value="<?= $alumno_editar['dni'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Estado Civil</label>
                                <select name="estado_civil" class="form-select">
                                    <?php $ec = $alumno_editar['estado_civil'] ?? ''; ?>
                                    <option <?= $ec=='Soltero/a'?'selected':'' ?>>Soltero/a</option>
                                    <option <?= $ec=='Casado/a'?'selected':'' ?>>Casado/a</option>
                                    <option <?= $ec=='Divorciado/a'?'selected':'' ?>>Divorciado/a</option>
                                    <option <?= $ec=='Viudo/a'?'selected':'' ?>>Viudo/a</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" name="nombre" class="form-control" value="<?= $alumno_editar['nombre'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Apellido</label>
                                <input type="text" name="apellido" class="form-control" value="<?= $alumno_editar['apellido'] ?? ''; ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">CUIL</label>
                                <input type="text" name="cuil" class="form-control" value="<?= $alumno_editar['cuil'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="<?= $alumno_editar['telefono'] ?? ''; ?>" placeholder="Celular/Fijo">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= $alumno_editar['email'] ?? ''; ?>" placeholder="correo@ejemplo.com">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Localidad</label>
                                <input type="text" name="localidad" class="form-control" value="<?= $alumno_editar['localidad'] ?? 'Rawson'; ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" name="direccion" class="form-control" value="<?= $alumno_editar['direccion'] ?? ''; ?>">
                            </div>
                            <input type="hidden" name="pais" value="Argentina">
                        </div>
                    </div>

                    <div class="tab-pane fade" id="social">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Situación Laboral</label>
                                <select name="situacion_laboral" class="form-select">
                                    <?php $sl = $alumno_editar['situacion_laboral'] ?? ''; ?>
                                    <option <?= $sl=='Desempleado'?'selected':'' ?>>Desempleado</option>
                                    <option <?= $sl=='Empleado'?'selected':'' ?>>Empleado</option>
                                    <option <?= $sl=='Independiente'?'selected':'' ?>>Independiente</option>
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Lugar de Trabajo</label>
                                <input type="text" name="lugar_trabajo" class="form-control" value="<?= $alumno_editar['lugar_trabajo'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Cant. Hijos</label>
                                <input type="number" name="hijos" class="form-control" value="<?= $alumno_editar['hijos'] ?? 0; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Obra Social</label>
                                <input type="text" name="obra_social" class="form-control" value="<?= $alumno_editar['obra_social'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Personas a Cargo</label>
                                <input type="number" name="personas_a_cargo" class="form-control" value="<?= $alumno_editar['personas_a_cargo'] ?? 0; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="economico">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Configure los beneficios económicos del alumno. Una beca del 100% en mensualidad evitará que el alumno sea marcado como moroso.
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-primary">Beca Mensualidad (%)</label>
                                <div class="input-group">
                                    <input type="number" name="beca_mensualidad" class="form-control" min="0" max="100" value="<?= $alumno_editar['beca_mensualidad'] ?? 0; ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-primary">Beca Matrícula (%)</label>
                                <div class="input-group">
                                    <input type="number" name="beca_matricula" class="form-control" min="0" max="100" value="<?= $alumno_editar['beca_matricula'] ?? 0; ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="documentos">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">DNI (PDF)</label>
                                <input type="file" name="pdf_dni" class="form-control" accept=".pdf" onchange="validarTamaño(this)">
                                <input type="hidden" name="pdf_dni_actual" value="<?= $alumno_editar['pdf_dni'] ?? ''; ?>">
                                <?php if(!empty($alumno_editar['pdf_dni'])): ?> 
                                    <div class="mt-2 small text-success"><i class="fas fa-check-circle"></i> Cargado: <?= htmlspecialchars($alumno_editar['pdf_dni']) ?></div> 
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Título (PDF)</label>
                                <input type="file" name="pdf_titulo" class="form-control" accept=".pdf" onchange="validarTamaño(this)">
                                <input type="hidden" name="pdf_titulo_actual" value="<?= $alumno_editar['pdf_titulo'] ?? ''; ?>">
                                <?php if(!empty($alumno_editar['pdf_titulo'])): ?> 
                                    <div class="mt-2 small text-success"><i class="fas fa-check-circle"></i> Cargado: <?= htmlspecialchars($alumno_editar['pdf_titulo']) ?></div> 
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Aptitud Física (PDF)</label>
                                <input type="file" name="pdf_aptitud" class="form-control" accept=".pdf" onchange="validarTamaño(this)">
                                <input type="hidden" name="pdf_aptitud_actual" value="<?= $alumno_editar['pdf_aptitud'] ?? ''; ?>">
                                <?php if(!empty($alumno_editar['pdf_aptitud'])): ?> 
                                    <div class="mt-2 small text-success"><i class="fas fa-check-circle"></i> Cargado: <?= htmlspecialchars($alumno_editar['pdf_aptitud']) ?></div> 
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex align-items-center justify-content-between">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="checkActivo" <?= (!isset($alumno_editar) || $alumno_editar['activo'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="checkActivo">Alumno en estado Activo</label>
                    </div>
                    <div>
                        <?php if ($alumno_editar): ?> 
                            <a href="<?= BASE_URL; ?>alumnos/gestion" class="btn btn-secondary me-2">Cancelar</a> 
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary px-5 shadow"><?= $alumno_editar ? '<i class="fas fa-save"></i> Guardar Cambios' : '<i class="fas fa-plus"></i> Registrar Alumno'; ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-light fw-bold text-dark"><i class="fas fa-list me-2"></i> Listado General de Alumnos</div>
        <div class="card-body">
            <div class="table-responsive p-2">
                <table id="tablaAlumnos" class="table table-hover table-striped align-middle w-100">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Legajo</th>
                            <th>DNI</th>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th class="text-center">Estado</th> 
                            <th class="text-center">Docs</th> 
                            <th class="text-center">Acciones</th> 
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>
    // 1. FUNCIÓN GLOBAL DE ELIMINACIÓN (Ahora recibe UUID)
    function confirmarEliminacion(uuid, apellido) {
        Swal.fire({
            title: '¿Dar de baja?',
            html: `Se desactivará el legajo del alumno: <strong>${apellido}</strong>.<br><small class="text-danger">Esta acción quedará registrada en el log de seguridad.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Sí, desactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // REDIRECCIÓN A RUTA AMIGABLE CON UUID
                window.location.href = '<?= BASE_URL; ?>alumnos/eliminar/' + uuid;
            }
        });
    }

    // Funciones de utilidad
    function validarTamaño(input) {
        const maxSize = 2 * 1024 * 1024;
        if (input.files && input.files[0] && input.files[0].size > maxSize) {
            Swal.fire({ icon: 'error', title: 'Archivo excedido', text: 'El límite es de 2MB.' });
            input.value = "";
        }
    }

    function sugerirLegajo() {
        const idAlumnoElement = $('#id_alumno');
        const idAlumno = idAlumnoElement.length ? idAlumnoElement.val() : "";
        if (idAlumno == 0 || idAlumno == "") {
            $.get('<?= BASE_URL; ?>ajax/alumnos/get_next_legajo', function(data) {
                $('#inputLegajo').val(data);
            });
        }
    }

    $(document).ready(function() {
        // --- DATA TABLES ---
        $('#tablaAlumnos').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": { 
                "url": "<?= BASE_URL; ?>ajax/alumnos/server_processing_alumnos", 
                "type": "POST" 
            },
            "columns": [
                { "data": 0 }, // ID (interno)
                { "data": 1 }, // Legajo
                { "data": 2 }, // DNI
                { "data": 3 }, // Nombre
                { "data": 4 }, // Apellido
                { "data": 5, "className": "text-center" }, // Estado/Ciclo
                { "data": 6, "className": "text-center" }, // Fecha
                { 
                    "data": 8, // <--- ASUMIMOS QUE AQUÍ VIENE EL UUID_ALUMNO
                    "orderable": false,
                    "render": function(data, type, row) {
                        let uuid = data;
                        let apellidoParaBoton = row[4].replace(/'/g, "\\'");
                        
                        return `<div class="btn-group shadow-sm" role="group">
                                    <a href="<?= BASE_URL; ?>alumnos/ficha/${uuid}" target="_blank" class="btn btn-outline-info btn-sm" title="Ficha PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                   <a href="<?= BASE_URL; ?>alumnos/editar/${uuid}" class="btn btn-outline-primary btn-sm" title="Editar Alumno">
                                        <i class="fas fa-edit"></i>
                                   </a>
                                    <button class="btn btn-outline-danger btn-sm" onclick="confirmarEliminacion('${uuid}', '${apellidoParaBoton}')" title="Dar de Baja">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>`;
                    }
                }
            ],
            "order": [[4, "asc"]],
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.10.21/i18n/Spanish.json" },
        });

        // --- MANEJO DEL FORMULARIO CON SWEETALERT (CORREGIDO) ---
        // Usamos el ID del formulario para evitar capturar otros forms de la página
        $('#formAlumno').on('submit', function(e) {
            e.preventDefault();
            const form = this; 

            // Verificamos si existe el input id_alumno de forma segura
            const inputId = $('#id_alumno');
            const esNuevo = inputId.length > 0 ? (inputId.val() == 0) : true;
            
            const titulo = esNuevo ? '¿Confirmar alta de alumno?' : '¿Guardar cambios en el legajo?';
            const texto = esNuevo ? 'Se creará un nuevo registro en el sistema.' : 'Se sobrescribirán los datos actuales del alumno.';

            Swal.fire({
                title: titulo,
                text: texto,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Deshabilitamos el botón para evitar doble envío
                    $(form).find('button[type="submit"]').prop('disabled', true);
                    form.submit();
                }
            });
        });

        // --- DETECTOR DE ERRORES DE SEGURIDAD ---
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'seguridad') {
            Swal.fire({
                icon: 'error',
                title: 'Alerta de Seguridad',
                text: 'Se ha detectado un intento de acceso a un registro no válido.'
            }).then(() => {
                window.history.replaceState({}, document.title, "<?= BASE_URL; ?>alumnos/gestion");
            });
        }

        // --- MENSAJES PHP (REDIRECTS) ---
        <?php if (isset($mensaje) && !empty($mensaje) && isset($mensaje['icon'])): ?>
            //console.log("Intentando disparar Swal...");
            Swal.fire({ 
                icon: '<?= $mensaje['icon']; ?>', 
                title: '<?= $mensaje['title']; ?>', 
                text: '<?= $mensaje['text']; ?>',
                confirmButtonColor: '#3085d6'
            });
            <?php unset($_SESSION['mensaje_alerta']); // Limpiamos aquí, al final de la lógica de visualización ?>
        <?php endif; ?>

        sugerirLegajo();
    });
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>