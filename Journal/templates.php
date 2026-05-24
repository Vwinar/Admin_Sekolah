<?php
session_start();
// Include Journal DB handles $pdo
require_once 'db.php';
// Include Main App DB handles $db (for user profile/auth)
require_once '../laporan_harian/config/db_connect.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../laporan_harian/index.php');
    exit;
}

// Get User Profile for Sidebar
$stmt_user = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$current_user = $stmt_user->fetch();

// --- TEMPLATE LOGIC START ---

// 1. Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_delete'])) {
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        foreach ($_POST['selected_ids'] as $id) {
            deleteTemplate($id);
        }
    }
    header('Location: templates.php?success=bulk_delete');
    exit;
}

// 2. Handle Export
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_template'])) {
    $subject = $_POST['subject_export'] ?? '';

    // Determine filename
    $filename = 'templates_' . ($subject ? preg_replace('/[^a-z0-9]/i', '_', $subject) : 'all') . '_' . date('Y-m-d') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add logic to get templates
    // If subject is selected, filter by subject. If "all", get all.
    // We reuse existing functions but might need new one if we want generic export
    // For simplicity, let's just fetch all and filter in PHP loop if simpler, or use DB.

    // CSV Header
    fputcsv($output, ['Subject', 'Type', 'Content']);

    $allTemplates = getAllTemplates(); // This is filtered by User ID

    foreach ($allTemplates as $tpl) {
        if ($subject && $subject !== 'all' && $tpl['subject'] !== $subject) {
            continue;
        }
        fputcsv($output, [
            $tpl['subject'],
            $tpl['type'],
            $tpl['content']
        ]);
    }

    fclose($output);
    exit;
}

