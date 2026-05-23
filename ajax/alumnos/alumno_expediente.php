<?php
session_start();
require_once '../../core/conexion.php';
require_once '../../core/seguridad.php';

verificar_permisos(['Administrador', 'Secretaría']);

$rol = $_SESSION['rol']; 
// 1. SEGURIDAD: Captura y limpieza de UUID
$uuid = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['uuid'] ?? ''));

if (!isset($_SESSION['loggedin']) || !$uuid) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// 2. DATOS DEL ALUMNO
$stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido, dni, email, activo FROM alumnos WHERE uuid_alumno = ? LIMIT 1");
$stmt->execute([$uuid]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alumno) { die("Alumno no encontrado."); }
$id_al = (int)$alumno['id_alumno'];

// 3. CONSULTA UNIFICADA DE NOTAS (Con Filtro de Planilla Cerrada)
$sql_historial = "
    (SELECT 
        'CURSADA' as origen, e.nombre_espacio, e.anio_cursada, cn.id_espacio, cn.ciclo_lectivo as ciclo,
        cn.nota_final_cursada as nota, cn.fecha_registro as fecha, NULL as libro, NULL as folio,
        car.nombre_carrera, car.id_carrera
    FROM cursadas_notas cn
    JOIN espacios_curriculares e ON cn.id_espacio = e.id_espacio
    JOIN carreras car ON cn.id_carrera = car.id_carrera
    -- REGLA: Solo notas de planillas cerradas
    JOIN control_planillas cp ON (cp.id_espacio = cn.id_espacio AND cp.ciclo_lectivo = cn.ciclo_lectivo)
    WHERE cn.id_alumno = ? AND cp.estado = 'Cerrada')
    
    UNION ALL

    (SELECT 
        'EXAMEN' as origen, e.nombre_espacio, e.anio_cursada, m.id_espacio, m.ciclo_lectivo as ciclo,
        en.nota_final as nota, en.fecha_registro as fecha, en.libro, en.folio,
        car.nombre_carrera, car.id_carrera
    FROM examenes_notas en
    JOIN mesas_examenes m ON en.id_mesa = m.id_mesa
    JOIN espacios_curriculares e ON m.id_espacio = e.id_espacio
    JOIN carreras car ON m.id_carrera = car.id_carrera
    WHERE en.id_alumno = ?)
    
    ORDER BY nombre_carrera ASC, fecha DESC
";

$stmt_h = $pdo->prepare($sql_historial);
$stmt_h->execute([$id_al, $id_al]);
$historial = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

// 4. LÓGICA DE PROMEDIOS (Histórico vs Académico con Mejor Nota)
$notas_agrupadas = [];
$stats = []; 
$mejor_nota_por_espacio = []; // Para el promedio académico (aprobados)

foreach ($historial as $n) {
    $carrera = $n['nombre_carrera'];
    $id_c = $n['id_carrera'];
    $id_e = $n['id_espacio'];
    $nota = (float)($n['nota'] ?? 0);
    
    $notas_agrupadas[$carrera][] = $n;

    if (!isset($stats[$id_c])) {
        $stats[$id_c] = ['sum_h' => 0, 'cant_h' => 0];
    }

    // Estadísticas para Promedio Histórico (Todas las notas cerradas/rendidas)
    $stats[$id_c]['sum_h'] += $nota;
    $stats[$id_c]['cant_h']++;

    // Lógica para Promedio Académico: Solo la nota más alta aprobada (>= 7) por espacio
    if ($nota >= 7) {
        if (!isset($mejor_nota_por_espacio[$id_c][$id_e]) || $nota > $mejor_nota_por_espacio[$id_c][$id_e]) {
            $mejor_nota_por_espacio[$id_c][$id_e] = $nota;
        }
    }
}

// 5. VALIDACIÓN DE TESORERÍA (DEUDA)
$stmt_deuda = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE id_alumno = ? AND estado = 'Pendiente'");
$stmt_deuda->execute([$id_al]);
$tiene_deuda = ($stmt_deuda->fetchColumn() > 0);

