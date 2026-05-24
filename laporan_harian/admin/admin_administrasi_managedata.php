<?php
session_start();
require_once '../config/db_connect.php';

// Fetch Teachers for Dropdowns
$teachers = [];
$class_teacher_map = [];
if (isset($_SESSION['user_id'])) {
    // Check if assigned_class column exists first to be safe, though db_connect creates it.
    // We assume it exists based on db_connect.php
    $stmt_teachers = $db->query("SELECT id, full_name, nip, assigned_class FROM users WHERE role IN ('admin', 'guru') ORDER BY full_name ASC");
    while ($row_t = $stmt_teachers->fetch()) {
        $teachers[$row_t['id']] = $row_t['full_name'];
        if (!empty($row_t['assigned_class'])) {
            $class_teacher_map[$row_t['assigned_class']] = $row_t['id'];
        }
    }
}
// Fetch School Rooms (for Pemeliharaan)
$school_rooms = [];
if (isset($_SESSION['user_id'])) {
    // Check if table exists first to avoid errors if not created yet
    $check_table = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='school_rooms'");
    if ($check_table->fetch()) {
        $stmt_rooms = $db->query("SELECT id, name FROM school_rooms ORDER BY name ASC");
        while ($row_r = $stmt_rooms->fetch()) {
            $school_rooms[$row_r['id']] = $row_r['name'];
        }
    }
}

// Fetch Classes (for Admin Guru)
$school_classes = [];
if (isset($_SESSION['user_id'])) {
    $check_table_c = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='classes'");
    if ($check_table_c->fetch()) {
        $stmt_c = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
        while ($row_c = $stmt_c->fetch()) {
            $school_classes[] = $row_c['name'];
        }
    }
}


$type = $_GET['type'] ?? '';
// Fetch Students for Mutasi Dropdown
$students_list = [];
if ($type === 'mutasi' && isset($_SESSION['user_id'])) {
    // Check if students table exists
    $check_table_s = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='students'");
    if ($check_table_s->fetch()) {
        $stmt_s = $db->query("SELECT id, name, class_name FROM students ORDER BY class_name ASC, name ASC");
        while ($row_s = $stmt_s->fetch()) {
            $students_list[$row_s['id']] = $row_s['name'] . ' (' . $row_s['class_name'] . ')';
        }
    }
}


// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$type = $_GET['type'] ?? '';

// --- CUSTOM REDIRECTS FOR COMPLEX FORMS ---
if ($type === 'supervisi_admin') {
    header('Location: admin_supervisi_admin.php');
    exit;
}
if ($type === 'pembinaan_guru') {
    header('Location: admin_pembinaan_guru.php');
    exit;
}
if ($type === 'catatan_khusus') {
    header('Location: admin_catatan_khusus.php');
    exit;
}
if ($type === 'statistik') {
    header('Location: admin_statistik.php');
    exit;
}
if ($type === 'notulen') {
    header('Location: admin_administrasi_notulen.php');
    exit;
}
if ($type === 'diary_ks') {
    header('Location: admin_diary_ks.php');
    exit;
}

if ($type === 'master_plan') {
    header('Location: admin_master_plan.php');
    exit;
}
$action = $_POST['action'] ?? '';
$msg = '';
$table = '';
$page_title = 'Kelola Data';
$fields = [];

