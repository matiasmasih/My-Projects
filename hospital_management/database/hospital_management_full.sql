/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: hospital_management
-- ------------------------------------------------------
-- Server version	10.6.23-MariaDB-0ubuntu0.22.04.1

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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` varchar(100) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `admitted_at` datetime DEFAULT current_timestamp(),
  `discharged_at` datetime DEFAULT NULL,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `room_id` int(10) unsigned DEFAULT NULL,
  `bed_id` int(10) unsigned DEFAULT NULL,
  `admitting_doctor_id` int(10) unsigned DEFAULT NULL,
  `admitted_by` int(10) unsigned DEFAULT NULL,
  `status` enum('admitted','discharged','transferred') DEFAULT 'admitted',
  `discharge_summary` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `ward_id` (`ward_id`),
  KEY `room_id` (`room_id`),
  KEY `bed_id` (`bed_id`),
  KEY `admitting_doctor_id` (`admitting_doctor_id`),
  KEY `admitted_by` (`admitted_by`),
  CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admissions_ibfk_2` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_ibfk_4` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_ibfk_5` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_ibfk_6` FOREIGN KEY (`admitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `admissions_ibfk_7` FOREIGN KEY (`admitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
INSERT INTO `admissions` VALUES (4,1,'2026-09-16 20:00:00',NULL,13,NULL,NULL,1,1,'admitted',NULL,'2026-09-17 14:16:39','2026-09-17 14:16:39'),(5,2,'2026-09-14 08:00:00','2026-09-15 10:00:00',13,NULL,NULL,1,1,'discharged','Recovered well, discharged with medication.','2026-09-17 14:16:39','2026-09-17 14:16:39');
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `doctor_id` int(10) unsigned NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` smallint(5) unsigned DEFAULT 30,
  `status` enum('scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show') DEFAULT 'scheduled',
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_by` (`created_by`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `idx_appointment_doctor_time` (`doctor_id`,`scheduled_at`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,1,1,'2026-09-17 11:00:00',30,'in_progress','Checkup',NULL,NULL,'2026-09-17 11:03:35','2026-09-17 16:42:03'),(2,1,1,'2026-09-15 09:00:00',30,'completed','Annual checkup',NULL,NULL,'2026-09-17 17:15:27','2026-09-17 17:15:27'),(3,2,1,'2026-09-16 14:30:00',45,'completed','Flu symptoms',NULL,NULL,'2026-09-17 17:15:27','2026-09-17 17:15:27'),(4,3,1,'2026-09-18 10:00:00',30,'scheduled','Blood test follow-up',NULL,NULL,'2026-09-17 17:15:27','2026-09-17 17:15:27'),(5,4,1,'2026-09-18 11:30:00',30,'confirmed','Back pain consultation',NULL,NULL,'2026-09-17 17:15:27','2026-09-17 17:15:27'),(6,5,1,'2026-09-20 15:00:00',30,'scheduled','Vaccination',NULL,NULL,'2026-09-17 17:15:27','2026-09-17 17:15:27');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beds`
--

DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `beds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(10) unsigned NOT NULL,
  `bed_label` varchar(50) DEFAULT NULL,
  `status` enum('free','occupied','maintenance') DEFAULT 'free',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beds`
--

LOCK TABLES `beds` WRITE;
/*!40000 ALTER TABLE `beds` DISABLE KEYS */;
/*!40000 ALTER TABLE `beds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_schedules`
--

DROP TABLE IF EXISTS `doctor_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` int(10) unsigned NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `slot_minutes` smallint(5) unsigned DEFAULT 30,
  `is_available` tinyint(1) DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_schedules_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `doctor_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_specialties`
--

DROP TABLE IF EXISTS `doctor_specialties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_specialties` (
  `doctor_id` int(10) unsigned NOT NULL,
  `specialty_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`doctor_id`,`specialty_id`),
  KEY `specialty_id` (`specialty_id`),
  CONSTRAINT `doctor_specialties_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `doctor_specialties_ibfk_2` FOREIGN KEY (`specialty_id`) REFERENCES `specialties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_specialties`
--

LOCK TABLES `doctor_specialties` WRITE;
/*!40000 ALTER TABLE `doctor_specialties` DISABLE KEYS */;
/*!40000 ALTER TABLE `doctor_specialties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','on_leave','terminated') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `idx_doctor_license` (`license_number`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (1,3,'LIC-AZIZ-001','General Practitioner',50.00,'2026-09-17 16:33:31','2026-09-17 16:38:29','active');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `related_table` varchar(100) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `file_path` varchar(1024) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `files`
--

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insurance_providers`
--

DROP TABLE IF EXISTS `insurance_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_providers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contact_info`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insurance_providers`
--

LOCK TABLES `insurance_providers` WRITE;
/*!40000 ALTER TABLE `insurance_providers` DISABLE KEYS */;
/*!40000 ALTER TABLE `insurance_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `item_type` enum('service','medicine','room','other') DEFAULT 'service',
  `reference_id` int(10) unsigned DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(12,2) DEFAULT 0.00,
  `total_price` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (1,2,'General consultation','service',NULL,1,30.00,30.00),(2,2,'Rapid flu test','service',NULL,1,15.00,15.00),(3,3,'Annual checkup','service',NULL,1,50.00,50.00),(4,3,'Blood test panel','service',NULL,1,40.00,40.00),(5,3,'ECG','service',NULL,1,30.00,30.00),(6,4,'Consultation','service',NULL,1,30.00,30.00),(7,5,'Vaccination','service',NULL,1,50.00,50.00),(8,5,'Medication (7 days)','service',NULL,1,25.00,25.00),(9,6,'Medical Consultation — Blood test follow-up','service',1,1,50.00,50.00);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `patient_id` int(10) unsigned DEFAULT NULL,
  `issued_by` int(10) unsigned DEFAULT NULL,
  `issued_at` datetime DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('draft','issued','partially_paid','paid','cancelled') DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `patient_id` (`patient_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,1,'INV-2026-0022',1,1,'2026-09-16 16:29:06','2026-09-23',50.00,'draft','Invoice for appointment: Checkup','2026-09-16 16:29:06','2026-09-17 16:42:03'),(2,NULL,'INV-2026-0023',2,1,'2026-09-16 15:00:00','2026-09-23',45.00,'issued','Flu consultation','2026-09-17 17:15:29','2026-09-17 17:15:29'),(3,NULL,'INV-2026-0024',3,1,'2026-09-15 10:00:00','2026-09-22',120.00,'paid','Annual checkup + tests','2026-09-17 17:15:29','2026-09-17 17:15:29'),(4,NULL,'INV-2026-0025',4,1,'2026-09-16 16:00:00','2026-09-23',30.00,'draft','Back pain consultation','2026-09-17 17:15:29','2026-09-17 17:15:29'),(5,NULL,'INV-2026-0026',5,1,'2026-09-17 09:00:00','2026-09-24',75.00,'partially_paid','Vaccination + medication','2026-09-17 17:15:29','2026-09-17 17:15:29'),(6,4,'INV-2026-0027',3,1,'2026-09-20 13:41:13','2026-09-27',50.00,'issued','Invoice for appointment: Blood test follow-up','2026-09-20 13:41:13','2026-09-20 13:41:13');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_records`
--

DROP TABLE IF EXISTS `medical_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `appointment_id` int(10) unsigned DEFAULT NULL,
  `doctor_id` int(10) unsigned DEFAULT NULL,
  `visit_date` datetime DEFAULT current_timestamp(),
  `visit_type` varchar(100) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_records`
--

LOCK TABLES `medical_records` WRITE;
/*!40000 ALTER TABLE `medical_records` DISABLE KEYS */;
INSERT INTO `medical_records` VALUES (1,2,NULL,1,'2026-09-20 10:00:00','Consultation','Patient presented with flu-like symptoms','Influenza (seasonal)','Rest, fluids, antipyretics, monitor for 5 days','No complications. Follow-up if fever persists.','2026-09-22 16:45:57','2026-09-22 16:45:57'),(3,3,NULL,1,'2026-09-18 11:00:00','Lab Test','Annual blood panel for routine checkup','Mild anemia detected','Iron supplement, dietary advice','Recheck in 4 weeks.','2026-09-22 16:45:57','2026-09-22 16:45:57'),(4,4,NULL,1,'2026-09-17 09:30:00','Consultation','Patient reported lower back pain for 3 days','Lumbar strain','NSAIDs, physical therapy referral, rest','Advised against heavy lifting.','2026-09-22 16:45:57','2026-09-22 16:45:57'),(5,5,NULL,1,'2026-09-19 15:00:00','Vaccination','Routine childhood vaccination visit','Healthy — no contraindications','Administered scheduled vaccine','Parent advised on possible mild fever.','2026-09-22 16:45:57','2026-09-22 16:45:57');
/*!40000 ALTER TABLE `medical_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicine_batches`
--

DROP TABLE IF EXISTS `medicine_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicine_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_id` int(10) unsigned NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_id` (`medicine_id`,`batch_number`),
  KEY `idx_pharmacy_batch_expiry` (`expiry_date`),
  CONSTRAINT `medicine_batches_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicine_batches`
--

LOCK TABLES `medicine_batches` WRITE;
/*!40000 ALTER TABLE `medicine_batches` DISABLE KEYS */;
INSERT INTO `medicine_batches` VALUES (1,1,'BATCH-PARA-001','2029-12-31',2.50,5.00,'2025-10-15 17:14:46'),(2,2,'BATCH-AMOX-001','2029-12-30',3.00,6.00,'2025-10-15 17:14:46'),(3,3,'BATCH-VENT-001','2029-12-31',8.00,15.00,'2025-10-15 17:14:46'),(5,4,'BATCH-IBU-001-NEW','2029-12-31',3.00,7.00,'2025-10-16 15:52:55'),(6,5,'BATCH-VITA-001','2027-10-17',3.00,5.00,'2025-10-17 13:35:17'),(13,6,'BATCH-OMEP-001','2027-12-31',3.00,6.50,'2026-09-21 11:45:25'),(14,7,'BATCH-METF-001','2027-12-31',2.00,4.50,'2026-09-21 11:45:25'),(15,8,'BATCH-ATOR-001','2027-12-31',4.00,8.50,'2026-09-21 11:45:25');
/*!40000 ALTER TABLE `medicine_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `generic_name` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `form` varchar(100) DEFAULT NULL,
  `strength` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'tablet',
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`brand`,`strength`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
INSERT INTO `medicines` VALUES (1,'Paracetamol 500mg','Acetaminophen','PharmaCorp','Tablet','500mg','tablet','Pain reliever and fever reducer','2025-10-15 17:14:24','2026-09-21 11:51:53'),(2,'Amoxicillin 250mg','Amoxicillin','MediLife','Capsule','250mg','capsule','Antibiotic for bacterial infections','2025-10-15 17:14:24','2026-09-21 11:53:09'),(3,'Ventolin Inhaler','Salbutamol','GSK','Inhaler','100mcg','inhaler','Bronchodilator for asthma','2025-10-15 17:14:24','2026-09-21 11:52:43'),(4,'Ibuprofen 400mg','Ibuprofen','PainFree Inc','Tablet','400mg','tablet','Anti-inflammatory pain reliever','2025-10-15 17:14:24','2026-09-21 11:53:29'),(5,'Vitamin C 1000mg','Ascorbic Acid','HealthPlus','Tablet','1000mg','tablet','Immune system support and antioxidant','2025-10-17 13:35:17','2025-10-18 14:07:39'),(6,'Omeprazole 20mg','Omeprazole','Losec','Capsule','20mg','capsule','For acid reflux and ulcers','2026-09-17 17:15:27','2026-09-21 11:49:33'),(7,'Metformin 500mg','Metformin','Glucophage','tablet','500mg','tablet','For type 2 diabetes','2026-09-17 17:15:27','2026-09-21 11:49:33'),(8,'Atorvastatin 20mg','Atorvastatin','Lipitor','tablet','20mg','tablet','For high cholesterol','2026-09-17 17:15:27','2026-09-21 11:49:33');
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `message_templates`
--

DROP TABLE IF EXISTS `message_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `category` varchar(50) DEFAULT 'general',
  `body` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `message_templates`
--

LOCK TABLES `message_templates` WRITE;
/*!40000 ALTER TABLE `message_templates` DISABLE KEYS */;
INSERT INTO `message_templates` VALUES (1,'Acknowledge receipt','general','Thank you for your message. We have received it and will respond as soon as possible.','2026-09-22 13:58:13'),(2,'Appointment confirmed','appointment','Your appointment has been confirmed. Please arrive 10 minutes before your scheduled time.','2026-09-22 13:58:13'),(3,'Appointment reschedule','appointment','We need to reschedule your appointment. Please contact us at your earliest convenience to pick a new time.','2026-09-22 13:58:13'),(4,'Billing question received','billing','Your billing question has been received. Our finance team will review it and get back to you within 2 business days.','2026-09-22 13:58:13'),(5,'Test results ready','medical','Your test results are ready. Please log in to the patient portal or visit us to view them.','2026-09-22 13:58:13'),(6,'Prescription ready','pharmacy','Your prescription is ready for pickup at the pharmacy.','2026-09-22 13:58:13'),(7,'Please call us','general','Please call us at your earliest convenience so we can discuss this further.','2026-09-22 13:58:13'),(8,'Closed - resolved','general','This matter has been resolved. If you need further assistance, please do not hesitate to reach out again.','2026-09-22 13:58:13');
/*!40000 ALTER TABLE `message_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `conversation_id` int(10) unsigned DEFAULT NULL,
  `sender_id` int(10) unsigned NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('unread','read') DEFAULT 'unread',
  `is_deleted` tinyint(4) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `recipient_type` enum('user','admin','manager') DEFAULT 'user',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (19,NULL,NULL,1,0,'Budget Approval Needed','Need your approval for the Q4 budget allocation.','urgent','read',0,0,'2025-10-25 15:01:29',NULL,'admin'),(20,NULL,NULL,4,0,'System Maintenance','System will be down for maintenance this weekend.','high','read',0,0,'2025-10-25 15:01:52',NULL,'manager'),(21,NULL,NULL,1,0,'Budget Approval Needed','Need your approval for the Q4 budget allocation.','urgent','read',0,0,'2025-10-25 15:02:12',NULL,'admin'),(22,NULL,NULL,4,0,'System Maintenance','System will be down for maintenance this weekend.','high','read',0,0,'2025-10-25 15:02:28',NULL,'manager'),(23,NULL,NULL,4,0,'Re: Emergency Case','Check this Emergency Case','normal','unread',0,0,'2025-11-19 12:24:05',NULL,'admin'),(24,NULL,NULL,3,5,'Appointment reminder','Hi Alice, this is a reminder about your upcoming appointment on Friday.','normal','unread',0,0,'2026-09-17 17:16:40',NULL,'user'),(25,NULL,NULL,5,3,'Re: Appointment reminder','Thank you, doctor. I will be there.','normal','read',0,0,'2026-09-17 17:16:40',NULL,'user'),(26,NULL,NULL,3,6,'Test results','Your blood test results are ready. Everything looks good.','normal','unread',0,0,'2026-09-17 17:16:40',NULL,'user'),(27,NULL,NULL,1,3,'Schedule update','Please note that the staff meeting has been moved to 3 PM.','high','unread',0,0,'2026-09-17 17:16:40',NULL,'user');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` varchar(100) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_contacts`
--

DROP TABLE IF EXISTS `patient_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `relation` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `patient_contacts_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_contacts`
--

LOCK TABLES `patient_contacts` WRITE;
/*!40000 ALTER TABLE `patient_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_insurances`
--

DROP TABLE IF EXISTS `patient_insurances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_insurances` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) unsigned NOT NULL,
  `provider_id` int(10) unsigned NOT NULL,
  `policy_number` varchar(255) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `patient_insurances_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_insurances_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `insurance_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_insurances`
--

LOCK TABLES `patient_insurances` WRITE;
/*!40000 ALTER TABLE `patient_insurances` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_insurances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_number` varchar(100) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other','unspecified') DEFAULT 'unspecified',
  `national_id` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive','deceased','transferred') NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_record_number` (`medical_record_number`),
  KEY `idx_patient_name` (`last_name`,`first_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'MRN-0001','Aziz','Rahman','2026-09-16','male','12345-432B','Vaahtokuja 5 E50','0413114312','aziz.rahman@test.com','2026-09-16 13:26:50','2026-09-23 15:52:10','Ali','045321432','active','uploads/avatars/avatar_1.jpg'),(2,'MRN-0002','Alice','Smith','1990-05-15','female','150590-987X','Vaahtokuja 5 E50','045321432','alice@patient.com','2026-09-17 16:11:35','2026-09-23 15:29:27','aziz','0413114312','active','uploads/avatars/avatar_2_1790166567.jpg'),(3,'MRN-0003','Maria','Garcia','1985-03-12','female','120385-123A','Mannerheimintie 12, Helsinki','0451234567','maria.garcia@test.com','2026-09-17 17:15:27','2026-09-23 15:52:10',NULL,NULL,'active','uploads/avatars/avatar_3.jpg'),(4,'MRN-0004','John','Doe','1978-07-22','male','220778-456B','Aleksanterinkatu 5, Helsinki','0407654321','john.doe@test.com','2026-09-17 17:15:27','2026-09-23 15:52:10',NULL,NULL,'active','uploads/avatars/avatar_4.jpg'),(5,'MRN-0005','Sophia','Nguyen','1995-11-08','female','081195-789C','Esplanadi 8, Helsinki','0509998877','sophia.nguyen@test.com','2026-09-17 17:15:27','2026-09-23 15:52:10',NULL,NULL,'active','uploads/avatars/avatar_5.jpg');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','card','insurance','bank_transfer','other') DEFAULT 'cash',
  `transaction_reference` varchar(255) DEFAULT NULL,
  `paid_by` varchar(255) DEFAULT NULL,
  `paid_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_dates` (`paid_at`,`status`),
  KEY `idx_payments_invoice_status` (`invoice_id`,`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (9,3,120.00,'card','TXN-2026-001','Sarah Johnson','2026-09-15 11:30:00','2026-09-17 17:16:39','paid','Full payment'),(10,5,40.00,'cash',NULL,'Sarah Johnson','2026-09-17 10:00:00','2026-09-17 17:16:39','paid','Partial payment'),(11,2,20.00,'insurance','INS-2026-42','Sarah Johnson','2026-09-17 12:00:00','2026-09-17 17:16:39','pending','Waiting for insurance');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacy_stock`
--

DROP TABLE IF EXISTS `pharmacy_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pharmacy_stock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `medicine_batch_id` int(10) unsigned NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `min_threshold` int(11) DEFAULT 0,
  `location` varchar(100) DEFAULT 'main_pharmacy',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `medicine_batch_id` (`medicine_batch_id`),
  CONSTRAINT `pharmacy_stock_ibfk_1` FOREIGN KEY (`medicine_batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacy_stock`
--

LOCK TABLES `pharmacy_stock` WRITE;
/*!40000 ALTER TABLE `pharmacy_stock` DISABLE KEYS */;
INSERT INTO `pharmacy_stock` VALUES (1,1,150,20,'main_pharmacy','2026-09-21 11:51:53'),(2,2,75,15,'main_pharmacy','2026-09-21 11:53:09'),(3,3,45,10,'main_pharmacy','2026-09-21 11:52:43'),(5,5,100,15,'main_pharmacy','2026-09-21 11:53:29'),(6,6,50,10,'main_pharmacy','2025-10-18 14:07:39'),(12,13,100,15,'main_pharmacy','2026-09-21 11:45:25'),(13,14,100,15,'main_pharmacy','2026-09-21 11:45:25'),(14,15,100,15,'main_pharmacy','2026-09-21 11:45:25');
/*!40000 ALTER TABLE `pharmacy_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription_items`
--

DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` int(10) unsigned NOT NULL,
  `medicine_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `instructions` text DEFAULT NULL,
  `dispensed` tinyint(1) DEFAULT 0,
  `dispensed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`),
  CONSTRAINT `prescription_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription_items`
--

LOCK TABLES `prescription_items` WRITE;
/*!40000 ALTER TABLE `prescription_items` DISABLE KEYS */;
INSERT INTO `prescription_items` VALUES (1,1,1,NULL,'500mg','Every 6 hours','5 days',20,'Take with water after meals',0,NULL),(2,1,5,NULL,'1000mg','Once daily','10 days',10,'Take in the morning',0,NULL),(3,3,5,NULL,'500mg','Twice daily','4 weeks',56,'Take with orange juice to aid absorption',1,NULL),(4,4,4,NULL,'400mg','Every 8 hours as needed','7 days',21,'Take after food to avoid stomach upset',1,NULL),(5,5,1,NULL,'250mg','Every 6 hours if fever','2 days',8,'Children dose — consult if fever persists past 48h',0,NULL);
/*!40000 ALTER TABLE `prescription_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` int(10) unsigned DEFAULT NULL,
  `appointment_id` int(10) unsigned DEFAULT NULL,
  `prescribed_by` int(10) unsigned DEFAULT NULL,
  `prescribed_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `medical_record_id` (`medical_record_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `prescribed_by` (`prescribed_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`prescribed_by`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
INSERT INTO `prescriptions` VALUES (1,1,NULL,1,'2026-09-20 10:15:00','Take with food. Complete the full course.','2026-09-22 16:46:10'),(3,3,NULL,1,'2026-09-18 11:30:00','Take iron supplement away from dairy products.','2026-09-22 16:46:10'),(4,4,NULL,1,'2026-09-17 10:00:00','Take only as needed. Max 3 per day.','2026-09-22 16:46:10'),(5,5,NULL,1,'2026-09-19 15:15:00','For mild fever if it occurs. Not required unless symptoms appear.','2026-09-22 16:46:10');
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','Full access to the system',NULL,'2025-10-08 15:45:55','2025-10-08 15:45:55'),(2,'Manager','Manages staff and operations',NULL,'2025-10-08 15:45:55','2025-10-08 15:45:55'),(3,'Doctor','Medical professional',NULL,'2025-10-08 15:45:55','2025-10-08 15:45:55'),(4,'Staff','General staff member',NULL,'2025-10-08 15:45:55','2025-10-08 15:45:55'),(6,'Patient',NULL,NULL,'2025-10-22 16:31:44','2025-10-22 16:31:44');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_type` enum('single','double','icu','ward','other') DEFAULT 'ward',
  `capacity` smallint(5) unsigned DEFAULT 1,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ward_id` (`ward_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=668 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (15,13,'A1','ward',4,'First Floor, East Wing'),(16,13,'A11','ward',4,'First Floor, East Wing'),(17,13,'A21','ward',4,'First Floor, East Wing'),(18,13,'A31','ward',4,'First Floor, East Wing'),(19,13,'A41','ward',4,'First Floor, East Wing'),(20,13,'A51','ward',4,'First Floor, East Wing'),(21,13,'A61','ward',4,'First Floor, East Wing'),(22,13,'A71','ward',4,'First Floor, East Wing'),(23,13,'A2','ward',4,'First Floor, East Wing'),(24,13,'A12','ward',4,'First Floor, East Wing'),(25,13,'A22','ward',4,'First Floor, East Wing'),(26,13,'A32','ward',4,'First Floor, East Wing'),(27,13,'A42','ward',4,'First Floor, East Wing'),(28,13,'A52','ward',4,'First Floor, East Wing'),(29,13,'A62','ward',4,'First Floor, East Wing'),(30,13,'A72','ward',4,'First Floor, East Wing'),(31,13,'A3','ward',4,'First Floor, East Wing'),(32,13,'A13','ward',4,'First Floor, East Wing'),(33,13,'A23','ward',4,'First Floor, East Wing'),(34,13,'A33','ward',4,'First Floor, East Wing'),(35,13,'A43','ward',4,'First Floor, East Wing'),(36,13,'A53','ward',4,'First Floor, East Wing'),(37,13,'A63','ward',4,'First Floor, East Wing'),(38,13,'A73','ward',4,'First Floor, East Wing'),(39,13,'A4','ward',4,'First Floor, East Wing'),(40,13,'A14','ward',4,'First Floor, East Wing'),(41,13,'A24','ward',4,'First Floor, East Wing'),(42,13,'A34','ward',4,'First Floor, East Wing'),(43,13,'A44','ward',4,'First Floor, East Wing'),(44,13,'A54','ward',4,'First Floor, East Wing'),(45,13,'A64','ward',4,'First Floor, East Wing'),(46,13,'A74','ward',4,'First Floor, East Wing'),(47,13,'A5','ward',4,'First Floor, East Wing'),(48,13,'A15','ward',4,'First Floor, East Wing'),(49,13,'A25','ward',4,'First Floor, East Wing'),(50,13,'A35','ward',4,'First Floor, East Wing'),(51,13,'A45','ward',4,'First Floor, East Wing'),(52,13,'A55','ward',4,'First Floor, East Wing'),(53,13,'A65','ward',4,'First Floor, East Wing'),(54,13,'A75','ward',4,'First Floor, East Wing'),(55,13,'A6','ward',4,'First Floor, East Wing'),(56,13,'A16','ward',4,'First Floor, East Wing'),(57,13,'A26','ward',4,'First Floor, East Wing'),(58,13,'A36','ward',4,'First Floor, East Wing'),(59,13,'A46','ward',4,'First Floor, East Wing'),(60,13,'A56','ward',4,'First Floor, East Wing'),(61,13,'A66','ward',4,'First Floor, East Wing'),(62,13,'A76','ward',4,'First Floor, East Wing'),(63,13,'A7','ward',4,'First Floor, East Wing'),(64,13,'A17','ward',4,'First Floor, East Wing'),(65,13,'A27','ward',4,'First Floor, East Wing'),(66,13,'A37','ward',4,'First Floor, East Wing'),(67,13,'A47','ward',4,'First Floor, East Wing'),(68,13,'A57','ward',4,'First Floor, East Wing'),(69,13,'A67','ward',4,'First Floor, East Wing'),(70,13,'A77','ward',4,'First Floor, East Wing'),(71,13,'A8','ward',4,'First Floor, East Wing'),(72,13,'A18','ward',4,'First Floor, East Wing'),(73,13,'A28','ward',4,'First Floor, East Wing'),(74,13,'A38','ward',4,'First Floor, East Wing'),(75,13,'A48','ward',4,'First Floor, East Wing'),(76,13,'A58','ward',4,'First Floor, East Wing'),(77,13,'A68','ward',4,'First Floor, East Wing'),(78,13,'A78','ward',4,'First Floor, East Wing'),(79,13,'A9','ward',4,'First Floor, East Wing'),(80,13,'A19','ward',4,'First Floor, East Wing'),(81,13,'A29','ward',4,'First Floor, East Wing'),(82,13,'A39','ward',4,'First Floor, East Wing'),(83,13,'A49','ward',4,'First Floor, East Wing'),(84,13,'A59','ward',4,'First Floor, East Wing'),(85,13,'A69','ward',4,'First Floor, East Wing'),(86,13,'A79','ward',4,'First Floor, East Wing'),(87,13,'A10','ward',4,'First Floor, East Wing'),(88,13,'A20','ward',4,'First Floor, East Wing'),(89,13,'A30','ward',4,'First Floor, East Wing'),(90,13,'A40','ward',4,'First Floor, East Wing'),(91,13,'A50','ward',4,'First Floor, East Wing'),(92,13,'A60','ward',4,'First Floor, East Wing'),(93,13,'A70','ward',4,'First Floor, East Wing'),(94,13,'A80','ward',4,'First Floor, East Wing'),(142,14,'B1','ward',4,'First Floor, West Wing'),(143,14,'B11','ward',4,'First Floor, West Wing'),(144,14,'B21','ward',4,'First Floor, West Wing'),(145,14,'B31','ward',4,'First Floor, West Wing'),(146,14,'B41','ward',4,'First Floor, West Wing'),(147,14,'B51','ward',4,'First Floor, West Wing'),(148,14,'B61','ward',4,'First Floor, West Wing'),(149,14,'B71','ward',4,'First Floor, West Wing'),(150,14,'B2','ward',4,'First Floor, West Wing'),(151,14,'B12','ward',4,'First Floor, West Wing'),(152,14,'B22','ward',4,'First Floor, West Wing'),(153,14,'B32','ward',4,'First Floor, West Wing'),(154,14,'B42','ward',4,'First Floor, West Wing'),(155,14,'B52','ward',4,'First Floor, West Wing'),(156,14,'B62','ward',4,'First Floor, West Wing'),(157,14,'B72','ward',4,'First Floor, West Wing'),(158,14,'B3','ward',4,'First Floor, West Wing'),(159,14,'B13','ward',4,'First Floor, West Wing'),(160,14,'B23','ward',4,'First Floor, West Wing'),(161,14,'B33','ward',4,'First Floor, West Wing'),(162,14,'B43','ward',4,'First Floor, West Wing'),(163,14,'B53','ward',4,'First Floor, West Wing'),(164,14,'B63','ward',4,'First Floor, West Wing'),(165,14,'B73','ward',4,'First Floor, West Wing'),(166,14,'B4','ward',4,'First Floor, West Wing'),(167,14,'B14','ward',4,'First Floor, West Wing'),(168,14,'B24','ward',4,'First Floor, West Wing'),(169,14,'B34','ward',4,'First Floor, West Wing'),(170,14,'B44','ward',4,'First Floor, West Wing'),(171,14,'B54','ward',4,'First Floor, West Wing'),(172,14,'B64','ward',4,'First Floor, West Wing'),(173,14,'B74','ward',4,'First Floor, West Wing'),(174,14,'B5','ward',4,'First Floor, West Wing'),(175,14,'B15','ward',4,'First Floor, West Wing'),(176,14,'B25','ward',4,'First Floor, West Wing'),(177,14,'B35','ward',4,'First Floor, West Wing'),(178,14,'B45','ward',4,'First Floor, West Wing'),(179,14,'B55','ward',4,'First Floor, West Wing'),(180,14,'B65','ward',4,'First Floor, West Wing'),(181,14,'B6','ward',4,'First Floor, West Wing'),(182,14,'B16','ward',4,'First Floor, West Wing'),(183,14,'B26','ward',4,'First Floor, West Wing'),(184,14,'B36','ward',4,'First Floor, West Wing'),(185,14,'B46','ward',4,'First Floor, West Wing'),(186,14,'B56','ward',4,'First Floor, West Wing'),(187,14,'B66','ward',4,'First Floor, West Wing'),(188,14,'B7','ward',4,'First Floor, West Wing'),(189,14,'B17','ward',4,'First Floor, West Wing'),(190,14,'B27','ward',4,'First Floor, West Wing'),(191,14,'B37','ward',4,'First Floor, West Wing'),(192,14,'B47','ward',4,'First Floor, West Wing'),(193,14,'B57','ward',4,'First Floor, West Wing'),(194,14,'B67','ward',4,'First Floor, West Wing'),(195,14,'B8','ward',4,'First Floor, West Wing'),(196,14,'B18','ward',4,'First Floor, West Wing'),(197,14,'B28','ward',4,'First Floor, West Wing'),(198,14,'B38','ward',4,'First Floor, West Wing'),(199,14,'B48','ward',4,'First Floor, West Wing'),(200,14,'B58','ward',4,'First Floor, West Wing'),(201,14,'B68','ward',4,'First Floor, West Wing'),(202,14,'B9','ward',4,'First Floor, West Wing'),(203,14,'B19','ward',4,'First Floor, West Wing'),(204,14,'B29','ward',4,'First Floor, West Wing'),(205,14,'B39','ward',4,'First Floor, West Wing'),(206,14,'B49','ward',4,'First Floor, West Wing'),(207,14,'B59','ward',4,'First Floor, West Wing'),(208,14,'B69','ward',4,'First Floor, West Wing'),(209,14,'B10','ward',4,'First Floor, West Wing'),(210,14,'B20','ward',4,'First Floor, West Wing'),(211,14,'B30','ward',4,'First Floor, West Wing'),(212,14,'B40','ward',4,'First Floor, West Wing'),(213,14,'B50','ward',4,'First Floor, West Wing'),(214,14,'B60','ward',4,'First Floor, West Wing'),(215,14,'B70','ward',4,'First Floor, West Wing'),(269,15,'ICU-1','icu',1,'Second Floor, North Wing — Critical Care'),(270,15,'ICU-11','icu',1,'Second Floor, North Wing — Critical Care'),(271,15,'ICU-21','icu',1,'Second Floor, North Wing — Critical Care'),(272,15,'ICU-2','icu',1,'Second Floor, North Wing — Critical Care'),(273,15,'ICU-12','icu',1,'Second Floor, North Wing — Critical Care'),(274,15,'ICU-22','icu',1,'Second Floor, North Wing — Critical Care'),(275,15,'ICU-3','icu',1,'Second Floor, North Wing — Critical Care'),(276,15,'ICU-13','icu',1,'Second Floor, North Wing — Critical Care'),(277,15,'ICU-23','icu',1,'Second Floor, North Wing — Critical Care'),(278,15,'ICU-4','icu',1,'Second Floor, North Wing — Critical Care'),(279,15,'ICU-14','icu',1,'Second Floor, North Wing — Critical Care'),(280,15,'ICU-24','icu',1,'Second Floor, North Wing — Critical Care'),(281,15,'ICU-5','icu',1,'Second Floor, North Wing — Critical Care'),(282,15,'ICU-15','icu',1,'Second Floor, North Wing — Critical Care'),(283,15,'ICU-25','icu',1,'Second Floor, North Wing — Critical Care'),(284,15,'ICU-6','icu',1,'Second Floor, North Wing — Critical Care'),(285,15,'ICU-16','icu',1,'Second Floor, North Wing — Critical Care'),(286,15,'ICU-26','icu',1,'Second Floor, North Wing — Critical Care'),(287,15,'ICU-7','icu',1,'Second Floor, North Wing — Critical Care'),(288,15,'ICU-17','icu',1,'Second Floor, North Wing — Critical Care'),(289,15,'ICU-27','icu',1,'Second Floor, North Wing — Critical Care'),(290,15,'ICU-8','icu',1,'Second Floor, North Wing — Critical Care'),(291,15,'ICU-18','icu',1,'Second Floor, North Wing — Critical Care'),(292,15,'ICU-28','icu',1,'Second Floor, North Wing — Critical Care'),(293,15,'ICU-9','icu',1,'Second Floor, North Wing — Critical Care'),(294,15,'ICU-19','icu',1,'Second Floor, North Wing — Critical Care'),(295,15,'ICU-29','icu',1,'Second Floor, North Wing — Critical Care'),(296,15,'ICU-10','icu',1,'Second Floor, North Wing — Critical Care'),(297,15,'ICU-20','icu',1,'Second Floor, North Wing — Critical Care'),(298,15,'ICU-30','icu',1,'Second Floor, North Wing — Critical Care'),(300,16,'M1','single',1,'Second Floor, East Wing — Maternity'),(301,16,'M11','single',1,'Second Floor, East Wing — Maternity'),(302,16,'M21','single',1,'Second Floor, East Wing — Maternity'),(303,16,'M31','single',1,'Second Floor, East Wing — Maternity'),(304,16,'M2','single',1,'Second Floor, East Wing — Maternity'),(305,16,'M12','single',1,'Second Floor, East Wing — Maternity'),(306,16,'M22','single',1,'Second Floor, East Wing — Maternity'),(307,16,'M32','single',1,'Second Floor, East Wing — Maternity'),(308,16,'M3','single',1,'Second Floor, East Wing — Maternity'),(309,16,'M13','single',1,'Second Floor, East Wing — Maternity'),(310,16,'M23','single',1,'Second Floor, East Wing — Maternity'),(311,16,'M4','single',1,'Second Floor, East Wing — Maternity'),(312,16,'M14','single',1,'Second Floor, East Wing — Maternity'),(313,16,'M24','single',1,'Second Floor, East Wing — Maternity'),(314,16,'M5','single',1,'Second Floor, East Wing — Maternity'),(315,16,'M15','single',1,'Second Floor, East Wing — Maternity'),(316,16,'M25','single',1,'Second Floor, East Wing — Maternity'),(317,16,'M6','single',1,'Second Floor, East Wing — Maternity'),(318,16,'M16','single',1,'Second Floor, East Wing — Maternity'),(319,16,'M26','single',1,'Second Floor, East Wing — Maternity'),(320,16,'M7','single',1,'Second Floor, East Wing — Maternity'),(321,16,'M17','single',1,'Second Floor, East Wing — Maternity'),(322,16,'M27','single',1,'Second Floor, East Wing — Maternity'),(323,16,'M8','single',1,'Second Floor, East Wing — Maternity'),(324,16,'M18','single',1,'Second Floor, East Wing — Maternity'),(325,16,'M28','single',1,'Second Floor, East Wing — Maternity'),(326,16,'M9','single',1,'Second Floor, East Wing — Maternity'),(327,16,'M19','single',1,'Second Floor, East Wing — Maternity'),(328,16,'M29','single',1,'Second Floor, East Wing — Maternity'),(329,16,'M10','single',1,'Second Floor, East Wing — Maternity'),(330,16,'M20','single',1,'Second Floor, East Wing — Maternity'),(331,16,'M30','single',1,'Second Floor, East Wing — Maternity'),(363,17,'P1','double',2,'Third Floor, East Wing — Pediatric'),(364,17,'P11','double',2,'Third Floor, East Wing — Pediatric'),(365,17,'P21','double',2,'Third Floor, East Wing — Pediatric'),(366,17,'P31','double',2,'Third Floor, East Wing — Pediatric'),(367,17,'P2','double',2,'Third Floor, East Wing — Pediatric'),(368,17,'P12','double',2,'Third Floor, East Wing — Pediatric'),(369,17,'P22','double',2,'Third Floor, East Wing — Pediatric'),(370,17,'P32','double',2,'Third Floor, East Wing — Pediatric'),(371,17,'P3','double',2,'Third Floor, East Wing — Pediatric'),(372,17,'P13','double',2,'Third Floor, East Wing — Pediatric'),(373,17,'P23','double',2,'Third Floor, East Wing — Pediatric'),(374,17,'P4','double',2,'Third Floor, East Wing — Pediatric'),(375,17,'P14','double',2,'Third Floor, East Wing — Pediatric'),(376,17,'P24','double',2,'Third Floor, East Wing — Pediatric'),(377,17,'P5','double',2,'Third Floor, East Wing — Pediatric'),(378,17,'P15','double',2,'Third Floor, East Wing — Pediatric'),(379,17,'P25','double',2,'Third Floor, East Wing — Pediatric'),(380,17,'P6','double',2,'Third Floor, East Wing — Pediatric'),(381,17,'P16','double',2,'Third Floor, East Wing — Pediatric'),(382,17,'P26','double',2,'Third Floor, East Wing — Pediatric'),(383,17,'P7','double',2,'Third Floor, East Wing — Pediatric'),(384,17,'P17','double',2,'Third Floor, East Wing — Pediatric'),(385,17,'P27','double',2,'Third Floor, East Wing — Pediatric'),(386,17,'P8','double',2,'Third Floor, East Wing — Pediatric'),(387,17,'P18','double',2,'Third Floor, East Wing — Pediatric'),(388,17,'P28','double',2,'Third Floor, East Wing — Pediatric'),(389,17,'P9','double',2,'Third Floor, East Wing — Pediatric'),(390,17,'P19','double',2,'Third Floor, East Wing — Pediatric'),(391,17,'P29','double',2,'Third Floor, East Wing — Pediatric'),(392,17,'P10','double',2,'Third Floor, East Wing — Pediatric'),(393,17,'P20','double',2,'Third Floor, East Wing — Pediatric'),(394,17,'P30','double',2,'Third Floor, East Wing — Pediatric'),(426,18,'S1','ward',4,'Third Floor, West Wing — Surgical'),(427,18,'S11','ward',4,'Third Floor, West Wing — Surgical'),(428,18,'S21','ward',4,'Third Floor, West Wing — Surgical'),(429,18,'S31','ward',4,'Third Floor, West Wing — Surgical'),(430,18,'S2','ward',4,'Third Floor, West Wing — Surgical'),(431,18,'S12','ward',4,'Third Floor, West Wing — Surgical'),(432,18,'S22','ward',4,'Third Floor, West Wing — Surgical'),(433,18,'S32','ward',4,'Third Floor, West Wing — Surgical'),(434,18,'S3','ward',4,'Third Floor, West Wing — Surgical'),(435,18,'S13','ward',4,'Third Floor, West Wing — Surgical'),(436,18,'S23','ward',4,'Third Floor, West Wing — Surgical'),(437,18,'S33','ward',4,'Third Floor, West Wing — Surgical'),(438,18,'S4','ward',4,'Third Floor, West Wing — Surgical'),(439,18,'S14','ward',4,'Third Floor, West Wing — Surgical'),(440,18,'S24','ward',4,'Third Floor, West Wing — Surgical'),(441,18,'S34','ward',4,'Third Floor, West Wing — Surgical'),(442,18,'S5','ward',4,'Third Floor, West Wing — Surgical'),(443,18,'S15','ward',4,'Third Floor, West Wing — Surgical'),(444,18,'S25','ward',4,'Third Floor, West Wing — Surgical'),(445,18,'S35','ward',4,'Third Floor, West Wing — Surgical'),(446,18,'S6','ward',4,'Third Floor, West Wing — Surgical'),(447,18,'S16','ward',4,'Third Floor, West Wing — Surgical'),(448,18,'S26','ward',4,'Third Floor, West Wing — Surgical'),(449,18,'S36','ward',4,'Third Floor, West Wing — Surgical'),(450,18,'S7','ward',4,'Third Floor, West Wing — Surgical'),(451,18,'S17','ward',4,'Third Floor, West Wing — Surgical'),(452,18,'S27','ward',4,'Third Floor, West Wing — Surgical'),(453,18,'S37','ward',4,'Third Floor, West Wing — Surgical'),(454,18,'S8','ward',4,'Third Floor, West Wing — Surgical'),(455,18,'S18','ward',4,'Third Floor, West Wing — Surgical'),(456,18,'S28','ward',4,'Third Floor, West Wing — Surgical'),(457,18,'S38','ward',4,'Third Floor, West Wing — Surgical'),(458,18,'S9','ward',4,'Third Floor, West Wing — Surgical'),(459,18,'S19','ward',4,'Third Floor, West Wing — Surgical'),(460,18,'S29','ward',4,'Third Floor, West Wing — Surgical'),(461,18,'S39','ward',4,'Third Floor, West Wing — Surgical'),(462,18,'S10','ward',4,'Third Floor, West Wing — Surgical'),(463,18,'S20','ward',4,'Third Floor, West Wing — Surgical'),(464,18,'S30','ward',4,'Third Floor, West Wing — Surgical'),(465,18,'S40','ward',4,'Third Floor, West Wing — Surgical'),(489,19,'O1','ward',4,'Fourth Floor, East Wing — Orthopedic'),(490,19,'O11','ward',4,'Fourth Floor, East Wing — Orthopedic'),(491,19,'O21','ward',4,'Fourth Floor, East Wing — Orthopedic'),(492,19,'O2','ward',4,'Fourth Floor, East Wing — Orthopedic'),(493,19,'O12','ward',4,'Fourth Floor, East Wing — Orthopedic'),(494,19,'O22','ward',4,'Fourth Floor, East Wing — Orthopedic'),(495,19,'O3','ward',4,'Fourth Floor, East Wing — Orthopedic'),(496,19,'O13','ward',4,'Fourth Floor, East Wing — Orthopedic'),(497,19,'O23','ward',4,'Fourth Floor, East Wing — Orthopedic'),(498,19,'O4','ward',4,'Fourth Floor, East Wing — Orthopedic'),(499,19,'O14','ward',4,'Fourth Floor, East Wing — Orthopedic'),(500,19,'O24','ward',4,'Fourth Floor, East Wing — Orthopedic'),(501,19,'O5','ward',4,'Fourth Floor, East Wing — Orthopedic'),(502,19,'O15','ward',4,'Fourth Floor, East Wing — Orthopedic'),(503,19,'O6','ward',4,'Fourth Floor, East Wing — Orthopedic'),(504,19,'O16','ward',4,'Fourth Floor, East Wing — Orthopedic'),(505,19,'O7','ward',4,'Fourth Floor, East Wing — Orthopedic'),(506,19,'O17','ward',4,'Fourth Floor, East Wing — Orthopedic'),(507,19,'O8','ward',4,'Fourth Floor, East Wing — Orthopedic'),(508,19,'O18','ward',4,'Fourth Floor, East Wing — Orthopedic'),(509,19,'O9','ward',4,'Fourth Floor, East Wing — Orthopedic'),(510,19,'O19','ward',4,'Fourth Floor, East Wing — Orthopedic'),(511,19,'O10','ward',4,'Fourth Floor, East Wing — Orthopedic'),(512,19,'O20','ward',4,'Fourth Floor, East Wing — Orthopedic'),(520,20,'C1','single',1,'Fourth Floor, West Wing — Cardiac Care'),(521,20,'C11','single',1,'Fourth Floor, West Wing — Cardiac Care'),(522,20,'C21','single',1,'Fourth Floor, West Wing — Cardiac Care'),(523,20,'C31','single',1,'Fourth Floor, West Wing — Cardiac Care'),(524,20,'C2','single',1,'Fourth Floor, West Wing — Cardiac Care'),(525,20,'C12','single',1,'Fourth Floor, West Wing — Cardiac Care'),(526,20,'C22','single',1,'Fourth Floor, West Wing — Cardiac Care'),(527,20,'C32','single',1,'Fourth Floor, West Wing — Cardiac Care'),(528,20,'C3','single',1,'Fourth Floor, West Wing — Cardiac Care'),(529,20,'C13','single',1,'Fourth Floor, West Wing — Cardiac Care'),(530,20,'C23','single',1,'Fourth Floor, West Wing — Cardiac Care'),(531,20,'C4','single',1,'Fourth Floor, West Wing — Cardiac Care'),(532,20,'C14','single',1,'Fourth Floor, West Wing — Cardiac Care'),(533,20,'C24','single',1,'Fourth Floor, West Wing — Cardiac Care'),(534,20,'C5','single',1,'Fourth Floor, West Wing — Cardiac Care'),(535,20,'C15','single',1,'Fourth Floor, West Wing — Cardiac Care'),(536,20,'C25','single',1,'Fourth Floor, West Wing — Cardiac Care'),(537,20,'C6','single',1,'Fourth Floor, West Wing — Cardiac Care'),(538,20,'C16','single',1,'Fourth Floor, West Wing — Cardiac Care'),(539,20,'C26','single',1,'Fourth Floor, West Wing — Cardiac Care'),(540,20,'C7','single',1,'Fourth Floor, West Wing — Cardiac Care'),(541,20,'C17','single',1,'Fourth Floor, West Wing — Cardiac Care'),(542,20,'C27','single',1,'Fourth Floor, West Wing — Cardiac Care'),(543,20,'C8','single',1,'Fourth Floor, West Wing — Cardiac Care'),(544,20,'C18','single',1,'Fourth Floor, West Wing — Cardiac Care'),(545,20,'C28','single',1,'Fourth Floor, West Wing — Cardiac Care'),(546,20,'C9','single',1,'Fourth Floor, West Wing — Cardiac Care'),(547,20,'C19','single',1,'Fourth Floor, West Wing — Cardiac Care'),(548,20,'C29','single',1,'Fourth Floor, West Wing — Cardiac Care'),(549,20,'C10','single',1,'Fourth Floor, West Wing — Cardiac Care'),(550,20,'C20','single',1,'Fourth Floor, West Wing — Cardiac Care'),(551,20,'C30','single',1,'Fourth Floor, West Wing — Cardiac Care'),(583,21,'E1','ward',2,'Ground Floor — Emergency'),(584,21,'E11','ward',2,'Ground Floor — Emergency'),(585,21,'E2','ward',2,'Ground Floor — Emergency'),(586,21,'E12','ward',2,'Ground Floor — Emergency'),(587,21,'E3','ward',2,'Ground Floor — Emergency'),(588,21,'E13','ward',2,'Ground Floor — Emergency'),(589,21,'E4','ward',2,'Ground Floor — Emergency'),(590,21,'E14','ward',2,'Ground Floor — Emergency'),(591,21,'E5','ward',2,'Ground Floor — Emergency'),(592,21,'E15','ward',2,'Ground Floor — Emergency'),(593,21,'E6','ward',2,'Ground Floor — Emergency'),(594,21,'E16','ward',2,'Ground Floor — Emergency'),(595,21,'E7','ward',2,'Ground Floor — Emergency'),(596,21,'E17','ward',2,'Ground Floor — Emergency'),(597,21,'E8','ward',2,'Ground Floor — Emergency'),(598,21,'E18','ward',2,'Ground Floor — Emergency'),(599,21,'E9','ward',2,'Ground Floor — Emergency'),(600,21,'E19','ward',2,'Ground Floor — Emergency'),(601,21,'E10','ward',2,'Ground Floor — Emergency'),(602,21,'E20','ward',2,'Ground Floor — Emergency'),(614,22,'PS1','single',1,'Fifth Floor, East Wing — Psychiatric'),(615,22,'PS11','single',1,'Fifth Floor, East Wing — Psychiatric'),(616,22,'PS2','single',1,'Fifth Floor, East Wing — Psychiatric'),(617,22,'PS12','single',1,'Fifth Floor, East Wing — Psychiatric'),(618,22,'PS3','single',1,'Fifth Floor, East Wing — Psychiatric'),(619,22,'PS13','single',1,'Fifth Floor, East Wing — Psychiatric'),(620,22,'PS4','single',1,'Fifth Floor, East Wing — Psychiatric'),(621,22,'PS14','single',1,'Fifth Floor, East Wing — Psychiatric'),(622,22,'PS5','single',1,'Fifth Floor, East Wing — Psychiatric'),(623,22,'PS15','single',1,'Fifth Floor, East Wing — Psychiatric'),(624,22,'PS6','single',1,'Fifth Floor, East Wing — Psychiatric'),(625,22,'PS16','single',1,'Fifth Floor, East Wing — Psychiatric'),(626,22,'PS7','single',1,'Fifth Floor, East Wing — Psychiatric'),(627,22,'PS8','single',1,'Fifth Floor, East Wing — Psychiatric'),(628,22,'PS9','single',1,'Fifth Floor, East Wing — Psychiatric'),(629,22,'PS10','single',1,'Fifth Floor, East Wing — Psychiatric'),(645,23,'ON1','single',1,'Fifth Floor, West Wing — Oncology'),(646,23,'ON11','single',1,'Fifth Floor, West Wing — Oncology'),(647,23,'ON2','single',1,'Fifth Floor, West Wing — Oncology'),(648,23,'ON12','single',1,'Fifth Floor, West Wing — Oncology'),(649,23,'ON3','single',1,'Fifth Floor, West Wing — Oncology'),(650,23,'ON4','single',1,'Fifth Floor, West Wing — Oncology'),(651,23,'ON5','single',1,'Fifth Floor, West Wing — Oncology'),(652,23,'ON6','single',1,'Fifth Floor, West Wing — Oncology'),(653,23,'ON7','single',1,'Fifth Floor, West Wing — Oncology'),(654,23,'ON8','single',1,'Fifth Floor, West Wing — Oncology'),(655,23,'ON9','single',1,'Fifth Floor, West Wing — Oncology'),(656,23,'ON10','single',1,'Fifth Floor, West Wing — Oncology'),(660,24,'IS1','single',1,'Fifth Floor, North Wing — Isolation'),(661,24,'IS2','single',1,'Fifth Floor, North Wing — Isolation'),(662,24,'IS3','single',1,'Fifth Floor, North Wing — Isolation'),(663,24,'IS4','single',1,'Fifth Floor, North Wing — Isolation'),(664,24,'IS5','single',1,'Fifth Floor, North Wing — Isolation'),(665,24,'IS6','single',1,'Fifth Floor, North Wing — Isolation'),(666,24,'IS7','single',1,'Fifth Floor, North Wing — Isolation'),(667,24,'IS8','single',1,'Fifth Floor, North Wing — Isolation');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `specialties`
--

DROP TABLE IF EXISTS `specialties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `specialties` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `specialties`
--

LOCK TABLES `specialties` WRITE;
/*!40000 ALTER TABLE `specialties` DISABLE KEYS */;
/*!40000 ALTER TABLE `specialties` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contact_info`)),
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(50) DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,2,'Sarah','Johnson','matiasmasih@gmail.com',NULL,'$2y$10$kMsN72mxaHt6zm0Y/Opr.OtdfkY65U7CKseLoJ7eQj35Nx150Lra2',1,NULL,'2025-10-08 15:54:21','2026-09-23 16:39:13','active'),(2,1,'Michael','Chen','aziz.noyan@outlook.com',NULL,'$2y$10$g/ydqux1qI2Zcb9hnYWbGOrO4QQ4M6u73x9m5dZSiWboDkam696va',1,NULL,'2025-10-08 16:54:38','2026-09-23 16:39:59','active'),(3,3,'Aziz Rahman','Noyan','aziznoyan50@gmail.com',NULL,'$2y$10$fkmT3UYUJMJJO02GEdkYAOVwpsCivYDLOaQgLUZXgDRdS0wIXddFu',1,NULL,'2025-11-09 15:12:19','2026-09-23 16:40:08','active'),(4,4,'Emma','Wilson','aziz.noyan1994@gmail.com',NULL,'$2y$10$2dhkF/rRUTaWLFj.pGJmre/8Nyqoe1VpTlix3oxqy.uwKp61GwqX6',1,NULL,'2026-04-20 15:26:37','2026-09-23 16:40:18','active'),(5,6,'Alice','Smith','alice@patient.com','0413114312','$2y$10$TBPKGSZN4paTfqNHZjYynum1RDYvq3ym3V9BVCx/u//YXtOEgsFUe',1,NULL,'2026-09-14 12:34:55','2026-09-23 16:42:03','active'),(6,6,'Aziz','Rahman','aziz.rahman@test.com',NULL,'$2y$10$TBPKGSZN4paTfqNHZjYynum1RDYvq3ym3V9BVCx/u//YXtOEgsFUe',1,NULL,'2026-09-17 16:18:44','2026-09-23 16:42:03','active'),(7,6,'Maria','Garcia','maria.garcia@test.com','0451234567','$2y$10$TBPKGSZN4paTfqNHZjYynum1RDYvq3ym3V9BVCx/u//YXtOEgsFUe',1,NULL,'2026-09-23 15:58:23','2026-09-23 16:42:03','active'),(8,6,'John','Doe','john.doe@test.com','0441234567','$2y$10$TBPKGSZN4paTfqNHZjYynum1RDYvq3ym3V9BVCx/u//YXtOEgsFUe',1,NULL,'2026-09-23 15:58:23','2026-09-23 16:42:03','active'),(9,6,'Sophia','Nguyen','sophia.nguyen@test.com','0461234567','$2y$10$TBPKGSZN4paTfqNHZjYynum1RDYvq3ym3V9BVCx/u//YXtOEgsFUe',1,NULL,'2026-09-23 15:58:23','2026-09-23 16:42:03','active');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wards`
--

DROP TABLE IF EXISTS `wards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `wards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `type` enum('general','icu','maternity','pediatric','surgical','orthopedic','cardiac','emergency','psychiatric','isolation','other') DEFAULT 'general',
  `capacity` int(11) DEFAULT 0,
  `status` enum('active','inactive','maintenance','full') DEFAULT 'active',
  `charge_per_day` decimal(10,2) DEFAULT 0.00,
  `phone_extension` varchar(20) DEFAULT NULL,
  `in_charge_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `in_charge_id` (`in_charge_id`),
  CONSTRAINT `wards_ibfk_1` FOREIGN KEY (`in_charge_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wards`
--

LOCK TABLES `wards` WRITE;
/*!40000 ALTER TABLE `wards` DISABLE KEYS */;
INSERT INTO `wards` VALUES (13,'General Ward - A','First Floor, East Wing','General patient care ward with 20 beds','2025-10-19 15:22:16','general',20,'active',50.00,'101',4),(14,'General Ward - B','First Floor, West Wing','General patient care for post-operative patients','2025-10-19 15:22:16','general',20,'active',50.00,'102',1),(15,'ICU - Intensive Care Unit','Second Floor, North Wing','Critical care unit with monitoring equipment','2025-10-19 15:22:16','icu',10,'active',200.00,'201',1),(16,'Maternity Ward','Third Floor, South Wing','Maternity and newborn care with private rooms','2025-10-19 15:22:16','maternity',15,'active',80.00,'301',4),(17,'Pediatric Ward','Second Floor, East Wing','Children and adolescent care unit','2025-10-19 15:22:16','pediatric',18,'active',60.00,'202',NULL),(18,'Surgical Ward','First Floor, Central Wing','Pre and post-operative surgical care','2025-10-19 15:22:16','surgical',16,'active',75.00,'103',NULL),(19,'Orthopedic Ward','Second Floor, West Wing','Bone and joint injury treatment','2025-10-19 15:22:16','orthopedic',15,'active',70.00,'203',NULL),(20,'Cardiac Care Unit','Third Floor, North Wing','Heart disease and cardiac monitoring','2025-10-19 15:22:16','cardiac',12,'active',150.00,'302',1),(21,'Emergency Ward','Ground Floor, Main Building','24/7 emergency care and triage','2025-10-19 15:22:18','emergency',15,'active',100.00,'001',4),(22,'Psychiatric Ward','Fourth Floor, East Wing','Mental health and psychiatric care','2025-10-19 15:22:18','psychiatric',20,'active',65.00,'401',NULL),(23,'Oncology Ward','Third Floor, West Wing','Cancer treatment and chemotherapy','2025-10-19 15:22:18','other',15,'active',90.00,'303',NULL),(24,'Isolation Ward','Fourth Floor, North Wing','Infectious disease isolation unit','2025-10-19 15:22:18','isolation',8,'active',90.00,'402',NULL);
/*!40000 ALTER TABLE `wards` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-02 12:13:48
