<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create') {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        $full_name = sanitizeInput($_POST['full_name']);
        $role = 'siswa'; // Only allow creating students here
        $dbRole = 'siswa';

        $assigned_class = isset($_POST['class']) ? sanitizeInput($_POST['class']) : null;

        try {
            // Check if username already exists in Laporan DB
            $stmt = $laporanPdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception("Username sudah digunakan");
            }

            // Start Transaction to ensure User and Student Data stay synced
            $laporanPdo->beginTransaction();

            // 1. Create new user in Laporan DB (For Login & Linum)
            $stmt = $laporanPdo->prepare("INSERT INTO users (username, password, full_name, role, assigned_class) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $full_name, $dbRole, $assigned_class]);

            // 2. Insert into students table (For Laporan Harian Attendance/Class functionality)
            // Laporan Harian uses 'students' table for class administration.
            if ($dbRole == 'siswa' && $assigned_class) {
                // Check if student already exists in students table to avoid duplicates?
                // Usually Laporan Harian allows multiple students with same name? Let's Assume safe to add.
                $stmtStudent = $laporanPdo->prepare("INSERT INTO students (name, class_name) VALUES (?, ?)");
                $stmtStudent->execute([$full_name, $assigned_class]);
            }

            $laporanPdo->commit();

            $success_message = "User berhasil ditambahkan dan disinkronkan dengan Data Siswa";
        } catch (Exception $e) {
            if ($laporanPdo->inTransaction()) {
                $laporanPdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
    // Handle user deletion (with comprehensive cascade delete)
    elseif ($_POST['action'] == 'delete' && isset($_POST['user_id'])) {
        try {
            if ($_POST['user_id'] == $_SESSION['user_id']) {
                throw new Exception("Tidak dapat menghapus diri sendiri.");
            }

            // Start transactions for both databases
            $laporanPdo->beginTransaction();
            $pdo->beginTransaction();

            // Fetch User Details before deletion
            $stmtUser = $laporanPdo->prepare("SELECT full_name, role, assigned_class FROM users WHERE id = ?");
            $stmtUser->execute([$_POST['user_id']]);
            $userToDelete = $stmtUser->fetch();

            $deletedInfo = [];

            // === DELETE FROM LAPORAN DATABASE ===

            // 1. Delete reports
            $stmt1 = $laporanPdo->prepare("DELETE FROM reports WHERE user_id = ?");
            $stmt1->execute([$_POST['user_id']]);
            $deletedReports = $stmt1->rowCount();
            if ($deletedReports > 0)
                $deletedInfo[] = "$deletedReports laporan";

            // 2. Delete attendance records
            $stmt2 = $laporanPdo->prepare("DELETE FROM attendance WHERE user_id = ?");
            $stmt2->execute([$_POST['user_id']]);
            $deletedAttendance = $stmt2->rowCount();
            if ($deletedAttendance > 0)
                $deletedInfo[] = "$deletedAttendance absensi";

            // 3. Delete izin/permission records
            $stmt3 = $laporanPdo->prepare("DELETE FROM izin WHERE user_id = ?");
            $stmt3->execute([$_POST['user_id']]);
            $deletedIzin = $stmt3->rowCount();
            if ($deletedIzin > 0)
                $deletedInfo[] = "$deletedIzin izin";

            // 4. Delete student attendance recorded by this teacher
            $stmt4 = $laporanPdo->prepare("DELETE FROM student_attendance WHERE teacher_id = ?");
            $stmt4->execute([$_POST['user_id']]);
            $deletedStudentAttendance = $stmt4->rowCount();
            if ($deletedStudentAttendance > 0)
                $deletedInfo[] = "$deletedStudentAttendance absensi siswa";

            // 5. Delete from students table if applicable
            if ($userToDelete && $userToDelete['role'] == 'siswa') {
                $stmtDelStudent = $laporanPdo->prepare("DELETE FROM students WHERE name = ? AND class_name = ?");
                $stmtDelStudent->execute([$userToDelete['full_name'], $userToDelete['assigned_class']]);
                $deletedStudents = $stmtDelStudent->rowCount();
                if ($deletedStudents > 0)
                    $deletedInfo[] = "$deletedStudents data siswa";
            }

            // === DELETE FROM LINUM DATABASE ===

            // 6. Delete literasi entries
            try {
                $stmt5 = $pdo->prepare("DELETE FROM literasi WHERE user_id = ?");
                $stmt5->execute([$_POST['user_id']]);
                $deletedLiterasi = $stmt5->rowCount();
                if ($deletedLiterasi > 0)
                    $deletedInfo[] = "$deletedLiterasi literasi";
            } catch (Exception $e) {
                // Table might not exist, ignore
            }

            // 7. Delete summaries
            try {
                $stmt6 = $pdo->prepare("DELETE FROM summaries WHERE user_id = ?");
                $stmt6->execute([$_POST['user_id']]);
                $deletedSummaries = $stmt6->rowCount();
                if ($deletedSummaries > 0)
                    $deletedInfo[] = "$deletedSummaries rangkuman";
            } catch (Exception $e) {
                // Table might not exist, ignore
            }

            // === DELETE FROM JOURNAL DATABASE ===
            try {
                $journalDbFile = __DIR__ . '/../../Journal/journal.db';
                if (file_exists($journalDbFile)) {
                    $journalPdo = new PDO('sqlite:' . $journalDbFile);
                    $journalPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $journalPdo->beginTransaction();

                    // Delete journal entries
                    $stmtJ1 = $journalPdo->prepare("DELETE FROM journal_entries WHERE user_id = ?");
                    $stmtJ1->execute([$_POST['user_id']]);
                    $deletedJEntries = $stmtJ1->rowCount();
                    if ($deletedJEntries > 0)
                        $deletedInfo[] = "$deletedJEntries jurnal";

                    // Delete templates
                    $stmtJ2 = $journalPdo->prepare("DELETE FROM templates WHERE user_id = ?");
                    $stmtJ2->execute([$_POST['user_id']]);
                    $deletedTemplates = $stmtJ2->rowCount();
                    if ($deletedTemplates > 0)
                        $deletedInfo[] = "$deletedTemplates template";

                    // Delete schedules
                    $stmtJ3 = $journalPdo->prepare("DELETE FROM schedules WHERE user_id = ?");
                    $stmtJ3->execute([$_POST['user_id']]);
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

            // 8. Finally, delete the user from laporan database
            $stmt = $laporanPdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);

            // Commit both transactions
            $laporanPdo->commit();
            $pdo->commit();

            // Build success message
            $success_message = "User " . htmlspecialchars($userToDelete['full_name']) . " berhasil dihapus.";
            if (!empty($deletedInfo)) {
                $success_message .= " Terhapus juga: " . implode(", ", $deletedInfo) . ".";
            }

        } catch (Exception $e) {
            // Rollback all transactions on error
            if ($laporanPdo->inTransaction()) {
                $laporanPdo->rollBack();
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($journalPdo) && $journalPdo->inTransaction()) {
                $journalPdo->rollBack();
            }
            $error_message = "Gagal menghapus user: " . $e->getMessage();
        }
    }
}

// Fetch Classes for Dropdown (from Laporan DB)
$classes = [];
try {
    $stmtClass = $laporanPdo->query("SELECT * FROM classes ORDER BY name");
    $classes = $stmtClass->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore or handle
}

// Fetch all users from Laporan DB (Mapped for Linum Display)
$users = [];
try {
    // Get current user's class if they are a guru
    $currentUserClass = null;
    if ($_SESSION['role'] == 'guru') {
        $stmtCurrentUser = $laporanPdo->prepare("SELECT assigned_class FROM users WHERE id = ?");
        $stmtCurrentUser->execute([$_SESSION['user_id']]);
        $currentUser = $stmtCurrentUser->fetch();
        $currentUserClass = $currentUser['assigned_class'] ?? null;
    }

    // Select users relevant to Linum (Siswa & Guru)
    // If guru, filter to show only users from their class
    if ($_SESSION['role'] == 'guru' && $currentUserClass) {
        // Guru: Only show siswa from their class
        $stmt = $laporanPdo->prepare("SELECT id, username, full_name, role, assigned_class as class, profile_photo as photo, '2025-01-01' as created_at FROM users WHERE role = 'siswa' AND assigned_class = ? ORDER BY full_name");
        $stmt->execute([$currentUserClass]);
    } else {
        // Admin: Show all users
        $stmt = $laporanPdo->prepare("SELECT id, username, full_name, role, assigned_class as class, profile_photo as photo, '2025-01-01' as created_at FROM users WHERE role IN ('guru', 'siswa') ORDER BY role, full_name");
        $stmt->execute();
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map 'guru' role back to 'admin' for Linum display consistency
    foreach ($users as &$u) {
        if ($u['role'] == 'guru')
            $u['role'] = 'admin';
    }
} catch (PDOException $e) {
    $error_message = "Database Error: " . $e->getMessage();
}

// Set page title
$pageTitle = "Manajemen User - Admin Dashboard";

// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 1.5rem;
    }

    /* Profile image styling - small thumbnail for table */
    .profile-image {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e9ecef;
        transition: transform 0.2s ease;
    }

    .profile-image:hover {
        transform: scale(1.1);
        border-color: #4361ee;
        box-shadow: 0 2px 8px rgba(67, 97, 238, 0.3);
    }

    /* Badge styling */
    .badge-admin {
        background-color: #4361ee;
        color: white;
    }

    .badge-siswa {
        background-color: #4cc9f0;
        color: white;
    }

    /* Table styling */
    .table>tbody>tr>td {
        vertical-align: middle;
    }
</style>

<div class="container py-4">
    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Add User Card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <i class="bi bi-person-plus-fill me-2"></i>
            <h5 class="card-title mb-0">Tambah User Baru (Laporan Harian DB)</h5>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="full_name" class="form-label">Nama Lengkap</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" value="Siswa" readonly>
                            <input type="hidden" name="role" value="siswa">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="class" class="form-label">Kelas (Khusus Siswa)</label>
                        <select class="form-select" id="class" name="class">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($classes as $cls): ?>
                                <option value="<?php echo htmlspecialchars($cls['name']); ?>">
                                    <?php echo htmlspecialchars($cls['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Tambah User
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-people-fill me-2"></i>
                <h5 class="card-title mb-0 d-inline">Daftar User</h5>
            </div>
            <span class="badge bg-primary rounded-pill"><?php echo count($users); ?> user</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="bi bi-image"></i></th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Kelas</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <?php
                                    // Determine profile photo source
                                    $userPhoto = $user['photo'] ?? '';
                                    $userName = $user['full_name'] ?? 'User';

                                    // Path for checking file existence (absolute)
                                    $absPath = __DIR__ . '/../../laporan_harian/uploads/profile/' . $userPhoto;

                                    // Path for HTML src (relative to this file)
                                    $webPath = '../../laporan_harian/uploads/profile/' . $userPhoto;

                                    if ($userPhoto && file_exists($absPath)) {
                                        $imgSrc = $webPath;
                                    } else {
                                        $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=' . substr(md5($userName), 0, 6) . '&color=fff';
                                    }
                                    ?>
                                    <img src="<?php echo $imgSrc; ?>" class="profile-image"
                                        alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span
                                        class="badge <?php echo $user['role'] == 'admin' ? 'badge-admin' : 'badge-siswa'; ?> rounded-pill">
                                        <i
                                            class="bi <?php echo $user['role'] == 'admin' ? 'bi-shield-fill' : 'bi-person-fill'; ?> me-1"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['class'] ?? '-'); ?></td>
                                <td>
                                    <i class="bi bi-calendar3 me-1"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>"
                                        class="btn btn-sm btn-outline-primary me-1">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <?php if ($user['role'] != 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal"
                                            data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modals -->
<?php foreach ($users as $user): ?>
    <?php if ($user['role'] != 'admin'): ?>
        <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1"
            aria-labelledby="deleteModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel<?php echo $user['id']; ?>">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                            Konfirmasi Hapus
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus user
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>?
                        </p>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>⚠️ Peringatan: Semua data terkait akan dihapus!</strong>
                            <ul class="mt-2 mb-0" style="font-size: 0.875rem;">
                                <li>Semua laporan harian</li>
                                <li>Semua data absensi</li>
                                <li>Semua data izin/sakit</li>
                                <li>Rekaman absensi siswa yang di-input</li>
                                <li>Semua data literasi & numerasi</li>
                                <li>Semua rangkuman harian</li>
                                <li>Semua jurnal pembelajaran</li>
                                <li>Semua template & jadwal</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1"></i> Batal
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash-fill me-1"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
    // Enable tooltips
    document.addEventListener('DOMContentLoaded', function () {
        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Add animation to cards when they appear in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.card').forEach(card => {
            observer.observe(card);
        });
    });
</script>
<?php require_once 'footer.php'; ?>