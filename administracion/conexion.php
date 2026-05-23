<?php


    $host = 'localhost';
    $user = 'admin';
    $password = 'chromo20';
    $db = 'asistencia';

    $con = @mysqli_connect($host,$user,$password,$db);
    mysqli_set_charset($con,"utf8");

    if (!$con) {
        echo 'Error en la conexion';
    }

?>
