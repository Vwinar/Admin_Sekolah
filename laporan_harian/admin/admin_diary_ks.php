<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Buku Harian Kepala Sekolah';
$msg = '';

// Ensure document_path column exists
try {
    $db->query("SELECT document_path FROM school_logs LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such column') !== false) {
        $db->exec("ALTER TABLE school_logs ADD COLUMN document_path TEXT");
    }
}

// Default School Identity (Fetch from Settings if possible, or static)
$settings = $db->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$default_school_name = $settings['school_name'] ?? 'Nama Sekolah';
$default_principal = $_SESSION['full_name'] ?? 'Kepala Sekolah';

// Fetch Default NIP
$stmtUser = $db->prepare("SELECT nip FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$uData = $stmtUser->fetch();
$default_nip = $uData['nip'] ?? '';

// ACTION HANDLER
$view_action = $_GET['action'] ?? 'list'; // list, add, edit
$edit_id = $_GET['id'] ?? null;

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_diary') {
        $date = $_POST['date'];

        // Construct JSON Data
        $diary_data = [
            'identity' => [
                'school_name' => $_POST['school_name'] ?? $default_school_name,
                'principal_name' => $_POST['principal_name'] ?? $default_principal,
                'principal_nip' => $_POST['principal_nip'] ?? $default_nip,
                'school_year' => $_POST['school_year'] ?? date('Y/Y+1'),
                'period' => $_POST['period'] ?? date('F Y')
            ],
            'todo_list' => [],
            'activity_log' => [],
            'findings' => [
                'findings' => $_POST['findings_text'] ?? '',
                'problems' => $_POST['problems_text'] ?? '',
                'ideas' => $_POST['ideas_text'] ?? '',
                'decisions' => $_POST['decisions_text'] ?? ''
            ],
            'follow_up' => [], // Will be array of strings
            'additional' => [
                'weekly_plan' => $_POST['weekly_plan'] ?? '',
                'weekly_eval' => $_POST['weekly_eval'] ?? '',
                'other_notes' => $_POST['other_notes'] ?? ''
            ]
        ];

        // Process To-Do List
        if (isset($_POST['todo_task'])) {
            foreach ($_POST['todo_task'] as $i => $task) {
                if (!empty($task)) {
                    $diary_data['todo_list'][] = [
                        'task' => $task,
                        'priority' => $_POST['todo_priority'][$i] ?? 'medium',
                        'status' => isset($_POST['todo_check'][$i]) ? 'done' : 'pending'
                    ];
                }
            }
        }

        // Process Activity Log
        if (isset($_POST['log_time'])) {
            foreach ($_POST['log_time'] as $i => $time) {
                if (!empty($_POST['log_desc'][$i])) {
                    $diary_data['activity_log'][] = [
                        'time' => $time,
                        'desc' => $_POST['log_desc'][$i],
                        'location' => $_POST['log_location'][$i] ?? '',
                        'parties' => $_POST['log_parties'][$i] ?? '',
                        'notes' => $_POST['log_notes'][$i] ?? ''
                    ];
                }
            }
        }

        // Process Follow Up
        if (isset($_POST['follow_up_item'])) {
            foreach ($_POST['follow_up_item'] as $item) {
                if (!empty($item)) {
                    $diary_data['follow_up'][] = $item;
                }
            }
        }

        $diary_content_json = json_encode($diary_data);

        // Common Fields
        $subject = "Catatan Harian KS - " . date('d M Y', strtotime($date));
        $details = "Catatan aktivitas harian.";
        $type = 'diary_ks';

        if (empty($_POST['id'])) {
            $stmt = $db->prepare("INSERT INTO school_logs (type, date, subject, details, diary_content, school_year) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$type, $date, $subject, $details, $diary_content_json, $_POST['school_year'] ?? '']);
            $msg = "Catatan harian berhasil disimpan.";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("UPDATE school_logs SET date = ?, subject = ?, details = ?, diary_content = ?, school_year = ? WHERE id = ?");
            $stmt->execute([$date, $subject, $details, $diary_content_json, $_POST['school_year'] ?? '', $id]);
            $msg = "Catatan harian berhasil diperbarui.";
        }

        header("Location: admin_diary_ks.php?msg=" . urlencode($msg));
        exit;

    } elseif ($action === 'upload_doc') {
        $id = $_POST['id'];
        $msg = "Gagal upload file.";

        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $target_dir = "../uploads/diary_ks/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["document"]["name"], PATHINFO_EXTENSION);
            $new_filename = "diary_ks_" . $id . "_" . date("YmdHis") . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            // Get old file to delete if exists
            $stmt_old = $db->prepare("SELECT document_path FROM school_logs WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_file = $stmt_old->fetchColumn();

            if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                // Delete old file if exists
                if ($old_file && file_exists($old_file)) {
                    unlink($old_file);
                }

                $stmt = $db->prepare("UPDATE school_logs SET document_path = ? WHERE id = ?");
                $stmt->execute([$target_file, $id]);
                $msg = "Dokumen berhasil diupload.";
            }
        }
        header("Location: admin_diary_ks.php?msg=" . urlencode($msg));
        exit;

    } elseif ($action === 'delete') {
        $id = $_POST['id'];

        // Delete associated document if exists
        $stmt_file = $db->prepare("SELECT document_path FROM school_logs WHERE id = ?");
        $stmt_file->execute([$id]);
        $doc_path = $stmt_file->fetchColumn();
        if ($doc_path && file_exists($doc_path)) {
            unlink($doc_path);
        }

        $stmt = $db->prepare("DELETE FROM school_logs WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_diary_ks.php?msg=" . urlencode("Data berhasil dihapus."));
        exit;
    }
}

