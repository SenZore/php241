-- YouTube Downloader Database Schema
-- Version: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Database: ytdlp_db

-- --------------------------------------------------------

-- Table structure for table `downloads`
CREATE TABLE IF NOT EXISTS `downloads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `format` varchar(50) NOT NULL,
  `quality` varchar(50) NOT NULL,
  `status` enum('pending','downloading','completed','failed') NOT NULL DEFAULT 'pending',
  `progress` int(11) DEFAULT 0,
  `file_size` bigint(20) DEFAULT NULL,
  `download_time` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `rate_limits`
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `downloads_count` int(11) NOT NULL DEFAULT 0,
  `last_download` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip` (`ip_address`),
  KEY `idx_last_download` (`last_download`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `system_stats`
CREATE TABLE IF NOT EXISTS `system_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cpu_usage` decimal(5,2) NOT NULL,
  `memory_usage` decimal(5,2) NOT NULL,
  `disk_usage` decimal(5,2) NOT NULL,
  `load_average` varchar(50) NOT NULL,
  `uptime` varchar(100) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `admin_users`
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','moderator') NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `admin_sessions`
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_admin_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `site_settings`
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `user_permissions`
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_type` enum('guest','vip','banned') NOT NULL DEFAULT 'guest',
  `download_limit` int(11) NOT NULL DEFAULT 5,
  `time_window` int(11) NOT NULL DEFAULT 30,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_permission` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Table structure for table `admin_logs`
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `resource_type` varchar(50) NOT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_admin_logs_user` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Insert default settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('site_title', 'YouTube Downloader', 'string', 'Website title'),
('site_description', 'Professional YouTube video downloader using yt-dlp', 'string', 'Website description'),
('maintenance_mode', 'false', 'boolean', 'Enable/disable maintenance mode'),
('maintenance_message', 'Site is under maintenance. Please check back later.', 'string', 'Maintenance mode message'),
('global_rate_limit', '5', 'integer', 'Global download rate limit per time window'),
('global_time_window', '30', 'integer', 'Global rate limit time window in minutes'),
('max_file_size', '500', 'integer', 'Maximum download file size in MB'),
('cleanup_interval', '7', 'integer', 'Cleanup interval in days'),
('admin_email', 'admin@localhost', 'string', 'Administrator email'),
('site_domain', 'localhost', 'string', 'Site domain name')
ON DUPLICATE KEY UPDATE 
  `setting_value` = VALUES(`setting_value`),
  `description` = VALUES(`description`);

COMMIT;
