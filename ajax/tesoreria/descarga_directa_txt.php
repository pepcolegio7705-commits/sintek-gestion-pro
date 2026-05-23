<?php
/**
 * DESCARGA DIRECTA DE TXT BANCARIO (RE-DESCARGA)
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';
// Incluimos el motor que creamos anteriormente
require_once 'motor_generador_txt.php'; 

// Verificamos permisos (solo personal autorizado)
verificar_permisos(['Administrador', 'Tesorería']);

$id_lote = (int)($_GET['id'] ?? 0);

if ($id_lote <= 0) {
    die("ID de lote no válido.");
}

try {
    // 1. Verificamos que el lote exista y esté en un estado que permita descarga (Procesado)
    $stmt = $pdo->prepare("SELECT estado FROM lotes_liquidaciones WHERE id_lote = ?");
    $stmt->execute([$id_lote]);
    $lote = $stmt->fetch();

    if (!$lote) {
        throw new Exception("El lote no existe.");
    }
    
    if ($lote['estado'] === 'Anulado') {
        throw new Exception("No se puede generar el archivo de un lote anulado.");
    }

    // 2. Generamos el contenido usando el motor dinámico
    $contenido = generarContenidoTXT($id_lote, $pdo);

    // 3. Definimos el nombre del archivo
    $nombre_archivo = "RE_ORDEN_PAGO_LOTE_" . $id_lote . "_" . date('Ymd_His') . ".txt";

    // 4. Enviamos las cabeceras HTTP para forzar la descarga
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // 5. Escupimos el contenido y terminamos
    echo $contenido;
    exit;

} catch (Exception $e) {
    // Si hay un error, lo mostramos de forma simple (o podrías redirigir con un mensaje)
    die("Error al generar la descarga: " . $e->getMessage());
}