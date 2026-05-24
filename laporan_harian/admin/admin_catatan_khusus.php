<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Catatan Khusus Kepala Sekolah';
$msg = '';
$msgType = '';

// ACTION HANDLER
$view_action = $_GET['action'] ?? 'list'; // list, add, edit
$edit_id = $_GET['id'] ?? null;

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_record') {
        // A. IDENTITAS
        $teacher_id = $_POST['teacher_id'] ?? '';
        $teacher_rank = $_POST['teacher_rank'] ?? '';
        $years_of_service = $_POST['years_of_service'] ?? null;
        $school_year = $_POST['school_year'] ?? '';
        $student_name = $_POST['student_name'] ?? '';
        $student_class = $_POST['student_class'] ?? '';
        $staff_name = $_POST['staff_name'] ?? '';
        $staff_position = $_POST['staff_position'] ?? '';
        $subject_field = $_POST['subject_field'] ?? '';

        // B. JENIS CATATAN
        $record_type = $_POST['record_type'] ?? '';
        $record_type_other = $_POST['record_type_other'] ?? '';

        // C. DESKRIPSI KEJADIAN
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $event_location = $_POST['event_location'] ?? '';
        $event_description = $_POST['event_description'] ?? '';

        // D. ANALISIS
        $analysis_cause = $_POST['analysis_cause'] ?? '';
        $impact_individual = $_POST['impact_individual'] ?? '';
        $impact_team = $_POST['impact_team'] ?? '';
        $impact_school = $_POST['impact_school'] ?? '';

        // E. TINDAKAN
        $actions_data = $_POST['actions_data'] ?? '';

        // F. REKOMENDASI
        $recommendation_individual = $_POST['recommendation_individual'] ?? '';
        $recommendation_deadline = $_POST['recommendation_deadline'] ?? '';
        $recommendation_system = $_POST['recommendation_system'] ?? '';
        $recommendation_pic = $_POST['recommendation_pic'] ?? '';

        // G. MONITORING
        $monitoring_date = $_POST['monitoring_date'] ?? '';
        $monitoring_progress = $_POST['monitoring_progress'] ?? '';
        $evaluation_status = $_POST['evaluation_status'] ?? '';
        $additional_notes = $_POST['additional_notes'] ?? '';

        // H. PENUTUP
        $completion_date = $_POST['completion_date'] ?? date('Y-m-d');
        $acknowledged_by = $_POST['acknowledged_by'] ?? '';
        $acknowledged_date = $_POST['acknowledged_date'] ?? '';

        // Add school_name field
        $school_name = $_POST['school_name'] ?? '';

        if (empty($_POST['id'])) {
            $stmt = $db->prepare("INSERT INTO anecdotal_records 
                (teacher_id, teacher_rank, years_of_service, school_year, school_name, student_name, student_class, 
                 staff_name, staff_position, subject_field, record_type, record_type_other,
                 event_date, event_time, event_location, event_description, 
                 analysis_cause, impact_individual, impact_team, impact_school,
                 actions_data, recommendation_individual, recommendation_deadline, recommendation_system, 
                 recommendation_pic, monitoring_date, monitoring_progress, evaluation_status, 
                 additional_notes, completion_date, acknowledged_by, acknowledged_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
            $stmt->execute([
                $teacher_id,
                $teacher_rank,
                $years_of_service,
                $school_year,
                $school_name,
                $student_name,
                $student_class,
                $staff_name,
                $staff_position,
                $subject_field,
                $record_type,
                $record_type_other,
                $event_date,
                $event_time,
                $event_location,
                $event_description,
                $analysis_cause,
                $impact_individual,
                $impact_team,
                $impact_school,
                $actions_data,
                $recommendation_individual,
                $recommendation_deadline,
                $recommendation_system,
                $recommendation_pic,
                $monitoring_date,
                $monitoring_progress,
                $evaluation_status,
                $additional_notes,
                $completion_date,
                $acknowledged_by,
                $acknowledged_date
            ]);
            $msg = "Catatan khusus berhasil ditambahkan.";
            $msgType = "success";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("UPDATE anecdotal_records SET
                teacher_id = ?, teacher_rank = ?, years_of_service = ?, school_year = ?, school_name = ?, student_name = ?, student_class = ?,
                staff_name = ?, staff_position = ?, subject_field = ?, record_type = ?, record_type_other = ?,
                event_date = ?, event_time = ?, event_location = ?, event_description = ?,
                analysis_cause = ?, impact_individual = ?, impact_team = ?, impact_school = ?,
                actions_data = ?, recommendation_individual = ?, recommendation_deadline = ?, recommendation_system = ?,
                recommendation_pic = ?, monitoring_date = ?, monitoring_progress = ?, evaluation_status = ?,
                additional_notes = ?, completion_date = ?, acknowledged_by = ?, acknowledged_date = ?
                WHERE id = ?");
            $stmt->execute([
                $teacher_id,
                $teacher_rank,
                $years_of_service,
                $school_year,
                $school_name,
                $student_name,
                $student_class,
                $staff_name,
                $staff_position,
                $subject_field,
                $record_type,
                $record_type_other,
                $event_date,
                $event_time,
                $event_location,
                $event_description,
                $analysis_cause,
                $impact_individual,
                $impact_team,
                $impact_school,
                $actions_data,
                $recommendation_individual,
                $recommendation_deadline,
                $recommendation_system,
                $recommendation_pic,
                $monitoring_date,
                $monitoring_progress,
                $evaluation_status,
                $additional_notes,
                $completion_date,
                $acknowledged_by,
                $acknowledged_date,
                $id
            ]);
            $msg = "Catatan khusus berhasil diperbarui.";
            $msgType = "success";
        }
        header("Location: admin_catatan_khusus.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;

    } elseif ($action === 'upload_doc') {
        $id = $_POST['id'];
        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $target_dir = "../uploads/anecdotal/";
            if (!file_exists($target_dir))
                mkdir($target_dir, 0777, true);

            $file_extension = pathinfo($_FILES["document"]["name"], PATHINFO_EXTENSION);
            $new_filename = "anecdotal_" . $id . "_" . date("YmdHis") . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            $stmt_old = $db->prepare("SELECT document_path FROM anecdotal_records WHERE id = ?");
            $stmt_old->execute([$id]);
            $old_file = $stmt_old->fetchColumn();

            if (move_uploaded_file($_FILES["document"]["tmp_name"], $target_file)) {
                if ($old_file && file_exists($old_file))
                    unlink($old_file);
                $stmt = $db->prepare("UPDATE anecdotal_records SET document_path = ? WHERE id = ?");
                $stmt->execute([$target_file, $id]);
                $msg = "Dokumen berhasil diupload.";
                $msgType = "success";
            } else {
                $msg = "Maaf, terjadi kesalahan saat mengupload file.";
                $msgType = "error";
            }
        }
        header("Location: admin_catatan_khusus.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;

    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $stmt_file = $db->prepare("SELECT document_path FROM anecdotal_records WHERE id = ?");
        $stmt_file->execute([$id]);
        $doc_path = $stmt_file->fetchColumn();
        if ($doc_path && file_exists($doc_path))
            unlink($doc_path);

        $stmt = $db->prepare("DELETE FROM anecdotal_records WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Catatan khusus berhasil dihapus.";
        $msgType = "success";
        header("Location: admin_catatan_khusus.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;

    } elseif ($action === 'edit_teacher_note') {
        $id = $_POST['tn_id'];
        $student_id = $_POST['tn_student_id'];
        $type = $_POST['tn_type'];
        $category = $_POST['tn_category'];
        $description = $_POST['tn_description'];
        $date = $_POST['tn_date'];

        $stmt = $db->prepare("UPDATE student_notes SET student_id=?, type=?, category=?, description=?, date=? WHERE id=?");
        $stmt->execute([$student_id, $type, $category, $description, $date, $id]);
        $msg = "Catatan guru berhasil diperbarui.";
        $msgType = "success";
        header("Location: admin_catatan_khusus.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;

    } elseif ($action === 'delete_teacher_note') {
        $id = $_POST['tn_id'];
        $stmt = $db->prepare("DELETE FROM student_notes WHERE id=?");
        $stmt->execute([$id]);
        $msg = "Catatan guru berhasil dihapus.";
        $msgType = "success";
        header("Location: admin_catatan_khusus.php?msg=" . urlencode($msg) . "&type=" . $msgType);
        exit;
    }
}

// Ensure columns and table exist (Migrations)
$db->exec("CREATE TABLE IF NOT EXISTS anecdotal_records (id INTEGER PRIMARY KEY AUTOINCREMENT, teacher_id INTEGER, teacher_rank TEXT, years_of_service INTEGER, school_year TEXT, school_name TEXT, student_name TEXT, student_class TEXT, staff_name TEXT, staff_position TEXT, subject_field TEXT, record_type TEXT, record_type_other TEXT, event_date TEXT, event_time TEXT, event_location TEXT, event_description TEXT, analysis_cause TEXT, impact_individual TEXT, impact_team TEXT, impact_school TEXT, actions_data TEXT, recommendation_individual TEXT, recommendation_deadline TEXT, recommendation_system TEXT, recommendation_pic TEXT, monitoring_date TEXT, monitoring_progress TEXT, evaluation_status TEXT, additional_notes TEXT, completion_date TEXT, acknowledged_by TEXT, acknowledged_date TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
try {
    $db->exec("ALTER TABLE anecdotal_records ADD COLUMN school_name TEXT");
} catch (Exception $e) {
}
try {
    $db->exec("ALTER TABLE anecdotal_records ADD COLUMN document_path TEXT");
} catch (Exception $e) {
}

$msg = $_GET['msg'] ?? '';
$msgType = $_GET['type'] ?? '';

// Fetch Data for List
$records = [];
$teacherNotes = [];
if ($view_action === 'list') {
    $stmt = $db->query("SELECT ar.*, u.full_name, u.nip, u.subject, u.assigned_class FROM anecdotal_records ar LEFT JOIN users u ON ar.teacher_id = u.id ORDER BY ar.created_at DESC");
    $records = $stmt->fetchAll();

    $teacherNotes = $db->query("SELECT sn.*, s.name as student_name, s.class_name, u.full_name as teacher_name FROM student_notes sn LEFT JOIN students s ON sn.student_id = s.id LEFT JOIN users u ON s.class_name = u.assigned_class AND u.role = 'guru' ORDER BY sn.date DESC, sn.id DESC")->fetchAll();
}

// Fetch Dropdown Data
$schoolInfo = $db->query("SELECT school_name FROM settings LIMIT 1")->fetch();
$schoolName = $schoolInfo['school_name'] ?? 'SD NEGERI CONTOH';
$teachers = $db->query("SELECT id, full_name, nip, subject, assigned_class FROM users WHERE role = 'guru' ORDER BY full_name")->fetchAll();
$students = $db->query("SELECT id, full_name, assigned_class FROM users WHERE role = 'siswa' ORDER BY assigned_class, full_name")->fetchAll();
$classes = $db->query("SELECT DISTINCT name FROM classes ORDER BY name")->fetchAll();
$subjects = $db->query("SELECT DISTINCT name FROM subjects ORDER BY name")->fetchAll();
$allStudents = $db->query("SELECT id, name, class_name FROM students ORDER BY class_name, name")->fetchAll();

// Fetch Edit Data
$d = null;
if ($view_action === 'edit' && $edit_id) {
    $stmt = $db->prepare("SELECT * FROM anecdotal_records WHERE id = ?");
    $stmt->execute([$edit_id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$d) {
        header("Location: admin_catatan_khusus.php");
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .form-section h4 {
            margin-bottom: 1rem;
            color: var(--primary);
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
        }

        .checkbox-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .checkbox-list label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .identity-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .identity-tab {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            background: white;
        }

        .identity-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .identity-section {
            display: none;
        }

        .identity-section.active {
            display: block;
        }

        #actionsTable {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        #actionsTable th,
        #actionsTable td {
            border: 1px solid #ddd;
            padding: 0.5rem;
        }

        #actionsTable input,
        #actionsTable textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            background: white;
            padding: 0.4rem;
            border-radius: 4px;
        }

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

        /* Utility */
        .text-muted {
            color: #64748b;
        }

        /* Responsive */
        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .header-content {
                gap: 0.5rem;
            }

            .header h1 {
                font-size: 1.2rem;
                margin: 0;
            }

            .header p {
                display: none;
            }

            .btn-responsive-text {
                display: none;
            }

            .card-header-actions h2 {
                font-size: 1.1rem;
            }

            .card-header-actions h2 {
                font-size: 1.1rem;
            }

            .table-container {
                overflow-x: auto;
            }

            /* Section E Actions Table Mobile Optimization */
            .actions-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-top: 0.5rem;
                border: 1px solid #e2e8f0;
                /* Optional visual border */
            }

            #actionsTable {
                margin: 0;
                /* Remove margin inside wrapper */
                min-width: 600px;
                /* Force scroll on small screens */
            }

            #actionsTable th,
            #actionsTable td {
                font-size: 0.75rem;
                /* Smaller font */
                padding: 0.25rem;
                /* Tighter padding */
            }

            #actionsTable input,
            #actionsTable textarea {
                font-size: 0.75rem;
                padding: 0.2rem;
            }

            /* Force 2 columns on mobile for specific rows */
            .form-row-forced-2-col {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
                /* Tighter gap for mobile */
            }

            /* Record Type Mobile Optimization */
            .checkbox-list {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
            }

            .checkbox-list label {
                font-size: 0.85rem;
            }

            /* Mobile Padding & Font Adjustments */
            .card {
                padding: 0.75rem;
            }

            .form-section {
                padding: 0.75rem;
            }

            .form-section h4 {
                font-size: 1rem;
            }
        }

        /* Custom 2:1 grid for student name row */
        .form-row-2fr-1fr {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .form-row-2fr-1fr {
                gap: 0.5rem;
            }
        }
        }

        /* Default desktop style for forced 2-col */
        .form-row-forced-2-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-2fr-1fr {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-content">
                    <button class="sidebar-toggle" id="sidebarToggle"><span></span><span></span><span></span></button>
                    <div>
                        <h1><?= $page_title ?></h1>
                        <p style="color: var(--text-muted)">Anecdotal Record</p>
                    </div>
                </div>
            </header>

            <?php if ($msg): ?>
                <div
                    style="background: <?= $msgType === 'success' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $msgType === 'success' ? '#065f46' : '#991b1b' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- LIST VIEW -->
            <?php if ($view_action === 'list'): ?>
                <!-- Main Records -->
                <div class="card">
                    <div class="card-header-actions">
                        <a href="admin_administrasi.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i>
                            <span class="btn-responsive-text">Kembali</span></a>
                        <a href="admin_catatan_khusus.php?action=add" class="btn btn-primary"><i class="bi bi-plus-lg"></i>
                            <span class="btn-responsive-text">Tambah Catatan Khusus</span></a>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Jenis Catatan</th>
                                    <th>Perihal</th>
                                    <th>Tahun Ajaran</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Belum ada catatan khusus.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($records as $i => $row): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars($row['event_date'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($row['record_type'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars(substr($row['event_description'] ?? '', 0, 50)) ?>...</td>
                                            <td><?= htmlspecialchars($row['school_year'] ?? '-') ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <?php if (!empty($row['document_path']) && file_exists($row['document_path'])): ?>
                                                        <button class="btn btn-sm btn-info btn-view-pdf"
                                                            data-pdf-path="<?= htmlspecialchars($row['document_path']) ?>"
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
                                                    <a href="admin_catatan_khusus.php?action=edit&id=<?= $row['id'] ?>"
                                                        class="btn btn-sm btn-secondary"><i class="bi bi-pencil"></i></a>
                                                    <button class="btn btn-sm btn-primary"
                                                        onclick="printRecord(<?= $row['id'] ?>)"><i
                                                            class="bi bi-printer"></i></button>
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="openDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['event_date'] ?? 'Data', ENT_QUOTES) ?>')"><i
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

                <!-- Teacher Notes Section -->
                <div class="card" style="margin-top: 2rem;">
                    <div class="card-header-actions">
                        <div>
                            <h2 style="margin: 0; font-size: 1.5rem;">Catatan Prestasi & Pelanggaran Siswa</h2>
                            <p style="color: var(--text-muted); margin: 0.25rem 0 0 0;">Data dari semua kelas yang diinput
                                oleh guru</p>
                        </div>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Siswa</th>
                                    <th>Kelas</th>
                                    <th>Jenis</th>
                                    <th>Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Guru</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teacherNotes)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Belum ada catatan dari guru.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teacherNotes as $i => $note): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($note['date']))) ?></td>
                                            <td><?= htmlspecialchars($note['student_name'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($note['class_name'] ?? '-') ?></td>
                                            <td>
                                                <span class="badge"
                                                    style="background: <?= $note['type'] == 'Prestasi' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $note['type'] == 'Prestasi' ? '#065f46' : '#991b1b' ?>; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem;">
                                                    <?= htmlspecialchars($note['type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($note['category'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars(substr($note['description'] ?? '', 0, 60)) ?>...</td>
                                            <td><?= htmlspecialchars($note['teacher_name'] ?? '-') ?></td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="btn btn-sm btn-secondary"
                                                        onclick='openEditTeacherNoteModal(<?= json_encode($note) ?>)'><i
                                                            class="bi bi-pencil"></i></button>
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="openDeleteTeacherNoteModal(<?= $note['id'] ?>, '<?= htmlspecialchars($note['student_name'] ?? 'Data', ENT_QUOTES) ?>')"><i
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

                <!-- ADD / EDIT VIEW -->
            <?php elseif ($view_action === 'add' || $view_action === 'edit'): ?>
                <div class="card">
                    <div class="card-header-actions">
                        <h2><?= $view_action === 'edit' ? 'Edit Catatan Khusus' : 'Tambah Catatan Khusus' ?></h2>
                        <a href="admin_catatan_khusus.php"><i class="bi bi-x-lg"></i> <span
                                class="btn-responsive-text">Batal</span></a>
                    </div>

                    <form method="POST" id="recordForm">
                        <input type="hidden" name="action" value="save_record">
                        <?php if ($d): ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><?php endif; ?>

                        <div class="form-section">
                            <div class="form-row-forced-2-col">
                                <div class="form-group">
                                    <label class="form-label">SD</label>
                                    <input type="text" name="school_name" class="form-control"
                                        value="<?= htmlspecialchars($d['school_name'] ?? $schoolName) ?>" readonly
                                        style="background-color: #f0f0f0; font-size: 0.85rem;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tahun Ajaran *</label>
                                    <input type="text" name="school_year" class="form-control" placeholder="2023/2024"
                                        required value="<?= $d['school_year'] ?? '' ?>">
                                </div>
                            </div>
                        </div>

                        <!-- A. Identification -->
                        <div class="form-section">
                            <h4>A. IDENTIFIKASI</h4>
                            <div class="identity-type-tabs">
                                <button type="button" class="identity-tab active"
                                    onclick="switchIdentityType('siswa')">Siswa</button>
                                <button type="button" class="identity-tab"
                                    onclick="switchIdentityType('guru')">Guru/TK</button>
                                <button type="button" class="identity-tab"
                                    onclick="switchIdentityType('lainnya')">Lainnya</button>
                            </div>

                            <div id="identity-siswa" class="identity-section active">
                                <div class="form-row-2fr-1fr">
                                    <div class="form-group">
                                        <label class="form-label">Nama Siswa</label>
                                        <select name="student_name" id="student_name" class="form-control"
                                            onchange="updateStudentClass()">
                                            <option value="">-- Pilih Siswa --</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?= htmlspecialchars($student['full_name']) ?>"
                                                    data-class="<?= htmlspecialchars($student['assigned_class']) ?>" <?= ($d && $d['student_name'] == $student['full_name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($student['full_name']) ?>
                                                    (<?= htmlspecialchars($student['assigned_class']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Kelas</label>
                                        <select name="student_class" id="student_class" class="form-control">
                                            <option value="">-- Pilih Kelas --</option>
                                            <?php foreach ($classes as $class): ?>
                                                <option value="<?= htmlspecialchars($class['name']) ?>" <?= ($d && $d['student_class'] == $class['name']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($class['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div id="identity-guru" class="identity-section">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Nama Guru</label>
                                        <select name="teacher_id" id="teacher_id" class="form-control"
                                            onchange="updateTeacherInfo()">
                                            <option value="">-- Pilih Guru --</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                                <option value="<?= $teacher['id'] ?>"
                                                    data-nip="<?= htmlspecialchars($teacher['nip'] ?? '') ?>" <?= ($d && $d['teacher_id'] == $teacher['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($teacher['full_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">NIP</label>
                                        <input type="text" name="teacher_nip" id="teacher_nip" class="form-control" readonly
                                            style="background-color: #f0f0f0;">
                                    </div>
                                </div>
                                <div class="form-row-forced-2-col" style="margin-top:1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Pangkat / Golongan</label>
                                        <input type="text" name="teacher_rank" class="form-control"
                                            value="<?= $d['teacher_rank'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Masa Kerja (Tahun)</label>
                                        <input type="number" name="years_of_service" class="form-control"
                                            value="<?= $d['years_of_service'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div id="identity-lainnya" class="identity-section">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label class="form-label">Nama</label>
                                        <input type="text" name="staff_name" class="form-control"
                                            value="<?= $d['staff_name'] ?? '' ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Jabatan/Posisi</label>
                                        <input type="text" name="staff_position" class="form-control"
                                            value="<?= $d['staff_position'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:1rem;">
                                <label class="form-label">Bidang/Mata Pelajaran (jika relevan)</label>
                                <select name="subject_field" class="form-control">
                                    <option value="">-- Pilih Mata Pelajaran --</option>
                                    <?php foreach ($subjects as $s): ?>
                                        <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($d && $d['subject_field'] == $s['name']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="Lainnya" <?= ($d && $d['subject_field'] == 'Lainnya') ? 'selected' : '' ?>>
                                        Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <!-- B. Record Type -->
                        <div class="form-section">
                            <h4>B. JENIS CATATAN</h4>
                            <div class="checkbox-list">
                                <?php $rt = $d['record_type'] ?? ''; ?>
                                <label><input type="radio" name="record_type" value="Prestasi/Penghargaan"
                                        <?= $rt == 'Prestasi/Penghargaan' ? 'checked' : '' ?>> Prestasi/Penghargaan</label>
                                <label><input type="radio" name="record_type" value="Pelanggaran/Kekurangan"
                                        <?= $rt == 'Pelanggaran/Kekurangan' ? 'checked' : '' ?>> Pelanggaran</label>
                                <label><input type="radio" name="record_type" value="Masalah/Problem"
                                        <?= $rt == 'Masalah/Problem' ? 'checked' : '' ?>> Masalah/Problem</label>
                                <label><input type="radio" name="record_type" value="Inovasi/Kreativitas"
                                        <?= $rt == 'Inovasi/Kreativitas' ? 'checked' : '' ?>> Inovasi/Kreativitas</label>
                                <label><input type="radio" name="record_type" value="Kejadian Istimewa" <?= $rt == 'Kejadian Istimewa' ? 'checked' : '' ?>> Kejadian Istimewa</label>
                            </div>
                            <div class="form-group" style="margin-top: 1rem;">
                                <label class="form-label">Lainnya</label>
                                <input type="text" name="record_type_other" class="form-control"
                                    value="<?= $d['record_type_other'] ?? '' ?>">
                            </div>
                        </div>

                        <!-- C. Description -->
                        <div class="form-section">
                            <h4>C. DESKRIPSI KEJADIAN/FAKTA</h4>
                            <div class="form-row-forced-2-col">
                                <div class="form-group">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="event_date" class="form-control"
                                        value="<?= $d['event_date'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Waktu</label>
                                    <input type="time" name="event_time" class="form-control"
                                        value="<?= $d['event_time'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Lokasi</label>
                                <input type="text" name="event_location" class="form-control"
                                    value="<?= $d['event_location'] ?? '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Uraian Kejadian</label>
                                <textarea name="event_description" class="form-control"
                                    rows="5"><?= $d['event_description'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <!-- D. Analysis -->
                        <div class="form-section">
                            <h4>D. ANALISIS & INTERPRETASI</h4>
                            <div class="form-group">
                                <label class="form-label">Faktor Penyebab</label>
                                <textarea name="analysis_cause" class="form-control"
                                    rows="3"><?= $d['analysis_cause'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dampak Bagi Individu</label>
                                <textarea name="impact_individual" class="form-control"
                                    rows="2"><?= $d['impact_individual'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dampak Bagi Tim/Kelas</label>
                                <textarea name="impact_team" class="form-control"
                                    rows="2"><?= $d['impact_team'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Dampak Bagi Sekolah</label>
                                <textarea name="impact_school" class="form-control"
                                    rows="2"><?= $d['impact_school'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <!-- E. Actions -->
                        <div class="form-section">
                            <h4>E. TINDAKAN YANG SUDAH DILAKUKAN</h4>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="addActionRow()">+ Tambah
                                Tindakan</button>
                            <div class="actions-table-wrapper">
                                <table id="actionsTable">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Tindakan/Kegiatan</th>
                                            <th>Pihak yang Terlibat</th>
                                            <th>Hasil Sementara</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="actionsTableBody"></tbody>
                                </table>
                            </div>
                            <input type="hidden" name="actions_data" id="actions_data"
                                value='<?= $d['actions_data'] ?? '[]' ?>'>
                        </div>

                        <!-- F. Recommendations -->
                        <div class="form-section">
                            <h4>F. REKOMENDASI/RTL</h4>
                            <div class="form-group">
                                <label class="form-label">Untuk Individu</label>
                                <textarea name="recommendation_individual" class="form-control"
                                    rows="2"><?= $d['recommendation_individual'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Batas Waktu</label>
                                <input type="date" name="recommendation_deadline" class="form-control"
                                    value="<?= $d['recommendation_deadline'] ?? '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Untuk Sistem</label>
                                <textarea name="recommendation_system" class="form-control"
                                    rows="2"><?= $d['recommendation_system'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">PIC</label>
                                <input type="text" name="recommendation_pic" class="form-control"
                                    value="<?= $d['recommendation_pic'] ?? '' ?>">
                            </div>
                        </div>

                        <!-- G. Monitoring -->
                        <div class="form-section">
                            <h4>G. MONITORING & EVALUASI</h4>
                            <div class="form-group">
                                <label class="form-label">Tanggal Monitoring</label>
                                <input type="date" name="monitoring_date" class="form-control"
                                    value="<?= $d['monitoring_date'] ?? '' ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Perkembangan</label>
                                <textarea name="monitoring_progress" class="form-control"
                                    rows="3"><?= $d['monitoring_progress'] ?? '' ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Evaluasi</label>
                                <div class="checkbox-list">
                                    <?php $es = $d['evaluation_status'] ?? ''; ?>
                                    <label><input type="radio" name="evaluation_status" value="Tuntas" <?= $es == 'Tuntas' ? 'checked' : '' ?>> Tuntas</label>
                                    <label><input type="radio" name="evaluation_status" value="Masih Berproses"
                                            <?= $es == 'Masih Berproses' ? 'checked' : '' ?>> Masih Berproses</label>
                                    <label><input type="radio" name="evaluation_status" value="Belum Tercapai"
                                            <?= $es == 'Belum Tercapai' ? 'checked' : '' ?>> Belum Tercapai</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Catatan Tambahan</label>
                                <textarea name="additional_notes" class="form-control"
                                    rows="3"><?= $d['additional_notes'] ?? '' ?></textarea>
                            </div>
                        </div>

                        <!-- H. Closure -->
                        <div class="form-section">
                            <h4>H. PENUTUP</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Diketahui Oleh (Nama)</label>
                                    <input type="text" name="acknowledged_by" id="acknowledged_by" class="form-control"
                                        value="<?= $d['acknowledged_by'] ?? '' ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="acknowledged_date" class="form-control"
                                        value="<?= $d['acknowledged_date'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Penyelesaian</label>
                                <input type="date" name="completion_date" class="form-control"
                                    value="<?= $d['completion_date'] ?? date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem; justify-content: flex-end;">
                            <a href="admin_catatan_khusus.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Data</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Modals for List View Operations -->

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

    <!-- Delete Main Record Modal -->
    <div id="deleteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #dc2626;"></i>
            <h3 style="margin: 1rem 0; color: #dc2626;">Konfirmasi Hapus</h3>
            <p>Hapus catatan tanggal: <strong id="deleteRecordDate"></strong>?</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteId">
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('deleteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Teacher Note Modal -->
    <div id="editTeacherNoteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div class="modal-content"
            style="background: white; margin: 5% auto; padding: 2rem; border-radius: 12px; max-width: 700px;">
            <h3>Edit Catatan Guru</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_teacher_note">
                <input type="hidden" name="tn_id" id="tn_id">
                <!-- Fields reuse existing logic -->
                <div class="form-group"><label>Siswa</label><select name="tn_student_id" id="tn_student_id"
                        class="form-control" required>
                        <option value="">Pilih Siswa</option>
                        <?php foreach ($allStudents as $student): ?>
                            <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?>
                                (<?= htmlspecialchars($student['class_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Tanggal</label><input type="date" name="tn_date" id="tn_date"
                        class="form-control" required></div>
                <div class="form-group"><label>Jenis</label><select name="tn_type" id="tn_type" class="form-control">
                        <option value="Prestasi">Prestasi</option>
                        <option value="Pelanggaran">Pelanggaran</option>
                    </select></div>
                <div class="form-group"><label>Kategori</label><input type="text" name="tn_category" id="tn_category"
                        class="form-control"></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="tn_description" id="tn_description"
                        class="form-control" rows="4"></textarea></div>
                <div style="margin-top:1rem; text-align:right;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('editTeacherNoteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Teacher Note Modal -->
    <div id="deleteTeacherNoteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; max-width: 400px; text-align: center; border-radius: 12px;">
            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #dc2626;"></i>
            <h3>Hapus Catatan Guru?</h3>
            <form method="POST">
                <input type="hidden" name="action" value="delete_teacher_note">
                <input type="hidden" name="tn_id" id="delete_tn_id">
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('deleteTeacherNoteModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF Modal -->
    <div id="pdfModal"
        style="display:none; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85);">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <h3 style="color:white; margin:0;">Lihat Dokumen</h3>
                <span onclick="closePdfModal()" style="cursor:pointer; color:white; font-size:24px;">&times;</span>
            </div>
            <div class="modal-body-pdf">
                <div id="loading" style="color:white;">Memuat...</div>
                <canvas id="pdf-canvas"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Sidebar Toggle - Use external script
    </script>

    <!-- Sidebar Toggle Script -->
    <script src="../assets/admin-sidebar.js"></script>

    <script>
        function openUploadModal(id) {
            document.getElementById('uploadId').value = id;
            document.getElementById('uploadModal').style.display = 'block';
        }

        function openDeleteModal(id, date) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteRecordDate').innerText = date;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function printRecord(id) {
            window.open('../print/get_anecdotal_print.php?id=' + id, '_blank');
        }

        // Teacher Notes Modals
        function openEditTeacherNoteModal(note) {
            document.getElementById('tn_id').value = note.id;
            document.getElementById('tn_student_id').value = note.student_id;
            document.getElementById('tn_date').value = note.date;
            document.getElementById('tn_type').value = note.type;
            document.getElementById('tn_category').value = note.category || '';
            document.getElementById('tn_description').value = note.description || '';
            document.getElementById('editTeacherNoteModal').style.display = 'block';
        }
        function openDeleteTeacherNoteModal(id) {
            document.getElementById('delete_tn_id').value = id;
            document.getElementById('deleteTeacherNoteModal').style.display = 'block';
        }

        // PDF Logic
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        let pdfDoc = null, pageNum = 1, pageRendering = false, scale = 1.5;
        const canvas = document.getElementById('pdf-canvas');
        const ctx = canvas ? canvas.getContext('2d') : null;

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                const renderContext = { canvasContext: ctx, viewport: viewport };
                page.render(renderContext).promise.then(() => { pageRendering = false; });
            });
        }

        async function viewDocument(filename) {
            document.getElementById('pdfModal').style.display = 'block';
            document.getElementById('loading').style.display = 'block';

            try {
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
                if (!response.ok) throw new Error('Network error');
                const json = await response.json();
                if (json.error) throw new Error(json.error);

                const binaryString = atob(json.content);
                const bytes = new Uint8Array(binaryString.length);
                for (let i = 0; i < binaryString.length; i++) bytes[i] = binaryString.charCodeAt(i);

                pdfDoc = await pdfjsLib.getDocument({ data: bytes }).promise;
                document.getElementById('loading').style.display = 'none';
                renderPage(1);
            } catch (e) {
                document.getElementById('loading').innerText = 'Error: ' + e.message;
            }
        }
        function closePdfModal() { document.getElementById('pdfModal').style.display = 'none'; }

        document.addEventListener('click', function (e) {
            if (e.target.closest('.btn-view-pdf')) {
                const path = e.target.closest('.btn-view-pdf').getAttribute('data-pdf-path');
                if (path) viewDocument(path);
            }
        });

        window.onclick = function (e) {
            if (e.target == document.getElementById('uploadModal')) document.getElementById('uploadModal').style.display = 'none';
            if (e.target == document.getElementById('deleteModal')) document.getElementById('deleteModal').style.display = 'none';
            if (e.target == document.getElementById('editTeacherNoteModal')) document.getElementById('editTeacherNoteModal').style.display = 'none';
            if (e.target == document.getElementById('deleteTeacherNoteModal')) document.getElementById('deleteTeacherNoteModal').style.display = 'none';
            if (e.target == document.getElementById('pdfModal')) closePdfModal();
        }

        // --- Form Scripts ---
        <?php if ($view_action === 'add' || $view_action === 'edit'): ?>

            function switchIdentityType(type) {
                document.querySelectorAll('.identity-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.identity-section').forEach(s => s.classList.remove('active'));

                // Find button with specific onclick text for simple matching
                const btns = document.querySelectorAll('.identity-tab');
                btns.forEach(b => {
                    if (b.getAttribute('onclick').includes(type)) b.classList.add('active');
                });

                document.getElementById('identity-' + type).classList.add('active');
            }

            function updateTeacherInfo() {
                const select = document.getElementById('teacher_id');
                const selected = select.options[select.selectedIndex];
                if (select.value) {
                    document.getElementById('teacher_nip').value = selected.getAttribute('data-nip') || '';
                    // Update Acknowledged By
                    document.getElementById('acknowledged_by').value = selected.text.trim();
                } else {
                    document.getElementById('teacher_nip').value = '';
                }
            }

            function updateStudentClass() {
                const select = document.getElementById('student_name');
                const selected = select.options[select.selectedIndex];
                if (select.value) {
                    document.getElementById('student_class').value = selected.getAttribute('data-class') || '';
                    document.getElementById('acknowledged_by').value = selected.value;
                }
            }

            function addActionRow(date = '', desc = '', parties = '', res = '') {
                const tbody = document.getElementById('actionsTableBody');
                const row = tbody.insertRow();
                row.innerHTML = `
                <td><input type="date" class="action-date" value="${date}"></td>
                <td><textarea class="action-description" rows="1">${desc}</textarea></td>
                <td><input type="text" class="action-parties" value="${parties}"></td>
                <td><textarea class="action-result" rows="1">${res}</textarea></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
            `;
            }

            function collectActionsData() {
                const data = [];
                document.querySelectorAll('#actionsTableBody tr').forEach(row => {
                    data.push({
                        date: row.querySelector('.action-date').value,
                        description: row.querySelector('.action-description').value,
                        parties: row.querySelector('.action-parties').value,
                        result: row.querySelector('.action-result').value
                    });
                });
                return JSON.stringify(data);
            }

            document.getElementById('recordForm').addEventListener('submit', function () {
                document.getElementById('actions_data').value = collectActionsData();
            });

            // Init Data
            document.addEventListener('DOMContentLoaded', function () {
                const d = <?= json_encode($d) ?>;
                if (d) {
                    // Actions Table
                    const actions = d.actions_data ? JSON.parse(d.actions_data) : [];
                    if (actions.length > 0) actions.forEach(a => addActionRow(a.date, a.description, a.parties, a.result));
                    else addActionRow();

                    // Identity Tab Switch
                    if (d.teacher_id) switchIdentityType('guru');
                    else if (d.student_name) switchIdentityType('siswa');
                    else if (d.staff_name) switchIdentityType('lainnya');
                    else switchIdentityType('siswa'); // Default

                    // Update info fields if NIP is empty (trigger onChange simulation)
                    if (d.teacher_id && !d.teacher_nip) updateTeacherInfo();
                } else {
                    addActionRow();
                }
            });

        <?php endif; ?>

    </script>
</body>

</html>