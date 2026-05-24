<?php
// verify_users.php
// Simple script to list all users in the database for verification

try {
    $db = new SQLite3('absen.db');
    $result = $db->query('SELECT id, username, role FROM users');
    echo "Users in database:\\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "ID: {$row['id']}, Username: {$row['username']}, Role: {$row['role']}\\n";
    }
} catch (Exception $e) {
    echo "Error accessing database: " . $e->getMessage() . "\\n";
}
?>
