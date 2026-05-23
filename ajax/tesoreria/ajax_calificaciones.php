<?php
// ajax_calificaciones.php
require 'conexion.php';

$action = $_POST['action'] ?? '';

// --- 1. BUSCAR ALUMNO Y SUS CARRERAS ACTIVAS ---
if ($action == 'buscar_alumno') {
    $dni = trim($_POST['dni']);
    
    try {
        $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido FROM alumnos WHERE dni = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$dni]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($alumno) {
            // AGREGAMOS "DISTINCT" para evitar duplicados si hay varios registros en alumnos_carreras
            $sqlCarreras = "SELECT DISTINCT c.id_carrera, c.nombre_carrera 
                            FROM alumnos_carreras ac
                            INNER JOIN carreras c ON ac.id_carreras = c.id_carrera 
                            WHERE ac.id_alumno = ? AND c.activo = 1";
            
            $stmtC = $pdo->prepare($sqlCarreras);
            $stmtC->execute([$alumno['id_alumno']]);
            $carreras = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            if (empty($carreras)) {
                echo json_encode(['success' => false, 'message' => 'El alumno está activo pero no tiene carreras asignadas.']);
            } else {
                echo json_encode([
                    'success' => true,
                    'alumno' => [
                        'id_alumno' => $alumno['id_alumno'],
                        'nombre_completo' => $alumno['apellido'] . ", " . $alumno['nombre']
                    ],
                    'carreras' => $carreras
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Alumno no encontrado o inactivo.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de SQL: ' . $e->getMessage()]);
    }
    exit;
}

// --- 2. CARGAR ESPACIOS ACTIVOS DONDE EL ALUMNO ESTÁ INSCRIPTO ---
// --- 2. CARGAR ESPACIOS ACTIVOS DONDE EL ALUMNO ESTÁ INSCRIPTO ---
if ($action == 'cargar_espacios') {
    $id_carrera = (int)$_POST['id_carrera'];
    $id_alumno = (int)$_POST['id_alumno'];

    try {
        // CAMBIO CLAVE: La subconsulta ahora solo cuenta si la condición es 'APROBADO'
        $sql = "SELECT e.id_espacio, e.nombre_espacio,
                (SELECT COUNT(*) FROM calificaciones c 
                 WHERE c.id_alumno = ? 
                 AND c.id_espacio = e.id_espacio 
                 AND c.condicion = 'APROBADO') as ya_aprobado
                FROM espacios_curriculares e
                INNER JOIN inscripciones_espacios i ON e.id_espacio = i.id_espacio
                WHERE i.id_alumno = ? 
                AND e.id_carrera = ? 
                AND e.activo = 1
                ORDER BY e.nombre_espacio ASC";

        $stmt = $pdo->prepare($sql);
        // Seguimos pasando los mismos parámetros: id_alumno, id_alumno, id_carrera
        $stmt->execute([$id_alumno, $id_alumno, $id_carrera]);
        $espacios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'espacios' => $espacios
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al cargar materias: ' . $e->getMessage()]);
    }
    exit;
}
// --- 3. REGISTRAR LA CALIFICACIÓN ---
if ($action == 'registrar_calificacion') {
    $id_alumno = $_POST['id_alumno'];
    $id_carrera = $_POST['id_carrera'];
    $id_espacio = $_POST['id_espacio'];
    $nota = floatval($_POST['nota_final']);
    $libro = trim($_POST['libro'] ?? '');
    $folio = trim($_POST['folio'] ?? '');
    $observacion = trim($_POST['observacion'] ?? '');
    
    // Condición automática
    $condicion = 'DESAPROBADO';
    if ($nota >= 7) $condicion = 'APROBADO';
    elseif ($nota >= 4) $condicion = 'REGULAR';

    try {
        // Doble verificación de seguridad: Que el espacio siga activo antes de insertar
        $checkActivo = $pdo->prepare("SELECT activo FROM espacios_curriculares WHERE id_espacio = ?");
        $checkActivo->execute([$id_espacio]);
        $esActivo = $checkActivo->fetchColumn();

        if ($esActivo == 0) {
            echo json_encode(['success' => false, 'message' => 'No se puede registrar la nota: El espacio curricular ya no está activo.']);
            exit;
        }

        $sql = "INSERT INTO calificaciones (id_alumno, id_espacio, id_carrera, nota_final, condicion, libro, folio, observacion, fecha_registro) 
                VALUES (:alumno, :espacio, :carrera, :nota, :condicion, :libro, :folio, :obs, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':alumno'    => $id_alumno,
            ':espacio'   => $id_espacio,
            ':carrera'   => $id_carrera,
            ':nota'      => $nota,
            ':condicion' => $condicion,
            ':libro'     => $libro,
            ':folio'     => $folio,
            ':obs'       => $observacion
        ]);

        echo json_encode(['success' => true, 'message' => 'Calificación registrada con éxito']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
    }
    exit;
}