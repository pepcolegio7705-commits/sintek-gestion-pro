<?php
$password = 'chromo20'; // Tu contraseña
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "El hash de tu contraseña es: " . $hash;
?>