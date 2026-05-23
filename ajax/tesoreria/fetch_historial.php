<?php
// 1. Ajuste de ruta de conexión
require_once '../../core/conexion.php';

// Parámetros básicos de Datatables (usamos $_POST por consistencia con el JS anterior)
$draw = $_POST['draw'] ?? 1;
$row = $_POST['start'] ?? 0;
$rowperpage = $_POST['length'] ?? 10;
$searchValue = $_POST['search']['value'] ?? '';

// Captura de filtros personalizados
$f_desde    = $_POST['f_desde'] ?? '';
$f_hasta    = $_POST['f_hasta'] ?? '';
$f_concepto = $_POST['f_concepto'] ?? '';
$f_apellido = $_POST['f_apellido'] ?? '';

// 2. CONSTRUCCIÓN DEL WHERE DINÁMICO (Con Placeholders para seguridad)
$where = " WHERE 1 ";
$params = [];

if($searchValue != ''){
    $where .= " AND (a.dni LIKE :search OR a.apellido LIKE :search OR f.nro_factura LIKE :search OR f.nro_transaccion LIKE :search) ";
    $params[':search'] = "%$searchValue%";
}

if(!empty($f_desde) && !empty($f_hasta)){
    $where .= " AND f.fecha_emision BETWEEN :desde AND :hasta ";
    $params[':desde'] = "$f_desde 00:00:00";
    $params[':hasta'] = "$f_hasta 23:59:59";
}

if(!empty($f_concepto)){
    $where .= " AND EXISTS (SELECT 1 FROM factura_detalle fd_sub 
                            JOIN conceptos_pago cp_sub ON fd_sub.id_concepto = cp_sub.id_concepto 
                            WHERE fd_sub.id_factura = f.id_factura 
                            AND cp_sub.nombre_concepto LIKE :concepto) ";
    $params[':concepto'] = "%$f_concepto%";
}

if(!empty($f_apellido)){
    $where .= " AND a.apellido LIKE :apellido ";
    $params[':apellido'] = "%$f_apellido%";
}

// --- CÁLCULO PARA LAS TARJETAS DINÁMICAS ---
$sqlTotals = "SELECT 
                SUM(f.total) as bruto,
                SUM(CASE WHEN f.estado = 'Pagado' THEN f.total ELSE 0 END) as pagado,
                SUM(CASE WHEN f.estado = 'Anulado' THEN f.total ELSE 0 END) as anulado
              FROM facturas f 
              JOIN alumnos a ON f.id_alumno = a.id_alumno 
              $where";
$stmtTotals = $pdo->prepare($sqlTotals);
$stmtTotals->execute($params);
$totals = $stmtTotals->fetch(PDO::FETCH_ASSOC);

$card_bruto   = $totals['bruto'] ?? 0;
$card_pagado  = $totals['pagado'] ?? 0;
$card_anulado = $totals['anulado'] ?? 0;
$card_indice  = ($card_bruto > 0) ? ($card_pagado / $card_bruto) * 100 : 0;

// 3. TOTALES DE REGISTROS
$totalRecords = $pdo->query("SELECT COUNT(*) FROM facturas")->fetchColumn();
$sqlFilter = "SELECT COUNT(*) FROM facturas f JOIN alumnos a ON f.id_alumno = a.id_alumno $where";
$stmtCount = $pdo->prepare($sqlFilter);
$stmtCount->execute($params);
$totalRecordwithFilter = $stmtCount->fetchColumn();

