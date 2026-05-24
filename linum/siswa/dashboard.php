<?php
require_once '../config.php';
require_once '../functions.php';

// Linum no longer requires login - ensure session exists with default values
ensureSession();


// Fetch subjects for dropdown
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY name");
$subjects = $stmt->fetchAll();

// Fetch student's summaries
$stmt = $pdo->prepare("SELECT s.*, sub.name as subject_name 
                       FROM summaries s 
                       JOIN subjects sub ON s.subject_id = sub.id 
                       WHERE s.user_id = ? 
                       ORDER BY s.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$summaries = $stmt->fetchAll();

$materiList = [];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - Sistem Laporan Resume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Quill Rich Text Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4bb543;
            --danger-color: #f44336;
            --warning-color: #ff9800;
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

        .profile-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
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

        .word-count {
            position: absolute;
            bottom: 15px;
            right: 15px;
            font-size: 0.8rem;
            color: #6c757d;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 2px 8px;
            border-radius: 10px;
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

        .badge {
            font-weight: 500;
            padding: 0.5em 0.75em;
            border-radius: 8px;
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            margin: 0 0.5rem;
            width: auto;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-outline-danger {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
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

            .table-responsive {
                border-radius: 0;
            }

            .table th,
            .table td {
                padding: 0.75rem;
            }
        }

        /* Quill Editor Styles */
        #editor-container {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: white;
        }

        .ql-toolbar {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            border: none;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .ql-container {
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }

        .ql-editor {
            min-height: 150px;
        }

        .ql-editor.ql-blank::before {
            color: #6c757d;
            font-style: normal;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>Sistem Laporan Resume</span>
            </a>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="literasi_numerasi.php">Literasi dan Numerasi</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <?php
                    // Determine profile photo source
                    $userPhoto = $_SESSION['photo'] ?? '';
                    $userName = $_SESSION['full_name'] ?? 'User';

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
                    <img src="<?php echo $imgSrc; ?>" class="rounded-circle profile-image shadow-sm" alt="Profile">
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button"
                        id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                        <li><a class="dropdown-item" href="edit_profile.php"><i class="bi bi-person me-2"></i>Edit
                                Profil</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="../logout.php"><i
                                    class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <!-- Form Rangkuman -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-pencil-square"></i> Buat Rangkuman Baru</h5>
                    </div>
                    <div class="card-body">
                        <form id="summaryForm" action="submit_summary.php" method="POST">
                            <div class="mb-3">
                                <label for="subject_id" class="form-label fw-semibold">Mata Pelajaran</label>
                                <select class="form-select" id="subject_id" name="subject_id" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>">
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="materi" class="form-label fw-semibold">Topik Materi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-book"></i></span>
                                    <input type="text" class="form-control" id="materi" name="materi"
                                        placeholder="Masukkan topik materi">
                                </div>
                            </div>
                            <div class="mb-3 position-relative">
                                <label for="content" class="form-label fw-semibold">Isi Rangkuman <small
                                        class="text-muted">(minimal 100 kata)</small></label>
                                <div id="editor-container" style="height: 200px;"></div>
                                <textarea class="form-control d-none" id="content" name="content" required></textarea>
                                <div class="word-count" id="wordCount">0 kata</div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="bi bi-send-fill me-1"></i> Kirim Rangkuman
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Statistik -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line"></i> Statistik Rangkuman</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Total Rangkuman</h6>
                                <p class="text-muted small mb-0">Yang telah Anda buat</p>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                <?php echo count($summaries); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Mata Pelajaran</h6>
                                <p class="text-muted small mb-0">Berbeda yang telah dirangkum</p>
                            </div>
                            <span class="badge bg-success bg-opacity-10 text-success">
                                <?php
                                $uniqueSubjects = array_unique(array_column($summaries, 'subject_id'));
                                echo count($uniqueSubjects);
                                ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Rata-rata Kata</h6>
                                <p class="text-muted small mb-0">Per rangkuman</p>
                            </div>
                            <span class="badge bg-warning bg-opacity-10 text-warning">
                                <?php
                                if (count($summaries) > 0) {
                                    $totalWords = 0;
                                    foreach ($summaries as $summary) {
                                        $totalWords += str_word_count($summary['content']);
                                    }
                                    echo round($totalWords / count($summaries));
                                } else {
                                    echo '0';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Materi Terbaru -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Materi Terbaru</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($summaries)): ?>
                            <div class="text-center py-3 text-muted">
                                Belum ada materi
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php
                                $recentSummaries = array_slice($summaries, 0, 3);
                                foreach ($recentSummaries as $summary):
                                    ?>
                                    <li class="list-group-item border-0 px-0 py-2">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($summary['subject_name']); ?></h6>
                                                <p class="small text-muted mb-0">
                                                    <?php echo htmlspecialchars($summary['materi'] ?? 'Tidak ada topik'); ?>
                                                </p>
                                            </div>
                                            <span class="badge bg-light text-dark">
                                                <?php
                                                $date = new DateTime($summary['created_at']);
                                                echo $date->format('d M');
                                                ?>
                                            </span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabel Rangkuman -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="bi bi-collection"></i> Rekapitulasi Rangkuman</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($summaries)): ?>
                            <div class="empty-state">
                                <i class="bi bi-journal-text"></i>
                                <h5 class="mt-3">Belum ada rangkuman</h5>
                                <p class="text-muted">Mulailah dengan membuat rangkuman pertama Anda</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Materi</th>
                                            <th>Isi Rangkuman</th>
                                            <th>Tanggal</th>
                                            <th>Catatan Guru</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($summaries as $index => $summary): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 p-2 rounded me-2">
                                                            <i class="bi bi-book text-primary"></i>
                                                        </div>
                                                        <span><?php echo htmlspecialchars($summary['subject_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($summary['materi'] ?? '-'); ?></td>
                                                <td>
                                                    <div class="mb-2">
                                                        <div class="rating-display mb-1">
                                                            <?php
                                                            $rating = $summary['rating'] ?? 0;
                                                            if ($rating > 0) {
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    if ($i <= $rating) {
                                                                        echo '<i class="bi bi-star-fill text-warning"></i>';
                                                                    } else {
                                                                        echo '<i class="bi bi-star text-muted"></i>';
                                                                    }
                                                                }
                                                            } else {
                                                                echo '<span class="text-muted small">Belum dinilai</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="summary-preview">
                                                            <?php
                                                            // Strip HTML tags for preview to get plain text
                                                            $plainText = strip_tags($summary['content']);
                                                            echo htmlspecialchars(substr($plainText, 0, 100));
                                                            if (strlen($plainText) > 100)
                                                                echo '...';
                                                            ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-calendar3 me-2 text-muted"></i>
                                                        <?php
                                                        $date = new DateTime($summary['created_at']);
                                                        echo $date->format('d M Y');
                                                        ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $notes = $summary['notes'] ?? '';
                                                    if (!empty(trim($notes))) {
                                                        // Show first 50 characters of notes with "Lihat" button
                                                        echo '<div class="notes-preview">';
                                                        echo '<span class="notes-text">' . htmlspecialchars(substr($notes, 0, 50));
                                                        if (strlen($notes) > 50) {
                                                            echo '...';
                                                        }
                                                        echo '</span> ';
                                                        if (strlen($notes) > 50) {
                                                            echo '<button class="btn btn-sm btn-link p-0 view-notes-btn" data-bs-toggle="modal" data-bs-target="#notesModal" data-notes="' . htmlspecialchars($notes) . '">Lihat</button>';
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        echo '<span class="text-muted small">Tidak ada catatan</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex">
                                                        <button class="btn btn-sm btn-outline-primary edit-btn me-2"
                                                            data-id="<?php echo $summary['id']; ?>" data-bs-toggle="modal"
                                                            data-bs-target="#editModal">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-btn"
                                                            data-id="<?php echo $summary['id']; ?>" data-bs-toggle="modal"
                                                            data-bs-target="#deleteModal">
                                                            <i class="bi bi-trash"></i>
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
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <!-- Modal Header -->
                <div class="modal-header bg-gradient-primary text-white">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-edit fa-lg me-3"></i>
                        <h5 class="modal-title fs-5 fw-bold">Edit Rangkuman</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-4">
                    <form id="editForm">
                        <input type="hidden" id="edit_id" name="id">

                        <!-- Subject Selection -->
                        <div class="mb-4">
                            <label for="edit_subject_id" class="form-label fw-semibold d-flex align-items-center">
                                <i class="fas fa-book-open me-2 text-primary"></i> Mata Pelajaran
                            </label>
                            <select class="form-select form-select-lg py-2" id="edit_subject_id" name="subject_id"
                                required>
                                <option value="" disabled selected>Pilih mata pelajaran</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Topic Input -->
                        <div class="mb-4">
                            <label for="edit_materi" class="form-label fw-semibold d-flex align-items-center">
                                <i class="fas fa-tag me-2 text-primary"></i> Topik Materi
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="fas fa-book text-muted"></i></span>
                                <input type="text" class="form-control py-2" id="edit_materi" name="materi"
                                    placeholder="Masukkan topik materi" required>
                            </div>
                        </div>

                        <!-- Content Textarea -->
                        <div class="mb-3 position-relative">
                            <label for="edit_content" class="form-label fw-semibold d-flex align-items-center">
                                <i class="fas fa-align-left me-2 text-primary"></i> Isi Rangkuman
                                <small class="text-muted ms-2">(minimal 100 kata)</small>
                            </label>
                            <div id="edit-editor-container" style="height: 200px;"></div>
                            <textarea class="form-control d-none" id="edit_content" name="content" required></textarea>
                            <div class="d-flex justify-content-between mt-2">
                                <div class="word-count-badge" id="editWordCount">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-font me-1"></i>
                                        <span id="wordCount">0</span>/100 kata
                                    </span>
                                </div>
                                <div class="text-muted small">
                                    <i class="fas fa-info-circle me-1"></i> Tekan Shift+Enter untuk baris baru
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-lg btn-outline-secondary rounded-pill px-4"
                        data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Batal
                    </button>
                    <button type="button" class="btn btn-lg btn-primary rounded-pill px-4" id="saveEdit">
                        <span id="saveButtonText">
                            <i class="fas fa-save me-2"></i> Simpan Perubahan
                        </span>
                        <span id="saveButtonLoader" class="d-none">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
        }

        .word-count-badge .badge {
            transition: all 0.3s ease;
            font-size: 0.85rem;
            padding: 0.35rem 0.75rem;
        }

        #edit_content {
            border-radius: 10px;
            border: 1px solid #dee2e6;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        #edit_content:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }

        .form-select,
        .form-control {
            border-radius: 8px !important;
        }

        @media (max-width: 768px) {
            .modal-dialog {
                margin: 1rem auto;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editContent = document.getElementById('edit_content');
            const wordCountBadge = document.getElementById('editWordCount').querySelector('.badge');
            const wordCountDisplay = document.getElementById('wordCount');
            const saveButton = document.getElementById('saveEdit');
            const saveButtonText = document.getElementById('saveButtonText');
            const saveButtonLoader = document.getElementById('saveButtonLoader');

            // Update word count
            function updateWordCount() {
                const text = editContent.value.trim();
                const count = text ? text.split(/\s+/).length : 0;
                wordCountDisplay.textContent = count;

                // Update badge color based on word count
                if (count < 50) {
                    wordCountBadge.classList.remove('bg-warning', 'bg-success');
                    wordCountBadge.classList.add('bg-danger', 'text-white');
                } else if (count < 100) {
                    wordCountBadge.classList.remove('bg-danger', 'bg-success');
                    wordCountBadge.classList.add('bg-warning', 'text-dark');
                } else {
                    wordCountBadge.classList.remove('bg-danger', 'bg-warning');
                    wordCountBadge.classList.add('bg-success', 'text-white');
                }
            }

            // Initialize word count
            editContent.addEventListener('input', updateWordCount);

            // Handle save button click
            saveButton.addEventListener('click', function () {
                // Show loading state
                saveButtonText.classList.add('d-none');
                saveButtonLoader.classList.remove('d-none');
                saveButton.disabled = true;

                // Here you would typically submit the form via AJAX
                // For demonstration, we'll simulate a 1.5 second delay
                setTimeout(function () {
                    // Hide loading state
                    saveButtonText.classList.remove('d-none');
                    saveButtonLoader.classList.add('d-none');
                    saveButton.disabled = false;

                    // Close modal (in a real app, this would be after successful submission)
                    bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();

                    // Show success toast/alert
                    showAlert('success', '<i class="fas fa-check-circle me-2"></i> Rangkuman berhasil diperbarui');
                }, 1500);
            });

            // Function to show alert (example)
            function showAlert(type, message) {
                // Implementation for showing toast/alert
                console.log(`Showing ${type} alert: ${message}`);
            }
        });
    </script>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus rangkuman ini? Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="bi bi-trash me-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>Catatan dari Guru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="modalNotesContent" class="mb-0" style="white-space: pre-wrap;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .rating-display {
            font-size: 1.2rem;
        }

        .rating-display i {
            margin-right: 2px;
        }

        .notes-preview {
            max-width: 200px;
        }

        .notes-text {
            display: inline;
        }

        .view-notes-btn {
            text-decoration: none;
            font-weight: 500;
        }

        .view-notes-btn:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .rating-display {
                font-size: 1rem;
            }
        }
    </style>


    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Quill editor for new summary
        const quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            },
            placeholder: 'Tulis rangkuman materi Anda di sini...'
        });

        // Word counter for new summary
        const content = document.getElementById('content');
        const wordCount = document.getElementById('wordCount');
        const submitBtn = document.getElementById('submitBtn');

        function countWords(text) {
            return text.trim().split(/\s+/).filter(word => word.length > 0).length;
        }

        // Update hidden textarea and word count when Quill content changes
        quill.on('text-change', function () {
            const text = quill.getText();
            const html = quill.root.innerHTML;
            content.value = html; // Store HTML in hidden textarea

            const words = countWords(text);
            wordCount.textContent = words + ' kata';
            submitBtn.disabled = words < 100;

            // Update word count color based on threshold
            if (words < 100) {
                wordCount.style.color = '#dc3545';
            } else {
                wordCount.style.color = '#28a745';
            }
        });

        // Initialize Quill editor for edit summary
        const quillEdit = new Quill('#edit-editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'align': [] }],
                    ['clean']
                ]
            },
            placeholder: 'Tulis rangkuman materi Anda di sini...'
        });

        // Word counter for edit form
        const editContent = document.getElementById('edit_content');
        const editWordCount = document.getElementById('editWordCount');
        const saveEditBtn = document.getElementById('saveEdit');

        quillEdit.on('text-change', function () {
            const text = quillEdit.getText();
            const html = quillEdit.root.innerHTML;
            editContent.value = html; // Store HTML in hidden textarea

            const words = countWords(text);
            editWordCount.querySelector('#wordCount').textContent = words;

            // Note: The previous logic updated the whole textContent of editWordCount, removing the inner structure.
            // We need to target the span #wordCount inside it or reconstruct the text.
            // Looking at HTML line 667: <div class="word-count-badge" id="editWordCount"><span class="badge ..."><i...> <span id="wordCount">0</span>/100 kata</span></div>
            // Wait, the previous JS was: editWordCount.textContent = words + ' kata'; 
            // This would wipe out the badge structure!
            // Let's check the HTML again.
            // HTML: 
            // <div class="word-count-badge" id="editWordCount">
            //    <span class="badge bg-light text-dark">
            //        <i class="fas fa-font me-1"></i>
            //        <span id="wordCount">0</span>/100 kata
            //    </span>
            // </div>

            // The previous JS (lines 932) was: editWordCount.textContent = words + ' kata';
            // This indeed destroyed the badge UI. I should fix that too.
            // I will use querySelector to find the inner span or badge.

            const badge = editWordCount.querySelector('.badge');
            const wordCountSpan = editWordCount.querySelector('#wordCount');
            if (wordCountSpan) wordCountSpan.textContent = words;

            // Update badge color
            if (words < 100) {
                badge.classList.remove('bg-warning', 'bg-success', 'text-dark');
                badge.classList.add('bg-danger', 'text-white');
            } else {
                badge.classList.remove('bg-danger', 'bg-warning', 'text-dark');
                badge.classList.add('bg-success', 'text-white');
            }
            // Logic for save button
            saveEditBtn.disabled = words < 100;
        });

        // Edit functionality
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function () {
                const id = this.dataset.id;
                fetch(`edit_summary.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const summary = data.data;
                            document.getElementById('edit_id').value = summary.id;
                            document.getElementById('edit_subject_id').value = summary.subject_id;
                            document.getElementById('edit_materi').value = summary.materi ? atob(summary.materi) : '';

                            // Initialize Quill content
                            quillEdit.root.innerHTML = summary.content ? atob(summary.content) : '';
                            editContent.value = quillEdit.root.innerHTML;

                            const text = quillEdit.getText();
                            const words = countWords(text);

                            // Update word count UI correctly
                            const badge = editWordCount.querySelector('.badge');
                            const wordCountSpan = editWordCount.querySelector('#wordCount');
                            if (wordCountSpan) wordCountSpan.textContent = words;

                            saveEditBtn.disabled = words < 100;

                            // Update word count color based on threshold
                            if (words < 100) {
                                badge.classList.remove('bg-warning', 'bg-success', 'text-dark');
                                badge.classList.add('bg-danger', 'text-white');
                            } else {
                                badge.classList.remove('bg-danger', 'bg-warning', 'text-dark');
                                badge.classList.add('bg-success', 'text-white');
                            }
                        } else {
                            alert(data.message);
                        }
                    });
            });
        });

        // Save edit
        document.getElementById('saveEdit').addEventListener('click', function () {
            const formData = new FormData(document.getElementById('editForm'));
            fetch('edit_summary.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
        });

        // Delete functionality
        let deleteId = null;
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function () {
                deleteId = this.dataset.id;
            });
        });

        document.getElementById('confirmDelete').addEventListener('click', function () {
            if (deleteId) {
                fetch('delete_summary.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${deleteId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        });

        // Add animation to cards on load
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = `opacity 0.3s ease, transform 0.3s ease ${index * 0.1}s`;

                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });
        });

        // Notes modal functionality
        document.querySelectorAll('.view-notes-btn').forEach(button => {
            button.addEventListener('click', function () {
                const notes = this.getAttribute('data-notes');
                document.getElementById('modalNotesContent').textContent = notes;
            });
        });
    </script>
</body>

</html>