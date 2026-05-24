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

// Get summary ID from URL
$summary_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$summary_id) {
    header("Location: dashboard.php");
    exit();
}

// Fetch summary details
$stmt = $pdo->prepare("
    SELECT s.*, sub.name as subject_name, u.full_name as student_name
    FROM summaries s 
    JOIN subjects sub ON s.subject_id = sub.id 
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$summary_id]);
$summary = $stmt->fetch();

if (!$summary) {
    header("Location: dashboard.php");
    exit();
}

// Set page title
$pageTitle = "Detail Rangkuman - " . htmlspecialchars($summary['subject_name']);

// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .card-header {
        background-color: white;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }

    .badge {
        padding: 0.5em 0.75em;
        font-weight: 500;
    }

    .summary-content {
        line-height: 1.8;
        font-size: 1rem;
    }
</style>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="fas fa-book-open me-2"></i>Detail Rangkuman
                </h4>
                <a href="view_student.php?id=<?php echo $summary['user_id']; ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <?php echo htmlspecialchars($summary['subject_name']); ?> -
                            <?php echo htmlspecialchars($summary['materi'] ?? ''); ?>
                        </h5>
                        <span class="badge bg-primary">
                            <?php echo htmlspecialchars($summary['student_name']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-4">
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar-plus me-1"></i>
                            Dibuat: <?php echo date('d/m/Y H:i', strtotime($summary['created_at'])); ?>
                        </span>
                        <span class="badge bg-light text-dark">
                            <i class="fas fa-calendar-check me-1"></i>
                            Diubah: <?php echo date('d/m/Y H:i', strtotime($summary['updated_at'])); ?>
                        </span>
                    </div>
                    <div class="summary-content p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($summary['content'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>