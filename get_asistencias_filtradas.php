<?php
require 'conexion.php';

$id_alumno = (int)($_POST['id'] ?? 0);
$desde = $_POST['desde'] ?? '';
$hasta = $_POST['hasta'] ?? '';

try {
    if ($desde != '' && $hasta != '') {
        // Si hay fechas, buscamos en ese rango
        $sql = "SELECT fecha, hora_registro, tipo_registro 
                FROM asistencias 
                WHERE id_alumno = ? AND fecha BETWEEN ? AND ? 
                ORDER BY fecha DESC, hora_registro DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_alumno, $desde, $hasta]);
    } else {
        // Si NO hay fechas, cargamos solo las últimas 30 por rendimiento
        $sql = "SELECT fecha, hora_registro, tipo_registro 
                FROM asistencias 
                WHERE id_alumno = ? 
                ORDER BY fecha DESC, hora_registro DESC 
                LIMIT 30";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_alumno]);
    }
    
    $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$asistencias) {
        echo '<div class="alert alert-info mt-3">No se encontraron registros de asistencia para este periodo.</div>';
    } else {
        echo '<div class="table-responsive mt-3">
                <table class="table table-sm table-hover border">
                    <thead class="bg-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Evento</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($asistencias as $a) {
            $badge = ($a['tipo_registro'] == 'ENTRADA') ? 'badge-success' : 'badge-info';
            echo '<tr>
                    <td>' . date('d/m/Y', strtotime($a['fecha'])) . '</td>
                    <td>' . $a['hora_registro'] . '</td>
                    <td><span class="badge ' . $badge . '">' . $a['tipo_registro'] . '</span></td>
                  </tr>';
        }
        echo '    </tbody>
                </table>
              </div>';
        
        if ($desde == '') {
            echo '<p class="small text-muted text-center mt-2">Mostrando los últimos 30 movimientos. Use los filtros para ver más.</p>';
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}