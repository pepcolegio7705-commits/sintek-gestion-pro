<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validamos permisos (Admin, Secretaría y Profesor pueden cargar)
verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uuid_mesa = $_POST['uuid_mesa'] ?? '';
    $id_espacio = (int)($_POST['id_espacio'] ?? 0);
    $libro = $_POST['libro'] ?? '';
    $folio = $_POST['folio'] ?? '';
    $obs_gral = $_POST['observacion_general'] ?? '';
    $notas = $_POST['notas'] ?? []; // Array [id_alumno => nota]
    $condiciones = $_POST['condicion'] ?? [];
    $id_usuario = $_SESSION['id_usuario'];

    try {
        $pdo->beginTransaction();

        // 1. Validar la mesa y obtener su ID real
        $stmt_m = $pdo->prepare("SELECT id_mesa, id_carrera, estado FROM mesas_examenes WHERE uuid_mesa = ?");
        $stmt_m->execute([$uuid_mesa]);
        $mesa = $stmt_m->fetch(PDO::FETCH_ASSOC);

        if (!$mesa) throw new Exception("Mesa no encontrada.");
        if ($mesa['estado'] === 'Cerrada') throw new Exception("Esta mesa ya se encuentra cerrada.");

        $id_mesa = $mesa['id_mesa'];
        $id_carrera = $mesa['id_carrera'];

        // 2. Procesar cada alumno
        foreach ($notas as $id_alumno => $nota_final) {
            // Saltamos si no se ingresó nota (opcional, depende si permites dejar ausentes)
            if ($nota_final === '') continue;

            $nota_f = (float)$nota_final;
            $condicion_actual = $condiciones[$id_alumno] ?? 'REGULAR';
            
            // Determinamos resultado básico (ajustar según tu escala, ej: 4 o 6)
            $resultado = ($nota_f >= 4) ? 'APROBADO' : 'REPROBADO';

            // A. INSERTAR EN examenes_notas (HISTORIAL COMPLETO)
            // Primero verificamos si ya existe (por si es una re-carga parcial)
            $check_ex = $pdo->prepare("SELECT id_examen_nota FROM examenes_notas WHERE id_mesa = ? AND id_alumno = ?");
            $check_ex->execute([$id_mesa, $id_alumno]);
            $existe_examen = $check_ex->fetch();

            if ($existe_examen) {
                $sql_ex = "UPDATE examenes_notas SET nota_final = ?, resultado = ?, libro = ?, folio = ?, observaciones = ?, id_usuario_carga = ?, fecha_registro = NOW() 
                           WHERE id_examen_nota = ?";
                $pdo->prepare($sql_ex)->execute([$nota_f, $resultado, $libro, $folio, $obs_gral, $id_usuario, $existe_examen['id_examen_nota']]);
            } else {
                $sql_ex = "INSERT INTO examenes_notas (id_mesa, id_alumno, nota_final, resultado, libro, folio, observaciones, id_usuario_carga, fecha_registro) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sql_ex)->execute([$id_mesa, $id_alumno, $nota_f, $resultado, $libro, $folio, $obs_gral, $id_usuario]);
            }

            // B. LÓGICA DE MEJORA EN cursadas_notas
            // Buscamos la nota que tiene actualmente en la cursada
            $stmt_c = $pdo->prepare("SELECT id_cursada_nota, nota_final_cursada FROM cursadas_notas WHERE id_alumno = ? AND id_espacio = ?");
            $stmt_c->execute([$id_alumno, $id_espacio]);
            $cursada = $stmt_c->fetch(PDO::FETCH_ASSOC);

            if ($cursada) {
                // REGLA DE ORO: Solo actualiza si la nota del examen es estrictamente MAYOR
                if ($nota_f > (float)$cursada['nota_final_cursada']) {
                    $upd_c = "UPDATE cursadas_notas SET nota_final_cursada = ?, fecha_registro = NOW() WHERE id_cursada_nota = ?";
                    $pdo->prepare($upd_c)->execute([$nota_f, $cursada['id_cursada_nota']]);
                }
            } else {
                // Si el alumno no tiene registro de cursada (ej. rindió libre sin cursar), lo creamos
                $ins_c = "INSERT INTO cursadas_notas (id_alumno, id_carrera, id_espacio, nota_final_cursada, fecha_registro) VALUES (?, ?, ?, ?, NOW())";
                $pdo->prepare($ins_c)->execute([$id_alumno, $id_carrera, $id_espacio, $nota_f]);
            }
        }

        // 3. CERRAR LA MESA
        $pdo->prepare("UPDATE mesas_examenes SET estado = 'Cerrada' WHERE id_mesa = ?")->execute([$id_mesa]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Acta cerrada. Notas registradas e historial actualizado.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}