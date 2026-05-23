<?php
/**
 * Funciones de Seguridad Base
 */

// Simulación de lectura de .env para esta clase
$env = parse_ini_file(__DIR__ . '/../.env');
define('HASH_SALT', $env['HASH_SALT'] ?? 'default_salt_change_me');

/**
 * Escapar variables para imprimir en HTML (Prevención XSS)
 */
function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Generar Token CSRF
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar Token CSRF
 */
function verify_csrf_token($token) {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    return false;
}

/**
 * Encriptar ID (Ejemplo nativo, idealmente reemplazar por Hashids)
 */
function encrypt_id($id) {
    // Usamos openssl_encrypt como base para ofuscar (No es Hashids pero sirve como PoC)
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($id, 'aes-256-cbc', HASH_SALT, 0, $iv);
    // Retornamos el IV pegado al hash para poder desencriptar, codificado en base64 y adaptado para URL
    return rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');
}

/**
 * Desencriptar ID
 */
function decrypt_id($encrypted_string) {
    $data = base64_decode(strtr($encrypted_string, '-_', '+/'));
    $iv_size = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_size);
    $encrypted_id = substr($data, $iv_size);
    
    $decrypted = openssl_decrypt($encrypted_id, 'aes-256-cbc', HASH_SALT, 0, $iv);
    return is_numeric($decrypted) ? (int) $decrypted : null;
}
