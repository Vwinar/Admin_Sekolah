<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $materi = trim($_POST['materi'] ?? '');
    $content = trim($_POST['content']);

    // Validate input
    if (!$subject_id) {
        die(json_encode(['success' => false, 'message' => 'Invalid subject']));
    }

    // Check word count
    if (str_word_count($content) < 100) {
        die(json_encode(['success' => false, 'message' => 'Rangkuman harus minimal 100 kata']));
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO summaries (user_id, subject_id, materi, content, created_at, updated_at) 
                              VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute([$_SESSION['user_id'], $subject_id, $materi, $content]);

        header("Location: dashboard.php");
        exit();
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
}
?>