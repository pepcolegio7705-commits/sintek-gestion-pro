<?php
/**
 * DASHBOARD ACADÉMICO - SINTEK GESTIÓN PREMIUM
 */

// 1. Cargamos el núcleo
require_once 'core/conexion.php';
require_once 'core/funciones.php';
require_once 'core/seguridad.php';

// 2. Blindaje de seguridad (Cualquier rol logueado puede ver el Dashboard)
verificar_permisos(); 

$rol = $_SESSION['rol'];
// Usamos el ciclo del filtro o el actual definido en la DB
$ciclo_filtro = isset($_GET['ciclo']) ? (int)$_GET['ciclo'] : (int)CICLO_INST;

// --- CONSULTAS ACADÉMICAS ---
$total_alumnos  = $pdo->query("SELECT COUNT(*) FROM alumnos")->fetchColumn();
$activos        = $pdo->query("SELECT COUNT(*) FROM alumnos WHERE activo = 1")->fetchColumn();
$total_carreras = $pdo->query("SELECT COUNT(*) FROM carreras WHERE activo = 1")->fetchColumn();

// Rendimiento Académico (Gráfico de Barras)
$sql_rendimiento = "SELECT 
                        SUM(CASE WHEN condicion = 'APROBADO' THEN 1 ELSE 0 END) as aprobados,
                        SUM(CASE WHEN condicion = 'REGULAR' THEN 1 ELSE 0 END) as regulares,
                        SUM(CASE WHEN condicion = 'DESAPROBADO' THEN 1 ELSE 0 END) as desaprobados
                    FROM calificaciones 
                    WHERE YEAR(fecha_registro) = :ciclo";
$stmt_rend = $pdo->prepare($sql_rendimiento);
$stmt_rend->execute(['ciclo' => $ciclo_filtro]);
$rendimiento = $stmt_rend->fetch();

// Alumnos por Carrera (Gráfico de Dona)
$sql_carreras = "SELECT c.nombre_carrera as etiqueta, COUNT(DISTINCT ie.id_alumno) as total 
                 FROM carreras c 
                 INNER JOIN espacios_curriculares ec ON c.id_carrera = ec.id_carrera 
                 INNER JOIN inscripciones_espacios ie ON ec.id_espacio = ie.id_espacio 
                 INNER JOIN alumnos a ON ie.id_alumno = a.id_alumno
                 WHERE a.activo = 1 AND c.activo = 1
                 GROUP BY c.id_carrera";
$res_carreras = $pdo->query($sql_carreras)->fetchAll();
$labels_carreras = array_column($res_carreras, 'etiqueta');
$data_carreras   = array_column($res_carreras, 'total');

// Histórico Egresados (Tabla inferior)
$sql_egresados = "SELECT YEAR(fecha_egreso) as anio, COUNT(*) as total 
                  FROM alumnos WHERE activo = 2 AND fecha_egreso IS NOT NULL 
                  GROUP BY YEAR(fecha_egreso) ORDER BY anio DESC LIMIT 5";
