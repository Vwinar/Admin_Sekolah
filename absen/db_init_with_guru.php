<?php
// db_init_with_guru.php
// Initialize SQLite database and create tables with guru users

try {
    $db = new SQLite3('absen.db');

    // Drop existing tables if they exist (optional, to reset database)
    $db->exec("DROP TABLE IF EXISTS users");
    $db->exec("DROP TABLE IF EXISTS attendance");
    $db->exec("DROP TABLE IF EXISTS izin");

    // Create users table (id, full_name, username, password_hash, role, nip)
    $db->exec("CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        full_name TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'guru')),
        nip TEXT
    )") or die("Failed to create users table");

    // Create attendance table (id, user_id, date, jam_masuk, jam_pulang, status, durasi, lokasi_lat, lokasi_lng, jarak, keterangan, foto_masuk, foto_pulang)
    $db->exec("CREATE TABLE attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        jam_masuk TEXT,
        jam_pulang TEXT,
        status TEXT,
        durasi TEXT,
        lokasi_lat REAL,
        lokasi_lng REAL,
        jarak REAL,
        keterangan TEXT,
        foto_masuk TEXT,
        foto_pulang TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )") or die("Failed to create attendance table");

    // Create izin table (id, user_id, date, jenis_izin, keterangan, foto, status)
    $db->exec("CREATE TABLE izin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        jenis_izin TEXT NOT NULL CHECK(jenis_izin IN ('izin', 'sakit')),
        keterangan TEXT,
        foto TEXT,
        status TEXT DEFAULT 'pending',
        FOREIGN KEY(user_id) REFERENCES users(id)
    )") or die("Failed to create izin table");

    // Insert default admin user (username: admin, password: admin123)
    $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $result = $db->exec("INSERT INTO users (full_name, username, password_hash, role) VALUES ('Admin', 'admin', '$adminPasswordHash', 'admin')");
    if (!$result) {
        die("Failed to insert admin user");
    }

    // Insert given guru users with custom usernames and passwords
    $guru_users = [
        ["full_name" => "Miftakhul Janah", "username" => "miftakhul_janah", "password" => "miftakhul123", "nip" => ""],
        ["full_name" => "Ahmad Zamroni Fadlil", "username" => "zam", "password" => "zam123", "nip" => ""],
        ["full_name" => "Isrofatul Husnah", "username" => "isrofatul", "password" => "isrofatul123", "nip" => ""],
        ["full_name" => "Ita Purwati", "username" => "ita", "password" => "ita123", "nip" => ""],
        ["full_name" => "Meilia Ika Nur Irdana", "username" => "meilia", "password" => "meilia123", "nip" => ""],
        ["full_name" => "Munipah", "username" => "munipah", "password" => "munipah123", "nip" => ""],
        ["full_name" => "Siti Azima", "username" => "siti", "password" => "siti123", "nip" => ""],
        ["full_name" => "Yanu Wachid Setiaji", "username" => "yanu", "password" => "yanu123", "nip" => ""],
        ["full_name" => "Yono Harto Winarno", "username" => "yono", "password" => "yono123", "nip" => ""],
        ["full_name" => "Ahmad Hari", "username" => "ahmad", "password" => "ahmad123", "nip" => ""],
        ["full_name" => "Puspita Ayu Wulandari", "username" => "puspita", "password" => "puspita123", "nip" => ""]
    ];

    foreach ($guru_users as $guru) {
        $full_name = $guru['full_name'];
        $username = $guru['username'];
        $nip = $guru['nip'];
        $passwordHash = password_hash($guru['password'], PASSWORD_DEFAULT);
        $result = $db->exec("INSERT INTO users (full_name, username, password_hash, role, nip) VALUES ('$full_name', '$username', '$passwordHash', 'guru', '$nip')");
        if (!$result) {
            die("Failed to insert user: $username");
        }
    }

    echo "Database initialized successfully with admin and guru users.\n";
} catch (Exception $e) {
    die("Database initialization error: " . $e->getMessage());
}
?>
