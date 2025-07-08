<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'ytdlp_user');
define('DB_PASS', 'ytdlp_password');
define('DB_NAME', 'ytdlp_db');

// Rate limiting configuration
define('RATE_LIMIT_DOWNLOADS', 5);
define('RATE_LIMIT_WINDOW', 1800); // 30 minutes in seconds

// Download configuration
define('DOWNLOAD_DIR', '/var/www/html/downloads/');
define('MAX_FILE_SIZE', '2G'); // Maximum file size for yt-dlp
define('YTDLP_PATH', '/usr/local/bin/yt-dlp');

// SSL and domain configuration
define('DOMAIN_NAME', getenv('DOMAIN_NAME') ?: 'your-domain.com');
define('SSL_EMAIL', getenv('SSL_EMAIL') ?: 'admin@your-domain.com');

// Security settings
define('ALLOWED_DOMAINS', [
    'youtube.com',
    'youtu.be',
    'www.youtube.com',
    'm.youtube.com'
]);

// System monitoring
define('ENABLE_SYSTEM_MONITORING', true);
define('MONITORING_INTERVAL', 5); // seconds

// Error logging
define('LOG_FILE', '/var/log/ytdlp/app.log');
define('ERROR_LOG_FILE', '/var/log/ytdlp/error.log');

// Timezone
date_default_timezone_set('UTC');

// Initialize database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check configuration.");
}

// Create tables if they don't exist
$createTables = "
CREATE TABLE IF NOT EXISTS downloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    video_url TEXT NOT NULL,
    video_title VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT,
    format VARCHAR(10),
    quality VARCHAR(20),
    status ENUM('pending', 'downloading', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_ip_created (ip_address, created_at),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    download_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_download TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    custom_limit INT NULL,
    custom_window INT NULL,
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_window (window_start),
    INDEX idx_last_download (last_download)
);

CREATE TABLE IF NOT EXISTS system_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cpu_usage DECIMAL(5,2),
    ram_usage DECIMAL(5,2),
    disk_usage DECIMAL(5,2),
    load_average VARCHAR(20),
    uptime VARCHAR(50),
    active_downloads INT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recorded (recorded_at)
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'moderator') DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,
    user_type ENUM('guest', 'vip', 'banned') DEFAULT 'guest',
    custom_rate_limit INT NULL,
    custom_rate_window INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_user_type (user_type)
);

CREATE TABLE IF NOT EXISTS admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id VARCHAR(100),
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin (admin_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Insert default settings
INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('maintenance_mode', 'false', 'boolean', 'Enable or disable maintenance mode'),
('maintenance_message', 'Site is under maintenance. Please check back later.', 'string', 'Message shown during maintenance'),
('default_rate_limit', '5', 'number', 'Default downloads per time window'),
('default_rate_window', '1800', 'number', 'Default time window in seconds'),
('max_file_size', '2G', 'string', 'Maximum file size for downloads'),
('allowed_formats', '[\"mp4\", \"mp3\", \"webm\"]', 'json', 'Allowed download formats'),
('site_title', 'YouTube Downloader - YT-DLP', 'string', 'Site title'),
('site_description', 'Professional YouTube video downloader with real-time monitoring', 'string', 'Site description'),
('enable_monitoring', 'true', 'boolean', 'Enable real-time system monitoring'),
('cleanup_interval', '7', 'number', 'Days to keep downloaded files'),
('log_retention', '30', 'number', 'Days to keep log files');
";

try {
    $pdo->exec($createTables);
} catch (PDOException $e) {
    error_log("Failed to create tables: " . $e->getMessage());
}
?>
