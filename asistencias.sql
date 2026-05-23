-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 26-02-2023 a las 20:59:54
-- Versión del servidor: 5.7.40
-- Versión de PHP: 8.0.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `asistencias`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
CREATE TABLE IF NOT EXISTS `asistencia` (
  `idasistencia` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_persona` int(15) NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo` varchar(45) COLLATE utf8_bin NOT NULL,
  `fecha` date NOT NULL,
  PRIMARY KEY (`idasistencia`),
  KEY `codigo_persona` (`codigo_persona`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`idasistencia`, `codigo_persona`, `fecha_hora`, `tipo`, `fecha`) VALUES
(1, 28880532, '2023-02-22 20:11:47', 'Entrada', '2023-02-22'),
(2, 28880532, '2023-02-22 20:12:09', 'Salida', '2023-02-22'),
(3, 28880532, '2023-02-22 20:12:25', 'Entrada', '2023-02-22'),
(4, 28880532, '2023-02-23 14:13:38', 'Salida', '2023-02-23'),
(5, 28880532, '2023-02-23 14:13:47', 'Entrada', '2023-02-23'),
(6, 28880532, '2023-02-23 14:14:48', 'Salida', '2023-02-23'),
(7, 28880532, '2023-02-23 14:14:57', 'Entrada', '2023-02-23'),
(8, 28880532, '2023-02-23 18:02:16', 'Salida', '2023-02-23'),
(9, 28880532, '2023-02-23 18:02:28', 'Entrada', '2023-02-23'),
(10, 28880532, '2023-02-25 14:12:21', 'Salida', '2023-02-25'),
(11, 28880532, '2023-02-25 14:12:28', 'Entrada', '2023-02-25'),
(12, 13288793, '2023-02-26 16:00:30', 'Entrada', '2023-02-26'),
(13, 13288793, '2023-02-26 16:08:08', 'Salida', '2023-02-26'),
(14, 28880532, '2023-02-26 20:58:32', 'Salida', '2023-02-26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

DROP TABLE IF EXISTS `cargos`;
CREATE TABLE IF NOT EXISTS `cargos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `estatus` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`id`, `nombre`, `estatus`) VALUES
(1, 'Directivo', 1),
(2, 'Docente', 1),
(3, 'Auxiliar Administrativo', 1),
(4, 'Preceptor', 1),
(5, 'POT', 1),
(6, 'PEP (Profesor de Enseñanzas Prácticas)', 1),
(7, 'ADR', 1),
(8, 'Auxiliar Operativo', 1),
(9, 'Portero', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `departamentos`
--

DROP TABLE IF EXISTS `departamentos`;
CREATE TABLE IF NOT EXISTS `departamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(20) NOT NULL,
  `estatus` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `departamentos`
--

INSERT INTO `departamentos` (`id`, `descripcion`, `estatus`) VALUES
(1, 'Ciencias Sociales', 1),
(2, 'Ciencias Naturales', 1),
(3, 'Matemática', 1),
(4, 'Humanidades', 1),
(5, 'Economía', 1),
(6, 'Educ. Física', 1),
(7, 'Alumnado', 1),
(8, 'Informática', 1),
(9, 'Artes Visuales', 1),
(10, 'PMI', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

DROP TABLE IF EXISTS `docentes`;
CREATE TABLE IF NOT EXISTS `docentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ayn` varchar(50) NOT NULL,
  `dni` int(15) NOT NULL,
  `iddepartamento` int(11) NOT NULL,
  `idcargo` int(11) NOT NULL,
  `email` varchar(30) NOT NULL,
  `foto` text,
  `estatus` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `iddepartamento` (`iddepartamento`),
  KEY `idcargo` (`idcargo`),
  KEY `dni` (`dni`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `docentes`
--

INSERT INTO `docentes` (`id`, `ayn`, `dni`, `iddepartamento`, `idcargo`, `email`, `foto`, `estatus`) VALUES
(2, 'Wiener, Jorge Alfredo', 28880532, 8, 6, 'jawsistema@gmail.com', NULL, 1),
(3, 'Gomez, Juan', 13288793, 1, 5, 'g@gmail.com', NULL, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

DROP TABLE IF EXISTS `rol`;
CREATE TABLE IF NOT EXISTS `rol` (
  `idrol` int(11) NOT NULL AUTO_INCREMENT,
  `rol` varchar(50) NOT NULL,
  PRIMARY KEY (`idrol`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`idrol`, `rol`) VALUES
(1, 'Administrador'),
(2, 'Directivo'),
(3, 'Alumnado'),
(4, 'Preceptores'),
(5, 'Docente'),
(6, 'Alumno');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `idusuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `correo` varchar(50) NOT NULL,
  `usuario` varchar(20) NOT NULL,
  `clave` varchar(50) NOT NULL,
  `rol` int(11) NOT NULL,
  `estatus` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`idusuario`),
  KEY `rol` (`rol`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`idusuario`, `nombre`, `correo`, `usuario`, `clave`, `rol`, `estatus`) VALUES
(1, 'Jorge Wiener', 'jaw@gmail.com', 'admin', '123', 1, 1),
(2, 'Guzman, Claudia', 'claudia@gmail.com', 'claudia', '323', 1, 1),
(3, 'Perez, Juan', 'perez@gmail.com', 'juan', '321', 4, 1),
(4, 'Fontana, Norberto', 'norber@gmail.com', 'norber', '321', 3, 0);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_asistencias`
-- (Véase abajo para la vista actual)
--
DROP VIEW IF EXISTS `vista_asistencias`;
CREATE TABLE IF NOT EXISTS `vista_asistencias` (
`idasistencia` int(11)
,`codigo_persona` int(15)
,`fecha_hora` timestamp
,`tipo` varchar(45)
,`fecha` date
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_asistencias`
--
DROP TABLE IF EXISTS `vista_asistencias`;

DROP VIEW IF EXISTS `vista_asistencias`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_asistencias`  AS SELECT `asistencia`.`idasistencia` AS `idasistencia`, `asistencia`.`codigo_persona` AS `codigo_persona`, `asistencia`.`fecha_hora` AS `fecha_hora`, `asistencia`.`tipo` AS `tipo`, `asistencia`.`fecha` AS `fecha` FROM `asistencia``asistencia`  ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD CONSTRAINT `asistencia_ibfk_1` FOREIGN KEY (`codigo_persona`) REFERENCES `docentes` (`dni`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD CONSTRAINT `docentes_ibfk_1` FOREIGN KEY (`iddepartamento`) REFERENCES `departamentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `docentes_ibfk_2` FOREIGN KEY (`idcargo`) REFERENCES `cargos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
