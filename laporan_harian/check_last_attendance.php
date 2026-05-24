<?php
require_once 'config/db_connect.php';
header('Content-Type: text/plain');

echo "Checking latest attendance...\n";
$stmt = $db->query("SELECT * FROM attendance ORDER BY date DESC, jam_masuk DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "User ID: " . $row['user_id'] . "\n";
    echo "Date: " . $row['date'] . "\n";
    echo "Jam Masuk: " . $row['jam_masuk'] . "\n";
    echo "Jam Pulang: " . $row['jam_pulang'] . "\n";
    echo "Foto Masuk: " . $row['foto_masuk'] . "\n";
    echo "Foto Pulang: " . ($row['foto_pulang'] ? $row['foto_pulang'] : "NULL/EMPTY") . "\n";

    if ($row['foto_pulang']) {
        $path = __DIR__ . '/' . $row['foto_pulang'];
        echo "Checking file path: $path\n";
        if (file_exists($path)) {
            echo "File EXISTS.\n";
        } else {
            echo "File DOES NOT EXIST.\n";
        }
    }
} else {
    echo "No records found.\n";
}
?>