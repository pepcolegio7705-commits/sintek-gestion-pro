<?php
/**
 * SERVERSIDE: LISTADO DINÁMICO DE GASTOS Y EGRESOS
 */
session_start();
require_once '../../core/conexion.php';

header('Content-Type: application/json');

// Parámetros de DataTables
$draw   = $_GET['draw'] ?? 1;
$start  = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';

try {
    // 1. CONSTRUCCIÓN DE LA CONSULTA BASE
    $base_query = "FROM gastos g
                   INNER JOIN gastos_categorias gc ON g.id_cat_gasto = gc.id_cat_gasto
                   LEFT JOIN modos_pago mp ON g.id_modo_pago = mp.id_modo
                   WHERE 1=1";

    if (!empty($search)) {
        $base_query .= " AND (g.descripcion LIKE :search OR gc.nombre_categoria LIKE :search)";
    }

    // 2. CONTEO DE TOTALES
    $stmt_count = $pdo->prepare("SELECT COUNT(*) $base_query");
    if (!empty($search)) $stmt_count->bindValue(':search', "%$search%");
    $stmt_count->execute();
    $totalRecords = $stmt_count->fetchColumn();

    // 3. OBTENCIÓN DE DATOS
    $sql = "SELECT g.*, gc.nombre_categoria, mp.nombre_modo 
            $base_query 
            ORDER BY g.fecha_gasto DESC, g.id_gasto DESC 
            LIMIT $start, $length";
    
    $stmt = $pdo->prepare($sql);
    if (!empty($search)) $stmt->bindValue(':search', "%$search%");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataset = [];
    foreach ($data as $r) {
        // Badge de Estado
        $estado_badge = '<span class="badge bg-success">Pagado</span>';
        if ($r['estado'] === 'Anulado') {
            $estado_badge = '<span class="badge bg-danger" data-bs-toggle="tooltip" title="Motivo: '.$r['motivo_anulacion'].'">Anulado</span>';
        } elseif ($r['estado'] === 'Pendiente') {
            $estado_badge = '<span class="badge bg-warning text-dark">Pendiente</span>';
        }

        // Acciones
        $btn_anular = ($r['estado'] !== 'Anulado') ? 
            '<button class="btn btn-sm btn-outline-danger border-0" onclick="confirmarAnulacion('.$r['id_gasto'].')" title="Anular Gasto"><i class="fas fa-ban"></i></button>' : 
            '<i class="fas fa-info-circle text-muted" title="Ya anulado"></i>';

        // Si el gasto viene de haberes, mostramos un ícono especial
        $desc = $r['descripcion'];
        if (!empty($r['id_referencia_pago'])) {
            $desc = '<strong><i class="fas fa-university text-primary me-1"></i> ' . $desc . '</strong>';
        }

        $dataset[] = [
            "fecha_gasto"      => date('d/m/Y', strtotime($r['fecha_gasto'])),
            "nombre_categoria" => '<span class="fw-bold">'.$r['nombre_categoria'].'</span>',
            "descripcion"      => $desc,
            "monto"            => '$ ' . number_format($r['monto'], 2, ',', '.'),
            "estado"           => $estado_badge,
            "acciones"         => $btn_anular
        ];
    }

    echo json_encode([
        "draw"            => intval($draw),
        "recordsTotal"    => intval($totalRecords),
        "recordsFiltered" => intval($totalRecords),
        "data"            => $dataset
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}