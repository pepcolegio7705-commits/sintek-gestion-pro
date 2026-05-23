<?php
/**
 * SCRIPT ÚNICO: GENERADOR DE UUID PARA ESPACIOS CURRICULARES
 */

// 1. Ajusta la ruta a tu conexión
require_once 'core/conexion.php'; 

try {
    // 2. Buscamos registros que tengan el campo vacío o nulo
    $stmt = $pdo->query("SELECT id_espacio FROM espacios_curriculares WHERE uuid_espacio = '' OR uuid_espacio IS NULL");
    $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($materias) === 0) {
        die("<div style='font-family:sans-serif; color: #666;'>No hay registros pendientes de UUID en la tabla <b>espacios_curriculares</b>.</div>");
    }

    echo "<div style='font-family:sans-serif;'>";
    echo "<h3>Procesando actualización de UUIDs...</h3>";
    
    $pdo->beginTransaction();
    $updateStmt = $pdo->prepare("UPDATE espacios_curriculares SET uuid_espacio = ? WHERE id_espacio = ?");

    $contador = 0;
    foreach ($materias as $m) {
        // Generación de UUID v4 (compatible con PHP 7.4 y 8.x)
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante RFC 4122
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        if ($updateStmt->execute([$uuid, $m['id_espacio']])) {
            $contador++;
        }
    }

    $pdo->commit();
    echo "<p style='color: green;'><b>Éxito:</b> Se han actualizado <b>$contador</b> registros correctamente.</p>";
    echo "<p><i>Ya puedes eliminar este archivo por seguridad.</i></p>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("<p style='color: red;'>Error durante la actualización: " . $e->getMessage() . "</p>");
}