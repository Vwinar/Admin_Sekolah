<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$page_title = 'Notulen Rapat';
$msg = '';

// Ensure document_path column exists
try {
    $db->query("SELECT document_path FROM school_logs LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such column') !== false) {
        $db->exec("ALTER TABLE school_logs ADD COLUMN document_path TEXT");
    }
}

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Fix for manual input name handling
    if (isset($_POST['leader_name']) && $_POST['leader_name'] === 'Manual' && !empty($_POST['leader_name_manual'])) {
        $_POST['leader_name'] = $_POST['leader_name_manual'];
    }

    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;

        // Basic Fields
        $date = $_POST['date'];
        $subject = $_POST['subject']; // Also used as Jenis Rapat
        $meeting_time = $_POST['meeting_time'] ?? '';
        $location = $_POST['location'] ?? '';
        $leader_name = $_POST['leader_name'] ?? '';
        $leader_nip = $_POST['leader_nip'] ?? '';
        $notulis_name = $_POST['notulis_name'] ?? '';
        $notulis_nip = $_POST['notulis_nip'] ?? '';
        $moderator_name = $_POST['moderator_name'] ?? '';
        $book_number = $_POST['book_number'] ?? '';
        $agenda_header = $_POST['agenda_header'] ?? '';
        $details = $_POST['details'] ?? ''; // Proses Pembahasan

        // Structured JSON Fields
        // Attendees: array of {no, name, position, status}
        $attendees = [];
        if (isset($_POST['attendee_name'])) {
            foreach ($_POST['attendee_name'] as $k => $v) {
                if (trim($v)) {
                    $attendees[] = [
                        'name' => $v,
                        'position' => $_POST['attendee_position'][$k] ?? '',
                        'status' => $_POST['attendee_status'][$k] ?? 'Hadir'
                    ];
                }
            }
        }
        $attendees_json = json_encode($attendees);

        // Decisions: array of {decision, pic, target, note}
        $decisions = [];
        if (isset($_POST['decision_point'])) {
            foreach ($_POST['decision_point'] as $k => $v) {
                if (trim($v)) {
                    $decisions[] = [
                        'point' => $v,
                        'pic' => $_POST['decision_pic'][$k] ?? '',
                        'target' => $_POST['decision_target'][$k] ?? '',
                        'note' => $_POST['decision_note'][$k] ?? ''
                    ];
                }
            }
        }
        $decisions_json = json_encode($decisions);

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO school_logs 
                (type, date, subject, details, notes, book_number, meeting_time, location, leader_name, leader_nip, notulis_name, notulis_nip, moderator_name, attendees_json, decisions_json, agenda_header) 
                VALUES 
                ('notulen', ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$date, $subject, $details, $book_number, $meeting_time, $location, $leader_name, $leader_nip, $notulis_name, $notulis_nip, $moderator_name, $attendees_json, $decisions_json, $agenda_header]);
            $msg = "Notulen berhasil ditambahkan.";
            $action = 'list'; // Return to list view
        } elseif ($action === 'edit') {
            $stmt = $db->prepare("UPDATE school_logs SET 
                date = ?, subject = ?, details = ?, book_number = ?, meeting_time = ?, location = ?, 
                leader_name = ?, leader_nip = ?, notulis_name = ?, notulis_nip = ?, moderator_name = ?, attendees_json = ?, decisions_json = ?, agenda_header = ? 
                WHERE id = ?");
            $stmt->execute([$date, $subject, $details, $book_number, $meeting_time, $location, $leader_name, $leader_nip, $notulis_name, $notulis_nip, $moderator_name, $attendees_json, $decisions_json, $agenda_header, $id]);
            $msg = "Notulen berhasil diperbarui.";
            $action = 'list'; // Return to list view
        }
    } elseif ($action === 'upload_doc') {
        $id = $_POST['id'];

        if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
            $target_dir = "../uploads/notulen/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES["document"]["name"], PATHINFO_EXTENSION);
            $new_filename = "notulen_" . $id . "_" . date("YmdHis") . "." . $file_extension;
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
            } else {
                $msg = "Maaf, terjadi kesalahan saat mengupload file.";
            }
        } else {
            $msg = "Pilih file terlebih dahulu.";
        }

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
        $msg = "Data berhasil dihapus.";
    }
}

