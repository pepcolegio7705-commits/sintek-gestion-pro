<?php
/**
 * BACKEND PARA HISTORIAL DE RECIBOS (Sintek-Pro)
 * Bloqueo estricto de recibos no procesados.
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php'; 

header('Content-Type: application/json');

$id_persona = (int)($_POST['id'] ?? 0);
$tipo_persona = $_POST['tipo'] ?? '';
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$draw = (int)($_POST['draw'] ?? 1);
$anio_actual = date('Y');

if (!$id_persona || !$tipo_persona) {
    echo json_encode(['error' => 'Parámetros insuficientes', 'data' => []]);
    exit;
}

try {
    // 1. ESTADÍSTICAS (Solo lo que ya está cobrado/procesado)
    $sql_stats = "SELECT 
                    SUM(monto_neto) as total_anual, 
                    AVG(monto_neto) as promedio, 
                    COUNT(id_liquidacion) as cantidad 
                  FROM liquidaciones_haberes 
                  WHERE id_persona = ? 
                  AND tipo_persona = ? 
                  AND anio_liquidado = ? 
                  AND TRIM(estado) = 'Procesado'"; // TRIM para evitar errores de espacios
    
    $stmt_s = $pdo->prepare($sql_stats);
    $stmt_s->execute([$id_persona, $tipo_persona, $anio_actual]);
    $stats = $stmt_s->fetch(PDO::FETCH_ASSOC);

    // 2. LISTADO PAGINADO (Filtro estricto de seguridad)
    $sql_list = "SELECT lh.*, l.fecha_autorizacion 
                 FROM liquidaciones_haberes lh
                 LEFT JOIN lotes_liquidaciones l ON lh.id_lote = l.id_lote
                 WHERE lh.id_persona = ? 
                 AND lh.tipo_persona = ?
                 AND TRIM(lh.estado) = 'Procesado' 
                 ORDER BY lh.anio_liquidado DESC, lh.mes_liquidado DESC
                 LIMIT $start, $length";
    
    $stmt_l = $pdo->prepare($sql_list);
    $stmt_l->execute([$id_persona, $tipo_persona]);
    $recibos = $stmt_l->fetchAll(PDO::FETCH_ASSOC);

    // 3. CONTEO PARA PAGINACIÓN
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM liquidaciones_haberes WHERE id_persona = ? AND tipo_persona = ? AND TRIM(estado) = 'Procesado'");
    $stmt_count->execute([$id_persona, $tipo_persona]);
    $totalRecords = $stmt_count->fetchColumn();

    $data = [];
    foreach ($recibos as $r) {
        $token = encriptar_url($r['id_liquidacion']);
        $url_reimpresion = BASE_URL . "tesoreria/imprimir-recibo/" . $token . "/Legal?r=1";

        // Lógica de visualización de pago
        if (!empty($r['id_lote']) && $r['id_lote'] > 0) {
            $fecha_pago = ($r['fecha_autorizacion']) ? date('d/m/Y', strtotime($r['fecha_autorizacion'])) : 'Confirmado';
            $info_pago = '<div class="badge bg-light text-dark border shadow-sm p-2 w-100">
                            <i class="fas fa-layer-group me-1 text-primary"></i> Lote #' . $r['id_lote'] . '<br>
                            <small class="text-muted">' . $fecha_pago . '</small>
                          </div>';
        } else {
            $fecha_manual = date('d/m/Y', strtotime($r['fecha_pago']));
            $info_pago = '<div class="badge bg-warning-subtle text-warning border border-warning-subtle p-2 w-100">
                            <i class="fas fa-hand-holding-dollar me-1"></i> PAGO MANUAL<br>
                            <small>' . $fecha_manual . '</small>
                          </div>';
        }

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
    echo json_encode(["error" => $e->getMessage(), "data" => []]);
}