<?php
session_start();
require_once '../config/db_connect.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$name = $_GET['name'] ?? '';
$class = $_GET['class'] ?? '';

if (empty($name) || empty($class)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    // Get student ID
    $stmtStudent = $db->prepare("SELECT id FROM students WHERE name = ? AND class_name = ?");
    $stmtStudent->execute([$name, $class]);
    $student = $stmtStudent->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit;
    }

    $studentId = $student['id'];

    // Get gender from student_details
    $stmtDetails = $db->prepare("SELECT gender FROM student_details WHERE student_id = ?");
    $stmtDetails->execute([$studentId]);
    $details = $stmtDetails->fetch();
    $gender = $details['gender'] ?? '';

    // Get activities from student_activities
    $stmtActivities = $db->prepare("SELECT activity_name FROM student_activities WHERE student_id = ?");
    $stmtActivities->execute([$studentId]);
    $activitiesRows = $stmtActivities->fetchAll();
    $activities = array_column($activitiesRows, 'activity_name');

    echo json_encode([
        'success' => true,
        'gender' => $gender,
        'activities' => $activities
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
