<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

// Handle Delete User (with cascade delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id_to_delete = $_POST['user_id'];
    if ($user_id_to_delete) {
        try {
            // Start transaction for cascade delete
            $db->beginTransaction();

            // Get user info before deletion
            $userInfo = $db->prepare("SELECT full_name, role FROM users WHERE id = ?");
            $userInfo->execute([$user_id_to_delete]);
            $user = $userInfo->fetch();

            $deletedInfo = [];

            // Delete related data in correct order
            // 1. Delete reports
            $stmt1 = $db->prepare("DELETE FROM reports WHERE user_id = ?");
            $stmt1->execute([$user_id_to_delete]);
            $deletedReports = $stmt1->rowCount();

            // 2. Delete attendance records
            $stmt2 = $db->prepare("DELETE FROM attendance WHERE user_id = ?");
            $stmt2->execute([$user_id_to_delete]);
            $deletedAttendance = $stmt2->rowCount();

            // 3. Delete izin/permission records
            $stmt3 = $db->prepare("DELETE FROM izin WHERE user_id = ?");
            $stmt3->execute([$user_id_to_delete]);
            $deletedIzin = $stmt3->rowCount();

            // 4. Delete student attendance recorded by this teacher
            $stmt4 = $db->prepare("DELETE FROM student_attendance WHERE teacher_id = ?");
            $stmt4->execute([$user_id_to_delete]);
            $deletedStudentAttendance = $stmt4->rowCount();
            if ($deletedStudentAttendance > 0)
                $deletedInfo[] = "$deletedStudentAttendance absensi siswa";

            // === DELETE FROM JOURNAL DATABASE ===
            try {
                $journalDbFile = __DIR__ . '/../Journal/journal.db';
                if (file_exists($journalDbFile)) {
                    $journalPdo = new PDO('sqlite:' . $journalDbFile);
                    $journalPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $journalPdo->beginTransaction();

                    // Delete journal entries
                    $stmtJ1 = $journalPdo->prepare("DELETE FROM journal_entries WHERE user_id = ?");
                    $stmtJ1->execute([$user_id_to_delete]);
                    $deletedJEntries = $stmtJ1->rowCount();
                    if ($deletedJEntries > 0)
                        $deletedInfo[] = "$deletedJEntries jurnal";

                    // Delete templates
                    $stmtJ2 = $journalPdo->prepare("DELETE FROM templates WHERE user_id = ?");
                    $stmtJ2->execute([$user_id_to_delete]);
                    $deletedTemplates = $stmtJ2->rowCount();
                    if ($deletedTemplates > 0)
                        $deletedInfo[] = "$deletedTemplates template";

                    // Delete schedules
                    $stmtJ3 = $journalPdo->prepare("DELETE FROM schedules WHERE user_id = ?");
                    $stmtJ3->execute([$user_id_to_delete]);
                    $deletedSchedules = $stmtJ3->rowCount();
                    if ($deletedSchedules > 0)
                        $deletedInfo[] = "$deletedSchedules jadwal";

                    $journalPdo->commit();
                }
            } catch (Exception $e) {
                // Journal DB might not exist, ignore
                if (isset($journalPdo) && $journalPdo->inTransaction()) {
                    $journalPdo->rollBack();
                }
            }

            // === DELETE FROM LINUM DATABASE ===
            try {
                $linumDbFile = __DIR__ . '/../linum/database.sqlite';
                if (file_exists($linumDbFile)) {
                    $linumPdo = new PDO('sqlite:' . $linumDbFile);
                    $linumPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $linumPdo->beginTransaction();

                    // Delete literasi entries
                    $stmtL1 = $linumPdo->prepare("DELETE FROM literasi WHERE user_id = ?");
                    $stmtL1->execute([$user_id_to_delete]);
                    $deletedLiterasi = $stmtL1->rowCount();
                    if ($deletedLiterasi > 0)
                        $deletedInfo[] = "$deletedLiterasi literasi";

                    // Delete summaries
                    $stmtL2 = $linumPdo->prepare("DELETE FROM summaries WHERE user_id = ?");
                    $stmtL2->execute([$user_id_to_delete]);
                    $deletedSummaries = $stmtL2->rowCount();
                    if ($deletedSummaries > 0)
                        $deletedInfo[] = "$deletedSummaries rangkuman";

                    $linumPdo->commit();
                }
            } catch (Exception $e) {
                // Linum DB might not exist, ignore
                if (isset($linumPdo) && $linumPdo->inTransaction()) {
                    $linumPdo->rollBack();
                }
            }

            // 5. Delete from students table and all related student data if applicable
            if ($user && $user['role'] == 'siswa') {
                // Get student_id first
                $stmtGetStudent = $db->prepare("SELECT id FROM students WHERE name = ? AND class_name = ?");
                $stmtGetStudent->execute([$user['full_name'], $user['assigned_class'] ?? '']);
                $student = $stmtGetStudent->fetch();

                if ($student) {
                    $studentId = $student['id'];

                    // Delete from all related student tables
                    $studentTables = [
                        'student_attendance' => 'absensi siswa',
                        'student_notes' => 'catatan siswa',
                        'student_health' => 'kesehatan siswa',
                        'consultations' => 'konsultasi',
                        'student_activities' => 'kegiatan ekstrakurikuler',
                        'student_details' => 'detail siswa',
                        'student_grades' => 'nilai siswa',
                        'student_mutation' => 'mutasi siswa'
                    ];

                    foreach ($studentTables as $table => $label) {
                        $stmtDelRelated = $db->prepare("DELETE FROM {$table} WHERE student_id = ?");
                        $stmtDelRelated->execute([$studentId]);
                        $deletedCount = $stmtDelRelated->rowCount();
                        if ($deletedCount > 0) {
                            $deletedInfo[] = "$deletedCount data $label";
                        }
                    }

                    // Finally delete from students table
                    $stmtDelStudent = $db->prepare("DELETE FROM students WHERE id = ?");
                    $stmtDelStudent->execute([$studentId]);
                    $deletedStudents = $stmtDelStudent->rowCount();
                    if ($deletedStudents > 0)
                        $deletedInfo[] = "$deletedStudents data siswa";
                }
            }

            // 6. Finally, delete the user
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id_to_delete]);

            // Commit transaction
            $db->commit();

            // Add remaining deletion info
            if ($deletedReports > 0)
                $deletedInfo[] = "$deletedReports laporan";
            if ($deletedAttendance > 0)
                $deletedInfo[] = "$deletedAttendance absensi";
            if ($deletedIzin > 0)
                $deletedInfo[] = "$deletedIzin izin";

            $success = "User " . htmlspecialchars($user['full_name']) . " berhasil dihapus.";
            if (!empty($deletedInfo)) {
                $success .= " Terhapus juga: " . implode(", ", $deletedInfo) . ".";
            }

        } catch (Exception $e) {
            // Rollback on error
            $db->rollBack();
            if (isset($journalPdo) && $journalPdo->inTransaction()) {
                $journalPdo->rollBack();
            }
            if (isset($linumPdo) && $linumPdo->inTransaction()) {
                $linumPdo->rollBack();
            }
            $error = "Gagal menghapus user: " . $e->getMessage();
        }
    }
}

