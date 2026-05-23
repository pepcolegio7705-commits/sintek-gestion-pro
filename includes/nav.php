<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="dashboard.php">Sistema Asistencias</a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="areas_abm.php">ABM Áreas</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profesores_abm.php">ABM Profesores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="profesores_abm.php">ABM Profesores</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="carreras_abm.php">ABM Carreras</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reportes.php">Reportes</a>
            </li>
        </ul>
        <span class="navbar-text mr-3">
            Admin (<?php echo htmlspecialchars($_SESSION['nombre_usuario']); ?>)
        </span>
        <a href="./logout.php" class="btn btn-danger my-2 my-sm-0">Cerrar Sesión</a>
    </div>
</nav>