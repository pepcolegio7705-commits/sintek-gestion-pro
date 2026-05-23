<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    exit('<option value="">Sesión expirada</option>');
}

$rol = $_SESSION['rol'];
$id_usuario = $_SESSION['id_usuario'];
$uuid_carrera = $_POST['uuid_carrera'] ?? '';

if (empty($uuid_carrera)) {
    exit('<option value="">Seleccione carrera primero</option>');
}

try {
    // 1. Obtener el id_carrera interno a partir del UUID por seguridad
    $stmt_c = $pdo->prepare("SELECT id_carrera FROM carreras WHERE uuid_carrera = ? LIMIT 1");
    $stmt_c->execute([$uuid_carrera]);
    $id_carrera = $stmt_c->fetchColumn();

    if (!$id_carrera) {
        exit('<option value="">Carrera no encontrada</option>');
    }

    // 2. Lógica de filtrado según el Rol
    if ($rol == "Administrador" || $rol == "Secretaría" || $rol == "Profesor") {
        // El administrativo ve todos los espacios de esa carrera
        $sql = "SELECT uuid_espacio, nombre_espacio, anio_cursada 
                FROM espacios_curriculares 
                WHERE id_carrera = ? 
                ORDER BY anio_cursada ASC, nombre_espacio ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_carrera]);
    } else {
        // El Profesor solo ve sus espacios asignados en esa carrera
        $sql = "SELECT e.uuid_espacio, e.nombre_espacio, e.anio_cursada 
                FROM espacios_curriculares e
                INNER JOIN profesores_espacios pe ON e.id_espacio = pe.id_espacio
                WHERE e.id_carrera = ? 
                AND pe.id_profesor = (SELECT id_profesor FROM profesores WHERE id_usuario = ?)
                ORDER BY e.anio_cursada ASC, e.nombre_espacio ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_carrera, $id_usuario]);
    }

    $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Generar las opciones del Select
    if ($espacios) {
        echo '<option value="" selected disabled>Seleccione espacio...</option>';
        foreach ($espacios as $e) {
            $anio = $e['anio_cursada'] . "° Año";
            echo "<option value='{$e['uuid_espacio']}'>" . htmlspecialchars($e['nombre_espacio']) . " ($anio)</option>";
        }
    } else {
        echo '<option value="">Sin espacios asignados</option>';
    }

} catch (PDOException $e) {
    exit('<option value="">Error en el servidor</option>');
}