<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Under Maintenance - YouTube Downloader</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .glass { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); }
        .maintenance-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); }
        .pulse-slow { animation: pulse 3s infinite; }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center">
    <div class="w-full max-w-2xl mx-4">
        <!-- Maintenance Icon -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-24 h-24 bg-orange-500 rounded-full mb-6 pulse-slow">
                <i class="fas fa-tools text-4xl text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-4">Under Maintenance</h1>
            <p class="text-xl text-white/80">We're making some improvements</p>
        </div>

        <!-- Maintenance Message -->
        <div class="maintenance-card rounded-2xl shadow-2xl p-8 border border-white/20 text-center">
            <div class="mb-6">
                <i class="fas fa-wrench text-6xl text-orange-500 mb-4"></i>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Site Temporarily Unavailable</h2>
                <p class="text-gray-600 text-lg leading-relaxed">
                    <?php echo htmlspecialchars($maintenanceMessage); ?>
                </p>
            </div>
            
            <!-- Features List -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 my-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-download text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Video Downloads</h3>
                    <p class="text-sm text-gray-600">High-quality video downloads</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-tachometer-alt text-green-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Real-time Monitoring</h3>
                    <p class="text-sm text-gray-600">Live system statistics</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-shield-alt text-purple-600 text-xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-800">Secure & Fast</h3>
                    <p class="text-sm text-gray-600">Safe and reliable service</p>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mb-6">
                <div class="flex justify-between text-sm text-gray-600 mb-2">
                    <span>Maintenance Progress</span>
                    <span>75%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full" style="width: 75%"></div>
                </div>
            </div>
            
            <!-- Estimated Time -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-center">
                    <i class="fas fa-clock text-blue-600 mr-3"></i>
                    <div>
                        <p class="font-semibold text-blue-800">Estimated Completion Time</p>
                        <p class="text-blue-600" id="countdown">Calculating...</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div class="text-center">
                <p class="text-gray-600 mb-4">Need urgent access or have questions?</p>
                <div class="flex justify-center space-x-4">
                    <a href="mailto:admin@<?php echo htmlspecialchars(DOMAIN_NAME); ?>" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-envelope mr-2"></i>
                        Contact Admin
                    </a>
                    <button onclick="checkStatus()" 
                            class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-sync mr-2"></i>
                        Check Status
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8 text-white/60 text-sm">
            <p>&copy; 2025 YouTube Downloader. All rights reserved.</p>
            <p class="mt-2">Follow us for updates and announcements</p>
        </div>
    </div>

    <script>
        // Auto-refresh every 60 seconds
        let refreshInterval = setInterval(() => {
            location.reload();
        }, 60000);
        
        // Estimated completion countdown (example)
        function updateCountdown() {
            const now = new Date();
            const completion = new Date();
            completion.setHours(completion.getHours() + 2); // 2 hours from now
            
            const diff = completion - now;
            
            if (diff <= 0) {
                document.getElementById('countdown').textContent = 'Completing soon...';
                return;
            }
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('countdown').textContent = 
                `Approximately ${hours}h ${minutes}m remaining`;
        }
        
        // Update countdown every minute
        updateCountdown();
        setInterval(updateCountdown, 60000);
        
        function checkStatus() {
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Checking...';
            button.disabled = true;
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
        
        // Preload check - if maintenance is disabled, redirect
        fetch('/', { method: 'HEAD' })
            .then(response => {
                if (response.ok && !response.url.includes('maintenance')) {
                    location.reload();
                }
            })
            .catch(() => {
                // Ignore errors during maintenance
            });
    </script>
</body>
</html>
