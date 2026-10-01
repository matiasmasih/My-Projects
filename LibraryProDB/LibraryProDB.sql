-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: LibraryProDB
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
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `books` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `is_borrowed` tinyint(1) NOT NULL DEFAULT '0',
  `cover_image` varchar(255) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `year` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books`
--

LOCK TABLES `books` WRITE;
/*!40000 ALTER TABLE `books` DISABLE KEYS */;
INSERT INTO `books` VALUES (8,'The Catcher in the Rye','J.D. Salinger',0,'uploads/covers/1750762140_0008231854-L.jpg','9780316769488','Fiction, Coming-of-Age',1951),(9,'Brave New World','Aldous Huxley',0,'uploads/covers/1750770590_0008772141-L.jpg','9780060850524','Science Fiction, Dystopian',1932),(10,'The Hobbit','J.R.R. Tolkien',0,'uploads/covers/1750770706_6979861-L.jpg','9780547928227','Fantasy',1937),(11,'Moby-Dick','Herman Melville',0,'uploads/covers/1750770808_0008109251-L.jpg','9780142437247','Adventure, Classic',1851),(12,'Pride and Prejudice','Jane Austen',0,'uploads/covers/1750770927_0008231856-L.jpg','9780141439518','Romance, Classic',1813),(13,'The Great Gatsby','F. Scott Fitzgerald',0,'uploads/covers/1750771028_7352168-L.jpg','9780743273565','Classic, Fiction',1925),(14,'1984','George Orwell',0,'uploads/covers/1750771132_7222246-L.jpg','9780451524935','Dystopian, Science Fiction',1949),(15,'To Kill a Mockingbird','Harper Lee',0,'uploads/covers/1750771226_0008225261-L.jpg','9780060935467','Fiction',1960),(16,'Sartor Resartus','Thomas Carlyle',0,'uploads/covers/1751199329_888548-L.jpg','1841952788','Philosophy / Literature',1898);
/*!40000 ALTER TABLE `books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `borrowed_books`
--

DROP TABLE IF EXISTS `borrowed_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `borrowed_books` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `borrow_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `return_date` datetime DEFAULT NULL,
  `status` enum('borrowed','returned') DEFAULT 'borrowed',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `borrowed_books`
--

LOCK TABLES `borrowed_books` WRITE;
/*!40000 ALTER TABLE `borrowed_books` DISABLE KEYS */;
INSERT INTO `borrowed_books` VALUES (1,3,14,'2025-07-01 13:10:56',NULL,'borrowed');
/*!40000 ALTER TABLE `borrowed_books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_borrowings`
--

DROP TABLE IF EXISTS `device_borrowings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_borrowings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_id` int NOT NULL,
  `borrow_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `return_date` datetime DEFAULT NULL,
  `status` enum('borrowed','returned') DEFAULT 'borrowed',
  PRIMARY KEY (`id`),
  KEY `idx_device` (`device_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `serial_number` varchar(255) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `year` int DEFAULT NULL,
  `is_borrowed` tinyint(1) NOT NULL DEFAULT '0',
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devices`
--

LOCK TABLES `devices` WRITE;
/*!40000 ALTER TABLE `devices` DISABLE KEYS */;
INSERT INTO `devices` VALUES (24,'Dell XPS 13 (9310)','13.4-inch FHD+, Intel Core i7, 16GB RAM, 512GB SSD. Ultralight premium ultrabook.','D-XPS13-2022-001','Laptop',2022,0,'1751197368_dell-xps-13-9310-i5.jpg'),(25,'MacBook Air M2 (2022)','Apple MacBook Air M2, 13.6-inch Liquid Retina, M2 chip, 8GB RAM, 256GB SSD. Lightweight and powerful.','MBA-M2-2022-002','Laptop',2022,0,'1751197447_apple-macbook-air-m2-2022.jpg'),(26,'Samsung Galaxy Tab S8','11-inch LTPS TFT, Snapdragon 8 Gen 1, S Pen included.','TAB-S8-SAM-2023-003','Tablet',2023,0,'1751197658_samsung-galaxy-tab-a9-5g.jpg'),(28,'Epson PowerLite X49 Projector','3600 lumens, XGA resolution, HDMI and VGA ports.','EPS-X49-2021-005','Projector',2021,1,'1751198534_8715946706849_2_fullHD.jpg'),(32,'Meta Quest 2 VR Headset','Standalone VR headset, 128GB storage, wireless controllers.','MQ2-OCULUS-2021-009','VR Headset',2021,0,'1751198241_head-elite-strap.jpg'),(33,'HTC Vive Pro 2','High-resolution VR headset, requires PC, adjustable head strap.','VIVE2-HTC-2022-010','VR Headset',2022,0,'1751198134_dv_web_D18000127412527.jpg'),(36,'Kindle Paperwhite (11th Gen)','6.8-inch display, adjustable warm light, waterproof, 8GB.','KPW-11G-2022-013','E-Reader',2022,0,'1751197863_amazon-kindle-2024-11th-gen-16gb-69941_reference.jpg'),(37,'Kobo Libra 2','7-inch E Ink Carta 1200 screen, 32GB storage, waterproof, Bluetooth support.','KOBO-LIBRA2-2022-014','E-Reader',2022,0,'1751197776_dv_web_D1800010021829898.jpg');
/*!40000 ALTER TABLE `devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('member','admin') DEFAULT 'member',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (8,'Ahmad','Ali','aziz.noyan@outlook.com','$2y$10$/QtvQi9NGUHlPQMTNxNfkONmuR00vOLEFPnbJiBGXOuqqgOG/Y3aq','member','2025-09-02 13:52:01','avatar_8.jpg'),(9,'Aziz','Noyan','aziznoyan50@gmail.com','$2y$10$duuryaKJaY7/A2QcKnilluWGN4zV8kbLoNCBmE0fjp8BOGvef7DkG','admin','2025-09-02 13:54:48','admin_avatar_9.jpg'),(10,'Aziz Rahman','Noyan','matiasmasih@gmail.com','$2y$10$FWrQwVzHDCJggvd86wVKUujfN5FIcQAbHUYFtQBZ13fbt48FLOWd6','member','2026-03-24 11:50:16',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `wishlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `book_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist`
--

LOCK TABLES `wishlist` WRITE;
/*!40000 ALTER TABLE `wishlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-22 18:31:47
