<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/admin_auth.php';
require_once '../includes/admin_settings.php';

$auth = new AdminAuth();
$settings = new AdminSettings();

$auth->requireAuth();
$currentUser = $auth->getCurrentUser();

$success = '';
$error = '';
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_admin':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'moderator';
            
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                $userId = $auth->createUser($username, $password, $role);
                if ($userId) {
                    $success = "Admin user '$username' created successfully!";
                } else {
                    $error = 'Failed to create user. Username might already exist.';
                }
            }
            break;
            
        case 'update_user_permissions':
            $ipAddress = $_POST['ip_address'] ?? '';
            $userType = $_POST['user_type'] ?? 'guest';
            $customRateLimit = !empty($_POST['custom_rate_limit']) ? intval($_POST['custom_rate_limit']) : null;
            $customRateWindow = !empty($_POST['custom_rate_window']) ? intval($_POST['custom_rate_window']) : null;
            $notes = $_POST['notes'] ?? '';
            
            if (empty($ipAddress)) {
                $error = 'IP address is required.';
            } else {
                if ($settings->setUserPermissions($ipAddress, $userType, $customRateLimit, $customRateWindow, $notes)) {
                    $auth->logAction($currentUser['id'], 'update_user_permissions', 'user', $ipAddress, 
                        "Set user type: $userType for IP: $ipAddress");
                    $success = 'User permissions updated successfully!';
                } else {
                    $error = 'Failed to update user permissions.';
                }
            }
            break;
            
        case 'delete_user_permissions':
            $ipAddress = $_POST['ip_address'] ?? '';
            
            if ($settings->deleteUserPermissions($ipAddress)) {
                $auth->logAction($currentUser['id'], 'delete_user_permissions', 'user', $ipAddress, 
                    "Deleted permissions for IP: $ipAddress");
                $success = 'User permissions deleted successfully!';
            } else {
                $error = 'Failed to delete user permissions.';
            }
            break;
            
        case 'delete_admin':
            $userId = intval($_POST['user_id'] ?? 0);
            
            if ($userId === $currentUser['id']) {
                $error = 'You cannot delete yourself.';
            } elseif ($auth->deleteUser($userId)) {
                $success = 'Admin user deleted successfully!';
            } else {
                $error = 'Failed to delete admin user.';
            }
            break;
    }
}

// Get admin users
$adminUsers = $auth->getAllUsers();

