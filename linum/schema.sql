-- Database schema for Student Learning Summary Report Application

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS siswa_report;
USE siswa_report;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('siswa', 'admin') NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subjects table
CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL
);

-- Summaries table
CREATE TABLE IF NOT EXISTS summaries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    subject_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Insert default subjects
INSERT INTO subjects (name) VALUES 
    ('PAI-BP'),
    ('Pendidikan Pancasila'),
    ('B. Indonesia'),
    ('Matematika'),
    ('IPAS'),
    ('B. Inggris'),
    ('Seni Budaya'),
    ('B. Jawa'),
    ('PJOK'),
    ('B. Arab'),
    ('Aqidah');

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, role) VALUES 
    ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');
