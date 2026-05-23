<?php
session_start();
require 'conexion.php';

// Verificar acceso
require 'seguridad.php'; // Incluimos el guardián

// Solo permitimos a Administradores y Secretarias
verificar_permisos(['Administrador', 'Secretaría']);

if (!isset($_GET['id'])) {
    die("ID de alumno no proporcionado.");
}

$id_alumno = (int)$_GET['id'];

// 1. Obtener datos del alumno, su carrera y la fecha de egreso
$sql = "SELECT a.*, c.nombre_carrera 
        FROM alumnos a 
        JOIN alumnos_carreras ac ON a.id_alumno = ac.id_alumno
        JOIN carreras c ON ac.id_carreras = c.id_carrera
        WHERE a.id_alumno = ? AND a.activo = 2 LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_alumno]);
$datos = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$datos) {
    die("Error: El alumno no existe o aún no ha sido registrado como EGRESADO.");
}

// 2. Calcular promedio general de materias aprobadas
$stmt_prom = $pdo->prepare("SELECT AVG(nota_final) FROM calificaciones WHERE id_alumno = ? AND condicion = 'APROBADO'");
$stmt_prom->execute([$id_alumno]);
$promedio = number_format($stmt_prom->fetchColumn(), 2);

// 3. Función para meses en español
function fechaEnEspanol($fecha) {
    $meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
    $dia = date('d', strtotime($fecha));
    $mes = $meses[date('n', strtotime($fecha)) - 1];
    $anio = date('Y', strtotime($fecha));
    return "$dia de $mes de $anio";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Egreso - <?= htmlspecialchars($datos['apellido']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Estilos para pantalla y para impresión */
        body { font-family: 'Times New Roman', serif; background-color: #f0f0f0; margin: 0; padding: 20px; }
        
        .no-print-area { text-align: center; margin-bottom: 20px; }
        
        .certificado-hoja {
            width: 210mm; /* Tamaño A4 */
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 1cm;
            box-sizing: border-box;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            border: 2px solid #333;
            position: relative;
        }

        /* Marco decorativo */
        .marco-interno {
            border: 5px double #002347;
            padding: 10px;
            height: 100%;
            min-height: 150mm;
        }

        .header { text-align: center; margin-bottom: 20px; }
        .escudo { width: 120px; margin-bottom: 10px; }
        
        .titulo-principal { 
            font-size: 38px; 
            color: #002347; 
            margin: 0; 
            text-transform: uppercase; 
            letter-spacing: 2px;
        }

        .subtitulo { font-size: 20px; margin-top: 5px; color: #555; }

        .cuerpo { text-align: justify; line-height: 2; font-size: 19px; margin-top: 50px; }
        
        .resaltado { font-weight: bold; text-transform: uppercase; }
        
        .nombre-alumno { 
            display: block; 
            font-size: 28px; 
            text-align: center; 
            margin: 30px 0; 
            font-weight: bold; 
            border-bottom: 1px solid #000;
        }

        .footer-firmas { 
            margin-top: 100px; 
            display: flex; 
            justify-content: space-around; 
        }

        .firma { 
            text-align: center; 
            width: 200px; 
            border-top: 1px solid #000; 
            padding-top: 10px; 
            font-size: 14px;
        }

        /* Configuración para imprimir */
        @media print {
            body { background: none; padding: 0; }
            .no-print-area { display: none; }
            .certificado-hoja { box-shadow: none; border: none; margin: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="no-print-area">
        <!--<a href="alumno_expediente.php?id=<?= $id_alumno ?>" class="btn-volver" style="text-decoration:none; color:#333; margin-right:20px;">
            <i class="fas fa-arrow-left"></i> Volver al Expediente
        </a>-->
        <button onclick="window.print()" style="padding: 12px 25px; background: #002347; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            <i class="fas fa-print"></i> IMPRIMIR ACTA OFICIAL
        </button>
    </div>

    <div class="certificado-hoja">
        <div class="marco-interno">
            <div class="header">
                <i class="fas fa-university fa-4x" style="color: #002347; margin-bottom: 15px;"></i>
                <h1 class="titulo-principal">Constancia de Egreso</h1>
                <p class="subtitulo">Institución de Educación Superior Académica</p>
            </div>

            <div class="cuerpo">
                <p>Por la presente, la Dirección de esta Institución certifica que el/la estudiante:</p>
                
                <span class="nombre-alumno"><?= htmlspecialchars($datos['apellido'] . ", " . $datos['nombre']) ?></span>
                
                <p>Documento Nacional de Identidad <span class="resaltado">Nº <?= number_format($datos['dni'], 0, '', '.') ?></span>, 
                ha cumplimentado y aprobado la totalidad de las obligaciones académicas que integran el Plan de Estudios de la carrera:</p>
                
                <h2 style="text-align: center; color: #002347;"><?= htmlspecialchars($datos['nombre_carrera']) ?></h2>
                
                <p>Habiendo registrado su última calificación aprobada en fecha <span class="resaltado"><?= date('d/m/Y', strtotime($datos['fecha_egreso'])) ?></span>, 
                alcanzando un promedio general de calificaciones de <span class="resaltado"><?= $promedio ?></span> puntos.</p>
                
                <p style="margin-top: 40px;">
                    Se expide la presente constancia a pedido del interesado, en la ciudad de [Tu Ciudad], 
                    a los <?= fechaEnEspanol(date('Y-m-d')) ?>.
                </p>
            </div>

            <div class="footer-firmas">
                <div class="firma">Firma y Sello del Secretario/a</div>
                <div class="firma">Firma y Sello del Director/a</div>
            </div>
        </div>
    </div>

</body>
</html>