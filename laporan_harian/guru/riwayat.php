<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

// Handle Deletion
if (isset($_POST['delete_id'])) {
    $delId = $_POST['delete_id'];

    // Verify ownership first
    $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$delId, $_SESSION['user_id']]);
    $report = $stmt->fetch();

    if ($report) {
        // Delete files
        if (!empty($report['module_file'])) {
            $filePath = __DIR__ . '/../uploads/' . $report['module_file'];
            if (file_exists($filePath))
                unlink($filePath);
        }
        if (!empty($report['evaluation_file'])) {
            $imgPath = __DIR__ . '/../uploads/' . $report['evaluation_file'];
            if (file_exists($imgPath))
                unlink($imgPath);
        }

        // Delete DB record
        $delStmt = $db->prepare("DELETE FROM reports WHERE id = ?");
        $delStmt->execute([$delId]);

        $tabRedirect = $_POST['tab_source'] ?? 'tab1';
        header("Location: riwayat.php?deleted=1&tab=" . $tabRedirect);
        exit;
    }
}

$user_id = $_SESSION['user_id'];
$reports = $db->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY report_date DESC");
$reports->execute([$user_id]);
$allReports = $reports->fetchAll();

// Filter reports by tab relevance

// Tab 1: Rencana Hari Ini - show if has plan data (new columns)
$tab1Reports = array_filter($allReports, function ($report) {
    return !empty($report['plan_topic']) ||
        !empty($report['plan_learning_objective']) ||
        !empty($report['plan_method_type']);
});

// Tab 2: Hari Ini (Laporan Harian) - show if has usage data
$tab2Reports = array_filter($allReports, function ($report) {
    return !empty($report['material_taught']) ||
        !empty($report['attendance']) ||
        !empty($report['achievement']);
});

// Tab 3: Evaluasi - show if has evaluation data
$tab3Reports = array_filter($allReports, function ($report) {
    return !empty($report['reflection']) ||
        !empty($report['improvement_notes']) ||
        !empty($report['evaluation_file']);
});

// Tab 4: Rencana Besok - show if has future plan data (old columns)
$tab4Reports = array_filter($allReports, function ($report) {
    return !empty($report['plan_material']) ||
        !empty($report['plan_media']) || // Note: This is old plan_media column, different from plan_media_used
        !empty($report['plan_goal']);
});

$msg = $_GET['msg'] ?? '';
$activeTab = $_GET['tab'] ?? 'tab1'; // Default to tab1
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Laporan</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        /* PDF Modal Styles */
        #pdfModal {
            display: none;
            position: fixed;
            z-index: 99999;
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

        /* Toast Notification */
        .toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 12px 16px;
            position: fixed;
            z-index: 9999;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            transition: opacity 0.3s, bottom 0.3s;
            font-size: 0.9rem;
        }

        .toast.show {
            visibility: visible;
            opacity: 1;
            bottom: 50px;
        }

        .toast-success {
            background-color: #10B981;
        }

        /* Modal Confirmation */
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            align-items: center;
            justify-content: center;
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-modal-content {
            background: white;
            padding: 24px;
            border-radius: 12px;
            width: 320px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            animation: popIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .modal-icon-danger {
            width: 48px;
            height: 48px;
            background: #FEF2F2;
            color: #DC2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
        }

        /* Tooltip Styles */
        .tooltip-container {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .tooltip-text {
            visibility: hidden;
            width: 220px;
            background-color: #1f2937;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 8px 12px;
            position: absolute;
            z-index: 10;
            bottom: 125%;
            /* Position above */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.2s;
            font-size: 0.8rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            white-space: normal;
            line-height: 1.4;
        }

        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #1f2937 transparent transparent transparent;
        }

        .tooltip-container.active .tooltip-text,
        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Mobile Responsive Header */
        @media (max-width: 768px) {
            .header {
                display: flex !important;
                flex-direction: row !important;
                /* Force row to aligning items horizontally */
                align-items: center !important;
                position: relative;
                padding-right: 0;
                margin-bottom: 1.5rem;
                gap: 0 !important;
                /* Remove gap from global style */
            }

            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 10px !important;
                width: 100%;
            }

            .header-left h1 {
                margin: 0 !important;
                font-size: 1.25rem !important;
                line-height: 1 !important;
                display: inline-block !important;
            }

            .header-right {
                position: absolute;
                top: 0;
                right: 0;
                margin-top: 5px;
                /* Adjust alignment */
            }

            .back-btn {
                width: 40px !important;
                height: 40px;
                padding: 0 !important;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background: white !important;
                color: var(--text-main) !important;
                box-shadow: var(--shadow-sm);
                border: 1px solid var(--border);
            }

            .back-btn .btn-text {
                display: none;
            }

            .back-btn i {
                margin-right: 0 !important;
            }
        }
    </style>

