<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get download ID from command line argument
$downloadId = intval($argv[1] ?? 0);

if (!$downloadId) {
    exit("Invalid download ID\n");
}

$progressFile = DOWNLOAD_DIR . $downloadId . '/progress.txt';
$progressDir = dirname($progressFile);

if (!is_dir($progressDir)) {
    mkdir($progressDir, 0755, true);
}

// Read from stdin (yt-dlp output)
while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    
    // Write progress to file
    file_put_contents($progressFile, $line . "\n", FILE_APPEND | LOCK_EX);
    
    // Parse progress and update database
    if (preg_match('/(\d+\.?\d*)%/', $line, $matches)) {
        $progress = floatval($matches[1]);
        updateDownloadProgress($downloadId, $progress, $line);
    }
    
    // Check for completion or errors
    if (strpos($line, '[download] 100%') !== false) {
        markDownloadCompleted($downloadId);
    } elseif (strpos($line, 'ERROR:') !== false) {
        markDownloadFailed($downloadId, $line);
    }
}

function updateDownloadProgress($downloadId, $progress, $statusLine) {
    global $pdo;
    
    $status = $progress >= 100 ? 'completed' : 'downloading';
    
    $stmt = $pdo->prepare("
        UPDATE downloads 
        SET status = ?
        WHERE id = ?
    ");
    $stmt->execute([$status, $downloadId]);
}

function markDownloadCompleted($downloadId) {
    global $pdo;
    
    // Find the downloaded file
    $downloadDir = DOWNLOAD_DIR . $downloadId . '/';
    $files = glob($downloadDir . '*');
    $downloadedFile = null;
    $fileSize = 0;
    
    foreach ($files as $file) {
        if (is_file($file) && basename($file) !== 'progress.txt') {
            $downloadedFile = $file;
            $fileSize = filesize($file);
            break;
        }
    }
    
    $stmt = $pdo->prepare("
        UPDATE downloads 
        SET status = 'completed', file_path = ?, file_size = ?, completed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$downloadedFile, $fileSize, $downloadId]);
    
    logMessage("Download completed: ID $downloadId, File: $downloadedFile");
}

function markDownloadFailed($downloadId, $errorMessage) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE downloads 
        SET status = 'failed', error_message = ?
        WHERE id = ?
    ");
    $stmt->execute([$errorMessage, $downloadId]);
    
    logError("Download failed: ID $downloadId, Error: $errorMessage");
}
?>
