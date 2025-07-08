$(document).ready(function() {
    let currentDownloadId = null;
    let progressInterval = null;
    let statsInterval = null;
    
    // Initialize stats monitoring
    updateStats();
    statsInterval = setInterval(updateStats, 5000);
    
    // Download form submission
    $('#downloadForm').on('submit', function(e) {
        e.preventDefault();
        
        const url = $('#videoUrl').val();
        const quality = $('#quality').val();
        const format = $('#format').val();
        
        if (!url) {
            showAlert('Please enter a YouTube URL', 'error');
            return;
        }
        
        startDownload(url, quality, format);
    });
    
    function startDownload(url, quality, format) {
        $('#downloadBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Starting...');
        
        $.post('api.php', {
            action: 'download',
            url: url,
            quality: quality,
            format: format
        })
        .done(function(response) {
            if (response.success) {
                currentDownloadId = response.download_id;
                showAlert('Download started: ' + response.video_title, 'success');
                showProgress();
                updateRateLimit(response.remaining_downloads);
                
                // Start progress monitoring
                progressInterval = setInterval(checkProgress, 2000);
            } else {
                showAlert(response.error || 'Download failed', 'error');
                resetDownloadButton();
            }
        })
        .fail(function() {
            showAlert('Network error occurred', 'error');
            resetDownloadButton();
        });
    }
    
    function checkProgress() {
        if (!currentDownloadId) return;
        
        $.post('api.php', {
            action: 'get_progress',
            download_id: currentDownloadId
        })
        .done(function(response) {
            if (response.error) {
                showAlert(response.error, 'error');
                hideProgress();
                resetDownloadButton();
                clearInterval(progressInterval);
                return;
            }
            
            updateProgressBar(response.progress);
            
            if (response.status === 'completed') {
                showDownloadComplete(response);
                hideProgress();
                resetDownloadButton();
                clearInterval(progressInterval);
                updateRecentDownloads();
            } else if (response.status === 'failed') {
                showAlert('Download failed: ' + (response.error || 'Unknown error'), 'error');
                hideProgress();
                resetDownloadButton();
                clearInterval(progressInterval);
            }
        })
        .fail(function() {
            console.error('Failed to check progress');
        });
    }
    
    function showProgress() {
        $('#progressContainer').removeClass('hidden');
        updateProgressBar(0);
    }
    
    function hideProgress() {
        $('#progressContainer').addClass('hidden');
    }
    
    function updateProgressBar(progress) {
        $('#progressBar').css('width', progress + '%');
        $('#progressText').text(Math.round(progress) + '%');
    }
    
    function showDownloadComplete(data) {
        const html = `
            <div class="p-4 bg-green-500/20 border border-green-500/30 rounded-lg">
                <div class="flex items-center justify-between text-white">
                    <div>
                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                        <span class="font-semibold">Download Complete!</span>
                    </div>
                    <div class="text-sm">${data.file_size}</div>
                </div>
                <div class="mt-2">
                    <a href="${data.download_url}" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition duration-200">
                        <i class="fas fa-download mr-2"></i>
                        Download File
                    </a>
                </div>
            </div>
        `;
        $('#downloadResult').html(html).removeClass('hidden');
        
        // Hide result after 30 seconds
        setTimeout(() => {
            $('#downloadResult').addClass('hidden');
        }, 30000);
    }
    
    function resetDownloadButton() {
        $('#downloadBtn').prop('disabled', false).html('<i class="fas fa-download mr-2"></i>Download Video');
    }
    
    function updateStats() {
        $.post('api.php', {
            action: 'get_stats'
        })
        .done(function(response) {
            $('#cpu-usage').text(response.cpu + '%');
            $('#cpu-bar').css('width', response.cpu + '%');
            
            $('#ram-usage').text(response.ram + '%');
            $('#ram-bar').css('width', response.ram + '%');
            
            $('#disk-usage').text(response.disk + '%');
            $('#disk-bar').css('width', response.disk + '%');
            
            $('#load-avg').text(response.load);
            $('#uptime').text(response.uptime);
            
            // Update bar colors based on usage
            updateBarColor('#cpu-bar', response.cpu);
            updateBarColor('#ram-bar', response.ram);
            updateBarColor('#disk-bar', response.disk);
        })
        .fail(function() {
            console.error('Failed to update stats');
        });
    }
    
    function updateBarColor(selector, percentage) {
        const $bar = $(selector);
        $bar.removeClass('bg-green-500 bg-yellow-500 bg-red-500');
        
        if (percentage < 60) {
            $bar.addClass('bg-green-500');
        } else if (percentage < 80) {
            $bar.addClass('bg-yellow-500');
        } else {
            $bar.addClass('bg-red-500');
        }
    }
    
    function updateRecentDownloads() {
        $.post('api.php', {
            action: 'get_recent'
        })
        .done(function(response) {
            let html = '';
            
            if (response.length === 0) {
                html = '<div class="text-gray-400 text-sm">No recent downloads</div>';
            } else {
                response.forEach(function(download) {
                    const timeAgo = moment(download.created_at).fromNow();
                    html += `
                        <div class="p-2 bg-white/5 rounded">
                            <div class="truncate">${download.video_title}</div>
                            <div class="text-xs text-gray-400">${timeAgo} • ${download.format.toUpperCase()}</div>
                        </div>
                    `;
                });
            }
            
            $('#recentDownloads').html(html);
        })
        .fail(function() {
            console.error('Failed to update recent downloads');
        });
    }
    
    function updateRateLimit(remaining) {
        const percentage = (remaining / 5) * 100;
        $('#rate-status').text(`${remaining}/5 downloads remaining`);
        $('.bg-blue-500').first().css('width', percentage + '%');
    }
    
    function showAlert(message, type) {
        const alertClass = type === 'error' ? 'bg-red-500/20 border-red-500/30 text-red-400' : 'bg-green-500/20 border-green-500/30 text-green-400';
        const icon = type === 'error' ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle';
        
        const html = `
            <div class="alert p-3 ${alertClass} border rounded-lg mb-4">
                <i class="${icon} mr-2"></i>
                ${message}
            </div>
        `;
        
        $('.alert').remove();
        $('#downloadForm').before(html);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            $('.alert').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Load recent downloads on page load
    updateRecentDownloads();
    
    // Cleanup intervals when page unloads
    $(window).on('beforeunload', function() {
        if (progressInterval) clearInterval(progressInterval);
        if (statsInterval) clearInterval(statsInterval);
    });
});

// Add moment.js for time formatting
if (typeof moment === 'undefined') {
    // Fallback for time formatting if moment.js is not available
    window.moment = function(date) {
        return {
            fromNow: function() {
                const now = new Date();
                const past = new Date(date);
                const diff = Math.floor((now - past) / 1000);
                
                if (diff < 60) return diff + ' seconds ago';
                if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
                if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
                return Math.floor(diff / 86400) + ' days ago';
            }
        };
    };
}
