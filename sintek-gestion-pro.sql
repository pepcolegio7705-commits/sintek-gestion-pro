-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 31-05-2026 a las 16:01:27
-- Versión del servidor: 9.1.0
-- Versión de PHP: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sintek-gestion-pro`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

DROP TABLE IF EXISTS `alumnos`;
CREATE TABLE IF NOT EXISTS `alumnos` (
  `id_alumno` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL DEFAULT '6',
  `uuid_alumno` char(36) DEFAULT NULL,
  `legajo` varchar(20) NOT NULL,
  `libro_matriz` int DEFAULT NULL,
  `folio_matriz` int DEFAULT NULL,
  `dni` varchar(15) NOT NULL,
  `cuil` varchar(15) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(25) DEFAULT NULL,
  `apellido` varchar(100) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `id_espacio` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecha_egreso` date DEFAULT NULL,
  `pais` varchar(100) DEFAULT 'Argentina',
  `localidad` varchar(100) DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `situacion_laboral` varchar(50) DEFAULT NULL,
  `lugar_trabajo` varchar(255) DEFAULT NULL,
  `hijos` int DEFAULT '0',
  `obra_social` varchar(100) DEFAULT NULL,
  `personas_a_cargo` int DEFAULT '0',
  `pdf_dni` varchar(255) DEFAULT NULL,
  `pdf_titulo` varchar(255) DEFAULT NULL,
  `pdf_aptitud` varchar(255) DEFAULT NULL,
  `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `beca_mensualidad` int DEFAULT '0',
  `beca_matricula` int DEFAULT '0',
  PRIMARY KEY (`id_alumno`),
  UNIQUE KEY `legajo` (`legajo`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `uuid_alumno` (`uuid_alumno`),
  KEY `id_espacio` (`id_espacio`),
  KEY `fk_alumnos_rol` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos_carreras`
--

