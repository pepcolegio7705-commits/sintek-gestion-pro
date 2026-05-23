<?php
/**
 * PROCESADOR AJAX: GUARDAR ASIGNACIÓN (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/profesores/guardar_asignacion.php
 */

session_start();
require_once '../../core/conexion.php';
require_once '../../core/funciones.php';
require_once '../../core/seguridad.php';

// 1. VALIDACIÓN DE PERMISOS
verificar_permisos(['Administrador', 'Secretaría']);

if (ob_get_length()) ob_clean();
header('Content-Type: application/json');

// 2. CAPTURA DEL UUID (Desde la URL amigable vía .htaccess)
$uuid = $_GET['uuid'] ?? null;

// Paracaídas por si el .htaccess no inyectó el parámetro en local
if (!$uuid) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $uuid = end($segments);
}

// 3. CAPTURA DE DATOS DEL POST
$id_espacio   = isset($_POST['id_espacio']) ? (int)$_POST['id_espacio'] : 0;
$horas        = isset($_POST['horas_catedra']) ? (int)$_POST['horas_catedra'] : 0;
$action       = $_POST['action'] ?? '';

// VALIDACIÓN INICIAL
if (!$uuid || $id_espacio === 0 || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para procesar la asignación.']);
    exit;
}

try {
    // 4. EL PUENTE: Traducir UUID a ID real
    $stmt = $pdo->prepare("SELECT id_profesor FROM profesores WHERE uuid_profesor = ? LIMIT 1");
    $stmt->execute([$uuid]);
    $profesor = $stmt->fetch();

    if (!$profesor) {
        echo json_encode(['success' => false, 'message' => 'El profesor no existe o el identificador es inválido.']);
        exit;
    }

    $id_profesor_real = $profesor['id_profesor'];

    // 5. OPERACIONES DE BASE DE DATOS
    if ($action === 'asignar') {
        // Usamos INSERT ... ON DUPLICATE KEY UPDATE para manejar cambios de horas o nuevas asignaciones
        $sql = "INSERT INTO profesores_espacios (id_profesor, id_espacio, horas_catedra) 
                VALUES (:id_p, :id_e, :horas) 
                ON DUPLICATE KEY UPDATE horas_catedra = :horas_upd";
        
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([
            ':id_p'      => $id_profesor_real,
            ':id_e'      => $id_espacio,
            ':horas'     => $horas,
            ':horas_upd' => $horas
        ]);

        $msg = "Espacio asignado/actualizado correctamente.";

    } elseif ($action === 'desasignar') {
        // Eliminar la relación
        $sql = "DELETE FROM profesores_espacios WHERE id_profesor = :id_p AND id_espacio = :id_e";
        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute([
            ':id_p' => $id_profesor_real,
            ':id_e' => $id_espacio
        ]);

        $msg = "Espacio removido correctamente.";
    }

    // 6. RESPUESTA FINAL
    if ($res) {
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al ejecutar la operación en la base de datos.']);
    }

} catch (PDOException $e) {
    error_log("Error en guardar_asignacion: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
exit;