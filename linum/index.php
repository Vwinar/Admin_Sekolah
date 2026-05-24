<?php
// Linum no longer requires login - directly redirect to student dashboard
// Check if database exists, if not redirect to setup
try {
    $dbFile = __DIR__ . '/database.sqlite';
    if (!file_exists($dbFile)) {
        header("Location: setup_sqlite.php");
        exit;
    }
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if users table exists
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    $tableExists = $result->fetch();

    if (!$tableExists) {
        // Database tables not created, redirect to setup
        header("Location: setup_sqlite.php");
        exit;
    } else {
        // Database exists, redirect directly to siswa dashboard (no login required)
        header("Location: siswa/dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>