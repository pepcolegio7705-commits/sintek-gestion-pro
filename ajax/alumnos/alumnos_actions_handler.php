<?php
/**
 * CONTROLADOR DE ACCIONES RÁPIDAS - SINTEK
 * Ubicación: /ajax/alumnos/alumnos_actions_handler.php
 */

// 1. Cargamos el núcleo (Subimos dos niveles)
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Establecemos que la respuesta siempre será un JSON
header('Content-Type: application/json');

// 2. Verificación de Seguridad: Sesión iniciada y Rol adecuado
// He añadido Secretaría para que sea consistente con tus ABM anteriores
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado o sesión expirada.']);
    exit;
}

// 3. Captura y validación de parámetros POST
$action    = $_POST['action'] ?? '';
$id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
$estado    = isset($_POST['estado']) ? (int)$_POST['estado'] : -1; 

// 4. Procesamiento de acciones
if ($action === 'toggle_activo') {
    
    // Validación de datos mínimos
    if ($id_alumno <= 0 || ($estado !== 0 && $estado !== 1)) {
        echo json_encode(['success' => false, 'message' => 'Parámetros de solicitud inválidos.']);
        exit;
    }

    try {
        // Preparamos la actualización del campo 'activo'
        $sql = "UPDATE alumnos SET activo = :estado WHERE id_alumno = :id";
        $stmt = $pdo->prepare($sql);
        
        $ejecucion = $stmt->execute([
            ':estado' => $estado,
            ':id'     => $id_alumno
        ]);

        if ($ejecucion) {
            $textoEstado = ($estado === 1) ? 'activado' : 'desactivado';
            echo json_encode([
                'success' => true, 
                'message' => "El alumno ha sido $textoEstado con éxito."
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se realizaron cambios en la base de datos.']);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Error en el servidor: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Si la acción no es reconocida
echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);