</head>

<body class="riwayat-page">
    <div class="dashboard-layout">
        <?php include '../layout/user_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-left"
                    style="display: flex; flex-direction: row; align-items: center; gap: 10px; flex-wrap: nowrap;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar"
                        style="flex-shrink: 0;">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1 style="margin: 0; font-size: 1.25rem; line-height: 1;">Riwayat Laporan</h1>
                </div>
                <div class="header-right">
                    <a href="dashboard_guru.php" class="btn btn-secondary back-btn">
                        <i class="fas fa-arrow-left me-1"></i> <span class="btn-text">Kembali</span>
                    </a>
                </div>
            </header>

            <?php if ($msg): ?>
                <div
                    style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <!-- TAB NAVIGATION -->
                <div class="tabs">
                    <button type="button" class="tab-btn <?= $activeTab == 'tab1' ? 'active' : '' ?>"
                        onclick="switchTab('tab1')">1. Rencana Hari Ini</button>
                    <button type="button" class="tab-btn <?= $activeTab == 'tab2' ? 'active' : '' ?>"
                        onclick="switchTab('tab2')">2. Hari Ini</button>
                    <button type="button" class="tab-btn <?= $activeTab == 'tab3' ? 'active' : '' ?>"
                        onclick="switchTab('tab3')">3. Evaluasi</button>
                    <button type="button" class="tab-btn <?= $activeTab == 'tab4' ? 'active' : '' ?>"
                        onclick="switchTab('tab4')">4. Rencana Besok</button>
                </div>

                <!-- TAB 1: RENCANA HARI INI (NEW) -->
                <div id="tab1" class="tab-content <?= $activeTab == 'tab1' ? 'active' : '' ?>">
                    <div class="table-container">
                        <table style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Tanggal</th>
                                    <th style="width: 10%;">Mapel</th>
                                    <th style="width: 8%;">Kelas</th>
                                    <th style="width: 35%;">Topik & Tujuan</th>
                                    <th style="width: 10%;">Modul Ajar</th>
                                    <th>Keterangan</th>
                                    <th style="width: 5%; text-align: center;">Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tab1Reports) === 0): ?>
                                    <tr>
                                        <td colspan="7"
                                            style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <div>
                                                <div style="font-size: 3rem; margin-bottom: 1rem;"></div>
                                                <div style="font-weight: 600; margin-bottom: 0.5rem;">Belum Ada Rencana
                                                    Harian</div>
                                                <div style="font-size: 0.875rem;">Buat rencana pembelajaran pertama Anda
                                                    hari ini</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tab1Reports as $row): ?>
                                        <?php
                                        $stages = [];
                                        if (!empty($row['plan_topic']) || !empty($row['plan_learning_objective']) || !empty($row['plan_method_type']))
                                            $stages[] = ['label' => 'Rencana', 'class' => 'badge-revision'];
                                        if (!empty($row['material_taught']) || !empty($row['attendance']))
                                            $stages[] = ['label' => 'Hari Ini', 'class' => 'badge-approved'];
                                        if (!empty($row['reflection']) || !empty($row['evaluation_file']))
                                            $stages[] = ['label' => 'Evaluasi', 'class' => 'badge-warning', 'style' => 'background-color: #fef3c7; color: #d97706;'];
                                        if (!empty($row['plan_material']) || !empty($row['plan_goal']))
                                            $stages[] = ['label' => 'Besok', 'class' => 'badge-pending'];
                                        ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['subject'] ?? $row['plan_subject'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($row['class_name'] ?? $row['plan_class'] ?? '-') ?></td>
                                            <td>
                                                <div
                                                    style="font-weight:600; font-size:0.9em; color:var(--primary); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['plan_topic'] ?? '-') ?>
                                                </div>
                                                <div
                                                    style="font-size:0.8em; color:var(--text-muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 2px;">
                                                    <?= htmlspecialchars($row['plan_learning_objective'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['module_file'])):
                                                    $ext = pathinfo($row['module_file'], PATHINFO_EXTENSION);
                                                    if (strtolower($ext) === 'pdf'):
                                                        ?>
                                                        <button type="button"
                                                            onclick="viewPdf('../uploads/<?= $row['module_file'] ?>'); return false;"
                                                            style="display:inline-flex; align-items:center; gap:4px; font-size:0.85em; background:#f0f9ff; color:#0ea5e9; padding:4px 8px; border-radius:4px; border: none; cursor: pointer;">
                                                            <span>📄</span> PDF
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="../uploads/<?= $row['module_file'] ?>" target="_blank"
                                                            style="display:inline-flex; align-items:center; gap:4px; font-size:0.85em; background:#f0f9ff; color:#0ea5e9; padding:4px 8px; border-radius:4px; text-decoration:none;">
                                                            <span>📄</span> File
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size:0.85em;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                    <?php foreach ($stages as $stage): ?>
                                                        <span class="badge <?= $stage['class'] ?>" <?= isset($stage['style']) ? 'style="' . $stage['style'] . '"' : '' ?>
                                                            style="font-size: 0.7rem; padding: 2px 6px;">
                                                            <?= $stage['label'] ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($stages)): ?>
                                                        <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($row['admin_comment'])): ?>
                                                    <div class="tooltip-container" onclick="this.classList.toggle('active')">
                                                        <span style="font-size: 1.2rem;">📝</span>
                                                        <div class="tooltip-text">
                                                            <?= htmlspecialchars($row['admin_comment']) ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                                <a href="laporan_detail.php?id=<?= $row['id'] ?>&tab=tab1"
                                                    style="color: var(--primary); font-weight: 500;">Lihat</a>
                                                <a href="laporan_baru.php?id=<?= $row['id'] ?>&tab=tab1"
                                                    style="color: #f59e0b; font-weight: 500;">Edit</a>
                                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0;">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="tab_source" value="tab1">
                                                    <button type="submit"
                                                        style="background: none; border: none; color: #ef4444; font-weight: 500; cursor: pointer; padding: 0;">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: HARI INI (LAPORAN HARIAN) -->
                <div id="tab2" class="tab-content <?= $activeTab == 'tab2' ? 'active' : '' ?>">
                    <div class="table-container">
                        <table style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th style="width: 8%;">Tanggal</th>
                                    <th style="width: 8%;">Mapel</th>
                                    <th style="width: 6%;">Kelas</th>
                                    <th style="width: 20%;">Materi Terlaksana</th>
                                    <th style="width: 15%;">Pencapaian</th>
                                    <th style="width: 15%;">Kendala</th>
                                    <th style="width: 15%;">Solusi</th>
                                    <th style="width: 8%;">Kehadiran</th>
                                    <th style="width: 5%; text-align: center;">Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tab2Reports) === 0): ?>
                                    <tr>
                                        <td colspan="9"
                                            style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <div>
                                                <div style="font-size: 3rem; margin-bottom: 1rem;"></div>
                                                <div style="font-weight: 600; margin-bottom: 0.5rem;">Belum Ada Laporan
                                                    Harian</div>
                                                <div style="font-size: 0.875rem;">Laporan pelaksanaan pembelajaran akan
                                                    muncul di sini</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tab2Reports as $row): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['subject'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['class_name'] ?? '') ?></td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['material_taught'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['achievement'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #dc2626;">
                                                    <?= htmlspecialchars($row['obstacles'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #16a34a;">
                                                    <?= htmlspecialchars($row['solution'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($row['attendance'] ?? '') ?></td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($row['admin_comment'])): ?>
                                                    <div class="tooltip-container" onclick="this.classList.toggle('active')">
                                                        <span style="font-size: 1.2rem;">📝</span>
                                                        <div class="tooltip-text">
                                                            <?= htmlspecialchars($row['admin_comment']) ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                                <a href="laporan_detail.php?id=<?= $row['id'] ?>&tab=tab2"
                                                    style="color: var(--primary); font-weight: 500;">Lihat</a>
                                                <a href="laporan_baru.php?id=<?= $row['id'] ?>&tab=tab2"
                                                    style="color: #f59e0b; font-weight: 500;">Edit</a>
                                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0;">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="tab_source" value="tab2">
                                                    <button type="submit"
                                                        style="background: none; border: none; color: #ef4444; font-weight: 500; cursor: pointer; padding: 0;">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: EVALUASI -->
                <div id="tab3" class="tab-content <?= $activeTab == 'tab3' ? 'active' : '' ?>">
                    <div class="table-container">
                        <table style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Tanggal</th>
                                    <th style="width: 10%;">Mapel</th>
                                    <th>Refleksi</th>
                                    <th style="width: 10%;">Bukti</th>
                                    <th style="width: 5%; text-align: center;">Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tab3Reports) === 0): ?>
                                    <tr>
                                        <td colspan="5"
                                            style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <div>
                                                <div style="font-size: 3rem; margin-bottom: 1rem;">⭐</div>
                                                <div style="font-weight: 600; margin-bottom: 0.5rem;">Belum Ada Evaluasi
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tab3Reports as $row): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['subject']) ?></td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['reflection']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['evaluation_file'])):
                                                    $ext = pathinfo($row['evaluation_file'], PATHINFO_EXTENSION);
                                                    if (strtolower($ext) === 'pdf'):
                                                        ?>
                                                        <button type="button"
                                                            onclick="viewPdf('../uploads/<?= $row['evaluation_file'] ?>'); return false;"
                                                            style="background:none; border:none; color: var(--success); cursor: pointer; font-size: 1rem;">
                                                            📷 Ada (PDF)
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="../uploads/<?= $row['evaluation_file'] ?>" target="_blank"
                                                            style="color: var(--success); text-decoration:none;">📷 Ada</a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($row['admin_comment'])): ?>
                                                    <div class="tooltip-container" onclick="this.classList.toggle('active')">
                                                        <span style="font-size: 1.2rem;">📝</span>
                                                        <div class="tooltip-text">
                                                            <?= htmlspecialchars($row['admin_comment']) ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                                <a href="laporan_detail.php?id=<?= $row['id'] ?>&tab=tab3"
                                                    style="color: var(--primary); font-weight: 500;">Lihat</a>
                                                <a href="laporan_baru.php?id=<?= $row['id'] ?>&tab=tab3"
                                                    style="color: #f59e0b; font-weight: 500;">Edit</a>
                                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0;">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="tab_source" value="tab3">
                                                    <button type="submit"
                                                        style="background: none; border: none; color: #ef4444; font-weight: 500; cursor: pointer; padding: 0;">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: RENCANA BESOK (Moved from Old Tab 2) -->
                <div id="tab4" class="tab-content <?= $activeTab == 'tab4' ? 'active' : '' ?>">
                    <div class="table-container">
                        <table style="font-size: 0.85rem;">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Tanggal Saat Ini</th>
                                    <th style="width: 25%;">Target Besok</th>
                                    <th style="width: 25%;">Rencana Materi</th>
                                    <th style="width: 20%;">Media</th>
                                    <th style="width: 5%; text-align: center;">Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tab4Reports) === 0): ?>
                                    <tr>
                                        <td colspan="5"
                                            style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                            <div>
                                                <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
                                                <div style="font-weight: 600; margin-bottom: 0.5rem;">Belum Ada Rencana
                                                    Besok</div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tab4Reports as $row): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['plan_goal'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['plan_material'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                    <?= htmlspecialchars($row['plan_media'] ?? '-') ?>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!empty($row['admin_comment'])): ?>
                                                    <div class="tooltip-container" onclick="this.classList.toggle('active')">
                                                        <span style="font-size: 1.2rem;">📝</span>
                                                        <div class="tooltip-text">
                                                            <?= htmlspecialchars($row['admin_comment']) ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="display: flex; gap: 0.5rem; align-items: center;">
                                                <a href="laporan_detail.php?id=<?= $row['id'] ?>&tab=tab4"
                                                    style="color: var(--primary); font-weight: 500;">Lihat</a>
                                                <a href="laporan_baru.php?id=<?= $row['id'] ?>&tab=tab4"
                                                    style="color: #f59e0b; font-weight: 500;">Edit</a>
                                                <form method="POST" onsubmit="confirmDelete(event, this)" style="margin:0;">
                                                    <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                                    <input type="hidden" name="tab_source" value="tab4">
                                                    <button type="submit"
                                                        style="background: none; border: none; color: #ef4444; font-weight: 500; cursor: pointer; padding: 0;">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            // Update URL with tab parameter
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);

            // Hide all tabs
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(el => {
                el.style.display = 'none';
                el.classList.remove('active');
            });

            // Remove active from all buttons
            const btns = document.querySelectorAll('.tab-btn');
            btns.forEach(b => b.classList.remove('active'));

            // Show selected tab
            const target = document.getElementById(tabName);
            if (target) {
                target.style.display = 'block';
                target.classList.add('active');
            }

            // Activate button
            // Using logic less prone to index errors:
            btns.forEach(b => {
                if (b.innerText.includes('1.') && tabName === 'tab1') b.classList.add('active');
                if (b.innerText.includes('2.') && tabName === 'tab2') b.classList.add('active');
                if (b.innerText.includes('3.') && tabName === 'tab3') b.classList.add('active');
                if (b.innerText.includes('4.') && tabName === 'tab4') b.classList.add('active');
            });
        }

        // Initialize on page load
        document.addEventListener("DOMContentLoaded", () => {
            const activeForm = document.querySelector('.tab-content.active');
            if (activeForm) activeForm.style.display = 'block';
        });
    </script>

    <script>
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

    <!-- Toast Element -->
    <div id="toast" class="toast toast-success">
        <span>✅</span>
        <span id="toast-message">Laporan berhasil dihapus!</span>
    </div>

    <!-- Confirmation Modal -->
    <div id="deleteModal" class="confirm-modal">
        <div class="confirm-modal-content">
            <div class="modal-icon-danger">🗑️</div>
            <h3 style="margin-bottom: 8px; font-size: 1.1rem; color: #1f2937;">Konfirmasi Hapus</h3>
            <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 20px;">
                Apakah Anda yakin ingin menghapus laporan ini? Tindakan ini tidak dapat dibatalkan.
            </p>
            <div style="display: flex; gap: 10px;">
                <button onclick="closeDeleteModal()"
                    style="flex: 1; padding: 10px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer; color: #374151; font-weight: 500;">Batal</button>
                <button id="confirmDeleteBtn"
                    style="flex: 1; padding: 10px; border: none; background: #dc2626; border-radius: 6px; cursor: pointer; color: white; font-weight: 500;">Ya,
                    Hapus</button>
            </div>
        </div>
    </div>

    <!-- PDF MODAL -->
    <div id="pdfModal">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <div>
                    <span>Preview PDF</span>
                    <span id="page_info" style="font-size: 0.8rem; margin-left: 1rem; color: #ccc;"></span>
                </div>
                <button type="button" onclick="closePdfModal()"
                    style="background:none; border:none; color:#ccc; font-size:2rem; cursor:pointer;">&times;</button>
            </div>
            <div class="modal-body-pdf">
                <div id="loading" style="display:none;">Memuat Dokumen...</div>
                <div id="pdf-container"></div>
            </div>
        </div>
    </div>

    <script>
        // PDF Logic
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        const canvas = document.createElement('canvas'); // Re-use canvas
        const ctx = canvas.getContext('2d');

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderTask = page.render({
                    canvasContext: ctx,
                    viewport: viewport
                });

                renderTask.promise.then(function () {
                    pageRendering = false;
                    const numDisplay = document.getElementById('page_num_display');
                    if (numDisplay) numDisplay.textContent = num;
                    const infoDisplay = document.getElementById('page_info');
                    if (infoDisplay) infoDisplay.textContent = `Halaman ${num} dari ${pdfDoc.numPages}`;

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

        async function viewPdf(filename) {
            const modal = document.getElementById('pdfModal');
            const container = document.getElementById('pdf-container');
            const loading = document.getElementById('loading');

            modal.style.display = 'block';
            loading.style.display = 'block';
            loading.innerText = 'Memuat Dokumen...';
            container.innerHTML = '';

            container.appendChild(canvas);

            // Add controls
            const controls = document.createElement('div');
            controls.id = 'pdf-controls';
            controls.style.marginTop = '15px';
            controls.style.textAlign = 'center';
            controls.style.display = 'none';
            controls.innerHTML = `
                <button id="prevBtn" type="button" style="margin-right: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">← Sebelumnya</button>
                <span style="color:white;">Halaman <span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                <button id="nextBtn" type="button" style="margin-left: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">Selanjutnya →</button>
            `;
            container.appendChild(controls);

            try {
                loading.innerText = 'Mengunduh data...';
                // Use get_pdf_content.php
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
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
                document.getElementById('pdf-controls').style.display = 'block';
                loading.style.display = 'none';

                document.getElementById('prevBtn').onclick = onPrevPage;
                document.getElementById('nextBtn').onclick = onNextPage;

                pageNum = 1;
                renderPage(pageNum);

            } catch (err) {
                console.error("PDF Load Error:", err);
                loading.style.display = 'block';
                loading.style.color = '#ff6b6b';
                loading.innerText = 'Gagal memuat PDF: ' + err.message;
            }
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = 'none';
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        // Close modal on outside click
        window.addEventListener('click', function (event) {
            const pdfModal = document.getElementById('pdfModal');
            if (event.target == pdfModal) {
                closePdfModal();
            }
        });
    </script>

    <script>
        // Toast Logic
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-message').innerText = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Check URL params for deleted flag
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('deleted')) {
            showToast('Laporan berhasil dihapus');
            // Clean URL
            const newUrl = window.location.pathname + "?tab=" + (urlParams.get('tab') || 'tab1');
            window.history.replaceState({}, document.title, newUrl);
        }

        // Modal Logic
        let formToSubmit = null;

        function confirmDelete(event, formElement) {
            event.preventDefault();
            formToSubmit = formElement;
            const modal = document.getElementById('deleteModal');
            modal.classList.add('show');
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            formToSubmit = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            if (formToSubmit) {
                formToSubmit.submit();
            }
        });

        // Close on outside click
        window.onclick = function (event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>

</body>

</html>