// 4. CONSULTA PRINCIPAL (USANDO UUID PARA LOS BOTONES)
$sql = "SELECT f.uuid_factura, f.fecha_emision, f.nro_factura, f.total, f.estado,
               f.motivo_anulacion, f.fecha_anulacion, f.usuario_anulo, f.nro_transaccion,
               a.dni, CONCAT(a.apellido, ' ', a.nombre) as alumno,
               mp.nombre_modo,
               (SELECT GROUP_CONCAT(cp.nombre_concepto SEPARATOR ', ') 
                FROM factura_detalle fd 
                JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto 
                WHERE fd.id_factura = f.id_factura) as concepto,
               (SELECT GROUP_CONCAT(DISTINCT c.nombre_carrera SEPARATOR ', ') 
                FROM factura_detalle fd 
                JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto 
                LEFT JOIN carreras c ON cp.id_carrera = c.id_carrera
                WHERE fd.id_factura = f.id_factura) as nombre_carrera
        FROM facturas f
        JOIN alumnos a ON f.id_alumno = a.id_alumno
        JOIN modos_pago mp ON f.id_modo_pago = mp.id_modo
        $where
        ORDER BY f.fecha_emision DESC LIMIT $row, $rowperpage";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = [];

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $uuid = $r['uuid_factura'];
    
   if ($r['estado'] === 'Anulado') {
        $motivo = htmlspecialchars($r['motivo_anulacion'] ?? 'No especificado');
        $fecha_anul = !empty($r['fecha_anulacion']) ? date('d/m/Y H:i', strtotime($r['fecha_anulacion'])) : 'Sin fecha';
        $quien_anulo = htmlspecialchars($r['usuario_anulo'] ?? 'Sistema');
        
        $estado_html = '<span class="badge bg-danger">Anulado</span> 
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-ver-motivo" 
                        data-motivo="'.$motivo.'" data-fecha="'.$fecha_anul.'" data-usuario="'.$quien_anulo.'">
                        <i class="fas fa-info-circle"></i></button>';
        $btn_anular = ''; 
        
        // Si está ANULADO, la impresión siempre debe llevar marca de agua
        $url_print = BASE_URL . "tesoreria/imprimir/" . $uuid . "?tipo=reimpresion";
    } else {
        $estado_html = '<span class="badge bg-success">Pagado</span>';
        
        $btn_anular = '<button class="btn btn-sm btn-outline-danger ms-1" onclick="confirmarAnulacion(\''.$uuid.'\', \''.$r['nro_factura'].'\')" title="Anular"><i class="fas fa-ban"></i></button>';
        
        // Si es desde el HISTORIAL (reimpresión), forzamos la marca de agua con el parámetro ?tipo=reimpresion
        $url_print = BASE_URL . "tesoreria/imprimir/" . $uuid . "?tipo=reimpresion";
    }

    $btn_reimprimir = '<a href="'.$url_print.'" target="_blank" class="btn btn-sm btn-outline-primary" title="Reimprimir"><i class="fas fa-print"></i></a>';

    $data[] = [
        "fecha_emision" => date('d/m/Y H:i', strtotime($r['fecha_emision'])),
        "nro_factura"   => "<b>" . $r['nro_factura'] . "</b>",
        "dni"           => $r['dni'],
        "alumno"        => $r['alumno'],
        "nombre_carrera" => $r['nombre_carrera'] ?? '<span class="text-muted small">Institucional</span>',
        "concepto"      => $r['concepto'],
        "total"         => "$ " . number_format($r['total'], 2, ',', '.'),
        "modo_pago"     => $r['nombre_modo'], // Coincidencia con el JS del frontend
        "nro_transaccion" => $r['nro_transaccion'],
        "estado"        => $estado_html,
        "acciones"      => '<div class="btn-group">' . $btn_reimprimir . $btn_anular . '</div>'
    ];
}

echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalRecordwithFilter),
    "data" => $data, // Datatables moderno espera "data", no "aaData"
    "totales" => [
        "bruto" => number_format($card_bruto, 2, ',', '.'),
        "pagado" => number_format($card_pagado, 2, ',', '.'),
        "anulado" => number_format($card_anulado, 2, ',', '.'),
        "indice" => number_format($card_indice, 1),
        "porcentaje_raw" => $card_indice
    ]
]);