/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: banking_app
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
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `account_number` varchar(24) NOT NULL,
  `iban` varchar(34) DEFAULT NULL,
  `account_type` enum('checking','savings') DEFAULT 'checking',
  `currency` varchar(3) DEFAULT 'EUR',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pin_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,1,'FI1212340000018300',NULL,'checking','EUR','Test','User',1000.00,'$2y$10$phyUKLXZ8mynq3pG55pm.ecfoVm15X8vJrmD6UkTRBziX1jTfbJYG','2026-10-22 10:05:43',1);
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `status` enum('success','failed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'register','::1','success','2026-10-22 08:15:31'),(2,1,'login','::1','success','2026-10-22 08:15:41'),(3,1,'login','::1','success','2026-10-22 09:52:11'),(4,1,'logout','::1','success','2026-10-22 10:24:26'),(5,2,'login','::1','success','2026-10-22 10:26:42'),(6,2,'logout','::1','success','2026-10-22 10:44:03'),(7,3,'login','::1','success','2026-10-22 10:44:24'),(8,3,'logout','::1','success','2026-10-22 11:11:54'),(9,2,'login','::1','success','2026-10-22 11:12:04'),(10,2,'logout','::1','success','2026-10-22 11:26:13'),(11,3,'login','::1','success','2026-10-22 11:26:20'),(12,3,'logout','::1','success','2026-10-22 11:46:16'),(13,2,'login','::1','success','2026-10-22 11:46:34'),(14,2,'logout','::1','success','2026-10-22 12:00:16'),(15,3,'login','::1','success','2026-10-22 12:00:23'),(16,3,'login','::1','success','2026-10-22 13:05:15'),(17,3,'logout','::1','success','2026-10-22 13:10:19'),(18,2,'login','::1','success','2026-10-22 13:10:26'),(19,2,'logout','::1','success','2026-10-22 13:15:03'),(20,3,'login','::1','success','2026-10-22 13:15:27'),(21,2,'login','::1','success','2026-10-22 14:26:15'),(22,3,'login','::1','success','2026-10-23 07:36:32'),(23,3,'logout','::1','success','2026-10-23 07:45:38'),(24,3,'login','::1','success','2026-10-23 07:45:55'),(25,3,'logout','::1','success','2026-10-23 07:46:19'),(26,NULL,'login_attempt','::1','failed','2026-10-23 08:00:54'),(27,4,'login','::1','success','2026-10-23 08:02:22'),(28,4,'login','::1','success','2026-10-23 08:07:31'),(29,1,'login','::1','success','2026-10-23 09:40:13'),(30,1,'logout','::1','success','2026-10-23 09:43:22'),(31,1,'login','::1','success','2026-10-23 09:43:27'),(32,1,'logout','::1','success','2026-10-23 09:51:43'),(33,1,'login','::1','success','2026-10-23 09:51:49'),(34,1,'logout','::1','success','2026-10-23 10:02:03'),(35,1,'login','::1','success','2026-10-23 10:02:10');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beneficiaries`
--

DROP TABLE IF EXISTS `beneficiaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `beneficiaries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `beneficiary_name` varchar(100) NOT NULL,
  `account_number` varchar(34) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `beneficiaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beneficiaries`
--

LOCK TABLES `beneficiaries` WRITE;
/*!40000 ALTER TABLE `beneficiaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `beneficiaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cards`
--

DROP TABLE IF EXISTS `cards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `card_number` varchar(16) NOT NULL,
  `card_type` enum('debit','credit') NOT NULL,
  `expiry_date` date NOT NULL,
  `cvv` varchar(3) NOT NULL,
  `status` enum('active','blocked','expired') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `card_number` (`card_number`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `cards_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cards`
--

LOCK TABLES `cards` WRITE;
/*!40000 ALTER TABLE `cards` DISABLE KEYS */;
/*!40000 ALTER TABLE `cards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exchange_rates`
--

DROP TABLE IF EXISTS `exchange_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `currency_from` varchar(3) NOT NULL,
  `currency_to` varchar(3) NOT NULL,
  `rate` decimal(10,4) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exchange_rates`
--

LOCK TABLES `exchange_rates` WRITE;
/*!40000 ALTER TABLE `exchange_rates` DISABLE KEYS */;
INSERT INTO `exchange_rates` VALUES (1,'EUR','EUR',0.9200,'2026-10-22 09:06:28'),(2,'EUR','GBP',0.7900,'2026-10-22 09:06:28');
/*!40000 ALTER TABLE `exchange_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kyc_documents`
--

DROP TABLE IF EXISTS `kyc_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `kyc_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `document_type` enum('passport','national_id','drivers_license') NOT NULL,
  `document_number` varchar(50) NOT NULL,
  `expiry_date` date NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `kyc_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kyc_documents`
--

LOCK TABLES `kyc_documents` WRITE;
/*!40000 ALTER TABLE `kyc_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `kyc_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `legal_pages`
--

DROP TABLE IF EXISTS `legal_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `legal_pages`
--

LOCK TABLES `legal_pages` WRITE;
/*!40000 ALTER TABLE `legal_pages` DISABLE KEYS */;
INSERT INTO `legal_pages` VALUES (1,'privacy','Privacy Policy','<h2>1. Information We Collect</h2><p>We collect personal information such as your name, email address, phone number, date of birth, and national ID when you open an account.</p><h2>2. How We Use Your Information</h2><p>Your data is used to verify your identity, process transactions, prevent fraud, and comply with legal obligations.</p><h2>3. Data Security</h2><p>We use industry-standard encryption (SSL) and store all passwords using Bcrypt hashing. We never store your raw PIN.</p><h2>4. Sharing Your Information</h2><p>We do not sell your data. We may share it with regulatory authorities if required by law.</p>','2026-10-23 10:24:38'),(2,'terms','Terms of Service','<h2>1. Account Eligibility</h2><p>You must be at least 18 years old and provide valid government ID to open an account.</p><h2>2. Account Security</h2><p>You are responsible for keeping your PIN and password confidential. Notify us immediately if you suspect unauthorized access.</p><h2>3. Transactions</h2><p>All deposits and withdrawals are subject to verification. We reserve the right to freeze accounts suspected of fraud.</p><h2>4. Loan Agreements</h2><p>Approved loans are subject to the interest rate displayed at the time of application. Late payments may incur fees.</p>','2026-10-23 10:24:38'),(3,'cookies','Cookie Policy','<h2>1. What Are Cookies?</h2><p>Cookies are small text files stored on your device to help us remember your preferences and keep you logged in.</p><h2>2. How We Use Cookies</h2><p>We use cookies to maintain your session, remember your Dark/Light mode choice, and analyze traffic.</p><h2>3. Managing Cookies</h2><p>You can disable cookies in your browser settings, but this may affect the functionality of the banking app.</p>','2026-10-23 10:24:38');
/*!40000 ALTER TABLE `legal_pages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `loan_type` enum('personal','mortgage','auto') NOT NULL,
  `principal_amount` decimal(15,2) NOT NULL,
  `interest_rate` decimal(5,2) NOT NULL,
  `term_months` int(11) NOT NULL,
  `status` enum('pending','approved','active','paid_off') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('bank_name','Modern Bank','text','2026-10-22 13:58:46'),('bank_tagline','Banking for the Future','text','2026-10-22 14:42:14'),('daily_transfer_limit','10000','text','2026-10-22 14:42:14'),('intl_transfer_fee','15.00','text','2026-10-22 14:42:14'),('kyc_required','1','text','2026-10-22 14:42:14'),('loan_interest_rate','5.5','number','2026-10-22 13:58:46'),('maintenance_mode','0','bool','2026-10-22 13:58:46'),('max_loan_amount','50000','number','2026-10-22 13:58:46'),('min_balance','0.00','text','2026-10-22 14:42:14'),('registration_open','1','text','2026-10-22 14:42:14'),('support_address','Mannerheimintie 1, Helsinki, Finland','text','2026-10-22 14:42:14'),('support_email','support@modernbank.com','text','2026-10-22 13:58:46'),('support_phone','+358 40 123 4567','text','2026-10-22 14:42:14');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `type` enum('deposit','withdraw','transfer_in','transfer_out') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `related_account_id` int(10) unsigned DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `related_account_id` (`related_account_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`related_account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (1,1,NULL,'deposit',1000.00,NULL,NULL,'completed','2026-10-22 10:05:43');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `kyc_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('user','staff','manager','admin') DEFAULT 'user',
  `photo` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'test@bank.com','045324523','$2y$10$4S/KBpb38N4o4mEE1F9GHORVapczzwJgzzpPGW0RfYGloBuoGJMLy','pending','2026-10-22 08:15:31','user','user_1_1792750253.jpg','Vaahtokuja 5 E50','1945-02-13','130245-224M','Ali','Akber'),(2,'admin@bank.com','12345678','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','pending','2026-10-22 08:27:23','admin','admin_2_1792680726.jpeg','Vaahtokuja 5 E50','1988-12-31','311298-314U','Ahmed','Karimi'),(3,'manager@bank.com','1111111111','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','pending','2026-10-22 08:27:41','manager','manager_3_1792741326.jpg','Lutherinkatu 3, 00100 Helsinki','1988-12-31','123234-309D','Aziz','Masih'),(4,'staff@bank.com','+358401234567','$2y$10$RosMu6bq8qH8WGVQlEsx6eSm7N2MqmAITeqfGxSyszumF9VUxbemC','pending','2026-10-23 07:59:41','staff',NULL,NULL,NULL,NULL,'John','Teller');
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

-- Dump completed on 2026-10-23 13:34:14