// --- CONFIGURATION MAPPING ---
$config = [
    // DOCUMENTS
    'rkas' => ['table' => 'school_documents', 'title' => 'RKAS / RKT / RAPBS', 'fields' => ['title', 'file_path', 'description']],
    'kalender' => ['table' => 'school_documents', 'title' => 'Kalender Pendidikan', 'fields' => ['title', 'file_path', 'description']],
    'program_ks' => ['table' => 'school_documents', 'title' => 'Program Kerja KS', 'fields' => ['title', 'file_path', 'description']],
    'jadwal_pelajaran' => ['table' => 'school_documents', 'title' => 'Jadwal Pelajaran', 'fields' => ['title', 'file_path', 'description']],
    'laporan' => ['table' => 'school_documents', 'title' => 'Laporan Bulanan/Tahunan', 'fields' => ['title', 'file_path', 'description']],
    'akreditasi' => ['table' => 'school_documents', 'title' => 'Dokumen Akreditasi', 'fields' => ['title', 'file_path', 'description']],
    'eds' => ['table' => 'school_documents', 'title' => 'Evaluasi Diri Sekolah', 'fields' => ['title', 'file_path', 'description']],
    'sk' => ['table' => 'school_documents', 'title' => 'SK Pembagian Tugas', 'fields' => ['title', 'related_user_id', 'category', 'file_path']],

    'denah' => ['table' => 'school_documents', 'title' => 'Denah Sekolah', 'fields' => ['title', 'file_path', 'description']],
    'peraturan' => ['table' => 'school_documents', 'title' => 'Peraturan Perundangan', 'fields' => ['title', 'file_path', 'description']],
    'kelulusan' => ['table' => 'school_documents', 'title' => 'Buku Kenaikan & Kelulusan', 'fields' => ['title', 'file_path', 'description']],
    'mutasi' => ['table' => 'student_mutation', 'title' => 'Buku Mutasi Siswa', 'fields' => ['date', 'student_id', 'student_name', 'type', 'from_school', 'to_school', 'reason']],
    'statistik' => ['table' => 'school_documents', 'title' => 'Statistik & Grafik Siswa', 'fields' => ['title', 'file_path', 'description']],
    'lpj' => ['table' => 'school_documents', 'title' => 'LPJ Keuangan', 'fields' => ['title', 'category', 'related_user_id', 'file_path', 'description']],

    // STANDAR ISI
    'ktsp' => ['table' => 'school_documents', 'title' => 'Dokumen KTSP', 'fields' => ['title', 'file_path', 'description']],
    'kurikulum' => ['table' => 'school_documents', 'title' => 'Dokumen Kurikulum', 'fields' => ['title', 'file_path', 'description']],
    'sk_tim_kurikulum' => ['table' => 'school_documents', 'title' => 'SK Tim Pengembang', 'fields' => ['title', 'file_path', 'description']],
    'kkm' => ['table' => 'school_documents', 'title' => 'Dokumen Penetapan KKM', 'fields' => ['title', 'file_path', 'description']],
    'prog_pengembangan' => ['table' => 'school_documents', 'title' => 'Program Pengembangan Diri', 'fields' => ['title', 'file_path', 'description']],
    'pemetaan_sk_kd' => ['table' => 'school_documents', 'title' => 'Pemetaan SK-KD', 'fields' => ['title', 'class_name', 'related_user_id', 'file_path', 'description']],
    'prota' => ['table' => 'school_documents', 'title' => 'Prota, Promes, & KMTT', 'fields' => ['title', 'file_path', 'description']],
    'Prosem' => ['table' => 'school_documents', 'title' => 'Prota, Promes, & KMTT', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR PROSES
    'admin_guru' => ['table' => 'school_documents', 'title' => 'Administrasi Guru (Silabus/RPP)', 'fields' => ['title', 'class_name', 'related_user_id', 'file_path', 'description']],
    'daftar_buku' => ['table' => 'school_documents', 'title' => 'Daftar Buku Teks & Referensi', 'fields' => ['title', 'file_path', 'description']],
    'kemajuan_kelas' => ['table' => 'school_documents', 'title' => 'Buku Kemajuan Kelas', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR SKL
    'dok_tugas' => ['table' => 'school_documents', 'title' => 'Dokumen Tugas Terstruktur', 'fields' => ['title', 'file_path', 'description']],
    'karya_siswa' => ['table' => 'school_documents', 'title' => 'Karya Siswa', 'fields' => ['title', 'file_path', 'description']],
    'prestasi_siswa' => ['table' => 'school_documents', 'title' => 'Dokumen Prestasi Siswa', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR PTK
    'file_ptk' => ['table' => 'school_documents', 'title' => 'File / Data PTK', 'fields' => ['title', 'file_path', 'description']],
    'buku_induk_ptk' => ['table' => 'school_documents', 'title' => 'Buku Induk Pegawai', 'fields' => ['title', 'file_path', 'description']],
    'pkb' => ['table' => 'school_documents', 'title' => 'Laporan PKB / Seminar', 'fields' => ['title', 'file_path', 'description']],
    'pkg' => ['table' => 'school_documents', 'title' => 'Laporan PKG / SKP / DP3', 'fields' => ['title', 'file_path', 'description']],
    'kepegawaian_lain' => ['table' => 'school_documents', 'title' => 'Administrasi Kepegawaian Lain', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR SARPRAS
    'analisis_sarpras' => ['table' => 'school_documents', 'title' => 'Analisis Kebutuhan Sarpras', 'fields' => ['title', 'file_path', 'description']],
    'master_plan' => ['table' => 'school_documents', 'title' => 'Master Plan & Dokumen Aset', 'fields' => ['title', 'file_path', 'description']],
    'log_barang' => ['table' => 'school_documents', 'title' => 'Buku Log Barang', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR PENGELOLAAN
    'visi_misi' => ['table' => 'school_documents', 'title' => 'Dokumen Visi Misi', 'fields' => ['title', 'file_path', 'description']],
    'struktur_org' => ['table' => 'school_documents', 'title' => 'Struktur Organisasi', 'fields' => ['title', 'file_path', 'description']],
    'evaluasi_program' => ['table' => 'school_documents', 'title' => 'Evaluasi Program Kerja', 'fields' => ['title', 'file_path', 'description']],
    'ppdb' => ['table' => 'school_documents', 'title' => 'Dokumen PPDB', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR PEMBIAYAAN
    'investasi' => ['table' => 'school_documents', 'title' => 'Dokumen Investasi', 'fields' => ['title', 'file_path', 'description']],
    'realisasi_anggaran' => ['table' => 'school_documents', 'title' => 'Laporan Realisasi Anggaran', 'fields' => ['title', 'file_path', 'description']],
    'beasiswa' => ['table' => 'school_documents', 'title' => 'Dokumen Beasiswa', 'fields' => ['title', 'file_path', 'description']],

    // STANDAR PENILAIAN
    'instrumen_penilaian' => ['table' => 'school_documents', 'title' => 'Instrumen Penilaian', 'fields' => ['title', 'file_path', 'description']],
    'analisis_penilaian' => ['table' => 'school_documents', 'title' => 'Analisis Hasil Evaluasi', 'fields' => ['title', 'file_path', 'description']],
    'remedial' => ['table' => 'school_documents', 'title' => 'Program Remedial & Pengayaan', 'fields' => ['title', 'file_path', 'description']],
    'legger' => ['table' => 'school_documents', 'title' => 'Legger Nilai', 'fields' => ['title', 'category', 'semester', 'class_name', 'related_user_id', 'file_path', 'description']],
    'ijazah' => ['table' => 'school_documents', 'title' => 'Arsip Ijazah & SKHUN', 'fields' => ['title', 'file_path', 'description']],

    // BUDAYA & LINGKUNGAN
    'sop' => ['table' => 'school_documents', 'title' => 'SOP Sekolah', 'fields' => ['title', 'file_path', 'description']],
    'tata_tertib' => ['table' => 'school_documents', 'title' => 'Tata Tertib', 'fields' => ['title', 'file_path', 'description']],
    'program_7k' => ['table' => 'school_documents', 'title' => 'Program 7K', 'fields' => ['title', 'file_path', 'description']],

    // HUMAS
    'komite' => ['table' => 'school_documents', 'title' => 'Dokumen Komite Sekolah', 'fields' => ['title', 'file_path', 'description']],
    'mou' => ['table' => 'school_documents', 'title' => 'Dokumen MoU Kemitraan', 'fields' => ['title', 'file_path', 'description']],

    // LOGS
    'diary_ks' => ['table' => 'school_logs', 'title' => 'Buku Harian KS', 'fields' => ['date', 'subject', 'details', 'notes']],
    'notulen' => ['table' => 'school_logs', 'title' => 'Notulen Rapat', 'fields' => ['date', 'subject', 'details', 'notes']],
    'pembinaan_guru' => ['table' => 'school_logs', 'title' => 'Pembinaan Profesi Guru', 'fields' => ['date', 'subject', 'details', 'notes']],
    'supervisi_admin' => ['table' => 'school_logs', 'title' => 'Supervisi Administrasi', 'fields' => ['date', 'subject', 'details', 'notes']],
    'catatan_khusus' => ['table' => 'school_logs', 'title' => 'Catatan Khusus', 'fields' => ['date', 'subject', 'details', 'notes']],
    'pemeliharaan' => ['table' => 'school_logs', 'title' => 'Pemeliharaan Sarpras', 'fields' => ['date', 'subject', 'details', 'notes']],

    // SPECIFIC TABLES
    'bku' => ['table' => 'school_finance', 'title' => 'Buku Kas Umum', 'fields' => ['date', 'type', 'amount', 'category', 'description', 'proof_file']],
    'inventaris' => ['table' => 'school_inventory', 'title' => 'Inventaris Barang', 'fields' => ['item_name', 'quantity', 'condition', 'location', 'acquisition_date', 'price', 'notes']],
    'surat_masuk' => ['table' => 'school_correspondence', 'title' => 'Surat Masuk', 'fields' => ['reference_number', 'date', 'sender', 'subject', 'file_path', 'disposition']],
    'surat_keluar' => ['table' => 'school_correspondence', 'title' => 'Surat Keluar', 'fields' => ['reference_number', 'date', 'sender', 'subject', 'file_path']],
    'buku_tamu' => ['table' => 'school_guest_book', 'title' => 'Buku Tamu', 'fields' => ['date', 'time_in', 'time_out', 'name', 'organization', 'position', 'phone', 'purpose', 'pic_school', 'result']],
];

if (!array_key_exists($type, $config)) {
    die("Tipe data tidak valid. Kembali ke <a href='admin_administrasi.php'>Menu Utama</a>.");
}

$current_config = $config[$type];
$table_name = $current_config['table'];
$page_title = $current_config['title'];
$fields = $current_config['fields'];

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $data = [];
        // Handle File Uploads
        $file_uploaded = null;
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/administrasi/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES['file_upload']['name']);
            move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadDir . $fileName);
            $file_uploaded = $uploadDir . $fileName;
        }

        // Prepare Data
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = $_POST[$field];
            }
            // Special handling for file fields
            if (strpos($field, 'file') !== false || $field === 'proof_file') {
                if ($file_uploaded) {
                    $data[$field] = $file_uploaded;
                } elseif ($action === 'edit' && isset($_POST['existing_' . $field])) {
                    $data[$field] = $_POST['existing_' . $field];
                }
            }
        }

        // Add 'type' column for shared tables
        if ($table_name === 'school_documents' || $table_name === 'school_logs' || $table_name === 'school_correspondence') {
            $data['type'] = ($table_name === 'school_correspondence' && $type === 'surat_masuk') ? 'masuk' :
                (($table_name === 'school_correspondence' && $type === 'surat_keluar') ? 'keluar' :
                    (($type === 'pemetaan_sk_kd') ? 'admin_guru' : $type));
        }

        if ($action === 'add') {
            $cols_str = implode(", ", array_keys($data));
            $vals_str = implode(", ", array_fill(0, count($data), "?"));
            $stmt = $db->prepare("INSERT INTO $table_name ($cols_str) VALUES ($vals_str)");
            $stmt->execute(array_values($data));
            $msg = "Data berhasil ditambahkan.";
        } elseif ($action === 'edit') {
            $id = $_POST['id'];
            $set_str = "";
            foreach ($data as $key => $val) {
                $set_str .= "$key = ?, ";
            }
            $set_str = rtrim($set_str, ", ");

            $values = array_values($data);
            $values[] = $id;

            $stmt = $db->prepare("UPDATE $table_name SET $set_str WHERE id = ?");
            $stmt->execute($values);
            $msg = "Data berhasil diperbarui.";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];

        // Fetch file path before deleting
        // Determine which columns exist for this table content
        $cols_to_fetch = [];
        $check_file_path = false;
        $check_proof_file = false;

        // Check columns based on config or simply check if they are in the $fields array
        // We can check $fields array which comes from config
        if (in_array('file_path', $fields)) {
            $cols_to_fetch[] = 'file_path';
        }
        if (in_array('proof_file', $fields)) {
            $cols_to_fetch[] = 'proof_file';
        }

        if (!empty($cols_to_fetch)) {
            $cols_query = implode(", ", $cols_to_fetch);
            $stmt_file = $db->prepare("SELECT $cols_query FROM $table_name WHERE id = ?");
            $stmt_file->execute([$id]);
            $row_file = $stmt_file->fetch();

            if ($row_file) {
                $files_to_delete = [];
                if (isset($row_file['file_path']) && !empty($row_file['file_path']))
                    $files_to_delete[] = $row_file['file_path'];
                if (isset($row_file['proof_file']) && !empty($row_file['proof_file']))
                    $files_to_delete[] = $row_file['proof_file'];


                foreach ($files_to_delete as $file_path) {
                    // Ensure the file path is relative to the script or absolute
                    // If stored as "../uploads/administrasi/file.pdf"
                    $absolute_path = __DIR__ . '/' . $file_path;
                    if (file_exists($absolute_path)) {
                        unlink($absolute_path);
                    }
                }
            }
        }

        $stmt = $db->prepare("DELETE FROM $table_name WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Data berhasil dihapus.";
    }
}

