<?php
require_once 'config.php';

class AdminAuth {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    public function login($username, $password) {
        $stmt = $this->pdo->prepare("
            SELECT id, username, password, role 
            FROM admin_users 
            WHERE username = ? AND role IN ('admin', 'moderator')
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $sessionToken = $this->generateSessionToken();
            $this->createSession($user['id'], $sessionToken);
            $this->updateLastLogin($user['id']);
            
            $_SESSION['admin_token'] = $sessionToken;
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ];
            
            $this->logAction($user['id'], 'login', 'user', $user['id'], 'User logged in');
            
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if (isset($_SESSION['admin_token'])) {
            $this->deleteSession($_SESSION['admin_token']);
        }
        
        if (isset($_SESSION['admin_user'])) {
            $this->logAction($_SESSION['admin_user']['id'], 'logout', 'user', $_SESSION['admin_user']['id'], 'User logged out');
        }
        
        unset($_SESSION['admin_token']);
        unset($_SESSION['admin_user']);
        session_destroy();
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_token']) || !isset($_SESSION['admin_user'])) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT s.*, u.username, u.role 
            FROM admin_sessions s
            JOIN admin_users u ON s.user_id = u.id
            WHERE s.session_token = ? AND s.expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['admin_token']]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $this->logout();
            return false;
        }
        
        // Extend session
        $this->extendSession($_SESSION['admin_token']);
        
        return true;
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: /admin/login.php');
            exit;
        }
    }
    
    public function requireRole($requiredRole = 'admin') {
        $this->requireAuth();
        
        if ($_SESSION['admin_user']['role'] !== $requiredRole && $_SESSION['admin_user']['role'] !== 'admin') {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $_SESSION['admin_user'];
    }
    
    public function createUser($username, $password, $role = 'moderator') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_users (username, password, role)
            VALUES (?, ?, ?)
        ");
        
        try {
            $stmt->execute([$username, $hashedPassword, $role]);
            $userId = $this->pdo->lastInsertId();
            
            $this->logAction($_SESSION['admin_user']['id'], 'create_user', 'user', $userId, 
                "Created user: $username with role: $role");
            
            return $userId;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function updateUser($userId, $username = null, $password = null, $role = null) {
        $updates = [];
        $params = [];
        
        if ($username !== null) {
            $updates[] = "username = ?";
            $params[] = $username;
        }
        
        if ($password !== null) {
            $updates[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        if ($role !== null) {
            $updates[] = "role = ?";
            $params[] = $role;
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        $sql = "UPDATE admin_users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->logAction($_SESSION['admin_user']['id'], 'update_user', 'user', $userId, 
                "Updated user: " . implode(', ', array_keys(array_filter(compact('username', 'password', 'role')))));
        }
        
        return $result;
    }
    
    public function deleteUser($userId) {
        // Don't allow deleting yourself
        if ($userId == $_SESSION['admin_user']['id']) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            $this->logAction($_SESSION['admin_user']['id'], 'delete_user', 'user', $userId, 
                "Deleted user ID: $userId");
        }
        
        return $result;
    }
    
    public function getAllUsers() {
        $stmt = $this->pdo->prepare("
            SELECT id, username, role, last_login, created_at
            FROM admin_users
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function logAction($adminId, $action, $targetType = null, $targetId = null, $details = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $adminId,
            $action,
            $targetType,
            $targetId,
            $details,
            getClientIP()
        ]);
    }
    
    public function getRecentLogs($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT l.*, u.username
            FROM admin_logs l
            JOIN admin_users u ON l.admin_id = u.id
            ORDER BY l.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function createSession($userId, $token) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_sessions (user_id, session_token, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $token,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
    }
    
    private function deleteSession($token) {
        $stmt = $this->pdo->prepare("DELETE FROM admin_sessions WHERE session_token = ?");
        $stmt->execute([$token]);
    }
    
    private function extendSession($token) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->pdo->prepare("
            UPDATE admin_sessions 
            SET expires_at = ? 
            WHERE session_token = ?
        ");
        $stmt->execute([$expiresAt, $token]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    public function cleanupExpiredSessions() {
        $stmt = $this->pdo->prepare("DELETE FROM admin_sessions WHERE expires_at < NOW()");
        $stmt->execute();
    }
}
?>
