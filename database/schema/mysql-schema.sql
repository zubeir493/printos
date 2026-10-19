/*M!999999\- enable the sandbox mode */
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
DROP TABLE IF EXISTS `accounting_account_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_account_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `accounting_integration_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `external_account_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounting_mapping_integration_account_unique` (`accounting_integration_id`,`account_id`),
  KEY `accounting_account_mappings_account_id_foreign` (`account_id`),
  CONSTRAINT `accounting_account_mappings_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounting_account_mappings_accounting_integration_id_foreign` FOREIGN KEY (`accounting_integration_id`) REFERENCES `accounting_integrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounting_export_journal_entry`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_export_journal_entry` (
  `accounting_integration_id` bigint unsigned NOT NULL,
  `accounting_export_id` bigint unsigned NOT NULL,
  `journal_entry_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`accounting_export_id`,`journal_entry_id`),
  UNIQUE KEY `accounting_export_integration_journal_unique` (`accounting_integration_id`,`journal_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounting_exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `accounting_integration_id` bigint unsigned NOT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `cutoff_at` datetime NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journal_count` int unsigned NOT NULL DEFAULT '0',
  `row_count` int unsigned NOT NULL DEFAULT '0',
  `total_debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `generated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounting_exports_generated_by_foreign` (`generated_by`),
  KEY `accounting_exports_accounting_integration_id_cutoff_at_index` (`accounting_integration_id`,`cutoff_at`),
  KEY `accounting_exports_status_index` (`status`),
  CONSTRAINT `accounting_exports_accounting_integration_id_foreign` FOREIGN KEY (`accounting_integration_id`) REFERENCES `accounting_integrations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounting_exports_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounting_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_integrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0',
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Africa/Addis_Ababa',
  `daily_cutoff` time NOT NULL DEFAULT '23:55:00',
  `configuration` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounting_integrations_provider_unique` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Asset','Liability','Equity','Revenue','Expense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `default_tracking_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_code_unique` (`code`),
  KEY `accounts_default_tracking_type_index` (`default_tracking_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `attribute_changes` json DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `artworks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `artworks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_id` bigint unsigned DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deliverable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `artworks_uploaded_by_foreign` (`uploaded_by`),
  KEY `artworks_job_order_id_foreign` (`job_order_id`),
  CONSTRAINT `artworks_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `artworks_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_daily_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_daily_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `expected_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `worked_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `regular_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `late_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `overtime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `holiday_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `night_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `absence_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `paid_leave_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `unpaid_leave_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absent',
  `calculation_snapshot` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_daily_summaries_employee_id_date_unique` (`employee_id`,`date`),
  CONSTRAINT `attendance_daily_summaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_import_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_import_id` bigint unsigned NOT NULL,
  `row_number` int unsigned NOT NULL,
  `raw_data` json NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `attendance_log_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_import_rows_attendance_import_id_foreign` (`attendance_import_id`),
  CONSTRAINT `attendance_import_rows_attendance_import_id_foreign` FOREIGN KEY (`attendance_import_id`) REFERENCES `attendance_imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `report_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'punch_logs',
  `shift_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_rows` int unsigned NOT NULL DEFAULT '0',
  `successful_rows` int unsigned NOT NULL DEFAULT '0',
  `failed_rows` int unsigned NOT NULL DEFAULT '0',
  `created_by` bigint unsigned DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_imports_created_by_foreign` (`created_by`),
  CONSTRAINT `attendance_imports_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `punched_at` datetime NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `attendance_import_id` bigint unsigned DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_logs_attendance_import_id_foreign` (`attendance_import_id`),
  KEY `attendance_logs_created_by_foreign` (`created_by`),
  KEY `attendance_logs_employee_id_punched_at_index` (`employee_id`,`punched_at`),
  CONSTRAINT `attendance_logs_attendance_import_id_foreign` FOREIGN KEY (`attendance_import_id`) REFERENCES `attendance_imports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_logs_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_period_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_period_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_import_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `shift_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `work_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `actual_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `absent_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `late_minutes` int unsigned NOT NULL DEFAULT '0',
  `early_minutes` int unsigned NOT NULL DEFAULT '0',
  `lunch_minutes` int unsigned NOT NULL DEFAULT '0',
  `overtime_minutes` int unsigned NOT NULL DEFAULT '0',
  `holiday_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `leave_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `dayoff_days` decimal(8,2) NOT NULL DEFAULT '0.00',
  `work_time_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `work_percentage` decimal(8,2) NOT NULL DEFAULT '0.00',
  `raw_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_period_summary_unique` (`employee_id`,`period_start`,`period_end`,`shift_type`),
  KEY `attendance_period_summaries_attendance_import_id_foreign` (`attendance_import_id`),
  CONSTRAINT `attendance_period_summaries_attendance_import_id_foreign` FOREIGN KEY (`attendance_import_id`) REFERENCES `attendance_imports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_period_summaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_segments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_segments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `attendance_import_id` bigint unsigned DEFAULT NULL,
  `attendance_import_row_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `shift_id` bigint unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `fp_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schedule_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scheduled_start` time DEFAULT NULL,
  `scheduled_end` time DEFAULT NULL,
  `clock_in` time DEFAULT NULL,
  `clock_out` time DEFAULT NULL,
  `late_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `early_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `worked_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `overtime_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `day_fraction` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exception` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correction_reason` text COLLATE utf8mb4_unicode_ci,
  `raw_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_segments_attendance_import_id_foreign` (`attendance_import_id`),
  KEY `attendance_segments_attendance_import_row_id_foreign` (`attendance_import_row_id`),
  KEY `attendance_segments_employee_id_date_index` (`employee_id`,`date`),
  KEY `attendance_segments_shift_id_foreign` (`shift_id`),
  CONSTRAINT `attendance_segments_attendance_import_id_foreign` FOREIGN KEY (`attendance_import_id`) REFERENCES `attendance_imports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_segments_attendance_import_row_id_foreign` FOREIGN KEY (`attendance_import_row_id`) REFERENCES `attendance_import_rows` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_segments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_segments_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bank_transactions`;
/*!50001 DROP VIEW IF EXISTS `bank_transactions`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `bank_transactions` AS SELECT
 NULL AS `id`,
 NULL AS `bank_id`,
 NULL AS `source_id`,
 NULL AS `source_type`,
 NULL AS `transaction_number`,
 NULL AS `transaction_type`,
 NULL AS `direction`,
 NULL AS `amount`,
 NULL AS `balance_delta`,
 NULL AS `transaction_date`,
 NULL AS `status`,
 NULL AS `reference`,
 NULL AS `counterparty`,
 NULL AS `related_bank_name`,
 NULL AS `created_at`,
 NULL AS `updated_at` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `bank_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_bank_id` bigint unsigned NOT NULL,
  `to_bank_id` bigint unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transfer_date` date NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` bigint unsigned DEFAULT NULL,
  `completed_by` bigint unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_transfers_transfer_number_unique` (`transfer_number`),
  KEY `bank_transfers_from_bank_id_foreign` (`from_bank_id`),
  KEY `bank_transfers_to_bank_id_foreign` (`to_bank_id`),
  KEY `bank_transfers_created_by_foreign` (`created_by`),
  KEY `bank_transfers_completed_by_foreign` (`completed_by`),
  KEY `bank_transfers_transfer_number_index` (`transfer_number`),
  KEY `bank_transfers_status_index` (`status`),
  KEY `bank_transfers_transfer_date_index` (`transfer_date`),
  CONSTRAINT `bank_transfers_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transfers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bank_transfers_from_bank_id_foreign` FOREIGN KEY (`from_bank_id`) REFERENCES `banks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `bank_transfers_to_bank_id_foreign` FOREIGN KEY (`to_bank_id`) REFERENCES `banks` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `banks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_holder_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `current_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `opening_balance` decimal(15,2) DEFAULT NULL,
  `status` enum('active','inactive','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `banks_code_unique` (`code`),
  UNIQUE KEY `banks_account_number_unique` (`account_number`),
  KEY `banks_code_index` (`code`),
  KEY `banks_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bids` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bid_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tender_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submission_date` date DEFAULT NULL,
  `deadline_date` date DEFAULT NULL,
  `estimated_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bid_bond_amount` decimal(15,2) DEFAULT NULL,
  `bid_files` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bids_bid_number_unique` (`bid_number`),
  KEY `bids_partner_id_foreign` (`partner_id`),
  KEY `bids_status_deadline_date_index` (`status`,`deadline_date`),
  CONSTRAINT `bids_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bonds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bid',
  `bid_id` bigint unsigned NOT NULL,
  `issuing_partner_id` bigint unsigned NOT NULL,
  `bank_id` bigint unsigned DEFAULT NULL,
  `cpo_bank_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `issue_date` date DEFAULT NULL,
  `recovery_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `issue_payment_id` bigint unsigned DEFAULT NULL,
  `recovery_payment_id` bigint unsigned DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bid_bonds_bid_id_foreign` (`bid_id`),
  KEY `bid_bonds_issuing_partner_id_foreign` (`issuing_partner_id`),
  KEY `bid_bonds_bank_id_foreign` (`bank_id`),
  KEY `bid_bonds_issue_payment_id_foreign` (`issue_payment_id`),
  KEY `bid_bonds_recovery_payment_id_foreign` (`recovery_payment_id`),
  KEY `bid_bonds_status_expiry_date_index` (`status`,`expiry_date`),
  KEY `bonds_type_index` (`type`),
  CONSTRAINT `bid_bonds_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  CONSTRAINT `bid_bonds_bid_id_foreign` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bid_bonds_issue_payment_id_foreign` FOREIGN KEY (`issue_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bid_bonds_issuing_partner_id_foreign` FOREIGN KEY (`issuing_partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `bid_bonds_recovery_payment_id_foreign` FOREIGN KEY (`recovery_payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cash_deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_deposits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deposit_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deposit_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash_transfer',
  `bank_id` bigint unsigned NOT NULL,
  `cash_account_id` bigint unsigned DEFAULT NULL,
  `income_account_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','posted','reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` bigint unsigned DEFAULT NULL,
  `posted_by` bigint unsigned DEFAULT NULL,
  `reversed_by` bigint unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversal_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cash_deposits_deposit_number_unique` (`deposit_number`),
  KEY `cash_deposits_cash_account_id_foreign` (`cash_account_id`),
  KEY `cash_deposits_created_by_foreign` (`created_by`),
  KEY `cash_deposits_posted_by_foreign` (`posted_by`),
  KEY `cash_deposits_reversed_by_foreign` (`reversed_by`),
  KEY `cash_deposits_bank_id_deposit_date_index` (`bank_id`,`deposit_date`),
  KEY `cash_deposits_status_deposit_date_index` (`status`,`deposit_date`),
  KEY `cash_deposits_income_account_id_foreign` (`income_account_id`),
  CONSTRAINT `cash_deposits_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_deposits_cash_account_id_foreign` FOREIGN KEY (`cash_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_deposits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_deposits_income_account_id_foreign` FOREIGN KEY (`income_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cash_deposits_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_deposits_reversed_by_foreign` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cost_estimate_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_estimate_inputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cost_estimate_id` bigint unsigned NOT NULL,
  `step` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cost_estimate_inputs_cost_estimate_id_step_unique` (`cost_estimate_id`,`step`),
  CONSTRAINT `cost_estimate_inputs_cost_estimate_id_foreign` FOREIGN KEY (`cost_estimate_id`) REFERENCES `cost_estimates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cost_estimate_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_estimate_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cost_estimate_id` bigint unsigned NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_item_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `snapshot` json DEFAULT NULL,
  `sort` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cost_estimate_lines_cost_estimate_id_foreign` (`cost_estimate_id`),
  KEY `cost_estimate_lines_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `cost_estimate_lines_cost_estimate_id_foreign` FOREIGN KEY (`cost_estimate_id`) REFERENCES `cost_estimates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cost_estimate_lines_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cost_estimates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cost_estimates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `estimate_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'labels',
  `partner_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `deadline` date DEFAULT NULL,
  `services` json DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `overhead_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `profit_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `unit_price` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `margin_percent` decimal(6,2) NOT NULL DEFAULT '0.00',
  `formula_version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'v1',
  `settings_snapshot` json DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cost_estimates_estimate_number_unique` (`estimate_number`),
  KEY `cost_estimates_partner_id_foreign` (`partner_id`),
  CONSTRAINT `cost_estimates_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `costing_service_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `costing_service_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calculator_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `schema_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `defaults` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `costing_service_types_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dieline_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dieline_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `standard` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FEFCO',
  `service_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `defaults` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dieline_templates_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dielines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dielines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_order_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `dimensions` json NOT NULL,
  `geometry` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dielines_created_by_foreign` (`created_by`),
  KEY `dielines_template_key_index` (`template_key`),
  KEY `dielines_job_order_id_foreign` (`job_order_id`),
  CONSTRAINT `dielines_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dielines_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispatch_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispatch_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_id` bigint unsigned NOT NULL,
  `job_order_id` bigint unsigned NOT NULL,
  `quantity` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispatch_items_dispatch_id_job_order_id_unique` (`dispatch_id`,`job_order_id`),
  KEY `dispatch_items_dispatch_id_foreign` (`dispatch_id`),
  KEY `dispatch_items_job_order_task_id_foreign` (`job_order_id`),
  CONSTRAINT `dispatch_items_dispatch_id_foreign` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`),
  CONSTRAINT `dispatch_items_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dispatches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_id` bigint unsigned NOT NULL,
  `delivery_date` date NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dispatches_job_order_id_foreign` (`job_order_id`),
  KEY `dispatches_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `dispatches_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`),
  CONSTRAINT `dispatches_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `artwork_id` bigint unsigned DEFAULT NULL,
  `sent_by` bigint unsigned DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_logs_artwork_id_foreign` (`artwork_id`),
  KEY `email_logs_sent_by_foreign` (`sent_by`),
  CONSTRAINT `email_logs_artwork_id_foreign` FOREIGN KEY (`artwork_id`) REFERENCES `artworks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_logs_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_loan_installments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_loan_installments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_loan_id` bigint unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payroll_run_employee_id` bigint unsigned DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_loan_installments_payroll_run_employee_id_foreign` (`payroll_run_employee_id`),
  KEY `employee_loan_installments_payment_id_foreign` (`payment_id`),
  KEY `employee_loan_installments_due_date_status_index` (`due_date`,`status`),
  KEY `employee_loan_installments_employee_loan_id_status_index` (`employee_loan_id`,`status`),
  CONSTRAINT `employee_loan_installments_employee_loan_id_foreign` FOREIGN KEY (`employee_loan_id`) REFERENCES `employee_loans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_loan_installments_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_loan_installments_payroll_run_employee_id_foreign` FOREIGN KEY (`payroll_run_employee_id`) REFERENCES `payroll_run_employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_loans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `loan_date` date NOT NULL,
  `return_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `installment_count` int unsigned NOT NULL DEFAULT '1',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_loans_employee_id_return_date_status_index` (`employee_id`,`return_date`,`status`),
  CONSTRAINT `employee_loans_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_salary_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_salary_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL,
  `overtime_multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `effective_date` date NOT NULL,
  `change_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_salary_histories_employee_id_foreign` (`employee_id`),
  CONSTRAINT `employee_salary_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attendance_device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date NOT NULL,
  `termination_date` date DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `employment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'permanent',
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pension_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `transport_allowance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `overtime_multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_id_unique` (`employee_id`),
  UNIQUE KEY `employees_attendance_device_id_unique` (`attendance_device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expense_tracking_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_tracking_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_tracking_items_type_code_unique` (`type`,`code`),
  KEY `expense_tracking_items_type_status_index` (`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `exports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_disk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exporter` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_rows` int unsigned NOT NULL DEFAULT '0',
  `total_rows` int unsigned NOT NULL,
  `successful_rows` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exports_user_id_foreign` (`user_id`),
  CONSTRAINT `exports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_import_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_import_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` json NOT NULL,
  `import_id` bigint unsigned NOT NULL,
  `validation_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `failed_import_rows_import_id_foreign` (`import_id`),
  CONSTRAINT `failed_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `filament_exceptions_table`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `filament_exceptions_table` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `line` int unsigned NOT NULL,
  `trace` json NOT NULL,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `cookies` json DEFAULT NULL,
  `body` json DEFAULT NULL,
  `query` json DEFAULT NULL,
  `route_context` json DEFAULT NULL,
  `route_parameters` json DEFAULT NULL,
  `markdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `filament_exceptions_table_created_at_index` (`created_at`),
  KEY `filament_exceptions_table_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goods_receipt_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` bigint unsigned NOT NULL,
  `purchase_order_item_id` bigint unsigned NOT NULL,
  `quantity_received` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_items_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `goods_receipt_items_purchase_order_item_id_foreign` (`purchase_order_item_id`),
  CONSTRAINT `goods_receipt_items_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`),
  CONSTRAINT `goods_receipt_items_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `goods_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goods_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned DEFAULT NULL,
  `receipt_date` date NOT NULL,
  `status` enum('draft','posted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `goods_receipts_receipt_number_unique` (`receipt_number`),
  KEY `goods_receipts_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `goods_receipts_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `goods_receipts_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `goods_receipts_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `holidays` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `counts_as_holiday_overtime` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `holidays_date_unique` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `completed_at` timestamp NULL DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `importer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `processed_rows` int unsigned NOT NULL DEFAULT '0',
  `total_rows` int unsigned NOT NULL,
  `successful_rows` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imports_user_id_foreign` (`user_id`),
  CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_balances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_balances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `quantity_on_hand` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_balances_inventory_item_id_warehouse_id_unique` (`inventory_item_id`,`warehouse_id`),
  UNIQUE KEY `inventory_balances_item_warehouse_unique` (`inventory_item_id`,`warehouse_id`),
  KEY `inventory_balances_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `inventory_balances_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_balances_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `average_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `low_stock_threshold` decimal(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `purchase_unit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_factor` decimal(15,4) DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'raw_material',
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_sellable` tinyint(1) NOT NULL DEFAULT '0',
  `price` decimal(15,2) DEFAULT '0.00',
  `gsm` decimal(8,2) DEFAULT NULL,
  `width` decimal(10,3) DEFAULT NULL,
  `height` decimal(10,3) DEFAULT NULL,
  `default_waste_percent` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventory_items_sku_unique` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_type` enum('sales','purchase','service','receipt') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `order_type` enum('sales_order','purchase_order','job_order','payment') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `balance_due` decimal(12,2) NOT NULL,
  `status` enum('draft','sent','paid','unpaid','partial','overdue','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `emailed_at` timestamp NULL DEFAULT NULL,
  `email_recipient` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_calculations` json DEFAULT NULL,
  `options` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_invoice_type_status_index` (`invoice_type`,`status`),
  KEY `invoices_partner_id_invoice_date_index` (`partner_id`,`invoice_date`),
  KEY `invoices_order_type_order_id_index` (`order_type`,`order_id`),
  CONSTRAINT `invoices_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_order_outputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_order_outputs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_order_outputs_job_order_id_foreign` (`job_order_id`),
  KEY `job_order_outputs_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `job_order_outputs_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `job_order_outputs_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `job_order_outputs_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_order_outputs_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned DEFAULT NULL,
  `job_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_calc_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'legacy-cost-calculation',
  `services` json NOT NULL,
  `submission_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `advance_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `advance_paid` tinyint(1) NOT NULL DEFAULT '0',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `production_started_at` timestamp NULL DEFAULT NULL,
  `notified_late_at` timestamp NULL DEFAULT NULL,
  `materials_fully_issued_at` timestamp NULL DEFAULT NULL,
  `production_mode` enum('make_to_order','make_to_stock') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'make_to_order',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paper` json DEFAULT NULL,
  `deliverables` json DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `designer_id` bigint unsigned DEFAULT NULL,
  `typist_id` bigint unsigned DEFAULT NULL,
  `proforma_id` bigint unsigned DEFAULT NULL,
  `proforma_task_id` bigint unsigned DEFAULT NULL,
  `output_inventory_item_id` bigint unsigned DEFAULT NULL,
  `design_completed_at` timestamp NULL DEFAULT NULL,
  `production_completed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_orders_job_order_number_unique` (`job_order_number`),
  UNIQUE KEY `job_orders_proforma_task_id_unique` (`proforma_task_id`),
  KEY `job_orders_partner_id_foreign` (`partner_id`),
  KEY `job_orders_proforma_id_foreign` (`proforma_id`),
  KEY `job_orders_output_inventory_item_id_foreign` (`output_inventory_item_id`),
  KEY `job_orders_status_submission_date_index` (`status`,`submission_date`),
  KEY `job_orders_designer_id_status_index` (`designer_id`,`status`),
  KEY `job_orders_typist_id_status_index` (`typist_id`,`status`),
  CONSTRAINT `job_orders_designer_id_foreign` FOREIGN KEY (`designer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_orders_output_inventory_item_id_foreign` FOREIGN KEY (`output_inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `job_orders_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_orders_proforma_id_foreign` FOREIGN KEY (`proforma_id`) REFERENCES `proformas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `job_orders_proforma_task_id_foreign` FOREIGN KEY (`proforma_task_id`) REFERENCES `proforma_tasks` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `job_orders_typist_id_foreign` FOREIGN KEY (`typist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `narration` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_debit` decimal(15,2) NOT NULL,
  `total_credit` decimal(15,2) NOT NULL,
  `status` enum('draft','posted','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `posted_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `reversal_of_journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_reversal_of_journal_entry_id_foreign` (`reversal_of_journal_entry_id`),
  CONSTRAINT `journal_entries_reversal_of_journal_entry_id_foreign` FOREIGN KEY (`reversal_of_journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_items_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `journal_items_account_id_foreign` (`account_id`),
  CONSTRAINT `journal_items_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `journal_items_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `leave_type_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `minutes` smallint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_leave_type_id_foreign` (`leave_type_id`),
  KEY `leave_requests_approved_by_foreign` (`approved_by`),
  KEY `leave_requests_employee_id_start_date_end_date_index` (`employee_id`,`start_date`,`end_date`),
  CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_leave_type_id_foreign` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `machines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `baseline_rounds_per_week` int DEFAULT '0',
  `operation_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `hourly_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `production_speed` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `machines_operation_type_index` (`operation_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_issue_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_issue_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_request_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned DEFAULT NULL,
  `processed_by` bigint unsigned DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `decision_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_issue_approvals_material_request_id_foreign` (`material_request_id`),
  KEY `material_issue_approvals_warehouse_id_foreign` (`warehouse_id`),
  KEY `material_issue_approvals_requested_by_foreign` (`requested_by`),
  KEY `material_issue_approvals_processed_by_foreign` (`processed_by`),
  CONSTRAINT `material_issue_approvals_material_request_id_foreign` FOREIGN KEY (`material_request_id`) REFERENCES `material_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_issue_approvals_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issue_approvals_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_issue_approvals_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `material_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `material_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `required_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `requested_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `issued_quantity` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `material_requests_job_order_task_id_foreign` (`job_order_id`),
  KEY `material_requests_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `material_requests_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_requests_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `overtime_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `overtime_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `minutes_basis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applies_on_days` json DEFAULT NULL,
  `multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `hourly_rate` decimal(15,4) DEFAULT NULL,
  `minimum_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `rounding_increment_minutes` smallint unsigned NOT NULL DEFAULT '1',
  `window_start_time` time DEFAULT NULL,
  `window_end_time` time DEFAULT NULL,
  `priority` smallint unsigned NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `overtime_rules_code_unique` (`code`),
  KEY `overtime_rules_minutes_basis_index` (`minutes_basis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tin_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_supplier` tinyint(1) NOT NULL DEFAULT '0',
  `is_customer` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer_receipt',
  `partner_id` bigint unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `withholding_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `direction` enum('inbound','outbound') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` enum('cash','bank','cheque','bank_transfer','cpo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voided_at` timestamp NULL DEFAULT NULL,
  `voided_by` bigint unsigned DEFAULT NULL,
  `void_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `account_id` bigint unsigned DEFAULT NULL,
  `bank_id` bigint unsigned DEFAULT NULL,
  `expense_account_id` bigint unsigned DEFAULT NULL,
  `petty_cash_account_id` bigint unsigned DEFAULT NULL,
  `expense_tracking_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expense_tracking_item_id` bigint unsigned DEFAULT NULL,
  `expense_tracking_employee_id` bigint unsigned DEFAULT NULL,
  `expense_tracking_bid_id` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payable_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_payment_number_unique` (`payment_number`),
  KEY `payments_partner_id_foreign` (`partner_id`),
  KEY `payments_account_id_foreign` (`account_id`),
  KEY `payments_expense_account_id_foreign` (`expense_account_id`),
  KEY `payments_petty_cash_account_id_foreign` (`petty_cash_account_id`),
  KEY `payments_voided_by_foreign` (`voided_by`),
  KEY `payments_bank_id_index` (`bank_id`),
  KEY `payments_payable_type_payable_id_index` (`payable_type`,`payable_id`),
  KEY `payments_expense_tracking_item_id_foreign` (`expense_tracking_item_id`),
  KEY `payments_expense_tracking_employee_id_foreign` (`expense_tracking_employee_id`),
  KEY `payments_expense_tracking_bid_id_foreign` (`expense_tracking_bid_id`),
  KEY `payments_transaction_type_expense_tracking_type_index` (`transaction_type`,`expense_tracking_type`),
  KEY `payments_expense_tracking_type_expense_tracking_item_id_index` (`expense_tracking_type`,`expense_tracking_item_id`),
  CONSTRAINT `payments_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `payments_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_expense_account_id_foreign` FOREIGN KEY (`expense_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `payments_expense_tracking_bid_id_foreign` FOREIGN KEY (`expense_tracking_bid_id`) REFERENCES `bids` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_expense_tracking_employee_id_foreign` FOREIGN KEY (`expense_tracking_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_expense_tracking_item_id_foreign` FOREIGN KEY (`expense_tracking_item_id`) REFERENCES `expense_tracking_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `payments_petty_cash_account_id_foreign` FOREIGN KEY (`petty_cash_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `payments_voided_by_foreign` FOREIGN KEY (`voided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_line_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_employee_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_line_items_payroll_run_employee_id_foreign` (`payroll_run_employee_id`),
  CONSTRAINT `payroll_line_items_payroll_run_employee_id_foreign` FOREIGN KEY (`payroll_run_employee_id`) REFERENCES `payroll_run_employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_overtime_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_overtime_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint unsigned NOT NULL,
  `payroll_run_employee_id` bigint unsigned DEFAULT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `overtime_rule_id` bigint unsigned NOT NULL,
  `date` date DEFAULT NULL,
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `source_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `minutes` int unsigned NOT NULL DEFAULT '0',
  `hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hourly_rate` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `multiplier` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `manual_amount` decimal(15,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_overtime_entries_unique_source` (`payroll_run_id`,`employee_id`,`overtime_rule_id`,`source_key`),
  KEY `payroll_overtime_entries_payroll_run_employee_id_foreign` (`payroll_run_employee_id`),
  KEY `payroll_overtime_entries_employee_id_foreign` (`employee_id`),
  KEY `payroll_overtime_entries_overtime_rule_id_foreign` (`overtime_rule_id`),
  KEY `payroll_overtime_entries_reviewed_by_foreign` (`reviewed_by`),
  KEY `payroll_overtime_entries_review_index` (`payroll_run_id`,`employee_id`,`status`),
  KEY `payroll_overtime_entries_status_index` (`status`),
  CONSTRAINT `payroll_overtime_entries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_overtime_entries_overtime_rule_id_foreign` FOREIGN KEY (`overtime_rule_id`) REFERENCES `overtime_rules` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `payroll_overtime_entries_payroll_run_employee_id_foreign` FOREIGN KEY (`payroll_run_employee_id`) REFERENCES `payroll_run_employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_overtime_entries_payroll_run_id_foreign` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_overtime_entries_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_run_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_run_employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `time_on_duty` decimal(10,2) NOT NULL DEFAULT '0.00',
  `pay_per_hour` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `bonus` decimal(15,2) NOT NULL DEFAULT '0.00',
  `transport_allowance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `employer_pension_contribution` decimal(15,2) NOT NULL DEFAULT '0.00',
  `overtime_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `overtime_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gross_earning` decimal(15,2) NOT NULL DEFAULT '0.00',
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `income_tax` decimal(15,2) NOT NULL DEFAULT '0.00',
  `penalty_hours` decimal(10,2) NOT NULL DEFAULT '0.00',
  `penalty_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `pension_contribution` decimal(15,2) NOT NULL DEFAULT '0.00',
  `loan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `workers_union` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_deduction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_pay` decimal(15,2) NOT NULL DEFAULT '0.00',
  `calculation_snapshot` json DEFAULT NULL,
  `payment_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_run_employees_payroll_run_id_employee_id_unique` (`payroll_run_id`,`employee_id`),
  KEY `payroll_run_employees_employee_id_foreign` (`employee_id`),
  KEY `payroll_run_employees_payment_id_foreign` (`payment_id`),
  CONSTRAINT `payroll_run_employees_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payroll_run_employees_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_run_employees_payroll_run_id_foreign` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `payroll_month` date DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employment_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selected_employee_ids` json DEFAULT NULL,
  `excluded_employee_ids` json DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `pay_date` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `journal_entry_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_runs_created_by_foreign` (`created_by`),
  KEY `payroll_runs_journal_entry_id_foreign` (`journal_entry_id`),
  KEY `payroll_runs_period_type_index` (`period_type`),
  KEY `payroll_runs_payroll_month_index` (`payroll_month`),
  KEY `payroll_runs_department_index` (`department`),
  KEY `payroll_runs_employment_type_index` (`employment_type`),
  CONSTRAINT `payroll_runs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_runs_journal_entry_id_foreign` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_tax_brackets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_tax_brackets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payroll_tax_rule_id` bigint unsigned NOT NULL,
  `min_income` decimal(15,2) NOT NULL,
  `max_income` decimal(15,2) DEFAULT NULL,
  `rate` decimal(5,2) NOT NULL,
  `deduction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_tax_brackets_payroll_tax_rule_id_foreign` (`payroll_tax_rule_id`),
  CONSTRAINT `payroll_tax_brackets_payroll_tax_rule_id_foreign` FOREIGN KEY (`payroll_tax_rule_id`) REFERENCES `payroll_tax_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_tax_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_tax_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jurisdiction` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ET',
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_plan_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_plan_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_plan_id` bigint unsigned DEFAULT NULL,
  `production_plan_machine_id` bigint unsigned NOT NULL,
  `machine_id` bigint unsigned DEFAULT NULL,
  `job_order_id` bigint unsigned NOT NULL,
  `planned_quantity` decimal(15,2) NOT NULL,
  `planned_plates` int NOT NULL DEFAULT '0',
  `planned_rounds` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_plan_items_production_plan_machine_id_foreign` (`production_plan_machine_id`),
  KEY `production_plan_items_job_order_task_id_foreign` (`job_order_id`),
  KEY `production_plan_items_machine_id_foreign` (`machine_id`),
  KEY `production_plan_items_production_plan_id_foreign` (`production_plan_id`),
  CONSTRAINT `production_plan_items_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `production_plan_items_machine_id_foreign` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plan_items_production_plan_id_foreign` FOREIGN KEY (`production_plan_id`) REFERENCES `production_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_plan_items_production_plan_machine_id_foreign` FOREIGN KEY (`production_plan_machine_id`) REFERENCES `production_plan_machines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_plan_machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_plan_machines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_plan_id` bigint unsigned NOT NULL,
  `machine_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_plan_machines_production_plan_id_foreign` (`production_plan_id`),
  KEY `production_plan_machines_machine_id_foreign` (`machine_id`),
  CONSTRAINT `production_plan_machines_machine_id_foreign` FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`),
  CONSTRAINT `production_plan_machines_production_plan_id_foreign` FOREIGN KEY (`production_plan_id`) REFERENCES `production_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_report_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_report_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_report_id` bigint unsigned DEFAULT NULL,
  `production_report_machine_id` bigint unsigned NOT NULL,
  `production_plan_item_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `actual_quantity` decimal(15,2) NOT NULL,
  `plates_used` int NOT NULL DEFAULT '0',
  `rounds` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_report_items_production_report_machine_id_foreign` (`production_report_machine_id`),
  KEY `production_report_items_production_plan_item_id_foreign` (`production_plan_item_id`),
  KEY `production_report_items_production_report_id_foreign` (`production_report_id`),
  CONSTRAINT `production_report_items_production_plan_item_id_foreign` FOREIGN KEY (`production_plan_item_id`) REFERENCES `production_plan_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_report_items_production_report_id_foreign` FOREIGN KEY (`production_report_id`) REFERENCES `production_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_report_items_production_report_machine_id_foreign` FOREIGN KEY (`production_report_machine_id`) REFERENCES `production_report_machines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_report_machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_report_machines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_report_id` bigint unsigned NOT NULL,
  `production_plan_machine_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_report_machines_production_report_id_foreign` (`production_report_id`),
  KEY `production_report_machines_production_plan_machine_id_foreign` (`production_plan_machine_id`),
  CONSTRAINT `production_report_machines_production_plan_machine_id_foreign` FOREIGN KEY (`production_plan_machine_id`) REFERENCES `production_plan_machines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `production_report_machines_production_report_id_foreign` FOREIGN KEY (`production_report_id`) REFERENCES `production_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `production_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `production_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `production_plan_id` bigint unsigned NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `production_reports_production_plan_id_foreign` (`production_plan_id`),
  CONSTRAINT `production_reports_production_plan_id_foreign` FOREIGN KEY (`production_plan_id`) REFERENCES `production_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proforma_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proforma_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proforma_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `task_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `paper` json DEFAULT NULL,
  `deliverables` json DEFAULT NULL,
  `instructions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `inputs` json DEFAULT NULL,
  `cost_breakdown` json DEFAULT NULL,
  `rate_snapshot` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proforma_tasks_proforma_id_foreign` (`proforma_id`),
  CONSTRAINT `proforma_tasks_proforma_id_foreign` FOREIGN KEY (`proforma_id`) REFERENCES `proformas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `proformas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `proformas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `proforma_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_estimate_id` bigint unsigned DEFAULT NULL,
  `partner_id` bigint unsigned DEFAULT NULL,
  `job_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `services` json DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emailed_at` timestamp NULL DEFAULT NULL,
  `email_recipient` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proformas_proforma_number_unique` (`proforma_number`),
  KEY `proformas_partner_id_foreign` (`partner_id`),
  KEY `proformas_approved_by_foreign` (`approved_by`),
  KEY `proformas_cost_estimate_id_foreign` (`cost_estimate_id`),
  CONSTRAINT `proformas_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proformas_cost_estimate_id_foreign` FOREIGN KEY (`cost_estimate_id`) REFERENCES `cost_estimates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `proformas_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_invoice_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_invoice_items_purchase_invoice_id_foreign` (`purchase_invoice_id`),
  KEY `purchase_invoice_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `purchase_invoice_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `purchase_invoice_items_purchase_invoice_id_foreign` FOREIGN KEY (`purchase_invoice_id`) REFERENCES `purchase_invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('draft','posted','partially_paid','paid','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_invoices_partner_id_foreign` (`partner_id`),
  KEY `purchase_invoices_purchase_order_id_foreign` (`purchase_order_id`),
  CONSTRAINT `purchase_invoices_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`),
  CONSTRAINT `purchase_invoices_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `received_quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `purchase_order_items_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `purchase_order_items_purchase_order_id_foreign` (`purchase_order_id`),
  CONSTRAINT `purchase_order_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `po_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `order_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('draft','approved','received','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_po_number_unique` (`po_number`),
  KEY `purchase_orders_partner_id_foreign` (`partner_id`),
  CONSTRAINT `purchase_orders_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscribable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subscribable_id` bigint unsigned NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auth_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_encoding` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_subscribable_morph_idx` (`subscribable_type`,`subscribable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_invoice_id` bigint unsigned NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_invoice_items_sales_invoice_id_foreign` (`sales_invoice_id`),
  CONSTRAINT `sales_invoice_items_sales_invoice_id_foreign` FOREIGN KEY (`sales_invoice_id`) REFERENCES `sales_invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_id` bigint unsigned NOT NULL,
  `job_order_id` bigint unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('draft','issued','partially_paid','paid','void') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_invoices_invoice_number_unique` (`invoice_number`),
  KEY `sales_invoices_partner_id_foreign` (`partner_id`),
  KEY `sales_invoices_job_order_id_foreign` (`job_order_id`),
  CONSTRAINT `sales_invoices_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`),
  CONSTRAINT `sales_invoices_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT '1.00',
  `unit_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_order_items_sales_order_id_foreign` (`sales_order_id`),
  KEY `sales_order_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `sales_order_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_order_items_sales_order_id_foreign` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `partner_id` bigint unsigned DEFAULT NULL,
  `order_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `payment_mode` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `payment_method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_id` bigint unsigned DEFAULT NULL,
  `payment_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','deposit_received','submitted','completed','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_order_number_unique` (`order_number`),
  KEY `sales_orders_warehouse_id_foreign` (`warehouse_id`),
  KEY `sales_orders_partner_id_foreign` (`partner_id`),
  KEY `sales_orders_bank_id_foreign` (`bank_id`),
  CONSTRAINT `sales_orders_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_partner_id_foreign` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sales_orders_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Packledge',
  `company_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `company_phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_tax_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Africa/Addis_Ababa',
  `company_logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT '15.00',
  `vat_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `workers_union_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `workers_union_rate` decimal(5,2) NOT NULL DEFAULT '1.00',
  `employee_pension_rate` decimal(5,2) NOT NULL DEFAULT '7.00',
  `employer_pension_rate` decimal(5,2) NOT NULL DEFAULT '11.00',
  `tax_configuration` json DEFAULT NULL,
  `invoice_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `invoice_due_days` int NOT NULL DEFAULT '30',
  `invoice_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INV',
  `receipt_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'RCP',
  `currency_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ETB',
  `currency_symbol` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Birr',
  `fiscal_calendar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gregorian',
  `email_from_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_from_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_footer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `costing_defaults` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `break_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `grace_minutes` smallint unsigned NOT NULL DEFAULT '0',
  `expected_minutes` smallint unsigned NOT NULL DEFAULT '480',
  `is_night_shift` tinyint(1) NOT NULL DEFAULT '0',
  `overtime_eligible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sizes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `size` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustment_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustment_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_adjustment_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `system_quantity` decimal(15,2) NOT NULL,
  `adjustment_quantity` decimal(15,2) NOT NULL,
  `difference` decimal(15,2) NOT NULL,
  `new_quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_adjustment_items_stock_adjustment_id_foreign` (`stock_adjustment_id`),
  KEY `stock_adjustment_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `stock_adjustment_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `stock_adjustment_items_stock_adjustment_id_foreign` FOREIGN KEY (`stock_adjustment_id`) REFERENCES `stock_adjustments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `adjustment_date` date NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_adjustments_adjustment_number_unique` (`adjustment_number`),
  KEY `stock_adjustments_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_adjustments_created_by_foreign` (`created_by`),
  CONSTRAINT `stock_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_adjustments_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventory_item_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `unit_cost` decimal(15,2) DEFAULT NULL,
  `total_cost` decimal(15,2) DEFAULT NULL,
  `movement_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_warehouse_id_foreign` (`warehouse_id`),
  KEY `stock_movements_item_wh_date_idx` (`inventory_item_id`,`warehouse_id`,`movement_date`),
  CONSTRAINT `stock_movements_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `stock_movements_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfer_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfer_items_stock_transfer_id_foreign` (`stock_transfer_id`),
  KEY `stock_transfer_items_inventory_item_id_foreign` (`inventory_item_id`),
  CONSTRAINT `stock_transfer_items_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `stock_transfer_items_stock_transfer_id_foreign` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_warehouse_id` bigint unsigned NOT NULL,
  `to_warehouse_id` bigint unsigned NOT NULL,
  `transfer_date` datetime NOT NULL,
  `status` enum('draft','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_transfers_transfer_number_unique` (`transfer_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `text_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `text_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_order_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `deliverable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `text_files_job_order_id_foreign` (`job_order_id`),
  KEY `text_files_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `text_files_job_order_id_foreign` FOREIGN KEY (`job_order_id`) REFERENCES `job_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `text_files_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_warehouse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_warehouse` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `warehouse_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_warehouse_user_id_warehouse_id_unique` (`user_id`,`warehouse_id`),
  KEY `user_warehouse_warehouse_id_foreign` (`warehouse_id`),
  CONSTRAINT `user_warehouse_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_warehouse_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role` enum('admin','design','typist','production','finance','sales','warehouse','hr','retail','operations') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sales',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouses_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50001 DROP VIEW IF EXISTS `bank_transactions`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `bank_transactions` AS select concat('payment-',`payments`.`id`) AS `id`,`payments`.`bank_id` AS `bank_id`,`payments`.`id` AS `source_id`,'payment' AS `source_type`,`payments`.`payment_number` AS `transaction_number`,`payments`.`transaction_type` AS `transaction_type`,`payments`.`direction` AS `direction`,(`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0)) AS `amount`,(case when (`payments`.`direction` = 'inbound') then (`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0)) else -((`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0))) end) AS `balance_delta`,`payments`.`payment_date` AS `transaction_date`,(case when (`payments`.`voided_at` is null) then 'posted' else 'voided' end) AS `status`,`payments`.`reference` AS `reference`,`partners`.`name` AS `counterparty`,NULL AS `related_bank_name`,`payments`.`created_at` AS `created_at`,`payments`.`updated_at` AS `updated_at` from (`payments` left join `partners` on((`partners`.`id` = `payments`.`partner_id`))) where ((`payments`.`bank_id` is not null) and (`payments`.`method` in ('bank','bank_transfer','cheque','check')) and (`payments`.`deleted_at` is null)) union all select concat('payment-void-',`payments`.`id`) AS `id`,`payments`.`bank_id` AS `bank_id`,`payments`.`id` AS `source_id`,'payment_void' AS `source_type`,concat('VOID-',`payments`.`payment_number`) AS `transaction_number`,`payments`.`transaction_type` AS `transaction_type`,(case when (`payments`.`direction` = 'inbound') then 'outbound' else 'inbound' end) AS `direction`,(`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0)) AS `amount`,(case when (`payments`.`direction` = 'inbound') then -((`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0))) else (`payments`.`amount` - coalesce(`payments`.`withholding_amount`,0)) end) AS `balance_delta`,cast(`payments`.`voided_at` as date) AS `transaction_date`,'posted' AS `status`,`payments`.`void_reason` AS `reference`,`partners`.`name` AS `counterparty`,NULL AS `related_bank_name`,`payments`.`voided_at` AS `created_at`,`payments`.`voided_at` AS `updated_at` from (`payments` left join `partners` on((`partners`.`id` = `payments`.`partner_id`))) where ((`payments`.`bank_id` is not null) and (`payments`.`method` in ('bank','bank_transfer','cheque','check')) and (`payments`.`voided_at` is not null) and (`payments`.`deleted_at` is null)) union all select concat('bank-transfer-out-',`bank_transfers`.`id`) AS `id`,`bank_transfers`.`from_bank_id` AS `bank_id`,`bank_transfers`.`id` AS `source_id`,'bank_transfer' AS `source_type`,`bank_transfers`.`transfer_number` AS `transaction_number`,'transfer_out' AS `transaction_type`,'outbound' AS `direction`,`bank_transfers`.`amount` AS `amount`,-(`bank_transfers`.`amount`) AS `balance_delta`,`bank_transfers`.`transfer_date` AS `transaction_date`,`bank_transfers`.`status` AS `status`,`bank_transfers`.`reference` AS `reference`,NULL AS `counterparty`,`to_banks`.`name` AS `related_bank_name`,`bank_transfers`.`created_at` AS `created_at`,`bank_transfers`.`updated_at` AS `updated_at` from (`bank_transfers` join `banks` `to_banks` on((`to_banks`.`id` = `bank_transfers`.`to_bank_id`))) where (`bank_transfers`.`status` = 'completed') union all select concat('bank-transfer-in-',`bank_transfers`.`id`) AS `id`,`bank_transfers`.`to_bank_id` AS `bank_id`,`bank_transfers`.`id` AS `source_id`,'bank_transfer' AS `source_type`,`bank_transfers`.`transfer_number` AS `transaction_number`,'transfer_in' AS `transaction_type`,'inbound' AS `direction`,`bank_transfers`.`amount` AS `amount`,`bank_transfers`.`amount` AS `balance_delta`,`bank_transfers`.`transfer_date` AS `transaction_date`,`bank_transfers`.`status` AS `status`,`bank_transfers`.`reference` AS `reference`,NULL AS `counterparty`,`from_banks`.`name` AS `related_bank_name`,`bank_transfers`.`created_at` AS `created_at`,`bank_transfers`.`updated_at` AS `updated_at` from (`bank_transfers` join `banks` `from_banks` on((`from_banks`.`id` = `bank_transfers`.`from_bank_id`))) where (`bank_transfers`.`status` = 'completed') union all select concat('cash-deposit-',`cash_deposits`.`id`) AS `id`,`cash_deposits`.`bank_id` AS `bank_id`,`cash_deposits`.`id` AS `source_id`,'cash_deposit' AS `source_type`,`cash_deposits`.`deposit_number` AS `transaction_number`,'cash_deposit' AS `transaction_type`,'inbound' AS `direction`,`cash_deposits`.`amount` AS `amount`,`cash_deposits`.`amount` AS `balance_delta`,`cash_deposits`.`deposit_date` AS `transaction_date`,`cash_deposits`.`status` AS `status`,`cash_deposits`.`reference` AS `reference`,coalesce(`cash_accounts`.`name`,`income_accounts`.`name`) AS `counterparty`,NULL AS `related_bank_name`,`cash_deposits`.`created_at` AS `created_at`,`cash_deposits`.`updated_at` AS `updated_at` from ((`cash_deposits` left join `accounts` `cash_accounts` on((`cash_accounts`.`id` = `cash_deposits`.`cash_account_id`))) left join `accounts` `income_accounts` on((`income_accounts`.`id` = `cash_deposits`.`income_account_id`))) where (`cash_deposits`.`status` in ('posted','reversed')) union all select concat('cash-deposit-reversal-',`cash_deposits`.`id`) AS `id`,`cash_deposits`.`bank_id` AS `bank_id`,`cash_deposits`.`id` AS `source_id`,'cash_deposit_reversal' AS `source_type`,concat('REV-',`cash_deposits`.`deposit_number`) AS `transaction_number`,'cash_deposit_reversal' AS `transaction_type`,'outbound' AS `direction`,`cash_deposits`.`amount` AS `amount`,-(`cash_deposits`.`amount`) AS `balance_delta`,cast(`cash_deposits`.`reversed_at` as date) AS `transaction_date`,'posted' AS `status`,`cash_deposits`.`reversal_reason` AS `reference`,coalesce(`cash_accounts`.`name`,`income_accounts`.`name`) AS `counterparty`,NULL AS `related_bank_name`,`cash_deposits`.`reversed_at` AS `created_at`,`cash_deposits`.`reversed_at` AS `updated_at` from ((`cash_deposits` left join `accounts` `cash_accounts` on((`cash_accounts`.`id` = `cash_deposits`.`cash_account_id`))) left join `accounts` `income_accounts` on((`income_accounts`.`id` = `cash_deposits`.`income_account_id`))) where ((`cash_deposits`.`status` = 'reversed') and (`cash_deposits`.`reversed_at` is not null)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

/*M!999999\- enable the sandbox mode */
SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_02_18_112305_create_partners_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_02_18_112306_create_job_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_02_18_112307_create_job_order_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_02_18_112308_create_dispatches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_02_18_112309_create_dispatch_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_02_18_112310_create_sales_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_02_18_112311_create_sales_invoice_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_02_18_112312_create_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_02_18_112312_create_purchase_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_02_18_112313_create_purchase_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_02_18_112314_create_purchase_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_02_18_112315_create_purchase_invoice_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_02_18_112316_create_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_02_18_112317_create_payment_allocations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_02_18_112318_create_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_02_18_112320_create_inventory_balances_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_02_18_112321_create_stock_movements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_02_18_112322_create_stock_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_02_18_112323_create_stock_transfer_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_02_18_112324_create_accounts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_02_18_112325_create_journal_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_02_18_112326_create_journal_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_02_21_112331_add_unit_fields_to_inventory_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_02_22_083004_add_subtotal_to_purchase_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_02_22_103948_add_received_quantity_to_purchase_order_items',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_02_22_104215_create_goods_receipts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_02_22_104303_create_goods_receipt_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_02_22_143500_add_posted_at_to_goods_receipts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_02_22_145000_make_warehouse_id_nullable_in_goods_receipts',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_02_22_161500_update_job_orders_and_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_02_26_110912_create_artworks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_02_26_110912_update_job_orders_for_production_flow',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_03_02_082442_cleanup_legacy_job_order_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_03_02_090913_update_stock_movement_type_to_string',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_03_07_125400_add_type_and_sellable_to_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_03_07_125451_add_production_mode_to_job_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_03_07_125512_create_job_order_outputs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_03_07_125623_create_sales_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_03_07_125624_create_sales_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_03_08_074009_add_job_order_id_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_03_08_121223_refactor_payments_for_polymorphic_allocations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_03_09_065922_create_stock_adjustments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_03_09_065923_create_stock_adjustment_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_03_09_065923_update_inventory_balances_table_unique_constraint',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_03_09_065923_update_stock_movements_table_for_new_architecture',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_03_09_082126_update_stock_adjustment_items_table_to_delta',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_03_09_083817_add_price_to_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_03_09_131703_create_production_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_03_09_131705_create_machines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_03_09_131705_create_production_plan_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_03_09_131706_create_production_reports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_03_09_131707_create_production_report_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_03_30_063941_add_role_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_04_08_132411_create_imports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_04_08_132412_create_exports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_04_08_132413_create_failed_import_rows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_04_09_052214_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_04_09_070124_add_status_to_job_order_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_04_09_094126_add_status_to_purchase_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_04_09_115722_add_default_value_to_job_orders_advance_amount',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_04_19_123419_add_is_default_to_warehouses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_04_19_124300_create_material_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_04_19_135757_add_job_order_task_id_to_artworks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_04_19_150632_add_warehouse_id_to_dispatches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_04_20_080649_update_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_04_20_082653_change_movement_date_to_datetime_in_stock_movements_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_04_21_064213_add_size_to_job_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_04_21_083348_update_job_orders_job_type_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_04_21_090628_create_sizes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_04_21_122546_create_production_plan_machines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_04_21_123927_create_production_report_machines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_04_21_182755_create_employees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_04_21_184524_rename_unit_cost_to_task_cost_in_job_order_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_04_21_185244_add_payment_type_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_04_22_045553_add_image_to_employees_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_04_22_045642_create_employee_salary_histories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_04_22_090000_add_job_order_id_to_artworks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_04_22_091000_add_production_report_id_to_production_report_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_04_22_092000_add_machine_id_to_production_plan_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_04_22_093000_add_production_plan_id_to_production_plan_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_04_22_100000_add_transaction_type_and_account_fields_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_04_22_110000_add_void_fields_to_payments_and_reversal_link_to_journal_entries',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_04_22_133822_create_email_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_04_23_120000_add_designer_id_to_job_order_tasks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_04_24_180000_create_material_issue_approvals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_04_24_180100_create_user_warehouse_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_04_24_190000_add_payment_fields_to_sales_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_04_26_120000_create_banks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_04_26_120100_create_bank_transfers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_04_26_120200_add_bank_id_to_payments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_04_27_101235_create_invoices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_04_27_110000_add_category_to_inventory_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_04_27_110100_add_reason_to_material_requests_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_04_28_120901_add_due_date_to_job_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_04_28_120902_add_due_date_to_sales_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_04_28_120904_add_due_date_to_purchase_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_04_28_131707_add_baseline_rounds_per_week_to_machines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_05_04_000000_add_status_to_dispatches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_05_04_084141_update_job_orders_status_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_05_05_170342_add_unpaid_and_partial_to_invoices_status_enum',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_05_07_054322_create_filament_exceptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_05_07_062044_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_05_07_095439_add_tax_amount_to_orders_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_05_07_104620_add_subtotal_and_total_to_job_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_05_07_114524_remove_total_price_from_job_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_05_07_151532_add_unique_constraint_to_inventory_balances',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_05_07_164451_add_email_to_partners_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_05_08_175404_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_05_09_072749_add_batch_uuid_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_05_09_170155_create_text_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_05_10_031109_update_purchase_order_items_foreign_key_cascade',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_05_10_054835_make_partner_id_nullable_on_job_orders',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_05_11_000001_rename_job_order_id_to_job_order_task_id_in_text_files_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_05_11_082003_add_instructions_to_job_order_tasks_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_05_11_105501_add_soft_deletes_to_partners_job_orders_invoices_payments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_05_11_105503_add_notified_late_at_to_job_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_05_13_000001_add_unit_label_to_sales_order_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_05_16_090110_change_transfer_date_to_datetime_in_stock_transfers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_05_17_194957_create_push_subscriptions_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_05_20_000001_create_hr_attendance_payroll_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_05_20_000002_add_employment_type_to_existing_employees_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_05_20_000003_create_attendance_segments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_05_21_000001_refine_payroll_run_employee_calculation_fields',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2026_05_21_000002_simplify_attendance_payroll_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_05_21_000003_add_finance_template_payroll_fields',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_05_21_000004_remove_legacy_payroll_fields',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_05_21_000005_cleanup_attendance_loans_and_payroll_settings',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_05_21_153948_add_payable_link_to_payments_and_remove_payment_allocations',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_05_21_195103_drop_work_schedule_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_05_22_115831_create_bank_transactions_view',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_05_22_145246_add_submitted_status_to_sales_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_05_22_155306_add_low_stock_threshold_to_inventory_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_05_22_171915_add_global_pension_rates_to_settings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_05_22_171915_create_employee_loan_installments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_05_22_171915_rename_payroll_pension_columns',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_05_22_171951_drop_employee_pension_rates_from_employees_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_05_22_180048_add_termination_date_to_employees_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_05_23_000001_add_transport_allowance_to_employees_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_05_24_000001_add_deliverables_to_job_order_tasks_and_is_approved_to_text_files_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_05_24_000002_add_deliverable_to_artworks_and_text_files_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_05_24_000002_add_typist_id_to_job_order_tasks_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_05_24_000003_add_typist_to_users_role_enum',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_05_25_002218_add_bank_transfer_to_payments_method_enum',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_05_25_122938_create_proformas_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_05_25_122939_create_proforma_tasks_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_05_25_122948_replace_cost_calc_file_with_required_proforma_on_job_orders',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_05_30_000001_add_costing_module_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_05_30_090724_add_costing_ux_fields_to_machines',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_05_30_141639_drop_unused_costing_metadata_fields',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_06_09_000001_create_bids_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_06_09_000002_create_bid_bonds_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_06_09_000003_add_bid_bond_fields_to_bids_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_06_09_000004_add_bid_files_to_bids_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_06_10_000001_create_unified_bonds_and_add_cpo_payment_method',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_06_15_000001_add_cpo_bank_name_to_bonds_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_06_24_000001_register_books_costing_service_type',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_06_27_000001_restore_job_order_cost_calc_file_and_remove_proforma_id',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_06_27_000002_add_attachment_to_journal_entries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_06_27_000002_add_withholding_amount_to_payments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2026_06_29_000001_add_fiscal_calendar_to_settings',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2026_06_30_200001_add_workflow_fields_to_payroll_runs_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2026_07_06_111348_create_overtime_types_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2026_07_06_111348_create_payroll_overtime_entries_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2026_07_06_172804_rename_overtime_types_to_overtime_rules',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2026_07_08_142145_add_default_tracking_type_to_accounts_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2026_07_08_151853_create_expense_tracking_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2026_07_08_151904_add_expense_tracking_fields_to_payments_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2026_07_11_210038_create_accounting_integrations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2026_07_11_210040_create_accounting_account_mappings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2026_07_11_210042_create_accounting_exports_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2026_07_11_210044_create_accounting_export_journal_entry_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2026_07_12_112842_add_timezone_to_settings_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2026_07_18_181522_create_dieline_templates_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2026_07_18_181522_create_dielines_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2026_08_04_155615_remove_demo_dieline_templates',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2026_08_19_161248_create_cash_deposits_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2026_08_19_161257_add_opening_balance_and_rebuild_bank_transactions_view',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2026_08_26_000001_add_other_cash_sources_account_and_rebuild_bank_transactions_view',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2026_08_26_000002_add_other_income_deposit_fields_to_cash_deposits',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2026_08_29_000001_add_bank_id_to_sales_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2026_08_29_000002_add_deposit_received_status_to_sales_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2026_10_13_121614_consolidate_job_order_tasks',2);
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
