<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['subject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Subject is required']);
    exit;
}

$subject = $_GET['subject'];
$fields = [
    'capaian_pembelajaran',
    'pokok_materi',
    'pencapaian',
    'permasalahan',
    'solusi',
    'catatan_pembelajaran'
];

$response = [];

foreach ($fields as $field) {
    if ($subject) {
        $templates = getTemplatesBySubjectAndType($subject, $field);
        // Extract content string only
        $options = array_column($templates, 'content');
        $response[$field] = $options;
    } else {
        $response[$field] = [];
    }
}

echo json_encode($response);
exit;
