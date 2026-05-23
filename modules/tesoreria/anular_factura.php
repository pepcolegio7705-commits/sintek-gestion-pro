<?php
session_start();
// 1. Ajuste de rutas relativas
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Verificamos permisos (Solo el Administrador puede anular)
verificar_permisos(['Administrador']); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. Cambiamos ID por UUID (es lo que envía el JS ahora)
    $uuid_factura = $_POST['uuid'] ?? null;
    $motivo = strip_tags(trim($_POST['motivo'] ?? 'Anulación desde historial'));
    $usuario_que_anula = $_SESSION['nombre_usuario'] ?? 'Sistema';

    if (!$uuid_factura) {
        echo json_encode(['status' => 'error', 'message' => 'Identificador de factura no proporcionado']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // --- PASO 1: Buscar la factura por UUID para obtener su ID interno ---
        $stmt_f = $pdo->prepare("SELECT id_factura, id_alumno FROM facturas WHERE uuid_factura = ?");
        $stmt_f->execute([$uuid_factura]);
        $factura = $stmt_f->fetch(PDO::FETCH_ASSOC);

        if (!$factura) {
            throw new Exception("La factura no existe.");
        }

        $id_factura = $factura['id_factura'];
        $id_alumno = $factura['id_alumno'];

        // --- PASO 2: Identificar referencias académicas (Efecto Dominó) ---
        // Buscamos si alguno de los detalles tiene una referencia a mesa de examen
        $sql_ref = "SELECT id_referencia_mesa FROM factura_detalle 
                    WHERE id_factura = ? AND id_referencia_mesa IS NOT NULL";
        $stmt_ref = $pdo->prepare($sql_ref);
        $stmt_ref->execute([$id_factura]);
        $referencias = $stmt_ref->fetchAll(PDO::FETCH_ASSOC);

        // --- PASO 3: Actualizar Factura (Auditoría) ---
        $sql1 = "UPDATE facturas SET 
                    estado = 'Anulado', 
                    motivo_anulacion = ?, 
                    usuario_anulo = ?, 
                    fecha_anulacion = NOW() 
                 WHERE id_factura = ?";
        $pdo->prepare($sql1)->execute([$motivo, $usuario_que_anula, $id_factura]);

        // --- PASO 4: Actualizar Detalles ---
        $pdo->prepare("UPDATE factura_detalle SET estado = 'Anulado' WHERE id_factura = ?")
            ->execute([$id_factura]);

        // --- PASO 5: Borrar Inscripciones Académicas vinculadas ---
        $deleted_academic = false;
        if ($referencias) {
            foreach ($referencias as $ref) {
                $sql_del_ins = "DELETE FROM inscripciones_examen WHERE id_alumno = ? AND id_mesa = ?";
                $pdo->prepare($sql_del_ins)->execute([$id_alumno, $ref['id_referencia_mesa']]);
                $deleted_academic = true;
            }
        }

        $pdo->commit();
        
        $msg = "Comprobante anulado correctamente.";
        if ($deleted_academic) {
            $msg .= " Se ha liberado el cupo en la mesa de examen.";
        }

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}