<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $dbFile = __DIR__ . '/database.sqlite';
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Checking summaries table structure:\n";
    $stmt = $pdo->query("PRAGMA table_info(summaries)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "Column: " . $col['name'] . " | Type: " . $col['type'] . "\n";
    }

    echo "\n\nSample data from summaries (first 2 rows):\n";
    $stmt = $pdo->query("SELECT * FROM summaries LIMIT 2");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
        echo "---\n";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>