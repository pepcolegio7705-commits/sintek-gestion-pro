<?php
/**
 * CONTROLADOR DE CIERRE DE SESIÓN - SINTEK
 */

// 1. Cargamos el núcleo (esto ya trae config.php y seguridad.php)
require_once 'core/conexion.php';
require_once 'core/seguridad.php';

// 2. Ejecutamos la función centralizada de cierre
// Esta función hará: session_unset(), session_destroy() y redirigir al index
cerrar_sesion();