// 3. Handle Download Sample
if (isset($_GET['download_sample'])) {
    $type = $_GET['type'] ?? 'auto';

    header('Content-Type: text/csv');
    // Sanitize filename
    $filename = ($type === 'auto') ? 'template_import_master.csv' : 'template_' . $type . '.csv';
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if ($type === 'auto') {
        // --- MASTER TEMPLATE (2 Columns) ---
        // Header
        fputcsv($output, ['Type (Jenis Template)', 'Content (Isi Template)']);

        // Contoh Capaian Pembelajaran (5 item)
        fputcsv($output, ['Capaian Pembelajaran', 'Peserta didik mampu menjelaskan konsep dasar operasi hitung bilangan bulat.']);
        fputcsv($output, ['Capaian Pembelajaran', 'Peserta didik mampu menganalisis hubungan antar sudut pada dua garis sejajar.']);
        // ... (truncated for brevity in code, but I'll keep the full list or a subset to be safe, actually I should keep the full list if replacing)
        // Wait, replacing the whole block, I should re-include the relevant examples or just a few if the user wants specific ones.
        // User asked for "template untuk masing-masing bagian".
        // Let's allow the 'auto' to remain as is (comprehensive), and 'specific' to be just that list.

        // To save space in this Replace block, I will re-use the arrays if possible or just write them out.
        // Since I'm replacing the WHOLE block, I must reproduce the 'auto' content.

        $examples = [
            ['Capaian Pembelajaran', 'Peserta didik mampu menjelaskan konsep dasar operasi hitung bilangan bulat.'],
            ['Capaian Pembelajaran', 'Peserta didik mampu menganalisis hubungan antar sudut pada dua garis sejajar.'],
            ['Tujuan Pembelajaran', 'Siswa dapat mengidentifikasi unsur-unsur lingkaran dengan benar.'],
            ['Tujuan Pembelajaran', 'Siswa mampu menghitung luas permukaan bangun ruang sisi datar.'],
            ['Pokok Materi', 'Bilangan Bulat dan Pecahan'],
            ['Pokok Materi', 'Aljabar dan Operasi Hitung'],
            ['Permasalahan', 'Sebagian siswa kesulitan memahami konsep bilangan negatif.'],
            ['Permasalahan', 'Motivasi belajar menurun pada jam pelajaran terakhir.'],
            ['Solusi', 'Menggunakan alat peraga garis bilangan untuk visualisasi.'],
            ['Solusi', 'Melakukan ice breaking singkat di tengah pelajaran untuk mengembalikan fokus.'],
            ['Catatan Pembelajaran', 'Pembelajaran berjalan lancar, 80% siswa mencapai KKM.'],
            ['Catatan Pembelajaran', 'Perlu pendalaman materi tambahan untuk kelompok bawah.']
        ];
        foreach ($examples as $row)
            fputcsv($output, $row);

    } else {
        // --- SPECIFIC TEMPLATE (1 Column) ---
        // Header
        fputcsv($output, ['Content (Isi Template)']);

        // Define examples based on type
        $specificExamples = [
            'capaian_pembelajaran' => [
                'Peserta didik mampu menjelaskan konsep dasar operasi hitung bilangan bulat.',
                'Peserta didik mampu menganalisis hubungan antar sudut pada dua garis sejajar.',
                'Peserta didik dapat menyajikan data dalam bentuk tabel dan diagram batang.',
                'Peserta didik memahami prinsip kesebangunan dan kekongruenan antar bangun datar.',
                'Peserta didik mampu menyelesaikan masalah kontekstual persamaan linear dua variabel.'
            ],
            'pencapaian' => [
                'Siswa dapat mengidentifikasi unsur-unsur lingkaran dengan benar.',
                'Siswa mampu menghitung luas permukaan bangun ruang sisi datar.',
                'Siswa dapat membedakan fungsi linear dan non-linear.',
                'Siswa mampu menerapkan teorema Pythagoras dalam pemecahan masalah.',
                'Siswa dapat menyusun model matematika dari masalah aritmatika sosial.'
            ],
            'pokok_materi' => [
                'Bilangan Bulat dan Pecahan',
                'Aljabar dan Operasi Hitung',
                'Geometri dan Pengukuran',
                'Statistika dan Peluang',
                'Persamaan dan Pertidaksamaan Linear'
            ],
            'permasalahan' => [
                'Sebagian siswa kesulitan memahami konsep bilangan negatif.',
                'Motivasi belajar menurun pada jam pelajaran terakhir.',
                'Siswa cenderung pasif saat sesi diskusi kelompok.',
                'Beberapa siswa belum menguasai operasi perkalian dasar.',
                'Kurangnya konsentrasi siswa saat penjelasan materi abstrak.'
            ],
            'solusi' => [
                'Menggunakan alat peraga garis bilangan untuk visualisasi.',
                'Melakukan ice breaking singkat di tengah pelajaran untuk mengembalikan fokus.',
                'Menerapkan model pembelajaran kooperatif tipe STAD.',
                'Memberikan drill soal dasar selama 10 menit awal.',
                'Menggunakan media visual atau video animasi untuk menjelaskan konsep.'
            ],
            'catatan_pembelajaran' => [
                'Pembelajaran berjalan lancar, 80% siswa mencapai KKM.',
                'Perlu pendalaman materi tambahan untuk kelompok bawah.',
                'Antusiasme siswa sangat tinggi saat praktek lapangan.',
                'Waktu pengerjaan tugas perlu ditambah pada pertemuan berikutnya.',
                'Kondisi kelas kondusif, semua target pembelajaran tercapai.'
            ]
        ];

        if (isset($specificExamples[$type])) {
            foreach ($specificExamples[$type] as $ex) {
                fputcsv($output, [$ex]);
            }
        } else {
            fputcsv($output, ['Contoh isi template...']);
        }
    }

    fclose($output);
    exit;
}

