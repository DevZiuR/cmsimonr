-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: contraloria_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ejecucion_presupuestaria`
--

DROP TABLE IF EXISTS `ejecucion_presupuestaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ejecucion_presupuestaria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codificacion` varchar(50) NOT NULL,
  `denominacion` varchar(300) NOT NULL,
  `credito_aprobado` decimal(18,2) NOT NULL DEFAULT 0.00,
  `aumentos` decimal(18,2) NOT NULL DEFAULT 0.00,
  `disminuciones` decimal(18,2) NOT NULL DEFAULT 0.00,
  `mes` tinyint(2) NOT NULL,
  `anio` smallint(4) NOT NULL,
  `credito_actualizado_override` decimal(18,2) DEFAULT NULL,
  `compromiso_mensual_override` decimal(18,2) DEFAULT NULL,
  `compromiso_acumulado_override` decimal(18,2) DEFAULT NULL,
  `gastos_causados_override` decimal(18,2) DEFAULT NULL,
  `pago_acumulado_override` decimal(18,2) DEFAULT NULL,
  `compromisos_pagar_override` decimal(18,2) DEFAULT NULL,
  `disponibilidad_override` decimal(18,2) DEFAULT NULL,
  `credito_adicional_override` decimal(18,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codificacion_mes_anio` (`codificacion`,`mes`,`anio`)
) ENGINE=InnoDB AUTO_INCREMENT=505 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ejecucion_presupuestaria`
--

LOCK TABLES `ejecucion_presupuestaria` WRITE;
/*!40000 ALTER TABLE `ejecucion_presupuestaria` DISABLE KEYS */;
/*!40000 ALTER TABLE `ejecucion_presupuestaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oc_partidas`
--

DROP TABLE IF EXISTS `oc_partidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oc_partidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `oc_id` int(11) NOT NULL,
  `partida` varchar(50) DEFAULT NULL,
  `monto` decimal(18,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oc_id` (`oc_id`),
  CONSTRAINT `oc_partidas_ibfk_1` FOREIGN KEY (`oc_id`) REFERENCES `ordenes_compra` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_partidas`
--

LOCK TABLES `oc_partidas` WRITE;
/*!40000 ALTER TABLE `oc_partidas` DISABLE KEYS */;
INSERT INTO `oc_partidas` VALUES (3,5,'4.02.10.02.00',7.68),(34,17,'4.02.10.02.00',0.00),(35,17,'4.03.18.01.00',0.00),(36,17,'4.03.18.99.00',0.00),(37,18,'4.02.10.02.00',3.60),(38,18,'4.03.18.01.00',576.00),(39,18,'4.03.18.99.00',3.00);
/*!40000 ALTER TABLE `oc_partidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `oc_renglones`
--

DROP TABLE IF EXISTS `oc_renglones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `oc_renglones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `oc_id` int(11) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `imput_presupuestaria` varchar(50) DEFAULT NULL,
  `unidad` varchar(30) DEFAULT NULL,
  `cantidad` decimal(10,2) DEFAULT NULL,
  `precio_unitario` decimal(18,2) DEFAULT NULL,
  `total` decimal(18,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oc_id` (`oc_id`),
  CONSTRAINT `oc_renglones_ibfk_1` FOREIGN KEY (`oc_id`) REFERENCES `ordenes_compra` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_renglones`
--

LOCK TABLES `oc_renglones` WRITE;
/*!40000 ALTER TABLE `oc_renglones` DISABLE KEYS */;
INSERT INTO `oc_renglones` VALUES (7,5,'JABON FRESH','4.02.10.02.00','1',1.00,7675.00,7675.00),(8,5,'','','1',1.00,0.00,0.00),(9,5,'','','1',1.00,0.00,0.00),(73,17,'JABON LIQUIDO MULTIUSO','4.02.10.02.00','1',1.00,0.00,0.00),(74,17,'','','1',1.00,0.00,0.00),(75,17,'','','1',1.00,0.00,0.00),(76,18,'PRIDE NARANJA','4.02.10.02.00','1',1.00,3600.00,3600.00),(77,18,'','','1',1.00,0.00,0.00),(78,18,'','','1',1.00,0.00,0.00);
/*!40000 ALTER TABLE `oc_renglones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `op_retenciones`
--

DROP TABLE IF EXISTS `op_retenciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `op_retenciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `op_id` int(11) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `codigo_presupuestario` varchar(100) DEFAULT NULL,
  `tasa` decimal(5,2) DEFAULT 0.00,
  `monto_comision` decimal(18,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `op_id` (`op_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `op_retenciones`
--

LOCK TABLES `op_retenciones` WRITE;
/*!40000 ALTER TABLE `op_retenciones` DISABLE KEYS */;
INSERT INTO `op_retenciones` VALUES (1,1,'Retencion IVA 75%','01-02-03',75.00,100.00),(2,2,'RETENCION IVA 75%','01-08-00-00-51-403-18-01-00',75.00,4219.80),(24,24,'RETENCION IVA 75%','01-08-00-00-51-403-18-01-00',75.00,9.09),(25,24,'RETENCION SAT 0,1%','01-08-00-00-51-403-18-99-00',0.10,0.15),(26,25,'RETENCION IVA 75%','01-08-00-00-51-403-18-01-00',75.00,75623.76),(27,25,'RETENCION SAT 0,1%','01-08-00-00-51-403-18-99-00',0.10,630.20);
/*!40000 ALTER TABLE `op_retenciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_compra`
--

DROP TABLE IF EXISTS `ordenes_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ordenes_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_orden` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `base_imponible` decimal(18,2) DEFAULT 0.00,
  `sat_porcentaje` decimal(5,2) DEFAULT 0.10,
  `sat_monto` decimal(18,2) DEFAULT 0.00,
  `iva_porcentaje` decimal(5,2) DEFAULT 16.00,
  `iva_monto` decimal(18,2) DEFAULT 0.00,
  `total_general` decimal(18,2) DEFAULT 0.00,
  `monto_letras` text DEFAULT NULL,
  `status` enum('pendiente','aprobada','anulada','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
  `tipo` enum('normal','farmacia') NOT NULL DEFAULT 'normal',
  `deleted_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_orden` (`numero_orden`),
  KEY `proveedor_id` (`proveedor_id`),
  CONSTRAINT `ordenes_compra_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_compra`
--

LOCK TABLES `ordenes_compra` WRITE;
/*!40000 ALTER TABLE `ordenes_compra` DISABLE KEYS */;
INSERT INTO `ordenes_compra` VALUES (5,'07','2026-07-29',1,7675.00,0.10,7.67,16.00,1228.00,8910.67,'OCHO MIL NOVECIENTOS DIEZ BOLIVARES CON 67/100','pendiente','normal','2026-08-14 08:50:41',NULL,'2026-08-09 23:36:31','GLIBER','2026-08-14 08:50:41'),(8,'011','2026-07-31',1,35165.00,0.10,35.16,16.00,5626.40,40826.57,'CUARENTA MIL OCHOCIENTOS VEINTISEIS BOLIVARES CON 57/100','','normal','2026-08-02 23:24:22',NULL,'2026-08-09 23:36:31',NULL,NULL),(12,'087','2026-08-10',7,75768.77,0.10,75.77,16.00,12123.00,87891.77,'OCHENTA Y SIETE MIL OCHOCIENTOS NOVENTA Y UNO BOLIVARES CON 77/100','','normal','2026-08-10 07:00:06',NULL,'2026-08-10 06:41:34',NULL,'2026-08-10 07:00:06'),(17,'012','2026-08-12',7,0.00,0.10,0.00,16.00,0.00,0.00,'—','pendiente','normal',NULL,'Gliber Pérez','2026-08-12 06:32:19','GLIBER','2026-08-14 14:48:22'),(18,'027','2026-08-14',7,3600.00,0.10,3.60,16.00,576.00,4176.00,'CUATRO MIL CIEN SETENTA Y SEIS BOLIVARES CON 00/100','pendiente','normal',NULL,'GliBER Pérez','2026-08-14 14:49:48','GliBER Pérez','2026-08-14 14:49:48');
/*!40000 ALTER TABLE `ordenes_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_pago`
--

DROP TABLE IF EXISTS `ordenes_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ordenes_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `oc_id` int(11) DEFAULT NULL,
  `doc_tipo` varchar(10) DEFAULT 'OC',
  `os_id` int(11) DEFAULT NULL,
  `concepto` text DEFAULT NULL,
  `numero_cofer` varchar(50) DEFAULT NULL,
  `monto_bruto` decimal(18,2) DEFAULT NULL,
  `monto_letras` text DEFAULT NULL,
  `tasa_retencion` decimal(5,2) DEFAULT NULL,
  `monto_retencion` decimal(18,2) DEFAULT NULL,
  `monto_neto_pagar` decimal(18,2) DEFAULT NULL,
  `status` enum('pendiente','pagada','anulada','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
  `rif_beneficiario` varchar(50) DEFAULT NULL,
  `banco` varchar(100) DEFAULT NULL,
  `numero_cuenta` varchar(100) DEFAULT NULL,
  `recibe_firma` varchar(200) DEFAULT NULL,
  `recibe_cedula` varchar(50) DEFAULT NULL,
  `recibe_fecha` date DEFAULT NULL,
  `cont_json` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `oc_id` (`oc_id`),
  CONSTRAINT `ordenes_pago_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`),
  CONSTRAINT `ordenes_pago_ibfk_2` FOREIGN KEY (`oc_id`) REFERENCES `ordenes_compra` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_pago`
--

LOCK TABLES `ordenes_pago` WRITE;
/*!40000 ALTER TABLE `ordenes_pago` DISABLE KEYS */;
INSERT INTO `ordenes_pago` VALUES (2,'192','2026-07-31',1,8,'OC',NULL,'',NULL,40826.57,'TREINTA Y SEIS MIL SEISCIENTOS SEIS BOLIVARES CON 77/100',NULL,4219.80,36606.77,'pendiente','J-40184563-0','VENEZUELA','','','',NULL,'[{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"402\\\",\\\"partid\\\":\\\"10\\\",\\\"gen\\\":\\\"02\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"}]',NULL,NULL,'2026-08-09 23:36:31','gliber','2026-08-17 15:36:26'),(24,'364','2026-08-10',7,12,'OC',NULL,'0',NULL,16289.00,'0',NULL,9.24,153.65,'','J-29512429-5','BANCO DE VENEZUELA','0100-5555-55-5555555550','','',NULL,'[{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"402\\\",\\\"partid\\\":\\\"10\\\",\\\"gen\\\":\\\"02\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"},{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"403\\\",\\\"partid\\\":\\\"18\\\",\\\"gen\\\":\\\"01\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"},{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"403\\\",\\\"partid\\\":\\\"18\\\",\\\"gen\\\":\\\"99\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"}]',NULL,NULL,'2026-08-10 06:42:23',NULL,NULL),(25,'102','2026-08-10',7,NULL,'OS',2,'0',NULL,73102968.00,'0',NULL,76253.96,654775.72,'pendiente','J-29512429-5','BANCO DE VENEZUELA','','','',NULL,'[{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"403\\\",\\\"partid\\\":\\\"01\\\",\\\"gen\\\":\\\"01\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"},{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"403\\\",\\\"partid\\\":\\\"18\\\",\\\"gen\\\":\\\"01\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"},{\\\"anio\\\":\\\"2026\\\",\\\"sector\\\":\\\"01\\\",\\\"prog\\\":\\\"08\\\",\\\"subprog\\\":\\\"00\\\",\\\"proy\\\":\\\"00\\\",\\\"acti\\\":\\\"51\\\",\\\"obra\\\":\\\"403\\\",\\\"partid\\\":\\\"18\\\",\\\"gen\\\":\\\"99\\\",\\\"espec\\\":\\\"00\\\",\\\"sub\\\":\\\"\\\"}]',NULL,NULL,'2026-08-10 07:06:32','gliber','2026-08-17 15:36:22');
/*!40000 ALTER TABLE `ordenes_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_servicio`
--

DROP TABLE IF EXISTS `ordenes_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ordenes_servicio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_os` varchar(20) NOT NULL,
  `fecha` date NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `lugar_entrega` varchar(255) DEFAULT NULL,
  `base_imponible` decimal(18,2) DEFAULT 0.00,
  `sat_porcentaje` decimal(5,2) DEFAULT 0.10,
  `sat_monto` decimal(18,2) DEFAULT 0.00,
  `iva_porcentaje` decimal(5,2) DEFAULT 16.00,
  `iva_monto` decimal(18,2) DEFAULT 0.00,
  `descripcion_servicio` text DEFAULT NULL,
  `partida` varchar(50) DEFAULT NULL,
  `monto_total` decimal(18,2) DEFAULT 0.00,
  `monto_letras` text DEFAULT NULL,
  `status` enum('pendiente','aprobada','anulada','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
  `tipo` varchar(20) DEFAULT 'normal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_os` (`numero_os`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_servicio`
--

LOCK TABLES `ordenes_servicio` WRITE;
/*!40000 ALTER TABLE `ordenes_servicio` DISABLE KEYS */;
INSERT INTO `ordenes_servicio` VALUES (2,'025','2026-08-07',7,'AV. FRANCISCO DE MIRANDA CON CALLE 14 SUR - SECTOR PUEBLO NUEVO SUR,  EL TIGRE ESTADO ANZOÁTEGUI.',629567.80,0.10,629.57,16.00,100730.85,NULL,NULL,730298.65,'SETECIENTOS TREINTA MIL DOSCIENTOS NOVENTA Y OCHO BOLIVARES CON 65/100','pendiente','normal','2026-08-07 02:25:29',NULL,NULL,'gliber','2026-08-14 17:30:23'),(4,'033','2026-08-20',1,'3era carrera sur',445555.00,0.10,445.56,16.00,71288.80,NULL,NULL,516843.80,'QUINIENTOS DIECISIETE MIL OCHOCIENTOS CUARENTA Y TRES BOLIVARES CON 80/100','pendiente','normal','2026-08-19 05:24:32',NULL,'Gliber Pérez','Gliber Pérez','2026-08-19 01:24:32');
/*!40000 ALTER TABLE `ordenes_servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_partidas`
--

DROP TABLE IF EXISTS `os_partidas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_partidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `os_id` int(11) NOT NULL,
  `partida` varchar(100) DEFAULT NULL,
  `monto` decimal(18,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `os_id` (`os_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_partidas`
--

LOCK TABLES `os_partidas` WRITE;
/*!40000 ALTER TABLE `os_partidas` DISABLE KEYS */;
INSERT INTO `os_partidas` VALUES (7,2,'4.03.01.01.00',629567.80),(8,2,'4.03.18.01.00',100730.85),(9,2,'4.03.18.99.00',629.57),(10,4,'4.02.10.02.00',445555.00),(11,4,'4.03.18.01.00',71288.80),(12,4,'4.03.18.99.00',445.56);
/*!40000 ALTER TABLE `os_partidas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `os_renglones`
--

DROP TABLE IF EXISTS `os_renglones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `os_renglones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `os_id` int(11) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imput_presupuestaria` varchar(100) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT '1',
  `cantidad` decimal(18,2) DEFAULT 1.00,
  `precio_unitario` decimal(18,2) DEFAULT 0.00,
  `total` decimal(18,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `os_id` (`os_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `os_renglones`
--

LOCK TABLES `os_renglones` WRITE;
/*!40000 ALTER TABLE `os_renglones` DISABLE KEYS */;
INSERT INTO `os_renglones` VALUES (7,2,'CANCELACIÓN  DE ARRENDAMIENTO CORRESPONDIENTE AL MES DE JUNIO','4.03.01.01.00','1',1.00,629567.80,629567.80),(8,2,'','','1',1.00,0.00,0.00),(9,2,'','','1',1.00,0.00,0.00),(10,4,'JABON FRESH','4.02.10.02.00','1',1.00,445555.00,445555.00),(11,4,'','','1',1.00,0.00,0.00),(12,4,'','','1',1.00,0.00,0.00);
/*!40000 ALTER TABLE `os_renglones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(300) NOT NULL,
  `imput_presupuestaria` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'PAPEL INDUSTRIAL','4.02.05.01.00'),(2,'TOALLIN','4.02.05.01.00'),(3,'PACK BOLSAS 15 LTS 10UND','4.02.06.08.00'),(4,'PACK BOLSAS 30 LTS 10UND','4.02.06.08.00'),(5,'JABON AVENA','4.02.10.02.00'),(6,'JABON FRESH','4.02.10.02.00'),(7,'JABON LIQUIDO MULTIUSO','4.02.10.02.00'),(8,'DESENGRASANTE MULTIUSO','4.02.10.02.00'),(9,'BLANQUEADOR ACTIVO','4.02.10.02.00'),(10,'BACTERICIDAJ','4.02.10.02.00'),(11,'LAMPAZO PEQUEÑO','4.02.10.02.00'),(12,'PRIDE NARANJA','4.02.10.02.00'),(13,'GLADE MANZANA Y CANELA','4.02.10.02.00'),(14,'GLADE PARAISO AZUL','4.02.10.02.00'),(15,'VASOS V37','4.02.06.08.00'),(16,'CANCELAMIENTO ARRIENDO','4.03.01.01.00'),(17,'CANCELACIÓN  DE ARRENDAMIENTO CORRESPONDIENTE AL MES DE JUNIO','4.03.01.01.00'),(18,'JABON FRESHV','4.02.10.02.00'),(19,'SHAMPOO','4.01.02.03');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rif` varchar(20) NOT NULL,
  `razon_social` varchar(200) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `rif` (`rif`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'J-40184563-0','INVERSIONES HOGARCLEAN, C.A','3era carrera sur','04121038084',NULL,'2026-06-15 15:44:35'),(7,'J-29512429-5','CENTRO COMERCIAL SILVANA , C.A','AV. FRANCISCO DE MIRANDA CON CALLE 14 SUR - SECTOR PUEBLO NUEVO SUR,  EL TIGRE ESTADO ANZOÁTEGUI.','','','2026-08-07 02:21:45');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `rol` enum('admin','visualizador') NOT NULL DEFAULT 'visualizador',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'gliber','0192023a7bbd73250516f069df18b500','Gliber Pérez','admin'),(2,'daniela','0192023a7bbd73250516f069df18b500','Daniela Marín','admin'),(3,'silvia','0192023a7bbd73250516f069df18b500','Silvia Muñoz','visualizador'),(4,'luisana','a1e47c8b40c15dacfc65b894498b4e9e','Luisana','visualizador');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'contraloria_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-18 23:27:36
