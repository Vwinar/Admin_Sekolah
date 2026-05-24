<?php
ob_start();
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $content = trim($_POST['content']);

    // Validate input
    if (!$id || !$subject_id) {
        die(json_encode(['success' => false, 'message' => 'Invalid input data']));
    }

    // Check word count
    if (str_word_count($content) < 100) {
        die(json_encode(['success' => false, 'message' => 'Rangkuman harus minimal 100 kata']));
    }

    try {
        // First verify the summary belongs to the current user
        $stmt = $pdo->prepare("SELECT user_id FROM summaries WHERE id = ?");
        $stmt->execute([$id]);
        $summary = $stmt->fetch();

        if (!$summary || $summary['user_id'] != $_SESSION['user_id']) {
            die(json_encode(['success' => false, 'message' => 'Unauthorized access to this summary']));
        }

        $materi = trim($_POST['materi'] ?? '');

        // Update the summary
        $stmt = $pdo->prepare("UPDATE summaries 
                              SET subject_id = ?, content = ?, materi = ?, updated_at = CURRENT_TIMESTAMP 
                              WHERE id = ? AND user_id = ?");
        $stmt->execute([$subject_id, $content, $materi, $id, $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'message' => 'Rangkuman berhasil diperbarui']);
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
    ob_clean();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        die(json_encode(['success' => false, 'message' => 'Invalid or missing summary ID']));
    }
    try {
        $stmt = $pdo->prepare("SELECT id, subject_id, content, materi FROM summaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$summary) {
            die(json_encode(['success' => false, 'message' => 'Summary not found or unauthorized']));
        }
        // Sanitize content to remove control characters that break JSON
        $summary['content'] = preg_replace('/[\x00-\x1F\x7F\x80-\x9F]/u', '', $summary['content']);
        $summary['materi'] = preg_replace('/[\x00-\x1F\x7F\x80-\x9F]/u', '', $summary['materi']);
        // Optionally base64 encode content and materi to avoid JSON issues
        $summary['content'] = base64_encode($summary['content']);
        $summary['materi'] = base64_encode($summary['materi']);
        echo json_encode(['success' => true, 'data' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}
?>