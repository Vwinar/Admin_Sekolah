<?php
$db = new SQLite3('absen.db');

// Add full_name and nip columns to users table if they don't exist
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin', 'guru'),
    full_name TEXT,
    nip TEXT
)");

// Create attendance table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS attendance (
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
)");

// Create izin table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS izin (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    jenis_izin TEXT NOT NULL CHECK(jenis_izin IN ('izin', 'sakit')),
    keterangan TEXT,
    foto TEXT,
    status TEXT DEFAULT 'pending',
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// Create settings table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY,
    latitude REAL,
    longitude REAL,
    radius REAL,
    waktu_masuk TEXT,
    waktu_pulang TEXT,
    school_logo TEXT,
    school_name TEXT
)");

// Delete all data from tables
$db->exec("DELETE FROM users");
$db->exec("DELETE FROM attendance");
$db->exec("DELETE FROM izin");
$db->exec("DELETE FROM settings");

// Insert only the specified admin user
$adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
$insertAdminStmt = $db->prepare("INSERT INTO users (username, password_hash, role, full_name, nip) VALUES (:username, :password_hash, :role, :full_name, :nip)");
$insertAdminStmt->bindValue(':username', 'vaywinar', SQLITE3_TEXT);
$insertAdminStmt->bindValue(':password_hash', $adminPasswordHash, SQLITE3_TEXT);
$insertAdminStmt->bindValue(':role', 'admin', SQLITE3_TEXT);
$insertAdminStmt->bindValue(':full_name', 'vaywinar', SQLITE3_TEXT);
$insertAdminStmt->bindValue(':nip', '00000', SQLITE3_TEXT);
$insertAdminStmt->execute();

// Delete all files from uploads folder
$uploadDir = __DIR__ . '/uploads';
$uploadMessage = '';
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $filePath = $uploadDir . '/' . $file;
            if (is_file($filePath)) {
                if (!unlink($filePath)) {
                    $uploadMessage .= "Failed to delete file: $filePath\n";
                }
            }
        }
    }
    $uploadMessage = "All files in uploads folder have been deleted.";
} else {
    $uploadMessage = "Uploads folder does not exist.";
}

// HTML output
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Initialization</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #4CAF50;
            margin-top: 0;
        }
        .message {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
            text-align: left;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 10px 2px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Initialization Complete</h1>
        
        <div class="message">
            <p><strong>Admin account created:</strong></p>
            <p>Username: vaywinar</p>
            <p>Password: admin123</p>
        </div>
        
        <div class="message">
            <p><strong>Uploads folder:</strong> {$uploadMessage}</p>
        </div>
        
        <p>Database has been successfully initialized with only the admin user.</p>
        
        <a href="login.php" class="btn">OK</a>
    </div>
</body>
</html>
HTML;
?>