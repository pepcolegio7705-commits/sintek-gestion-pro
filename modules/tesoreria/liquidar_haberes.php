<?php
/**
 * MÓDULO: LIQUIDACIÓN INDIVIDUAL PRO (BASADO EN TOKEN)
 * Ubicación: modules/tesoreria/liquidar_haberes.php
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

// Verificación de acceso
verificar_permisos(['Administrador', 'Tesoreria']);
$rol = $_SESSION['rol']; 
// 1. Recepción y Desencriptación del Token
$token = $_GET['token'] ?? '';
$params_raw = desencriptar_url($token);

if (!$params_raw) {
    // Si el token es inválido o fue alterado, bloqueamos el acceso
    header("Location: " . BASE_URL . "tesoreria/liquidacion-masiva?error=token_invalido");
    exit;
}

// Extraemos los datos del paquete cifrado
list($id_persona, $tipo_persona, $mes_liq, $anio_liq) = explode('|', $params_raw);

// 2. Consulta de Datos Básicos del Agente (Solo para Identificación en la UI)
$tabla = ($tipo_persona === 'Profesor') ? 'profesores' : 'personal_staff';
$id_col = ($tipo_persona === 'Profesor') ? 'id_profesor' : 'id_staff';

$stmt = $pdo->prepare("SELECT p.apellido, p.nombre, p.dni, p.cuil, a.nombre_area 
                       FROM $tabla p 
                       INNER JOIN areas a ON p.id_area = a.id_area 
                       WHERE p.$id_col = ?");
$stmt->execute([$id_persona]);
$agente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agente) die("Agente no encontrado en el sistema.");

$meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sintek | Liquidar Agente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --sintek-dark: #0f172a; --sintek-accent: #3b82f6; }
        body { background-color: #f1f5f9; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .card-liquidar { border: none; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .header-perfil { background: var(--sintek-dark); color: white; padding: 40px; border-radius: 20px 20px 0 0; }
        .badge-periodo { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 10px; }
        .monto-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .monto-total { font-size: 1.1rem; font-weight: 700; color: var(--sintek-dark); }
        .box-neto { background: #f0fdf4; border: 2px dashed #22c55e; border-radius: 15px; padding: 30px; text-align: center; }
        .btn-confirmar { background: #10b981; border: none; padding: 15px; font-weight: bold; font-size: 1.1rem; transition: 0.3s; }
        .btn-confirmar:hover { background: #059669; transform: translateY(-2px); }
    </style>
</head>
<body>

<?php include '../../vistas/nav.php'; ?>

<div class="container py-3">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card card-liquidar">
                <div class="header-perfil">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <span class="text-uppercase small opacity-75 fw-bold">Procesando Liquidación Individual</span>
                            <h1 class="display-6 fw-bold mt-1 mb-2"><?= $agente['apellido'] . ', ' . $agente['nombre'] ?></h1>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary px-3"><?= $tipo_persona ?></span>
                                <span class="opacity-75">| CUIL: <?= $agente['cuil'] ?? $agente['dni'] ?></span>
                                <span class="opacity-75">| <?= $agente['nombre_area'] ?></span>
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end mt-4 mt-md-0">
                            <div class="badge-periodo">
                                <i class="far fa-calendar-alt me-2"></i>
                                <span class="fw-bold"><?= $meses[$mes_liq] ?></span> de <?= $anio_liq ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <form id="formLiquidacionPro">
                        <input type="hidden" name="token_op" value="<?= $token ?>">

                        <div class="row g-5">
                            <div class="col-lg-7 border-end">
                                <h5 class="fw-bold mb-4 text-dark border-bottom pb-2">Desglose de Haberes</h5>
                                <div id="load-haberes" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-2 text-muted">Calculando escalafón y bonos...</p>
                                </div>
                                <div id="display-haberes" style="display:none;">
                                    </div>
                                
                                <h5 class="fw-bold mt-5 mb-4 text-dark border-bottom pb-2">Deducciones de Ley</h5>
                                <div id="display-descuentos">
                                    </div>
                            </div>

                            <div class="col-lg-5 ps-lg-5">
                                <div class="box-neto mb-4 shadow-sm">
                                    <span class="text-muted small fw-bold text-uppercase tracking-wider">Monto Neto a Liquidar</span>
                                    <div class="display-5 fw-bold text-success my-2" id="valNeto">$ 0,00</div>
                                    <span class="text-muted extra-small">Sujeto a validación de Tesorería</span>
                                </div>

                                <div class="p-4 bg-light rounded-4 border">
                                    <label class="form-label fw-bold text-muted small">MÉTODO DE PAGO</label>
                                    <select name="metodo_desembolso" id="metodo_desembolso" class="form-select border-0 shadow-sm mb-3">
                                        <option value="Efectivo">Efectivo (Caja Chica)</option>
                                        <option value="Cheque">Cheque Propio</option>
                                        <option value="Transferencia">Transferencia Manual</option>
                                    </select>

                                    <div id="extra-info-pago" style="display:none;">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Nro. Comprobante / Referencia</label>
                                            <input type="text" name="ref_pago" class="form-control border-0 shadow-sm" placeholder="Ej: CHQ-5502 / TRANS-991">
                                        </div>
                                    </div>

                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" id="checkAfectarCaja" name="afectar_caja" checked>
                                        <label class="form-check-label small" for="checkAfectarCaja">Registrar egreso automático en Libro Diario</label>
                                    </div>
                                </div>

                                <button type="button" id="btnProcesarFinal" class="btn btn-confirmar text-white w-100 mt-4 rounded-3 shadow-lg">
                                    <i class="fas fa-check-circle me-2"></i> REGISTRAR LIQUIDACIÓN
                                </button>

                                <div class="text-center mt-3">
                                    <a href="<?= BASE_URL ?>tesoreria/liquidar-masivo" class="text-muted small text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i> Volver al listado
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const BASE_URL = '<?= BASE_URL ?>';

    $(document).ready(function() {
        // 1. Cargar Vista Previa de Montos (Seguro, el cliente no ve la lógica)
        solicitarVistaPrevia();

        // 2. Control de Interfaz
        $('#metodo_desembolso').change(function() {
            $('#extra-info-pago').toggle($(this).val() !== 'Efectivo');
        });

        // 3. Botón de Procesamiento
        $('#btnProcesarFinal').click(function() {
            confirmarYProcesar();
        });
    });

    function solicitarVistaPrevia() {
        $.post(BASE_URL + 'ajax/tesoreria/obtener_previa_liquidacion.php', { token: '<?= $token ?>' }, function(res) {
            if(res.success) {
                $('#load-haberes').fadeOut(200, function() {
                    $('#display-haberes').html(res.html_haberes).fadeIn();
                    $('#display-descuentos').html(res.html_descuentos);
                    $('#valNeto').text(res.neto_formateado);
                });
            } else {
                Swal.fire('Error de Cálculo', res.error, 'error');
            }
        }, 'json');
    }

    function confirmarYProcesar() {
        Swal.fire({
            title: '¿Confirmar Operación?',
            text: "Se registrará el pago y se afectará la disponibilidad de caja.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Sí, Liquidar y Pagar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarAccion();
            }
        });
    }
    
    let procesando = false;

    function ejecutarAccion() {
        if(procesando) return; // Evita el doble click

        Swal.fire({ 
            title: 'Procesando...', 
            allowOutsideClick: false, 
            didOpen: () => { Swal.showLoading(); } 
        });

        procesando = true;
        $('#btnProcesarFinal').prop('disabled', true).addClass('opacity-50');

        // ESTE ES EL BLOQUE QUE PREGUNTABAS:
        $.post(BASE_URL + 'ajax/tesoreria/procesar_liquidacion_individual.php', $('#formLiquidacionPro').serialize(), function(res) {
            if(res.success) {
                // Si el guardado fue exitoso, llamamos a la pregunta de impresión
                // Pasamos 'res' porque contiene el 'token' encriptado que generó el PHP
                lanzarPreguntaImpresion(res); 
                
                // Cambiamos el estado del botón para que visualmente se sepa que ya terminó
                $('#btnProcesarFinal').html('<i class="fas fa-check-double me-2"></i> REGISTRADO EXITOSAMENTE');
            } else {
                // Si hubo un error (ej: ya estaba liquidado), liberamos el botón para corregir
                procesando = false;
                $('#btnProcesarFinal').prop('disabled', false).removeClass('opacity-50');
                Swal.fire('Atención', res.error, 'warning');
            }
        }, 'json');
    }

    function lanzarPreguntaImpresion(res) {
        Swal.fire({
            title: '¡Liquidación Registrada!',
            text: '¿Cómo desea imprimir el comprobante de haberes?',
            icon: 'success',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-file-pdf me-1"></i> Formato A4',
            denyButtonText: '<i class="fas fa-cut me-1"></i> Troquelado',
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#3b82f6',
            denyButtonColor: '#64748b',
            allowOutsideClick: false 
        }).then((result) => {
            if (result.isConfirmed || result.isDenied) {
                const formato = result.isConfirmed ? 'A4' : 'T';
                
                // La URL ahora es totalmente "malicia-proof" porque usa el token
                const urlRecibo = `${BASE_URL}tesoreria/imprimir-recibo/${res.token}/${formato}`;
                
                window.open(urlRecibo, '_blank');
            }
            
            // Al cerrar el modal o imprimir, volvemos al listado
            window.location.href = BASE_URL + 'tesoreria/liquidacion-masiva';
        });
    }
</script>

</body>
</html>