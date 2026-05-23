<?php
// 1. Ajuste de ruta de conexión y seguridad
require_once '../../core/conexion.php';

// pero mantenemos la limpieza del buffer para el JSON
if (ob_get_length()) ob_clean();

try {
    // 1. Configuración de Reglas de Tesorería
    $stmt_conf = $pdo->query("SELECT limite_meses_mora, cuotas_por_ciclo FROM configuracion_tesoreria WHERE id_config_teso = 1");
    $config = $stmt_conf->fetch(PDO::FETCH_ASSOC);
    $limite_permitido = $config['limite_meses_mora'] ?? 2;
    $total_cuotas_config = $config['cuotas_por_ciclo'] ?? 10;

    $mes_actual = date('n');
    $anio_actual = date('Y');
    // Ciclo lectivo estándar: Marzo (3) a Diciembre (12)
    $cuotas_esperadas = ($mes_actual >= 3) ? ($mes_actual - 2) : 0;

    // 2. Parámetros DataTables (Usamos $_GET o $_POST según tu config de JS)
    $limit  = isset($_GET['length']) ? (int)$_GET['length'] : 10;
    $start  = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $search = isset($_GET['search']['value']) ? $_GET['search']['value'] : '';

    // 3. Query Dinámica
    $tablas = " FROM alumnos a
                LEFT JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno
                LEFT JOIN carreras c ON ac.id_carreras = c.id_carrera";
    
    $where = " WHERE a.activo = '1'";
    $sql_params = [];

    if (!empty($search)) {
        $where .= " AND (a.dni LIKE ? OR a.apellido LIKE ? OR a.nombre LIKE ? OR c.nombre_carrera LIKE ?)";
        $term = '%' . $search . '%';
        $sql_params = [$term, $term, $term, $term];
    }

    // Conteo filtrado
    $sql_count = "SELECT COUNT(DISTINCT a.id_alumno) " . $tablas . $where;
    $stmt_c = $pdo->prepare($sql_count);
    $stmt_c->execute($sql_params);
    $totalFiltered = $stmt_c->fetchColumn();

    // Consulta de datos (Agregamos uuid_alumno)
    $sql = "SELECT a.id_alumno, a.uuid_alumno, a.nombre, a.apellido, a.dni, a.telefono, 
                   c.nombre_carrera, 
                   cp_ref.monto_sugerido as valor_cuota,
            (SELECT COUNT(*) 
             FROM factura_detalle fd 
             INNER JOIN facturas f ON fd.id_factura = f.id_factura
             INNER JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto
             WHERE f.id_alumno = a.id_alumno 
             AND cp.categoria = 'Mensualidad'
             AND f.estado = 'Pagado'
             AND fd.anio_lectivo = $anio_actual
            ) as cuotas_pagas,
            (SELECT CONCAT(f2.fecha_emision, '|', cp2.nombre_concepto)
             FROM factura_detalle fd2
             INNER JOIN facturas f2 ON fd2.id_factura = f2.id_factura
             INNER JOIN conceptos_pago cp2 ON fd2.id_concepto = cp2.id_concepto
             WHERE f2.id_alumno = a.id_alumno AND f2.estado = 'Pagado'
             ORDER BY f2.fecha_emision DESC 
             LIMIT 1
            ) as ultimo_movimiento
            " . $tablas . "
            LEFT JOIN conceptos_pago cp_ref ON cp_ref.id_carrera = c.id_carrera 
                 AND cp_ref.categoria = 'Mensualidad' AND cp_ref.activo = 1
            " . $where . " 
            GROUP BY a.id_alumno 
            ORDER BY a.apellido ASC 
            LIMIT $start, $limit";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($sql_params);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($alumnos as $row) {
        $uuid = $row['uuid_alumno']; // Usamos el UUID para las rutas
        $pagas = (int)$row['cuotas_pagas'];
        $deuda_meses = ($cuotas_esperadas > $pagas) ? ($cuotas_esperadas - $pagas) : 0;
        $en_mora = ($deuda_meses > $limite_permitido);
        
        $saldo_pesos = $deuda_meses * ($row['valor_cuota'] ?? 0);
        $tel_limpio = preg_replace('/[^0-9]/', '', $row['telefono'] ?? '');
        $nombre_completo = htmlspecialchars($row['apellido'] . ' ' . $row['nombre']);

        // --- LÓGICA DE WHATSAPP ---
        $mensaje_wa = "Hola, le informamos que el alumno *{$nombre_completo}* registra una deuda de *{$deuda_meses} meses* (Saldo: *$" . number_format($saldo_pesos, 2, ',', '.') . "*). Por favor, contacte a Tesorería para regularizar. Gracias.";
        $url_wa = "https://wa.me/54" . $tel_limpio . "?text=" . urlencode($mensaje_wa);

        // Procesar Último Movimiento
        $ult_pago_html = '<span class="text-muted small">Sin registros</span>';
        if ($row['ultimo_movimiento']) {
            list($fecha_mov, $concepto_mov) = explode('|', $row['ultimo_movimiento']);
            $ult_pago_html = "<div>" . date('d/m/Y', strtotime($fecha_mov)) . "</div>";
            $ult_pago_html .= "<span class='concepto-detalle'>" . mb_strtoupper($concepto_mov) . "</span>";
        }

        $data[] = [
            "alumno"       => "<strong>" . $nombre_completo . "</strong><br><small class='text-muted text-xs'>".htmlspecialchars($row['nombre_carrera'] ?? 'Sin Carrera')."</small>",
            "dni"          => '<span class="text-secondary">' . $row['dni'] . '</span>',
            "pagas"        => '<span class="badge bg-light text-dark border">' . $pagas . ' / ' . $total_cuotas_config . '</span>',
            "meses_deuda"  => '<span class="fw-bold ' . ($deuda_meses > 0 ? 'text-danger' : 'text-success') . '">' . $deuda_meses . ' meses</span>',
            "ultimo_pago"  => $ult_pago_html,
            "estado_badge" => $en_mora 
                                ? '<span class="status-pill bg-danger text-white">MOROSO</span>' 
                                : '<span class="status-pill bg-success text-white">AL DÍA</span>',
            "acciones"     => '<div class="btn-group shadow-sm">
                                <a href="'.BASE_URL.'tesoreria/cobrar/'.$uuid.'" class="btn btn-sm btn-primary" title="Cobrar">
                                    <i class="fas fa-cash-register"></i>
                                </a>
                                <a href="'.BASE_URL.'tesoreria/deuda/'.$uuid.'" target="_blank" class="btn btn-sm btn-outline-danger" title="Imprimir Deuda">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a href="'.BASE_URL.'alumnos/ficha-financiera/'.$uuid.'" class="btn btn-sm btn-info text-white" title="Ficha Financiera">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </a>
                                <a href="'.$url_wa.'" target="_blank" class="btn btn-sm btn-whatsapp" title="WhatsApp">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </div>',
            "mora_critica" => $en_mora
        ];
    }

    $totalData = $pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = '1'")->fetchColumn();

    header('Content-Type: application/json');
    echo json_encode([
        "draw"            => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
        "recordsTotal"    => intval($totalData),
        "recordsFiltered" => intval($totalFiltered),
        "data"            => $data
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Error interno", "debug" => $e->getMessage()]);
}