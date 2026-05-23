<?php
session_start();
require_once '../../core/conexion.php';

if (!isset($_SESSION['rol'])) exit('<option value="">Sesión expirada</option>');
if (!isset($_POST['id_carrera'])) exit('<option value="">Error de parámetro</option>');

$id_carrera = (int)$_POST['id_carrera'];
$rol = $_SESSION['rol'];
$usuario = $_SESSION['nombre_usuario'] ?? '';

try {
    // Agregamos uuid_espacio a la consulta
    if ($rol == 'Administrador' || $rol == 'Secretaría') {
        $sql = "SELECT id_espacio, uuid_espacio, nombre_espacio, anio_cursada 
                FROM espacios_curriculares 
                WHERE id_carrera = ? AND activo = 1
                ORDER BY anio_cursada, nombre_espacio";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_carrera]);
    } else {
        $sql = "SELECT DISTINCT e.id_espacio, e.uuid_espacio, e.nombre_espacio, e.anio_cursada 
                FROM espacios_curriculares e
                INNER JOIN profesores_espacios ad ON e.id_espacio = ad.id_espacio
                INNER JOIN profesores p ON ad.id_profesor = p.id_profesor
                WHERE e.id_carrera = ? AND p.nombre_usuario = ? AND e.activo = 1
                ORDER BY e.anio_cursada, e.nombre_espacio";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_carrera, $usuario]);
    }

    $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($espacios) {
        echo '<option value="">-- Seleccione Espacio --</option>';
        foreach ($espacios as $e) {
            $nombre = htmlspecialchars($e['nombre_espacio']);
            // CLAVE: Agregar data-uuid='{$e['uuid_espacio']}'
            echo "<option value='{$e['id_espacio']}' data-uuid='{$e['uuid_espacio']}'>({$e['anio_cursada']}º Año) {$nombre}</option>";
        }
    } else {
        echo '<option value="">No hay materias disponibles</option>';
    }
} catch (PDOException $e) {
    echo '<option value="">Error técnico</option>';
}