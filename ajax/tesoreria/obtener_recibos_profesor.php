<?php
/**
 * BACKEND EXCLUSIVO: HISTORIAL DE RECIBOS - PROFESORES
 * Filtro estricto por estado del Lote Relacionado.
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; 

header('Content-Type: application/json');

$id_persona = (int)($_POST['id'] ?? 0);
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$draw = (int)($_POST['draw'] ?? 1);
$anio_actual = date('Y');

if (!$id_persona) {
    echo json_encode(['error' => 'ID de profesor no especificado', 'data' => []]);
    exit;
}

try {
    /**
     * EXPLICACIÓN DE LA LÓGICA:
     * 1. Hacemos INNER JOIN con lotes_liquidaciones.
     * 2. El lote DEBE existir y su estado DEBE ser 'Procesado' o 'Autorizado' 
     * (ajusta el string según cómo guardes el estado del lote en tu tabla).
     */
    $estado_lote_ok = 'Procesado'; 

    // 1. ESTADÍSTICAS (Solo si el lote está procesado)
    $sql_stats = "SELECT 
                    SUM(lh.monto_neto) as total_anual, 
                    AVG(lh.monto_neto) as promedio, 
                    COUNT(lh.id_liquidacion) as cantidad 
                  FROM liquidaciones_haberes lh
                  INNER JOIN lotes_liquidaciones l ON lh.id_lote = l.id_lote
                  WHERE lh.id_persona = ? 
                  AND lh.tipo_persona = 'Profesor' 
                  AND lh.anio_liquidado = ? 
                  AND l.estado = ?";
    
    $stmt_s = $pdo->prepare($sql_stats);
    $stmt_s->execute([$id_persona, $anio_actual, $estado_lote_ok]);
    $stats = $stmt_s->fetch(PDO::FETCH_ASSOC);

    // 2. LISTADO (Filtro por relación de Lote)
    $sql_list = "SELECT lh.*, l.fecha_autorizacion, l.id_lote as lote_nro
                 FROM liquidaciones_haberes lh
                 INNER JOIN lotes_liquidaciones l ON lh.id_lote = l.id_lote
                 WHERE lh.id_persona = ? 
                 AND lh.tipo_persona = 'Profesor'
                 AND l.estado = ? 
                 ORDER BY lh.anio_liquidado DESC, lh.mes_liquidado DESC
                 LIMIT $start, $length";
    
    $stmt_l = $pdo->prepare($sql_list);
    $stmt_l->execute([$id_persona, $estado_lote_ok]);
    $recibos = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

    // 3. CONTEO TOTAL
    $stmt_count = $pdo->prepare("SELECT COUNT(*) 
                                 FROM liquidaciones_haberes lh
                                 INNER JOIN lotes_liquidaciones l ON lh.id_lote = l.id_lote
                                 WHERE lh.id_persona = ? AND lh.tipo_persona = 'Profesor' AND l.estado = ?");
    $stmt_count->execute([$id_persona, $estado_lote_ok]);
    $totalRecords = $stmt_count->fetchColumn();

    $data = [];
    foreach ($recibos as $r) {
        $token = encriptar_url($r['id_liquidacion']);
        $url_reimpresion = BASE_URL . "tesoreria/imprimir-recibo/" . $token . "/Legal?r=1";

        $info_pago = '<div class="badge bg-light text-dark border shadow-sm p-2 w-100">
                        <i class="fas fa-layer-group me-1 text-primary"></i> Lote #' . $r['lote_nro'] . '<br>
                        <small class="text-muted">' . date('d/m/Y', strtotime($r['fecha_autorizacion'])) . '</small>
                      </div>';

        $data[] = [
            "periodo" => str_pad($r['mes_liquidado'], 2, "0", STR_PAD_LEFT) . "/" . $r['anio_liquidado'],
            "bruto" => "$ " . number_format($r['monto_bruto'], 2, ',', '.'),
            "descuentos" => "$ " . number_format($r['monto_retenciones'], 2, ',', '.'),
            "neto" => "<span class='fw-bold text-success'>$ " . number_format($r['monto_neto'], 2, ',', '.') . "</span>",
            "info_pago" => $info_pago,
            "acciones" => '<a href="'.$url_reimpresion.'" target="_blank" class="btn btn-sm btn-outline-danger shadow-sm">
                                <i class="fas fa-print me-1"></i> Reimprimir
                            </a>'
        ];
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => (int)$totalRecords,
        "recordsFiltered" => (int)$totalRecords,
        "data" => $data,
        "stats" => [
            "total" => "$ " . number_format($stats['total_anual'] ?? 0, 2, ',', '.'),
            "promedio" => "$ " . number_format($stats['promedio'] ?? 0, 2, ',', '.'),
            "cantidad" => $stats['cantidad'] ?? 0
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}