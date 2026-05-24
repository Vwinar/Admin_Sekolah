<?php
ini_set('display_errors', 0);
require_once '../config.php';
require_once '../functions.php';

// Ensure user is logged in as admin or guru
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$file = $_GET['file'] ?? '';
// Security: prevent directory traversal
$filename = basename($file);

// The path stored in DB is relative to root linum, e.g. "uploads/books/file.pdf"
// in manage_books.php, the link is href="../uploads/books/..." representing a path relative to admin folder.
// The uploads folder is in c:/xampp/htdocs/linum/uploads/books/
// So relative to this file (in c:/xampp/htdocs/linum/admin/), the path is ../uploads/books/

$filepath = __DIR__ . '/../uploads/books/' . $filename;

// Also check if the input was a full path "uploads/books/filename.pdf" or something
if (!file_exists($filepath)) {
    // Basic directory traversal protection
    $realBase = realpath(__DIR__ . '/../uploads/books');
    // Try to see if $file is relative to admin
    $filepathVal = __DIR__ . '/../' . $file;
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