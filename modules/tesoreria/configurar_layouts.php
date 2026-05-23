<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// PROTECCIÓN ESTRICTA: Solo Administrador
if ($_SESSION['rol'] !== 'Administrador') {
    header("Location: " . BASE_URL . "dashboard?error=acceso_denegado");
    exit;
}
$rol = $_SESSION['rol'];
// Opciones de origen de datos para el Select
$origenes = [
    'agente_cbu'    => 'CBU del Agente/Entidad',
    'monto_pago'    => 'Monto Neto (Haberes/Retención)',
    'agente_nombre' => 'Nombre Completo Agente',
    'agente_dni'    => 'DNI del Agente',
    'entidad_nombre'=> 'Nombre de Entidad (Terceros)',
    'entidad_cuit'  => 'CUIT de Entidad (Terceros)',
    'fecha_hoy'     => 'Fecha Actual (AAAAMMDD)',
    'id_lote'       => 'ID de Lote'
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Layouts Bancarios | Sintek Pro</title>
    <link href="<?= BASE_URL; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .kpi-card { border: none; border-radius: 12px; transition: all 0.3s; border-left: 5px solid transparent; }
        .status-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .table-danger-light { background-color: #fff1f2 !important; }
        .text-xs { font-size: 0.7rem; font-weight: bold; }
        .btn-whatsapp { color: #fff; background-color: #25d366; border-color: #25d366; }
        .btn-whatsapp:hover { color: #fff; background-color: #128c7e; }
        .concepto-detalle { font-size: 0.65rem; color: #3b82f6; font-weight: bold; display: block; line-height: 1.2; margin-top: 2px; }
    </style>
</head>
<body>

    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark"><i class="fas fa-file-export me-2 text-primary"></i>Configuración de Layouts Bancarios</h4>
        <button type="button" class="btn btn-primary" onclick="nuevaConfiguracion()">
            <i class="fas fa-plus me-1"></i> Nuevo Banco
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Banco / Layout</th>
                            <th>Tipo</th>
                            <th>Secciones</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $stmt = $pdo->query("SELECT * FROM bancos_config ORDER BY nombre_banco ASC");
                            while ($b = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($b['nombre_banco']) ?></div>
                                    <small class="text-muted">Creado: <?= date('d/m/Y', strtotime($b['fecha_creacion'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $b['tipo_archivo'] ?></span>
                                    <?php if ($b['tipo_archivo'] === 'Delimitado'): ?>
                                        <small class="d-block text-muted">Separador: <b><?= htmlspecialchars($b['separador']) ?></b></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="fas <?= $b['usa_encabezado'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i> Cabecera<br>
                                        <i class="fas <?= $b['usa_pie_pagina'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>"></i> Pie
                                    </div>
                                </td>
                                <td>
                                    <?= $b['estado'] == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-warning" onclick="editarLayout(<?= $b['id_banco'] ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarBanco(<?= $b['id_banco'] ?>)" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLayout" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Diseñador de Estructura TXT</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <form id="formLayout">
                    <input type="hidden" name="id_banco" id="auth_id_banco" value="0">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="small fw-bold">Nombre del Banco / Configuración</label>
                            <input type="text" name="nombre_banco" class="form-control form-control-sm" placeholder="Ej: Santander - Pago Haberes" required>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Tipo de Archivo</label>
                            <select name="tipo_archivo" class="form-select form-select-sm">
                                <option value="Fijo">Ancho Fijo (Posiciones)</option>
                                <option value="Delimitado">Delimitado (Separadores)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="small fw-bold">Separador</label>
                            <input type="text" name="separador" class="form-control form-control-sm" placeholder="Ej: ;">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Opciones Adicionales</label>
                            <div class="d-flex gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="usa_encabezado" value="1" id="chkEnc">
                                    <label class="form-check-label small" for="chkEnc">Encabezado</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="usa_pie_pagina" value="1" id="chkPie">
                                    <label class="form-check-label small" for="chkPie">Pie de Página</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3 text-secondary">Definición de Columnas</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered bg-white shadow-sm" id="tablaColumnas">
                            <thead class="table-dark">
                                <tr class="small text-center">
                                    <th width="70">Orden</th>
                                    <th>Nombre Campo</th>
                                    <th>Origen Dato</th>
                                    <th width="100">Longitud</th>
                                    <th width="100">Relleno</th>
                                    <th width="100">Alineación</th>
                                    <th width="120">Formato</th>
                                    <th width="50"></th>
                                </tr>
                            </thead>
                            <tbody id="bodyColumnas">
                                </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFilaColumna()">
                        <i class="fas fa-plus-circle me-1"></i> Agregar Campo
                    </button>
                </form>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm px-4" onclick="guardarLayout()">GUARDAR CONFIGURACIÓN</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include '../../vistas/footer.php'; ?>
<script>
    const BASE_URL = '<?= BASE_URL; ?>';
    // Diccionario de orígenes traído desde PHP para mantener consistencia
    const ORIGENES_DATOS = <?= json_encode($origenes); ?>;

    /**
     * Agrega una fila a la tabla dinámica de columnas.
     * @param {Object|null} data - Datos de la columna si es edición.
     */
    function agregarFilaColumna(data = null) {
        // Calculamos el índice basado en la cantidad de filas actuales
        const index = $('#bodyColumnas tr').length + 1;
        
        // Generamos las opciones del select de origen de datos
        let opcionesOrigen = '';
        for (const [val, txt] of Object.entries(ORIGENES_DATOS)) {
            opcionesOrigen += `<option value="${val}" ${data && data.origen_dato == val ? 'selected' : ''}>${txt}</option>`;
        }
        opcionesOrigen += `<option value="Constante" ${data && data.origen_dato == 'Constante' ? 'selected' : ''}>-- Valor Fijo --</option>`;

        const fila = `
            <tr>
                <td><input type="number" name="col_orden[]" class="form-control form-control-sm text-center" value="${data ? data.orden : index}"></td>
                <td><input type="text" name="col_nombre[]" class="form-control form-control-sm" placeholder="Ej: CBU" value="${data ? data.nombre_campo : ''}"></td>
                <td>
                    <select name="col_origen[]" class="form-select form-select-sm">
                        ${opcionesOrigen}
                    </select>
                </td>
                <td><input type="number" name="col_longitud[]" class="form-control form-control-sm" value="${data ? data.longitud : ''}"></td>
                <td>
                    <select name="col_relleno[]" class="form-select form-select-sm">
                        <option value=" " ${data && data.relleno === ' ' ? 'selected' : ''}>Espacio</option>
                        <option value="0" ${data && data.relleno === '0' ? 'selected' : ''}>Cero (0)</option>
                    </select>
                </td>
                <td>
                    <select name="col_alineacion[]" class="form-select form-select-sm">
                        <option value="L" ${data && data.alineacion === 'L' ? 'selected' : ''}>Izquierda</option>
                        <option value="R" ${data && data.alineacion === 'R' ? 'selected' : ''}>Derecha</option>
                    </select>
                </td>
                <td>
                    <select name="col_formato[]" class="form-select form-select-sm">
                        <option value="Texto" ${data && data.tipo_formato === 'Texto' ? 'selected' : ''}>Texto</option>
                        <option value="Monto" ${data && data.tipo_formato === 'Monto' ? 'selected' : ''}>Monto</option>
                        <option value="Fecha" ${data && data.tipo_formato === 'Fecha' ? 'selected' : ''}>Fecha</option>
                    </select>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-link text-danger p-0" onclick="$(this).closest('tr').remove()"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        $('#bodyColumnas').append(fila);
    }

    /**
     * Prepara el modal para crear una nueva configuración de banco.
     */
    function nuevaConfiguracion() {
        // Resetear el formulario y asegurar que el ID de banco sea 0 para INSERT
        $('#formLayout')[0].reset();
        $('#auth_id_banco').val(0); 
        
        // Limpiar filas de la tabla y estados de checkboxes
        $('#bodyColumnas').empty();
        $('#chkEnc, #chkPie').prop('checked', false);
        
        // Agregar una fila inicial vacía
        agregarFilaColumna();
        
        $('.modal-title').html('<i class="fas fa-plus me-2"></i>Nueva Configuración de Banco');
        $('#modalLayout').modal('show');
    }

    /**
     * Carga los datos de un banco existente para su edición.
     * @param {int} id - ID del banco a editar.
     */
    function editarLayout(id) {
        Swal.fire({
            title: 'Cargando configuración...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        // Llamada al backend para obtener la estructura completa
        $.get(BASE_URL + 'tesoreria/obtener-detalle-layout', { id: id }, function(res) {
            Swal.close();
            if(res.success) {
                // 1. Limpiar el modal antes de inyectar datos
                $('#formLayout')[0].reset();
                $('#bodyColumnas').empty(); 
                
                // 2. Poblar campos de cabecera
                $('#auth_id_banco').val(res.banco.id_banco); // Este ID garantiza el UPDATE
                $('[name="nombre_banco"]').val(res.banco.nombre_banco);
                $('[name="tipo_archivo"]').val(res.banco.tipo_archivo);
                $('[name="separador"]').val(res.banco.separador);
                
                // Sincronizar checkboxes
                $('#chkEnc').prop('checked', parseInt(res.banco.usa_encabezado) === 1);
                $('#chkPie').prop('checked', parseInt(res.banco.usa_pie_pagina) === 1);

                // 3. Renderizar las columnas existentes
                if(res.columnas && res.columnas.length > 0) {
                    res.columnas.forEach(col => {
                        agregarFilaColumna(col);
                    });
                } else {
                    agregarFilaColumna(); // Fila por defecto si no hay columnas cargadas
                }

                $('.modal-title').html('<i class="fas fa-edit me-2"></i>Editar Layout: ' + res.banco.nombre_banco);
                $('#modalLayout').modal('show');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }, 'json').fail(function() {
            Swal.fire('Error', 'No se pudo obtener el detalle del banco.', 'error');
        });
    }

    /**
     * Envía la información al servidor para procesar el guardado o actualización.
     */
    function guardarLayout() {
        // Validación mínima: al menos una fila de datos
        if ($('#bodyColumnas tr').length === 0) {
            Swal.fire('Atención', 'Debe definir al menos una columna para el layout.', 'warning');
            return;
        }

        const datos = $('#formLayout').serialize();
        
        Swal.fire({
            title: 'Guardando configuración...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.post(BASE_URL + 'tesoreria/guardar-layout-banco', datos, function(res) {
            if(res.success) {
                Swal.fire('¡Excelente!', res.mensaje, 'success').then(() => {
                    location.reload(); // Recargar para refrescar la tabla principal
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }, 'json').fail(function() {
            Swal.fire('Error Crítico', 'Hubo un problema de comunicación con el servidor.', 'error');
        });
    }

    function eliminarBanco(id) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: "Si el banco no tiene historial se borrará, de lo contrario se marcará como inactivo.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(BASE_URL + 'tesoreria/eliminar-banco', { id: id }, function(res) {
                    if(res.success) {
                        Swal.fire('Procesado', res.mensaje, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                }, 'json');
            }
        });
    }
</script>