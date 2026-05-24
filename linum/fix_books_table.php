<?php
try {
    $dbFile = __DIR__ . '/database.sqlite';
    $pdo = new PDO("sqlite:" . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if books table exists
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='books'");
    $tableExists = $result->fetch();

    if (!$tableExists) {
        // Create books table
        $pdo->exec("CREATE TABLE books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            author TEXT NOT NULL,
            publisher TEXT NOT NULL,
            link TEXT DEFAULT NULL,
            pdf_path TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Books table created successfully.\n";
    } else {
        echo "Books table already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
