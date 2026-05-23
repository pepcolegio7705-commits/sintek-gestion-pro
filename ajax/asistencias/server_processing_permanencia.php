<?php
    // server_processing_permanencia.php
    session_start();
    require_once '../../core/conexion.php';
    require_once '../../core/seguridad.php';


    date_default_timezone_set('America/Argentina/Buenos_Aires'); 

    if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['rol'], ['Administrador', 'Secretaría'])) {
        exit(json_encode(['error' => 'Acceso denegado.']));
    }

    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

    $fechaInicio = $_POST['fecha_inicio'] ?? null;
    $fechaFin = $_POST['fecha_fin'] ?? null;
    $tipoPersona = $_POST['tipo_persona'] ?? '';

    // Si no hay fechas filtradas, devolvemos vacío (para que no cargue nada al inicio)
    if (empty($fechaInicio) || empty($fechaFin)) {
        echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
        exit;
    }

    try {
        // 1. CONSULTA COMPLEJA: Emparejar cada entrada con su salida siguiente
        // Esta consulta busca para cada 'Entrada', la primera 'Salida' del mismo DNI que ocurra después
        $sql_base = "
            SELECT 
                e.dni_persona, 
                e.nombre_completo, 
                e.tipo_persona, 
                e.fecha, 
                e.hora_registro AS hora_entrada,
                (SELECT MIN(s.hora_registro) 
                FROM asistencias s 
                WHERE s.dni_persona = e.dni_persona 
                AND s.fecha = e.fecha 
                AND s.tipo_registro = 'Salida' 
                AND s.hora_registro > e.hora_registro) AS hora_salida
            FROM asistencias e
            WHERE e.tipo_registro = 'Entrada'
        ";

        // 2. Aplicar Filtros sobre la subconsulta
        $where = " AND e.fecha BETWEEN :f_inicio AND :f_fin";
        $params = [':f_inicio' => $fechaInicio, ':f_fin' => $fechaFin];

        if (!empty($tipoPersona)) {
            $where .= " AND e.tipo_persona = :tipo";
            $params[':tipo'] = $tipoPersona;
        }

        if (!empty($searchValue)) {
            // Usamos marcadores numerados o distintos para evitar el error HY093
            $where .= " AND (e.dni_persona LIKE :search1 OR e.nombre_completo LIKE :search2)";
            $params[':search1'] = "%$searchValue%";
            $params[':search2'] = "%$searchValue%";
        }

        // 3. Contar registros totales filtrados
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM ($sql_base $where) AS total");
        $stmt_count->execute($params);
        $recordsFiltered = $stmt_count->fetchColumn();

        // 4. Obtener datos con límites
        $sql_final = $sql_base . $where . " ORDER BY e.fecha DESC, e.hora_registro DESC LIMIT $start, $length";
        $stmt = $pdo->prepare($sql_final);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $tiempo_total = "---";

            if ($row['hora_entrada'] && $row['hora_salida']) {
                $t1 = new DateTime($row['hora_entrada']);
                $t2 = new DateTime($row['hora_salida']);
                $diff = $t1->diff($t2);
                // Si ya salió, guardamos el tiempo (Ej: 02:30:00)
                $tiempo_total = $diff->format('%H:%I:%S');
            } else {
                // SI NO SALIÓ, enviamos el texto plano "En Tránsito"
                $tiempo_total = "En Tránsito";
            }

            $data[] = [
                $row['dni_persona'],
                htmlspecialchars($row['nombre_completo']),
                $row['tipo_persona'],
                date('d/m/Y', strtotime($row['fecha'])),
                $row['hora_entrada'],
                $row['hora_salida'] ?? '---',
                $tiempo_total // Enviamos el dato limpio aquí
            ];
        }

        echo json_encode([
            "draw" => $draw,
            "recordsTotal" => $recordsFiltered, // En este caso usamos el mismo para simplificar
            "recordsFiltered" => $recordsFiltered,
            "data" => $data
        ]);

    } catch (PDOException $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }