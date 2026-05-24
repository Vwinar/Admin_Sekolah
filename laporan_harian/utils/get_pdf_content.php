<?php
session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$file = $_GET['file'] ?? '';

if (empty($file)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No file specified']);
    exit;
}

// Normalize path separators
$file = str_replace('\\', '/', $file);

// Security: Prevent directory traversal but allow pointing to uploads from root
// We expect the file to be within the uploads directory
// Current file structure: root/utils/get_pdf_content.php
// Uploads structure: root/uploads/

// Remove any leading "../" or "./" components to sanitize the filename part essentially
// But wait, if the input is "../uploads/file.pdf", we want "uploads/file.pdf"
$filename = basename($file);
// We generally assume the structure: uploads/subdir/filename or ../uploads/subdir/filename
// Let's strip any ".." components to be safe and reconstruct.

// Simple approach:
// 1. Remove "../" prefix if present
$cleanRelativePath = preg_replace('/^(\.\.\/)+/', '', $file);
// Now we expect "uploads/..."

if (strpos($cleanRelativePath, 'uploads/') !== 0) {
    // If it doesn't start with uploads/, prepend it (assuming it's just a filename)
    // But better to be strict.
    // Let's assume the DB might just store "file.pdf" ? No, usually "uploads/..." or "../uploads/..."

    // For safety, ensure we only serve from the uploads directory.
    // If the path doesn't start with uploads/, reject or assume it's in root uploads?

    // Let's look at the usage in existing code.
    // "uploads/administrasi/..."

    // If clean path doesn't start with uploads/, reject.
    if (strpos($cleanRelativePath, 'uploads/') !== 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid file path format']);
        exit;
    }
}

// Define the absolute path to the web root (parent of utils)
$rootPath = realpath(__DIR__ . '/../');
$fullPath = $rootPath . '/' . $cleanRelativePath;

// Validate that the resolved path is indeed within the uploads directory
$realUploadsPath = realpath($rootPath . '/uploads');
$realFullPath = realpath($fullPath);

if ($realFullPath === false || strpos($realFullPath, $realUploadsPath) !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File not found or access denied']);
    exit;
}

// Read file content
$content = file_get_contents($fullPath);

if ($content === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to read file']);
    exit;
}

// If download parameter is set, serve file directly
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header('Content-Type: application/pdf');
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
    echo $content;
    exit;
}

// Return as base64 encoded JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'content' => base64_encode($content),
    'filename' => basename($fullPath),
    'size' => filesize($fullPath)
]);
exit;
?>