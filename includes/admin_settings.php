<?php
require_once 'config.php';

class AdminSettings {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function getSetting($key, $default = null) {
        $stmt = $this->pdo->prepare("SELECT setting_value, setting_type FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            return $default;
        }
        
        return $this->castValue($setting['setting_value'], $setting['setting_type']);
    }
    
    public function setSetting($key, $value, $type = 'string', $description = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                setting_type = VALUES(setting_type),
                description = COALESCE(VALUES(description), description)
        ");
        
        $stringValue = $this->stringifyValue($value, $type);
        return $stmt->execute([$key, $stringValue, $type, $description]);
    }
    
    public function getAllSettings() {
        $stmt = $this->pdo->prepare("SELECT * FROM site_settings ORDER BY setting_key");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = [
                'value' => $this->castValue($setting['setting_value'], $setting['setting_type']),
                'type' => $setting['setting_type'],
                'description' => $setting['description'],
                'updated_at' => $setting['updated_at']
            ];
        }
        
        return $result;
    }
    
    public function updateSettings($settings) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($settings as $key => $data) {
                $value = $data['value'] ?? $data;
                $type = $data['type'] ?? 'string';
                $description = $data['description'] ?? null;
                
                $this->setSetting($key, $value, $type, $description);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function isMaintenanceMode() {
        return $this->getSetting('maintenance_mode', false);
    }
    
    public function setMaintenanceMode($enabled, $message = null) {
        $this->setSetting('maintenance_mode', $enabled, 'boolean');
        
        if ($message !== null) {
            $this->setSetting('maintenance_message', $message, 'string');
        }
        
        return true;
    }
    
    public function getRateLimitSettings() {
        return [
            'default_limit' => $this->getSetting('default_rate_limit', 5),
            'default_window' => $this->getSetting('default_rate_window', 1800)
        ];
    }
    
    public function setRateLimitSettings($limit, $window) {
        $this->setSetting('default_rate_limit', $limit, 'number');
        $this->setSetting('default_rate_window', $window, 'number');
        return true;
    }
    
    public function getUserPermissions($ipAddress) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_permissions 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ipAddress]);
        return $stmt->fetch();
    }
    
    public function setUserPermissions($ipAddress, $userType, $customRateLimit = null, $customRateWindow = null, $notes = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_permissions (ip_address, user_type, custom_rate_limit, custom_rate_window, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                user_type = VALUES(user_type),
                custom_rate_limit = VALUES(custom_rate_limit),
                custom_rate_window = VALUES(custom_rate_window),
                notes = VALUES(notes),
                updated_at = NOW()
        ");
        
        return $stmt->execute([$ipAddress, $userType, $customRateLimit, $customRateWindow, $notes]);
    }
    
    public function getAllUserPermissions($limit = 100, $offset = 0) {
        $stmt = $this->pdo->prepare("
            SELECT up.*, 
                   COUNT(d.id) as download_count,
                   MAX(d.created_at) as last_download
            FROM user_permissions up
            LEFT JOIN downloads d ON up.ip_address = d.ip_address
            GROUP BY up.ip_address
            ORDER BY up.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function deleteUserPermissions($ipAddress) {
        $stmt = $this->pdo->prepare("DELETE FROM user_permissions WHERE ip_address = ?");
        return $stmt->execute([$ipAddress]);
    }
    
    public function getCustomRateLimit($ipAddress) {
        $permissions = $this->getUserPermissions($ipAddress);
        
        if ($permissions && $permissions['user_type'] === 'banned') {
            return ['limit' => 0, 'window' => 0];
        }
        
        if ($permissions && $permissions['custom_rate_limit'] !== null) {
            return [
                'limit' => $permissions['custom_rate_limit'],
                'window' => $permissions['custom_rate_window'] ?? $this->getSetting('default_rate_window', 1800)
            ];
        }
        
        if ($permissions && $permissions['user_type'] === 'vip') {
            return [
                'limit' => $this->getSetting('default_rate_limit', 5) * 3, // VIP gets 3x normal rate
                'window' => $this->getSetting('default_rate_window', 1800)
            ];
        }
        
        return [
            'limit' => $this->getSetting('default_rate_limit', 5),
            'window' => $this->getSetting('default_rate_window', 1800)
        ];
    }
    
    public function getSystemStats() {
        $stats = [];
        
        // Total downloads
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM downloads");
        $stmt->execute();
        $stats['total_downloads'] = $stmt->fetchColumn();
        
        // Downloads today
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as today FROM downloads WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $stats['downloads_today'] = $stmt->fetchColumn();
        
        // Active downloads
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as active FROM downloads WHERE status IN ('pending', 'downloading')");
        $stmt->execute();
        $stats['active_downloads'] = $stmt->fetchColumn();
        
        // Total users (unique IPs)
        $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT ip_address) as users FROM downloads");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetchColumn();
        
        // Storage used
        $stmt = $this->pdo->prepare("SELECT SUM(file_size) as storage FROM downloads WHERE file_size IS NOT NULL");
        $stmt->execute();
        $stats['storage_used'] = $stmt->fetchColumn() ?? 0;
        
        // Average file size
        $stmt = $this->pdo->prepare("SELECT AVG(file_size) as avg_size FROM downloads WHERE file_size IS NOT NULL AND file_size > 0");
        $stmt->execute();
        $stats['avg_file_size'] = $stmt->fetchColumn() ?? 0;
        
        return $stats;
    }
    
    private function castValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? (float)$value : 0;
            case 'json':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }
    
    private function stringifyValue($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'json':
                return json_encode($value);
            default:
                return (string)$value;
        }
    }
}
?>
