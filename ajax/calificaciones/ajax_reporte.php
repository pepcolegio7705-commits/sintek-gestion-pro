<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

/**
 * Función de Negocio: Determina la condición del alumno según la nota final.
 * Se fuerza el cálculo para que el listado sea coherente con la nota.
 */
function calcularCondicion($nota) {
    if ($nota === null || $nota === '' || $nota == 0) return null;
    $n = floatval($nota);
    
    if ($n >= 7.00) return 3; // PROMOCIONADO
    if ($n >= 6.00) return 1; // REGULAR
    return 2;                // LIBRE
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_espacios':
        $stmt = $pdo->prepare("SELECT id_espacio, nombre_espacio FROM espacios_curriculares WHERE id_carrera = ?");
        $stmt->execute([$_POST['id_carrera']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'reporte_alumno':
        $dni = $_POST['dni'];
        $ciclo = $_POST['ciclo'];
        
        $sql = "SELECT cn.*, e.nombre_espacio, a.uuid_alumno 
                FROM cursadas_notas cn
                JOIN alumnos a ON cn.id_alumno = a.id_alumno
                JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
                WHERE a.dni = ? AND cn.ciclo_lectivo = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dni, $ciclo]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if($res) {
            $html_cab = "<th>MATERIA</th><th>P1</th><th>P2</th><th>REC</th><th>FINAL</th><th>CONDICIÓN</th><th>ACCIONES</th>";
            $html_body = "";
            foreach($res as $f) {
                // RECALCULO DE CONDICIÓN EN TIEMPO REAL
                $id_c = $f['id_condicion'];
                if (!empty($f['nota_final_cursada'])) {
                    $id_c = calcularCondicion($f['nota_final_cursada']);
                }

                $texto_condicion = 'CURSANDO';
                $badge_class = 'bg-secondary';

                if ($id_c == 1) { 
                    $badge_class = 'bg-primary'; $texto_condicion = 'REGULAR'; 
                } elseif ($id_c == 2) { 
                    $badge_class = 'bg-danger'; $texto_condicion = 'LIBRE'; 
                } elseif ($id_c == 3) { 
                    $badge_class = 'bg-success'; $texto_condicion = 'PROMOCIONADO'; 
                }

                $html_body .= "<tr>
                    <td class='fw-bold small'>{$f['nombre_espacio']}</td>
                    <td>".($f['parcial_1'] ?? '-')."</td>
                    <td>".($f['parcial_2'] ?? '-')."</td>
                    <td>".($f['recuperatorio'] ?? '-')."</td>
                    <td class='text-primary fw-bold'>".($f['nota_final_cursada'] ?? '-')."</td>
                    <td><span class='badge {$badge_class} badge-condicion'>{$texto_condicion}</span></td>
                    <td>
                        <button class='btn btn-sm btn-outline-dark' onclick='editarFila({$f['id_cursada_nota']})'><i class='fas fa-edit'></i></button>
                    </td>
                </tr>";
            }
            echo json_encode([
                'success' => true, 
                'titulo' => 'Historial Académico', 
                'uuid_alumno' => $res[0]['uuid_alumno'],
                'html_cabecera' => $html_cab, 
                'html_cuerpo' => $html_body
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Sin resultados']);
        }
        break;

    case 'get_nota_individual':
        $id = (int)$_POST['id'];
        $sql = "SELECT cn.*, a.nombre, a.apellido, e.nombre_espacio 
                FROM cursadas_notas cn
                JOIN alumnos a ON cn.id_alumno = a.id_alumno
                JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
                WHERE cn.id_cursada_nota = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => !!$data, 'data' => $data]);
        break;

    case 'update_nota_individual':
        try {
            $id = (int)$_POST['id'];
            $p1 = $_POST['p1'] !== '' ? floatval($_POST['p1']) : null;
            $p2 = $_POST['p2'] !== '' ? floatval($_POST['p2']) : null;
            $rec = $_POST['rec'] !== '' ? floatval($_POST['rec']) : null;
            $asist = $_POST['asist'] !== '' ? (int)$_POST['asist'] : 0;
            $n_final = $_POST['n_final'] !== '' ? floatval($_POST['n_final']) : null;

            $id_condicion = calcularCondicion($n_final);
            
            $sql = "UPDATE cursadas_notas SET 
                    parcial_1 = :p1, parcial_2 = :p2, recuperatorio = :rec, 
                    asistencia = :asist, nota_final_cursada = :final, id_condicion = :cond 
                    WHERE id_cursada_nota = :id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':p1' => $p1, ':p2' => $p2, ':rec' => $rec,
                ':asist' => $asist, ':final' => $n_final,
                ':cond' => $id_condicion, ':id' => $id
            ]);

            echo json_encode(['success' => true, 'message' => 'Registro actualizado correctamente.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error SQL: ' . $e->getMessage()]);
        }
        break;

    case 'reporte_materia':
        $id_espacio = (int)$_POST['id_espacio'];
        $ciclo = $_POST['ciclo'];
        
        $stmt_uuid = $pdo->prepare("SELECT uuid_espacio FROM espacios_curriculares WHERE id_espacio = ?");
        $stmt_uuid->execute([$id_espacio]);
        $uuid_espacio = $stmt_uuid->fetchColumn();
        
        try {
            $sql = "SELECT cn.*, a.nombre, a.apellido, a.dni
                    FROM cursadas_notas cn
                    JOIN alumnos a ON cn.id_alumno = a.id_alumno
                    WHERE cn.id_espacio = :espacio AND cn.ciclo_lectivo = :ciclo
                    ORDER BY a.apellido, a.nombre ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':espacio' => $id_espacio, ':ciclo' => $ciclo]);
            $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if($res) {
                $html_cab = "<th>ALUMNO</th><th>DNI</th><th>P1</th><th>P2</th><th>REC</th><th>FINAL</th><th>ESTADO</th><th>ACCIONES</th>";
                $html_body = "";
                
                foreach($res as $f) {
                    // RECALCULO DE CONDICIÓN EN TIEMPO REAL
                    $id_c = $f['id_condicion'];
                    if (!empty($f['nota_final_cursada'])) {
                        $id_c = calcularCondicion($f['nota_final_cursada']);
                    }

                    $badge_class = 'bg-secondary';
                    $texto_condicion = 'CURSANDO';

                    if($id_c == 1) { $badge_class = 'bg-primary'; $texto_condicion = 'REGULAR'; }
                    elseif($id_c == 2) { $badge_class = 'bg-danger'; $texto_condicion = 'LIBRE'; }
                    elseif($id_c == 3) { $badge_class = 'bg-success'; $texto_condicion = 'PROMOCIONADO'; }

                    $html_body .= "<tr>
                        <td class='text-start'>
                            <div class='fw-bold text-uppercase' style='font-size:0.85rem;'>{$f['apellido']}, {$f['nombre']}</div>
                        </td>
                        <td>{$f['dni']}</td>
                        <td>" . ($f['parcial_1'] ?? '-') . "</td>
                        <td>" . ($f['parcial_2'] ?? '-') . "</td>
                        <td>" . ($f['recuperatorio'] ?? '-') . "</td>
                        <td class='fw-bold text-primary'>" . ($f['nota_final_cursada'] ?? '-') . "</td>
                        <td><span class='badge {$badge_class}'>{$texto_condicion}</span></td>
                        <td>
                            <button class='btn btn-sm btn-dark' onclick='editarFila({$f['id_cursada_nota']})'><i class='fas fa-edit'></i></button>
                        </td>
                    </tr>";
                }
                
                echo json_encode([
                    'success' => true, 
                    'titulo' => "Planilla de Cursada (" . count($res) . " alumnos)", 
                    'uuid_espacio' => $uuid_espacio,
                    'html_cabecera' => $html_cab, 
                    'html_cuerpo' => $html_body
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No se encontraron alumnos.']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error de DB: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}