<?php
require_once 'conexion.php';
require_once 'funciones.php'; // Necesitamos generar_uuid_v4()

try {
    // 1. Buscamos todos los profesores que tengan el UUID vacío o NULL
    $stmt = $pdo->query("SELECT id_profesor FROM profesores WHERE uuid_profesor IS NULL OR uuid_profesor = ''");
    $profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($profesores) === 0) {
        die("Todos los profesores ya tienen UUID. No es necesario correr este parche.");
    }

    $pdo->beginTransaction();
    $update = $pdo->prepare("UPDATE profesores SET uuid_profesor = ? WHERE id_profesor = ?");

    $count = 0;
    foreach ($profesores as $p) {
        $nuevo_uuid = generar_uuid_v4();
        $update->execute([$nuevo_uuid, $p['id_profesor']]);
        $count++;
    }

    $pdo->commit();
    echo "¡Éxito! Se asignaron $count UUIDs a la tabla de profesores.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error durante el parche: " . $e->getMessage());
}