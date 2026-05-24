<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        die(json_encode(['success' => false, 'message' => 'Invalid summary ID']));
    }

    try {
        // First verify the summary belongs to the current user
        $stmt = $pdo->prepare("SELECT user_id FROM summaries WHERE id = ?");
        $stmt->execute([$id]);
        $summary = $stmt->fetch();

        if (!$summary || $summary['user_id'] != $_SESSION['user_id']) {
            die(json_encode(['success' => false, 'message' => 'Unauthorized access to this summary']));
        }

        // Delete the summary
        $stmt = $pdo->prepare("DELETE FROM summaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'message' => 'Rangkuman berhasil dihapus']);
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => 'Invalid request method']));
}
?>