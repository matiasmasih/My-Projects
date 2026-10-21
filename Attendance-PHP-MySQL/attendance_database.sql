/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: attendance
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
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `total_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('present','absent','late','early-leave','half-day','holiday','sick','vacation') NOT NULL DEFAULT 'present',
  `notes` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_emp_date` (`employee_id`,`date`),
  KEY `idx_date` (`date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (5,1,'2026-10-06','2026-10-06 07:58:00','2026-10-06 16:02:00',484,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(6,2,'2026-10-06','2026-10-06 08:00:00','2026-10-06 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(7,3,'2026-10-06','2026-10-06 16:05:00','2026-10-06 23:58:00',473,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(8,4,'2026-10-06','2026-10-06 08:01:00','2026-10-06 16:03:00',482,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(9,1,'2026-10-07','2026-10-07 08:00:00','2026-10-07 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(10,2,'2026-10-07','2026-10-07 08:25:00','2026-10-07 16:05:00',460,'late','Traffic delay','2026-10-20 14:37:44','2026-10-20 14:37:44'),(11,3,'2026-10-07','2026-10-07 16:00:00','2026-10-07 23:55:00',475,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(12,4,'2026-10-07','2026-10-07 08:00:00','2026-10-07 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(13,1,'2026-10-08','2026-10-08 07:59:00','2026-10-08 16:01:00',482,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(14,2,'2026-10-08',NULL,NULL,0,'absent','Sick leave','2026-10-20 14:37:44','2026-10-20 14:37:44'),(15,3,'2026-10-08','2026-10-08 16:02:00','2026-10-08 23:58:00',476,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(16,4,'2026-10-08','2026-10-08 08:00:00','2026-10-08 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(17,1,'2026-10-09','2026-10-09 08:00:00','2026-10-09 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(18,2,'2026-10-09','2026-10-09 08:00:00','2026-10-09 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(19,3,'2026-10-09','2026-10-09 16:00:00','2026-10-09 23:59:00',479,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(20,4,'2026-10-09','2026-10-09 08:00:00','2026-10-09 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(21,1,'2026-10-10','2026-10-10 08:00:00','2026-10-10 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(22,2,'2026-10-10','2026-10-10 08:15:00','2026-10-10 16:00:00',465,'late','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(23,3,'2026-10-10','2026-10-10 16:20:00','2026-10-10 23:55:00',455,'late','Late start','2026-10-20 14:37:44','2026-10-20 14:37:44'),(24,4,'2026-10-10','2026-10-10 08:00:00','2026-10-10 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(25,1,'2026-10-13','2026-10-13 07:58:00','2026-10-13 16:02:00',484,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(26,2,'2026-10-13','2026-10-13 08:00:00','2026-10-13 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(27,3,'2026-10-13','2026-10-13 16:00:00','2026-10-13 23:58:00',478,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(28,4,'2026-10-13','2026-10-13 08:02:00','2026-10-13 16:01:00',479,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(29,1,'2026-10-14','2026-10-14 08:00:00','2026-10-14 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(30,2,'2026-10-14','2026-10-14 08:00:00','2026-10-14 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(31,3,'2026-10-14',NULL,NULL,0,'absent','Family emergency','2026-10-20 14:37:44','2026-10-20 14:37:44'),(32,4,'2026-10-14','2026-10-14 08:00:00','2026-10-14 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(33,1,'2026-10-15','2026-10-15 08:00:00','2026-10-15 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(34,2,'2026-10-15','2026-10-15 08:00:00','2026-10-15 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(35,3,'2026-10-15','2026-10-15 16:00:00','2026-10-15 23:59:00',479,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(36,4,'2026-10-15','2026-10-15 07:59:00','2026-10-15 16:01:00',482,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(37,1,'2026-10-16','2026-10-16 08:00:00','2026-10-16 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(38,2,'2026-10-16','2026-10-16 08:00:00','2026-10-16 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(39,3,'2026-10-16','2026-10-16 16:10:00','2026-10-16 23:58:00',468,'late','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(40,4,'2026-10-16','2026-10-16 08:00:00','2026-10-16 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(41,1,'2026-10-17','2026-10-17 08:00:00','2026-10-17 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(42,2,'2026-10-17','2026-10-17 07:58:00','2026-10-17 16:03:00',485,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(43,3,'2026-10-17','2026-10-17 16:00:00','2026-10-17 23:55:00',475,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(44,4,'2026-10-17','2026-10-17 08:00:00','2026-10-17 16:00:00',480,'present','','2026-10-20 14:37:44','2026-10-20 14:37:44'),(45,1,'2026-10-19','2026-10-19 08:00:00','2026-10-19 16:00:00',480,'present','','2026-10-20 14:37:46','2026-10-20 14:37:46'),(46,2,'2026-10-19',NULL,NULL,0,'absent','Personal day','2026-10-20 14:37:46','2026-10-20 14:37:46'),(47,3,'2026-10-19','2026-10-19 16:00:00','2026-10-19 23:59:00',479,'present','','2026-10-20 14:37:46','2026-10-20 14:37:46'),(48,4,'2026-10-19','2026-10-19 08:00:00','2026-10-19 16:00:00',480,'present','','2026-10-20 14:37:46','2026-10-20 14:37:46'),(49,1,'2026-10-20',NULL,NULL,0,'absent','Auto-marked as absent — no clock-in','2026-10-20 15:13:43','2026-10-20 15:13:43'),(50,2,'2026-10-20',NULL,NULL,0,'absent','Auto-marked as absent — no clock-in','2026-10-20 15:13:43','2026-10-20 15:13:43'),(51,4,'2026-10-20',NULL,NULL,0,'absent','Auto-marked as absent — no clock-in','2026-10-20 15:13:43','2026-10-20 15:13:43'),(52,3,'2026-10-20',NULL,NULL,0,'absent','Auto-marked as absent — no clock-in','2026-10-20 15:13:43','2026-10-20 15:13:43');
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(80) NOT NULL DEFAULT '',
  `phone` varchar(30) NOT NULL DEFAULT '',
  `timezone` varchar(50) NOT NULL DEFAULT 'Europe/Helsinki',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Helsinki Main Office','Mannerheimintie 1','Helsinki','+358 40 123 4567','Europe/Helsinki',1,'2026-10-19 16:21:41'),(2,'Espoo Branch','Espoon Keskus 5','Espoo','+358 40 765 4321','Europe/Helsinki',1,'2026-10-19 16:21:41'),(3,'Tampere Branch','Hämeenkatu 10, 33100 Tampere','Tampere','+358 40 555 5555','Europe/Helsinki',1,'2026-10-20 15:27:41'),(4,'Turku Branch','Aurakatu 5, 20100 Turku','Turku','+358 40 666 6666','Europe/Helsinki',1,'2026-10-20 15:28:51');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `branch_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `description` text NOT NULL DEFAULT '',
  `manager_id` int(10) unsigned DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#3b82f6',
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Sales',1,1,'2026-10-19 16:21:41','',NULL,'#3b82f6'),(2,'IT',1,1,'2026-10-19 16:21:41','',NULL,'#3b82f6'),(3,'HR',2,1,'2026-10-19 16:21:41','',NULL,'#3b82f6'),(4,'Sales',3,1,'2026-10-20 15:43:52','',NULL,'#3b82f6'),(5,'IT',3,1,'2026-10-20 15:43:52','',NULL,'#3b82f6'),(6,'Customer Service',4,1,'2026-10-20 15:43:52','',NULL,'#3b82f6'),(7,'Warehouse',4,1,'2026-10-20 15:43:52','',NULL,'#3b82f6');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(20) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) NOT NULL DEFAULT '',
  `position` varchar(100) NOT NULL DEFAULT '',
  `branch_id` int(10) unsigned DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `shift_id` int(10) unsigned DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `photo` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_code` (`employee_code`),
  UNIQUE KEY `email` (`email`),
  KEY `branch_id` (`branch_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,'EMP001','Ahmed','Karimi','ahmed@attendance.local','+358 40 111 1111','Sales Manager',1,1,1,'2024-01-15','emp_1_1792578679.jpeg',1,'2026-10-19 16:21:41','2026-10-21 13:31:19'),(2,'EMP002','Fatima','Rahman','fatima@attendance.local','+358 40 222 2222','Software Developer',1,2,1,'2024-03-01','',1,'2026-10-19 16:21:41','2026-10-20 12:43:10'),(3,'EMP003','Mohammed','Hassan','mohammed@attendance.local','+358 40 333 3333','HR Specialist',2,3,2,'2024-06-10','',1,'2026-10-19 16:21:41','2026-10-20 12:43:10'),(4,'ADM001','System','Administrator','admin@attendance.local','+358 40 000 0001','System Administrator',1,1,1,'2024-01-01','',1,'2026-10-20 12:42:59','2026-10-20 12:42:59'),(5,'EMP004','Ali','Ahmadi','ali@attendance.local','+358 40 444 4444','Sales Manager',3,4,1,'2024-08-01','',1,'2026-10-20 15:43:54','2026-10-20 15:43:54'),(6,'EMP005','Sara','Hassan','sara@attendance.local','+358 40 555 1111','IT Specialist',3,5,1,'2024-05-15','',1,'2026-10-20 15:43:54','2026-10-20 15:43:54'),(7,'EMP006','Omar','Rahman','omar@attendance.local','+358 40 666 2222','Customer Service',4,6,1,'2024-09-10','',1,'2026-10-20 15:43:54','2026-10-20 15:43:54'),(8,'EMP007','Nadia','Karimi','nadia@attendance.local','+358 40 777 3333','Warehouse Manager',4,7,2,'2024-04-20','',1,'2026-10-20 15:43:54','2026-10-20 15:43:54');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `date` date NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_holiday_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `type` enum('vacation','sick','personal','unpaid') NOT NULL DEFAULT 'vacation',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `reviewed_by` (`reviewed_by`),
  KEY `idx_status` (`status`),
  CONSTRAINT `leaves_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leaves_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leaves`
