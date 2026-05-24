<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Connection to Linum Local DB (for Literasi/Numerasi Data)
    $dbFile = __DIR__ . '/database.sqlite';
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Connection to Laporan Harian DB (for Authentication/User Data)
    $laporanDbFile = __DIR__ . '/../laporan_harian/database/laporan.db';
    $laporanPdo = new PDO("sqlite:" . $laporanDbFile);
    $laporanPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Migration: Add class column to users if not exists (This is for LOCAL linum users if any are left? Or unnecessary now?)
    // If we migrate to Laporan Users completely, we might not need this.
    // But existing code might rely on 'users' table in Linum DB.
    // Ideally we should start using $laporanPdo for user queries.
    // For now, let's keep local connection valid.

    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('class', $cols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN class TEXT");
    }
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
session_start();
?>