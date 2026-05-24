<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Helper function for JSON decoding
function safe_json_decode($json)
{
    if (!$json)
        return [];
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

// Fetch Teachers
$teachers = [];
$stmt_t = $db->query("SELECT id, full_name, nip, assigned_class, subject FROM users WHERE role = 'guru' ORDER BY full_name ASC");
while ($row = $stmt_t->fetch()) {
    $teachers[] = $row;
}

// Fetch School Info
$school_name = "SEKOLAH DASAR"; // Default
$stmt_s = $db->query("SELECT school_name FROM settings LIMIT 1");
$sett = $stmt_s->fetch();
if ($sett)
    $school_name = $sett['school_name'];

// Handle Actions
$action = $_GET['action'] ?? 'list';
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $act = $_POST['action_type'];

    if ($act === 'delete' && $id) {
        $db->prepare("DELETE FROM academic_supervision WHERE id = ?")->execute([$id]);
        header("Location: admin_supervisi_akademik.php?msg=deleted");
        exit;
    }

    if ($act === 'upload_pdf') {
        $id = $_POST['id'];
        if (!empty($_FILES['pdf_file']['name'])) {
            $target_dir = "../uploads/supervision_docs/";
            if (!is_dir($target_dir))
                mkdir($target_dir, 0777, true);

            $fileType = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            if ($fileType != 'pdf') {
                header("Location: admin_supervisi_akademik.php?msg=error_format");
                exit;
            }

            $filename = "Supervisi_" . $id . "_" . time() . ".pdf";
            $target_file = $target_dir . $filename;

            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)) {
                $db->prepare("UPDATE academic_supervision SET document_path = ? WHERE id = ?")->execute([$target_file, $id]);
                header("Location: admin_supervisi_akademik.php?msg=" . urlencode("Dokumen PDF berhasil diupload"));
            } else {
                header("Location: admin_supervisi_akademik.php?msg=" . urlencode("Gagal upload file"));
            }
        }
        exit;
    }

    if ($act === 'save') {
        // Collect Data
        $data = [
            'teacher_id' => $_POST['teacher_id'],
            'school_name' => $_POST['school_name'],
            'date' => $_POST['date'], // Pra-Observasi Date
            'observation_date' => $_POST['observation_date'] ?? null,
            'post_date' => $_POST['post_date'] ?? null,
            'subject' => $_POST['subject'] ?? '',
            'class_name' => $_POST['class_name'] ?? '',
            'topic' => $_POST['topic'] ?? '',
            'time_allocation' => $_POST['time_allocation'] ?? '',
            'kd' => $_POST['kd'] ?? '',
            'indicators' => $_POST['indicators'] ?? '',
            'objectives' => $_POST['objectives'] ?? '',
            'methods' => $_POST['methods'] ?? '',
            'media' => $_POST['media'] ?? '',
            'focus_aspects' => $_POST['focus_aspects'] ?? '',
            'special_needs' => $_POST['special_needs'] ?? '',
            'obs_time_start' => $_POST['obs_time_start'] ?? '',
            'obs_time_end' => $_POST['obs_time_end'] ?? '',
            'students_present' => $_POST['students_present'] ?? '',

            // JSON fields for scores and notes
            'planning_scores' => json_encode($_POST['score_a'] ?? []),
            'planning_notes' => json_encode($_POST['note_a'] ?? []),

            // Execution scores merged or structured - wait, logic remains same
            'execution_scores' => json_encode([
                'b1' => $_POST['score_b1'] ?? [],
                'b2' => $_POST['score_b2'] ?? [],
                'b3' => $_POST['score_b3'] ?? []
            ]),
            'execution_notes' => json_encode([
                'b1' => $_POST['note_b1'] ?? [],
                'b2' => $_POST['note_b2'] ?? [],
                'b3' => $_POST['note_b3'] ?? []
            ]),

            'assessment_scores' => json_encode($_POST['score_c'] ?? []),
            'assessment_notes' => json_encode($_POST['note_c'] ?? []),

            'strengths' => $_POST['strengths'] ?? '',
            'areas_for_improvement' => $_POST['improvement'] ?? '',

            'total_score' => $_POST['total_score_input'] ?? 0,
            'max_score' => 80, // Total 20 items * 4 = 80
            'percentage' => $_POST['percentage_input'] ?? 0,
            'recommendation' => $_POST['recommendation'] ?? '',

            'post_reflection' => $_POST['post_reflection'] ?? '',
            'post_feedback_strengths' => $_POST['post_feedback_strengths'] ?? '',
            'post_feedback_improvements' => $_POST['post_feedback_improvements'] ?? '',
            'post_action_plan_targets' => $_POST['post_action_plan_targets'] ?? '',
            'post_action_plan_support' => $_POST['post_action_plan_support'] ?? '',
            'post_timeline' => $_POST['post_timeline'] ?? '',
            'post_next_date' => $_POST['post_next_date'] ?? '',
            'supervisor_id' => $_SESSION['user_id']
        ];

        // File Upload
        if (!empty($_FILES['photo_doc']['name'])) {
            $target_dir = "../uploads/supervision/";
            if (!is_dir($target_dir))
                mkdir($target_dir, 0777, true);
            $filename = time() . "_" . basename($_FILES['photo_doc']['name']);
            move_uploaded_file($_FILES['photo_doc']['tmp_name'], $target_dir . $filename);
            $data['photo_path'] = $target_dir . $filename;
        } elseif ($id && isset($_POST['existing_photo_path'])) {
            $data['photo_path'] = $_POST['existing_photo_path'];
        } else {
            $data['photo_path'] = '';
        }

        if ($id) {
            // Update
            $sql = "UPDATE academic_supervision SET 
                teacher_id=?, school_name=?, date=?, observation_date=?, post_date=?, subject=?, class_name=?, topic=?, time_allocation=?, 
                kd=?, indicators=?, objectives=?, methods=?, media=?, focus_aspects=?, special_needs=?,
                obs_time_start=?, obs_time_end=?, students_present=?, 
                planning_scores=?, planning_notes=?, execution_scores=?, execution_notes=?, assessment_scores=?, assessment_notes=?,
                strengths=?, areas_for_improvement=?, total_score=?, max_score=?, percentage=?, recommendation=?,
                post_reflection=?, post_feedback_strengths=?, post_feedback_improvements=?, 
                post_action_plan_targets=?, post_action_plan_support=?, post_timeline=?, post_next_date=?, photo_path=?, supervisor_id=?, updated_at=CURRENT_TIMESTAMP
                WHERE id=?";
            $data_values = array_values($data);
            $data_values[] = $id;
            $db->prepare($sql)->execute($data_values);
            $msg = 'Data berhasil disimpan (Mode Edit)';
        } else {
            // Insert
            $cols = implode(", ", array_keys($data));
            $vals = implode(", ", array_fill(0, count($data), "?"));
            $db->prepare("INSERT INTO academic_supervision ($cols) VALUES ($vals)")->execute(array_values($data));
            $msg = 'Data berhasil disimpan (Baru)';
        }

        header("Location: admin_supervisi_akademik.php?msg=" . urlencode($msg));
        exit;
    }
}