// Display messages from redirect
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// FETCH DATA FOR LIST
$reports = [];
if ($view_action === 'list') {
    $stmt = $db->prepare("SELECT * FROM school_logs WHERE type = 'diary_ks' ORDER BY date DESC, id DESC");
    $stmt->execute();
    $reports = $stmt->fetchAll();
}

// FETCH DATA FOR EDIT
$edit_data = null;
if ($view_action === 'edit' && $edit_id) {
    $stmt = $db->prepare("SELECT * FROM school_logs WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
    if (!$edit_data) {
        header("Location: admin_diary_ks.php");
        exit;
    }
}

// Prepare Edit content
$d = $edit_data;
$content = $d ? json_decode($d['diary_content'], true) : [];

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        /* Custom Styles for Diary Form */
        .diary-form-section {
            margin-bottom: 2rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            background: #fcfcfc;
        }

        .diary-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
        }

        .diary-input-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .dynamic-row {
            display: flex;
            gap: 0.5rem;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .dynamic-row input,
        .dynamic-row select,
        .dynamic-row textarea {
            font-size: 0.9rem;
        }

        .btn-add-row {
            background: transparent;
            color: var(--primary-color);
            border: 1px dashed var(--primary-color);
            padding: 0.4rem 1rem;
            text-align: center;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-add-row:hover {
            background: #f0fdf4;
        }

        /* Tabs */
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        textarea.form-control {
            min-height: 80px;
        }

        /* PDF Viewer Modal */
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

        #loading {
            color: white;
        }

        /* Desktop Header Styles (Default) */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .header-title p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {

            /* Fixed Header for Mobile */
            .header {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 100 !important;
                background: white !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
                padding: 0.75rem !important;
                margin-bottom: 0 !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.5rem !important;
            }

            .main-content {
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

            .header-title h1 {
                font-size: 1rem !important;
                font-weight: 700 !important;
                margin: 0 !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .header-title p {
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

            /* Compact Tabs for Mobile - Fit in one line */
            .tab-buttons {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                margin-bottom: 0.75rem !important;
                padding-bottom: 0 !important;
                border-bottom: 1px solid #e5e7eb !important;
                gap: 2px !important;
            }

            .tab-btn {
                flex: 1 !important;
                /* Distribute space evenly */
                white-space: normal !important;
                /* Allow wrapping if needed */
                text-align: center !important;
                padding: 0.5rem 0.25rem !important;
                font-size: 0.7rem !important;
                /* Smaller font */
                line-height: 1.1 !important;
                flex-shrink: 1 !important;
                min-width: min-content !important;
                height: 100% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .tab-btn.active {
                border-bottom-width: 3px !important;
                color: var(--primary-color) !important;
                font-weight: 700 !important;
                background-color: rgba(0, 0, 0, 0.02) !important;
            }

            /* Tab 1 Layout Improvements for Mobile */
            .diary-input-group {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                /* Force 2 columns */
                gap: 0.5rem !important;
            }

            .diary-input-group>div {
                min-width: 0 !important;
            }

            .diary-form-section {
                padding: 1rem !important;
            }

            /* Input styling for dense layout */
            .form-control,
            .diary-input-group input,
            .diary-input-group select {
                font-size: 0.75rem !important;
                padding: 0.35rem 0.5rem !important;
            }

            .form-label {
                font-size: 0.7rem !important;
                margin-bottom: 0.2rem !important;
            }

            /* To-Do List Mobile Optimization */
            .dynamic-row {
                display: grid !important;
                grid-template-columns: 25px 1fr 80px 30px !important;
                /* Checkbox, Task, Priority, Delete */
                gap: 0.3rem !important;
                align-items: center !important;
                margin-bottom: 0.5rem !important;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 0.5rem;
            }

            .dynamic-row input[type="text"] {
                width: 100% !important;
                font-size: 0.8rem !important;
            }

            .dynamic-row select {
                width: 100% !important;
                font-size: 0.75rem !important;
                padding: 0.35rem 0.2rem !important;
            }

            .dynamic-row .btn-danger {
                padding: 0 !important;
                width: 1.75rem !important;
                height: 1.75rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 4px !important;
            }

            /* Replace text 'x' with icon visuals via CSS content if needed, or rely on font awesome if 'x' is just text */
            /* Since HTML has 'x', we just style the button container to be square */
            /* Tab 2: Activity Log Table Optimization */
            #tab2 .table-responsive {
                display: block !important;
                width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                margin-bottom: 1rem !important;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
            }

            #tab2 table {
                width: auto !important;
                /* Allow table to grow */
                min-width: 800px !important;
                /* Force min width to trigger scroll */
            }

            #tab2 th,
            #tab2 td {
                white-space: nowrap !important;
                padding: 0.5rem !important;
                vertical-align: middle !important;
            }

            /* Make inputs wider inside the scrollable table */
            #tab2 input.form-control {
                min-width: 120px !important;
                font-size: 0.85rem !important;
            }

            /* Specific column widths for better UX */
            #tab2 td:nth-child(1) input {
                min-width: 100px !important;
            }

            /* Waktu */
            #tab2 td:nth-child(2) input {
                min-width: 250px !important;
            }

            /* Uraian - Wider */
            #tab2 td:nth-child(3) input {
                min-width: 150px !important;
            }

            /* Lokasi */
            #tab2 td:nth-child(4) input {
                min-width: 150px !important;
            }

            /* Pihak */
            #tab2 td:nth-child(5) input {
                min-width: 150px !important;
            }

            /* Ket */

            #tab2 .btn-danger {
                padding: 0 !important;
                width: 2rem !important;
                height: 2rem !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle"><span></span><span></span><span></span></button>
                    <div class="header-title">
                        <h1><?= $page_title ?></h1>
                        <p>Catatan Harian, Rencana Kerja & Refleksi</p>
                    </div>
                </div>
                <?php if ($view_action === 'list'): ?>
                    <div class="header-actions">
                        <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                        <a href="admin_diary_ks.php?action=add" class="btn btn-primary" title="Tambah Catatan Baru">
                            <i class="bi bi-plus-lg"></i> <span>Tambah </span>
                        </a>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($msg): ?>
                <div class="card" style="margin-bottom: 1rem; background: #d1fae5; color: #065f46; padding: 1rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- LIST VIEW -->
            <?php if ($view_action === 'list'): ?>
                <div class="card">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Tahun/Periode</th>
                                    <th>Ringkasan Kegiatan</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($reports)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Belum ada catatan harian.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $row):
                                        $cnt = json_decode($row['diary_content'] ?? '{}', true);
                                        $summary = count($cnt['activity_log'] ?? []) . ' Kegiatan tercatat';
                                        if (count($cnt['todo_list'] ?? []) > 0)
                                            $summary .= ', ' . count($cnt['todo_list']) . ' Rencana';
                                        ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                                            <td><?= htmlspecialchars($row['school_year'] ?? '-') ?> <br> <small
                                                    class="text-muted"><?= htmlspecialchars($cnt['identity']['period'] ?? '') ?></small>
                                            </td>
                                            <td><?= $summary ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <?php if (!empty($row['document_path']) && file_exists($row['document_path'])): ?>
                                                        <button class="btn btn-sm btn-info btn-view-pdf"
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
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="btn btn-sm btn-info"
                                                        onclick='printDiary(<?= json_encode($row) ?>)' title="Cetak"><i
                                                            class="bi bi-printer"></i></button>
                                                    <a href="admin_diary_ks.php?action=edit&id=<?= $row['id'] ?>"
                                                        class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="openDeleteModal(<?= $row['id'] ?>)"><i
                                                            class="bi bi-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- FORM VIEW (ADD / EDIT) -->
            <?php elseif ($view_action === 'add' || $view_action === 'edit'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                        <h2><?= $view_action === 'edit' ? 'Edit Catatan Harian' : 'Catatan Harian Baru' ?></h2>
                        <a href="admin_diary_ks.php"><i class="bi bi-x-lg"></i> </a>
                    </div>

                    <form method="POST" id="diaryForm">
                        <input type="hidden" name="action" value="save_diary">
                        <?php if ($d): ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><?php endif; ?>

                        <!-- Tabs -->
                        <div class="tab-buttons">
                            <button type="button" class="tab-btn active" onclick="switchTab('tab1')">1. Identitas &
                                Rencana</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab2')">2. Log Aktivitas</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab3')">3. Temuan & Refleksi</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab4')">4. Mingguan/Bulanan</button>
                        </div>

                        <!-- TAB 1 -->
                        <div id="tab1" class="tab-content active">
                            <div class="diary-form-section">
                                <div class="diary-section-title"><i class="bi bi-card-heading"></i> Identitas</div>
                                <div class="diary-input-group">
                                    <div>
                                        <label class="form-label">Tanggal</label>
                                        <input type="date" name="date" class="form-control" required
                                            value="<?= $d['date'] ?? date('Y-m-d') ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Tahun Pelajaran</label>
                                        <input type="text" name="school_year" class="form-control"
                                            placeholder="Contoh: 2024/2025"
                                            value="<?= $d['school_year'] ?? ($content['identity']['school_year'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Periode</label>
                                        <input type="text" name="period" class="form-control" placeholder="Bulan/Minggu ke-"
                                            value="<?= $content['identity']['period'] ?? '' ?>">
                                    </div>
                                </div>
                                <div class="diary-input-group">
                                    <div>
                                        <label class="form-label">Nama Sekolah</label>
                                        <input type="text" name="school_name" class="form-control"
                                            value="<?= $content['identity']['school_name'] ?? $default_school_name ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Nama Kepala Sekolah</label>
                                        <input type="text" name="principal_name" class="form-control"
                                            value="<?= $content['identity']['principal_name'] ?? $default_principal ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">NIP Kepala Sekolah</label>
                                        <input type="text" name="principal_nip" class="form-control"
                                            value="<?= $content['identity']['principal_nip'] ?? $default_nip ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="diary-form-section">
                                <div class="diary-section-title"><i class="bi bi-list-check"></i> Rencana Kegiatan Harian
                                    (To-Do List)</div>
                                <div id="todoContainer"></div>
                                <button type="button" class="btn-add-row" onclick="addTodoRow()">+ Tambah Tugas</button>
                            </div>
                        </div>

                        <!-- TAB 2 -->
                        <div id="tab2" class="tab-content">
                            <div class="diary-form-section">
                                <div class="diary-section-title"><i class="bi bi-journal-text"></i> Catatan Kegiatan yang
                                    Dilaksanakan</div>
                                <div class="table-responsive">
                                    <table class="table table-bordered" style="width:100%">
                                        <thead>
                                            <tr style="background:#f1f5f9;">
                                                <th style="width:120px">Waktu</th>
                                                <th>Uraian Kegiatan</th>
                                                <th>Lokasi</th>
                                                <th>Pihak Terlibat</th>
                                                <th>Catatan/Ket.</th>
                                                <th style="width:50px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="activityLogBody"></tbody>
                                    </table>
                                    <button type="button" class="btn-add-row" onclick="addActivityRow()">+ Tambah
                                        Aktivitas</button>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3 -->
                        <div id="tab3" class="tab-content">
                            <div class="diary-input-group" style="grid-template-columns: 1fr 1fr;">
                                <div class="diary-form-section">
                                    <div class="diary-section-title"><i class="bi bi-search"></i> Temuan (Positif/Negatif)
                                    </div>
                                    <textarea name="findings_text" class="form-control"
                                        rows="4"><?= $content['findings']['findings'] ?? '' ?></textarea>
                                </div>
                                <div class="diary-form-section">
                                    <div class="diary-section-title"><i class="bi bi-exclamation-triangle"></i> Masalah /
                                        Kendala</div>
                                    <textarea name="problems_text" class="form-control"
                                        rows="4"><?= $content['findings']['problems'] ?? '' ?></textarea>
                                </div>
                            </div>
                            <div class="diary-input-group" style="grid-template-columns: 1fr 1fr;">
                                <div class="diary-form-section">
                                    <div class="diary-section-title"><i class="bi bi-lightbulb"></i> Ide / Solusi /
                                        Keputusan</div>
                                    <textarea name="ideas_text" class="form-control"
                                        rows="4"><?= $content['findings']['ideas'] ?? '' ?></textarea>
                                </div>
                                <div class="diary-form-section">
                                    <div class="diary-section-title"><i class="bi bi-hammer"></i> Keputusan Penting
                                        (Opsional)</div>
                                    <textarea name="decisions_text" class="form-control"
                                        rows="4"><?= $content['findings']['decisions'] ?? '' ?></textarea>
                                </div>
                            </div>
                            <div class="diary-form-section">
                                <div class="diary-section-title"><i class="bi bi-arrow-right-circle"></i> Tindak Lanjut
                                    Besok</div>
                                <div id="followUpContainer"></div>
                                <button type="button" class="btn-add-row" onclick="addFollowUpRow()">+ Tambah Tindak
                                    Lanjut</button>
                            </div>
                        </div>

                        <!-- TAB 4 -->
                        <div id="tab4" class="tab-content">
                            <div class="diary-form-section">
                                <div class="diary-section-title">Bagian Tambahan (Mingguan/Bulanan)</div>
                                <label class="form-label">Rencana Kerja Mingguan/Bulanan</label>
                                <textarea name="weekly_plan" class="form-control"
                                    rows="4"><?= $content['additional']['weekly_plan'] ?? '' ?></textarea>

                                <label class="form-label" style="margin-top:1rem;">Evaluasi Mingguan/Bulanan</label>
                                <textarea name="weekly_eval" class="form-control"
                                    rows="4"><?= $content['additional']['weekly_eval'] ?? '' ?></textarea>

                                <label class="form-label" style="margin-top:1rem;">Catatan Penting Lainnya</label>
                                <textarea name="other_notes" class="form-control"
                                    rows="4"><?= $content['additional']['other_notes'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <div
                            style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee; justify-content: flex-end;">
                            <a href="admin_diary_ks.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Data</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODALS -->

    <!-- Upload Modal -->
    <div id="uploadModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 500px;">
            <h3>Upload Dokumen</h3>
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

    <!-- PDF Viewer Modal -->
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i class="bi bi-exclamation-circle" style="font-size: 3rem; color: #dc2626;"></i>
            <h3 style="margin-bottom: 0.5rem; color: #1e293b;">Hapus Catatan?</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Data yang dihapus tidak dapat dikembalikan.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('deleteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
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

        // Tab Handling
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        // Dynamic Rows Logic
        function addTodoRow(task = '', priority = 'medium', checked = false) {
            const container = document.getElementById('todoContainer');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <input type="checkbox" name="todo_check[]" value="1" ${checked ? 'checked' : ''} style="margin-top:0.6rem;">
                <input type="text" name="todo_task[]" class="form-control" placeholder="Tugas..." value="${task}">
                <select name="todo_priority[]" class="form-control">
                    <option value="high" ${priority === 'high' ? 'selected' : ''}>Penting</option>
                    <option value="medium" ${priority === 'medium' ? 'selected' : ''}>Sedang</option>
                    <option value="low" ${priority === 'low' ? 'selected' : ''}>Biasa</option>
                    <option value="longterm" ${priority === 'longterm' ? 'selected' : ''}>Jangka Panjang</option>
                </select>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
            `;
            container.appendChild(div);
        }

        function addActivityRow(time = '', desc = '', loc = '', parties = '', notes = '') {
            const tbody = document.getElementById('activityLogBody');
            if (!tbody) return;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="log_time[]" class="form-control" placeholder="07.00-08.00" value="${time}"></td>
                <td><input type="text" name="log_desc[]" class="form-control" placeholder="Deskripsi..." value="${desc}"></td>
                <td><input type="text" name="log_location[]" class="form-control" placeholder="Tempat" value="${loc}"></td>
                <td><input type="text" name="log_parties[]" class="form-control" placeholder="Pihak Terlibat" value="${parties}"></td>
                <td><input type="text" name="log_notes[]" class="form-control" placeholder="Ket." value="${notes}"></td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="bi bi-x-lg"></i></button></td>
            `;
            tbody.appendChild(tr);
        }

        function addFollowUpRow(text = '') {
            const container = document.getElementById('followUpContainer');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'dynamic-row';
            div.innerHTML = `
                <span style="padding-top:0.5rem;">•</span>
                <input type="text" name="follow_up_item[]" class="form-control" placeholder="Tindak lanjut..." value="${text}" style="width:100%;">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="bi bi-x-lg"></i></button>
            `;
            container.appendChild(div);
        }

        // Initialize Data on Load (if in Edit/Add Mode)
        <?php if ($view_action === 'add' || $view_action === 'edit'): ?>
            document.addEventListener('DOMContentLoaded', function () {
                const content = <?= json_encode($content) ?>;

                // Init Todo
                if (content.todo_list && content.todo_list.length > 0) {
                    content.todo_list.forEach(item => addTodoRow(item.task, item.priority, item.status === 'done'));
                } else {
                    addTodoRow();
                    addTodoRow();
                }

                // Init Activity
                if (content.activity_log && content.activity_log.length > 0) {
                    content.activity_log.forEach(item => addActivityRow(item.time, item.desc, item.location, item.parties, item.notes));
                } else {
                    addActivityRow();
                    addActivityRow();
                }

                // Init Follow Up
                if (content.follow_up && content.follow_up.length > 0) {
                    content.follow_up.forEach(item => addFollowUpRow(item));
                } else {
                    addFollowUpRow();
                }
            });
        <?php endif; ?>

        // Setup Modals
        function openUploadModal(id) {
            document.getElementById('uploadId').value = id;
            document.getElementById('uploadModal').style.display = 'block';
        }

        function openDeleteModal(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'block'; // Corrected flex to block for consistency or use class
            document.getElementById('deleteModal').style.display = 'flex';
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('uploadModal')) document.getElementById('uploadModal').style.display = 'none';
            if (event.target == document.getElementById('deleteModal')) document.getElementById('deleteModal').style.display = 'none';
            if (event.target == document.getElementById('pdfModal')) closePdfModal();
        }

        function printDiary(data) {
            const printWindow = window.open('', '_blank');
            if (!printWindow) return alert('Pop-up blocked');
            printWindow.document.write('<html><body><center><h2>Preview Print</h2><p>Fitur cetak sedang dalam pengembangan lebih lanjut.</p><pre>' + JSON.stringify(data, null, 2) + '</pre></center></body></html>');
        }

        // PDF Logic
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null, scale = 1.5;
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

        function queueRenderPage(num) { if (pageRendering) pageNumPending = num; else renderPage(num); }
        function onPrevPage() { if (pageNum <= 1) return; pageNum--; queueRenderPage(pageNum); }
        function onNextPage() { if (pageNum >= pdfDoc.numPages) return; pageNum++; queueRenderPage(pageNum); }

        async function viewPdf(filename) {
            document.getElementById('pdfModal').style.display = 'block';
            loading.style.display = 'block';
            document.getElementById('pdf-controls').style.display = 'none';
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height); // Safe check

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

    </script>
</body>

</html>