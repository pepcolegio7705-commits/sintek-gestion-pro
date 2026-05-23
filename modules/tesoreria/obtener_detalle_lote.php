<?php
require 'conexion.php';
header('Content-Type: application/json');

$id_lote = (int)($_POST['id_lote'] ?? 0);

try {
    // Traemos las liquidaciones vinculadas, uniendo con las tablas de origen para ver datos frescos
    // Usamos UNION para unificar Profesores y Staff en una sola vista para el modal
    $sql = "SELECT 
                lh.tipo_persona as categoria,
                lh.monto_bruto,
                lh.monto_retenciones,
                lh.monto_neto,
                CASE 
                    WHEN lh.tipo_persona = 'Profesor' THEN p.apellido 
                    ELSE s.apellido 
                END as apellido,
                CASE 
                    WHEN lh.tipo_persona = 'Profesor' THEN p.nombre 
                    ELSE s.nombre 
                END as nombre,
                CASE 
                    WHEN lh.tipo_persona = 'Profesor' THEN p.dni 
                    ELSE s.dni 
                END as dni,
                CASE 
                    WHEN lh.tipo_persona = 'Profesor' THEN p.cbu 
                    ELSE s.cbu 
                END as cbu
            FROM liquidaciones_haberes lh
            LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor')
            LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona = 'Staff')
            WHERE lh.id_lote = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_lote]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $data]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}