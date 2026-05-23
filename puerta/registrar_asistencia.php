<?php
// registrar_asistencia.php
date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json');
require '../core/conexion.php'; 

$response = ['success' => false, 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['dni'])) {
    exit(json_encode(['success' => false, 'message' => 'Acceso inválido.']));
}

$dni = trim($_POST['dni']);
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

try {
    // 1. TRIPLE BÚSQUEDA: Profesores, Alumnos y Staff (con su Área)
    $sql_search = "
        SELECT 'Profesor' as tipo, id_profesor as id, nombre, apellido, activo FROM profesores WHERE dni = :d1
        UNION ALL
        SELECT 'Alumno' as tipo, id_alumno as id, nombre, apellido, activo FROM alumnos WHERE dni = :d2
        UNION ALL
        SELECT a.nombre_area as tipo, s.id_staff as id, s.nombre, s.apellido, s.activo 
        FROM personal_staff s 
        INNER JOIN areas a ON s.id_area = a.id_area 
        WHERE s.dni = :d3
    ";
    
    $stmt_search = $pdo->prepare($sql_search);
    $stmt_search->execute([':d1' => $dni, ':d2' => $dni, ':d3' => $dni]);
    $persona = $stmt_search->fetch(PDO::FETCH_ASSOC);

    if (!$persona) {
        exit(json_encode(['success' => false, 'message' => "DNI ($dni) no registrado.", 'icon' => 'error']));
    }

    if ($persona['activo'] == 0) {
        exit(json_encode(['success' => false, 'message' => "Usuario INACTIVO. Consulte en oficina.", 'icon' => 'warning']));
    }

    $nombre_completo = $persona['nombre'] . ' ' . $persona['apellido'];
    $tipo_persona = $persona['tipo']; // Puede ser 'Profesor', 'Alumno' o el 'Nombre del Área'
    $id_referencia = $persona['id'];

    // 2. DETERMINAR ENTRADA O SALIDA
    $sql_last = "SELECT tipo_registro, hora_registro FROM asistencias 
                 WHERE dni_persona = ? AND fecha = ? ORDER BY id_asistencia DESC LIMIT 1";
    $stmt_last = $pdo->prepare($sql_last);
    $stmt_last->execute([$dni, $fecha_hoy]);
    $last_row = $stmt_last->fetch(PDO::FETCH_ASSOC);

    // Anti-duplicados (mismo minuto)
    if ($last_row && substr($last_row['hora_registro'], 0, 5) === substr($hora_actual, 0, 5)) {
        exit(json_encode(['success' => false, 'message' => "Ya registrado recientemente.", 'icon' => 'info']));
    }

    $tipo_registro = (!$last_row || $last_row['tipo_registro'] === 'Salida') ? 'Entrada' : 'Salida';
    
    // 3. GRABAR REGISTRO CON ID ESPECÍFICO
    $id_profesor = ($tipo_persona === 'Profesor') ? $id_referencia : null;
    $id_alumno   = ($tipo_persona === 'Alumno') ? $id_referencia : null;
    // Si no es ninguno de los dos, es Staff
    $id_staff    = ($id_profesor === null && $id_alumno === null) ? $id_referencia : null;

    $sql_ins = "INSERT INTO asistencias (dni_persona, nombre_completo, fecha, hora_registro, tipo_registro, tipo_persona, id_profesor, id_alumno, id_staff) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $pdo->prepare($sql_ins)->execute([
        $dni, $nombre_completo, $fecha_hoy, $hora_actual, $tipo_registro, $tipo_persona, $id_profesor, $id_alumno, $id_staff
    ]);

    echo json_encode([
        'success' => true,
        'message' => ($tipo_registro === 'Entrada') ? "¡Bienvenido, $nombre_completo!" : "¡Hasta pronto, $nombre_completo!",
        'icon' => ($tipo_registro === 'Entrada') ? 'success' : 'info',
        'nombre' => $nombre_completo,
        'registro' => $tipo_registro,
        'hora' => date('H:i', strtotime($hora_actual)),
        'tipo_persona' => $tipo_persona // Aquí viaja 'Alumno', 'Profesor' o 'Nombre del Área'
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => "Error de base de datos."]);
}