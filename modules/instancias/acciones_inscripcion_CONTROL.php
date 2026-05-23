<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Tesorería']);

if (isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    header('Content-Type: application/json');
    $id = (int)$_POST['id_inscripcion'];
    $motivo = $_POST['motivo'] ?? 'Anulación por eliminación de inscripción';
    
    try {
        $pdo->beginTransaction();
        
        // 1. Buscamos datos de la inscripción
        $stmt = $pdo->prepare("SELECT id_alumno, id_mesa FROM inscripciones_examen WHERE id_inscripcion = ?");
        $stmt->execute([$id]);
        $ins = $stmt->fetch();

        if($ins) {
            // 2. Anulamos la factura asociada y guardamos el MOTIVO
            // Ajusta "motivo_anulacion" al nombre real de tu columna en la tabla facturas
            $sql_anular = "UPDATE facturas f 
                           JOIN factura_detalle fd ON f.id_factura = fd.id_factura 
                           SET f.estado = 'Anulado', 
                               f.motivo_anulacion = ?, 
                               fd.estado = 'Anulado' 
                           WHERE f.id_alumno = ? AND fd.id_referencia_mesa = ?";
            
            $pdo->prepare($sql_anular)->execute([$motivo, $ins['id_alumno'], $ins['id_mesa']]);
        }

        // 3. Borramos la inscripción físicamente
        $pdo->prepare("DELETE FROM inscripciones_examen WHERE id_inscripcion = ?")->execute([$id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Inscripción eliminada y comprobante anulado correctamente.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}