<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Linum no longer requires login - ensure session exists
ensureSession();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit();
}

$id = intval($_GET['id']);
$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM literasi WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);
    $literasi = $stmt->fetch();

    if ($literasi) {
        echo json_encode(['success' => true, 'literasi' => $literasi]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Literasi not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>