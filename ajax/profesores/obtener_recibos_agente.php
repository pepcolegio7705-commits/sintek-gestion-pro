<?php
/**
 * PROCESADOR AJAX PARA DATA TABLES: HISTORIAL DE RECIBOS (VERSIÓN FINAL MAPEO EXACTO)
 * Ubicación: /ajax/profesores/obtener_recibos_agente.php
 */
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Validamos permisos
verificar_permisos(['Administrador', 'Secretaría', 'Tesoreria']);

// 1. CAPTURA DEL UUID (GET) y PARÁMETROS DT (POST)
$uuid = $_GET['uuid'] ?? '';
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$uuid));

$draw   = intval($_POST['draw'] ?? 1);
$start  = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

if (empty($uuid)) {
    echo json_encode(["draw" => $draw, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

try {
    // 2. IDENTIFICAR AL AGENTE (Profesor o Staff)
    $stmt = $pdo->prepare("SELECT id_profesor as id_int, 'Profesor' as tipo FROM profesores WHERE uuid_profesor = ? LIMIT 1");
    $stmt->execute([$uuid]);
    $agente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agente) {
        $stmt = $pdo->prepare("SELECT id_staff as id_int, 'Staff' as tipo FROM personal_staff WHERE uuid_staff = ? LIMIT 1");
        $stmt->execute([$uuid]);
        $agente = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$agente) throw new Exception("Agente no identificado.");

    $id_persona = $agente['id_int'];
    $tipo_persona = $agente['tipo'];

    // 3. CONSULTA DE LIQUIDACIONES
    $query_base = "FROM liquidaciones_haberes WHERE id_persona = :id AND tipo_persona = :tipo";
    $params = [':id' => $id_persona, ':tipo' => $tipo_persona];

    // Conteo para paginación
    $stmt_count = $pdo->prepare("SELECT COUNT(*) $query_base");
    $stmt_count->execute($params);
    $recordsTotal = $stmt_count->fetchColumn();

    // Obtención de datos paginados
    $sql_data = "SELECT * $query_base ORDER BY anio_liquidado DESC, mes_liquidado DESC LIMIT $start, $length";
    $stmt_data = $pdo->prepare($sql_data);
    $stmt_data->execute($params);
    $resultados = $stmt_data->fetchAll(PDO::FETCH_ASSOC);

    // 4. CÁLCULO DE ESTADÍSTICAS (KPIs) - Año actual
    $anio_actual = date('Y');
    $stmt_stats = $pdo->prepare("SELECT 
                                    SUM(monto_neto) as total, 
                                    AVG(monto_neto) as promedio, 
                                    COUNT(*) as cantidad 
                                 FROM liquidaciones_haberes 
                                 WHERE id_persona = ? AND tipo_persona = ? AND anio_liquidado = ?");
    $stmt_stats->execute([$id_persona, $tipo_persona, $anio_actual]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    // 5. FORMATEO PARA DATATABLES
    $data = [];
    foreach ($resultados as $row) {
        // Mapeo según tus columnas reales
        $periodo = $row['mes_liquidado'] . "/" . $row['anio_liquidado'];
        $lote    = $row['id_lote'] ?? 'N/A';
        $uuid_liq = $row['uuid_liquidacion'] ?? ''; // Usamos uuid_liquidacion
        $f_pago  = isset($row['fecha_pago']) ? date('d/m/Y', strtotime($row['fecha_pago'])) : 'Pendiente';
        
        // El neto es monto_neto
        $neto = (float)$row['monto_neto'];
        $bruto = (float)$row['monto_bruto'];
        $retenciones = (float)$row['monto_retenciones'];

        $data[] = [
            "periodo"    => '<span class="fw-bold">'.$periodo.'</span>',
            "bruto"      => '$ ' . number_format($bruto, 2, ',', '.'),
            "descuentos" => '<span class="text-danger">$ ' . number_format($retenciones, 2, ',', '.') . '</span>',
            "neto"       => '<strong class="text-success">$ ' . number_format($neto, 2, ',', '.') . '</strong>',
            "info_pago"  => 'Lote: ' . $lote . ' <br><small class="text-muted">' . $f_pago . '</small>',
            
            // MODIFICACIÓN AQUÍ: Agregamos ?r=1 al final de la URL
            "acciones"   => !empty($uuid_liq) ? 
                            '<a href="'.BASE_URL.'recibos/ver/'.$uuid_liq.'?r=1" target="_blank" class="btn btn-sm btn-outline-danger shadow-sm"><i class="fas fa-print me-1"></i> Reimprimir</a>' : 
                            '<span class="text-muted small">N/A</span>'
        ];
    }

    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsTotal,
        "data"            => $data,
        "stats"           => [
            "total"    => "$ " . number_format($stats['total'] ?? 0, 2, ',', '.'),
            "promedio" => "$ " . number_format($stats['promedio'] ?? 0, 2, ',', '.'),
            "cantidad" => $stats['cantidad'] ?? 0
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}