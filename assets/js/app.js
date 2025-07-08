$(document).ready(function() {
    let currentDownloadId = null;
    let progressInterval = null;
    let statsInterval = null;
    let currentVideoInfo = null;
    
    // Initialize stats monitoring
    updateStats();
    statsInterval = setInterval(updateStats, 5000);
    
    // Video URL analysis
    $('#analyzeBtn').on('click', function() {
        const url = $('#videoUrl').val();
        if (!url) {
            showAlert('Please enter a YouTube URL first', 'warning');
            return;
        }
        analyzeVideo(url);
    });
    
    // Auto-analyze when URL is pasted
    $('#videoUrl').on('blur', function() {
        const url = $(this).val();
        if (url && isValidYouTubeUrl(url)) {
            analyzeVideo(url);
        }
    });
    
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
    
    function analyzeVideo(url) {
        $('#analyzeBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $('#videoInfoPanel').hide();
        
        $.post('video_api.php', {
            action: 'get_video_info',
            url: url
        })
        .done(function(response) {
            if (response.success) {
                currentVideoInfo = response.info;
                displayVideoInfo(response.info);
                updateQualityOptions(response.info.formats);
                showAlert('Video analyzed successfully!', 'success');
            } else {
                showAlert(response.error || 'Failed to analyze video', 'error');
            }
        })
        .fail(function() {
            showAlert('Network error during video analysis', 'error');
        })
        .always(function() {
            $('#analyzeBtn').prop('disabled', false).html('<i class="fas fa-search"></i>');
        });
    }
    
    function displayVideoInfo(info) {
        $('#videoThumbnail').attr('src', info.thumbnail);
        $('#videoTitle').text(info.title);
        $('#videoUploader').text('by ' + info.uploader);
        $('#videoDuration').text(formatDuration(info.duration) + ' • ' + formatViews(info.view_count) + ' views');
        
        let bestQuality = 'Unknown';
        if (info.best_quality.video) {
            bestQuality = info.best_quality.video;
            if (info.best_quality.audio) {
                bestQuality += ' + ' + info.best_quality.audio + ' audio';
            }
        } else if (info.best_quality.audio) {
            bestQuality = info.best_quality.audio + ' audio only';
        }
        $('#bestQuality').text(bestQuality);
        
        $('#videoInfoPanel').slideDown();
    }
    
    function updateQualityOptions(formats) {
        const qualitySelect = $('#quality');
        const formatsList = $('#formatsList');
        
        // Clear existing options except defaults
        qualitySelect.find('option').each(function() {
            const val = $(this).val();
            if (!['best', 'worst'].includes(val)) {
                $(this).remove();
            }
        });
        
        // Add video qualities
        const videoQualities = [];
        const audioQualities = [];
        
        formats.forEach(format => {
            if (format.type === 'video') {
                const quality = parseInt(format.quality);
                if (!videoQualities.includes(quality)) {
                    videoQualities.push(quality);
                }
            } else if (format.type === 'audio') {
                audioQualities.push(format);
            }
        });
        
        // Sort video qualities (highest first)
        videoQualities.sort((a, b) => b - a);
        
        // Add video quality options
        videoQualities.forEach(quality => {
            qualitySelect.append(`<option value="${quality}">${quality}p</option>`);
        });
        
        // Display available formats
        if (formats.length > 0) {
            formatsList.empty();
            
            // Group formats by type
            const videoFormats = formats.filter(f => f.type === 'video').slice(0, 6);
            const audioFormats = formats.filter(f => f.type === 'audio').slice(0, 3);
            
            videoFormats.forEach(format => {
                const sizeText = format.filesize ? formatFileSize(format.filesize) : 'Unknown size';
                formatsList.append(`
                    <div class="bg-white/5 rounded p-2 text-xs text-white cursor-pointer hover:bg-white/10 transition" 
                         data-quality="${format.quality.replace('p', '')}" data-format="${format.format}">
                        <div class="font-medium">${format.quality} ${format.format.toUpperCase()}</div>
                        <div class="text-gray-400">${sizeText}</div>
                    </div>
                `);
            });
            
            audioFormats.forEach(format => {
                const sizeText = format.filesize ? formatFileSize(format.filesize) : 'Unknown size';
                formatsList.append(`
                    <div class="bg-white/5 rounded p-2 text-xs text-white cursor-pointer hover:bg-white/10 transition" 
                         data-quality="audio" data-format="${format.format}">
                        <div class="font-medium">${format.quality} ${format.format.toUpperCase()}</div>
                        <div class="text-gray-400">${sizeText} (Audio)</div>
                    </div>
                `);
            });
            
            $('#availableFormats').slideDown();
        }
        
        // Handle format selection clicks
        formatsList.off('click').on('click', '> div', function() {
            const quality = $(this).data('quality');
            const format = $(this).data('format');
            
            if (quality === 'audio') {
                $('#quality').val('best');
                $('#format').val('mp3');
            } else {
                $('#quality').val(quality);
                $('#format').val(format);
            }
            
            // Highlight selected format
            formatsList.find('> div').removeClass('bg-blue-500/20 border-blue-500/50');
            $(this).addClass('bg-blue-500/20 border border-blue-500/50');
        });
    }
    
    function startDownload(url, quality, format) {
        $('#downloadBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Starting...');
        
        // Use video_api.php for enhanced download functionality
        $.post('video_api.php', {
            action: 'download',
            url: url,
            quality: quality,
            format: format
        })
        .done(function(response) {
            if (response.success) {
                currentDownloadId = response.download_id;
                showAlert('Download started successfully!', 'success');
                showProgress();
                updateRateLimit(response.remaining);
                
                // Start progress monitoring
                progressInterval = setInterval(checkProgress, 1000);
            } else {
                showAlert(response.error || response.message || 'Download failed', 'error');
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
        
        $.post('video_api.php', {
            action: 'get_progress',
            download_id: currentDownloadId
        })
        .done(function(response) {
            if (response.success) {
                const progress = response.progress || 0;
                const status = response.status;
                
                updateProgressBar(progress);
                
                if (status === 'completed') {
                    clearInterval(progressInterval);
                    showDownloadComplete(response.filename);
                    resetDownloadButton();
                    updateRecentDownloads();
                } else if (status === 'failed') {
                    clearInterval(progressInterval);
                    showAlert('Download failed', 'error');
                    resetDownloadButton();
                    hideProgress();
                }
            }
        });
    }
    
    function showDownloadComplete(filename) {
        const downloadLink = filename ? 
            `<a href="/downloads/${filename}" class="text-blue-400 hover:text-blue-300 underline" download>Download File</a>` :
            'Download completed successfully!';
            
        $('#downloadResult').html(`
            <div class="bg-green-500/20 border border-green-500/30 rounded-lg p-4">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                    <span class="text-white">Download completed! ${downloadLink}</span>
                </div>
            </div>
        `).removeClass('hidden');
        
        hideProgress();
    }
    
    function showProgress() {
        $('#progressContainer').removeClass('hidden');
        $('#downloadResult').addClass('hidden');
    }
    
    function hideProgress() {
        $('#progressContainer').addClass('hidden');
    }
    
    function updateProgressBar(progress) {
        $('#progressBar').css('width', progress + '%');
        $('#progressText').text(Math.round(progress) + '%');
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
        $.post('video_api.php', {
            action: 'get_recent'
        })
        .done(function(response) {
            if (response.success && response.downloads) {
                const container = $('#recentDownloads');
                container.empty();
                
                if (response.downloads.length === 0) {
                    container.html('<div class="text-gray-400 text-sm">No recent downloads</div>');
                    return;
                }
                
                response.downloads.forEach(download => {
                    const timeAgo = getTimeAgo(download.created_at);
                    container.append(`
                        <div class="p-2 bg-white/5 rounded hover:bg-white/10 transition">
                            <div class="truncate text-sm">${download.title || 'Unknown Title'}</div>
                            <div class="text-xs text-gray-400">${timeAgo}</div>
                        </div>
                    `);
                });
            }
        });
    }
    
    function updateRateLimit(remaining) {
        $('#rate-status').text(`${remaining}/5 downloads remaining`);
        const percentage = (remaining / 5) * 100;
        $('.bg-blue-500').css('width', percentage + '%');
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

// Utility functions
function isValidYouTubeUrl(url) {
    const pattern = /^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//;
    return pattern.test(url);
}

function formatDuration(seconds) {
    if (!seconds) return 'Unknown';
    
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    } else {
        return `${minutes}:${secs.toString().padStart(2, '0')}`;
    }
}

function formatViews(views) {
    if (!views) return '0';
    
    if (views >= 1000000) {
        return (views / 1000000).toFixed(1) + 'M';
    } else if (views >= 1000) {
        return (views / 1000).toFixed(1) + 'K';
    }
    return views.toString();
}

function formatFileSize(bytes) {
    if (!bytes) return 'Unknown';
    
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(1024));
    return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
}

function getTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}
