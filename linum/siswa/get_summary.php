<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();


if (isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$id) {
        die(json_encode(['success' => false, 'message' => 'Invalid summary ID']));
    }

    try {
        // Fetch summary ensuring it belongs to the current user
        $stmt = $pdo->prepare("SELECT s.*, sub.name as subject_name 
                              FROM summaries s 
                              JOIN subjects sub ON s.subject_id = sub.id 
                              WHERE s.id = ? AND s.user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

        if ($summary = $stmt->fetch()) {
            echo json_encode([
                'success' => true,
                'id' => $summary['id'],
                'subject_id' => $summary['subject_id'],
                'subject_name' => $summary['subject_name'],
                'content' => $summary['content']
            ]);
        } else {
            die(json_encode(['success' => false, 'message' => 'Summary not found']));
        }
    } catch (PDOException $e) {
        die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
    }
} else {
    die(json_encode(['success' => false, 'message' => 'No summary ID provided']));
}
?>