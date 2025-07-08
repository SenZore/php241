<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$downloadId = intval($_GET['id'] ?? 0);

if (!$downloadId) {
    http_response_code(400);
    die('Invalid download ID');
}

$stmt = $pdo->prepare("
    SELECT * FROM downloads 
    WHERE id = ? AND status = 'completed'
");
$stmt->execute([$downloadId]);
$download = $stmt->fetch();

if (!$download) {
    http_response_code(404);
    die('Download not found or not completed');
}

$filePath = $download['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

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
