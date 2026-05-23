<?php
/**
 * PROCESAR ALTA DE GASTO MANUAL
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validar permisos y CSRF
verificar_permisos(['Administrador', 'Tesorería']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "tesoreria/gastos");
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die("Error de validación de seguridad (CSRF).");
}

try {
    // 1. Captura y limpieza de datos
    $fecha_gasto  = $_POST['fecha_gasto'] ?? date('Y-m-d');
    $id_cat       = (int)$_POST['id_cat_gasto'];
    $monto        = (float)$_POST['monto'];
    $id_modo      = (int)$_POST['id_modo_pago'];
    $descripcion  = strip_tags(trim($_POST['descripcion']));
    $usuario_reg  = $_SESSION['nombre_usuario'] ?? 'Sistema';

    if ($monto <= 0 || empty($descripcion) || $id_cat <= 0) {
        throw new Exception("Datos incompletos o monto inválido.");
    }

    $pdo->beginTransaction();

    // 2. Inserción en la tabla de Gastos
    $sql = "INSERT INTO gastos (
                id_cat_gasto, 
                descripcion, 
                monto, 
                fecha_gasto, 
                id_modo_pago, 
                estado, 
                usuario_registro, 
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, 'Pagado', ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $id_cat,
        $descripcion,
        $monto,
        $fecha_gasto,
        $id_modo,
        $usuario_reg
    ]);

    $pdo->commit();

    // Redirección con éxito
    header("Location: " . BASE_URL . "tesoreria/gastos?status=success");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Podrías pasar el mensaje de error por URL para capturarlo con SweetAlert
    header("Location: " . BASE_URL . "tesoreria/gastos?status=error&msg=" . urlencode($e->getMessage()));
}