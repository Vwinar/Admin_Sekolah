<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Get current teacher's details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();

$selected_class = $_GET['class'] ?? $teacher['assigned_class'] ?? '';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$selected_subject = $_GET['subject'] ?? $teacher['subject'] ?? '';

// Handle Student Management (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_student') {
        $name = trim($_POST['student_name']);
        $class = $_POST['student_class'];

        if (!empty($name) && !empty($class)) {
            try {
                $stmt = $db->prepare("INSERT INTO students (name, class_name) VALUES (?, ?)");
                $stmt->execute([$name, $class]);
                $success_msg = "Siswa berhasil ditambahkan.";
                // Redirect to avoid resubmission and refresh list
                header("Location: absen_siswa.php?class=" . urlencode($selected_class) . "&date=" . urlencode($selected_date) . "&subject=" . urlencode($selected_subject) . "&msg=added");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal menambah siswa: " . $e->getMessage();
            }
        }
    } elseif ($action === 'edit_student') {
        $id = $_POST['student_id'];
        $name = trim($_POST['student_name']);
        $class = $_POST['student_class'];

        if (!empty($id) && !empty($name) && !empty($class)) {
            try {
                $stmt = $db->prepare("UPDATE students SET name = ?, class_name = ? WHERE id = ?");
                $stmt->execute([$name, $class, $id]);
                // Redirect
                header("Location: absen_siswa.php?class=" . urlencode($class) . "&date=" . urlencode($selected_date) . "&subject=" . urlencode($selected_subject) . "&msg=edited");
                exit;
            } catch (PDOException $e) {
                $error_msg = "Gagal mengedit siswa: " . $e->getMessage();
            }
        }
    }
}

