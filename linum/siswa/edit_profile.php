<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists
ensureSession();


// Since Linum uses Laporan Harian for Auth, we MUST update Laporan Harian DB/Files.
// $laporanPdo is available from config.php if setup correctly, else we rely on $pdo if incorrect.
// Previous steps established $laporanPdo in config.php.

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);

    try {
        // Start transaction on Laporan DB
        if (!isset($laporanPdo))
            throw new Exception("Koneksi ke Database Laporan tidak ditemukan.");
        $laporanPdo->beginTransaction();

        // Update name
        $stmt = $laporanPdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $_SESSION['user_id']]);

        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $file = $_FILES['photo'];
            $fileName = $file['name'];
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];

            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = array('jpg', 'jpeg', 'png');

            if (in_array($fileExt, $allowed)) {
                if ($fileError === 0) {
                    if ($fileSize < 5000000) { // 5MB
                        $newFileName = uniqid('', true) . "." . $fileExt;
                        // Target Dir: Laporan Harian Uploads
                        // Linum is c:/xampp/htdocs/linum/siswa/
                        // Laporan Uploads is c:/xampp/htdocs/laporan_harian/uploads/profile/
                        $uploadDir = __DIR__ . '/../../laporan_harian/uploads/profile/';

                        // Ensure dir exists
                        if (!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        if (move_uploaded_file($fileTmp, $uploadDir . $newFileName)) {
                            // Update photo in database
                            $stmt = $laporanPdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                            $stmt->execute([$newFileName, $_SESSION['user_id']]);
                            $_SESSION['photo'] = $newFileName;
                        } else {
                            throw new Exception("Gagal mengupload file.");
                        }
                    } else {
                        throw new Exception("Ukuran file terlalu besar.");
                    }
                } else {
                    throw new Exception("Error saat upload file.");
                }
            } else {
                throw new Exception("Tipe file tidak diizinkan.");
            }
        }

        // Commit
        $laporanPdo->commit();

        // Update session
        $_SESSION['full_name'] = $full_name;

        $success_message = 'Profil berhasil diperbarui';
        // Redirect
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        if (isset($laporanPdo) && $laporanPdo->inTransaction()) {
            $laporanPdo->rollBack();
        }
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get current user data from Laporan DB
if (isset($laporanPdo)) {
    $stmt = $laporanPdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} else {
    $user = false;
    $error_message = "Koneksi database Laporan gagal.";
}

// Fallback user data to prevent warnings
if (!$user) {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        // Only redirect if not posting (prevents redirect loop on error)
        // Or handle logout if user really lost.
        // For now, providing dummy to prevent crash.
    }
    $user = [
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'profile_photo' => $_SESSION['photo'] ?? ''
    ];
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - Sistem Laporan Resume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --danger-color: #f44336;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
        }

        .navbar {
            background-color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 0.8rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }

        .navbar-brand i {
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: white;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0 !important;
        }

        .card-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e9ecef;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
            border: 5px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .profile-image:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .profile-image-preview {
            max-width: 150px;
            max-height: 150px;
            display: none;
            margin-top: 1rem;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }

        .file-upload-btn {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            width: 100%;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-btn:hover {
            border-color: var(--primary-color);
            background-color: rgba(67, 97, 238, 0.05);
        }

        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .alert {
            border-radius: 8px;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .navbar-brand span {
                display: none;
            }

            .navbar-brand i {
                margin-right: 0;
                font-size: 1.8rem;
            }

            .card-body {
                padding: 1rem;
            }

            .profile-image {
                width: 120px;
                height: 120px;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Sistem Laporan Resume</span>
            </a>
            <a href="dashboard.php" class="btn btn-outline-primary d-flex align-items-center">
                <i class="bi bi-arrow-left-short me-1"></i>
                <span class="d-none d-sm-inline">Kembali ke Dashboard</span>
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-person-gear"></i> Edit Profil</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <?php
                            // Use 'profile_photo' field from Laporan DB
                            $userPhoto = $user['profile_photo'] ?? '';
                            $userName = $user['full_name'] ?? 'User';

                            // Path for checking file existence (absolute)
                            $absPath = __DIR__ . '/../../laporan_harian/uploads/profile/' . $userPhoto;

                            // Path for HTML src (relative to this file)
                            $webPath = '../../laporan_harian/uploads/profile/' . $userPhoto;

                            if ($userPhoto && file_exists($absPath)) {
                                $imgSrc = $webPath;
                            } else {
                                $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=' . substr(md5($userName), 0, 6);
                            }
                            ?>
                            <img src="<?php echo $imgSrc; ?>" class="profile-image" alt="Profile Picture"
                                id="currentPhoto">
                            <img id="photoPreview" class="profile-image-preview" alt="Preview">
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="full_name" class="form-label">Nama Lengkap</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                        value="<?php echo htmlspecialchars($userName); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Foto Profil</label>
                                <div class="file-upload">
                                    <label class="file-upload-btn">
                                        <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                                        <div class="mt-2">Klik untuk mengunggah foto</div>
                                        <small class="text-muted d-block">Format JPG, JPEG, atau PNG (maksimal
                                            5MB)</small>
                                        <input type="file" class="file-upload-input" id="photo" name="photo"
                                            accept="image/jpeg,image/png,image/jpg">
                                    </label>
                                </div>
                                <div class="text-center mt-2">
                                    <small class="text-muted" id="fileName"></small>
                                </div>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary py-2">
                                    <i class="bi bi-save-fill me-2"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview uploaded image
        document.getElementById('photo').addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                // Show file name
                document.getElementById('fileName').textContent = file.name;

                // Preview image
                const reader = new FileReader();
                const preview = document.getElementById('photoPreview');
                const currentPhoto = document.getElementById('currentPhoto');

                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    currentPhoto.style.display = 'none';
                }

                reader.readAsDataURL(file);
            } else {
                document.getElementById('fileName').textContent = '';
            }
        });

        // Add animation to card on load
        document.addEventListener('DOMContentLoaded', function () {
            const card = document.querySelector('.card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>

</html>