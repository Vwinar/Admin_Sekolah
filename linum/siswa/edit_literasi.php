<?php
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Linum no longer requires login - ensure session exists
ensureSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$author = isset($_POST['author']) ? trim($_POST['author']) : '';
$publisher = isset($_POST['publisher']) ? trim($_POST['publisher']) : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$userId = $_SESSION['user_id'];

if ($id <= 0 || empty($title) || empty($author) || empty($publisher) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE literasi SET title = ?, author = ?, publisher = ?, content = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$title, $author, $publisher, $content, $id, $userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>