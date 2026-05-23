<?php
// calificaciones_actions_handler.php

require 'conexion.php'; // Archivo de conexión PDO
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    $response['message'] = 'Acción no válida.';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'];
$id_calificacion = isset($_POST['id_calificacion']) ? (int)$_POST['id_calificacion'] : 0;

if ($id_calificacion === 0) {
    $response['message'] = 'ID de calificación no proporcionado.';
    echo json_encode($response);
    exit;
}

try {
    // --- VALIDACIÓN DE SEGURIDAD: Verificar si el alumno está ACTIVO ---
    $sql_check = "SELECT a.activo 
                  FROM alumnos a 
                  JOIN calificaciones c ON a.id_alumno = c.id_alumno 
                  WHERE c.id_calificacion = :id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id_calificacion, PDO::PARAM_INT);
    $stmt_check->execute();
    $estado_alumno = $stmt_check->fetchColumn();

    if ($estado_alumno === false || $estado_alumno == 0) {
        $response['message'] = 'Operación denegada. El alumno está INACTIVO y su historial es de solo lectura.';
        echo json_encode($response);
        exit;
    }

    if ($action === 'modificar') {
        $nueva_nota = isset($_POST['nota_final']) ? (float)$_POST['nota_final'] : 0;
        
        if ($nueva_nota < 1 || $nueva_nota > 10) {
            $response['message'] = 'La nota debe estar entre 1 y 10.';
            echo json_encode($response);
            exit;
        }

        // --- LÓGICA PARA DETERMINAR LA CONDICIÓN ---
        if ($nueva_nota >= 7) {
            $nueva_condicion = 'APROBADO';
        } elseif ($nueva_nota >= 6 && $nueva_nota < 7) {
            // Solo si es 6 (o entre 6 y 6.99) es REGULAR
            $nueva_condicion = 'REGULAR';
        } else {
            // Si es 5.99 o menos (incluyendo el 5) es DESAPROBADO
            $nueva_condicion = 'DESAPROBADO';
        }
        // --- UPDATE CORREGIDO: Ahora incluye la columna 'condicion' ---
        $sql = "UPDATE calificaciones 
                SET nota_final = :nota, 
                    condicion = :condicion, 
                    fecha_registro = NOW() 
                WHERE id_calificacion = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':nota', $nueva_nota);
        $stmt->bindParam(':condicion', $nueva_condicion); // Se agrega la condición calculada
        $stmt->bindParam(':id', $id_calificacion, PDO::PARAM_INT);
        $stmt->execute();
        
        $response['success'] = true;
        $response['message'] = "Calificación actualizada a $nueva_nota ($nueva_condicion) con éxito.";

    } elseif ($action === 'eliminar') {
        $sql = "DELETE FROM calificaciones WHERE id_calificacion = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id_calificacion, PDO::PARAM_INT);
        $stmt->execute();

        $response['success'] = true;
        $response['message'] = 'Calificación eliminada correctamente.';
        
    } else {
        $response['message'] = 'Acción desconocida.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
}

echo json_encode($response);