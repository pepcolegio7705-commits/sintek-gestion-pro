<style>
    /* Sidebar Principal */
    .sidebar {
        width: 250px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: #001a33; 
        display: flex;
        flex-direction: column;
        z-index: 1050;
        overflow-y: auto;
    }

    .sidebar-header {
        padding: 25px 15px;
        background: #003366;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    /* Estilo del Acordeón en el Sidebar */
    .sidebar .accordion { background: transparent; }
    .sidebar .accordion-item { background: transparent; border: none; }

    .sidebar .accordion-button {
        background: transparent;
        color: #cbd5e0;
        font-size: 0.9rem;
        padding: 15px 20px;
        box-shadow: none;
        font-weight: 500;
    }

    .sidebar .accordion-button:not(.collapsed) {
        color: #63b3ed;
        background: rgba(255,255,255,0.05);
    }

    .sidebar .accordion-button::after {
        filter: invert(1); 
    }

    /* Sub-enlaces del Acordeón */
    .nav-sublink {
        display: block;
        padding: 10px 20px 10px 45px;
        text-decoration: none;
        color: #a0aec0;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .nav-sublink:hover {
        color: #ffffff;
        background: rgba(255,255,255,0.1);
        padding-left: 50px;
    }

    /* Links simples (Inicio, Usuarios) */
    .nav-link-custom {
        display: block;
        padding: 15px 20px;
        color: #cbd5e0;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .nav-link-custom:hover {
        background: rgba(255,255,255,0.05);
        color: #ffffff;
    }

    .sidebar-footer {
        background: rgba(0,0,0,0.2);
        margin-top: auto;
    }

    body {
        padding-left: 250px; 
    }

    @media (max-width: 991.98px) {
        .sidebar { left: -250px; }
        body { padding-left: 0; }
    }
</style>

<div class="sidebar shadow-lg" id="sidebar">
    <div class="sidebar-header text-center">
        <i class="fas fa-school fa-2x mb-2 text-info"></i>
        <h6 class="fw-bold mb-0 text-white">SINTEK GESTIÓN</h6>
        <small class="text-info opacity-75">Sistema Académico</small>
    </div>

    <div class="accordion accordion-flush flex-grow-1" id="accordionSidebar">
        
        <div class="nav-item-single">
            <a href="<?= BASE_URL ?>dashboard" class="nav-link-custom">
                <i class="fas fa-home me-2"></i> Inicio
            </a>
        </div>

        <?php if ($rol === 'Administrador' || $rol === 'Secretaría'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colAlumnos">
                    <i class="fas fa-user-graduate me-2"></i> Alumnos
                </button>
            </h2>
            <div id="colAlumnos" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>alumnos/gestion" class="nav-sublink">Nuevo Alumno</a>
                    <a href="<?= BASE_URL ?>alumnos/historico" class="nav-sublink">Listado Histórico</a>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colPersonal">
                    <i class="fas fa-users me-2"></i> Personal
                </button>
            </h2>
            <div id="colPersonal" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>profesores/gestion" class="nav-sublink">Gestión Docentes</a>
                    <a href="<?= BASE_URL ?>staff/lista" class="nav-sublink">Listado de Staff</a>
                    <a href="<?= BASE_URL ?>staff/gestion" class="nav-sublink">Alta de Personal</a>
                    <a href="<?= BASE_URL ?>areas/gestion" class="nav-sublink">Áreas / Deptos.</a>
                </div>
            </div>
        </div>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colEstructura">
                    <i class="fas fa-sitemap me-2"></i> Institución
                </button>
            </h2>
            <div id="colEstructura" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>carreras/gestion" class="nav-sublink">Carreras</a>
                    <a href="<?= BASE_URL ?>materias/gestion" class="nav-sublink">Materias</a>
                    <a href="<?= BASE_URL ?>inscripciones/gestionar" class="nav-sublink">Gestionar Inscripciones</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rol === 'Secretaría' || $rol === 'Administrador' || $rol === 'Profesor'): ?> 
        <div class="accordion-item bg-transparent border-0">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colExamenes">
                    <i class="fas fa-edit me-2"></i> Exámenes
                </button>
            </h2>
            <div id="colExamenes" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>instancias/apertura" class="nav-sublink">Apertura de Mesas</a>
                    <a href="<?= BASE_URL ?>instancias/gestion-inscripciones" class="nav-sublink">Actas de Examen</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rol === 'Secretaría' || $rol === 'Administrador' || $rol === 'Profesor'): ?> 
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colNotas">
                    <i class="fas fa-star me-2"></i> Calificaciones
                </button>
            </h2>
            <div id="colNotas" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>calificaciones/cargar" class="nav-sublink">Carga Masiva</a>
                    <?php if ($rol === 'Secretaría' || $rol === 'Administrador'): ?>
                    <a href="<?= BASE_URL ?>gestion-academica/reporte" class="nav-sublink">Reporte</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colReportes">
                    <i class="fas fa-file-pdf me-2"></i> Asistencias / Reportes
                </button>
            </h2>
            <div id="colReportes" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <?php if ($rol === 'Secretaría' || $rol === 'Administrador'): ?>
                        <a href="<?= BASE_URL ?>reporte/permanencia" class="nav-sublink">Por Periodo</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>gestion-asistencias/inicio" class="nav-sublink">Diaria</a>
                </div>
            </div>
        </div>

        <?php if ($rol === 'Administrador' || $rol === 'Tesoreria'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colTesoreria">
                    <i class="fas fa-wallet me-2"></i> Tesorería
                </button>
            </h2>
            <div id="colTesoreria" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>tesoreria/mora" class="nav-sublink">Cobrar / Facturar</a>
                    <a href="<?= BASE_URL ?>tesoreria/historial" class="nav-sublink">Historial Pagos</a>
                    <a href="<?= BASE_URL ?>tesoreria/conceptos" class="nav-sublink">Conceptos Cobro</a>
                    <a href="<?= BASE_URL ?>tesoreria/finanzas" class="nav-sublink">Panel Tesorería</a>
                    <?php if ($rol === 'Administrador'): ?>
                        <a href="<?= BASE_URL ?>tesoreria/configuracion" class="nav-sublink">Config. Tesorería</a>
                        <a href="<?= BASE_URL ?>tesoreria/bonos" class="nav-sublink">Bonos Extra</a>
                        <a href="<?= BASE_URL ?>liquidaciones_historial" class="nav-sublink">Historial Haberes</a>
                        <a href="<?= BASE_URL ?>tesoreria/liquidar-masivo" class="nav-sublink fw-bold">Liq. Masiva</a>
                        <a href="<?= BASE_URL; ?>tesoreria/lotes-pendientes" class="nav-sublink">Autorizar Lotes</a>
                        <a href="<?= BASE_URL; ?>tesoreria/gastos" class="nav-sublink">Gastos</a>
                        <a href="<?= BASE_URL; ?>tesoreria/configurar-layouts" class="nav-sublink text-info">
                            <i class="fas fa- university me-1 small"></i> Layouts Bancarios
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rol === 'Administrador'): ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#colConfig">
                    <i class="fas fa-cogs me-2"></i> Configuración
                </button>
            </h2>
            <div id="colConfig" class="accordion-collapse collapse" data-bs-parent="#accordionSidebar">
                <div class="accordion-body p-0">
                    <a href="<?= BASE_URL ?>usuarios/gestion" class="nav-sublink">Gestión Usuarios</a>
                    <a href="<?= BASE_URL ?>academico/datos" class="nav-sublink">Datos Institución</a>
                    <a href="<?= BASE_URL ?>dashboard_finanzas" class="nav-sublink">Panel Financiero</a>
                    <a href="<?= BASE_URL ?>backup_db" class="nav-sublink">Backup DB</a>
                    <a href="<?= BASE_URL ?>backup_sistema" class="nav-sublink">Backup Archivos</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="sidebar-footer p-3">
        <div class="user-info mb-3 px-2">
            <small class="text-muted d-block text-uppercase">Sesión iniciada:</small>
            <div class="fw-bold text-white small">
                <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nombre_usuario']); ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout" class="btn btn-danger btn-sm w-100 py-2 shadow-sm">
            <i class="fas fa-sign-out-alt me-1"></i> Cerrar Sistema
        </a>
    </div>
</div>