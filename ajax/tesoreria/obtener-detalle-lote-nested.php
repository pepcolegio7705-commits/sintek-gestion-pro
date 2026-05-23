<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

try {
    // 1. Captura básica (como el código viejo)
    $id_lote = (int)($_POST['id_lote'] ?? 0);
    $start   = (int)($_POST['start'] ?? 0);
    $length  = (int)($_POST['length'] ?? 10);
    $draw    = (int)($_POST['draw'] ?? 1);
    
    // Filtros manuales (solo si los envías)
    $f_ape   = trim($_POST['apellido'] ?? '');
    $f_dni   = trim($_POST['dni'] ?? '');

    // 2. Construcción de Query
    $joins = " FROM liquidaciones_haberes lh
               LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor')
               LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona = 'Staff')
               LEFT JOIN areas a ON (COALESCE(p.id_area, s.id_area) = a.id_area)";

    $where = " WHERE lh.id_lote = $id_lote"; // Inyectamos ID directo

    // Antes de armar el SQL, limpiamos las entradas
    $f_ape = preg_replace("/[^a-zA-Z0-9 ]/", "", $f_ape); // Solo letras y números
    $f_dni = preg_replace("/[^0-9]/", "", $f_dni);        // Solo números

    // Agregamos filtros solo si existen
    if ($f_ape !== '') {
        $where .= " AND (p.apellido LIKE '%$f_ape%' OR s.apellido LIKE '%$f_ape%')";
    }
    if ($f_dni !== '') {
        $where .= " AND (p.dni LIKE '%$f_dni%' OR s.dni LIKE '%$f_dni%')";
    }

    // 3. Conteo (Simple, sin parámetros para no fallar)
    $recordsFiltered = $pdo->query("SELECT COUNT(*) $joins $where")->fetchColumn();

    // 4. Datos (Usando la técnica del código viejo: LIMIT directo)
    $sql = "SELECT lh.*, a.nombre_area,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.apellido ELSE s.apellido END as ape,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.nombre ELSE s.nombre END as nom,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.dni ELSE s.dni END as dni_res
            $joins 
            $where 
            ORDER BY ape ASC 
            LIMIT $start, $length";

    $stmt = $pdo->query($sql); // Usamos query directo como el código viejo
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataset = [];
    foreach ($rows as $r) {
        $dataset[] = [
            "agente" => "<b>{$r['ape']}, {$r['nom']}</b>",
            "dni"    => $r['dni_res'],
            "bruto"  => "$ " . number_format($r['monto_bruto'], 2, ',', '.'),
            "ret"    => "$ " . number_format($r['monto_retenciones_ley'], 2, ',', '.'),
            "neto"   => "<span class='fw-bold text-success'>$ " . number_format($r['monto_neto'], 2, ',', '.') . "</span>"
        ];
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsFiltered, 
        "recordsFiltered" => $recordsFiltered,
        "data"            => $dataset
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}