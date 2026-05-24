<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get student ID from URL
$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$student_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch student details
$stmt = $laporanPdo->prepare("SELECT id, full_name, username, profile_photo as photo, assigned_class as class FROM users WHERE id = ? AND role = 'siswa'");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: dashboard.php");
    exit();
}

// Access restrict: If guru, student must be in same class
if ($_SESSION['role'] === 'guru') {
    $guruClass = getGuruClass($_SESSION['user_id']);
    // If student has no class or different class, deny access
    $studentClass = isset($student['class']) ? $student['class'] : null;

    if (!$guruClass || $studentClass !== $guruClass) {
        // Redirect to dashboard with error? or just dashboard.
        header("Location: dashboard.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_summary' && isset($_POST['summary_id'])) {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
        header("Location: ../login.php");
        exit();
    }
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "Token CSRF tidak valid.";
    } else {
        $delete_summary_id = filter_var($_POST['summary_id'], FILTER_VALIDATE_INT);
        if ($delete_summary_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM summaries WHERE id = ?");
                $stmt->execute([$delete_summary_id]);
                header("Location: view_student.php?id=" . $student_id . "&message=Summary+deleted+successfully");
                exit();
            } catch (Exception $e) {
                $error_message = "Gagal menghapus rangkuman.";
            }
        } else {
            $error_message = "ID rangkuman tidak valid.";
        }
    }
}

// Fetch student's summaries
$stmt = $pdo->prepare("
    SELECT s.*, sub.name as subject_name 
    FROM summaries s 
    JOIN subjects sub ON s.subject_id = sub.id 
    WHERE s.user_id = ? 
    ORDER BY s.created_at DESC
");
$stmt->execute([$student_id]);
$summaries = $stmt->fetchAll();

// Get summary statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_summaries,
        COUNT(DISTINCT subject_id) as unique_subjects,
        MAX(created_at) as last_submission
    FROM summaries 
    WHERE user_id = ?
");
$stmt->execute([$student_id]);
$stats = $stmt->fetch();

// Set page title
$pageTitle = "Detail Siswa - " . htmlspecialchars($student['full_name']);

// Set header action button
$headerAction = '
    <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-arrow-left me-md-1"></i> <span class="d-none d-md-inline">Kembali</span>
    </a>
';

// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin-bottom: 1.5rem;
        background-color: white;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }


    .stats-card {
        text-align: center;
        padding: 1.5rem;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }

    .stats-card .icon {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--primary-color);
    }

    .stats-card .count {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--primary-color);
    }

    .stats-card .label {
        font-size: 0.9rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 500;
    }

    /* Mobile specific adjustments */
    @media (max-width: 768px) {
        .stats-card {
            padding: 0.5rem;
            margin-bottom: 0;
        }

        .stats-card .icon {
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
        }

        .stats-card .count {
            font-size: 1.1rem !important;
            margin-bottom: 0.1rem;
        }

        .stats-card .label {
            font-size: 0.65rem;
            letter-spacing: 0;
            line-height: 1.1;
        }

        .profile-card-body {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            text-align: left !important;
            padding: 0.75rem !important;
        }

        .profile-image {
            width: 60px !important;
            height: 60px !important;
            margin-bottom: 0 !important;
            margin-right: 0.75rem !important;
            border-width: 2px !important;
        }

        .profile-info {
            flex: 1;
            min-width: 0;
            /* Ensures truncation works if needed */
        }

        .profile-info h3 {
            font-size: 1rem;
            /* Smaller name */
            margin-bottom: 0.1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-badges {
            justify-content: flex-start !important;
            margin-bottom: 0.25rem !important;
            transform: scale(0.9);
            /* Slightly smaller badges */
            transform-origin: left;
        }

        .profile-meta {
            justify-content: flex-start !important;
            font-size: 0.8rem;
        }
    }

    /* Desktop defaults */
    .profile-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
    }
</style>

<!-- Back Button Removed (Moved to Header) -->