// Fetch Logic for Edit or List
$edit_data = null;
if (($action === 'edit' || $action === 'add') && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM academic_supervision WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $edit_data = $stmt->fetch();
    $action = 'edit'; // Ensure action is edit if ID is present
} else {
    // List
    $list_stmt = $db->query("SELECT s.*, u.full_name as teacher_name FROM academic_supervision s LEFT JOIN users u ON s.teacher_id = u.id ORDER BY s.date DESC");
    $list_data = $list_stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisi Akademik</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .supervisi-form input[type="text"],
        .supervisi-form input[type="date"],
        .supervisi-form input[type="time"],
        .supervisi-form input[type="number"],
        .supervisi-form select,
        .supervisi-form textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .supervisi-form textarea {
            resize: vertical;
        }

        .section-header {
            background-color: #f3f4f6;
            padding: 10px 15px;
            font-weight: 700;
            border-left: 4px solid var(--primary);
            margin: 20px 0 10px 0;
            font-size: 1.1rem;
        }

        .score-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            table-layout: fixed;
            /* Fix layout for consistent widths */
        }

        .score-table th,
        .score-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            font-size: 0.9rem;
            vertical-align: top;
            /* Align top for multi-line content */
        }

        .score-table th {
            background-color: #f9fafb;
            text-align: left;
            font-weight: 600;
        }

        .nav-tabs {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            font-weight: 600;
            color: #6b7280;
        }

        .nav-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .print-only {
            display: none;
        }

        .save-bar {
            background: #fdfdfd;
            padding: 10px;
            border-top: 1px solid #eee;
            margin-top: 20px;
            text-align: right;
            position: sticky;
            bottom: 0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            z-index: 100;
        }

        /* Floating close button for forms */
        .close-btn {
            position: fixed;
            top: 4.5rem;
            right: 1rem;
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.2s;
            z-index: 100;
            border: none;
            cursor: pointer;
            text-decoration: none;
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
            .btn {
                padding: 0.5rem 1rem !important;
                font-size: 0.9375rem !important;
                min-width: auto !important;
                max-width: none !important;
                width: auto !important;
            }

            .btn-sm {
                padding: 0.375rem 0.75rem !important;
                font-size: 0.875rem !important;
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

            /* Force header to stay horizontal and FIXED on mobile */
            .main-content .header,
            main.main-content .header,
            .header {
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.75rem !important;
                margin-bottom: 0 !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 100 !important;
                background: white !important;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
                padding: 0.75rem !important;
            }

            .header>div:first-child {
                flex-direction: row !important;
                display: flex !important;
                align-items: center !important;
                width: 100% !important;
            }

            .header>div {
                width: 100% !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.75rem !important;
            }

            .header div>div {
                flex: 1 !important;
                min-width: 0 !important;
            }

            .header h1 {
                font-size: 1rem !important;
                margin: 0 !important;
                line-height: 1.3 !important;
                white-space: normal !important;
                word-break: break-word !important;
            }

            .header p {
                font-size: 0.75rem !important;
                margin: 0.125rem 0 0 0 !important;
                line-height: 1.3 !important;
            }

            .sidebar-toggle {
                flex-shrink: 0 !important;
                width: 2.25rem !important;
                height: 2.25rem !important;
            }

            .main-content {
                padding: 0.875rem !important;
                padding-top: 5rem !important;
            }

            /* Hide button text, show icon only */
            .btn span:not(.bi):not([class*="bi-"]) {
                display: none;
            }

            .btn i {
                margin: 0 !important;
            }

            /* Compact buttons - much smaller */
            .btn {
                padding: 0.25rem 0.4rem !important;
                min-width: auto !important;
                max-width: fit-content !important;
                width: auto !important;
                font-size: 0.75rem;
            }

            .btn-sm {
                padding: 0.2rem 0.35rem !important;
                font-size: 0.7rem;
            }

            .btn i {
                font-size: 0.85rem;
            }

            .btn-primary,
            .btn-secondary {
                flex-shrink: 0 !important;
                width: auto !important;
            }

            /* Card header compact - keep in one row */
            .card>div:first-child {
                flex-wrap: nowrap !important;
                gap: 0.35rem;
                overflow-x: auto;
            }

            .card>div:first-child>div {
                flex: 1 1 auto;
                min-width: 0;
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }

            .card h3 {
                font-size: 0.9rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin: 0;
            }

            .card .btn-primary,
            .card .btn-secondary {
                flex-shrink: 0;
            }

            /* Score tables - horizontal scroll on mobile */
            .score-table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin: 1rem 0;
            }

            .score-table table {
                min-width: 600px;
                font-size: 0.75rem;
            }

            .score-table th,
            .score-table td {
                padding: 0.4rem;
                font-size: 0.7rem;
            }

            .score-table input,
            .score-table select,
            .score-table textarea {
                font-size: 0.7rem;
                padding: 0.3rem;
            }

            /* Make notes column wider on mobile */
            .score-table td:last-child {
                min-width: 180px;
            }

            .score-table td:last-child input {
                min-width: 170px;
            }

            /* Make score column (4th column) wider */
            .score-table td:nth-child(4) {
                min-width: 80px;
            }

            .score-table td:nth-child(4) select {
                min-width: 70px;
            }

            /* Tab navigation compact */
            .nav-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .nav-tab {
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem;
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout no-print">
        <?php include '../layout/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle"><span></span><span></span><span></span></button>
                    <div>
                        <h1>Supervisi Akademik</h1>
                        <p style="color: var(--text-muted)">Evaluasi dan Monitoring Pembelajaran</p>
                    </div>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="alert alert-success"
                    style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                    <?= htmlspecialchars(urldecode($msg)) ?>
                </div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <div class="card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <div style="display:flex; gap:0.5rem; align-items:center;">
                            <a href="admin_administrasi.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i>
                                <span>Kembali</span></a>
                            <h3 style="margin:0;">Riwayat Supervisi</h3>
                        </div>
                        <a href="admin_supervisi_akademik.php?action=add" class="btn btn-primary"><i
                                class="bi bi-plus-lg"></i> <span>Supervisi Baru</span></a>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Guru</th>
                                    <th>Mapel / Kelas</th>
                                    <th>Status / Nilai</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list_data)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada data supervisi.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($list_data as $i => $d): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= date('d/m/Y', strtotime($d['date'])) ?></td>
                                            <td><?= htmlspecialchars($d['teacher_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($d['subject']) ?> (<?= htmlspecialchars($d['class_name']) ?>)
                                            </td>
                                            <td>
                                                <?php if ($d['total_score'] > 0): ?>
                                                    <?= $d['total_score'] ?> (<?= round($d['percentage'], 1) ?>%)
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Dalam Proses</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary"
                                                    onclick="openUploadModal(<?= $d['id'] ?>)"><i class="bi bi-upload"></i></button>
                                                <?php if (!empty($d['document_path'])): ?>
                                                    <button type="button" class="btn btn-sm btn-success"
                                                        onclick="viewPdf('<?= $d['document_path'] ?>')"><i
                                                            class="bi bi-eye"></i></button>
                                                    <a href="<?= $d['document_path'] ?>" download class="btn btn-sm btn-warning"><i
                                                            class="bi bi-download"></i></a>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="admin_supervisi_akademik.php?action=edit&id=<?= $d['id'] ?>"
                                                    class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i> Edit / Lanjut</a>
                                                <a href="#" onclick="printSupervision(<?= $d['id'] ?>)"
                                                    class="btn btn-sm btn-info"><i class="bi bi-printer"></i></a>
                                                <form method="POST" style="display:inline;"
                                                    onsubmit="return confirm('Hapus data ini?');">
                                                    <input type="hidden" name="action_type" value="delete">
                                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i
                                                            class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($action === 'add' || $action === 'edit'):
                $d = $edit_data;
                $plan_scores = safe_json_decode($d['planning_scores'] ?? '[]');
                $plan_notes = safe_json_decode($d['planning_notes'] ?? '[]');

                $exec_scores = safe_json_decode($d['execution_scores'] ?? '[]');
                $exec_notes = safe_json_decode($d['execution_notes'] ?? '[]');
                if (!isset($exec_scores['b1'])) { // Old structure capability check
                    $temp_s = $exec_scores;
                    $temp_n = $exec_notes;
                    $exec_scores = ['b1' => $temp_s['b1'] ?? [], 'b2' => $temp_s['b2'] ?? [], 'b3' => $temp_s['b3'] ?? []];
                    $exec_notes = ['b1' => $temp_n['b1'] ?? [], 'b2' => $temp_n['b2'] ?? [], 'b3' => $temp_n['b3'] ?? []];
                }

                $assess_scores = safe_json_decode($d['assessment_scores'] ?? '[]');
                $assess_notes = safe_json_decode($d['assessment_notes'] ?? '[]');
                ?>
                <!-- FORM -->
                <!-- Floating Close Button -->
                <a href="admin_supervisi_akademik.php" class="close-btn" title="Kembali">
                    <i class="bi bi-x-lg"></i>
                </a>

                <form method="POST" enctype="multipart/form-data" class="supervisi-form" id="supervisionForm">
                    <input type="hidden" name="action_type" value="save">
                    <?php if ($d): ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><?php endif; ?>
                    <input type="hidden" name="school_name" value="<?= htmlspecialchars($school_name) ?>">

                    <div class="card">
                        <div class="nav-tabs">
                            <div class="nav-tab active" onclick="showTab(1)">1. Pra-Observasi</div>
                            <div class="nav-tab" onclick="showTab(2)">2. Instrumen Observasi</div>
                            <div class="nav-tab" onclick="showTab(3)">3. Pasca-Observasi</div>
                        </div>

                        <!-- TAB 1 -->
                        <div id="tab1" class="tab-content active">
                            <h2 class="section-header">BAGIAN 1: PRA-OBSERVASI</h2>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label>Nama Guru <span style="color:red">*</span></label>
                                    <select name="teacher_id" required>
                                        <option value="">-- Pilih Guru --</option>
                                        <?php foreach ($teachers as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= ($d && $d['teacher_id'] == $t['id']) ? 'selected' : '' ?> data-subject="<?= htmlspecialchars($t['subject']) ?>"
                                                data-class="<?= htmlspecialchars($t['assigned_class']) ?>">
                                                <?= htmlspecialchars($t['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Tanggal Pra-Observasi <span style="color:red">*</span></label>
                                    <input type="date" name="date" value="<?= $d['date'] ?? date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <!-- More inputs... NO REQUIRED -->
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top:10px;">
                                <div><label>Mata Pelajaran</label><input type="text" name="subject"
                                        value="<?= $d['subject'] ?? '' ?>"></div>
                                <div><label>Kelas/Fase</label><input type="text" name="class_name"
                                        value="<?= $d['class_name'] ?? '' ?>" placeholder="Contoh: Kelas 4 (Fase B)"></div>
                                <div><label>Alokasi Waktu</label><input type="text" name="time_allocation"
                                        value="<?= $d['time_allocation'] ?? '' ?>"></div>
                                <div><label>Topik / Modul Ajar</label><input type="text" name="topic"
                                        value="<?= $d['topic'] ?? '' ?>"></div>
                            </div>
                            <div style="margin-top:10px;"><label>Capaian Pembelajaran (CP)</label><textarea name="kd"
                                    rows="3"><?= $d['kd'] ?? '' ?></textarea></div>
                            <div><label>Tujuan Pembelajaran (TP)</label><textarea name="indicators"
                                    rows="3"><?= $d['indicators'] ?? '' ?></textarea></div>
                            <div><label>Indikator / KKTP</label><textarea name="objectives"
                                    rows="3"><?= $d['objectives'] ?? '' ?></textarea></div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div><label>Pendekatan / Model Pembelajaran</label><textarea name="methods" rows="2"
                                        placeholder="Misal: PBL, PjBL, Diferensiasi"><?= $d['methods'] ?? '' ?></textarea>
                                </div>
                                <div><label>Media / Sumber Belajar</label><textarea name="media"
                                        rows="2"><?= $d['media'] ?? '' ?></textarea></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div><label>Fokus Observasi (Perilaku Kinerja)</label><textarea name="focus_aspects"
                                        rows="2"><?= $d['focus_aspects'] ?? '' ?></textarea></div>
                                <div><label>Kesiapan Belajar Murid</label><textarea name="special_needs"
                                        rows="2"><?= $d['special_needs'] ?? '' ?></textarea></div>
                            </div>

                            <div class="save-bar">
                                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan
                                    Pra-Observasi</button>
                                <button type="button" class="btn btn-primary" onclick="showTab(2)">Lanjut <i
                                        class="bi bi-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- TAB 2 -->
                        <div id="tab2" class="tab-content">
                            <h2 class="section-header">BAGIAN 2: OBSERVASI PEMBELAJARAN</h2>
                            <div style="margin-bottom:1rem; background:#fef3c7; padding:10px; border-radius:6px;">
                                <label style="font-weight:bold;">Tanggal Observasi:</label>
                                <input type="date" name="observation_date" value="<?= $d['observation_date'] ?? '' ?>"
                                    style="max-width:200px; display:inline-block; margin-left:10px;">
                            </div>

                            <div
                                style="background:#f0f9ff; padding:10px; border-left:4px solid #0ea5e9; margin-bottom:15px; font-size:0.9rem;">
                                <strong>Kriteria Penilaian:</strong><br>
                                <strong>4 (Sangat Baik):</strong> Terlihat Jelas & Konsisten (Membudaya)<br>
                                <strong>3 (Baik):</strong> Terlihat Sering (Sudah Efektif)<br>
                                <strong>2 (Cukup):</strong> Mulai Terlihat (Perlu Perbaikan)<br>
                                <strong>1 (Kurang):</strong> Belum Terlihat
                            </div>

                            <!-- Scoring Tables (All same as before) -->
                            <!-- A -->
                            <!-- A -->
                            <h3>A. Perencanaan & Lingkungan Belajar</h3>
                            <?php
                            $rubric_a = [
                                1 => [
                                    'aspect' => 'Ketersediaan Modul Ajar/RPP',
                                    'criteria' => [
                                        1 => 'Dokumen tidak tersedia/tidak sesuai.',
                                        2 => 'Dokumen ada, namun komponen tidak selaras dengan CP.',
                                        3 => 'Modul/RPP lengkap selaras CP/TP, memuat diferensiasi.',
                                        4 => 'Modul/RPP inspiratif, fleksibel, berdiferensiasi, terintegrasi Profil Pelajar Pancasila.'
                                    ]
                                ],
                                2 => [
                                    'aspect' => 'Lingkungan Kelas Aman & Nyaman',
                                    'criteria' => [
                                        1 => 'Tidak ada kesepakatan, lingkungan tidak mendukung.',
                                        2 => 'Ada kesepakatan tapi belum dipatuhi/direfleksikan.',
                                        3 => 'Kesepakatan diterapkan, partisipasi aktif semua murid.',
                                        4 => 'Kesepakatan hidup, budaya saling menghargai & kolaboratif tinggi.'
                                    ]
                                ],
                                3 => [
                                    'aspect' => 'Kesiapan Diagnosa Kebutuhan',
                                    'criteria' => [
                                        1 => 'Tidak ada bukti diagnosis awal.',
                                        2 => 'Ada data awal tapi belum dianalisis.',
                                        3 => 'Menggunakan instrumen (kuis/survei) untuk pemetaan.',
                                        4 => 'Diagnosis sistematis, digunakan untuk desain pembelajaran personal.'
                                    ]
                                ]
                            ];
                            ?>
                            <table class="score-table">
                                <tr>
                                    <th width="30">No</th>
                                    <th>Aspek Observasi</th>
                                    <th width="300">Kriteria / Rubrik</th>
                                    <th width="80">Skor</th>
                                    <th>Catatan</th>
                                </tr>
                                <?php foreach ($rubric_a as $k => $item): ?>
                                    <tr>
                                        <td><?= $k ?></td>
                                        <td><b><?= $item['aspect'] ?></b></td>
                                        <td style="font-size:0.85rem; line-height:1.2;">
                                            <div style="margin-bottom:2px;"><b style="color:#15803d;">[4]</b>
                                                <?= $item['criteria'][4] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#0369a1;">[3]</b>
                                                <?= $item['criteria'][3] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b45309;">[2]</b>
                                                <?= $item['criteria'][2] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b91c1c;">[1]</b>
                                                <?= $item['criteria'][1] ?></div>
                                        </td>
                                        <td><select name="score_a[<?= $k ?>]" class="score-input" onchange="calcScores()">
                                                <option value="0">-</option>
                                                <?php for ($x = 1; $x <= 4; $x++)
                                                    echo "<option value='$x' " . (($plan_scores[$k] ?? 0) == $x ? 'selected' : '') . ">$x</option>"; ?>
                                            </select></td>
                                        <td><input type="text" name="note_a[<?= $k ?>]" value="<?= $plan_notes[$k] ?? '' ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- B -->
                            <h3>B. Pelaksanaan Pembelajaran</h3>
                            <h4>B1. Pendahuluan</h4>
                            <?php
                            $rubric_b1 = [
                                1 => [
                                    'aspect' => 'Mengkondisikan Suasana Belajar',
                                    'criteria' => [
                                        1 => 'Langsung mulai tanpa menyapa.',
                                        2 => 'Menyapa tapi tidak fokus kehadiran penuh.',
                                        3 => 'Ada ice breaker/kegiatan pemusatan perhatian.',
                                        4 => 'Pembukaan sangat efektif, kreatif, murid siap belajar.'
                                    ]
                                ],
                                2 => [
                                    'aspect' => 'Apersepsi & Motivasi',
                                    'criteria' => [
                                        1 => 'Tidak ada apersepsi.',
                                        2 => 'Apersepsi satu arah (ceramah).',
                                        3 => 'Menggunakan pertanyaan pemantik/fenomena relevan.',
                                        4 => 'Stimulus powerful, menantang pemikiran, murid terlibat aktif.'
                                    ]
                                ],
                                3 => [
                                    'aspect' => 'Menyampaikan TP & Manfaat',
                                    'criteria' => [
                                        1 => 'Tidak disampaikan.',
                                        2 => 'Dibacakan tanpa penjelasan manfaat.',
                                        3 => 'Bahasa murid, dijelaskan manfaatnya.',
                                        4 => 'TP & manfaat dirumuskan/direfleksikan bersama murid.'
                                    ]
                                ]
                            ];
                            ?>
                            <table class="score-table">
                                <tr>
                                    <th width="30">No</th>
                                    <th>Aspek Observasi</th>
                                    <th width="300">Rubrik</th>
                                    <th width="80">Skor</th>
                                    <th>Catatan</th>
                                </tr>
                                <?php foreach ($rubric_b1 as $k => $item): ?>
                                    <tr>
                                        <td><?= $k ?></td>
                                        <td><b><?= $item['aspect'] ?></b></td>
                                        <td style="font-size:0.85rem; line-height:1.2;">
                                            <div style="margin-bottom:2px;"><b style="color:#15803d;">[4]</b>
                                                <?= $item['criteria'][4] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#0369a1;">[3]</b>
                                                <?= $item['criteria'][3] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b45309;">[2]</b>
                                                <?= $item['criteria'][2] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b91c1c;">[1]</b>
                                                <?= $item['criteria'][1] ?></div>
                                        </td>
                                        <td><select name="score_b1[<?= $k ?>]" class="score-input" onchange="calcScores()">
                                                <option value="0">-</option>
                                                <?php for ($x = 1; $x <= 4; $x++)
                                                    echo "<option value='$x' " . (($exec_scores['b1'][$k] ?? 0) == $x ? 'selected' : '') . ">$x</option>"; ?>
                                            </select></td>
                                        <td><input type="text" name="note_b1[<?= $k ?>]"
                                                value="<?= $exec_notes['b1'][$k] ?? '' ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <h4>B2. Kegiatan Inti</h4>
                            <?php
                            $rubric_b2 = [
                                1 => ['aspect' => 'Pembelajaran Berdiferensiasi', 'criteria' => [1 => 'Seragam semua murid.', 2 => 'Tugas beda tapi tidak berdasar diagnosa.', 3 => 'Pilihan di min. 2 aspek (konten/proses/produk).', 4 => 'Diferensiasi utuh, fleksibel, murid punya otonomi.']],
                                2 => ['aspect' => 'Penerapan KSE', 'criteria' => [1 => 'Tidak ada penguatan KSE.', 2 => 'Hanya nasihat lisan.', 3 => 'Kegiatan butuh kolaborasi/empati.', 4 => 'KSE integral, refleksi pengembangan diri murid.']],
                                3 => ['aspect' => 'Melibatkan Murid Aktif', 'criteria' => [1 => 'Didominasi ceramah.', 2 => 'Tanya jawab terbatas.', 3 => 'Murid eksplorasi/diskusi, guru fasilitator.', 4 => 'Student agency tinggi, murid evaluasi proses sendiri.']],
                                4 => ['aspect' => 'Metode/Strategi Variatif', 'criteria' => [1 => 'Satu metode.', 2 => 'Lebih dari satu tapi kurang optimal.', 3 => 'Kombinasi metode sesuai tujuan, interaktif.', 4 => 'Sangat kreatif, kontekstual, penuhi gaya belajar.']],
                                5 => ['aspect' => 'Penggunaan Media/Teknologi', 'criteria' => [1 => 'Tidak relevan/tidak ada.', 2 => 'Konvensional/satu arah.', 3 => 'Interaktif, bantu visualisasi.', 4 => 'Efektif untuk kolaborasi & cipta karya.']],
                                6 => ['aspect' => 'Pengelolaan Kelas', 'criteria' => [1 => 'Tidak terkendali/kaku.', 2 => 'Cenderung reaktif/otoriter.', 3 => 'Disiplin positif, kelas tertib kooperatif.', 4 => 'Murid atur diri sendiri, iklim sangat positif.']],
                                7 => ['aspect' => 'Asesmen Formatif', 'criteria' => [1 => 'Tidak ada.', 2 => 'Hanya pertanyaan lisan.', 3 => 'Beragam teknik (kuis/observasi) & feedback.', 4 => 'Berjalan alami, feedback spesifik & digunakan murid.']],
                                8 => ['aspect' => 'Pengembangan Karakter', 'criteria' => [1 => 'Tidak terintegrasi.', 2 => 'Hanya tempelan/disebut sepintas.', 3 => 'Dirancang kembangkan Profil Pelajar Pancasila.', 4 => 'Nilai hidup dalam interaksi & refleksi.']]
                            ];
                            ?>
                            <table class="score-table">
                                <tr>
                                    <th width="30">No</th>
                                    <th>Aspek Observasi</th>
                                    <th width="300">Rubrik</th>
                                    <th width="80">Skor</th>
                                    <th>Catatan</th>
                                </tr>
                                <?php foreach ($rubric_b2 as $k => $item): ?>
                                    <tr>
                                        <td><?= $k ?></td>
                                        <td><b><?= $item['aspect'] ?></b></td>
                                        <td style="font-size:0.85rem; line-height:1.2;">
                                            <div style="margin-bottom:2px;"><b style="color:#15803d;">[4]</b>
                                                <?= $item['criteria'][4] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#0369a1;">[3]</b>
                                                <?= $item['criteria'][3] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b45309;">[2]</b>
                                                <?= $item['criteria'][2] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b91c1c;">[1]</b>
                                                <?= $item['criteria'][1] ?></div>
                                        </td>
                                        <td><select name="score_b2[<?= $k ?>]" class="score-input" onchange="calcScores()">
                                                <option value="0">-</option>
                                                <?php for ($x = 1; $x <= 4; $x++)
                                                    echo "<option value='$x' " . (($exec_scores['b2'][$k] ?? 0) == $x ? 'selected' : '') . ">$x</option>"; ?>
                                            </select></td>
                                        <td><input type="text" name="note_b2[<?= $k ?>]"
                                                value="<?= $exec_notes['b2'][$k] ?? '' ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <h4>B3. Penutup</h4>
                            <?php
                            $rubric_b3 = [
                                1 => ['aspect' => 'Refleksi Murid dan Guru', 'criteria' => [1 => 'Tidak ada.', 2 => 'Guru menyimpulkan sendiri.', 3 => 'Murid diajak refleksi proses & hasil.', 4 => 'Refleksi mendalam, pengaruhi rencana berikutnya.']],
                                2 => ['aspect' => 'Penyimpulan Pembelajaran', 'criteria' => [1 => 'Tidak ada.', 2 => 'Baca ulang tujuan.', 3 => 'Guru pandu murid menyimpulkan.', 4 => 'Dibangun bersama dengan bahasa murid.']],
                                3 => ['aspect' => 'Rencana Tindak Lanjut', 'criteria' => [1 => 'Tidak ada.', 2 => 'PR rutin tanpa kaitan.', 3 => 'Sampaikan materi berikut/tugas pengayaan.', 4 => 'Diferensiasi & kolaboratif, ada feedback murid.']]
                            ];
                            ?>
                            <table class="score-table">
                                <tr>
                                    <th width="30">No</th>
                                    <th>Aspek Observasi</th>
                                    <th width="300">Rubrik</th>
                                    <th width="80">Skor</th>
                                    <th>Catatan</th>
                                </tr>
                                <?php foreach ($rubric_b3 as $k => $item): ?>
                                    <tr>
                                        <td><?= $k ?></td>
                                        <td><b><?= $item['aspect'] ?></b></td>
                                        <td style="font-size:0.85rem; line-height:1.2;">
                                            <div style="margin-bottom:2px;"><b style="color:#15803d;">[4]</b>
                                                <?= $item['criteria'][4] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#0369a1;">[3]</b>
                                                <?= $item['criteria'][3] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b45309;">[2]</b>
                                                <?= $item['criteria'][2] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b91c1c;">[1]</b>
                                                <?= $item['criteria'][1] ?></div>
                                        </td>
                                        <td><select name="score_b3[<?= $k ?>]" class="score-input" onchange="calcScores()">
                                                <option value="0">-</option>
                                                <?php for ($x = 1; $x <= 4; $x++)
                                                    echo "<option value='$x' " . (($exec_scores['b3'][$k] ?? 0) == $x ? 'selected' : '') . ">$x</option>"; ?>
                                            </select></td>
                                        <td><input type="text" name="note_b3[<?= $k ?>]"
                                                value="<?= $exec_notes['b3'][$k] ?? '' ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <!-- C -->
                            <h3>C. Asesmen Hasil Belajar</h3>
                            <?php
                            $rubric_c = [
                                1 => ['aspect' => 'Teknik Penilaian', 'criteria' => [1 => 'Hanya 1 teknik (tulis).', 2 => 'Lebih dari 1 tapi belum holistik.', 3 => 'Beragam (tes, observasi, proyek).', 4 => 'Variatif, inovatif, holistik & otentik.']],
                                2 => ['aspect' => 'Kriteria Penilaian', 'criteria' => [1 => 'Tidak jelas/subjektif.', 2 => 'Ada rubrik tapi tidak diinfokan.', 3 => 'Rubrik jelas & dipahami murid.', 4 => 'Rubrik dikembangkan bersama, untuk self-assessment.']],
                                3 => ['aspect' => 'Keautentikan', 'criteria' => [1 => 'Tidak ada konteks nyata.', 2 => 'Kontekstual tapi artifisial.', 3 => 'Terkait masalah nyata.', 4 => 'Libatkan audiens nyata, produk bermanfaat.']]
                            ];
                            ?>
                            <table class="score-table">
                                <tr>
                                    <th width="30">No</th>
                                    <th>Aspek Observasi</th>
                                    <th width="300">Rubrik</th>
                                    <th width="80">Skor</th>
                                    <th>Catatan</th>
                                </tr>
                                <?php foreach ($rubric_c as $k => $item): ?>
                                    <tr>
                                        <td><?= $k ?></td>
                                        <td><b><?= $item['aspect'] ?></b></td>
                                        <td style="font-size:0.85rem; line-height:1.2;">
                                            <div style="margin-bottom:2px;"><b style="color:#15803d;">[4]</b>
                                                <?= $item['criteria'][4] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#0369a1;">[3]</b>
                                                <?= $item['criteria'][3] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b45309;">[2]</b>
                                                <?= $item['criteria'][2] ?></div>
                                            <div style="margin-bottom:2px;"><b style="color:#b91c1c;">[1]</b>
                                                <?= $item['criteria'][1] ?></div>
                                        </td>
                                        <td><select name="score_c[<?= $k ?>]" class="score-input" onchange="calcScores()">
                                                <option value="0">-</option>
                                                <?php for ($x = 1; $x <= 4; $x++)
                                                    echo "<option value='$x' " . (($assess_scores[$k] ?? 0) == $x ? 'selected' : '') . ">$x</option>"; ?>
                                            </select></td>
                                        <td><input type="text" name="note_c[<?= $k ?>]" value="<?= $assess_notes[$k] ?? '' ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div><label>Hal-hal yang sudah baik (Kekuatan)</label><textarea name="strengths"
                                        rows="3"><?= $d['strengths'] ?? '' ?></textarea></div>
                                <div><label>Hal-hal yang perlu dikembangkan</label><textarea name="improvement"
                                        rows="3"><?= $d['areas_for_improvement'] ?? '' ?></textarea></div>
                            </div>

                            <div style="background:#f0f9ff; padding:15px; border:1px solid #bae6fd; margin-top:10px;">
                                Total Skor: <span id="display_total">0</span> (Nilai: <span
                                    id="display_percentage">0</span>%)
                                <input type="hidden" name="total_score_input" id="input_total"
                                    value="<?= $d['total_score'] ?? 0 ?>">
                                <input type="hidden" name="percentage_input" id="input_percentage"
                                    value="<?= $d['percentage'] ?? 0 ?>">
                                | Predikat:
                                <select name="recommendation">
                                    <option value="">-pilih-</option>
                                    <option value="Sangat Baik" <?= ($d['recommendation'] ?? '') == 'Sangat Baik' ? 'selected' : '' ?>>Sangat Baik</option>
                                    <option value="Baik" <?= ($d['recommendation'] ?? '') == 'Baik' ? 'selected' : '' ?>>Baik
                                    </option>
                                    <option value="Cukup" <?= ($d['recommendation'] ?? '') == 'Cukup' ? 'selected' : '' ?>>
                                        Cukup</option>
                                    <option value="Perlu Perbaikan" <?= ($d['recommendation'] ?? '') == 'Perlu Perbaikan' ? 'selected' : '' ?>>Perlu Perbaikan</option>
                                </select>
                            </div>

                            <div class="save-bar">
                                <button type="button" class="btn btn-secondary" onclick="showTab(1)"><i
                                        class="bi bi-arrow-left"></i> Kembali</button>
                                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan
                                    Observasi</button>
                                <button type="button" class="btn btn-primary" onclick="showTab(3)">Lanjut <i
                                        class="bi bi-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- TAB 3 -->
                        <div id="tab3" class="tab-content">
                            <h2 class="section-header">BAGIAN 3: PASCA-OBSERVASI (Umpan Balik & Coaching)</h2>
                            <div style="margin-bottom:1rem; background:#dcfce7; padding:10px; border-radius:6px;">
                                <label style="font-weight:bold;">Tanggal Pasca-Observasi:</label>
                                <input type="date" name="post_date" value="<?= $d['post_date'] ?? '' ?>"
                                    style="max-width:200px; display:inline-block; margin-left:10px;">
                            </div>

                            <div><label>Refleksi Guru (Perasaan & Evaluasi Diri)</label><textarea name="post_reflection"
                                    rows="3"><?= $d['post_reflection'] ?? '' ?></textarea></div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div><label>Umpan Balik Supervisor (Apresiasi)</label><textarea
                                        name="post_feedback_strengths"
                                        rows="3"><?= $d['post_feedback_strengths'] ?? '' ?></textarea></div>
                                <div><label>Umpan Balik Supervisor (Saran Konstruktif)</label><textarea
                                        name="post_feedback_improvements"
                                        rows="3"><?= $d['post_feedback_improvements'] ?? '' ?></textarea></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div><label>Rencana Pengembangan Diri</label><textarea name="post_action_plan_targets"
                                        rows="3"><?= $d['post_action_plan_targets'] ?? '' ?></textarea></div>
                                <div><label>Dukungan yang Dibutuhkan</label><textarea name="post_action_plan_support"
                                        rows="3"><?= $d['post_action_plan_support'] ?? '' ?></textarea></div>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top:10px;">
                                <div><label>Timeline / Waktu Pelaksanaan</label><input type="text" name="post_timeline"
                                        value="<?= $d['post_timeline'] ?? '' ?>"></div>
                                <div><label>Jadwal Supervisi Selanjutnya</label><input type="date" name="post_next_date"
                                        value="<?= $d['post_next_date'] ?? '' ?>"></div>
                            </div>
                            <div style="margin-top:20px;">
                                <label>Foto Dokumen</label>
                                <input type="file" name="photo_doc" accept="image/*">
                                <?php if (isset($d['photo_path']) && $d['photo_path']): ?>
                                    <div style="margin-top:5px;"><img src="<?= $d['photo_path'] ?>" style="height:100px;"></div>
                                    <input type="hidden" name="existing_photo_path" value="<?= $d['photo_path'] ?>">
                                <?php endif; ?>
                            </div>

                            <div class="save-bar">
                                <button type="button" class="btn btn-secondary" onclick="showTab(2)"><i
                                        class="bi bi-arrow-left"></i> Kembali</button>
                                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Simpan
                                    Pasca-Observasi (Selesai)</button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </main>
    </div>
    <script>
        function showTab(n) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));
            document.getElementById('tab' + n).classList.add('active');
            document.querySelectorAll('.nav-tab')[n - 1].classList.add('active');
        }
        function calcScores() {
            let total = 0;
            document.querySelectorAll('.score-input').forEach(el => { total += parseInt(el.value || 0); });
            let max = 80;
            let pct = (total / max) * 100;
            document.getElementById('display_total').innerText = total;
            document.getElementById('input_total').value = total;

            let finalPct = pct.toFixed(1);
            document.getElementById('display_percentage').innerText = finalPct;
            document.getElementById('input_percentage').value = finalPct;

            // Auto Recommendation based on Percentage
            // 91 - 100 : Sangat Baik
            // 81 - 90  : Baik
            // 71 - 80  : Cukup
            // < 71     : Perlu Perbaikan

            let recSelect = document.querySelector('select[name="recommendation"]');
            let recVal = "";

            if (finalPct >= 91) recVal = "Sangat Baik";
            else if (finalPct >= 81) recVal = "Baik";
            else if (finalPct >= 71) recVal = "Cukup";
            else if (total > 0 && finalPct < 71) recVal = "Perlu Perbaikan";
            else if (total === 0) recVal = "";

            if (recVal) {
                recSelect.value = recVal;
            } else if (total === 0) {
                recSelect.value = "";
            }
        }
        document.querySelector('select[name="teacher_id"]')?.addEventListener('change', function () {
            let opt = this.options[this.selectedIndex];
            if (opt.value) {
                document.querySelector('input[name="subject"]').value = opt.getAttribute('data-subject') || '';
                document.querySelector('input[name="class_name"]').value = opt.getAttribute('data-class') || '';
            }
        });
        if (document.querySelector('.score-input')) calcScores();
        function printSupervision(id) { window.open('../print/get_supervisiakademik_print.php?id=' + id, '_blank'); }
    </script>
    <!-- Upload Modal -->
    <div id="uploadModal" class="modal"
        style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
        <div class="modal-content"
            style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:40%; border-radius:8px;">
            <span onclick="closeUploadModal()"
                style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            <h2 style="margin-top:0;">Upload Dokumen PDF</h2>
            <form action="admin_supervisi_akademik.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action_type" value="upload_pdf">
                <input type="hidden" name="id" id="upload_id">
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px;">Pilih File PDF</label>
                    <input type="file" name="pdf_file" accept=".pdf" required
                        style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div style="text-align:right;">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        /* PDF Modal Styles */
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
            font-size: 1.2rem;
            margin-top: 20px;
        }
    </style>

    <!-- PDF Viewer Modal -->
    <div id="pdfModal">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <h3 style="margin:0;">Lihat Dokumen</h3>
                <span onclick="closePdfModal()" style="cursor:pointer; font-size:24px;">&times;</span>
            </div>
            <div id="pdf-controls" style="background:#444; padding:10px; display:none; text-align:center; color:white;">
                <button type="button" class="btn btn-sm btn-secondary" id="prevBtn"><i class="bi bi-chevron-left"></i>
                    Prev</button>
                <span style="margin:0 15px;">Halaman <span id="page_num"></span> dari <span
                        id="page_count"></span></span>
                <button type="button" class="btn btn-sm btn-secondary" id="nextBtn">Next <i
                        class="bi bi-chevron-right"></i></button>
            </div>
            <div class="modal-body-pdf">
                <div id="loading">Memuat PDF...</div>
                <canvas id="pdf-canvas"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Upload Modal Logic
        function openUploadModal(id) {
            document.getElementById('upload_id').value = id;
            document.getElementById('uploadModal').style.display = "block";
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = "none";
        }

        // PDF Viewer Logic
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
                const viewport = page.getViewport({
                    scale: scale
                });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                const renderTask = page.render(renderContext);

                renderTask.promise.then(function () {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('page_num').textContent = num;
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
            document.getElementById('pdfModal').style.display = 'block';
            loading.style.display = 'block';
            loading.innerText = 'Menyiapkan dokumen...';
            document.getElementById('pdf-controls').style.display = 'none';

            // Clear previous render
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            try {
                // Fetch using our utility script to handle paths and return base64
                // match functionality of admin_administrasi_managedata.php
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
                if (!response.ok) throw new Error('Gagal menghubungi server');

                const json = await response.json();
                if (json.error) throw new Error(json.error);

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
                loading.innerText = 'Gagal memuat PDF: ' + err.message;
            }
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = "none";
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        // Close modal if user clicks outside
        window.onclick = function (event) {
            if (event.target == document.getElementById('uploadModal')) {
                closeUploadModal();
            }
            if (event.target == document.getElementById('pdfModal')) {
                closePdfModal();
            }
        }
    </script>

    <!-- Sidebar Toggle Script -->
    <script src="../assets/admin-sidebar.js"></script>
</body>

</html>