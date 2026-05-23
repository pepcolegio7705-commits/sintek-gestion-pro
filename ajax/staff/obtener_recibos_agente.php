<?php
session_start();

// Subimos dos niveles para llegar a la raíz y entrar a core
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

// Verificación de seguridad
verificar_permisos(['Administrador', 'Tesoreria', 'Secretaría']);

// Capturamos los datos del POST de DataTable
$id_agente = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tipo      = $_POST['tipo'] ?? '';

if ($id_agente === 0 || empty($tipo)) {
    echo json_encode(["draw" => 0, "recordsTotal" => 0, "recordsFiltered" => 0, "data" => []]);
    exit;
}

$id = $_POST['id'];
$tipo = $_POST['tipo'];
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);

// 1. Estadísticas (KPIs)
$stmtStats = $pdo->prepare("
    SELECT SUM(monto_neto) as total, AVG(monto_neto) as promedio, COUNT(*) as cantidad 
    FROM liquidaciones_haberes 
    WHERE id_persona = ? AND tipo_persona = ? AND estado = 'Pagado' AND anio_liquidado = YEAR(CURDATE())
");
$stmtStats->execute([$id, $tipo]);
$statsData = $stmtStats->fetch(PDO::FETCH_ASSOC);

$stats = [
    "total" => "$ " . number_format($statsData['total'] ?? 0, 2, ',', '.'),
    "promedio" => "$ " . number_format($statsData['promedio'] ?? 0, 2, ',', '.'),
    "cantidad" => $statsData['cantidad'] ?? 0
];

// 2. Datos de la tabla - Corregido el ON del JOIN
$sql = "SELECT lh.*, ll.fecha_autorizacion, mp.nombre_modo
        FROM liquidaciones_haberes lh
        LEFT JOIN lotes_liquidaciones ll ON lh.id_lote = ll.id_lote
        LEFT JOIN modos_pago mp ON lh.id_modo_pago = mp.id_modo
        WHERE lh.id_persona = ? AND lh.tipo_persona = ? AND lh.estado = 'Pagado'
        ORDER BY lh.anio_liquidado DESC, lh.mes_liquidado DESC
        LIMIT $start, $length";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id, $tipo]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
$meses = [1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"];

foreach ($items as $i) {
    // Determinamos el detalle del modo de pago
    $metodo = $i['nombre_modo'] ?? 'No especificado';
    if ($i['id_modo_pago'] == 2 && !empty($i['nro_cheque'])) {
        $metodo .= " N° " . $i['nro_cheque'];
    }

    if (!empty($i['id_lote'])) {
        $etiqueta = "<span class='badge bg-info text-dark'>Lote #".$i['id_lote']."</span>";
        $fecha_pago = !empty($i['fecha_autorizacion']) ? date('d/m/Y', strtotime($i['fecha_autorizacion'])) : date('d/m/Y', strtotime($i['fecha_pago']));
    } else {
        $etiqueta = "<span class='badge bg-warning text-dark'>Individual</span>";
        $fecha_pago = date('d/m/Y', strtotime($i['fecha_pago']));
    }

    $data[] = [
        "periodo" => "<b>".$meses[$i['mes_liquidado']]."</b> ".$i['anio_liquidado'],
        "bruto" => "$ ".number_format($i['monto_bruto'], 2, ',', '.'),
        "descuentos" => "<span class='text-danger'>$ ".number_format($i['monto_retenciones'], 2, ',', '.')."</span>",
        "neto" => "<b class='text-success'>$ ".number_format($i['monto_neto'], 2, ',', '.')."</b>",
        "info_pago" => "<small>".$etiqueta."<br>".$metodo."<br>Pagado: ".$fecha_pago."</small>",
        "acciones" => "<a href='imprimir_recibo.php?id=".$i['id_liquidacion']."' target='_blank' class='btn btn-sm btn-outline-danger'><i class='fas fa-file-pdf'></i> Recibo</a>"
    ];
}

echo json_encode([
    "draw" => intval($_POST['draw'] ?? 1),
    "recordsTotal" => $stats['cantidad'],
    "recordsFiltered" => $stats['cantidad'],
    "data" => $data,
    "stats" => $stats
]);