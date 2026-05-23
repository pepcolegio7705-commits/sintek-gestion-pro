<?php
    // Configuración de la base de datos
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'asistencias'); // Reemplaza con el nombre de tu DB
    define('DB_USER', 'root');                // Reemplaza con tu usuario
    define('DB_PASS', '');                    // Reemplaza con tu contraseña

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        // Establece el modo de error de PDO para que lance excepciones
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Desactiva la emulación de sentencias preparadas (más seguro)
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    } catch (PDOException $e) {
        // Si hay un error de conexión, lo muestra y detiene el script
        exit("Error de conexión a la base de datos: " . $e->getMessage());
    }
?>