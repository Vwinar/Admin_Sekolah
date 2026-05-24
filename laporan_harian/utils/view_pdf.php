<?php
// Prevent any implicit output
ob_start();

session_start();

// Simple Auth Check (bypass db connection to be safe from stray output)
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Access Denied");
}

$file = $_GET['file'] ?? '';
$filename = basename($file);
$filepath = __DIR__ . '/uploads/' . $filename;

// Disable compression
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);

if ($filename && file_exists($filepath) && strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) === 'pdf') {

    // Clean all buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filesize = filesize($filepath);

    header("HTTP/1.1 200 OK");
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=\"$filename\"");
    header("Content-Length: " . $filesize);
    header("Cache-Control: private, max-age=0, must-revalidate");
    header("Pragma: public");

    // Output file
    readfile($filepath);
    exit;
} else {
    // Return simple text error if file not found
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($filename);
}
?>
