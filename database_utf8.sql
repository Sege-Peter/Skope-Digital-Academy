-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: skopedigital
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
-- Table structure for table `announcements`
--

DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_pinned` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `announcements`
--

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignment_submissions`
--

DROP TABLE IF EXISTS `assignment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('pending','graded') DEFAULT 'pending',
  `submitted_at` datetime DEFAULT current_timestamp(),
  `graded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignment_submissions`
--

LOCK TABLES `assignment_submissions` WRITE;
/*!40000 ALTER TABLE `assignment_submissions` DISABLE KEYS */;
INSERT INTO `assignment_submissions` VALUES (1,1,5,'SUB_5_1_1773779489.pdf','',100.00,'Tutam','graded','2026-03-17 23:31:29','2026-03-17 23:35:34');
/*!40000 ALTER TABLE `assignment_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `max_score` int(11) DEFAULT 100,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,3,'Assignment: Introduction to Software Development','Software development is the process of creating computer programs and applications that help users perform specific tasks. It involves a series of steps such as planning, designing, coding, testing, and maintaining software systems. In todayΓÇÖs digital world, software development plays a major role in almost every sector, including education, business, healthcare, and entertainment.','2026-03-17 00:00:00',100,'2026-03-17 23:28:37');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `badges`
--

DROP TABLE IF EXISTS `badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT '?',
  `color` varchar(20) DEFAULT '#F7941D',
  `criteria_type` enum('courses_completed','lessons_completed','quizzes_passed','points_earned','manually_awarded') DEFAULT 'courses_completed',
  `criteria_value` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badges`
--

