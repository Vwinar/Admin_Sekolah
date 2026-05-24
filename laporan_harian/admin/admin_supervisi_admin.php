<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$msg = '';
$view_action = $_GET['action'] ?? 'list'; // list, add, edit
$edit_id = $_GET['id'] ?? null;

// Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'save_supervision') {
        // Collect Scores
        $scores = [];
        $total_score = 0;
        for ($i = 1; $i <= 12; $i++) {
            $score = intval($_POST["score_$i"] ?? 0);
            $scores[$i] = $score;
            $total_score += $score;
        }
        $final_score = ($total_score / 48) * 100;
        $json_scores = json_encode($scores);

        // Determine Predicate
        if ($final_score >= 90)
            $predicate = 'Sangat Baik';
        elseif ($final_score >= 75)
            $predicate = 'Baik';
        elseif ($final_score >= 60)
            $predicate = 'Cukup';
        else
            $predicate = 'Perlu Perbaikan';

        $data = [
            $_POST['teacher_name'],
            $_POST['nuptk'],
            $_POST['subject'],
            $_POST['class'],
            $_POST['semester'],
            $_POST['academic_year'],
            $_POST['supervision_date'],
            $_POST['supervisor_name'],
            $json_scores, // item_scores
            $total_score,
            $final_score,
            $predicate,
            $_POST['strengths'],
            $_POST['weaknesses'],
            $_POST['recommendations'],
            $_POST['deadline_date']
        ];

        if (empty($_POST['id'])) {
            // INSERT
            $stmt = $db->prepare("INSERT INTO school_supervisi_admin 
                (teacher_name, nuptk, subject, class, semester, academic_year, supervision_date, supervisor_name, item_scores, 
                 total_score, final_score, predicate, strengths, weaknesses, recommendations, deadline_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($data);
            $msg = "Data Supervisi berhasil disimpan.";
        } else {
            // UPDATE
            $data[] = $_POST['id'];
            $stmt = $db->prepare("UPDATE school_supervisi_admin SET 
                teacher_name=?, nuptk=?, subject=?, class=?, semester=?, academic_year=?, supervision_date=?, supervisor_name=?, item_scores=?, 
                total_score=?, final_score=?, predicate=?, strengths=?, weaknesses=?, recommendations=?, deadline_date=? 
                WHERE id=?");
            $stmt->execute($data);
            $msg = "Data Supervisi berhasil diperbarui.";
        }

        // Redirect to list
        header("Location: admin_supervisi_admin.php?msg=" . urlencode($msg));
        exit;

    } elseif ($post_action === 'delete') {
        $stmt = $db->prepare("DELETE FROM school_supervisi_admin WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header("Location: admin_supervisi_admin.php?msg=" . urlencode("Data berhasil dihapus."));
        exit;

    } elseif ($post_action === 'upload_doc') {
        $id = $_POST['id'];
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $target_dir = "../uploads/supervisi/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["document"]["name"], PATHINFO_EXTENSION);
            $new_filename = "supervisi_" . $id . "_" . date("YmdHis") . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            // Get old file to delete if exists
            $stmt_old = $db->prepare("SELECT document_path FROM school_supervisi_admin WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_file = $stmt_old->fetchColumn();

            if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                if ($old_file && file_exists($old_file)) {
                    unlink($old_file);
                }
                $stmt = $db->prepare("UPDATE school_supervisi_admin SET document_path = ? WHERE id = ?");
                $stmt->execute([$target_file, $id]);
                $msg = "Dokumen berhasil diupload.";
            } else {
                $msg = "Gagal upload file.";
            }
        }
        header("Location: admin_supervisi_admin.php?msg=" . urlencode($msg));
        exit;
    }
}

// Display messages from redirect
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Fetch Common Data (Teachers, Subjects, Classes)
$teachers = $db->query("SELECT full_name, nip, subject, assigned_class FROM users WHERE role = 'guru' ORDER BY full_name")->fetchAll();
$subjects = [];
try {
    $stmt_sub = $db->query("SELECT name FROM subjects ORDER BY name");
    $subjects = $stmt_sub->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
}
if (empty($subjects))
    $subjects = ['Tematik (Guru Kelas)', 'PAI & Budi Pekerti', 'Pendidikan Pancasila', 'Bahasa Indonesia', 'Matematika', 'IPAS', 'Seni Budaya', 'PJOK', 'Bahasa Inggris', 'Muatan Lokal'];

$classes = [];
try {
    $stmt_cls = $db->query("SELECT name FROM classes ORDER BY name");
    $classes = $stmt_cls->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
}
if (empty($classes))
    $classes = ['1', '2', '3', '4', '5', '6'];

// Check table existence
try {
    $db->query("SELECT 1 FROM school_supervisi_admin LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false) {
        $db->exec("CREATE TABLE IF NOT EXISTS school_supervisi_admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            teacher_name TEXT,
            nuptk TEXT,
            subject TEXT,
            class TEXT,
            semester TEXT,
            academic_year TEXT,
            supervision_date TEXT,
            supervisor_name TEXT,
            item_scores TEXT,
            total_score INTEGER,
            final_score REAL,
            predicate TEXT,
            strengths TEXT,
            weaknesses TEXT,
            recommendations TEXT,
            deadline_date TEXT,
            document_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

// Fetch Data for List View
$rows = [];
if ($view_action === 'list') {
    $stmt = $db->query("SELECT * FROM school_supervisi_admin ORDER BY supervision_date DESC");
    $rows = $stmt->fetchAll();
}

// Fetch Data for Edit View
$edit_data = null;
if ($view_action === 'edit' && $edit_id) {
    $stmt = $db->prepare("SELECT * FROM school_supervisi_admin WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
    if (!$edit_data) {
        header("Location: admin_supervisi_admin.php");
        exit;
    }
}

// Prepare Admin Data for Print
$admin_data = $db->query("SELECT full_name, nip FROM users WHERE id = {$_SESSION['user_id']}")->fetch();

// Components Data
$components = [
    1 => ['title' => 'PROGRAM TAHUNAN (PROTA)', 'crit' => 'Kesesuaian Kalender, Alokasi Waktu, KD Esensial.'],
    2 => ['title' => 'PROGRAM SEMESTER (PROSEM)', 'crit' => 'Penjabaran Prota, Distribusi Waktu Mingguan/Bulanan.'],
    3 => ['title' => 'SILABUS / ATP', 'crit' => 'Identitas, KD/CP, Materi, Kegiatan, Penilaian.'],
    4 => ['title' => 'RPP / MODUL AJAR', 'crit' => 'Tujuan, Langkah Pembelajaran, Asesmen, Integrasi 4C.'],
    5 => ['title' => 'BUKU JURNAL / AGENDA', 'crit' => 'Rutin, Catatan Kejadian, Rekap Harian.'],
    6 => ['title' => 'DAFTAR NILAI', 'crit' => 'Nilai Pengetahuan, Keterampilan, Sikap, Up to date.'],
    7 => ['title' => 'ANALISIS PENILAIAN', 'crit' => 'Analisis butir soal, Daya beda, Ketuntasan.'],
    8 => ['title' => 'PROGRAM REMEDIAL & PENGAYAAN', 'crit' => 'Daftar siswa, Soal/Materi, Bukti pelaksanaan.'],
    9 => ['title' => 'DAFTAR HADIR SISWA', 'crit' => 'Isi tertib, Rekap bulanan, Keterangan jelas.'],
    10 => ['title' => 'KISI-KISI & BANK SOAL', 'crit' => 'Sesuai Indikator, Arsip Bank Soal Rapi.'],
    11 => ['title' => 'CATATAN PRIBADI SISWA', 'crit' => 'Jurnal Sikap, Anekdot, Bukti layanan/komunikasi.'],
    12 => ['title' => 'KERAPIAN DOKUMEN', 'crit' => 'Dokumen terkini, Rapi dalam map/binder.']
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisi Administrasi Guru</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        /* Shared Styles */
        .form-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .form-section h3 {
            font-size: 1.1rem;
            margin-top: 0;
            margin-bottom: 1rem;
            color: var(--primary);
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }

        .score-row {
            display: grid;
            grid-template-columns: 50px 2fr 3fr 150px;
            gap: 1rem;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .score-row:last-child {
            border-bottom: none;
        }

        .score-header {
            font-weight: 700;
            background: #eef2ff;
            padding: 0.5rem;
            border-radius: 6px;
        }

        /* PDF Modal Styles from Managedata */
        #pdfModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.85);
        }

        .modal-content-pdf {
            background-color: #383838;
            margin: 2% auto;
            padding: 0;
            width: 80%;
            height: 90%;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }

        .modal-header-pdf {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2b2b2b;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .modal-body-pdf {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #383838;
        }

        canvas {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            max-width: 100%;
        }

        /* Desktop: Proper button sizing */
        @media (min-width: 769px) {
            .header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                flex-direction: row !important;
            }

            .header>div {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.5rem !important;
            }

            .btn {
                padding: 0.5rem 1rem !important;
                font-size: 0.9375rem !important;
                min-width: auto !important;
                max-width: none !important;
                width: auto !important;
            }

            .btn i {
                font-size: 1rem !important;
                margin-right: 0.25rem !important;
            }

            .btn span {
                display: inline !important;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {

            /* Main content */
            .main-content {
                padding: 1rem !important;
            }

            /* Overall text smaller on mobile */
            body {
                font-size: 0.8rem !important;
            }

            /* Horizontal Header for Mobile */
            .header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.5rem !important;
                margin-bottom: 0 !important;
                width: 100% !important;
                padding: 0.75rem !important;
                height: auto !important;
                min-height: 3rem !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 100 !important;
                background: white !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            }

            /* Add padding-top to main-content to compensate for fixed header */
            .main-content {
                padding: 1rem !important;
                padding-top: 5rem !important;
            }

            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.6rem !important;
                flex: 1 !important;
                min-width: 0 !important;
            }

            .header-title {
                display: flex !important;
                flex-direction: column !important;
                min-width: 0 !important;
                justify-content: center !important;
            }

            .header h1 {
                font-size: 1rem !important;
                font-weight: 700 !important;
                margin: 0 !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .header p {
                font-size: 0.7rem !important;
                margin: 0 !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                color: var(--text-muted) !important;
            }

            .header-actions {
                display: flex !important;
                flex-direction: row !important;
                gap: 0.4rem !important;
                flex-shrink: 0 !important;
                align-items: center !important;
            }

            /* Sidebar toggle adjustments */
            .sidebar-toggle {
                width: 2rem !important;
                height: 2rem !important;
                padding: 0.4rem !important;
                flex-shrink: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-around !important;
                margin: 0 !important;
            }

            .sidebar-toggle span {
                height: 2px !important;
            }

            /* Button responsiveness - icon only */
            .btn {
                padding: 0.4rem !important;
                height: 2.25rem !important;
                width: 2.25rem !important;
                min-width: 2.25rem !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 0.5rem !important;
            }

            .btn i {
                margin: 0 !important;
                font-size: 1rem !important;
            }

            .btn span {
                display: none !important;
            }

            /* Form adjustments */
            .form-section {
                padding: 1rem !important;
            }

            .form-section h3 {
                font-size: 0.9rem !important;
                margin-bottom: 0.75rem !important;
            }

            /* Keep Section A (Data Guru) in 2 columns on mobile */
            .form-section:first-of-type>div[style*="grid-template-columns: 1fr 1fr"] {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 0.75rem !important;
            }

            /* All other grids become single column */
            .form-section:not(:first-of-type)>div[style*="grid"] {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
            }

            .score-row {
                display: block !important;
                padding: 0.75rem !important;
                margin-bottom: 0.75rem !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                background: white !important;
            }

            .score-header {
                display: grid !important;
                grid-template-columns: 40px 1fr 1fr 60px !important;
                gap: 0.5rem !important;
                font-weight: 700 !important;
                background: #eef2ff !important;
                padding: 0.5rem !important;
                font-size: 0.75rem !important;
                text-align: center !important;
                border-radius: 6px !important;
                margin-bottom: 0.5rem !important;
                position: sticky !important;
                top: 5rem !important;
                z-index: 10 !important;
            }

            .score-row:not(.score-header) {
                display: grid !important;
                grid-template-columns: 40px 1fr auto !important;
                gap: 0.5rem !important;
                align-items: start !important;
            }

            .score-row:not(.score-header) > div:first-child {
                font-weight: 700 !important;
                color: var(--primary) !important;
                text-align: center !important;
                padding-top: 0.25rem !important;
            }

            .score-row:not(.score-header) select {
                width: 60px !important;
                padding: 0.375rem !important;
                font-size: 0.875rem !important;
                text-align: left !important;
            }

            .score-row strong {
                display: block !important;
                font-size: 0.875rem !important;
                font-weight: 600 !important;
                color: #1e293b !important;
                margin-bottom: 0.25rem !important;
                line-height: 1.3 !important;
            }

            .score-row small {
                display: block !important;
                font-size: 0.75rem !important;
                color: #64748b !important;
                line-height: 1.4 !important;
            }

            .score-row>div {
                padding: 0.25rem 0 !important;
            }

            .form-control,
            .form-label {
                font-size: 0.875rem !important;
            }

            .form-group {
                margin-bottom: 0.75rem !important;
            }

            /* Form action buttons - keep in one row on mobile */
            .form-actions {
                display: flex !important;
                flex-direction: row !important;
                gap: 0.75rem !important;
                justify-content: flex-end !important;
                margin-top: 1.5rem !important;
            }

            .form-actions .btn {
                flex: 1 !important;
                max-width: none !important;
                width: auto !important;
                padding: 0.75rem 1rem !important;
                height: auto !important;
                min-width: 0 !important;
                font-size: 0.875rem !important;
            }

            .form-actions .btn i {
                margin-right: 0.25rem !important;
                font-size: 0.875rem !important;
            }

            .form-actions .btn span,
            .form-actions .btn:not(:has(i)) {
                display: inline !important;
            }
        }

        @media print {
            .no-print {
                display: none;
            }

            .card {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <!-- LIST VIEW -->
            <?php if ($view_action === 'list'): ?>
                <header class="header">
                    <div class="header-left">
                        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                        <div class="header-title">
                            <h1>Supervisi Administrasi Guru</h1>
                            <p>Evaluasi Kelengkapan Administrasi Guru</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                        <a href="admin_supervisi_admin.php?action=add" class="btn btn-primary" title="Input Supervisi Baru">
                            <i class="bi bi-plus-lg"></i> <span>Input Supervisi</span>
                        </a>
                    </div>
                </header>

                <?php if ($msg): ?>
                    <div class="card" style="margin-bottom: 1rem; background: #d1fae5; color: #065f46; padding: 1rem;">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <div class="card">

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Nama Guru</th>
                                    <th>Mapel/Kelas</th>
                                    <th>Skor</th>
                                    <th>Predikat</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align:center;">Belum ada data supervisi.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $i => $row): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($row['supervision_date']) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($row['teacher_name']) ?></strong><br>
                                                <small class="text-muted">NIP: <?= htmlspecialchars($row['nuptk']) ?></small>
                                            </td>
                                            <td><?= htmlspecialchars($row['subject']) ?> / <?= htmlspecialchars($row['class']) ?>
                                            </td>
                                            <td><?= number_format($row['final_score'], 1) ?></td>
                                            <td><span class="badge"
                                                    style="background: #e0f2fe; color: #0369a1;"><?= $row['predicate'] ?></span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <?php if (!empty($row['document_path']) && file_exists($row['document_path'])): ?>
                                                        <button class="btn btn-sm btn-info"
                                                            onclick="viewPdf('<?= htmlspecialchars($row['document_path']) ?>')"
                                                            title="Lihat PDF"><i class="bi bi-eye"></i></button>
                                                        <a href="<?= htmlspecialchars($row['document_path']) ?>" download
                                                            class="btn btn-sm btn-success" title="Download"><i
                                                                class="bi bi-download"></i></a>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-warning"
                                                        onclick="openUploadModal(<?= $row['id'] ?>)" title="Upload Dokumen"><i
                                                            class="bi bi-upload"></i></button>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="admin_supervisi_admin.php?action=edit&id=<?= $row['id'] ?>"
                                                    class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></a>
                                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id'] ?>)"><i
                                                        class="bi bi-trash"></i></button>
                                                <button class="btn btn-sm btn-info" onclick='printData(<?= json_encode($row) ?>)'><i
                                                        class="bi bi-printer"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FORM VIEW (ADD/EDIT) -->
            <?php elseif ($view_action === 'add' || $view_action === 'edit'): ?>
                <header class="header">
                    <div class="header-left">
                        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                        <div class="header-title">
                            <h1><?= $view_action === 'edit' ? 'Edit Data Supervisi' : 'Form Instrumen Supervisi' ?></h1>
                            <p>Evaluasi Kelengkapan Administrasi Guru</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="admin_supervisi_admin.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                    </div>
                </header>

                <?php if ($msg): ?>
                    <div class="card" style="margin-bottom: 1rem; background: #d1fae5; color: #065f46; padding: 1rem;">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <div class="card">

                    <?php
                    // Prepare form data
                    $d = $edit_data;
                    $scores = $d ? json_decode($d['item_scores'], true) : [];
                    ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="save_supervision">
                        <?php if ($d): ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><?php endif; ?>

                        <!-- A. DATA GURU -->
                        <div class="form-section">
                            <h3>A. Data Guru & Supervisi</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label class="form-label">Nama Guru</label>
                                    <select name="teacher_name" id="teacher_name" class="form-control" required onchange="updateTeacherInfo(this)">
                                        <option value="">-- Pilih Guru --</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= htmlspecialchars($t['full_name']) ?>"
                                                data-nip="<?= htmlspecialchars($t['nip']) ?>"
                                                data-subject="<?= htmlspecialchars($t['subject'] ?? '') ?>"
                                                data-class="<?= htmlspecialchars($t['assigned_class'] ?? '') ?>"
                                                <?= ($d && $d['teacher_name'] == $t['full_name']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($t['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">NIP</label>
                                    <input type="text" name="nuptk" id="nuptk" class="form-control" readonly
                                        style="background: #f1f5f9;" value="<?= $d['nuptk'] ?? '' ?>">
                                </div>
                                <div>
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="subject" id="subject" class="form-control" required>
                                        <option value="">-- Pilih Mapel --</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= htmlspecialchars($s) ?>" <?= ($d && $d['subject'] == $s) ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Kelas</label>
                                    <select name="class" id="class" class="form-control" required>
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= htmlspecialchars($c) ?>" <?= ($d && $d['class'] == $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Semester</label>
                                    <select name="semester" class="form-control">
                                        <option value="Ganjil" <?= ($d && $d['semester'] == 'Ganjil') ? 'selected' : '' ?>>
                                            Ganjil</option>
                                        <option value="Genap" <?= ($d && $d['semester'] == 'Genap') ? 'selected' : '' ?>>Genap
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Tahun Pelajaran</label>
                                    <input type="text" name="academic_year" class="form-control"
                                        value="<?= $d['academic_year'] ?? (date('Y') . '/' . (date('Y') + 1)) ?>">
                                </div>
                                <div>
                                    <label class="form-label">Tanggal Supervisi</label>
                                    <input type="date" name="supervision_date" class="form-control" required
                                        value="<?= $d['supervision_date'] ?? date('Y-m-d') ?>">
                                </div>
                                <div>
                                    <label class="form-label">Supervisor</label>
                                    <input type="text" name="supervisor_name" class="form-control"
                                        value="<?= $d['supervisor_name'] ?? 'Kepala Sekolah' ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- B. INSTRUMEN -->
                        <div class="form-section">
                            <h3>B. Instrumen Penilaian (Skala 1 - 4)</h3>
                            <div class="score-row score-header">
                                <div>No</div>
                                <div>Komponen Administrasi</div>
                                <div>Kriteria Penilaian</div>
                                <div>Skor</div>
                            </div>
                            <?php foreach ($components as $no => $comp): ?>
                                <div class="score-row">
                                    <div><b><?= $no ?>.</b></div>
                                    <div>
                                        <strong><?= $comp['title'] ?></strong>
                                        <small><?= $comp['crit'] ?></small>
                                    </div>
                                    <div>
                                        <select name="score_<?= $no ?>" class="form-control" required>
                                            <option value="4" <?= (isset($scores[$no]) && $scores[$no] == 4) ? 'selected' : '' ?>>4
                                                - Sangat Lengkap</option>
                                            <option value="3" <?= (isset($scores[$no]) && $scores[$no] == 3) ? 'selected' : '' ?>>3
                                                - Lengkap</option>
                                            <option value="2" <?= (isset($scores[$no]) && $scores[$no] == 2) ? 'selected' : '' ?>>2
                                                - Kurang Lengkap</option>
                                            <option value="1" <?= (isset($scores[$no]) && $scores[$no] == 1) ? 'selected' : '' ?>>1
                                                - Tidak Lengkap</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- C. CATATAN -->
                        <div class="form-section">
                            <h3>C. Catatan & Rekomendasi</h3>
                            <div class="form-group" style="margin-bottom:1rem;">
                                <label class="form-label">1. Kekuatan / Kelebihan</label>
                                <textarea name="strengths" class="form-control"
                                    rows="2"><?= $d['strengths'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:1rem;">
                                <label class="form-label">2. Kelemahan / Kekurangan</label>
                                <textarea name="weaknesses" class="form-control"
                                    rows="2"><?= $d['weaknesses'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group" style="margin-bottom:1rem;">
                                <label class="form-label">3. Tindak Lanjut / Rekomendasi</label>
                                <textarea name="recommendations" class="form-control"
                                    rows="2"><?= $d['recommendations'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Batas Waktu Perbaikan</label>
                                <input type="date" name="deadline_date" class="form-control"
                                    value="<?= $d['deadline_date'] ?? '' ?>">
                            </div>
                        </div>

                        <div class="form-actions"
                            style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                            <a href="admin_supervisi_admin.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Data</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- UTILITY MODALS & SCRIPTS -->

    <!-- DELETE MODAL -->
    <div id="deleteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i class="bi bi-exclamation-circle" style="font-size: 3rem; color: #ef4444;"></i>
            <h3>Hapus Data?</h3>
            <p>Data yang dihapus tidak dapat dikembalikan.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('deleteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <!-- UPLOAD MODAL -->
    <div id="uploadModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 500px;">
            <h3>Upload Dokumen Supervisi</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_doc">
                <input type="hidden" name="id" id="uploadId">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <input type="file" name="document" class="form-control" required
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('uploadModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF VIEWER MODAL -->
    <div id="pdfModal">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <h3>Lihat Dokumen</h3>
                <span onclick="closePdfModal()" style="cursor:pointer; font-size:24px;">&times;</span>
            </div>
            <div id="pdf-controls" style="background:#444; padding:10px; display:none; text-align:center; color:white;">
                <button type="button" class="btn btn-sm btn-secondary" id="prevBtn"><i
                        class="bi bi-chevron-left"></i></button>
                <span style="margin:0 15px;">Halaman <span id="page_num"></span> / <span id="page_count"></span></span>
                <button type="button" class="btn btn-sm btn-secondary" id="nextBtn"><i
                        class="bi bi-chevron-right"></i></button>
            </div>
            <div class="modal-body-pdf">
                <div id="loading">Memuat Dokumen...</div>
                <canvas id="pdf-canvas"></canvas>
            </div>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Sidebar Toggle - Enhanced for Mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');

        // Check if mobile
        const isMobile = () => window.innerWidth <= 768;

        // Initialize sidebar state
        function initSidebar() {
            if (isMobile()) {
                // On mobile, start collapsed
                dashboardLayout.classList.add('sidebar-collapsed');
                if (sidebarToggle) sidebarToggle.classList.remove('active');
            } else {
                // On desktop, use saved state
                const sidebarState = localStorage.getItem('sidebarCollapsed');
                if (sidebarState === 'true') {
                    dashboardLayout.classList.add('sidebar-collapsed');
                    if (sidebarToggle) sidebarToggle.classList.add('active');
                } else {
                    dashboardLayout.classList.remove('sidebar-collapsed');
                    if (sidebarToggle) sidebarToggle.classList.remove('active');
                }
            }
        }

        // Toggle sidebar
        function toggleSidebar() {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            if (sidebarToggle) sidebarToggle.classList.toggle('active');

            // Only save state on desktop
            if (!isMobile()) {
                localStorage.setItem('sidebarCollapsed', dashboardLayout.classList.contains('sidebar-collapsed'));
            }
        }

        // Add click listeners
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        // Close sidebar when clicking overlay (mobile only)
        if (dashboardLayout) {
            dashboardLayout.addEventListener('click', function (e) {
                if (isMobile() && !dashboardLayout.classList.contains('sidebar-collapsed')) {
                    if (e.target === dashboardLayout) {
                        toggleSidebar();
                    }
                }
            });
        }

        // Initialize and handle resize
        initSidebar();
        window.addEventListener('resize', initSidebar);

        // Auto-fill teacher information when selecting teacher
        function updateTeacherInfo(select) {
            const selectedOption = select.options[select.selectedIndex];
            
            // Get data from selected option
            const nip = selectedOption.getAttribute('data-nip') || '';
            const subject = selectedOption.getAttribute('data-subject') || '';
            const classValue = selectedOption.getAttribute('data-class') || '';
            
            // Fill NIP field
            const nipField = document.getElementById('nuptk');
            if (nipField) {
                nipField.value = nip;
            }
            
            // Fill Subject field
            const subjectField = document.getElementById('subject');
            if (subjectField && subject) {
                // Find and select the matching option
                for (let i = 0; i < subjectField.options.length; i++) {
                    if (subjectField.options[i].value === subject) {
                        subjectField.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Fill Class field
            const classField = document.getElementById('class');
            if (classField && classValue) {
                // Find and select the matching option
                for (let i = 0; i < classField.options.length; i++) {
                    if (classField.options[i].value === classValue) {
                        classField.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function openUploadModal(id) {
            document.getElementById('uploadId').value = id;
            document.getElementById('uploadModal').style.display = 'block';
        }

        // PDF Logic (Matching admin_supervisi_akademik)
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas.getContext('2d');
        const loading = document.getElementById('loading');

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                const renderContext = { canvasContext: ctx, viewport: viewport };
                const renderTask = page.render(renderContext);
                renderTask.promise.then(function () {
                    pageRendering = false;
                    if (pageNumPending !== null) { renderPage(pageNumPending); pageNumPending = null; }
                });
            });
            document.getElementById('page_num').textContent = num;
        }

        function queueRenderPage(num) {
            if (pageRendering) pageNumPending = num;
            else renderPage(num);
        }

        function onPrevPage() { if (pageNum <= 1) return; pageNum--; queueRenderPage(pageNum); }
        function onNextPage() { if (pageNum >= pdfDoc.numPages) return; pageNum++; queueRenderPage(pageNum); }

        async function viewPdf(filename) {
            document.getElementById('pdfModal').style.display = 'block';
            loading.style.display = 'block';
            document.getElementById('pdf-controls').style.display = 'none';
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            try {
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
                if (!response.ok) throw new Error('Gagal menghubungi server');
                const json = await response.json();
                if (json.error) throw new Error(json.error);

                const binaryString = atob(json.content);
                const len = binaryString.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) bytes[i] = binaryString.charCodeAt(i);

                const loadingTask = pdfjsLib.getDocument({ data: bytes });
                pdfDoc = await loadingTask.promise;
                document.getElementById('page_count').textContent = pdfDoc.numPages;
                document.getElementById('pdf-controls').style.display = 'block';
                loading.style.display = 'none';
                document.getElementById('prevBtn').onclick = onPrevPage;
                document.getElementById('nextBtn').onclick = onNextPage;
                pageNum = 1;
                renderPage(pageNum);
            } catch (err) {
                console.error("PDF Load Error:", err);
                loading.innerText = 'Gagal memuat PDF: ' + err.message;
            }
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = 'none';
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        function printData(data) {
            // Simple Print Logic reusing Admin Data
            const printWindow = window.open('', '', 'width=800,height=600');
            let scores = {};
            try { scores = JSON.parse(data.item_scores); } catch (e) { }

            // Reconstruct table rows
            const components = <?= json_encode($components) ?>;
            let scoresHtml = '';
            let total = 0;

            for (let i = 1; i <= 12; i++) {
                let s = scores[i] || 0;
                total += parseInt(s);
                let comp = components[i];
                scoresHtml += `<tr>
                     <td style="border:1px solid #000; padding:5px; text-align:center;">${i}</td>
                     <td style="border:1px solid #000; padding:5px;"><b>${comp.title}</b><br><small>${comp.crit}</small></td>
                     <td style="border:1px solid #000; padding:5px; text-align:center;"><b>${s}</b></td>
                  </tr>`;
            }

            printWindow.document.write(`
                 <html><head><title>Supervisi ${data.teacher_name}</title>
                 <style>
                    body { font-family: 'Times New Roman', serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    td, th { border: 1px solid #000; padding: 5px; }
                    h2, p { text-align: center; margin: 5px 0; }
                 </style></head><body>
                     <h2>INSTRUMEN SUPERVISI ADMINISTRASI GURU</h2>
                     <p>Tahun Pelajaran ${data.academic_year}</p>
                     <br>
                     <table>
                         <tr><td>Nama: ${data.teacher_name}</td><td>NIP: ${data.nuptk}</td></tr>
                         <tr><td>Mapel: ${data.subject}</td><td>Kelas: ${data.class}</td></tr>
                         <tr><td>Tanggal: ${data.supervision_date}</td><td>Supervisor: ${data.supervisor_name}</td></tr>
                     </table>
                     <br>
                     <h3>HASIL PENILAIAN</h3>
                     <table>
                         <thead><tr><th width="5%">No</th><th>Komponen & Kriteria</th><th width="10%">Skor</th></tr></thead>
                         <tbody>${scoresHtml}</tbody>
                         <tfoot>
                             <tr><td colspan="2" align="right"><b>Total Skor</b></td><td align="center"><b>${total}</b></td></tr>
                             <tr><td colspan="2" align="right"><b>Nilai Akhir</b></td><td align="center"><b>${data.final_score.toFixed(1)}</b></td></tr>
                             <tr><td colspan="2" align="right"><b>Predikat</b></td><td align="center"><b>${data.predicate}</b></td></tr>
                         </tfoot>
                     </table>
                     <br>
                     <p><b>Catatan:</b><br>${data.strengths || '-'}<br>${data.weaknesses || '-'}<br>${data.recommendations || '-'}</p>
                     <script>window.print();<\/script>
                 </body></html>
             `);
            printWindow.document.close();
        }

        // Close modal if user clicks outside
        window.onclick = function (event) {
            if (event.target == document.getElementById('deleteModal')) document.getElementById('deleteModal').style.display = 'none';
            if (event.target == document.getElementById('uploadModal')) document.getElementById('uploadModal').style.display = 'none';
            if (event.target == document.getElementById('pdfModal')) closePdfModal();
        }
    </script>
</body>

</html>