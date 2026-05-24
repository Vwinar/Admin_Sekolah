<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
echo "<h2 style='text-align: center; margin-bottom: 20px;'>SQLite Database Setup Process</h2>";

try {
    $dbFile = __DIR__ . '/database.sqlite';
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connected to SQLite database at $dbFile<br>";

    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT CHECK(role IN ('siswa', 'admin')) NOT NULL,
        photo TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Users table created successfully<br>";

    // Create subjects table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    )");
    echo "✅ Subjects table created successfully<br>";

    // Create summaries table
    $pdo->exec("CREATE TABLE IF NOT EXISTS summaries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        subject_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        materi TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )");
    echo "✅ Summaries table created successfully<br>";

    // Insert default subjects
    $subjects = [
        'PAI-BP', 'Pendidikan Pancasila', 'B. Indonesia', 'Matematika',
        'IPAS', 'B. Inggris', 'Seni Budaya', 'B. Jawa', 'PJOK',
        'B. Arab', 'Aqidah'
    ];
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO subjects (name) VALUES (:name)");
    foreach ($subjects as $subject) {
        $stmt->execute([':name' => $subject]);
    }
    echo "✅ Default subjects inserted successfully<br>";

    // Insert default admin user (password: admin123)
    $adminUsername = 'vaywinar';
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $adminFullName = 'Administrator';
    $adminRole = 'admin';

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)");
    $stmt->execute([
        ':username' => $adminUsername,
        ':password' => $adminPassword,
        ':full_name' => $adminFullName,
        ':role' => $adminRole
    ]);
    echo "✅ Default admin user created successfully<br>";

    // Create materi table
    $pdo->exec("CREATE TABLE IF NOT EXISTS materi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "✅ Materi table created successfully<br>";

    // Create books table
    $pdo->exec("CREATE TABLE IF NOT EXISTS books (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        author TEXT NOT NULL,
        publisher TEXT NOT NULL,
        link TEXT DEFAULT NULL,
        pdf_path TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ Books table created successfully<br>";

} catch (PDOException $e) {
    echo "<div style='color: red;'>Error: " . $e->getMessage() . "</div>";
}

echo "</div>";
?>
