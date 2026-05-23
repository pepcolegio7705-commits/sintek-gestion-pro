<?php
require 'conexion.php';
require_once 'funciones_tesoreria.php';
$id = $_GET['id_alumno'] ?? null;
if ($id) {
    echo json_encode(validarInscripcion($id, $pdo));
} else {
    echo json_encode(['status' => false, 'msj' => 'ID no recibido']);
}