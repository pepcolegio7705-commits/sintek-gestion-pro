<?php
require_once __DIR__ . '/../../core/conexion.php'; 
header('Content-Type: application/json');

$meses = [1=>"Ene",2=>"Feb",3=>"Mar",4=>"Abr",5=>"May",6=>"Jun",7=>"Jul",8=>"Ago",9=>"Sep",10=>"Oct",11=>"Nov",12=>"Dic"];

$draw = $_POST['draw'] ?? 0;
$start = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 10);

try {
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM bonos_extraordinarios")->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM bonos_extraordinarios ORDER BY id_bono DESC LIMIT :start, :length");
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
    $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach($bonos as $b) {
        $data[] = [
            "descripcion" => "<strong>".$b['descripcion']."</strong>",
            "monto" => "$ ".number_format($b['monto'], 2, ',', '.'),
            "periodo" => $meses[$b['mes_periodo']]." / ".$b['anio_periodo'],
            "alcance" => '<span class="badge bg-info text-dark">'.$b['alcance_destino'].'</span>',
            "estado" => $b['estado'] == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Anulado</span>',
            "acciones" => '<div class="text-center">'.($b['estado'] == 1 ? '<button class="btn btn-sm btn-outline-danger btn-anular" data-uuid="'.$b['uuid_bono'].'"><i class="fas fa-times"></i></button>' : '---').'</div>'
        ];
    }

    echo json_encode(["draw" => intval($draw), "recordsTotal" => $totalRecords, "recordsFiltered" => $totalRecords, "data" => $data]);
} catch (Exception $e) { echo json_encode(["error" => $e->getMessage()]); }