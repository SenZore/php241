<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/rate_limiter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$clientIP = getClientIP();

switch ($action) {
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

function handleDownload() {
    global $clientIP, $pdo;
    
    $rateLimiter = new RateLimiter();
    
    // Check rate limit
    if (!$rateLimiter->canDownload($clientIP)) {
        $timeUntilReset = $rateLimiter->getTimeUntilReset($clientIP);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'You have exceeded the download limit. Please wait ' . formatTime($timeUntilReset) . ' before trying again.',
            'time_until_reset' => $timeUntilReset
        ]);
        return;
    }
    
    $url = $_POST['url'] ?? '';
    $quality = $_POST['quality'] ?? 'best';
    $format = $_POST['format'] ?? 'mp4';
    
    // Validate URL
    if (!validateYouTubeURL($url)) {
        echo json_encode(['error' => 'Invalid YouTube URL']);
        return;
    }
    
    // Get video info
    $videoInfo = getVideoInfo($url);
    if (!$videoInfo) {
        echo json_encode(['error' => 'Could not fetch video information']);
        return;
    }
    
    // Record rate limit
    $rateLimiter->recordDownload($clientIP);
    
    // Generate unique filename
    $safeTitle = sanitizeFilename($videoInfo['title']);
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $safeTitle . '_' . $timestamp;
    
    // Insert download record
    $stmt = $pdo->prepare("
        INSERT INTO downloads (ip_address, video_url, video_title, format, quality, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$clientIP, $url, $videoInfo['title'], $format, $quality]);
    $downloadId = $pdo->lastInsertId();
    
    // Start download in background
    $downloadPath = DOWNLOAD_DIR . $downloadId . '/';
    if (!is_dir($downloadPath)) {
        mkdir($downloadPath, 0755, true);
    }
    
    // Build yt-dlp command
    $cmd = buildDownloadCommand($url, $quality, $format, $downloadPath, $filename, $downloadId);
    
    // Execute in background
    exec($cmd . ' > /dev/null 2>&1 &');
    
    echo json_encode([
        'success' => true,
        'download_id' => $downloadId,
        'message' => 'Download started',
        'video_title' => $videoInfo['title'],
        'remaining_downloads' => $rateLimiter->getRemainingDownloads($clientIP)
    ]);
}

function buildDownloadCommand($url, $quality, $format, $downloadPath, $filename, $downloadId) {
    $ytdlpPath = escapeshellcmd(YTDLP_PATH);
    $url = escapeshellarg($url);
    $downloadPath = escapeshellarg($downloadPath);
    
    // Format options
    $formatOption = '';
    switch ($format) {
        case 'mp3':
            $formatOption = '--extract-audio --audio-format mp3 --audio-quality 0';
            $outputTemplate = $downloadPath . $filename . '.%(ext)s';
            break;
        case 'webm':
            $formatOption = '-f "best[ext=webm]"';
            $outputTemplate = $downloadPath . $filename . '.%(ext)s';
            break;
        default: // mp4
            if ($quality === 'best') {
                $formatOption = '-f "best[ext=mp4]"';
            } elseif ($quality === 'worst') {
                $formatOption = '-f "worst[ext=mp4]"';
            } else {
                $formatOption = "-f \"best[height<=$quality][ext=mp4]\"";
            }
            $outputTemplate = $downloadPath . $filename . '.%(ext)s';
            break;
    }
    
    $outputTemplate = escapeshellarg($outputTemplate);
    
    // Progress hook
    $progressHook = '--newline --progress-template "download:%(progress._percent_str)s %(progress._downloaded_bytes_str)s/%(progress._total_bytes_str)s %(progress._speed_str)s %(progress._eta_str)s"';
    
    // Additional options
    $additionalOptions = '--no-playlist --restrict-filenames --max-filesize ' . MAX_FILE_SIZE;
    
    // Build final command with progress tracking
    $cmd = "$ytdlpPath $formatOption $progressHook $additionalOptions -o $outputTemplate $url";
    
    // Add PHP script to track progress
    $cmd .= " | php " . __DIR__ . "/track_progress.php $downloadId";
    
    return $cmd;
}

function handleGetStats() {
    $stats = getSystemStats();
    $activeDownloads = getActiveDownloads();
    
    // Record stats in database
    recordSystemStats();
    
    echo json_encode([
        'cpu' => $stats['cpu'],
        'ram' => $stats['ram'],
        'disk' => $stats['disk'],
        'load' => $stats['load'],
        'uptime' => $stats['uptime'],
        'active_downloads' => $activeDownloads
    ]);
}

function handleGetProgress() {
    global $pdo;
    
    $downloadId = intval($_POST['download_id'] ?? 0);
    
    $stmt = $pdo->prepare("
        SELECT status, error_message, file_path, file_size
        FROM downloads 
        WHERE id = ?
    ");
    $stmt->execute([$downloadId]);
    $download = $stmt->fetch();
    
    if (!$download) {
        echo json_encode(['error' => 'Download not found']);
        return;
    }
    
    $response = [
        'status' => $download['status'],
        'progress' => 0
    ];
    
    if ($download['status'] === 'completed') {
        $response['progress'] = 100;
        $response['file_path'] = $download['file_path'];
        $response['file_size'] = formatFileSize($download['file_size']);
        $response['download_url'] = 'download.php?id=' . $downloadId;
    } elseif ($download['status'] === 'failed') {
        $response['error'] = $download['error_message'];
    } else {
        // Try to get progress from progress file
        $progressFile = DOWNLOAD_DIR . $downloadId . '/progress.txt';
        if (file_exists($progressFile)) {
            $progressData = file_get_contents($progressFile);
            if ($progressData) {
                $lines = explode("\n", trim($progressData));
                $lastLine = end($lines);
                if (preg_match('/(\d+\.?\d*)%/', $lastLine, $matches)) {
                    $response['progress'] = floatval($matches[1]);
                }
            }
        }
    }
    
    echo json_encode($response);
}

function handleGetRecent() {
    $recent = getRecentDownloads(5);
    echo json_encode($recent);
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
