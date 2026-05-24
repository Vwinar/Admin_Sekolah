<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Pembinaan Profesi Guru';
$msg = '';
$msgType = '';

// ACTION HANDLER
$view_action = $_GET['action'] ?? 'list'; // list, add, edit
$edit_id = $_GET['id'] ?? null;

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_coaching') {
        $teacher_id = $_POST['teacher_id'] ?? '';
        $teacher_rank = $_POST['teacher_rank'] ?? '';
        $years_of_service = $_POST['years_of_service'] ?? null;
        $school_year = $_POST['school_year'] ?? '';
        $semester = $_POST['semester'] ?? '';
        $coaching_type = $_POST['coaching_type'] ?? '';
        $analysis_strengths = $_POST['analysis_strengths'] ?? '';
        $analysis_improvements = $_POST['analysis_improvements'] ?? '';
        $competency_focus = $_POST['competency_focus'] ?? '';
        $coaching_goal = $_POST['coaching_goal'] ?? '';
        $program_data = $_POST['program_data'] ?? ''; // JSON
        $progress_data = $_POST['progress_data'] ?? ''; // JSON
        $achievement_level = $_POST['achievement_level'] ?? '';
        $teacher_feedback = $_POST['teacher_feedback'] ?? '';
        $principal_analysis = $_POST['principal_analysis'] ?? '';
        $recommendations_maintain = $_POST['recommendations_maintain'] ?? '';
        $recommendations_improve = $_POST['recommendations_improve'] ?? '';
        $followup_actions = $_POST['followup_actions'] ?? '';
        $completion_date = $_POST['completion_date'] ?? date('Y-m-d');

        if (empty($_POST['id'])) {
            // INSERT
            $stmt = $db->prepare("INSERT INTO teacher_coaching 
                (teacher_id, teacher_rank, years_of_service, school_year, semester, coaching_type, 
                 analysis_strengths, analysis_improvements, competency_focus, coaching_goal, program_data, 
                 progress_data, achievement_level, teacher_feedback, principal_analysis, 
                 recommendations_maintain, recommendations_improve, followup_actions, completion_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([
                $teacher_id, $teacher_rank, $years_of_service, $school_year, $semester, $coaching_type, 
                $analysis_strengths, $analysis_improvements, $competency_focus, $coaching_goal, $program_data, 
                $progress_data, $achievement_level, $teacher_feedback, $principal_analysis, 
                $recommendations_maintain, $recommendations_improve, $followup_actions, $completion_date
            ]);
            $msg = "Data pembinaan berhasil ditambahkan.";
            $msgType = "success";
        } else {
            // UPDATE
            $id = $_POST['id'];
            $stmt = $db->prepare("UPDATE teacher_coaching SET
                teacher_id = ?, teacher_rank = ?, years_of_service = ?, school_year = ?, semester = ?, 
                coaching_type = ?, analysis_strengths = ?, analysis_improvements = ?, competency_focus = ?,
                coaching_goal = ?, program_data = ?, progress_data = ?, achievement_level = ?,
                teacher_feedback = ?, principal_analysis = ?, recommendations_maintain = ?,
                recommendations_improve = ?, followup_actions = ?, completion_date = ?
                WHERE id = ?");
            $stmt->execute([
                $teacher_id, $teacher_rank, $years_of_service, $school_year, $semester, 
                $coaching_type, $analysis_strengths, $analysis_improvements, $competency_focus,
                $coaching_goal, $program_data, $progress_data, $achievement_level,
                $teacher_feedback, $principal_analysis, $recommendations_maintain,
                $recommendations_improve, $followup_actions, $completion_date,
                $id
            ]);
            $msg = "Data pembinaan berhasil diperbarui.";
            $msgType = "success";
        }
        
        // Redirect to list
        header("Location: admin_pembinaan_guru.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;

    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM teacher_coaching WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Data pembinaan berhasil dihapus.";
        $msgType = "success";
        header("Location: admin_pembinaan_guru.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? '';

// Fetch Dropdown Data
$teachers = $db->query("SELECT id, full_name, nip, subject, assigned_class FROM users WHERE role = 'guru' ORDER BY full_name")->fetchAll();
$subjects = $db->query("SELECT DISTINCT name FROM subjects ORDER BY name")->fetchAll();
$classes = $db->query("SELECT DISTINCT name FROM classes ORDER BY name")->fetchAll();

// Fetch List Data
$records = [];
if ($view_action === 'list') {
    $stmt = $db->query("
        SELECT tc.*, u.full_name, u.nip, u.subject, u.assigned_class
        FROM teacher_coaching tc
        LEFT JOIN users u ON tc.teacher_id = u.id
        ORDER BY tc.created_at DESC
    ");
    $records = $stmt->fetchAll();
}

// Fetch Edit Data
$edit_data = null;
if ($view_action === 'edit' && $edit_id) {
    $stmt = $db->prepare("SELECT * FROM teacher_coaching WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit_data) {
        header("Location: admin_pembinaan_guru.php");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .form-section { background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; }
        .form-section h4 { margin-bottom: 1rem; color: var(--primary); border-bottom: 2px solid var(--primary); padding-bottom: 0.5rem; }
        .checkbox-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .checkbox-list label { display: flex; align-items: center; gap: 0.5rem; }
        #programTable, #progressTable { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        #programTable th, #programTable td, #progressTable th, #progressTable td { border: 1px solid #ddd; padding: 0.5rem; }
        #programTable input, #programTable textarea, #progressTable input, #progressTable textarea { width: 100%; border: 1px solid #cbd5e1; background: white; padding: 0.4rem; border-radius: 4px; }
        
        /* Close button styling */
        .close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f1f5f9;
            transition: all 0.2s;
            z-index: 10;
        }
        
        .close-btn:hover {
            background: #e2e8f0;
            transform: scale(1.1);
        }
        
        .close-btn i {
            font-size: 1.25rem;
            color: #64748b;
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
                padding-top: 5rem !important;
            }

            /* Overall text smaller on mobile */
            body {
                font-size: 0.8rem !important;
            }

            /* Horizontal Header for Mobile - FIXED */
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
            
            /* Compact buttons */
            .btn-sm {
                padding: 0.375rem 0.5rem;
            }
            
            /* Form sections more compact */
            .form-section {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .form-section h4 {
                font-size: 0.95rem;
                margin-bottom: 0.75rem;
            }
            
            /* All grid layouts become single column by default */
            .form-section > div[style*="grid"] {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
            }
            
            /* Exception: Section A (Identitas) keeps 2 columns on mobile */
            .form-section:first-of-type > div[style*="grid-template-columns: 1fr 1fr"],
            .form-section:first-of-type > div[style*="grid-template-columns: repeat(3, 1fr)"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* Keep 2 columns for specific identity fields */
            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* But 3-column grid (tahun/semester/jenis) stays 2 columns on mobile */
            div[style*="grid-template-columns: repeat(3, 1fr)"] {
                grid-template-columns: 1fr 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* Form groups more compact */
            .form-group {
                margin-bottom: 0.75rem;
            }
            
            .form-label {
                font-size: 0.8rem;
                margin-bottom: 0.25rem;
            }
            
            .form-control, .form-control select, .form-control input, .form-control textarea {
                font-size: 0.8rem;
                padding: 0.4rem;
            }
            
            select.form-control, input.form-control, textarea.form-control {
                font-size: 0.8rem !important;
                padding: 0.4rem !important;
            }
            
            /* Tables responsive - with proper wrapper */
            .form-group {
                position: relative;
            }
            
            #programTable, #progressTable {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            #programTable table, #progressTable table {
                min-width: 600px;
            }
            
            #programTable, #progressTable {
                font-size: 0.75rem;
            }
            
            #programTable th, #programTable td,
            #progressTable th, #progressTable td {
                padding: 0.375rem;
                white-space: nowrap;
            }
            
            #programTable input, #programTable textarea,
            #progressTable input, #progressTable textarea {
                font-size: 0.75rem;
                padding: 0.25rem;
                min-width: 120px;
            }
            
            /* Checkbox list single column */
            .checkbox-list {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .checkbox-list label {
                font-size: 0.875rem;
            }
            
            /* Header compact */
            .header h1 {
                font-size: 1.1rem;
            }
            
            .header p {
                font-size: 0.8rem;
            }
            
            /* Card compact */
            .card {
                padding: 1rem;
            }
            
            /* Action buttons container */
            .card > div[style*="justify-content"] {
                flex-direction: column-reverse;
                gap: 0.75rem;
                align-items: stretch !important;
            }
            
            .card > div[style*="justify-content"] .btn,
            .card > div[style*="justify-content"] a {
                width: 100%;
                justify-content: center;
            }
            
            /* Table action buttons in rows */
            td > div[style*="flex"] {
                gap: 0.375rem !important;
            }
        }
        
        /* Very small mobile */
        @media (max-width: 480px) {
            .form-section {
                padding: 0.75rem;
            }
            
            .form-section h4 {
                font-size: 0.875rem;
            }
            
            .form-label {
                font-size: 0.8rem;
            }
            
            .form-control {
                font-size: 0.8rem;
            }
            
            #programTable, #progressTable {
                font-size: 0.7rem;
            }
            
            #programTable input, #programTable textarea,
            #progressTable input, #progressTable textarea {
                font-size: 0.7rem;
                min-width: 100px;
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
                            <h1><?= $page_title ?></h1>
                            <p>Administrasi Sekolah</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                        <a href="admin_pembinaan_guru.php?action=add" class="btn btn-primary" title="Tambah Data">
                            <i class="bi bi-plus-lg"></i> <span>Tambah Data</span>
                        </a>
                    </div>
                </header>

                <?php if ($msg): ?>
                    <div style="background: <?= $msgType === 'success' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $msgType === 'success' ? '#065f46' : '#991b1b' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Guru</th>
                                    <th>NIP</th>
                                    <th>Mata Pelajaran/Kelas</th>
                                    <th>Tahun Pelajaran</th>
                                    <th>Semester</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                    <tr><td colspan="8" class="text-center">Belum ada data pembinaan.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($records as $i => $row): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($row['full_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($row['nip'] ?? '-') ?></td>
                                            <td>
                                                <?php
                                                $info = [];
                                                if ($row['subject']) $info[] = htmlspecialchars($row['subject']);
                                                if ($row['assigned_class']) $info[] = 'Kelas ' . htmlspecialchars($row['assigned_class']);
                                                echo implode(' / ', $info) ?: '-';
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['school_year']) ?></td>
                                            <td><?= htmlspecialchars($row['semester']) ?></td>
                                            <td><?= htmlspecialchars($row['completion_date']) ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="admin_pembinaan_guru.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-sm btn-primary" onclick="printRecord(<?= $row['id'] ?>)"><i class="bi bi-printer"></i></button>
                                                    <button class="btn btn-sm btn-danger" onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['full_name'] ?? 'Data', ENT_QUOTES) ?>')"><i class="bi bi-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <!-- ADD / EDIT VIEW -->
            <?php elseif ($view_action === 'add' || $view_action === 'edit'): ?>
                <header class="header">
                    <div class="header-left">
                        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                            <span></span>
                            <span></span>
                            <span></span>
                        </button>
                        <div class="header-title">
                            <h1><?= $view_action === 'edit' ? 'Edit Data Pembinaan' : 'Tambah Data Pembinaan' ?></h1>
                            <p>Pembinaan Profesi Guru</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <a href="admin_pembinaan_guru.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                    </div>
                </header>

                <?php if ($msg): ?>
                    <div style="background: <?= $msgType === 'success' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $msgType === 'success' ? '#065f46' : '#991b1b' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="position: relative;">

                    <form method="POST" id="coachingForm">
                        <input type="hidden" name="action" value="save_coaching">
                        <?php if($edit_data): ?><input type="hidden" name="id" value="<?= $edit_data['id'] ?>"><?php endif; ?>

                        <!-- Section A -->
                        <div class="form-section">
                            <h4>A. IDENTITAS GURU YANG DIBINA</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Nama Guru *</label>
                                    <select name="teacher_id" id="teacher_id" class="form-control" required onchange="updateTeacherInfo()">
                                        <option value="">-- Pilih Guru --</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?= $teacher['id'] ?>"
                                                data-nip="<?= htmlspecialchars($teacher['nip'] ?? '') ?>"
                                                data-subject="<?= htmlspecialchars($teacher['subject'] ?? '') ?>"
                                                data-class="<?= htmlspecialchars($teacher['assigned_class'] ?? '') ?>"
                                                <?= ($edit_data && $edit_data['teacher_id'] == $teacher['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($teacher['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">NIP</label>
                                    <input type="text" name="teacher_nip" id="teacher_nip" class="form-control" readonly style="background-color: #f0f0f0;">
                                </div>
                            </div>
                            <!-- More identity fields -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="teacher_subject" id="teacher_subject" class="form-control">
                                        <option value="">-- Pilih Mata Pelajaran --</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Kelas</label>
                                    <select name="teacher_class" id="teacher_class" class="form-control">
                                        <option value="">-- Pilih Kelas --</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Pangkat / Golongan</label>
                                    <input type="text" name="teacher_rank" class="form-control" value="<?= $edit_data['teacher_rank'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Masa Kerja (Tahun)</label>
                                    <input type="number" name="years_of_service" class="form-control" value="<?= $edit_data['years_of_service'] ?? '' ?>">
                                </div>
                            </div>
                             <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Tahun Pelajaran *</label>
                                    <input type="text" name="school_year" class="form-control" required value="<?= $edit_data['school_year'] ?? date('Y') . '/' . (date('Y')+1) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Semester *</label>
                                    <select name="semester" class="form-control" required>
                                        <option value="1" <?= ($edit_data['semester'] ?? '') == '1' ? 'selected' : '' ?>>1 (Ganjil)</option>
                                        <option value="2" <?= ($edit_data['semester'] ?? '') == '2' ? 'selected' : '' ?>>2 (Genap)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Jenis Pembinaan *</label>
                                    <select name="coaching_type" class="form-control" required>
                                        <option value="In Service" <?= ($edit_data['coaching_type'] ?? '') == 'In Service' ? 'selected' : '' ?>>In Service</option>
                                        <option value="On Service" <?= ($edit_data['coaching_type'] ?? '') == 'On Service' ? 'selected' : '' ?>>On Service</option>
                                        <option value="Induksi" <?= ($edit_data['coaching_type'] ?? '') == 'Induksi' ? 'selected' : '' ?>>Induksi</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                         <!-- Section B -->
                        <div class="form-section">
                            <h4>B. PETA AWAL KONDISI & KEBUTUHAN GURU</h4>
                            <div class="form-group">
                                <label class="form-label">Aspek yang Sudah Kuat/Unggul</label>
                                <textarea name="analysis_strengths" class="form-control" rows="3"><?= $edit_data['analysis_strengths'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Aspek yang Memerlukan Pengembangan</label>
                                <textarea name="analysis_improvements" class="form-control" rows="3"><?= $edit_data['analysis_improvements'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Fokus Kompetensi</label>
                                <div class="checkbox-list">
                                    <?php $comps = explode(', ', $edit_data['competency_focus'] ?? ''); ?>
                                    <label><input type="checkbox" class="competency-check" value="Pedagogik" <?= in_array('Pedagogik', $comps) ? 'checked' : '' ?>> Pedagogik</label>
                                    <label><input type="checkbox" class="competency-check" value="Kepribadian" <?= in_array('Kepribadian', $comps) ? 'checked' : '' ?>> Kepribadian</label>
                                    <label><input type="checkbox" class="competency-check" value="Sosial" <?= in_array('Sosial', $comps) ? 'checked' : '' ?>> Sosial</label>
                                    <label><input type="checkbox" class="competency-check" value="Profesional" <?= in_array('Profesional', $comps) ? 'checked' : '' ?>> Profesional</label>
                                </div>
                                <input type="hidden" name="competency_focus" id="competency_focus" value="<?= $edit_data['competency_focus'] ?? '' ?>">
                            </div>
                        </div>

                        <!-- Section C -->
                        <div class="form-section">
                            <h4>C. RENCANA PROGRAM PEMBINAAN INDIVIDU (RPPI)</h4>
                            <div class="form-group">
                                <label class="form-label">Tujuan Pembinaan</label>
                                <textarea name="coaching_goal" class="form-control" rows="2"><?= $edit_data['coaching_goal'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Program Kegiatan</label>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addProgramRow()">+ Tambah Baris</button>
                                <table id="programTable">
                                    <thead>
                                        <tr>
                                            <th>Bentuk Kegiatan</th>
                                            <th>Materi/Topik</th>
                                            <th>Indikator Keberhasilan</th>
                                            <th>Jadwal</th>
                                            <th>Penanggung Jawab</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="programTableBody"></tbody>
                                </table>
                                <input type="hidden" name="program_data" id="program_data" value='<?= $edit_data['program_data'] ?? '[]' ?>'>
                            </div>
                        </div>

                        <!-- Section D -->
                        <div class="form-section">
                            <h4>D. CATATAN PELAKSANAAN DAN PERKEMBANGAN</h4>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addProgressRow()">+ Tambah Catatan</button>
                            <table id="progressTable">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jenis Kegiatan</th>
                                        <th>Proses & Temuan</th>
                                        <th>Kemajuan</th>
                                        <th>Kendala & Solusi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="progressTableBody"></tbody>
                            </table>
                            <input type="hidden" name="progress_data" id="progress_data" value='<?= $edit_data['progress_data'] ?? '[]' ?>'>
                        </div>

                        <!-- Section E -->
                        <div class="form-section">
                            <h4>E. EVALUASI AKHIR DAN TINDAK LANJUT</h4>
                            <div class="form-group">
                                <label class="form-label">Capaian Hasil Pembinaan</label>
                                <select name="achievement_level" class="form-control">
                                    <option value="Tercapai seluruhnya" <?= ($edit_data['achievement_level'] ?? '') == 'Tercapai seluruhnya' ? 'selected' : '' ?>>Tercapai seluruhnya</option>
                                    <option value="Tercapai sebagian besar" <?= ($edit_data['achievement_level'] ?? '') == 'Tercapai sebagian besar' ? 'selected' : '' ?>>Tercapai sebagian besar</option>
                                    <option value="Belum tercapai secara signifikan" <?= ($edit_data['achievement_level'] ?? '') == 'Belum tercapai secara signifikan' ? 'selected' : '' ?>>Belum tercapai secara signifikan</option>
                                </select>
                            </div>
                            
                            <!-- Always show in Edit Mode for simplicity, or toggle based on role? Existing code hid based on add/edit mode. -->
                            <?php if ($view_action === 'edit'): ?>
                                <div class="form-group">
                                    <label class="form-label">Perubahan/Kemajuan yang Dirasakan Guru</label>
                                    <textarea name="teacher_feedback" class="form-control" rows="3"><?= $edit_data['teacher_feedback'] ?? '' ?></textarea>
                                </div>
                                <div class="form-group" style="margin-top:1rem; border-top:1px dashed #ccc; padding-top:1rem;">
                                    <h5>Analisis dan Rekomendasi Pembina</h5>
                                    <label class="form-label">Kesimpulan Umum</label>
                                    <textarea name="principal_analysis" class="form-control" rows="2"><?= $edit_data['principal_analysis'] ?? '' ?></textarea>
                                    
                                    <label class="form-label">Rekomendasi untuk Dipertahankan</label>
                                    <textarea name="recommendations_maintain" class="form-control" rows="2"><?= $edit_data['recommendations_maintain'] ?? '' ?></textarea>
                                    
                                    <label class="form-label">Rekomendasi untuk Ditingkatkan</label>
                                    <textarea name="recommendations_improve" class="form-control" rows="2"><?= $edit_data['recommendations_improve'] ?? '' ?></textarea>
                                </div>
                            <?php endif; ?>

                             <div class="form-group">
                                <label class="form-label">Rencana Tindak Lanjut Jangka Panjang</label>
                                <textarea name="followup_actions" class="form-control" rows="3"><?= $edit_data['followup_actions'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <!-- Section F -->
                        <div class="form-section">
                            <h4>F. PENUTUP</h4>
                            <div class="form-group">
                                <label class="form-label">Tanggal Penyelesaian</label>
                                <input type="date" name="completion_date" class="form-control" value="<?= $edit_data['completion_date'] ?? date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: flex-end;">
                            <a href="admin_pembinaan_guru.php" class="btn btn-secondary">Batal</a>
                             <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Data</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #dc2626;"></i>
            <h3 style="margin: 1rem 0; color: #dc2626;">Konfirmasi Hapus</h3>
            <p>Data yang dihapus tidak dapat dikembalikan: <strong id="deleteTeacherName"></strong>?</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('deleteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // JS Logic - Sidebar handled by external script

    function printRecord(id) {
        const printWindow = window.open(`../print/get_pembinaan_print.php?id=${id}`, '_blank');
        if (printWindow) {
            printWindow.onload = function () { printWindow.focus(); printWindow.print(); };
        }
    }

    function openDeleteModal(id, name) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteTeacherName').innerText = name;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    // Form Logic
    <?php if ($view_action === 'add' || $view_action === 'edit'): ?>

        // Teacher Auto-Fill
        function updateTeacherInfo() {
            const select = document.getElementById('teacher_id');
            const selectedOption = select.options[select.selectedIndex];
            if (select.value) {
                document.getElementById('teacher_nip').value = selectedOption.getAttribute('data-nip') || '';
                const subject = selectedOption.getAttribute('data-subject') || '';
                const kelas = selectedOption.getAttribute('data-class') || '';

                // Set Subject
                const subjSelect = document.getElementById('teacher_subject');
                for(let i=0; i<subjSelect.options.length; i++) {
                    if(subjSelect.options[i].value === subject) { subjSelect.selectedIndex = i; break; }
                }
                // Set Class
                const clsSelect = document.getElementById('teacher_class');
                for(let i=0; i<clsSelect.options.length; i++) {
                     if(clsSelect.options[i].value === kelas) { clsSelect.selectedIndex = i; break; }
                }
            } else {
                document.getElementById('teacher_nip').value = '';
            }
        }

        // Competency Checkboxes
        document.querySelectorAll('.competency-check').forEach(cb => {
            cb.addEventListener('change', function() {
                const checked = Array.from(document.querySelectorAll('.competency-check:checked')).map(c => c.value);
                document.getElementById('competency_focus').value = checked.join(', ');
            });
        });

        // Dynamic Tables
        function addProgramRow(activity='', topic='', indicator='', schedule='', pic='') {
            const tbody = document.getElementById('programTableBody');
            const row = tbody.insertRow();
            row.innerHTML = `
                <td><input type="text" class="prog-activity" value="${activity}" placeholder="Lokus"></td>
                <td><input type="text" class="prog-topic" value="${topic}" placeholder="Materi"></td>
                <td><input type="text" class="prog-indicator" value="${indicator}" placeholder="Indikator"></td>
                <td><input type="text" class="prog-schedule" value="${schedule}" placeholder="Jadwal"></td>
                <td><input type="text" class="prog-pic" value="${pic}" placeholder="PIC"></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
            `;
        }

        function addProgressRow(date='', activity='', process='', progress='', obstacles='') {
            const tbody = document.getElementById('progressTableBody');
            const row = tbody.insertRow();
            row.innerHTML = `
                <td><input type="date" class="prog-date" value="${date}"></td>
                <td><input type="text" class="prog-activity-name" value="${activity}" placeholder="Kegiatan"></td>
                <td><textarea class="prog-process" rows="1">${process}</textarea></td>
                <td><textarea class="prog-progress" rows="1">${progress}</textarea></td>
                <td><textarea class="prog-obstacles" rows="1">${obstacles}</textarea></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
            `;
        }

        function collectProgramData() {
            const rows = document.querySelectorAll('#programTableBody tr');
            const data = [];
            rows.forEach(row => {
                data.push({
                    activity: row.querySelector('.prog-activity').value,
                    topic: row.querySelector('.prog-topic').value,
                    indicator: row.querySelector('.prog-indicator').value,
                    schedule: row.querySelector('.prog-schedule').value,
                    pic: row.querySelector('.prog-pic').value
                });
            });
            return JSON.stringify(data);
        }

        function collectProgressData() {
            const rows = document.querySelectorAll('#progressTableBody tr');
            const data = [];
            rows.forEach(row => {
                data.push({
                    date: row.querySelector('.prog-date').value,
                    activity: row.querySelector('.prog-activity-name').value,
                    process: row.querySelector('.prog-process').value,
                    progress: row.querySelector('.prog-progress').value,
                    obstacles: row.querySelector('.prog-obstacles').value
                });
            });
            return JSON.stringify(data);
        }

        document.getElementById('coachingForm').addEventListener('submit', function (e) {
            document.getElementById('program_data').value = collectProgramData();
            document.getElementById('progress_data').value = collectProgressData();
        });

        // Initialize Existing Data
        document.addEventListener('DOMContentLoaded', function() {
            // Update Teacher UI first time (if edit)
            <?php if (!$edit_data): ?>
                updateTeacherInfo(); // Clean state
            <?php endif; ?>

            // Init Program Table
            const programData = <?= $edit_data['program_data'] ?? '[]' ?>;
            if(programData.length > 0) {
                programData.forEach(p => addProgramRow(p.activity, p.topic, p.indicator, p.schedule, p.pic));
            } else {
                addProgramRow();
            }

            // Init Progress Table
            const progressData = <?= $edit_data['progress_data'] ?? '[]' ?>;
            if(progressData.length > 0) {
                progressData.forEach(p => addProgressRow(p.date, p.activity, p.process, p.progress, p.obstacles));
            } else {
                addProgressRow();
            }
        });

    <?php endif; ?>
    </script>

    <!-- Sidebar Toggle Script - Separate from form logic -->
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
            dashboardLayout.addEventListener('click', function(e) {
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
    </script>
</body>
</html>
