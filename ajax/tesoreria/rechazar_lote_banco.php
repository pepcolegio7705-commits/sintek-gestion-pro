<?php
/**
 * PROCESO: RECHAZO BANCARIO DE LOTE
 * Libera agentes y anula el gasto vinculado.
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

// Capturamos datos del POST
$id_lote = (int)($_POST['id_lote'] ?? 0);
$motivo  = trim($_POST['motivo'] ?? '');
$user    = $_POST['user'] ?? '';
$pass    = $_POST['pass'] ?? '';

try {
    // 1. Re-validación de identidad (Step-up Auth)
    $stmtU = $pdo->prepare("SELECT id_usuario, password, id_rol FROM usuarios WHERE nombre_usuario = ? AND estado = 1");
    $stmtU->execute([$user]);
    $u = $stmtU->fetch();

    if (!$u || !password_verify($pass, $u['password']) || !in_array($u['id_rol'], [1, 2])) {
        throw new Exception("Credenciales incorrectas o permisos insuficientes.");
    }

    if (strlen($motivo) < 10) {
        throw new Exception("Debe ingresar un motivo de rechazo detallado (mín. 10 caracteres).");
    }

    $pdo->beginTransaction();

    // 2. Identificar el gasto vinculado antes de borrar el detalle
    // Buscamos en liquidaciones_haberes el ID del gasto que generó este lote
    $stmtG = $pdo->prepare("SELECT id_gasto_vinculado FROM liquidaciones_haberes WHERE id_lote = ? LIMIT 1");
    $stmtG->execute([$id_lote]);
    $id_gasto = $stmtG->fetchColumn();

    // 3. LIBERACIÓN DE AGENTES (EL CORAZÓN DEL PROCESO)
    // Borramos los registros de las tablas de liquidación para que vuelvan a aparecer como "pendientes"
    $pdo->prepare("DELETE FROM liquidaciones_haberes WHERE id_lote = ?")->execute([$id_lote]);
    $pdo->prepare("DELETE FROM liquidaciones_terceros WHERE id_lote = ?")->execute([$id_lote]);

    // 4. ACTUALIZAR CABECERA DEL LOTE
    // Cambiamos a estado 'Rechazo Banco' y guardamos quién lo hizo y por qué
    $sqlL = "UPDATE lotes_liquidaciones SET 
                estado = 'Rechazo Banco', 
                motivo_anulacion = ?, 
                id_user_autoriza = ? 
             WHERE id_lote = ?";
    $pdo->prepare($sqlL)->execute([$motivo, $u['id_usuario'], $id_lote]);

    // 5. ANULAR GASTO VINCULADO
    // El gasto en el libro diario debe quedar como 'Anulado' para que no afecte el balance
    if ($id_gasto) {
        $pdo->prepare("UPDATE gastos SET estado = 'Anulado', motivo_anulacion = ? WHERE id_gasto = ?")
            ->execute(["Lote #$id_lote rechazado por Banco. Motivo: $motivo", $id_gasto]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'mensaje' => "El lote #$id_lote ha sido marcado como Rechazado por el Banco. Los agentes ya se encuentran disponibles para ser liquidados nuevamente."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}