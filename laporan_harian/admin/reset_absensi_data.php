<?php
session_start();
require_once '../config/db_connect.php';

// Validasi Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

try {
    // 1. Truncate/Delete Attendance Table
    $db->exec("DELETE FROM attendance");

    // 2. Truncate/Delete Izin Table
    $db->exec("DELETE FROM izin");

    // 3. Reset Auto Increment (Optional for SQLite)
    // $db->exec("DELETE FROM sqlite_sequence WHERE name='attendance'");
    // $db->exec("DELETE FROM sqlite_sequence WHERE name='izin'");

    // 4. Also clear photos to keep sync? 
    // Usually "Reset Data" implies cleaning everything. 
    // Let's call the clear uploads logic or include it here.
    // For now, let's keep it separate as per user UI (two buttons). 
    // If the user wants to clear photos too, they should click the other button or we can include it.
    // Based on the prompt "Warning: Ini akan menghapus SEMUA data absensi... dan foto bukti", 
    // we SHOULD clear the photos here too.

    $absensiDir = dirname(__DIR__) . '/uploads/absensi';
    if (file_exists($absensiDir)) {
        array_map('unlink', glob("$absensiDir/*.*"));
    }

    $legacyDir = dirname(__DIR__) . '/utils/uploads';
    if (file_exists($legacyDir)) {
        array_map('unlink', glob("$legacyDir/*.*"));
    }

    $_SESSION['flash_message'] = "All attendance data and photos have been reset.";

} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Failed to reset data: " . $e->getMessage();
}

header('Location: admin_absensi.php');
exit();
?>