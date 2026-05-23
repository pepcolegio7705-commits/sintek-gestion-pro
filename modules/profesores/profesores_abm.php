<?php
    /**
     * LÓGICA DE PROCESAMIENTO: PROFESORES ABM (VERSIÓN BLINDADA UUID + SINDICATO DINÁMICO)
     * Ubicación: /modules/profesores/profesores_abm.php
     */

    session_start();

    // 1. CARGA DE DEPENDENCIAS
    require_once '../../core/conexion.php';
    require_once '../../core/funciones.php'; 
    require_once '../../core/seguridad.php';

    verificar_permisos(['Administrador', 'Secretaría']);

    $rol = $_SESSION['rol']; 
    $mensaje = [];
    $profesor_editar = null;
    $retenciones_existentes = [];

    // 2. CARGA DE ÁREAS Y SINDICATOS PARA LOS SELECTS
    $areas = $pdo->query("SELECT id_area, nombre_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);

    // NUEVO: Consulta para cargar sindicatos disponibles
    $sindicatos = $pdo->query("SELECT id_entidad, nombre_entidad, porcentaje_retencion 
                           FROM entidades_pagos_terceros 
                           WHERE estado = 1 
                           AND tipo_entidad = 'Sindicato'
                           AND categoria_afectada IN ('Profesor', 'Todos') 
                           ORDER BY nombre_entidad ASC")->fetchAll(PDO::FETCH_ASSOC);

    /**
     * FUNCIÓN SUBIR ARCHIVO SEGURA
     */
    function subirArchivoDocente($file, $dni, $tipo) {
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            $folder = "../../uploads/docentes/";
            if (!file_exists($folder)) { mkdir($folder, 0777, true); }
            
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nombre_archivo = $tipo . "_" . $dni . "_" . bin2hex(random_bytes(4)) . "." . $extension;
            
            if (move_uploaded_file($file['tmp_name'], $folder . $nombre_archivo)) { 
                return "uploads/docentes/" . $nombre_archivo; 
            }
        }
        return null;
    }

    // 3. RECUPERAR DATOS PARA EDICIÓN
    if (isset($_GET['uuid']) && isset($_GET['action']) && $_GET['action'] == 'edit') {
        $uuid_url = $_GET['uuid'];
        
        $stmt = $pdo->prepare("SELECT p.*, u.email as email_usuario 
                            FROM profesores p 
                            LEFT JOIN usuarios u ON p.nombre_usuario = u.nombre_usuario 
                            WHERE p.uuid_profesor = ? LIMIT 1");
        $stmt->execute([$uuid_url]);
        $profesor_editar = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profesor_editar) {
            $id_int = $profesor_editar['id_profesor'];
            $stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales 
                                    WHERE id_persona = ? AND tipo_persona = 'Profesor' AND activo = 1");
            $stmt_ret->execute([$id_int]);
            $retenciones_existentes = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);
        } else {
            registrar_log_seguridad($pdo, 'URL_MANIPULADA_EDICION', "Intento de acceso a UUID inexistente: " . htmlspecialchars($uuid_url));
            $mensaje = [
                'icon' => 'error',
                'title' => 'Acceso Denegado',
                'text' => 'El identificador de legajo no es válido.'
            ];
            $profesor_editar = null;
        }
    }

    // 4. ACCIÓN RÁPIDA: TOGGLE ESTADO (Sin cambios)
    if (isset($_GET['action']) && $_GET['action'] == 'toggle' && isset($_GET['uuid'])) {
        $uuid_toggle = $_GET['uuid'];
        $st = (int)$_GET['st'];

        $stmt_id = $pdo->prepare("SELECT id_profesor FROM profesores WHERE uuid_profesor = ?");
        $stmt_id->execute([$uuid_toggle]);
        $id_val = $stmt_id->fetchColumn();

        if ($id_val) {
            if ($st == 0) {
                $check = $pdo->prepare("SELECT COUNT(*) FROM profesores_espacios WHERE id_profesor = ?");
                $check->execute([$id_val]);
                if ($check->fetchColumn() > 0) {
                    $mensaje = ['icon' => 'error', 'title' => 'Bloqueado', 'text' => "El docente tiene materias asignadas activas."];
                    goto mostrar_formulario;
                }
            }
            $pdo->prepare("UPDATE profesores SET activo = ? WHERE id_profesor = ?")->execute([$st, $id_val]);
            header("Location: " . BASE_URL . "profesores/gestion"); 
            exit;
        }
    }

    // 5. PROCESAMIENTO POST (GUARDADO)
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        $uuid_post = $_POST['uuid_profesor'] ?? null;
        $legajo = trim($_POST['legajo'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $cuil = trim($_POST['cuil'] ?? '');
        $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
        $password_input = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $cbu = trim($_POST['cbu'] ?? '');
        
        // NUEVO: Capturar el ID del sindicato seleccionado
        $id_entidad_sindicato = !empty($_POST['id_entidad_sindicato']) ? (int)$_POST['id_entidad_sindicato'] : null;

        $stmt_exists = $pdo->prepare("SELECT id_profesor, nombre_usuario FROM profesores WHERE uuid_profesor = ?");
        $stmt_exists->execute([$uuid_post]);
        $docente_db = $stmt_exists->fetch(PDO::FETCH_ASSOC);
        $id_actual = $docente_db ? $docente_db['id_profesor'] : 0;

        // --- VALIDACIÓN DE UNICIDAD AMPLIADA ---
        $sql_check = "SELECT 
                        SUM(CASE WHEN legajo = :leg THEN 1 ELSE 0 END) as e_legajo,
                        SUM(CASE WHEN dni = :dni THEN 1 ELSE 0 END) as e_dni,
                        SUM(CASE WHEN cuil = :cuil AND cuil <> '' THEN 1 ELSE 0 END) as e_cuil,
                        SUM(CASE WHEN cbu = :cbu AND cbu <> '' THEN 1 ELSE 0 END) as e_cbu
                    FROM profesores 
                    WHERE (legajo = :leg_alt OR dni = :dni_alt OR (cuil = :cuil_alt AND cuil <> '') OR (cbu = :cbu_alt AND cbu <> ''))
                    AND id_profesor <> :id_actual";

        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute([
            ':leg' => $legajo, ':leg_alt' => $legajo, ':dni' => $dni, ':dni_alt' => $dni,
            ':cuil' => $cuil, ':cuil_alt' => $cuil, ':cbu' => $cbu, ':cbu_alt' => $cbu,
            ':id_actual' => $id_actual
        ]);
        $res_check = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $errores = [];
        if (($res_check['e_legajo'] ?? 0) > 0) $errores[] = "Legajo";
        if (($res_check['e_dni'] ?? 0) > 0)    $errores[] = "DNI";
        if (($res_check['e_cuil'] ?? 0) > 0)   $errores[] = "CUIL";
        if (($res_check['e_cbu'] ?? 0) > 0)    $errores[] = "CBU";

        if (!empty($errores)) {
            registrar_log_seguridad($pdo, 'INTENTO_DUPLICADO', "Campos duplicados: " . implode(", ", $errores));
            echo "Error: Ya existe un registro con el mismo " . implode(", ", $errores) . ".";
            exit;
        }

        try {
            $pdo->beginTransaction();

            $pdf_dni    = subirArchivoDocente($_FILES['pdf_dni'] ?? null, $dni, "DNI");
            $pdf_cv     = subirArchivoDocente($_FILES['pdf_cv'] ?? null, $dni, "CV");
            $pdf_titulo = subirArchivoDocente($_FILES['pdf_titulo'] ?? null, $dni, "TITULO");
            $pdf_hijos  = subirArchivoDocente($_FILES['pdf_hijos'] ?? null, $dni, "HIJOS");

            $hash = !empty($password_input) ? password_hash($password_input, PASSWORD_DEFAULT) : null;

            if (!$docente_db) {
                // --- NUEVO DOCENTE ---
                $new_uuid = generar_uuid_v4();
                $sql_u = "INSERT INTO usuarios (nombre_usuario, password, apellido, email, id_rol) VALUES (?, ?, ?, ?, 3)";
                $pdo->prepare($sql_u)->execute([$nombre_usuario, $hash, strtoupper($_POST['apellido']), $email]);

                $sql_p = "INSERT INTO profesores (uuid_profesor, legajo, dni, nombre_usuario, cbu, banco, password, cuil, nombre, apellido, fecha_nacimiento, sexo, estado_civil, domicilio, localidad, telefono, email, fecha_ingreso, cantidad_hijos, hijos_verificados, titulo_principal, id_area, activo, observaciones, pago_banco, id_entidad_sindicato, cobra_presentismo, ruta_pdf_dni, ruta_pdf_cv, ruta_pdf_titulo, ruta_pdf_hijos) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $pdo->prepare($sql_p)->execute([
                    $new_uuid, $legajo, $dni, $nombre_usuario, $cbu, $_POST['banco'], $hash, $cuil,
                    strtoupper($_POST['nombre']), strtoupper($_POST['apellido']), $_POST['fecha_nacimiento'], $_POST['sexo'],
                    $_POST['estado_civil'], $_POST['domicilio'], $_POST['localidad'], $_POST['telefono'], $email,
                    $_POST['fecha_ingreso'], (int)$_POST['cantidad_hijos'], isset($_POST['hijos_verificados']) ? 1 : 0,
                    $_POST['titulo_principal'], (int)$_POST['id_area'], 1, $_POST['observaciones'],
                    isset($_POST['pago_banco']) ? 1 : 0, $id_entidad_sindicato, isset($_POST['cobra_presentismo']) ? 1 : 0,
                    $pdf_dni, $pdf_cv, $pdf_titulo, $pdf_hijos
                ]);
                $id_final = $pdo->lastInsertId();
            } else {
                // --- ACTUALIZACIÓN DOCENTE ---
                $id_final = $docente_db['id_profesor'];

                $sql_up_u = "UPDATE usuarios SET nombre_usuario = ?, email = ?, apellido = ?" . ($hash ? ", password = ?" : "") . " WHERE nombre_usuario = ?";
                $params_u = [$nombre_usuario, $email, strtoupper($_POST['apellido'])];
                if($hash) $params_u[] = $hash;
                $params_u[] = $docente_db['nombre_usuario'];
                $pdo->prepare($sql_up_u)->execute($params_u);

                $extra_sql = ""; $extra_params = [];
                if ($pdf_dni)    { $extra_sql .= ", ruta_pdf_dni = ?"; $extra_params[] = $pdf_dni; }
                if ($pdf_cv)     { $extra_sql .= ", ruta_pdf_cv = ?"; $extra_params[] = $pdf_cv; }
                if ($pdf_titulo) { $extra_sql .= ", ruta_pdf_titulo = ?"; $extra_params[] = $pdf_titulo; }
                if ($pdf_hijos)  { $extra_sql .= ", ruta_pdf_hijos = ?"; $extra_params[] = $pdf_hijos; }
                if ($hash)       { $extra_sql .= ", password = ?"; $extra_params[] = $hash; }

                $sql_up_p = "UPDATE profesores SET legajo=?, dni=?, nombre_usuario=?, cbu=?, banco=?, cuil=?, nombre=?, apellido=?, fecha_nacimiento=?, sexo=?, estado_civil=?, domicilio=?, localidad=?, telefono=?, email=?, fecha_ingreso=?, cantidad_hijos=?, hijos_verificados=?, titulo_principal=?, id_area=?, activo=?, observaciones=?, pago_banco=?, id_entidad_sindicato=?, cobra_presentismo=? $extra_sql WHERE id_profesor = ?";
                
                $main_params = [
                    $legajo, $dni, $nombre_usuario, $cbu, $_POST['banco'], $cuil,
                    strtoupper($_POST['nombre']), strtoupper($_POST['apellido']), $_POST['fecha_nacimiento'], $_POST['sexo'],
                    $_POST['estado_civil'], $_POST['domicilio'], $_POST['localidad'], $_POST['telefono'], $email,
                    $_POST['fecha_ingreso'], (int)$_POST['cantidad_hijos'], isset($_POST['hijos_verificados']) ? 1 : 0,
                    $_POST['titulo_principal'], (int)$_POST['id_area'], isset($_POST['activo']) ? 1 : 0, $_POST['observaciones'],
                    isset($_POST['pago_banco']) ? 1 : 0, $id_entidad_sindicato, isset($_POST['cobra_presentismo']) ? 1 : 0
                ];

                $pdo->prepare($sql_up_p)->execute(array_merge($main_params, $extra_params, [$id_final]));
            }

            // --- RETENCIONES ---
            $pdo->prepare("DELETE FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Profesor'")->execute([$id_final]);
            if (!empty($_POST['ret_expediente'])) {
                $stmt_ret = $pdo->prepare("INSERT INTO retenciones_judiciales (id_persona, tipo_persona, nro_expediente, beneficiario_nombre, cuit_beneficiario, tipo_calculo, valor, cbu_destino, activo) VALUES (?, 'Profesor', ?, ?, ?, ?, ?, 1)");
                foreach ($_POST['ret_expediente'] as $k => $exp) {
                    if(!empty(trim($exp))) {
                        $stmt_ret->execute([$id_final, $exp, $_POST['ret_beneficiario'][$k], $_POST['ret_cuit'][$k], $_POST['ret_tipo'][$k], $_POST['ret_valor'][$k], $_POST['ret_cbu'][$k]]);
                    }
                }
            }

            $pdo->commit();
            echo "success"; exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo "Error de Base de Datos: " . $e->getMessage(); exit;
        }
    }

    mostrar_formulario:
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Legajo Docente | Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        body { background-color: #f8f9fa; }
        .nav-tabs .nav-link { color: #495057; border-radius: 0; }
        .nav-tabs .nav-link.active { font-weight: bold; color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .tab-content { border: 1px solid #dee2e6; border-top: 0; padding: 25px; background: #fff; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid px-4 mt-4">
        <h2 class="mb-4"><i class="fas fa-chalkboard-teacher text-primary"></i> Gestión de Docentes</h2>

        <div class="card shadow-sm mb-5">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><?= $profesor_editar ? '<i class="fas fa-user-edit"></i> Editando: ' . $profesor_editar['apellido'] : '<i class="fas fa-user-plus"></i> Nuevo Registro' ?></span>
            </div>
            <div class="card-body p-0">
                <form id="formProfesor" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="uuid_profesor" value="<?= $profesor_editar['uuid_profesor'] ?? '' ?>">

                    <ul class="nav nav-tabs px-3 pt-3 bg-light" id="profesorTab" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="basicos-tab" data-bs-toggle="tab" data-bs-target="#basicos" type="button" role="tab">Identidad</button></li>
                        <li class="nav-item"><button class="nav-link" id="acceso-tab" data-bs-toggle="tab" data-bs-target="#acceso" type="button" role="tab">Acceso</button></li>
                        <li class="nav-item"><button class="nav-link" id="contacto-tab" data-bs-toggle="tab" data-bs-target="#contacto" type="button" role="tab">Contacto</button></li>
                        <li class="nav-item"><button class="nav-link" id="laboral-tab" data-bs-toggle="tab" data-bs-target="#laboral" type="button" role="tab">Laboral</button></li>
                        <li class="nav-item"><button class="nav-link" id="familia-tab" data-bs-toggle="tab" data-bs-target="#familia" type="button" role="tab">Familia</button></li>
                        <li class="nav-item"><button class="nav-link" id="documentos-tab" data-bs-toggle="tab" data-bs-target="#documentos" type="button" role="tab">Documentos</button></li>
                        <li class="nav-item"><button class="nav-link" id="retenciones-tab" data-bs-toggle="tab" data-bs-target="#retenciones" type="button" role="tab">Retenciones</button></li> 
                        <li class="nav-item"><button class="nav-link" id="finanzas-tab" data-bs-toggle="tab" data-bs-target="#finanzas" type="button" role="tab">Finanzas</button></li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="basicos" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-2"><label class="form-label fw-bold">Legajo (*)</label><input type="text" name="legajo" class="form-control" value="<?= $profesor_editar['legajo'] ?? '' ?>" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">DNI (*)</label><input type="text" id="dni_input" name="dni" class="form-control" value="<?= $profesor_editar['dni'] ?? '' ?>" required onkeyup="document.getElementById('user_input').value = this.value"></div>
                                <div class="col-md-4"><label class="form-label fw-bold">Apellido (*)</label><input type="text" name="apellido" class="form-control" value="<?= $profesor_editar['apellido'] ?? '' ?>" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold">Nombre (*)</label><input type="text" name="nombre" class="form-control" value="<?= $profesor_editar['nombre'] ?? '' ?>" required></div>
                                <div class="col-md-3"><label class="form-label">CUIL</label><input type="text" name="cuil" class="form-control" value="<?= $profesor_editar['cuil'] ?? '' ?>"></div>
                                <div class="col-md-3"><label class="form-label">Fecha Nacimiento</label><input type="date" name="fecha_nacimiento" class="form-control" value="<?= $profesor_editar['fecha_nacimiento'] ?? '' ?>"></div>
                                <div class="col-md-3"><label class="form-label">Sexo</label>
                                    <select name="sexo" class="form-select">
                                        <option value="Masculino" <?= ($profesor_editar['sexo'] ?? '') == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                        <option value="Femenino" <?= ($profesor_editar['sexo'] ?? '') == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                    </select>
                                </div>
                                <div class="col-md-3"><label class="form-label">Estado Civil</label><input type="text" name="estado_civil" class="form-control" value="<?= $profesor_editar['estado_civil'] ?? '' ?>"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="acceso" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Nombre de Usuario</label>
                                        <input type="text" id="user_input" name="nombre_usuario" class="form-control" value="<?= $profesor_editar['nombre_usuario'] ?? '' ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Nueva Clave</label>
                                        <input type="password" name="password" class="form-control" <?= $profesor_editar ? '' : 'required' ?> placeholder="<?= $profesor_editar ? 'Dejar en blanco para no cambiar' : 'Asignar contraseña' ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded shadow-sm border-start border-4 border-info">
                                        <h6><i class="fas fa-info-circle text-info"></i> Ayuda de Seguridad</h6>
                                        <ul class="small mb-0">
                                            <li>Por defecto, el usuario se genera con el DNI.</li>
                                            <li>Si el docente olvida su clave, ingrese una nueva aquí.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="contacto" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Domicilio</label><input type="text" name="domicilio" class="form-control" value="<?= $profesor_editar['domicilio'] ?? '' ?>"></div>
                                <div class="col-md-3"><label class="form-label">Localidad</label><input type="text" name="localidad" class="form-control" value="<?= $profesor_editar['localidad'] ?? '' ?>"></div>
                                <div class="col-md-3"><label class="form-label">Teléfono</label><input type="text" name="telefono" class="form-control" value="<?= $profesor_editar['telefono'] ?? '' ?>"></div>
                                <div class="col-md-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $profesor_editar['email'] ?? '' ?>"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="laboral" role="tabpanel">
                            <div class="row g-3 p-3">
                                <div class="col-md-4"><label class="form-label fw-bold">Fecha Ingreso</label><input type="date" name="fecha_ingreso" class="form-control" value="<?= $profesor_editar['fecha_ingreso'] ?? '' ?>"></div>
                                <div class="col-md-8"><label class="form-label fw-bold">Título Principal / Especialidad</label><input type="text" name="titulo_principal" class="form-control" value="<?= $profesor_editar['titulo_principal'] ?? '' ?>" placeholder="Ej: Lic. en Ciencias de la Educación"></div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-primary">Área Asignada (*)</label>
                                    <select name="id_area" id="id_area_prof" class="form-select border-primary" required onchange="detectarTipoContrato()">
                                        <option value="">Seleccione...</option>
                                        <?php foreach($areas as $a): ?>
                                            <option value="<?= $a['id_area'] ?>" data-nombre="<?= htmlspecialchars(strtolower($a['nombre_area'])) ?>" <?= (isset($profesor_editar['id_area']) && $profesor_editar['id_area'] == $a['id_area']) ? 'selected' : '' ?>><?= $a['nombre_area'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Tipo Contratación</label>
                                    <select name="tipo_contratacion" id="tipo_contra_prof" class="form-select border-info">
                                        <option value="Relacion_Dependencia" <?= ($profesor_editar['tipo_contratacion'] ?? '') == 'Relacion_Dependencia' ? 'selected' : '' ?>>Relación de Dependencia</option>
                                        <option value="Monotributista" <?= ($profesor_editar['tipo_contratacion'] ?? '') == 'Monotributista' ? 'selected' : '' ?>>Monotributista (Factura)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Presentismo</label>
                                    <div class="form-check form-switch p-2 border rounded bg-light shadow-sm">
                                        <input type="checkbox" name="cobra_presentismo" class="form-check-input ms-0 me-2" id="pres" <?= ($profesor_editar['cobra_presentismo'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="pres">Aplica Adicional</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold small text-primary">CUOTA SINDICAL / AFILIACIÓN</label>
                                    <div class="p-2 border rounded bg-primary bg-opacity-10 shadow-sm border-primary border-opacity-25">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white border-primary border-opacity-25 text-primary">
                                                <i class="fas fa-handshake"></i>
                                            </span>
                                            <select name="id_entidad_sindicato" class="form-select border-primary border-opacity-25">
                                                <option value="">-- No Afiliado / Ninguno --</option>
                                                <?php foreach($sindicatos as $s): ?>
                                                    <?php 
                                                        // Verificamos si es el sindicato que ya tiene asignado
                                                        $selected = (isset($profesor_editar['id_entidad_sindicato']) && $profesor_editar['id_entidad_sindicato'] == $s['id_entidad']) ? 'selected' : ''; 
                                                    ?>
                                                    <option value="<?= $s['id_entidad'] ?>" <?= $selected ?>>
                                                        <?= htmlspecialchars($s['nombre_entidad']) ?> (<?= $s['porcentaje_retencion'] ?>%)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text small text-muted">Seleccione la entidad para aplicar el descuento automáticamente.</div>
                                </div>
                                <div class="col-12 mt-2"><label class="form-label fw-bold">Observaciones Internas / Legajo</label><textarea name="observaciones" class="form-control" rows="3"><?= $profesor_editar['observaciones'] ?? '' ?></textarea></div>
                                <div class="col-12 mt-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($profesor_editar['activo'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="activo">Docente habilitado para acceso al sistema</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="familia" role="tabpanel">
                            <div class="row g-4 align-items-end">
                                <?php $tiene_archivo_hijos = !empty($profesor_editar['ruta_pdf_hijos']); ?>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Cantidad de Hijos</label>
                                    <input type="number" name="cantidad_hijos" id="cant_hijos" class="form-control" min="0" value="<?= $profesor_editar['cantidad_hijos'] ?? 0 ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label fw-bold">DDJJ de Hijos (PDF Combinado)</label>
                                    <input type="file" name="pdf_hijos" id="input_pdf_hijos" class="form-control" accept=".pdf" onchange="validarArchivoHijos()">
                                    <?php if($tiene_archivo_hijos): ?>
                                        <a href="<?= BASE_URL . $profesor_editar['ruta_pdf_hijos']; ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-file-pdf"></i> Ver Actual</a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check form-switch p-3 border rounded bg-light">
                                        <input type="checkbox" name="hijos_verificados" class="form-check-input" id="hijos_v" <?= ($profesor_editar['hijos_verificados'] ?? 0) ? 'checked' : '' ?> <?= (!$tiene_archivo_hijos) ? 'disabled' : '' ?>>
                                        <label class="form-check-label fw-bold text-primary" for="hijos_v">Documentación Verificada</label>
                                        <small id="msg_ayuda_hijos" class="d-block text-muted mt-1"><?= (!$tiene_archivo_hijos) ? 'Cargar archivo para verificar.' : 'Habilita pago de asignaciones.' ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="documentos" role="tabpanel">
                            <div class="row g-4 text-center">
                                <div class="col-md-4">
                                    <i class="fas fa-id-card fa-3x text-muted mb-2"></i><br>
                                    <label class="form-label fw-bold">Escaneo DNI</label><input type="file" name="pdf_dni" class="form-control" accept=".pdf">
                                    <?php if(!empty($profesor_editar['ruta_pdf_dni'])): ?><a href="<?= BASE_URL . $profesor_editar['ruta_pdf_dni']; ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-file-pdf"></i> Ver</a><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <i class="fas fa-file-pdf fa-3x text-muted mb-2"></i><br>
                                    <label class="form-label fw-bold">Curriculum Vitae</label><input type="file" name="pdf_cv" class="form-control" accept=".pdf">
                                    <?php if(!empty($profesor_editar['ruta_pdf_cv'])): ?><a href="<?= BASE_URL . $profesor_editar['ruta_pdf_cv']; ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-file-pdf"></i> Ver</a><?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <i class="fas fa-graduation-cap fa-3x text-muted mb-2"></i><br>
                                    <label class="form-label fw-bold">Copia Título</label><input type="file" name="pdf_titulo" class="form-control" accept=".pdf">
                                    <?php if(!empty($profesor_editar['ruta_pdf_titulo'])): ?><a href="<?= BASE_URL . $profesor_editar['ruta_pdf_titulo']; ?>" target="_blank" class="btn btn-sm btn-outline-danger mt-2"><i class="fas fa-file-pdf"></i> Ver</a><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="retenciones" role="tabpanel">
                            <div class="p-3 bg-white border rounded shadow-sm m-2">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h5 class="text-danger mb-0"><i class="fas fa-gavel"></i> Retenciones Judiciales / Embargos</h5>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="agregarFilaRetencion()"><i class="fas fa-plus"></i> Agregar</button>
                                </div>
                                <div id="contenedor-retenciones">
                                    <?php if (empty($retenciones_existentes)): ?>
                                        <p class="text-muted text-center py-3" id="msg-sin-retenciones">No hay retenciones registradas.</p>
                                    <?php else: foreach ($retenciones_existentes as $r): ?>
                                        <div class="row g-2 border-bottom pb-3 mb-3 fila-retencion shadow-sm p-2 bg-light rounded align-items-center">
                                            <div class="col-md-3">
                                                <label class="small fw-bold">Nro. Expediente</label>
                                                <input type="text" name="ret_expediente[]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['nro_expediente']) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="small fw-bold">Cálculo</label>
                                                <select name="ret_tipo[]" class="form-select form-select-sm">
                                                    <option value="Porcentaje" <?= $r['tipo_calculo'] == 'Porcentaje' ? 'selected' : '' ?>>%</option>
                                                    <option value="Monto Fijo" <?= $r['tipo_calculo'] == 'Monto Fijo' ? 'selected' : '' ?>>$</option>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="small fw-bold">Valor</label>
                                                <input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" value="<?= $r['valor'] ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="small fw-bold">CUIT Beneficiario</label>
                                                <input type="text" 
                                                    name="ret_cuit[]" 
                                                    class="form-control form-control-sm contador-input" 
                                                    data-length="11" 
                                                    value="<?= htmlspecialchars($r['cuit_beneficiario'] ?? '') ?>" 
                                                    placeholder="CUIT/CUIL (11 dígitos)" 
                                                    maxlength="11" 
                                                    required>
                                                <div class="text-xs text-muted ms-1">
                                                    <span class="count"><?= strlen($r['cuit_beneficiario'] ?? '') ?></span>/11
                                                </div>
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <label class="small d-block">&nbsp;</label>
                                                <button type="button" class="btn btn-sm btn-danger w-100" onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button>
                                            </div>

                                            <div class="col-md-5 mt-1">
                                                <input type="text" name="ret_beneficiario[]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['beneficiario_nombre']) ?>" placeholder="Nombre del Beneficiario" required>
                                            </div>
                                            <div class="col-md-7 mt-1">
                                                <input type="text" 
                                                    name="ret_cbu[]" 
                                                    class="form-control form-control-sm contador-input" 
                                                    data-length="22" 
                                                    value="<?= htmlspecialchars($r['cbu_destino'] ?? '') ?>" 
                                                    placeholder="CBU Destino (22 dígitos)" 
                                                    maxlength="22">
                                                <div class="text-xs text-muted ms-1">
                                                    <span class="count"><?= strlen($r['cbu_destino'] ?? '') ?></span>/22
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="finanzas" role="tabpanel">
                            <div class="row g-3 p-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-muted">CBU (22 dígitos)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-university text-primary"></i></span>
                                        <input type="text" name="cbu" id="cbu_input" class="form-control font-monospace" maxlength="22" value="<?= $profesor_editar['cbu'] ?? '' ?>">
                                    </div>
                                    <div id="cbu_helper" class="form-text small mt-1"><span id="cbu_counter">0</span> / 22 dígitos.</div>
                                </div>
                                <div class="col-md-4"><label class="form-label fw-bold">Banco</label><input type="text" name="banco" class="form-control" value="<?= $profesor_editar['banco'] ?? '' ?>"></div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Pago por Banco</label>
                                    <div class="form-check form-switch p-2 border rounded bg-warning bg-opacity-10 shadow-sm">
                                        <input type="checkbox" name="pago_banco" class="form-check-input ms-0 me-2" id="pago_banco_prof" <?= ($profesor_editar['pago_banco'] ?? 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="pago_banco_prof">Habilitar Lote BNA</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3">
                        <div class="d-flex justify-content-end gap-2">
                            <?php if($profesor_editar): ?><a href="<?= BASE_URL ?>profesores/gestion" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-lg px-5 shadow-sm"><i class="fas fa-save me-2"></i> <?= $profesor_editar ? 'Guardar Cambios' : 'Registrar Docente' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaProfesores" class="table table-hover table-striped align-middle shadow-sm bg-white w-100">
                <thead class="table-dark">
                    <tr>
                        <th>Legajo</th>
                        <th>Docente / Usuario</th>
                        <th>Titulación</th>
                        <th>Datos Bancarios</th> 
                        <th>Docs / Embargos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/jquery.dataTables.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>
    $(document).ready(function() {
        // 1. Inicialización de DataTable Blindada (UUID en la posición 31)
        $('#tablaProfesores').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": { 
                "url": "<?= BASE_URL; ?>profesores/data_server", 
                "type": "POST" 
            },
            "order": [[0, "desc"]],
            "columns": [
                { "data": 1 }, // Legajo
                { 
                    "data": null,
                    "render": function(data) {
                        var u = data[21] ? data[21] : 'Sin usuario';
                        return `<strong>${data[4]}, ${data[3]}</strong><br><small class="text-muted">User: ${u}</small>`;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        return `<span>${data[5]}</span><br><small class="text-info">${data[14] || ''}</small>`;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        var cuil = data[6] ? data[6] : '<span class="text-danger fw-bold">Falta CUIL</span>';
                        var cbu = data[23] ? `<span class="text-primary">${data[23]}</span>` : '<span class="badge bg-danger">FALTA CBU</span>';
                        var banco = data[24] ? `<br><small class="text-muted">${data[24]}</small>` : '';
                        
                        return `<small class="fw-bold">CUIL:</small> ${cuil}<br>
                                <small class="fw-bold">CBU:</small> ${cbu}${banco}`;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        let hijos = parseInt(data[16]) || 0;
                        let verificado = parseInt(data[17]) === 1;
                        let totalRet = parseInt(data[25]) || 0;
                        let html = '';
                        
                        if (hijos > 0) {
                            html += verificado ? 
                                `<span class="badge bg-success mb-1"><i class="fas fa-users"></i> ${hijos} (Verif.)</span>` : 
                                `<span class="badge bg-warning text-dark mb-1"><i class="fas fa-users"></i> ${hijos} (PENDIENTE)</span>`;
                        }
                        
                        if (totalRet > 0) {
                            html += `<br><span class="badge bg-danger pulse-danger" title="Embargos activos"><i class="fas fa-gavel"></i> EMBARGADO (${totalRet})</span>`;
                        }

                        return html || '<span class="text-muted small">Sin novedades</span>';
                    }
                },
                { 
                    "data": 15, // Estado
                    "render": function(data) {
                        return data == 1 ? '<span class="badge bg-success">Habilitado</span>' : '<span class="badge bg-danger">Bloqueado</span>';
                    }
                },
                { 
                    "data": null, 
                    "orderable": false,
                    "render": function(data) {
                        // Ahora data[26] es el uuid_profesor según el orden del SELECT
                        var uuid = data[26]; 
                        var st = (data[15] == 1);
                        
                        if (!uuid) {
                            console.error("Error: UUID no recibido en el índice 26", data);
                            return '<span class="text-danger">Error de enlace</span>';
                        }

                        return `
                            <div class="btn-group">
                                <a href="<?= BASE_URL ?>profesores/certificacion/${uuid}" target="_blank" class="btn btn-sm btn-outline-info" title="Descargar Certificación de Servicios">
                                <i class="fas fa-file-signature"></i>
                                </a>
                                <a href="<?= BASE_URL; ?>profesores/historial/${uuid}" class="btn btn-sm btn-dark" title="Historial"><i class="fas fa-history"></i></a>
                                <a href="<?= BASE_URL; ?>profesores/editar/${uuid}" class="btn btn-sm btn-info text-white" title="Editar"><i class="fas fa-edit"></i></a>
                                <button type="button" class="btn btn-sm ${st ? 'btn-warning' : 'btn-success'}" onclick="cambiarEstado('${uuid}', ${st ? 0 : 1})">
                                    <i class="fas ${st ? 'fa-user-lock' : 'fa-user-check'}"></i>
                                </button>
                                <a href="<?= BASE_URL; ?>profesores/asignar/${uuid}" class="btn btn-sm btn-primary" title="Asignar"><i class="fas fa-book"></i></a>
                                <a href="<?= BASE_URL; ?>profesores/legajo_pdf/${uuid}" target="_blank" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf"></i></a>
                            </div>`;
                    }
                }
            ],
            "language": { "url": "<?= BASE_URL; ?>datatables/Spanish.json" }
        });

        // 1. Script para contar caracteres y validar (Delegado)
        $(document).on('input', '.contador-input', function() {
            const input = $(this);
            let actual = input.val().replace(/\D/g, ''); // Solo números
            input.val(actual); 
            
            const requerido = input.data('length');
            const contenedorContador = input.next('.text-xs');
            const spanCount = contenedorContador.find('.count');
            
            spanCount.text(actual.length);

            if (actual.length === requerido) {
                contenedorContador.removeClass('text-muted text-danger').addClass('text-success fw-bold');
                input.addClass('is-valid').removeClass('is-invalid');
            } else if (actual.length > 0) {
                contenedorContador.removeClass('text-muted text-success fw-bold').addClass('text-danger');
                input.addClass('is-invalid').removeClass('is-valid');
            } else {
                contenedorContador.removeClass('text-success text-danger fw-bold').addClass('text-muted');
                input.removeClass('is-valid is-invalid');
            }
        });

        // 2. Disparar la validación inicial para los datos que vienen de la DB
        // Esto recorre los inputs existentes y los pinta de verde/rojo al cargar
        $('.contador-input').each(function() {
            $(this).trigger('input');
        });

        // Persistencia Pestañas
        var triggerTabList = [].slice.call(document.querySelectorAll('#profesorTab button'))
        triggerTabList.forEach(function (el) {
            var tab = new bootstrap.Tab(el);
            el.addEventListener('click', function (e) {
                e.preventDefault(); tab.show();
                localStorage.setItem('activeTabProf', el.getAttribute('data-bs-target'));
            });
        });

        var activeTab = localStorage.getItem('activeTabProf');
        if(activeTab) {
            var el = document.querySelector('button[data-bs-target="' + activeTab + '"]');
            if(el) { new bootstrap.Tab(el).show(); }
        }

        <?php if (!empty($mensaje)): ?>
        Swal.fire({ 
            icon: '<?= $mensaje['icon'] ?>', 
            title: '<?= $mensaje['title'] ?>', 
            text: '<?= addslashes($mensaje['text']) ?>' 
        });
        <?php endif; ?>
    });

    // --- FUNCIONES DE NAVEGACIÓN BLINDADAS ---
    function cambiarEstado(uuid, st) {
        const titulo = (st == 0) ? "¿Desactivar Docente?" : "¿Habilitar Docente?";
        
        Swal.fire({ 
            title: titulo, 
            text: (st == 0) ? "Se verificará que no tenga materias asignadas." : "Podrá volver a asignarse a espacios.",
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: (st == 0) ? '#d33' : '#198754',
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar'
        }).then((r) => { 
            if (r.isConfirmed) {
                // Redirección amigable usando el parámetro 'uuid' para el .htaccess
                window.location.href = `<?= BASE_URL; ?>profesores/gestion?action=toggle&uuid=${uuid}&st=${st}`; 
            }
        });
    }

    // --- SUBMIT DEL FORMULARIO CON FETCH (AJAX) ---
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        
        // Validación de CBU
        const cbuInput = document.getElementById('cbu_input');
        if (cbuInput && cbuInput.value.length > 0 && cbuInput.value.length < 22) {
            Swal.fire('Error de CBU', 'Debe tener 22 dígitos o estar vacío.', 'error');
            return;
        }

        const camposObligatorios = [
            { name: 'legajo', label: 'Legajo' }, { name: 'dni', label: 'DNI' },
            { name: 'apellido', label: 'Apellido' }, { name: 'nombre', label: 'Nombre' },
            { name: 'nombre_usuario', label: 'Usuario' }, { name: 'id_area', label: 'Área' }
        ];

        let faltantes = [];
        camposObligatorios.forEach(campo => {
            const input = document.querySelector(`[name="${campo.name}"]`);
            if (!input || input.value.trim() === "") {
                faltantes.push(campo.label);
                if(input) input.classList.add('is-invalid');
            } else {
                if(input) input.classList.remove('is-invalid');
            }
        });

        if (faltantes.length > 0) {
            Swal.fire({ icon: 'warning', title: 'Faltan campos', html: `Complete: <b>${faltantes.join(', ')}</b>` });
            return;
        }

        const uuidProf = document.getElementsByName('uuid_profesor')[0].value;
        const tituloAlerta = (uuidProf === "") ? '¿Registrar nuevo profesor?' : '¿Guardar cambios?';

        Swal.fire({
            title: tituloAlerta,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

                const formData = new FormData(form);
                // Si estamos editando, incluimos el uuid en la URL para que el .htaccess lo capture
                const urlAction = (uuidProf !== "") ? `<?= BASE_URL; ?>profesores/gestion?uuid=${uuidProf}` : `<?= BASE_URL; ?>profesores/gestion`;

                fetch(urlAction, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    if(data.trim() === "success") {
                        Swal.fire({ icon: 'success', title: '¡Éxito!', timer: 1500, showConfirmButton: false })
                        .then(() => window.location.href = '<?= BASE_URL; ?>profesores/gestion');
                    } else {
                        Swal.fire('Error', data, 'error');
                    }
                })
                .catch(error => Swal.fire('Error', 'Fallo de conexión', 'error'));
            }
        });
    });

    // --- LÓGICA DINÁMICA DE RETENCIONES Y CBU ---
    function agregarFilaRetencion() {
        const contenedor = document.getElementById('contenedor-retenciones');
        const msg = document.getElementById('msg-sin-retenciones');
        if (msg) msg.remove();

        const div = document.createElement('div');
        div.className = 'row g-2 border-bottom pb-3 mb-3 fila-retencion shadow-sm p-2 bg-light rounded align-items-center';
        div.innerHTML = `
            <div class="col-md-3">
                <label class="small fw-bold">Nro. Expediente</label>
                <input type="text" name="ret_expediente[]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Cálculo</label>
                <select name="ret_tipo[]" class="form-select form-select-sm">
                    <option value="Porcentaje">Porcentaje %</option>
                    <option value="Monto Fijo">Monto Fijo $</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Valor</label>
                <input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold">CUIT Beneficiario</label>
                <input type="text" name="ret_cuit[]" class="form-control form-control-sm contador-input" 
                    data-length="11" placeholder="11 dígitos" maxlength="11" required>
                <div class="text-xs text-muted ms-1"><span class="count">0</span>/11</div>
            </div>
            <div class="col-md-1 text-end">
                <label class="small d-block">&nbsp;</label>
                <button type="button" class="btn btn-sm btn-danger w-100" onclick="eliminarFila(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="col-md-5 mt-1">
                <input type="text" name="ret_beneficiario[]" class="form-control form-control-sm" placeholder="Nombre del Beneficiario" required>
            </div>
            <div class="col-md-7 mt-1">
                <input type="text" name="ret_cbu[]" class="form-control form-control-sm contador-input" 
                    data-length="22" placeholder="CBU Destino (22 dígitos)" maxlength="22">
                <div class="text-xs text-muted ms-1"><span class="count">0</span>/22</div>
            </div>
        `;
        contenedor.appendChild(div);
    }

    function eliminarFila(btn) { btn.closest('.fila-retencion').remove(); }

    document.addEventListener('DOMContentLoaded', function() {
        const cbuInput = document.getElementById('cbu_input');
        if(cbuInput) {
            cbuInput.addEventListener('input', function() {
                let valor = this.value.replace(/\D/g, '');
                this.value = valor;
                const largo = valor.length;
                const helper = document.getElementById('cbu_helper');
                document.getElementById('cbu_counter').textContent = largo;

                if (largo === 22) {
                    this.classList.add('is-valid'); this.classList.remove('is-invalid');
                    helper.className = "form-text small mt-1 text-success fw-bold";
                } else if (largo > 0) {
                    this.classList.add('is-invalid'); this.classList.remove('is-valid');
                    helper.className = "form-text small mt-1 text-danger";
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                    helper.className = "form-text small mt-1 text-muted";
                }
            });
        }
    });
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>