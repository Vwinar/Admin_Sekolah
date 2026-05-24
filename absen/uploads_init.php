<?php
// Define uploads directory path
$uploadDir = __DIR__ . '/uploads';

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("<div class='error'>Failed to create uploads folder.</div>");
    }
}

// Initialize counters
$deletedCount = 0;
$errorCount = 0;

// Get all files in the uploads directory
$files = scandir($uploadDir);

// Process each file
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..') {
        $filePath = $uploadDir . '/' . $file;
        // Only delete if it's a file (not a directory)
        if (is_file($filePath)) {
            if (unlink($filePath)) {
                $deletedCount++;
            } else {
                $errorCount++;
            }
        }
    }
}

// HTML output with styling
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Cleanup Status</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }
        .container {
            background-color: white;
            padding: 2.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        .status-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .success {
            color: #28a745;
            font-weight: 600;
        }
        .warning {
            color: #ffc107;
            font-weight: 600;
        }
        .error {
            color: #dc3545;
            font-weight: 600;
        }
        .loading-bar {
            width: 100%;
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 2rem;
            overflow: hidden;
        }
        .loading-progress {
            height: 100%;
            width: 100%;
            background-color: #28a745;
            animation: progress 2s ease-out;
        }
        @keyframes progress {
            from { width: 0%; }
            to { width: 100%; }
        }
        .redirect-message {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>File Cleanup Status</h1>
        
        <div class="status-card">
HTML;

// Display results
if ($deletedCount > 0) {
    echo "<p class='success'>✓ Successfully deleted {$deletedCount} files from uploads folder.</p>";
} else {
    echo "<p>No files found to delete in uploads folder.</p>";
}

if ($errorCount > 0) {
    echo "<p class='error'>✗ Failed to delete {$errorCount} files.</p>";
}

echo <<<HTML
        </div>
        
        <div class="loading-bar">
            <div class="loading-progress"></div>
        </div>
        
        <div class="redirect-message">
            You will be redirected to admin home shortly...
        </div>
    </div>
</body>
</html>
HTML;

// Redirect back to admin home after 2 seconds
header("refresh:2;url=admin_home.php");
?>