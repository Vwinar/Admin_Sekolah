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

// Get user id from username
$stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
$stmt->bindValue(':username', $username, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    header('Location: rekap_absensi.php');
    exit();
}

$user_id = $user['id'];

// Delete attendance record for the user and date
$stmt = $db->prepare("DELETE FROM attendance WHERE user_id = :user_id AND date = :date");
$stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$stmt->bindValue(':date', $date, SQLITE3_TEXT);
$stmt->execute();

header('Location: rekap_absensi.php?success=Record+deleted+successfully');
exit();
?>
