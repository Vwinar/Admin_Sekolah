<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $password = $_POST['password'];
    $uploadDir = __DIR__ . '/../uploads/profile/';

    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    try {
        $photoUpdate = "";
        $params = [$full_name];

        // Handle profile photo upload
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                throw new Exception("Foto profil harus JPG, PNG, atau WEBP");
            }

            // Check file size (max 2MB)
            if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
                throw new Exception("Ukuran foto maksimal 2MB");
            }

            // Delete old photo if exists
            if (!empty($user['profile_photo']) && file_exists($uploadDir . $user['profile_photo'])) {
                unlink($uploadDir . $user['profile_photo']);
            }

            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $filename);

            $photoUpdate = ", profile_photo = ?";
            $params[] = $filename;
        }

        // Handle password update
        if ($password) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $params[] = $hashed;
            $passwordUpdate = ", password = ?";
        } else {
            $passwordUpdate = "";
        }

        $params[] = $_SESSION['user_id'];

        $sql = "UPDATE users SET full_name = ? $photoUpdate $passwordUpdate WHERE id = ?";
        $update = $db->prepare($sql);
        $res = $update->execute($params);

        if ($res) {
            $success = "Profil berhasil diperbarui.";
            $_SESSION['full_name'] = $full_name;
            // Refresh data
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        } else {
            $error = "Gagal memperbarui profil.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="dashboard-layout">
        <?php if ($_SESSION['role'] === 'guru'): ?>
            <?php include '../layout/user_sidebar.php'; ?>
        <?php else: ?>
            <aside class="sidebar" id="sidebar">
                <a href="#" class="sidebar-brand">LaporanApp</a>
                <div class="sidebar-profile">
                    <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile/' . $user['profile_photo'])): ?>
                        <img src="../uploads/profile/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile"
                            class="profile-photo">
                    <?php else: ?>
                        <div class="profile-photo-placeholder">
                            <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="profile-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
                    <div
                        style="margin-top: 0.5rem; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #bae6fd;">
                        <?= ucfirst($user['role']) ?>
                    </div>
                </div>

                <nav>
                    <a href="dashboard_admin.php" class="nav-link">Dashboard</a>
                    <a href="monitoring.php" class="nav-link">Monitoring Laporan</a>
                    <a href="users.php" class="nav-link">Manajemen User</a>
                    <a href="profil.php" class="nav-link active">Profil Saya</a>
                    <a href="../logout.php" class="nav-link" style="color: var(--danger);">Keluar</a>
                </nav>
            </aside>
        <?php endif; ?>
        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1>Profil Saya</h1>
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

            <div class="card" style="max-width: 600px;">
                <h3 class="mb-2">Foto Profil</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group" style="text-align: center;">
                        <div class="profile-photo-preview">
                            <?php if (!empty($user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile/' . $user['profile_photo'])): ?>
                                <img src="../uploads/profile/<?= htmlspecialchars($user['profile_photo']) ?>" alt="Profile"
                                    id="photoPreview" class="preview-image">
                            <?php else: ?>
                                <div class="preview-placeholder" id="photoPreview">
                                    <?= strtoupper(substr($_SESSION['full_name'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label class="form-label" style="margin-top: 1rem;">Upload Foto Profil</label>
                        <input type="file" name="profile_photo" class="form-control" accept="image/*"
                            onchange="previewPhoto(this)">
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Format: JPG, PNG,
                            WEBP (Max 2MB)</small>
                    </div>

                    <h3 class="mb-2 mt-2">Informasi Akun</h3>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" class="form-control"
                            disabled style="background: #f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>"
                            class="form-control" required>
                    </div>
                    <?php if ($_SESSION['role'] === 'guru'): ?>
                        <div class="form-group">
                            <label class="form-label">Mata Pelajaran</label>
                            <input type="text" value="<?= htmlspecialchars($user['subject'] ?? '-') ?>" class="form-control"
                                disabled style="background: #f1f5f9;">
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">Password Baru (Biarkan kosong jika tidak ingin mengubah)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <button type="submit" class="chip-btn chip-btn-blue" style="width: auto;">Simpan Perubahan</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Photo Preview Function
        function previewPhoto(input) {
            const preview = document.getElementById('photoPreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="preview-image">';
                };
                reader.readAsDataURL(input.files[0]);
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
    </script>
</body>

</html>
