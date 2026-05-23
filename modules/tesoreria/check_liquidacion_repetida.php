<?php
// Eliminamos FPDF porque este archivo solo devuelve JSON, no genera PDFs
require('conexion.php');
require 'seguridad.php';

// Establecemos el header para que el navegador sepa que es JSON
header('Content-Type: application/json');

verificar_permisos(['Administrador', 'Secretaría']);

// Captura de datos con validación básica para evitar errores de índice
$id_p = $_POST['id_p'] ?? 0;
$mes  = $_POST['mes'] ?? 0;
$anio = $_POST['anio'] ?? 0;
$tipo = $_POST['tipo'] ?? 'Profesor';

// Consulta optimizada: solo necesitamos saber si hay una fila
$sql = "SELECT id_liquidacion FROM liquidaciones_haberes 
        WHERE id_persona = ? AND tipo_persona = ? 
        AND mes_liquidado = ? AND anio_liquidado = ? 
        AND estado != 'Anulado' LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id_p, $tipo, $mes, $anio]);
$existe = $stmt->fetch();

$meses = ["", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// Devolvemos la respuesta limpia
echo json_encode([
    'existe' => (bool)$existe,
    'mes_nombre' => $meses[(int)$mes] ?? 'Mes desconocido'
]);