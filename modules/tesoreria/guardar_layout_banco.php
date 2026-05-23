<?php
session_start();
require_once '../../core/conexion.php';
header('Content-Type: application/json');

// 1. Verificación de Seguridad
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Administrador') {
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para realizar esta operación.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 2. Captura de datos de la cabecera (bancos_config)
    $id_banco       = (int)($_POST['id_banco'] ?? 0);
    $nombre_banco   = trim($_POST['nombre_banco'] ?? '');
    $tipo_archivo   = $_POST['tipo_archivo'] ?? 'Fijo';
    $separador      = $_POST['separador'] ?? '';
    
    // Captura de los nuevos campos booleanos (checkboxes)
    $usa_encabezado = isset($_POST['usa_encabezado']) ? 1 : 0;
    $usa_pie_pagina = isset($_POST['usa_pie_pagina']) ? 1 : 0;

    if (empty($nombre_banco)) {
        throw new Exception("El nombre del banco es obligatorio.");
    }

    // Insertar o Actualizar Cabecera
    if ($id_banco === 0) {
        // Se agregaron las columnas usa_encabezado y usa_pie_pagina al INSERT
        $sqlB = "INSERT INTO bancos_config (nombre_banco, tipo_archivo, separador, usa_encabezado, usa_pie_pagina, estado) VALUES (?, ?, ?, ?, ?, 1)";
        $stmtB = $pdo->prepare($sqlB);
        $stmtB->execute([$nombre_banco, $tipo_archivo, $separador, $usa_encabezado, $usa_pie_pagina]);
        $id_banco = $pdo->lastInsertId();
    } else {
        // Se agregaron las columnas usa_encabezado y usa_pie_pagina al UPDATE
        $sqlB = "UPDATE bancos_config SET nombre_banco = ?, tipo_archivo = ?, separador = ?, usa_encabezado = ?, usa_pie_pagina = ? WHERE id_banco = ?";
        $stmtB = $pdo->prepare($sqlB);
        $stmtB->execute([$nombre_banco, $tipo_archivo, $separador, $usa_encabezado, $usa_pie_pagina, $id_banco]);
        
        // Si estamos editando, limpiamos las columnas viejas para poner las nuevas y mantener integridad
        $pdo->prepare("DELETE FROM bancos_layout_columnas WHERE id_banco = ?")->execute([$id_banco]);
    }

    // 3. Captura de las Columnas (Arrays del Formulario)
    $orden      = $_POST['col_orden'] ?? [];
    $nombres    = $_POST['col_nombre'] ?? [];
    $origenes   = $_POST['col_origen'] ?? [];
    $longitudes = $_POST['col_longitud'] ?? [];
    $rellenos   = $_POST['col_relleno'] ?? [];
    $alineas    = $_POST['col_alineacion'] ?? [];
    $formatos   = $_POST['col_formato'] ?? [];

    if (empty($orden)) {
        throw new Exception("Debe definir al menos una columna para el layout.");
    }

    // Preparar el INSERT de columnas
    $sqlCol = "INSERT INTO bancos_layout_columnas 
               (id_banco, seccion, orden, nombre_campo, origen_dato, longitud, relleno, alineacion, tipo_formato) 
               VALUES (?, 'Detalle', ?, ?, ?, ?, ?, ?, ?)";
    $stmtCol = $pdo->prepare($sqlCol);

    foreach ($orden as $i => $pos) {
        // Validamos que la fila tenga datos mínimos (nombre del campo)
        if (empty($nombres[$i])) continue;

        $stmtCol->execute([
            $id_banco,
            (int)$pos,
            $nombres[$i],
            $origenes[$i],
            (int)($longitudes[$i] ?? 0),
            $rellenos[$i] ?? ' ',
            $alineas[$i] ?? 'L',
            $formatos[$i] ?? 'Texto'
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'mensaje' => 'Configuración de banco y layout guardada correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}