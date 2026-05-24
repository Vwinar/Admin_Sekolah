<?php
ini_set('display_errors', 0);
require_once '../config.php';
require_once '../functions.php';

// Ensure user is logged in
ensureSession();

header('Content-Type: application/json');

$file = $_GET['file'] ?? '';
// Security: prevent directory traversal
$filename = basename($file);

// The path stored in DB is relative to root linum, e.g. "uploads/books/file.pdf"
// But here we are looking for the file on disk.
// We expect the 'file' param to be just the filename ideally, or a path we can map.
// In literasi_numerasi.php, data-pdf="../uploads/books/filename.pdf".
// Let's assume the frontend passes the relative path or just the filename.

// If we pass just the filename, we look in ../uploads/books/
$filepath = __DIR__ . '/../uploads/books/' . $filename;

// Also check if the input was a full path "uploads/books/filename.pdf"
if (!file_exists($filepath)) {
    // Try constructed path from root usually stored in DB
    $filepathVal = __DIR__ . '/../' . $file;
    // Basic directory traversal protection
    $realBase = realpath(__DIR__ . '/../uploads/books');
    $realTarget = realpath($filepathVal);

    if ($realTarget && $realBase && strpos($realTarget, $realBase) === 0 && file_exists($realTarget)) {
        $filepath = $realTarget;
    }
}

if (file_exists($filepath)) {
    $data = file_get_contents($filepath);
    if ($data === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read file']);
        exit;
    }

    // Return base64 encoded content
    echo json_encode(['content' => base64_encode($data)]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
}
?>