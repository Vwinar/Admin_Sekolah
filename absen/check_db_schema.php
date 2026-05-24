<?php
try {
    $db = new SQLite3('absen.db');
    $result = $db->query("PRAGMA table_info(users);");
    echo "Users table columns:\n";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo $row['cid'] . ": " . $row['name'] . " (" . $row['type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
