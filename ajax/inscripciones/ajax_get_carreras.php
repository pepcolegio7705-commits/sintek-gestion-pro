<?php
require_once '../../core/conexion.php';

try {
    $stmt = $pdo->query("SELECT id_carrera, nombre_carrera FROM carreras WHERE activo = 1 ORDER BY nombre_carrera");
    $carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<option value="">Seleccione Carrera...</option>';
    foreach ($carreras as $c) {
        echo '<option value="' . $c['id_carrera'] . '">' . htmlspecialchars($c['nombre_carrera']) . '</option>';
    }
} catch (Exception $e) {
    echo '<option value="">Error al cargar</option>';
}