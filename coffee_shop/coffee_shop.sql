-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: coffee_shop
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
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `title_fi` varchar(500) DEFAULT NULL,
  `title_sv` varchar(500) DEFAULT NULL,
  `title_fa` varchar(500) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `content_fi` text,
  `content_sv` text,
  `content_fa` text,
  `excerpt` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `author` varchar(100) DEFAULT 'Admin',
  `published_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_published` tinyint(1) DEFAULT '1',
  `views` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`),
  KEY `idx_published` (`is_published`,`published_at`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blog_posts`
--

LOCK TABLES `blog_posts` WRITE;
/*!40000 ALTER TABLE `blog_posts` DISABLE KEYS */;
INSERT INTO `blog_posts` VALUES (1,'The Best Way to Start Your Morning','Paras tapa aloittaa aamusi','Det bästa sättet att börja din morgon','بهترین راه برای شروع صبح',NULL,'tips','Starting your morning with a cup of freshly brewed coffee is one of life\'s simple pleasures. The aroma fills your kitchen, the warmth comforts your hands, and the first sip wakes up your senses. Studies show that coffee drinkers are more alert, focused, and productive throughout the day. Whether you prefer a strong espresso, a creamy latte, or a simple black coffee, taking those few minutes to enjoy your brew sets a positive tone for the entire day. At Bean & Brew, we believe that every cup tells a story. From the farmers who grow our beans to the baristas who craft your drink, we are committed to excellence in every step. So tomorrow morning, take an extra moment to savor your coffee. You deserve it.','Aamun aloittaminen kupilla vastakeitettyä kahvia on yksi elämän yksinkertaisista nautinnoista. Tuoksu täyttää keittiösi, lämpö lohduttaa käsiäsi ja ensimmäinen kulaus herättää aistisi. Tutkimukset osoittavat, että kahvin juojat ovat valppaampia, keskittyneempiä ja tuottavampia koko päivän. Halusitpa sitten vahvaa espressoa, kermaista lattea tai yksinkertaista mustaa kahvia, ne muutamat minuutit nauttimaan juomasta luovat positiivisen sävyn koko päiväksi. Me Bean & Brew\'lla uskomme, että jokainen kuppi kertoo tarinan. Olemme sitoutuneet huippuosaamiseen jokaisessa vaiheessa papujamme kasvattavista viljelijöistä juomaasi valmistaviin baristoihin. Joten huomisaamuna käytä ylimääräinen hetki kahvisi maistelemiseen. Olet sen ansainnut.','Att börja din morgon med en kopp nybryggt kaffe är en av livets enkla nöjen. Doften fyller ditt kök, värmen tröstar dina händer och den första klunken väcker dina sinnen. Studier visar att kaffedrickare är mer pigga, fokuserade och produktiva under hela dagen. Oavsett om du föredrar en stark espresso, en krämig latte eller ett enkelt svart kaffe, att ta några minuter för att njuta av din bryggning ger en positiv ton för hela dagen. På Bean & Brew tror vi att varje kopp berättar en historia. Från bönderna som odlar våra bönor till baristorna som tillverkar din drink, vi är engagerade i excellens i varje steg. Så i morgon bitti, ta en extra stund för att njuta av ditt kaffe. Du förtjänar det.','شروع صبح با یک فنجان قهوه تازه دم کرده یکی از لذت های ساده زندگی است. عطر آشپزخانه شما را پر می کند، گرما به دستان شما آرامش می دهد و اولین جرعه حواس شما را بیدار می کند. مطالعات نشان می دهد که مصرف کنندگان قهوه در طول روز هوشیارتر، متمرکزتر و مولدتر هستند. خواه یک اسپرسوی قوی، یک لاته خامه ای یا یک قهوه سیاه ساده را ترجیح می دهید، صرف این چند دقیقه برای لذت بردن از دم کردن، لحن مثبتی برای کل روز ایجاد می کند. در Bean & Brew، ما معتقدیم که هر فنجان داستانی را بیان می کند. از کشاورزانی که حبوبات ما را پرورش می دهند تا باریستاهایی که نوشیدنی شما را درست می کنند، ما در هر مرحله به برتری متعهد هستیم. پس فردا صبح، یک لحظه بیشتر برای طعم قهوه خود وقت بگذارید. شما سزاوار آن هستید.',NULL,'','Aziz','2026-06-10 13:28:18',1,0,'2026-06-12 14:25:11','2026-06-12 14:25:11');
/*!40000 ALTER TABLE `blog_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chatbot_orders`
--

DROP TABLE IF EXISTS `chatbot_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chatbot_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `delivery_address` text,
  `items` text NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `payment_status` varchar(50) DEFAULT 'unpaid',
  `payment_link` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chatbot_orders`
--

LOCK TABLES `chatbot_orders` WRITE;
/*!40000 ALTER TABLE `chatbot_orders` DISABLE KEYS */;
INSERT INTO `chatbot_orders` VALUES (2,'CHAT7AB6FD43','aziz','aziz@gmail.com','0413114312','vaahtokuja 5 e 50 vantaa','[{\"name\": \"Green Tea\", \"price\": 3}]',3.00,'pending','unpaid',NULL,'2026-05-20 10:47:14',NULL),(4,'CHATFA00315F','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-20 10:59:00',NULL),(5,'CHATZ1OUND','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 11:33:45',NULL),(6,'CHATYX91JI','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 11:35:50',NULL),(7,'CHATI2OLPW','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 11:40:59',NULL),(8,'CHATBW0S34','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 11:44:14',NULL),(9,'CHATFWKPCW','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 11:53:17',NULL),(10,'CHAT2YDRDX','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Latte\", \"price\": 4.5, \"quantity\": 1}]',4.50,'pending','unpaid',NULL,'2026-05-28 13:00:03',NULL),(11,'CHAT5HL35M','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 13:36:26',NULL),(12,'CHAT0OC0O4','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 13:44:49',NULL),(13,'CHAT0ILCAI','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-28 13:52:05',NULL),(16,'CHATKNOQJG','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-31 09:19:43',NULL),(17,'CHATN66GEK','Chatbot Customer','chatbot@coffeeshop.com','00000000','pickup','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'pending','unpaid',NULL,'2026-05-31 09:22:13',NULL),(19,'CHAT43E4B6','Aziz Rahman Noyan','matiasmasih@gmail.com','358 413 114312','vaahtokuja 5 E50','[{\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}]',3.00,'completed','paid',NULL,'2026-05-31 10:47:08','cash'),(20,'CHATQZ0NKN','Aziz Rahman Noyan','matiasmasih@gmail.com','358 413 114312','pickup','[{\"name\": \"Latte\", \"price\": 4.5, \"quantity\": 1}, {\"name\": \"Green Tea\", \"price\": 3, \"quantity\": 1}, {\"name\": \"Hot Chocolate\", \"price\": 4, \"quantity\": 1}]',11.50,'pending','unpaid',NULL,'2026-06-01 14:58:07',NULL);
/*!40000 ALTER TABLE `chatbot_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT '0.00',
  `valid_until` datetime DEFAULT NULL,
  `usage_limit` int DEFAULT '1',
  `used_count` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coupons`
--

LOCK TABLES `coupons` WRITE;
/*!40000 ALTER TABLE `coupons` DISABLE KEYS */;
INSERT INTO `coupons` VALUES (1,'WELCOME10','percentage',10.00,10.00,'2026-06-30 14:29:35',100,0,1);
/*!40000 ALTER TABLE `coupons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_rewards`
--

DROP TABLE IF EXISTS `loyalty_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_rewards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `points_earned` int DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `loyalty_rewards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_rewards`
--

LOCK TABLES `loyalty_rewards` WRITE;
/*!40000 ALTER TABLE `loyalty_rewards` DISABLE KEYS */;
/*!40000 ALTER TABLE `loyalty_rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loyalty_transactions`
--

DROP TABLE IF EXISTS `loyalty_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loyalty_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `points` int NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `loyalty_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loyalty_transactions`
--

LOCK TABLES `loyalty_transactions` WRITE;
/*!40000 ALTER TABLE `loyalty_transactions` DISABLE KEYS */;
INSERT INTO `loyalty_transactions` VALUES (1,2,5,'Order ORDF2B63B6D','2026-05-24 10:44:12'),(2,2,4,'Order ORD2DC8A16A','2026-05-24 10:48:54'),(3,2,5,'Order ORDB2667F9B','2026-05-24 10:58:04'),(4,2,4,'Order ORD87DB035E','2026-05-28 11:16:30'),(5,2,3,'Order ORDA574A86D','2026-05-28 11:45:52'),(6,2,3,'Order ORD291E5972','2026-05-28 11:45:53'),(7,2,3,'Order ORDA471FDD6','2026-05-28 11:45:56'),(8,2,3,'Order ORD8302D441','2026-05-28 13:52:35'),(9,2,3,'Order ORD2736D32B','2026-05-28 15:29:59'),(10,2,3,'Order ORDD1E85439','2026-05-31 09:20:58'),(11,2,13,'Order ORD4B5907C2','2026-06-01 12:41:14'),(12,2,13,'Order ORD76942314','2026-06-01 12:41:16'),(13,2,13,'Order ORD459F42D6','2026-06-01 12:41:16'),(14,2,13,'Order ORDA06E42DE','2026-06-01 12:41:17');
/*!40000 ALTER TABLE `loyalty_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `user_message` text NOT NULL,
  `bot_response` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `newsletter_subscribers`
--

DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subscribed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint DEFAULT '1',
  `confirmation_token` varchar(100) DEFAULT NULL,
  `is_confirmed` tinyint DEFAULT '0',
  `unsubscribed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `newsletter_subscribers`
--

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `price_at_time` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,2,1,4.50),(2,2,33,1,5.50);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `delivery_address` text,
  `special_instructions` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_link` text,
  `coupon_code` varchar(50) DEFAULT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,2,'ORD6334CD4B',4.50,'preparing','paypal','Piikkikuja 3,','','2026-05-18 12:14:30',NULL,NULL,0.00),(2,2,'ORDF2B63B6D',5.50,'preparing','cash','Vaahtokuja 5 E50','','2026-05-24 10:44:12',NULL,NULL,0.00);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` varchar(50) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_reviews`
--

DROP TABLE IF EXISTS `product_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `product_reviews_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_reviews`
--

LOCK TABLES `product_reviews` WRITE;
/*!40000 ALTER TABLE `product_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `ingredients` text,
  `is_available` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `stock` int DEFAULT '10',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Espresso','Strong and rich Italian coffee',3.50,'coffee','https://images.pexels.com/photos/312418/pexels-photo-312418.jpeg?w=400&h=300&fit=crop','100% Arabica beans',1,'2026-05-15 09:20:31',10),(2,'Cappuccino','Espresso with steamed milk foam',4.50,'coffee','https://images.pexels.com/photos/414630/pexels-photo-414630.jpeg?w=400&h=300&fit=crop','Espresso, steamed milk, milk foam',1,'2026-05-15 09:20:31',10),(3,'Latte','Smooth espresso with steamed milk',4.50,'coffee','https://images.pexels.com/photos/2253643/pexels-photo-2253643.jpeg?w=400&h=300&fit=crop','Espresso, steamed milk, light foam',1,'2026-05-15 09:20:31',10),(4,'Mocha','Chocolate coffee delight',5.00,'coffee','https://images.pexels.com/photos/3020919/pexels-photo-3020919.jpeg?w=400&h=300&fit=crop','Espresso, chocolate, steamed milk',1,'2026-05-15 09:20:31',10),(5,'Caramel Macchiato','Vanilla and caramel with espresso',5.50,'coffee','https://images.pexels.com/photos/1659146/pexels-photo-1659146.jpeg?w=400&h=300&fit=crop','Espresso, milk, vanilla, caramel',1,'2026-05-15 09:20:31',10),(6,'Croissant','Buttery, flaky French pastry',3.00,'pastry','https://images.pexels.com/photos/324574/pexels-photo-324574.jpeg?w=400&h=300&fit=crop','Butter, flour, yeast',1,'2026-05-15 09:20:31',10),(7,'Blueberry Muffin','Fresh baked muffin with blueberries',3.50,'pastry','https://images.pexels.com/photos/1620916/pexels-photo-1620916.jpeg?w=400&h=300&fit=crop','Flour, blueberries, sugar, eggs',1,'2026-05-15 09:20:31',10),(8,'Chocolate Chip Cookie','Soft and chewy cookie',2.50,'pastry','https://images.pexels.com/photos/6199656/pexels-photo-6199656.jpeg?w=400&h=300&fit=crop','Flour, chocolate chips, butter, sugar',1,'2026-05-15 09:20:31',10),(9,'Green Tea','Premium Japanese green tea',3.00,'tea','https://images.pexels.com/photos/2111651/pexels-photo-2111651.jpeg?w=400&h=300&fit=crop','Green tea leaves',1,'2026-05-15 09:20:31',10),(10,'Hot Chocolate','Rich and creamy hot chocolate',4.00,'other_drinks','https://images.pexels.com/photos/2065686/pexels-photo-2065686.jpeg?w=400&h=300&fit=crop','Cocoa, milk, sugar',1,'2026-05-15 09:20:31',10),(11,'Cold Brew','Smooth cold brewed coffee',4.50,'coffee','https://images.pexels.com/photos/2668506/pexels-photo-2668506.jpeg?w=400&h=300&fit=crop','Slow-steeped coffee beans',1,'2026-05-15 09:20:31',10),(12,'Chai Latte','Spiced tea with steamed milk',4.50,'tea','https://images.pexels.com/photos/312416/pexels-photo-312416.jpeg?w=400&h=300&fit=crop','Black tea, spices, milk',1,'2026-05-15 09:20:31',10),(13,'Pumpkin Spice Latte','Fall favorite with pumpkin spice and whipped cream',5.50,'coffee',NULL,'Espresso, milk, pumpkin spice syrup, whipped cream',1,'2026-05-18 09:12:00',10),(14,'Peppermint Mocha','Festive peppermint chocolate coffee',5.50,'coffee',NULL,'Espresso, chocolate, peppermint syrup, whipped cream',1,'2026-05-18 09:12:00',10),(15,'Salted Caramel Latte','Sweet and salty caramel latte',5.50,'coffee',NULL,'Espresso, milk, caramel sauce, sea salt',1,'2026-05-18 09:12:00',10),(16,'Irish Coffee','Whiskey-infused coffee with cream',7.50,'coffee',NULL,'Coffee, Irish whiskey, cream, sugar',1,'2026-05-18 09:12:00',10),(17,'Vanilla Latte','Smooth latte with vanilla syrup',5.00,'coffee',NULL,'Espresso, steamed milk, vanilla syrup',1,'2026-05-18 09:12:00',10),(18,'Hazelnut Cappuccino','Nutty cappuccino with hazelnut flavor',5.00,'coffee',NULL,'Espresso, milk foam, hazelnut syrup',1,'2026-05-18 09:12:00',10),(19,'Matcha Latte','Japanese matcha green tea with steamed milk',5.00,'tea',NULL,'Matcha powder, steamed milk, honey',1,'2026-05-18 09:12:19',10),(20,'London Fog','Earl Grey tea with vanilla and steamed milk',4.50,'tea',NULL,'Earl Grey tea, vanilla syrup, steamed milk',1,'2026-05-18 09:12:19',10),(21,'Peach Iced Tea','Refreshing peach flavored iced tea',4.00,'tea',NULL,'Black tea, peach syrup, ice, lemon',1,'2026-05-18 09:12:19',10),(22,'Cinnamon Roll','Soft roll with cinnamon glaze and icing',3.50,'pastry',NULL,'Dough, cinnamon, sugar, cream cheese icing',1,'2026-05-18 09:12:36',10),(23,'Breakfast Sandwich','Egg, cheese, and ham on fresh croissant',6.50,'pastry',NULL,'Croissant, egg, cheddar cheese, ham',1,'2026-05-18 09:12:36',10),(25,'Yogurt Parfait','Greek yogurt with granola and mixed berries',5.50,'pastry',NULL,'Greek yogurt, granola, strawberries, blueberries, honey',1,'2026-05-18 09:12:36',10),(26,'Chocolate Cake','Rich chocolate layer cake',4.50,'pastry',NULL,'Chocolate, flour, eggs, butter, ganache',1,'2026-05-18 09:12:36',10),(27,'Cheese Danish','Flaky pastry with cream cheese filling',3.50,'pastry',NULL,'Pastry dough, cream cheese, sugar, vanilla',1,'2026-05-18 09:12:36',10),(28,'Tiramisu','Classic Italian coffee dessert',6.00,'pastry',NULL,'Ladyfingers, espresso, mascarpone, cocoa powder',1,'2026-05-18 09:12:52',10),(29,'Cheesecake','New York style cheesecake with berry sauce',5.50,'pastry',NULL,'Cream cheese, graham cracker crust, sugar, eggs, berry sauce',1,'2026-05-18 09:12:52',10),(30,'Apple Pie','Homemade apple pie slice',4.00,'pastry',NULL,'Apples, cinnamon, pastry crust, sugar',1,'2026-05-18 09:12:52',10),(32,'Vanilla Milkshake','Creamy vanilla milkshake with whipped cream',5.50,'other_drinks',NULL,'Ice cream, milk, vanilla syrup, whipped cream',1,'2026-05-22 12:44:06',10),(33,'Strawberry Smoothie','Fresh strawberry smoothie',5.50,'other_drinks',NULL,'Strawberries, yogurt, honey, ice',1,'2026-05-22 12:44:06',10),(34,'Mango Smoothie','Tropical mango smoothie',5.50,'other_drinks',NULL,'Mango, yogurt, honey, ice',1,'2026-05-22 12:44:06',10),(35,'Iced Chocolate','Cold chocolate drink with ice cream',5.00,'other_drinks',NULL,'Chocolate syrup, milk, ice cream, ice',1,'2026-05-22 12:44:06',10),(36,'Banana Smoothie','Creamy banana smoothie made with fresh bananas and yogurt',5.50,'other_drinks',NULL,'Fresh bananas, yogurt, honey, ice, milk',1,'2026-05-24 10:15:34',10);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `referrals`
--

DROP TABLE IF EXISTS `referrals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referrals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_user_id` int NOT NULL,
  `points_earned` int DEFAULT '100',
  `status` varchar(20) DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_user_id` (`referred_user_id`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `referrals`
--

LOCK TABLES `referrals` WRITE;
/*!40000 ALTER TABLE `referrals` DISABLE KEYS */;
/*!40000 ALTER TABLE `referrals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `rating` int DEFAULT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_chk_1` CHECK (((`rating` >= 1) and (`rating` <= 5)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reviews`
--

LOCK TABLES `reviews` WRITE;
/*!40000 ALTER TABLE `reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rewards`
--

DROP TABLE IF EXISTS `rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rewards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `name_fi` varchar(100) DEFAULT NULL,
  `name_sv` varchar(100) DEFAULT NULL,
  `name_fa` varchar(100) DEFAULT NULL,
  `points_required` int DEFAULT NULL,
  `description` text,
  `description_fi` varchar(255) DEFAULT NULL,
  `description_sv` varchar(255) DEFAULT NULL,
  `description_fa` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rewards`
--

LOCK TABLES `rewards` WRITE;
/*!40000 ALTER TABLE `rewards` DISABLE KEYS */;
INSERT INTO `rewards` VALUES (1,'Free Coffee','Ilmainen kahvi','Gratis kaffe','قهوه رایگان',100,'Any regular coffee drink','Mikä tahansa tavallinen kahvijuoma','Valfri vanlig kaffedryck','هر نوشیدنی قهوه معمولی',1),(2,'Free Pastry','Ilmainen leivonnainen','Gratis bakverk','شیرینی رایگان',50,'Any pastry item','Mikä tahansa leivonnainen','Valfri bakelse','هر نوع شیرینی',1),(3,'10% Off','10% alennus','10% rabatt','۱۰٪ تخفیف',200,'10% discount on entire order','10% alennus koko tilauksesta','10% rabatt på hela ordern','۱۰٪ تخفیف روی کل سفارش',1),(4,'Free Drink Upgrade','Ilmainen koko päivitys','Gratis storleksuppgradering','ارتقاء سایز رایگان',75,'Upgrade to large size for free','Päivitä isompaan kokoon ilmaiseksi','Uppgradera till storlek gratis','ارتقاء به سایز بزرگ به صورت رایگان',1);
/*!40000 ALTER TABLE `rewards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `team_members`
--

DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `name_fi` varchar(100) DEFAULT NULL,
  `name_sv` varchar(100) DEFAULT NULL,
  `name_fa` varchar(100) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `position_fi` varchar(100) DEFAULT NULL,
  `position_sv` varchar(100) DEFAULT NULL,
  `position_fa` varchar(100) DEFAULT NULL,
  `bio` text,
  `bio_fi` text,
  `bio_sv` text,
  `bio_fa` text,
  `image_url` varchar(255) DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `is_active` tinyint DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `team_members`
--

LOCK TABLES `team_members` WRITE;
/*!40000 ALTER TABLE `team_members` DISABLE KEYS */;
INSERT INTO `team_members` VALUES (1,'Aziz Rahman','Aziz Rahman','Aziz Rahman','عزیز رحمان','Founder & Master Roaster','Perustaja & Mestaripaahtaja','Grundare & Mästarroster','بنیان‌گذار و برشته‌کار ماهر','Coffee enthusiast with over 15 years of experience. Aziz personally selects and roasts every batch to ensure the highest quality.','Kahviharrastaja, jolla on yli 15 vuoden kokemus. Aziz valitsee ja paahtaa henkilökohtaisesti jokaisen erän varmistaakseen korkeimman laadun.','Kaffeentusiast med över 15 års erfarenhet. Aziz väljer personligen ut och rost varje sats för att säkerställa högsta kvalitet.','عاشق قهوه با بیش از ۱۵ سال تجربه. عزیز شخصاً هر دسته را انتخاب و برشته می‌کند تا بالاترین کیفیت را تضمین کند.',NULL,1,1,'2026-06-11 12:48:07'),(2,'Maria Lahtinen','Maria Lahtinen','Maria Lahtinen','ماریا لاهتینن','Head Barista','Head Barista','Head Barista','باریستای ارشد','Award-winning barista with a passion for latte art and creating memorable coffee experiences for every customer.','Palkittu barista, jolla on intohimo latte-taiteeseen ja unohtumattomien kahvikokemusten luomiseen jokaiselle asiakkaalle.','Prisbelönt barista med en passion för lattekonst och att skapa minnesvärda kaffeupplevelser för varje kund.','باریستای برنده جوایز با اشتیاق به هنر لاته و ایجاد تجارب به یاد ماندنی قهوه برای هر مشتری.',NULL,2,1,'2026-06-11 12:48:07'),(3,'Johan Andersson','Johan Andersson','Johan Andersson','یوهان آندرشون','Coffee Sourcing Manager','Kahvin hankintapäällikkö','Kaffeanskaffningschef','مدیر تأمین قهوه','Travels the world to source the finest beans directly from farmers, ensuring fair trade and sustainable practices.','Matkustaa ympäri maailmaa hankkimaan parhaat pavut suoraan viljelijöiltä varmistaen reilun kaupan ja kestävät käytännöt.','Reser världen runt för att köpa de finaste bönorna direkt från bönder, vilket säkerställer fair trade och hållbara metoder.','برای تهیه بهترین دانه‌ها مستقیماً از کشاورزان به سراسر جهان سفر می‌کند و تجارت عادلانه و شیوه‌های پایدار را تضمین می‌کند.',NULL,3,1,'2026-06-11 12:48:07');
/*!40000 ALTER TABLE `team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_favorites`
--

DROP TABLE IF EXISTS `user_favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_favorites` (
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `user_favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_favorites`
--

LOCK TABLES `user_favorites` WRITE;
/*!40000 ALTER TABLE `user_favorites` DISABLE KEYS */;
INSERT INTO `user_favorites` VALUES (2,2,'2026-05-19 12:42:20'),(2,5,'2026-05-27 09:10:44'),(2,9,'2026-05-27 09:11:12'),(2,10,'2026-05-19 12:43:00'),(2,15,'2026-05-19 12:42:33'),(2,16,'2026-05-27 09:10:54'),(2,21,'2026-05-19 12:42:52'),(2,23,'2026-05-27 09:10:56'),(2,28,'2026-05-19 12:42:45'),(6,2,'2026-06-17 14:04:18'),(6,3,'2026-06-17 14:04:20'),(6,14,'2026-06-17 14:04:24'),(6,15,'2026-06-17 14:04:26'),(6,18,'2026-06-17 14:04:33');
/*!40000 ALTER TABLE `user_favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `loyalty_points` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_admin` tinyint(1) DEFAULT '0',
  `profile_picture` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `address` text,
  `newsletter` tinyint(1) DEFAULT '0',
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` int DEFAULT NULL,
  `referral_points` int DEFAULT '0',
  `last_birthday_redeemed` year DEFAULT NULL,
  `role` varchar(20) DEFAULT 'customer',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'Masih','matiasmasih@gmail.com','$2b$12$MT8nPBFC2Lk/IcS3SsWhZegeG/f03j7pG0wW49HpWfeQp3LTaUQvW','Aziz Rahman Noyan','358 413 114312',88,'2026-05-18 09:08:41',0,'/static/uploads/avatar_2.jpg','1997-03-31',NULL,0,'COFFEE734E22',NULL,0,NULL,'employee'),(6,'Aziz','aziznoyan50@gmail.com','$2b$12$qgWYZtiNsfaD0zccSuJCD.hL6uk6zkrhXsOWE2JtU0mUTDpYFFMJC',NULL,NULL,0,'2026-05-22 10:58:30',1,NULL,NULL,NULL,0,'ADMIN51158E',NULL,0,NULL,'admin');
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

-- Dump completed on 2026-06-22 18:32:57
