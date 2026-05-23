<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesoreria']);
$rol = $_SESSION['rol'];

// 1. LÓGICA PARA GUARDAR / EDITAR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['btn_guardar'])) {
    try {
        $nombre = trim($_POST['nombre_concepto']);
        $monto = floatval($_POST['monto_sugerido']);
        $categoria = $_POST['categoria']; 
        $id_carrera = !empty($_POST['id_carrera']) ? (int)$_POST['id_carrera'] : null;
        $uuid_concepto = $_POST['uuid_concepto'] ?? '';

        if ($monto < 0) throw new Exception("El monto no puede ser negativo.");

        if (!empty($uuid_concepto)) {
            // MODIFICACIÓN
            $sql = "UPDATE conceptos_pago SET nombre_concepto = ?, monto_sugerido = ?, categoria = ?, id_carrera = ? 
                    WHERE uuid_concepto = ?";
            $params = [$nombre, $monto, $categoria, $id_carrera, $uuid_concepto];
            $status = "updated";
        } else {
            // ALTA CON UUID
            $new_uuid = bin2hex(random_bytes(16));
            $new_uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($new_uuid, 4));

            $sql = "INSERT INTO conceptos_pago (uuid_concepto, nombre_concepto, monto_sugerido, categoria, id_carrera, activo) 
                    VALUES (?, ?, ?, ?, ?, 1)";
            $params = [$new_uuid, $nombre, $monto, $categoria, $id_carrera];
            $status = "created";
        }
        
        $pdo->prepare($sql)->execute($params);
        header("Location: " . BASE_URL . "tesoreria/conceptos?status=" . $status);
        exit;
    } catch (Exception $e) {
        header("Location: " . BASE_URL . "tesoreria/conceptos?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// 2. LÓGICA PARA ELIMINACIÓN (Usando UUID)
if (isset($_GET['delete_uuid'])) {
    $uuid = $_GET['delete_uuid'];
    $stmt = $pdo->prepare("UPDATE conceptos_pago SET activo = 0 WHERE uuid_concepto = ?");
    $stmt->execute([$uuid]);
    header("Location: " . BASE_URL . "tesoreria/conceptos?status=deleted");
    exit;
}

// 3. CONSULTA DE DATOS
$carreras = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera ASC")->fetchAll(PDO::FETCH_ASSOC);
$conceptos = $pdo->query("SELECT cp.*, c.nombre_carrera 
                          FROM conceptos_pago cp 
                          LEFT JOIN carreras c ON cp.id_carrera = c.id_carrera 
                          WHERE cp.activo = 1 
                          ORDER BY cp.categoria ASC, c.nombre_carrera ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Conceptos de Cobro | Sintek</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .form-label { font-size: 0.75rem; letter-spacing: 0.5px; }
        .card { border-radius: 12px; }
    </style>
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container mt-4">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-header bg-dark text-white py-3" id="formHeader">
                        <h6 class="mb-0 fw-bold" id="formTitle"><i class="fas fa-plus-circle me-2"></i>Nuevo Concepto</h6>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="<?= BASE_URL ?>tesoreria/conceptos" id="formConcepto">
                            <input type="hidden" name="uuid_concepto" id="uuid_concepto">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted text-uppercase">Nombre del Concepto</label>
                                <input type="text" name="nombre_concepto" id="nombre_concepto" class="form-control" placeholder="Ej: Cuota Marzo" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted text-uppercase">Carrera Vinculada</label>
                                <select name="id_carrera" id="id_carrera" class="form-select">
                                    <option value="">-- Concepto General (Todas) --</option>
                                    <?php foreach($carreras as $car): ?>
                                        <option value="<?= $car['id_carrera'] ?>"><?= htmlspecialchars($car['nombre_carrera']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold text-muted text-uppercase">Monto ($)</label>
                                    <input type="number" step="0.01" name="monto_sugerido" id="monto_sugerido" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-muted text-uppercase">Categoría</label>
                                    <select name="categoria" id="categoria" class="form-select" required>
                                        <option value="Mensualidad">Cuota</option>
                                        <option value="Matricula">Matrícula</option>
                                        <option value="Derecho de Examen">Examen</option>
                                        <option value="Extraordinario">Extraordinario</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" name="btn_guardar" id="btnSubmit" class="btn btn-primary w-100 fw-bold shadow-sm">Guardar Concepto</button>
                            <button type="button" onclick="cancelarEdicion()" id="btnCancelar" class="btn btn-link w-100 mt-2 text-decoration-none text-muted" style="display:none;">Cancelar edición</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Descripción</th>
                                    <th>Monto</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($conceptos as $c): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="fw-bold d-block"><?= htmlspecialchars($c['nombre_concepto']) ?></span>
                                        <small class="text-muted d-block mb-1"><?= $c['nombre_carrera'] ?? '<span class="text-primary">General</span>' ?></small>
                                        <?php 
                                            $badge = match($c['categoria']) {
                                                'Mensualidad' => 'bg-info',
                                                'Matricula' => 'bg-success',
                                                'Derecho de Examen' => 'bg-warning text-dark',
                                                'Extraordinario' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badge ?> rounded-pill" style="font-size: 0.7rem;"><?= $c['categoria'] ?></span>
                                    </td>
                                    <td class="fw-bold text-dark">$<?= number_format($c['monto_sugerido'], 2, ',', '.') ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick='editarConcepto(<?= json_encode($c) ?>)' title="Editar"><i class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarConcepto('<?= $c['uuid_concepto'] ?>')" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editarConcepto(data) {
            document.getElementById('formHeader').className = 'card-header bg-warning text-dark py-3';
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-pen-to-square me-2"></i>Modificando Concepto';
            document.getElementById('btnSubmit').className = 'btn btn-warning w-100 fw-bold';
            document.getElementById('btnSubmit').innerText = 'Actualizar Concepto';
            document.getElementById('btnCancelar').style.display = 'block';

            document.getElementById('uuid_concepto').value = data.uuid_concepto;
            document.getElementById('nombre_concepto').value = data.nombre_concepto;
            document.getElementById('monto_sugerido').value = data.monto_sugerido;
            document.getElementById('categoria').value = data.categoria;
            document.getElementById('id_carrera').value = data.id_carrera || "";
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelarEdicion() {
            window.location.href = '<?= BASE_URL ?>tesoreria/conceptos';
        }

        function eliminarConcepto(uuid) {
            Swal.fire({
                title: '¿Eliminar concepto?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '<?= BASE_URL ?>tesoreria/conceptos?delete_uuid=' + uuid;
                }
            })
        }

        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if (status === 'created') Swal.fire('¡Éxito!', 'Concepto registrado.', 'success');
            if (status === 'updated') Swal.fire('¡Actualizado!', 'Concepto modificado.', 'success');
            if (status === 'deleted') Swal.fire('¡Eliminado!', 'Concepto desactivado.', 'success');
            if (status === 'error') Swal.fire('Error', decodeURIComponent(urlParams.get('msg')), 'error');
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>