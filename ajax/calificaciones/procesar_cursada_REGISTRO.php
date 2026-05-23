<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');

// Solo personal autorizado
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_espacio = (int)$_POST['id_espacio'];
    $ciclo_lectivo = (int)$_POST['ciclo_lectivo'];
    $notas = $_POST['alum'] ?? []; 

    if (empty($notas)) {
        echo json_encode(['status' => 'error', 'message' => 'No hay datos para procesar.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Agregamos nota_final_cursada al UPDATE
        $sql = "UPDATE cursadas_notas SET 
                    parcial_1 = ?, 
                    parcial_2 = ?, 
                    recuperatorio = ?, 
                    nota_final_cursada = ?, 
                    asistencia = ?, 
                    id_condicion = ?
                WHERE id_alumno = ? 
                AND id_espacio = ? 
                AND ciclo_lectivo = ?";
        
        $stmt = $pdo->prepare($sql);

        foreach ($notas as $id_alumno => $data) {
            $p1    = ($data['p1'] === '')    ? null : $data['p1'];
            $p2    = ($data['p2'] === '')    ? null : $data['p2'];
            $rec   = ($data['rec'] === '')   ? null : $data['rec'];
            $final = ($data['final'] === '') ? null : $data['final']; // Captura la nota final
            $asist = ($data['asist'] === '') ? null : $data['asist'];
            $cond  = ($data['cond'] === '')  ? null : $data['cond'];

            if ($final > 10 || $p1 > 10) {
                throw new Exception("Las notas no pueden ser mayores a 10.");
            }

            $stmt->execute([
                $p1, $p2, $rec, $final, $asist, $cond, 
                (int)$id_alumno, $id_espacio, $ciclo_lectivo
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Planilla actualizada correctamente.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
}