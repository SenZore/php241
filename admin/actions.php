<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_settings.php';

header('Content-Type: application/json');

$auth = new AdminAuth();
$settings = new AdminSettings();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'clear_cache':
        try {
            // Clear various caches
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Clear session files older than 24 hours
            $sessionPath = session_save_path();
            if ($sessionPath) {
                $files = glob($sessionPath . '/sess_*');
                $now = time();
                foreach ($files as $file) {
                    if (filemtime($file) < $now - 86400) {
                        unlink($file);
                    }
                }
            }
            
            $auth->logAction($currentUser['id'], 'clear_cache', 'system', null, 'Cleared system cache');
            echo json_encode(['success' => true, 'message' => 'Cache cleared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error clearing cache: ' . $e->getMessage()]);
        }
        break;
        
    case 'update_ytdlp':
        try {
            $output = shell_exec('pip3 install -U yt-dlp 2>&1');
            
            if (strpos($output, 'Successfully') !== false || strpos($output, 'already satisfied') !== false) {
                $auth->logAction($currentUser['id'], 'update_ytdlp', 'system', null, 'Updated yt-dlp');
                echo json_encode(['success' => true, 'message' => 'yt-dlp updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update yt-dlp: ' . $output]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating yt-dlp: ' . $e->getMessage()]);
        }
        break;
        
    case 'run_cleanup':
        try {
            // Run cleanup function
            cleanupOldDownloads('7 days');
            
            // Clean up old logs
            $stmt = $pdo->prepare("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            
            // Clean up old system stats
            $stmt = $pdo->prepare("DELETE FROM system_stats WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();
            
            // Clean up expired sessions
            $auth->cleanupExpiredSessions();
            
            $auth->logAction($currentUser['id'], 'run_cleanup', 'system', null, 'Ran manual cleanup');
            echo json_encode(['success' => true, 'message' => 'Cleanup completed successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error running cleanup: ' . $e->getMessage()]);
        }
        break;
        
    case 'get_system_info':
        try {
            $info = [
                'php_version' => PHP_VERSION,
                'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
                'ytdlp_version' => trim(shell_exec('yt-dlp --version 2>/dev/null') ?: 'Not installed'),
                'nginx_status' => shell_exec('systemctl is-active nginx 2>/dev/null') ?: 'unknown',
                'disk_usage' => disk_total_space('/') ? round((1 - disk_free_space('/') / disk_total_space('/')) * 100, 2) : 0,
                'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
            ];
            
            echo json_encode(['success' => true, 'data' => $info]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error getting system info: ' . $e->getMessage()]);
        }
        break;
        
    case 'restart_services':
        if ($currentUser['role'] === 'admin') {
            try {
                $services = ['nginx', 'php8.1-fpm'];
                $results = [];
                
                foreach ($services as $service) {
                    $output = shell_exec("systemctl restart $service 2>&1");
                    $status = shell_exec("systemctl is-active $service 2>/dev/null");
                    $results[$service] = trim($status) === 'active';
                }
                
                $auth->logAction($currentUser['id'], 'restart_services', 'system', null, 'Restarted system services');
                echo json_encode(['success' => true, 'data' => $results]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error restarting services: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Admin role required']);
        }
        break;
        
    case 'get_download_stats':
        try {
            $stats = [];
            
            // Downloads by day (last 7 days)
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM downloads 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date
            ");
            $stmt->execute();
            $stats['daily'] = $stmt->fetchAll();
            
            // Downloads by format
            $stmt = $pdo->prepare("
                SELECT format, COUNT(*) as count
                FROM downloads 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND format IS NOT NULL
                GROUP BY format
            ");
            $stmt->execute();
            $stats['formats'] = $stmt->fetchAll();
            
            // Top downloading IPs
            $stmt = $pdo->prepare("
                SELECT ip_address, COUNT(*) as count
                FROM downloads 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY ip_address
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $stats['top_ips'] = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $stats]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error getting download stats: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
