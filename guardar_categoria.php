<?php
require 'conexion.php';

if (isset($_POST['nombre'])) {
    $nombre = trim($_POST['nombre']);
    
    $sql = "INSERT INTO gastos_categorias (nombre_categoria) VALUES (?)";
    $stmt = $pdo->prepare($sql);
    
    if ($stmt->execute([$nombre])) {
        echo "ok";
    } else {
        echo "error";
    }
}