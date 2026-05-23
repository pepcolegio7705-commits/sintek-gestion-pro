<?php
    /**
     * NÚCLEO DE SEGURIDAD Y CONTROL DE ACCESO - SINTEK
     */

    /**
     * Verifica si el usuario tiene una sesión activa y si posee los roles permitidos.
     * * @param array $roles_permitidos Lista de strings con los roles que pueden ver la página.
     */
    function verificar_permisos($roles_permitidos = []) {
        // 1. Verificar si existe la sesión
        if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol'])) {
            // Si no está logueado, lo mandamos al index (login)
            // Usamos BASE_URL definida en config.php para asegurar la ruta
            header("Location: " . BASE_URL . "index");
            exit;
        }

        // 2. Si se especificaron roles, verificar si el usuario tiene uno válido
        if (!empty($roles_permitidos)) {
            if (!in_array($_SESSION['rol'], $roles_permitidos)) {
                // Si el rol no está en la lista permitida, denegamos el acceso
                // Podrías redirigir a una página de "Acceso Denegado"
                header("Location: " . BASE_URL . "dashboard?error=acceso_denegado");
                exit;
            }
        }

        // 3. Opcional: Renovación de tiempo de sesión
        // Podrías implementar aquí un chequeo de inactividad
    }

    /**
     * Función para cerrar sesión de forma segura
     */
    function cerrar_sesion() {
        session_unset();
        session_destroy();
        
        // Quitamos el .php para que el .htaccess maneje la URL limpia
        header("Location: " . BASE_URL . "login"); 
        exit;
    }