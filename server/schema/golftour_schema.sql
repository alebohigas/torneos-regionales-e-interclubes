/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.25-MariaDB, for Win64 (AMD64)
--
-- Host: 70.35.203.117    Database: golftour
-- ------------------------------------------------------
-- Server version	10.4.34-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `avance`
--

DROP TABLE IF EXISTS `avance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `avance` (
  `idavance` int(11) NOT NULL AUTO_INCREMENT,
  `salidagrupoid` int(11) DEFAULT NULL,
  `hoyo` int(11) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL,
  `tarjetaid` int(11) DEFAULT NULL,
  PRIMARY KEY (`idavance`),
  KEY `salidagpoid` (`salidagrupoid`),
  KEY `xx` (`hoyo`),
  KEY `cc` (`fecha`),
  KEY `vv` (`tarjetaid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `caljuego`
--

DROP TABLE IF EXISTS `caljuego`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `caljuego` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `torneoid` int(11) NOT NULL,
  `categoria` varchar(25) NOT NULL,
  `fecha` date DEFAULT NULL,
  `campo` int(11) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL,
  `numfechajgo` int(11) NOT NULL DEFAULT 1,
  `ordenSal` int(11) NOT NULL DEFAULT 1,
  `numjug` int(11) NOT NULL DEFAULT 0,
  `numfoursome` int(11) NOT NULL DEFAULT 0,
  `foursomevuelta2` int(11) NOT NULL DEFAULT 0,
  `minutossal2` int(11) NOT NULL DEFAULT 10,
  `horainicio_1` time NOT NULL DEFAULT '06:00:00',
  `horainicio_10` time NOT NULL DEFAULT '06:00:00',
  `numgpos_1` varchar(100) NOT NULL DEFAULT '4',
  `numgpos_10` int(11) NOT NULL DEFAULT 0,
  `estatus` int(11) NOT NULL DEFAULT 0,
  `fecha_cambioestatus` varchar(25) NOT NULL DEFAULT '1900-01-01 00:00:01',
  `ordensalidas2` int(11) NOT NULL DEFAULT 0,
  `otiposalidas2` int(11) NOT NULL DEFAULT 0,
  `numjugfoursome` int(11) NOT NULL DEFAULT 4,
  `cierre` int(11) NOT NULL DEFAULT 0,
  `salhoyos` varchar(100) NOT NULL DEFAULT '1,1,1,1,1,1,1,1,1,1',
  `categoriaid` int(11) DEFAULT 0,
  `hoyos` varchar(145) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `torneoid` (`torneoid`),
  KEY `orden` (`orden`),
  KEY `numfechajgo` (`numfechajgo`),
  KEY `cierre` (`cierre`),
  KEY `catid` (`categoriaid`)
) ENGINE=InnoDB AUTO_INCREMENT=1541 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campo_tee`
--

DROP TABLE IF EXISTS `campo_tee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campo_tee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tee` int(10) unsigned NOT NULL DEFAULT 0,
  `id_campo` int(10) unsigned zerofill NOT NULL DEFAULT 0000000000,
  `slope` double unsigned NOT NULL DEFAULT 0,
  `rating` double unsigned NOT NULL DEFAULT 0,
  `max_hcp` int(11) NOT NULL DEFAULT 0,
  `activa` tinyint(1) NOT NULL,
  `parcampohoyo` varchar(145) DEFAULT '',
  `ventajas` varchar(145) DEFAULT '',
  `parcampo` varchar(145) DEFAULT '',
  `orden` int(11) DEFAULT 1,
  `yardaje` varchar(345) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `Index_2` (`id_tee`),
  KEY `Index_3` (`id_campo`),
  KEY `activa` (`activa`)
) ENGINE=InnoDB AUTO_INCREMENT=1546 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `campos`
--

DROP TABLE IF EXISTS `campos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campos` (
  `id` int(10) unsigned zerofill NOT NULL AUTO_INCREMENT,
  `campo` varchar(45) NOT NULL DEFAULT '',
  `id_club` int(10) unsigned zerofill NOT NULL DEFAULT 0000000001,
  `campo_id` int(10) unsigned zerofill NOT NULL DEFAULT 0000000000,
  `activo` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `Index_campo` (`campo`),
  KEY `Index_id_club` (`id_club`),
  KEY `activo` (`activo`),
  KEY `campo_id` (`campo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100922 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `catego_estatus`
--

DROP TABLE IF EXISTS `catego_estatus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `catego_estatus` (
  `idnew_table` int(11) NOT NULL AUTO_INCREMENT,
  `k` int(11) DEFAULT NULL,
  `v` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`idnew_table`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `categoria_id` int(11) NOT NULL AUTO_INCREMENT,
  `torneo_id` int(11) NOT NULL,
  `categoria` varchar(25) DEFAULT NULL,
  `sistema` varchar(25) DEFAULT 'Match Play',
  `formato` varchar(25) DEFAULT 'Individual',
  `estilo` varchar(25) DEFAULT 'Personal',
  `salida` varchar(25) DEFAULT NULL,
  `hcpCampoMin` int(11) DEFAULT 0,
  `hcpCampoMax` int(11) DEFAULT 0,
  `hcpIdxMin` double DEFAULT 0,
  `hcpIdxMax` double DEFAULT 0,
  `porcentaje` int(11) DEFAULT 80,
  `maxjugadores` int(11) DEFAULT 25,
  `corte` int(11) DEFAULT 8,
  `criterio_corte` varchar(45) DEFAULT 'Empates',
  `criterioDesempate` varchar(45) DEFAULT 'Retrogresion',
  `gross` int(11) DEFAULT 0,
  `hoyosajugar` int(11) DEFAULT 36,
  `hoyosacorte` int(11) DEFAULT 36,
  `fechaHandicap` varchar(10) DEFAULT '1900-01-01',
  `estatus` int(11) NOT NULL DEFAULT 1,
  `numjug` int(11) NOT NULL DEFAULT 0,
  `numfoursome` int(11) NOT NULL DEFAULT 0,
  `tipocorte` int(11) NOT NULL DEFAULT 1,
  `sexo` varchar(1) DEFAULT 'M',
  `Skeenporcent` int(11) NOT NULL DEFAULT 0,
  `Skin_grupo_id` int(11) NOT NULL DEFAULT 0,
  `numjuggross` int(11) DEFAULT 1,
  `numjugprem` int(11) DEFAULT 3,
  `orden` int(11) DEFAULT 0,
  `catidoriginal` int(11) DEFAULT 1,
  `campoid` int(11) DEFAULT 715,
  `numjugcorte` int(11) DEFAULT NULL,
  `inclusiveempates` int(11) DEFAULT 0,
  `criteriodesempatecorte` int(11) DEFAULT 0,
  `concatenarid` int(11) DEFAULT 1,
  `hoyosxronda` int(11) DEFAULT 18,
  `fechainicio` date DEFAULT NULL,
  `maxedad` int(11) DEFAULT 18,
  `tipopuntosid` int(11) DEFAULT 21,
  PRIMARY KEY (`categoria_id`),
  KEY `sexo` (`sexo`),
  KEY `ff` (`torneo_id`),
  KEY `gg` (`categoria`),
  KEY `catoriginal` (`catidoriginal`),
  KEY `orden` (`orden`),
  KEY `estatus` (`estatus`)
) ENGINE=InnoDB AUTO_INCREMENT=1338 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categorias_tmp`
--

