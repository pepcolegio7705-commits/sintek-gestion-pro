<?php
require_once '../../core/conexion.php';
$data = $pdo->query("SELECT id_cat_gasto, nombre_categoria FROM gastos_categorias WHERE estado = 1 ORDER BY nombre_categoria ASC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);