LOCK TABLES `badges` WRITE;
/*!40000 ALTER TABLE `badges` DISABLE KEYS */;
INSERT INTO `badges` VALUES (1,'Referral Hero','Referred 5 successful learners','≡ƒñ¥','#00BFFF','',5,'2026-03-18 13:27:08'),(2,'Academic Elite','Completed 10 courses','≡ƒÅà','#FF8C00','courses_completed',10,'2026-03-18 13:27:08'),(3,'Quiz Champion','Passed 20 quizzes','≡ƒÅå','#10B981','quizzes_passed',20,'2026-03-18 13:27:08'),(4,'Loyal Student','Referred 1st student','Γ£¿','#3B82F6','',1,'2026-03-18 13:27:08'),(5,'Merit Multiplier','Earned 1000 merit coins','≡ƒÆ░','#FBBF24','',1000,'2026-03-18 13:27:08');
/*!40000 ALTER TABLE `badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `icon` varchar(50) DEFAULT 'fas fa-book',
  `color` varchar(20) DEFAULT '#00AEEF',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Technology','technology','fas fa-laptop-code','#EC4899','2026-03-17 15:23:22'),(2,'Business','business','fas fa-briefcase','#F7941D','2026-03-17 15:23:22'),(3,'Design','design','fas fa-palette','#9b59b6','2026-03-17 15:23:22'),(4,'Artificial Inteligence','artificial-inteligence','fas fa-rocket','#6366F1','2026-03-18 12:20:15'),(6,'Financial Literacy','financial-literacy','fas fa-bank','#F59E0B','2026-03-18 16:51:24');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `certificate_url` varchar(500) DEFAULT NULL,
  `verification_code` varchar(50) DEFAULT NULL,
  `issued_at` datetime DEFAULT current_timestamp(),
  `issued_by` int(11) DEFAULT NULL,
  `issued_by_role` enum('admin','tutor','system') DEFAULT 'system',
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','revoked') DEFAULT 'approved',
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_code` (`verification_code`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `fk_cert_issuer` (`issued_by`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_issuer` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,5,3,NULL,'7BB43F53877E','2026-03-18 02:04:35',4,'tutor','','approved'),(2,5,2,NULL,'FB1D23C0733C','2026-03-18 12:11:34',4,'admin','you did amazing','approved');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_ratings`
--

DROP TABLE IF EXISTS `course_ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `review` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rating` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `course_ratings_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_ratings_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_ratings`
--

LOCK TABLES `course_ratings` WRITE;
/*!40000 ALTER TABLE `course_ratings` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tutor_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(280) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `price` decimal(10,2) DEFAULT 0.00,
  `thumbnail` varchar(255) DEFAULT NULL,
  `preview_video` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending','published','archived') DEFAULT 'draft',
  `duration_hours` decimal(5,1) DEFAULT 0.0,
  `total_lessons` int(11) DEFAULT 0,
  `enrolled_count` int(11) DEFAULT 0,
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tutor_id` (`tutor_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,4,3,'TEST COURSE',NULL,'This is a test course','beginner',150.00,'COURSE_1.png',NULL,'published',0.0,2,1,0.00,'2026-03-17 19:04:36'),(2,4,3,'Literature',NULL,'English/Kiswahili','advanced',15000.00,'COURSE_2.png',NULL,'published',0.0,0,2,0.00,'2026-03-17 19:43:02'),(3,4,1,'SOFTWARE DEVELOPMENT',NULL,'Frontend Developer','beginner',1500.00,'COURSE_3.png',NULL,'published',0.0,5,2,0.00,'2026-03-17 23:15:24');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('active','pending','completed','cancelled') DEFAULT 'active',
  `progress_percent` decimal(5,2) DEFAULT 0.00,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,4,2,'pending',0.00,'2026-03-17 19:54:26',NULL),(2,5,1,'active',100.00,'2026-03-17 20:04:04',NULL),(4,5,2,'active',0.00,'2026-03-17 20:09:59',NULL),(6,5,3,'active',80.00,'2026-03-17 23:16:38',NULL),(8,7,3,'active',0.00,'2026-03-18 13:37:10',NULL),(10,9,2,'active',0.00,'2026-03-18 17:02:16',NULL);
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_progress`
--

DROP TABLE IF EXISTS `lesson_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `time_spent_mins` int(11) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_progress` (`student_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `lesson_progress_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lesson_progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_progress`
--

LOCK TABLES `lesson_progress` WRITE;
/*!40000 ALTER TABLE `lesson_progress` DISABLE KEYS */;
INSERT INTO `lesson_progress` VALUES (1,5,1,1,'completed',0,'2026-03-17 22:06:59'),(2,5,2,1,'completed',0,'2026-03-17 23:08:35'),(3,5,3,3,'completed',0,'2026-03-17 23:56:12'),(4,5,6,3,'in_progress',0,NULL),(5,5,7,3,'completed',0,'2026-03-17 23:58:47'),(6,5,4,3,'completed',0,'2026-03-18 02:08:07'),(7,5,5,3,'completed',0,'2026-03-18 02:08:18');
/*!40000 ALTER TABLE `lesson_progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `lesson_type` enum('video','pdf','audio','text','image') DEFAULT 'text',
  `file_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `order_num` int(11) DEFAULT 0,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `duration_mins` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (1,1,'Introduction','','video',NULL,'',1,1,15,'2026-03-17 19:05:41'),(2,1,'topic one','','video',NULL,'',2,1,15,'2026-03-17 19:05:55'),(3,3,'Introduction to Software Development','Introduction to Software Development\r\n\r\nSoftware development is the process of designing, creating, testing, and maintaining applications, systems, or programs that run on computers and other devices. ItΓÇÖs the backbone of everything digitalΓÇöfrom mobile apps and websites to operating systems and business tools.\r\n\r\n≡ƒö╣ What Software Development Involves\r\n\r\nAt its core, software development follows a structured process often called the Software Development Life Cycle (SDLC), which includes:\r\n\r\nPlanning ΓÇô Understanding the problem and defining goals\r\n\r\nAnalysis ΓÇô Gathering requirements and user needs\r\n\r\nDesign ΓÇô Creating system architecture and interface layouts\r\n\r\nDevelopment (Coding) ΓÇô Writing the actual code\r\n\r\nTesting ΓÇô Finding and fixing bugs\r\n\r\nDeployment ΓÇô Releasing the software to users\r\n\r\nMaintenance ΓÇô Updating and improving the system over time\r\n\r\n≡ƒö╣ Types of Software Development\r\n\r\nWeb Development ΓÇô Building websites and web apps\r\n\r\nMobile Development ΓÇô Creating apps for Android and iOS\r\n\r\nDesktop Applications ΓÇô Software for computers (e.g., Windows apps)\r\n\r\nGame Development ΓÇô Designing and developing video games\r\n\r\nEmbedded Systems ΓÇô Software for hardware devices (e.g., IoT)\r\n\r\n≡ƒö╣ Common Programming Languages\r\n\r\nJavaScript ΓÇô Web development\r\n\r\nPython ΓÇô Data science, AI, and backend systems\r\n\r\nJava ΓÇô Enterprise and Android apps\r\n\r\nC++ / C ΓÇô System-level programming\r\n\r\nPHP ΓÇô Web backend development\r\n\r\n≡ƒö╣ Key Skills Needed\r\n\r\nProblem-solving and logical thinking\r\n\r\nUnderstanding algorithms and data structures\r\n\r\nVersion control (e.g., Git)\r\n\r\nCommunication and teamwork\r\n\r\nContinuous learning\r\n\r\n≡ƒö╣ Why It Matters\r\n\r\nSoftware development powers modern lifeΓÇöbanking systems, education platforms, healthcare, entertainment, and even social media. ItΓÇÖs a highly in-demand field with opportunities for innovation, creativity, and impact.\r\n\r\n≡ƒÜÇ Simple Takeaway\r\n\r\nSoftware development is not just about writing codeΓÇöitΓÇÖs about solving real-world problems using technology.','video','',NULL,1,1,15,'2026-03-17 23:45:22'),(4,3,'Software Development Life Cycle (SDLC)','1. Introduction\r\n\r\nThe Software Development Life Cycle (SDLC) is a structured process used by developers to design, build, and maintain high-quality software. It ensures that software is developed in a systematic and efficient way, reducing errors and improving reliability.\r\n\r\n2. Phases of SDLC\r\n1. Planning\r\n\r\nThis is the first stage where the project idea is defined. Developers and stakeholders identify:\r\n\r\nThe problem to be solved\r\n\r\nProject goals\r\n\r\nBudget and timeline\r\n\r\n2. Requirement Analysis\r\n\r\nIn this phase, developers gather detailed information about what users need.\r\n\r\nFunctional requirements (what the system should do)\r\n\r\nNon-functional requirements (performance, security, usability)\r\n\r\n3. Design\r\n\r\nThe system structure is created:\r\n\r\nArchitecture design (how components interact)\r\n\r\nUser interface design (how it looks and feels)\r\n\r\n4. Implementation (Coding)\r\n\r\nDevelopers write the actual code using programming languages like Java, Python, or JavaScript.\r\nThis is where the system is built.\r\n\r\n5. Testing\r\n\r\nThe software is tested to identify and fix errors (bugs).\r\nTypes of testing include:\r\n\r\nUnit testing\r\n\r\nIntegration testing\r\n\r\nSystem testing\r\n\r\n6. Deployment\r\n\r\nThe finished software is released to users.\r\nIt can be deployed on:\r\n\r\nWeb servers\r\n\r\nMobile platforms\r\n\r\nDesktop systems\r\n\r\n7. Maintenance\r\n\r\nAfter deployment, the software is continuously updated:\r\n\r\nFixing bugs\r\n\r\nImproving performance\r\n\r\nAdding new features\r\n\r\n3. Importance of SDLC\r\n\r\nEnsures quality software\r\n\r\nReduces development risks\r\n\r\nImproves project management\r\n\r\nSaves time and cost\r\n\r\n4. Common SDLC Models\r\n\r\nWaterfall Model ΓÇô Linear and step-by-step\r\n\r\nAgile Model ΓÇô Flexible and iterative\r\n\r\nSpiral Model ΓÇô Focuses on risk analysis\r\n\r\nV-Model ΓÇô Testing at every stage\r\n\r\n5. Conclusion\r\n\r\nThe SDLC is essential in software development as it provides a clear roadmap from idea to final product. Following these steps ensures the software is reliable, efficient, and meets user needs.\r\n\r\nΓ£ì∩╕Å Quick Tip for You (as a developer)\r\n\r\nSince youΓÇÖre into web development, start practicing SDLC by:\r\n\r\nPlanning small projects (like a portfolio site)\r\n\r\nDesigning before coding\r\n\r\nTesting your work before publishing','video','',NULL,2,1,15,'2026-03-17 23:46:55'),(5,3,'Programming Languages and Tools','1. Introduction\r\n\r\nProgramming languages and tools are essential in software development. They enable developers to communicate with computers and build functional applications. Choosing the right language and tools depends on the type of software being developed.\r\n\r\n2. What is a Programming Language?\r\n\r\nA programming language is a formal set of instructions used to produce various kinds of output, such as software applications, websites, or mobile apps.\r\n\r\n3. Types of Programming Languages\r\n1. High-Level Languages\r\n\r\nThese are easy to read and write, closer to human language.\r\nExamples:\r\n\r\nPython\r\n\r\nJava\r\n\r\nJavaScript\r\n\r\n2. Low-Level Languages\r\n\r\nThese are closer to machine language and hardware.\r\nExamples:\r\n\r\nAssembly Language\r\n\r\nMachine Code\r\n\r\n3. Object-Oriented Languages (OOP)\r\n\r\nThese organize code into objects and classes.\r\nExamples:\r\n\r\nJava\r\n\r\nC++\r\n\r\nPython\r\n\r\n4. Scripting Languages\r\n\r\nUsed for automating tasks and web development.\r\nExamples:\r\n\r\nJavaScript\r\n\r\nPHP\r\n\r\n4. Common Programming Languages and Their Uses\r\n\r\nJavaScript ΓÇô Web development (frontend and backend)\r\n\r\nPython ΓÇô Data science, AI, and backend systems\r\n\r\nJava ΓÇô Android apps and enterprise systems\r\n\r\nC++ ΓÇô Game development and system software\r\n\r\nPHP ΓÇô Server-side web development\r\n\r\n5. Development Tools\r\n1. Code Editors and IDEs\r\n\r\nVisual Studio Code\r\n\r\nIntelliJ IDEA\r\n\r\nSublime Text\r\n\r\n2. Version Control Systems\r\n\r\nGit\r\n\r\nGitHub (for hosting and collaboration)\r\n\r\n3. Debugging Tools\r\n\r\nUsed to identify and fix errors in code.\r\n\r\n4. Frameworks and Libraries\r\n\r\nReact (JavaScript)\r\n\r\nDjango (Python)\r\n\r\nLaravel (PHP)\r\n\r\n6. Importance of Choosing the Right Tools\r\n\r\nImproves productivity\r\n\r\nEnhances code quality\r\n\r\nMakes collaboration easier\r\n\r\nSpeeds up development\r\n\r\n7. Conclusion\r\n\r\nProgramming languages and tools are the foundation of software development. A good developer should understand different languages and know when and how to use the right tools for a project.\r\n\r\n≡ƒÜÇ Quick Tip for You\r\n\r\nSince you\'re aiming to be a full-stack developer:\r\n\r\nFocus on JavaScript (React + Node.js)\r\n\r\nPractice using GitHub for projects\r\n\r\nExplore frameworks to speed up your work','video','',NULL,3,1,15,'2026-03-17 23:48:02'),(6,3,'≡ƒÄ» QUIZ CHALLENGE','≡ƒºá Round 1: Quick Fire (Easy)\r\n\r\nWhat does SDLC stand for?\r\n\r\nWhich language is mainly used for web interactivity?\r\n\r\nName one phase where bugs are fixed.\r\n\r\nIs Python a high-level or low-level language?\r\n\r\nWhat tool is used for version control?\r\n\r\nΓÜí Round 2: Intermediate\r\n\r\nWhich SDLC model is flexible and iterative?\r\n\r\nWhat is the main purpose of the design phase?\r\n\r\nGive one example of a frontend framework.\r\n\r\nWhat does IDE stand for?\r\n\r\nWhich language is commonly used for Android development?\r\n\r\n≡ƒÜÇ Round 3: Challenge Mode\r\n\r\nExplain the difference between frontend and backend development.\r\n\r\nWhy is testing important in software development?\r\n\r\nName two advantages of using GitHub.\r\n\r\nWhat is a framework in programming?\r\n\r\nDescribe one real-life application of software development.\r\n\r\n≡ƒÅå Scoring System\r\n\r\n1ΓÇô5 correct ΓåÆ Beginner ≡ƒƒó\r\n\r\n6ΓÇô10 correct ΓåÆ Intermediate ≡ƒö╡\r\n\r\n11ΓÇô15 correct ΓåÆ Pro Developer ≡ƒöÑ\r\n\r\n≡ƒÆí Bonus Challenge\r\n\r\n≡ƒæë In one sentence:\r\nWhat kind of developer do you want to become?','','',NULL,4,1,15,'2026-03-17 23:49:33'),(7,3,'Testing and Debugging in Software Development','Introduction\r\n\r\nTesting and debugging are critical steps in software development that ensure a system works correctly and efficiently. They help identify errors (bugs) and improve the quality of the software before it is released to users.\r\n\r\n2. What is Software Testing?\r\n\r\nSoftware testing is the process of evaluating a system to check whether it meets the required specifications and is free from defects.\r\n\r\n3. Types of Software Testing\r\n1. Unit Testing\r\n\r\nTests individual components or functions\r\n\r\nDone by developers\r\n\r\n2. Integration Testing\r\n\r\nTests how different parts of the system work together\r\n\r\n3. System Testing\r\n\r\nTests the complete system as a whole\r\n\r\n4. User Acceptance Testing (UAT)\r\n\r\nDone by users to confirm the system meets their needs\r\n\r\n4. What is Debugging?\r\n\r\nDebugging is the process of identifying, analyzing, and fixing errors in a program after they have been detected during testing.\r\n\r\n5. Common Types of Errors (Bugs)\r\n\r\nSyntax Errors ΓÇô Mistakes in code structure (e.g., missing brackets)\r\n\r\nLogical Errors ΓÇô Code runs but gives wrong results\r\n\r\nRuntime Errors ΓÇô Errors that occur during execution\r\n\r\n6. Tools for Testing and Debugging\r\n\r\nDebuggers (built into IDEs like VS Code)\r\n\r\nBrowser Developer Tools\r\n\r\nTesting frameworks (e.g., Jest for JavaScript)\r\n\r\n7. Importance of Testing and Debugging\r\n\r\nImproves software quality\r\n\r\nEnhances user satisfaction\r\n\r\nPrevents system failures\r\n\r\nSaves time and cost in the long run\r\n\r\n8. Best Practices\r\n\r\nTest early and regularly\r\n\r\nWrite clean and simple code\r\n\r\nUse automated testing tools\r\n\r\nFix bugs immediately after detection\r\n\r\n9. Conclusion\r\n\r\nTesting and debugging are essential for delivering reliable and efficient software. A good developer not only writes code but also ensures that it works correctly under all conditions.\r\n\r\n≡ƒÜÇ Quick Tip for You\r\n\r\nAs a developer:\r\n\r\nAlways test your projects before publishing\r\n\r\nUse browser dev tools when working with websites\r\n\r\nLearn debuggingΓÇöitΓÇÖs what separates beginners from pros','video','https://youtu.be/tNLY9gnYWSY',NULL,5,1,25,'2026-03-17 23:53:47');
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `user_role` enum('all','admin','tutor','student') DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'Scholarship Decision','Your application for the scholarship has been APPROVED. Check your dashboard for details.','student',5,0,'2026-03-17 20:42:49'),(2,'Scholarship Decision','Your application for the scholarship has been APPROVED. Check your dashboard for details.','student',5,0,'2026-03-17 20:43:50'),(3,'New Assignment Submission','A student has submitted work for one of your assignments.','tutor',4,0,'2026-03-17 23:31:29'),(4,'Assignment Graded','Your assignment submission has been graded. Check your dashboard for feedback.','student',5,0,'2026-03-17 23:35:34'),(5,'Hello','see you soon','all',NULL,0,'2026-03-18 12:06:54'),(6,'HELLO','HOW ARE YOU?','student',NULL,0,'2026-03-18 22:37:08');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','verified','failed') DEFAULT 'pending',
  `transaction_message` text DEFAULT NULL,
  `proof_file` varchar(255) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,4,2,15000.00,'failed','wertyuio','PROOF_4_1773766466.png',NULL,'2026-03-17 19:54:26',NULL),(2,5,1,150.00,'verified','','PROOF_5_1773767044.jpeg',4,'2026-03-17 20:04:04','2026-03-17 20:08:55'),(3,5,2,15000.00,'verified','','PROOF_5_1773767399.png',4,'2026-03-17 20:09:59','2026-03-17 20:14:18'),(4,5,3,1500.00,'verified','','PROOF_5_1773778598.png',4,'2026-03-17 23:16:38','2026-03-17 23:30:44'),(5,7,3,1500.00,'verified','we5sdf','PROOF_7_1773830230.png',4,'2026-03-18 13:37:10','2026-03-18 13:39:28'),(6,9,2,15000.00,'verified','','PROOF_9_1773842536.png',1,'2026-03-18 17:02:16','2026-03-18 17:03:00');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `point_ledger`
--

DROP TABLE IF EXISTS `point_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `point_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `awarded_by` int(11) DEFAULT NULL,
  `awarded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `awarded_by` (`awarded_by`),
  CONSTRAINT `point_ledger_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `point_ledger_ibfk_2` FOREIGN KEY (`awarded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `point_ledger`
--

LOCK TABLES `point_ledger` WRITE;
/*!40000 ALTER TABLE `point_ledger` DISABLE KEYS */;
INSERT INTO `point_ledger` VALUES (1,5,100,'Certificate awarded by tutor',4,'2026-03-18 02:04:36'),(2,5,450,'300',4,'2026-03-18 02:06:08'),(3,5,100,'Certificate awarded',4,'2026-03-18 12:11:34');
/*!40000 ALTER TABLE `point_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_attempts`
--

DROP TABLE IF EXISTS `quiz_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `quiz_id` int(11) NOT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `passed` tinyint(1) DEFAULT 0,
  `answers_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers_json`)),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `quiz_id` (`quiz_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_attempts`
--

LOCK TABLES `quiz_attempts` WRITE;
/*!40000 ALTER TABLE `quiz_attempts` DISABLE KEYS */;
INSERT INTO `quiz_attempts` VALUES (1,5,1,20.00,0,NULL,'2026-03-18 23:49:01');
/*!40000 ALTER TABLE `quiz_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_questions`
--

DROP TABLE IF EXISTS `quiz_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quiz_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `type` enum('mcq','tf','text') DEFAULT 'mcq',
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options_json`)),
  `correct_answer` text DEFAULT NULL,
  `points` int(11) DEFAULT 10,
  `order_num` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `quiz_id` (`quiz_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_questions`
--

LOCK TABLES `quiz_questions` WRITE;
/*!40000 ALTER TABLE `quiz_questions` DISABLE KEYS */;
INSERT INTO `quiz_questions` VALUES (1,1,'Which principle of design refers to visual weight and harmony?','mcq','[\"Proximity\",\"Repetition\",\"Balance\",\"Contrast\"]','Balance',10,1),(2,1,'Which file format supports transparent backgrounds?','mcq','[\"BMP\",\"PNG\",\"TIFF\",\"JPEG\"]','PNG',10,2),(3,1,'What is the purpose of a wireframe in UI/UX design?','mcq','[\"To define a brand color palette\",\"To write CSS animations\",\"To test a product on real users\",\"To create a low-fidelity blueprint of a page layout\"]','To create a low-fidelity blueprint of a page layout',10,3),(4,1,'What is the \"F-pattern\" in web design?','mcq','[\"A reading pattern where users scan pages in an F-shaped path\",\"A layout using three equal columns\",\"A method for centering grid elements\",\"A font pairing technique\"]','A reading pattern where users scan pages in an F-shaped path',10,4),(5,1,'What does \"UX\" stand for?','mcq','[\"Universal Exchange\",\"User Experience\",\"Unified Extension\",\"User Execution\"]','User Experience',10,5),(6,3,'What is the \"F-pattern\" in web design?','mcq','[\"A layout using three equal columns\",\"A font pairing technique\",\"A method for centering grid elements\",\"A reading pattern where users scan pages in an F-shaped path\"]','A reading pattern where users scan pages in an F-shaped path',10,1),(7,3,'What does \"UX\" stand for?','mcq','[\"Unified Extension\",\"Universal Exchange\",\"User Execution\",\"User Experience\"]','User Experience',10,2),(8,3,'What is the purpose of a wireframe in UI/UX design?','mcq','[\"To define a brand color palette\",\"To create a low-fidelity blueprint of a page layout\",\"To test a product on real users\",\"To write CSS animations\"]','To create a low-fidelity blueprint of a page layout',10,3),(9,3,'Which principle of design refers to visual weight and harmony?','mcq','[\"Balance\",\"Repetition\",\"Proximity\",\"Contrast\"]','Balance',10,4),(10,3,'Which file format supports transparent backgrounds?','mcq','[\"PNG\",\"BMP\",\"JPEG\",\"TIFF\"]','PNG',10,5);
/*!40000 ALTER TABLE `quiz_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quizzes`
--

DROP TABLE IF EXISTS `quizzes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quizzes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `time_limit_mins` int(11) DEFAULT 30,
  `pass_score` int(11) DEFAULT 70,
  `type` enum('quiz','cat','final') DEFAULT 'quiz',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quizzes`
--

LOCK TABLES `quizzes` WRITE;
/*!40000 ALTER TABLE `quizzes` DISABLE KEYS */;
INSERT INTO `quizzes` VALUES (1,1,'AI Quiz: TEST COURSE',20,70,'quiz','2026-03-17 19:20:44'),(2,1,'test 2',11,70,'quiz','2026-03-17 19:21:56'),(3,2,'AI Quiz: Literature',20,70,'quiz','2026-03-17 19:45:04');
/*!40000 ALTER TABLE `quizzes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarship_applications`
--

DROP TABLE IF EXISTS `scholarship_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarship_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scholarship_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sop` text NOT NULL,
  `academic_background` text NOT NULL,
  `document_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `scholarship_id` (`scholarship_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarship_applications`
--

LOCK TABLES `scholarship_applications` WRITE;
/*!40000 ALTER TABLE `scholarship_applications` DISABLE KEYS */;
INSERT INTO `scholarship_applications` VALUES (1,1,5,'ehjk','high school','SCH_APP_5_1773767156.jpeg','approved','2026-03-17 20:05:56');
/*!40000 ALTER TABLE `scholarship_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarships`
--

DROP TABLE IF EXISTS `scholarships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarships`
--

LOCK TABLES `scholarships` WRITE;
/*!40000 ALTER TABLE `scholarships` DISABLE KEYS */;
INSERT INTO `scholarships` VALUES (1,'LIKIZO B4 CAMPUS','Free for all highschoolers',0.00,'2026-04-01','active','2026-03-17 20:00:26');
/*!40000 ALTER TABLE `scholarships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_badges`
--

DROP TABLE IF EXISTS `student_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_badges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `awarded_at` datetime DEFAULT current_timestamp(),
  `awarded_by` int(11) DEFAULT NULL,
  `awarded_by_role` enum('admin','tutor','system') DEFAULT 'system',
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_badge` (`student_id`,`badge_id`),
  KEY `badge_id` (`badge_id`),
  CONSTRAINT `student_badges_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_badges`
--

LOCK TABLES `student_badges` WRITE;
/*!40000 ALTER TABLE `student_badges` DISABLE KEYS */;
INSERT INTO `student_badges` VALUES (1,7,4,'2026-03-18 23:15:55',4,'tutor',''),(2,5,4,'2026-03-18 23:18:41',4,'tutor','');
/*!40000 ALTER TABLE `student_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','in_progress','closed') DEFAULT 'open',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `support_tickets_user_fk` (`user_id`),
  CONSTRAINT `support_tickets_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
INSERT INTO `support_tickets` VALUES (1,5,'Billing & Payment Verification','delayed','open','2026-03-17 22:11:09'),(2,5,'Certificate Verification','i want','open','2026-03-17 23:06:56'),(3,5,'Course Access Issue','hello','in_progress','2026-03-18 02:27:46'),(4,NULL,'Technical Support','From: SEge (mzalendo.test@example.com)\n\nasdfgj','open','2026-03-18 22:07:37');
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transcript_entries`
--

DROP TABLE IF EXISTS `transcript_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transcript_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `entry_type` enum('course_completion','quiz_pass','assignment_grade','manual_entry') DEFAULT 'course_completion',
  `title` varchar(255) NOT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `credits` decimal(4,1) DEFAULT 1.0,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `course_id` (`course_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `transcript_entries_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transcript_entries_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transcript_entries_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transcript_entries`
--

LOCK TABLES `transcript_entries` WRITE;
/*!40000 ALTER TABLE `transcript_entries` DISABLE KEYS */;
INSERT INTO `transcript_entries` VALUES (1,5,3,'course_completion','final',87.00,100.00,'A',1.0,'CERTIFIED',4,'2026-03-18 02:05:40');
/*!40000 ALTER TABLE `transcript_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','tutor','student') NOT NULL DEFAULT 'student',
  `admission_number` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT 'Kenyan',
  `national_id` varchar(50) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `id_document` varchar(255) DEFAULT NULL,
  `referral_code` varchar(50) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `status` enum('active','pending','suspended') NOT NULL DEFAULT 'pending',
  `email_verified` tinyint(1) DEFAULT 0,
  `points` int(11) DEFAULT 0,
  `merit_coins` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `referral_code` (`referral_code`),
  UNIQUE KEY `national_id` (`national_id`),
  UNIQUE KEY `admission_number` (`admission_number`),
  KEY `fk_referred_by` (`referred_by`),
  CONSTRAINT `fk_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Super Admin','admin@skopedigitalacademy.com','admin123','admin',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Kenyan',NULL,NULL,NULL,'SDA160BD3',NULL,'active',1,0,0.00,'2026-03-17 15:23:20',NULL),(4,'Sege Peter','skopedesigns91@gmail.com','$2y$10$U4IXDYBw9aX5gziNiqZCfuQVFOFVhQj5C5bIyat8z/VA3.YWITBT6','tutor',NULL,NULL,'AVATAR_4_1773864119.jpeg',NULL,NULL,NULL,NULL,'Kenyan',NULL,NULL,NULL,'SDA4CF06F',NULL,'active',1,0,0.00,'2026-03-17 19:00:17','2026-03-18 22:59:29'),(5,'Sege Peter ENG','segepeter91@gmail.com','$2y$10$KGOn958ccIicZnTbl8ndOerP3o0XtE5/EUTgdRRI45.5pkx70awGS','student','SDA/2026/0005','+254 742380183','av_69ba72313f6c2.png','Full Stack Developer','2000-05-23','male','Kisumu','Kenyan','','MASENO UNIVERSITY','id_5_1773863309.pdf','SDA598F41',NULL,'active',1,650,0.00,'2026-03-17 20:02:54','2026-03-18 22:58:29'),(7,'Maseno','ticlubmaseno@gmail.com','$2y$10$C6j8wIINIYu1yJTi5j5T0epFDgJ5lHNMt7zYlp036mDd0RZ4V4ZJe','student','SDA/2026/0007',NULL,NULL,NULL,NULL,NULL,NULL,'Kenyan',NULL,NULL,NULL,'SDA338A8E622AF',NULL,'active',1,0,0.00,'2026-03-18 13:35:13','2026-03-18 13:35:49'),(9,'Junior','junior@skopedigital.ac.ke','$2y$10$Swj.LDtedMAxmdWk/v30DOjglnNYrG7AUaKI2plwLpU5XVluGsRBy','admin','SDA/2026/0009',NULL,NULL,NULL,NULL,NULL,NULL,'Kenyan',NULL,NULL,NULL,'SDA9285ADD7F95',5,'active',1,0,0.00,'2026-03-18 17:00:22','2026-03-18 23:39:19');
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

-- Dump completed on 2026-03-19  2:27:58