// 4. Handle Import
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_template'])) {
    // Check file
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] == 0) {
        $file = $_FILES['import_file']['tmp_name'];
        $subject = $_POST['subject_import'];
        $targetType = $_POST['target_type'] ?? 'auto';

        $handle = fopen($file, "r");
        // Skip header
        fgetcsv($handle);

        $count = 0;

        // Use Transaction for speed and integrity
        $pdo->beginTransaction();

        try {
            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                // Layout: Type, Content OR just Content if targetType is set

                $content = '';
                $finalType = null;

                if ($targetType !== 'auto') {
                    // FORCE TYPE MODE
                    $finalType = $targetType;

                    // If CSV has 1 col: Content is col 0
                    // If CSV has 2+ cols: Content is col 1 (assume col 0 is ignored type/index)
                    if (count($data) == 1) {
                        $content = trim($data[0]);
                    } else if (count($data) >= 2) {
                        $content = trim($data[1]);
                    }
                } else {
                    // AUTO DETECT MODE (Original Logic)
                    if (count($data) >= 2) {
                        $type = trim($data[0]);
                        $content = trim($data[1]);

                        // Validate Type
                        $allowedTypes = ['capaian_pembelajaran', 'pokok_materi', 'pencapaian', 'permasalahan', 'solusi', 'catatan_pembelajaran'];

                        // Simple mapping lookup
                        $typeMap = [
                            // Official Keys & Common Vars
                            'capaian_pembelajaran' => 'capaian_pembelajaran',
                            'capaian pembelajaran' => 'capaian_pembelajaran',
                            'cp' => 'capaian_pembelajaran',
                            'capaian materi' => 'capaian_pembelajaran',

                            'pokok_materi' => 'pokok_materi',
                            'pokok materi' => 'pokok_materi',
                            'materi' => 'pokok_materi',

                            'pencapaian' => 'pencapaian',
                            'tujuan pembelajaran' => 'pencapaian',
                            'tp' => 'pencapaian',
                            'tujuan' => 'pencapaian',

                            'permasalahan' => 'permasalahan',
                            'masalah' => 'permasalahan',

                            'solusi' => 'solusi',

                            'catatan_pembelajaran' => 'catatan_pembelajaran',
                            'catatan pembelajaran' => 'catatan_pembelajaran',
                            'catatan' => 'catatan_pembelajaran'
                        ];

                        $typeLower = strtolower($type);
                        $finalType = isset($typeMap[$typeLower]) ? $typeMap[$typeLower] : (in_array($type, $allowedTypes) ? $type : null);
                    }
                }

                if ($finalType && !empty($content)) {
                    $insertData = [
                        'subject' => $subject,
                        'type' => $finalType,
                        'content' => $content
                    ];
                    insertTemplate($insertData);
                    $count++;
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            header('Location: templates.php?error=import_failed');
            exit;
        }

        fclose($handle);
        header('Location: templates.php?success=import&count=' . $count);
        exit;
    } else {
        header('Location: templates.php?error=upload_failed');
        exit;
    }
}


// Handle form submission for adding new template
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_template'])) {
    $subject = $_POST['subject'];
    $type = $_POST['type'];

    // Insert templates from selected checkboxes
    if (isset($_POST['template_checkbox']) && is_array($_POST['template_checkbox'])) {
        foreach ($_POST['template_checkbox'] as $templateId) {
            $existingTemplate = getTemplateById($templateId);
            if ($existingTemplate) {
                $data = [
                    'subject' => $subject,
                    'type' => $type,
                    'content' => $existingTemplate['content']
                ];
                insertTemplate($data);
            }
        }
    }

    // Insert manual templates
    if (isset($_POST['manual_content']) && is_array($_POST['manual_content'])) {
        foreach ($_POST['manual_content'] as $content) {
            $trimmed = trim($content);
            if ($trimmed !== '') {
                $data = [
                    'subject' => $subject,
                    'type' => $type,
                    'content' => $trimmed
                ];
                insertTemplate($data);
            }
        }
    }

    header('Location: templates.php?success=add');
    exit;
}

// Handle delete (Legacy Single Delete)
if (isset($_GET['delete'])) {
    deleteTemplate($_GET['delete']);
    header('Location: templates.php?success=delete');
    exit;
}

// Handle edit variables
$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = getTemplateById($_GET['edit']);
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_template'])) {
    $data = [
        'subject' => $_POST['subject'],
        'type' => $_POST['type'],
        'content' => trim($_POST['content'])
    ];
    updateTemplate($_POST['id'], $data);
    header('Location: templates.php?success=update');
    exit;
}

$templates = getAllTemplatesGroupedBySubject();
$subjects = getAllSubjects();
$allTemplatesGrouped = getAllTemplatesGrouped();

// --- APPLY NATURAL SORTING ---
// Helper to sort by content naturally
function sortTemplatesNaturally(&$array) {
    usort($array, function($a, $b) {
        return strnatcmp($a['content'], $b['content']);
    });
}

// Sort $templates (Main List)
foreach ($templates as $subj => &$types) {
    foreach ($types as $type => &$items) {
        sortTemplatesNaturally($items);
    }
}
unset($types, $items);

// Sort $allTemplatesGrouped (Copy Options)
foreach ($allTemplatesGrouped as $type => &$items) {
    sortTemplatesNaturally($items);
}
unset($items);