--

LOCK TABLES `leaves` WRITE;
/*!40000 ALTER TABLE `leaves` DISABLE KEYS */;
/*!40000 ALTER TABLE `leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('text','number','bool','json') NOT NULL DEFAULT 'text',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('attendance_mode','clock_in_out','text','2026-10-20 13:09:02'),('auto_absence_enabled','1','bool','2026-10-20 13:09:02'),('company_address','Mannerheimintie 10, 00100 Helsinki','text','2026-10-20 13:09:02'),('company_email','info@acmesolutions.fi','text','2026-10-20 13:09:02'),('company_name','Acme Solutions Oy','text','2026-10-20 13:09:02'),('company_phone','+358 40 123 4567','text','2026-10-20 13:09:02'),('currency','€','text','2026-10-20 13:09:02'),('debug_mode','0','bool','2026-10-20 13:08:56'),('late_threshold_minutes','15','text','2026-10-20 14:10:52'),('logo_url','assets/images/logo.png','text','2026-10-20 13:09:02'),('maintenance_mode','0','bool','2026-10-20 14:57:51'),('notify_absent','1','bool','2026-10-20 13:08:56'),('notify_late','1','bool','2026-10-20 13:08:56'),('notify_weekly','1','bool','2026-10-20 13:08:56'),('overtime_threshold_minutes','30','text','2026-10-20 14:10:52'),('smtp_from_email','noreply@acmesolutions.fi','text','2026-10-20 13:08:56'),('smtp_from_name','Acme Attendance','text','2026-10-20 13:08:56'),('smtp_host','smtp.gmail.com','text','2026-10-20 13:09:02'),('smtp_password','example-app-password','text','2026-10-20 13:08:56'),('smtp_port','587','text','2026-10-20 14:10:52'),('smtp_username','notifications@acmesolutions.fi','text','2026-10-20 13:08:56'),('timezone','Europe/Helsinki','text','2026-10-20 13:09:02'),('week_start_day','monday','text','2026-10-20 13:09:02'),('work_day_hours','8','text','2026-10-20 14:10:52'),('work_days','1,2,3,4,5','text','2026-10-20 14:10:52');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shifts`
--

DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `days` varchar(50) NOT NULL DEFAULT '1,2,3,4,5',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `break_minutes` int(10) unsigned NOT NULL DEFAULT 30,
  `color` varchar(20) NOT NULL DEFAULT '#3b82f6',
  `description` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shifts`
--

LOCK TABLES `shifts` WRITE;
/*!40000 ALTER TABLE `shifts` DISABLE KEYS */;
INSERT INTO `shifts` VALUES (1,'Morning Shift','1,2,3,4,5','08:00:00','16:00:00',1,'2026-10-19 16:21:41',30,'#3b82f6',''),(2,'Evening Shift','1,2,3,4,5','16:00:00','00:00:00',1,'2026-10-19 16:21:41',30,'#3b82f6',''),(3,'Night Shift','1,2,3,4,5','00:00:00','08:00:00',1,'2026-10-19 16:21:41',30,'#3b8cf7','');
/*!40000 ALTER TABLE `shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `role` enum('admin','manager','employee') NOT NULL DEFAULT 'employee',
  `employee_id` int(10) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_employee` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','admin@attendance.local','$2y$10$f5pqUQEz7VBtA/IAxFOfKOmI4ddfNQjqLUig12XctHcsjFPYqQSba','System Administrator','admin',NULL,1,'2026-10-21 15:06:17','2026-10-19 16:17:50','2026-10-21 15:33:11'),(3,'employee1','ahmed@attendance.local','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Test Employee One','employee',1,1,'2026-10-21 15:51:54','2026-10-20 16:56:15','2026-10-21 15:51:54');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-21 16:54:29
