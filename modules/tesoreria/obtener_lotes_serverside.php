<?php
require 'conexion.php';

$draw = $_POST['draw'] ?? 1;
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);
$meses = [1=>"Ene",2=>"Feb",3=>"Mar",4=>"Abr",5=>"May",6=>"Jun",7=>"Jul",8=>"Ago",9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dic"];

// Conteo total
$total = $pdo->query("SELECT COUNT(*) FROM lotes_liquidaciones")->fetchColumn();

// Consulta de datos con tus columnas exactas
$sql = "SELECT * FROM lotes_liquidaciones ORDER BY id_lote DESC LIMIT $start, $length";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach($lotes as $l) {
    $btn = "";
    $badge = "";
    
    // 1. Botón de "Ver Detalle" (Siempre visible)
    $btn_ver = '<button class="btn btn-sm btn-outline-dark btn-ver-detalle" 
                        data-id="'.$l['id_lote'].'" 
                        data-motivo="'.htmlspecialchars($l['motivo_rechazo'] ?? '').'" 
                        title="Ver Agentes">
                    <i class="fas fa-eye"></i>
                </button>';

    // 2. Lógica de estados y botones de acción
    if ($l['estado'] == 'Pendiente') {
        $badge = '<span class="badge bg-warning text-dark">Pendiente</span>';
        $btn = '<button class="btn btn-sm btn-success btn-autorizar" data-id="'.$l['id_lote'].'" data-total="'.number_format($l['monto_total_neto'],2,',','.').'" title="Autorizar y Generar TXT"><i class="fas fa-check"></i></button> 
                <button class="btn btn-sm btn-danger btn-rechazar" data-id="'.$l['id_lote'].'" title="Rechazo Interno"><i class="fas fa-ban"></i></button>';
    
    } elseif ($l['estado'] == 'Procesado') {
        $badge = '<span class="badge bg-success">Procesado</span>';
        // Botón de descarga original + Botón de Anulación (Rechazo Bancario)
        $btn = '<button class="btn btn-sm btn-primary btn-descargar" data-id="'.$l['id_lote'].'" title="Descargar TXT"><i class="fas fa-file-download"></i></button> 
                <button class="btn btn-sm btn-outline-danger btn-anular" data-id="'.$l['id_lote'].'" title="Informar Rechazo Bancario (Anular Lote)"><i class="fas fa-undo"></i></button>';
    
    } elseif ($l['estado'] == 'Rechazado') {
        $badge = '<span class="badge bg-danger" title="'.htmlspecialchars($l['motivo_rechazo'] ?? '').'">Rechazado</span>';
        $btn = ''; 

    } elseif ($l['estado'] == 'Anulado') {
        $badge = '<span class="badge bg-dark" title="'.htmlspecialchars($l['motivo_rechazo'] ?? '').'">Anulado (Banco)</span>';
        $btn = ''; // Lote revertido, no hay más acciones.

    } else {
        $badge = '<span class="badge bg-secondary">'.$l['estado'].'</span>';
    }

    $data[] = [
        "id_lote" => $l['id_lote'],
        "fecha_procesamiento" => date('d/m/Y H:i', strtotime($l['fecha_procesamiento'])),
        "periodo" => $meses[$l['mes_liquidado']] . " / " . $l['anio_liquidado'],
        "tipo_personal" => "<strong>".$l['tipo_personal']."</strong>",
        "cantidad_agentes" => $l['cantidad_agentes'],
        "monto_total_neto" => '<strong>$ ' . number_format($l['monto_total_neto'], 2, ',', '.') . '</strong>',
        "estado" => $badge,
        "acciones" => '<div class="btn-group">' . $btn_ver . $btn . '</div>'
    ];
}

header('Content-Type: application/json');
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($total),
    "recordsFiltered" => intval($total),
    "data" => $data
]);