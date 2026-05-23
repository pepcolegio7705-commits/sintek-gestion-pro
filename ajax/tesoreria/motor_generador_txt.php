<?php
function generarContenidoTXT($id_lote, $pdo) {
    // 1. Obtener Configuración y Layout
    $stmt = $pdo->prepare("SELECT l.*, b.* FROM lotes_liquidaciones l 
                           INNER JOIN bancos_config b ON l.id_banco = b.id_banco 
                           WHERE l.id_lote = ?");
    $stmt->execute([$id_lote]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    // Solo traemos columnas de la sección 'Detalle' (Cuerpo)
    $stmtCol = $pdo->prepare("SELECT * FROM bancos_layout_columnas WHERE id_banco = ? AND seccion = 'Detalle' ORDER BY orden ASC");
    $stmtCol->execute([$config['id_banco']]);
    $columnas = $stmtCol->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener Datos (Actualizado para traer CUIT de terceros)
    $sqlData = "
        SELECT lh.monto_neto as monto, COALESCE(p.cbu, s.cbu) as cbu, COALESCE(p.dni, s.dni) as dni,
               CONCAT(COALESCE(p.apellido, s.apellido), ' ', COALESCE(p.nombre, s.nombre)) as nombre_receptor
        FROM liquidaciones_haberes lh
        LEFT JOIN profesores p ON (lh.id_persona = p.id_profesor AND lh.tipo_persona = 'Profesor')
        LEFT JOIN personal_staff s ON (lh.id_persona = s.id_staff AND lh.tipo_persona = 'Staff')
        WHERE lh.id_lote = ?
        UNION ALL
        SELECT lt.monto as monto, lt.cbu_destino as cbu, lt.cuit_beneficiario as dni, lt.beneficiario_nombre as nombre_receptor
        FROM liquidaciones_terceros lt 
        WHERE lt.id_lote = ?";
    
    $stmtData = $pdo->prepare($sqlData);
    $stmtData->execute([$id_lote, $id_lote]);
    $registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    $txt_final = ""; 

    foreach ($registros as $reg) {
        $linea = "";
        foreach ($columnas as $col) {
            $valor = "";
            switch ($col['origen_dato']) {
                case 'agente_cbu':    $valor = (string)$reg['cbu']; break;
                case 'monto_pago':    $valor = str_replace(['.', ','], '', number_format((float)$reg['monto'], 2, '.', '')); break;
                case 'agente_nombre': $valor = mb_strtoupper($reg['nombre_receptor']); break;
                case 'agente_dni':    
                case 'entidad_cuit':  // Ambos usan el campo 'dni' del alias del SQL
                    $valor = (string)$reg['dni']; 
                    break;
                case 'fecha_hoy':     $valor = date('Ymd'); break;
                case 'id_lote':       $valor = (string)$id_lote; break;
                case 'Constante':     $valor = (string)$col['valor_constante']; break;
                default:              $valor = ""; break;
            }

            // Limpieza de caracteres especiales (Bancos no aceptan tildes ni Ñ)
            $buscar  = ['Á','É','Í','Ó','Ú','Ñ','á','é','í','ó','ú','ñ'];
            $reemplazo = ['A','E','I','O','U','N','a','e','i','o','u','n'];
            $valor = str_replace($buscar, $reemplazo, $valor);
            
            // Quitar cualquier otro caracter que no sea alfanumérico o espacios (opcional pero recomendado)
            $valor = preg_replace('/[^A-Za-z0-9 ]/', '', $valor);

            $longitud = (int)$col['longitud'];
            if (strlen($valor) > $longitud) $valor = substr($valor, 0, $longitud);

            $pad = ($col['alineacion'] == 'R') ? STR_PAD_LEFT : STR_PAD_RIGHT;
            $fill = (!empty($col['relleno'])) ? $col['relleno'] : " ";
            $linea .= str_pad($valor, $longitud, $fill, $pad);
            
            if ($config['tipo_archivo'] == 'Delimitado') $linea .= $config['separador'];
        }
        $txt_final .= ($config['tipo_archivo'] == 'Delimitado' ? rtrim($linea, $config['separador']) : $linea) . "\r\n";
    }

    // Actualizamos el HASH y la fecha de descarga en la tabla de control
    $hash = hash('sha256', $txt_final);
    $pdo->prepare("UPDATE lotes_liquidaciones SET hash_control = ?, fecha_descarga = NOW() WHERE id_lote = ?")
        ->execute([$hash, $id_lote]);

    return $txt_final;
}