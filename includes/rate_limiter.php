<?php
require_once 'config.php';

class RateLimiter {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function canDownload($ipAddress) {
        $this->cleanupOldEntries();
        
        $stmt = $this->pdo->prepare("
            SELECT download_count, window_start 
            FROM rate_limits 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return true; // No previous downloads
        }
        
        $windowStart = new DateTime($result['window_start']);
        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $windowStart->getTimestamp();
        
        // If window has expired, reset the counter
        if ($timeDiff >= RATE_LIMIT_WINDOW) {
            $this->resetCounter($ipAddress);
            return true;
        }
        
        // Check if under limit
        return $result['download_count'] < RATE_LIMIT_DOWNLOADS;
    }
    
    public function recordDownload($ipAddress) {
        $stmt = $this->pdo->prepare("
            INSERT INTO rate_limits (ip_address, download_count, window_start, last_download)
            VALUES (?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                download_count = CASE 
                    WHEN TIMESTAMPDIFF(SECOND, window_start, NOW()) >= ? THEN 1
                    ELSE download_count + 1
                END,
                window_start = CASE 
                    WHEN TIMESTAMPDIFF(SECOND, window_start, NOW()) >= ? THEN NOW()
                    ELSE window_start
                END,
                last_download = NOW()
        ");
        $stmt->execute([$ipAddress, RATE_LIMIT_WINDOW, RATE_LIMIT_WINDOW]);
    }
    
    public function getRemainingDownloads($ipAddress) {
        $this->cleanupOldEntries();
        
        $stmt = $this->pdo->prepare("
            SELECT download_count, window_start 
            FROM rate_limits 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return RATE_LIMIT_DOWNLOADS;
        }
        
        $windowStart = new DateTime($result['window_start']);
        $now = new DateTime();
        $timeDiff = $now->getTimestamp() - $windowStart->getTimestamp();
        
        // If window has expired
        if ($timeDiff >= RATE_LIMIT_WINDOW) {
            return RATE_LIMIT_DOWNLOADS;
        }
        
        return max(0, RATE_LIMIT_DOWNLOADS - $result['download_count']);
    }
    
    public function getTimeUntilReset($ipAddress) {
        $stmt = $this->pdo->prepare("
            SELECT window_start 
            FROM rate_limits 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ipAddress]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return 0;
        }
        
        $windowStart = new DateTime($result['window_start']);
        $resetTime = clone $windowStart;
        $resetTime->add(new DateInterval('PT' . RATE_LIMIT_WINDOW . 'S'));
        $now = new DateTime();
        
        return max(0, $resetTime->getTimestamp() - $now->getTimestamp());
    }
    
    private function resetCounter($ipAddress) {
        $stmt = $this->pdo->prepare("
            UPDATE rate_limits 
            SET download_count = 0, window_start = NOW() 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ipAddress]);
    }
    
    private function cleanupOldEntries() {
        // Clean up entries older than 24 hours
        $stmt = $this->pdo->prepare("
            DELETE FROM rate_limits 
            WHERE last_download < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }
}
?>
