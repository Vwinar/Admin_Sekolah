<?php
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$successMsg = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$userId = intval($_GET['id']);

// Fetch existing user data from Laporan DB
try {
    $stmt = $laporanPdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: manage_users.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Fetch Classes for Dropdown (from Laporan DB)
$classes = [];
try {
    $stmtClass = $laporanPdo->query("SELECT * FROM classes ORDER BY name");
    $classes = $stmtClass->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Ignore or handle
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $role = $_POST['role'];
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $assigned_class = isset($_POST['assigned_class']) ? $_POST['assigned_class'] : null;

    // Validate required fields
    if (empty($username))
        $errors[] = "Username wajib diisi.";
    if (empty($full_name))
        $errors[] = "Nama lengkap wajib diisi.";
    if (empty($role))
        $errors[] = "Role wajib dipilih.";

    // Check if username already exists (excluding current user)
    if (!empty($username)) {
        $stmt = $laporanPdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $errors[] = "Username sudah digunakan.";
        }
    }

    if (empty($errors)) {
        try {
            $laporanPdo->beginTransaction();

            // Store old data for syncing with students table
            $oldFullName = $user['full_name'];
            $oldClass = $user['assigned_class'];
            $oldRole = $user['role'];

            if (!empty($password)) {
                // Update with new password
                $stmt = $laporanPdo->prepare("UPDATE users SET username = :username, password = :password, full_name = :full_name, role = :role, assigned_class = :class WHERE id = :id");
                $stmt->execute([
                    ':username' => $username,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':full_name' => $full_name,
                    ':role' => $role,
                    ':class' => $assigned_class,
                    ':id' => $userId
                ]);
            } else {
                // Update without changing password
                $stmt = $laporanPdo->prepare("UPDATE users SET username = :username, full_name = :full_name, role = :role, assigned_class = :class WHERE id = :id");
                $stmt->execute([
                    ':username' => $username,
                    ':full_name' => $full_name,
                    ':role' => $role,
                    ':class' => $assigned_class,
                    ':id' => $userId
                ]);
            }

            // Sync with students table if role is siswa
            if ($role == 'siswa') {
                // Check if student record exists
                $stmtCheck = $laporanPdo->prepare("SELECT id FROM students WHERE name = ? AND class_name = ?");
                $stmtCheck->execute([$oldFullName, $oldClass]);
                $existingStudent = $stmtCheck->fetch();

                if ($existingStudent) {
                    // Update existing student record
                    $stmtUpdate = $laporanPdo->prepare("UPDATE students SET name = ?, class_name = ? WHERE id = ?");
                    $stmtUpdate->execute([$full_name, $assigned_class, $existingStudent['id']]);
                } else if ($assigned_class) {
                    // Create new student record
                    $stmtInsert = $laporanPdo->prepare("INSERT INTO students (name, class_name) VALUES (?, ?)");
                    $stmtInsert->execute([$full_name, $assigned_class]);
                }
            } else if ($oldRole == 'siswa' && $role != 'siswa') {
                // If role changed from siswa to something else, optionally delete from students
                // For now, we'll keep the record but you can uncomment below to delete
                // $stmtDel = $laporanPdo->prepare("DELETE FROM students WHERE name = ? AND class_name = ?");
                // $stmtDel->execute([$oldFullName, $oldClass]);
            }

            $laporanPdo->commit();

            $successMsg = "User berhasil diperbarui.";

            // Refresh user data
            $stmt = $laporanPdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            if ($laporanPdo->inTransaction()) {
                $laporanPdo->rollBack();
            }
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title
$pageTitle = "Edit User - Admin";

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

    .card-header {
        background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
        color: white;
        border-radius: 0.5rem 0.5rem 0 0 !important;
        padding: 1rem 1.5rem;
    }

    .card-title {
        margin: 0;
        font-weight: 600;
    }

    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #3f37c9 0%, #4361ee 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(67, 97, 238, 0.4);
    }
</style>

<div class="container py-4">
    <!-- Back Button -->
    <a href="manage_users.php" class="btn btn-outline-primary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-pencil-square me-2"></i>Edit User
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($successMsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="mb-3">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" name="username" id="username" class="form-control" required
                                value="<?php echo htmlspecialchars($user['username']); ?>" />
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                            <input type="text" name="full_name" id="full_name" class="form-control" required
                                value="<?php echo htmlspecialchars($user['full_name']); ?>" />
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <select name="role" id="role" class="form-select" required>
                                <option value="siswa" <?php echo $user['role'] == 'siswa' ? 'selected' : ''; ?>>Siswa</option>
                                <option value="guru" <?php echo $user['role'] == 'guru' ? 'selected' : ''; ?>>Guru</option>
                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="assigned_class" class="form-label">Kelas</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-building"></i></span>
                            <select class="form-select" id="assigned_class" name="assigned_class">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($classes as $cls): ?>
                                    <option value="<?php echo htmlspecialchars($cls['name']); ?>" 
                                        <?php echo ($user['assigned_class'] == $cls['name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cls['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="text-muted">Wajib untuk siswa, opsional untuk guru</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password Baru (Opsional)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Biarkan kosong jika tidak ingin mengubah password" />
                    </div>
                    <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah password.</small>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save-fill me-1"></i> Simpan Perubahan
                    </button>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>