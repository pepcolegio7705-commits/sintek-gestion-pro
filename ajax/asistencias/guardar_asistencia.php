<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

header('Content-Type: application/json');
// Verificamos que tenga permiso para escribir
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uuid_espacio = $_POST['uuid_espacio'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $asistencias = $_POST['asistencia'] ?? [];    // Array [id_alumno => 'P', 'T', etc]
    $observaciones = $_POST['observacion'] ?? []; // Array [id_alumno => 'Texto']
    $id_usuario = $_SESSION['id_usuario'];

    if (empty($uuid_espacio) || empty($fecha)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Obtener IDs reales desde el UUID por seguridad
        $stmt_v = $pdo->prepare("SELECT id_espacio, id_carrera FROM espacios_curriculares WHERE uuid_espacio = ?");
        $stmt_v->execute([$uuid_espacio]);
        $info = $stmt_v->fetch(PDO::FETCH_ASSOC);

        if (!$info) throw new Exception("Espacio curricular no válido.");

        $id_espacio = $info['id_espacio'];
        $id_carrera = $info['id_carrera'];

        // 2. Preparar el INSERT con UPDATE por duplicado
        // Importante: id_usuario_registro y fecha_registro se actualizan si se edita
        $sql = "INSERT INTO asistencias_clases 
                (id_alumno, id_espacio, id_carrera, fecha, estado, observacion, id_usuario_registro, fecha_registro) 
                VALUES (:id_a, :id_e, :id_c, :fecha, :estado, :obs, :id_u, NOW())
                ON DUPLICATE KEY UPDATE 
                estado = VALUES(estado), 
                observacion = VALUES(observacion),
                id_usuario_registro = VALUES(id_usuario_registro),
                fecha_registro = NOW()";
        
        $stmt = $pdo->prepare($sql);

        foreach ($asistencias as $id_alumno => $valor_radio) {
            $estado_db = 'PRESENTE'; // Default
            if ($valor_radio == 'A')  $estado_db = 'AUSENTE';
            if ($valor_radio == 'T')  $estado_db = 'TARDE';
            if ($valor_radio == 'AJ') $estado_db = 'JUSTIFICADO';

            $stmt->execute([
                ':id_a'   => (int)$id_alumno,
                ':id_e'   => (int)$id_espacio,
                ':id_c'   => (int)$id_carrera,
                ':fecha'  => $fecha,
                ':estado' => $estado_db,
                ':obs'    => $observaciones[$id_alumno] ?? '',
                ':id_u'   => (int)$id_usuario
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Asistencia procesada correctamente.']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}