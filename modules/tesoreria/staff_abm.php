<?php
    ob_start(); 
    session_start();
    require 'conexion.php';
    require 'seguridad.php';

    // Desactivamos la visualización de errores para el JSON
    error_reporting(0);
    ini_set('display_errors', 0);

    verificar_permisos(['Administrador']);
    $rol = $_SESSION['rol'];
    $staff_editar = null;
    $retenciones_existentes = []; // <--- NUEVO

    $areas = $pdo->query("SELECT id_area, nombre_area, sueldo_base_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);
    $roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles WHERE id_rol != 3 ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);

    // 2. RECUPERAR DATOS (GET)
    if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $id_get = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT s.*, u.nombre_usuario, u.email as email_usuario, u.id_rol as rol_sistema 
                            FROM personal_staff s 
                            LEFT JOIN usuarios u ON s.id_usuario = u.id_usuario 
                            WHERE s.id_staff = ?");
        $stmt->execute([$id_get]);
        $staff_editar = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff_editar) {
            if ($staff_editar['activo'] == 0) {
                header("Location: staff_lista.php?error=bloqueado");
                exit;
            }
            // --- NUEVO: RECUPERAR RETENCIONES MÚLTIPLES ---
            $stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff' AND activo = 1");
            $stmt_ret->execute([$id_get]);
            $retenciones_existentes = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    function subirArchivoPersonal($file, $dni, $tipo) {
        $folder = "uploads/personal/";
        if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
            if (!file_exists($folder)) { mkdir($folder, 0777, true); }
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nombre_archivo = $tipo . "_" . $dni . "_" . time() . "." . $extension;
            $ruta_destino = $folder . $nombre_archivo;
            if (move_uploaded_file($file['tmp_name'], $ruta_destino)) { return $ruta_destino; }
        }
        return null;
    }

    // 4. PROCESAMIENTO POST
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        ob_clean();
        header('Content-Type: application/json');

        $id_staff = isset($_POST['id_staff']) ? (int)$_POST['id_staff'] : 0;
        $dni = trim($_POST['dni'] ?? '');
        $id_area_post = (int)($_POST['id_area'] ?? 0);

        try {
            if (empty($dni)) throw new Exception("El DNI es obligatorio.");

            $pdo->beginTransaction();

            $stmt_s = $pdo->prepare("SELECT sueldo_base_area FROM areas WHERE id_area = ?");
            $stmt_s->execute([$id_area_post]);
            $sueldo_oficial = $stmt_s->fetchColumn() ?: 0;

            $id_usuario_vinculado = !empty($_POST['id_usuario_actual']) ? (int)$_POST['id_usuario_actual'] : null;
            
            if (isset($_POST['crear_usuario'])) {
                $user_name = trim($_POST['nombre_usuario'] ?? '');
                $email_u   = trim($_POST['email_usuario'] ?? '');
                $rol_sis   = (int)($_POST['id_rol'] ?? 0);
                $pass      = $_POST['password'] ?? '';
                $apellido_u = trim($_POST['apellido'] ?? '');

                if (empty($id_usuario_vinculado)) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $sql_u = "INSERT INTO usuarios (nombre_usuario, password, apellido, email, id_rol) VALUES (?, ?, ?, ?, ?)";
                    $stmt_u = $pdo->prepare($sql_u);
                    $stmt_u->execute([$user_name, $hash, $apellido_u, $email_u, $rol_sis]);
                    $id_usuario_vinculado = $pdo->lastInsertId();
                } else {
                    if (!empty($pass)) {
                        $sql_u = "UPDATE usuarios SET nombre_usuario=?, email=?, id_rol=?, password=? WHERE id_usuario=?";
                        $pdo->prepare($sql_u)->execute([$user_name, $email_u, $rol_sis, password_hash($pass, PASSWORD_DEFAULT), $id_usuario_vinculado]);
                    } else {
                        $sql_u = "UPDATE usuarios SET nombre_usuario=?, email=?, id_rol=? WHERE id_usuario=?";
                        $pdo->prepare($sql_u)->execute([$user_name, $email_u, $rol_sis, $id_usuario_vinculado]);
                    }
                }
            }

            $pdf_dni    = subirArchivoPersonal($_FILES['pdf_dni'] ?? null, $dni, "DNI");
            $pdf_cv     = subirArchivoPersonal($_FILES['pdf_cv'] ?? null, $dni, "CV");
            $pdf_titulo = subirArchivoPersonal($_FILES['pdf_titulo'] ?? null, $dni, "TITULO");
            $pdf_hijos  = subirArchivoPersonal($_FILES['pdf_hijos'] ?? null, $dni, "HIJOS");

            $data = [
                'id_u'      => $id_usuario_vinculado,
                'legajo'    => trim($_POST['legajo'] ?? ''),
                'dni'       => $dni,
                'cuil'      => trim($_POST['cuil'] ?? ''),
                'apellido'  => trim($_POST['apellido'] ?? ''),
                'nombre'    => trim($_POST['nombre'] ?? ''),
                'nac'       => trim($_POST['nacionalidad'] ?? 'Argentina'),
                'tel'       => trim($_POST['telefono'] ?? ''),
                'email_p'   => trim($_POST['email_personal'] ?? ''),
                'dir'       => trim($_POST['direccion'] ?? ''),
                'id_area'   => $id_area_post,
                'sueldo'    => (float)$sueldo_oficial,
                'cbu'       => trim($_POST['cbu'] ?? ''),
                'banco'     => trim($_POST['banco'] ?? ''),
                'f_ing'     => !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : date('Y-m-d'),
                'hijos'     => (int)($_POST['cantidad_hijos'] ?? 0),
                'h_verif'   => isset($_POST['hijos_verificados']) ? 1 : 0,
                'p_banco'   => isset($_POST['pago_banco']) ? 1 : 0,
                't_contra'  => $_POST['tipo_contratacion'] ?? 'Relacion_Dependencia',
                'sind'      => isset($_POST['afiliado_sindicato']) ? 1 : 0, 
                'pres'      => isset($_POST['cobra_presentismo']) ? 1 : 0
            ];

            if ($id_staff == 0) {
                $sql = "INSERT INTO personal_staff (id_usuario, legajo, dni, cuil, apellido, nombre, nacionalidad, telefono, email_personal, direccion, id_area, sueldo_base, cbu, banco, fecha_ingreso, cantidad_hijos, hijos_verificados, pago_banco, tipo_contratacion, afiliado_sindicato, cobra_presentismo, ruta_pdf_dni, ruta_pdf_cv, ruta_pdf_titulo, ruta_pdf_hijos) 
                        VALUES (:id_u, :legajo, :dni, :cuil, :apellido, :nombre, :nac, :tel, :email_p, :dir, :id_area, :sueldo, :cbu, :banco, :f_ing, :hijos, :h_verif, :p_banco, :t_contra,:sind, :pres,:r_dni, :r_cv, :r_tit, :r_hijos)";
                $data['r_dni'] = $pdf_dni; $data['r_cv'] = $pdf_cv; $data['r_tit'] = $pdf_titulo; $data['r_hijos'] = $pdf_hijos;
                $pdo->prepare($sql)->execute($data);
                $id_staff = $pdo->lastInsertId(); // Necesario para retenciones
            } else {
                $sql = "UPDATE personal_staff SET id_usuario=:id_u, legajo=:legajo, dni=:dni, cuil=:cuil, apellido=:apellido, nombre=:nombre, nacionalidad=:nac, telefono=:tel, email_personal=:email_p, direccion=:dir, id_area=:id_area, sueldo_base=:sueldo, cbu=:cbu, banco=:banco, fecha_ingreso=:f_ing, cantidad_hijos=:hijos, hijos_verificados=:h_verif, pago_banco=:p_banco, tipo_contratacion=:t_contra, afiliado_sindicato=:sind, cobra_presentismo=:pres";
                if ($pdf_dni)    { $sql .= ", ruta_pdf_dni = :r_dni";    $data['r_dni'] = $pdf_dni; }
                if ($pdf_cv)     { $sql .= ", ruta_pdf_cv = :r_cv";      $data['r_cv']  = $pdf_cv; }
                if ($pdf_titulo) { $sql .= ", ruta_pdf_titulo = :r_tit"; $data['r_tit'] = $pdf_titulo; }
                if ($pdf_hijos)  { $sql .= ", ruta_pdf_hijos = :r_hijos"; $data['r_hijos'] = $pdf_hijos; }
                $sql .= " WHERE id_staff = :id_s";
                $data['id_s'] = $id_staff;
                $pdo->prepare($sql)->execute($data);
            }

            // --- NUEVO: GUARDAR RETENCIONES DINÁMICAS ---
            $pdo->prepare("DELETE FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff'")->execute([$id_staff]);
            if (!empty($_POST['ret_expediente'])) {
                $sql_ret = "INSERT INTO retenciones_judiciales (id_persona, tipo_persona, nro_expediente, beneficiario_nombre, tipo_calculo, valor, cbu_destino, activo) 
                            VALUES (?, 'Staff', ?, ?, ?, ?, ?, 1)";
                $stmt_ret = $pdo->prepare($sql_ret);
                foreach ($_POST['ret_expediente'] as $key => $expediente) {
                    if (!empty(trim($expediente))) {
                        $stmt_ret->execute([
                            $id_staff,
                            trim($expediente),
                            trim($_POST['ret_beneficiario'][$key] ?? ''),
                            $_POST['ret_tipo'][$key],
                            $_POST['ret_valor'][$key],
                            trim($_POST['ret_cbu'][$key] ?? '')
                        ]);
                    }
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Personal y Retenciones almacenados con éxito']);
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Staff | Sintek Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-access { border-left: 5px solid #0d6efd; background-color: #f8f9ff; }
        .bg-readonly { background-color: #e9ecef !important; font-weight: bold; }
        #seccion_usuario { display: none; }
    </style>
</head>
<body class="bg-light">
    <?php include 'vistas/nav.php'; ?>

    <div class="container py-4">
    <h2 class="mb-4">
        <i class="fas fa-users-cog text-primary"></i> 
        <?= $staff_editar ? 'Editar Legajo: ' . $staff_editar['apellido'] . ', ' . $staff_editar['nombre'] : 'Alta de Personal de Staff' ?>
    </h2>

    <form id="form_staff" method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
        <input type="hidden" name="id_staff" value="<?= $staff_editar['id_staff'] ?? 0 ?>">
        <input type="hidden" name="id_usuario_actual" value="<?= $staff_editar['id_usuario'] ?? '' ?>">

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">DNI (*)</label>
                    <input type="text" name="dni" id="dni_staff" class="form-control" value="<?= $staff_editar['dni'] ?? '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">CUIL (*)</label>
                    <input type="text" name="cuil" id="cuil_staff" class="form-control" value="<?= $staff_editar['cuil'] ?? '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">BANCO (*)</label>
                    <input type="text" name="banco" id="banco_staff" class="form-control" value="<?= $staff_editar['banco'] ?? '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Legajo</label>
                    <input type="text" name="legajo" class="form-control" value="<?= $staff_editar['legajo'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Apellido (*)</label>
                    <input type="text" name="apellido" class="form-control" value="<?= $staff_editar['apellido'] ?? '' ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Nombre (*)</label>
                    <input type="text" name="nombre" class="form-control" value="<?= $staff_editar['nombre'] ?? '' ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Nacionalidad</label>
                    <input type="text" name="nacionalidad" class="form-control" value="<?= $staff_editar['nacionalidad'] ?? 'Argentina' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= $staff_editar['telefono'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email Personal</label>
                    <input type="email" name="email_personal" class="form-control" value="<?= $staff_editar['email_personal'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha de Ingreso</label>
                    <input type="date" name="fecha_ingreso" class="form-control" value="<?= $staff_editar['fecha_ingreso'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Dirección Completa</label>
                    <input type="text" name="direccion" class="form-control" value="<?= $staff_editar['direccion'] ?? '' ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-primary">Área / Escalafón (*)</label>
                    <select name="id_area" id="id_area_select" class="form-select border-primary shadow-sm" required onchange="gestionarCambioArea()">
                        <option value="">Seleccione el Área...</option>
                        <?php foreach($areas as $a): ?>
                            <option value="<?= $a['id_area'] ?>" 
                                    data-sueldo="<?= $a['sueldo_base_area'] ?>"
                                    data-nombre="<?= $a['nombre_area'] ?>" 
                                    <?= (isset($staff_editar['id_area']) && $staff_editar['id_area'] == $a['id_area']) ? 'selected' : '' ?>>
                                <?= $a['nombre_area'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold">Tipo Contratación (*)</label>
                    <select name="tipo_contratacion" id="tipo_contratacion" class="form-select border-info shadow-sm" required>
                        <option value="Relacion_Dependencia" <?= (isset($staff_editar['tipo_contratacion']) && $staff_editar['tipo_contratacion'] == 'Relacion_Dependencia') ? 'selected' : '' ?>>Relación de Dependencia</option>
                        <option value="Monotributista" <?= (isset($staff_editar['tipo_contratacion']) && $staff_editar['tipo_contratacion'] == 'Monotributista') ? 'selected' : '' ?>>Monotributista (Factura)</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold text-muted">Sueldo Base (Automático)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">$</span>
                        <input type="text" id="sueldo_display" class="form-control bg-readonly" readonly value="<?= number_format($staff_editar['sueldo_base'] ?? 0, 2, ',', '.') ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="cbu" class="form-label small fw-bold text-muted">CBU (CLAVE BANCARIA UNIFORME)</label>
                    <div class="input-group has-validation">
                        <span class="input-group-text bg-light"><i class="fas fa-university text-primary"></i></span>
                        <input 
                            type="text" 
                            name="cbu" 
                            id="cbu_input"
                            class="form-control shadow-sm font-monospace" 
                            maxlength="22" 
                            placeholder="Ingrese los 22 dígitos del CBU"
                            value="<?= $staff_editar['cbu'] ?? '' ?>"
                            autocomplete="off"
                        >
                        <div id="cbu_feedback" class="invalid-feedback">
                            El CBU debe tener exactamente 22 números.
                        </div>
                    </div>
                    <!-- Ayuda visual debajo del campo -->
                    <div id="cbu_helper" class="form-text small mt-1">
                        <span id="cbu_counter">0</span> / 22 dígitos ingresados.
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mt-4 p-2 border rounded bg-light">
                        <input type="checkbox" name="pago_banco" class="form-check-input ms-0 me-2" id="pago_b" <?= ($staff_editar['pago_banco'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="pago_b">Incluir en pago masivo (Banco)</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Afiliaciones y Adicionales</label>
                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="afiliado_sindicato" class="form-check-input" id="sind_staff" <?= ($staff_editar['afiliado_sindicato'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-primary" for="sind_staff">Afiliado a Sindicato</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="checkbox" name="cobra_presentismo" class="form-check-input" id="pres_staff" <?= ($staff_editar['cobra_presentismo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-success" for="pres_staff">Cobra Presentismo</label>
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold text-primary">Hijos</label>
                    <input type="number" name="cantidad_hijos" id="cant_hijos" class="form-control" value="<?= $staff_editar['cantidad_hijos'] ?? 0 ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-bold">DDJJ Hijos (PDF)</label>
                    <input type="file" name="pdf_hijos" class="form-control" accept=".pdf">
                    <?php if(!empty($staff_editar['ruta_pdf_hijos'])): ?>
                        <div class="mt-1">
                             <a href="<?= $staff_editar['ruta_pdf_hijos'] ?>" target="_blank" class="badge bg-info text-decoration-none"><i class="fas fa-file-pdf"></i> Ver Archivo</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-5">
                    <div class="form-check form-switch mt-4 p-2 border rounded bg-light">
                        <input type="checkbox" name="hijos_verificados" class="form-check-input ms-0 me-2" id="hijos_v" <?= ($staff_editar['hijos_verificados'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="hijos_v">Documentación de Hijos Verificada</label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">DNI Escaneado (PDF)</label>
                    <input type="file" name="pdf_dni" class="form-control" accept=".pdf">
                    <?php if(!empty($staff_editar['ruta_pdf_dni'])): ?>
                        <a href="<?= $staff_editar['ruta_pdf_dni'] ?>" target="_blank" class="text-danger small"><i class="fas fa-file-pdf"></i> DNI Cargado</a>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Curriculum Vitae (PDF)</label>
                    <input type="file" name="pdf_cv" class="form-control" accept=".pdf">
                    <?php if(!empty($staff_editar['ruta_pdf_cv'])): ?>
                        <a href="<?= $staff_editar['ruta_pdf_cv'] ?>" target="_blank" class="text-danger small"><i class="fas fa-file-pdf"></i> CV Cargado</a>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Título (PDF)</label>
                    <input type="file" name="pdf_titulo" class="form-control" accept=".pdf">
                    <?php if(!empty($staff_editar['ruta_pdf_titulo'])): ?>
                        <a href="<?= $staff_editar['ruta_pdf_titulo'] ?>" target="_blank" class="text-danger small"><i class="fas fa-file-pdf"></i> Título Cargado</a>
                    <?php endif; ?>
                </div>

                <hr class="my-4">
                    <div class="row g-3 p-3 bg-white border rounded shadow-sm m-1">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="text-danger mb-0"><i class="fas fa-gavel"></i> Retenciones Judiciales / Embargos (Staff)</h5>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="agregarFilaRetencion()">
                                <i class="fas fa-plus"></i> Agregar Retención
                            </button>
                        </div>

                        <div id="contenedor-retenciones">
                            <?php if (empty($retenciones_existentes)): ?>
                                <p class="text-muted text-center py-2" id="msg-sin-retenciones">Sin retenciones judiciales activas.</p>
                            <?php else: ?>
                                <?php foreach ($retenciones_existentes as $r): ?>
                                    <div class="row g-2 border-bottom pb-3 mb-3 fila-retencion bg-light p-2 rounded">
                                        <div class="col-md-3">
                                            <label class="small fw-bold">Expediente</label>
                                            <input type="text" name="ret_expediente[]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['nro_expediente']) ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold">Cálculo</label>
                                            <select name="ret_tipo[]" class="form-select form-select-sm">
                                                <option value="Porcentaje" <?= $r['tipo_calculo'] == 'Porcentaje' ? 'selected' : '' ?>>Porcentaje %</option>
                                                <option value="Monto Fijo" <?= $r['tipo_calculo'] == 'Monto Fijo' ? 'selected' : '' ?>>Monto Fijo $</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold">Valor</label>
                                            <input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" value="<?= $r['valor'] ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold text-primary">Beneficiario / CBU Destino</label>
                                            <input type="text" name="ret_beneficiario[]" class="form-control form-control-sm mb-1" value="<?= htmlspecialchars($r['beneficiario_nombre']) ?>" placeholder="Nombre">
                                            <input type="text" name="ret_cbu[]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['cbu_destino']) ?>" placeholder="CBU" maxlength="22">
                                        </div>
                                        <div class="col-md-1 d-flex align-items-center">
                                            <button type="button" class="btn btn-sm btn-danger w-100" onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <hr class="my-4">

                <div class="col-12">
                    <div class="form-check form-switch card-access p-3 rounded shadow-sm border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="crear_usuario" id="checkAcceso" 
                               onchange="toggleUsuario()" <?= !empty($staff_editar['id_usuario']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="checkAcceso">
                            Habilitar acceso al sistema para este empleado
                        </label>
                    </div>
                </div>

                <div id="seccion_usuario" class="row g-3 mt-1 ms-1 p-3 border rounded bg-white shadow-sm">
                    <div class="col-md-4">
                        <label class="form-label">Nombre de Usuario</label>
                        <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" value="<?= $staff_editar['nombre_usuario'] ?? '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contraseña <?= $staff_editar ? '<span class="text-muted small">(Vacío para mantener)</span>' : '(*)' ?></label>
                       <input type="password" name="password" id="pass_usuario" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Asignar Rol</label>
                        <select name="id_rol" class="form-select">
                            <?php foreach($roles as $r): ?>
                                <option value="<?= $r['id_rol'] ?>" <?= (isset($staff_editar['rol_sistema']) && $staff_editar['rol_sistema'] == $r['id_rol']) ? 'selected' : '' ?>>
                                    <?= $r['nombre_rol'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Email de sistema</label>
                        <input type="email" name="email_usuario" class="form-control" value="<?= $staff_editar['email_usuario'] ?? '' ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-white border-top-0 py-3 text-end">
            <a href="staff_lista.php" class="btn btn-outline-secondary px-4 me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary px-5 shadow-sm">
                <i class="fas fa-save me-2"></i> <?= $staff_editar ? 'Actualizar Legajo' : 'Guardar Nuevo Personal' ?>
            </button>
        </div>
    </form>
</div>

<script src="js/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // --- FUNCIÓN PARA ACTUALIZAR SUELDO VISUALMENTE ---
        function actualizarSueldoArea() {
            const select = document.getElementById('id_area_select');
            const display = document.getElementById('sueldo_display');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value !== "") {
                const sueldo = parseFloat(selectedOption.getAttribute('data-sueldo'));
                display.value = sueldo.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                display.value = "0,00";
            }
        }

        // --- PROCESAMIENTO AJAX CON VALIDACIÓN DE TAMAÑO ---
        document.getElementById('form_staff').addEventListener('submit', function(e) {
            e.preventDefault();

            // VALIDACIÓN DE TAMAÑO DE ARCHIVOS (Máximo 2MB)
            const inputsArchivos = this.querySelectorAll('input[type="file"]');
            const MAX_SIZE = 2 * 1024 * 1024; // 2 MegaBytes
            let errorArchivo = "";

            inputsArchivos.forEach(input => {
                if (input.files.length > 0) {
                    if (input.files[0].size > MAX_SIZE) {
                        // Obtenemos el nombre del campo para el mensaje de error
                        const label = input.closest('.col-md-4') || input.closest('.col-md-5');
                        const nombreCampo = label ? label.querySelector('label').innerText : "Archivo";
                        errorArchivo = `El archivo en "${nombreCampo}" supera el límite de 2MB.`;
                    }
                }
            });

            if (errorArchivo !== "") {
                Swal.fire({
                    title: 'Archivo demasiado grande',
                    text: errorArchivo,
                    icon: 'warning',
                    confirmButtonColor: '#3085d6'
                });
                return; // Detiene el envío y mantiene los datos del formulario
            }

            // Si los archivos están bien, procedemos con el envío
            Swal.fire({
                title: '¿Desea almacenar al personal?',
                text: "Se guardarán todos los datos y el legajo digital",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(this);
                    
                    // Mostramos un loading para que el usuario sepa que se está subiendo
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Subiendo archivos y guardando datos',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    fetch('staff_abm.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Manejo de errores de red o respuestas no JSON (como el error 413)
                        if (!response.ok) throw new Error('Error en la respuesta del servidor');
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: '¡Almacenado!',
                                text: data.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'staff_lista.php';
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error Crítico', 'El servidor rechazó la solicitud. Es posible que el archivo sea demasiado grande para la configuración de PHP.', 'error');
                    });
                }
            });
        });

        // --- MANEJO DE SECCIÓN USUARIO ---
        function toggleUsuario() {
            const seccion = document.getElementById('seccion_usuario');
            const check = document.getElementById('checkAcceso');
            const passInput = document.getElementById('pass_usuario');
            const esEdicion = <?= $staff_editar ? 'true' : 'false' ?>;

            if (check && check.checked) {
                seccion.style.display = 'flex';
                if (!esEdicion && passInput) passInput.setAttribute('required', 'required');
            } else if (seccion) {
                seccion.style.display = 'none';
                if (passInput) passInput.removeAttribute('required');
            }
        }

        function gestionarCambioArea() {
            const areaSelect = document.getElementById('id_area_select');
            const contratoSelect = document.getElementById('tipo_contratacion');
            const sueldoDisplay = document.getElementById('sueldo_display');
            
            const selectedOption = areaSelect.options[areaSelect.selectedIndex];

            if (selectedOption.value !== "") {
                // 1. Actualizar Sueldo (Tu lógica que ya funcionaba)
                const sueldo = parseFloat(selectedOption.getAttribute('data-sueldo'));
                sueldoDisplay.value = sueldo.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                // 2. Seteo automático de Tipo de Contratación
                const nombreArea = selectedOption.getAttribute('data-nombre');
                
                if (nombreArea === "Monotributistas") {
                    contratoSelect.value = "Monotributista";
                } else {
                    contratoSelect.value = "Relacion_Dependencia";
                }
            } else {
                sueldoDisplay.value = "0,00";
            }
        }

        window.onload = function() {
            toggleUsuario();
            const areaSelect = document.getElementById('id_area_select');
            if(areaSelect && areaSelect.value !== "") {
                actualizarSueldoArea();
            }
        };

        function agregarFilaRetencion() {
            const contenedor = document.getElementById('contenedor-retenciones');
            const msg = document.getElementById('msg-sin-retenciones');
            if (msg) msg.remove();

            const div = document.createElement('div');
            div.className = 'row g-2 border-bottom pb-3 mb-3 fila-retencion bg-light p-2 rounded';
            div.innerHTML = `
                <div class="col-md-3"><label class="small fw-bold">Expediente</label><input type="text" name="ret_expediente[]" class="form-control form-control-sm" required></div>
                <div class="col-md-2"><label class="small fw-bold">Cálculo</label><select name="ret_tipo[]" class="form-select form-select-sm"><option value="Porcentaje">Porcentaje %</option><option value="Monto Fijo">Monto Fijo $</option></select></div>
                <div class="col-md-2"><label class="small fw-bold">Valor</label><input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" required></div>
                <div class="col-md-4">
                    <label class="small fw-bold text-primary">Beneficiario / CBU Destino</label>
                    <input type="text" name="ret_beneficiario[]" class="form-control form-control-sm mb-1" placeholder="Nombre">
                    <input type="text" name="ret_cbu[]" class="form-control form-control-sm" placeholder="CBU" maxlength="22">
                </div>
                <div class="col-md-1 d-flex align-items-center"><button type="button" class="btn btn-sm btn-danger w-100" onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button></div>
            `;
            contenedor.appendChild(div);
        }

        function eliminarFila(btn) {
            btn.closest('.fila-retencion').remove();
            const contenedor = document.getElementById('contenedor-retenciones');
            if (contenedor.children.length === 0) {
                contenedor.innerHTML = '<p class="text-muted text-center py-2" id="msg-sin-retenciones">Sin retenciones judiciales activas.</p>';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const cbuInput = document.getElementById('cbu_input');
            const cbuCounter = document.getElementById('cbu_counter');
            const cbuHelper = document.getElementById('cbu_helper');

            function validarCBU() {
                // 1. Limpiar: Solo permitir números
                let valor = cbuInput.value.replace(/\D/g, '');
                cbuInput.value = valor;

                const largo = valor.length;
                cbuCounter.textContent = largo;

                // 2. Aplicar clases de Bootstrap según la longitud
                if (largo === 0) {
                    cbuInput.classList.remove('is-valid', 'is-invalid');
                    cbuHelper.className = "form-text small mt-1 text-muted";
                    cbuHelper.innerHTML = `<i class="fas fa-info-circle"></i> Ingrese los 22 dígitos requeridos.`;
                } else if (largo === 22) {
                    cbuInput.classList.remove('is-invalid');
                    cbuInput.classList.add('is-valid');
                    cbuHelper.className = "form-text small mt-1 text-success fw-bold";
                    cbuHelper.innerHTML = `<i class="fas fa-check-circle"></i> CBU Completo (22 dígitos).`;
                } else {
                    cbuInput.classList.remove('is-valid');
                    cbuInput.classList.add('is-invalid');
                    cbuHelper.className = "form-text small mt-1 text-danger";
                    cbuHelper.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Faltan ${22 - largo} dígitos.`;
                }
            }

            // Escuchar el evento de entrada y pegado
            cbuInput.addEventListener('input', validarCBU);
            
            // Validar al cargar (por si viene un valor de la DB)
            validarCBU();
        });
</script>
</body>
</html>