// Handle Edit/Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $user_id_to_edit = $_POST['user_id'];
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $subject = trim($_POST['subject']);
    $assigned_class = trim($_POST['assigned_class']);
    $role = trim($_POST['role']);
    $nip = trim($_POST['nip']);
    $new_password = $_POST['password'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $ekstrakurikuler = $_POST['ekstrakurikuler'] ?? [];

    if ($user_id_to_edit) {
        try {
            // Check if username is taken by another user
            $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
            $check->execute([$username, $user_id_to_edit]);
            if ($check->fetchColumn() > 0) {
                $error = "Username sudah digunakan oleh user lain!";
            } else {
                $db->beginTransaction();

                // Prepare update query
                if (!empty($new_password)) {
                    // Update with new password
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, password = ?, role = ?, full_name = ?, subject = ?, assigned_class = ?, nip = ? WHERE id = ?");
                    $stmt->execute([$username, $hashed, $role, $full_name, $subject, $assigned_class, $nip, $user_id_to_edit]);
                } else {
                    // Update without changing password
                    $stmt = $db->prepare("UPDATE users SET username = ?, role = ?, full_name = ?, subject = ?, assigned_class = ?, nip = ? WHERE id = ?");
                    $stmt->execute([$username, $role, $full_name, $subject, $assigned_class, $nip, $user_id_to_edit]);
                }

                // Jika role siswa, update student_details dan activities
                if ($role === 'siswa') {
                    // Dapatkan student_id dari nama dan kelas
                    $stmtGetStudent = $db->prepare("SELECT id FROM students WHERE name = ? AND class_name = ?");
                    $stmtGetStudent->execute([$full_name, $assigned_class]);
                    $student = $stmtGetStudent->fetch();

                    if ($student) {
                        $studentId = $student['id'];

                        // Update gender di student_details
                        if (!empty($gender)) {
                            $checkDetail = $db->prepare("SELECT COUNT(*) FROM student_details WHERE student_id = ?");
                            $checkDetail->execute([$studentId]);
                            if ($checkDetail->fetchColumn() > 0) {
                                $stmtUpdateDetail = $db->prepare("UPDATE student_details SET gender = ? WHERE student_id = ?");
                                $stmtUpdateDetail->execute([$gender, $studentId]);
                            } else {
                                $stmtInsertDetail = $db->prepare("INSERT INTO student_details (student_id, gender) VALUES (?, ?)");
                                $stmtInsertDetail->execute([$studentId, $gender]);
                            }
                        }

                        // Update ekstrakurikuler - hapus yang lama, tambah yang baru
                        $stmtDelActivity = $db->prepare("DELETE FROM student_activities WHERE student_id = ?");
                        $stmtDelActivity->execute([$studentId]);

                        if (!empty($ekstrakurikuler)) {
                            $stmtActivity = $db->prepare("INSERT INTO student_activities (student_id, activity_name, role) VALUES (?, ?, ?)");
                            foreach ($ekstrakurikuler as $activity) {
                                $stmtActivity->execute([$studentId, $activity, 'Anggota']);
                            }
                        }
                    }
                }

                $db->commit();
                $success = "User berhasil diperbarui.";
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Gagal memperbarui user: " . $e->getMessage();
        }
    }
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $subject = trim($_POST['subject']);
    $assigned_class = trim($_POST['assigned_class']);
    $role = trim($_POST['role']);
    $nip = trim($_POST['nip']);
    $gender = $_POST['gender'] ?? '';
    $ekstrakurikuler = $_POST['ekstrakurikuler'] ?? [];

    // Check if username exists
    $check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetchColumn() > 0) {
        $error = "Username sudah digunakan!";
    } else {
        try {
            $db->beginTransaction();

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, role, full_name, subject, assigned_class, nip) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed, $role, $full_name, $subject, $assigned_class, $nip]);
            $userId = $db->lastInsertId();

            // Jika role siswa, tambahkan ke tabel students dan student_details
            if ($role === 'siswa') {
                // Insert ke students table
                $stmtStudent = $db->prepare("INSERT INTO students (name, class_name) VALUES (?, ?)");
                $stmtStudent->execute([$full_name, $assigned_class]);
                $studentId = $db->lastInsertId();

                // Insert ke student_details dengan gender
                if (!empty($gender)) {
                    $stmtDetail = $db->prepare("INSERT INTO student_details (student_id, gender) VALUES (?, ?)");
                    $stmtDetail->execute([$studentId, $gender]);
                }

                // Insert ekstrakurikuler
                if (!empty($ekstrakurikuler)) {
                    $stmtActivity = $db->prepare("INSERT INTO student_activities (student_id, activity_name, role) VALUES (?, ?, ?)");
                    foreach ($ekstrakurikuler as $activity) {
                        $stmtActivity->execute([$studentId, $activity, 'Anggota']);
                    }
                }
            }

            $db->commit();
            $success = "User berhasil ditambahkan.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Gagal menambahkan user: " . $e->getMessage();
        }
    }
}

