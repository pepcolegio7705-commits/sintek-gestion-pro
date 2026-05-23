<?php
session_start();
require 'conexion.php';

// Validamos que existan los datos necesarios
if (!isset($_POST['id_carrera']) || !isset($_SESSION['nombre_usuario'])) {
    exit('<option value="">Error de sesión o carrera</option>');
}

$id_carrera = $_POST['id_carrera'];
$rol = $_SESSION['rol']; 
$usuario = $_SESSION['nombre_usuario']; // Usamos el nombre de usuario de la sesión

try {
    if ($rol === 'Administrador' || $rol === 'Secretaría') {
        // ADMIN/SECRETARIO: Acceso total a la carrera
        $sql = "SELECT id_espacio, nombre_espacio 
                FROM espacios_curriculares 
                WHERE id_carrera = :id AND activo = 1 
                ORDER BY nombre_espacio ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id_carrera]);
    } 
    else {
        // DOCENTE/PROFESOR: Filtramos usando el nombre_usuario tal como en tu ejemplo
        $sql = "SELECT DISTINCT e.id_espacio, e.nombre_espacio 
                FROM espacios_curriculares e
                INNER JOIN profesores_espacios ad ON e.id_espacio = ad.id_espacio
                INNER JOIN profesores p ON ad.id_profesor = p.id_profesor
                WHERE e.id_carrera = :id 
                AND p.nombre_usuario = :usuario 
                AND e.activo = 1
                ORDER BY e.nombre_espacio ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $id_carrera, 
            'usuario' => $usuario
        ]);
    }

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($resultados) > 0) {
        echo '<option value="">Seleccione Espacio</option>';
        foreach ($resultados as $r) {
            echo "<option value='{$r['id_espacio']}'>{$r['nombre_espacio']}</option>";
        }
    } else {
        echo '<option value="">Sin materias asignadas para esta carrera</option>';
    }

} catch (PDOException $e) {
    echo '<option value="">Error en base de datos: ' . $e->getMessage() . '</option>';
}