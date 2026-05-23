<?php
// profesores_asignar_handler.php
// Script que maneja las peticiones AJAX para asignar/desasignar espacios y sus horas


require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Solicitud inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_profesor   = isset($_POST['id_profesor']) ? (int)$_POST['id_profesor'] : 0;
    $id_espacio    = isset($_POST['id_espacio']) ? (int)$_POST['id_espacio'] : 0;
    $horas_catedra = isset($_POST['horas_catedra']) ? (int)$_POST['horas_catedra'] : 0;
    $action        = isset($_POST['action']) ? $_POST['action'] : '';

    if ($id_profesor > 0 && $id_espacio > 0) {
        try {
            if ($action === 'asignar') {
                // Usamos una sentencia que inserta o actualiza las horas si ya existe la relación
                // Esto es vital para el input numérico que agregamos
                $sql = "INSERT INTO profesores_espacios (id_profesor, id_espacio, horas_catedra) 
                        VALUES (:id_p, :id_e, :horas) 
                        ON DUPLICATE KEY UPDATE horas_catedra = :horas_update";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_p' => $id_profesor,
                    ':id_e' => $id_espacio,
                    ':horas' => $horas_catedra,
                    ':horas_update' => $horas_catedra
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Asignación y horas actualizadas correctamente.';

            } elseif ($action === 'desasignar') {
                $sql = "DELETE FROM profesores_espacios WHERE id_profesor = :id_p AND id_espacio = :id_e";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id_p' => $id_profesor,
                    ':id_e' => $id_espacio
                ]);

                $response['success'] = true;
                $response['message'] = 'Desasignación eliminada correctamente.';
            }

        } catch (PDOException $e) {
            $response['message'] = 'Error de base de datos: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Faltan datos obligatorios.';
    }
}

echo json_encode($response);
?>