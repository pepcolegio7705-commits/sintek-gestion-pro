<?php
    require 'conexion.php';
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("SELECT id_cat_gasto, nombre_categoria FROM gastos_categorias ORDER BY nombre_categoria ASC");
        $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($categorias);
    } catch (PDOException $e) {
        echo json_encode([]);
    }