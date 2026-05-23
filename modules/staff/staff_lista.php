<?php
session_start();
// Subimos dos niveles para llegar a core
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
$rol = $_SESSION['rol']; 

// Contadores para los KPIs superiores
$total_personal = $pdo->query("SELECT COUNT(*) FROM personal_staff")->fetchColumn();
$activos = $pdo->query("SELECT COUNT(*) FROM personal_staff WHERE activo = 1")->fetchColumn();
$areas_count = $pdo->query("SELECT COUNT(*) FROM areas")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Personal Staff | Sintek Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary-color: #4e73df; --secondary-color: #858796; }
        .card-kpi { border: none; border-left: 4px solid; transition: transform 0.2s; }
        .card-kpi:hover { transform: translateY(-3px); }
        .btn-action { width: 32px; height: 32px; padding: 0; line-height: 32px; text-align: center; border-radius: 50%; }
        table.dataTable thead { background-color: #f8f9fc; color: #4e73df; }
        .pulse-danger {
            animation: pulse-red 2s infinite;
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        @keyframes pulse-red {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(220, 53, 69, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid py-4">
        <div class="d-sm-flex align-items-center justify-content-between mb-4 px-3">
            <h1 class="h3 mb-0 text-gray-800">Panel de Staff Administrativo</h1>
            <a href="<?= BASE_URL ?>staff/gestion" class="btn btn-primary shadow-sm">
                <i class="fas fa-user-plus fa-sm text-white-50 me-2"></i> Nuevo Personal
            </a>
        </div>

        <div class="row px-3 mb-4">
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card card-kpi shadow h-100 py-2" style="border-left-color: #4e73df;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Personal</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $total_personal ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card card-kpi shadow h-100 py-2" style="border-left-color: #1cc88a;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Personal Activo</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $activos ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-check-circle fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card card-kpi shadow h-100 py-2" style="border-left-color: #36b9cc;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Áreas Configuradas</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $areas_count ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-building fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4 mx-3">
            <div class="card-header py-3 d-flex justify-content-between align-items-center bg-white">
                <h6 class="m-0 font-weight-bold text-primary">Listado General de Staff</h6>
                <div class="dropdown">
                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="far fa-file-excel text-success me-2"></i> Excel</a></li>
                        <li><a class="dropdown-item" href="#"><i class="far fa-file-pdf text-danger me-2"></i> PDF</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaStaff" class="table table-bordered w-100" style="width:100%">
                        <thead>
                            <tr>
                                <th>Legajo</th>
                                <th>DNI / CBU</th>
                                <th>Personal</th>
                                <th>Área / Situación</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tablaStaff').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": { 
                "url": "<?= BASE_URL; ?>staff/listado-data", 
                "type": "POST" 
            },
            "columns": [
                { "data": 0 }, // Legajo / ID
                { 
                    "data": null,
                    "render": function(data) {
                        let cbu = data[5] ? `<small class="text-primary d-block">${data[5]}</small>` : `<span class="badge bg-danger">FALTA CBU</span>`;
                        return `<b>${data[1]}</b>${cbu}`;
                    }
                },
                { "data": 2 }, // Apellido, Nombre
                { 
                    "data": null,
                    "render": function(data) {
                        let area = `<span>${data[3]}</span>`;
                        let hijos = parseInt(data[7]) || 0;
                        let verif = parseInt(data[8]) === 1;
                        let ret = parseInt(data[9]) || 0;
                        
                        let badges = '<div class="mt-1">';
                        if (hijos > 0) {
                            badges += verif ? 
                                `<span class="badge bg-success me-1" title="Hijos verificados"><i class="fas fa-users"></i></span>` : 
                                `<span class="badge bg-warning text-dark me-1" title="Pendiente verificación hijos"><i class="fas fa-exclamation-circle"></i></span>`;
                        }
                        if (ret > 0) {
                            badges += `<span class="badge bg-danger pulse-danger" title="Posee ${ret} embargos"><i class="fas fa-gavel"></i></span>`;
                        }
                        badges += '</div>';
                        return area + badges;
                    }
                },
                { 
                    "data": 4, // ESTADO
                    "render": function(data) {
                        return data == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                { 
                    "data": null,
                    "orderable": false,
                    "className": "text-center",
                    "render": function(data) {
                        let st = data[4];
                        let area = data[10];
                        let dni = data[1];
                        let uuid = data[11]; // Tomamos el UUID del agente
                        
                        return `
                            <div class="btn-group">
                                <a href="<?= BASE_URL; ?>staff/edit/${uuid}" class="btn btn-outline-primary" title="Editar"><i class="fas fa-edit"></i></a>
                                <button onclick="cambiarEstadoStaff('${uuid}', ${st == 1 ? 0 : 1}, ${area}, '${dni}')" class="btn ${st == 1 ? 'btn-outline-danger' : 'btn-outline-success'}">
                                    <i class="fas ${st == 1 ? 'fa-user-slash' : 'fa-user-check'}"></i>
                                </button>
                                <a href="<?= BASE_URL; ?>staff/legajo-pdf/${uuid}" target="_blank" class="btn btn-outline-danger" title="Imprimir Legajo Completo">
                                    <i class="fas fa-file-pdf"></i>
                                </a>
                                <a href="<?= BASE_URL; ?>staff/recibos/${uuid}" class="btn btn-outline-dark" title="Ver Recibos">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </a>
                                <a href="<?= BASE_URL ?>staff/certificacion/${uuid}" target="_blank" class="btn btn-sm btn-outline-info" title="Certificación de Servicios">
                                    <i class="fas fa-file-signature"></i>
                                </a>
                            </div>`;
                    }
                }
            ],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" }
        });
    });

    function cambiarEstadoStaff(uuid, nuevoEstado, area, dni) {
        const titulo = nuevoEstado === 0 ? "¿Dar de baja?" : "¿Reactivar personal?";
        let texto = nuevoEstado === 0 ? "El empleado pasará a estado inactivo." : "El empleado volverá a estar disponible.";
        
        if(nuevoEstado === 0 && area === 2) {
            texto += " ADVERTENCIA: Se eliminará su acceso al sistema permanentemente.";
        }

        Swal.fire({
            title: titulo,
            text: texto,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: nuevoEstado === 0 ? '#d33' : '#1cc88a',
            confirmButtonText: 'Sí, confirmar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    // URL amigable usando el UUID en la ruta
                    url: '<?= BASE_URL; ?>staff/status/' + uuid,
                    type: 'POST',
                    data: { 
                        uuid: uuid, 
                        estado: nuevoEstado,
                        area: area,
                        dni: dni
                    },
                    success: function(response) {
                        if(response.trim() === 'ok') {
                            $('#tablaStaff').DataTable().ajax.reload(null, false);
                            Swal.fire('Procesado', 'El estado se actualizó correctamente.', 'success');
                        } else {
                            Swal.fire('Error', response, 'error');
                        }
                    }
                });
            }
        });
    }

    // Manejo de errores de redirección
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'bloqueado') {
        Swal.fire({
            title: 'Acceso Denegado',
            text: 'No se puede editar un legajo inactivo. Debe reactivarlo primero.',
            icon: 'error'
        });
    }
</script>
<?php include '../../vistas/footer.php'; ?>
</body>
</html>