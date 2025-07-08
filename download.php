<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get filename from URL
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('Invalid file parameter');
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

// Check if file exists in downloads directory
$downloadsDir = '/var/www/html/downloads';
$filePath = $downloadsDir . '/' . $filename;

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Verify file is in our downloads database
$stmt = $pdo->prepare("SELECT * FROM downloads WHERE filename = ? AND status = 'completed'");
$stmt->execute([$filename]);
$download = $stmt->fetch();

if (!$download) {
    http_response_code(403);
    die('File access denied');
}

// Get file info
$fileSize = filesize($filePath);
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Set appropriate content type
$contentTypes = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'avi' => 'video/x-msvideo',
    'mov' => 'video/quicktime',
    'mkv' => 'video/x-matroska'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';

// Log download access
logMessage("File download: $filename by IP: " . getClientIP());

// Handle range requests for video streaming
$start = 0;
$end = $fileSize - 1;
$partial = false;

if (isset($_SERVER['HTTP_RANGE'])) {
    $partial = true;
    list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if (strpos($range, ',') !== false) {
        http_response_code(416);
        header("Content-Range: bytes */$fileSize");
        exit;
    }
    
    if ($range == '-') {
        $start = $fileSize - substr($range, 1);
    } else {
        $range = explode('-', $range);
        $start = intval($range[0]);
        if (isset($range[1]) && is_numeric($range[1])) {
            $end = intval($range[1]);
        }
    }
    
    if ($start > $end || $start > $fileSize - 1 || $end >= $fileSize) {
        http_response_code(416);
        header("Content-Range: bytes */$fileSize");
        exit;
    }
}

// Set headers
if ($partial) {
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$fileSize");
} else {
    http_response_code(200);
}

header("Content-Type: $contentType");
header("Content-Length: " . ($end - $start + 1));
header("Accept-Ranges: bytes");
header("Content-Disposition: attachment; filename=\"" . addslashes($filename) . "\"");
header("Cache-Control: public, max-age=3600");
header("Expires: " . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Stream the file
$file = fopen($filePath, 'rb');
if ($file) {
    if ($start > 0) {
        fseek($file, $start);
    }
    
    $bufferSize = 8192; // 8KB chunks
    $bytesRemaining = $end - $start + 1;
    
    while (!feof($file) && $bytesRemaining > 0 && connection_status() == 0) {
        $bytesToRead = min($bufferSize, $bytesRemaining);
        $buffer = fread($file, $bytesToRead);
        echo $buffer;
        flush();
        $bytesRemaining -= strlen($buffer);
    }
    
    fclose($file);
} else {
    http_response_code(500);
    die('Error reading file');
}

exit;

// Get file info
$fileName = basename($filePath);
$fileSize = filesize($filePath);
$mimeType = mime_content_type($filePath);

// Set headers for download
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Stream the file
$handle = fopen($filePath, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        ob_flush();
        flush();
    }
    fclose($handle);
} else {
    http_response_code(500);
    die('Error reading file');
}

// Log download
logMessage("File downloaded: ID $downloadId, File: $fileName, IP: " . getClientIP());
?>
