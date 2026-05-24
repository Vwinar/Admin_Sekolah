<?php
session_start();
require_once '../config/db_connect.php';

// Validasi Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

/**
 * Recursively delete a directory and its contents
 */
function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

// 1. Clear 'uploads/absensi' folder (new structure)
$absensiDir = dirname(__DIR__) . '/uploads/absensi';
if (file_exists($absensiDir)) {
    // Delete all files inside absensi, but keep the folder or recreate it
    array_map('unlink', glob("$absensiDir/*.*"));
}

// 2. Clear 'utils/uploads' folder (legacy structure)
$legacyDir = dirname(__DIR__) . '/utils/uploads';
if (file_exists($legacyDir)) {
    array_map('unlink', glob("$legacyDir/*.*"));
}

// Optional: Re-create the folder if it was deleted or emptied to ensure future uploads work
if (!file_exists($absensiDir)) {
    mkdir($absensiDir, 0777, true);
} else {
    // Ensure index.php protection or empty file exists if needed
}

// Redirect back with message
$_SESSION['flash_message'] = "All attendance photos have been cleared.";
header('Location: admin_absensi.php');
exit();
?>