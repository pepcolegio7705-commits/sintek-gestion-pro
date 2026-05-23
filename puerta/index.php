<?php
require_once '../core/conexion.php'; 
// (Mantener lógica de configuración de logo y nombre de institución igual que antes)
?>
<!DOCTYPE html>
<html lang="es" class="h-100">
<head>
    <meta charset="UTF-8">
    <title>Scanner Asistencia | Sintek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --azul-escuela: #003366; --azul-oscuro: #001a33; }
        body { background: linear-gradient(135deg, var(--azul-escuela) 0%, var(--azul-oscuro) 100%); color: #fff; font-family: 'Segoe UI', sans-serif; height: 100vh; overflow: hidden; }
        .flex-shrink-0 { flex-grow: 1; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .scanner-container { background: #fff; border-radius: 25px; padding: 40px; width: 100%; max-width: 600px; text-align: center; color: #333; box-shadow: 0 20px 60px rgba(0,0,0,0.8); }
        .scanner-header { color: var(--azul-escuela); font-weight: 850; font-size: 2.5rem; text-transform: uppercase; }
        #dniInput { height: 95px; font-size: 3.8rem; text-align: center; font-weight: 900; border: 4px solid #f0f2f5; border-radius: 20px; color: var(--azul-escuela); }
        .status-box { margin-top: 25px; padding: 20px; border-radius: 18px; background-color: #f8f9fc; border-left: 10px solid #eaecf4; min-height: 140px; text-align: left; }
        .pulse-input { animation: pulse-border 2s infinite; }
        @keyframes pulse-border { 0%, 100% { border-color: #f0f2f5; } 50% { border-color: #4e73df; } }
    </style>
</head>
<body class="d-flex flex-column h-100">
<main class="flex-shrink-0">
    <div class="scanner-container">
        <h1 class="scanner-header mb-1">Asistencia</h1>
        <div class="mb-4">
            <form id="formAsistencia">
                <input type="text" id="dniInput" name="dni" class="form-control pulse-input" placeholder="DNI" autofocus autocomplete="off">
            </form>
            <label class="form-label text-muted mt-2 small text-uppercase fw-bold">Ingrese DNI + ENTER</label>
        </div>
        <div id="statusBox" class="status-box shadow-sm">
            <div class="status-header h4 fw-bold text-muted" id="statusHeader">
                <i class="fas fa-fingerprint me-2"></i> Esperando...
            </div>
            <div id="lastScan">
                <p class="text-center text-muted my-2 small">Registre su entrada o salida.</p>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    const $dniInput = $('#dniInput');
    const $statusBox = $('#statusBox');
    const $statusHeader = $('#statusHeader');
    const $lastScan = $('#lastScan');

    $(document).on('click keydown', function() { if (!Swal.isVisible()) { $dniInput.focus(); } });

    // Función para activar Pantalla Completa
    function activarPantallaCompleta() {
        let elem = document.documentElement; // Selecciona toda la página
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) { /* Safari */
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) { /* IE11 */
            elem.msRequestFullscreen();
        }
    }   

    $('#formAsistencia').on('submit', function(e) {
        e.preventDefault();

        // DISPARADOR: Intentar poner pantalla completa al procesar el primer DNI
        activarPantallaCompleta();

        const dni = $dniInput.val().trim();
        if (dni.length < 6) return;

        $dniInput.prop('disabled', true);
        $statusHeader.html('<i class="fas fa-sync fa-spin me-2"></i> PROCESANDO...');

        $.post('registrar_asistencia.php', { dni: dni }, function(res) {
            if (res.success) {
                updateStatusBox(res);
                Swal.fire({ 
                    toast: true, 
                    position: 'top-end', 
                    icon: 'success', 
                    title: res.message, 
                    showConfirmButton: false, 
                    timer: 2000 
                });
            } else {
                $statusHeader.html('<i class="fas fa-times-circle me-2"></i> ERROR').addClass('text-danger');
                Swal.fire('Atención', res.message, 'warning');
            }
            $dniInput.val('').prop('disabled', false).focus();
        }, 'json');
    });

    function updateStatusBox(data) {
        const isEntrada = data.registro === 'Entrada';
        const colorClass = isEntrada ? 'text-success' : 'text-primary';
        $statusBox.css('border-left-color', isEntrada ? '#198754' : '#0d6efd');
        $statusHeader.removeClass('text-muted text-danger text-success text-primary').addClass(colorClass)
                     .html(`<i class="fas ${isEntrada ? 'fa-sign-in-alt' : 'fa-sign-out-alt'} me-2"></i> ${data.registro.toUpperCase()} OK`);
        
        $lastScan.hide().html(`
            <div class="row align-items-center">
                <div class="col-7">
                    <h5 class="mb-0 fw-bold text-dark">${data.nombre}</h5>
                    <small class="badge bg-secondary text-uppercase">${data.tipo_persona}</small>
                </div>
                <div class="col-5 text-end">
                    <h2 class="mb-0 fw-bold ${colorClass}">${data.hora}</h2>
                </div>
            </div>
        `).fadeIn();
    }
});
</script>
</body>
</html>