/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `chart_of_accounts` WRITE;
/*!40000 ALTER TABLE `chart_of_accounts` DISABLE KEYS */;
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'1000','Kasse','asset','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:16','2026-05-19 08:11:16',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,'1100','Bank','asset','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:16','2026-05-19 08:11:16',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (3,'1200','Forderungen aus L.u.L.','asset','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:16','2026-05-19 08:11:16',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (4,'1300','Vorräte','asset','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (5,'1400','Vorsteuer','asset','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (6,'2000','Verbindlichkeiten aus L.u.L.','liability','credit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (7,'2100','Umsatzsteuer','liability','credit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (8,'2200','Lohnverbindlichkeiten','liability','credit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (9,'3000','Eigenkapital','equity','credit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (10,'3100','Gewinnvortrag','equity','credit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (11,'4000','Umsatzerlöse','revenue','credit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (12,'4100','Dienstleistungserlöse','revenue','credit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (13,'4900','Sonstige Erträge','revenue','credit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (14,'5000','Wareneinsatz','expense','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (15,'5100','Personalaufwand','expense','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (16,'5200','Mietaufwand','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (17,'5300','Energie- und Nebenkosten','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (18,'5400','Sonstige betriebliche Aufwendungen','expense','debit',NULL,NULL,1,1,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (19,'5500','Bürobedarf','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (20,'5600','Marketing & Werbung','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (21,'5700','Reisekosten','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
INSERT INTO `chart_of_accounts` (`id`, `account_code`, `account_name`, `account_type`, `normal_balance`, `parent_id`, `branch_id`, `allow_manual_entry`, `is_system`, `is_active`, `description`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES (22,'5800','Kommunikation & IT','expense','debit',NULL,NULL,1,0,1,NULL,NULL,NULL,'2026-05-19 08:11:17','2026-05-19 08:11:17',NULL);
/*!40000 ALTER TABLE `chart_of_accounts` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (57,'manager','pos','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (58,'manager','sales','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (59,'manager','purchases','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (60,'manager','quotations','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (61,'manager','products','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (62,'manager','inventory','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (63,'manager','customers','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (64,'manager','suppliers','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (65,'manager','reports','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (66,'manager','finance','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (67,'cashier','pos','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (68,'cashier','sales','2026-05-19 08:11:17','2026-05-19 08:11:17');
INSERT INTO `role_permissions` (`id`, `role`, `module`, `created_at`, `updated_at`) VALUES (69,'cashier','customers','2026-05-19 08:11:17','2026-05-19 08:11:17');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `branch_id`, `is_active`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES (1,'System Administrator','admin@posmeister.com',NULL,'admin',NULL,1,NULL,'$2y$12$70837E9BwLbFAB9L9bQiSe5xO2aNY.PLZkXk.zoQtxz5HlrFUfjhq',NULL,'2026-04-16 06:50:07','2026-04-16 06:50:07');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `branch_id`, `is_active`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES (2,'System Administrator','admin@posmeister.local',NULL,'admin',NULL,1,NULL,'$2y$12$ega5MqDTJXacbHBTU.5iNuoT7Y1PxlpW0XbgpiImkr2NlhSwfs1ja',NULL,'2026-04-18 23:46:29','2026-04-18 23:46:29');
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `branch_id`, `is_active`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`) VALUES (4,'Tareku Islam','tarek@gmail.com','+8801868332991','cashier',2,1,NULL,'$2y$12$91hdML1cMx1bGd8.keIlPeGZowq7TYyPk19F8YKhLqwdJvESMbU8.',NULL,'2026-04-20 02:50:17','2026-04-20 02:50:17');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

