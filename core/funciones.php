<?php
/**
 * ARCHIVO DE FUNCIONES GLOBALES - SISTEMA SINTEK GESTIÓN PREMIUM
 * core/funciones.php - Funciones de seguridad, utilidad y cálculo
 */

require_once 'conexion.php';
require_once 'seguridad.php';

// --- 1. SEGURIDAD Y IDENTIFICACIÓN (UUID) ---

/**
 * Genera un UUID v4 de forma segura (RFC 4122)
 * @return string
 */
function generar_uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Versión 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variante RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Registra un evento de seguridad con trazabilidad completa
 * @param PDO $pdo Instancia de conexión
 * @param string $evento Nombre del evento (ej: 'URL_MANIPULADA')
 * @param string $detalle Información descriptiva del suceso
 */
function registrar_log_seguridad($pdo, $evento, $detalle) {
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    // Captura la URL exacta que disparó el log para forense digital
    $url_peticion = $_SERVER['REQUEST_URI'] ?? 'N/A';

    $sql = "INSERT INTO logs_seguridad (id_usuario, evento, detalle_data, url_peticion, ip_origen, user_agent, fecha_hora) 
            VALUES (:user, :evento, :detalle, :url, :ip, :ua, NOW())";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user'    => $id_usuario,
            ':evento'  => $evento,
            ':detalle' => $detalle,
            ':url'     => $url_peticion,
            ':ip'      => $ip,
            ':ua'      => $user_agent
        ]);
    } catch (PDOException $e) {
        // En entorno de desarrollo puedes loguear el error del insert si falla
        error_log("Error grabando log_seguridad: " . $e->getMessage());
    }
}

/**
 * Registro unificado de auditoría para acciones administrativas autorizadas
 */
function registrar_auditoria($pdo, $id_user, $modulo, $accion, $motivo, $detalles) {
    $sql = "INSERT INTO logs_autorizaciones (id_usuario_autorizo, modulo, accion, motivo, detalles, fecha_hora) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    return $pdo->prepare($sql)->execute([$id_user, $modulo, $accion, $motivo, $detalles]);
}

// --- 2. MANTENIMIENTO Y PROCESOS AUTOMÁTICOS ---

/**
 * Cierra automáticamente las mesas de exámenes vencidas
 */
function realizar_mantenimiento_mesas($pdo) {
    if (isset($_SESSION['rol'])) {
        if (!isset($_SESSION['last_cleanup_mesas']) || $_SESSION['last_cleanup_mesas'] != date('Y-m-d')) {
            $sql_auto = "UPDATE mesas_examenes 
                        SET estado = 'Cerrada' 
                        WHERE estado = 'Abierta' 
                        AND fecha_fin_inscripcion < CURDATE()";
            $pdo->exec($sql_auto);
            $_SESSION['last_cleanup_mesas'] = date('Y-m-d');
        }
    }
}

// --- 3. FORMATOS Y COMPATIBILIDAD PHP 8 ---

/**
 * Limpieza de strings para reportes PDF (Compatible con PHP 8.2+)
 */
if (!function_exists('limpiar_cadena')) {
    function limpiar_cadena($texto) {
        return mb_convert_encoding($texto ?? '', 'ISO-8859-1', 'UTF-8');
    }
}

function money_fmt($monto) {
    return '$ ' . number_format((float)$monto, 2, ',', '.');
}

function clean_numeric($valor) {
    return preg_replace('/\D/', '', $valor ?? '');
}

// --- 4. MOTOR DE CÁLCULO DE HABERES ---

function calc_monto_antiguedad($basico, $fecha_ingreso, $porc_anual) {
    if (empty($fecha_ingreso)) return 0;
    $fecha_ing = new DateTime($fecha_ingreso);
    $hoy = new DateTime();
    $anios = $hoy->diff($fecha_ing)->y;
    return ($basico * ($porc_anual * $anios)) / 100;
}

function calcular_total_remunerativo($basico, $antiguedad, $zona, $presentismo) {
    return (float)$basico + (float)$antiguedad + (float)$zona + (float)$presentismo;
}

function calc_retenciones_ley($remunerativo, $p_jub, $p_os) {
    return ($remunerativo * ($p_jub + $p_os)) / 100;
}

// --- 5. VALIDACIONES BANCARIAS Y CONTROL ---

function generar_hash_control($contenido, $usuario) {
    // Requiere SECRET_SALT definido en config.php
    return hash('sha256', $contenido . $usuario . (defined('SECRET_SALT') ? SECRET_SALT : ''));
}

function is_cbu_valido($cbu) {
    $cbu_clean = clean_numeric($cbu);
    return (strlen($cbu_clean) === 22);
}

// Define una clave secreta única para tu sistema
define('URL_KEY', 'Sintek_Secret_2026'); 

function encriptar_url($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', URL_KEY, 0, $iv);
    // Limpiamos caracteres que rompen URLs
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted . '::' . $iv));
}

function desencriptar_url($data) {
    $data = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    if (!$data) return false;
    list($encrypted_data, $iv) = explode('::', $data, 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', URL_KEY, 0, $iv);
}

// --- 6. SANITIZACIÓN ---

/**
 * Escapa HTML de manera centralizada para evitar ataques XSS
 * @param string|null $string La cadena a escapar
 * @return string La cadena escapada
 */
function escape_html($string) {
    if (is_null($string)) {
        return '';
    }
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}