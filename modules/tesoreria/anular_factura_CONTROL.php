<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validamos permisos (Solo personal de confianza/jerarquía)
verificar_permisos(['Administrador', 'Tesoreria']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uuid'])) {
    header('Content-Type: application/json');
    
    $uuid = $_POST['uuid'];
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Anulación sin motivo especificado';
    $id_usuario_anulo = $_SESSION['id_usuario'] ?? 0;

    try {
        // Iniciamos transacción para que sea "Todo o Nada"
        $pdo->beginTransaction();

        // 1. Buscamos los datos de la factura y su relación con exámenes
        $sql_search = "SELECT f.id_factura, f.id_alumno, fd.id_referencia_mesa 
                       FROM facturas f 
                       LEFT JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
                       WHERE f.uuid_factura = ? 
                       LIMIT 1";
        $stmt_search = $pdo->prepare($sql_search);
        $stmt_search->execute([$uuid]);
        $data_factura = $stmt_search->fetch(PDO::FETCH_ASSOC);

        if (!$data_factura) {
            throw new Exception("No se encontró la factura con el identificador proporcionado.");
        }

        $id_factura = $data_factura['id_factura'];
        $id_alumno  = $data_factura['id_alumno'];
        $id_mesa    = $data_factura['id_referencia_mesa'];

        // 2. ANULACIÓN FINANCIERA: Actualizamos la cabecera de la factura
        $sql_upd_f = "UPDATE facturas SET 
                        estado = 'Anulado', 
                        motivo_anulacion = ?, 
                        fecha_anulacion = NOW(), 
                        usuario_anulo = ? 
                      WHERE id_factura = ?";
        $pdo->prepare($sql_upd_f)->execute([$motivo, $id_usuario_anulo, $id_factura]);

        // Actualizamos todos los ítems del detalle a estado 'Anulado'
        $sql_upd_fd = "UPDATE factura_detalle SET estado = 'Anulado' WHERE id_factura = ?";
        $pdo->prepare($sql_upd_fd)->execute([$id_factura]);

        // 3. BAJA ACADÉMICA: Si la factura era por un Derecho de Examen (id_mesa no es null)
        if (!empty($id_mesa)) {
            $sql_del_ins = "DELETE FROM inscripciones_examen 
                            WHERE id_alumno = ? AND id_mesa = ?";
            $pdo->prepare($sql_del_ins)->execute([$id_alumno, $id_mesa]);
            
            $mensaje_final = "Factura anulada e inscripción al examen eliminada correctamente.";
        } else {
            $mensaje_final = "Factura anulada correctamente (No poseía inscripciones académicas asociadas).";
        }

        // Si todo salió bien, confirmamos los cambios en la DB
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => $mensaje_final
        ]);

    } catch (Exception $e) {
        // Si algo falló, revertimos cualquier cambio hecho arriba
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Error al procesar la anulación: ' . $e->getMessage()
        ]);
    }
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Petición no válida.']);
    exit;
}