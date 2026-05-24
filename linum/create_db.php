<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300); // 5 minutes

echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f8f9fa; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
echo "<h2 style='text-align: center; margin-bottom: 20px;'>Database Setup Process (mysqli)</h2>";

$host = "localhost";
$user = "vaywinar";
$pass = "admin123";
$dbname = "siswa_report";

// Connect to MySQL server
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("<div style='color: red;'>Connection failed: " . $conn->connect_error . "</div>");
}
echo "✅ Connected to MySQL server<br>";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database '$dbname' created or already exists<br>";
} else {
    die("<div style='color: red;'>Error creating database: " . $conn->error . "</div>");
}

// Select the database
$conn->select_db($dbname);
echo "✅ Selected database '$dbname'<br>";

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('siswa', 'admin') NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "✅ Users table created successfully<br>";
} else {
    die("<div style='color: red;'>Error creating users table: " . $conn->error . "</div>");
}

// Create subjects table
$sql = "CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "✅ Subjects table created successfully<br>";
} else {
    die("<div style='color: red;'>Error creating subjects table: " . $conn->error . "</div>");
}

// Create summaries table
$sql = "CREATE TABLE IF NOT EXISTS summaries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)";
if ($conn->query($sql) === TRUE) {
    echo "✅ Summaries table created successfully<br>";
} else {
    die("<div style='color: red;'>Error creating summaries table: " . $conn->error . "</div>");
}

// Insert default subjects
$subjects = [
    'PAI-BP', 'Pendidikan Pancasila', 'B. Indonesia', 'Matematika',
    'IPAS', 'B. Inggris', 'Seni Budaya', 'B. Jawa', 'PJOK',
    'B. Arab', 'Aqidah'
];
$stmt = $conn->prepare("INSERT IGNORE INTO subjects (name) VALUES (?)");
foreach ($subjects as $subject) {
    $stmt->bind_param("s", $subject);
    $stmt->execute();
}
echo "✅ Default subjects inserted successfully<br>";

// Insert default admin user with hashed password
$admin_username = 'admin';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$admin_fullname = 'Administrator';
$admin_role = 'admin';

$stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $admin_username, $admin_password, $admin_fullname, $admin_role);
$stmt->execute();
echo "✅ Default admin user created successfully<br>";

echo "</div>";
$conn->close();
?>
