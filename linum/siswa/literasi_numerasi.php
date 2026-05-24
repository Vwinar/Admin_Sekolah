<?php
require_once '../config.php';
require_once '../functions.php';

date_default_timezone_set('Asia/Jakarta');

// Linum no longer requires login - ensure session exists with default values
ensureSession();


$userId = $_SESSION['user_id'];

// Fetch all uploaded books
try {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY created_at DESC");
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}

// Fetch literasi entries for the user
try {
    $stmt = $pdo->prepare("SELECT * FROM literasi WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $literasiEntries = $stmt->fetchAll();
} catch (PDOException $e) {
    $literasiEntries = [];
}

// Calculate statistics for literasi
$totalLiterasi = count($literasiEntries);
$averageWords = 0;
if ($totalLiterasi > 0) {
    $totalWords = 0;
    foreach ($literasiEntries as $entry) {
        $totalWords += str_word_count($entry['content']);
    }
    $averageWords = round($totalWords / $totalLiterasi);
}

$errors = [];
$successMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $publisher = trim($_POST['publisher']);
    $content = trim($_POST['content']);

    if (empty($title))
        $errors[] = "Judul buku wajib diisi.";
    if (empty($author))
        $errors[] = "Penulis wajib diisi.";
    if (empty($publisher))
        $errors[] = "Penerbit wajib diisi.";
    if (empty($content))
        $errors[] = "Isi literasi wajib diisi.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO literasi (user_id, title, author, publisher, content, created_at) VALUES (?, ?, ?, ?, ?, datetime('now'))");
            $stmt->execute([$userId, $title, $author, $publisher, $content]);
            header("Location: literasi_numerasi.php?added=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title>Literasi dan Numerasi - Dashboard Siswa</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-hover: #3a56d4;
            --secondary-color: #f8f9fa;
            --text-color: #212529;
            --light-text: #6c757d;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--text-color);
            line-height: 1.6;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
            background-color: white;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            font-weight: 600;
            color: var(--primary-color);
            background-color: white;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            transition: var(--transition);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .table th {
            background-color: var(--secondary-color);
            font-weight: 600;
            color: var(--light-text);
        }

        .table td,
        .table th {
            vertical-align: middle;
            padding: 1rem;
        }

        .table-responsive {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 1.25rem;
            transition: var(--transition);
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }

        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 0.75rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-image {
            transition: var(--transition);
        }

        .profile-image:hover {
            transform: scale(1.1);
        }

        .dropdown-menu {
            border: none;
            box-shadow: var(--box-shadow);
            border-radius: 8px;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }

        .alert {
            border-radius: 8px;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--light-text);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .modal-content {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.25rem 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header {
                padding: 1rem;
            }

            .table td,
            .table th {
                padding: 0.75rem;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand span {
                display: none;
            }

            .card-body {
                padding: 1rem;
            }

            .list-group-item {
                padding: 1rem;
            }
        }

        +

        /* Additional styling for improved layout */
        +.list-group-item .text-muted {
            +font-size: 0.9rem;
            +
        }

        +.list-group-item h6 {
            +font-size: 1.1rem;
            +font-weight: 600;
            +margin-bottom: 0.3rem;
            +
        }

        +.list-group-item:hover {
            +background-color: #e9f0ff;
            +cursor: pointer;
            +
        }

        +.card-header i {
            +color: var(--primary-color);
            +
        }

        +.btn-outline-primary {
            +border-color: var(--primary-color);
            +color: var(--primary-color);
            +transition: background-color 0.3s ease, color 0.3s ease;
            +
        }

        +.btn-outline-primary:hover {
            +background-color: var(--primary-color);
            +color: white;
            +
        }

        +.stat-card {
            +background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            +color: var(--primary-color);
            +box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
            +
        }

        +.stat-number {
            +font-size: 3rem;
            +font-weight: 700;
            +
        }

        +.stat-label {
            +font-size: 1rem;
            +letter-spacing: 1.5px;
            +text-transform: uppercase;
            +margin-top: 0.25rem;
            +
        }

        +.card {
            +transition: box-shadow 0.4s ease, transform 0.4s ease;
            +
        }

        +.card:hover {
            +box-shadow: 0 15px 25px rgba(67, 97, 238, 0.4);
            +transform: translateY(-5px);
            +
        }

        +.btn-primary {
            +font-weight: 600;
            +padding: 0.85rem 1.75rem;
            +font-size: 1rem;
            +
        }

        +.btn-primary:hover {
            +box-shadow: 0 8px 20px rgba(67, 97, 238, 0.5);
            +
        }

        +

        /* Scrollbar styling for scrollable lists */
        +.list-group.list-group-flush {
            +scrollbar-width: thin;
            +scrollbar-color: var(--primary-color) #f1f1f1;
            +
        }

        +.list-group.list-group-flush::-webkit-scrollbar {
            +width: 8px;
            +
        }

        +.list-group.list-group-flush::-webkit-scrollbar-track {
            +background: #f1f1f1;
            +border-radius: 4px;
            +
        }

        +.list-group.list-group-flush::-webkit-scrollbar-thumb {
            +background-color: var(--primary-color);
            +border-radius: 4px;
            +border: 2px solid #f1f1f1;
            +
        }

        +
        /* Responsive tweaks */
        +@media (max-width: 992px) {
            +.stat-number {
                +font-size: 2.2rem;
                +
            }

            +.stat-label {
                +font-size: 0.85rem;
                +
            }

            +
        }

        +@media (max-width: 576px) {
            +.stat-number {
                +font-size: 1.8rem;
                +
            }

            +.stat-label {
                +font-size: 0.75rem;
                +
            }

            +
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top bg-white shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-book-open text-primary"></i>
                <span>Sistem Laporan Resume</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="literasi_numerasi.php">
                            <i class="fas fa-book-reader me-1"></i> Literasi & Numerasi
                        </a>
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
                        <img src="<?php echo $imgSrc; ?>" class="rounded-circle profile-image shadow-sm" alt="Profile"
                            style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e9ecef;">
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button"
                            id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span
                                class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <i class="fas fa-chevron-down ms-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li>
                                <a class="dropdown-item" href="edit_profile.php">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profil
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="../logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <div>
                        <h6 class="mb-1">Terjadi Kesalahan</h6>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-circle me-2"></i>
                    <div>
                        <h6 class="mb-0">Literasi berhasil ditambahkan!</h6>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Books List -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100" style="max-height: 600px; overflow-y: auto;">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-book me-2"></i>
                        <span>Buku yang Diunggah</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($books)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada buku yang diunggah.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($books as $book): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($book['title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-pen me-1"></i>
                                                    <?php echo htmlspecialchars($book['author']); ?>
                                                </small><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-building me-1"></i>
                                                    <?php echo htmlspecialchars($book['publisher']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <?php if (!empty($book['link']) && !empty($book['pdf_path'])): ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                            Pilih Aksi
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item open-link" href="#"
                                                                    data-link="<?php echo htmlspecialchars($book['link']); ?>">
                                                                    <i class="fas fa-external-link-alt me-1"></i> Buka Link
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="#" class="dropdown-item view-pdf"
                                                                    data-pdf="../<?php echo htmlspecialchars($book['pdf_path']); ?>">
                                                                    <i class="fas fa-file-pdf me-1"></i> Lihat PDF
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                <?php elseif (!empty($book['link'])): ?>
                                                    <a href="#" data-link="<?php echo htmlspecialchars($book['link']); ?>"
                                                        class="btn btn-sm btn-outline-primary open-link">
                                                        <i class="fas fa-external-link-alt"></i> Link
                                                    </a>
                                                <?php elseif (!empty($book['pdf_path'])): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#pdfModal"
                                                        data-pdf="../<?php echo htmlspecialchars($book['pdf_path']); ?>">
                                                        <i class="fas fa-file-pdf"></i> PDF
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Column -->
            <div class="col-lg-6 mb-4">
                <div class="row">
                    <!-- Literasi Statistics -->
                    <div class="col-md-6 mb-4">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="stat-number"><?php echo $totalLiterasi; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-list-check me-1"></i> Total Literasi
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="stat-number"><?php echo $averageWords; ?></div>
                                <div class="stat-label">
                                    <i class="fas fa-font me-1"></i> Rata-rata Kata
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Latest Literasi -->
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-clock-rotate-left me-2"></i>
                        <span>Literasi Terbaru</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($literasiEntries)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-pen-fancy fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Belum ada literasi.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach (array_slice($literasiEntries, 0, 2) as $entry): ?>
                                    <div class="list-group-item">
                                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($entry['title']); ?></h6>
                                        <p class="mb-2 text-muted small">
                                            <?php echo nl2br(htmlspecialchars(substr($entry['content'], 0, 150))); ?>...
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-day me-1"></i>
                                                <?php echo date("d M Y", strtotime($entry['created_at'])); ?>
                                            </small>
                                            <a href="#" class="btn btn-sm btn-outline-primary"
                                                data-id="<?php echo $entry['id']; ?>" data-bs-toggle="modal"
                                                data-bs-target="#detailModal">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($literasiEntries) > 2): ?>
                                <div class="text-center mt-3">
                                    <a href="#literasiTable" class="btn btn-sm btn-outline-primary">
                                        Lihat Semua <i class="fas fa-arrow-down ms-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Literasi Recap Table -->
            <div class="col-12 mt-4 mb-4" id="literasiTable">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-table me-2"></i>
                            <span>Rekapitulasi Literasi</span>
                        </div>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                            data-bs-target="#addLiterasiModal">
                            <i class="fas fa-plus me-1"></i> Tambah Literasi
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($literasiEntries)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-pen-fancy fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada literasi</h5>
                                <p class="text-muted mb-4">Mulai dengan menambahkan literasi pertama Anda</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLiterasiModal">
                                    <i class="fas fa-plus me-1"></i> Tambah Literasi
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive" style="overflow-x:auto;">
                                <table class="table table-hover" style="min-width: 700px;">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Judul Buku</th>
                                            <th>Penulis</th>
                                            <th>Penerbit</th>
                                            <th>Isi Literasi</th>
                                            <th>Tanggal</th>
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
                                                <td class="text-truncate" style="max-width: 300px;"
                                                    title="<?php echo htmlspecialchars($entry['content']); ?>">
                                                    <?php echo nl2br(htmlspecialchars(substr($entry['content'], 0, 50))); ?>...
                                                </td>
                                                <td><?php echo date("d/m/Y", strtotime($entry['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-outline-primary edit-btn"
                                                            data-id="<?php echo $entry['id']; ?>" data-bs-toggle="modal"
                                                            data-bs-target="#editModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger delete-btn"
                                                            data-id="<?php echo $entry['id']; ?>" data-bs-toggle="modal"
                                                            data-bs-target="#deleteModal">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary view-btn"
                                                            data-id="<?php echo $entry['id']; ?>" data-bs-toggle="modal"
                                                            data-bs-target="#detailModal">
                                                            <i class="fas fa-eye"></i>
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

    <!-- Add Literasi Modal -->
    <div class="modal fade" id="addLiterasiModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i> Tambah Literasi Baru
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Judul Buku</label>
                            <select class="form-select" id="title" name="title" required>
                                <option value="">Pilih Judul Buku</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-author="<?php echo htmlspecialchars($book['author']); ?>"
                                        data-publisher="<?php echo htmlspecialchars($book['publisher']); ?>">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="author" class="form-label">Penulis</label>
                                <input type="text" class="form-control" id="author" name="author" readonly required />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="publisher" class="form-label">Penerbit</label>
                                <input type="text" class="form-control" id="publisher" name="publisher" readonly
                                    required />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Isi Literasi</label>
                            <textarea class="form-control" id="content" name="content" rows="8" required></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Literasi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i> Edit Literasi
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <input type="hidden" id="edit_id" name="id" />
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Judul Buku</label>
                            <select class="form-select" id="edit_title" name="title" required>
                                <option value="">Pilih Judul Buku</option>
                                <?php foreach ($books as $book): ?>
                                    <option value="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-author="<?php echo htmlspecialchars($book['author']); ?>"
                                        data-publisher="<?php echo htmlspecialchars($book['publisher']); ?>">
                                        <?php echo htmlspecialchars($book['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_author" class="form-label">Penulis</label>
                                <input type="text" class="form-control" id="edit_author" name="author" readonly
                                    required />
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_publisher" class="form-label">Penerbit</label>
                                <input type="text" class="form-control" id="edit_publisher" name="publisher" readonly
                                    required />
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Isi Literasi</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="8"
                                required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="saveEdit">
                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-user-pen me-2"></i> Penulis:</strong> <span
                                    id="detailAuthor"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><i class="fas fa-building me-2"></i> Penerbit:</strong> <span
                                    id="detailPublisher"></span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="fw-bold">Isi Literasi:</h6>
                        <div id="detailContent" class="p-3 bg-light rounded"></div>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-calendar-day me-1"></i> Dibuat pada: <span id="detailDate"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> Konfirmasi Hapus
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus literasi ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <p class="fw-bold" id="deleteTitle"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash me-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PDF Modal -->
    <div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh; overflow-y: auto; background-color: #525659;">
                    <div id="loading" class="text-center text-white pt-5">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Memuat Dokumen...</p>
                    </div>
                    <div id="pdf-container" class="d-flex flex-column align-items-center py-4"></div>
                </div>
                <div class="modal-footer justify-content-between bg-light">
                    <div id="pdf-controls" style="display: none;" class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="prevBtn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="small fw-bold">Halaman <span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="nextBtn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <a id="downloadPdf" href="#" class="btn btn-primary" download>
                            <i class="fas fa-download me-1"></i> Unduh PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Link Modal -->
    <div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="height: 80vh;">
                    <iframe id="linkFrame" src="" style="width: 100%; height: 100%;" frameborder="0"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a id="openInNewTab" href="#" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Buka di Tab Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Book selection for both forms
        document.getElementById('title').addEventListener('change', function () {
            var selectedOption = this.options[this.selectedIndex];
            var author = selectedOption.getAttribute('data-author') || '';
            var publisher = selectedOption.getAttribute('data-publisher') || '';
            document.getElementById('author').value = author;
            document.getElementById('publisher').value = publisher;
        });

        document.getElementById('edit_title').addEventListener('change', function () {
            var selectedOption = this.options[this.selectedIndex];
            var author = selectedOption.getAttribute('data-author') || '';
            var publisher = selectedOption.getAttribute('data-publisher') || '';
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_publisher').value = publisher;
        });

        // PDF.js Logic
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                // Make canvas responsive
                canvas.style.maxWidth = '100%';
                canvas.style.height = 'auto';

                const renderTask = page.render({
                    canvasContext: ctx,
                    viewport: viewport
                });

                renderTask.promise.then(function () {
                    pageRendering = false;
                    const numDisplay = document.getElementById('page_num_display');
                    if (numDisplay) numDisplay.textContent = num;
                    
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        function onPrevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        function onNextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        async function previewPDF(fileUrl) {
             const container = document.getElementById('pdf-container');
             const loading = document.getElementById('loading');
             const controls = document.getElementById('pdf-controls');
             const downloadBtn = document.getElementById('downloadPdf');
             
             // Extract filename from URL (assuming ../uploads/books/filename.pdf)
             // We need to send just the filename or correct relative path to get_book_pdf.php
             // fileUrl is like "../uploads/books/book_xyz.pdf"
             // basename would be "book_xyz.pdf"
             const filename = fileUrl.split('/').pop();

             loading.style.display = 'block';
             loading.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2">Memuat Dokumen...</p>';
             container.innerHTML = '';
             controls.style.display = 'none';
             
             // Update download link
             if(downloadBtn) downloadBtn.href = fileUrl;

             container.appendChild(canvas);

             try {
                 // Fetch from local helper
                 const response = await fetch('get_book_pdf.php?file=' + encodeURIComponent(filename));
                 if (!response.ok) throw new Error('Gagal menghubungi server');

                 const json = await response.json();
                 if (json.error) throw new Error(json.error);

                 loading.innerText = 'Memproses data...';
                 const binaryString = atob(json.content);
                 const len = binaryString.length;
                 const bytes = new Uint8Array(len);
                 for (let i = 0; i < len; i++) {
                     bytes[i] = binaryString.charCodeAt(i);
                 }

                 const loadingTask = pdfjsLib.getDocument({ data: bytes });
                 pdfDoc = await loadingTask.promise;

                 document.getElementById('page_count').textContent = pdfDoc.numPages;
                 controls.style.display = 'flex';
                 loading.style.display = 'none';

                 document.getElementById('prevBtn').onclick = onPrevPage;
                 document.getElementById('nextBtn').onclick = onNextPage;

                 pageNum = 1;
                 renderPage(pageNum);

             } catch (err) {
                 console.error("PDF Load Error:", err);
                 loading.style.color = '#ff6b6b';
                 loading.innerHTML = '<i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>Gagal memuat PDF:<br>' + err.message;
             }
        }

        // Connect functions to Modal events
        var pdfModal = document.getElementById('pdfModal');
        pdfModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button) {
                var pdfSrc = button.getAttribute('data-pdf');
                previewPDF(pdfSrc);
            }
        });

        pdfModal.addEventListener('hidden.bs.modal', function () {
            // Cleanup
            if (ctx && canvas.width > 0) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
            document.getElementById('pdf-container').innerHTML = '';
        });

        // Edit modal
        var editButtons = document.querySelectorAll('.edit-btn');
        editButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                fetch('get_literasi.php?id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var literasi = data.literasi;
                            document.getElementById('edit_id').value = literasi.id;
                            document.getElementById('edit_title').value = literasi.title;
                            document.getElementById('edit_author').value = literasi.author;
                            document.getElementById('edit_publisher').value = literasi.publisher;
                            document.getElementById('edit_content').value = literasi.content;
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Gagal mengambil data literasi.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat mengambil data.'
                        });
                    });
            });
        });

        // Save edit
        document.getElementById('saveEdit').addEventListener('click', function () {
            var formData = new FormData(document.getElementById('editForm'));
            fetch('edit_literasi.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: 'Literasi berhasil diperbarui!',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Gagal menyimpan perubahan.'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan saat menyimpan data.'
                    });
                });
        });

        // Delete modal
        var deleteId = null;
        var deleteButtons = document.querySelectorAll('.delete-btn');
        deleteButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                deleteId = this.getAttribute('data-id');
                var title = this.closest('tr').querySelector('td:nth-child(2)').textContent;
                document.getElementById('deleteTitle').textContent = title;
            });
        });

        document.getElementById('confirmDelete').addEventListener('click', function () {
            if (deleteId) {
                fetch('delete_literasi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({ id: deleteId }).toString()
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: 'Literasi berhasil dihapus!',
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Gagal menghapus literasi.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat menghapus data.'
                        });
                    });
            }
        });

        // Detail modal
        var viewButtons = document.querySelectorAll('.view-btn, [data-bs-target="#detailModal"]');
        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                fetch('get_literasi.php?id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            var literasi = data.literasi;
                            document.getElementById('detailTitle').textContent = literasi.title;
                            document.getElementById('detailAuthor').textContent = literasi.author;
                            document.getElementById('detailPublisher').textContent = literasi.publisher;
                            document.getElementById('detailContent').innerHTML = literasi.content.replace(/\n/g, '<br>');
                            document.getElementById('detailDate').textContent = new Date(literasi.created_at).toLocaleString('id-ID', {
                                timeZone: 'Asia/Jakarta',
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Gagal mengambil detail literasi.'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Terjadi kesalahan saat mengambil data.'
                        });
                    });
            });
        });

        // Auto-focus content textarea when add modal is shown
        var addLiterasiModal = document.getElementById('addLiterasiModal');
        if (addLiterasiModal) {
            addLiterasiModal.addEventListener('shown.bs.modal', function () {
                document.getElementById('content').focus();
            });
        }
    </script>
    <script>
        // Handle dropdown item click for viewing PDF
        document.querySelectorAll('.view-pdf').forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                var pdfSrc = this.getAttribute('data-pdf');
                var pdfModalEl = document.getElementById('pdfModal');
                var pdfModal = bootstrap.Modal.getOrCreateInstance(pdfModalEl);
                
                previewPDF(pdfSrc);
                pdfModal.show();
            });
        });

        // Handle click events on elements with the "open-link" class
        document.querySelectorAll('.open-link').forEach(function (element) {
            element.addEventListener('click', function (event) {
                event.preventDefault();
                var link = this.getAttribute('data-link');
                if (link) {
                    // Set the iframe's src to the link
                    document.getElementById('linkFrame').src = link;
                    // Also update the 'Buka di Tab Baru' button's href
                    document.getElementById('openInNewTab').href = link;
                    // Show the link modal
                    var linkModal = new bootstrap.Modal(document.getElementById('linkModal'));
                    linkModal.show();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Link tidak valid.'
                    });
                }
            });
        });

        // Clear iframe when link modal is closed
        var linkModal = document.getElementById('linkModal');
        linkModal.addEventListener('hidden.bs.modal', function () {
            document.getElementById('linkFrame').src = '';
        });
    </script>
</body>

</html>