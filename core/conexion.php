<?php
    /**
     * NÚCLEO DE CONEXIÓN Y CONFIGURACIÓN DINÁMICA - SINTEK GESTIÓN PREMIUM
     */

    // 1. Cargamos la configuración global (Rutas y Credenciales)
    require_once __DIR__ . '/config.php';

    try {
        // 2. Establecemos la conexión PDO usando las constantes de config.php
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        
        // Configuración de seguridad y rendimiento para PDO
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // 3. Cargamos la configuración de la institución desde la base de datos
        $query_conf = $pdo->query("SELECT * FROM configuracion LIMIT 1");
        $conf_db = $query_conf->fetch();

        if ($conf_db) {
            define('NOM_INST',    $conf_db['nombre_institucion']);
            define('DIR_INST',    $conf_db['domicilio']);
            define('TEL_INST',    $conf_db['telefono']);
            define('MAIL_INST',   $conf_db['email_contacto']);
            define('LOGO_INST',   $conf_db['logo_path']);
            
            // Autoridades y Ciclo Lectivo
            define('RECTOR_INST', $conf_db['rector'] ?? 'No asignado');
            define('VICE_INST',   $conf_db['vicedirector'] ?? 'No asignado');
            define('SEC_INST',    $conf_db['secretario'] ?? 'No asignado');
            define('CICLO_INST',  $conf_db['ciclo_lectivo_actual'] ?? date('Y'));
        } else {
            // Valores por defecto de emergencia
            define('NOM_INST',    'Sintek Gestión Premium');
            define('LOGO_INST',   'assets/img/default_logo.png');
            define('RECTOR_INST', 'No asignado');
            define('VICE_INST',   'No asignado');
            define('SEC_INST',    'No asignado');
            define('CICLO_INST',  date('Y'));
        }

        // Datos de Autoría (Fijos por sistema)
        //define('AUTOR_SISTEMA', 'Sintek - Soluciones Tecnológicas');
        //define('WEB_AUTOR', 'https://sintek.com.ar');

        // 4. Aseguramos el inicio de sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

    } catch (PDOException $e) {
        // Registro de error silencioso para el servidor
        error_log("Error de conexión PDO: " . $e->getMessage());
        exit("Error crítico: El servicio de datos no está disponible actualmente.");
    }