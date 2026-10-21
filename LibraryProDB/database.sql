/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.23-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: LibraryProDB
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
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,5,'register','New member registered: testuser (card LIB-2026-00005)',NULL,NULL,NULL,NULL,'2026-10-05 09:28:40'),(2,5,'login','User testuser logged in',NULL,NULL,'::1',NULL,'2026-10-05 09:54:13'),(3,1,'login','User aziznoyan1 logged in',NULL,NULL,'::1',NULL,'2026-10-05 12:23:40'),(4,1,'login','User aziznoyan1 logged in',NULL,NULL,'::1',NULL,'2026-10-05 13:09:20'),(5,1,'checkout','Checked out copy BC-0001 (1984) to alice — due 2026-10-19','borrowing',1,NULL,NULL,'2026-10-05 13:18:53'),(6,1,'checkin','Returned copy BC-0001 (\"1984\") from alice','borrowing',1,NULL,NULL,'2026-10-05 13:20:49'),(7,5,'login','User testuser logged in',NULL,NULL,'::1',NULL,'2026-10-05 13:26:01'),(8,5,'profile_update','Updated own profile','user',5,NULL,NULL,'2026-10-05 13:41:27'),(9,3,'login','User aziznoyan logged in',NULL,NULL,'::1',NULL,'2026-10-05 13:47:32'),(10,3,'checkout','Checked out copy BC-0001 (1984) to alice — due 2026-10-19','borrowing',2,NULL,NULL,'2026-10-05 13:50:00'),(11,3,'checkin','Returned copy BC-0001 (\"1984\") from alice — €5.00 fine issued (5 days late)','borrowing',2,NULL,NULL,'2026-10-05 13:57:59'),(12,4,'login','User alice logged in',NULL,NULL,'::1',NULL,'2026-10-05 14:02:37'),(13,3,'login','User aziznoyan logged in',NULL,NULL,'::1',NULL,'2026-10-05 14:10:51'),(14,4,'login','User alice logged in',NULL,NULL,'::1',NULL,'2026-10-06 07:53:21'),(15,3,'login','User aziznoyan logged in',NULL,NULL,'::1',NULL,'2026-10-06 08:15:06'),(16,3,'fine_paid','Collected €5.00 for fine #1 via cash','fine',1,NULL,NULL,'2026-10-06 10:05:37'),(17,3,'login','User aziznoyan logged in',NULL,NULL,'::1',NULL,'2026-10-06 13:00:06'),(18,1,'login','User aziznoyan1 logged in',NULL,NULL,'::1',NULL,'2026-10-06 13:59:09'),(19,1,'login','User aziznoyan1 logged in',NULL,NULL,'::1',NULL,'2026-10-07 08:34:09'),(20,1,'user_updated','Updated user #5 (test@example.com)','user',5,NULL,NULL,'2026-10-07 09:30:12'),(21,4,'login','User alice logged in',NULL,NULL,'::1',NULL,'2026-10-07 11:40:29'),(22,4,'preferences_updated','Updated own preferences','user',4,NULL,NULL,'2026-10-07 12:10:10');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `authors`
--

DROP TABLE IF EXISTS `authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `bio` text DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `death_year` int(11) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`last_name`,`first_name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `authors`
--

