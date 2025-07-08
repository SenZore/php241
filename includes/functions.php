<?php
require_once 'config.php';

function getSystemStats() {
    $stats = [];
    
    // CPU Usage
    $cpuLoad = sys_getloadavg();
    $cpuCores = getNumberOfCores();
    $stats['cpu'] = round(($cpuLoad[0] / $cpuCores) * 100, 1);
    
    // RAM Usage
    $memInfo = getMemoryInfo();
    $stats['ram'] = round((($memInfo['total'] - $memInfo['free'] - $memInfo['buffers'] - $memInfo['cached']) / $memInfo['total']) * 100, 1);
    
    // Disk Usage
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $stats['disk'] = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
    
    // Load Average
    $stats['load'] = implode(', ', array_map(function($load) {
        return number_format($load, 2);
    }, $cpuLoad));
    
    // Uptime
    $stats['uptime'] = getUptime();
    
    return $stats;
}

function getNumberOfCores() {
    $cores = 1;
    if (is_file('/proc/cpuinfo')) {
        $cores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
    }
    return $cores ?: 1;
}

function getMemoryInfo() {
    $memInfo = [];
    if (is_file('/proc/meminfo')) {
        $lines = file('/proc/meminfo');
        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s*(\d+)\s*kB/', $line, $matches)) {
                $memInfo[strtolower($matches[1])] = intval($matches[2]) * 1024;
            }
        }
    }
    return $memInfo;
}

function getUptime() {
    if (is_file('/proc/uptime')) {
        $uptime = floatval(explode(' ', file_get_contents('/proc/uptime'))[0]);
        return formatUptime($uptime);
    }
    return 'Unknown';
}

function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = $days . 'd';
    if ($hours > 0) $parts[] = $hours . 'h';
    if ($minutes > 0) $parts[] = $minutes . 'm';
    
    return implode(' ', $parts) ?: '0m';
}

function validateYouTubeURL($url) {
    $allowedDomains = ALLOWED_DOMAINS;
    $parsedUrl = parse_url($url);
    
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
        return false;
    }
    
    $host = strtolower($parsedUrl['host']);
    foreach ($allowedDomains as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return true;
        }
    }
    
    return false;
}

function sanitizeFilename($filename) {
    // Remove or replace problematic characters
    $filename = preg_replace('/[^\w\s\-\.\(\)]/', '', $filename);
    $filename = preg_replace('/\s+/', '_', $filename);
    return trim($filename, '_');
}

function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function logError($message, $exception = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [ERROR] $message";
    
    if ($exception) {
        $logEntry .= " - " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
    }
    
    $logEntry .= PHP_EOL;
    file_put_contents(ERROR_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

function getVideoInfo($url) {
    $cmd = escapeshellcmd(YTDLP_PATH) . ' --dump-json --no-download ' . escapeshellarg($url) . ' 2>&1';
    $output = shell_exec($cmd);
    
    if ($output) {
        $json = json_decode($output, true);
        if ($json && isset($json['title'])) {
            return [
                'title' => $json['title'],
                'duration' => $json['duration'] ?? 0,
                'uploader' => $json['uploader'] ?? 'Unknown',
                'upload_date' => $json['upload_date'] ?? date('Ymd'),
                'thumbnail' => $json['thumbnail'] ?? null
            ];
        }
    }
    
    return null;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

function isYtDlpInstalled() {
    $output = shell_exec('which yt-dlp 2>/dev/null');
    return !empty(trim($output));
}

function updateYtDlp() {
    $cmd = escapeshellcmd(YTDLP_PATH) . ' --update 2>&1';
    return shell_exec($cmd);
}

function getActiveDownloads() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM downloads 
        WHERE status IN ('pending', 'downloading')
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['count'] ?? 0;
}

function getRecentDownloads($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT video_title, created_at, status, format, quality
        FROM downloads 
        WHERE status = 'completed'
        ORDER BY completed_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

function recordSystemStats() {
    global $pdo;
    
    $stats = getSystemStats();
    $activeDownloads = getActiveDownloads();
    
    $stmt = $pdo->prepare("
        INSERT INTO system_stats (cpu_usage, ram_usage, disk_usage, load_average, uptime, active_downloads)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $stats['cpu'],
        $stats['ram'],
        $stats['disk'],
        $stats['load'],
        $stats['uptime'],
        $activeDownloads
    ]);
    
    // Clean up old stats (keep only last 24 hours)
    $stmt = $pdo->prepare("
        DELETE FROM system_stats 
        WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
}

function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function cleanupOldDownloads($olderThan = '7 days') {
    global $pdo;
    
    // Get files to delete
    $stmt = $pdo->prepare("
        SELECT file_path 
        FROM downloads 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL $olderThan) 
        AND file_path IS NOT NULL
    ");
    $stmt->execute();
    $files = $stmt->fetchAll();
    
    // Delete physical files
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }
    
    // Delete database records
    $stmt = $pdo->prepare("
        DELETE FROM downloads 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL $olderThan)
    ");
    $stmt->execute();
    
    logMessage("Cleaned up " . count($files) . " old download files");
}
?>
