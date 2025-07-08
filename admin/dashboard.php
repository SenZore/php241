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

// Get system statistics
$systemStats = getSystemStats();
$adminStats = $settings->getSystemStats();
$recentLogs = $auth->getRecentLogs(10);

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'toggle_maintenance':
            $enabled = !$settings->isMaintenanceMode();
            $settings->setMaintenanceMode($enabled);
            $auth->logAction($currentUser['id'], 'toggle_maintenance', 'system', null, 
                'Maintenance mode ' . ($enabled ? 'enabled' : 'disabled'));
            header('Location: /admin/dashboard.php');
            exit;
            break;
    }
}

$maintenanceMode = $settings->isMaintenanceMode();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - YouTube Downloader</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .sidebar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
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
                    <a href="/admin/dashboard.php" class="flex items-center px-4 py-3 text-gray-800 bg-blue-100 rounded-lg">
                        <i class="fas fa-tachometer-alt mr-3"></i>
                        Dashboard
                    </a>
                    <a href="/admin/settings.php" class="flex items-center px-4 py-3 text-gray-600 hover:bg-gray-100 rounded-lg">
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
                        <h2 class="text-2xl font-bold text-white">Dashboard</h2>
                        <p class="text-white/80">Welcome back, <?php echo htmlspecialchars($currentUser['username']); ?>!</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Maintenance Mode Toggle -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_maintenance">
                            <button type="submit" 
                                    class="flex items-center px-4 py-2 rounded-lg transition duration-200 <?php echo $maintenanceMode ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white'; ?>">
                                <i class="fas fa-<?php echo $maintenanceMode ? 'times' : 'wrench'; ?> mr-2"></i>
                                <?php echo $maintenanceMode ? 'Disable Maintenance' : 'Enable Maintenance'; ?>
                            </button>
                        </form>
                        
                        <!-- Quick Actions -->
                        <div class="relative">
                            <button onclick="toggleDropdown()" class="flex items-center px-4 py-2 bg-white/20 hover:bg-white/30 text-white rounded-lg">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="quickActions" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10">
                                <a href="/admin/settings.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <a href="/" target="_blank" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-external-link-alt mr-2"></i>View Site
                                </a>
                                <a href="/admin/logs.php" class="block px-4 py-2 text-gray-800 hover:bg-gray-100">
                                    <i class="fas fa-file-alt mr-2"></i>View Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Dashboard Content -->
            <main class="p-6">
                <!-- Status Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Downloads -->
                    <div class="card rounded-xl p-6 shadow-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Downloads</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($adminStats['total_downloads']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-download text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Today's Downloads -->
                    <div class="card rounded-xl p-6 shadow-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Today's Downloads</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($adminStats['downloads_today']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-day text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Downloads -->
                    <div class="card rounded-xl p-6 shadow-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Active Downloads</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($adminStats['active_downloads']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-spinner text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Users -->
                    <div class="card rounded-xl p-6 shadow-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Users</p>
                                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($adminStats['total_users']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Status & Recent Activity -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- System Status -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-server mr-2"></i>System Status
                        </h3>
                        
                        <div class="space-y-4">
                            <!-- CPU Usage -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">CPU Usage</span>
                                    <span class="text-gray-800 font-medium"><?php echo $systemStats['cpu']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo $systemStats['cpu']; ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- RAM Usage -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">RAM Usage</span>
                                    <span class="text-gray-800 font-medium"><?php echo $systemStats['ram']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $systemStats['ram']; ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- Disk Usage -->
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Disk Usage</span>
                                    <span class="text-gray-800 font-medium"><?php echo $systemStats['disk']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-600 h-2 rounded-full" style="width: <?php echo $systemStats['disk']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Load Average:</span>
                                <span class="text-gray-800 font-medium"><?php echo $systemStats['load']; ?></span>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                <span class="text-gray-600">Uptime:</span>
                                <span class="text-gray-800 font-medium"><?php echo $systemStats['uptime']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="card rounded-xl p-6 shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-history mr-2"></i>Recent Activity
                        </h3>
                        
                        <div class="space-y-3">
                            <?php foreach ($recentLogs as $log): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($log['username']); ?></p>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($log['action']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500"><?php echo date('M j, H:i', strtotime($log['created_at'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <a href="/admin/logs.php" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                                View all logs <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Mode Banner -->
                <?php if ($maintenanceMode): ?>
                <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Maintenance Mode is Active</strong>
                        <span class="ml-2">- The site is currently in maintenance mode.</span>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('quickActions');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('quickActions');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick) {
                dropdown.classList.add('hidden');
            }
        });

        // Auto-refresh system stats every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