LOCK TABLES `authors` WRITE;
/*!40000 ALTER TABLE `authors` DISABLE KEYS */;
INSERT INTO `authors` VALUES (1,'George','Orwell',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(2,'Jane','Austen',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(3,'Fyodor','Dostoevsky',NULL,NULL,NULL,'Russia','2026-10-05 09:56:17'),(4,'Ernest','Hemingway',NULL,NULL,NULL,'USA','2026-10-05 09:56:17'),(5,'Gabriel','Garcia Marquez',NULL,NULL,NULL,'Colombia','2026-10-05 09:56:17'),(6,'Harper','Lee',NULL,NULL,NULL,'USA','2026-10-05 09:56:17'),(7,'J.R.R.','Tolkien',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(8,'J.K.','Rowling',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(9,'Yuval','Harari',NULL,NULL,NULL,'Israel','2026-10-05 09:56:17'),(10,'Stephen','Hawking',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(11,'Carl','Sagan',NULL,NULL,NULL,'USA','2026-10-05 09:56:17'),(12,'Malcolm','Gladwell',NULL,NULL,NULL,'Canada','2026-10-05 09:56:17'),(13,'Daniel','Kahneman',NULL,NULL,NULL,'Israel','2026-10-05 09:56:17'),(14,'Robert','Martin',NULL,NULL,NULL,'USA','2026-10-05 09:56:17'),(15,'Martin','Fowler',NULL,NULL,NULL,'UK','2026-10-05 09:56:17'),(16,'Mika','Waltari',NULL,NULL,NULL,'Finland','2026-10-05 09:56:17'),(17,'Väinö','Linna',NULL,NULL,NULL,'Finland','2026-10-05 09:56:17'),(18,'Tove','Jansson',NULL,NULL,NULL,'Finland','2026-10-05 09:56:17'),(19,'Paulo','Coelho',NULL,NULL,NULL,'Brazil','2026-10-05 09:56:17'),(20,'Khaled','Hosseini',NULL,NULL,NULL,'Afghanistan','2026-10-05 09:56:17');
/*!40000 ALTER TABLE `authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `book_authors`
--

DROP TABLE IF EXISTS `book_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `book_authors` (
  `book_id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  PRIMARY KEY (`book_id`,`author_id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `book_authors_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `book_authors_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book_authors`
--

LOCK TABLES `book_authors` WRITE;
/*!40000 ALTER TABLE `book_authors` DISABLE KEYS */;
INSERT INTO `book_authors` VALUES (1,1),(2,2),(3,3),(4,4),(5,5),(6,6),(7,7),(8,8),(9,9),(10,10),(11,11),(12,12),(13,13),(14,14),(15,15);
/*!40000 ALTER TABLE `book_authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `book_genres`
--

DROP TABLE IF EXISTS `book_genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `book_genres` (
  `book_id` int(11) NOT NULL,
  `genre_id` int(11) NOT NULL,
  PRIMARY KEY (`book_id`,`genre_id`),
  KEY `genre_id` (`genre_id`),
  CONSTRAINT `book_genres_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `book_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book_genres`
--

LOCK TABLES `book_genres` WRITE;
/*!40000 ALTER TABLE `book_genres` DISABLE KEYS */;
INSERT INTO `book_genres` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(8,10),(9,4),(10,3),(11,3),(12,6),(13,8),(14,5),(15,5);
/*!40000 ALTER TABLE `book_genres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `isbn` varchar(20) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `publisher_id` int(11) DEFAULT NULL,
  `edition` varchar(50) DEFAULT NULL,
  `publication_year` int(11) DEFAULT NULL,
  `pages` int(11) DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT 'en',
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `isbn` (`isbn`),
  KEY `idx_title` (`title`),
  KEY `idx_isbn` (`isbn`),
  KEY `idx_publisher` (`publisher_id`),
  CONSTRAINT `books_ibfk_1` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books`
--

LOCK TABLES `books` WRITE;
/*!40000 ALTER TABLE `books` DISABLE KEYS */;
INSERT INTO `books` VALUES (1,'9780451524935','1984',NULL,1,NULL,1949,328,'en','Dystopian social science fiction novel.','1984.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(2,'9780141439518','Pride and Prejudice',NULL,1,NULL,1813,432,'en','Classic romantic novel of manners.','pride-and-prejudice.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(3,'9780143058144','Crime and Punishment',NULL,1,NULL,1866,671,'en','Psychological drama of a young man in St. Petersburg.','crime-and-punishment.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(4,'9780684801223','The Old Man and the Sea',NULL,6,NULL,1952,127,'en','Short novel of an old fisherman and his struggle.','old-man-and-the-sea.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(5,'9780060883287','One Hundred Years of Solitude',NULL,2,NULL,1967,417,'en','Magical realism masterpiece of the Buendía family.','one-hundred-years.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(6,'9780061120084','To Kill a Mockingbird',NULL,2,NULL,1960,336,'en','Justice and racial inequality in the American South.','to-kill-a-mockingbird.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(7,'9780618640157','The Lord of the Rings',NULL,5,NULL,1954,1178,'en','Epic high fantasy of Middle-earth.','lord-of-the-rings.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(8,'9780747532743','Harry Potter and the Philosopher\'s Stone',NULL,5,NULL,1997,223,'en','A young wizard begins his education at Hogwarts.','harry-potter-1.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(9,'9780062316097','Sapiens: A Brief History of Humankind',NULL,2,NULL,2011,464,'en','A brief history of human evolution and civilization.','sapiens.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(10,'9780553380163','A Brief History of Time',NULL,3,NULL,1988,212,'en','Cosmology for the general reader.','brief-history-of-time.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(11,'9780345539434','Cosmos',NULL,1,NULL,1980,396,'en','A personal voyage through the universe.','cosmos.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(12,'9780316017930','Outliers',NULL,4,NULL,2008,336,'en','The story of success — why some people succeed.','outliers.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(13,'9780374533557','Thinking, Fast and Slow',NULL,4,NULL,2011,499,'en','Two systems that drive the way we think.','thinking-fast-and-slow.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(14,'9780132350884','Clean Code',NULL,6,NULL,2008,464,'en','A handbook of agile software craftsmanship.','clean-code.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01'),(15,'9780201633610','Design Patterns',NULL,6,NULL,1994,395,'en','Elements of reusable object-oriented software.','design-patterns.jpg','2026-10-05 09:56:17','2026-10-05 11:04:01');
/*!40000 ALTER TABLE `books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `borrowings`
--

DROP TABLE IF EXISTS `borrowings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `copy_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `borrow_date` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `renewed_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('borrowed','returned','overdue','lost') NOT NULL DEFAULT 'borrowed',
  `notes` text DEFAULT NULL,
  `checked_out_by` int(11) DEFAULT NULL,
  `checked_in_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_copy` (`copy_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`due_date`),
  KEY `branch_id` (`branch_id`),
  KEY `checked_out_by` (`checked_out_by`),
  KEY `checked_in_by` (`checked_in_by`),
  CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`copy_id`) REFERENCES `copies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `borrowings_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `borrowings_ibfk_4` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `borrowings_ibfk_5` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `borrowings`
--

LOCK TABLES `borrowings` WRITE;
/*!40000 ALTER TABLE `borrowings` DISABLE KEYS */;
INSERT INTO `borrowings` VALUES (1,4,1,1,'2026-10-05 16:18:53','2026-10-19','2026-10-05 16:20:49',0,'returned','',1,1,'2026-10-05 13:18:53'),(2,4,1,1,'2026-10-05 16:50:00','2026-09-30','2026-10-05 16:57:59',0,'returned','',3,3,'2026-10-05 13:50:00');
/*!40000 ALTER TABLE `borrowings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `opening_hours` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'HEL-C','Helsinki Central Library','Unioninkatu 1','Helsinki','00130','+358 9 1234 5678','info@hel-c.library.fi','Mon-Fri 09:00-20:00, Sat 10:00-18:00',1,'2026-10-05 08:25:05'),(2,'ESP','Espoo Library','Kauppakatu 5','Espoo','02100','+358 9 2345 6789','info@esp.library.fi','Mon-Fri 09:00-19:00, Sat 10:00-16:00',1,'2026-10-05 08:25:05'),(3,'TRE','Tampere Library','Hämeenkatu 10','Tampere','33100','+358 3 3456 7890','info@tre.library.fi','Mon-Fri 09:00-20:00, Sat 10:00-18:00',1,'2026-10-05 08:25:05'),(4,'TKU','Turku Library','Aurakatu 3','Turku','20100','+358 2 4567 8901','info@tku.library.fi','Mon-Fri 09:00-19:00, Sat 10:00-16:00',1,'2026-10-05 08:25:05'),(5,'OUL','Oulu Library','Kirkkokatu 8','Oulu','90100','+358 8 5678 9012','info@oul.library.fi','Mon-Fri 10:00-19:00, Sat 10:00-16:00',1,'2026-10-05 08:25:05');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `copies`
--

DROP TABLE IF EXISTS `copies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `copies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `barcode` varchar(50) NOT NULL,
  `shelf_id` int(11) DEFAULT NULL,
  `copy_condition` enum('new','good','worn','damaged','lost') NOT NULL DEFAULT 'good',
  `status` enum('available','borrowed','reserved','on_hold','lost','damaged','withdrawn') NOT NULL DEFAULT 'available',
  `acquired_date` date DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `idx_book` (`book_id`),
  KEY `idx_status` (`status`),
  KEY `shelf_id` (`shelf_id`),
  CONSTRAINT `copies_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `copies_ibfk_2` FOREIGN KEY (`shelf_id`) REFERENCES `shelves` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `copies`
--

LOCK TABLES `copies` WRITE;
/*!40000 ALTER TABLE `copies` DISABLE KEYS */;
INSERT INTO `copies` VALUES (1,1,'BC-0001',1,'good','available','2026-10-05',12.50,NULL,'2026-10-05 12:41:49'),(2,2,'BC-0002',1,'good','available','2026-10-05',10.00,NULL,'2026-10-05 12:41:49'),(3,3,'BC-0003',1,'good','available','2026-10-05',11.00,NULL,'2026-10-05 12:41:49'),(4,4,'BC-0004',1,'good','available','2026-10-05',9.50,NULL,'2026-10-05 12:41:49'),(5,5,'BC-0005',1,'good','available','2026-10-05',13.00,NULL,'2026-10-05 12:41:49'),(6,6,'BC-0006',1,'good','available','2026-10-05',10.50,NULL,'2026-10-05 12:41:49'),(7,7,'BC-0007',1,'good','available','2026-10-05',25.00,NULL,'2026-10-05 12:41:49'),(8,8,'BC-0008',1,'good','available','2026-10-05',20.00,NULL,'2026-10-05 12:41:49'),(9,9,'BC-0009',3,'good','available','2026-10-05',22.00,NULL,'2026-10-05 12:41:49'),(10,10,'BC-0010',4,'new','available','2026-10-05',15.00,NULL,'2026-10-05 12:41:49'),(11,11,'BC-0011',4,'new','available','2026-10-05',18.00,NULL,'2026-10-05 12:41:49'),(12,12,'BC-0012',3,'good','available','2026-10-05',16.00,NULL,'2026-10-05 12:41:49'),(13,13,'BC-0013',3,'good','available','2026-10-05',17.00,NULL,'2026-10-05 12:41:49'),(14,14,'BC-0014',4,'good','available','2026-10-05',35.00,NULL,'2026-10-05 12:41:49'),(15,15,'BC-0015',4,'good','available','2026-10-05',40.00,NULL,'2026-10-05 12:41:49');
/*!40000 ALTER TABLE `copies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_borrowings`
--

DROP TABLE IF EXISTS `device_borrowings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_borrowings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `borrow_date` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `renewed_count` int(11) NOT NULL DEFAULT 0,
  `status` enum('borrowed','returned','overdue','lost') NOT NULL DEFAULT 'borrowed',
  `notes` text DEFAULT NULL,
  `checked_out_by` int(11) DEFAULT NULL,
  `checked_in_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_status` (`status`),
  KEY `branch_id` (`branch_id`),
  KEY `checked_out_by` (`checked_out_by`),
  KEY `checked_in_by` (`checked_in_by`),
  CONSTRAINT `device_borrowings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `device_borrowings_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `device_borrowings_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `device_borrowings_ibfk_4` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `device_borrowings_ibfk_5` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_borrowings`
--

LOCK TABLES `device_borrowings` WRITE;
/*!40000 ALTER TABLE `device_borrowings` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_borrowings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `devices`
--

DROP TABLE IF EXISTS `devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `purchase_year` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('available','borrowed','maintenance','retired') NOT NULL DEFAULT 'available',
  `device_condition` enum('new','good','worn','damaged') NOT NULL DEFAULT 'good',
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `idx_status` (`status`),
  KEY `idx_branch` (`branch_id`),
  CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devices`
--

LOCK TABLES `devices` WRITE;
/*!40000 ALTER TABLE `devices` DISABLE KEYS */;
INSERT INTO `devices` VALUES (1,'Lenovo ThinkPad L14','Public-use laptop for in-library use','LNV-2024-0001','Laptop','Lenovo','ThinkPad L14',2024,1,'available','new','thinkpad-l14.jpg','2026-10-06 10:38:25'),(2,'Lenovo ThinkPad L14','Public-use laptop for in-library use','LNV-2024-0002','Laptop','Lenovo','ThinkPad L14',2024,1,'available','good','thinkpad-l14.jpg','2026-10-06 10:38:25'),(3,'Dell Latitude 5540','Staff workstation laptop','DLL-2024-0001','Laptop','Dell','Latitude 5540',2024,1,'borrowed','good','dell-5540.jpg','2026-10-06 10:38:25'),(4,'Apple iPad 10.9\"','Tablet for children\'s section','APL-2023-0001','Tablet','Apple','iPad 10.9',2023,1,'available','good','ipad.jpg','2026-10-06 10:38:25'),(5,'Apple iPad 10.9\"','Tablet for children\'s section','APL-2023-0002','Tablet','Apple','iPad 10.9',2023,1,'maintenance','worn','ipad.jpg','2026-10-06 10:38:25'),(6,'Amazon Kindle Paperwhite','eReader for loan','AMZ-2023-0001','eReader','Amazon','Kindle Paperwhite',2023,2,'available','new','kindle.jpg','2026-10-06 10:38:25'),(7,'Amazon Kindle Paperwhite','eReader for loan','AMZ-2023-0002','eReader','Amazon','Kindle Paperwhite',2023,2,'borrowed','good','kindle.jpg','2026-10-06 10:38:25'),(8,'Samsung Galaxy Tab A9','Tablet for teen section','SAM-2024-0001','Tablet','Samsung','Galaxy Tab A9',2024,2,'available','good','galaxy-tab.jpg','2026-10-06 10:38:25'),(9,'HP EliteBook 840','Laptop for study room','HPQ-2022-0001','Laptop','HP','EliteBook 840',2022,3,'available','worn','hp-840.jpg','2026-10-06 10:38:25'),(10,'HP EliteBook 840','Laptop for study room','HPQ-2022-0002','Laptop','HP','EliteBook 840',2022,3,'retired','damaged','hp-840.jpg','2026-10-06 10:38:25'),(11,'Raspberry Pi 4 Kit','Coding club kit','RPI-2024-0001','Kit','Raspberry Pi','Pi 4 Model B',2024,4,'available','new','raspberry-pi.jpg','2026-10-06 10:38:25'),(12,'Epson EB-X06 Projector','Portable projector for events','EPS-2023-0001','Projector','Epson','EB-X06',2023,4,'available','good','epson-x06.jpg','2026-10-06 10:38:25'),(13,'Logitech C920 Webcam','Webcam for video calls','LOG-2024-0001','Webcam','Logitech','C920',2024,5,'available','new','logitech-c920.jpg','2026-10-06 10:38:25'),(14,'Logitech C920 Webcam','Webcam for video calls','LOG-2024-0002','Webcam','Logitech','C920',2024,5,'maintenance','worn','logitech-c920.jpg','2026-10-06 10:38:25');
/*!40000 ALTER TABLE `devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fines`
--

DROP TABLE IF EXISTS `fines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `borrowing_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` enum('overdue','damage','lost','other') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `issued_date` date NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_date` date DEFAULT NULL,
  `waived` tinyint(1) NOT NULL DEFAULT 0,
  `waived_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_paid` (`paid`),
  KEY `borrowing_id` (`borrowing_id`),
  KEY `waived_by` (`waived_by`),
  CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fines_ibfk_2` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fines_ibfk_3` FOREIGN KEY (`waived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fines`
--

LOCK TABLES `fines` WRITE;
/*!40000 ALTER TABLE `fines` DISABLE KEYS */;
INSERT INTO `fines` VALUES (1,4,2,5.00,'overdue','5 day(s) late on \"1984\" (BC-0001)','2026-10-05',1,'2026-10-06',0,NULL,'2026-10-05 13:57:59');
/*!40000 ALTER TABLE `fines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `genres`
--

DROP TABLE IF EXISTS `genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `genres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `genres`
--

LOCK TABLES `genres` WRITE;
/*!40000 ALTER TABLE `genres` DISABLE KEYS */;
INSERT INTO `genres` VALUES (1,'Fiction','Novels and fictional stories'),(2,'Non-Fiction','Factual books, essays, memoirs'),(3,'Science','Physics, chemistry, biology, mathematics'),(4,'History','World history, biographies, historical events'),(5,'Technology','Computers, engineering, programming'),(6,'Business','Management, economics, finance, marketing'),(7,'Philosophy','Ethics, logic, metaphysics, existentialism'),(8,'Psychology','Human behavior, mental health, cognition'),(9,'Art','Painting, sculpture, design, architecture'),(10,'Children','Books for children and young readers'),(11,'Poetry','Poems and verse collections'),(12,'Language','Dictionaries, grammar, language learning');
/*!40000 ALTER TABLE `genres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holds`
--

DROP TABLE IF EXISTS `holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `holds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `placed_date` datetime NOT NULL DEFAULT current_timestamp(),
  `notified_date` datetime DEFAULT NULL,
  `expires_date` date DEFAULT NULL,
  `status` enum('pending','ready','fulfilled','cancelled','expired') NOT NULL DEFAULT 'pending',
  `queue_position` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_book` (`book_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `holds_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `holds_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `holds_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holds`
--

LOCK TABLES `holds` WRITE;
/*!40000 ALTER TABLE `holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `holds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('due_soon','overdue','hold_ready','fine_issued','welcome','general') NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_read` (`is_read`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,4,'welcome','Welcome to LibraryPro','Thanks for joining! Browse our catalog and start borrowing.',NULL,1,'2026-10-06 11:11:57','2026-10-06 07:58:56'),(2,4,'due_soon','Book due soon','Your copy of \"1984\" is due in 3 days. Renew or return it at your branch.',NULL,1,'2026-10-06 11:12:08','2026-10-06 07:58:56'),(3,4,'fine_issued','Fine issued','A €5.00 overdue fine has been added to your account.',NULL,1,'2026-10-06 11:12:06','2026-10-06 07:58:56');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `fine_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` enum('cash','card','bank','online','other') NOT NULL DEFAULT 'cash',
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `received_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_date` (`payment_date`),
  KEY `fine_id` (`fine_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`fine_id`) REFERENCES `fines` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,4,1,5.00,'cash','2026-10-06 13:05:37',3,NULL,'2026-10-06 10:05:37');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `publishers`
--

DROP TABLE IF EXISTS `publishers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `publishers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `publishers`
--

LOCK TABLES `publishers` WRITE;
/*!40000 ALTER TABLE `publishers` DISABLE KEYS */;
INSERT INTO `publishers` VALUES (1,'Penguin Random House','USA','New York',NULL,'2026-10-05 09:56:17'),(2,'HarperCollins','USA','New York',NULL,'2026-10-05 09:56:17'),(3,'Oxford University Press','UK','Oxford',NULL,'2026-10-05 09:56:17'),(4,'Cambridge University Press','UK','Cambridge',NULL,'2026-10-05 09:56:17'),(5,'Macmillan','UK','London',NULL,'2026-10-05 09:56:17'),(6,'Simon & Schuster','USA','New York',NULL,'2026-10-05 09:56:17'),(7,'WSOY','Finland','Helsinki',NULL,'2026-10-05 09:56:17'),(8,'Otava','Finland','Helsinki',NULL,'2026-10-05 09:56:17');
/*!40000 ALTER TABLE `publishers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_book` (`user_id`,`book_id`),
  KEY `idx_book` (`book_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating` CHECK (`rating` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `setting_type` enum('int','decimal','string','bool') NOT NULL DEFAULT 'string',
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `label` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`),
  KEY `settings_ibfk_1` (`updated_by`),
  CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('default_language','en','string','general','Default language','en / fi / fa',NULL,'2026-10-07 09:58:36'),('fine_block_threshold','10.00','decimal','fines','Borrowing block threshold (EUR)','Block new loans if unpaid fines exceed this',NULL,'2026-10-07 09:58:36'),('fine_per_day','1.00','decimal','fines','Fine per day (EUR)','Overdue charge per day per book',NULL,'2026-10-07 09:58:36'),('loan_days','14','int','circulation','Loan period (days)','How many days a member can keep a book',NULL,'2026-10-07 10:03:43'),('max_fine_per_book','20.00','decimal','fines','Max fine per book (EUR)','Cap on overdue charges per book',NULL,'2026-10-07 09:58:36'),('max_loans_per_member','5','int','circulation','Max concurrent loans','How many books a member can have out at once',NULL,'2026-10-07 09:58:36'),('max_renewals','2','int','circulation','Max renewals per loan','How many times a loan can be extended',NULL,'2026-10-07 09:58:36'),('site_name','LibraryPro','string','general','Site name','Shown in the header and emails',NULL,'2026-10-07 09:58:36');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shelves`
--

DROP TABLE IF EXISTS `shelves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shelves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `section` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_branch_code` (`branch_id`,`code`),
  CONSTRAINT `shelves_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shelves`
--

LOCK TABLES `shelves` WRITE;
/*!40000 ALTER TABLE `shelves` DISABLE KEYS */;
INSERT INTO `shelves` VALUES (1,1,'A-01','Fiction','Fiction, alphabetical A-D'),(2,1,'A-02','Fiction','Fiction, alphabetical E-K'),(3,1,'A-03','Non-Fiction','History and Biography'),(4,1,'A-04','Science','Science and Technology'),(5,1,'A-05','Children','Children\'s books'),(6,2,'A-01','Fiction','Fiction'),(7,2,'A-02','Non-Fiction','Non-Fiction'),(8,2,'A-03','Children','Children'),(9,3,'A-01','Fiction','Fiction'),(10,3,'A-02','Science','Science'),(11,3,'A-03','Technology','Technology'),(12,4,'A-01','Fiction','Fiction'),(13,4,'A-02','History','History'),(14,4,'A-03','Psychology','Psychology'),(15,5,'A-01','Fiction','Fiction'),(16,5,'A-02','Non-Fiction','Non-Fiction'),(17,5,'A-03','Children','Children'),(18,5,'A-04','Technology','Technology');
/*!40000 ALTER TABLE `shelves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_preferences` (
  `user_id` int(11) NOT NULL,
  `default_branch_id` int(11) DEFAULT NULL,
  `notify_email` tinyint(1) NOT NULL DEFAULT 1,
  `notify_due_soon` tinyint(1) NOT NULL DEFAULT 1,
  `notify_overdue` tinyint(1) NOT NULL DEFAULT 1,
  `notify_hold_ready` tinyint(1) NOT NULL DEFAULT 1,
  `notify_fine_issued` tinyint(1) NOT NULL DEFAULT 1,
  `hide_reading_history` tinyint(1) NOT NULL DEFAULT 0,
  `favourite_genres` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `user_preferences_ibfk_2` (`default_branch_id`),
  CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_preferences_ibfk_2` FOREIGN KEY (`default_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (4,NULL,1,1,1,1,1,0,NULL,NULL,'2026-10-07 12:10:10');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_number` varchar(30) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Finland',
  `role` enum('guest','member','librarian','admin') NOT NULL DEFAULT 'member',
  `branch_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended','expired') NOT NULL DEFAULT 'active',
  `language` enum('en','fi','fa') NOT NULL DEFAULT 'en',
  `avatar` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `card_number` (`card_number`),
  KEY `idx_role` (`role`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'LIB-2026-00001','aziznoyan1','aziznoyan50@gmail.com','$2y$10$IfT6OSDMKD.MXs2YY7H5wui7fVb8ZrEgHc149S5dOYt7zo0NaQTkS','Aziz','Noyan',NULL,NULL,NULL,NULL,'Finland','admin',1,'active','en',NULL,'2026-10-07 11:34:09','2026-10-05 08:36:13'),(2,'LIB-2026-00002','matiasmasih','matiasmasih@gmail.com','$2y$10$Q49YuUt7zn5s8g0IpuAqfucyqhPMQKAx4a8cT1FwjpvQKO7hkjz5a','Matias','Masih',NULL,NULL,NULL,NULL,'Finland','admin',1,'active','en',NULL,'2026-10-07 12:28:24','2026-10-05 08:36:13'),(3,'LIB-2026-00003','aziznoyan','aziz.noyan@outlook.com','$2y$10$ygGIo1mh3mleFtkJd4uD4OxdmfVRMWDC8uCLmxk9t1jFzFsJOQ552','Aziz','Rahman',NULL,NULL,NULL,NULL,'Finland','librarian',1,'active','en',NULL,'2026-10-06 16:00:06','2026-10-05 08:36:13'),(4,'LIB-2026-00004','alice','alice@patient.com','$2y$10$7qMT3KYQzNDRaPh654jsW.I/Enc6VspW6ePyuBYDMUdiFGwCfSJhS','Alice','Test',NULL,NULL,NULL,NULL,'Finland','member',1,'active','en',NULL,'2026-10-07 14:40:29','2026-10-05 08:36:13'),(5,'LIB-2026-00005','testuser','test@example.com','$2y$10$QLfBGa0ajPmqwwDr/zfSXO89eEiX2G/f1hZYbva3ozEqfCqE5hoFe','Test','User',NULL,NULL,NULL,NULL,'Finland','member',1,'active','en','u5_1791207687.jpeg','2026-10-05 16:26:01','2026-10-05 09:28:40');
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

-- Dump completed on 2026-10-07 15:21:16
