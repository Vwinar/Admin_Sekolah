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

// Handle database operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'truncate_summaries':
                $pdo->exec("DELETE FROM summaries");
                $pdo->exec("DELETE FROM sqlite_sequence WHERE name='summaries';");
                $success_message = "Semua data rangkuman berhasil dihapus";
                break;

            case 'truncate_users':
                // Delete all users except admins
                $stmt = $pdo->prepare("DELETE FROM users WHERE role != 'admin'");
                $stmt->execute();
                $success_message = "Semua data user (kecuali admin) berhasil dihapus";
                break;

            case 'clean_uploads':
                // Get list of photos from database for siswa
                $stmt = $pdo->query("SELECT photo FROM users WHERE photo IS NOT NULL");
                $db_photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Get list of photos from database for admin
                $stmt = $pdo->query("SELECT photo FROM users WHERE role = 'admin' AND photo IS NOT NULL");
                $db_admin_photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Get list of pdf_path from books table
                $stmt = $pdo->query("SELECT pdf_path FROM books WHERE pdf_path IS NOT NULL");
                $db_books_files = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Clean siswa/uploads directory
                $siswa_upload_dir = "../siswa/uploads/";
                if (is_dir($siswa_upload_dir)) {
                    $files = scandir($siswa_upload_dir);
                    foreach ($files as $file) {
                        if ($file != "." && $file != "..") {
                            if (!in_array($file, $db_photos)) {
                                unlink($siswa_upload_dir . $file);
                            }
                        }
                    }
                }

                // Clean admin/uploads directory
                $admin_upload_dir = "uploads/";
                if (is_dir($admin_upload_dir)) {
                    $files = scandir($admin_upload_dir);
                    foreach ($files as $file) {
                        if ($file != "." && $file != "..") {
                            // Check if file is referenced in books pdf_path or admin photos
                            $found = false;
                            foreach ($db_books_files as $db_file) {
                                if (basename($db_file) === $file) {
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                foreach ($db_admin_photos as $admin_photo) {
                                    if ($admin_photo === $file) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found) {
                                unlink($admin_upload_dir . $file);
                            }
                        }
                    }
                }

                $success_message = "File foto dan buku yang tidak terpakai berhasil dibersihkan";
                break;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get database statistics
try {
    // Count total summaries
    $stmt = $pdo->query("SELECT COUNT(*) FROM summaries");
    $total_summaries = $stmt->fetchColumn();

    // Count total books
    $stmt = $pdo->query("SELECT COUNT(*) FROM books");
    $total_books = $stmt->fetchColumn();

    // Count total students
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'");
    $total_students = $stmt->fetchColumn();

    // Count total admins
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $total_admins = $stmt->fetchColumn();

    // Get upload directory size from both siswa/uploads and admin/uploads
    $upload_size = 0;

    // siswa/uploads directory
    $siswa_upload_dir = "../siswa/uploads/";
    if (is_dir($siswa_upload_dir)) {
        $files = scandir($siswa_upload_dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $upload_size += filesize($siswa_upload_dir . $file);
            }
        }
    }

    // admin/uploads directory
    $admin_upload_dir = "uploads/";
    if (is_dir($admin_upload_dir)) {
        $files = scandir($admin_upload_dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $upload_size += filesize($admin_upload_dir . $file);
            }
        }
    }

    $upload_size = round($upload_size / 1024 / 1024, 2); // Convert to MB

    // Get books directory size (volume)
    $books_size = 0;
    $books_dir = "../uploads/books/";
    if (is_dir($books_dir)) {
        $files = scandir($books_dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $books_size += filesize($books_dir . $file);
            }
        }
    }
    $books_size = round($books_size / 1024 / 1024, 2); // Convert to MB

} catch (Exception $e) {
    $error_message = "Error fetching statistics: " . $e->getMessage();
}

// Set page title
$pageTitle = "Manajemen Database - Admin Dashboard";

// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        padding: 1rem 1.5rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    .stats-card {
        border-left: 4px solid var(--primary-color);
    }

    .stats-card .icon {
        font-size: 2rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    .stats-card h3 {
        font-weight: 700;
        color: var(--dark-color);
    }

    .stats-card p {
        color: #718096;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    .btn-danger {
        background-color: var(--danger-color);
        border-color: var(--danger-color);
    }

    .btn-danger:hover {
        background-color: #d1146a;
        border-color: #d1146a;
    }

    .btn-warning {
        background-color: var(--warning-color);
        border-color: var(--warning-color);
        color: white;
    }

    .btn-warning:hover {
        background-color: #e07e0c;
        border-color: #e07e0c;
        color: white;
    }

    .alert {
        border-radius: 0.5rem;
    }

    .modal-content {
        border: none;
        border-radius: 0.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .modal-footer {
        border-top: 1px solid rgba(0, 0, 0, 0.05);
    }

    .action-card {
        height: 100%;
        border-left: 4px solid;
    }

    .action-card.summaries {
        border-left-color: var(--danger-color);
    }

    .action-card.users {
        border-left-color: var(--info-color);
    }

    .action-card.uploads {
        border-left-color: var(--warning-color);
    }

    .action-card .icon {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .action-card.summaries .icon {
        color: var(--danger-color);
    }

    .action-card.users .icon {
        color: var(--info-color);
    }

    .action-card.uploads .icon {
        color: var(--warning-color);
    }

    @media (max-width: 768px) {
        .card-body {
            padding: 1rem;
        }

        .stats-card .icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stats-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .stats-card p {
            font-size: 0.8rem;
        }

        /* Compact grid for stats on mobile */
        .col-md-6.col-lg-3 {
            width: 50%;
            /* Manual override to ensure 2 per row if bootstrap classes fail or to force it */
            padding-right: 0.5rem;
            padding-left: 0.5rem;
        }

        .row.g-4 {
            --bs-gutter-x: 1rem;
            --bs-gutter-y: 1rem;
        }
    }
</style>

<div class="container py-4">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Database Statistics -->
    <div class="row mb-4 g-4">
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo number_format($total_summaries); ?></h3>
                    <p class="text-muted mb-0">Total Rangkuman</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo number_format($total_books); ?></h3>
                    <p class="text-muted mb-0">Total Buku</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo number_format($total_students); ?></h3>
                    <p class="text-muted mb-0">Total Siswa</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo number_format($total_admins); ?></h3>
                    <p class="text-muted mb-0">Total Admin</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo $upload_size; ?> MB</h3>
                    <p class="text-muted mb-0">Ukuran Upload</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card stats-card h-100">
                <div class="card-body text-center">
                    <div class="icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3 class="display-5 fw-bold"><?php echo $books_size; ?> MB</h3>
                    <p class="text-muted mb-0">Volume Buku</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Management -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-tasks me-2"></i>Manajemen Database
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="card action-card summaries h-100">
                        <div class="card-body">
                            <div class="icon">
                                <i class="fas fa-trash-alt"></i>
                            </div>
                            <h5 class="card-title">Hapus Semua Rangkuman</h5>
                            <p class="text-muted">Menghapus seluruh data rangkuman dari database.</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#truncateSummariesModal">
                                <i class="fas fa-trash me-1"></i>Hapus Semua
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card action-card users h-100">
                        <div class="card-body">
                            <div class="icon">
                                <i class="fas fa-user-slash"></i>
                            </div>
                            <h5 class="card-title">Hapus Semua User</h5>
                            <p class="text-muted">Menghapus seluruh data user kecuali admin.</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#truncateUsersModal">
                                <i class="fas fa-trash me-1"></i>Hapus Semua
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-4">
                    <div class="card action-card uploads h-100">
                        <div class="card-body">
                            <div class="icon">
                                <i class="fas fa-broom"></i>
                            </div>
                            <h5 class="card-title">Bersihkan Upload</h5>
                            <p class="text-muted">Menghapus file foto yang tidak terpakai.</p>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal"
                                data-bs-target="#cleanUploadsModal">
                                <i class="fas fa-broom me-1"></i>Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Clean Uploads Modal -->
<div class="modal fade" id="cleanUploadsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                    Konfirmasi Bersihkan Upload
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Peringatan!</strong> Tindakan ini akan menghapus file foto yang tidak terpakai dan tidak
                    dapat dibatalkan.
                </div>
                <p>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="clean_uploads">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-broom me-1"></i>Ya, Bersihkan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Truncate Summaries Modal -->
<div class="modal fade" id="truncateSummariesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Konfirmasi Hapus Semua Rangkuman
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Peringatan!</strong> Tindakan ini akan menghapus seluruh data rangkuman dan tidak dapat
                    dibatalkan.
                </div>
                <p>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="truncate_summaries">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Ya, Hapus Semua
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Truncate Users Modal -->
<div class="modal fade" id="truncateUsersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                    Konfirmasi Hapus Semua User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Peringatan!</strong> Tindakan ini akan menghapus seluruh data user (kecuali admin) dan tidak
                    dapat dibatalkan.
                </div>
                <p>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="modal-footer">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="truncate_users">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Ya, Hapus Semua
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
</script>
<?php require_once 'footer.php'; ?>