<?php
// modules/inscripciones/get_materias_alumno.php
require_once '../../core/conexion.php'; 

// Capturamos el UUID
$uuid_alumno = isset($_GET['uuid']) ? $_GET['uuid'] : null;

if ($uuid_alumno) {
    try {
        // 1. Traducimos el UUID a ID interno
        $stmt_id = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE uuid_alumno = ?");
        $stmt_id->execute([$uuid_alumno]);
        $id_alumno = $stmt_id->fetchColumn();

        if (!$id_alumno) {
            echo '<div class="alert alert-warning small">Alumno no identificado.</div>';
            exit;
        }

        /**
         * Lógica de la consulta:
         * 1. Traemos las inscripciones actuales.
         * 2. Excluimos las que ya están aprobadas con final (nota >= 7).
         */
        $sql = "SELECT i.id_inscripcion, e.nombre_espacio, c.nombre_carrera, 
                       e.activo as espacio_activo, c.activo as carrera_activa
                FROM inscripciones_espacios i
                JOIN espacios_curriculares e ON i.id_espacio = e.id_espacio
                JOIN carreras c ON e.id_carrera = c.id_carrera
                LEFT JOIN calificaciones cal ON (
                    cal.id_alumno = i.id_alumno 
                    AND cal.id_espacio = i.id_espacio 
                    AND cal.nota_final >= 7
                )
                WHERE i.id_alumno = ? 
                AND cal.id_calificacion IS NULL
                ORDER BY c.nombre_carrera, e.nombre_espacio";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_alumno]);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($materias) {
            echo '<ul class="list-group list-group-flush shadow-sm rounded border">';
            foreach ($materias as $m) {
                
                $es_vigente = ($m['espacio_activo'] == 1 && $m['carrera_activa'] == 1);
                $estilo_texto = !$es_vigente ? 'text-decoration: line-through; opacity: 0.6;' : '';
                $badge_inactivo = !$es_vigente ? ' <span class="badge bg-warning text-dark" style="font-size: 0.6rem;">NO VIGENTE</span>' : '';

                echo '<li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2 bg-white">
                    <div style="flex: 1; ' . $estilo_texto . '">
                        <small class="text-primary d-block fw-bold" style="font-size: 0.65rem; text-transform: uppercase;">' 
                            . htmlspecialchars($m['nombre_carrera']) . 
                        '</small>
                        <span class="text-dark fw-medium" style="font-size: 0.85rem;">' 
                            . htmlspecialchars($m['nombre_espacio']) . $badge_inactivo .
                        '</span>
                    </div>
                    
                    <button type="button" 
                            class="btn btn-sm btn-link text-danger btn-quitar-materia p-1" 
                            title="Dar de baja inscripción" 
                            data-id="' . $m['id_inscripcion'] . '" 
                            data-nombre="' . htmlspecialchars($m['nombre_espacio'], ENT_QUOTES, 'UTF-8') . '">
                        <i class="fas fa-trash-alt" style="font-size: 1rem;"></i>
                    </button>
                </li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="text-center p-4 bg-light rounded border border-dashed">
                    <i class="fas fa-book-open text-muted mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                    <p class="text-muted small mb-0">Sin cursadas activas.</p>
                  </div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger small">Error: ' . $e->getMessage() . '</div>';
    }
} else {
    echo '<div class="text-center p-3 text-muted small">Seleccione un alumno válido.</div>';
}
?>