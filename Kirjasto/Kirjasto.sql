-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: Kirjasto
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `Kirjakopiot`
--

DROP TABLE IF EXISTS `Kirjakopiot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Kirjakopiot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kirja_id` int NOT NULL,
  `kopion_numero` varchar(50) DEFAULT NULL,
  `kunto` enum('erinomainen','hyvä','tyydyttävä','huono','korjattava') DEFAULT 'hyvä',
  `tila` enum('saatavilla','lainassa','varattu','hävinnyt','korjauksessa') DEFAULT 'saatavilla',
  `sijainti` varchar(100) DEFAULT NULL,
  `hankintapaiva` date DEFAULT NULL,
  `huomiot` text,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paivitetty` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kirja_id` (`kirja_id`),
  CONSTRAINT `Kirjakopiot_ibfk_1` FOREIGN KEY (`kirja_id`) REFERENCES `kirjat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Kirjakopiot`
--

LOCK TABLES `Kirjakopiot` WRITE;
/*!40000 ALTER TABLE `Kirjakopiot` DISABLE KEYS */;
INSERT INTO `Kirjakopiot` VALUES (4,2,'KOPIO-001','hyvä','saatavilla',NULL,NULL,NULL,'2026-04-09 13:22:23','2026-04-09 13:22:23'),(5,3,'KOPIO-001','hyvä','saatavilla',NULL,NULL,NULL,'2026-04-09 13:22:36','2026-04-09 13:22:36');
/*!40000 ALTER TABLE `Kirjakopiot` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Laitehuoltoloki`
--

DROP TABLE IF EXISTS `Laitehuoltoloki`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Laitehuoltoloki` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laite_id` int NOT NULL,
  `huolto_tyyppi` varchar(100) DEFAULT NULL,
  `kuvaus` text,
  `suorittaja` varchar(100) DEFAULT NULL,
  `kustannus` decimal(10,2) DEFAULT NULL,
  `huolto_paiva` date NOT NULL,
  `seuraava_huolto` date DEFAULT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laite_id` (`laite_id`),
  CONSTRAINT `Laitehuoltoloki_ibfk_1` FOREIGN KEY (`laite_id`) REFERENCES `Laitteet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Laitehuoltoloki`
--

LOCK TABLES `Laitehuoltoloki` WRITE;
/*!40000 ALTER TABLE `Laitehuoltoloki` DISABLE KEYS */;
/*!40000 ALTER TABLE `Laitehuoltoloki` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Laitelainat`
--

DROP TABLE IF EXISTS `Laitelainat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Laitelainat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laite_id` int NOT NULL,
  `jasen_id` int NOT NULL,
  `varaus_id` int DEFAULT NULL,
  `lainaus_pvm` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `erapaiva` datetime NOT NULL,
  `palautus_pvm` datetime DEFAULT NULL,
  `lainaus_kunto` enum('erinomainen','hyvä','tyydyttävä','huono') DEFAULT NULL,
  `palautus_kunto` enum('erinomainen','hyvä','tyydyttävä','huono') DEFAULT NULL,
  `myohastyymismaksu` decimal(10,2) DEFAULT '0.00',
  `huomiot` text,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `jatkettu` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `laite_id` (`laite_id`),
  KEY `jasen_id` (`jasen_id`),
  KEY `varaus_id` (`varaus_id`),
  CONSTRAINT `Laitelainat_ibfk_1` FOREIGN KEY (`laite_id`) REFERENCES `Laitteet` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `Laitelainat_ibfk_2` FOREIGN KEY (`jasen_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Laitelainat_ibfk_3` FOREIGN KEY (`varaus_id`) REFERENCES `Laitevaraukset` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Laitelainat`
--

LOCK TABLES `Laitelainat` WRITE;
/*!40000 ALTER TABLE `Laitelainat` DISABLE KEYS */;
INSERT INTO `Laitelainat` VALUES (8,29,1,NULL,'2026-03-09 00:00:00','2026-03-24 00:00:00','2026-03-17 13:34:28','hyvä',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(9,30,1,NULL,'2026-03-11 00:00:00','2026-03-28 00:00:00','2026-03-27 10:06:04','erinomainen',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(10,31,1,NULL,'2026-03-04 00:00:00','2026-03-19 00:00:00','2026-03-14 13:36:49','hyvä',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(11,32,1,NULL,'2026-02-22 00:00:00','2026-03-23 00:00:00','2026-03-17 13:34:18','tyydyttävä',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(12,33,1,NULL,'2026-02-27 00:00:00','2026-03-26 00:00:00','2026-03-27 10:05:48','hyvä',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(13,34,1,NULL,'2026-01-13 00:00:00','2026-01-18 00:00:00','2026-01-23 00:00:00','erinomainen',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(14,35,1,NULL,'2026-01-28 00:00:00','2026-02-02 00:00:00','2026-02-07 00:00:00','hyvä',NULL,0.00,NULL,'2026-03-14 13:03:04',0),(15,34,1,NULL,'2026-03-27 10:48:57','2026-04-10 10:48:57','2026-03-27 10:50:27','hyvä',NULL,0.00,'','2026-03-27 10:48:57',0),(16,32,1,NULL,'2026-04-04 10:51:00','2026-04-18 10:51:00','2026-04-07 11:27:44','hyvä',NULL,0.00,'','2026-04-04 10:51:00',0),(17,29,1,NULL,'2026-04-04 13:55:43','2026-04-11 13:55:43','2026-04-04 11:48:14',NULL,NULL,0.00,NULL,'2026-04-04 10:55:43',0),(18,29,1,NULL,'2026-04-15 15:16:39','2026-04-29 15:16:39','2026-04-15 15:20:09','hyvä',NULL,0.00,NULL,'2026-04-15 12:16:39',0),(19,29,1,NULL,'2026-04-15 15:19:19','2026-04-29 15:19:19',NULL,'hyvä',NULL,0.00,NULL,'2026-04-15 12:19:19',0),(20,30,1,NULL,'2026-04-15 15:26:54','2026-04-29 15:26:54',NULL,'hyvä',NULL,0.00,NULL,'2026-04-15 12:26:54',0);
/*!40000 ALTER TABLE `Laitelainat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Laitetyypit`
--

DROP TABLE IF EXISTS `Laitetyypit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Laitetyypit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nimi` varchar(50) NOT NULL,
  `kuvaus` text,
  `laina_aika` int DEFAULT '30',
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paivitetty` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Laitetyypit`
--

LOCK TABLES `Laitetyypit` WRITE;
/*!40000 ALTER TABLE `Laitetyypit` DISABLE KEYS */;
INSERT INTO `Laitetyypit` VALUES (1,'kannettava','Kannettava tietokone',30,'2026-03-14 11:50:55','2026-03-14 11:50:55'),(2,'tabletti','Tablettitietokone',30,'2026-03-14 11:50:55','2026-03-14 11:50:55'),(3,'puhelin','Älypuhelin',30,'2026-03-14 11:50:55','2026-03-14 11:50:55'),(4,'kamera','Digitaalikamera',30,'2026-03-14 11:50:55','2026-03-14 11:50:55'),(5,'projektori','Videoprojektori',30,'2026-03-14 11:50:55','2026-03-14 11:50:55');
/*!40000 ALTER TABLE `Laitetyypit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Laitevaraukset`
--

DROP TABLE IF EXISTS `Laitevaraukset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Laitevaraukset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laite_id` int NOT NULL,
  `jasen_id` int NOT NULL,
  `tila` enum('odottaa','vahvistettu','peruttu','täytetty') DEFAULT 'odottaa',
  `varaus_paiva` date NOT NULL,
  `noutoaika` time DEFAULT NULL,
  `noutopaiva` date DEFAULT NULL,
  `vanhenee` datetime DEFAULT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laite_id` (`laite_id`),
  KEY `jasen_id` (`jasen_id`),
  CONSTRAINT `Laitevaraukset_ibfk_1` FOREIGN KEY (`laite_id`) REFERENCES `Laitteet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `Laitevaraukset_ibfk_2` FOREIGN KEY (`jasen_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Laitevaraukset`
--

LOCK TABLES `Laitevaraukset` WRITE;
/*!40000 ALTER TABLE `Laitevaraukset` DISABLE KEYS */;
/*!40000 ALTER TABLE `Laitevaraukset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `Laitteet`
--

DROP TABLE IF EXISTS `Laitteet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `Laitteet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laite_tyyppi_id` int NOT NULL,
  `sarjanumero` varchar(100) NOT NULL,
  `merkki` varchar(100) DEFAULT NULL,
  `malli` varchar(100) DEFAULT NULL,
  `ominaisuudet` json DEFAULT NULL,
  `kunto` enum('erinomainen','hyvä','tyydyttävä','huono','huoltotila') DEFAULT 'hyvä',
  `tila` enum('saatavilla','lainassa','varattu','huoltotila','kadonnut') DEFAULT 'saatavilla',
  `sijainti` varchar(100) DEFAULT NULL,
  `huomiot` text,
  `hankintapaiva` date DEFAULT NULL,
  `viime_huolto` date DEFAULT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paivitetty` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sarjanumero` (`sarjanumero`),
  KEY `laite_tyyppi_id` (`laite_tyyppi_id`),
  CONSTRAINT `Laitteet_ibfk_1` FOREIGN KEY (`laite_tyyppi_id`) REFERENCES `Laitetyypit` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `Laitteet`
--

LOCK TABLES `Laitteet` WRITE;
/*!40000 ALTER TABLE `Laitteet` DISABLE KEYS */;
INSERT INTO `Laitteet` VALUES (29,1,'LAP001','Lenovo','ThinkPad X1','{\"ram\": \"16GB\", \"storage\": \"512GB SSD\", \"prosessori\": \"Intel i7\"}','erinomainen','saatavilla','Varasto 1',NULL,'2025-01-15',NULL,'2026-03-14 11:53:46','2026-04-15 12:20:34'),(30,1,'LAP002','Dell','XPS 15','{\"ram\": \"32GB\", \"storage\": \"1TB SSD\", \"prosessori\": \"Intel i9\"}','hyvä','saatavilla','Varasto 1',NULL,'2025-02-20',NULL,'2026-03-14 11:53:46','2026-04-15 12:31:27'),(31,1,'LAP003','Apple','MacBook Pro','{\"ram\": \"18GB\", \"storage\": \"512GB SSD\", \"prosessori\": \"M3 Pro\"}','erinomainen','saatavilla','Asiakkaalla',NULL,'2025-01-10',NULL,'2026-03-14 11:53:46','2026-03-14 13:36:49'),(32,1,'LAP004','HP','EliteBook','{\"ram\": \"8GB\", \"storage\": \"256GB SSD\", \"prosessori\": \"Intel i5\"}','tyydyttävä','huoltotila','Varasto 1',NULL,'2024-11-05',NULL,'2026-03-14 11:53:46','2026-04-15 11:57:13'),(33,2,'TAB001','Apple','iPad Pro','{\"naytto\": \"12.9\\\"\", \"storage\": \"256GB\"}','erinomainen','saatavilla','Varasto 2',NULL,'2025-03-01',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(34,2,'TAB002','Samsung','Galaxy Tab S9','{\"naytto\": \"11\\\"\", \"storage\": \"128GB\"}','hyvä','saatavilla','Varasto 2',NULL,'2025-02-15',NULL,'2026-03-14 11:53:46','2026-03-27 10:50:27'),(35,2,'TAB003','Microsoft','Surface Pro','{\"ram\": \"8GB\", \"storage\": \"256GB\", \"prosessori\": \"Intel i5\"}','hyvä','saatavilla','Asiakkaalla',NULL,'2025-01-20',NULL,'2026-03-14 11:53:46','2026-03-27 10:47:39'),(36,3,'PHN001','Apple','iPhone 15','{\"color\": \"black\", \"storage\": \"128GB\"}','erinomainen','saatavilla','Varasto 3',NULL,'2025-02-10',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(37,3,'PHN002','Samsung','Galaxy S24','{\"color\": \"silver\", \"storage\": \"256GB\"}','erinomainen','saatavilla','Varasto 3',NULL,'2025-03-05',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(38,3,'PHN003','Google','Pixel 8','{\"color\": \"white\", \"storage\": \"128GB\"}','hyvä','saatavilla','Asiakkaalla',NULL,'2025-01-25',NULL,'2026-03-14 11:53:46','2026-03-27 10:47:39'),(39,4,'CAM001','Canon','EOS R6','{\"sensor\": \"Full frame\", \"megapixels\": \"20MP\"}','erinomainen','saatavilla','Varasto 4',NULL,'2024-12-10',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(40,4,'CAM002','Sony','A7 III','{\"sensor\": \"Full frame\", \"megapixels\": \"24MP\"}','hyvä','saatavilla','Varasto 4',NULL,'2024-11-15',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(41,5,'PRJ001','Epson','EB-695Wi','{\"brightness\": \"3500 lumens\", \"resolution\": \"WXGA\"}','hyvä','saatavilla','Varasto 5',NULL,'2024-10-20',NULL,'2026-03-14 11:53:46','2026-03-14 11:53:46'),(42,5,'PRJ002','BenQ','MH550','{\"brightness\": \"3400 lumens\", \"resolution\": \"1080p\"}','erinomainen','saatavilla','Kokoushuone 1',NULL,'2025-01-30',NULL,'2026-03-14 11:53:46','2026-03-27 10:47:39');
/*!40000 ALTER TABLE `Laitteet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jasenet`
--

DROP TABLE IF EXISTS `jasenet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jasenet` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etunimi` varchar(100) NOT NULL,
  `sukunimi` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `puhelin` varchar(20) DEFAULT NULL,
  `osoite` text,
  `jasentyyppi` enum('perus','premium','opiskelija','seniori') DEFAULT 'perus',
  `jasennumero` varchar(50) DEFAULT NULL,
  `liittymispaiva` date DEFAULT NULL,
  `tila` enum('aktiivinen','passiivinen','keskeytetty') DEFAULT 'aktiivinen',
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rooli` enum('user','manager','admin') DEFAULT 'user',
  `profile_image` varchar(255) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `jasennumero` (`jasennumero`),
  KEY `idx_reset_token` (`reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jasenet`
--

LOCK TABLES `jasenet` WRITE;
/*!40000 ALTER TABLE `jasenet` DISABLE KEYS */;
INSERT INTO `jasenet` VALUES (1,'Aziz Rahman','Noyan','matiasmasih@gmail.com','$2y$10$PSx306gYWSFLYL/Vv16vHudx4ja0qemI5Yn0VaOCy40kcxevIf8um','+358413114312','Piikkikuja 3,','perus','KIR202511279932','2025-11-27','aktiivinen','2025-11-27 12:55:26','user','1_1767442463_4e526cc2.jpg','a06c4a569828c841d3301e22d7a3539054a3e058fd48a1aca5021806f3304bbd','2026-03-03 14:35:31'),(5,'Matias','Masih','aziznoyan50@gmail.com','$2y$10$ZhIuBFAFQaaU/9FgC9phoOZ6b3XhijoqcMJHBmQKXHWc3IkMsNpj.','+358413114312','Vaahtokuja 5 E50','perus','KIR202603039006','2026-03-03','aktiivinen','2026-03-03 13:37:20','manager','manager_5_1773330690.jpg',NULL,NULL),(6,'Ali','Ahmad','aziz.noyan@gmail.com','$2y$10$asUQ2h280eroSIjpJDiRiOgxF9ZT3oesbNP6mw4ar23Mi.WaCq43e','+358413114312','Vaahtokuja 5 E50','perus','KIR202603072054','2026-03-07','aktiivinen','2026-03-07 12:43:05','admin','profile_6_1773065132.jpg',NULL,NULL);
/*!40000 ALTER TABLE `jasenet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kayttajat`
--

DROP TABLE IF EXISTS `kayttajat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kayttajat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kayttajanimi` varchar(100) NOT NULL,
  `etunimi` varchar(100) DEFAULT NULL,
  `sukunimi` varchar(100) DEFAULT NULL,
  `salasana` varchar(255) NOT NULL,
  `rooli` enum('admin','manager','user') DEFAULT 'user',
  `email` varchar(255) NOT NULL,
  `tila` enum('aktiivinen','passiivinen') DEFAULT 'aktiivinen',
  `profile_image` varchar(255) DEFAULT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kayttajanimi` (`kayttajanimi`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kayttajat`
--

LOCK TABLES `kayttajat` WRITE;
/*!40000 ALTER TABLE `kayttajat` DISABLE KEYS */;
INSERT INTO `kayttajat` VALUES (1,'Aziz Rahman Noyan','Aziz Rahman','Noyan','$2y$10$ajepr1BClCZLf3LOT77DseeWKb95LFiAj/hMrKviCcgnC4mHqZaEa','admin','matiasmasih@gmail.com','aktiivinen',NULL,'2026-02-21 13:37:03');
/*!40000 ALTER TABLE `kayttajat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `keskustelu_viestit`
--

DROP TABLE IF EXISTS `keskustelu_viestit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keskustelu_viestit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `keskustelu_id` int NOT NULL,
  `lahettaja_id` int NOT NULL,
  `viesti` text NOT NULL,
  `luettelo` text,
  `luontiaika` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `keskustelu_id` (`keskustelu_id`),
  KEY `lahettaja_id` (`lahettaja_id`),
  CONSTRAINT `keskustelu_viestit_ibfk_1` FOREIGN KEY (`keskustelu_id`) REFERENCES `keskustelut` (`id`),
  CONSTRAINT `keskustelu_viestit_ibfk_2` FOREIGN KEY (`lahettaja_id`) REFERENCES `jasenet` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `keskustelu_viestit`
--

LOCK TABLES `keskustelu_viestit` WRITE;
/*!40000 ALTER TABLE `keskustelu_viestit` DISABLE KEYS */;
/*!40000 ALTER TABLE `keskustelu_viestit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `keskustelut`
--

DROP TABLE IF EXISTS `keskustelut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keskustelut` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kayttaja_id` int NOT NULL,
  `otsikko` varchar(255) DEFAULT NULL,
  `viimeisin_viesti` text,
  `viimeisin_aika` timestamp NULL DEFAULT NULL,
  `tila` enum('avoin','suljettu') DEFAULT 'avoin',
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kayttaja_id` (`kayttaja_id`),
  CONSTRAINT `keskustelut_ibfk_1` FOREIGN KEY (`kayttaja_id`) REFERENCES `jasenet` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `keskustelut`
--

LOCK TABLES `keskustelut` WRITE;
/*!40000 ALTER TABLE `keskustelut` DISABLE KEYS */;
/*!40000 ALTER TABLE `keskustelut` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kirjat`
--

DROP TABLE IF EXISTS `kirjat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kirjat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nimi` varchar(255) NOT NULL,
  `tekija` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `kategoria` varchar(100) DEFAULT NULL,
  `julkaisuvuosi` int DEFAULT NULL,
  `kustantaja` varchar(255) DEFAULT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kirjat`
--

LOCK TABLES `kirjat` WRITE;
/*!40000 ALTER TABLE `kirjat` DISABLE KEYS */;
INSERT INTO `kirjat` VALUES (2,'To Kill a Mockingbird','Harper Lee','9780061120084','Fiction',1960,'HarperCollins','2026-04-09 13:21:43'),(3,'1984','George Orwell','9780451524935','Dystopian',1949,'Signet Classics','2026-04-09 13:21:56');
/*!40000 ALTER TABLE `kirjat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kuitit`
--

DROP TABLE IF EXISTS `kuitit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kuitit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jasen_id` int NOT NULL,
  `laina_id` int DEFAULT NULL,
  `laitelaina_id` int DEFAULT NULL,
  `sakko_id` int DEFAULT NULL,
  `summa` decimal(10,2) NOT NULL,
  `kuvaus` text,
  `tila` enum('maksettu','hyvitetty','peruttu') DEFAULT 'maksettu',
  `maksupaiva` datetime DEFAULT CURRENT_TIMESTAMP,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jasen_id` (`jasen_id`),
  KEY `sakko_id` (`sakko_id`),
  CONSTRAINT `kuitit_ibfk_1` FOREIGN KEY (`jasen_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kuitit_ibfk_2` FOREIGN KEY (`sakko_id`) REFERENCES `sakot` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kuitit`
--

LOCK TABLES `kuitit` WRITE;
/*!40000 ALTER TABLE `kuitit` DISABLE KEYS */;
INSERT INTO `kuitit` VALUES (1,1,NULL,NULL,NULL,5.00,'Kirjalainan myöhästymismaksu - Kirja: The Great Gatsby','maksettu','2026-03-20 14:30:00','2026-03-27 11:31:11'),(2,1,NULL,NULL,NULL,2.50,'Laitelainan myöhästymismaksu - Laite: Lenovo ThinkPad','maksettu','2026-03-15 10:15:00','2026-03-27 11:31:11'),(3,5,NULL,NULL,NULL,10.00,'Sakko - Palautus myöhässä','maksettu','2026-03-10 16:45:00','2026-03-27 11:31:11'),(4,1,NULL,NULL,NULL,5.00,'Test receipt - Manual creation','maksettu','2026-04-04 15:19:27','2026-04-04 12:19:27'),(5,1,2,NULL,NULL,0.00,'Kirjalaina: The Great Gatsby - Lainattu 04.04.2026','maksettu','2026-04-04 15:24:18','2026-04-04 12:24:18'),(6,1,NULL,NULL,NULL,5.00,'Test receipt - Manual creation','maksettu','2026-04-04 15:45:58','2026-04-04 12:45:58'),(7,6,3,NULL,NULL,0.00,'LAINAKUITTI: The Great Gatsby - Lainattu 07.04.2026','maksettu','2026-04-07 12:31:41','2026-04-07 09:31:41'),(8,1,NULL,16,NULL,0.00,'✅ PALAUTUSKUITTI - Laite: HP EliteBook - Palautettu: 07.04.2026','maksettu','2026-04-07 14:27:44','2026-04-07 11:27:44'),(9,1,8,NULL,NULL,0.00,'✅ PALAUTUSKUITTI - Kirja: 1984 - Palautettu: 11.04.2026','maksettu','2026-04-11 12:38:31','2026-04-11 09:38:31'),(10,1,9,NULL,NULL,0.00,'📚 LAINAKUITTI - Kirja: To Kill a Mockingbird - Lainattu: 11.04.2026','maksettu','2026-04-11 12:58:14','2026-04-11 09:58:14'),(11,1,9,NULL,NULL,0.00,'✅ PALAUTUSKUITTI - Kirja: To Kill a Mockingbird - Palautettu: 11.04.2026','maksettu','2026-04-11 13:20:41','2026-04-11 10:20:41');
/*!40000 ALTER TABLE `kuitit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lainat`
--

DROP TABLE IF EXISTS `lainat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lainat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jasen_id` int DEFAULT NULL,
  `kirja_id` int NOT NULL,
  `lainauspaiva` date NOT NULL,
  `erapaiva` date NOT NULL,
  `palautuspaiva` date DEFAULT NULL,
  `tila` enum('aktiivinen','palautettu','myohassa') DEFAULT 'aktiivinen',
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sakot` decimal(10,2) DEFAULT '0.00',
  `jatkettu` int DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_loan` (`jasen_id`,`kirja_id`,`lainauspaiva`),
  KEY `idx_kirja_id` (`kirja_id`),
  KEY `idx_jasen_id` (`jasen_id`),
  KEY `idx_tila` (`tila`),
  KEY `idx_erapaiva` (`erapaiva`),
  CONSTRAINT `lainat_ibfk_1` FOREIGN KEY (`kirja_id`) REFERENCES `kirjat` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lainat`
--

LOCK TABLES `lainat` WRITE;
/*!40000 ALTER TABLE `lainat` DISABLE KEYS */;
INSERT INTO `lainat` VALUES (8,1,3,'2026-04-11','2026-04-25',NULL,'aktiivinen','2026-04-11 09:38:31',0.00,0),(9,1,2,'2026-04-11','2026-04-25','2026-04-11','palautettu','2026-04-11 09:49:41',0.00,0);
/*!40000 ALTER TABLE `lainat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `palvelin_lokit`
--

DROP TABLE IF EXISTS `palvelin_lokit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `palvelin_lokit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aikaleima` datetime DEFAULT CURRENT_TIMESTAMP,
  `taso` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tyyppi` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kayttaja` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `viesti` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_osoite` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selain` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_aikaleima` (`aikaleima`),
  KEY `idx_taso` (`taso`),
  KEY `idx_tyyppi` (`tyyppi`),
  KEY `idx_kayttaja` (`kayttaja`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `palvelin_lokit`
--

LOCK TABLES `palvelin_lokit` WRITE;
/*!40000 ALTER TABLE `palvelin_lokit` DISABLE KEYS */;
INSERT INTO `palvelin_lokit` VALUES (1,'2026-02-22 13:36:38','INFO','system','Järjestelmä','Järjestelmä käynnistetty','127.0.0.1','Chrome'),(2,'2026-02-22 13:36:38','SUCCESS','login','admin','Kirjautuminen onnistui','192.168.1.100','Firefox'),(3,'2026-02-22 13:36:38','WARNING','activity','manager','Epäonnistunut kirjautumisyritys','192.168.1.101','Safari'),(4,'2026-02-22 13:36:38','ERROR','error','Järjestelmä','Tietokantayhteys katkesi','127.0.0.1','Chrome'),(5,'2026-02-22 13:36:38','SECURITY','security','Järjestelmä','Virheellinen salasana 3 kertaa','192.168.1.102','Edge');
/*!40000 ALTER TABLE `palvelin_lokit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ryhmaviestit`
--

DROP TABLE IF EXISTS `ryhmaviestit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ryhmaviestit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lahettaja_id` int NOT NULL,
  `viesti` text NOT NULL,
  `on_ilmoitus` tinyint(1) DEFAULT '0',
  `luontiaika` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `kohderyhma` varchar(50) DEFAULT 'kaikille',
  `kayttaja_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lahettaja_id` (`lahettaja_id`),
  KEY `kayttaja_id` (`kayttaja_id`),
  CONSTRAINT `ryhmaviestit_ibfk_1` FOREIGN KEY (`lahettaja_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ryhmaviestit_ibfk_2` FOREIGN KEY (`kayttaja_id`) REFERENCES `jasenet` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Ryhmäviestit ja ilmoitukset';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ryhmaviestit`
--

LOCK TABLES `ryhmaviestit` WRITE;
/*!40000 ALTER TABLE `ryhmaviestit` DISABLE KEYS */;
/*!40000 ALTER TABLE `ryhmaviestit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sakot`
--

DROP TABLE IF EXISTS `sakot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sakot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `laina_id` int DEFAULT NULL,
  `jasen_id` int DEFAULT NULL,
  `sakko_maara` decimal(10,2) DEFAULT '0.00',
  `sakko_paiva` date DEFAULT NULL,
  `maksettu_maara` decimal(10,2) DEFAULT '0.00',
  `maksettu_paiva` date DEFAULT NULL,
  `tila` enum('maksettava','maksettu','osittain') DEFAULT 'maksettava',
  `syy` text,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sakot`
--

LOCK TABLES `sakot` WRITE;
/*!40000 ALTER TABLE `sakot` DISABLE KEYS */;
/*!40000 ALTER TABLE `sakot` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suosikit`
--

DROP TABLE IF EXISTS `suosikit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suosikit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jasen_id` int NOT NULL,
  `kohde_tyyppi` enum('kirja','laite') NOT NULL,
  `kohde_id` int NOT NULL,
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_suosikki` (`jasen_id`,`kohde_tyyppi`,`kohde_id`),
  CONSTRAINT `suosikit_ibfk_1` FOREIGN KEY (`jasen_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suosikit`
--

LOCK TABLES `suosikit` WRITE;
/*!40000 ALTER TABLE `suosikit` DISABLE KEYS */;
/*!40000 ALTER TABLE `suosikit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `varaukset`
--

DROP TABLE IF EXISTS `varaukset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `varaukset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kirja_id` int DEFAULT NULL,
  `jasen_id` int DEFAULT NULL,
  `varaus_paiva` date NOT NULL,
  `tila` enum('odottaa','valmis','peruutettu') DEFAULT 'odottaa',
  `luotu` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `kirjakopio_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kirjakopio_id` (`kirjakopio_id`),
  CONSTRAINT `varaukset_ibfk_1` FOREIGN KEY (`kirjakopio_id`) REFERENCES `Kirjakopiot` (`id`) ON DELETE CASCADE,
  CONSTRAINT `varaukset_ibfk_2` FOREIGN KEY (`kirjakopio_id`) REFERENCES `Kirjakopiot` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `varaukset`
--

LOCK TABLES `varaukset` WRITE;
/*!40000 ALTER TABLE `varaukset` DISABLE KEYS */;
/*!40000 ALTER TABLE `varaukset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viestiasetukset`
--

DROP TABLE IF EXISTS `viestiasetukset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `viestiasetukset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asetus_nimi` varchar(100) NOT NULL,
  `asetus_arvo` text,
  `kuvaus` text,
  `luontiaika` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paivitysaika` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asetus_nimi` (`asetus_nimi`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viestiasetukset`
--

LOCK TABLES `viestiasetukset` WRITE;
/*!40000 ALTER TABLE `viestiasetukset` DISABLE KEYS */;
INSERT INTO `viestiasetukset` VALUES (1,'SALLI_JASEN_VIESTIT','1','Salli jäsenien lähettää viestejä toisilleen','2025-12-23 12:10:45','2025-12-23 12:10:45'),(2,'SALLI_RYHMAVIESTIT','1','Salli ryhmäviestien lähettäminen','2025-12-23 12:10:45','2025-12-23 12:10:45'),(3,'SALLI_ILMOITUKSET_VAIN_ADMIN','1','Vain admin voi lähettää ilmoituksia','2025-12-23 12:10:45','2025-12-23 12:10:45'),(4,'MAX_VIESTIN_PITUUS','1000','Viestin maksimipituus merkkeinä','2025-12-23 12:10:45','2025-12-23 12:10:45'),(5,'SALLI_LIIITTEET','0','Salli liitteiden lähettäminen viesteihin','2025-12-23 12:10:45','2025-12-23 12:10:45'),(6,'MAX_LIIITE_KOKO_MB','5','Liitteen maksimikoko megatavuina','2025-12-23 12:10:45','2025-12-23 12:10:45'),(7,'AUTO_POISTO_PV','365','Viestien automaattinen poisto päivien jälkeen (0=ei autopoistoa)','2025-12-23 12:10:45','2025-12-23 12:10:45'),(8,'ILMOITUS_EMAIL','1','Lähetä sähköposti-ilmoitus uusista viesteistä','2025-12-23 12:10:45','2025-12-23 12:10:45'),(9,'SAVESTA_KOPIO_LAHETETTY','1','Tallenna lähetettyjen viestien kopiot','2025-12-23 12:10:45','2025-12-23 12:10:45'),(10,'NAKYVYYS_JULKINEN_PROFIILI','0','Näytä viestilinkki julkisessa profiilissa','2025-12-23 12:10:45','2025-12-23 12:10:45');
/*!40000 ALTER TABLE `viestiasetukset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `viestit`
--

DROP TABLE IF EXISTS `viestit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `viestit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lahettaja_id` int NOT NULL,
  `vastaanottaja_id` int NOT NULL,
  `viesti` text NOT NULL,
  `luettu` tinyint(1) DEFAULT '0',
  `luontiaika` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lahettaja` (`lahettaja_id`),
  KEY `idx_vastaanottaja` (`vastaanottaja_id`),
  KEY `idx_keskustelu` (`lahettaja_id`,`vastaanottaja_id`,`luontiaika`),
  CONSTRAINT `viestit_ibfk_1` FOREIGN KEY (`lahettaja_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `viestit_ibfk_2` FOREIGN KEY (`vastaanottaja_id`) REFERENCES `jasenet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Käyttäjäviestit';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `viestit`
--

LOCK TABLES `viestit` WRITE;
/*!40000 ALTER TABLE `viestit` DISABLE KEYS */;
INSERT INTO `viestit` VALUES (7,1,1,'hello\r\n',1,'2026-03-05 14:23:06'),(8,1,1,'hello',1,'2026-03-05 14:37:30'),(9,1,1,'how can i help you ?',1,'2026-03-05 14:37:59'),(10,1,1,'hello\r\n',1,'2026-03-05 14:38:09'),(11,1,1,'hello\r\n',1,'2026-03-05 14:38:51'),(12,1,1,'hello',1,'2026-03-05 15:31:01');
/*!40000 ALTER TABLE `viestit` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-22 18:30:43
