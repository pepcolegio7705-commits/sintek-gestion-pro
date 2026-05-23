<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

// Solo el Administrador puede realizar respaldos del sistema
verificar_permisos(['Administrador']);

// Nombre del archivo ZIP resultante
$zip_name = 'backup_sistema_' . date("Ymd_His") . '.zip';

// Ruta de la carpeta a comprimir (el directorio actual del sistema)
$dir_path = realpath(__DIR__); 

// Crear instancia de ZipArchive
$zip = new ZipArchive();

if ($zip->open($zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Crear un iterador para recorrer todas las carpetas y archivos
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir_path),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        // Omitir directorios (se agregan automáticamente con los archivos)
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            
            // Nombre relativo dentro del ZIP (para que no guarde la ruta completa de C:/wamp...)
            $relativePath = substr($filePath, strlen($dir_path) + 1);

            // IMPORTANTE: Evitar que el propio backup se incluya dentro de sí mismo
            if (strpos($relativePath, '.zip') === false) {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    $zip->close();

    // 2. Forzar la descarga del archivo generado
    if (file_exists($zip_name)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_name . '"');
        header('Content-Length: ' . filesize($zip_name));
        
        flush();
        readfile($zip_name);
        
        // 3. Borrar el archivo temporal del servidor después de enviarlo
        unlink($zip_name); 
        exit;
    }
} else {
    exit("Error al crear el archivo comprimido.");
}