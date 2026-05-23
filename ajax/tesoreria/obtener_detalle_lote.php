<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

// Evitamos que cualquier warning ensucie el JSON
error_reporting(0); 

try {
    // 1. Recibimos solo lo que enviamos manualmente desde el JS
    $id_lote = (int)($_POST['id_lote'] ?? 0);
    $start   = (int)($_POST['start'] ?? 0);
    $length  = (int)($_POST['length'] ?? 10);
    $draw    = (int)($_POST['draw'] ?? 1);
    $f_ape   = trim($_POST['apellido'] ?? '');
    $f_dni   = trim($_POST['dni'] ?? '');

    // 2. Conteo simple (1 parámetro)
    $st_count = $pdo->prepare("SELECT COUNT(*) FROM liquidaciones_haberes WHERE id_lote = ?");
    $st_count->execute([$id_lote]);
    $recordsTotal = (int)$st_count->fetchColumn();

    // 3. Construcción del SQL con marcadores nombrados
    $where = " WHERE lh.id_lote = :id_lote";
    if ($f_ape !== '') $where .= " AND (p.apellido LIKE :ape OR s.apellido LIKE :ape)";
    if ($f_dni !== '') $where .= " AND (p.dni LIKE :dni OR s.dni LIKE :dni)";

    $joins = " FROM liquidaciones_haberes lh
               LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor')
               LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona = 'Staff')
               LEFT JOIN areas a ON (COALESCE(p.id_area, s.id_area) = a.id_area)";

    // 4. Conteo filtrado (Sincronizamos parámetros)
    $st_f = $pdo->prepare("SELECT COUNT(*) $joins $where");
    $st_f->bindValue(':id_lote', $id_lote, PDO::PARAM_INT);
    if ($f_ape !== '') $st_f->bindValue(':ape', "%$f_ape%", PDO::PARAM_STR);
    if ($f_dni !== '') $st_f->bindValue(':dni', "%$f_dni%", PDO::PARAM_STR);
    $st_f->execute();
    $recordsFiltered = (int)$st_f->fetchColumn();

    // 5. Query de datos final
    $sql = "SELECT lh.*, a.nombre_area,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.apellido ELSE s.apellido END as ape_res,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.nombre ELSE s.nombre END as nom_res,
            CASE WHEN lh.tipo_persona = 'Profesor' THEN p.dni ELSE s.dni END as dni_res
            $joins $where 
            ORDER BY ape_res ASC 
            LIMIT :start, :length";

    $stmt = $pdo->prepare($sql);
    
    // Bindeo uno por uno (Aquí es donde moría el HY093)
    $stmt->bindValue(':id_lote', $id_lote, PDO::PARAM_INT);
    if ($f_ape !== '') $stmt->bindValue(':ape', "%$f_ape%", PDO::PARAM_STR);
    if ($f_dni !== '') $stmt->bindValue(':dni', "%$f_dni%", PDO::PARAM_STR);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataset = [];
    foreach ($rows as $r) {
        // Retenciones de terceros
        $st_j = $pdo->prepare("SELECT SUM(monto) FROM liquidaciones_terceros WHERE id_liquidacion_origen = ?");
        $st_j->execute([$r['id_liquidacion']]);
        $monto_jud = (float)$st_j->fetchColumn();

        $dataset[] = [
            "agente"       => "<b>{$r['ape_res']}, {$r['nom_res']}</b><br><small>{$r['dni_res']}</small>",
            "nombre_area"  => $r['nombre_area'] ?? 'General',
            "bruto"        => '$ '.number_format($r['monto_bruto'], 2, ',', '.'),
            "ret_ley"      => '$ '.number_format($r['monto_retenciones_ley'], 2, ',', '.'),
            "ret_judicial" => '$ '.number_format($monto_jud, 2, ',', '.'),
            "neto"         => '$ '.number_format($r['monto_neto'], 2, ',', '.')
        ];
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data" => $dataset
    ]);

} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}