// Fetch Data
$stmt = $db->prepare("SELECT * FROM school_logs WHERE type = 'notulen' ORDER BY date DESC, id DESC");
$stmt->execute();
$rows = $stmt->fetchAll();

// View Data Logic
$view_action = $_GET['action'] ?? (($action ?? '') === 'list' || !empty($msg) ? 'list' : 'list');
// Override view action if just finished post and we have msg
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($msg)) {
    $view_action = 'list';
}

$formData = [];
if ($view_action === 'edit' && isset($_GET['id'])) {
    $stmtData = $db->prepare("SELECT * FROM school_logs WHERE id = ?");
    $stmtData->execute([$_GET['id']]);
    $formData = $stmtData->fetch(PDO::FETCH_ASSOC);
    if (!$formData)
        $view_action = 'list'; // Fallback if not found
}
if ($view_action === 'add') {
    $page_title = 'Buat Notulen Baru';
} elseif ($view_action === 'edit') {
    $page_title = 'Edit Notulen';
}

// Fetch Users for Dropdowns
$stmtUser = $db->query("SELECT id, full_name, role, nip, subject, assigned_class FROM users WHERE role IN ('admin', 'guru') ORDER BY full_name ASC");
$users = $stmtUser->fetchAll();

// Prepare User Data for JS
$usersJs = [];
foreach ($users as $u) {
    // Determine Position/Jabatan
    $pos = ucfirst($u['role']);
    if ($u['role'] === 'admin') {
        if (stripos($u['full_name'], 'Kepala') !== false)
            $pos = 'Kepala Sekolah';
        else
            $pos = 'Staff Tata Usaha';
    } elseif ($u['role'] === 'guru') {
        $pos = 'Guru';
        if ($u['subject'])
            $pos .= " Mapel " . $u['subject'];
        if ($u['assigned_class'])
            $pos .= " / Wali Kelas " . $u['assigned_class'];
    }

    $usersJs[] = [
        'id' => $u['id'],
        'name' => $u['full_name'],
        'nip' => $u['nip'] ?? '',
        'role' => $u['role'],
        'position' => $pos
    ];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Notulen Rapat</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>

<body>
    <!-- (Skipping dashboard layout part to focus on form) -->
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle"><span></span><span></span><span></span></button>
                    <div class="header-title">
                        <h1><?= $page_title ?></h1>
                        <p>Administrasi Sekolah</p>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($view_action !== 'list'): ?>
                        <a href="admin_administrasi_notulen.php" class="btn btn-secondary" title="Batal">
                            <i class="bi bi-x-lg"></i> <span>Batal</span>
                        </a>
                    <?php else: ?>
                        <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                            <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                        </a>
                        <a href="?action=add" class="btn btn-primary" title="Buat Notulen Baru">
                            <i class="bi bi-plus-lg"></i> <span>Tambah</span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($view_action === 'list'): ?>
                    <div class="card">
                        <div class="table-container">
                            <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Jenis Rapat</th>
                                    <th>Pemimpin</th>
                                    <th>Notulis</th>
                                    <th>Juml. Peserta</th>
                                    <th>Dokumen</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rows) === 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Belum ada notulen rapat.</td>
                                        </tr>
                                <?php else: ?>
                                        <?php foreach ($rows as $i => $row):
                                            $att = json_decode($row['attendees_json'] ?? '[]', true);
                                            $count = is_array($att) ? count($att) : 0;
                                            ?>
                                                <tr>
                                                    <td><?= $i + 1 ?></td>
                                                    <td><?= date('d M Y', strtotime($row['date'])) ?></td>
                                                    <td><?= htmlspecialchars($row['subject']) ?></td>
                                                    <td><?= htmlspecialchars($row['leader_name']) ?></td>
                                                    <td><?= htmlspecialchars($row['notulis_name']) ?></td>
                                                    <td><?= $count ?> Orang</td>
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
                                                            <a href="../print/get_notulen_print.php?id=<?= $row['id'] ?>" target="_blank"
                                                                class="btn btn-sm btn-info" title="Lihat/Cetak"><i
                                                                    class="bi bi-printer"></i></a>
                                                            <a href="?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary" title="Edit"><i
                                                                    class="bi bi-pencil"></i></a>
                                                            <button type="button" class="btn btn-sm btn-danger" title="Hapus"
                                                                onclick="confirmDelete(<?= $row['id'] ?>)"><i
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
            <?php endif; ?>

            <?php if ($view_action === 'add' || $view_action === 'edit'): ?>
                    <div class="card">
                        <!-- TABS HEADER -->
                        <div style="display: flex; gap: 1rem; border-bottom: 1px solid #ccc; margin-bottom: 1.5rem; overflow-x:auto;">
                            <button type="button" class="tab-btn active" onclick="switchTab('tab1')">1. Identitas Rapat</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab2')">2. Peserta</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab3')">3. Pembahasan</button>
                            <button type="button" class="tab-btn" onclick="switchTab('tab4')">4. Keputusan & Penutup</button>
                        </div>

                        <form method="POST" id="notulenForm">
                            <input type="hidden" name="action" value="<?= $view_action ?>">
                            <input type="hidden" name="id" value="<?= $view_action === 'edit' ? ($formData['id'] ?? '') : '' ?>">

                            <!-- TAB 1: IDENTITAS -->
                            <div id="tab1" class="tab-content" style="display: block;">
                                <div class="row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div class="form-group">
                                        <label>Jenis Rapat</label>
                                        <input type="text" name="subject" id="subject" class="form-control"
                                            placeholder="Contoh: Rapat Dinas" required
                                            value="<?= htmlspecialchars($formData['subject'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Nomor Buku</label>
                                        <input type="text" name="book_number" id="book_number" class="form-control"
                                            placeholder="..." value="<?= htmlspecialchars($formData['book_number'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Hari/Tanggal</label>
                                        <input type="date" name="date" id="date" class="form-control" required
                                            value="<?= htmlspecialchars($formData['date'] ?? date('Y-m-d')) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Waktu (Pukul)</label>
                                        <input type="text" name="meeting_time" id="meeting_time" class="form-control"
                                            placeholder="13.00 - 15.30 WIB"
                                            value="<?= htmlspecialchars($formData['meeting_time'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Tempat</label>
                                        <input type="text" name="location" id="location" class="form-control"
                                            placeholder="Ruang Guru/Aula"
                                            value="<?= htmlspecialchars($formData['location'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Pemimpin Rapat</label>
                                        <select name="leader_name" id="leader_name" class="form-control"
                                            onchange="autoFillNip('leader')">
                                            <option value="">-- Pilih Pemimpin --</option>
                                            <?php
                                            // Handle manual logic pre-check
                                            $leaderIsManual = false;
                                            if ($view_action === 'edit' && !empty($formData['leader_name'])) {
                                                $found = false;
                                                foreach ($users as $u) {
                                                    if ($u['full_name'] === $formData['leader_name'])
                                                        $found = true;
                                                }
                                                if (!$found)
                                                    $leaderIsManual = true;
                                            }
                                            ?>
                                            <?php foreach ($users as $u): ?>
                                                    <option value="<?= htmlspecialchars($u['full_name']) ?>" data-nip="<?= $u['nip'] ?>"
                                                        <?= ($view_action === 'edit' && isset($formData['leader_name']) && $formData['leader_name'] === $u['full_name']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($u['full_name']) ?>
                                                    </option>
                                            <?php endforeach; ?>
                                            <option value="Manual" <?= $leaderIsManual ? 'selected' : '' ?>>Input Manual...</option>
                                        </select>
                                        <!-- Fallback input for manual entry if needed, managed by JS -->
                                        <input type="text" id="leader_name_manual" name="leader_name_manual" class="form-control"
                                            style="<?= $leaderIsManual ? 'display:block;' : 'display:none;' ?> margin-top:0.25rem;"
                                            placeholder="Nama Pemimpin Manual"
                                            value="<?= $leaderIsManual ? htmlspecialchars($formData['leader_name'] ?? '') : '' ?>">

                                        <input type="text" name="leader_nip" id="leader_nip" class="form-control"
                                            placeholder="NIP Pemimpin" style="margin-top:0.25rem;"
                                            value="<?= htmlspecialchars($formData['leader_nip'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Notulis</label>
                                        <select name="notulis_name" id="notulis_name" class="form-control"
                                            onchange="autoFillNip('notulis')">
                                            <option value="">-- Pilih Notulis --</option>
                                            <?php foreach ($users as $u): ?>
                                                    <option value="<?= htmlspecialchars($u['full_name']) ?>" data-nip="<?= $u['nip'] ?>"
                                                        <?= ($view_action === 'edit' && isset($formData['notulis_name']) && $formData['notulis_name'] === $u['full_name']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($u['full_name']) ?>
                                                    </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" name="notulis_nip" id="notulis_nip" class="form-control"
                                            placeholder="NIP Notulis" style="margin-top:0.25rem;"
                                            value="<?= htmlspecialchars($formData['notulis_nip'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Moderator (Opsional)</label>
                                        <select name="moderator_name" id="moderator_name" class="form-control">
                                            <option value="">-- Pilih Moderator --</option>
                                            <?php foreach ($users as $u): ?>
                                                    <option value="<?= htmlspecialchars($u['full_name']) ?>"
                                                        <?= ($view_action === 'edit' && isset($formData['moderator_name']) && $formData['moderator_name'] === $u['full_name']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($u['full_name']) ?>
                                                    </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 1rem;">
                                    <label>Agenda Utama (Untuk Header)</label>
                                    <textarea name="agenda_header" id="agenda_header" class="form-control" rows="3"
                                        placeholder="Poin-poin agenda utama..."><?= htmlspecialchars($formData['agenda_header'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- TAB 2: PESERTA -->
                            <div id="tab2" class="tab-content" style="display: none;">
                                <label>Daftar Hadir Peserta Rapat</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="attendeesTable"
                                        style="width: 100%; border-collapse: collapse; margin-top: 0.5rem;">
                                        <thead>
                                            <tr style="background: #f8f9fa;">
                                                <th style="min-width: 250px;">Nama Lengkap</th>
                                                <th style="min-width: 200px;">Jabatan</th>
                                                <th style="min-width: 120px;">Status</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Rows added via JS -->
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addAttendee()"
                                    style="margin-top: 0.5rem;">+ Tambah Peserta</button>
                                <!-- Bulk Add Button -->
                                <button type="button" class="btn btn-sm btn-info" onclick="addAllTeachers()"
                                    style="margin-top: 0.5rem;">+ Tambah Semua Guru</button>
                            </div>

                            <!-- TAB 3: PEMBAHASAN -->
                            <div id="tab3" class="tab-content" style="display: none;">
                                <div class="form-group">
                                    <label>Proses / Isi Pembahasan (Naratif)</label>
                                    <p class="text-muted" style="font-size: 0.8rem;">Tuliskan pembukaan, inti paparan per agenda,
                                        diskusi, dan kesimpulan sementara.</p>
                                    <textarea name="details" id="details" class="form-control" rows="15"
                                        placeholder="Tulis notulen lengkap di sini..."><?= htmlspecialchars($formData['details'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- TAB 4: KEPUTUSAN -->
                            <div id="tab4" class="tab-content" style="display: none;">
                                <label>Kesimpulan, Keputusan, dan Tindak Lanjut</label>
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="decisionsTable"
                                        style="width: 100%; border-collapse: collapse; margin-top: 0.5rem;">
                                        <thead>
                                            <tr style="background: #f8f9fa;">
                                                <th style="min-width: 250px;">Poin Keputusan / Tugas</th>
                                                <th style="min-width: 150px;">PIC</th>
                                                <th style="min-width: 150px;">Target Waktu</th>
                                                <th style="min-width: 150px;">Ket.</th>
                                                <th style="width: 50px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Rows added via JS -->
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="addDecision()"
                                    style="margin-top: 0.5rem;">+ Tambah Poin</button>
                            </div>

                            <div style="display: flex; gap: 1rem; margin-top: 2rem; border-top: 1px solid #eee; padding-top: 1rem;">
                                <button type="submit" class="btn btn-primary">Simpan Notulen</button>
                            </div>
                        </form>
                    </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- TEMPLATES FOR JS -->
    <template id="attendeeRow">
        <tr>
            <td>
                <select name="attendee_name[]" class="form-control attendee-select" onchange="autoFillPosition(this)">
                    <option value="">-- Pilih Peserta --</option>
                    <?php foreach ($usersJs as $u): ?>
                            <option value="<?= htmlspecialchars($u['name']) ?>"
                                data-position="<?= htmlspecialchars($u['position']) ?>"><?= htmlspecialchars($u['name']) ?>
                            </option>
                    <?php endforeach; ?>
                    <option value="Manual">Input Manual...</option>
                </select>
                <input type="text" name="attendee_name_manual[]" class="form-control manual-input"
                    style="display:none; margin-top:5px;" placeholder="Nama Peserta">
            </td>
            <td><input type="text" name="attendee_position[]" class="form-control position-input" placeholder="Jabatan">
            </td>
            <td>
                <select name="attendee_status[]" class="form-control">
                    <option value="Hadir">Hadir</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Izin">Izin</option>
                    <option value="Alpha">Alpha</option>
                </select>
            </td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i
                        class="bi bi-x"></i></button></td>
        </tr>
    </template>

    <template id="decisionRow">
        <tr>
            <td><input type="text" name="decision_point[]" class="form-control" placeholder="Isi Keputusan"></td>
            <td><input type="text" name="decision_pic[]" class="form-control" placeholder="PIC"></td>
            <td><input type="text" name="decision_target[]" class="form-control" placeholder="Target"></td>
            <td><input type="text" name="decision_note[]" class="form-control" placeholder="-"></td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove()"><i
                        class="bi bi-x"></i></button></td>
        </tr>
    </template>

    <script>
        // DELETE MODAL LOGIC
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteModal').style.display = 'flex'; // Use flex to center
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close delete modal if clicked outside
        window.addEventListener('click', function (event) {
            const delModal = document.getElementById('deleteModal');
            if (event.target == delModal) {
                closeDeleteModal();
            }

        });

        // DATA USERS FOR JS
        const allUsers = <?= json_encode($usersJs) ?>;

        // TAB LOGIC
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.getElementById(tabId).style.display = 'block';
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.querySelector(`[onclick="switchTab('${tabId}')"]`).classList.add('active');
        }

        // AUTO FILL NIP
        function autoFillNip(role) {
            const select = document.getElementById(role + '_name');
            const nipInput = document.getElementById(role + '_nip');
            const selectedOption = select.options[select.selectedIndex];

            if (nipInput && selectedOption) {
                const nip = selectedOption.getAttribute('data-nip');
                if (nip) nipInput.value = nip;
            }
        }

        // AUTO FILL POSITION IN LIST
        function autoFillPosition(selectEl) {
            const tr = selectEl.closest('tr');
            const posInput = tr.querySelector('.position-input');
            const manualInput = tr.querySelector('.manual-input');

            if (selectEl.value === 'Manual') {
                selectEl.style.display = 'none';
                manualInput.style.display = 'block';
                manualInput.name = 'attendee_name[]'; // switch name to manual input
                selectEl.name = 'attendee_name_select[]'; // dummy name
                posInput.value = '';
            } else {
                const selectedOption = selectEl.options[selectEl.selectedIndex];
                const pos = selectedOption.getAttribute('data-position');
                if (pos) posInput.value = pos;
            }
        }

        // ADD ROW LOGIC
        function addAttendee(data = null) {
            const tmpl = document.getElementById('attendeeRow').content.cloneNode(true);
            const tbody = document.querySelector('#attendeesTable tbody');
            const select = tmpl.querySelector('select');

            if (data) {
                // Check if user exists in dropdown
                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === data.name) {
                        select.selectedIndex = i;
                        exists = true;
                        break;
                    }
                }

                if (!exists && data.name) {
                    // Switch to manual if not found
                    select.value = 'Manual';
                    // Trigger manual mode logic manually since we are not in DOM yet
                    // But easier to append first then manipulate
                } else {
                    select.value = data.name;
                }

                tmpl.querySelector('[name="attendee_position[]"]').value = data.position;
                tmpl.querySelector('[name="attendee_status[]"]').value = data.status;
            }
            tbody.appendChild(tmpl);

            // Handle Manual Mode post-append if needed
            if (data) {
                const lastTr = tbody.lastElementChild;
                const lastSelect = lastTr.querySelector('select');
                if (lastSelect.value === 'Manual') {
                    lastSelect.style.display = 'none';
                    const manual = lastTr.querySelector('.manual-input');
                    manual.style.display = 'block';
                    manual.value = data.name;
                    manual.name = 'attendee_name[]';
                    lastSelect.name = 'attendee_name_select[]';
                }
            }
        }

        function addAllTeachers() {
            allUsers.forEach(u => {
                if (u.role === 'guru') {
                    addAttendee({
                        name: u.name,
                        position: u.position,
                        status: 'Hadir'
                    });
                }
            });
        }

        // ... (rest of addDecision, openModal, etc.)

        function addDecision(data = null) {
            const tmpl = document.getElementById('decisionRow').content.cloneNode(true);
            const tbody = document.querySelector('#decisionsTable tbody');
            if (data) {
                tmpl.querySelector('[name="decision_point[]"]').value = data.point;
                tmpl.querySelector('[name="decision_pic[]"]').value = data.pic;
                tmpl.querySelector('[name="decision_target[]"]').value = data.target;
                tmpl.querySelector('[name="decision_note[]"]').value = data.note;
            }
            tbody.appendChild(tmpl);
        }

        // Initialize Form Data
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($view_action === 'edit' && !empty($formData)): ?>
                    const attData = <?= $formData['attendees_json'] ?? '[]' ?>;
                    if (attData && attData.length > 0) {
                        attData.forEach(d => addAttendee(d));
                    } else {
                        addAttendee();
                    }

                    const decData = <?= $formData['decisions_json'] ?? '[]' ?>;
                    if (decData && decData.length > 0) {
                        decData.forEach(d => addDecision(d));
                    } else {
                        addDecision();
                    }
            <?php elseif ($view_action === 'add'): ?>
                    addAttendee();
                    addDecision();
            <?php endif; ?>
        });

        // MODAL LOGIC FOR DELETE ONLY
        // const modal = document.getElementById('dataModal'); // Removed

        // REMOVED MODAL FUNCTIONS
        // function closeModal() { ... }


        // Sidebar logic

        // Sidebar logic initialization happens in external script
        
        // Form Logic
        const notulenForm = document.getElementById('notulenForm');
        if (notulenForm) {
            notulenForm.addEventListener('submit', function (e) {
                const lnSelect = document.getElementById('leader_name');
                const lnManual = document.getElementById('leader_name_manual');

                if (lnSelect.value === 'Manual') {
                    lnSelect.removeAttribute('name');
                    lnManual.setAttribute('name', 'leader_name');
                } else {
                    lnManual.removeAttribute('name');
                    lnSelect.setAttribute('name', 'leader_name');
                }
            });
        }

        const leaderNameSelect = document.getElementById('leader_name');
        if (leaderNameSelect) {
            leaderNameSelect.addEventListener('change', function () {
                const val = this.value;
                const man = document.getElementById('leader_name_manual');
                const nip = document.getElementById('leader_nip');

                if (val === 'Manual') {
                    man.style.display = 'block';
                    man.value = '';
                    nip.value = '';
                } else {
                    man.style.display = 'none';
                    const selected = this.options[this.selectedIndex];
                    if (selected) {
                        nip.value = selected.getAttribute('data-nip') || '';
                    }
                }
            });
        }
        // PDF.js Configuration
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

        async function viewDocument(filename) {
            const modal = document.getElementById('pdfModal');
            const container = document.getElementById('pdf-container');
            const loading = document.getElementById('loading');

            modal.style.display = 'block';
            loading.style.display = 'block';
            loading.innerText = 'Memuat Dokumen...';
            container.innerHTML = '';

            container.appendChild(canvas);

            const controls = document.createElement('div');
            controls.id = 'pdf-controls';
            controls.style.marginTop = '15px';
            controls.style.textAlign = 'center';
            controls.style.display = 'none';
            controls.innerHTML = `
                <button id="prevBtn" type="button" style="margin-right: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">← Sebelumnya</button>
                <span style="color:white;">Halaman <span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                <button id="nextBtn" type="button" style="margin-left: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">Berikutnya →</button>
            `;
            container.appendChild(controls);

            try {
                loading.innerText = 'Mengunduh data...';
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

                loading.style.display = 'none';
                controls.style.display = 'block';

                const countDisplay = document.getElementById('page_count');
                if (countDisplay) countDisplay.textContent = pdfDoc.numPages;

                pageNum = 1;
                renderPage(pageNum);

                document.getElementById('prevBtn').onclick = onPrevPage;
                document.getElementById('nextBtn').onclick = onNextPage;
            } catch (error) {
                console.error('Error loading PDF:', error);
                loading.style.color = '#ff6b6b';
                loading.innerText = 'Gagal memuat PDF: ' + error.message;
                loading.style.display = 'block';
            }
        }

        function closePdfModal() {
            const modal = document.getElementById('pdfModal');
            modal.style.display = 'none';
            const container = document.getElementById('pdf-container');
            container.innerHTML = '';
            pdfDoc = null;
        }

        // PDF View button event listener
        document.addEventListener('click', function (e) {
            if (e.target.closest('.btn-view-pdf')) {
                const btn = e.target.closest('.btn-view-pdf');
                const pdfPath = btn.getAttribute('data-pdf-path');
                if (pdfPath) {
                    viewDocument(pdfPath);
                }
            }
        });

        // Upload Modal Functions
        function openUploadModal(id) {
            document.getElementById('uploadId').value = id;
            document.getElementById('uploadModal').style.display = 'block';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
    </script>

    <!-- UPLOAD MODAL -->
    <div id="uploadModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div class="modal-content"
            style="background: white; margin: 15% auto; padding: 2rem; border-radius: 12px; max-width: 500px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h3 style="margin:0;">Upload Dokumen Notulen</h3>
                <button onclick="closeUploadModal()"
                    style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_doc">
                <input type="hidden" name="id" id="uploadId">

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem;">Pilih File
                        (PDF/Doc/Gambar)</label>
                    <input type="file" name="document" class="form-control" required
                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>

                <div style="text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF MODAL -->
    <div id="pdfModal"
        style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.85);">
        <div
            style="background-color: #383838; margin: 2% auto; padding: 0; width: 80%; height: 90%; border-radius: 8px; display: flex; flex-direction: column;">
            <div
                style="padding: 1rem; display: flex; justify-content: space-between; align-items: center; background: #2b2b2b; color: white; border-radius: 8px 8px 0 0;">
                <div>
                    <span>Preview PDF</span>
                    <span id="page_info" style="font-size: 0.8rem; margin-left: 1rem; color: #ccc;"></span>
                </div>
                <button type="button" onclick="closePdfModal()"
                    style="background:none; border:none; color:#ccc; font-size:2rem; cursor:pointer;">&times;</button>
            </div>
            <div
                style="flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; align-items: center; background: #383838;">
                <div id="loading" style="display:none; color: white;">Memuat Dokumen...</div>
                <div id="pdf-container"></div>
            </div>
        </div>
    </div>

    <style>
        /* PDF Viewer */
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

        /* Desktop Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            background: white;
            /* Ensure background is white */
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

        /* Tabs */
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 2px solid transparent;
        }

        .tab-btn.active {
            color: #0d6efd;
            border-bottom: 2px solid #0d6efd;
        }

        .tab-content {
            display: none;
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
                height: 60px;
                /* Fixed height to prevent collapse */
            }

            .main-content {
                padding-top: 5rem !important;
                /* Space for fixed header */
            }

            /* Header Left Section (Toggle + Title) */
            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.6rem !important;
                flex: 1 !important;
                min-width: 0 !important;
                /* Allow flex item to shrink properly */
            }

            /* Sidebar Toggle Button */
            .sidebar-toggle {
                width: 2.5rem !important;
                height: 2.5rem !important;
                padding: 0.5rem !important;
                flex-shrink: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-around !important;
                margin: 0 !important;
                border: 1px solid #eee;
                border-radius: 4px;
            }

            .sidebar-toggle span {
                height: 2px !important;
                background-color: #333;
                width: 100%;
                display: block;
            }

            /* Header Title Section */
            .header-title {
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                min-width: 0 !important;
                /* Critical for text overflow */
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
                display: block !important;
                /* Ensure subtitle is visible if desired, or set none */
            }

            /* Header Actions Section (Buttons) */
            .header-actions {
                display: flex !important;
                flex-direction: row !important;
                gap: 0.5rem !important;
                flex-shrink: 0 !important;
                /* Prevent buttons from shrinking */
                align-items: center !important;
            }

            /* Buttons in Header */
            .header-actions .btn {
                padding: 0 !important;
                width: 2.5rem !important;
                height: 2.5rem !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 0.5rem !important;
                font-size: 1.2rem !important;
                /* Larger icon */
            }

            .header-actions .btn i {
                margin: 0 !important;
                font-size: 1.2rem !important;
            }

            /* Tab 2: Attendees Table Optimization for Mobile */
            .table-responsive {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            #attendeesTable,
            #decisionsTable {
                width: auto !important; /* Allow growing */
                min-width: 700px; /* Force scroll */
            }

            .header-actions .btn span {
                display: none !important;
            }
        }
    </style>
    <script src="../assets/admin-sidebar.js"></script>
</body>

</html>