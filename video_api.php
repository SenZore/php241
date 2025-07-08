<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed ;v']);
    exit;
}

$action = $_POST['action'] ?? '';
$clientIP = getClientIP();

switch ($action) {
    case 'get_video_info':
        handleGetVideoInfo();
        break;
    
    case 'download':
        handleDownload();
        break;
    
    case 'get_stats':
        handleGetStats();
        break;
    
    case 'get_progress':
        handleGetProgress();
        break;
    
    case 'get_recent':
        handleGetRecent();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleGetVideoInfo() {
    global $clientIP;
    
    $url = $_POST['url'] ?? '';
    
    if (empty($url)) {
        echo json_encode(['error' => 'URL is required']);
        return;
    }
    
    // Validate URL
    if (!isValidYouTubeUrl($url)) {
        echo json_encode(['error' => 'Invalid YouTube URL']);
        return;
    }
    
    try {
        // Get video information using yt-dlp
        $command = 'yt-dlp --dump-json --no-warnings ' . escapeshellarg($url) . ' 2>&1';
        $output = shell_exec($command);
        
        if (empty($output)) {
            echo json_encode(['error' => 'Could not fetch video information']);
            return;
        }
        
        $videoInfo = json_decode($output, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'Invalid video information received']);
            return;
        }
        
        // Extract relevant information
        $info = [
            'title' => $videoInfo['title'] ?? 'Unknown Title',
            'duration' => $videoInfo['duration'] ?? 0,
            'thumbnail' => $videoInfo['thumbnail'] ?? '',
            'uploader' => $videoInfo['uploader'] ?? 'Unknown',
            'view_count' => $videoInfo['view_count'] ?? 0,
            'upload_date' => $videoInfo['upload_date'] ?? '',
            'description' => substr($videoInfo['description'] ?? '', 0, 200) . '...',
            'formats' => []
        ];
        
        // Process available formats
        if (isset($videoInfo['formats']) && is_array($videoInfo['formats'])) {
            $formats = [];
            $audioFormats = [];
            
            foreach ($videoInfo['formats'] as $format) {
                if (isset($format['height']) && $format['height'] > 0) {
                    // Video format
                    $quality = $format['height'] . 'p';
                    $ext = $format['ext'] ?? 'mp4';
                    $filesize = $format['filesize'] ?? 0;
                    $fps = $format['fps'] ?? 30;
                    
                    $formats[] = [
                        'quality' => $quality,
                        'format' => $ext,
                        'filesize' => $filesize,
                        'fps' => $fps,
                        'type' => 'video'
                    ];
                } elseif (isset($format['acodec']) && $format['acodec'] !== 'none') {
                    // Audio format
                    $ext = $format['ext'] ?? 'mp3';
                    $filesize = $format['filesize'] ?? 0;
                    $abr = $format['abr'] ?? 128;
                    
                    $audioFormats[] = [
                        'quality' => $abr . 'kbps',
                        'format' => $ext,
                        'filesize' => $filesize,
                        'type' => 'audio'
                    ];
                }
            }
            
            // Sort formats by quality (descending)
            usort($formats, function($a, $b) {
                return (int)$b['quality'] - (int)$a['quality'];
            });
            
            // Sort audio formats by quality (descending)
            usort($audioFormats, function($a, $b) {
                return (int)$b['quality'] - (int)$a['quality'];
            });
            
            $info['formats'] = array_merge($formats, $audioFormats);
        }
        
        // Get best quality automatically
        $bestQuality = getBestQuality($videoInfo['formats'] ?? []);
        $info['best_quality'] = $bestQuality;
        
        echo json_encode(['success' => true, 'info' => $info]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to get video information: ' . $e->getMessage()]);
    }
}

function getBestQuality($formats) {
    $bestVideo = null;
    $bestAudio = null;
    
    foreach ($formats as $format) {
        if (isset($format['height']) && $format['height'] > 0) {
            // Video format
            if ($bestVideo === null || $format['height'] > $bestVideo['height']) {
                $bestVideo = $format;
            }
        } elseif (isset($format['acodec']) && $format['acodec'] !== 'none') {
            // Audio format
            if ($bestAudio === null || ($format['abr'] ?? 0) > ($bestAudio['abr'] ?? 0)) {
                $bestAudio = $format;
            }
        }
    }
    
    return [
        'video' => $bestVideo ? $bestVideo['height'] . 'p' : null,
        'audio' => $bestAudio ? ($bestAudio['abr'] ?? 128) . 'kbps' : null
    ];
}

