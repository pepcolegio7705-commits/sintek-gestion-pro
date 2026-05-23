<?php
session_start();
require 'conexion.php';

// 1. Verificación de Seguridad: Sesión iniciada y existencia de datos
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['asistencia'])) {
    
    // Recibimos y sanitizamos los datos del contexto
    $id_espacio = (int)$_POST['id_espacio'];
    $id_carrera = (int)$_POST['id_carrera'];
    $fecha      = $_POST['fecha'];
    
    // Capturamos el ID del usuario desde la SESIÓN (más seguro que un hidden)
    // Asegúrate de que tu variable de sesión se llame 'id_usuario'
    $id_usuario_registro = isset($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;

    $asistencias_data = $_POST['asistencia']; // Array [id_alumno] => ESTADO
    $observaciones    = $_POST['obs'];        // Array [id_alumno] => TEXTO

    try {
        // Iniciamos la transacción para asegurar integridad
        $pdo->beginTransaction();

        // 2. Limpieza de registros previos
        // Esto permite "sobrescribir" la asistencia si el docente entra a corregir el mismo día
        $delete_sql = "DELETE FROM asistencias_clases WHERE id_espacio = ? AND fecha = ?";
        $stmt_del = $pdo->prepare($delete_sql);
        $stmt_del->execute([$id_espacio, $fecha]);

        // 3. Preparar la inserción masiva
        $insert_sql = "INSERT INTO asistencias_clases (
                            id_alumno, 
                            id_espacio, 
                            id_carrera, 
                            fecha, 
                            estado, 
                            observacion, 
                            id_usuario_registro
                       ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_ins = $pdo->prepare($insert_sql);

        // 4. Recorrer el array de asistencia enviado desde la planilla
        foreach ($asistencias_data as $id_alumno => $estado) {
            // Obtenemos la observación si existe para ese ID de alumno
            $obs = !empty($observaciones[$id_alumno]) ? trim($observaciones[$id_alumno]) : null;
            
            // Ejecutamos la inserción para cada alumno
            $stmt_ins->execute([
                (int)$id_alumno, 
                $id_espacio, 
                $id_carrera, 
                $fecha, 
                $estado, 
                $obs,
                $id_usuario_registro
            ]);
        }

        // Si todo salió bien, confirmamos los cambios
        $pdo->commit();

        // Redirigir al inicio con el mensaje de éxito
        header("Location: asistencia_inicio.php?msg=ok");
        exit;

    } catch (Exception $e) {
        // Si hay algún error (ej: falla la base de datos), deshacemos todo
        $pdo->rollBack();
        
        // En producción, es mejor loguear el error y mostrar un mensaje genérico
        die("Error crítico al procesar la asistencia: " . $e->getMessage());
    }

} else {
    // Si intentan entrar al archivo sin enviar el formulario
    header('Location: asistencia_inicio.php');
    exit;
}