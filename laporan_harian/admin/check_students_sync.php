<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get all students from students table
$studentsQuery = "SELECT s.*, sd.gender, GROUP_CONCAT(sa.activity_name) as activities
                  FROM students s
                  LEFT JOIN student_details sd ON s.id = sd.student_id
                  LEFT JOIN student_activities sa ON s.id = sa.student_id
                  GROUP BY s.id
                  ORDER BY s.class_name, s.name";
$students = $db->query($studentsQuery)->fetchAll();

// Get all users with role siswa
$usersQuery = "SELECT * FROM users WHERE role = 'siswa' ORDER BY assigned_class, full_name";
$usersWithRoleSiswa = $db->query($usersQuery)->fetchAll();

// Find students in students table but NOT in users table
$orphanedStudents = [];
foreach ($students as $student) {
    $found = false;
    foreach ($usersWithRoleSiswa as $user) {
        if ($user['full_name'] == $student['name'] && $user['assigned_class'] == $student['class_name']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $orphanedStudents[] = $student;
    }
}

// Find users with role siswa but NOT in students table
$orphanedUsers = [];
foreach ($usersWithRoleSiswa as $user) {
    $found = false;
    foreach ($students as $student) {
        if ($user['full_name'] == $student['name'] && $user['assigned_class'] == $student['class_name']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $orphanedUsers[] = $user;
    }
}

// Handle delete orphaned student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    $student_id = $_POST['student_id'];
    try {
        $db->beginTransaction();

        // Delete from all related student tables
        $studentTables = [
            'student_attendance',
            'student_notes',
            'student_health',
            'consultations',
            'student_activities',
            'student_details',
            'student_grades',
            'student_mutation'
        ];

        foreach ($studentTables as $table) {
            $stmt = $db->prepare("DELETE FROM {$table} WHERE student_id = ?");
            $stmt->execute([$student_id]);
        }

        // Delete from students table
        $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$student_id]);

        $db->commit();
        header('Location: check_students_sync.php?success=1');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Sinkronisasi Data Siswa</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .stats-card {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .stats-synced {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .stats-orphaned {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .stats-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .comparison-table {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .comparison-table {
                grid-template-columns: 1fr;
            }
        }

        .btn-danger-small {
            background-color: #dc2626;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-danger-small:hover {
            background-color: #b91c1c;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1>Sinkronisasi Data Siswa</h1>
                </div>
            </header>

            <?php if (isset($_GET['success'])): ?>
                <div class="stats-card stats-synced">
                    ✅ Data berhasil dihapus!
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="stats-card stats-orphaned">
                    ❌ Error: <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="stats-card stats-synced">
                <h3>📊 Statistik Data</h3>
                <p>Total data di tabel <strong>students</strong>: <?= count($students) ?></p>
                <p>Total data di tabel <strong>users</strong> dengan role siswa: <?= count($usersWithRoleSiswa) ?></p>
                <p>Data tersinkronisasi: <?= count($students) - count($orphanedStudents) ?></p>
            </div>

            <?php if (count($orphanedStudents) > 0): ?>
                <div class="stats-card stats-orphaned">
                    <h3>⚠️ Data siswa di tabel STUDENTS tapi TIDAK di tabel USERS</h3>
                    <p>Ditemukan <strong><?= count($orphanedStudents) ?></strong> data yang perlu dibersihkan:</p>
                </div>

                <div class="card">
                    <h3 class="mb-2">Data Siswa yang Perlu Dibersihkan</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Gender</th>
                                    <th>Ekstrakurikuler</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanedStudents as $student): ?>
                                    <tr>
                                        <td><?= $student['id'] ?></td>
                                        <td><?= htmlspecialchars($student['name']) ?></td>
                                        <td><?= htmlspecialchars($student['class_name']) ?></td>
                                        <td><?= htmlspecialchars($student['gender'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($student['activities'] ?? '-') ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;"
                                                onsubmit="return confirm('Hapus data siswa ini dan semua data terkait?')">
                                                <input type="hidden" name="action" value="delete_student">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <button type="submit" class="btn-danger-small">🗑️ Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="stats-card stats-synced">
                    <h3>✅ Semua data tersinkronisasi dengan baik!</h3>
                    <p>Tidak ada data siswa di tabel STUDENTS yang tidak ada di tabel USERS.</p>
                </div>
            <?php endif; ?>

            <?php if (count($orphanedUsers) > 0): ?>
                <div class="stats-card stats-warning">
                    <h3>⚠️ Data user dengan role siswa TIDAK di tabel STUDENTS</h3>
                    <p>Ditemukan <strong><?= count($orphanedUsers) ?></strong> user dengan role siswa yang tidak punya data
                        di tabel students.</p>
                    <p><small>Ini bisa terjadi jika data baru ditambahkan. Silakan edit user tersebut di halaman <a
                                href="users.php">Manajemen User</a> untuk sinkronisasi otomatis.</small></p>
                </div>

                <div class="card">
                    <h3 class="mb-2">User dengan Role Siswa tanpa Data Students</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Kelas</th>
                                    <th>Info</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orphanedUsers as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                        <td><?= htmlspecialchars($user['assigned_class'] ?? '-') ?></td>
                                        <td>
                                            <a href="users.php" style="color: #2563eb; text-decoration: underline;">
                                                Edit di Manajemen User
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="comparison-table">
                <div class="card">
                    <h3 class="mb-2">📋 Semua Data di Tabel STUDENTS</h3>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($s['name']) ?></td>
                                        <td><?= htmlspecialchars($s['class_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <h3 class="mb-2">👤 Semua Data di Tabel USERS (Role Siswa)</h3>
                    <div class="table-container" style="max-height: 400px; overflow-y: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithRoleSiswa as $u): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td><?= htmlspecialchars($u['assigned_class'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <a href="users.php" class="btn btn-primary">← Kembali ke Manajemen User</a>
            </div>
        </main>
    </div>

    <script>
        // Sidebar Toggle Functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');

        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        }

        sidebarToggle.addEventListener('click', function () {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');
            const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

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
    </script>
</body>

</html>