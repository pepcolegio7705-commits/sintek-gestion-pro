<?php
// Script temporal para copiar las imágenes generadas a la carpeta pública
$sourceDir = 'C:\Users\jawsi\.gemini\antigravity-ide\brain\efab64af-47be-4faa-9c32-bf2c4aa2d2a6\\';
$destDir = __DIR__ . '/img/';

if (!file_exists($destDir)) {
    mkdir($destDir, 0777, true);
}

$files = glob($sourceDir . '*_mockup_*.png');
foreach ($files as $file) {
    $basename = basename($file);
    // Extraer el nombre base (dashboard, tesoreria, etc)
    if (strpos($basename, 'dashboard_mockup') !== false) {
        copy($file, $destDir . 'dashboard.png');
    } elseif (strpos($basename, 'tesoreria_mockup') !== false) {
        copy($file, $destDir . 'tesoreria.png');
    } elseif (strpos($basename, 'academico_mockup') !== false) {
        copy($file, $destDir . 'academico.png');
    } elseif (strpos($basename, 'asistencia_mockup') !== false) {
        copy($file, $destDir . 'asistencia.png');
    }
}
echo "Imagenes copiadas exitosamente!";
?>
