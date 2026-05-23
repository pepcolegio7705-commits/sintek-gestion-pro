<?php
/**
 * ACCIÓN: GENERAR ARCHIVO TXT PARA BANCO (Sincronizado con Motor y Seguridad)
 * Ubicación: /ajax/tesoreria/descargar_archivo_banco.php (o el nombre que uses en el .htaccess)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
// IMPORTANTE: Usamos el motor unificado para que el formato sea SIEMPRE el mismo
require_once '../../ajax/tesoreria/motor_generador_txt.php'; 

// 1. Verificación de Seguridad (Step-up Auth)
// Capturamos los datos que envía el JS mediante POST
$id_lote = (int)($_GET['id_lote'] ?? $_GET['id'] ?? 0);
$user_confirm = $_POST['confirm_user'] ?? '';
$pass_confirm = $_POST['confirm_pass'] ?? '';

if (!$id_lote) {
    die("ID de lote no válido.");
}

try {
    // 2. RE-VALIDACIÓN DE IDENTIDAD
    $stmtU = $pdo->prepare("SELECT password, id_rol FROM usuarios WHERE nombre_usuario = ? AND estado = 1");
    $stmtU->execute([$user_confirm]);
    $u = $stmtU->fetch();

    if (!$u || !password_verify($pass_confirm, $u['password']) || !in_array($u['id_rol'], [1, 2])) {
        echo "<script>alert('Error: Credenciales de autorizacion invalidas.'); window.history.back();</script>";
        exit;
    }

    // 3. Obtener información del lote y chequear estado de descarga
    $stmt_lote = $pdo->prepare("SELECT mes_periodo, anio_periodo, estado, descargado_txt FROM lotes_liquidaciones WHERE id_lote = ?");
    $stmt_lote->execute([$id_lote]);
    $lote = $stmt_lote->fetch(PDO::FETCH_ASSOC);

    if (!$lote) throw new Exception("El lote no existe.");
    if ($lote['estado'] !== 'Procesado') throw new Exception("El lote no ha sido finalizado correctamente.");

    $es_copia = ($lote['descargado_txt'] == 1);

    // 4. LIMPIEZA DE BUFFER
    // Esto evita que cualquier eco previo "pegue" los datos o rompa el formato
    if (ob_get_length()) ob_clean();

    // 5. GENERAR CONTENIDO USANDO EL MOTOR
    // El motor ya sabe cómo unir Profesores, Staff y Terceros con el layout del banco
    $contenido = generarContenidoTXT($id_lote, $pdo);

    // 6. MARCAR COMO DESCARGADO
    if (!$es_copia) {
        $pdo->prepare("UPDATE lotes_liquidaciones SET descargado_txt = 1 WHERE id_lote = ?")->execute([$id_lote]);
    }

    // 7. Configurar Nombre y Headers
    $prefijo = $es_copia ? "COPIA_" : "ORIGINAL_";
    $nombre_archivo = $prefijo . "LOTE_HABERES_" . str_pad($lote['mes_periodo'], 2, "0", STR_PAD_LEFT) . "_" . $lote['anio_periodo'] . ".txt";

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    // 8. Entregar el contenido
    echo $contenido;
    exit;

} catch (Exception $e) {
    die("Error al generar el archivo: " . $e->getMessage());
}