// Fetch Users with Student Data
$usersQuery = "
    SELECT 
        u.*,
        s.id as student_id,
        sd.gender,
        GROUP_CONCAT(DISTINCT sa.activity_name) as ekstrakurikuler
    FROM users u
    LEFT JOIN students s ON u.full_name = s.name AND u.assigned_class = s.class_name
    LEFT JOIN student_details sd ON s.id = sd.student_id
    LEFT JOIN student_activities sa ON s.id = sa.student_id
    WHERE u.role != 'superadmin'
    GROUP BY u.id
    ORDER BY u.role ASC, u.full_name ASC
";
$users = $db->query($usersQuery)->fetchAll();
// Fetch Master Data
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-cancel {
            background-color: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-cancel:hover {
            background-color: #e5e7eb;
            transform: translateY(-1px);
        }

        .btn-confirm {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-confirm:hover {
            background-color: #fecaca;
            transform: translateY(-1px);
        }

        .btn-delete {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            background-color: #fecaca;
            transform: translateY(-1px);
        }

        .btn-edit {
            background-color: #eff5ff;
            color: #2e5192;
            border: 1px solid #b8d4ff;
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-edit:hover {
            background-color: #dbeafe;
            transform: translateY(-1px);
        }

        /* Desktop: Proper button sizing */
        @media (min-width: 769px) {
            .header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                flex-direction: row !important;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {

            /* Main content */
            .main-content {
                padding: 1rem !important;
            }

            /* Overall text smaller on mobile */
            body {
                font-size: 0.8rem !important;
            }

            /* Strict Horizontal Header for Mobile */
            .header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.5rem !important;
                margin-bottom: 1rem !important;
                width: 100% !important;
                padding: 0 !important;
                min-height: 4rem !important;
                flex-wrap: wrap !important;
            }

            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.6rem !important;
                flex: 1 !important;
                min-width: 0 !important;
            }

            .header-title {
                display: flex !important;
                flex-direction: column !important;
                min-width: 0 !important;
                justify-content: center !important;
            }

            .header h1 {
                font-size: 1rem !important;
                font-weight: 700 !important;
                margin: 0 !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .header-actions {
                flex-shrink: 0 !important;
            }

            /* Sidebar toggle adjustments */
            .sidebar-toggle {
                width: 2rem !important;
                height: 2rem !important;
                padding: 0.4rem !important;
                flex-shrink: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-around !important;
                margin: 0 !important;
            }

            .sidebar-toggle span {
                height: 2px !important;
            }

            /* Grid layout responsive */
            div[style*="grid-template-columns: 2fr 1fr"] {
                display: block !important;
            }

            .card {
                margin-bottom: 1rem !important;
            }

            /* Table responsive */
            .table-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            table {
                font-size: 0.7rem !important;
                min-width: 900px !important;
            }

            table th,
            table td {
                padding: 0.5rem 0.3rem !important;
            }

            .badge {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.4rem !important;
            }

            .btn-edit,
            .btn-delete {
                font-size: 0.7rem !important;
                padding: 0.3rem 0.5rem !important;
            }

            .form-group {
                margin-bottom: 0.75rem !important;
            }

            .form-label {
                font-size: 0.8rem !important;
            }

            .form-control {
                font-size: 0.8rem !important;
                padding: 0.5rem !important;
            }

            .btn {
                font-size: 0.75rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            /* Header button icon only on mobile */
            .header-actions .btn {
                padding: 0.4rem !important;
                height: 2.25rem !important;
                width: 2.25rem !important;
                min-width: 2.25rem !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 0.5rem !important;
            }

            .header-actions .btn .btn-text {
                display: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="header-title">
                        <h1>Manajemen Pengguna</h1>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="check_students_sync.php" class="chip-btn chip-btn-orange" title="Cek Sinkronisasi Data Siswa">
                        🔄 <span class="btn-text" style="margin-left: 0.25rem;">Cek Sinkronisasi Data Siswa</span>
                    </a>
                </div>
            </header>

            <?php if ($success): ?>
                <div
                    style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <div class="card">
                    <h3 class="mb-2">Daftar Pengguna</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>NIP</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Mapel</th>
                                    <th>Kelas</th>
                                    <th>Gender</th>
                                    <th>Ekstrakurikuler</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td><?= htmlspecialchars($u['nip'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($u['username']) ?></td>
                                        <td><span class="badge"><?= ucfirst($u['role']) ?></span></td>
                                        <td><?= htmlspecialchars($u['subject'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($u['assigned_class'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'siswa' && !empty($u['gender'])): ?>
                                                <span style="font-size: 0.85rem;">
                                                    <?= $u['gender'] === 'Laki-laki' ? '👦 L' : '👧 P' ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width: 200px; font-size: 0.85rem;">
                                            <?php if ($u['role'] === 'siswa' && !empty($u['ekstrakurikuler'])): ?>
                                                <span style="color: #4f46e5;">
                                                    <?= htmlspecialchars(str_replace(',', ', ', $u['ekstrakurikuler'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #9ca3af;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-edit"
                                                onclick="openEditModal(<?= $u['id'] ?>, '<?= addslashes($u['username']) ?>', '<?= addslashes($u['full_name']) ?>', '<?= addslashes($u['role']) ?>', '<?= addslashes($u['subject'] ?? '') ?>', '<?= addslashes($u['assigned_class'] ?? '') ?>', '<?= addslashes($u['nip'] ?? '') ?>')">Edit</button>
                                            <button class="btn-delete"
                                                onclick="confirmDelete(<?= $u['id'] ?>, '<?= addslashes($u['full_name']) ?>')">Hapus</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card" style="height: fit-content;">
                    <h3 class="mb-2">Tambah User Baru</h3>
                    <form method="POST" id="addUserForm">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">NIP (Khusus Guru/Admin)</label>
                            <input type="text" name="nip" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select name="role" id="addRoleSelect" class="form-control" required
                                onchange="toggleSiswaFields('add')">
                                <option value="guru">Guru</option>
                                <option value="siswa">Siswa</option>
                            </select>
                        </div>

                        <!-- Siswa specific fields -->
                        <div id="addSiswaFields" style="display: none;">
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin (L/P)</label>
                                <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="radio" name="gender" value="Laki-laki">
                                        <span>Laki-laki</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="radio" name="gender" value="Perempuan">
                                        <span>Perempuan</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Ekstrakurikuler (Pilih yang diikuti)</label>
                                <div
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Pramuka">
                                        <span>Pramuka</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Paskibra">
                                        <span>Paskibra</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="PMR">
                                        <span>PMR</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Basket">
                                        <span>Basket</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Futsal">
                                        <span>Futsal</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Tari">
                                        <span>Tari</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Musik">
                                        <span>Musik</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Robotik">
                                        <span>Robotik</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Bahasa Inggris">
                                        <span>Bahasa Inggris</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" name="ekstrakurikuler[]" value="Seni Lukis">
                                        <span>Seni Lukis</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mata Pelajaran (Khusus Guru)</label>
                            <select name="subject" class="form-control">
                                <option value="">- Pilih Mapel -</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kelas (Wali/Siswa)</label>
                            <select name="assigned_class" class="form-control">
                                <option value="">- Pilih Kelas -</option>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn chip-btn chip-btn-blue">Tambah User</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Confirm Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 1rem; color: #1f2937; font-size: 1.25rem; font-weight: 700;">⚠️ Konfirmasi Hapus
            </h3>
            <p style="color: #4b5563; margin-bottom: 0.5rem;">Apakah Anda yakin ingin menghapus user:</p>
            <p id="deleteUserName" style="font-weight: 600; color: #111827; margin-bottom: 0.5rem;"></p>

            <div
                style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 0.75rem; margin: 1rem 0; border-radius: 4px; text-align: left;">
                <p style="color: #92400e; font-size: 0.875rem; margin: 0; font-weight: 600;">
                    ⚠️ Peringatan: Semua data terkait akan dihapus!
                </p>
                <p style="color: #92400e; font-size: 0.8rem; margin: 0.5rem 0 0 0; line-height: 1.4;">
                    • Semua laporan harian<br>
                    • Semua data absensi guru<br>
                    • Semua data izin/sakit<br>
                    • Rekaman absensi siswa yang di-input<br>
                    • Semua data literasi & numerasi<br>
                    • Semua rangkuman harian<br>
                    • Semua jurnal pembelajaran<br>
                    • Semua template & jadwal<br>
                    <strong>• Khusus untuk Siswa:</strong><br>
                    &nbsp;&nbsp;- Data absensi siswa<br>
                    &nbsp;&nbsp;- Catatan prestasi & pelanggaran<br>
                    &nbsp;&nbsp;- Data kesehatan<br>
                    &nbsp;&nbsp;- Riwayat konsultasi<br>
                    &nbsp;&nbsp;- Kegiatan ekstrakurikuler<br>
                    &nbsp;&nbsp;- Detail profil siswa<br>
                    &nbsp;&nbsp;- Nilai & ranking<br>
                    &nbsp;&nbsp;- Data mutasi<br>
                    &nbsp;&nbsp;- Data dari tabel students
                </p>
            </div>

            <div class="modal-buttons">
                <button class="btn-cancel" onclick="closeModal()">Batal</button>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn-confirm">Ya, Hapus Semua</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h3 style="margin-bottom: 1rem; color: #1f2937; font-size: 1.25rem; font-weight: 700;">Edit User</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="editUserId">

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">NIP (Khusus Guru/Admin)</label>
                    <input type="text" name="nip" id="editNip" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="editUsername" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password (Biarkan kosong jika tidak ingin mengubah)</label>
                    <input type="password" name="password" id="editPassword" class="form-control"
                        placeholder="Masukkan password baru (opsional)">
                </div>

                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" id="editRole" class="form-control" required
                        onchange="toggleSiswaFields('edit')">
                        <option value="guru">Guru</option>
                        <option value="siswa">Siswa</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Siswa specific fields for Edit  -->
                <div id="editSiswaFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Jenis Kelamin (L/P)</label>
                        <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="gender" id="editGenderL" value="Laki-laki">
                                <span>Laki-laki</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="radio" name="gender" id="editGenderP" value="Perempuan">
                                <span>Perempuan</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Ekstrakurikuler (Pilih yang diikuti)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Pramuka">
                                <span>Pramuka</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Paskibra">
                                <span>Paskibra</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="PMR">
                                <span>PMR</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Basket">
                                <span>Basket</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Futsal">
                                <span>Futsal</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Tari">
                                <span>Tari</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Musik">
                                <span>Musik</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Robotik">
                                <span>Robotik</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra"
                                    value="Bahasa Inggris">
                                <span>Bahasa Inggris</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="ekstrakurikuler[]" class="edit-ekstra" value="Seni Lukis">
                                <span>Seni Lukis</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mata Pelajaran (Khusus Guru)</label>
                    <select name="subject" id="editSubject" class="form-control">
                        <option value="">- Pilih Mapel -</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Kelas (Wali/Siswa)</label>
                    <select name="assigned_class" id="editAssignedClass" class="form-control">
                        <option value="">- Pilih Kelas -</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Batal</button>
                    <button type="submit" class="btn chip-btn chip-btn-blue">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Logic
        const modal = document.getElementById('deleteModal');
        const deleteUserIdInput = document.getElementById('deleteUserId');
        const deleteUserNameDisplay = document.getElementById('deleteUserName');

        function confirmDelete(id, name) {
            deleteUserIdInput.value = id;
            deleteUserNameDisplay.textContent = name;
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Sidebar Toggle Functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');

        // Check localStorage for sidebar state
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        }

        // Toggle sidebar on button click
        sidebarToggle.addEventListener('click', function () {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');

            // Save state to localStorage
            const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    if (!dashboardLayout.classList.contains('sidebar-collapsed')) {
                        dashboardLayout.classList.add('sidebar-collapsed');
                        sidebarToggle.classList.add('active');
                        localStorage.setItem('sidebarCollapsed', 'true');
                    }
                }
            }
        });

        // Edit Modal Logic
        const editModal = document.getElementById('editModal');

        // Toggle Siswa Fields based on Role
        function toggleSiswaFields(formType) {
            const roleSelect = formType === 'add' ? document.getElementById('addRoleSelect') : document.getElementById('editRole');
            const siswaFields = formType === 'add' ? document.getElementById('addSiswaFields') : document.getElementById('editSiswaFields');

            if (roleSelect.value === 'siswa') {
                siswaFields.style.display = 'block';
            } else {
                siswaFields.style.display = 'none';
            }
        }

        function openEditModal(id, username, fullName, role, subject, assignedClass, nip) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editUsername').value = username;
            document.getElementById('editFullName').value = fullName;
            document.getElementById('editRole').value = role;
            document.getElementById('editSubject').value = subject || '';
            document.getElementById('editAssignedClass').value = assignedClass || '';
            document.getElementById('editNip').value = nip || '';
            document.getElementById('editPassword').value = ''; // Clear password field

            // Toggle siswa fields based on role
            toggleSiswaFields('edit');

            // If siswa, fetch student data (gender and activities)
            if (role === 'siswa') {
                // Clear previous selections
                document.getElementById('editGenderL').checked = false;
                document.getElementById('editGenderP').checked = false;
                document.querySelectorAll('.edit-ekstra').forEach(cb => cb.checked = false);

                // Fetch student data
                fetch('../utils/get_student_data.php?name=' + encodeURIComponent(fullName) + '&class=' + encodeURIComponent(assignedClass))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Set gender
                            if (data.gender === 'Laki-laki') {
                                document.getElementById('editGenderL').checked = true;
                            } else if (data.gender === 'Perempuan') {
                                document.getElementById('editGenderP').checked = true;
                            }

                            // Set ekstrakurikuler
                            if (data.activities && data.activities.length > 0) {
                                data.activities.forEach(activity => {
                                    document.querySelectorAll('.edit-ekstra').forEach(cb => {
                                        if (cb.value === activity) {
                                            cb.checked = true;
                                        }
                                    });
                                });
                            }
                        }
                    })
                    .catch(error => console.error('Error fetching student data:', error));
            }

            editModal.style.display = 'flex';
        }

        function closeEditModal() {
            editModal.style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>

</html>