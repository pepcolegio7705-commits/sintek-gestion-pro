<?php
/**
 * SCRIPT ÚNICO: GENERADOR DE UUID PARA CARRERAS EXISTENTES
 */
require_once 'core/conexion.php'; // Ajustá la ruta a tu archivo de conexión

try {
    // 1. Buscamos todas las carreras que tengan el uuid_carrera vacío o NULL
    $stmt = $pdo->query("SELECT id_carrera FROM carreras WHERE uuid_carrera IS NULL OR uuid_carrera = ''");
    $carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($carreras) === 0) {
        die("No hay carreras pendientes de UUID o la columna no existe.");
    }

    echo "Iniciando actualización de " . count($carreras) . " carreras...<br>";

    // 2. Preparamos la actualización
    $update = $pdo->prepare("UPDATE carreras SET uuid_carrera = ? WHERE id_carrera = ?");

    $count = 0;
    foreach ($carreras as $c) {
        // Generamos un UUID v4 manual o usando random_bytes para máxima compatibilidad
        $nuevo_uuid = bin2hex(random_bytes(16)); 
        // Si preferís el formato con guiones (8-4-4-4-12):
        $nuevo_uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($nuevo_uuid, 4));

        if ($update->execute([$nuevo_uuid, $c['id_carrera']])) {
            echo "Carrera ID: {$c['id_carrera']} -> UUID: $nuevo_uuid <br>";
            $count++;
        }
    }

    echo "<br><b>¡Éxito! Se actualizaron $count carreras correctamente.</b>";
    echo "<br><span style='color:red'>RECUERDA BORRAR ESTE ARCHIVO AHORA.</span>";

} catch (Exception $e) {
    die("Error en el script: " . $e->getMessage());
}