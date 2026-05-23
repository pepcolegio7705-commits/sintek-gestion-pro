<?php
require 'conexion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_espacio = (int)$_POST['id_espacio'];
    $id_carrera = (int)$_POST['id_carrera'];
    $id_mesa    = (int)$_POST['id_mesa'];
    $libro      = trim($_POST['libro'] ?? '');
    $folio      = trim($_POST['folio'] ?? '');
    $obs        = trim($_POST['observacion_general'] ?? '');
    
    $notas      = $_POST['notas'] ?? []; // Array [id_alumno => nota]
    $cont = 0;

    if (empty($notas) || $id_mesa === 0) {
        echo json_encode(['success' => false, 'message' => "Datos insuficientes para procesar (Falta Mesa o Notas)."]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // --- PASO 1: LIMPIEZA PREVENTIVA ---
        $alumnos_ids = array_keys($notas);
        $placeholder = str_repeat('?,', count($alumnos_ids) - 1) . '?';
        $sqlDelete = "DELETE FROM calificaciones WHERE id_mesa = ? AND id_alumno IN ($placeholder)";
        $stmtDel = $pdo->prepare($sqlDelete);
        $stmtDel->execute(array_merge([$id_mesa], $alumnos_ids));

        // --- PASO 2: INSERCIÓN DE NUEVAS NOTAS ---
        $sql = "INSERT INTO calificaciones (
                    id_alumno, 
                    id_espacio, 
                    id_carrera, 
                    id_mesa, 
                    nota_final, 
                    condicion, 
                    libro, 
                    folio, 
                    observacion, 
                    fecha_registro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);

        foreach ($notas as $id_alumno => $valor_nota) {
            if ($valor_nota === "" || $valor_nota === null) continue;

            $nota = floatval($valor_nota);

            // Lógica de Condición Estándar
            if ($nota >= 7) {
                $condicion = 'APROBADO';
            } elseif ($nota == 6) {
                $condicion = 'REGULAR';
            } elseif ($nota <= 5) {
                $condicion = 'DESAPROBADO';
            }

            $stmt->execute([
                (int)$id_alumno, 
                $id_espacio, 
                $id_carrera, 
                $id_mesa, 
                $nota, 
                $condicion, 
                $libro, 
                $folio, 
                $obs
            ]);
            
            $cont++;
        }

        // --- PASO 3: CIERRE DE MESA (Modificación solicitada) ---
        // Actualizamos el estado de la mesa para que ya no figure como activa
        if ($cont > 0) {
            $sqlUpdateMesa = "UPDATE mesas_examenes SET estado = 'Cerrada' WHERE id_mesa = ?";
            $stmtMesa = $pdo->prepare($sqlUpdateMesa);
            $stmtMesa->execute([$id_mesa]);
        }

        $pdo->commit();
        
        if ($cont > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "¡Éxito! Se procesaron $cont alumnos y la mesa ha sido CERRADA. Libro: $libro, Folio: $folio."
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => "No se procesó ninguna nota (campos vacíos)."]);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => "Error de base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Método no permitido."]);
}