// 6. ASISTENCIAS (RESUMEN)
$sql_asist = "SELECT e.nombre_espacio, COUNT(ac.id_asistencia_clase) as total,
              SUM(CASE WHEN ac.estado = 'Presente' THEN 1 ELSE 0 END) as presentes
              FROM asistencias_clases ac
              JOIN espacios_curriculares e ON ac.id_espacio = e.id_espacio
              WHERE ac.id_alumno = ? GROUP BY e.id_espacio";
$stmt_asist = $pdo->prepare($sql_asist);
$stmt_asist->execute([$id_al]);
$asistencias = $stmt_asist->fetchAll(PDO::FETCH_ASSOC);

// 7. CARRERAS PARA TABS
$stmt_c = $pdo->prepare("SELECT c.id_carrera, c.nombre_carrera, ac.cohorte FROM alumnos_carreras ac JOIN carreras c ON ac.id_carreras = c.id_carrera WHERE ac.id_alumno = ?");
$stmt_c->execute([$id_al]);
$carreras_alumno = $stmt_c->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente | <?= htmlspecialchars($alumno['apellido']) ?></title>
    <link href="<?= BASE_URL ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .bg-egreso-blocked { background-color: #fff5f5; border: 1px dashed #feb2b2; }
        .bg-egreso-ok { background-color: #f0fff4; border: 1px dashed #9ae6b4; }
        .nav-pills .nav-link.active { background-color: #6f42c1; }
        .card-stats { border-left: 5px solid #6f42c1; }
    </style>
</head>
<body class="bg-light">

<?php include '../../vistas/nav.php'; ?>

<div class="container-fluid mt-4 px-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-0 text-uppercase"><?= htmlspecialchars($alumno['apellido'] . ", " . $alumno['nombre']) ?></h2>
                <p class="text-muted mb-0">DNI: <?= number_format((float)$alumno['dni'], 0, '', '.') ?> | <?= htmlspecialchars($alumno['email']) ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-dark dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-print me-2"></i>Documentación
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL; ?>alumnos/analitico/<?= $uuid ?>" target="_blank"><i class="fas fa-file-pdf text-danger me-2"></i> Historial Completo</a></li>
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>alumnos/analitico-aprobado/<?= $uuid ?>" target="_blank"><i class="fas fa-file-signature text-success me-2"></i> Solo Aprobadas (Mejor Nota)</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="nav flex-column nav-pills shadow-sm bg-white p-3 rounded mb-4">
                <button class="nav-link active mb-2" data-bs-toggle="pill" data-bs-target="#tab-notas"><i class="fas fa-graduation-cap me-2"></i>Historial de Notas</button>
                <button class="nav-link mb-2" data-bs-toggle="pill" data-bs-target="#tab-asistencia"><i class="fas fa-user-clock me-2"></i>Asistencias de Clases</button>
            </div>

            <?php foreach($carreras_alumno as $ca): 
                $id_c = $ca['id_carrera'];
                $s = $stats[$id_c] ?? ['sum_h'=>0, 'cant_h'=>0];
                
                // Promedio Histórico
                $p_hist = ($s['cant_h'] > 0) ? round($s['sum_h'] / $s['cant_h'], 2) : 0;
                
                // Promedio Académico (Sobre mejores notas aprobadas >= 7)
                $aprobadas_unicas = isset($mejor_nota_por_espacio[$id_c]) ? count($mejor_nota_por_espacio[$id_c]) : 0;
                $suma_mejores = isset($mejor_nota_por_espacio[$id_c]) ? array_sum($mejor_nota_por_espacio[$id_c]) : 0;
                $p_acad = ($aprobadas_unicas > 0) ? round((float)$suma_mejores / $aprobadas_unicas, 2) : 0;

                $stmt_p = $pdo->prepare("SELECT COUNT(*) FROM espacios_curriculares WHERE id_carrera = ? AND activo = 1");
                $stmt_p->execute([$id_c]);
                $total_m = $stmt_p->fetchColumn();
                $progreso = ($total_m > 0) ? round(($aprobadas_unicas / $total_m) * 100) : 0;
            ?>
            <div class="card shadow-sm mb-3 border-0 card-stats">
                <div class="card-body">
                    <h6 class="fw-bold text-uppercase small text-primary mb-3"><?= htmlspecialchars($ca['nombre_carrera']) ?></h6>
                    <div class="row text-center mb-3">
                        <div class="col-6 border-end">
                            <small class="text-muted d-block" style="font-size: 0.7rem;">P. HISTÓRICO</small>
                            <strong class="h5 text-danger"><?= number_format((float)$p_hist, 2) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block" style="font-size: 0.7rem;">P. ACADÉMICO</small>
                            <strong class="h5 text-success"><?= number_format((float)$p_acad, 2) ?></strong>
                        </div>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?= $progreso ?>%"></div>
                    </div>
                    <p class="text-center small mt-2 mb-0"><?= $progreso ?>% del plan aprobado (<?= $aprobadas_unicas ?>/<?= $total_m ?>)</p>

                    <?php if($progreso >= 100): ?>
                        <div class="p-2 rounded mt-3 text-center <?= $tiene_deuda ? 'bg-egreso-blocked' : 'bg-egreso-ok' ?>">
                            <?php if($tiene_deuda): ?>
                                <small class="text-danger fw-bold d-block mb-2"><i class="fas fa-lock me-1"></i> Bloqueo por Deuda</small>
                                <button class="btn btn-sm btn-secondary w-100 disabled">Regularizar en Tesorería</button>
                            <?php else: ?>
                                <small class="text-success fw-bold d-block mb-2"><i class="fas fa-check-circle me-1"></i> Apto para Egreso</small>
                                <a href="<?= BASE_URL ?>alumnos/acta-egreso/<?= $uuid ?>" target="_blank" class="btn btn-sm btn-success w-100 fw-bold shadow-sm">
                                    <i class="fas fa-certificate me-2"></i> IMPRIMIR ACTA DE EGRESO
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="col-md-8">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-notas">
                    <?php foreach($notas_agrupadas as $carrera_nombre => $notas): ?>
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3">
                                <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($carrera_nombre) ?></h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light small">
                                        <tr>
                                            <th>Origen</th><th>Materia</th><th class="text-center">Año</th><th class="text-center">Nota</th><th>Libro/Folio</th><th class="text-center">Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 0.85rem;">
                                        <?php foreach($notas as $n): ?>
                                        <tr>
                                            <td>
                                                <span class="badge border text-dark bg-light">
                                                    <?= $n['origen'] == 'CURSADA' ? '<i class="fas fa-pen-nib me-1"></i>' : '<i class="fas fa-graduation-cap me-1"></i>' ?>
                                                    <?= $n['origen'] ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold"><?= htmlspecialchars($n['nombre_espacio']) ?></td>
                                            <td class="text-center"><?= $n['anio_cursada'] ?>°</td>
                                            <td class="text-center fw-bold <?= $n['nota'] >= 7 ? 'text-success' : 'text-danger' ?>" style="font-size: 1rem;">
                                                <?= number_format((float)($n['nota'] ?? 0), 0) ?>
                                            </td>
                                            <td class="text-muted"><?= $n['libro'] ? "L:{$n['libro']} F:{$n['folio']}" : '--' ?></td>
                                            <td class="text-center"><?= date('d/m/Y', strtotime($n['fecha'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="tab-pane fade" id="tab-asistencia">
                    <div class="card shadow-sm border-0 p-4">
                        <h6 class="fw-bold mb-4">Asistencia acumulada por Materia</h6>
                        <table class="table table-hover">
                            <thead class="table-light"><tr><th>Materia</th><th class="text-center">Clases</th><th class="text-center">Presente</th><th>% Asist.</th></tr></thead>
                            <tbody>
                                <?php foreach($asistencias as $as): 
                                    $p = ($as['total'] > 0) ? round(($as['presentes'] / $as['total']) * 100) : 0;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($as['nombre_espacio']) ?></td>
                                    <td class="text-center"><?= $as['total'] ?></td>
                                    <td class="text-center"><?= $as['presentes'] ?></td>
                                    <td width="35%">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar <?= $p >= 75 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $p ?>%"></div>
                                            </div>
                                            <span class="ms-2 fw-bold small"><?= $p ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>