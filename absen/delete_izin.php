<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['username']) || !isset($_GET['date'])) {
    header('Location: rekap_absensi.php');
    exit();
}

$username = $_GET['username'];
$date = $_GET['date'];

$db = new SQLite3('absen.db');

// Get user ID from username
$stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    header('Location: rekap_absensi.php?error=User not found');
    exit();
}

$user_id = $user['id'];

// Delete izin record
$stmt = $db->prepare('DELETE FROM izin WHERE user_id = :user_id AND date = :date');
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$stmt->bindValue(':date', $date, SQLITE3_TEXT);
$success = $stmt->execute();

header('Location: rekap_absensi.php');
exit();
?>
