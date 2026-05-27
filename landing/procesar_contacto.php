<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = strip_tags(trim($_POST["nombre"]));
    $email = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $institucion = strip_tags(trim($_POST["institucion"]));
    $mensaje = trim($_POST["mensaje"]);
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Validar campos vacíos
    if (empty($nombre) || empty($mensaje) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: index.html?status=error");
        exit;
    }

    // VALIDACIÓN DE GOOGLE reCAPTCHA v2
    $recaptcha_secret = 'TU_CLAVE_SECRETA_AQUI'; // <-- REEMPLAZAR CON TU CLAVE SECRETA DE GOOGLE
    
    // Solo validamos si no está en localhost para permitirte probar localmente. 
    // Si estás en producción, quita esta condición del localhost.
    if($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        $verify_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);
        $response_data = json_decode($verify_response);
        
        if (!$response_data->success) {
            // El captcha falló (es un bot o no se resolvió)
            header("Location: index.html?status=captcha_error");
            exit;
        }
    }

    $destinatario = "sintekgestion@gmail.com";
    $asunto = "Nuevo contacto desde Landing Page: $nombre";

    $contenido = "Has recibido un nuevo mensaje desde la Landing Page de Sintek Gestión Pro.\n\n";
    $contenido .= "Nombre: $nombre\n";
    $contenido .= "Email: $email\n";
    $contenido .= "Institución: $institucion\n\n";
    $contenido .= "Mensaje:\n$mensaje\n";

    $headers = "From: $nombre <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";

    // Intentar enviar correo (requiere SMTP configurado en el servidor WAMP o Hosting)
    if (mail($destinatario, $asunto, $contenido, $headers)) {
        header("Location: index.html?status=success");
    } else {
        // En entorno local de pruebas (WAMP) el mail() suele fallar si no hay un servidor SMTP como MailHog o Sendmail configurado.
        // Simular éxito para propósitos de demostración si es localhost
        if($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
            header("Location: index.html?status=success_local");
        } else {
            header("Location: index.html?status=error");
        }
    }
} else {
    header("Location: index.html");
}
?>
