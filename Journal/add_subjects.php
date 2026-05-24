<?php
// Script to add subjects PAI and PJOK to the subjects table if they do not exist

require_once 'db.php';

function addSubjectIfNotExists($subjectName) {
    global $pdo;
    // Check if subject exists
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
    $stmt->execute([$subjectName]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "Subject '{$subjectName}' already exists with ID: " . $existing['id'] . "\n";
        return false;
    }

    // Insert new subject with default color_class
    $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
    $stmt->execute([$subjectName]);
    $newId = $pdo->lastInsertId();
    echo "Subject '{$subjectName}' added with ID: {$newId}\n";
    return true;
}

$subjectsToAdd = ['Pend. Agama Islam', 'PJOK'];

foreach ($subjectsToAdd as $subject) {
    addSubjectIfNotExists($subject);
}

// Verify: Display all subjects
echo "\nAll subjects in database:\n";
$allSubjects = getAllSubjects();
foreach ($allSubjects as $subj) {
    echo "- ID: {$subj['id']}, Name: {$subj['name']}, Color: {$subj['color_class']}\n";
}
?>
