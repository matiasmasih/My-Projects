-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: hospital_management
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.3

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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned NOT NULL,
  `admitted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `discharged_at` datetime DEFAULT NULL,
  `ward_id` int unsigned DEFAULT NULL,
  `room_id` int unsigned DEFAULT NULL,
  `bed_id` int unsigned DEFAULT NULL,
  `admitting_doctor_id` int unsigned DEFAULT NULL,
  `admitted_by` int unsigned DEFAULT NULL,
  `status` enum('admitted','discharged','transferred') COLLATE utf8mb4_unicode_ci DEFAULT 'admitted',
  `discharge_summary` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
INSERT INTO `admissions` VALUES (2,3,'2025-10-22 13:18:31',NULL,14,NULL,NULL,3,4,'admitted',NULL,'2025-10-22 10:18:31','2025-10-22 10:18:31'),(3,2,'2025-10-22 13:18:31',NULL,15,NULL,NULL,3,1,'discharged','Patient recovered well and discharged with follow-up instructions','2025-10-22 10:18:31','2025-10-22 10:18:31');
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned NOT NULL,
  `doctor_id` int unsigned NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `duration_minutes` smallint unsigned DEFAULT '30',
  `status` enum('scheduled','confirmed','checked_in','in_progress','completed','cancelled','no_show') COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `created_by` (`created_by`),
  KEY `scheduled_at` (`scheduled_at`),
  KEY `idx_appointment_doctor_time` (`doctor_id`,`scheduled_at`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (18,2,1,'2025-10-15 15:00:00',30,'in_progress','Blood test review',4,NULL,'2025-10-14 17:27:27','2025-10-14 17:27:27'),(19,2,3,'2025-10-20 12:00:00',20,'confirmed','it is complete',4,NULL,'2025-10-14 18:00:25','2025-10-14 18:00:25'),(20,3,1,'2025-10-19 15:00:00',40,'completed','Blood test review',4,NULL,'2025-10-15 13:45:19','2025-10-15 13:45:19'),(21,2,1,'2025-10-21 16:00:00',30,'in_progress','Blood test review',4,NULL,'2025-10-15 15:26:29','2025-10-15 15:26:29');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beds`
--

DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `beds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int unsigned NOT NULL,
  `bed_label` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('free','occupied','maintenance') COLLATE utf8mb4_unicode_ci DEFAULT 'free',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedules` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `doctor_id` int unsigned NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `slot_minutes` smallint unsigned DEFAULT '30',
  `is_available` tinyint(1) DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_specialties` (
  `doctor_id` int unsigned NOT NULL,
  `specialty_id` int unsigned NOT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `consultation_fee` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive','on_leave','terminated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `license_number` (`license_number`),
  KEY `idx_doctor_license` (`license_number`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (1,1,'LIC12345','Experienced general practitioner',50.00,'2025-10-11 14:36:16','2025-10-11 14:36:16','active'),(3,4,'LB31126489','',20.00,'2025-10-11 15:03:14','2025-10-11 15:03:14','active');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `files`
--

DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `related_table` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_id` int unsigned DEFAULT NULL,
  `file_path` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `uploaded_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_providers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_info` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` enum('service','medicine','room','other') COLLATE utf8mb4_unicode_ci DEFAULT 'service',
  `reference_id` int unsigned DEFAULT NULL,
  `quantity` int DEFAULT '1',
  `unit_price` decimal(12,2) DEFAULT '0.00',
  `total_price` decimal(12,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int DEFAULT NULL,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_id` int unsigned DEFAULT NULL,
  `issued_by` int unsigned DEFAULT NULL,
  `issued_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `due_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT '0.00',
  `status` enum('draft','issued','partially_paid','paid','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `patient_id` (`patient_id`),
  KEY `issued_by` (`issued_by`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (24,18,'INV-2025-0018',2,4,'2025-10-14 17:27:37','2025-10-21',50.00,'draft','Invoice for appointment: Blood test review','2025-10-14 17:27:37','2025-10-14 17:27:37'),(25,19,'INV-2025-0019',2,4,'2025-10-14 18:00:27','2025-10-21',33.33,'draft','Invoice for appointment: it is complete','2025-10-14 18:00:27','2025-10-14 18:00:27'),(26,20,'INV-2025-0020',3,4,'2025-10-15 13:45:27','2025-10-22',66.67,'draft','Invoice for appointment: Blood test review','2025-10-15 13:45:27','2025-10-15 13:45:27'),(27,21,'INV-2025-0021',2,4,'2025-10-15 15:26:35','2025-10-22',50.00,'draft','Invoice for appointment: Blood test review','2025-10-15 15:26:35','2025-10-15 15:26:35'),(28,NULL,'DIRECT-20251015-134653-789',2,4,'2025-10-15 13:46:53','2025-10-15',20.00,'paid','tomorrow she will pay the payments with Bank Transfering','2025-10-15 16:46:53','2025-10-15 16:46:53');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_records`
--

DROP TABLE IF EXISTS `medical_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned NOT NULL,
  `appointment_id` int unsigned DEFAULT NULL,
  `doctor_id` int unsigned DEFAULT NULL,
  `visit_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `visit_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `diagnosis` text COLLATE utf8mb4_unicode_ci,
  `treatment` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_records`
--

LOCK TABLES `medical_records` WRITE;
/*!40000 ALTER TABLE `medical_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `medical_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicine_batches`
--

DROP TABLE IF EXISTS `medicine_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicine_batches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `medicine_id` int unsigned NOT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT '0.00',
  `selling_price` decimal(10,2) DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicine_id` (`medicine_id`,`batch_number`),
  KEY `idx_pharmacy_batch_expiry` (`expiry_date`),
  CONSTRAINT `medicine_batches_ibfk_1` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicine_batches`
--

LOCK TABLES `medicine_batches` WRITE;
/*!40000 ALTER TABLE `medicine_batches` DISABLE KEYS */;
INSERT INTO `medicine_batches` VALUES (1,1,'BATCH-PARA-001','2025-12-31',2.50,5.00,'2025-10-15 17:14:46'),(2,2,'BATCH-AMOX-001','2025-11-30',3.00,6.00,'2025-10-15 17:14:46'),(3,3,'BATCH-VENT-001','2026-03-31',8.00,15.00,'2025-10-15 17:14:46'),(5,4,'BATCH-IBU-001-NEW','2025-12-31',3.00,7.00,'2025-10-16 15:52:55'),(6,5,'BATCH-VITA-001','2027-10-17',3.00,5.00,'2025-10-17 13:35:17');
/*!40000 ALTER TABLE `medicine_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicines`
--

DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `strength` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'tablet',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`brand`,`strength`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicines`
--

LOCK TABLES `medicines` WRITE;
/*!40000 ALTER TABLE `medicines` DISABLE KEYS */;
INSERT INTO `medicines` VALUES (1,'Paracetamol 500mg','Acetaminophen','PharmaCorp','Tablet','500mg','tablet','Pain reliever and fever reducer','2025-10-15 17:14:24','2025-10-16 16:17:36'),(2,'Amoxicillin 250mg','Amoxicillin','MediLife','Capsule','250mg','capsule','Antibiotic for bacterial infections','2025-10-15 17:14:24','2025-10-16 16:08:59'),(3,'Ventolin Inhaler','Salbutamol','GSK','Inhaler','100mcg','puff','Bronchodilator for asthma','2025-10-15 17:14:24','2025-10-17 13:59:00'),(4,'Ibuprofen 400mg','Ibuprofen','PainFree Inc','Tablet','400mg','tablet','Anti-inflammatory pain reliever','2025-10-15 17:14:24','2025-10-15 17:14:24'),(5,'Vitamin C 1000mg','Ascorbic Acid','HealthPlus','Tablet','1000mg','tablet','Immune system support and antioxidant','2025-10-17 13:35:17','2025-10-18 14:07:39');
/*!40000 ALTER TABLE `medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned DEFAULT NULL,
  `conversation_id` int unsigned DEFAULT NULL,
  `sender_id` int unsigned NOT NULL,
  `recipient_id` int NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `status` enum('unread','read') COLLATE utf8mb4_unicode_ci DEFAULT 'unread',
  `is_deleted` tinyint DEFAULT '0',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `read_at` timestamp NULL DEFAULT NULL,
  `recipient_type` enum('user','admin','manager') COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (19,NULL,NULL,1,0,'Budget Approval Needed','Need your approval for the Q4 budget allocation.','urgent','read',0,0,'2025-10-25 15:01:29',NULL,'admin'),(20,NULL,NULL,4,0,'System Maintenance','System will be down for maintenance this weekend.','high','read',0,0,'2025-10-25 15:01:52',NULL,'manager'),(21,NULL,NULL,1,0,'Budget Approval Needed','Need your approval for the Q4 budget allocation.','urgent','read',0,0,'2025-10-25 15:02:12',NULL,'admin'),(22,NULL,NULL,4,0,'System Maintenance','System will be down for maintenance this weekend.','high','read',0,0,'2025-10-25 15:02:28',NULL,'manager'),(23,NULL,NULL,4,0,'Re: Emergency Case','Check this Emergency Case','normal','unread',0,0,'2025-11-19 12:24:05',NULL,'admin');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_insurances` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned NOT NULL,
  `provider_id` int unsigned NOT NULL,
  `policy_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_to` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other','unspecified') COLLATE utf8mb4_unicode_ci DEFAULT 'unspecified',
  `national_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `emergency_contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','deceased','transferred') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `medical_record_number` (`medical_record_number`),
  KEY `idx_patient_name` (`last_name`,`first_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (2,'MRN1002','Alice','Smith','2025-10-03','female','987654321','Vaahtokuja 5 E50','0413114312','matiasmasih@gmail.com','2025-10-11 13:50:33','2025-10-11 14:01:04','Aziz','0413114312','active'),(3,'MRN1003','Matias','Noyan','2025-10-06','male','987654322','Vaahtokuja 5 E50','0413114312','matiasmasih@gmail.com','2025-10-14 15:24:28','2025-10-14 15:24:28',NULL,NULL,'active');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','card','insurance','bank_transfer','other') COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `transaction_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_payments_dates` (`paid_at`,`status`),
  KEY `idx_payments_invoice_status` (`invoice_id`,`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (3,24,50.00,'cash',NULL,'4','2025-10-14 00:00:00','2025-10-14 17:58:58','paid',''),(4,25,40.00,'cash',NULL,'4','2025-10-15 00:00:00','2025-10-14 18:12:08','paid',''),(5,26,60.00,'cash',NULL,'4','2025-10-15 16:00:16','2025-10-15 13:46:51','paid',''),(6,27,50.00,'cash',NULL,'4','2025-10-15 16:00:16','2025-10-15 15:37:04','paid',''),(7,27,50.00,'card',NULL,'4','2025-10-15 00:00:00','2025-10-15 15:45:13','paid','payments completed with card'),(8,28,20.00,'bank_transfer',NULL,'4','2025-10-16 00:00:00','2025-10-15 16:46:53','pending','tomorrow she will pay the payments with Bank Transfering');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacy_stock`
--

DROP TABLE IF EXISTS `pharmacy_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pharmacy_stock` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `medicine_batch_id` int unsigned NOT NULL,
  `quantity` int DEFAULT '0',
  `min_threshold` int DEFAULT '0',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'main_pharmacy',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medicine_batch_id` (`medicine_batch_id`),
  CONSTRAINT `pharmacy_stock_ibfk_1` FOREIGN KEY (`medicine_batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacy_stock`
--

LOCK TABLES `pharmacy_stock` WRITE;
/*!40000 ALTER TABLE `pharmacy_stock` DISABLE KEYS */;
INSERT INTO `pharmacy_stock` VALUES (1,1,150,20,'main_pharmacy','2025-10-16 16:17:36'),(2,2,75,15,'main_pharmacy','2025-10-16 16:08:59'),(3,3,45,10,'main_pharmacy','2025-10-16 16:17:19'),(5,5,100,15,'main_pharmacy','2025-10-16 15:52:55'),(6,6,50,10,'main_pharmacy','2025-10-18 14:07:39');
/*!40000 ALTER TABLE `pharmacy_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription_items`
--

DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` int unsigned NOT NULL,
  `medicine_id` int unsigned NOT NULL,
  `batch_id` int unsigned DEFAULT NULL,
  `dosage` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT '1',
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `dispensed` tinyint(1) DEFAULT '0',
  `dispensed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `medicine_id` (`medicine_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `prescription_items_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `medicine_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription_items`
--

LOCK TABLES `prescription_items` WRITE;
/*!40000 ALTER TABLE `prescription_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `prescription_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `medical_record_id` int unsigned DEFAULT NULL,
  `appointment_id` int unsigned DEFAULT NULL,
  `prescribed_by` int unsigned DEFAULT NULL,
  `prescribed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `medical_record_id` (`medical_record_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `prescribed_by` (`prescribed_by`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `prescriptions_ibfk_3` FOREIGN KEY (`prescribed_by`) REFERENCES `doctors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rooms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ward_id` int unsigned DEFAULT NULL,
  `room_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type` enum('single','double','icu','ward','other') COLLATE utf8mb4_unicode_ci DEFAULT 'ward',
  `capacity` smallint unsigned DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `ward_id` (`ward_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (9,20,'1','single',1,'Third Floor, North Wing');
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `specialties`
--

DROP TABLE IF EXISTS `specialties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `specialties` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_info` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,2,'Aziz ','Noyan','matiasmasih@gmail.com',NULL,'$2y$10$LoURuQTdfNbPliQQ5PQOIeEBXMHvMuigWm/cJEiT7sLFLzXg5v.cK',1,NULL,'2025-10-08 15:54:21','2025-10-08 15:54:21','active'),(4,1,'Matias','Masih','aziz.noyan@outlook.com',NULL,'$2y$10$gFyJ2g9UEBrqIOFoz.HYCeVss6fAz5kOBkKkP6Op5.MXDxLU4mznG',1,NULL,'2025-10-08 16:54:38','2025-10-08 16:54:38','active'),(6,4,'Aziz Rahman','Noyan','aziznoyan50@gmail.com',NULL,'$2y$10$g/TVh7JfFHmg6l7wKs9ez.V2PwWc3c6kVcK4Fw8EBKpXZxlCYwiwS',1,NULL,'2025-11-09 15:12:19','2025-11-09 15:12:19','active'),(7,6,'Aziz Rahman','Noyan','aziz.noyan1994@gmail.com',NULL,'$2y$10$bDhLAa1eV9aefKIgPcS38uLvfV.WghNlnguBhV0vQxDUqAyX4kTiy',1,NULL,'2026-04-20 15:26:37','2026-04-20 15:26:37','active');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wards`
--

DROP TABLE IF EXISTS `wards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `type` enum('general','icu','maternity','pediatric','surgical','orthopedic','cardiac','emergency','psychiatric','isolation','other') COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `capacity` int DEFAULT '0',
  `status` enum('active','inactive','maintenance','full') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `charge_per_day` decimal(10,2) DEFAULT '0.00',
  `phone_extension` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `in_charge_id` int unsigned DEFAULT NULL,
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

-- Dump completed on 2026-06-24 15:44:38
