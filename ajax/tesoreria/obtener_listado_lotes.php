<?php
/**
 * SERVERSIDE: OBTENER LISTADO DE LOTES
 */
session_start();
require_once '../../core/conexion.php';

header('Content-Type: application/json');

try {
    $draw   = intval($_POST['draw'] ?? 1);
    $start  = intval($_POST['start'] ?? 0);
    $length = intval($_POST['length'] ?? 10);
    $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    $mes_filtro  = intval($_POST['mes'] ?? 0);
    $anio_filtro = intval($_POST['anio'] ?? 0);

    $meses = [1=>"Enero", 2=>"Febrero", 3=>"Marzo", 4=>"Abril", 5=>"Mayo", 6=>"Junio", 7=>"Julio", 8=>"Agosto", 9=>"Septiembre", 10=>"Octubre", 11=>"Noviembre", 12=>"Diciembre"];

    $where = " WHERE 1=1";
    
    if ($mes_filtro > 0) $where .= " AND mes_periodo = $mes_filtro";
    if ($anio_filtro > 0) $where .= " AND anio_periodo = $anio_filtro";
    
    if ($search !== '') {
        $s = preg_replace("/[^a-zA-Z0-9 ]/", "", $search);
        $where .= " AND (estado LIKE '%$s%' OR tipo_lote LIKE '%$s%' OR CAST(id_lote AS CHAR) LIKE '%$s%')";
    }

    $totalRecords = $pdo->query("SELECT COUNT(*) FROM lotes_liquidaciones")->fetchColumn();
    $recordsFiltered = $pdo->query("SELECT COUNT(*) FROM lotes_liquidaciones $where")->fetchColumn();

    // Traemos la columna descargado_txt que es vital para el JS
    $sql = "SELECT * FROM lotes_liquidaciones $where ORDER BY id_lote DESC LIMIT $start, $length";

    $stmt = $pdo->query($sql);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dataset = [];
    foreach ($lotes as $l) {
        
        // 1. Lógica de Badges (Incluyendo Rechazo Banco)
        if ($l['estado'] == 'Pendiente') {
            $badge = '<span class="badge badge-pendiente border px-3 py-2">PENDIENTE</span>';
        } elseif ($l['estado'] == 'Procesado') {
            $badge = '<span class="badge badge-procesado border px-3 py-2">PROCESADO</span>';
        } elseif ($l['estado'] == 'Rechazo Banco') {
            $badge = '<span class="badge bg-warning text-dark border px-3 py-2">RECHAZO BANCO</span>';
        } else {
            $badge = '<span class="badge badge-anulado border px-3 py-2">ANULADO</span>';
        }

        // 2. Botones de acción base
        // Solo mandamos los botones que NO requieren la validación de Step-up Auth aquí
        $btns = '<div class="btn-group shadow-sm">';
        
        if($l['estado'] == 'Pendiente') {
            $btns .= '<button class="btn btn-sm btn-success" onclick="prepararAccion('.$l['id_lote'].', \'confirmar\')" title="Confirmar Pago"><i class="fas fa-check"></i></button>';
            $btns .= '<button class="btn btn-sm btn-danger" onclick="prepararAccion('.$l['id_lote'].', \'anular\')" title="Anular Lote"><i class="fas fa-times-circle"></i></button>';
        } else {
            // Para lotes cerrados, solo un botón de "Ver Detalle" o similar
            $btns .= '<button class="btn btn-sm btn-light border" onclick="verDetalleLote('.$l['id_lote'].')" title="Ver Historial"><i class="fas fa-eye"></i></button>';
        }
        $btns .= '</div>';

        $dataset[] = [
            "id_lote"        => $l['id_lote'],
            "periodo"        => ($meses[$l['mes_periodo']] ?? 'N/A') . " " . $l['anio_periodo'],
            "nombre_area"    => $l['nombre_area_lote'] ?? 'Institucional',
            "tipo"           => $l['tipo_lote'],
            "agentes"        => $l['cantidad_agentes'],
            "total"          => '$ ' . number_format($l['total_monto'], 2, ',', '.'),
            "estado"         => $l['estado'], // Mandamos el TEXTO plano para que el JS lo use en el IF
            "estado_badge"   => $badge,      // Mandamos el HTML para mostrar en la tabla
            "descargado_txt" => $l['descargado_txt'], // VITAL para el JS
            "acciones"       => $btns
        ];
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => (int)$totalRecords,
        "recordsFiltered" => (int)$recordsFiltered,
        "data" => $dataset
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}