DROP TABLE IF EXISTS `alumnos_carreras`;
CREATE TABLE IF NOT EXISTS `alumnos_carreras` (
  `id_alumno` int NOT NULL,
  `id_carreras` int NOT NULL,
  `cohorte` int DEFAULT NULL,
  `fecha_insc` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_alumno`,`id_carreras`),
  UNIQUE KEY `alumno_carrera_unico` (`id_alumno`,`id_carreras`),
  KEY `id_alumno` (`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

DROP TABLE IF EXISTS `areas`;
CREATE TABLE IF NOT EXISTS `areas` (
  `id_area` int NOT NULL AUTO_INCREMENT,
  `uuid_area` char(36) DEFAULT NULL,
  `nombre_area` varchar(100) NOT NULL,
  `sueldo_base_area` decimal(12,2) DEFAULT '0.00',
  `descripcion` text,
  PRIMARY KEY (`id_area`),
  UNIQUE KEY `nombre_area` (`nombre_area`),
  UNIQUE KEY `uuid_area` (`uuid_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias`
--

DROP TABLE IF EXISTS `asistencias`;
CREATE TABLE IF NOT EXISTS `asistencias` (
  `id_asistencia` int NOT NULL AUTO_INCREMENT,
  `dni_persona` varchar(15) NOT NULL,
  `nombre_completo` varchar(200) NOT NULL,
  `fecha` date NOT NULL,
  `hora_registro` time NOT NULL,
  `tipo_registro` varchar(10) NOT NULL,
  `tipo_persona` varchar(10) NOT NULL,
  `id_profesor` int DEFAULT NULL,
  `id_alumno` int DEFAULT NULL,
  `id_staff` int DEFAULT NULL,
  PRIMARY KEY (`id_asistencia`),
  KEY `idx_dni_fecha` (`dni_persona`,`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencias_clases`
--

DROP TABLE IF EXISTS `asistencias_clases`;
CREATE TABLE IF NOT EXISTS `asistencias_clases` (
  `id_asistencia_clase` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_espacio` int NOT NULL,
  `id_carrera` int NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('PRESENTE','AUSENTE','TARDE','JUSTIFICADO') DEFAULT 'PRESENTE',
  `observacion` varchar(255) DEFAULT NULL,
  `id_usuario_registro` int DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_asistencia_clase`),
  UNIQUE KEY `asistencia_unica` (`id_alumno`,`id_espacio`,`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bancos_config`
--

DROP TABLE IF EXISTS `bancos_config`;
CREATE TABLE IF NOT EXISTS `bancos_config` (
  `id_banco` int NOT NULL AUTO_INCREMENT,
  `nombre_banco` varchar(100) NOT NULL,
  `tipo_archivo` enum('Fijo','Delimitado') DEFAULT 'Fijo',
  `separador` varchar(5) DEFAULT NULL,
  `usa_encabezado` tinyint(1) DEFAULT '0',
  `usa_pie_pagina` tinyint(1) DEFAULT '0',
  `estado` int DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_banco`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bancos_layout_columnas`
--

DROP TABLE IF EXISTS `bancos_layout_columnas`;
CREATE TABLE IF NOT EXISTS `bancos_layout_columnas` (
  `id_columna` int NOT NULL AUTO_INCREMENT,
  `id_banco` int NOT NULL,
  `seccion` enum('Encabezado','Detalle','Pie') DEFAULT 'Detalle',
  `orden` int NOT NULL,
  `nombre_campo` varchar(100) DEFAULT NULL,
  `origen_dato` varchar(100) DEFAULT NULL,
  `longitud` int NOT NULL,
  `relleno` char(1) DEFAULT '',
  `alineacion` enum('L','R') DEFAULT 'L',
  `tipo_formato` enum('Texto','Monto','Fecha','Constante') DEFAULT 'Texto',
  `valor_constante` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_columna`),
  KEY `idx_banco_columnas` (`id_banco`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bonos_extraordinarios`
--

DROP TABLE IF EXISTS `bonos_extraordinarios`;
CREATE TABLE IF NOT EXISTS `bonos_extraordinarios` (
  `id_bono` int NOT NULL AUTO_INCREMENT,
  `uuid_bono` char(36) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `alcance_destino` enum('Todos','Profesor','Staff','Monotributo') DEFAULT 'Todos',
  `mes_periodo` tinyint NOT NULL,
  `anio_periodo` int NOT NULL,
  `estado` int DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_usuario_creador` int NOT NULL,
  PRIMARY KEY (`id_bono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calificaciones`
--

DROP TABLE IF EXISTS `calificaciones`;
CREATE TABLE IF NOT EXISTS `calificaciones` (
  `id_calificacion` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_espacio` int NOT NULL,
  `id_carrera` int NOT NULL,
  `id_mesa` int DEFAULT NULL,
  `nota_final` decimal(4,2) NOT NULL,
  `libro` varchar(10) DEFAULT NULL,
  `folio` varchar(10) DEFAULT NULL,
  `condicion` enum('APROBADO','REGULAR','DESAPROBADO') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `fecha_registro` date NOT NULL,
  `observacion` text,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_calificacion`),
  KEY `id_espacio` (`id_espacio`),
  KEY `fk_calificaciones_carrera` (`id_carrera`),
  KEY `idx_alumno_espacio_carrera` (`id_alumno`,`id_espacio`,`id_carrera`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carreras`
--

DROP TABLE IF EXISTS `carreras`;
CREATE TABLE IF NOT EXISTS `carreras` (
  `id_carrera` int NOT NULL AUTO_INCREMENT,
  `uuid_carrera` varchar(36) DEFAULT NULL,
  `nombre_carrera` varchar(150) NOT NULL,
  `duracion_anios` int DEFAULT NULL,
  `carga_horaria` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `plan_estudio` varchar(100) NOT NULL,
  `resolucion` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_carrera`),
  UNIQUE KEY `nombre_carrera` (`nombre_carrera`),
  KEY `idx_uuid_carrera` (`uuid_carrera`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `conceptos_pago`
--

DROP TABLE IF EXISTS `conceptos_pago`;
CREATE TABLE IF NOT EXISTS `conceptos_pago` (
  `id_concepto` int NOT NULL AUTO_INCREMENT,
  `uuid_concepto` varchar(36) NOT NULL,
  `nombre_concepto` varchar(100) NOT NULL,
  `monto_sugerido` decimal(10,2) NOT NULL,
  `categoria` enum('Mensualidad','Matricula','Extraordinario','Derecho de Examen') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Mensualidad',
  `id_carrera` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_concepto`),
  KEY `idx_categoria` (`categoria`),
  KEY `idx_carrera_pago` (`id_carrera`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `condiciones_alumno`
--

DROP TABLE IF EXISTS `condiciones_alumno`;
CREATE TABLE IF NOT EXISTS `condiciones_alumno` (
  `id_condicion` int NOT NULL AUTO_INCREMENT,
  `nombre_condicion` varchar(50) NOT NULL,
  `pasa_a_final` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_condicion`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `condiciones_alumno`
--

INSERT INTO `condiciones_alumno` (`id_condicion`, `nombre_condicion`, `pasa_a_final`) VALUES
(1, 'CURSANDO', 0),
(2, 'REGULAR', 1),
(3, 'PROMOCIONADO', 1),
(4, 'LIBRE', 0),
(5, 'ABANDONÓ', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE IF NOT EXISTS `configuracion` (
  `id_config` int NOT NULL DEFAULT '1',
  `nombre_institucion` varchar(100) DEFAULT NULL,
  `cuit` varchar(25) NOT NULL,
  `domicilio` varchar(150) DEFAULT NULL,
  `localidad` varchar(50) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email_contacto` varchar(100) DEFAULT NULL,
  `rector` varchar(100) DEFAULT NULL,
  `vicedirector` varchar(100) NOT NULL,
  `secretario` varchar(100) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `ciclo_lectivo_actual` int DEFAULT NULL,
  `terminos_condiciones` text,
  PRIMARY KEY (`id_config`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id_config`, `nombre_institucion`, `cuit`, `domicilio`, `localidad`, `telefono`, `email_contacto`, `rector`, `vicedirector`, `secretario`, `logo_path`, `ciclo_lectivo_actual`, `terminos_condiciones`) VALUES
(1, 'Instituto Superior - 801', '20-28880532-7', 'Calle Falsa 123, Playa Unión, Chubut', 'Rawson', '(280) 4671799', 'contacto@800edu.ar', 'Prof. Juan Pérez Fernández', 'Lic. Chamorro Andreas', 'Lic. San Felipe Agutina', 'img/logo_default.png', 2026, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_antiguedad_tramos`
--

DROP TABLE IF EXISTS `configuracion_antiguedad_tramos`;
CREATE TABLE IF NOT EXISTS `configuracion_antiguedad_tramos` (
  `id_tramo` int NOT NULL AUTO_INCREMENT,
  `anios_desde` int NOT NULL COMMENT 'Año de inicio del tramo',
  `anios_hasta` int NOT NULL COMMENT 'Año de fin del tramo',
  `porcentaje_aplicado` decimal(5,2) NOT NULL COMMENT 'Porcentaje total a aplicar sobre el básico',
  PRIMARY KEY (`id_tramo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `configuracion_antiguedad_tramos`
--

INSERT INTO `configuracion_antiguedad_tramos` (`id_tramo`, `anios_desde`, `anios_hasta`, `porcentaje_aplicado`) VALUES
(2, 3, 4, 5.00),
(3, 5, 9, 10.00),
(4, 10, 99, 20.00),
(5, 1, 2, 1.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_tesoreria`
--

DROP TABLE IF EXISTS `configuracion_tesoreria`;
CREATE TABLE IF NOT EXISTS `configuracion_tesoreria` (
  `id_config_teso` int NOT NULL DEFAULT '1',
  `uuid_config` varchar(36) DEFAULT NULL,
  `limite_meses_mora` int DEFAULT '2',
  `bloquear_inscripcion_sin_matricula` tinyint(1) DEFAULT '1',
  `bloquear_examen_con_deuda` tinyint(1) DEFAULT '1',
  `cbu_institucion` varchar(22) DEFAULT NULL,
  `banco` varchar(100) NOT NULL,
  `alias_institucion` varchar(50) DEFAULT NULL,
  `titular_cuenta` varchar(100) DEFAULT NULL,
  `cuit_institucion` varchar(11) DEFAULT NULL,
  `telefono` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `matricula_activa` tinyint(1) DEFAULT '1',
  `porcentaje_beca_max` int DEFAULT '100',
  `ciclo_lectivo_actual` int DEFAULT '2024',
  `cuotas_por_ciclo` int DEFAULT '10',
  `valor_cuota_referencia` decimal(10,2) DEFAULT '0.00',
  `valor_hora_catedra` decimal(10,2) DEFAULT '0.00',
  `porcentaje_jubilacion` decimal(5,2) DEFAULT '11.00',
  `porcentaje_obra_social` decimal(5,2) DEFAULT '3.00',
  `tope_imponible_ley` decimal(10,2) DEFAULT '0.00',
  `monto_asignacion_hijo` decimal(10,2) DEFAULT '0.00',
  `monto_seguro_vida` decimal(10,2) DEFAULT '0.00',
  `codigo_convenio` varchar(10) DEFAULT '0000000000',
  `tipo_archivo_banco` varchar(20) DEFAULT 'BNA',
  `porcentaje_antiguedad_anual` decimal(5,2) DEFAULT '2.00',
  `porcentaje_sindicato` decimal(5,2) DEFAULT '2.00',
  `porcentaje_zona_patagonica` decimal(5,2) DEFAULT '40.00',
  `porcentaje_presentismo` decimal(5,2) DEFAULT '10.00',
  PRIMARY KEY (`id_config_teso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `configuracion_tesoreria`
--

INSERT INTO `configuracion_tesoreria` (`id_config_teso`, `uuid_config`, `limite_meses_mora`, `bloquear_inscripcion_sin_matricula`, `bloquear_examen_con_deuda`, `cbu_institucion`, `banco`, `alias_institucion`, `titular_cuenta`, `cuit_institucion`, `telefono`, `email`, `matricula_activa`, `porcentaje_beca_max`, `ciclo_lectivo_actual`, `cuotas_por_ciclo`, `valor_cuota_referencia`, `valor_hora_catedra`, `porcentaje_jubilacion`, `porcentaje_obra_social`, `tope_imponible_ley`, `monto_asignacion_hijo`, `monto_seguro_vida`, `codigo_convenio`, `tipo_archivo_banco`, `porcentaje_antiguedad_anual`, `porcentaje_sindicato`, `porcentaje_zona_patagonica`, `porcentaje_presentismo`) VALUES
(1, 'b4704543-1d4b-11f1-90fc-0a0027000006', 2, 1, 0, '0830021831003462090019', 'Banco del Chubut S.A.', 'sintek.mp', 'sintekCorp', '20288805327', '2804671799', 'sintek@info.com', 1, 100, 2026, 10, 5875.00, 5870.00, 11.00, 3.00, 30.00, 17500.00, 0.00, '0000000000', 'BNA', 10.00, 2.00, 15.00, 10.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `control_planillas`
--

DROP TABLE IF EXISTS `control_planillas`;
CREATE TABLE IF NOT EXISTS `control_planillas` (
  `id_control` int NOT NULL AUTO_INCREMENT,
  `id_espacio` int NOT NULL,
  `ciclo_lectivo` int NOT NULL,
  `estado` enum('Abierta','Cerrada') DEFAULT 'Abierta',
  `fecha_cierre` datetime DEFAULT NULL,
  `usuario_cierre` int DEFAULT NULL,
  PRIMARY KEY (`id_control`),
  UNIQUE KEY `id_espacio` (`id_espacio`,`ciclo_lectivo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursadas_notas`
--

DROP TABLE IF EXISTS `cursadas_notas`;
CREATE TABLE IF NOT EXISTS `cursadas_notas` (
  `id_cursada_nota` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_carrera` int NOT NULL,
  `id_espacio` int NOT NULL,
  `ciclo_lectivo` int NOT NULL,
  `parcial_1` decimal(4,2) DEFAULT NULL,
  `parcial_2` decimal(4,2) DEFAULT NULL,
  `recuperatorio` decimal(4,2) NOT NULL,
  `nota_final_cursada` decimal(4,2) DEFAULT NULL,
  `asistencia` int DEFAULT '0',
  `id_condicion` int DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cursada_nota`),
  UNIQUE KEY `id_alumno` (`id_alumno`,`id_espacio`,`ciclo_lectivo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `egresados`
--

DROP TABLE IF EXISTS `egresados`;
CREATE TABLE IF NOT EXISTS `egresados` (
  `id_egreso` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_carrera` int NOT NULL,
  `fecha_egreso` date NOT NULL,
  `promedio_final` decimal(4,2) DEFAULT NULL,
  `cohorte_ingreso` int DEFAULT NULL,
  PRIMARY KEY (`id_egreso`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_carrera` (`id_carrera`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entidades_pagos_terceros`
--

DROP TABLE IF EXISTS `entidades_pagos_terceros`;
CREATE TABLE IF NOT EXISTS `entidades_pagos_terceros` (
  `id_entidad` int NOT NULL AUTO_INCREMENT,
  `nombre_entidad` varchar(100) NOT NULL,
  `cuit_entidad` varchar(11) DEFAULT NULL,
  `cbu_destino` varchar(22) DEFAULT NULL,
  `porcentaje_retencion` decimal(5,2) DEFAULT '0.00',
  `tipo_entidad` enum('Sindicato','Seguro','Caja Profesional','Otro') DEFAULT 'Sindicato',
  `categoria_afectada` enum('Profesor','Staff','Todos') DEFAULT 'Todos',
  `estado` tinyint DEFAULT '1',
  PRIMARY KEY (`id_entidad`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `espacios_curriculares`
--

DROP TABLE IF EXISTS `espacios_curriculares`;
CREATE TABLE IF NOT EXISTS `espacios_curriculares` (
  `id_espacio` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) DEFAULT NULL,
  `uuid_espacio` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `nombre_espacio` varchar(150) NOT NULL,
  `tipo_cursada` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carga_horaria` int DEFAULT NULL,
  `id_carrera` int NOT NULL,
  `anio_cursada` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_espacio`),
  UNIQUE KEY `uq_espacio_carrera` (`nombre_espacio`,`id_carrera`),
  KEY `id_carrera` (`id_carrera`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `examenes_notas`
--

DROP TABLE IF EXISTS `examenes_notas`;
CREATE TABLE IF NOT EXISTS `examenes_notas` (
  `id_examen_nota` int NOT NULL AUTO_INCREMENT,
  `id_mesa` int NOT NULL,
  `id_alumno` int NOT NULL,
  `id_inscripcion_mesa` int NOT NULL,
  `nota_escrito` decimal(4,2) DEFAULT NULL,
  `nota_oral` decimal(4,2) DEFAULT NULL,
  `nota_final` decimal(4,2) NOT NULL,
  `resultado` enum('Aprobado','Desaprobado','Ausente') COLLATE utf8mb4_unicode_ci NOT NULL,
  `libro` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folio` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `id_usuario_carga` int NOT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_examen_nota`),
  KEY `fk_nota_mesa` (`id_mesa`),
  KEY `fk_nota_alumno` (`id_alumno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facturas`
--

DROP TABLE IF EXISTS `facturas`;
CREATE TABLE IF NOT EXISTS `facturas` (
  `id_factura` int NOT NULL AUTO_INCREMENT,
  `uuid_factura` varchar(36) NOT NULL,
  `id_alumno` int NOT NULL,
  `nro_factura` varchar(20) DEFAULT NULL,
  `fecha_emision` datetime DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL,
  `id_modo_pago` int DEFAULT NULL,
  `nro_transaccion` varchar(50) DEFAULT NULL,
  `estado` enum('Pagado','Anulado') DEFAULT 'Pagado',
  `motivo_anulacion` text,
  `fecha_anulacion` datetime DEFAULT NULL,
  `usuario_anulo` varchar(100) DEFAULT NULL,
  `usuario_emisor` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_factura`),
  UNIQUE KEY `idx_uuid_factura` (`uuid_factura`),
  UNIQUE KEY `nro_factura` (`nro_factura`),
  KEY `fk_modo_pago` (`id_modo_pago`),
  KEY `idx_alumno_estado` (`id_alumno`,`estado`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura_detalle`
--

DROP TABLE IF EXISTS `factura_detalle`;
CREATE TABLE IF NOT EXISTS `factura_detalle` (
  `id_detalle` int NOT NULL AUTO_INCREMENT,
  `id_factura` int NOT NULL,
  `id_concepto` int NOT NULL,
  `monto_cobrado` decimal(10,2) NOT NULL,
  `monto_descuento` decimal(10,2) DEFAULT '0.00',
  `mes_correspondiente` int DEFAULT NULL,
  `anio_lectivo` int NOT NULL DEFAULT '2026',
  `id_referencia_mesa` int DEFAULT NULL,
  `estado` enum('Pagado','Anulado') DEFAULT 'Pagado',
  PRIMARY KEY (`id_detalle`),
  KEY `id_concepto` (`id_concepto`),
  KEY `id_referencia_mesa` (`id_referencia_mesa`),
  KEY `idx_factura_concepto` (`id_factura`,`id_concepto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos`
--

DROP TABLE IF EXISTS `gastos`;
CREATE TABLE IF NOT EXISTS `gastos` (
  `id_gasto` int NOT NULL AUTO_INCREMENT,
  `id_cat_gasto` int DEFAULT NULL,
  `descripcion` text,
  `motivo_anulacion` text,
  `monto` decimal(10,2) NOT NULL,
  `fecha_gasto` date NOT NULL,
  `id_modo_pago` int DEFAULT NULL,
  `estado` enum('Pagado','Anulado','Pendiente') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Pagado',
  `usuario_anulo` varchar(50) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `usuario_registro` varchar(50) DEFAULT NULL,
  `id_referencia_pago` int DEFAULT NULL,
  PRIMARY KEY (`id_gasto`),
  KEY `id_cat_gasto` (`id_cat_gasto`),
  KEY `id_referencia_pago` (`id_referencia_pago`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `gastos_categorias`
--

DROP TABLE IF EXISTS `gastos_categorias`;
CREATE TABLE IF NOT EXISTS `gastos_categorias` (
  `id_cat_gasto` int NOT NULL AUTO_INCREMENT,
  `nombre_categoria` varchar(100) NOT NULL,
  PRIMARY KEY (`id_cat_gasto`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_liquidaciones`
--

DROP TABLE IF EXISTS `historial_liquidaciones`;
CREATE TABLE IF NOT EXISTS `historial_liquidaciones` (
  `id_historial` int NOT NULL AUTO_INCREMENT,
  `id_lote` int NOT NULL,
  `id_persona` int NOT NULL,
  `tipo_persona` enum('Profesor','Staff') DEFAULT NULL,
  `apellido_nombre_hist` varchar(255) DEFAULT NULL,
  `dni_hist` varchar(20) DEFAULT NULL,
  `cbu_hist` varchar(22) DEFAULT NULL,
  `monto_bruto` decimal(15,2) DEFAULT NULL,
  `monto_retenciones` decimal(15,2) DEFAULT NULL,
  `monto_neto` decimal(15,2) DEFAULT NULL,
  `estado_final_lote` enum('Procesado','Anulado') DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_historial`),
  KEY `id_lote` (`id_lote`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_espacios`
--

DROP TABLE IF EXISTS `inscripciones_espacios`;
CREATE TABLE IF NOT EXISTS `inscripciones_espacios` (
  `id_inscripcion` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_espacio` int NOT NULL,
  `fecha_inscripcion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_inscripcion`),
  KEY `id_alumno` (`id_alumno`),
  KEY `id_espacio` (`id_espacio`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones_examen`
--

DROP TABLE IF EXISTS `inscripciones_examen`;
CREATE TABLE IF NOT EXISTS `inscripciones_examen` (
  `id_inscripcion` int NOT NULL AUTO_INCREMENT,
  `id_alumno` int NOT NULL,
  `id_mesa` int NOT NULL,
  `fecha_inscripcion` datetime DEFAULT CURRENT_TIMESTAMP,
  `condicion` enum('Regular','Libre') DEFAULT 'Regular',
  `asistencia` enum('Presente','Ausente','Pendiente') DEFAULT 'Pendiente',
  `nota_final` decimal(4,2) DEFAULT NULL,
  PRIMARY KEY (`id_inscripcion`),
  UNIQUE KEY `unico_registro` (`id_alumno`,`id_mesa`),
  KEY `id_mesa` (`id_mesa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `liquidaciones_haberes`
--

DROP TABLE IF EXISTS `liquidaciones_haberes`;
CREATE TABLE IF NOT EXISTS `liquidaciones_haberes` (
  `id_liquidacion` int NOT NULL AUTO_INCREMENT,
  `uuid_liquidacion` varchar(36) DEFAULT NULL,
  `id_persona` int NOT NULL,
  `tipo_persona` enum('Profesor','Staff') NOT NULL,
  `mes_liquidado` int NOT NULL,
  `anio_liquidado` int NOT NULL,
  `total_horas_catedra` int DEFAULT NULL,
  `valor_hora_aplicado` decimal(10,2) NOT NULL,
  `monto_bruto` decimal(10,2) NOT NULL,
  `monto_antiguedad_aplicado` decimal(10,2) DEFAULT NULL,
  `monto_asignacion_hijos` decimal(10,2) DEFAULT NULL,
  `monto_zona_patagonica` decimal(10,2) DEFAULT '0.00',
  `monto_presentismo` decimal(10,2) DEFAULT '0.00',
  `monto_sindicato` decimal(10,2) DEFAULT '0.00',
  `monto_bonos_extraordinarios` decimal(10,2) DEFAULT '0.00',
  `monto_retenciones_ley` decimal(10,2) DEFAULT NULL,
  `monto_retenciones_judiciales` decimal(10,2) DEFAULT NULL,
  `monto_bonificaciones` decimal(10,2) DEFAULT '0.00',
  `monto_retenciones` decimal(10,2) DEFAULT '0.00',
  `id_lote` int DEFAULT NULL,
  `monto_neto` decimal(10,2) NOT NULL,
  `id_modo_pago` int NOT NULL,
  `nro_cheque` varchar(50) DEFAULT NULL,
  `nro_transferencia` varchar(50) NOT NULL,
  `banco_emisor` varchar(100) DEFAULT NULL,
  `estado` enum('Pendiente','Procesado','Anulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Procesado',
  `fecha_pago` datetime DEFAULT CURRENT_TIMESTAMP,
  `usuario_registro` varchar(50) DEFAULT NULL,
  `observaciones` text,
  `id_gasto_vinculado` int DEFAULT NULL,
  PRIMARY KEY (`id_liquidacion`),
  UNIQUE KEY `uuid_liquidacion` (`uuid_liquidacion`),
  KEY `id_profesor` (`id_persona`),
  KEY `mes_liquidado` (`mes_liquidado`,`anio_liquidado`),
  KEY `estado` (`estado`),
  KEY `id_persona` (`id_persona`,`tipo_persona`),
  KEY `mes_liquidado_2` (`mes_liquidado`,`anio_liquidado`),
  KEY `fk_liquidacion_lote` (`id_lote`),
  KEY `idx_estado_liq` (`estado`),
  KEY `id_lote` (`id_lote`),
  KEY `id_persona_2` (`id_persona`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `liquidaciones_terceros`
--

DROP TABLE IF EXISTS `liquidaciones_terceros`;
CREATE TABLE IF NOT EXISTS `liquidaciones_terceros` (
  `id_pago_tercero` int NOT NULL AUTO_INCREMENT,
  `uuid_pago` char(36) NOT NULL,
  `id_lote` int NOT NULL,
  `id_liquidacion_origen` int NOT NULL COMMENT 'ID de la liquidacion del agente de donde proviene el descuento',
  `id_persona` int NOT NULL,
  `tipo_persona` varchar(50) NOT NULL,
  `beneficiario_nombre` varchar(150) NOT NULL,
  `cuit_beneficiario` varchar(11) NOT NULL,
  `cbu_destino` varchar(22) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `concepto` varchar(255) DEFAULT NULL COMMENT 'Ej: Cuota Alimentaria Exp. 123/24',
  `estado` enum('Pendiente','Procesado','Anulado') DEFAULT 'Pendiente',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago_tercero`),
  UNIQUE KEY `uuid_pago` (`uuid_pago`),
  KEY `idx_lote_terceros` (`id_lote`),
  KEY `idx_liquidacion_origen` (`id_liquidacion_origen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_autorizaciones`
--

DROP TABLE IF EXISTS `logs_autorizaciones`;
CREATE TABLE IF NOT EXISTS `logs_autorizaciones` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_usuario_autorizo` int NOT NULL,
  `modulo` varchar(100) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `motivo` text NOT NULL,
  `detalles` text,
  `fecha_hora` datetime NOT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_usuario_autorizo` (`id_usuario_autorizo`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_seguridad`
--

DROP TABLE IF EXISTS `logs_seguridad`;
CREATE TABLE IF NOT EXISTS `logs_seguridad` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int DEFAULT NULL,
  `evento` varchar(100) NOT NULL,
  `detalle_data` text,
  `url_peticion` text,
  `ip_origen` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `fecha_hora` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_tesoreria`
--

DROP TABLE IF EXISTS `log_tesoreria`;
CREATE TABLE IF NOT EXISTS `log_tesoreria` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `uuid_log` char(36) NOT NULL,
  `fecha_hora` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `id_usuario` int NOT NULL,
  `operacion` varchar(100) NOT NULL,
  `detalles` text,
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lotes_liquidaciones`
--

DROP TABLE IF EXISTS `lotes_liquidaciones`;
CREATE TABLE IF NOT EXISTS `lotes_liquidaciones` (
  `id_lote` int NOT NULL AUTO_INCREMENT,
  `uuid_lote` char(36) NOT NULL,
  `tipo_lote` enum('Bancario','Efectivo_Cheque') NOT NULL,
  `mes_periodo` tinyint NOT NULL,
  `anio_periodo` int NOT NULL,
  `total_monto` decimal(15,2) NOT NULL,
  `id_banco` int DEFAULT NULL,
  `cantidad_agentes` int NOT NULL,
  `estado` enum('Pendiente','Procesado','Anulado','Rechazo Banco') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Pendiente',
  `id_user_autoriza` int DEFAULT NULL,
  `fecha_autorizacion` datetime DEFAULT NULL,
  `motivo_anulacion` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `observaciones` text NOT NULL,
  `hash_control` varchar(64) DEFAULT NULL,
  `fecha_descarga` datetime DEFAULT NULL,
  `id_user_descarga` int DEFAULT NULL,
  `descargado_txt` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id_lote`),
  KEY `fk_lote_banco` (`id_banco`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mesas_examenes`
--

DROP TABLE IF EXISTS `mesas_examenes`;
CREATE TABLE IF NOT EXISTS `mesas_examenes` (
  `id_mesa` int NOT NULL AUTO_INCREMENT,
  `uuid_mesa` varchar(36) DEFAULT NULL,
  `id_espacio` int NOT NULL,
  `id_carrera` int NOT NULL,
  `llamado` varchar(50) NOT NULL,
  `ciclo_lectivo` int NOT NULL,
  `fecha_examen` date NOT NULL,
  `hora_examen` time DEFAULT NULL,
  `id_presidente` int DEFAULT NULL,
  `id_vocal1` int DEFAULT NULL,
  `id_vocal2` int DEFAULT NULL,
  `estado` enum('Abierta','Cerrada') DEFAULT 'Abierta',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio_inscripcion` date DEFAULT NULL,
  `fecha_fin_inscripcion` date DEFAULT NULL,
  PRIMARY KEY (`id_mesa`),
  UNIQUE KEY `uuid_mesa` (`uuid_mesa`),
  KEY `id_espacio` (`id_espacio`),
  KEY `id_carrera` (`id_carrera`),
  KEY `id_presidente` (`id_presidente`),
  KEY `id_vocal1` (`id_vocal1`),
  KEY `id_vocal2` (`id_vocal2`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modos_pago`
--

DROP TABLE IF EXISTS `modos_pago`;
CREATE TABLE IF NOT EXISTS `modos_pago` (
  `id_modo` int NOT NULL AUTO_INCREMENT,
  `nombre_modo` varchar(50) NOT NULL,
  `detalles_pago` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_modo`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personal_staff`
--

DROP TABLE IF EXISTS `personal_staff`;
CREATE TABLE IF NOT EXISTS `personal_staff` (
  `id_staff` int NOT NULL AUTO_INCREMENT,
  `uuid_staff` varchar(36) DEFAULT NULL,
  `id_usuario` int DEFAULT NULL,
  `legajo` varchar(20) DEFAULT NULL,
  `dni` varchar(15) DEFAULT NULL,
  `cuil` varchar(20) DEFAULT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `nacionalidad` varchar(50) DEFAULT 'Argentina',
  `telefono` varchar(30) DEFAULT NULL,
  `email_personal` varchar(100) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `localidad` varchar(50) NOT NULL,
  `id_area` int DEFAULT NULL,
  `sueldo_base` decimal(10,2) DEFAULT '0.00',
  `cbu` varchar(22) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `cantidad_hijos` int DEFAULT '0',
  `hijos_verificados` tinyint(1) DEFAULT '0',
  `activo` tinyint(1) DEFAULT '1',
  `ruta_pdf_dni` varchar(255) DEFAULT NULL,
  `ruta_pdf_cv` varchar(255) DEFAULT NULL,
  `ruta_pdf_titulo` varchar(255) DEFAULT NULL,
  `ruta_pdf_hijos` varchar(255) DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `pago_banco` tinyint(1) DEFAULT '1',
  `tipo_contratacion` enum('Relacion_Dependencia','Monotributista') DEFAULT 'Relacion_Dependencia',
  `afiliado_sindicato` tinyint(1) DEFAULT '0',
  `cobra_presentismo` tinyint(1) DEFAULT '1',
  `id_entidad_sindicato` int DEFAULT NULL,
  PRIMARY KEY (`id_staff`),
  UNIQUE KEY `legajo` (`legajo`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `uuid_staff` (`uuid_staff`),
  KEY `apellido` (`apellido`),
  KEY `nombre` (`nombre`),
  KEY `dni_2` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

DROP TABLE IF EXISTS `profesores`;
CREATE TABLE IF NOT EXISTS `profesores` (
  `id_profesor` int NOT NULL AUTO_INCREMENT,
  `id_rol` int NOT NULL DEFAULT '3',
  `uuid_profesor` char(36) DEFAULT NULL,
  `legajo` varchar(20) DEFAULT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `nombre_usuario` varchar(50) DEFAULT NULL,
  `cbu` varchar(22) DEFAULT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `cuil` varchar(15) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` varchar(20) DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `localidad` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `titulo_principal` varchar(255) DEFAULT NULL,
  `id_area` int DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `observaciones` text,
  `ruta_pdf_dni` varchar(255) DEFAULT NULL,
  `ruta_pdf_cv` varchar(255) DEFAULT NULL,
  `ruta_pdf_titulo` varchar(255) DEFAULT NULL,
  `ruta_pdf_hijos` varchar(255) DEFAULT NULL,
  `cantidad_hijos` int DEFAULT '0',
  `hijos_verificados` tinyint(1) DEFAULT '0',
  `pago_banco` tinyint(1) DEFAULT '1',
  `afiliado_sindicato` tinyint(1) DEFAULT '0',
  `cobra_presentismo` tinyint(1) DEFAULT '1',
  `id_entidad_sindicato` int DEFAULT NULL,
  PRIMARY KEY (`id_profesor`),
  UNIQUE KEY `legajo` (`legajo`),
  UNIQUE KEY `dni` (`dni`),
  UNIQUE KEY `uuid_profesor` (`uuid_profesor`),
  KEY `id_area` (`id_area`),
  KEY `dni_2` (`dni`),
  KEY `fk_profesores_rol` (`id_rol`),
  KEY `apellido` (`apellido`),
  KEY `nombre` (`nombre`),
  KEY `dni_3` (`dni`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores_espacios`
--

DROP TABLE IF EXISTS `profesores_espacios`;
CREATE TABLE IF NOT EXISTS `profesores_espacios` (
  `id_profesor_espacio` int NOT NULL AUTO_INCREMENT,
  `id_profesor` int NOT NULL,
  `id_espacio` int NOT NULL,
  `horas_catedra` int DEFAULT '0',
  PRIMARY KEY (`id_profesor_espacio`),
  UNIQUE KEY `uk_profesor_espacio` (`id_profesor`,`id_espacio`),
  KEY `id_espacio` (`id_espacio`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores_titulos`
--

DROP TABLE IF EXISTS `profesores_titulos`;
CREATE TABLE IF NOT EXISTS `profesores_titulos` (
  `id_titulo` int NOT NULL AUTO_INCREMENT,
  `id_profesor` int NOT NULL,
  `nombre_titulo` varchar(200) NOT NULL,
  `institucion` varchar(200) DEFAULT NULL,
  `anio_egreso` int DEFAULT NULL,
  `ruta_pdf_titulo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_titulo`),
  KEY `id_profesor` (`id_profesor`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retenciones_judiciales`
--

DROP TABLE IF EXISTS `retenciones_judiciales`;
CREATE TABLE IF NOT EXISTS `retenciones_judiciales` (
  `id_retencion` int NOT NULL AUTO_INCREMENT,
  `id_persona` int NOT NULL,
  `tipo_persona` enum('Staff','Profesor') NOT NULL,
  `nro_expediente` varchar(100) NOT NULL,
  `beneficiario_nombre` varchar(200) DEFAULT NULL,
  `cuit_beneficiario` varchar(11) NOT NULL,
  `tipo_calculo` enum('Porcentaje','Monto Fijo') NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `cbu_destino` varchar(22) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_retencion`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'Administrador'),
(6, 'Alumno'),
(5, 'Jefe Tesoreria'),
(4, 'Preceptor'),
(3, 'Profesor'),
(2, 'Secretaría');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `Apellido` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `id_rol` int DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `id_rol` (`id_rol`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre_usuario`, `password`, `Apellido`, `email`, `id_rol`, `estado`) VALUES
(1, 'administrador', '$2y$10$v/gK5jKBsn3OitwBiV3EnOpcG7zzZzQ.Tvgl2Nwoa6DltwNpUL4r2', 'Wiener Jorge', 'admin@ejemplo.com', 1, 1);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD CONSTRAINT `fk_alumnos_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `conceptos_pago`
--
ALTER TABLE `conceptos_pago`
  ADD CONSTRAINT `fk_concepto_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`);

--
-- Filtros para la tabla `examenes_notas`
--
ALTER TABLE `examenes_notas`
  ADD CONSTRAINT `fk_nota_alumno` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  ADD CONSTRAINT `fk_nota_mesa` FOREIGN KEY (`id_mesa`) REFERENCES `mesas_examenes` (`id_mesa`);

--
-- Filtros para la tabla `facturas`
--
ALTER TABLE `facturas`
  ADD CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  ADD CONSTRAINT `fk_modo_pago` FOREIGN KEY (`id_modo_pago`) REFERENCES `modos_pago` (`id_modo`) ON DELETE SET NULL;

--
-- Filtros para la tabla `factura_detalle`
--
ALTER TABLE `factura_detalle`
  ADD CONSTRAINT `factura_detalle_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `facturas` (`id_factura`) ON DELETE CASCADE,
  ADD CONSTRAINT `factura_detalle_ibfk_2` FOREIGN KEY (`id_concepto`) REFERENCES `conceptos_pago` (`id_concepto`);

--
-- Filtros para la tabla `inscripciones_examen`
--
ALTER TABLE `inscripciones_examen`
  ADD CONSTRAINT `inscripciones_examen_ibfk_1` FOREIGN KEY (`id_alumno`) REFERENCES `alumnos` (`id_alumno`),
  ADD CONSTRAINT `inscripciones_examen_ibfk_2` FOREIGN KEY (`id_mesa`) REFERENCES `mesas_examenes` (`id_mesa`) ON DELETE CASCADE;

--
-- Filtros para la tabla `log_tesoreria`
--
ALTER TABLE `log_tesoreria`
  ADD CONSTRAINT `log_tesoreria_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `lotes_liquidaciones`
--
ALTER TABLE `lotes_liquidaciones`
  ADD CONSTRAINT `fk_lote_banco` FOREIGN KEY (`id_banco`) REFERENCES `bancos_config` (`id_banco`);

--
-- Filtros para la tabla `mesas_examenes`
--
ALTER TABLE `mesas_examenes`
  ADD CONSTRAINT `mesas_examenes_ibfk_1` FOREIGN KEY (`id_espacio`) REFERENCES `espacios_curriculares` (`id_espacio`),
  ADD CONSTRAINT `mesas_examenes_ibfk_2` FOREIGN KEY (`id_carrera`) REFERENCES `carreras` (`id_carrera`),
  ADD CONSTRAINT `mesas_examenes_ibfk_3` FOREIGN KEY (`id_presidente`) REFERENCES `profesores` (`id_profesor`),
  ADD CONSTRAINT `mesas_examenes_ibfk_4` FOREIGN KEY (`id_vocal1`) REFERENCES `profesores` (`id_profesor`),
  ADD CONSTRAINT `mesas_examenes_ibfk_5` FOREIGN KEY (`id_vocal2`) REFERENCES `profesores` (`id_profesor`);

--
-- Filtros para la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD CONSTRAINT `fk_profesores_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
