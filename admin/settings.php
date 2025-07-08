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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_site_settings':
            $siteSettings = [
                'site_title' => $_POST['site_title'] ?? '',
                'site_description' => $_POST['site_description'] ?? '',
                'maintenance_message' => $_POST['maintenance_message'] ?? '',
                'max_file_size' => $_POST['max_file_size'] ?? '2G',
                'cleanup_interval' => intval($_POST['cleanup_interval'] ?? 7),
                'log_retention' => intval($_POST['log_retention'] ?? 30),
                'enable_monitoring' => isset($_POST['enable_monitoring'])
            ];
            
            foreach ($siteSettings as $key => $value) {
                $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string');
                $settings->setSetting($key, $value, $type);
            }
            
            $auth->logAction($currentUser['id'], 'update_site_settings', 'settings', null, 'Updated site settings');
            $success = 'Site settings updated successfully!';
            break;
            
        case 'update_rate_limits':
            $defaultLimit = intval($_POST['default_rate_limit'] ?? 5);
            $defaultWindow = intval($_POST['default_rate_window'] ?? 1800);
            
            $settings->setRateLimitSettings($defaultLimit, $defaultWindow);
            
            $auth->logAction($currentUser['id'], 'update_rate_limits', 'settings', null, 
                "Updated rate limits: $defaultLimit/$defaultWindow");
            $success = 'Rate limit settings updated successfully!';
            break;
            
        case 'toggle_maintenance':
            $enabled = isset($_POST['maintenance_mode']);
            $message = $_POST['maintenance_message'] ?? '';
            
            $settings->setMaintenanceMode($enabled, $message);
            
            $auth->logAction($currentUser['id'], 'toggle_maintenance', 'settings', null, 
                'Maintenance mode ' . ($enabled ? 'enabled' : 'disabled'));
            $success = 'Maintenance mode ' . ($enabled ? 'enabled' : 'disabled') . ' successfully!';
            break;
    }
}

// Get current settings
$allSettings = $settings->getAllSettings();
$rateLimitSettings = $settings->getRateLimitSettings();
$maintenanceMode = $settings->isMaintenanceMode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
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
                    <a href="/admin/settings.php" class="flex items-center px-4 py-3 text-gray-800 bg-blue-100 rounded-lg">
                        <i class="fas fa-cog mr-3"></i>
                        Settings
                    </a>
                    <a href="/admin/users.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
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
                        <h2 class="text-2xl font-bold text-white">Settings</h2>
                        <p class="text-white/80">Configure your YouTube downloader</p>
                    </div>
                    <a href="/admin/dashboard.php" class="px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </header>

            <!-- Settings Content -->
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

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Site Settings -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-globe mr-2"></i>Site Settings
                        </h3>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_site_settings">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Title</label>
                                <input type="text" name="site_title" 
                                       value="<?php echo htmlspecialchars($allSettings['site_title']['value'] ?? ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Description</label>
                                <textarea name="site_description" rows="3"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($allSettings['site_description']['value'] ?? ''); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Max File Size</label>
                                <input type="text" name="max_file_size" 
                                       value="<?php echo htmlspecialchars($allSettings['max_file_size']['value'] ?? '2G'); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">Use format like: 2G, 500M, 1024K</p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Cleanup Interval (days)</label>
                                    <input type="number" name="cleanup_interval" 
                                           value="<?php echo htmlspecialchars($allSettings['cleanup_interval']['value'] ?? 7); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Log Retention (days)</label>
                                    <input type="number" name="log_retention" 
                                           value="<?php echo htmlspecialchars($allSettings['log_retention']['value'] ?? 30); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="enable_monitoring" 
                                           <?php echo ($allSettings['enable_monitoring']['value'] ?? true) ? 'checked' : ''; ?>
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700">Enable real-time monitoring</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-save mr-2"></i>Save Site Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Rate Limiting Settings -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-tachometer-alt mr-2"></i>Rate Limiting
                        </h3>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_rate_limits">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Default Downloads per Window</label>
                                <input type="number" name="default_rate_limit" 
                                       value="<?php echo htmlspecialchars($rateLimitSettings['default_limit']); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Time Window (seconds)</label>
                                <input type="number" name="default_rate_window" 
                                       value="<?php echo htmlspecialchars($rateLimitSettings['default_window']); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">1800 = 30 minutes, 3600 = 1 hour</p>
                            </div>
                            
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-save mr-2"></i>Save Rate Limits
                            </button>
                        </form>
                    </div>
                    
                    <!-- Maintenance Mode -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-wrench mr-2"></i>Maintenance Mode
                        </h3>
                        
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="toggle_maintenance">
                            
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" name="maintenance_mode" 
                                           <?php echo $maintenanceMode ? 'checked' : ''; ?>
                                           class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                                    <span class="ml-2 text-sm text-gray-700">Enable maintenance mode</span>
                                </label>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Maintenance Message</label>
                                <textarea name="maintenance_message" rows="3"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($allSettings['maintenance_message']['value'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-save mr-2"></i>Update Maintenance Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-bolt mr-2"></i>Quick Actions
                        </h3>
                        
                        <div class="space-y-3">
                            <button onclick="clearCache()" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-trash mr-2"></i>Clear Cache
                            </button>
                            
                            <button onclick="updateYtDlp()" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-sync mr-2"></i>Update yt-dlp
                            </button>
                            
                            <button onclick="runCleanup()" class="w-full bg-orange-600 hover:bg-orange-700 text-white py-2 px-4 rounded-lg">
                                <i class="fas fa-broom mr-2"></i>Run Cleanup
                            </button>
                            
                            <a href="/admin/monitoring.php" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg text-center">
                                <i class="fas fa-chart-line mr-2"></i>View Monitoring
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function clearCache() {
            if (confirm('Are you sure you want to clear all cache?')) {
                fetch('/admin/actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=clear_cache'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cache cleared successfully!');
                    } else {
                        alert('Error clearing cache: ' + data.message);
                    }
                });
            }
        }

        function updateYtDlp() {
            if (confirm('Are you sure you want to update yt-dlp?')) {
                fetch('/admin/actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_ytdlp'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('yt-dlp updated successfully!');
                    } else {
                        alert('Error updating yt-dlp: ' + data.message);
                    }
                });
            }
        }

        function runCleanup() {
            if (confirm('Are you sure you want to run cleanup now?')) {
                fetch('/admin/actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=run_cleanup'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Cleanup completed successfully!');
                    } else {
                        alert('Error running cleanup: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html>