$stats_egresados = $pdo->query($sql_egresados)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Académico | <?= NOM_INST ?></title>
    
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/all.min.css">
    <script src="<?= BASE_URL ?>assets/js/chart.js"></script>

    <style>
        :root { 
            --azul-institucional: #003366; 
            --azul-claro: #00509e;
            --bg-gris: #f4f7f6; 
        }
        body { 
            background-color: var(--bg-gris); 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
        }
        /* Glassmorphism en Header Institucional */
        .info-institucional { 
            background: linear-gradient(135deg, var(--azul-institucional) 0%, #001a33 100%); 
            color: white; 
            border-radius: 15px; 
            padding: 30px; 
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 51, 102, 0.2);
            position: relative;
            overflow: hidden;
        }
        .info-institucional::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        /* Tarjetas con sombras suaves (Premium UI) */
        .stat-card { 
            border: none; 
            border-radius: 15px; 
            transition: all 0.3s ease; 
            border-left: 5px solid transparent; 
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .stat-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 15px 30px rgba(0,0,0,0.1); 
        }
        .icon-shape { 
            width: 55px; height: 55px; 
            display: flex; align-items: center; justify-content: center; 
            border-radius: 15px; font-size: 24px; 
        }
        .border-primary-accent { border-left-color: #0d6efd; }
        .border-success-accent { border-left-color: #198754; }
        .border-info-accent { border-left-color: #0dcaf0; }
        .card-header { 
            background-color: transparent !important; 
            border-bottom: 1px solid rgba(0,0,0,0.05); 
            font-weight: 700; 
            color: var(--azul-institucional); 
            padding-top: 1.5rem;
            padding-bottom: 1rem;
        }
        .subtitulo-explicativo { color: #8898aa; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    </style>
</head>
<body>
    
    <?php include 'vistas/nav.php'; ?>

    <div class="container-fluid py-4 px-4">
        
        <div class="info-institucional shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h1 class="display-6 fw-bold mb-2"><?= NOM_INST ?></h1>
                    <p class="lead mb-3 small fw-bold text-info text-uppercase">Panel de Gestión Académica</p>
                    <div class="d-flex flex-wrap gap-4 small opacity-90">
                        <span><i class="fas fa-calendar-check me-2"></i> Ciclo: <strong><?= $ciclo_filtro ?></strong></span>
                        <span><i class="fas fa-user-circle me-2"></i> <?= $_SESSION['nombre_usuario'] ?> (<?= $rol ?>)</span>
                    </div>
                </div>
                <div class="col-md-5 border-start border-white border-opacity-25 ps-md-5">
                    <div class="small fw-light">
                        <p class="mb-1"><i class="fas fa-user-tie me-2 text-info"></i> <strong>Rectoría:</strong> <?= RECTOR_INST ?></p>
                        <p class="mb-1"><i class="fas fa-phone-alt me-2 text-info"></i> <strong>Soporte:</strong> <?= TEL_INST ?></p>
                        <p class="mb-0"><i class="fas fa-envelope me-2 text-info"></i> <?= MAIL_INST ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 justify-content-end">
            <div class="col-md-3">
                <div class="bg-white p-2 rounded shadow-sm border">
                    <form method="GET" action="dashboard" class="d-flex align-items-center">
                        <small class="me-2 fw-bold text-muted text-nowrap small">FILTRAR CICLO:</small>
                        <select name="ciclo" class="form-select form-select-sm border-0 bg-light fw-bold" onchange="this.form.submit()">
                            <?php for($i = date('Y'); $i >= 2022; $i--): ?>
                                <option value="<?= $i ?>" <?= ($ciclo_filtro == $i ? 'selected' : '') ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-primary-accent h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="subtitulo-explicativo mb-1">Matrícula Total</h6>
                                <h2 class="fw-bold mb-0"><?= $total_alumnos ?></h2>
                            </div>
                            <div class="icon-shape bg-primary bg-opacity-10 text-primary"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-success-accent h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="subtitulo-explicativo mb-1">Alumnos Activos</h6>
                                <h2 class="fw-bold mb-0 text-success"><?= $activos ?></h2>
                            </div>
                            <div class="icon-shape bg-success bg-opacity-10 text-success"><i class="fas fa-user-check"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm border-info-accent h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="subtitulo-explicativo mb-1">Carreras Vigentes</h6>
                                <h2 class="fw-bold mb-0 text-info"><?= $total_carreras ?></h2>
                            </div>
                            <div class="icon-shape bg-info bg-opacity-10 text-info"><i class="fas fa-graduation-cap"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3">Rendimiento Académico Ciclo: <?= $ciclo_filtro ?></div>
                    <div class="card-body">
                        <canvas id="chartRendimiento" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header py-3">Distribución por Carrera</div>
                    <div class="card-body">
                        <canvas id="chartCarreras" style="max-height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header py-3">Histórico Reciente de Egresados</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Año</th>
                                    <th>Cantidad</th>
                                    <th>Crecimiento</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($stats_egresados)): ?>
                                    <tr><td colspan="4" class="text-center p-4 text-muted">No hay registros de egreso aún.</td></tr>
                                <?php else: ?>
                                    <?php foreach($stats_egresados as $e): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold"><?= $e['anio'] ?></td>
                                        <td><span class="badge bg-primary rounded-pill px-3"><?= $e['total'] ?></span></td>
                                        <td>
                                            <div class="progress" style="height: 6px; width: 150px;">
                                                <div class="progress-bar bg-success" style="width: 100%"></div>
                                            </div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="egresados?anio=<?= $e['anio'] ?>" class="btn btn-sm btn-outline-dark"><i class="fas fa-search"></i> Ver Legajos</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/js/bootstrap.bundle.min.js"></script>

    <script>
        // Configuración Global de Chart.js
        Chart.defaults.font.family = 'Segoe UI';
        Chart.defaults.color = '#8898aa';

        // 1. Chart Rendimiento (Barras)
        const ctxRend = document.getElementById('chartRendimiento').getContext('2d');
        new Chart(ctxRend, {
            type: 'bar',
            data: {
                labels: ['Aprobados', 'Regulares', 'Desaprobados'],
                datasets: [{
                    data: [
                        <?= (int)($rendimiento['aprobados'] ?? 0) ?>, 
                        <?= (int)($rendimiento['regulares'] ?? 0) ?>, 
                        <?= (int)($rendimiento['desaprobados'] ?? 0) ?>
                    ],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderRadius: 10,
                    barThickness: 50
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Chart Carreras (Dona)
        const ctxCarr = document.getElementById('chartCarreras').getContext('2d');
        new Chart(ctxCarr, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels_carreras) ?>,
                datasets: [{
                    data: <?= json_encode($data_carreras) ?>,
                    backgroundColor: ['#003366', '#0d6efd', '#20c997', '#0dcaf0', '#ffc107'],
                    borderWidth: 0,
                    hoverOffset: 15
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        position: 'bottom', 
                        labels: { padding: 20, usePointStyle: true, font: { size: 11 } } 
                    } 
                },
                cutout: '70%'
            }
        });
    </script>
    
    <?php include 'vistas/footer.php'; ?>
</body>
</html>