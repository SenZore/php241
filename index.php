<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/admin_settings.php';

// Check maintenance mode
$settings = new AdminSettings();
if ($settings->isMaintenanceMode()) {
    $maintenanceMessage = $settings->getSetting('maintenance_message', 'Site is under maintenance. Please check back later.');
    include 'maintenance.php';
    exit;
}

$rateLimiter = new RateLimiter();
$systemStats = getSystemStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Downloader - YT-DLP</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="gradient-bg min-h-screen">
    <!-- Header -->
    <nav class="glass border-b border-white/20 p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-white text-2xl font-bold">
                <i class="fab fa-youtube text-red-500"></i> YT-DLP Downloader
            </h1>
            <div class="text-white text-sm">
                <span class="pulse-dot inline-block w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                Server Online
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Download Panel -->
            <div class="lg:col-span-2">
                <div class="glass rounded-xl p-6 border border-white/20">
                    <h2 class="text-white text-xl font-semibold mb-4">
                        <i class="fas fa-download"></i> Download Video
                    </h2>
                    
                    <!-- Rate Limit Status -->
                    <div class="mb-4 p-3 bg-blue-500/20 rounded-lg border border-blue-500/30">
                        <div class="flex justify-between items-center text-white text-sm">
                            <span>Rate Limit Status:</span>
                            <span id="rate-status">
                                <?php 
                                $remaining = $rateLimiter->getRemainingDownloads($_SERVER['REMOTE_ADDR']);
                                echo "$remaining/5 downloads remaining";
                                ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2 mt-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo ($remaining/5)*100; ?>%"></div>
                        </div>
                    </div>

                    <!-- Download Form -->
                    <form id="downloadForm" class="space-y-4">
                        <div>
                            <label class="block text-white text-sm font-medium mb-2">YouTube URL</label>
                            <div class="flex gap-2">
                                <input type="url" id="videoUrl" name="url" 
                                       class="flex-1 px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white placeholder-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="https://www.youtube.com/watch?v=..."
                                       required>
                                <button type="button" id="analyzeBtn" 
                                        class="px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Video Info Panel -->
                        <div id="videoInfoPanel" class="hidden bg-white/5 rounded-lg p-4 border border-white/10">
                            <div class="flex gap-4">
                                <img id="videoThumbnail" src="" alt="Video Thumbnail" class="w-24 h-18 rounded object-cover">
                                <div class="flex-1">
                                    <h4 id="videoTitle" class="text-white font-medium text-sm mb-1"></h4>
                                    <p id="videoUploader" class="text-gray-300 text-xs mb-1"></p>
                                    <p id="videoDuration" class="text-gray-400 text-xs"></p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="flex justify-between text-xs text-gray-300 mb-1">
                                    <span>Best Available Quality:</span>
                                    <span id="bestQuality"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Quality</label>
                                <select id="quality" name="quality" 
                                        class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="best">Best Quality</option>
                                    <option value="1080">1080p</option>
                                    <option value="720">720p</option>
                                    <option value="480">480p</option>
                                    <option value="360">360p</option>
                                    <option value="worst">Smallest Size</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-white text-sm font-medium mb-2">Format</label>
                                <select id="format" name="format" 
                                        class="w-full px-4 py-3 rounded-lg bg-white/10 border border-white/20 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="mp4">MP4 (Video)</option>
                                    <option value="webm">WebM (Video)</option>
                                    <option value="mp3">MP3 (Audio Only)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Available Formats -->
                        <div id="availableFormats" class="hidden">
                            <label class="block text-white text-sm font-medium mb-2">Available Formats</label>
                            <div id="formatsList" class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-32 overflow-y-auto">
                                <!-- Dynamic format options will be inserted here -->
                            </div>
                        </div>
                        
                        <button type="submit" id="downloadBtn" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>
                            Download Video
                        </button>
                    </form>
                    
                    <!-- Progress Bar -->
                    <div id="progressContainer" class="hidden mt-4">
                        <div class="flex justify-between text-white text-sm mb-2">
                            <span>Downloading...</span>
                            <span id="progressText">0%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-3">
                            <div id="progressBar" class="bg-green-500 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                    
                    <!-- Download Result -->
                    <div id="downloadResult" class="hidden mt-4"></div>
                </div>
            </div>
            
            <!-- System Stats Panel -->
            <div class="space-y-6">
                <!-- Server Stats -->
                <div class="glass rounded-xl p-6 border border-white/20">
                    <h3 class="text-white text-lg font-semibold mb-4">
                        <i class="fas fa-server"></i> Server Status
                    </h3>
                    
                    <div class="space-y-4">
                        <!-- CPU Usage -->
                        <div>
                            <div class="flex justify-between text-white text-sm mb-1">
                                <span>CPU Usage</span>
                                <span id="cpu-usage"><?php echo $systemStats['cpu']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" 
                                     id="cpu-bar" style="width: <?php echo $systemStats['cpu']; ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- RAM Usage -->
                        <div>
                            <div class="flex justify-between text-white text-sm mb-1">
                                <span>RAM Usage</span>
                                <span id="ram-usage"><?php echo $systemStats['ram']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500" 
                                     id="ram-bar" style="width: <?php echo $systemStats['ram']; ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Disk Usage -->
                        <div>
                            <div class="flex justify-between text-white text-sm mb-1">
                                <span>Disk Usage</span>
                                <span id="disk-usage"><?php echo $systemStats['disk']; ?>%</span>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full transition-all duration-500" 
                                     id="disk-bar" style="width: <?php echo $systemStats['disk']; ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Load Average -->
                        <div class="text-white text-sm">
                            <span class="block">Load Average: <span id="load-avg"><?php echo $systemStats['load']; ?></span></span>
                            <span class="block">Uptime: <span id="uptime"><?php echo $systemStats['uptime']; ?></span></span>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Downloads -->
                <div class="glass rounded-xl p-6 border border-white/20">
                    <h3 class="text-white text-lg font-semibold mb-4">
                        <i class="fas fa-history"></i> Recent Downloads
                    </h3>
                    
                    <div id="recentDownloads" class="space-y-2 text-white text-sm">
                        <div class="p-2 bg-white/5 rounded">
                            <div class="truncate">Sample Video Title</div>
                            <div class="text-xs text-gray-400">2 minutes ago</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
