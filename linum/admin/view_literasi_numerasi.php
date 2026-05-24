<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

// Handle rating and notes updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_rating') {
        header('Content-Type: application/json');
        $entry_id = filter_var($_POST['entry_id'], FILTER_VALIDATE_INT);
        $rating = filter_var($_POST['rating'], FILTER_VALIDATE_INT);
        $type = $_POST['type']; // 'literasi' or 'summary'

        if ($entry_id && $rating >= 0 && $rating <= 5 && in_array($type, ['literasi', 'summary'])) {
            try {
                if ($type === 'literasi') {
                    $stmt = $pdo->prepare("UPDATE literasi SET rating = ? WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE summaries SET rating = ? WHERE id = ?");
                }
                $stmt->execute([$rating, $entry_id]);
                echo json_encode(['success' => true, 'message' => 'Rating updated']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit();
    }

    if ($_POST['action'] === 'save_notes_rating') {
        header('Content-Type: application/json');
        $entry_id = filter_var($_POST['entry_id'], FILTER_VALIDATE_INT);
        $rating = filter_var($_POST['rating'], FILTER_VALIDATE_INT);
        $notes = trim($_POST['notes']);
        $type = $_POST['type']; // 'literasi' or 'summary'

        if ($entry_id && $rating >= 0 && $rating <= 5 && in_array($type, ['literasi', 'summary'])) {
            try {
                if ($type === 'literasi') {
                    $stmt = $pdo->prepare("UPDATE literasi SET rating = ?, notes = ? WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE summaries SET rating = ?, notes = ? WHERE id = ?");
                }
                $stmt->execute([$rating, $notes, $entry_id]);
                echo json_encode(['success' => true, 'message' => 'Rating dan catatan berhasil disimpan']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit();
    }

    if ($_POST['action'] === 'delete_entry') {
        header('Content-Type: application/json');
        $entry_id = filter_var($_POST['entry_id'], FILTER_VALIDATE_INT);
        $type = $_POST['type']; // 'literasi' or 'summary'

        if ($entry_id && in_array($type, ['literasi', 'summary'])) {
            try {
                if ($type === 'literasi') {
                    $stmt = $pdo->prepare("DELETE FROM literasi WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("DELETE FROM summaries WHERE id = ?");
                }
                $stmt->execute([$entry_id]);
                echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit();
    }
}

// Get user ID from URL
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: literasi_numerasi.php");
    exit();
}

// Fetch user details
$stmt = $laporanPdo->prepare("SELECT id, full_name, username, profile_photo as photo, assigned_class as class FROM users WHERE id = ? AND role = 'siswa'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: literasi_numerasi.php");
    exit();
}

// Access restrict: If guru, student must be in same class
if ($_SESSION['role'] === 'guru') {
    $guruClass = getGuruClass($_SESSION['user_id']);
    $studentClass = isset($user['class']) ? $user['class'] : null;

    if (!$guruClass || $studentClass !== $guruClass) {
        header("Location: literasi_numerasi.php");
        exit();
    }
}

// Fetch literasi entries
$stmt = $pdo->prepare("SELECT * FROM literasi WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$literasiEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $stmt = $pdo->prepare("SELECT * FROM numerasi WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $numerasiEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Numerasi table might not exist yet
    $numerasiEntries = [];
}

// Set page title
$pageTitle = "Detail Literasi & Numerasi - " . htmlspecialchars($user['full_name']);

// Set header action button
$headerAction = '
    <a href="literasi_numerasi.php" class="chip-btn chip-btn-outline-blue chip-btn-sm">
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        overflow: hidden;
        margin-bottom: 1.5rem;
        background-color: white;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
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


    .badge {
        font-weight: 500;
        padding: 0.5em 0.75em;
        border-radius: 8px;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        font-weight: 600;
        color: var(--dark-color);
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
    }

    .table td {
        vertical-align: middle;
        border-color: #e9ecef;
    }

    .action-btn {
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 3px;
    }

    .summary-preview {
        max-height: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }

    .profile-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        margin-bottom: 1rem;
        border: 3px solid #f8f9fa;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        .stats-card {
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .stats-card .icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .stats-card .count {
            font-size: 1.5rem !important;
            margin-bottom: 0.25rem;
        }

        .stats-card .label {
            font-size: 0.7rem;
            line-height: 1.2;
        }

        .profile-image {
            width: 70px !important;
            height: 70px !important;
        }

        h3.fw-bold {
            font-size: 1.25rem;
        }

        .table {
            font-size: 0.85rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    }
</style>

<!-- Back Button Removed (Moved to Header) -->

<div class="container py-4">
    <!-- User Profile -->
    <div class="text-center mb-4">
        <?php
        $photoFilename = isset($user['photo']) ? trim($user['photo']) : '';
        // Use the same path logic as view_student.php
        $photoPath = $photoFilename ? '../../laporan_harian/uploads/profile/' . $photoFilename : '';
        $absolutePath = $photoFilename ? __DIR__ . '/../../laporan_harian/uploads/profile/' . $photoFilename : '';

        if ($photoFilename && file_exists($absolutePath)) {
            $imgSrc = $photoPath;
        } else {
            $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=' . substr(md5($user['id']), 0, 6);
        }
        ?>
        <img src="<?php echo $imgSrc; ?>" class="profile-image mb-3"
            alt="<?php echo htmlspecialchars($user['full_name']); ?>">
        <h3 class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h3>
    </div>

    <!-- Literasi Statistics -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="count">
                        <?php echo count($literasiEntries); ?>
                    </div>
                    <div class="label">
                        Total Literasi
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Literasi List -->
    <div class="card mb-4">
        <div class="card-header fw-bold">
            <i class="fas fa-list-check me-2"></i> Daftar Literasi
        </div>
        <div class="card-body p-0">
            <?php if (empty($literasiEntries)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum ada data literasi</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Judul Buku</th>
                                <th>Penulis</th>
                                <th>Penerbit</th>
                                <th>Isi Literasi</th>
                                <th>Tanggal Dibuat</th>
                                <th>Rating</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($literasiEntries as $index => $entry): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($entry['title']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['author']); ?></td>
                                    <td><?php echo htmlspecialchars($entry['publisher']); ?></td>
                                    <td>
                                        <a href="#" class="literasi-content-preview" data-bs-toggle="modal"
                                            data-bs-target="#literasiDetailModal" data-id="<?php echo $entry['id']; ?>"
                                            data-content="<?php echo nl2br(htmlspecialchars($entry['content'])); ?>"
                                            data-title="<?php echo htmlspecialchars($entry['title']); ?>"
                                            data-rating="<?php echo ($entry['rating'] ?? 0); ?>"
                                            data-notes="<?php echo htmlspecialchars($entry['notes'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(substr(strip_tags($entry['content']), 0, 100)); ?>...
                                        </a>
                                    </td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($entry['created_at'])); ?></td>
                                    <td>
                                        <div class="rating-stars" data-entry-id="<?php echo $entry['id']; ?>"
                                            data-type="literasi" data-rating="<?php echo ($entry['rating'] ?? 0); ?>">
                                            <?php
                                            $rating = $entry['rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star star-filled" data-value="' . $i . '"></i>';
                                                } else {
                                                    echo '<i class="far fa-star star-empty" data-value="' . $i . '"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <button type="button" class="chip-btn chip-btn-blue chip-btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#literasiDetailModal" data-id="<?php echo $entry['id']; ?>"
                                                data-content="<?php echo nl2br(htmlspecialchars($entry['content'])); ?>"
                                                data-title="<?php echo htmlspecialchars($entry['title']); ?>"
                                                data-rating="<?php echo ($entry['rating'] ?? 0); ?>"
                                                data-notes="<?php echo htmlspecialchars($entry['notes'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i> <span class="d-none d-md-inline ms-1">Lihat
                                                    Detail</span>
                                            </button>
                                            <button type="button" class="chip-btn chip-btn-red chip-btn-sm"
                                                onclick="deleteEntryConfirm('literasi', <?php echo $entry['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Daily Summaries List -->
    <div class="card">
        <div class="card-header fw-bold">
            <i class="fas fa-calendar-check me-2"></i> Rangkuman Pembelajaran (Harian)
        </div>
        <div class="card-body p-0">
            <?php
            // Fetch summaries for this user
            $stmt = $pdo->prepare("SELECT s.*, sub.name as subject_name FROM summaries s JOIN subjects sub ON s.subject_id = sub.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
            $stmt->execute([$user_id]);
            $summaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (empty($summaries)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum ada rangkuman harian</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Mata Pelajaran</th>
                                <th>Materi</th>
                                <th>Isi Rangkuman</th>
                                <th>Tanggal Dibuat</th>
                                <th>Rating</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summaries as $index => $summary): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><span
                                            class="badge bg-info text-dark"><?php echo htmlspecialchars($summary['subject_name'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($summary['materi'] ?? '-'); ?></td>
                                    <td>
                                        <a href="#" class="summary-content-preview" data-bs-toggle="modal"
                                            data-bs-target="#summaryDetailModal" data-id="<?php echo $summary['id']; ?>"
                                            data-content="<?php echo nl2br(htmlspecialchars($summary['content'] ?? '')); ?>"
                                            data-subject="<?php echo htmlspecialchars($summary['subject_name'] ?? ''); ?>"
                                            data-materi="<?php echo htmlspecialchars($summary['materi'] ?? ''); ?>"
                                            data-rating="<?php echo ($summary['rating'] ?? 0); ?>"
                                            data-notes="<?php echo htmlspecialchars($summary['notes'] ?? ''); ?>">
                                            <?php echo htmlspecialchars(substr(strip_tags($summary['content'] ?? ''), 0, 100)); ?>
                                            <?php echo strlen($summary['content'] ?? '') > 100 ? '...' : ''; ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d-m-Y H:i', strtotime($summary['created_at'])); ?></td>
                                    <td>
                                        <div class="rating-stars" data-entry-id="<?php echo $summary['id']; ?>"
                                            data-type="summary" data-rating="<?php echo ($summary['rating'] ?? 0); ?>">
                                            <?php
                                            $rating = $summary['rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $rating) {
                                                    echo '<i class="fas fa-star star-filled" data-value="' . $i . '"></i>';
                                                } else {
                                                    echo '<i class="far fa-star star-empty" data-value="' . $i . '"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="d-flex gap-1">
                                            <button type="button" class="chip-btn chip-btn-blue chip-btn-sm" data-bs-toggle="modal"
                                                data-bs-target="#summaryDetailModal" data-id="<?php echo $summary['id']; ?>"
                                                data-content="<?php echo nl2br(htmlspecialchars($summary['content'] ?? '')); ?>"
                                                data-subject="<?php echo htmlspecialchars($summary['subject_name'] ?? ''); ?>"
                                                data-materi="<?php echo htmlspecialchars($summary['materi'] ?? ''); ?>"
                                                data-rating="<?php echo ($summary['rating'] ?? 0); ?>"
                                                data-notes="<?php echo htmlspecialchars($summary['notes'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i> <span class="d-none d-md-inline ms-1">Lihat
                                                    Detail</span>
                                            </button>
                                            <button type="button" class="chip-btn chip-btn-red chip-btn-sm"
                                                onclick="deleteEntryConfirm('summary', <?php echo $summary['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Literasi Detail Modal -->
    <div class="modal fade" id="literasiDetailModal" tabindex="-1" aria-labelledby="literasiDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="literasiDetailModalLabel">Detail Isi Literasi - <span
                            id="literasiDetailTitle"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="literasiDetailContent" style="white-space: pre-wrap;" class="mb-4"></div>

                    <hr>
                    <h6 class="fw-bold mb-3">Catatan dan Rating Admin</h6>

                    <div class="mb-3">
                        <label class="form-label">Rating (1-5 bintang)</label>
                        <div class="modal-rating-stars" id="literasiModalRating" data-rating="0">
                            <i class="far fa-star" data-value="1"></i>
                            <i class="far fa-star" data-value="2"></i>
                            <i class="far fa-star" data-value="3"></i>
                            <i class="far fa-star" data-value="4"></i>
                            <i class="far fa-star" data-value="5"></i>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="literasiNotes" class="form-label">Catatan Admin</label>
                        <textarea class="form-control" id="literasiNotes" rows="4"
                            placeholder="Masukkan catatan untuk siswa..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="chip-btn chip-btn-blue" id="saveLiterasiNotes">Simpan Catatan &
                        Rating</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Detail Modal -->
    <div class="modal fade" id="summaryDetailModal" tabindex="-1" aria-labelledby="summaryDetailModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="summaryDetailModalLabel">Detail Rangkuman Pembelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-book me-2"></i> Mata Pelajaran:</strong> <span
                                    id="summarySubject"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-clipboard me-2"></i> Materi:</strong> <span
                                    id="summaryMateri"></span></p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <h6 class="fw-bold">Isi Rangkuman:</h6>
                        <div id="summaryContent" class="p-3 bg-light rounded" style="white-space: pre-wrap;"></div>
                    </div>

                    <hr>
                    <h6 class="fw-bold mb-3">Catatan dan Rating Admin</h6>

                    <div class="mb-3">
                        <label class="form-label">Rating (1-5 bintang)</label>
                        <div class="modal-rating-stars" id="summaryModalRating" data-rating="0">
                            <i class="far fa-star" data-value="1"></i>
                            <i class="far fa-star" data-value="2"></i>
                            <i class="far fa-star" data-value="3"></i>
                            <i class="far fa-star" data-value="4"></i>
                            <i class="far fa-star" data-value="5"></i>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="summaryNotes" class="form-label">Catatan Admin</label>
                        <textarea class="form-control" id="summaryNotes" rows="4"
                            placeholder="Masukkan catatan untuk siswa..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="chip-btn chip-btn-blue" id="saveSummaryNotes">Simpan Catatan & Rating</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Berhasil
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle text-success mb-3" style="font-size: 3rem;"></i>
                    <p class="mb-0" id="successMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="chip-btn chip-btn-green" id="successModalOk">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="errorModalLabel">
                        <i class="fas fa-exclamation-circle me-2"></i>Error
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 3rem;"></i>
                    <p class="mb-0" id="errorMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="confirmModalLabel">
                        <i class="fas fa-question-circle me-2"></i>Konfirmasi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-question-circle text-warning mb-3" style="font-size: 3rem;"></i>
                    <p class="mb-0" id="confirmMessage">Apakah Anda yakin ingin menyimpan rating dan catatan ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="chip-btn chip-btn-blue" id="confirmOkButton">Ya, Simpan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentLiterasiId = null;
    let currentSummaryId = null;

    // Literasi Detail Modal Handler
    var literasiDetailModal = document.getElementById('literasiDetailModal');
    literasiDetailModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        currentLiterasiId = button.getAttribute('data-id');
        var content = button.getAttribute('data-content');
        var title = button.getAttribute('data-title') || '';
        var rating = parseInt(button.getAttribute('data-rating')) || 0;
        var notes = button.getAttribute('data-notes') || '';

        var modalBody = literasiDetailModal.querySelector('#literasiDetailContent');
        var modalTitle = literasiDetailModal.querySelector('#literasiDetailTitle');
        var modalNotes = literasiDetailModal.querySelector('#literasiNotes');
        var modalRating = literasiDetailModal.querySelector('#literasiModalRating');

        modalBody.innerHTML = content;
        modalTitle.textContent = title;
        modalNotes.value = notes;

        // Update rating stars
        modalRating.setAttribute('data-rating', rating);
        updateModalStarDisplay(modalRating, rating);
    });

    // Summary Detail Modal Handler
    var summaryDetailModal = document.getElementById('summaryDetailModal');
    summaryDetailModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        currentSummaryId = button.getAttribute('data-id');
        var content = button.getAttribute('data-content');
        var subject = button.getAttribute('data-subject') || '';
        var materi = button.getAttribute('data-materi') || '';
        var rating = parseInt(button.getAttribute('data-rating')) || 0;
        var notes = button.getAttribute('data-notes') || '';

        var modalContent = summaryDetailModal.querySelector('#summaryContent');
        var modalSubject = summaryDetailModal.querySelector('#summarySubject');
        var modalMateri = summaryDetailModal.querySelector('#summaryMateri');
        var modalNotes = summaryDetailModal.querySelector('#summaryNotes');
        var modalRating = summaryDetailModal.querySelector('#summaryModalRating');

        modalContent.innerHTML = content;
        modalSubject.textContent = subject;
        modalMateri.textContent = materi;
        modalNotes.value = notes;

        // Update rating stars
        modalRating.setAttribute('data-rating', rating);
        updateModalStarDisplay(modalRating, rating);
    });

    // Modal Rating Stars Functionality
    function updateModalStarDisplay(container, rating) {
        const stars = container.querySelectorAll('i');
        stars.forEach((star, index) => {
            if ((index + 1) <= rating) {
                star.className = 'fas fa-star';
                star.style.color = '#FFD700';
            } else {
                star.className = 'far fa-star';
                star.style.color = '#d1d5db';
            }
        });
    }

    // Setup modal rating stars
    document.addEventListener('DOMContentLoaded', function () {
        const modalRatingContainers = document.querySelectorAll('.modal-rating-stars');

        modalRatingContainers.forEach(container => {
            const stars = container.querySelectorAll('i');

            stars.forEach(star => {
                star.style.cursor = 'pointer';
                star.style.fontSize = '1.5rem';
                star.style.marginRight = '5px';

                star.addEventListener('mouseover', function () {
                    const value = parseInt(this.getAttribute('data-value'));
                    updateModalStarDisplay(container, value);
                });

                star.addEventListener('click', function () {
                    const value = parseInt(this.getAttribute('data-value'));
                    container.setAttribute('data-rating', value);
                    updateModalStarDisplay(container, value);
                });
            });

            container.addEventListener('mouseleave', function () {
                const currentRating = parseInt(container.getAttribute('data-rating'));
                updateModalStarDisplay(container, currentRating);
            });
        });

        // Save Literasi Notes Button
        document.getElementById('saveLiterasiNotes').addEventListener('click', function () {
            const rating = parseInt(document.getElementById('literasiModalRating').getAttribute('data-rating'));
            const notes = document.getElementById('literasiNotes').value;

            if (!currentLiterasiId) {
                showErrorModal('Error: Entry ID tidak ditemukan');
                return;
            }

            // Show confirmation modal
            showConfirmModal('literasi', currentLiterasiId, rating, notes);
        });

        // Save Summary Notes Button
        document.getElementById('saveSummaryNotes').addEventListener('click', function () {
            const rating = parseInt(document.getElementById('summaryModalRating').getAttribute('data-rating'));
            const notes = document.getElementById('summaryNotes').value;

            if (!currentSummaryId) {
                showErrorModal('Error: Entry ID tidak ditemukan');
                return;
            }

            // Show confirmation modal
            showConfirmModal('summary', currentSummaryId, rating, notes);
        });

        // Success Modal OK Button
        document.getElementById('successModalOk').addEventListener('click', function () {
            bootstrap.Modal.getInstance(document.getElementById('successModal')).hide();
            location.reload();
        });
    });

    // Helper Functions for Modals
    function showSuccessModal(message) {
        document.getElementById('successMessage').textContent = message;
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    }

    function showErrorModal(message) {
        document.getElementById('errorMessage').textContent = message;
        const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
        errorModal.show();
    }

    function showConfirmModal(type, entryId, rating, notes) {
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const confirmButton = document.getElementById('confirmOkButton');

        // Remove previous event listeners by cloning
        const newConfirmButton = confirmButton.cloneNode(true);
        confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);

        // Add new event listener
        newConfirmButton.addEventListener('click', function () {
            confirmModal.hide();
            saveNotesRating(type, entryId, rating, notes);
        });

        confirmModal.show();
    }

    function saveNotesRating(type, entryId, rating, notes) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=save_notes_rating&entry_id=${entryId}&type=${type}&rating=${rating}&notes=${encodeURIComponent(notes)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close detail modal
                    if (type === 'literasi') {
                        bootstrap.Modal.getInstance(document.getElementById('literasiDetailModal')).hide();
                    } else {
                        bootstrap.Modal.getInstance(document.getElementById('summaryDetailModal')).hide();
                    }
                    // Show success modal
                    showSuccessModal(data.message);
                } else {
                    showErrorModal('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Gagal menyimpan rating dan catatan');
            });
    }

    function deleteEntryConfirm(type, entryId) {
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const confirmButton = document.getElementById('confirmOkButton');
        const confirmMessage = document.getElementById('confirmMessage');

        confirmMessage.textContent = "Apakah Anda yakin ingin menghapus data ini?";
        confirmButton.className = "btn btn-danger";
        confirmButton.textContent = "Ya, Hapus";

        // Remove previous event listeners by cloning
        const newConfirmButton = confirmButton.cloneNode(true);
        confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);

        // Add new event listener
        newConfirmButton.addEventListener('click', function () {
            confirmModal.hide();
            deleteEntry(type, entryId);
        });

        confirmModal.show();

        // Reset modal state when hidden (optional, but good for UX if reusing modal)
        document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function () {
            confirmButton.className = "btn btn-primary";
            confirmButton.textContent = "Ya, Simpan"; // Default text
        }, { once: true });
    }

    function deleteEntry(type, entryId) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_entry&entry_id=${entryId}&type=${type}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal(data.message);
                } else {
                    showErrorModal(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Terjadi kesalahan koneksi');
            });
    }

    // Star Rating Click Handler
    document.addEventListener('DOMContentLoaded', function () {
        const ratingContainers = document.querySelectorAll('.rating-stars');

        ratingContainers.forEach(container => {
            const stars = container.querySelectorAll('i');
            const entryId = container.getAttribute('data-entry-id');
            const type = container.getAttribute('data-type');

            stars.forEach(star => {
                star.style.cursor = 'pointer';

                star.addEventListener('mouseover', function () {
                    const value = parseInt(this.getAttribute('data-value'));
                    updateStarDisplay(stars, value);
                });

                star.addEventListener('click', function () {
                    const value = parseInt(this.getAttribute('data-value'));
                    updateRating(entryId, type, value, stars);
                });
            });

            container.addEventListener('mouseleave', function () {
                const currentRating = parseInt(container.getAttribute('data-rating'));
                updateStarDisplay(stars, currentRating);
            });
        });

        function updateStarDisplay(stars, rating) {
            stars.forEach((star, index) => {
                if ((index + 1) <= rating) {
                    star.className = 'fas fa-star star-filled';
                } else {
                    star.className = 'far fa-star star-empty';
                }
            });
        }

        function updateRating(entryId, type, rating, stars) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_rating&entry_id=${entryId}&type=${type}&rating=${rating}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const container = stars[0].closest('.rating-stars');
                        container.setAttribute('data-rating', rating);
                        updateStarDisplay(stars, rating);
                    } else {
                        alert('Error updating rating: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update rating');
                });
        }
    });
</script>

<style>
    .rating-stars {
        display: inline-flex;
        gap: 2px;
    }

    .rating-stars i {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .star-filled {
        color: #FFD700;
    }

    .star-empty {
        color: #d1d5db;
    }

    .rating-stars i:hover {
        transform: scale(1.2);
    }

    .summary-content-preview {
        color: var(--primary-color);
        text-decoration: none;
    }

    .summary-content-preview:hover {
        text-decoration: underline;
        cursor: pointer;
    }
</style>
<?php require_once 'footer.php'; ?>