<?php
// functions.php
require_once 'config.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function checkLogin()
{
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function sanitizeInput($data)
{
    return htmlspecialchars(trim($data));
}

function checkRole($required_role)
{
    if ($_SESSION['role'] !== $required_role) {
        // Allow guru to access admin pages
        if ($required_role === 'admin' && $_SESSION['role'] === 'guru') {
            return;
        }
        header("Location: login.php");
        exit();
    }
}

// New function: Ensure session exists for linum (no login required)
function ensureSession()
{
    if (!isset($_SESSION['user_id'])) {
        // Set default session values for direct access
        $_SESSION['user_id'] = 1; // Default to first siswa
        $_SESSION['role'] = 'siswa';
        $_SESSION['full_name'] = 'Siswa';
        $_SESSION['photo'] = '';
        $_SESSION['assigned_class'] = '';
    }
}

function countWords($text)
{
    return str_word_count(strip_tags($text));
}

function uploadImage($file)
{
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $target_file = $target_dir . uniqid() . "." . $imageFileType;

    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }

    // Check file size (max 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large."];
    }

    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        return ["success" => false, "message" => "Only JPG, JPEG & PNG files are allowed."];
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ["success" => true, "filename" => basename($target_file)];
    } else {
        return ["success" => false, "message" => "Error uploading file."];
    }
}

function getGuruClass($userId)
{
    // Only applies if role is 'guru' (checked via session in caller usually, but explicit check here is good)
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru')
        return null;

    try {
        // Connect to laporan.db
        // Assumes directory structure: 
        // /c:/xampp/htdocs/linum/functions.php
        // /c:/xampp/htdocs/laporan_harian/database/laporan.db
        $dbPath = __DIR__ . '/../laporan_harian/database/laporan.db';

        if (!file_exists($dbPath))
            return null;

        $laporanDb = new PDO('sqlite:' . $dbPath);
        $laporanDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $laporanDb->prepare("SELECT assigned_class FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['assigned_class'] : null;
    } catch (Exception $e) {
        return null;
    }
}
?>