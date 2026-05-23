<?php
    // modules/inscripciones/get_materias_por_carrera.php
    require_once '../../core/conexion.php';

    /*function u($txt) {
        return mb_convert_encoding($txt ?? '', 'ISO-8859-1', 'UTF-8');
    }*/

    $id_carrera = isset($_POST['id_carrera']) ? (int)$_POST['id_carrera'] : 0;
    $uuid_alumno = isset($_POST['uuid_alumno']) ? $_POST['uuid_alumno'] : null;

    if ($id_carrera === 0 || !$uuid_alumno) {
        echo '<div class="alert alert-warning small">Error: Datos de alumno o carrera no válidos.</div>';
        exit;
    }

    try {
        $stmt_id = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE uuid_alumno = ?");
        $stmt_id->execute([$uuid_alumno]);
        $id_alumno = $stmt_id->fetchColumn();

        if (!$id_alumno) {
            echo '<div class="alert alert-danger small">Alumno no encontrado.</div>';
            exit;
        }

        // 1. VALIDACIÓN DE MATRÍCULA (Configuración de Tesorería)
        $sql_conf = $pdo->query("SELECT * FROM configuracion_tesoreria WHERE id_config_teso = 1");
        $conf_teso = $sql_conf->fetch(PDO::FETCH_ASSOC);

        $puede_inscribir = true;
        $mensaje_bloqueo = "";

        if ($conf_teso['bloquear_inscripcion_sin_matricula'] == 1) {
            $anio_actual = date('Y');
            $stmtMatri = $pdo->prepare("SELECT COUNT(*) FROM factura_detalle fd 
                                        JOIN facturas f ON fd.id_factura = f.id_factura 
                                        JOIN conceptos_pago cp ON fd.id_concepto = cp.id_concepto 
                                        WHERE f.id_alumno = ? 
                                        AND cp.categoria = 'Matricula' 
                                        AND f.estado = 'Pagado' 
                                        AND fd.anio_lectivo = ?");
            $stmtMatri->execute([$id_alumno, $anio_actual]);
            
            if ($stmtMatri->fetchColumn() == 0) {
                $puede_inscribir = false;
                $mensaje_bloqueo = "El alumno no registra el pago de la Matrícula $anio_actual.";
            }
        }

        // 2. MATERIAS DISPONIBLES (Lógica de notas integrada)
        // Regla: Se oculta si ya está inscripto actualmente O si ya la aprobó con >= 7
        $sql_disponibles = "SELECT id_espacio, nombre_espacio, anio_cursada 
                            FROM espacios_curriculares 
                            WHERE id_carrera = ? AND activo = 1 
                            -- Filtro A: No mostrar si ya está inscripto en esta materia (evitar duplicados)
                            AND id_espacio NOT IN (SELECT id_espacio FROM inscripciones_espacios WHERE id_alumno = ?)
                            -- Filtro B: No mostrar si ya la aprobó con nota 7 o superior (Final aprobado)
                            AND id_espacio NOT IN (SELECT id_espacio FROM calificaciones WHERE id_alumno = ? AND nota_final >= 7)
                            ORDER BY anio_cursada, nombre_espacio";

        $stmt_disp = $pdo->prepare($sql_disponibles);
        $stmt_disp->execute([$id_carrera, $id_alumno, $id_alumno]);
        $materias_disp = $stmt_disp->fetchAll(PDO::FETCH_ASSOC);

        // 3. HISTORIAL DE APROBADAS (Para información del administrativo)
        $sql_aprobadas = "SELECT e.nombre_espacio, e.anio_cursada, c.nota_final, c.fecha_registro
                        FROM calificaciones c
                        JOIN espacios_curriculares e ON c.id_espacio = e.id_espacio
                        WHERE c.id_alumno = ? AND c.id_carrera = ? AND c.nota_final >= 6
                        ORDER BY e.anio_cursada, e.nombre_espacio";

        $stmt_aprob = $pdo->prepare($sql_aprobadas);
        $stmt_aprob->execute([$id_alumno, $id_carrera]);
        $materias_aprob = $stmt_aprob->fetchAll(PDO::FETCH_ASSOC);

        // --- RENDERIZADO DE LA VISTA ---
        echo '<h6 class="text-primary fw-bold mb-3 small text-uppercase"><i class="fas fa-plus-circle me-2"></i>Materias Disponibles</h6>';

        if (!$puede_inscribir) {
            echo '<div class="alert alert-danger border-0 shadow-sm mb-4">
                    <i class="fas fa-lock me-2"></i>'.htmlspecialchars($mensaje_bloqueo).'
                  </div>';
        } else if ($materias_disp) {
            echo '<div class="row px-2">';
            foreach ($materias_disp as $m) {
                echo '<div class="col-12 mb-1">
                        <div class="form-check custom-checkbox py-1">
                            <input class="form-check-input border-primary materia-checkbox" type="checkbox" name="materias[]" value="'.$m['id_espacio'].'" id="mat'.$m['id_espacio'].'">
                            <label class="form-check-label ms-2 d-flex justify-content-between w-100" for="mat'.$m['id_espacio'].'">
                                <span class="small">'.htmlspecialchars($m['nombre_espacio']).'</span>
                                <span class="badge bg-light text-dark border" style="font-size:0.65rem;">'.$m['anio_cursada'].'° Año</span>
                            </label>
                        </div>
                    </div>';
            }
            echo '</div>';
        } else {
            echo '<div class="alert alert-light border text-center py-3 text-muted small">Sin materias para inscribir (Todo aprobado o ya inscripto).</div>';
        }

        echo '<hr class="my-4">';

        // PANEL DE HISTORIAL (6 o más para ver Regularidades y Aprobadas)
        echo '<h6 class="text-success fw-bold mb-3 small text-uppercase"><i class="fas fa-certificate me-2"></i>Situación Académica (Aprobadas/Regulares)</h6>';
        if ($materias_aprob) {
            echo '<div class="list-group list-group-flush border rounded">';
            foreach ($materias_aprob as $ma) {
                $fecha = date('d/m/Y', strtotime($ma['fecha_registro']));
                $color_nota = ($ma['nota_final'] >= 7) ? 'bg-success' : 'bg-primary';
                $estado_txt = ($ma['nota_final'] >= 7) ? 'APROBADA' : 'REGULAR';

                echo '<div class="list-group-item d-flex justify-content-between align-items-center py-2 bg-white">
                        <div style="line-height:1.2">
                            <span class="small fw-bold">'.htmlspecialchars($ma['nombre_espacio']).'</span><br>
                            <small class="text-muted" style="font-size:0.7rem;">'.$estado_txt.' - '.$fecha.'</small>
                        </div>
                        <span class="badge '.$color_nota.'">'.$ma['nota_final'].'</span>
                    </div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-center text-muted small fst-italic">No registra materias con nota >= 6.</p>';
        }

    } catch (Exception $e) {
        echo '<div class="alert alert-danger small">Error: '.$e->getMessage().'</div>';
    }
?>