function handleDownload() {
    global $clientIP, $pdo;
    
    $rateLimiter = new RateLimiter();
    
    // Check rate limit
    if (!$rateLimiter->canDownload($clientIP)) {
        $timeUntilReset = $rateLimiter->getTimeUntilReset($clientIP);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'You have exceeded the download limit. Please wait ' . formatTime($timeUntilReset) . ' before trying again.',
            'remaining' => 0,
            'reset_time' => $timeUntilReset
        ]);
        return;
    }
    
    $url = $_POST['url'] ?? '';
    $quality = $_POST['quality'] ?? 'best';
    $format = $_POST['format'] ?? 'mp4';
    
    if (empty($url)) {
        echo json_encode(['error' => 'URL is required']);
        return;
    }
    
    // Validate URL
    if (!isValidYouTubeUrl($url)) {
        echo json_encode(['error' => 'Invalid YouTube URL']);
        return;
    }
    
    try {
        // Record download attempt
        $rateLimiter->recordDownload($clientIP);
        
        // Generate unique filename
        $downloadId = uniqid();
        $tempFile = sys_get_temp_dir() . "/ytdlp_$downloadId";
        
        // Start download in background
        $command = buildDownloadCommand($url, $quality, $format, $tempFile);
        
        // Save download record
        $stmt = $pdo->prepare("INSERT INTO downloads (url, quality, format, status, ip_address, download_id) VALUES (?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$url, $quality, $format, $clientIP, $downloadId]);
        
        // Start download process
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);
        
        if (is_resource($process)) {
            // Close input pipe
            fclose($pipes[0]);
            
            // Start reading output in background
            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);
            
            echo json_encode([
                'success' => true,
                'download_id' => $downloadId,
                'message' => 'Download started successfully',
                'remaining' => $rateLimiter->getRemainingDownloads($clientIP)
            ]);
            
            // Continue processing in background
            fastcgi_finish_request();
            
            // Monitor download progress
            monitorDownload($downloadId, $pipes, $process);
        } else {
            echo json_encode(['error' => 'Failed to start download process']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Download failed: ' . $e->getMessage()]);
    }
}

function buildDownloadCommand($url, $quality, $format, $tempFile) {
    $command = 'yt-dlp ';
    
    // Format selection
    if ($format === 'mp3') {
        $command .= '--extract-audio --audio-format mp3 --audio-quality 192K ';
    } else {
        // Video format
        switch ($quality) {
            case 'best':
                $command .= '--format "best[ext=' . $format . ']" ';
                break;
            case 'worst':
                $command .= '--format "worst[ext=' . $format . ']" ';
                break;
            default:
                $command .= '--format "best[height<=' . (int)$quality . '][ext=' . $format . ']" ';
                break;
        }
    }
    
    // Output options
    $command .= '--output "' . $tempFile . '.%(ext)s" ';
    $command .= '--progress --newline ';
    $command .= '--no-warnings ';
    $command .= escapeshellarg($url);
    
    return $command . ' 2>&1';
}

function monitorDownload($downloadId, $pipes, $process) {
    global $pdo;
    
    $progressFile = sys_get_temp_dir() . "/progress_$downloadId.txt";
    
    while (true) {
        $output = fread($pipes[1], 1024);
        $error = fread($pipes[2], 1024);
        
        if ($output) {
            file_put_contents($progressFile, $output, FILE_APPEND);
            
            // Parse progress
            if (preg_match('/(\d+\.?\d*)%/', $output, $matches)) {
                $progress = floatval($matches[1]);
                updateDownloadProgress($downloadId, $progress);
            }
        }
        
        if ($error) {
            logMessage("Download error for $downloadId: $error");
        }
        
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        
        usleep(100000); // 0.1 second
    }
    
    $exitCode = proc_close($process);
    
    // Update final status
    if ($exitCode === 0) {
        updateDownloadStatus($downloadId, 'completed');
        moveDownloadToStorage($downloadId);
    } else {
        updateDownloadStatus($downloadId, 'failed');
    }
    
    // Cleanup
    unlink($progressFile);
}

function updateDownloadProgress($downloadId, $progress) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE downloads SET progress = ?, status = 'downloading' WHERE download_id = ?");
    $stmt->execute([$progress, $downloadId]);
}

function updateDownloadStatus($downloadId, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE downloads SET status = ? WHERE download_id = ?");
    $stmt->execute([$status, $downloadId]);
}

function moveDownloadToStorage($downloadId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM downloads WHERE download_id = ?");
    $stmt->execute([$downloadId]);
    $download = $stmt->fetch();
    
    if ($download) {
        $tempFile = sys_get_temp_dir() . "/ytdlp_$downloadId";
        $storageDir = '/var/www/html/downloads';
        
        // Find the actual downloaded file
        $files = glob($tempFile . '.*');
        if (!empty($files)) {
            $sourceFile = $files[0];
            $extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
            $filename = sanitizeFilename($download['title'] ?? 'download') . '_' . $downloadId . '.' . $extension;
            $targetFile = $storageDir . '/' . $filename;
            
            if (rename($sourceFile, $targetFile)) {
                // Update database with final filename
                $stmt = $pdo->prepare("UPDATE downloads SET filename = ? WHERE download_id = ?");
                $stmt->execute([$filename, $downloadId]);
                
                logMessage("Download completed: $filename");
            }
        }
    }
}

function handleGetStats() {
    $stats = getSystemStats();
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function handleGetProgress() {
    global $pdo;
    
    $downloadId = $_POST['download_id'] ?? '';
    
    if (empty($downloadId)) {
        echo json_encode(['error' => 'Download ID required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM downloads WHERE download_id = ?");
    $stmt->execute([$downloadId]);
    $download = $stmt->fetch();
    
    if ($download) {
        echo json_encode([
            'success' => true,
            'progress' => $download['progress'],
            'status' => $download['status'],
            'filename' => $download['filename']
        ]);
    } else {
        echo json_encode(['error' => 'Download not found']);
    }
}

function handleGetRecent() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT title, filename, created_at FROM downloads WHERE status = 'completed' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $downloads = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'downloads' => $downloads]);
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
}

function isValidYouTubeUrl($url) {
    return preg_match('/^https?:\/\/(www\.)?(youtube\.com|youtu\.be)\//', $url);
}

function formatTime($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . ' minutes';
    } else {
        return floor($seconds / 3600) . ' hours';
    }
}
?>
