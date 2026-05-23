<?php
// inscripciones_actions_handler.php
session_start();
require 'conexion.php'; 
require 'seguridad.php'; // Importante para capturar el usuario que anula

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Error de procesamiento.'
];

if (!isset($_POST['action'])) {
    $response['message'] = 'Acción no especificada.';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'];

if ($action === 'eliminar') {
    if (!isset($_POST['id_inscripcion']) || !is_numeric($_POST['id_inscripcion'])) {
        $response['message'] = 'ID de inscripción no válido.';
        echo json_encode($response);
        exit;
    }

    $id_inscripcion = (int)$_POST['id_inscripcion'];
    $usuario_que_anula = $_SESSION['nombre_usuario'] ?? 'Sistema';
    
    try {
        $pdo->beginTransaction();

        // 1. Obtener ID de alumno e ID de mesa antes de borrar la inscripción
        $sql_info = "SELECT id_alumno, id_mesa FROM inscripciones_examen WHERE id_inscripcion = :id";
        $stmt_info = $pdo->prepare($sql_info);
        $stmt_info->execute([':id' => $id_inscripcion]);
        $datos = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($datos) {
            $id_al = $datos['id_alumno'];
            $id_me = $datos['id_mesa'];
            $motivo = "Anulación automática: Inscripción eliminada desde Gestión de Inscripciones por $usuario_que_anula";

            // 2. Buscar si hay una factura ligada a este alumno y esta mesa que esté activa
            $sql_fac = "SELECT f.id_factura FROM facturas f 
                        JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
                        WHERE f.id_alumno = :id_al 
                        AND fd.id_referencia_mesa = :id_me 
                        AND f.estado != 'Anulado'";
            
            $stmt_f = $pdo->prepare($sql_fac);
            $stmt_f->execute([':id_al' => $id_al, ':id_me' => $id_me]);
            $factura = $stmt_f->fetch(PDO::FETCH_ASSOC);

            if ($factura) {
                $id_f = $factura['id_factura'];
                
                // 3. Anular la Factura principal
                $sql_anula_f = "UPDATE facturas SET 
                                    estado = 'Anulado', 
                                    motivo_anulacion = :motivo, 
                                    usuario_anulo = :user, 
                                    fecha_anulacion = NOW() 
                                WHERE id_factura = :id_f";
                $pdo->prepare($sql_anula_f)->execute([
                    ':motivo' => $motivo, 
                    ':user' => $usuario_que_anula, 
                    ':id_f' => $id_f
                ]);

                // 4. Anular los detalles de la factura
                $pdo->prepare("UPDATE factura_detalle SET estado = 'Anulado' WHERE id_factura = :id_f")
                    ->execute([':id_f' => $id_f]);
            }

            // 5. Ahora sí, eliminamos el registro de la tabla de inscripciones
            $sql_del = "DELETE FROM inscripciones_examen WHERE id_inscripcion = :id";
            $stmt_del = $pdo->prepare($sql_del);
            $stmt_del->execute([':id' => $id_inscripcion]);

            if ($stmt_del->rowCount() > 0) {
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Inscripción eliminada' . ($factura ? ' y factura anulada ' : ' ') . 'correctamente.';
            } else {
                throw new Exception('No se pudo eliminar la inscripción.');
            }
        } else {
            $response['message'] = 'No se encontró la inscripción.';
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Acción no permitida.';
}

echo json_encode($response);
exit;