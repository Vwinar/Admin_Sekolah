<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';
$activeTab = $_GET['tab'] ?? 'tab1';
$report_id = $_GET['id'] ?? null;

// Fetch Master Data
$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();

// Pre-fill data logic
$reportData = [];
if ($report_id) {
    $stmt = $db->prepare("SELECT * FROM reports WHERE id = ? AND user_id = ?");
    $stmt->execute([$report_id, $_SESSION['user_id']]);
    $reportData = $stmt->fetch();
    if (!$reportData) {
        header('Location: laporan_baru.php');
        exit;
    }
} else {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userProfile = $stmt->fetch();
    $reportData['report_date'] = date('Y-m-d');
    $reportData['subject'] = $userProfile['subject'];
    $reportData['class_name'] = $userProfile['assigned_class'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['save_section'];
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir))
        mkdir($uploadDir, 0777, true);

    try {
        if (!$report_id) {
            // Get report_date from POST or use today's date as fallback
            $report_date = $_POST['report_date'] ?? date('Y-m-d');
            $sql = "INSERT INTO reports (user_id, report_date, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$_SESSION['user_id'], $report_date]);
            $report_id = $db->lastInsertId();

            // If creating from Tab 2 or Tab 3, also save basic info if provided
            if ($section !== 'tab1' && (isset($_POST['subject']) || isset($_POST['class_name']))) {
                $updateBasic = "UPDATE reports SET ";
                $basicParams = [];
                $updates = [];

                if (!empty($_POST['subject'])) {
                    $updates[] = "subject = ?";
                    $basicParams[] = $_POST['subject'];
                }
                if (!empty($_POST['class_name'])) {
                    $updates[] = "class_name = ?";
                    $basicParams[] = $_POST['class_name'];
                }

                if (!empty($updates)) {
                    $updateBasic .= implode(', ', $updates) . " WHERE id = ?";
                    $basicParams[] = $report_id;
                    $db->prepare($updateBasic)->execute($basicParams);
                }
            }
        }

        if ($section === 'tab1') {
            // TAB 1: RENCANA HARI INI
            $plan_media = isset($_POST['plan_media']) ? implode(',', $_POST['plan_media']) : '';
            $plan_assessment = isset($_POST['plan_assessment']) ? implode(',', $_POST['plan_assessment']) : '';

            // Logic Upload & Delete Modul Ajar
            $moduleUpdate = "";
            $uploadParams = [];

            // Check if user requested delete (Validation priority: Upload > Delete > Do Nothing)
            if (isset($_POST['delete_module_file']) && $_POST['delete_module_file'] == '1') {
                $moduleUpdate = ", module_file = ?";
                $uploadParams = [null]; // Set to NULL in DB
            }

            if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['module_file']['name'], PATHINFO_EXTENSION);
                if (strtolower($ext) !== 'pdf')
                    throw new Exception("Modul ajar harus PDF");

                $stmtU = $db->prepare("SELECT username FROM users WHERE id = ?");
                $stmtU->execute([$_SESSION['user_id']]);
                $uName = $stmtU->fetchColumn();

                $sUser = preg_replace('/[^a-zA-Z0-9]/', '', $uName);
                $sSubject = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['plan_subject'] ?? 'Subject');
                $sDate = str_replace('-', '', $_POST['plan_date'] ?? date('Ymd'));

                $filename = "{$sUser}_{$sSubject}_{$sDate}_" . date('His') . ".{$ext}";

                move_uploaded_file($_FILES['module_file']['tmp_name'], $uploadDir . $filename);
                $moduleUpdate = ", module_file = ?";
                $uploadParams = [$filename]; // Overwrites delete param if exists
            }

            $sql = "UPDATE reports SET 
                    plan_date=?, plan_subject=?, plan_class=?, plan_time=?, 
                    plan_topic=?, plan_learning_objective=?, plan_method_type=?, 
                    plan_media_used=?, plan_assessment_used=?, plan_notes=?
                    $moduleUpdate
                    WHERE id=?";

            $execParams = [
                $_POST['plan_date'],
                $_POST['plan_subject'],
                $_POST['plan_class'],
                $_POST['plan_time'],
                $_POST['plan_topic'],
                $_POST['plan_learning_objective'],
                $_POST['plan_method'],
                $plan_media,
                $plan_assessment,
                $_POST['plan_notes'] ?? ''
            ];

            // Merge params: Main params + Upload params (if any) + WHERE id param
            $finalParams = array_merge($execParams, $uploadParams, [$report_id]);

            $db->prepare($sql)->execute($finalParams);

            $success = "✅ Tab 1 (Rencana & Modul) berhasil disimpan!";
            $stay = $_POST['stay_on_tab'] ?? '1';
            $activeTab = ($stay === '1') ? 'tab1' : 'tab2';

        } elseif ($section === 'tab2') {
            // TAB 2: HARI INI (Laporan Harian)
            // (Module upload logic moved to Tab 1)
            $params = [
                $_POST['report_date'],
                $_POST['subject'],
                $_POST['class_name'],
                $_POST['material_taught'],
                $_POST['attendance'],
                $_POST['achievement'],
                $_POST['obstacles'],
                $_POST['solution']
            ];

            $currentStatus = $reportData['status'] ?? 'pending';
            $params[] = $report_id;

            $sql = "UPDATE reports SET 
                    report_date = ?, subject = ?, class_name = ?, 
                    material_taught = ?, attendance = ?, achievement = ?, obstacles = ?, solution = ?
                    WHERE id = ?";
            $db->prepare($sql)->execute($params);
            $success = "✅ Tab 2 (Hari Ini) berhasil disimpan!";
            $stay = $_POST['stay_on_tab'] ?? '1';
            $activeTab = ($stay === '1') ? 'tab2' : 'tab3';

        } elseif ($section === 'tab3') {
            // TAB 3: EVALUASI
            $evalUpdate = "";
            $params = [
                $_POST['report_date'],
                $_POST['subject'],
                $_POST['class_name'],
                $_POST['reflection'],
                $_POST['improvement_notes']
            ];

            if (isset($_FILES['evaluation_file']) && $_FILES['evaluation_file']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['evaluation_file']['name'], PATHINFO_EXTENSION);
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array(strtolower($ext), $allowed))
                    throw new Exception("Bukti evaluasi harus JPG/PNG");
                $filename = 'eval_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['evaluation_file']['tmp_name'], $uploadDir . $filename);
                $evalUpdate = ", evaluation_file = ?";
                $params[] = $filename;
            }

            $params[] = $report_id;

            $sql = "UPDATE reports SET report_date=?, subject=?, class_name=?, reflection=?, improvement_notes=? $evalUpdate WHERE id=?";
            $db->prepare($sql)->execute($params);
            $success = "✅ Tab 3 (Evaluasi) berhasil disimpan!";
            $stay = $_POST['stay_on_tab'] ?? '1';
            $activeTab = ($stay === '1') ? 'tab3' : 'tab4';

        } elseif ($section === 'tab4') {
            // TAB 4: RENCANA BESOK
            $sql = "UPDATE reports SET report_date=?, subject=?, class_name=?, plan_material=?, plan_media=?, plan_method=?, plan_goal=? WHERE id=?";
            $db->prepare($sql)->execute([
                $_POST['report_date'],
                $_POST['subject'],
                $_POST['class_name'],
                $_POST['plan_material'],
                $_POST['plan_media'],
                $_POST['plan_method'],
                $_POST['plan_goal'],
                $report_id
            ]);
            $success = "✅ Tab 4 (Rencana Besok) berhasil disimpan!";
            $stay = $_POST['stay_on_tab'] ?? '1';
            $activeTab = ($stay === '1') ? 'tab4' : 'tab4';
        }

        header("Location: laporan_baru.php?id=$report_id&tab=$activeTab&msg=" . urlencode($success));
        exit;

    } catch (Exception $e) {
        $error = "Gagal: " . $e->getMessage();
    }
}
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Laporan</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        .modal {
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

        .modal-content {
            background-color: #383838;
            margin: 2% auto;
            padding: 0;
            width: 80%;
            height: 90%;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2b2b2b;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .close-btn {
            color: #ccc;
            font-size: 28px;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #fff;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #383838;
        }

        canvas.pdf-canvas {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            max-width: 100%;
        }

        #loading {
            color: white;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                height: 95%;
                margin: 2.5% auto;
            }

            /* Responsive Header */
            .header {
                position: relative;
                padding-right: 0;
            }

            .header-right {
                position: absolute;
                top: 0;
                right: 0;
                margin-top: 0.25rem;
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

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/user_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div>
                        <h1>Form Laporan Harian</h1>
                        <p style="color: var(--text-muted)">
                            <?= $report_id ? "Mengedit Laporan #$report_id" : "Buat Laporan Baru" ?>
                        </p>
                    </div>
                </div>
                <div class="header-right">
                    <a href="dashboard_guru.php" class="chip-btn chip-btn-purple back-btn">
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

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: var(--radius); margin-bottom: 2rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php
            // Determine which tab to show based on URL parameter
            $showOnlyOne = isset($_GET['tab']) && $_GET['tab'] !== ''; // If specific tab requested
            ?>

            <div class="card">
                <?php if (!$showOnlyOne): ?>
                    <!-- Show all tabs if no specific tab requested -->
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
                <?php else: ?>
                    <div class="tabs">
                        <?php if ($activeTab === 'tab1'): ?>
                            <button type="button" class="tab-btn active">📋 Rencana Hari Ini</button>
                        <?php elseif ($activeTab === 'tab2'): ?>
                            <button type="button" class="tab-btn active">📅 Hari Ini</button>
                        <?php elseif ($activeTab === 'tab3'): ?>
                            <button type="button" class="tab-btn active">⭐ Evaluasi</button>
                        <?php elseif ($activeTab === 'tab4'): ?>
                            <button type="button" class="tab-btn active">📝 Rencana Besok</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$showOnlyOne || $activeTab === 'tab1'): ?>
                    <?php include '../guru/forms/tab1_rencana_hari_ini.php'; ?>
                <?php endif; ?>

                <?php if (!$showOnlyOne || $activeTab === 'tab2'): ?>
                    <!-- FORM TAB 2: HARI INI (LAPORAN HARIAN) -->
                    <form method="POST" enctype="multipart/form-data" id="tab2"
                        class="tab-content <?= $activeTab == 'tab2' ? 'active' : '' ?>">
                        <input type="hidden" name="save_section" value="tab2">

                        <div
                            style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #92400e; font-size: 1rem;">📋 Informasi Dasar Laporan</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="report_date" class="form-control"
                                        value="<?= htmlspecialchars($reportData['report_date'] ?? date('Y-m-d')) ?>"
                                        required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="subject" class="form-control" required>
                                        <option value="">- Pilih Mapel -</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($reportData['subject'] ?? '') === $s['name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                                <label class="form-label">Kelas</label>
                                <select name="class_name" class="form-control" required>
                                    <option value="">- Pilih Kelas -</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($reportData['class_name'] ?? '') === $c['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h3 class="mb-2 mt-2">Kegiatan Pembelajaran</h3>
                        <div class="form-group">
                            <label class="form-label">Materi yang diajarkan</label>
                            <textarea name="material_taught" class="form-control"
                                rows="3"><?= htmlspecialchars($reportData['material_taught'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kehadiran Siswa</label>
                            <input type="text" name="attendance" class="form-control" placeholder="Contoh: 28/30"
                                value="<?= htmlspecialchars($reportData['attendance'] ?? '') ?>">
                        </div>
                        <div class="form-group"><label class="form-label">Pencapaian</label><textarea name="achievement"
                                class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['achievement'] ?? '') ?></textarea></div>
                        <div class="form-group"><label class="form-label">Kendala</label><textarea name="obstacles"
                                class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['obstacles'] ?? '') ?></textarea></div>
                        <div class="form-group"><label class="form-label">Solusi</label><textarea name="solution"
                                class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['solution'] ?? '') ?></textarea></div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                            <button type="submit" name="stay_on_tab" value="1" class="btn chip-btn chip-btn-blue" style="width: auto;">
                                <span class="btn-icon">💾</span> <span class="btn-text">Simpan Hari Ini</span>
                            </button>
                            <button type="submit" class="btn chip-btn chip-btn-orange"
                                style="width: auto;"
                                onclick="this.form.stay_on_tab.value='0'">
                                <span class="btn-icon">➡️</span> <span class="btn-text">Simpan & Lanjut Evaluasi</span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (!$showOnlyOne || $activeTab === 'tab3'): ?>
                    <!-- FORM TAB 3: EVALUASI -->
                    <form method="POST" enctype="multipart/form-data" id="tab3"
                        class="tab-content <?= $activeTab == 'tab3' ? 'active' : '' ?>">
                        <input type="hidden" name="save_section" value="tab3">

                        <h3 class="mb-2">Evaluasi Diri</h3>

                        <!-- Editable Info Block for Tab 3 -->
                        <div
                            style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #92400e; font-size: 1rem;">📋 Informasi Dasar Laporan</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="report_date" class="form-control"
                                        value="<?= htmlspecialchars($reportData['report_date'] ?? date('Y-m-d')) ?>"
                                        required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="subject" class="form-control" required>
                                        <option value="">- Pilih Mapel -</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($reportData['subject'] ?? '') === $s['name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                                <label class="form-label">Kelas</label>
                                <select name="class_name" class="form-control" required>
                                    <option value="">- Pilih Kelas -</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($reportData['class_name'] ?? '') === $c['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group"
                            style="background: #fdf2f8; padding: 1rem; border-radius: 0.5rem; border: 1px dashed #fbcfe8;">
                            <label class="form-label">Upload Bukti Evaluasi / Foto</label>
                            <?php if (!empty($reportData['evaluation_file'])): ?>
                                <div style="margin-bottom: 0.5rem;"><img src="../uploads/<?= $reportData['evaluation_file'] ?>"
                                        height="50"></div>
                            <?php endif; ?>
                            <input type="file" name="evaluation_file" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Refleksi</label>
                            <textarea name="reflection" class="form-control"
                                rows="3"><?= htmlspecialchars($reportData['reflection'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Catatan Perbaikan</label>
                            <textarea name="improvement_notes" class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['improvement_notes'] ?? '') ?></textarea>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                            <button type="submit" name="stay_on_tab" value="1" class="btn chip-btn chip-btn-blue" style="width: auto;">
                                <span class="btn-icon">💾</span> <span class="btn-text">Simpan Evaluasi</span>
                            </button>
                            <button type="submit" class="btn chip-btn chip-btn-orange"
                                style="width: auto;"
                                onclick="this.form.stay_on_tab.value='0'">
                                <span class="btn-icon">➡️</span> <span class="btn-text">Simpan & Lanjut ke Rencana
                                    Besok</span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (!$showOnlyOne || $activeTab === 'tab4'): ?>
                    <!-- FORM TAB 4: RENCANA BESOK -->
                    <form method="POST" id="tab4" class="tab-content <?= $activeTab == 'tab4' ? 'active' : '' ?>">
                        <input type="hidden" name="save_section" value="tab4">
                        <h3 class="mb-2">Rencana Besok</h3>

                        <!-- Editable Info Block for Tab 4 -->
                        <div
                            style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.375rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #92400e; font-size: 1rem;">📋 Informasi Dasar Laporan</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" name="report_date" class="form-control"
                                        value="<?= htmlspecialchars($reportData['report_date'] ?? date('Y-m-d')) ?>"
                                        required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="subject" class="form-control" required>
                                        <option value="">- Pilih Mapel -</option>
                                        <?php foreach ($subjects as $s): ?>
                                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($reportData['subject'] ?? '') === $s['name'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                                <label class="form-label">Kelas</label>
                                <select name="class_name" class="form-control" required>
                                    <option value="">- Pilih Kelas -</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($reportData['class_name'] ?? '') === $c['name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Materi Selanjutnya</label>
                            <textarea name="plan_material" class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['plan_material'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Media & Alat</label>
                            <input type="text" name="plan_media" class="form-control"
                                value="<?= htmlspecialchars($reportData['plan_media'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Metode</label>
                            <input type="text" name="plan_method" class="form-control"
                                value="<?= htmlspecialchars($reportData['plan_method'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tujuan Pembelajaran</label>
                            <textarea name="plan_goal" class="form-control"
                                rows="2"><?= htmlspecialchars($reportData['plan_goal'] ?? '') ?></textarea>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
                            <button type="submit" name="stay_on_tab" value="1" class="btn chip-btn chip-btn-blue" style="width: auto;">
                                <span class="btn-icon">💾</span>
                                <span class="btn-text">Simpan & Selesai</span>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(el => { el.style.display = 'none'; el.classList.remove('active'); });
            const btns = document.querySelectorAll('.tab-btn');
            btns.forEach(b => b.classList.remove('active'));
            const target = document.getElementById(tabName);
            if (target) { target.style.display = 'block'; target.classList.add('active'); }

            if (tabName === 'tab1') btns[0].classList.add('active');
            if (tabName === 'tab2') btns[1].classList.add('active');
            if (tabName === 'tab3') btns[2].classList.add('active');
        }

        document.addEventListener("DOMContentLoaded", () => {
            const activeForm = document.querySelector('form.tab-content.active');
            if (activeForm) activeForm.style.display = 'block';
        });

        // PDF PREVIEW LOGIC USING PDF.JS (Client Side)
        async function previewPdfUpload(input) {
            const container = document.getElementById('pdf_preview_container');
            const loading = document.getElementById('pdf_loading_text');
            const canvas = document.getElementById('pdf_preview_canvas');
            const context = canvas.getContext('2d');

            if (input.files && input.files[0]) {
                const file = input.files[0];
                if (file.type === 'application/pdf') {
                    // Show container, hide canvas first
                    container.style.display = 'block';
                    loading.style.display = 'block';
                    canvas.style.display = 'none';

                    try {
                        const fileURL = URL.createObjectURL(file);
                        const loadingTask = pdfjsLib.getDocument(fileURL);
                        const pdf = await loadingTask.promise;

                        // Render first page only
                        const page = await pdf.getPage(1);
                        const scale = 1.0;
                        const viewport = page.getViewport({ scale: scale });

                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;

                        // Show canvas
                        loading.style.display = 'none';
                        canvas.style.display = 'block';

                        // Clean up blob URL to free memory
                        // URL.revokeObjectURL(fileURL); // Optional: keep it if needed
                    } catch (err) {
                        console.error(err);
                        loading.innerText = 'Gagal merender preview: ' + err.message;
                    }
                } else {
                    alert('Mohon upload file PDF.');
                    input.value = '';
                    container.style.display = 'none';
                }
            } else {
                container.style.display = 'none';
            }
        }
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
    <!-- PDF MODAL -->
    <div id="pdfModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span>Preview PDF</span>
                    <span id="page_info" style="font-size: 0.8rem; margin-left: 1rem; color: #ccc;"></span>
                </div>
                <span class="close-btn" onclick="closePdfModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="loading">Memuat Dokumen...</div>
                <div id="pdf-container"></div>
            </div>
        </div>
    </div>

    <script>
        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        // Check if canvas exists, else create it
        let canvas = document.createElement('canvas');
        let ctx = canvas.getContext('2d');
        // Add class for styling
        canvas.classList.add('pdf-canvas');

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

        async function previewExistingPdf(filename) {
            const modal = document.getElementById('pdfModal');
            const container = document.getElementById('pdf-container');
            const loading = document.getElementById('loading');

            modal.style.display = "block";
            loading.style.display = 'block';
            loading.innerText = 'Memuat Dokumen...';
            // Reset container
            container.innerHTML = '';
            container.appendChild(canvas);

            // Controls
            const controls = document.createElement('div');
            controls.id = 'pdf-controls';
            controls.style.marginTop = '15px';
            controls.style.textAlign = 'center';
            controls.style.display = 'none';
            controls.innerHTML = `
                <button id="prevBtn" type="button" style="margin-right: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">← Sebelumnya</button>
                <span>Halaman <span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                <button id="nextBtn" type="button" style="margin-left: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">Selanjutnya →</button>
            `;
            container.appendChild(controls);

            try {
                loading.innerText = 'Mengunduh data...';
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
                const responseText = await response.text();
                let json;

                try {
                    json = JSON.parse(responseText);
                } catch (e) {
                    console.error("Server Response Preview:", responseText);
                    throw new Error("Gagal memproses respon server. Cek console untuk detail.");
                }

                if (!response.ok) throw new Error(json.error || 'Gagal menghubungi server');
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
            document.getElementById('pdfModal').style.display = "none";
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('pdfModal')) closePdfModal();
        }
    </script>
</body>

</html>