// Handle Attendance Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $class_post = $_POST['class_name'];
    $date_post = $_POST['date'];
    $subject_post = $_POST['subject'];
    $attendance_data = $_POST['attendance'] ?? [];

    // Validate Date (Cannot be future date)
    if ($date_post > date('Y-m-d')) {
        $error_msg = "Gagal: Tidak dapat melakukan absensi untuk tanggal di masa depan (Besok/Lusa).";
    } else {
        try {
            $db->beginTransaction();

        // Optional: Clear existing attendance for this class/date/subject to allow updates
        // Or we can just insert/update. Let's do delete then insert for simplicity on re-submit
        // Getting student IDs in this class to limit deletion scope is safer but for now:
        // Delete records for these students on this date.

        foreach ($attendance_data as $student_id => $status) {
            // Check if record exists for this student on this date (ignoring subject to prevent duplicates)
            $check = $db->prepare("SELECT id FROM student_attendance WHERE student_id = ? AND date = ?");
            $check->execute([$student_id, $date_post]);
            $existing = $check->fetch();

            if ($existing) {
                $update = $db->prepare("UPDATE student_attendance SET status = ?, subject = ?, teacher_id = ? WHERE id = ?");
                $update->execute([$status, $subject_post, $user_id, $existing['id']]);
            } else {
                $insert = $db->prepare("INSERT INTO student_attendance (student_id, date, status, subject, teacher_id) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$student_id, $date_post, $status, $subject_post, $user_id]);
            }
        }

        $db->commit();
        $success_msg = "Data absensi berhasil disimpan.";
        
        // Redirect to the specific date to show the saved data correctly and prevent resubmission
        header("Location: absen_siswa.php?class=" . urlencode($class_post) . "&date=" . urlencode($date_post) . "&subject=" . urlencode($subject_post) . "&msg=saved");
        exit;

        } catch (Exception $e) {
            $db->rollBack();
            $error_msg = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// Check for redirect messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added')
        $success_msg = "Siswa berhasil ditambahkan.";
    if ($_GET['msg'] == 'edited')
        $success_msg = "Data siswa berhasil diperbarui.";
    if ($_GET['msg'] == 'saved')
        $success_msg = "Data absensi berhasil disimpan.";
}

// Fetch Master Data
$classes_data = $db->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();
$subjects_data = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();

// Get Students in Selected Class
$students = [];
if ($selected_class) {
    $stmt_students = $db->prepare("SELECT * FROM students WHERE class_name = ? ORDER BY name ASC");
    $stmt_students->execute([$selected_class]);
    $students = $stmt_students->fetchAll();
}

// Get Existing Attendance for View
$existing_attendance = [];
if ($selected_class && !empty($students)) {
    $student_ids = array_column($students, 'id');
    if (!empty($student_ids)) {
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        $sql = "SELECT * FROM student_attendance WHERE student_id IN ($placeholders) AND date = ?";
        $params = array_merge($student_ids, [$selected_date]);

        $stmt_att = $db->prepare($sql);
        $stmt_att->execute($params);
        while ($row = $stmt_att->fetch()) {
            $existing_attendance[$row['student_id']] = $row['status'];
        }
    }
}

// --- LOGIC FOR RECAP HISTORY (LAST 7 SESSIONS) ---
$history_dates = [];
$history_data = [];

if ($selected_class && $selected_subject && !empty($students)) {
    // 1. Get last 7 dates used for this class/subject
    $stmt_dates = $db->prepare("
        SELECT DISTINCT date 
        FROM student_attendance sa
        JOIN students s ON sa.student_id = s.id
        WHERE s.class_name = ?
        ORDER BY sa.date DESC 
        LIMIT 7
    ");
    $stmt_dates->execute([$selected_class]);
    $history_dates = $stmt_dates->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($history_dates)) {
        // Sort dates ascending for display (Oldest -> Newest)
        sort($history_dates);

        // 2. Fetch data for these dates
        $student_ids = array_column($students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));

        $date_placeholders = implode(',', array_fill(0, count($history_dates), '?'));

        $sql_hist = "
            SELECT student_id, date, status 
            FROM student_attendance 
            WHERE student_id IN ($placeholders) 
            AND date IN ($date_placeholders)
        ";

        // Params: [id1, id2...] + [date1, date2...]
        $params_hist = array_merge($student_ids, $history_dates);

        $stmt_hist = $db->prepare($sql_hist);
        $stmt_hist->execute($params_hist);

        while ($row = $stmt_hist->fetch()) {
            $history_data[$row['student_id']][$row['date']] = $row['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Siswa</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .filter-card {
            background: white;
            padding: 0.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .attendance-table th,
        .attendance-table td {
            vertical-align: middle;
        }

        .status-radio-group {
            display: flex;
            gap: 1rem;
        }

        .status-label {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            cursor: pointer;
            font-weight: 500;
        }

        .status-label input {
            accent-color: var(--primary-color);
        }

        /* Custom colors for statuses */
        .status-hadir {
            color: var(--success);
        }

        .status-sakit {
            color: var(--warning);
        }

        .status-izin {
            color: var(--info);
        }

        .status-alpha {
            color: var(--danger);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 999;
            display: none;
            /* Hidden by default, shown on mobile */
        }

        @media (max-width: 768px) {
            .fab {
                display: flex;
            }

            .btn-desktop-only {
                display: none;
            }
        }

        /* Mobile Responsive Header Fix */
        @media (max-width: 768px) {
            .header {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                position: relative;
                padding-right: 0;
                margin-bottom: 1.5rem;
                gap: 0 !important;
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
            }

            /* Reuse/override circular button styles for the Add button in header */
            .btn-add-header {
                width: 40px !important;
                height: 40px;
                padding: 0 !important;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background: var(--primary) !important;
                color: white !important;
                box-shadow: var(--shadow-sm);
                font-size: 1.25rem;
            }

            .btn-add-header span.btn-text {
                display: none;
            }

            .btn-add-header i {
                margin: 0 !important;
            }
        }
    </style>
</head>

<body class="dashboard-page">
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
                    <h1 style="margin: 0; font-size: 1.25rem; line-height: 1; white-space: nowrap;">Absensi Siswa</h1>
                </div>
                <div class="header-right">
                    <button type="button" class="chip-btn chip-btn-blue btn-add-header" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> <span class="btn-text">+ Tambah Siswa</span>
                    </button>
                </div>
            </header>

            <!-- Mobile FAB Removed - Moved to Header -->


            <?php if ($success_msg): ?>
                    <div class="alert alert-success"
                        style="margin-bottom: 1rem; background-color: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px;">
                        <?= $success_msg ?>
                    </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                    <div class="alert alert-danger"
                        style="margin-bottom: 1rem; background-color: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px;">
                        <?= $error_msg ?>
                    </div>
            <?php endif; ?>

            <form method="GET" action="" class="filter-card">
                <div class="filter-grid">
                    <div class="form-group">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="date" class="form-control"
                            value="<?= htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kelas</label>
                        <select name="class" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach ($classes_data as $cls): ?>
                                    <option value="<?= htmlspecialchars($cls['name']) ?>" <?= $selected_class == $cls['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cls['name']) ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="subject" class="form-control">
                            <option value="">-- Pilih Mapel --</option>
                            <?php foreach ($subjects_data as $sub): ?>
                                    <option value="<?= htmlspecialchars($sub['name']) ?>" <?= $selected_subject == $sub['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sub['name']) ?>
                                    </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="chip-btn chip-btn-blue" style="width: 100%;">Tampilkan</button>
                    </div>
                </div>
            </form>

            <?php if ($selected_class && !empty($students)): ?>
                    <form method="POST" action="">
                        <div class="card">
                            <div style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                                <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Input Absensi</h3>
                                <div class="form-group" style="max-width: 300px;">
                                    <label class="form-label">Tanggal Absensi</label>
                                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selected_date) ?>" max="<?= date('Y-m-d') ?>" required>
                                    <small style="color: var(--text-muted);">Ganti tanggal jika ingin menginput absen susulan/lupa absen.</small>
                                </div>
                            </div>
                            
                            <input type="hidden" name="class_name" value="<?= htmlspecialchars($selected_class) ?>">
                            <input type="hidden" name="subject" value="<?= htmlspecialchars($selected_subject) ?>">
                            <div class="table-container">
                                <table class="attendance-table" style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th
                                                style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); width: 50px;">
                                                No</th>
                                            <th
                                                style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">
                                                Nama Siswa</th>
                                            <th
                                                style="padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color);">
                                                Status Kehadiran</th>
                                            <th
                                                style="padding: 1rem; text-align: center; border-bottom: 1px solid var(--border-color); width: 80px;">
                                                Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $index => $student): ?>
                                                <?php
                                                // Default status is 'Hadir' if not set, or whatever is in DB
                                                $status = $existing_attendance[$student['id']] ?? 'Hadir';
                                                ?>
                                                <tr>
                                                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                                        <?= $index + 1 ?>
                                                    </td>
                                                    <td
                                                        style="padding: 1rem; border-bottom: 1px solid var(--border-color); font-weight: 500;">
                                                        <?= htmlspecialchars($student['name']) ?>
                                                    </td>
                                                    <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                                        <div class="status-radio-group">
                                                            <label class="status-label status-hadir">
                                                                <input type="radio" name="attendance[<?= $student['id'] ?>]"
                                                                    value="Hadir" <?= $status == 'Hadir' ? 'checked' : '' ?>>
                                                                Hadir
                                                            </label>
                                                            <label class="status-label status-sakit">
                                                                <input type="radio" name="attendance[<?= $student['id'] ?>]"
                                                                    value="Sakit" <?= $status == 'Sakit' ? 'checked' : '' ?>>
                                                                Sakit
                                                            </label>
                                                            <label class="status-label status-izin">
                                                                <input type="radio" name="attendance[<?= $student['id'] ?>]"
                                                                    value="Izin" <?= $status == 'Izin' ? 'checked' : '' ?>>
                                                                Izin
                                                            </label>
                                                            <label class="status-label status-alpha">
                                                                <input type="radio" name="attendance[<?= $student['id'] ?>]"
                                                                    value="Alpha" <?= $status == 'Alpha' ? 'checked' : '' ?>>
                                                                Alpha
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td
                                                        style="padding: 1rem; border-bottom: 1px solid var(--border-color); text-align: center;">
                                                        <button type="button"
                                                            onclick="openEditModal(<?= $student['id'] ?>, '<?= addslashes(htmlspecialchars($student['name'])) ?>', '<?= addslashes(htmlspecialchars($student['class_name'])) ?>')"
                                                            style="background:none; border:none; cursor:pointer; color: #f59e0b;">
                                                            ✏️
                                                        </button>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 1.5rem; text-align: right;">
                                <button type="submit" class="chip-btn chip-btn-blue">Simpan Absensi</button>
                            </div>
                        </div>
                    </form>
                    </form>

                    <!-- RECAP SECTION -->
                    <?php if (!empty($history_dates)): ?>
                            <div class="card" style="margin-top: 2rem;">
                                <h3 style="margin-bottom: 1rem; font-size: 1.1rem;">Rekap Absensi (<?= count($history_dates) ?> Pertemuan Terakhir)</h3>
                                <div class="table-container">
                                    <table class="attendance-table" style="width: 100%; border-collapse: collapse; min-width: 600px;">
                                        <thead>
                                            <tr>
                                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); width: 40px; background: #f8fafc;">No</th>
                                                <th style="padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--border-color); background: #f8fafc; position: sticky; left: 0; z-index: 2;">Nama Siswa</th>
                                                <?php foreach ($history_dates as $hdate): ?>
                                                        <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); font-size: 0.85rem; background: #f8fafc;">
                                                            <?= date('d/m', strtotime($hdate)) ?>
                                                        </th>
                                                <?php endforeach; ?>
                                                <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); background: #f8fafc;">Hadi</th>
                                                <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); background: #f8fafc;">Sakit</th>
                                                <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); background: #f8fafc;">Izin</th>
                                                <th style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); background: #f8fafc;">Alpha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($students as $index => $student): ?>
                                                    <?php
                                                    // Calculate stats for this row
                                                    $stats = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpha' => 0];
                                                    foreach ($history_dates as $d) {
                                                        $s = $history_data[$student['id']][$d] ?? '-';
                                                        if (isset($stats[$s]))
                                                            $stats[$s]++;
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color);"><?= $index + 1 ?></td>
                                                        <td style="padding: 0.75rem; border-bottom: 1px solid var(--border-color); font-weight: 500; position: sticky; left: 0; background: white; z-index: 1;">
                                                            <?= htmlspecialchars($student['name']) ?>
                                                        </td>
                                                        <?php foreach ($history_dates as $hdate): ?>
                                                                <?php
                                                                $status = $history_data[$student['id']][$hdate] ?? '-';
                                                                $color = '#cbd5e1'; // Gray for empty
                                                                if ($status === 'Hadir')
                                                                    $color = 'var(--success)';
                                                                if ($status === 'Sakit')
                                                                    $color = 'var(--warning)';
                                                                if ($status === 'Izin')
                                                                    $color = 'var(--info)';
                                                                if ($status === 'Alpha')
                                                                    $color = 'var(--danger)';

                                                                $symbol = '-';
                                                                if ($status === 'Hadir')
                                                                    $symbol = 'H';
                                                                if ($status === 'Sakit')
                                                                    $symbol = 'S';
                                                                if ($status === 'Izin')
                                                                    $symbol = 'I';
                                                                if ($status === 'Alpha')
                                                                    $symbol = 'A';
                                                                ?>
                                                                <td style="padding: 0.75rem; text-align: center; border-bottom: 1px solid var(--border-color); color: <?= $color ?>; font-weight: bold;">
                                                                    <?= $symbol ?>
                                                                </td>
                                                        <?php endforeach; ?>
                                                        <!-- Stats Columns -->
                                                        <td style="text-align: center; font-size: 0.85rem; color: var(--success); font-weight:600;"><?= $stats['Hadir'] ?></td>
                                                        <td style="text-align: center; font-size: 0.85rem; color: var(--warning); font-weight:600;"><?= $stats['Sakit'] ?></td>
                                                        <td style="text-align: center; font-size: 0.85rem; color: var(--info); font-weight:600;"><?= $stats['Izin'] ?></td>
                                                        <td style="text-align: center; font-size: 0.85rem; color: var(--danger); font-weight:600;"><?= $stats['Alpha'] ?></td>
                                                    </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                    <?php endif; ?>

            <?php elseif ($selected_class): ?>
                    <div class="card" style="text-align: center; padding: 3rem;">
                        <p style="color: var(--text-muted);">Belum ada data siswa untuk kelas ini.</p>
                        <button type="button" class="chip-btn chip-btn-blue" style="margin-top: 1rem; width: auto;"
                            onclick="openAddModal()">+ Tambah Siswa</button>
                    </div>
            <?php else: ?>
                    <div class="card" style="text-align: center; padding: 3rem;">
                        <p style="color: var(--text-muted);">Silakan pilih kelas terlebih dahulu.</p>
                    </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- ADD/EDIT STUDENT MODAL -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Tambah Siswa</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="add_student">
                <input type="hidden" name="student_id" id="studentId" value="">

                <div class="form-group">
                    <label class="form-label">Nama Siswa</label>
                    <input type="text" name="student_name" id="studentName" class="form-control" required
                        placeholder="Nama Lengkap Siswa">
                </div>

                <div class="form-group">
                    <label class="form-label">Kelas</label>
                    <select name="student_class" id="studentClass" class="form-control" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($classes_data as $cls): ?>
                                <option value="<?= htmlspecialchars($cls['name']) ?>">
                                    <?= htmlspecialchars($cls['name']) ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="chip-btn" style="width: auto; background-color: #f3f4f6; color: #4b5563; border: 1px solid #e5e7eb;"
                        onclick="closeModal()">Batal</button>
                    <button type="submit" class="chip-btn chip-btn-blue" style="width: auto;"
                        id="modalSubmitBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('studentModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const studentId = document.getElementById('studentId');
        const studentName = document.getElementById('studentName');
        const studentClass = document.getElementById('studentClass');
        const modalSubmitBtn = document.getElementById('modalSubmitBtn');

        function openAddModal() {
            modalTitle.textContent = "Tambah Siswa";
            formAction.value = "add_student";
            studentId.value = "";
            studentName.value = "";

            // Pre-select current filtered class if available
            const currentFilteredClass = "<?= addslashes($selected_class) ?>";
            if (currentFilteredClass) {
                studentClass.value = currentFilteredClass;
            } else {
                studentClass.value = "";
            }

            modalSubmitBtn.textContent = "Tambah Siswa";
            modal.classList.add('show');
        }

        function openEditModal(id, name, className) {
            modalTitle.textContent = "Edit Siswa";
            formAction.value = "edit_student";
            studentId.value = id;
            studentName.value = name;
            studentClass.value = className;
            modalSubmitBtn.textContent = "Simpan Perubahan";
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        // Close when clicking outside
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Sidebar logic
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');
        const sidebarState = localStorage.getItem('sidebarCollapsed');

        if (sidebarState === 'true') {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        }

        sidebarToggle.addEventListener('click', function () {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');
            localStorage.setItem('sidebarCollapsed', dashboardLayout.classList.contains('sidebar-collapsed'));
        });

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
</body>

</html>
