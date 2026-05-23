<?php
session_start();
require 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['rol'])) {
    $id_alumno = (int)$_POST['id_alumno'];
    $id_carrera = (int)$_POST['id_carrera'];
    $fecha_hoy = date('Y-m-d'); // Capturamos la fecha del sistema

    try {
        $pdo->beginTransaction();
        
        // 1. Cambiamos estado a 2 y guardamos la fecha de egreso
        $stmt = $pdo->prepare("UPDATE alumnos SET activo = 2, fecha_egreso = ? WHERE id_alumno = ?");
        $stmt->execute([$fecha_hoy, $id_alumno]);

        // 2. Sello en la tabla calificaciones (estado 2)
        $stmt2 = $pdo->prepare("UPDATE calificaciones SET estado = 2 WHERE id_alumno = ? AND id_carrera = ?");
        $stmt2->execute([$id_alumno, $id_carrera]);

        $stmt3 = $pdo->prepare("INSERT INTO egresados (id_alumno, id_carrera, fecha_egreso) VALUES (?, ?, NOW())");
        $stmt3->execute([$id_alumno, $id_carrera]);

        $pdo->commit();
        echo "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "Error: " . $e->getMessage();
    }
}