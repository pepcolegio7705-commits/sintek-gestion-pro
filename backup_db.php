<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

// Solo el Administrador puede realizar respaldos
verificar_permisos(['Administrador']);

try {
    // 1. Obtener todas las tablas de la base de datos
    $tables = array();
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql_script = "-- Respaldo generado por " . AUTOR_SISTEMA . "\n";
    $sql_script .= "-- Institución: " . NOM_INST . "\n";
    $sql_script .= "-- Fecha: " . date("d-m-Y H:i:s") . "\n\n";
    $sql_script .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // 2. Recorrer cada tabla para obtener su estructura y datos
    foreach ($tables as $table) {
        // Estructura de la tabla (CREATE TABLE)
        $res = $pdo->query("SHOW CREATE TABLE $table");
        $row = $res->fetch(PDO::FETCH_NUM);
        $sql_script .= "\n\n" . $row[1] . ";\n\n";

        // Datos de la tabla (INSERT INTO)
        $result = $pdo->query("SELECT * FROM $table");
        $column_count = $result->columnCount();

        for ($i = 0; $i < $column_count; $i++) {
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $sql_script .= "INSERT INTO $table VALUES(";
                for ($j = 0; $j < $column_count; $j++) {
                    // CORRECCIÓN: Verificamos si es null antes de aplicar addslashes
                    if (isset($row[$j])) {
                        $value = str_replace("\n", "\\n", addslashes($row[$j]));
                        $sql_script .= '"' . $value . '"';
                    } else {
                        $sql_script .= 'NULL';
                    }

                    if ($j < ($column_count - 1)) {
                        $sql_script .= ',';
                    }
                }
                $sql_script .= ");\n";
            }
        }
        $sql_script .= "\n";
    }

    $sql_script .= "\nSET FOREIGN_KEY_CHECKS=1;";

    // 3. Forzar la descarga del archivo
    $file_name = 'backup_' . DB_NAME . '_' . date("Ymd_His") . '.sql';
    
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $file_name . "\"");
    
    echo $sql_script;
    exit;

} catch (Exception $e) {
    exit("Error al generar el backup: " . $e->getMessage());
}