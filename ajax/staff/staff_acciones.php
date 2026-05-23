<?php
/**
 * ACCIONES DE STAFF: ALTA/BAJA (VERSIÓN BLINDADA UUID)
 * Ubicación: /ajax/staff/staff_acciones.php
 */
session_start();

// Subimos dos niveles para llegar a core desde ajax/staff/
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

// Evitamos cualquier salida accidental antes del echo
if (ob_get_length()) ob_clean();

verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== TRUE) {
    echo "Sesión expirada";
    exit;
}

// 1. CAPTURA DEL UUID (Desde la URL amigable vía .htaccess)
$uuid = $_GET['uuid'] ?? $_POST['uuid'] ?? null;

// Paracaídas para lectura manual si el .htaccess no inyecta el parámetro
if (!$uuid) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $segments = explode('/', trim($path, '/'));
    $uuid = end($segments);
}

// Limpieza de seguridad
$uuid = preg_replace('/[^a-z0-9-]/', '', strtolower($uuid));
$nuevo_estado = isset($_POST['estado']) ? (int)$_POST['estado'] : null;

if (!empty($uuid) && $nuevo_estado !== null) {
    $id_area = (int)($_POST['area'] ?? 0);
    $dni = $_POST['dni'] ?? '';

    try {
        $pdo->beginTransaction();

        // 2. EL PUENTE: Obtenemos el ID real a partir del UUID para las consultas internas
        $stmt_check = $pdo->prepare("SELECT id_staff, id_usuario FROM personal_staff WHERE uuid_staff = ? LIMIT 1");
        $stmt_check->execute([$uuid]);
        $agente = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if (!$agente) {
            throw new Exception("Agente no encontrado con el identificador proporcionado.");
        }

        $id_staff_real = $agente['id_staff'];
        $id_usuario_vinculado = $agente['id_usuario'];

        // 3. LÓGICA DE BAJA PARA ADMINISTRATIVOS (Eliminar acceso al sistema)
        if ($nuevo_estado == 0 && $id_area == 2) {
            // Eliminamos el usuario si existe (usando el DNI como nombre_usuario según tu lógica)
            if (!empty($dni)) {
                $sql_del = "DELETE FROM usuarios WHERE nombre_usuario = ?";
                $stmt_del = $pdo->prepare($sql_del);
                $stmt_del->execute([$dni]);
            }
            
            // Desvinculamos el id_usuario en la tabla staff
            $sql_null = "UPDATE personal_staff SET id_usuario = NULL WHERE id_staff = ?";
            $pdo->prepare($sql_null)->execute([$id_staff_real]);
        }

        // 4. ACTUALIZACIÓN DEL ESTADO (Usando el UUID como clave de seguridad en el WHERE)
        $sql_staff = "UPDATE personal_staff SET activo = ? WHERE uuid_staff = ?";
        $pdo->prepare($sql_staff)->execute([$nuevo_estado, $uuid]);

        $pdo->commit();
        echo "ok";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Datos insuficientes o identificador no válido.";
}