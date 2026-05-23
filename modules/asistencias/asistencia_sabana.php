<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php'; 

verificar_permisos(['Administrador', 'Secretaría', 'Profesor']);
$rol = $_SESSION['rol']; 

// CAMBIO CRÍTICO: Ahora aceptamos tanto URL Amigable como Parámetros Directos
$uuid_carrera = $_GET['uuid_carrera'] ?? '';
$uuid_espacio = $_GET['uuid_espacio'] ?? $_GET['id_espacio'] ?? ''; // Compatibilidad
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('n');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

if (empty($uuid_espacio)) {
    die("Error: Identificador de espacio curricular no recibido.");
}

try {
    // 1. Traducir el identificador (sea ID o UUID) y obtener info
    // Buscamos por UUID para mantener la seguridad que implementamos
    $info_sql = "SELECT e.id_espacio, e.nombre_espacio, e.uuid_espacio, c.id_carrera, c.nombre_carrera, c.uuid_carrera 
                 FROM espacios_curriculares e 
                 JOIN carreras c ON e.id_carrera = c.id_carrera 
                 WHERE e.uuid_espacio = ? OR e.id_espacio = ?";
    
    $stmt_info = $pdo->prepare($info_sql);
    $stmt_info->execute([$uuid_espacio, $uuid_espacio]);
    $info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$info) die("Error: El espacio curricular no existe o no es válido.");

    $id_espacio = $info['id_espacio'];
    $uuid_carrera = $info['uuid_carrera'];
    $uuid_espacio = $info['uuid_espacio'];
    $dias_en_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

    // 2. Obtener Alumnos
    $alumnos_sql = "SELECT a.id_alumno, a.apellido, a.nombre, a.dni 
                    FROM alumnos a
                    INNER JOIN inscripciones_espacios i ON a.id_alumno = i.id_alumno
                    WHERE i.id_espacio = ?
                    ORDER BY a.apellido, a.nombre";
    $stmt_alumnos = $pdo->prepare($alumnos_sql);
    $stmt_alumnos->execute([$id_espacio]);
    $alumnos = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener Asistencias
    $asist_sql = "SELECT id_alumno, DAY(fecha) as dia_num, estado 
                  FROM asistencias_clases 
                  WHERE id_espacio = ? AND MONTH(fecha) = ? AND YEAR(fecha) = ?";
    $stmt_asist = $pdo->prepare($asist_sql);
    $stmt_asist->execute([$id_espacio, $mes, $anio]);
    $asistencias_raw = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);

    $matriz = [];
    $dias_con_clase = [];
    foreach ($asistencias_raw as $reg) {
        $matriz[(int)$reg['id_alumno']][(int)$reg['dia_num']] = $reg['estado'];
        $dias_con_clase[(int)$reg['dia_num']] = true;
    }
    $total_clases_mes = count($dias_con_clase);

} catch (PDOException $e) { die("Error: " . $e->getMessage()); }

$meses_nombres = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sábana Asistencia - Sintek</title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-size: 0.85rem; }
        .table-sabana th, .table-sabana td { border: 1px solid #dee2e6; text-align: center; vertical-align: middle; }
        .col-nombre { text-align: left !important; min-width: 250px; position: sticky; left: 0; background: white; z-index: 5; border-right: 2px solid #dee2e6 !important; }
        .p-mark { color: #198754; font-weight: bold; }
        .a-mark { color: #dc3545; font-weight: bold; background-color: #fff5f5; }
        .dia-clase { background-color: #e7f1ff !important; font-weight: bold; }
    </style>
</head>
<body>
    <?php include '../../vistas/nav.php'; ?>

    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <h5 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($info['nombre_espacio']) ?></h5>
                        <small class="text-muted"><?= $meses_nombres[$mes] ?> <?= $anio ?></small>
                        <a href="asistencia_pdf.php?uuid_espacio=<?= $uuid_espacio ?>&mes=<?= $mes ?>&anio=<?= $anio ?>" target="_blank" class="btn btn-sm btn-danger"><i class="fas fa-file-pdf me-1"></i> Generar PDF</a>
                    </div>
                    <div class="col-md-5 text-end">
                        <div class="d-inline-flex gap-2">
                             <select id="selectorMes" class="form-select form-select-sm">
                                <?php foreach($meses_nombres as $num => $nom): ?>
                                    <option value="<?= $num ?>" <?= $mes == $num ? 'selected' : '' ?>><?= $nom ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?= BASE_URL ?>gestion-asistencias/inicio" class="btn btn-sm btn-secondary">Volver</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 table-sabana">
                        <thead class="table-dark">
                            <tr>
                                <th class="col-nombre ps-3">Estudiante</th>
                                <?php for($d=1; $d<=$dias_en_mes; $d++): ?>
                                    <th class="<?= isset($dias_con_clase[$d]) ? 'dia-clase' : '' ?>"><?= $d ?></th>
                                <?php endfor; ?>
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($alumnos as $al): 
                                $presentes = 0; 
                            ?>
                            <tr>
                                <td class="col-nombre ps-3 fw-bold"><?= htmlspecialchars($al['apellido'].", ".$al['nombre']) ?></td>
                                <?php for($d=1; $d<=$dias_en_mes; $d++): 
                                    $estado = $matriz[(int)$al['id_alumno']][$d] ?? '';
                                    if(in_array($estado, ['PRESENTE', 'TARDE', 'JUSTIFICADO'])) $presentes++;
                                    $letra = ($estado == 'PRESENTE') ? 'P' : (($estado == 'AUSENTE') ? 'A' : (($estado == 'TARDE') ? 'T' : ''));
                                ?>
                                    <td class="<?= ($letra == 'P') ? 'p-mark' : (($letra == 'A') ? 'a-mark' : '') ?>"><?= $letra ?></td>
                                <?php endfor; ?>
                                <td class="fw-bold bg-light"><?= ($total_clases_mes > 0) ? round(($presentes/$total_clases_mes)*100) : 0 ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <p class="small text-muted mb-0">
                <strong>Referencias:</strong> P: Presente | A: Ausente | T: Tarde | <span class="badge bg-info text-dark">Azul</span>: Día de clase dictada.
            </p>
            <p class="small text-muted mb-0">
                Total clases dictadas en el mes: <strong><?= $total_clases_mes ?></strong>
            </p>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.getElementById('selectorMes').addEventListener('change', function() {
            const mes = this.value;
            const anio = '<?= $anio ?>';
            const uuidC = '<?= $uuid_carrera ?>';
            const uuidE = '<?= $uuid_espacio ?>';
            
            // CONSTRUCCIÓN DE RUTA FÍSICA ABSOLUTA
            // Usamos BASE_URL para que no importe en qué "nivel" crea el navegador que está.
            // Esto garantiza que siempre encuentre el archivo en la carpeta de asistencias.
            const urlFisica = '<?= BASE_URL ?>' + `modules/asistencias/asistencia_sabana.php?uuid_carrera=${uuidC}&uuid_espacio=${uuidE}&mes=${mes}&anio=${anio}`;
            
            console.log("Cambiando mes a:", urlFisica);
            window.location.href = urlFisica;
        });
    </script>
</body>
</html>