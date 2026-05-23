<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; 
require_once '../../core/seguridad.php';


$rol = $_SESSION['rol'];
verificar_permisos(['Administrador', 'Tesoreria']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bonos Extraordinarios | Sintek</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
<?php include '../../vistas/nav.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-plus-circle me-2"></i>Nuevo Bono</h6>
                </div>
                <div class="card-body">
                    <form id="formBono">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Descripción</label>
                            <input type="text" name="descripcion" class="form-control" placeholder="Ej: Bono Conectividad" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Monto ($)</label>
                            <input type="number" step="0.01" name="monto" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Alcance (Destino)</label>
                            <select name="alcance_destino" class="form-select">
                                <option value="Todos">Todo el Personal</option>
                                <option value="Profesor">Solo Profesores / Directivos</option>
                                <option value="Staff">Solo Administrativos / Maestranza</option>
                                <option value="Monotributo">Solo Monotributistas</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small">Mes</label>
                                <select name="mes" class="form-select">
                                    <?php foreach($meses as $n => $m): ?>
                                        <option value="<?= $n ?>" <?= $n == date('n') ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small">Año</label>
                                <input type="number" name="anio" class="form-control" value="<?= date('Y') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                            <i class="fas fa-save me-2"></i>GUARDAR BONO
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-primary"></i>Historial de Bonos</h6>
                </div>
                <div class="card-body">
                    <table id="tablaBonos" class="table table-hover w-100 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Concepto</th><th>Monto</th><th>Periodo</th><th>Alcance</th><th>Estado</th><th class="text-center">Acción</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL; ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL; ?>assets/js/sweetalert2.all.min.js"></script>

<script>
$(document).ready(function() {
    const tabla = $('#tablaBonos').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": { "url": "<?= BASE_URL; ?>tesoreria/obtener_bonos", "type": "POST" },
        "columns": [
            { "data": "descripcion" },
            { "data": "monto" },
            { "data": "periodo" },
            { "data": "alcance" },
            { "data": "estado" },
            { "data": "acciones", "orderable": false }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" }
    });

    $('#formBono').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: '<?= BASE_URL; ?>tesoreria/guardar_bono',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.success) {
                    Swal.fire('¡Éxito!', 'Bono registrado.', 'success');
                    $('#formBono')[0].reset();
                    tabla.ajax.reload();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            }
        });
    });

    // Delegación de evento para el botón Anular
    $('#tablaBonos').on('click', '.btn-anular', function() {
        const uuid = $(this).data('uuid');
        const token = $('input[name="csrf_token"]').val(); // Usamos el token del formulario

        Swal.fire({
            title: '¿Anular este bono?',
            text: "Este beneficio ya no se incluirá en las próximas liquidaciones.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '<?= BASE_URL; ?>tesoreria/anular_bono',
                    type: 'POST',
                    data: { uuid: uuid, csrf_token: token },
                    dataType: 'json',
                    success: function(res) {
                        if(res.success) {
                            Swal.fire('Anulado', 'El bono ha sido desactivado.', 'success');
                            $('#tablaBonos').DataTable().ajax.reload(); // Recarga la tabla sin refrescar la página
                        } else {
                            Swal.fire('Error', res.error, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    }
                });
            }
        });
    });
});
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>