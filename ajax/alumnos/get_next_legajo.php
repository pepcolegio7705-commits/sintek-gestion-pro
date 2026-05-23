<?php
/**
 * SUGERIDOR DE PRÓXIMO LEGAJO - SINTEK
 * Ubicación: /ajax/alumnos/get_next_legajo.php
 */

// 1. Subimos dos niveles para llegar al core
require_once '../../core/conexion.php';

// 2. Opcional: Validar que hay una sesión activa para evitar consultas externas
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['loggedin'])) {
    http_response_code(403);
    exit("Acceso denegado");
}

// 3. Tu lógica original con PDO
try {
    // Buscamos el valor máximo numérico del legajo
    $stmt = $pdo->query("SELECT MAX(CAST(legajo AS UNSIGNED)) as max_legajo FROM alumnos");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextLegajo = ($resultado['max_legajo'] ?? 0) + 1;
    
    // Devolvemos el número para el success del $.get en el JS
    echo $nextLegajo;

} catch (PDOException $e) {
    // En caso de error de DB no devolvemos nada para no ensuciar el input
    exit();
}