function getLabel($type)
{
    $types = [
        'capaian_pembelajaran' => 'Capaian Pembelajaran',
        'pokok_materi' => 'Pokok Materi',
        'pencapaian' => 'Tujuan Pembelajaran',
        'permasalahan' => 'Permasalahan',
        'solusi' => 'Solusi',
        'catatan_pembelajaran' => 'Catatan Pembelajaran'
    ];
    return $types[$type] ?? $type;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Template Jurnal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Local overrides */
        .template-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .template-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        .subject-section {
            background: var(--surface);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .subject-header {
            background: #f8fafc;
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--text-main);
        }
        .subject-header:hover {
            background: #f1f5f9;
        }
        .subject-body {
            padding: 1rem;
            display: none;
        }
        .subject-body.expanded {
            display: block;
        }
        .type-group {
            margin-bottom: 1.5rem;
        }
        .type-group h5 {
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        .templates-layout {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 2rem;
        }
        .manual-entry {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            align-items: center;
        }
        .manual-textarea {
            flex: 1;
            min-height: 40px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
        }
        .manual-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .remove-manual-btn {
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
        }
        .remove-manual-btn:hover {
             background-color: #dc2626;
             color: white;
             border-color: #dc2626;
        }
        .add-manual-btn {
            width: 100%;
            padding: 0.6rem;
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        .add-manual-btn:hover {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }
        @media (max-width: 768px) {
            .templates-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .sticky-card { position: static !important; }
            .form-actions { flex-direction: row; justify-content: flex-end; }
            .form-actions .btn { width: auto !important; }
        }
        .btn-success {
            background-color: #10b981;
            color: white;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-success:hover {
            background-color: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }
        .form-actions {
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        @media (max-width: 768px) {
            .header { position: relative; }
            .header-right { position: absolute; top: 0; right: 0; margin-top: 0.25rem; }
            .back-btn { width: 40px !important; height: 40px; padding: 0 !important; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: white; box-shadow: var(--shadow-sm); }
            .back-btn .btn-text { display: none; }
            .back-btn i { margin-right: 0 !important; }
        }

        /* Checkbox & Bulk Actions */
        .select-checkbox {
            width: 18px;
            height: 18px;
            margin-right: 12px;
            cursor: pointer;
        }
        .bulk-actions-bar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: center;
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid var(--border);
        }
        .bulk-actions-bar.visible {
            transform: translateX(-50%) translateY(0);
        }
        .bulk-count {
            font-weight: 600;
            color: var(--text-main);
        }
        
        /* Modal Scroll List */
        .scrollable-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem;
            background: #f8fafc;
        }
        .scroll-item {
            padding: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .scroll-item:last-child {
            border-bottom: none;
        }
        
        /* Modal Styles enhancement */
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: modalFadeIn 0.3s; position: relative; }
        .modal-header { padding: 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; background: #f8fafc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; }
        .close { font-size: 1.5rem; font-weight: 700; line-height: 1; color: #94a3b8; cursor: pointer; border: none; background: none; }
        .close:hover { color: var(--text-main); }
        @keyframes modalFadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="dashboard-page">
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <?php include '../laporan_harian/layout/user_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                 <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <span></span><span></span><span></span>
                    </button>
                    <div>
                        <h1>Kelola Template</h1>
                        <p style="color: var(--text-muted)">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            <?php if (!empty($current_user['assigned_class'])): ?>
                                    <span class="mx-2">|</span> <i class="fas fa-chalkboard me-1"></i> Kelas <?php echo htmlspecialchars($current_user['assigned_class']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="header-right" style="display: flex; gap: 0.5rem; align-items: center;">
                     <button onclick="openImportModal()" class="btn btn-secondary"><i class="fas fa-file-import me-1"></i> Import</button>
                     <button onclick="openExportModal()" class="btn btn-secondary"><i class="fas fa-file-export me-1"></i> Export</button>
                     <a href="index.php" class="btn btn-secondary back-btn"><i class="fas fa-arrow-left me-1"></i> <span class="btn-text">Kembali ke Jurnal</span></a>
                </div>
            </header>

            <div class="templates-layout">
                
                <!-- Left Column: Form -->
                <div>
                    <div class="card sticky-card" style="position: sticky; top: 20px;">
                        <h3 class="mb-2"><i class="fas fa-plus-circle"></i> <?php echo $editTemplate ? 'Edit Template' : 'Tambah Template Baru'; ?></h3>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="subject" class="form-control" required>
                                    <option value="">-- Pilih Mapel --</option>
                                    <?php foreach ($subjects as $subj): ?>
                                            <option value="<?php echo htmlspecialchars($subj['name']); ?>" 
                                                <?php echo $editTemplate && $editTemplate['subject'] == $subj['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subj['name']); ?>
                                            </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Tipe Template</label>
                                <select name="type" id="type" class="form-control" required>
                                    <option value="">-- Pilih Tipe --</option>
                                    <option value="capaian_pembelajaran" <?php echo $editTemplate && $editTemplate['type'] == 'capaian_pembelajaran' ? 'selected' : ''; ?>>Capaian Pembelajaran</option>
                                    <option value="pokok_materi" <?php echo $editTemplate && $editTemplate['type'] == 'pokok_materi' ? 'selected' : ''; ?>>Pokok Materi</option>
                                    <option value="pencapaian" <?php echo $editTemplate && $editTemplate['type'] == 'pencapaian' ? 'selected' : ''; ?>>Tujuan Pembelajaran</option>
                                    <option value="permasalahan" <?php echo $editTemplate && $editTemplate['type'] == 'permasalahan' ? 'selected' : ''; ?>>Permasalahan</option>
                                    <option value="solusi" <?php echo $editTemplate && $editTemplate['type'] == 'solusi' ? 'selected' : ''; ?>>Solusi</option>
                                    <option value="catatan_pembelajaran" <?php echo $editTemplate && $editTemplate['type'] == 'catatan_pembelajaran' ? 'selected' : ''; ?>>Catatan Pembelajaran</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Isi Template</label>
                                
                                <?php if ($editTemplate): ?>
                                         <textarea name="content" class="form-control" rows="5" required><?php echo htmlspecialchars($editTemplate['content']); ?></textarea>
                                <?php else: ?>
                                        <!-- Options from existing templates (Copy feature) -->
                                        <div id="template-options" class="mb-2">
                                            <?php foreach ($allTemplatesGrouped as $templateType => $typeTemplates): ?>
                                                    <div class="template-type-section" id="type-<?php echo $templateType; ?>" style="display: none;">
                                                         <div class="collapsible-header" onclick="toggleCollapsible('<?php echo $templateType; ?>')">
                                                            <span class="toggle-text">Salin dari yang ada (<?php echo count($typeTemplates); ?>)</span>
                                                            <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                                                        </div>
                                                        <div id="collapsible-<?php echo $templateType; ?>" class="collapsible-content">
                                                            <?php foreach ($typeTemplates as $template): ?>
                                                                    <label class="template-item" data-subject="<?php echo htmlspecialchars($template['subject']); ?>" style="display: flex; gap: 8px; margin-bottom: 6px; align-items: flex-start; cursor: pointer;">
                                                                        <input type="checkbox" name="template_checkbox[]" value="<?php echo $template['id']; ?>" style="margin-top: 4px;">
                                                                        <span style="font-size: 0.9em; color: var(--text-secondary);">
                                                                            <?php echo htmlspecialchars($template['content']); ?>
                                                                            <span style="font-size: 0.8em; color: var(--text-muted); display: block;">(<?php echo htmlspecialchars($template['subject']); ?>)</span>
                                                                        </span>
                                                                    </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                            <?php endforeach; ?>
                                        </div>
                                    
                                        <!-- Manual input -->
                                        <div id="manual-templates">
                                            <div class="manual-entry">
                                                <textarea name="manual_content[]" placeholder="Masukan isi template baru..." class="manual-textarea"></textarea>
                                            </div>
                                        </div>
                                        <button type="button" class="add-manual-btn" onclick="addManualEntry()">
                                            <i class="fas fa-plus"></i> Tambah Field Input
                                        </button>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <?php if ($editTemplate): ?>
                                        <input type="hidden" name="id" value="<?php echo $editTemplate['id']; ?>" />
                                        <a href="templates.php" class="btn btn-secondary" style="width: auto;">Batal</a>
                                        <button type="submit" name="update_template" class="btn btn-success" style="width: auto;"><i class="fas fa-save"></i> Update</button>
                                <?php else: ?>
                                        <button type="submit" name="add_template" class="btn btn-success" style="width: auto;"><i class="fas fa-save"></i> Simpan Semua</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column: List -->
                <div>
                     <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-muted);">Daftar Template</h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-secondary" onclick="expandAllSubjects()"><i class="fas fa-expand"></i></button>
                            <button class="btn btn-sm btn-secondary" onclick="collapseAllSubjects()"><i class="fas fa-compress"></i></button>
                        </div>
                    </div>

                    <?php if (empty($templates)): ?>
                            <div class="card" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>Belum ada template. Silahkan tambah baru atau Import.</p>
                            </div>
                    <?php else: ?>
                            <form id="bulkActionForm" method="POST">
                                <input type="hidden" name="bulk_delete" value="1">
                                <?php foreach ($templates as $subject => $subjectTemplates): ?>
                                        <div class="subject-section">
                                            <div class="subject-header" onclick="toggleSubject(this)">
                                                <span><i class="fas fa-book" style="margin-right: 8px; color: var(--primary);"></i> <?php echo htmlspecialchars($subject); ?></span>
                                                <i class="fas fa-chevron-right toggle-icon"></i>
                                            </div>
                                            <div class="subject-body">
                                                <?php
                                                $order = ['capaian_pembelajaran', 'pokok_materi', 'pencapaian', 'permasalahan', 'solusi', 'catatan_pembelajaran'];
                                                foreach ($order as $type):
                                                    if (isset($subjectTemplates[$type])):
                                                        ?>
                                                            <div class="type-group">
                                                                <div class="type-header" onclick="toggleType(this)">
                                                                    <h5 style="margin: 0; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                                                                        <span>
                                                                            <?php echo getLabel($type); ?>
                                                                            <span style="font-size: 0.8em; color: var(--text-muted); font-weight: normal; margin-left: 5px;">(<?php echo count($subjectTemplates[$type]); ?>)</span>
                                                                        </span>
                                                                        <i class="fas fa-chevron-right type-toggle-icon" style="font-size: 0.8em;"></i>
                                                                    </h5>
                                                                </div>
                                                                <div class="type-body" style="display: none; margin-top: 0.5rem;">
                                                                    <?php foreach ($subjectTemplates[$type] as $template): ?>
                                                                            <div class="template-card" data-id="<?php echo $template['id']; ?>">
                                                                                <div style="display:flex; align-items:center; flex:1;">
                                                                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $template['id']; ?>" class="select-checkbox" onchange="updateBulkAction()">
                                                                                    <div style="margin-right: 1rem; font-size: 0.95rem; color: var(--text-secondary);" class="template-content-text">
                                                                                        <?php echo nl2br(htmlspecialchars($template['content'])); ?>
                                                                                    </div>
                                                                                </div>
                                                                                <div style="display: flex; gap: 0.25rem;">
                                                                                    <a href="?edit=<?php echo $template['id']; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-pencil-alt"></i></a>
                                                                                    <button type="button" class="btn btn-sm btn-danger" onclick="openDeleteModal(<?php echo $template['id']; ?>)"><i class="fas fa-trash"></i></button>
                                                                                </div>
                                                                            </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                    <?php endif; endforeach; ?>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </form>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
    
    <!-- Bulk Action Bar -->
    <div id="bulkActionsBar" class="bulk-actions-bar">
        <span class="bulk-count">0 items selected</span>
        <button type="button" class="btn btn-danger" onclick="openBulkDeleteModal()">
            <i class="fas fa-trash"></i> Hapus Terpilih
        </button>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Import Template</h3>
                <span class="close" onclick="closeModal('importModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="mb-3 text-sm text-muted">
                        Unggah file CSV berisi template. 
                        <a href="?download_sample=1&type=auto" id="downloadSampleLink" style="color: var(--primary); text-decoration: underline;">Unduh Sample CSV (Master)</a>
                    </p>
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Tujuan Mata Pelajaran</label>
                        <select name="subject_import" class="form-control" required>
                            <option value="">-- Pilih Mapel --</option>
                            <?php foreach ($subjects as $subj): ?>
                                <option value="<?php echo htmlspecialchars($subj['name']); ?>"><?php echo htmlspecialchars($subj['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-3">
                         <label class="form-label">Masuk ke Komponen</label>
                         <select name="target_type" id="targetTypeSelect" class="form-control" required onchange="updateDownloadLink()">
                            <option value="auto">Deteksi Otomatis (Dari kolom CSV)</option>
                            <option value="" disabled>--- ATAU PILIH MANUAL ---</option>
                            <option value="capaian_pembelajaran">Capaian Pembelajaran</option>
                            <option value="pencapaian">Tujuan Pembelajaran</option>
                            <option value="pokok_materi">Pokok Materi</option>
                            <option value="permasalahan">Permasalahan</option>
                            <option value="solusi">Solusi</option>
                            <option value="catatan_pembelajaran">Catatan Pembelajaran</option>
                         </select>
                         <div class="form-text" style="font-size: 0.8rem; color: #64748b;">
                             * Jika memilih manual, CSV bisa hanya berisi 1 kolom (Isi Template).
                         </div>
                    </div>
                    
                    <script>
                        function updateDownloadLink() {
                            const select = document.getElementById('targetTypeSelect');
                            const link = document.getElementById('downloadSampleLink');
                            const type = select.value;
                            
                            if (type === 'auto') {
                                link.href = '?download_sample=1&type=auto';
                                link.textContent = 'Unduh Sample CSV (Master)';
                            } else {
                                link.href = '?download_sample=1&type=' + type;
                                // Get text of selected option
                                const text = select.options[select.selectedIndex].text;
                                link.textContent = 'Unduh Template Khusus: ' + text;
                            }
                        }
                    </script>

                    <div class="form-group">
                        <label class="form-label">File CSV</label>
                        <input type="file" name="import_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Batal</button>
                    <button type="submit" name="import_template" class="btn btn-success">Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Export Template</h3>
                <span class="close" onclick="closeModal('exportModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Pilih Mata Pelajaran untuk di-Export</label>
                        <select name="subject_export" class="form-control">
                            <option value="all">Semua Mata Pelajaran</option>
                            <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo htmlspecialchars($subj['name']); ?>"><?php echo htmlspecialchars($subj['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('exportModal')">Batal</button>
                    <button type="submit" name="export_template" class="btn btn-success">Export CSV</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Single Delete Modal (Legacy) -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Template</h3>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus template ini? Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>
    
    <!-- Bulk Delete Confirmation Modal -->
    <div id="bulkDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Konfirmasi Hapus Banyak</h3>
                <span class="close" onclick="closeModal('bulkDeleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Anda akan menghapus <strong id="bulkDeleteCount">0</strong> template berikut:</p>
                <div id="bulkDeleteList" class="scrollable-list mt-2">
                    <!-- Items will be populated here -->
                </div>
                <p class="mt-3 text-danger"><small>Tindakan ini tidak dapat dibatalkan.</small></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('bulkDeleteModal')">Batal</button>
                <button type="button" onclick="submitBulkDelete()" class="btn btn-danger">Hapus Semua</button>
            </div>
        </div>
    </div>

    <!-- Toast + Scripts -->
    <script>
        // Modal Helpers
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function openImportModal() { openModal('importModal'); }
        function openExportModal() { openModal('exportModal'); }
        
        // Single Delete
        function openDeleteModal(id) {
            document.getElementById('confirmDeleteBtn').href = '?delete=' + id;
            openModal('deleteModal');
        }

        // Close when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Sidebar Toggle
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

        // Toggles
        function toggleSubject(header) {
            const body = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            if (body.classList.contains('expanded')) {
                body.classList.remove('expanded');
                icon.className = 'fas fa-chevron-right toggle-icon';
            } else {
                body.classList.add('expanded');
                icon.className = 'fas fa-chevron-down toggle-icon';
            }
        }
        function expandAllSubjects() {
            document.querySelectorAll('.subject-body').forEach(el => el.classList.add('expanded'));
            document.querySelectorAll('.subject-header .toggle-icon').forEach(el => el.className = 'fas fa-chevron-down toggle-icon');
        }
        function collapseAllSubjects() {
            document.querySelectorAll('.subject-body').forEach(el => el.classList.remove('expanded'));
            document.querySelectorAll('.subject-header .toggle-icon').forEach(el => el.className = 'fas fa-chevron-right toggle-icon');
        }
        function toggleCollapsible(type) {
            const content = document.getElementById(`collapsible-${type}`);
            const icon = document.querySelector(`.collapsible-header[onclick="toggleCollapsible('${type}')"] .toggle-icon i`);
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                icon.className = 'fas fa-chevron-right';
            } else {
                content.classList.add('expanded');
                icon.className = 'fas fa-chevron-down';
            }
        }
        function toggleType(header) {
            const body = header.nextElementSibling;
            const icon = header.querySelector('.type-toggle-icon');
            if (body.style.display === 'none' || body.style.display === '') {
                body.style.display = 'block';
                icon.className = 'fas fa-chevron-down type-toggle-icon';
            } else {
                body.style.display = 'none';
                icon.className = 'fas fa-chevron-right type-toggle-icon';
            }
        }

        // Manual Entries
        function addManualEntry() {
            const container = document.getElementById('manual-templates');
            const newDiv = document.createElement('div');
            newDiv.className = 'manual-entry';
            newDiv.innerHTML = `
                <textarea name="manual_content[]" placeholder="Masukan isi template..." class="manual-textarea"></textarea>
                <button type="button" class="remove-manual-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(newDiv);
        }

        // Copy Filter
        function filterTemplates() {
            const subjectSelect = document.querySelector('select[name="subject"]');
            const typeSelect = document.getElementById('type');
            if(!typeSelect) return;
            
            const selectedSubject = subjectSelect ? subjectSelect.value : '';
            const selectedType = typeSelect.value;

            document.querySelectorAll('.template-type-section').forEach(section => {
                section.style.display = 'none';
            });

            if (selectedType) {
                const targetSection = document.getElementById('type-' + selectedType);
                if (targetSection) {
                    targetSection.style.display = 'block';
                    const items = targetSection.querySelectorAll('.template-item');
                    let visibleCount = 0;
                    items.forEach(item => {
                        const itemSubject = item.getAttribute('data-subject');
                        if (!selectedSubject || itemSubject === selectedSubject) {
                            item.style.display = 'flex';
                            visibleCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    const countSpan = targetSection.querySelector('.toggle-text');
                    if(countSpan) countSpan.textContent = `Salin dari yang ada (${visibleCount})`;
                    
                    const content = targetSection.querySelector('.collapsible-content');
                    const icon = targetSection.querySelector('.toggle-icon i');
                    if (visibleCount > 0) {
                        content.classList.add('expanded');
                        icon.className = 'fas fa-chevron-down';
                    } else {
                        content.classList.remove('expanded');
                        icon.className = 'fas fa-chevron-right';
                    }
                }
            }
        }
        const typeSelect = document.getElementById('type');
        const subjectSelect = document.querySelector('select[name="subject"]');
        if(typeSelect) typeSelect.addEventListener('change', filterTemplates);
        if(subjectSelect) subjectSelect.addEventListener('change', filterTemplates);
        if(typeSelect || subjectSelect) filterTemplates();

        // --- BULK ACTION LOGIC ---
        function updateBulkAction() {
            const checkboxes = document.querySelectorAll('.select-checkbox:checked');
            const bar = document.getElementById('bulkActionsBar');
            const countSpan = bar.querySelector('.bulk-count');
            
            if (checkboxes.length > 0) {
                bar.classList.add('visible');
                countSpan.textContent = checkboxes.length + ' item dipilih';
            } else {
                bar.classList.remove('visible');
            }
        }
        
        function openBulkDeleteModal() {
            const checkboxes = document.querySelectorAll('.select-checkbox:checked');
            const listContainer = document.getElementById('bulkDeleteList');
            const countSpan = document.getElementById('bulkDeleteCount');
            
            listContainer.innerHTML = '';
            countSpan.textContent = checkboxes.length;
            
            checkboxes.forEach(cb => {
                // Find parent card to get text
                const card = cb.closest('.template-card');
                const contentDiv = card.querySelector('.template-content-text');
                const text = contentDiv.innerText.substring(0, 100) + (contentDiv.innerText.length > 100 ? '...' : '');
                
                const itemDiv = document.createElement('div');
                itemDiv.className = 'scroll-item';
                itemDiv.textContent = text;
                listContainer.appendChild(itemDiv);
            });
            
            openModal('bulkDeleteModal');
        }
        
        function submitBulkDelete() {
            document.getElementById('bulkActionForm').submit();
        }

        // Toast
        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        if (success) {
            const toast = document.createElement('div');
            toast.className = 'toast show';
            if(success === 'bulk_delete') toast.innerHTML = '<i class="fas fa-check-circle"></i> Item terpilih berhasil dihapus!';
            else if(success === 'import') toast.innerHTML = '<i class="fas fa-check-circle"></i> Import berhasil!';
            else toast.innerHTML = '<i class="fas fa-check-circle"></i> Berhasil disimpan!';
            
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>
</body>
</html>
