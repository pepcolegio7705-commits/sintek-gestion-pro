<?php
    session_start();
    require 'conexion.php';
    require 'seguridad.php';

    // Verificamos permisos (Solo el Administrador puede anular)
    verificar_permisos(['Administrador']); 

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_factura = $_POST['id'] ?? null;
        $motivo = $_POST['motivo'] ?? 'Anulación desde historial';
        // Capturamos el usuario que está realizando la acción (usando tu variable de sesión)
        $usuario_que_anula = $_SESSION['nombre_usuario'] ?? 'Sistema';

        if (!$id_factura) {
            echo json_encode(['status' => 'error', 'message' => 'ID no proporcionado']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // --- PASO 1: Identificar datos para el efecto dominó ---
            // Buscamos si alguno de los detalles de esta factura tiene una referencia a una mesa de examen
            $sql_ref = "SELECT f.id_alumno, fd.id_referencia_mesa 
                        FROM facturas f 
                        JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
                        WHERE f.id_factura = ? AND fd.id_referencia_mesa IS NOT NULL";
            $stmt_ref = $pdo->prepare($sql_ref);
            $stmt_ref->execute([$id_factura]);
            $referencias = $stmt_ref->fetchAll(PDO::FETCH_ASSOC);

            // --- PASO 2: Actualizar Factura principal con datos de auditoría ---
            $sql1 = "UPDATE facturas SET 
                        estado = 'Anulado', 
                        motivo_anulacion = ?, 
                        usuario_anulo = ?, 
                        fecha_anulacion = NOW() 
                    WHERE id_factura = ?";
            
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$motivo, $usuario_que_anula, $id_factura]);

            // --- PASO 3: Actualizar Detalles ---
            $stmt2 = $pdo->prepare("UPDATE factura_detalle SET estado = 'Anulado' WHERE id_factura = ?");
            $stmt2->execute([$id_factura]);

            // --- PASO 4: El Efecto Dominó (Borrar Inscripción Académica) ---
            // Si encontramos referencias a mesas, eliminamos la inscripción para liberar el cupo/mesa
            if ($referencias) {
                foreach ($referencias as $ref) {
                    $sql_del_ins = "DELETE FROM inscripciones_examen 
                                    WHERE id_alumno = ? AND id_mesa = ?";
                    $stmt_del = $pdo->prepare($sql_del_ins);
                    $stmt_del->execute([$ref['id_alumno'], $ref['id_referencia_mesa']]);
                }
            }

            $pdo->commit();
            
            $msg = "Comprobante anulado correctamente.";
            if ($referencias) {
                $msg .= " También se ha eliminado la inscripción al examen asociada.";
            }

            echo json_encode(['status' => 'success', 'message' => $msg]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
?>