DROP TABLE IF EXISTS `categorias_tmp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_tmp` (
  `categoriasTmp_id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(25) DEFAULT NULL,
  `sistema` varchar(25) DEFAULT 'Match Play',
  `formato` varchar(25) DEFAULT 'Individual',
  `estilo` varchar(25) DEFAULT 'Personal',
  `salida` varchar(25) DEFAULT NULL,
  `hcpCampoMin` int(11) DEFAULT 0,
  `hcpCampoMax` int(11) DEFAULT 0,
  `hcpIdxMin` double DEFAULT 0,
  `hcpIdxMax` double DEFAULT 0,
  `porcentaje` int(11) DEFAULT 80,
  `maxjugadores` int(11) DEFAULT 25,
  `corte` int(11) DEFAULT 8,
  `criterio_corte` varchar(45) DEFAULT 'Empates',
  `criterioDesempate` varchar(45) DEFAULT 'Retrogresion',
  `gross` int(11) DEFAULT 0,
  `hoyosajugar` int(11) DEFAULT 36,
  `hoyosacorte` int(11) DEFAULT 36,
  `fechaHandicap` date DEFAULT NULL,
  `fecha1` date DEFAULT NULL,
  `fecha2` date DEFAULT NULL,
  `fecha3` date DEFAULT NULL,
  `fecha4` date DEFAULT NULL,
  `fecha5` date DEFAULT NULL,
  `fecha6` date DEFAULT NULL,
  `fecha7` date DEFAULT NULL,
  `fecha8` date DEFAULT NULL,
  `fecha9` date DEFAULT NULL,
  `fecha10` date DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 1,
  `giraid` int(11) DEFAULT 1,
  `copaid` int(11) DEFAULT 1,
  `sexo` varchar(1) DEFAULT 'M',
  `numetapastop5` int(11) DEFAULT 4,
  `numetapastop5b` int(11) DEFAULT 4,
  PRIMARY KEY (`categoriasTmp_id`),
  KEY `orden` (`orden`),
  KEY `copaid` (`copaid`),
  KEY `giraid` (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=355 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clubs`
--

DROP TABLE IF EXISTS `clubs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clubs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(45) NOT NULL DEFAULT '',
  `ciudad` varchar(25) NOT NULL DEFAULT '',
  `estado` varchar(25) NOT NULL DEFAULT '',
  `pais` varchar(15) NOT NULL DEFAULT 'Mexico',
  `hoyos` int(10) unsigned NOT NULL DEFAULT 18,
  `numero` int(10) unsigned NOT NULL DEFAULT 0,
  `logo` varchar(100) NOT NULL DEFAULT '',
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `abr` varchar(15) NOT NULL,
  `zonaid` int(11) DEFAULT 99,
  PRIMARY KEY (`id`),
  KEY `nombre` (`nombre`),
  KEY `Index_ciudad` (`ciudad`),
  KEY `numero` (`numero`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=770246 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `copa`
--

DROP TABLE IF EXISTS `copa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `copa` (
  `idcopa` int(11) NOT NULL AUTO_INCREMENT,
  `giraid` int(11) DEFAULT NULL,
  `nombre` varchar(45) DEFAULT NULL,
  `sexo` varchar(1) DEFAULT 'V',
  PRIMARY KEY (`idcopa`),
  KEY `giraid` (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `copas`
--

DROP TABLE IF EXISTS `copas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `copas` (
  `copasid` int(11) NOT NULL AUTO_INCREMENT,
  `grupocopas` varchar(45) DEFAULT NULL,
  `nombre` varchar(45) DEFAULT NULL,
  `giraid` int(11) DEFAULT 1,
  PRIMARY KEY (`copasid`),
  KEY `giraid` (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuentas_correo`
--

DROP TABLE IF EXISTS `cuentas_correo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas_correo` (
  `idcuentas_correo` int(11) NOT NULL AUTO_INCREMENT,
  `cuenta` varchar(45) DEFAULT NULL,
  `pwd` varchar(45) DEFAULT NULL,
  `acum` int(11) DEFAULT 0,
  PRIMARY KEY (`idcuentas_correo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_criteriodesempatecorte`
--

DROP TABLE IF EXISTS `dd_criteriodesempatecorte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_criteriodesempatecorte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_estilo`
--

DROP TABLE IF EXISTS `dd_estilo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_estilo` (
  `idd_estilo` int(11) NOT NULL AUTO_INCREMENT,
  `estilo` varchar(25) DEFAULT NULL,
  `descrip` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`idd_estilo`),
  UNIQUE KEY `sistema_UNIQUE` (`estilo`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_sistema`
--

DROP TABLE IF EXISTS `dd_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_sistema` (
  `idd_sistema` int(11) NOT NULL AUTO_INCREMENT,
  `sistema` varchar(25) DEFAULT NULL,
  `descrip` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`idd_sistema`),
  UNIQUE KEY `sistema_UNIQUE` (`sistema`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_statseed`
--

DROP TABLE IF EXISTS `dd_statseed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_statseed` (
  `iddd_stattor` int(11) NOT NULL AUTO_INCREMENT,
  `estatus` varchar(1) DEFAULT NULL,
  `descrip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`iddd_stattor`),
  UNIQUE KEY `estatus_UNIQUE` (`estatus`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_stattor`
--

DROP TABLE IF EXISTS `dd_stattor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_stattor` (
  `iddd_stattor` int(11) NOT NULL AUTO_INCREMENT,
  `estatus` varchar(1) DEFAULT NULL,
  `descrip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`iddd_stattor`),
  UNIQUE KEY `estatus_UNIQUE` (`estatus`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_tiposalida`
--

DROP TABLE IF EXISTS `dd_tiposalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_tiposalida` (
  `k` int(11) NOT NULL,
  `v` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `dd_tiposaltor`
--

DROP TABLE IF EXISTS `dd_tiposaltor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dd_tiposaltor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `k` int(11) DEFAULT NULL,
  `v` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `diasjuego`
--

DROP TABLE IF EXISTS `diasjuego`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `diasjuego` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `torneoid` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `foursomevuelta` int(11) NOT NULL DEFAULT 13,
  `minutossal` int(11) NOT NULL DEFAULT 10,
  `horainicio` time NOT NULL DEFAULT '07:00:00',
  `minutosvuelta` int(11) NOT NULL DEFAULT 130,
  `horainiciopm` time NOT NULL DEFAULT '12:00:00',
  `estatus` int(11) NOT NULL DEFAULT 0,
  `ordensalidas` int(11) NOT NULL DEFAULT 0,
  `tiposalidas` int(11) NOT NULL DEFAULT 1,
  `estatusam` int(11) NOT NULL DEFAULT 0,
  `estatuspm` int(11) NOT NULL DEFAULT 0,
  `CAMPO` int(11) NOT NULL DEFAULT 0,
  `categoriaid` int(11) NOT NULL DEFAULT 1,
  `publica` int(11) NOT NULL DEFAULT 0,
  `correo` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torneoid_2` (`torneoid`,`fecha`),
  KEY `torneoid` (`torneoid`),
  KEY `fecha` (`fecha`),
  KEY `estatus` (`estatus`),
  KEY `CAMPO` (`CAMPO`),
  KEY `categoriaid` (`categoriaid`),
  KEY `publica` (`publica`),
  KEY `correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estatusjug`
--

DROP TABLE IF EXISTS `estatusjug`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estatusjug` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `estatus` varchar(45) NOT NULL,
  `estatusid` int(11) NOT NULL,
  `tipoestatus` varchar(3) NOT NULL DEFAULT '111',
  `imagen` varchar(45) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tipoestatus` (`tipoestatus`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `estatuspago`
--

DROP TABLE IF EXISTS `estatuspago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `estatuspago` (
  `k` int(11) NOT NULL AUTO_INCREMENT,
  `v` varchar(45) DEFAULT '',
  PRIMARY KEY (`k`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gira`
--

DROP TABLE IF EXISTS `gira`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `gira` (
  `giraid` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(45) DEFAULT '',
  `uso` int(11) DEFAULT 1,
  PRIMARY KEY (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hoyos`
--

DROP TABLE IF EXISTS `hoyos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hoyos` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero` int(10) unsigned NOT NULL DEFAULT 0,
  `par` int(10) unsigned NOT NULL DEFAULT 0,
  `id_campo` int(10) unsigned NOT NULL DEFAULT 0,
  `minutos` int(11) NOT NULL DEFAULT 16,
  PRIMARY KEY (`ID`),
  KEY `Index_2` (`numero`),
  KEY `Index_3` (`par`),
  KEY `Index_4` (`id_campo`)
) ENGINE=InnoDB AUTO_INCREMENT=5719 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hoyosxsalida`
--

DROP TABLE IF EXISTS `hoyosxsalida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `hoyosxsalida` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `numero` int(10) unsigned NOT NULL DEFAULT 0,
  `par` int(10) unsigned NOT NULL DEFAULT 0,
  `id_campo` int(10) unsigned NOT NULL DEFAULT 0,
  `salida` int(11) unsigned NOT NULL DEFAULT 0,
  `ventaja` int(11) NOT NULL DEFAULT 0,
  `yardaje` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `Index_2` (`numero`),
  KEY `Index_3` (`par`),
  KEY `Index_4` (`id_campo`),
  KEY `salida` (`salida`)
) ENGINE=InnoDB AUTO_INCREMENT=35433 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jugadores`
--

DROP TABLE IF EXISTS `jugadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jugadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `torneoid` int(11) NOT NULL,
  `numjugador` varchar(15) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `fechahandicap` date NOT NULL DEFAULT '2000-01-01',
  `sexo` varchar(1) NOT NULL,
  `hcpindex` double NOT NULL DEFAULT 0,
  `teesalidaid` int(11) NOT NULL,
  `salida` varchar(20) NOT NULL,
  `correo` varchar(100) NOT NULL DEFAULT '@',
  `club` varchar(250) NOT NULL,
  `tipoinsc` int(11) NOT NULL DEFAULT 1,
  `categoriaid` int(11) NOT NULL DEFAULT 0,
  `tipoinsc2` int(11) NOT NULL DEFAULT 3,
  `grupoid` varchar(20) NOT NULL DEFAULT '0',
  `indexjgo` double NOT NULL DEFAULT 0,
  `fechareg` timestamp NOT NULL DEFAULT '2000-01-01 00:00:00',
  `estatus` varchar(20) NOT NULL DEFAULT 'NORMAL',
  `cd1` int(11) NOT NULL DEFAULT 0,
  `cd2` int(11) NOT NULL DEFAULT 0,
  `cd3` int(11) NOT NULL DEFAULT 0,
  `cd4` int(11) NOT NULL DEFAULT 0,
  `cd5` int(11) NOT NULL DEFAULT 0,
  `cd6` int(11) NOT NULL DEFAULT 0,
  `campgross` int(11) NOT NULL DEFAULT 0,
  `muertesubita` int(11) NOT NULL DEFAULT 0,
  `estgross` int(11) NOT NULL DEFAULT 0,
  `Skeenjuga` int(11) NOT NULL DEFAULT 0,
  `Skeenjugagnal` int(11) NOT NULL DEFAULT 0,
  `puntos` double DEFAULT 0,
  `top5` int(11) NOT NULL DEFAULT 0,
  `puntossinp` int(11) DEFAULT 1,
  `puntossinp2` int(11) DEFAULT 1,
  `fechanac` date DEFAULT NULL,
  `clubid` int(11) DEFAULT 0,
  `puntos2` int(11) DEFAULT 0,
  `topseven` int(11) DEFAULT 1,
  `totso` int(11) DEFAULT 0,
  `posptos` int(11) DEFAULT 0,
  `penalties` int(11) DEFAULT 0,
  `puntos5_b` double DEFAULT 0,
  `top5b` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torneoid_2` (`torneoid`,`numjugador`,`club`,`nombre`,`apellido`),
  KEY `torneoid` (`torneoid`,`numjugador`,`fechahandicap`),
  KEY `categoriaid` (`categoriaid`),
  KEY `tipoinsc2` (`tipoinsc2`),
  KEY `grupoid` (`grupoid`),
  KEY `estatus` (`estatus`),
  KEY `cd1` (`cd1`),
  KEY `cd2` (`cd2`),
  KEY `cd3` (`cd3`),
  KEY `cd4` (`cd4`),
  KEY `cd5` (`cd5`),
  KEY `cd6` (`cd6`),
  KEY `murtesubita` (`muertesubita`),
  KEY `Skeenjuga` (`Skeenjuga`),
  KEY `Skeenjugagnal` (`Skeenjugagnal`),
  KEY `club` (`club`),
  KEY `numjg` (`numjugador`),
  KEY `xx` (`topseven`)
) ENGINE=InnoDB AUTO_INCREMENT=11400 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jugadores_seed`
--

DROP TABLE IF EXISTS `jugadores_seed`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jugadores_seed` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `torneoid` int(11) NOT NULL DEFAULT 1,
  `numjugador` varchar(15) NOT NULL DEFAULT 'num001',
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `fechahandicap` date NOT NULL DEFAULT '2000-01-01',
  `sexo` varchar(1) NOT NULL,
  `hcpindex` double NOT NULL DEFAULT 0,
  `teesalidaid` int(11) NOT NULL DEFAULT 1,
  `salida` varchar(20) NOT NULL DEFAULT 'azules',
  `correo` varchar(100) NOT NULL DEFAULT '@',
  `club` varchar(250) NOT NULL DEFAULT 'xx',
  `tipoinsc` int(11) NOT NULL DEFAULT 1,
  `categoriaid` int(11) NOT NULL DEFAULT 0,
  `tipoinsc2` int(11) NOT NULL DEFAULT 3,
  `grupoid` varchar(20) NOT NULL DEFAULT '0',
  `indexjgo` double NOT NULL DEFAULT 0,
  `fechareg` timestamp NOT NULL DEFAULT current_timestamp(),
  `estatus` varchar(20) NOT NULL DEFAULT 'NORMAL',
  `cd1` int(11) NOT NULL DEFAULT 0,
  `cd2` int(11) NOT NULL DEFAULT 0,
  `cd3` int(11) NOT NULL DEFAULT 0,
  `cd4` int(11) NOT NULL DEFAULT 0,
  `cd5` int(11) NOT NULL DEFAULT 0,
  `cd6` int(11) NOT NULL DEFAULT 0,
  `campgross` int(11) NOT NULL DEFAULT 0,
  `muertesubita` int(11) NOT NULL DEFAULT 0,
  `estgross` int(11) NOT NULL DEFAULT 0,
  `Skeenjuga` int(11) NOT NULL DEFAULT 0,
  `Skeenjugagnal` int(11) NOT NULL DEFAULT 0,
  `giraid` int(11) DEFAULT 1,
  `id_club` int(11) DEFAULT 1,
  `copaid` int(11) DEFAULT 1,
  `fechaNac` date DEFAULT NULL,
  `top5` double DEFAULT 1,
  `padre` varchar(45) DEFAULT NULL,
  `celpadre` varchar(45) DEFAULT NULL,
  `madre` varchar(45) DEFAULT NULL,
  `celmadre` varchar(45) DEFAULT NULL,
  `wpid` int(11) DEFAULT 0,
  `top5_b` int(11) DEFAULT 1,
  `top5_bSitienlasgiras` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torneoid_2` (`torneoid`,`numjugador`,`club`,`nombre`,`apellido`),
  KEY `torneoid` (`torneoid`,`numjugador`,`fechahandicap`),
  KEY `categoriaid` (`categoriaid`),
  KEY `tipoinsc2` (`tipoinsc2`),
  KEY `grupoid` (`grupoid`),
  KEY `estatus` (`estatus`),
  KEY `cd1` (`cd1`),
  KEY `cd2` (`cd2`),
  KEY `cd3` (`cd3`),
  KEY `cd4` (`cd4`),
  KEY `cd5` (`cd5`),
  KEY `cd6` (`cd6`),
  KEY `murtesubita` (`muertesubita`),
  KEY `Skeenjuga` (`Skeenjuga`),
  KEY `Skeenjugagnal` (`Skeenjugagnal`),
  KEY `club` (`club`),
  KEY `NOM` (`nombre`),
  KEY `APELL` (`apellido`),
  KEY `numjug` (`numjugador`),
  KEY `giraid` (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=2262 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`aliensystem`@`%`*/ /*!50003 TRIGGER t_bi_JugSeed
    before INSERT
    ON jugadores_seed FOR EACH ROW
BEGIN
    declare xx char(3);
    declare num int;
    select left(abr,3) into xx from clubs where id=new.id_club;
    select (max(id)+1) into num from jugadores_seed;
    set new.numjugador=concat(xx,num);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `par_campo`
--

DROP TABLE IF EXISTS `par_campo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `par_campo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_campo` int(10) unsigned zerofill NOT NULL DEFAULT 0000000000,
  `h1` int(10) unsigned NOT NULL DEFAULT 0,
  `h2` int(10) unsigned NOT NULL DEFAULT 0,
  `h3` int(10) unsigned NOT NULL DEFAULT 0,
  `h4` int(10) unsigned NOT NULL DEFAULT 0,
  `h5` int(10) unsigned NOT NULL DEFAULT 0,
  `h6` int(10) unsigned NOT NULL DEFAULT 0,
  `h7` int(10) unsigned NOT NULL DEFAULT 0,
  `h8` int(10) unsigned NOT NULL DEFAULT 0,
  `h9` int(10) unsigned NOT NULL DEFAULT 0,
  `h10` int(10) unsigned NOT NULL DEFAULT 0,
  `h11` int(10) unsigned NOT NULL DEFAULT 0,
  `h12` int(10) unsigned NOT NULL DEFAULT 0,
  `h13` int(10) unsigned NOT NULL DEFAULT 0,
  `h14` int(10) unsigned NOT NULL DEFAULT 0,
  `h15` int(10) unsigned NOT NULL DEFAULT 0,
  `h16` int(10) unsigned NOT NULL DEFAULT 0,
  `h17` int(10) unsigned NOT NULL DEFAULT 0,
  `h18` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `Index_id_campo` (`id_campo`)
) ENGINE=InnoDB AUTO_INCREMENT=2145 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `puntuacion`
--

DROP TABLE IF EXISTS `puntuacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `puntuacion` (
  `idpuntuacion` int(11) NOT NULL AUTO_INCREMENT,
  `lugar` int(11) DEFAULT NULL,
  `puntos` decimal(10,2) DEFAULT 0.00,
  `giraid` int(11) DEFAULT 1,
  PRIMARY KEY (`idpuntuacion`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `puntuacion_torneo`
--

DROP TABLE IF EXISTS `puntuacion_torneo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `puntuacion_torneo` (
  `idpuntuacion` int(11) NOT NULL AUTO_INCREMENT,
  `torneoid` int(11) DEFAULT NULL,
  `lugar` int(11) DEFAULT NULL,
  `puntos` decimal(10,2) DEFAULT 0.00,
  `tipopuntosid` int(11) DEFAULT 21,
  PRIMARY KEY (`idpuntuacion`),
  KEY `g` (`tipopuntosid`)
) ENGINE=InnoDB AUTO_INCREMENT=1625 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `registro`
--

DROP TABLE IF EXISTS `registro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `registro` (
  `reg_id` int(11) NOT NULL AUTO_INCREMENT,
  `reg_id_torneo` int(11) NOT NULL,
  `reg_id_club` int(11) NOT NULL,
  `reg_nombre` varchar(45) NOT NULL,
  `reg_apellido` varchar(45) NOT NULL,
  `reg_genero` varchar(2) NOT NULL,
  `reg_correo` varchar(45) NOT NULL,
  `reg_celular` varchar(15) NOT NULL,
  `reg_pais` varchar(45) NOT NULL,
  `reg_estado` varchar(45) NOT NULL,
  `reg_ciudad` varchar(45) NOT NULL,
  `reg_direccion` varchar(45) NOT NULL,
  `reg_cp` varchar(7) NOT NULL,
  `reg_spei` varchar(45) NOT NULL,
  `reg_handicap` float NOT NULL DEFAULT 0,
  `reg_categoria` int(11) NOT NULL,
  `reg_club` varchar(45) NOT NULL,
  `reg_mensaje` varchar(100) NOT NULL,
  `reg_cargo` varchar(45) NOT NULL,
  `reg_archivo` longblob DEFAULT NULL,
  `reg_archivo_nombre` varchar(345) NOT NULL,
  `status_pago` int(11) DEFAULT 0,
  `fecharegistro` timestamp NULL DEFAULT current_timestamp(),
  `verificado` int(11) DEFAULT 0,
  `reg_tutor` varchar(45) DEFAULT NULL,
  `reg_emailtutor` varchar(45) DEFAULT NULL,
  `reg_celtutor` varchar(15) DEFAULT NULL,
  `reg_fecnac` date DEFAULT NULL,
  PRIMARY KEY (`reg_id`),
  UNIQUE KEY `qqq` (`reg_nombre`,`reg_id_torneo`,`reg_emailtutor`,`reg_apellido`)
) ENGINE=InnoDB AUTO_INCREMENT=6604 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `result_ult_tar`
--

DROP TABLE IF EXISTS `result_ult_tar`;
/*!50001 DROP VIEW IF EXISTS `result_ult_tar`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `result_ult_tar` AS SELECT
 1 AS `jugadorid`,
  1 AS `tarjetaid`,
  1 AS `v2`,
  1 AS `ult_so` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `result_ult_tar_ec`
--

DROP TABLE IF EXISTS `result_ult_tar_ec`;
/*!50001 DROP VIEW IF EXISTS `result_ult_tar_ec`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `result_ult_tar_ec` AS SELECT
 1 AS `jugadorid`,
  1 AS `tarjetaid`,
  1 AS `v2`,
  1 AS `ult_so` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `salidagrupo`
--

DROP TABLE IF EXISTS `salidagrupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salidagrupo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `salidatorneoid` int(11) NOT NULL DEFAULT 0,
  `horainicio1a` datetime NOT NULL,
  `horafin1a` datetime NOT NULL,
  `horainicio2a` datetime NOT NULL,
  `horafin2a` datetime NOT NULL,
  `categoriaid` int(11) NOT NULL,
  `teesal` varchar(5) NOT NULL,
  `caljuegoid` int(11) NOT NULL DEFAULT 0,
  `numjug` int(11) NOT NULL,
  `numfoursome` int(11) NOT NULL,
  `torneoid` int(11) DEFAULT NULL,
  `pwd` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salidatorneoid` (`salidatorneoid`),
  KEY `categoriaid` (`categoriaid`),
  KEY `caljuegoid` (`caljuegoid`),
  KEY `cc` (`torneoid`)
) ENGINE=InnoDB AUTO_INCREMENT=5139 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salidas`
--

DROP TABLE IF EXISTS `salidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salidas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tee` varchar(45) NOT NULL,
  `color` varchar(8) NOT NULL,
  `bgcolor` varchar(12) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `Index_nombre` (`tee`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `salidasTorneo`
--

DROP TABLE IF EXISTS `salidasTorneo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salidasTorneo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoriaid` int(11) NOT NULL,
  `diajuegoid` int(11) NOT NULL,
  `h1am` int(11) NOT NULL,
  `h10am` int(11) NOT NULL,
  `h10pm` int(11) NOT NULL,
  `h1pm` int(11) NOT NULL,
  `estatus` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categoriaid_2` (`categoriaid`,`diajuegoid`),
  KEY `categoriaid` (`categoriaid`),
  KEY `diajuegoid` (`diajuegoid`),
  KEY `h1am` (`h1am`),
  KEY `h10am` (`h10am`),
  KEY `h10pm` (`h10pm`),
  KEY `h1pm` (`h1pm`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sino`
--

DROP TABLE IF EXISTS `sino`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sino` (
  `idsino` int(11) NOT NULL,
  `sinocol` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`idsino`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tarjetas`
--

DROP TABLE IF EXISTS `tarjetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tarjetas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_campo` int(10) unsigned zerofill NOT NULL DEFAULT 0000000000,
  `h1` int(10) unsigned NOT NULL DEFAULT 0,
  `h2` int(10) unsigned NOT NULL DEFAULT 0,
  `h3` int(10) unsigned NOT NULL DEFAULT 0,
  `h4` int(10) unsigned NOT NULL DEFAULT 0,
  `h5` int(10) unsigned NOT NULL DEFAULT 0,
  `h6` int(10) unsigned NOT NULL DEFAULT 0,
  `h7` int(10) unsigned NOT NULL DEFAULT 0,
  `h8` int(10) unsigned NOT NULL DEFAULT 0,
  `h9` int(10) unsigned NOT NULL DEFAULT 0,
  `h10` int(10) unsigned NOT NULL DEFAULT 0,
  `h11` int(10) unsigned NOT NULL DEFAULT 0,
  `h12` int(10) unsigned NOT NULL DEFAULT 0,
  `h13` int(10) unsigned NOT NULL DEFAULT 0,
  `h14` int(10) unsigned NOT NULL DEFAULT 0,
  `h15` int(10) unsigned NOT NULL DEFAULT 0,
  `h16` int(10) unsigned NOT NULL DEFAULT 0,
  `h17` int(10) unsigned NOT NULL DEFAULT 0,
  `h18` int(10) unsigned NOT NULL DEFAULT 0,
  `h1_a` int(10) NOT NULL DEFAULT 0,
  `h2_a` int(10) NOT NULL DEFAULT 0,
  `h3_a` int(10) NOT NULL DEFAULT 0,
  `h4_a` int(10) NOT NULL DEFAULT 0,
  `h5_a` int(10) NOT NULL DEFAULT 0,
  `h6_a` int(10) NOT NULL DEFAULT 0,
  `h7_a` int(10) NOT NULL DEFAULT 0,
  `h8_a` int(10) NOT NULL DEFAULT 0,
  `h9_a` int(10) NOT NULL DEFAULT 0,
  `h10_a` int(10) NOT NULL DEFAULT 0,
  `h11_a` int(10) NOT NULL DEFAULT 0,
  `h12_a` int(10) NOT NULL DEFAULT 0,
  `h13_a` int(10) NOT NULL DEFAULT 0,
  `h14_a` int(10) NOT NULL DEFAULT 0,
  `h15_a` int(10) NOT NULL DEFAULT 0,
  `h16_a` int(10) NOT NULL DEFAULT 0,
  `h17_a` int(10) NOT NULL DEFAULT 0,
  `h18_a` int(10) NOT NULL DEFAULT 0,
  `jugadorid` int(10) unsigned NOT NULL DEFAULT 0,
  `fecha_cap` date NOT NULL DEFAULT '1900-01-01',
  `tee_salida` int(10) unsigned NOT NULL DEFAULT 2,
  `color_tee` varchar(15) NOT NULL DEFAULT 'BLANCAS',
  `SO` int(10) unsigned NOT NULL DEFAULT 0,
  `SA` int(10) unsigned NOT NULL DEFAULT 0,
  `dif` double NOT NULL DEFAULT 0,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_juego` date NOT NULL DEFAULT '2000-01-01',
  `tipo` char(1) NOT NULL DEFAULT 'N',
  `salidagrupoid` int(10) unsigned NOT NULL DEFAULT 0,
  `categoriaid` bigint(20) NOT NULL DEFAULT 0,
  `utiliza` int(10) unsigned NOT NULL DEFAULT 0,
  `slope` double NOT NULL DEFAULT 113,
  `rating` double NOT NULL DEFAULT 72,
  `torneoid` int(11) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL DEFAULT 0,
  `estatus` varchar(1) NOT NULL DEFAULT 'N',
  `ventajas` varchar(145) DEFAULT '',
  `parcampohoyo` varchar(145) DEFAULT '',
  `parcampo` varchar(145) DEFAULT '',
  `statlsc` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jug-fecha` (`jugadorid`,`fecha_juego`),
  KEY `Index_id_campo` (`id_campo`),
  KEY `Index_id_jug` (`jugadorid`),
  KEY `Index_feca_cap` (`fecha_cap`),
  KEY `Index_tee` (`tee_salida`),
  KEY `Index_6` (`color_tee`),
  KEY `Index_dif` (`dif`),
  KEY `Index_estado` (`estado`),
  KEY `Index_fecha_juego` (`fecha_juego`),
  KEY `tipo` (`tipo`),
  KEY `index_club_cap` (`salidagrupoid`),
  KEY `id_tar_club` (`categoriaid`),
  KEY `Index_13` (`utiliza`),
  KEY `orden` (`orden`),
  KEY `toneoididx` (`torneoid`)
) ENGINE=InnoDB AUTO_INCREMENT=15235 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipotorneo`
--

DROP TABLE IF EXISTS `tipotorneo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipotorneo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sistema` varchar(25) NOT NULL,
  `formato` varchar(20) NOT NULL,
  `estilo` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `torneo`
--

DROP TABLE IF EXISTS `torneo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `torneo` (
  `torneo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(105) NOT NULL,
  `fecha_ini` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `fecha_hand` date NOT NULL,
  `fecha_cierre` varchar(10) DEFAULT '0000-00-00',
  `status` varchar(1) NOT NULL DEFAULT 'A',
  `club_id` int(10) unsigned NOT NULL DEFAULT 1,
  `logo` varchar(200) NOT NULL DEFAULT ' ',
  `formato` varchar(45) NOT NULL DEFAULT 'INDIVIDUAL',
  `estilo` varchar(45) NOT NULL DEFAULT 'PERSONAL',
  `correotorne` varchar(45) NOT NULL DEFAULT '@',
  `sistemajuego` varchar(45) NOT NULL DEFAULT 'STROKE PLAY/STABLEFORD',
  `telefono` varchar(45) NOT NULL DEFAULT ' ',
  `tipotorneo` int(11) NOT NULL DEFAULT 1,
  `varioscampos` int(11) NOT NULL DEFAULT 0,
  `oyesacum` int(11) NOT NULL DEFAULT 0,
  `oyesacumgpo` int(11) NOT NULL DEFAULT 0,
  `oyesnumprem` int(11) NOT NULL DEFAULT 5,
  `horapm` int(11) NOT NULL DEFAULT 12,
  `imagen_gif` varchar(150) NOT NULL DEFAULT ' ',
  `skeen_porcet1` int(11) NOT NULL DEFAULT 0,
  `skeen_porcet2` int(11) NOT NULL DEFAULT 0,
  `regla1312` int(11) NOT NULL DEFAULT 0,
  `color_cinta` varchar(6) DEFAULT 'A60282',
  `giraid` int(11) NOT NULL DEFAULT 1,
  `responsable` varchar(45) DEFAULT ' ',
  `campos` varchar(45) DEFAULT ' ',
  `tiposalida` int(11) DEFAULT 0 COMMENT 'tipo salida 0, normal, 1 salida unica',
  `camposformulario` varchar(45) DEFAULT '1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1',
  PRIMARY KEY (`torneo_id`),
  KEY `tipotorneo` (`tipotorneo`),
  KEY `giraid` (`giraid`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(15) NOT NULL,
  `pwd` varchar(10) NOT NULL,
  `clubid` int(11) NOT NULL,
  `tipo` int(11) NOT NULL,
  `torneoid` int(11) NOT NULL,
  `estatus` varchar(10) NOT NULL DEFAULT 'ACTIVO',
  `nombre` varchar(30) NOT NULL,
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `ultent` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `clubid` (`clubid`),
  KEY `estatus` (`estatus`),
  KEY `tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios2`
--

DROP TABLE IF EXISTS `usuarios2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `correo_electronico` varchar(45) DEFAULT '',
  `usuario` varchar(75) NOT NULL,
  `torneos` varchar(55) DEFAULT NULL,
  `pwd` varchar(100) NOT NULL,
  `gira` int(11) NOT NULL,
  `tipo` int(11) NOT NULL,
  `torneoid` int(11) NOT NULL,
  `estatus` varchar(10) NOT NULL DEFAULT 'ACTIVO',
  `desde` date DEFAULT NULL,
  `hasta` date DEFAULT NULL,
  `ultent` datetime NOT NULL,
  `mysqlusu` varchar(45) DEFAULT '',
  `activo` int(11) DEFAULT 1,
  `pwd2` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `clubid` (`gira`),
  KEY `estatus` (`estatus`),
  KEY `tipo` (`tipo`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usutipo`
--

DROP TABLE IF EXISTS `usutipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `usutipo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) NOT NULL,
  `opciones` varchar(50) NOT NULL,
  `imagen` varchar(50) NOT NULL,
  `smenu0` varchar(45) DEFAULT NULL,
  `smenu1` varchar(45) DEFAULT NULL,
  `smenu2` varchar(45) DEFAULT NULL,
  `smenu3` varchar(45) DEFAULT NULL,
  `smenu4` varchar(45) DEFAULT NULL,
  `smenu5` varchar(45) DEFAULT NULL,
  `smenu6` varchar(45) DEFAULT NULL,
  `smenu7` varchar(45) DEFAULT NULL,
  `smenu8` varchar(45) DEFAULT NULL,
  `smenu9` varchar(45) DEFAULT NULL,
  `header` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_caljgo_salgpo`
--

DROP TABLE IF EXISTS `v_caljgo_salgpo`;
/*!50001 DROP VIEW IF EXISTS `v_caljgo_salgpo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_caljgo_salgpo` AS SELECT
 1 AS `caljuegoid`,
  1 AS `pwd` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_cd_ulttar`
--

DROP TABLE IF EXISTS `v_cd_ulttar`;
/*!50001 DROP VIEW IF EXISTS `v_cd_ulttar`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_cd_ulttar` AS SELECT
 1 AS `torneoid`,
  1 AS `jugadorid`,
  1 AS `c1`,
  1 AS `c2`,
  1 AS `c3`,
  1 AS `c4`,
  1 AS `c5`,
  1 AS `c6` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_cd_ulttar_ec`
--

DROP TABLE IF EXISTS `v_cd_ulttar_ec`;
/*!50001 DROP VIEW IF EXISTS `v_cd_ulttar_ec`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_cd_ulttar_ec` AS SELECT
 1 AS `torneoid`,
  1 AS `jugadorid`,
  1 AS `c1`,
  1 AS `c2`,
  1 AS `c3`,
  1 AS `c4`,
  1 AS `c5`,
  1 AS `c6` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_diasjgo`
--

DROP TABLE IF EXISTS `v_diasjgo`;
/*!50001 DROP VIEW IF EXISTS `v_diasjgo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_diasjgo` AS SELECT
 1 AS `torneoid`,
  1 AS `categoria`,
  1 AS `INICIA`,
  1 AS `TERMINA`,
  1 AS `diasjgo`,
  1 AS `categoria_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_diasjgo_categoria`
--

DROP TABLE IF EXISTS `v_diasjgo_categoria`;
/*!50001 DROP VIEW IF EXISTS `v_diasjgo_categoria`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_diasjgo_categoria` AS SELECT
 1 AS `categoriaid`,
  1 AS `diajuegoid`,
  1 AS `fecha` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_difpar_tarjeta`
--

DROP TABLE IF EXISTS `v_difpar_tarjeta`;
/*!50001 DROP VIEW IF EXISTS `v_difpar_tarjeta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_difpar_tarjeta` AS SELECT
 1 AS `tarjetaid`,
  1 AS `difpar` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_difpar_ulttarjeta`
--

DROP TABLE IF EXISTS `v_difpar_ulttarjeta`;
/*!50001 DROP VIEW IF EXISTS `v_difpar_ulttarjeta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_difpar_ulttarjeta` AS SELECT
 1 AS `jugadorid`,
  1 AS `avance`,
  1 AS `difpar_ulttar` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_estatorneo`
--

DROP TABLE IF EXISTS `v_estatorneo`;
/*!50001 DROP VIEW IF EXISTS `v_estatorneo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_estatorneo` AS SELECT
 1 AS `numjugador`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `indexjgo`,
  1 AS `round((a.indexjgo*.8),0)`,
  1 AS `torneoid`,
  1 AS `categoria`,
  1 AS `club` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_gira_so`
--

DROP TABLE IF EXISTS `v_gira_so`;
/*!50001 DROP VIEW IF EXISTS `v_gira_so`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_gira_so` AS SELECT
 1 AS `giraid`,
  1 AS `numjugador`,
  1 AS `so` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_gira_so_ptos`
--

DROP TABLE IF EXISTS `v_gira_so_ptos`;
/*!50001 DROP VIEW IF EXISTS `v_gira_so_ptos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_gira_so_ptos` AS SELECT
 1 AS `giraid`,
  1 AS `numjugador`,
  1 AS `so`,
  1 AS `ptos`,
  1 AS `avgso`,
  1 AS `minso`,
  1 AS `maxso` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_horariox`
--

DROP TABLE IF EXISTS `v_horariox`;
/*!50001 DROP VIEW IF EXISTS `v_horariox`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_horariox` AS SELECT
 1 AS `id`,
  1 AS `horainicio1a`,
  1 AS `teesal`,
  1 AS `numjug`,
  1 AS `catjugador`,
  1 AS `fecha`,
  1 AS `campo`,
  1 AS `agrupo`,
  1 AS `torneoid`,
  1 AS `pwd`,
  1 AS `estatus` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_jugxcat`
--

DROP TABLE IF EXISTS `v_jugxcat`;
/*!50001 DROP VIEW IF EXISTS `v_jugxcat`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_jugxcat` AS SELECT
 1 AS `torneoid`,
  1 AS `categoriaid`,
  1 AS `totjug` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_jugxcat2`
--

DROP TABLE IF EXISTS `v_jugxcat2`;
/*!50001 DROP VIEW IF EXISTS `v_jugxcat2`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_jugxcat2` AS SELECT
 1 AS `torneoid`,
  1 AS `categoriaid`,
  1 AS `totjug`,
  1 AS `categoria` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_lista_jug`
--

DROP TABLE IF EXISTS `v_lista_jug`;
/*!50001 DROP VIEW IF EXISTS `v_lista_jug`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_lista_jug` AS SELECT
 1 AS `id`,
  1 AS `torneoid`,
  1 AS `numjugador`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `sexo`,
  1 AS `club`,
  1 AS `categoria`,
  1 AS `grupoid`,
  1 AS `estatus`,
  1 AS `puntossinp`,
  1 AS `puntossinp2`,
  1 AS `fechanac`,
  1 AS `diasmayorcatego`,
  1 AS `maxedad`,
  1 AS `categoriaid`,
  1 AS `clubid`,
  1 AS `logoclub`,
  1 AS `puntos`,
  1 AS `puntos2`,
  1 AS `cd1`,
  1 AS `cd2`,
  1 AS `cd3`,
  1 AS `cd4`,
  1 AS `cd5`,
  1 AS `cd6`,
  1 AS `muertesubita`,
  1 AS `correo`,
  1 AS `teesalidaid`,
  1 AS `salida` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_livesacor_pos`
--

DROP TABLE IF EXISTS `v_livesacor_pos`;
/*!50001 DROP VIEW IF EXISTS `v_livesacor_pos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_livesacor_pos` AS SELECT
 1 AS `categoriaid`,
  1 AS `jugadorid`,
  1 AS `so`,
  1 AS `rdn`,
  1 AS `rondas`,
  1 AS `maxid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_mejor_score`
--

DROP TABLE IF EXISTS `v_mejor_score`;
/*!50001 DROP VIEW IF EXISTS `v_mejor_score`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_mejor_score` AS SELECT
 1 AS `numjugador`,
  1 AS `giraid`,
  1 AS `corte`,
  1 AS `maxs`,
  1 AS `minso`,
  1 AS `numrondas` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_mejor_score_lista`
--

DROP TABLE IF EXISTS `v_mejor_score_lista`;
/*!50001 DROP VIEW IF EXISTS `v_mejor_score_lista`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_mejor_score_lista` AS SELECT
 1 AS `numjugador`,
  1 AS `categoria`,
  1 AS `giraid`,
  1 AS `categoriasTmp_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_mejor_score_lista3`
--

DROP TABLE IF EXISTS `v_mejor_score_lista3`;
/*!50001 DROP VIEW IF EXISTS `v_mejor_score_lista3`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_mejor_score_lista3` AS SELECT
 1 AS `numjugador`,
  1 AS `categoria`,
  1 AS `giraid`,
  1 AS `categoriasTmp_id` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_mejor_tarjeta`
--

DROP TABLE IF EXISTS `v_mejor_tarjeta`;
/*!50001 DROP VIEW IF EXISTS `v_mejor_tarjeta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_mejor_tarjeta` AS SELECT
 1 AS `numjugador`,
  1 AS `giraid`,
  1 AS `tarid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_parcampo`
--

DROP TABLE IF EXISTS `v_parcampo`;
/*!50001 DROP VIEW IF EXISTS `v_parcampo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_parcampo` AS SELECT
 1 AS `campoid`,
  1 AS `salidaid`,
  1 AS `pas` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_parcampoxsalida`
--

DROP TABLE IF EXISTS `v_parcampoxsalida`;
/*!50001 DROP VIEW IF EXISTS `v_parcampoxsalida`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_parcampoxsalida` AS SELECT
 1 AS `campoid`,
  1 AS `salidaid`,
  1 AS `parcampo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_posicionptos`
--

DROP TABLE IF EXISTS `v_posicionptos`;
/*!50001 DROP VIEW IF EXISTS `v_posicionptos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_posicionptos` AS SELECT
 1 AS `muertesubita`,
  1 AS `so`,
  1 AS `categoriaid`,
  1 AS `tot` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_resultar`
--

DROP TABLE IF EXISTS `v_resultar`;
/*!50001 DROP VIEW IF EXISTS `v_resultar`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_resultar` AS SELECT
 1 AS `jugadorid`,
  1 AS `SO`,
  1 AS `SA`,
  1 AS `estado`,
  1 AS `fecha_juego`,
  1 AS `salidagrupoid`,
  1 AS `categoriaid`,
  1 AS `torneoid`,
  1 AS `fechasal`,
  1 AS `teesal` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_resultar_ec`
--

DROP TABLE IF EXISTS `v_resultar_ec`;
/*!50001 DROP VIEW IF EXISTS `v_resultar_ec`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_resultar_ec` AS SELECT
 1 AS `jugadorid`,
  1 AS `SO`,
  1 AS `SA`,
  1 AS `estado`,
  1 AS `fecha_juego`,
  1 AS `salidagrupoid`,
  1 AS `categoriaid`,
  1 AS `torneoid`,
  1 AS `fechasal`,
  1 AS `teesal` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_resultarconcat`
--

DROP TABLE IF EXISTS `v_resultarconcat`;
/*!50001 DROP VIEW IF EXISTS `v_resultarconcat`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_resultarconcat` AS SELECT
 1 AS `jugadorid`,
  1 AS `SO`,
  1 AS `SA`,
  1 AS `estado`,
  1 AS `fecha_juego`,
  1 AS `salidagrupoid`,
  1 AS `categoriaid`,
  1 AS `torneoid`,
  1 AS `fechasal`,
  1 AS `teesal`,
  1 AS `dif`,
  1 AS `concatenarid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_rondasxgira`
--

DROP TABLE IF EXISTS `v_rondasxgira`;
/*!50001 DROP VIEW IF EXISTS `v_rondasxgira`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_rondasxgira` AS SELECT
 1 AS `numjugador`,
  1 AS `giraid`,
  1 AS `rondas` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sal_jug`
--

DROP TABLE IF EXISTS `v_sal_jug`;
/*!50001 DROP VIEW IF EXISTS `v_sal_jug`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_sal_jug` AS SELECT
 1 AS `tarjetaid`,
  1 AS `salidatorneoid`,
  1 AS `id_campo`,
  1 AS `jugadorid`,
  1 AS `tee_salida`,
  1 AS `salidagrupoid`,
  1 AS `slope`,
  1 AS `rating`,
  1 AS `horainicio1a`,
  1 AS `horainicio2a`,
  1 AS `teesal`,
  1 AS `numjugador`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `fechahandicap`,
  1 AS `sexo`,
  1 AS `hcpindex`,
  1 AS `teesalidaid`,
  1 AS `correo`,
  1 AS `clubjug`,
  1 AS `tipoinsc`,
  1 AS `tipoinsc2`,
  1 AS `indexjgo`,
  1 AS `colortee`,
  1 AS `tee`,
  1 AS `categoriaid`,
  1 AS `grupoid`,
  1 AS `torneoid`,
  1 AS `jugestatus`,
  1 AS `fecha_juego`,
  1 AS `caljuegoid`,
  1 AS `orden`,
  1 AS `logo`,
  1 AS `club`,
  1 AS `acumso`,
  1 AS `sistema`,
  1 AS `catjugador` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas`
--

DROP TABLE IF EXISTS `v_salidas`;
/*!50001 DROP VIEW IF EXISTS `v_salidas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas` AS SELECT
 1 AS `salidagrupoid`,
  1 AS `jugadorid`,
  1 AS `tee_salida`,
  1 AS `fecha`,
  1 AS `horainicio1a`,
  1 AS `categoriaid`,
  1 AS `estatus`,
  1 AS `torneoid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_gpo`
--

DROP TABLE IF EXISTS `v_salidas_gpo`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_gpo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_gpo` AS SELECT
 1 AS `fecha`,
  1 AS `categoriaid`,
  1 AS `torneoid`,
  1 AS `estatus` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_h1`
--

DROP TABLE IF EXISTS `v_salidas_h1`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_h1`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_h1` AS SELECT
 1 AS `id`,
  1 AS `salidatorneoid`,
  1 AS `horainicio1a`,
  1 AS `horafin1a`,
  1 AS `horainicio2a`,
  1 AS `horafin2a`,
  1 AS `categoriaid`,
  1 AS `teesal`,
  1 AS `caljuegoid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_h10`
--

DROP TABLE IF EXISTS `v_salidas_h10`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_h10`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_h10` AS SELECT
 1 AS `id`,
  1 AS `salidatorneoid`,
  1 AS `horainicio1a`,
  1 AS `horafin1a`,
  1 AS `horainicio2a`,
  1 AS `horafin2a`,
  1 AS `categoriaid`,
  1 AS `teesal`,
  1 AS `caljuegoid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_ls`
--

DROP TABLE IF EXISTS `v_salidas_ls`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_ls`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_ls` AS SELECT
 1 AS `torneoid`,
  1 AS `fecha`,
  1 AS `campo`,
  1 AS `campoid`,
  1 AS `pwd` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_tarj`
--

DROP TABLE IF EXISTS `v_salidas_tarj`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_tarj`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_tarj` AS SELECT
 1 AS `id`,
  1 AS `salidagrupoid`,
  1 AS `horainicio1a`,
  1 AS `teesal`,
  1 AS `numjug`,
  1 AS `categoria`,
  1 AS `fecha`,
  1 AS `campo`,
  1 AS `agrupo`,
  1 AS `numjugador`,
  1 AS `jugid`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `torneoid`,
  1 AS `orden`,
  1 AS `club`,
  1 AS `clubid`,
  1 AS `so`,
  1 AS `categoriaid`,
  1 AS `juestatus`,
  1 AS `avance` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_tarj1`
--

DROP TABLE IF EXISTS `v_salidas_tarj1`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_tarj1`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_tarj1` AS SELECT
 1 AS `id`,
  1 AS `salidagrupoid`,
  1 AS `horainicio1a`,
  1 AS `teesal`,
  1 AS `numjug`,
  1 AS `categoria`,
  1 AS `fecha`,
  1 AS `campo`,
  1 AS `agrupo`,
  1 AS `agrupo2`,
  1 AS `numjugador`,
  1 AS `jugid`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `torneoid`,
  1 AS `orden`,
  1 AS `club`,
  1 AS `clubid`,
  1 AS `so`,
  1 AS `caljuegoid`,
  1 AS `categoriaid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_tarjT`
--

DROP TABLE IF EXISTS `v_salidas_tarjT`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_tarjT`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_tarjT` AS SELECT
 1 AS `id`,
  1 AS `salidagrupoid`,
  1 AS `horainicio1a`,
  1 AS `teesal`,
  1 AS `numjug`,
  1 AS `categoria`,
  1 AS `fecha`,
  1 AS `campo`,
  1 AS `agrupo`,
  1 AS `numjugador`,
  1 AS `jugid`,
  1 AS `nombre`,
  1 AS `apellido`,
  1 AS `torneoid`,
  1 AS `orden`,
  1 AS `club`,
  1 AS `clubid`,
  1 AS `so`,
  1 AS `categoriaid`,
  1 AS `juestatus`,
  1 AS `avance` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_tl`
--

DROP TABLE IF EXISTS `v_salidas_tl`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_tl`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_tl` AS SELECT
 1 AS `torneoid`,
  1 AS `fecha`,
  1 AS `categoriaid`,
  1 AS `caljuegoid`,
  1 AS `categoria` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_salidas_x`
--

DROP TABLE IF EXISTS `v_salidas_x`;
/*!50001 DROP VIEW IF EXISTS `v_salidas_x`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_salidas_x` AS SELECT
 1 AS `id`,
  1 AS `fecha`,
  1 AS `categoria`,
  1 AS `categoriaid`,
  1 AS `torneoid`,
  1 AS `estatus`,
  1 AS `pwd` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sumsa_normal`
--

DROP TABLE IF EXISTS `v_sumsa_normal`;
/*!50001 DROP VIEW IF EXISTS `v_sumsa_normal`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_sumsa_normal` AS SELECT
 1 AS `jugadorid`,
  1 AS `categoriaid`,
  1 AS `sa` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sumtarjeta`
--

DROP TABLE IF EXISTS `v_sumtarjeta`;
/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_sumtarjeta` AS SELECT
 1 AS `jugadorid`,
  1 AS `so`,
  1 AS `sa`,
  1 AS `neto`,
  1 AS `hinicio`,
  1 AS `salidaid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sumtarjeta2`
--

DROP TABLE IF EXISTS `v_sumtarjeta2`;
/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta2`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_sumtarjeta2` AS SELECT
 1 AS `jugadorid`,
  1 AS `torneoid`,
  1 AS `categoriaid`,
  1 AS `so`,
  1 AS `sa`,
  1 AS `numtar`,
  1 AS `neto`,
  1 AS `hinicio`,
  1 AS `salidaid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_sumtarjeta3`
--

DROP TABLE IF EXISTS `v_sumtarjeta3`;
/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta3`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_sumtarjeta3` AS SELECT
 1 AS `jugadorid`,
  1 AS `torneoid`,
  1 AS `categoriaid`,
  1 AS `so`,
  1 AS `sa`,
  1 AS `numtar` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_tarjetas_giraant`
--

DROP TABLE IF EXISTS `v_tarjetas_giraant`;
/*!50001 DROP VIEW IF EXISTS `v_tarjetas_giraant`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_tarjetas_giraant` AS SELECT
 1 AS `id`,
  1 AS `id_campo`,
  1 AS `h1`,
  1 AS `h2`,
  1 AS `h3`,
  1 AS `h4`,
  1 AS `h5`,
  1 AS `h6`,
  1 AS `h7`,
  1 AS `h8`,
  1 AS `h9`,
  1 AS `h10`,
  1 AS `h11`,
  1 AS `h12`,
  1 AS `h13`,
  1 AS `h14`,
  1 AS `h15`,
  1 AS `h16`,
  1 AS `h17`,
  1 AS `h18`,
  1 AS `h1_a`,
  1 AS `h2_a`,
  1 AS `h3_a`,
  1 AS `h4_a`,
  1 AS `h5_a`,
  1 AS `h6_a`,
  1 AS `h7_a`,
  1 AS `h8_a`,
  1 AS `h9_a`,
  1 AS `h10_a`,
  1 AS `h11_a`,
  1 AS `h12_a`,
  1 AS `h13_a`,
  1 AS `h14_a`,
  1 AS `h15_a`,
  1 AS `h16_a`,
  1 AS `h17_a`,
  1 AS `h18_a`,
  1 AS `jugadorid`,
  1 AS `fecha_cap`,
  1 AS `tee_salida`,
  1 AS `color_tee`,
  1 AS `SO`,
  1 AS `SA`,
  1 AS `dif`,
  1 AS `estado`,
  1 AS `fecha_juego`,
  1 AS `tipo`,
  1 AS `salidagrupoid`,
  1 AS `categoriaid`,
  1 AS `utiliza`,
  1 AS `slope`,
  1 AS `rating`,
  1 AS `torneoid`,
  1 AS `orden`,
  1 AS `estatus`,
  1 AS `numjugador` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_tarjetas_giraant2`
--

DROP TABLE IF EXISTS `v_tarjetas_giraant2`;
/*!50001 DROP VIEW IF EXISTS `v_tarjetas_giraant2`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_tarjetas_giraant2` AS SELECT
 1 AS `id`,
  1 AS `id_campo`,
  1 AS `h1`,
  1 AS `h2`,
  1 AS `h3`,
  1 AS `h4`,
  1 AS `h5`,
  1 AS `h6`,
  1 AS `h7`,
  1 AS `h8`,
  1 AS `h9`,
  1 AS `h10`,
  1 AS `h11`,
  1 AS `h12`,
  1 AS `h13`,
  1 AS `h14`,
  1 AS `h15`,
  1 AS `h16`,
  1 AS `h17`,
  1 AS `h18`,
  1 AS `h1_a`,
  1 AS `h2_a`,
  1 AS `h3_a`,
  1 AS `h4_a`,
  1 AS `h5_a`,
  1 AS `h6_a`,
  1 AS `h7_a`,
  1 AS `h8_a`,
  1 AS `h9_a`,
  1 AS `h10_a`,
  1 AS `h11_a`,
  1 AS `h12_a`,
  1 AS `h13_a`,
  1 AS `h14_a`,
  1 AS `h15_a`,
  1 AS `h16_a`,
  1 AS `h17_a`,
  1 AS `h18_a`,
  1 AS `jugadorid`,
  1 AS `fecha_cap`,
  1 AS `tee_salida`,
  1 AS `color_tee`,
  1 AS `SO`,
  1 AS `SA`,
  1 AS `dif`,
  1 AS `estado`,
  1 AS `fecha_juego`,
  1 AS `tipo`,
  1 AS `salidagrupoid`,
  1 AS `categoriaid`,
  1 AS `utiliza`,
  1 AS `slope`,
  1 AS `rating`,
  1 AS `torneoid`,
  1 AS `orden`,
  1 AS `estatus`,
  1 AS `numjugador`,
  1 AS `jigidnvo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_totpuntos_gira`
--

DROP TABLE IF EXISTS `v_totpuntos_gira`;
/*!50001 DROP VIEW IF EXISTS `v_totpuntos_gira`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_totpuntos_gira` AS SELECT
 1 AS `numjugador`,
  1 AS `catidoriginal`,
  1 AS `giraid`,
  1 AS `puntos` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_ult_tarjeta`
--

DROP TABLE IF EXISTS `v_ult_tarjeta`;
/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ult_tarjeta` AS SELECT
 1 AS `torneoid`,
  1 AS `jugadorid`,
  1 AS `tarjetaid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_ult_tarjeta0`
--

DROP TABLE IF EXISTS `v_ult_tarjeta0`;
/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta0`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ult_tarjeta0` AS SELECT
 1 AS `torneoid`,
  1 AS `jugadorid`,
  1 AS `tarjetaid` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_ult_tarjeta_ec`
--

DROP TABLE IF EXISTS `v_ult_tarjeta_ec`;
/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta_ec`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `v_ult_tarjeta_ec` AS SELECT
 1 AS `torneoid`,
  1 AS `jugadorid`,
  1 AS `tarjetaid` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `zona`
--

DROP TABLE IF EXISTS `zona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `zona` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zona` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'golftour'
--

--
-- Dumping routines for database 'golftour'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_avgpuntos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_avgpuntos`(torneoidp int, posisionp int, empatesp int) RETURNS double
BEGIN
declare resp double;
DEClARE finished int;
declare puntosv int;
declare totptos int;

DEClARE curjugador     CURSOR FOR      SELECT puntos FROM agvm.puntuacion_torneo where torneoid=torneoidp order by lugar limit posisionp,empatesp;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

set totptos=0;
OPEN curjugador;

getidtar: LOOP
	FETCH curjugador INTO puntosv;
	IF finished = 1 THEN 
		LEAVE getidtar;
	END IF;
    set totptos =totptos+puntosv;
END LOOP getidtar;

CLOSE curjugador;
set resp=round(totptos/empatesp,1);

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_catgira` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_catgira`(catid int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;

SELECT catidoriginal into resp FROM categorias where categoria_id=catid;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_club` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_club`(clubid int) RETURNS char(45) CHARSET latin1 COLLATE latin1_swedish_ci
BEGIN
declare resp char(45);
set resp=0;

SELECT nombre into resp FROM acgn2021.clubs where id=clubid;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_clubid` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_clubid`(clubx char(50)) RETURNS int(11)
BEGIN
declare resp int;
set resp =0;
select id into resp from clubs where nombre=clubx;
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_empates` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_empates`(categoriap int,sop int,muertesubp int) RETURNS int(11)
BEGIN
declare resp int;
declare x int;
declare z int;

SELECT b.muertesubita,a.so,count(*) tot into x,z,resp
 FROM v_sumtarjeta3 as a join jugadores as b on (b.id=a.jugadorid and a.categoriaid=categoriap and b.estatus='NORMAL') where 1 group by b.muertesubita,a.so,a.categoriaid
 having muertesubita=muertesubp and so=sop;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_getsalid` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_getsalid`(catidp int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;

select salida into resp FROM categorias where categoria_id=catidp;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_getsalids` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_getsalids`(catidp int) RETURNS char(45) CHARSET latin1 COLLATE latin1_swedish_ci
BEGIN
declare resp char(45);
set resp='';

select tee into resp FROM categorias as a join salidas as b on (a.salida=b.id) where categoria_id=catidp;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_gira` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_gira`(torneoid int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;

select giraid into resp FROM torneo where torneo_id=torneoid;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_jugcategoria` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_jugcategoria`(catidp int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;
select count(*) into resp FROM registro WHERE reg_categoria=catidp;
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_jugcategoriareg` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_jugcategoriareg`(catidp int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;
select count(*) into resp FROM registro where status_pago<>99 and reg_categoria=catidp;
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_limite_neto` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_limite_neto`(catidp int) RETURNS int(11)
BEGIN
declare resp int;
declare neto int;
declare finished int;
declare j int;

set resp = 0;
select jugadorid into resp from v_sumtarjeta2 as a where categoriaid=catidp order by so limit 23,1;


RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_maxsalgpoid` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_maxsalgpoid`(caljgoidp int) RETURNS int(11)
BEGIN
declare resp int;

SELECT max(id) into resp FROM salidagrupo where caljuegoid=caljgoidp;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_minsalgpoid` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_minsalgpoid`(caljgoidp int) RETURNS int(11)
BEGIN
declare resp int;

SELECT min(id) into resp FROM salidagrupo where caljuegoid=caljgoidp;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_numjugcat` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_numjugcat`(catidp int) RETURNS int(11)
BEGIN
declare resp int;
declare xx int;

SELECT count(*) into xx FROM jugadores where  estatus='NORMAL' and categoriaid=catidp GRoup by  categoriaid;
if (xx is null) then begin
	set xx=0;
end; end if;
set resp=xx;
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_numjugTor` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_numjugTor`(torneoidp int) RETURNS int(11)
BEGIN
declare resp int;
declare xx int;

SELECT count(*) into xx FROM jugadores where  estatus='NORMAL' and torneoid=torneoidp GRoup by  torneoid;
if (xx is null) then begin
	set xx=0;
end; end if;
set resp=xx;
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_parcampo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_parcampo`(id_campox int,salidax int) RETURNS char(45) CHARSET latin1
BEGIN
declare parcampo char(45);
declare vpar char(1);
set parcampo='';

select par into vpar  FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=1 limit 1;
set parcampo=concat(parcampo,vpar,',');

select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=2  limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=3 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=4 limit 1 ;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=5 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=6 limit 1 ;
set parcampo=concat(parcampo,vpar,',');

select par into vpar  FROM hoyosxsalida where  id_campo=id_campox and salida=salidax and numero=7 limit 1 ;
set parcampo=concat(parcampo,vpar,',');

select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=8 limit 1 ;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=9  limit 1;
set parcampo=concat(parcampo,vpar,',');

select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=10 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=11 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=12 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=13 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=14 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=15 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=16 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=17 limit 1;
set parcampo=concat(parcampo,vpar,',');
select par into vpar   FROM hoyosxsalida where id_campo=id_campox and salida=salidax and numero=18 limit 1;
set parcampo=concat(parcampo,vpar);


RETURN parcampo;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_posgira` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_posgira`(giraidp int,catoriginalp int,puntosp double) RETURNS int(11)
BEGIN
declare resp int;

select count(*) into resp from v_totpuntos_gira where giraid=giraidp and catidoriginal=catoriginalp and puntos >=puntosp
order by puntos ;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_posicionptos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_posicionptos`(categoriap int, sop int,muertesubitap int) RETURNS int(11)
BEGIN
declare resp int;

set resp=0;
select sum(tot) into resp from v_posicionptos where categoriaid=categoriap and so<(sop+muertesubitap/10) ;
if resp is null then
begin
	set resp=0;
    end;
    else begin
		set resp=resp;
    end;
end if;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_puntos_torneo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_puntos_torneo`(torneop int,tipop int,idclubp int,girap int) RETURNS double
BEGIN
declare vtorneoid int ;
declare resp double;
set resp =0;
if torneop=1 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 0,1;
end; end if;
if torneop=2 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 1,1;
end; end if;
if torneop=3 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 2,1;
end; end if;
if torneop=4 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 3,1;
end; end if;
if torneop=5 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 4,1;
end; end if;
if torneop=6 then begin
	SELECT torneo_id into vtorneoid FROM torneo where giraid=girap order by fecha_ini  limit 5,1;
end; end if;
#
if tipop=0 then begin
	select sum(round(puntos-penalties,1)) into resp 
	from jugadores_seed as a join jugadores as b on (a.numjugador=b.numjugador and a.giraid=girap) 
	join torneo as t on (b.torneoid=t.torneo_id) 
	where a.id_club= idclubp and torneo_id=vtorneoid
	group by t.nombre,t.fecha_ini 
	order by t.fecha_ini;
end; end if;
if tipop=1 then begin
	select sum(round(puntos-penalties,1)) into resp 
	from jugadores_seed as a join jugadores as b on (a.numjugador=b.numjugador and a.giraid=girap) 
	join torneo as t on (b.torneoid=t.torneo_id) 
	where a.id_club= idclubp and torneo_id=vtorneoid and a.sexo='M';

end; end if;
if tipop=2 then begin
	select sum(round(puntos-penalties,1)) into resp 
	from jugadores_seed as a join jugadores as b on (a.numjugador=b.numjugador and a.giraid=girap) 
	join torneo as t on (b.torneoid=t.torneo_id) 
	where a.id_club= idclubp and torneo_id=vtorneoid and a.sexo='F';

end; end if;

set resp=round(resp,1);
RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_score_dia` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_score_dia`(jugid int,fecha date) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;

SELECT so into resp FROM `v_resultar` where left(fecha_juego,10)=fecha  and jugadorid=jugid;


RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `f_torneoso` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` FUNCTION `f_torneoso`(pjugid int ,ptorneoid int) RETURNS int(11)
BEGIN
declare resp int;
set resp=0;

select  sum(so) into resp 
from tarjetas
where torneoid=ptorneoid and jugadorid=pjugid
group by jugadorid,torneoid;

RETURN resp;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_calc_puntos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_calc_puntos`(categoriaidp int)
BEGIN
declare limite int;


    
update jugadores set puntos=0 where categoriaid=categoriaidp;
select f_limite_neto(categoriaidp) into limite;


END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_calc_puntos2` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_calc_puntos2`(categoriaidp int,limitep int)
BEGIN
DEClARE finished int;
declare vjugadorid int;
DEClARE curjugador     CURSOR FOR      SELECT b.id FROM v_sumtarjeta2 as a join jugadores as b on (b.id=a.jugadorid and a.categoriaid=categoriaidp and b.estatus='NORMAL') where neto<= limitep  order by so ,b.muertesubita;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;


OPEN curjugador;

getidtar: LOOP
	FETCH curjugador INTO vjugadorid;
	IF finished = 1 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;

CLOSE curjugador;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_gettop5` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_gettop5`(catidp int,griraidp int)
BEGIN
declare numetapas int;
declare catidorgv int;
declare giraidv int;
declare finished int;
declare vjugadorid char(30);

DEClARE curjugador     CURSOR FOR    SELECT numjugador FROM jugadores_seed where categoriaid=catidp and giraid=griraidp;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

SET SQL_SAFE_UPDATES = 0;
SELECT numetapastop5 into numetapas FROM categorias_tmp as a where (a.categoriasTmp_id=catidp) limit 1;
#update `jugadores` as a join categorias as b on (a.categoriaid=b.categoria_id and catidoriginal=catidorgv ) set top5=0; # se resetea todas las etapas en 0

OPEN curjugador;

getidtar: LOOP
	FETCH curjugador INTO vjugadorid;
    call sp_gettop5b(vjugadorid,numetapas,griraidp);
	IF finished = 1 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;

CLOSE curjugador;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_gettop5a_B` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_gettop5a_B`(numjugadorp int , numetapasp int,griraidp int)
BEGIN
DEClARE finished int;
declare vjugadorid int;
declare i int;

DEClARE curjugadorx     CURSOR FOR    select a.id from jugadores as a join categorias as b on (a.categoriaid=b.categoria_id and a.estatus<>'PENALTY'  and a.estatus<>'ABANDONO' )
	 join torneo  as t on (t.torneo_id= a.torneoid and t.giraid=griraidp )
	 join categorias_tmp as c on (b.catidoriginal=c.categoriasTmp_id and c.giraid=griraidp   )
	where a.numjugador=numjugadorp
	 order by puntos5_b desc;   
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
SET SQL_SAFE_UPDATES = 0;
set i=1;
update jugadores as a join categorias as b on (a.categoriaid=b.categoria_id  )
	 join torneo  as t on (t.torneo_id= a.torneoid and t.giraid=griraidp )
	 join categorias_tmp as c on (b.catidoriginal=c.categoriasTmp_id and c.giraid=griraidp   )
	 set a.top5b=0
	where a.numjugador=numjugadorp;
 
if (numetapasp=3) then begin
	OPEN curjugadorx;
getidtar: LOOP
	FETCH curjugadorx INTO vjugadorid;
    update jugadores set top5b=1 where id=vjugadorid;
    set i=i+1;
	IF finished = 1 or i>3 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;
CLOSE curjugadorx;	
end; end if;
if (numetapasp=4) then begin
OPEN curjugadorx;
getidtar: LOOP
	FETCH curjugadorx INTO vjugadorid;
    update jugadores set top5b=1 where id=vjugadorid;
    set i=i+1;
	IF finished = 1 or i>4 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;
CLOSE curjugadorx;	
end; end if;

if (numetapasp=5) then begin
	OPEN curjugadorx;
getidtar: LOOP
	FETCH curjugadorx INTO vjugadorid;
    update jugadores set top5b=1 where id=vjugadorid;
    set i=i+1;
	IF finished = 1 or i>5 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;
CLOSE curjugadorx;	
end; end if;

if (numetapasp=6) then begin
	OPEN curjugadorx;
getidtar: LOOP
	FETCH curjugadorx INTO vjugadorid;
    update jugadores set top5b=1 where id=vjugadorid;
    set i=i+1;
	IF finished = 1 or i>6 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;
CLOSE curjugadorx;	
end; end if;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_gettop5b` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_gettop5b`(numjugadorp char(30) , numetapasp int,griraidp int)
BEGIN
DEClARE finished int;
declare vjugadorid int;
declare i int;


DEClARE curjugadorx     CURSOR FOR    select a.id from jugadores as a join categorias as b on (a.categoriaid=b.categoria_id and a.estatus<>'PENALTY'  )
	 join torneo  as t on (t.torneo_id= a.torneoid and t.giraid=griraidp )
	 join categorias_tmp as c on (b.catidoriginal=c.categoriasTmp_id and c.giraid=griraidp   )
	where a.numjugador=numjugadorp and puntos>0
	 order by puntos desc;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;
SET SQL_SAFE_UPDATES = 0;
set i=1;
update  jugadores as a join categorias as b on (a.categoriaid=b.categoria_id and a.estatus<>'PENALTY'  )
	 join torneo  as t on (t.torneo_id= a.torneoid and t.giraid=griraidp )
	 join categorias_tmp as c on (b.catidoriginal=c.categoriasTmp_id and c.giraid=griraidp   )
	 set a.top5=0
	where a.numjugador=numjugadorp
     order by puntos desc;  
    
     
if (numetapasp=3) then begin
	OPEN curjugadorx;
	getidtar: LOOP
		FETCH curjugadorx INTO vjugadorid;
		update jugadores set top5=1 where id=vjugadorid;
		set i=i+1;
		IF finished = 1 or i>3 THEN 
			LEAVE getidtar;
		END IF;
	END LOOP getidtar;
	CLOSE curjugadorx;	
end; end if;

if (numetapasp=4) then begin
	OPEN curjugadorx;
	getidtar: LOOP
		FETCH curjugadorx INTO vjugadorid;
		update jugadores set top5=1 where id=vjugadorid;
		set i=i+1;
		IF finished = 1 or i>4 THEN 
			LEAVE getidtar;
		END IF;
	END LOOP getidtar;
	CLOSE curjugadorx;	
end; end if;

if (numetapasp=5) then begin
	OPEN curjugadorx;
	getidtar: LOOP
		FETCH curjugadorx INTO vjugadorid;
		update jugadores set top5=1 where id=vjugadorid;
		set i=i+1;
		IF finished = 1 or i>5 THEN 
			LEAVE getidtar;
		END IF;
	END LOOP getidtar;
	CLOSE curjugadorx;	
end; end if;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_gettop5_B` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`aliensystem`@`%` PROCEDURE `sp_gettop5_B`(catidp int,griraidp int)
BEGIN
declare numetapas int;
declare catidorgv int;
declare giraidv int;
declare finished int;
declare vjugadorid int;

DEClARE curjugador     CURSOR FOR    SELECT numjugador FROM jugadores_seed where categoriaid=catidp and giraid=griraidp and top5_b=1;
DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;


SET SQL_SAFE_UPDATES = 0;
SELECT numetapastop5b into numetapas FROM categorias_tmp as a where (a.categoriasTmp_id=catidp) limit 1;
#
update `jugadores` as a join categorias as b on (a.categoriaid=b.categoria_id and catidoriginal=catidorgv  ) 
 join categorias_tmp as c on (b.catidoriginal=c.categoriasTmp_id and c.giraid=griraidp   ) set a.top5b=0; # se resetea todas las etapas en 0

OPEN curjugador;

getidtar: LOOP
	FETCH curjugador INTO vjugadorid;
    call sp_gettop5a_B(vjugadorid,numetapas,griraidp);
	IF finished = 1 THEN 
		LEAVE getidtar;
	END IF;
END LOOP getidtar;

CLOSE curjugador;

END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `result_ult_tar`
--

/*!50001 DROP VIEW IF EXISTS `result_ult_tar`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `result_ult_tar` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`tarjetaid` AS `tarjetaid`,`b`.`h18` + `b`.`h17` + `b`.`h16` + `b`.`h15` + `b`.`h14` + `b`.`h13` + `b`.`h12` + `b`.`h11` + `b`.`h10` AS `v2`,`b`.`SO` AS `ult_so` from (`v_ult_tarjeta` `a` join `tarjetas` `b` on(`a`.`tarjetaid` = `b`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `result_ult_tar_ec`
--

/*!50001 DROP VIEW IF EXISTS `result_ult_tar_ec`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `result_ult_tar_ec` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`tarjetaid` AS `tarjetaid`,`b`.`h18` + `b`.`h17` + `b`.`h16` + `b`.`h15` + `b`.`h14` + `b`.`h13` + `b`.`h12` + `b`.`h11` + `b`.`h10` AS `v2`,`b`.`SO` AS `ult_so` from (`v_ult_tarjeta_ec` `a` join `tarjetas` `b` on(`a`.`tarjetaid` = `b`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_caljgo_salgpo`
--

/*!50001 DROP VIEW IF EXISTS `v_caljgo_salgpo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_caljgo_salgpo` AS select distinct `salidagrupo`.`caljuegoid` AS `caljuegoid`,`salidagrupo`.`pwd` AS `pwd` from `salidagrupo` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_cd_ulttar`
--

/*!50001 DROP VIEW IF EXISTS `v_cd_ulttar`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_cd_ulttar` AS select `a`.`torneoid` AS `torneoid`,`a`.`jugadorid` AS `jugadorid`,if(`b`.`h18` = 0,`b`.`h9`,`b`.`h18`) AS `c1`,if(`b`.`h17` = 0,`b`.`h8`,`b`.`h17`) AS `c2`,if(`b`.`h16` = 0,`b`.`h7`,`b`.`h16`) AS `c3`,if(`b`.`h18` = 0,`b`.`h6` + `b`.`h5` + `b`.`h4`,`b`.`h15` + `b`.`h14` + `b`.`h13`) AS `c4`,if(`b`.`h18` = 0,`b`.`h3` + `b`.`h2` + `b`.`h1`,`b`.`h12` + `b`.`h11` + `b`.`h10`) AS `c5`,if(`b`.`h1` = 0,`b`.`h10` + `b`.`h11` + `b`.`h12` + `b`.`h13` + `b`.`h14` + `b`.`h15` + `b`.`h16` + `b`.`h17` + `b`.`h18`,`b`.`h1` + `b`.`h2` + `b`.`h3` + `b`.`h4` + `b`.`h5` + `b`.`h6` + `b`.`h7` + `b`.`h8` + `b`.`h9`) AS `c6` from (`v_ult_tarjeta` `a` join `tarjetas` `b` on(`a`.`tarjetaid` = `b`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_cd_ulttar_ec`
--

/*!50001 DROP VIEW IF EXISTS `v_cd_ulttar_ec`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_cd_ulttar_ec` AS select `a`.`torneoid` AS `torneoid`,`a`.`jugadorid` AS `jugadorid`,if(`b`.`h18` = 0,`b`.`h9`,`b`.`h18`) AS `c1`,if(`b`.`h17` = 0,`b`.`h8`,`b`.`h17`) AS `c2`,if(`b`.`h16` = 0,`b`.`h7`,`b`.`h16`) AS `c3`,if(`b`.`h18` = 0,`b`.`h6` + `b`.`h5` + `b`.`h4`,`b`.`h15` + `b`.`h14` + `b`.`h13`) AS `c4`,if(`b`.`h18` = 0,`b`.`h3` + `b`.`h2` + `b`.`h1`,`b`.`h12` + `b`.`h11` + `b`.`h10`) AS `c5`,if(`b`.`h1` = 0,`b`.`h10` + `b`.`h11` + `b`.`h12` + `b`.`h13` + `b`.`h14` + `b`.`h15` + `b`.`h16` + `b`.`h17` + `b`.`h18`,`b`.`h1` + `b`.`h2` + `b`.`h3` + `b`.`h4` + `b`.`h5` + `b`.`h6` + `b`.`h7` + `b`.`h8` + `b`.`h9`) AS `c6` from (`v_ult_tarjeta_ec` `a` join `tarjetas` `b` on(`a`.`tarjetaid` = `b`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_diasjgo`
--

/*!50001 DROP VIEW IF EXISTS `v_diasjgo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_diasjgo` AS select `a`.`torneoid` AS `torneoid`,`a`.`categoria` AS `categoria`,min(`a`.`fecha`) AS `INICIA`,max(`a`.`fecha`) AS `TERMINA`,count(0) AS `diasjgo`,`b`.`categoria_id` AS `categoria_id` from (`caljuego` `a` join `categorias` `b` on(`a`.`torneoid` = `b`.`torneo_id` and `a`.`categoria` = `b`.`categoria`)) where `a`.`campo` > 0 group by `a`.`torneoid`,`a`.`categoria`,`b`.`categoria_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_diasjgo_categoria`
--

/*!50001 DROP VIEW IF EXISTS `v_diasjgo_categoria`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_diasjgo_categoria` AS select `a`.`categoriaid` AS `categoriaid`,`a`.`diajuegoid` AS `diajuegoid`,`b`.`fecha` AS `fecha` from (`salidasTorneo` `a` join `diasjuego` `b` on(`a`.`diajuegoid` = `b`.`id`)) order by `b`.`fecha` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_difpar_tarjeta`
--

/*!50001 DROP VIEW IF EXISTS `v_difpar_tarjeta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_difpar_tarjeta` AS select `a`.`id` AS `tarjetaid`,if(`a`.`h1` > 0,cast(`a`.`h1` as signed) - cast(`p`.`h1` as signed),0) + if(`a`.`h2` > 0,cast(`a`.`h2` as signed) - cast(`p`.`h2` as signed),0) + if(`a`.`h3` > 0,cast(`a`.`h3` as signed) - cast(`p`.`h3` as signed),0) + if(`a`.`h4` > 0,cast(`a`.`h4` as signed) - cast(`p`.`h4` as signed),0) + if(`a`.`h5` > 0,cast(`a`.`h5` as signed) - cast(`p`.`h5` as signed),0) + if(`a`.`h6` > 0,cast(`a`.`h6` as signed) - cast(`p`.`h6` as signed),0) + if(`a`.`h7` > 0,cast(`a`.`h7` as signed) - cast(`p`.`h7` as signed),0) + if(`a`.`h8` > 0,cast(`a`.`h8` as signed) - cast(`p`.`h8` as signed),0) + if(`a`.`h9` > 0,cast(`a`.`h9` as signed) - cast(`p`.`h9` as signed),0) + if(`a`.`h10` > 0,cast(`a`.`h10` as signed) - cast(`p`.`h10` as signed),0) + if(`a`.`h11` > 0,cast(`a`.`h11` as signed) - cast(`p`.`h11` as signed),0) + if(`a`.`h12` > 0,cast(`a`.`h12` as signed) - cast(`p`.`h12` as signed),0) + if(`a`.`h13` > 0,cast(`a`.`h13` as signed) - cast(`p`.`h13` as signed),0) + if(`a`.`h14` > 0,cast(cast(`a`.`h14` as signed) - cast(`p`.`h14` as signed) as signed),0) + if(`a`.`h15` > 0,cast(`a`.`h15` as signed) - cast(`p`.`h15` as signed),0) + if(`a`.`h16` > 0,cast(`a`.`h16` as signed) - cast(`p`.`h16` as signed),0) + if(`a`.`h17` > 0,cast(`a`.`h17` as signed) - cast(`p`.`h17` as signed),0) + if(`a`.`h18` > 0,cast(`a`.`h18` as signed) - cast(`p`.`h18` as signed),0) AS `difpar` from (`tarjetas` `a` join `par_campo` `p` on(`p`.`id_campo` = `a`.`id_campo`)) where 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_difpar_ulttarjeta`
--

/*!50001 DROP VIEW IF EXISTS `v_difpar_ulttarjeta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_difpar_ulttarjeta` AS select `v`.`jugadorid` AS `jugadorid`,if(`a`.`h1` > 0,1,0) + if(`a`.`h2` > 0,1,0) + if(`a`.`h3` > 0,1,0) + if(`a`.`h4` > 0,1,0) + if(`a`.`h5` > 0,1,0) + if(`a`.`h6` > 0,1,0) + if(`a`.`h7` > 0,1,0) + if(`a`.`h8` > 0,1,0) + if(`a`.`h9` > 0,1,0) + if(`a`.`h10` > 0,1,0) + if(`a`.`h11` > 0,1,0) + if(`a`.`h12` > 0,1,0) + if(`a`.`h13` > 0,1,0) + if(`a`.`h14` > 0,1,0) + if(`a`.`h15` > 0,1,0) + if(`a`.`h16` > 0,1,0) + if(`a`.`h17` > 0,1,0) + if(`a`.`h18` > 0,1,0) AS `avance`,if(`a`.`h1` > 0,cast(`a`.`h1` as signed) - cast(`p`.`h1` as signed),0) + if(`a`.`h2` > 0,cast(`a`.`h2` as signed) - cast(`p`.`h2` as signed),0) + if(`a`.`h3` > 0,cast(`a`.`h3` as signed) - cast(`p`.`h3` as signed),0) + if(`a`.`h4` > 0,cast(`a`.`h4` as signed) - cast(`p`.`h4` as signed),0) + if(`a`.`h5` > 0,cast(`a`.`h5` as signed) - cast(`p`.`h5` as signed),0) + if(`a`.`h6` > 0,cast(`a`.`h6` as signed) - cast(`p`.`h6` as signed),0) + if(`a`.`h7` > 0,cast(`a`.`h7` as signed) - cast(`p`.`h7` as signed),0) + if(`a`.`h8` > 0,cast(`a`.`h8` as signed) - cast(`p`.`h8` as signed),0) + if(`a`.`h9` > 0,cast(`a`.`h9` as signed) - cast(`p`.`h9` as signed),0) + if(`a`.`h10` > 0,cast(`a`.`h10` as signed) - cast(`p`.`h10` as signed),0) + if(`a`.`h11` > 0,cast(`a`.`h11` as signed) - cast(`p`.`h11` as signed),0) + if(`a`.`h12` > 0,cast(`a`.`h12` as signed) - cast(`p`.`h12` as signed),0) + if(`a`.`h13` > 0,cast(`a`.`h13` as signed) - cast(`p`.`h13` as signed),0) + if(`a`.`h14` > 0,cast(cast(`a`.`h14` as signed) - cast(`p`.`h14` as signed) as signed),0) + if(`a`.`h15` > 0,cast(`a`.`h15` as signed) - cast(`p`.`h15` as signed),0) + if(`a`.`h16` > 0,cast(`a`.`h16` as signed) - cast(`p`.`h16` as signed),0) + if(`a`.`h17` > 0,cast(`a`.`h17` as signed) - cast(`p`.`h17` as signed),0) + if(`a`.`h18` > 0,cast(`a`.`h18` as signed) - cast(`p`.`h18` as signed),0) AS `difpar_ulttar` from ((`tarjetas` `a` join `v_ult_tarjeta0` `v` on(`a`.`id` = `v`.`tarjetaid`)) join `par_campo` `p` on(`p`.`id_campo` = `a`.`id_campo`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_estatorneo`
--

/*!50001 DROP VIEW IF EXISTS `v_estatorneo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_estatorneo` AS select `a`.`numjugador` AS `numjugador`,`a`.`nombre` AS `nombre`,`a`.`apellido` AS `apellido`,`a`.`indexjgo` AS `indexjgo`,round(`a`.`indexjgo` * 0.8,0) AS `round((a.indexjgo*.8),0)`,`a`.`torneoid` AS `torneoid`,`b`.`categoria` AS `categoria`,`a`.`club` AS `club` from (`jugadores` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_gira_so`
--

/*!50001 DROP VIEW IF EXISTS `v_gira_so`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_gira_so` AS select `t`.`giraid` AS `giraid`,`a`.`numjugador` AS `numjugador`,sum(`b`.`SO`) AS `so` from ((`jugadores` `a` join `tarjetas` `b` on(`b`.`jugadorid` = `a`.`id`)) join `torneo` `t` on(`a`.`torneoid` = `t`.`torneo_id`)) group by `t`.`giraid`,`a`.`numjugador` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_gira_so_ptos`
--

/*!50001 DROP VIEW IF EXISTS `v_gira_so_ptos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_gira_so_ptos` AS select `t`.`giraid` AS `giraid`,`a`.`numjugador` AS `numjugador`,sum(`b`.`SO`) AS `so`,sum(`a`.`puntos`) AS `ptos`,avg(`b`.`SO`) AS `avgso`,min(if(`b`.`SO` > 0,`b`.`SO`,1000)) AS `minso`,max(`b`.`SO`) AS `maxso` from ((`jugadores` `a` join `tarjetas` `b` on(`b`.`jugadorid` = `a`.`id`)) join `torneo` `t` on(`a`.`torneoid` = `t`.`torneo_id`)) group by `t`.`giraid`,`a`.`numjugador` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_horariox`
--

/*!50001 DROP VIEW IF EXISTS `v_horariox`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_horariox` AS select `a`.`id` AS `id`,`a`.`horainicio1a` AS `horainicio1a`,`a`.`teesal` AS `teesal`,`a`.`numjug` AS `numjug`,`c`.`categoria` AS `catjugador`,`b`.`fecha` AS `fecha`,`d`.`campo` AS `campo`,concat(`b`.`fecha`,'  ',`d`.`campo`) AS `agrupo`,`a`.`torneoid` AS `torneoid`,`a`.`pwd` AS `pwd`,`b`.`estatus` AS `estatus` from ((((`salidagrupo` `a` join `caljuego` `b` on(`a`.`caljuegoid` = `b`.`id` and `a`.`torneoid` = `b`.`torneoid`)) join `categorias` `c` on(`b`.`categoriaid` = `c`.`categoria_id`)) join `copa` `p` on(`c`.`concatenarid` = `p`.`idcopa`)) join `campos` `d` on(`b`.`campo` = `d`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_jugxcat`
--

/*!50001 DROP VIEW IF EXISTS `v_jugxcat`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_jugxcat` AS select `jugadores`.`torneoid` AS `torneoid`,`jugadores`.`categoriaid` AS `categoriaid`,count(0) AS `totjug` from `jugadores` where `jugadores`.`estatus` = 'NORMAL' group by `jugadores`.`torneoid`,`jugadores`.`categoriaid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_jugxcat2`
--

/*!50001 DROP VIEW IF EXISTS `v_jugxcat2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_jugxcat2` AS select `a`.`torneoid` AS `torneoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`totjug` AS `totjug`,`b`.`categoria` AS `categoria` from (`v_jugxcat` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_lista_jug`
--

/*!50001 DROP VIEW IF EXISTS `v_lista_jug`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_lista_jug` AS select `a`.`id` AS `id`,`a`.`torneoid` AS `torneoid`,`a`.`numjugador` AS `numjugador`,`a`.`nombre` AS `nombre`,`a`.`apellido` AS `apellido`,`a`.`sexo` AS `sexo`,`c`.`nombre` AS `club`,`b`.`categoria` AS `categoria`,`a`.`grupoid` AS `grupoid`,`a`.`estatus` AS `estatus`,`a`.`puntossinp` AS `puntossinp`,`a`.`puntossinp2` AS `puntossinp2`,`a`.`fechanac` AS `fechanac`,if((to_days(`a`.`fechanac`) - to_days(`b`.`fechainicio`)) / 365 < 0,(to_days(`b`.`fechainicio`) - to_days(`a`.`fechanac`)) / 365,0) AS `diasmayorcatego`,`b`.`maxedad` AS `maxedad`,`a`.`categoriaid` AS `categoriaid`,`a`.`clubid` AS `clubid`,`c`.`logo` AS `logoclub`,`a`.`puntos` AS `puntos`,`a`.`puntos2` AS `puntos2`,`a`.`cd1` AS `cd1`,`a`.`cd2` AS `cd2`,`a`.`cd3` AS `cd3`,`a`.`cd4` AS `cd4`,`a`.`cd5` AS `cd5`,`a`.`cd6` AS `cd6`,`a`.`muertesubita` AS `muertesubita`,`a`.`correo` AS `correo`,`a`.`teesalidaid` AS `teesalidaid`,`a`.`salida` AS `salida` from ((`jugadores` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id`)) join `clubs` `c` on(`a`.`clubid` = `c`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_livesacor_pos`
--

/*!50001 DROP VIEW IF EXISTS `v_livesacor_pos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_livesacor_pos` AS select `tarjetas`.`categoriaid` AS `categoriaid`,`tarjetas`.`jugadorid` AS `jugadorid`,sum(`tarjetas`.`SO`) AS `so`,sum(`tarjetas`.`h1` + `tarjetas`.`h2` + `tarjetas`.`h3` + `tarjetas`.`h4` + `tarjetas`.`h5` + `tarjetas`.`h6` + `tarjetas`.`h7` + `tarjetas`.`h8` + `tarjetas`.`h9` + `tarjetas`.`h10` + `tarjetas`.`h11` + `tarjetas`.`h12` + `tarjetas`.`h13` + `tarjetas`.`h14` + `tarjetas`.`h15` + `tarjetas`.`h16` + `tarjetas`.`h17` + `tarjetas`.`h18`) AS `rdn`,count(0) AS `rondas`,max(`tarjetas`.`id`) AS `maxid` from `tarjetas` group by `tarjetas`.`categoriaid`,`tarjetas`.`jugadorid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_mejor_score`
--

/*!50001 DROP VIEW IF EXISTS `v_mejor_score`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_mejor_score` AS select `a`.`numjugador` AS `numjugador`,`c`.`giraid` AS `giraid`,`c`.`corte` AS `corte`,`c`.`maxjugadores` AS `maxs`,min(`t`.`SO`) AS `minso`,count(0) AS `numrondas` from (((`jugadores` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id` and `a`.`estatus` = 'NORMAL')) join `categorias_tmp` `c` on(`c`.`categoriasTmp_id` = `b`.`catidoriginal`)) join `tarjetas` `t` on(`a`.`id` = `t`.`jugadorid` and `t`.`estatus` = 'N' and (`t`.`h1` > 0 and `t`.`h10` > 0 and `c`.`corte` = 18 or (`t`.`h1` > 0 or `t`.`h10` > 0) and `c`.`corte` = 9))) where 1 group by `a`.`numjugador`,`c`.`giraid`,`c`.`corte`,`c`.`maxjugadores` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_mejor_score_lista`
--

/*!50001 DROP VIEW IF EXISTS `v_mejor_score_lista`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_mejor_score_lista` AS select `a`.`numjugador` AS `numjugador`,`b`.`categoria` AS `categoria`,`a`.`giraid` AS `giraid`,`b`.`categoriasTmp_id` AS `categoriasTmp_id` from (`jugadores_seed` `a` join `categorias_tmp` `b` on(`a`.`categoriaid` = `b`.`categoriasTmp_id` and `a`.`giraid` = `b`.`giraid` and `a`.`cd2` > 1)) where 1 order by `b`.`categoriasTmp_id`,`a`.`cd3` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_mejor_score_lista3`
--

/*!50001 DROP VIEW IF EXISTS `v_mejor_score_lista3`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_mejor_score_lista3` AS select `a`.`numjugador` AS `numjugador`,`b`.`categoria` AS `categoria`,`a`.`giraid` AS `giraid`,`b`.`categoriasTmp_id` AS `categoriasTmp_id` from (`jugadores_seed` `a` join `categorias_tmp` `b` on(`a`.`categoriaid` = `b`.`categoriasTmp_id` and `a`.`giraid` = `b`.`giraid` and `a`.`cd2` = 3)) where 1 order by `b`.`categoriasTmp_id`,`a`.`cd3` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_mejor_tarjeta`
--

/*!50001 DROP VIEW IF EXISTS `v_mejor_tarjeta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_mejor_tarjeta` AS select `a`.`numjugador` AS `numjugador`,`t`.`giraid` AS `giraid`,min(`b`.`id`) AS `tarid` from (((`jugadores` `a` join `tarjetas` `b` on(`a`.`id` = `b`.`jugadorid`)) join `v_mejor_score` `c` on(`c`.`numjugador` = `a`.`numjugador` and `c`.`minso` = `b`.`SO` and `c`.`numrondas` >= `c`.`maxs`)) join `torneo` `t` on(`b`.`torneoid` = `t`.`torneo_id` and `t`.`giraid` = `c`.`giraid`)) group by `a`.`numjugador`,`t`.`giraid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_parcampo`
--

/*!50001 DROP VIEW IF EXISTS `v_parcampo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_parcampo` AS select `hoyosxsalida`.`id_campo` AS `campoid`,`hoyosxsalida`.`salida` AS `salidaid`,sum(`hoyosxsalida`.`par`) AS `pas` from `hoyosxsalida` group by `hoyosxsalida`.`id_campo`,`hoyosxsalida`.`salida` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_parcampoxsalida`
--

/*!50001 DROP VIEW IF EXISTS `v_parcampoxsalida`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_parcampoxsalida` AS select `hoyosxsalida`.`id_campo` AS `campoid`,`hoyosxsalida`.`salida` AS `salidaid`,sum(`hoyosxsalida`.`par`) AS `parcampo` from `hoyosxsalida` group by `hoyosxsalida`.`id_campo`,`hoyosxsalida`.`salida` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_posicionptos`
--

/*!50001 DROP VIEW IF EXISTS `v_posicionptos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_posicionptos` AS select `b`.`muertesubita` AS `muertesubita`,`a`.`so` + `b`.`muertesubita` / 10 AS `so`,`a`.`categoriaid` AS `categoriaid`,count(0) AS `tot` from (`v_sumtarjeta3` `a` join `jugadores` `b` on(`b`.`id` = `a`.`jugadorid` and `b`.`estatus` = 'NORMAL')) group by `b`.`muertesubita`,`a`.`so`,`a`.`categoriaid` order by `a`.`so`,`b`.`muertesubita` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resultar`
--

/*!50001 DROP VIEW IF EXISTS `v_resultar`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_resultar` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`SO` AS `SO`,`a`.`SA` AS `SA`,`a`.`estado` AS `estado`,`a`.`fecha_juego` AS `fecha_juego`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`torneoid` AS `torneoid`,`b`.`horainicio1a` AS `fechasal`,`b`.`teesal` AS `teesal` from (`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) order by `a`.`fecha_juego` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resultar_ec`
--

/*!50001 DROP VIEW IF EXISTS `v_resultar_ec`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_resultar_ec` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`SO` AS `SO`,`a`.`SA` AS `SA`,`a`.`estado` AS `estado`,`a`.`fecha_juego` AS `fecha_juego`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`torneoid` AS `torneoid`,`b`.`horainicio1a` AS `fechasal`,`b`.`teesal` AS `teesal` from ((`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) join `caljuego` `j` on(`b`.`caljuegoid` = `j`.`id` and `j`.`estatus` = 3)) order by `a`.`fecha_juego` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resultarconcat`
--

/*!50001 DROP VIEW IF EXISTS `v_resultarconcat`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_resultarconcat` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`SO` AS `SO`,`a`.`SA` AS `SA`,`a`.`estado` AS `estado`,`a`.`fecha_juego` AS `fecha_juego`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`torneoid` AS `torneoid`,`b`.`horainicio1a` AS `fechasal`,`b`.`teesal` AS `teesal`,`a`.`dif` AS `dif`,`g`.`concatenarid` AS `concatenarid` from (((`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) join `jugadores` `c` on(`a`.`jugadorid` = `c`.`id` and `c`.`estatus` in ('NORMAL','FIRST','SECOND','OUT'))) join `categorias` `g` on(`c`.`categoriaid` = `g`.`categoria_id`)) order by `a`.`fecha_juego` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_rondasxgira`
--

/*!50001 DROP VIEW IF EXISTS `v_rondasxgira`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_rondasxgira` AS select `a`.`numjugador` AS `numjugador`,`d`.`giraid` AS `giraid`,count(0) AS `rondas` from ((((`jugadores_seed` `a` join `jugadores` `b` on(`a`.`numjugador` = `b`.`numjugador`)) join `categorias` `c` on(`b`.`categoriaid` = `c`.`categoria_id`)) join `categorias_tmp` `d` on(`c`.`catidoriginal` = `d`.`categoriasTmp_id` and `a`.`giraid` = `d`.`giraid`)) join `tarjetas` `t` on(`b`.`id` = `t`.`jugadorid`)) where 1 group by `a`.`numjugador`,`a`.`giraid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sal_jug`
--

/*!50001 DROP VIEW IF EXISTS `v_sal_jug`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sal_jug` AS select `a`.`id` AS `tarjetaid`,`b`.`salidatorneoid` AS `salidatorneoid`,`a`.`id_campo` AS `id_campo`,`a`.`jugadorid` AS `jugadorid`,`a`.`tee_salida` AS `tee_salida`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`slope` AS `slope`,`a`.`rating` AS `rating`,`b`.`horainicio1a` AS `horainicio1a`,`b`.`horainicio2a` AS `horainicio2a`,`b`.`teesal` AS `teesal`,`c`.`numjugador` AS `numjugador`,`c`.`nombre` AS `nombre`,`c`.`apellido` AS `apellido`,`c`.`fechahandicap` AS `fechahandicap`,`c`.`sexo` AS `sexo`,`c`.`hcpindex` AS `hcpindex`,`c`.`teesalidaid` AS `teesalidaid`,`c`.`correo` AS `correo`,`c`.`club` AS `clubjug`,`c`.`tipoinsc` AS `tipoinsc`,`c`.`tipoinsc2` AS `tipoinsc2`,`c`.`indexjgo` AS `indexjgo`,`c`.`salida` AS `colortee`,`d`.`tee` AS `tee`,`c`.`categoriaid` AS `categoriaid`,`c`.`grupoid` AS `grupoid`,`c`.`torneoid` AS `torneoid`,`c`.`estatus` AS `jugestatus`,`a`.`fecha_juego` AS `fecha_juego`,`b`.`caljuegoid` AS `caljuegoid`,`a`.`orden` AS `orden`,`l`.`logo` AS `logo`,`l`.`nombre` AS `club`,`F_TORNEOSO`(`a`.`jugadorid`,`c`.`torneoid`) AS `acumso`,`cc`.`sistema` AS `sistema`,`cc`.`categoria` AS `catjugador` from (((((`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) join `jugadores` `c` on(`a`.`jugadorid` = `c`.`id`)) join `categorias` `cc` on(`c`.`categoriaid` = `cc`.`categoria_id`)) join `clubs` `l` on(`c`.`clubid` = `l`.`id`)) join `salidas` `d` on(`d`.`id` = `a`.`tee_salida`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas` AS select `a`.`salidagrupoid` AS `salidagrupoid`,`a`.`jugadorid` AS `jugadorid`,`a`.`tee_salida` AS `tee_salida`,`c`.`fecha` AS `fecha`,`b`.`horainicio1a` AS `horainicio1a`,`a`.`categoriaid` AS `categoriaid`,`c`.`estatus` AS `estatus`,`a`.`torneoid` AS `torneoid` from ((`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) join `caljuego` `c` on(`b`.`caljuegoid` = `c`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_gpo`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_gpo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_gpo` AS select `v_salidas`.`fecha` AS `fecha`,`v_salidas`.`categoriaid` AS `categoriaid`,`v_salidas`.`torneoid` AS `torneoid`,`v_salidas`.`estatus` AS `estatus` from `v_salidas` group by `v_salidas`.`fecha`,`v_salidas`.`categoriaid`,`v_salidas`.`torneoid`,`v_salidas`.`estatus` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_h1`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_h1`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_h1` AS select `salidagrupo`.`id` AS `id`,`salidagrupo`.`salidatorneoid` AS `salidatorneoid`,`salidagrupo`.`horainicio1a` AS `horainicio1a`,`salidagrupo`.`horafin1a` AS `horafin1a`,`salidagrupo`.`horainicio2a` AS `horainicio2a`,`salidagrupo`.`horafin2a` AS `horafin2a`,`salidagrupo`.`categoriaid` AS `categoriaid`,`salidagrupo`.`teesal` AS `teesal`,`salidagrupo`.`caljuegoid` AS `caljuegoid` from `salidagrupo` where `salidagrupo`.`teesal` in ('h1am','h1pm') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_h10`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_h10`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_h10` AS select `salidagrupo`.`id` AS `id`,`salidagrupo`.`salidatorneoid` AS `salidatorneoid`,`salidagrupo`.`horainicio1a` AS `horainicio1a`,`salidagrupo`.`horafin1a` AS `horafin1a`,`salidagrupo`.`horainicio2a` AS `horainicio2a`,`salidagrupo`.`horafin2a` AS `horafin2a`,`salidagrupo`.`categoriaid` AS `categoriaid`,`salidagrupo`.`teesal` AS `teesal`,`salidagrupo`.`caljuegoid` AS `caljuegoid` from `salidagrupo` where `salidagrupo`.`teesal` in ('h10am','h10pm') */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_ls`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_ls`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_ls` AS select `a`.`torneoid` AS `torneoid`,`a`.`fecha` AS `fecha`,`b`.`campo` AS `campo`,`b`.`id` AS `campoid`,left(md5(concat(`a`.`fecha`,'Marene10+')),6) AS `pwd` from (`caljuego` `a` join `campos` `b` on(`a`.`campo` = `b`.`id`)) where `a`.`estatus` = 2 and `a`.`campo` > 0 group by `a`.`torneoid`,`a`.`fecha`,`b`.`campo`,`b`.`id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_tarj`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_tarj`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_tarj` AS select `t`.`id` AS `id`,`t`.`salidagrupoid` AS `salidagrupoid`,`a`.`horainicio1a` AS `horainicio1a`,`a`.`teesal` AS `teesal`,`a`.`numjug` AS `numjug`,`c`.`categoria` AS `categoria`,`b`.`fecha` AS `fecha`,`d`.`campo` AS `campo`,concat(' fecha ',`b`.`fecha`,' Categoria ',`c`.`categoria`) AS `agrupo`,`j`.`numjugador` AS `numjugador`,`j`.`id` AS `jugid`,`j`.`nombre` AS `nombre`,`j`.`apellido` AS `apellido`,`j`.`torneoid` AS `torneoid`,`t`.`orden` AS `orden`,`g`.`nombre` AS `club`,`j`.`clubid` AS `clubid`,`t`.`SO` AS `so`,`b`.`categoriaid` AS `categoriaid`,`j`.`estatus` AS `juestatus`,if(`t`.`h1` > 0,1,0) + if(`t`.`h2` > 0,1,0) + if(`t`.`h3` > 0,1,0) + if(`t`.`h4` > 0,1,0) + if(`t`.`h5` > 0,1,0) + if(`t`.`h6` > 0,1,0) + if(`t`.`h7` > 0,1,0) + if(`t`.`h8` > 0,1,0) + if(`t`.`h9` > 0,1,0) + if(`t`.`h10` > 0,1,0) + if(`t`.`h11` > 0,1,0) + if(`t`.`h12` > 0,1,0) + if(`t`.`h13` > 0,1,0) + if(`t`.`h14` > 0,1,0) + if(`t`.`h15` > 0,1,0) + if(`t`.`h16` > 0,1,0) + if(`t`.`h17` > 0,1,0) + if(`t`.`h18` > 0,1,0) AS `avance` from ((((((`salidagrupo` `a` join `caljuego` `b` on(`a`.`caljuegoid` = `b`.`id` and `b`.`estatus` = 1 and `a`.`torneoid` = `b`.`torneoid`)) join `categorias` `c` on(`b`.`categoriaid` = `c`.`categoria_id`)) join `campos` `d` on(`b`.`campo` = `d`.`id`)) join `tarjetas` `t` on(`t`.`salidagrupoid` = `a`.`id`)) join `jugadores` `j` on(`j`.`id` = `t`.`jugadorid`)) left join `clubs` `g` on(`j`.`clubid` = `g`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_tarj1`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_tarj1`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_tarj1` AS select `t`.`id` AS `id`,`t`.`salidagrupoid` AS `salidagrupoid`,`a`.`horainicio1a` AS `horainicio1a`,`a`.`teesal` AS `teesal`,`a`.`numjug` AS `numjug`,`c`.`categoria` AS `categoria`,`b`.`fecha` AS `fecha`,`d`.`campo` AS `campo`,concat(' fecha ',`b`.`fecha`,' Categoria ',`c`.`categoria`) AS `agrupo`,concat(' fecha ',`b`.`fecha`,' Categoria ',`cj`.`categoria`) AS `agrupo2`,`j`.`numjugador` AS `numjugador`,`j`.`id` AS `jugid`,`j`.`nombre` AS `nombre`,`j`.`apellido` AS `apellido`,`j`.`torneoid` AS `torneoid`,`t`.`orden` AS `orden`,`g`.`nombre` AS `club`,`j`.`clubid` AS `clubid`,`t`.`SO` AS `so`,`a`.`caljuegoid` AS `caljuegoid`,`b`.`categoriaid` AS `categoriaid` from (((((((`salidagrupo` `a` join `caljuego` `b` on(`a`.`caljuegoid` = `b`.`id` and `b`.`estatus` > 0 and `b`.`estatus` < 3 and `a`.`torneoid` = `b`.`torneoid`)) join `categorias` `c` on(`b`.`categoriaid` = `c`.`categoria_id`)) join `campos` `d` on(`b`.`campo` = `d`.`id`)) join `tarjetas` `t` on(`t`.`salidagrupoid` = `a`.`id`)) join `jugadores` `j` on(`j`.`id` = `t`.`jugadorid`)) left join `clubs` `g` on(`j`.`clubid` = `g`.`id`)) join `categorias` `cj` on(`j`.`categoriaid` = `cj`.`categoria_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_tarjT`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_tarjT`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_tarjT` AS select `t`.`id` AS `id`,`t`.`salidagrupoid` AS `salidagrupoid`,`a`.`horainicio1a` AS `horainicio1a`,`a`.`teesal` AS `teesal`,`a`.`numjug` AS `numjug`,`c`.`categoria` AS `categoria`,`b`.`fecha` AS `fecha`,`d`.`campo` AS `campo`,concat(' fecha ',`b`.`fecha`,' Categoria ',`c`.`categoria`) AS `agrupo`,`j`.`numjugador` AS `numjugador`,`j`.`id` AS `jugid`,`j`.`nombre` AS `nombre`,`j`.`apellido` AS `apellido`,`j`.`torneoid` AS `torneoid`,`t`.`orden` AS `orden`,`g`.`nombre` AS `club`,`j`.`clubid` AS `clubid`,`t`.`SO` AS `so`,`b`.`categoriaid` AS `categoriaid`,`j`.`estatus` AS `juestatus`,if(`t`.`h1` > 0,1,0) + if(`t`.`h2` > 0,1,0) + if(`t`.`h3` > 0,1,0) + if(`t`.`h4` > 0,1,0) + if(`t`.`h5` > 0,1,0) + if(`t`.`h6` > 0,1,0) + if(`t`.`h7` > 0,1,0) + if(`t`.`h8` > 0,1,0) + if(`t`.`h9` > 0,1,0) + if(`t`.`h10` > 0,1,0) + if(`t`.`h11` > 0,1,0) + if(`t`.`h12` > 0,1,0) + if(`t`.`h13` > 0,1,0) + if(`t`.`h14` > 0,1,0) + if(`t`.`h15` > 0,1,0) + if(`t`.`h16` > 0,1,0) + if(`t`.`h17` > 0,1,0) + if(`t`.`h18` > 0,1,0) AS `avance` from ((((((`salidagrupo` `a` join `caljuego` `b` on(`a`.`caljuegoid` = `b`.`id` and `b`.`estatus` = 2 and `a`.`torneoid` = `b`.`torneoid`)) join `categorias` `c` on(`b`.`categoriaid` = `c`.`categoria_id`)) join `campos` `d` on(`b`.`campo` = `d`.`id`)) join `tarjetas` `t` on(`t`.`salidagrupoid` = `a`.`id`)) join `jugadores` `j` on(`j`.`id` = `t`.`jugadorid`)) left join `clubs` `g` on(`j`.`clubid` = `g`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_tl`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_tl`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_tl` AS select `b`.`torneoid` AS `torneoid`,`b`.`fecha` AS `fecha`,`b`.`categoriaid` AS `categoriaid`,`a`.`caljuegoid` AS `caljuegoid`,`c`.`categoria` AS `categoria` from ((`salidagrupo` `a` join `caljuego` `b` on(`a`.`caljuegoid` = `b`.`id` and `b`.`estatus` in (1,2))) join `categorias` `c` on(`c`.`categoria_id` = `b`.`categoriaid`)) group by `a`.`torneoid`,`b`.`fecha`,`b`.`categoriaid`,`a`.`caljuegoid`,`c`.`categoria` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_salidas_x`
--

/*!50001 DROP VIEW IF EXISTS `v_salidas_x`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_salidas_x` AS select distinct `a`.`id` AS `id`,`a`.`fecha` AS `fecha`,`b`.`categoria` AS `categoria`,`b`.`categoria_id` AS `categoriaid`,`a`.`torneoid` AS `torneoid`,`a`.`estatus` AS `estatus`,`x`.`pwd` AS `pwd` from ((`caljuego` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id` and `a`.`campo` <> 0)) join `v_caljgo_salgpo` `x` on(`x`.`caljuegoid` = `a`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sumsa_normal`
--

/*!50001 DROP VIEW IF EXISTS `v_sumsa_normal`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sumsa_normal` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`categoriaid` AS `categoriaid`,sum(`a`.`SA`) AS `sa` from (`tarjetas` `a` join `jugadores` `b` on(`a`.`jugadorid` = `b`.`id`)) where `b`.`estatus` = 'NORMAL' group by `a`.`jugadorid`,`a`.`categoriaid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sumtarjeta`
--

/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sumtarjeta` AS select `a`.`jugadorid` AS `jugadorid`,sum(`a`.`SO`) AS `so`,sum(`a`.`SA`) AS `sa`,sum(if(`a`.`SO` > 0,`a`.`SO` - if(`a`.`SA` > 0,`a`.`SA`,0),0)) AS `neto`,max(`b`.`horainicio1a`) AS `hinicio`,max(`b`.`id`) AS `salidaid` from (`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id`)) group by `a`.`jugadorid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sumtarjeta2`
--

/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sumtarjeta2` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`torneoid` AS `torneoid`,`a`.`categoriaid` AS `categoriaid`,sum(`a`.`SO`) AS `so`,sum(`a`.`SA`) AS `sa`,count(0) AS `numtar`,sum(if(`a`.`SO` > 0,`a`.`SA`,0)) AS `neto`,max(`b`.`horainicio1a`) AS `hinicio`,max(`b`.`id`) AS `salidaid` from (`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id` and `a`.`SO` > 0)) group by `a`.`jugadorid`,`a`.`categoriaid`,`a`.`torneoid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_sumtarjeta3`
--

/*!50001 DROP VIEW IF EXISTS `v_sumtarjeta3`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_sumtarjeta3` AS select `a`.`jugadorid` AS `jugadorid`,`a`.`torneoid` AS `torneoid`,`a`.`categoriaid` AS `categoriaid`,sum(`a`.`SO`) AS `so`,sum(`a`.`SA`) AS `sa`,count(0) AS `numtar` from (`tarjetas` `a` join `salidagrupo` `b` on(`a`.`salidagrupoid` = `b`.`id` and `a`.`SO` > 0)) group by `a`.`jugadorid`,`a`.`categoriaid`,`a`.`torneoid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_tarjetas_giraant`
--

/*!50001 DROP VIEW IF EXISTS `v_tarjetas_giraant`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_tarjetas_giraant` AS select `a`.`id` AS `id`,`a`.`id_campo` AS `id_campo`,`a`.`h1` AS `h1`,`a`.`h2` AS `h2`,`a`.`h3` AS `h3`,`a`.`h4` AS `h4`,`a`.`h5` AS `h5`,`a`.`h6` AS `h6`,`a`.`h7` AS `h7`,`a`.`h8` AS `h8`,`a`.`h9` AS `h9`,`a`.`h10` AS `h10`,`a`.`h11` AS `h11`,`a`.`h12` AS `h12`,`a`.`h13` AS `h13`,`a`.`h14` AS `h14`,`a`.`h15` AS `h15`,`a`.`h16` AS `h16`,`a`.`h17` AS `h17`,`a`.`h18` AS `h18`,`a`.`h1_a` AS `h1_a`,`a`.`h2_a` AS `h2_a`,`a`.`h3_a` AS `h3_a`,`a`.`h4_a` AS `h4_a`,`a`.`h5_a` AS `h5_a`,`a`.`h6_a` AS `h6_a`,`a`.`h7_a` AS `h7_a`,`a`.`h8_a` AS `h8_a`,`a`.`h9_a` AS `h9_a`,`a`.`h10_a` AS `h10_a`,`a`.`h11_a` AS `h11_a`,`a`.`h12_a` AS `h12_a`,`a`.`h13_a` AS `h13_a`,`a`.`h14_a` AS `h14_a`,`a`.`h15_a` AS `h15_a`,`a`.`h16_a` AS `h16_a`,`a`.`h17_a` AS `h17_a`,`a`.`h18_a` AS `h18_a`,`a`.`jugadorid` AS `jugadorid`,`a`.`fecha_cap` AS `fecha_cap`,`a`.`tee_salida` AS `tee_salida`,`a`.`color_tee` AS `color_tee`,`a`.`SO` AS `SO`,`a`.`SA` AS `SA`,`a`.`dif` AS `dif`,`a`.`estado` AS `estado`,`a`.`fecha_juego` AS `fecha_juego`,`a`.`tipo` AS `tipo`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`utiliza` AS `utiliza`,`a`.`slope` AS `slope`,`a`.`rating` AS `rating`,`a`.`torneoid` AS `torneoid`,`a`.`orden` AS `orden`,`a`.`estatus` AS `estatus`,`b`.`numjugador` AS `numjugador` from (`tarjetas` `a` join `jugadores` `b` on(`a`.`jugadorid` = `b`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_tarjetas_giraant2`
--

/*!50001 DROP VIEW IF EXISTS `v_tarjetas_giraant2`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_tarjetas_giraant2` AS select `a`.`id` AS `id`,`a`.`id_campo` AS `id_campo`,`a`.`h1` AS `h1`,`a`.`h2` AS `h2`,`a`.`h3` AS `h3`,`a`.`h4` AS `h4`,`a`.`h5` AS `h5`,`a`.`h6` AS `h6`,`a`.`h7` AS `h7`,`a`.`h8` AS `h8`,`a`.`h9` AS `h9`,`a`.`h10` AS `h10`,`a`.`h11` AS `h11`,`a`.`h12` AS `h12`,`a`.`h13` AS `h13`,`a`.`h14` AS `h14`,`a`.`h15` AS `h15`,`a`.`h16` AS `h16`,`a`.`h17` AS `h17`,`a`.`h18` AS `h18`,`a`.`h1_a` AS `h1_a`,`a`.`h2_a` AS `h2_a`,`a`.`h3_a` AS `h3_a`,`a`.`h4_a` AS `h4_a`,`a`.`h5_a` AS `h5_a`,`a`.`h6_a` AS `h6_a`,`a`.`h7_a` AS `h7_a`,`a`.`h8_a` AS `h8_a`,`a`.`h9_a` AS `h9_a`,`a`.`h10_a` AS `h10_a`,`a`.`h11_a` AS `h11_a`,`a`.`h12_a` AS `h12_a`,`a`.`h13_a` AS `h13_a`,`a`.`h14_a` AS `h14_a`,`a`.`h15_a` AS `h15_a`,`a`.`h16_a` AS `h16_a`,`a`.`h17_a` AS `h17_a`,`a`.`h18_a` AS `h18_a`,`a`.`jugadorid` AS `jugadorid`,`a`.`fecha_cap` AS `fecha_cap`,`a`.`tee_salida` AS `tee_salida`,`a`.`color_tee` AS `color_tee`,`a`.`SO` AS `SO`,`a`.`SA` AS `SA`,`a`.`dif` AS `dif`,`a`.`estado` AS `estado`,`a`.`fecha_juego` AS `fecha_juego`,`a`.`tipo` AS `tipo`,`a`.`salidagrupoid` AS `salidagrupoid`,`a`.`categoriaid` AS `categoriaid`,`a`.`utiliza` AS `utiliza`,`a`.`slope` AS `slope`,`a`.`rating` AS `rating`,`a`.`torneoid` AS `torneoid`,`a`.`orden` AS `orden`,`a`.`estatus` AS `estatus`,`a`.`numjugador` AS `numjugador`,`b`.`id` AS `jigidnvo` from (`v_tarjetas_giraant` `a` join `jugadores` `b` on(`a`.`numjugador` = `b`.`numjugador` and `b`.`torneoid` = 128)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_totpuntos_gira`
--

/*!50001 DROP VIEW IF EXISTS `v_totpuntos_gira`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_totpuntos_gira` AS select `a`.`numjugador` AS `numjugador`,`b`.`catidoriginal` AS `catidoriginal`,`c`.`giraid` AS `giraid`,sum(`a`.`puntos`) AS `puntos` from ((`jugadores` `a` join `categorias` `b` on(`a`.`categoriaid` = `b`.`categoria_id`)) join `categorias_tmp` `c` on(`b`.`catidoriginal` = `c`.`categoriasTmp_id`)) group by `a`.`numjugador`,`c`.`giraid`,`b`.`catidoriginal` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_ult_tarjeta`
--

/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ult_tarjeta` AS select `tarjetas`.`torneoid` AS `torneoid`,`tarjetas`.`jugadorid` AS `jugadorid`,max(`tarjetas`.`id`) AS `tarjetaid` from `tarjetas` where `tarjetas`.`estatus` <> 'X' and (`tarjetas`.`h1` > 0 or `tarjetas`.`h10` > 0) group by `tarjetas`.`torneoid`,`tarjetas`.`jugadorid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_ult_tarjeta0`
--

/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta0`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ult_tarjeta0` AS select `tarjetas`.`torneoid` AS `torneoid`,`tarjetas`.`jugadorid` AS `jugadorid`,max(`tarjetas`.`id`) AS `tarjetaid` from `tarjetas` group by `tarjetas`.`torneoid`,`tarjetas`.`jugadorid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_ult_tarjeta_ec`
--

/*!50001 DROP VIEW IF EXISTS `v_ult_tarjeta_ec`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`aliensystem`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_ult_tarjeta_ec` AS select `tarjetas`.`torneoid` AS `torneoid`,`tarjetas`.`jugadorid` AS `jugadorid`,max(`tarjetas`.`id`) AS `tarjetaid` from ((`tarjetas` join `salidagrupo` `s` on(`tarjetas`.`salidagrupoid` = `s`.`id`)) join `caljuego` `j` on(`s`.`caljuegoid` = `j`.`id` and `j`.`estatus` = 3)) where 1 group by `tarjetas`.`torneoid`,`tarjetas`.`jugadorid` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-21 12:12:04
