<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    // Validate input
    if (empty($title) || empty($content)) {
        die(json_encode(['success' => false, 'message' => 'Judul dan isi materi harus diisi']));
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO materi (user_id, title, content, created_at, updated_at) 
                              VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute([$_SESSION['user_id'], $title, $content]);

        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}
?>