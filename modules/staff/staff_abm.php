<?php
/**
 * GESTIÓN DE STAFF: ALTA Y EDICIÓN (VERSIÓN COMPLETA BLINDADA CON SINDICATO DINÁMICO)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$rol = $_SESSION['rol'];
$staff_editar = null;
$retenciones_existentes = [];

// 1. CAPTURA DEL UUID (Desde .htaccess)
$uuid_param = $_GET['uuid'] ?? ''; 
$uuid_get = '';
if (!empty($uuid_param)) {
    $uuid_get = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid_param));
}

// CARGA DE SELECTS (Áreas, Roles y Sindicatos)
$areas = $pdo->query("SELECT id_area, nombre_area, sueldo_base_area FROM areas ORDER BY nombre_area")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT id_rol, nombre_rol FROM roles WHERE id_rol != 3 ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);

// NUEVO: Carga de Sindicatos configurados para Staff
$sindicatos = $pdo->query("SELECT id_entidad, nombre_entidad, porcentaje_retencion 
                           FROM entidades_pagos_terceros 
                           WHERE estado = 1 
                           AND tipo_entidad = 'Sindicato'
                           AND categoria_afectada IN ('Staff', 'Todos') 
                           ORDER BY nombre_entidad ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. RECUPERAR DATOS PARA EDICIÓN
if (!empty($uuid_get) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT s.*, u.nombre_usuario, u.email as email_usuario, u.id_rol as rol_sistema 
                            FROM personal_staff s 
                            LEFT JOIN usuarios u ON s.id_usuario = u.id_usuario 
                            WHERE s.uuid_staff = ? LIMIT 1");
    $stmt->execute([$uuid_get]);
    $staff_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($staff_editar) {
        if ($staff_editar['activo'] == 0) {
            header("Location: " . BASE_URL . "staff/lista?error=bloqueado");
            exit;
        }
        $stmt_ret = $pdo->prepare("SELECT * FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff' AND activo = 1");
        $stmt_ret->execute([$staff_editar['id_staff']]);
        $retenciones_existentes = $stmt_ret->fetchAll(PDO::FETCH_ASSOC);
    }
}

// FUNCIÓN PHP PARA SUBIR ARCHIVOS (Sin cambios)
function subirArchivoPersonal($file, $dni, $tipo) {
    $folder_fisica = "uploads/"; 
    $folder_bd = "modules/staff/uploads/";
    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        if (!file_exists($folder_fisica)) { mkdir($folder_fisica, 0777, true); }
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $nombre_archivo = $tipo . "_" . $dni . "_" . time() . "." . $extension;
        if (move_uploaded_file($file['tmp_name'], $folder_fisica . $nombre_archivo)) { 
            return $folder_bd . $nombre_archivo; 
        }
    }
    return null;
}

// 3. PROCESAMIENTO POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $id_staff_interno = isset($_POST['id_staff_interno']) ? (int)$_POST['id_staff_interno'] : 0;
    $dni = trim($_POST['dni'] ?? '');
    $id_area_post = (int)($_POST['id_area'] ?? 0);

    try {
        if (empty($dni)) throw new Exception("El DNI es obligatorio.");
        $pdo->beginTransaction();

        $stmt_s = $pdo->prepare("SELECT sueldo_base_area FROM areas WHERE id_area = ?");
        $stmt_s->execute([$id_area_post]);
        $sueldo_oficial = $stmt_s->fetchColumn() ?: 0;

        $id_usuario_vinculado = !empty($_POST['id_usuario_actual']) ? (int)$_POST['id_usuario_actual'] : null;
        
        // GESTIÓN DE USUARIO (Mantenida intacta)
        if (isset($_POST['crear_usuario'])) {
            $user_name = trim($_POST['nombre_usuario'] ?? '');
            $email_u = trim($_POST['email_usuario'] ?? '');
            $rol_sis = (int)($_POST['id_rol'] ?? 0);
            $pass = $_POST['password'] ?? '';
            $apellido_u = trim($_POST['apellido'] ?? '');

            if (empty($id_usuario_vinculado)) {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt_u = $pdo->prepare("INSERT INTO usuarios (nombre_usuario, password, apellido, email, id_rol) VALUES (?, ?, ?, ?, ?)");
                $stmt_u->execute([$user_name, $hash, $apellido_u, $email_u, $rol_sis]);
                $id_usuario_vinculado = $pdo->lastInsertId();
            } else {
                if (!empty($pass)) {
                    $pdo->prepare("UPDATE usuarios SET nombre_usuario=?, email=?, id_rol=?, password=? WHERE id_usuario=?")
                        ->execute([$user_name, $email_u, $rol_sis, password_hash($pass, PASSWORD_DEFAULT), $id_usuario_vinculado]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nombre_usuario=?, email=?, id_rol=? WHERE id_usuario=?")
                        ->execute([$user_name, $email_u, $rol_sis, $id_usuario_vinculado]);
                }
            }
        }

        $pdf_dni = subirArchivoPersonal($_FILES['pdf_dni'] ?? null, $dni, "DNI");
        $pdf_cv = subirArchivoPersonal($_FILES['pdf_cv'] ?? null, $dni, "CV");
        $pdf_titulo = subirArchivoPersonal($_FILES['pdf_titulo'] ?? null, $dni, "TITULO");
        $pdf_hijos = subirArchivoPersonal($_FILES['pdf_hijos'] ?? null, $dni, "HIJOS");

        // MAPEO DE DATOS (Incluyendo el nuevo ID de Sindicato)
        $data = [
            'id_u' => $id_usuario_vinculado,
            'legajo' => trim($_POST['legajo'] ?? ''),
            'dni' => $dni,
            'cuil' => trim($_POST['cuil'] ?? ''),
            'apellido' => trim($_POST['apellido'] ?? ''),
            'nombre' => trim($_POST['nombre'] ?? ''),
            'nac' => trim($_POST['nacionalidad'] ?? 'Argentina'),
            'tel' => trim($_POST['telefono'] ?? ''),
            'email_p' => trim($_POST['email_personal'] ?? ''),
            'dir' => trim($_POST['direccion'] ?? ''),
            'id_area' => $id_area_post,
            'sueldo' => (float)$sueldo_oficial,
            'cbu' => trim($_POST['cbu'] ?? ''),
            'banco' => trim($_POST['banco'] ?? ''),
            'f_ing' => !empty($_POST['fecha_ingreso']) ? $_POST['fecha_ingreso'] : date('Y-m-d'),
            'hijos' => (int)($_POST['cantidad_hijos'] ?? 0),
            'h_verif' => isset($_POST['hijos_verificados']) ? 1 : 0,
            'p_banco' => isset($_POST['pago_banco']) ? 1 : 0,
            't_contra' => $_POST['tipo_contratacion'] ?? 'Relacion_Dependencia',
            'id_sind' => !empty($_POST['id_entidad_sindicato']) ? (int)$_POST['id_entidad_sindicato'] : null, 
            'pres' => isset($_POST['cobra_presentismo']) ? 1 : 0
        ];

        if ($id_staff_interno == 0) {
            $sql = "INSERT INTO personal_staff (uuid_staff, id_usuario, legajo, dni, cuil, apellido, nombre, nacionalidad, telefono, email_personal, direccion, id_area, sueldo_base, cbu, banco, fecha_ingreso, cantidad_hijos, hijos_verificados, pago_banco, tipo_contratacion, id_entidad_sindicato, cobra_presentismo, ruta_pdf_dni, ruta_pdf_cv, ruta_pdf_titulo, ruta_pdf_hijos) 
                    VALUES (:uuid, :id_u, :legajo, :dni, :cuil, :apellido, :nombre, :nac, :tel, :email_p, :dir, :id_area, :sueldo, :cbu, :banco, :f_ing, :hijos, :h_verif, :p_banco, :t_contra, :id_sind, :pres, :r_dni, :r_cv, :r_tit, :r_hijos)";
            $data['uuid'] = bin2hex(random_bytes(16));
            $data['r_dni'] = $pdf_dni; $data['r_cv'] = $pdf_cv; $data['r_tit'] = $pdf_titulo; $data['r_hijos'] = $pdf_hijos;
            $pdo->prepare($sql)->execute($data);
            $id_staff_final = $pdo->lastInsertId();
        } else {
            $sql = "UPDATE personal_staff SET id_usuario=:id_u, legajo=:legajo, dni=:dni, cuil=:cuil, apellido=:apellido, nombre=:nombre, nacionalidad=:nac, telefono=:tel, email_personal=:email_p, direccion=:dir, id_area=:id_area, sueldo_base=:sueldo, cbu=:cbu, banco=:banco, fecha_ingreso=:f_ing, cantidad_hijos=:hijos, hijos_verificados=:h_verif, pago_banco=:p_banco, tipo_contratacion=:t_contra, id_entidad_sindicato=:id_sind, cobra_presentismo=:pres";
            if ($pdf_dni) { $sql .= ", ruta_pdf_dni = :r_dni"; $data['r_dni'] = $pdf_dni; }
            if ($pdf_cv) { $sql .= ", ruta_pdf_cv = :r_cv"; $data['r_cv'] = $pdf_cv; }
            if ($pdf_titulo) { $sql .= ", ruta_pdf_titulo = :r_tit"; $data['r_tit'] = $pdf_titulo; }
            if ($pdf_hijos) { $sql .= ", ruta_pdf_hijos = :r_hijos"; $data['r_hijos'] = $pdf_hijos; }
            $sql .= " WHERE id_staff = :id_s";
            $data['id_s'] = $id_staff_interno;
            $pdo->prepare($sql)->execute($data);
            $id_staff_final = $id_staff_interno;
        }

        // RETENCIONES JUDICIALES (Mantenidas intactas)
        $pdo->prepare("DELETE FROM retenciones_judiciales WHERE id_persona = ? AND tipo_persona = 'Staff'")->execute([$id_staff_final]);
        // 2. Procesamos las nuevas filas si existen
        if (!empty($_POST['ret_expediente'])) {
            // Preparamos la consulta con la columna cuit_beneficiario
            $sql_ins_ret = "INSERT INTO retenciones_judiciales 
                            (id_persona, tipo_persona, nro_expediente, beneficiario_nombre, cuit_beneficiario, tipo_calculo, valor, cbu_destino, activo) 
                            VALUES (?, 'Staff', ?, ?, ?, ?, ?, ?, 1)";
            
            $stmt_ret = $pdo->prepare($sql_ins_ret);

            foreach ($_POST['ret_expediente'] as $key => $exp) {
                if (!empty(trim($exp))) {
                    // Limpieza de CUIT y CBU (solo números para el banco)
                    $cuit_limpio = preg_replace('/\D/', '', $_POST['ret_cuit'][$key] ?? '');
                    $cbu_limpio  = preg_replace('/\D/', '', $_POST['ret_cbu'][$key] ?? '');

                    $stmt_ret->execute([
                        $id_staff_final, 
                        trim($exp), 
                        trim($_POST['ret_beneficiario'][$key] ?? ''), 
                        $cuit_limpio, // Guardamos el nuevo campo
                        $_POST['ret_tipo'][$key], 
                        $_POST['ret_valor'][$key], 
                        $cbu_limpio
                    ]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Legajo procesado correctamente']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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
    <?php include '../../vistas/nav.php'; ?>

    <div class="container py-4">
    <h2 class="mb-4">
        <i class="fas fa-users-cog text-primary"></i> 
        <?= $staff_editar ? 'Editar Legajo: ' . $staff_editar['apellido'] . ', ' . $staff_editar['nombre'] : 'Alta de Personal de Staff' ?>
    </h2>

    <form id="form_staff" method="POST" enctype="multipart/form-data" class="card shadow-sm border-0">
        <input type="hidden" name="id_staff_interno" value="<?= $staff_editar['id_staff'] ?? 0 ?>">
        <input type="hidden" name="uuid_staff" value="<?= $staff_editar['uuid_staff'] ?? '' ?>">
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
                <div class="col-md-6">
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
                    <label class="form-label fw-bold text-success"><i class="fas fa-handshake"></i> Sindicato / Afiliación</label>
                    <select name="id_entidad_sindicato" class="form-select border-success shadow-sm">
                        <option value="">-- No Afiliado / Ninguno --</option>
                        <?php foreach($sindicatos as $s): ?>
                            <option value="<?= $s['id_entidad'] ?>" <?= (isset($staff_editar['id_entidad_sindicato']) && $staff_editar['id_entidad_sindicato'] == $s['id_entidad']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['nombre_entidad']) ?> (<?= $s['porcentaje_retencion'] ?>%)
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
                    <label for="cbu" class="form-label small fw-bold text-muted">CBU (22 DÍGITOS)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-university text-primary"></i></span>
                        <input type="text" name="cbu" id="cbu_input" class="form-control shadow-sm font-monospace" maxlength="22" value="<?= $staff_editar['cbu'] ?? '' ?>">
                    </div>
                    <div id="cbu_helper" class="form-text small mt-1"><span id="cbu_counter">0</span> / 22 dígitos.</div>
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4 p-2 border rounded bg-light">
                        <input type="checkbox" name="pago_banco" class="form-check-input ms-0 me-2" id="pago_b" <?= ($staff_editar['pago_banco'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="pago_b">Incluir en pago masivo (Banco)</label>
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
                         <a href="<?= BASE_URL . $staff_editar['ruta_pdf_hijos'] ?>" target="_blank" class="badge bg-info mt-1"><i class="fas fa-file-pdf"></i> Ver</a>
                    <?php endif; ?>
                </div>
                <div class="col-md-5">
                    <div class="form-check form-switch mt-4 p-2 border rounded bg-light">
                        <input type="checkbox" name="hijos_verificados" class="form-check-input" id="hijos_v" <?= ($staff_editar['hijos_verificados'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold" for="hijos_v">Doc. Verificada</label>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">DNI Escaneado (PDF)</label>
                        <input type="file" name="pdf_dni" class="form-control" accept=".pdf">
                        <?php if(!empty($staff_editar['ruta_pdf_dni'])): ?>
                            <div class="mt-1">
                                <a href="<?= BASE_URL . $staff_editar['ruta_pdf_dni'] ?>" target="_blank" class="text-danger small fw-bold text-decoration-none">
                                    <i class="fas fa-file-pdf me-1"></i> Ver DNI Cargado
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">CV (PDF)</label>
                        <input type="file" name="pdf_cv" class="form-control" accept=".pdf">
                        <?php if(!empty($staff_editar['ruta_pdf_cv'])): ?>
                            <div class="mt-1">
                                <a href="<?= BASE_URL . $staff_editar['ruta_pdf_cv'] ?>" target="_blank" class="text-danger small fw-bold text-decoration-none">
                                    <i class="fas fa-file-pdf me-1"></i> Ver CV Cargado
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Título (PDF)</label>
                        <input type="file" name="pdf_titulo" class="form-control" accept=".pdf">
                        <?php if(!empty($staff_editar['ruta_pdf_titulo'])): ?>
                            <div class="mt-1">
                                <a href="<?= BASE_URL . $staff_editar['ruta_pdf_titulo'] ?>" target="_blank" class="text-danger small fw-bold text-decoration-none">
                                    <i class="fas fa-file-pdf me-1"></i> Ver Título Cargado
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <div class="row g-3 p-3 bg-white border rounded shadow-sm">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
                            <h5 class="text-danger mb-0 small uppercase fw-bold"><i class="fas fa-gavel"></i> Retenciones / Embargos</h5>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="agregarFilaRetencion()">+ Agregar</button>
                        </div>
                        <div id="contenedor-retenciones">
                            <?php if (empty($retenciones_existentes)): ?>
                                <p class="text-muted text-center py-2" id="msg-sin-retenciones">Sin retenciones activas.</p>
                            <?php else: ?>
                                <?php foreach ($retenciones_existentes as $r): ?>
                                    <div class="row g-2 border-bottom pb-3 mb-3 fila-retencion bg-light p-2 rounded align-items-center shadow-sm">
                                        <div class="col-md-3">
                                            <label class="small fw-bold">Nro. Expediente</label>
                                            <input type="text" name="ret_expediente[]" class="form-control form-control-sm" value="<?= htmlspecialchars($r['nro_expediente']) ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold">Cálculo</label>
                                            <select name="ret_tipo[]" class="form-select form-select-sm">
                                                <option value="Porcentaje" <?= $r['tipo_calculo'] == 'Porcentaje' ? 'selected' : '' ?>>%</option>
                                                <option value="Monto Fijo" <?= $r['tipo_calculo'] == 'Monto Fijo' ? 'selected' : '' ?>>$ Fijo</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="small fw-bold">Valor</label>
                                            <input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" value="<?= $r['valor'] ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="small fw-bold">CUIT Beneficiario</label>
                                            <input type="text" name="ret_cuit[]" class="form-control form-control-sm contador-input" 
                                                data-length="11" maxlength="11" 
                                                value="<?= htmlspecialchars($r['cuit_beneficiario'] ?? '') ?>" 
                                                placeholder="11 dígitos" required>
                                            <div class="text-xs text-muted ms-1">
                                                <span class="count"><?= strlen($r['cuit_beneficiario'] ?? '') ?></span>/11
                                                <span class="status-icon ms-1"></span>
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
                                            <input type="text" name="ret_cbu[]" class="form-control form-control-sm contador-input" 
                                                data-length="22" maxlength="22" 
                                                value="<?= htmlspecialchars($r['cbu_destino'] ?? '') ?>" 
                                                placeholder="CBU Destino (22 dígitos)">
                                            <div class="text-xs text-muted ms-1">
                                                <span class="count"><?= strlen($r['cbu_destino'] ?? '') ?></span>/22
                                                <span class="status-icon ms-1"></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <div class="form-check form-switch card-access p-3 rounded shadow-sm border">
                        <input class="form-check-input ms-0 me-2" type="checkbox" name="crear_usuario" id="checkAcceso" onchange="toggleUsuario()" <?= !empty($staff_editar['id_usuario']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="checkAcceso">Habilitar acceso al sistema</label>
                    </div>
                </div>

                <div id="seccion_usuario" class="row g-3 mt-1 ms-1 p-3 border rounded bg-white shadow-sm">
                    <div class="col-md-4"><label class="form-label">Usuario</label><input type="text" name="nombre_usuario" class="form-control" value="<?= $staff_editar['nombre_usuario'] ?? '' ?>"></div>
                    <div class="col-md-4"><label class="form-label">Contraseña</label><input type="password" name="password" id="pass_usuario" class="form-control"></div>
                    <div class="col-md-4">
                        <label class="form-label">Rol</label>
                        <select name="id_rol" class="form-select">
                            <?php foreach($roles as $r): ?><option value="<?= $r['id_rol'] ?>" <?= (isset($staff_editar['rol_sistema']) && $staff_editar['rol_sistema'] == $r['id_rol']) ? 'selected' : '' ?>><?= $r['nombre_rol'] ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-white border-top-0 py-3 text-end">
            <a href="<?= BASE_URL ?>staff/lista" class="btn btn-outline-secondary px-4 me-2">Cancelar</a>
            <button type="submit" class="btn btn-primary px-5 shadow-sm">Guardar Cambios</button>
        </div>
    </form>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>

    // Script de validación dinámica para CUIT (11) y CBU (22)
    $(document).on('input', '.contador-input', function() {
        const input = $(this);
        // 1. Limpieza: dejamos solo números
        let valorLimpio = input.val().replace(/\D/g, ''); 
        input.val(valorLimpio); 
        
        const requerido = input.data('length');
        const contenedorRelativo = input.next('.text-xs');
        const spanCount = contenedorRelativo.find('.count');
        
        // Actualizamos el número del contador
        spanCount.text(valorLimpio.length);

        // 2. Lógica de validación visual
        if (valorLimpio.length === requerido) {
            // ESTADO: CORRECTO (Verde + Tilde)
            contenedorRelativo.removeClass('text-muted text-danger').addClass('text-success fw-bold');
            contenedorRelativo.find('.status-icon').html('<i class="fas fa-check-circle"></i> Listo');
            input.addClass('is-valid').removeClass('is-invalid border-danger');
        } else if (valorLimpio.length > 0) {
            // ESTADO: INCOMPLETO (Rojo + mensaje de falta)
            const faltan = requerido - valorLimpio.length;
            contenedorRelativo.removeClass('text-muted text-success fw-bold').addClass('text-danger');
            contenedorRelativo.find('.status-icon').html(`<i class="fas fa-info-circle"></i> Faltan ${faltan}`);
            input.addClass('border-danger').removeClass('is-valid');
        } else {
            // ESTADO: VACÍO (Gris inicial)
            contenedorRelativo.removeClass('text-success text-danger fw-bold').addClass('text-muted');
            contenedorRelativo.find('.status-icon').html('');
            input.removeClass('is-valid is-invalid border-danger');
        }
    });

    // Al cargar la página, disparamos el evento para validar lo que ya viene de la DB
    $('.contador-input').trigger('input');

    function toggleUsuario() {
        const seccion = document.getElementById('seccion_usuario');
        const check = document.getElementById('checkAcceso');
        if (check && check.checked) seccion.style.display = 'flex';
        else if (seccion) seccion.style.display = 'none';
    }

    function gestionarCambioArea() {
        const areaSelect = document.getElementById('id_area_select');
        const contratoSelect = document.getElementById('tipo_contratacion');
        const sueldoDisplay = document.getElementById('sueldo_display');
        const selectedOption = areaSelect.options[areaSelect.selectedIndex];

        if (selectedOption.value !== "") {
            const sueldo = parseFloat(selectedOption.getAttribute('data-sueldo'));
            sueldoDisplay.value = sueldo.toLocaleString('es-AR', {minimumFractionDigits: 2});
            contratoSelect.value = (selectedOption.getAttribute('data-nombre') === "Monotributistas") ? "Monotributista" : "Relacion_Dependencia";
        }
    }

    // MANEJO DEL FORMULARIO CON SWEETALERT
    document.getElementById('form_staff').addEventListener('submit', function(e) {
        e.preventDefault(); // Detiene el envío normal
        
        Swal.fire({
            title: '¿Confirmar cambios?',
            text: "Se actualizará el legajo del personal",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Si confirma, ejecutamos el fetch
                const formData = new FormData(this);
                
                Swal.fire({
                    title: 'Procesando...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch('<?= BASE_URL; ?>staff/gestion', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            title: '¡Éxito!',
                            text: data.message,
                            icon: 'success'
                        }).then(() => {
                            window.location.href = '<?= BASE_URL; ?>staff/lista';
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                });
            }
        });
    });

    function agregarFilaRetencion() {
        const contenedor = document.getElementById('contenedor-retenciones');
        const msg = document.getElementById('msg-sin-retenciones');
        if (msg) msg.remove();

        const div = document.createElement('div');
        // Mantenemos las clases para el diseño en dos niveles y sombras
        div.className = 'row g-2 border-bottom pb-3 mb-3 fila-retencion bg-light p-2 rounded align-items-center shadow-sm';
        div.innerHTML = `
            <div class="col-md-3">
                <label class="small fw-bold">Nro. Expediente</label>
                <input type="text" name="ret_expediente[]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Cálculo</label>
                <select name="ret_tipo[]" class="form-select form-select-sm">
                    <option value="Porcentaje">Porcentaje %</option>
                    <option value="Monto Fijo">$ Fijo</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Valor</label>
                <input type="number" step="0.01" name="ret_valor[]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
                <label class="small fw-bold">CUIT Beneficiario</label>
                <input type="text" name="ret_cuit[]" class="form-control form-control-sm contador-input" 
                    data-length="11" maxlength="11" placeholder="11 dígitos" required>
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
                    data-length="22" maxlength="22" placeholder="CBU Destino (22 dígitos)">
                <div class="text-xs text-muted ms-1"><span class="count">0</span>/22</div>
            </div>
        `;
        contenedor.appendChild(div);
    }

    function eliminarFila(btn) { btn.closest('.fila-retencion').remove(); }

    document.getElementById('cbu_input').addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
        document.getElementById('cbu_counter').textContent = this.value.length;
    });

    window.onload = toggleUsuario;
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>