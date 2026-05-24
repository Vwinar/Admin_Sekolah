<?php
// Disable error display to prevent breaking PDF output
error_reporting(0);
ini_set('display_errors', 0);

// Start session
session_start();

// Comment out auth for debugging - REMOVE THIS LATER
/*
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}
*/

$file = $_GET['file'] ?? '';

if (empty($file)) {
    http_response_code(400);
    exit('No file specified');
}

// Security: prevent directory traversal
$file = str_replace(['../', '..\\', '..'], '', $file);

// Full path
$fullPath = __DIR__ . '/' . $file;

// Debugging - write to log file
$logFile = __DIR__ . '/pdf_debug.log';
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "Request: $file\nFull path: $fullPath\n", FILE_APPEND);

// Check if file exists
if (!file_exists($fullPath)) {
    file_put_contents($logFile, "ERROR: File not found\n", FILE_APPEND);
    http_response_code(404);
    exit('File not found: ' . $file);
}

// Check if it's readable
if (!is_readable($fullPath)) {
    file_put_contents($logFile, "ERROR: File not readable\n", FILE_APPEND);
    http_response_code(403);
    exit('File not readable');
}

// Get file size
$fileSize = filesize($fullPath);
file_put_contents($logFile, "File size: $fileSize bytes\n", FILE_APPEND);

// Check if file is empty
if ($fileSize === 0) {
    file_put_contents($logFile, "ERROR: File is empty\n", FILE_APPEND);
    http_response_code(500);
    exit('File is empty');
}

// Clear any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/pdf');
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

file_put_contents($logFile, "Sending file...\n", FILE_APPEND);

// Output file
$fp = fopen($fullPath, 'rb');
fpassthru($fp);
fclose($fp);

file_put_contents($logFile, "Done\n\n", FILE_APPEND);
exit;
