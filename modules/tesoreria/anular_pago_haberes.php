<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

verificar_permisos(['Administrador']);

$id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();

    // 1. Obtener ID del gasto vinculado
    $stmt = $pdo->prepare("SELECT id_gasto_vinculado FROM liquidaciones_haberes WHERE id_liquidacion = ?");
    $stmt->execute([$id]);
    $id_gasto = $stmt->fetchColumn();

    // 2. Anular la liquidación
    $pdo->prepare("UPDATE liquidaciones_haberes SET estado = 'Anulado' WHERE id_liquidacion = ?")->execute([$id]);

    // 3. Anular o eliminar el gasto de caja
    if($id_gasto) {
        // Opción A: Eliminarlo
        $pdo->prepare("DELETE FROM gastos WHERE id_gasto = ?")->execute([$id_gasto]);
        
        // Opción B (Más prolija): Marcarlo como anulado si tu tabla gastos tiene esa columna
        // $pdo->prepare("UPDATE gastos SET estado = 'Anulado' WHERE id_gasto = ?")->execute([$id_gasto]);
    }

    $pdo->commit();
    header("Location: liquidaciones_historial.php?msj=anulado");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al anular: " . $e->getMessage());
}