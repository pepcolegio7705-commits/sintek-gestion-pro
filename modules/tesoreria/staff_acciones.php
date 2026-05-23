<?php
require 'conexion.php';
require 'seguridad.php';

verificar_permisos(['Administrador']);
$rol = $_SESSION['rol'];

if (isset($_POST['id']) && isset($_POST['estado'])) {
    $id_staff = (int)$_POST['id'];
    $nuevo_estado = (int)$_POST['estado'];
    $id_area = (int)($_POST['area'] ?? 0);
    $dni = $_POST['dni'] ?? '';

    try {
        $pdo->beginTransaction();

        // 1. Si es Administrativo (Area 2) y estamos dando de BAJA (0)
        if ($nuevo_estado == 0 && $id_area == 2) {
            // Buscamos si existe en la tabla usuarios por su DNI (o nombre de usuario si usas DNI)
            // Asumimos que el 'nombre_usuario' en tu tabla usuarios es el DNI
            $sql_del = "DELETE FROM usuarios WHERE nombre_usuario = ?";
            $stmt_del = $pdo->prepare($sql_del);
            $stmt_del->execute([$dni]);
            
            // También limpiamos el vínculo en la tabla personal_staff
            $sql_null = "UPDATE personal_staff SET id_usuario = NULL WHERE id_staff = ?";
            $pdo->prepare($sql_null)->execute([$id_staff]);
        }

        // 2. Actualizamos el estado del personal (siempre ocurre)
        $sql_staff = "UPDATE personal_staff SET activo = ? WHERE id_staff = ?";
        $pdo->prepare($sql_staff)->execute([$nuevo_estado, $id_staff]);

        $pdo->commit();
        echo "ok";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "error: " . $e->getMessage();
    }
}