<div class="container py-2">
    <!-- Error Message -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if (isset($_GET['message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_GET['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Student Profile -->
    <div class="card mb-4">
        <div class="card-body text-center p-4 profile-card-body">
            <?php
            $photoFilename = isset($student['photo']) ? trim($student['photo']) : '';
            $photoPath = $photoFilename ? '../../laporan_harian/uploads/profile/' . $photoFilename : '';
            $absolutePath = $photoFilename ? __DIR__ . '/../../laporan_harian/uploads/profile/' . $photoFilename : '';
            if ($photoFilename && file_exists($absolutePath)) {
                $imgSrc = $photoPath;
            } else {
                $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=' . substr(md5($student['full_name']), 0, 6) . '&color=fff&size=256';
            }
            ?>
            <img src="<?php echo $imgSrc; ?>" class="profile-image mb-3 border border-3 border-primary"
                alt="<?php echo htmlspecialchars($student['full_name']); ?>">

            <div class="profile-info">
                <h3 class="mb-2 fw-bold"><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <div class="d-flex justify-content-center align-items-center mb-3 profile-badges">
                    <span class="badge bg-primary rounded-pill">
                        <i class="fas fa-user-graduate me-1"></i> Siswa
                    </span>
                </div>
                <div class="d-flex justify-content-center gap-2 profile-meta">
                    <span class="text-muted">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($student['username'] ?? ''); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4 g-2">
        <div class="col-4">
            <div class="card stats-card h-100">
                <div class="card-body p-0 p-md-3">
                    <div class="icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="count">
                        <?php echo $stats['total_summaries']; ?>
                    </div>
                    <div class="label">
                        Rangkuman
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stats-card h-100">
                <div class="card-body p-0 p-md-3">
                    <div class="icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="count">
                        <?php echo $stats['unique_subjects']; ?>
                    </div>
                    <div class="label">
                        Mapel
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stats-card h-100">
                <div class="card-body p-0 p-md-3">
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="count">
                        <?php
                        // Only show date part on mobile to save space, maybe just day/month
                        // Actually just using the smaller font size handled in CSS
                        if ($stats['last_submission']) {
                            // Logic to show simpler date on mobile could be nice but CSS font-size reduction is main key
                            echo date('d/m', strtotime($stats['last_submission']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                    <div class="label">
                        Terakhir
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summaries List -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">
                <i class="fas fa-list-check me-2"></i> Daftar Rangkuman
            </h5>
            <span class="badge bg-primary rounded-pill">
                <?php echo count($summaries); ?> Entri
            </span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($summaries)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum ada rangkuman yang dibuat</h5>
                    <p class="text-muted">Siswa ini belum membuat rangkuman apapun.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="50">No</th>
                                <th>Mata Pelajaran</th>
                                <th>Materi</th>
                                <th>Isi Rangkuman</th>
                                <th>Tanggal Dibuat</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaries as $index => $summary): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-book me-1"></i>
                                            <?php echo htmlspecialchars($summary['subject_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($summary['materi'] ?? '-'); ?></td>
                                    <td>
                                        <div class="summary-preview" role="button" tabindex="0"
                                            data-subject-name="<?php echo htmlspecialchars($summary['subject_name']); ?>"
                                            data-materi="<?php echo htmlspecialchars($summary['materi'] ?? ''); ?>"
                                            data-created-at="<?php echo date('d/m/Y H:i', strtotime($summary['created_at'])); ?>"
                                            data-updated-at="<?php echo date('d/m/Y H:i', strtotime($summary['updated_at'])); ?>"
                                            data-content="<?php echo htmlspecialchars($summary['content']); ?>">
                                            <?php echo substr(strip_tags($summary['content']), 0, 100) . '...'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted" data-bs-toggle="tooltip" data-bs-placement="top"
                                            title="<?php echo date('d M Y H:i', strtotime($summary['created_at'])); ?>">
                                            <?php echo date('d/m/Y', strtotime($summary['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger action-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteSummaryModal<?php echo $summary['id']; ?>" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>

                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteSummaryModal<?php echo $summary['id']; ?>"
                                            tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Apakah Anda yakin ingin menghapus rangkuman ini?</p>
                                                        <p class="fw-bold">
                                                            <?php echo htmlspecialchars($summary['subject_name']); ?> -
                                                            <?php echo htmlspecialchars($summary['materi'] ?? ''); ?>
                                                        </p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="fas fa-times me-1"></i> Batal
                                                        </button>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="delete_summary">
                                                            <input type="hidden" name="summary_id"
                                                                value="<?php echo $summary['id']; ?>">
                                                            <input type="hidden" name="csrf_token"
                                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fas fa-trash-alt me-1"></i> Hapus
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Single reusable modal for viewing summary details -->
<div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryModalLabel">
                    <i class="fas fa-book-open me-2"></i>
                    <span id="modalSubjectName"></span> - <span id="modalMateri"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between mb-3">
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-calendar-plus me-1"></i>
                        Dibuat: <span id="modalCreatedAt"></span>
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-calendar-check me-1"></i>
                        Diubah: <span id="modalUpdatedAt"></span>
                    </span>
                </div>
                <div class="summary-content p-3 bg-light rounded" id="modalContent">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Add click event listener to summary-preview elements
        var summaryPreviews = document.querySelectorAll('.summary-preview');
        var summaryModal = new bootstrap.Modal(document.getElementById('summaryModal'));
        summaryPreviews.forEach(function (preview) {
            preview.addEventListener('click', function () {
                document.getElementById('modalSubjectName').textContent = this.getAttribute('data-subject-name');
                document.getElementById('modalMateri').textContent = this.getAttribute('data-materi');
                document.getElementById('modalCreatedAt').textContent = this.getAttribute('data-created-at');
                document.getElementById('modalUpdatedAt').textContent = this.getAttribute('data-updated-at');
                document.getElementById('modalContent').innerHTML = this.getAttribute('data-content');
                summaryModal.show();
            });
            // Also allow keyboard accessibility
            preview.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    });
</script>

<?php
// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once 'footer.php';
?>