// --- FETCH DATA ---
$query = "SELECT * FROM $table_name";
$params = [];

if ($table_name === 'school_documents' || $table_name === 'school_logs') {
    $query .= " WHERE type = ?";
    $params[] = ($type === 'pemetaan_sk_kd') ? 'admin_guru' : $type;

    // Filter Pemetaan SK-KD to specific components only
    if ($type === 'pemetaan_sk_kd') {
        $query .= " AND title IN ('CP', 'TP', 'ATP', 'KKTP', 'Analisis CP', 'Perumusan TP', 'Penyusunan ATP', 'Pemetaan P5', 'Asesmen Diagnostik')";
    }
} elseif ($table_name === 'school_correspondence') {
    $c_type = ($type === 'surat_masuk') ? 'masuk' : 'keluar';
    $query .= " WHERE type = ?";
    $params[] = $c_type;
}

$query .= " ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola <?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
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

            /* Force header to stay horizontal */
            .main-content .header,
            main.main-content .header,
            .header {
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.75rem !important;
                margin-bottom: 1rem !important;
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
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div>
                        <h1><?= $page_title ?></h1>
                        <p style="color: var(--text-muted)">Administrasi Sekolah</p>
                    </div>
                </div>
            </header>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <a href="admin_administrasi.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($type === 'buku_tamu'): ?>
                            <button class="btn btn-info"
                                onclick="window.open('../print/get_buku_tamu_print.php', '_blank')">
                                <i class="bi bi-printer"></i> Cetak Buku
                            </button>
                        <?php elseif ($type === 'lpj'): ?>
                            <button class="btn btn-info" onclick="window.open('../print/get_lpj_print.php', '_blank')">
                                <i class="bi bi-printer"></i> Cetak Laporan
                            </button>
                        <?php elseif (in_array($type, ['ktsp', 'admin_guru', 'pemetaan_sk_kd'])): ?>
                            <button class="btn btn-info"
                                onclick="window.open('../print/get_administrasi_print.php?type=<?= $type ?>', '_blank')">
                                <i class="bi bi-printer"></i> Cetak Laporan
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-primary" onclick="openModal('add')">
                            <i class="bi bi-plus-lg"></i> Tambah Data
                        </button>
                    </div>
                </div>

                <?php if ($msg): ?>
                    <div
                        style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <?= $msg ?>
                    </div>
                <?php endif; ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <?php foreach ($fields as $f):
                                    // Allow description for admin_guru and pemetaan_sk_kd, otherwise hide long fields
                                    if ((strpos($f, 'file') !== false || $f === 'proof_file' || $f === 'details' || $f === 'notes') || ($f === 'description' && $type !== 'admin_guru' && $type !== 'pemetaan_sk_kd'))
                                        continue;
                                    ?>
                                    <?php
                                    $label = ucwords(str_replace('_', ' ', $f));
                                    if ($f === 'title' && $type === 'sk')
                                        $label = 'Nomor SK';
                                    if ($f === 'title' && ($type === 'ktsp' || $type === 'legger'))
                                        $label = 'Komponen Dokumen';
                                    if ($f === 'title' && ($type === 'admin_guru' || $type === 'pemetaan_sk_kd'))
                                        $label = 'Jenis Dokumen';
                                    if ($f === 'related_user_id')
                                        $label = ($type === 'lpj' || $type === 'admin_guru' || $type === 'pemetaan_sk_kd' || $type === 'legger') ? (($type === 'admin_guru' || $type === 'pemetaan_sk_kd' || $type === 'legger') ? 'Guru Kelas' : 'Petugas Administrasi') : 'Nama Guru';
                                    if ($f === 'title' && $type === 'legger')
                                        $label = 'Tahun Ajaran';
                                    if ($f === 'class_name')
                                        $label = 'Kelas';
                                    if ($f === 'category')
                                        $label = ($type === 'legger') ? 'Jenis Data' : 'Jenis SK';
                                    if ($f === 'semester')
                                        $label = 'Semester';
                                    if ($f === 'student_id')
                                        $label = 'Nama Siswa';
                                    if ($f === 'description')
                                        $label = 'Keterangan';
                                    if ($f === 'student_name')
                                        continue; // Handled in student_id column
                                    ?>
                                    <th><?= $label ?></th>
                                <?php endforeach; ?>
                                <th><?= ($type === 'admin_guru' || $type === 'pemetaan_sk_kd') ? 'Keterangan / File' : 'Detail' ?>
                                </th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($rows) === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center">Belum ada data.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $row): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <?php foreach ($fields as $f):
                                            if ((strpos($f, 'file') !== false || $f === 'proof_file' || $f === 'details' || $f === 'notes') || ($f === 'description' && $type !== 'admin_guru' && $type !== 'pemetaan_sk_kd'))
                                                continue;
                                            ?>
                                            <?php
                                            if ($f === 'student_name')
                                                continue; // Combined with student_id
                                            ?>
                                            <td>
                                                <?php
                                                if ($f === 'related_user_id') {
                                                    echo htmlspecialchars($teachers[$row[$f]] ?? '-');
                                                } elseif ($f === 'student_id') {
                                                    if (!empty($row['student_name'])) {
                                                        echo htmlspecialchars($row['student_name']);
                                                    } else {
                                                        echo htmlspecialchars($students_list[$row[$f]] ?? $row[$f]);
                                                    }
                                                } else {
                                                    echo htmlspecialchars($row[$f]);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td>
                                            <?php if (isset($row['description']) && $row['description']): ?>
                                                <?php if ($type !== 'admin_guru' && $type !== 'pemetaan_sk_kd'): ?>
                                                    <small
                                                        class="text-muted"><?= htmlspecialchars(substr($row['description'], 0, 30)) ?>...</small><br>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (isset($row['file_path']) && $row['file_path']):
                                                $ext = pathinfo($row['file_path'], PATHINFO_EXTENSION);
                                                if (strtolower($ext) === 'pdf'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        onclick="viewPdf('<?= htmlspecialchars($row['file_path']) ?>'); return false;">
                                                        <i class="bi bi-file-earmark-pdf"></i> Lihat PDF
                                                    </button>
                                                <?php else: ?>
                                                    <a href="<?= htmlspecialchars($row['file_path']) ?>" target="_blank"
                                                        class="text-primary"><i class="bi bi-file-earmark-text"></i> File</a>
                                                <?php endif; ?>
                                            <?php elseif (isset($row['proof_file']) && $row['proof_file']): ?>
                                                <a href="<?= htmlspecialchars($row['proof_file']) ?>" target="_blank"
                                                    class="text-primary"><i class="bi bi-receipt"></i> Bukti</a>
                                            <?php endif; ?>

                                            <?php if (isset($row['description']) && $row['description']): ?>
                                                <br><small
                                                    class="text-muted"><?= htmlspecialchars(substr($row['description'], 0, 30)) ?>...</small>
                                            <?php endif; ?>
                                            <?php if (isset($row['details']) && $row['details']): ?>
                                                <br><small
                                                    class="text-muted"><?= htmlspecialchars(substr($row['details'], 0, 30)) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-sm btn-secondary"
                                                    onclick='openModal("edit", <?= json_encode($row) ?>)'><i
                                                        class="bi bi-pencil"></i></button>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="confirmDelete(<?= $row['id'] ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($type === 'tata_tertib'): ?>
                <div class="card" style="margin-top: 2rem;">
                    <div style="border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1rem;">
                        <h3 style="margin: 0; color: var(--text-dark);">Data Tata Tertib Kelas (Input Guru)</h3>
                        <p style="margin: 0.25rem 0 0 0; color: var(--text-muted); font-size: 0.9rem;">
                            Rekapitulasi aturan yang dibuat oleh wali kelas.
                        </p>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 150px;">Kelas</th>
                                    <th>Isi Tata Tertib</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cr_data = [];
                                try {
                                    $cr_stmt = $db->query("SELECT * FROM class_rules ORDER BY class_name ASC");
                                    $cr_data = $cr_stmt->fetchAll();
                                } catch (PDOException $e) {
                                    // Table might not exist yet
                                }
                                ?>
                                <?php if (empty($cr_data)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align:center; padding: 2rem; color: var(--text-muted);">
                                            Belum ada data tata tertib dari guru.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cr_data as $idx => $row): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td style="font-weight: 600;"><?= htmlspecialchars($row['class_name']) ?></td>
                                            <td><?= htmlspecialchars($row['content']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- MODAL -->
    <div id="dataModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
        <div class="modal-content"
            style="background: white; margin: 1rem auto; padding: 1.5rem; border-radius: 12px; max-width: 1100px; width: 95%; position: relative;">
            <h3 id="modalTitle" style="margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;">
                Tambah Data</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="dataId">

                <div
                    style="display: grid; grid-template-columns: repeat(2, 1fr); column-gap: 1.5rem; row-gap: 0.25rem;">
                    <?php foreach ($fields as $f):
                        // Determine if field should be full width
                        $isFullWidth = in_array($f, ['description', 'details', 'notes', 'disposition', 'purpose', 'result', 'address', 'subject', 'title']);
                        $gridStyle = $isFullWidth ? 'grid-column: span 2;' : '';
                        ?>
                        <div class="form-group" style="<?= $gridStyle ?>">
                            <label class="form-label"
                                style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.25rem; display: block; color: var(--text-dark);">
                                <?php
                                $label = ucwords(str_replace('_', ' ', $f));
                                if ($f === 'title' && $type === 'sk')
                                    $label = 'Nomor SK';
                                if ($f === 'title' && ($type === 'ktsp' || $type === 'admin_guru' || $type === 'pemetaan_sk_kd' || $type === 'legger'))
                                    $label = 'Komponen Dokumen';
                                if ($f === 'related_user_id')
                                    $label = ($type === 'lpj' || $type === 'admin_guru' || $type === 'pemetaan_sk_kd' || $type === 'legger') ? (($type === 'admin_guru' || $type === 'pemetaan_sk_kd' || $type === 'legger') ? 'Guru Kelas' : 'Petugas Administrasi') : 'Nama Guru';
                                if ($f === 'title' && $type === 'legger')
                                    $label = 'Tahun Ajaran';
                                if ($f === 'class_name')
                                    $label = 'Kelas';
                                if ($f === 'category')
                                    $label = ($type === 'legger') ? 'Jenis Data' : 'Jenis SK';
                                if ($f === 'semester')
                                    $label = 'Semester';
                                echo $label;
                                ?>
                            </label>
                            <?php if (strpos($f, 'date') !== false): ?>
                                <input type="date" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control" required
                                    style="padding: 0.6rem 0.75rem;">
                            <?php elseif ($f === 'student_id'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($students_list as $sId => $sName): ?>
                                        <option value="<?= $sId ?>"><?= htmlspecialchars($sName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($f === 'student_name'): ?>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;" placeholder="Nama Siswa (Manual)">
                            <?php elseif ($f === 'type' && $table_name === 'student_mutation'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;" required onchange="handleMutationType(this)">
                                    <option value="">-- Jenis Mutasi --</option>
                                    <option value="masuk">Masuk</option>
                                    <option value="keluar">Keluar</option>
                                    <option value="lulus">Lulus</option>
                                    <option value="dropout">Dropout</option>
                                </select>
                            <?php elseif (strpos($f, 'time') !== false): ?>
                                <input type="time" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                            <?php elseif (strpos($f, 'amount') !== false || strpos($f, 'price') !== false || strpos($f, 'quantity') !== false): ?>
                                <input type="number" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                            <?php elseif (strpos($f, 'file') !== false || $f === 'proof_file'): ?>
                                <input type="file" name="file_upload" class="form-control" style="padding: 0.6rem 0.75rem;"
                                    <?= $type === 'sk' ? 'accept=".pdf"' : '' ?>>
                                <input type="hidden" name="existing_<?= $f ?>" id="field_<?= $f ?>">
                                <small class="text-muted" id="file_hint_<?= $f ?>"></small>
                            <?php elseif ($f === 'description' || $f === 'details' || $f === 'notes' || $f === 'disposition' || $f === 'purpose' || $f === 'result' || $f === 'address'): ?>
                                <textarea name="<?= $f ?>" id="field_<?= $f ?>" class="form-control" rows="2"
                                    style="padding: 0.6rem 0.75rem;"></textarea>
                            <?php elseif ($f === 'related_user_id'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Guru --</option>
                                    <?php foreach ($teachers as $tId => $tName): ?>
                                        <option value="<?= $tId ?>"><?= htmlspecialchars($tName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($f === 'category' && $type === 'sk'): ?>
                                <select id="field_<?= $f ?>_select" class="form-control" style="padding: 0.6rem 0.75rem;"
                                    onchange="handleCategoryChange(this)">
                                    <option value="">-- Pilih Jenis SK --</option>
                                    <option value="SK Berkala">SK Berkala</option>
                                    <option value="SK-KP">SK-KP</option>
                                    <option value="SKPBM">SKPBM</option>
                                    <option value="Lainnya">Lainnya (Input Manual)</option>
                                </select>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="display:none; margin-top: 0.5rem; padding: 0.6rem 0.75rem;"
                                    placeholder="Masukkan Jenis SK Manual">
                            <?php elseif ($f === 'category' && $type === 'legger'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Jenis Data --</option>
                                    <option value="UH">UH (Ulangan Harian/Formatif)</option>
                                    <option value="STS">STS (Sumatif Tengah Semester)</option>
                                    <option value="SAS">SAS (Sumatif Akhir Semester)</option>
                                    <option value="Remidi/Pengayaan">Remidi/Pengayaan</option>
                                </select>
                            <?php elseif ($f === 'semester'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Semester --</option>
                                    <option value="1">Semester 1 (Ganjil)</option>
                                    <option value="2">Semester 2 (Genap)</option>
                                </select>
                            <?php elseif ($f === 'type' && $table_name === 'school_finance'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="masuk">Pemasukan</option>
                                    <option value="keluar">Pengeluaran</option>
                                </select>
                            <?php elseif ($f === 'condition' && $table_name === 'school_inventory'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="baik">Baik</option>
                                    <option value="rusak_ringan">Rusak Ringan</option>
                                    <option value="rusak_berat">Rusak Berat</option>
                                </select>
                            <?php elseif ($f === 'location' && $table_name === 'school_inventory'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Lokasi --</option>
                                    <?php foreach ($school_rooms as $rId => $rName): ?>
                                        <option value="<?= htmlspecialchars($rName) ?>"><?= htmlspecialchars($rName) ?></option>
                                    <?php endforeach; ?>
                                    <option value="Gudang">Gudang</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            <?php elseif ($f === 'class_name'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;" onchange="updateClassTeacher(this.value)">
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($school_classes as $className): ?>
                                        <option value="<?= htmlspecialchars($className) ?>"><?= htmlspecialchars($className) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($f === 'sender'): ?>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    placeholder="<?= $type === 'surat_masuk' ? 'Pengirim' : 'Tujuan' ?>"
                                    style="padding: 0.6rem 0.75rem;">
                            <?php elseif ($f === 'subject' && $type === 'pemeliharaan'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Ruangan/Lokasi --</option>
                                    <?php foreach ($school_rooms as $rId => $rName): ?>
                                        <option value="<?= htmlspecialchars($rName) ?>"><?= htmlspecialchars($rName) ?></option>
                                    <?php endforeach; ?>
                                    <option value="Lainnya">Lainnya (Input Manual)</option>
                                </select>
                            <?php elseif ($f === 'reason'): ?>
                                <textarea name="<?= $f ?>" id="field_<?= $f ?>" class="form-control" rows="2"
                                    style="padding: 0.6rem 0.75rem;"></textarea>
                            <?php elseif ($f === 'title' && $type === 'ktsp'): ?>
                                <select id="field_<?= $f ?>_select" class="form-control" style="padding: 0.6rem 0.75rem;"
                                    onchange="handleSelectManual(this, 'field_<?= $f ?>')">
                                    <option value="">-- Pilih Dokumen --</option>
                                    <option value="Buku I (KTSP)">Buku I (KTSP)</option>
                                    <option value="Buku II (Silabus)">Buku II (Silabus)</option>
                                    <option value="Buku III (RPP)">Buku III (RPP)</option>
                                    <option value="Lainnya">Lainnya (Input Manual)</option>
                                </select>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="display:none; margin-top: 0.5rem; padding: 0.6rem 0.75rem;"
                                    placeholder="Masukkan Judul Manual">
                            <?php elseif ($f === 'title' && $type === 'admin_guru'): ?>
                                <select id="field_<?= $f ?>_select" class="form-control" style="padding: 0.6rem 0.75rem;"
                                    onchange="handleSelectManual(this, 'field_<?= $f ?>')">
                                    <option value="">-- Pilih Komponen --</option>
                                    <option value="CP">CP (Capaian Pembelajaran)</option>
                                    <option value="TP">TP (Tujuan Pembelajaran)</option>
                                    <option value="ATP">ATP (Alur Tujuan Pembelajaran)</option>
                                    <option value="Prota">Prota (Program Tahunan)</option>
                                    <option value="Promes">Promes (Program Semester)</option>
                                    <option value="Modul Ajar">Modul Ajar</option>
                                    <option value="Jurnal Harian">Jurnal Harian</option>
                                    <option value="KKTP">KKTP (Kriteria Ketercapaian Tujuan Pembelajaran)</option>
                                    <option value="Daftar Nilai">Daftar Nilai</option>
                                    <option value="Analisis Soal">Analisis Soal</option>
                                    <option value="Bank Soal & Kisi-kisi">Bank Soal & Kisi-kisi</option>
                                    <option value="Program Remedial & Pengayaan">Program Remedial & Pengayaan</option>
                                    <option value="Lainnya">Lainnya (Input Manual)</option>
                                </select>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="display:none; margin-top: 0.5rem; padding: 0.6rem 0.75rem;"
                                    placeholder="Masukkan Komponen Manual">
                            <?php elseif ($f === 'title' && $type === 'pemetaan_sk_kd'): ?>
                                <select id="field_<?= $f ?>_select" class="form-control" style="padding: 0.6rem 0.75rem;"
                                    onchange="handleSelectManual(this, 'field_<?= $f ?>')">
                                    <option value="">-- Pilih Komponen --</option>
                                    <option value="Analisis CP">Analisis CP (Capaian Pembelajaran)</option>
                                    <option value="Perumusan TP">Perumusan TP (Tujuan Pembelajaran)</option>
                                    <option value="Penyusunan ATP">Penyusunan ATP (Alur Tujuan Pembelajaran)</option>
                                    <option value="Pemetaan P5">Pemetaan P5 (Projek Penguatan Profil Pelajar Pancasila)</option>
                                    <option value="Asesmen Diagnostik">Asesmen Diagnostik</option>
                                    <option value="KKTP">KKTP (Kriteria Ketercapaian Tujuan Pembelajaran)</option>
                                    <option value="Lainnya">Lainnya (Input Manual)</option>
                                </select>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="display:none; margin-top: 0.5rem; padding: 0.6rem 0.75rem;"
                                    placeholder="Masukkan Komponen Manual">
                            <?php elseif ($f === 'title' && $type === 'legger'): ?>
                                <select name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                                    <option value="">-- Pilih Tahun Ajaran --</option>
                                    <?php
                                    $curYear = date('Y');
                                    for ($y = $curYear; $y >= $curYear - 5; $y--) {
                                        $nextY = $y + 1;
                                        echo "<option value='{$y}/{$nextY}'>{$y}/{$nextY}</option>";
                                    }
                                    ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="<?= $f ?>" id="field_<?= $f ?>" class="form-control"
                                    style="padding: 0.6rem 0.75rem;">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div
                    style="display: flex; gap: 1rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #eee; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()"
                        style="padding: 0.5rem 1.5rem;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DELETE CONFIRMATION MODAL -->
    <div id="deleteModal" class="modal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1002; align-items: center; justify-content: center;">
        <div class="modal-content"
            style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; margin: 0;">
            <div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;">
                <i class="bi bi-exclamation-circle"></i>
            </div>
            <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">Hapus Data?</h3>
            <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Data dan file terkait akan dihapus permanen.
                Tindakan ini tidak dapat dibatalkan.</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button onclick="closeDeleteModal()" class="btn btn-secondary" style="width: 100px;">Batal</button>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="btn btn-danger" style="width: 100px;">Hapus</button>
                </form>
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

    <!-- Scripts from dashboard -->
    <script>
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
                // Use get_pdf_content.php with specific validation there
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

        // Delete Logic
        const deleteModal = document.getElementById('deleteModal');
        const deleteIdInput = document.getElementById('deleteId');

        function confirmDelete(id) {
            deleteIdInput.value = id;
            deleteModal.style.display = 'flex';
        }

        function closeDeleteModal() {
            deleteModal.style.display = 'none';
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = 'none';
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        // Category Logic for SK
        function handleCategoryChange(select) {
            const input = document.getElementById('field_category');
            if (select.value === 'Lainnya') {
                input.style.display = 'block';
                input.value = '';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = select.value;
            }
        }

        // Mutation Logic
        function handleMutationType(select) {
            const val = select.value;
            // Use optional chaining or check existence

            const fromEl = document.getElementById('field_from_school');
            const toEl = document.getElementById('field_to_school');

            // Student Toggle
            const studentIdEl = document.getElementById('field_student_id');
            const studentNameEl = document.getElementById('field_student_name');

            if (fromEl && toEl) {
                const fromInput = fromEl.closest('.form-group');
                const toInput = toEl.closest('.form-group');

                // Student inputs container
                const idInput = studentIdEl ? studentIdEl.closest('.form-group') : null;
                const nameInput = studentNameEl ? studentNameEl.closest('.form-group') : null;

                if (val === 'masuk') {
                    fromInput.style.display = 'block';
                    toInput.style.display = 'none';
                    if (idInput) idInput.style.display = 'none';
                    if (nameInput) nameInput.style.display = 'block';
                    // Clear select if switching to manual
                    if (studentIdEl) studentIdEl.value = '';
                } else if (val === 'keluar') {
                    fromInput.style.display = 'none';
                    toInput.style.display = 'block';
                    if (idInput) idInput.style.display = 'block';
                    if (nameInput) nameInput.style.display = 'none';
                    // Clear manual if switching to select
                    if (studentNameEl) studentNameEl.value = '';
                } else {
                    fromInput.style.display = 'none';
                    toInput.style.display = 'none';
                    if (idInput) idInput.style.display = 'block';
                    if (nameInput) nameInput.style.display = 'none';
                }
            }
        }

        const modal = document.getElementById('dataModal');
        const modalTitle = document.getElementById('modalTitle');
        const formAction = document.getElementById('formAction');
        const dataId = document.getElementById('dataId');

        function openModal(mode, data = null) {
            modal.style.display = 'block';
            if (mode === 'add') {
                modalTitle.innerText = 'Tambah Data';
                formAction.value = 'add';
                dataId.value = '';
                // Clear fields
                document.querySelectorAll('.form-control').forEach(el => {
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                });

                // SK specific reset
                const catInput = document.getElementById('field_category');
                const catSelect = document.getElementById('field_category_select');
                if (catInput && catSelect) {
                    catInput.style.display = 'none';
                    catSelect.value = '';
                }

                // Clear all file hints
                document.querySelectorAll('[id^="file_hint_"]').forEach(el => el.innerText = '');

                // Set default date
                const dateField = document.querySelector('input[type="date"]');
                if (dateField) dateField.valueAsDate = new Date();
            } else {
                modalTitle.innerText = 'Edit Data';
                formAction.value = 'edit';
                dataId.value = data.id;
                // Fill fields
                <?php foreach ($fields as $f): ?>
                    if (document.getElementById('field_<?= $f ?>')) {
                        document.getElementById('field_<?= $f ?>').value = data['<?= $f ?>'] || '';

                        // Handle Category for SK
                        <?php if ($f === 'category' && $type === 'sk'): ?>
                            const catVal = data['<?= $f ?>'];
                            const catSelect = document.getElementById('field_<?= $f ?>_select');
                            const catInput = document.getElementById('field_<?= $f ?>');
                            if (catSelect && catInput) {
                                if (['SK Berkala', 'SK-KP', 'SKPBM'].includes(catVal)) {
                                    catSelect.value = catVal;
                                    catInput.style.display = 'none';
                                } else {
                                    catSelect.value = 'Lainnya';
                                    catInput.style.display = 'block';
                                    catInput.value = catVal;
                                }
                            }
                        <?php endif; ?>

                        <?php if ($f === 'title' && ($type === 'ktsp' || $type === 'admin_guru' || $type === 'pemetaan_sk_kd')): ?>
                            const titleVal = data['<?= $f ?>'];
                            const titleSelect = document.getElementById('field_<?= $f ?>_select');
                            const titleInput = document.getElementById('field_<?= $f ?>');
                            if (titleSelect && titleInput) {
                                // Check if value exists in options
                                let optionExists = false;
                                for (let i = 0; i < titleSelect.options.length; i++) {
                                    if (titleSelect.options[i].value === titleVal) {
                                        optionExists = true;
                                        break;
                                    }
                                }

                                if (optionExists) {
                                    titleSelect.value = titleVal;
                                    titleInput.style.display = 'none';
                                } else {
                                    titleSelect.value = 'Lainnya';
                                    titleInput.style.display = 'block';
                                    titleInput.value = titleVal;
                                }
                            }
                        <?php endif; ?>

                        <?php if (strpos($f, 'file') !== false || $f === 'proof_file'): ?>
                            var hintEl = document.getElementById('file_hint_<?= $f ?>');
                            if (hintEl && data['<?= $f ?>']) hintEl.innerText = 'File: ' + data['<?= $f ?>'].split('/').pop();
                        <?php endif; ?>
                    }
                <?php endforeach; ?>
            }

            // Check for mutation type trigger
            const typeSelect = document.getElementById('field_type');
            if (typeSelect && typeof handleMutationType === 'function') {
                handleMutationType(typeSelect);
            }
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close on outside click
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == document.getElementById('pdfModal')) {
                closePdfModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        // Class teacher mapping function
        const classTeacherMap = <?= json_encode($class_teacher_map ?? []) ?>;

        function updateClassTeacher(className) {
            const teacherSelect = document.getElementById('field_related_user_id');
            if (teacherSelect && classTeacherMap[className]) {
                teacherSelect.value = classTeacherMap[className];
            } else if (teacherSelect) {
                // Optional: clear selection if no mapping found, or keep as is
                teacherSelect.value = "";
            }
        }

        function handleSelectManual(select, inputId) {
            const input = document.getElementById(inputId);
            if (select.value === 'Lainnya') {
                input.style.display = 'block';
                input.value = '';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = select.value;
            }
        }

    </script>

    <!-- Sidebar Toggle Script -->
    <script src="../assets/admin-sidebar.js"></script>
</body>

</html>