// Get user permissions
$userPermissions = $settings->getAllUserPermissions($limit, $offset);

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permissions");
$stmt->execute();
$totalPermissions = $stmt->fetchColumn();
$totalPages = ceil($totalPermissions / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .sidebar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-64 sidebar shadow-2xl">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-shield-alt text-white"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800">Admin Panel</h1>
                        <p class="text-sm text-gray-600">YT-DLP Manager</p>
                    </div>
                </div>
                
                <nav class="space-y-2">
                    <a href="/admin/dashboard.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                    <a href="/admin/settings.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    <a href="/admin/users.php" class="flex items-center px-4 py-3 text-gray-800 bg-blue-100 rounded-lg">
                        <i class="fas fa-users mr-3"></i>
                        User Management
                    </a>
                    <a href="/admin/downloads.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-download mr-3"></i>
                        Downloads
                    </a>
                    <a href="/admin/logs.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-file-alt mr-3"></i>
                        Logs
                    </a>
                    <a href="/admin/monitoring.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-chart-line mr-3"></i>
                        Monitoring
                    </a>
                </nav>
            </div>
            
            <div class="absolute bottom-0 left-0 right-0 p-6">
                <div class="bg-gray-100 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-white"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($currentUser['username']); ?></p>
                            <p class="text-sm text-gray-600 capitalize"><?php echo htmlspecialchars($currentUser['role']); ?></p>
                        </div>
                    </div>
                </div>
                <a href="/admin/logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="glass p-6 border-b border-white/20">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-white">User Management</h2>
                        <p class="text-white/80">Manage admin users and user permissions</p>
                    </div>
                    <div class="flex space-x-3">
                        <button onclick="openCreateAdminModal()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg">
                            <i class="fas fa-user-plus mr-2"></i>Create Admin
                        </button>
                        <button onclick="openPermissionModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                            <i class="fas fa-shield-alt mr-2"></i>Set Permissions
                        </button>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="p-6">
                <!-- Success/Error Messages -->
                <?php if ($success): ?>
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <i class="fas fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- Admin Users -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-user-shield mr-2"></i>Admin Users
                        </h3>
                        
                        <div class="space-y-3">
                            <?php foreach ($adminUsers as $user): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-blue-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></p>
                                        <p class="text-sm text-gray-600 capitalize">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                            <?php if ($user['last_login']): ?>
                                            • Last login: <?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if ($user['id'] !== $currentUser['id']): ?>
                                <div class="flex space-x-2">
                                    <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo $user['role']; ?>')" 
                                            class="text-blue-600 hover:text-blue-700">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this admin user?')">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- User Permissions -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-users mr-2"></i>User Permissions
                        </h3>
                        
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($userPermissions as $permission): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($permission['ip_address']); ?></p>
                                    <div class="flex items-center space-x-4 text-sm text-gray-600">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php 
                                            switch($permission['user_type']) {
                                                case 'vip': echo 'bg-green-100 text-green-800'; break;
                                                case 'banned': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($permission['user_type']); ?>
                                        </span>
                                        <?php if ($permission['custom_rate_limit']): ?>
                                        <span>Limit: <?php echo $permission['custom_rate_limit']; ?></span>
                                        <?php endif; ?>
                                        <span>Downloads: <?php echo $permission['download_count']; ?></span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button onclick="editPermission('<?php echo htmlspecialchars($permission['ip_address']); ?>', '<?php echo $permission['user_type']; ?>', '<?php echo $permission['custom_rate_limit']; ?>', '<?php echo $permission['custom_rate_window']; ?>', '<?php echo htmlspecialchars($permission['notes']); ?>')" 
                                            class="text-blue-600 hover:text-blue-700">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete these permissions?')">
                                        <input type="hidden" name="action" value="delete_user_permissions">
                                        <input type="hidden" name="ip_address" value="<?php echo htmlspecialchars($permission['ip_address']); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                        <div class="mt-4 flex justify-center">
                            <nav class="flex space-x-2">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?> rounded">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Admin Modal -->
    <div id="createAdminModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Create Admin User</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_admin">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required minlength="6"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="moderator">Moderator</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg">
                        Create User
                    </button>
                    <button type="button" onclick="closeCreateAdminModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Permission Modal -->
    <div id="permissionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Set User Permissions</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_user_permissions">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">IP Address</label>
                    <input type="text" name="ip_address" id="edit_ip_address" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="192.168.1.1">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User Type</label>
                    <select name="user_type" id="edit_user_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="guest">Guest</option>
                        <option value="vip">VIP</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Rate Limit</label>
                        <input type="number" name="custom_rate_limit" id="edit_custom_rate_limit"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="5">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Window (sec)</label>
                        <input type="number" name="custom_rate_window" id="edit_custom_rate_window"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="1800">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                    <textarea name="notes" id="edit_notes" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg">
                        Save Permissions
                    </button>
                    <button type="button" onclick="closePermissionModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateAdminModal() {
            document.getElementById('createAdminModal').classList.remove('hidden');
        }

        function closeCreateAdminModal() {
            document.getElementById('createAdminModal').classList.add('hidden');
        }

        function openPermissionModal() {
            document.getElementById('permissionModal').classList.remove('hidden');
        }

        function closePermissionModal() {
            document.getElementById('permissionModal').classList.add('hidden');
        }

        function editPermission(ip, userType, customLimit, customWindow, notes) {
            document.getElementById('edit_ip_address').value = ip;
            document.getElementById('edit_user_type').value = userType;
            document.getElementById('edit_custom_rate_limit').value = customLimit || '';
            document.getElementById('edit_custom_rate_window').value = customWindow || '';
            document.getElementById('edit_notes').value = notes || '';
            openPermissionModal();
        }

        function editUser(userId, username, role) {
            // For now, just show alert. You can implement edit functionality
            alert('Edit functionality for admin users can be implemented if needed.');
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const createModal = document.getElementById('createAdminModal');
            const permissionModal = document.getElementById('permissionModal');
            
            if (event.target === createModal) {
                closeCreateAdminModal();
            }
            if (event.target === permissionModal) {
                closePermissionModal();
            }
        });
    </script>
</body>
</html>
