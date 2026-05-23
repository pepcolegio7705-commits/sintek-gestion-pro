<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

// Protección de rango
if ($_SESSION['rol'] !== 'Administrador') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Datos del Banco
    $id_banco      = (int)($_POST['id_banco'] ?? 0); // 0 si es nuevo
    $nombre_banco  = trim($_POST['nombre_banco'] ?? '');
    $tipo_archivo  = $_POST['tipo_archivo'] ?? 'Fijo';
    $separador     = $_POST['separador'] ?? '';

    if (empty($nombre_banco)) throw new Exception("El nombre del banco es obligatorio.");

    if ($id_banco === 0) {
        $sqlB = "INSERT INTO bancos_config (nombre_banco, tipo_archivo, separador) VALUES (?, ?, ?)";
        $pdo->prepare($sqlB)->execute([$nombre_banco, $tipo_archivo, $separador]);
        $id_banco = $pdo->lastInsertId();
    } else {
        $sqlB = "UPDATE bancos_config SET nombre_banco = ?, tipo_archivo = ?, separador = ? WHERE id_banco = ?";
        $pdo->prepare($sqlB)->execute([$nombre_banco, $tipo_archivo, $separador, $id_banco]);
        
        // Si estamos editando, borramos las columnas anteriores para re-insertar las nuevas
        $pdo->prepare("DELETE FROM bancos_layout_columnas WHERE id_banco = ?")->execute([$id_banco]);
    }

    // 2. Datos de las Columnas (Arrays de la tabla dinámica)
    $orden     = $_POST['col_orden'] ?? [];
    $nombres   = $_POST['col_nombre'] ?? [];
    $origenes  = $_POST['col_origen'] ?? [];
    $longitudes = $_POST['col_longitud'] ?? [];
    $rellenos  = $_POST['col_relleno'] ?? [];
    $alineas   = $_POST['col_alineacion'] ?? [];
    $formatos  = $_POST['col_formato'] ?? [];

    $sqlCol = "INSERT INTO bancos_layout_columnas 
               (id_banco, orden, nombre_campo, origen_dato, longitud, relleno, alineacion, tipo_formato) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtCol = $pdo->prepare($sqlCol);

    foreach ($orden as $i => $pos) {
        if (empty($nombres[$i])) continue; // Saltamos filas vacías

        $stmtCol->execute([
            $id_banco,
            $pos,
            $nombres[$i],
            $origenes[$i],
            (int)$longitudes[$i],
            $rellenos[$i],
            $alineas[$i],
            $formatos[$i]
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'mensaje' => 'Estructura de banco guardada con éxito.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}