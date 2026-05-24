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

// Get User Profile for Sidebar and Journal Logic
$stmt_user = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$current_user = $stmt_user->fetch();
$currentUserClass = $current_user['assigned_class'] ?? '';

// Populate session for DB helpers if needed (or pass explicitly)
$_SESSION['assigned_class'] = $currentUserClass;

// --- EXISTING JOURNAL LOGIC START ---

// Helper function to combine checkbox values and manual input
function combineInput($checkboxes, $manual)
{
    $values = [];
    if (is_array($checkboxes)) {
        foreach ($checkboxes as $val) {
            $trimmed = trim($val);
            if ($trimmed !== '') {
                $values[] = $trimmed;
            }
        }
    }
    if (is_array($manual)) {
        foreach ($manual as $val) {
            $trimmed = trim($val);
            if ($trimmed !== '') {
                $values[] = $trimmed;
            }
        }
    } elseif (trim($manual) !== '') {
        $values[] = trim($manual);
    }
    return implode(";\n", $values);
}

// Inisialisasi variabel untuk menghindari error
$selectedSubject = isset($_POST['selected_subject']) ? $_POST['selected_subject'] : '';

// Handle form submission for adding new entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
    // Validasi dan sanitasi input
    $data = [
        'entry_date' => filter_var($_POST['entry_date'], FILTER_SANITIZE_STRING),
        'hour' => filter_var($_POST['hour'], FILTER_SANITIZE_NUMBER_INT),
        'subject' => filter_var($_POST['subject'], FILTER_SANITIZE_STRING),
        'capaian_pembelajaran' => combineInput($_POST['capaian_pembelajaran_checkbox'] ?? [], $_POST['capaian_pembelajaran'] ?? []),
        'pokok_materi' => combineInput($_POST['pokok_materi_checkbox'] ?? [], $_POST['pokok_materi'] ?? []),
        'pencapaian' => combineInput($_POST['pencapaian_checkbox'] ?? [], $_POST['pencapaian'] ?? []),
        'permasalahan' => combineInput($_POST['permasalahan_checkbox'] ?? [], $_POST['permasalahan'] ?? []),
        'solusi' => combineInput($_POST['solusi_checkbox'] ?? [], $_POST['solusi'] ?? []),
        'catatan_pembelajaran' => combineInput($_POST['catatan_pembelajaran_checkbox'] ?? [], $_POST['catatan_pembelajaran'] ?? []),
        'jumlah_jp' => filter_var($_POST['jumlah_jp'], FILTER_SANITIZE_NUMBER_INT),
        'class_name' => $currentUserClass // Add class name to data
    ];

    if (insertEntry($data)) {
        $redirectUrl = 'index.php?success=add';
        if (isset($_GET['week_start'])) {
            $redirectUrl .= '&week_start=' . urlencode($_GET['week_start']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = "Gagal menambahkan entri";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = filter_var($_GET['delete'], FILTER_SANITIZE_NUMBER_INT);
    if (deleteEntry($id)) {
        $redirectUrl = 'index.php?success=delete';
        if (isset($_GET['week_start'])) {
            $redirectUrl .= '&week_start=' . urlencode($_GET['week_start']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = "Gagal menghapus entri";
    }
}

// Handle edit
$editEntry = null;
if (isset($_GET['edit'])) {
    $id = filter_var($_GET['edit'], FILTER_SANITIZE_NUMBER_INT);
    $editEntry = getEntryById($id);
    if ($editEntry) {
        $selectedSubject = $editEntry['subject'];
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_entry'])) {
    $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
    $data = [
        'entry_date' => filter_var($_POST['entry_date'], FILTER_SANITIZE_STRING),
        'hour' => filter_var($_POST['hour'], FILTER_SANITIZE_NUMBER_INT),
        'subject' => filter_var($_POST['subject'], FILTER_SANITIZE_STRING),
        'capaian_pembelajaran' => combineInput($_POST['capaian_pembelajaran_checkbox'] ?? [], $_POST['capaian_pembelajaran'] ?? []),
        'pokok_materi' => combineInput($_POST['pokok_materi_checkbox'] ?? [], $_POST['pokok_materi'] ?? []),
        'pencapaian' => combineInput($_POST['pencapaian_checkbox'] ?? [], $_POST['pencapaian'] ?? []),
        'permasalahan' => combineInput($_POST['permasalahan_checkbox'] ?? [], $_POST['permasalahan'] ?? []),
        'solusi' => combineInput($_POST['solusi_checkbox'] ?? [], $_POST['solusi'] ?? []),
        'catatan_pembelajaran' => combineInput($_POST['catatan_pembelajaran_checkbox'] ?? [], $_POST['catatan_pembelajaran'] ?? []),
        'jumlah_jp' => filter_var($_POST['jumlah_jp'], FILTER_SANITIZE_NUMBER_INT),
        'class_name' => $currentUserClass // Keep or update class? Probably keep or specific logic. For now assumes editing retains/updates to current.
    ];

    if (updateEntry($id, $data)) {
        $redirectUrl = 'index.php?success=update';
        if (isset($_GET['week_start'])) {
            $redirectUrl .= '&week_start=' . urlencode($_GET['week_start']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = "Gagal mengupdate entri";
    }
}

// Handle schedule add
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $data = [
        'day' => filter_var($_POST['day'], FILTER_SANITIZE_NUMBER_INT),
        'hour' => filter_var($_POST['hour'], FILTER_SANITIZE_NUMBER_INT),
        'jumlah_jp' => filter_var($_POST['jumlah_jp'], FILTER_SANITIZE_NUMBER_INT),
        'subject' => filter_var($_POST['subject'], FILTER_SANITIZE_STRING),
        'class_name' => $currentUserClass // Add class name
    ];

    if (insertSchedule($data)) {
        $redirectUrl = 'index.php?schedule_success=add';
        if (isset($_GET['week_start'])) {
            $redirectUrl .= '&week_start=' . urlencode($_GET['week_start']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = "Gagal menambahkan jadwal";
    }
}

// Handle schedule delete
if (isset($_GET['delete_schedule'])) {
    $id = filter_var($_GET['delete_schedule'], FILTER_SANITIZE_NUMBER_INT);
    if (deleteSchedule($id)) {
        $redirectUrl = 'index.php?schedule_success=delete';
        if (isset($_GET['week_start'])) {
            $redirectUrl .= '&week_start=' . urlencode($_GET['week_start']);
        }
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        $error = "Gagal menghapus jadwal";
    }
}

// Get entries for selected week (Monday to Saturday) - FILTERED BY CLASS
$weekStart = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +5 days')); // Saturday

$entries = getEntriesForWeek($weekStart, $weekEnd, $currentUserClass); // Pass Class
$recentEntries = $entries; // All entries for the week

// Get subjects from DB
$subjectsData = getAllSubjects();
$allSubjects = array_column($subjectsData, 'name');

// Get schedules from DB - FIltered by Class
$schedules = getAllSchedules($currentUserClass);

// Filter subjects based on selected date's day of week
$entryDate = isset($_POST['entry_date']) ? $_POST['entry_date'] : ($editEntry ? $editEntry['entry_date'] : date('Y-m-d'));
$dayOfWeek = date('N', strtotime($entryDate)); // 1=Monday to 7=Sunday
$scheduledSubjects = array_unique(array_column(array_filter($schedules, fn($s) => $s['day'] == $dayOfWeek), 'subject'));
$subjects = !empty($scheduledSubjects) ? array_intersect($allSubjects, $scheduledSubjects) : [];
$currentDate = $entryDate;

// Jika sedang edit, set subject yang dipilih
if (isset($editEntry) && $editEntry) {
    $selectedSubject = $editEntry['subject'];
}

// --- EXISTING JOURNAL LOGIC END ---
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Pembelajaran</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
</head>

<body class="dashboard-page">
    <div class="dashboard-layout">
        <?php include '../laporan_harian/layout/user_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div>
                        <h1>Journal Pembelajaran</h1>
                        <p style="color: var(--text-muted)">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            <?php if (!empty($current_user['assigned_class'])): ?>
                                <span class="mx-2">|</span> <i class="fas fa-chalkboard me-1"></i> Kelas
                                <?php echo htmlspecialchars($current_user['assigned_class']); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <!-- Action Buttons: Template, Print, Schedule -->
                <div class="header-actions">
                    <a href="templates.php" class="btn btn-secondary action-btn" title="Template">
                        <i class="fas fa-clipboard-list"></i> <span class="action-text">Template</span>
                    </a>
                    <a href="print.php" class="btn btn-secondary action-btn" title="Cetak">
                        <i class="fas fa-print"></i> <span class="action-text">Cetak</span>
                    </a>
                    <button onclick="openScheduleModal()" class="btn btn-primary action-btn" title="Jadwal">
                        <i class="fas fa-calendar-alt"></i> <span class="action-text">Jadwal</span>
                    </button>
                </div>

                <style>
                    .header-left {
                        display: flex;
                        align-items: center;
                        gap: 1rem;
                    }

                    .header-actions {
                        display: flex;
                        gap: 0.5rem;
                        flex-wrap: wrap;
                    }

                    .action-btn {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        white-space: nowrap;
                        width: auto;
                        padding: 0.75rem 1.5rem;
                        font-weight: 600;
                        transition: all 0.2s;
                        height: 38px;
                    }

                    /* Desktop: explicit widths if needed */
                    @media (min-width: 769px) {
                        .action-btn {
                            min-width: 120px;
                        }
                    }

                    @media (max-width: 768px) {
                        .header {
                            position: relative;
                            flex-direction: row;
                            align-items: center;
                            padding-right: 0;
                            padding-bottom: 1.5rem;
                        }

                        .header-actions {
                            position: absolute;
                            top: 0;
                            right: 0;
                            display: flex;
                            gap: 0.5rem;
                            width: auto;
                            grid-template-columns: none;
                        }

                        .action-btn {
                            width: 36px;
                            height: 36px;
                            padding: 0;
                            border-radius: 50%;
                            background: white;
                            box-shadow: var(--shadow-sm);
                            color: var(--text-main);
                            border: 1px solid var(--border);
                            flex-direction: row;
                        }

                        .action-btn.btn-primary {
                            background: var(--primary);
                            color: white;
                            border: none;
                        }

                        .action-text {
                            display: none;
                        }

                        .action-btn i {
                            margin-right: 0 !important;
                            font-size: 1rem;
                        }
                    }
                </style>
            </header>

            <?php if (isset($error)): ?>
                <div class="toast error show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <span class="close-btn" onclick="this.parentElement.style.display='none'">&times;</span>
                </div>
            <?php endif; ?>

            <!-- Week Filter -->
            <div class="card mb-2 filter-card">
                <form method="GET" class="filter-form">
                    <div class="filter-inputs">
                        <label for="week_start" class="filter-label"><i class="fas fa-calendar-week me-1"></i> Pilih
                            Minggu:</label>
                        <input type="date" id="week_start" name="week_start"
                            value="<?php echo htmlspecialchars($weekStart); ?>" class="form-control date-input">
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="index.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <style>
                .filter-card {
                    padding: 1rem;
                }

                .filter-form {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    flex-wrap: wrap;
                }

                .filter-inputs {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .date-input {
                    width: auto;
                }

                .filter-buttons {
                    display: flex;
                    gap: 0.5rem;
                }

                /* Use existing action-btn class but override specific behaviors if needed */
                .filter-buttons .btn {
                    width: auto;
                }

                @media (max-width: 768px) {
                    .filter-form {
                        flex-direction: column;
                        align-items: stretch;
                        gap: 0.75rem;
                    }

                    .filter-inputs {
                        flex-direction: column;
                        align-items: flex-start;
                        width: 100%;
                        gap: 0.25rem;
                    }

                    .date-input {
                        width: 100%;
                    }

                    /* Buttons side-by-side on one row */
                    .filter-buttons {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        width: 100%;
                    }

                    .filter-buttons .btn {
                        width: 100%;
                        justify-content: center;
                        border-radius: 0.5rem;
                        /* Make it "kotak" (box) with slight radius, not circle/pill */
                        height: auto;
                        /* Reset any fixed height */
                        padding: 0.6rem;
                    }
                }
            </style>

            <div class="journal-grid">
                <!-- Form Section -->
                <div class="form-section-container">
                    <div class="card">
                        <h3 class="mb-2"><i class="fas fa-plus-circle"
                                style="margin-right: 8px; color: var(--primary);"></i>
                            <?php echo $editEntry ? 'Edit Entri' : 'Entri Jurnal'; ?></h3>

                        <form method="POST" action="index.php" onsubmit="return validateForm()">
                            <input type="hidden" name="selected_subject" id="selected_subject"
                                value="<?php echo htmlspecialchars($selectedSubject); ?>">

                            <div class="entry-form-grid">
                                <div class="form-group">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" id="entry_date" name="entry_date" class="form-control" required
                                        value="<?php echo $currentDate; ?>" onchange="this.form.submit();">
                                </div>

                                <div class="form-group time-inputs-group">
                                    <div>
                                        <label class="form-label">Jam Ke</label>
                                        <select id="hour" name="hour" class="form-control" required>
                                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['hour']) && $_POST['hour'] == $i) ? 'selected' : (($editEntry && $editEntry['hour'] == $i) ? 'selected' : ''); ?>>Jam <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Jumlah JP</label>
                                        <select id="jumlah_jp" name="jumlah_jp" class="form-control" required>
                                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo (isset($_POST['jumlah_jp']) && $_POST['jumlah_jp'] == $i) ? 'selected' : (($editEntry && $editEntry['jumlah_jp'] == $i) ? 'selected' : ''); ?>>JP <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group subject-group">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select id="subject" name="subject" class="form-control" required>
                                        <option value="">-- Pilih --</option>
                                        <?php foreach ($subjects as $subj): ?>
                                            <?php $isSelected = (isset($_POST['subject']) && $_POST['subject'] == $subj) || ($selectedSubject == $subj) || (!$selectedSubject && $subj === $subjects[0]); ?>
                                            <option value="<?php echo htmlspecialchars($subj); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subj); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <script>
                                // Pass PHP schedules to JS
                                const schedules = <?php echo json_encode($schedules); ?>;

                                function autofillSchedule() {
                                    const subjectSelect = document.getElementById('subject');
                                    const dateInput = document.getElementById('entry_date');
                                    const hourSelect = document.getElementById('hour');
                                    const jpSelect = document.getElementById('jumlah_jp');

                                    const selectedSubject = subjectSelect.value;
                                    const selectedDate = new Date(dateInput.value);
                                    const dayOfWeek = selectedDate.getDay() || 7; // JS 0=Sun, PHP 7=Sun
                                    const jsDay = selectedDate.getDay();
                                    const phpDay = jsDay === 0 ? 7 : jsDay;

                                    if (selectedSubject) {
                                        // Find matching schedule
                                        const match = schedules.find(s => s.subject === selectedSubject && s.day == phpDay);

                                        if (match) {
                                            if (hourSelect) hourSelect.value = match.hour;
                                            if (jpSelect) jpSelect.value = match.jumlah_jp;
                                        }
                                    }
                                }

                                function loadTemplates(subject) {
                                    if (!subject) return;

                                    fetch('get_templates.php?subject=' + encodeURIComponent(subject))
                                        .then(response => response.json())
                                        .then(data => {
                                            for (const [field, options] of Object.entries(data)) {
                                                const container = document.getElementById('template-container-' + field);
                                                if (!container) continue;

                                                if (options.length === 0) {
                                                    container.innerHTML = '';
                                                    continue;
                                                }

                                                let html = `
                                                    <div class="collapsible-header" onclick="toggleCollapsible('${field}')">
                                                        <span class="toggle-text">Template (${options.length} opsi)</span>
                                                        <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                                                    </div>
                                                    <div id="collapsible-${field}" class="collapsible-content">
                                                `;

                                                options.forEach(option => {
                                                    const val = option.replace(/"/g, '&quot;');
                                                    html += `
                                                        <label style="font-size: 0.9rem; margin-bottom: 0.5rem; display: flex; gap: 0.5rem;">
                                                            <input type="checkbox" name="${field}_checkbox[]" value="${val}">
                                                            ${option}
                                                        </label>
                                                    `;
                                                });

                                                html += `</div>`;
                                                container.innerHTML = html;
                                            }
                                        })
                                        .catch(err => console.error('Error fetching templates:', err));
                                }

                                // Attach listeners
                                const subjEl = document.getElementById('subject');
                                if (subjEl) subjEl.addEventListener('change', function() {
                                    // Update hidden input if needed
                                    document.getElementById('selected_subject').value = this.value;
                                    // Run autofill
                                    autofillSchedule();
                                    // Run template load
                                    loadTemplates(this.value);
                                });

                                // Also run on date change maybe? 
                                // original logic: onchange="this.form.submit();"
                            </script>


                            <style>
                                .entry-form-grid {
                                    display: grid;
                                    grid-template-columns: 1fr 1fr;
                                    gap: 1rem;
                                    margin-bottom: 1rem;
                                }

                                .time-inputs-group {
                                    display: flex;
                                    gap: 1rem;
                                }

                                .time-inputs-group>div {
                                    flex: 1;
                                }

                                .subject-group {
                                    grid-column: 1 / -1;
                                }

                                @media (max-width: 768px) {
                                    .entry-form-grid {
                                        grid-template-columns: 1fr;
                                    }

                                    /* Ensure labels don't wrap awkwardly in small columns */
                                    .time-inputs-group label {
                                        white-space: nowrap;
                                        font-size: 0.85rem;
                                    }
                                }

                                /* Manual Entry CSS */
                                .manual-entry {
                                    display: flex;
                                    gap: 0.5rem;
                                    margin-bottom: 0.5rem;
                                    align-items: center;
                                    /* Center items vertically */
                                }

                                .manual-textarea {
                                    flex: 1;
                                    /* Take remaining width */
                                    /* width property removed to rely on flex */
                                    min-height: 40px;
                                    padding: 0.5rem 0.75rem;
                                    border: 1px solid var(--border-color, #e2e8f0);
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
                                    /* Rigid 40px width, don't grow or shrink */
                                    width: 40px;
                                    height: 40px;
                                    /* Fixed height to match width */
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
                            </style>

                            <?php
                            $fields = [
                                'capaian_pembelajaran' => 'Capaian Pembelajaran',
                                'pokok_materi' => 'Pokok Materi',
                                'pencapaian' => 'Tujuan Pembelajaran',
                                'permasalahan' => 'Permasalahan',
                                'solusi' => 'Solusi',
                                'catatan_pembelajaran' => 'Catatan Pembelajaran'
                            ];

                            foreach ($fields as $field => $label):
                                $options = [];
                                if ($selectedSubject) {
                                    $templates = getTemplatesBySubjectAndType($selectedSubject, $field);
                                    foreach ($templates as $template) {
                                        $options[] = $template['content'];
                                    }
                                }
                                $existingValues = [];
                                if ($editEntry && !empty($editEntry[$field])) {
                                    $existingValues = array_map('trim', explode(";\n", $editEntry[$field]));
                                }
                                ?>
                                <div class="form-group">
                                    <label class="form-label"><?php echo $label; ?></label>
                                    <div>
                                        <div id="template-container-<?php echo $field; ?>">
                                            <?php if (!empty($options)): ?>
                                                <div class="collapsible-header"
                                                    onclick="toggleCollapsible('<?php echo $field; ?>')">
                                                    <span class="toggle-text">Template (<?php echo count($options); ?> opsi)</span>
                                                    <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>
                                                </div>
                                                <div id="collapsible-<?php echo $field; ?>" class="collapsible-content">
                                                    <?php foreach ($options as $option):
                                                        $isChecked = in_array($option, $existingValues);
                                                        ?>
                                                        <label
                                                            style="font-size: 0.9rem; margin-bottom: 0.5rem; display: flex; gap: 0.5rem;">
                                                            <input type="checkbox" name="<?php echo $field; ?>_checkbox[]"
                                                                value="<?php echo htmlspecialchars($option); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                            <?php echo htmlspecialchars($option); ?>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php
                                        $manualValues = [];
                                        if ($editEntry && !empty($editEntry[$field])) {
                                            $allValues = array_map('trim', explode(";\n", $editEntry[$field]));
                                            $manualValues = array_diff($allValues, $options);
                                        }
                                        if (empty($manualValues)) {
                                            $manualValues = [''];
                                        }
                                        ?>

                                        <div id="manual-<?php echo $field; ?>" class="manual-entries">
                                            <?php foreach ($manualValues as $index => $manualValue): ?>
                                                <div class="manual-entry">
                                                    <textarea name="<?php echo $field; ?>[]" placeholder="Masukan manual..."
                                                        class="manual-textarea"><?php echo htmlspecialchars($manualValue); ?></textarea>
                                                    <?php if ($index > 0): ?>
                                                        <button type="button" class="remove-manual-btn"
                                                            onclick="removeManualEntry('<?php echo $field; ?>', this)"><i
                                                                class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="add-manual-btn"
                                            onclick="addManualEntry('<?php echo $field; ?>')"><i class="fas fa-plus"></i>
                                            Tambah</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="form-actions">
                                <?php if ($editEntry): ?>
                                    <input type="hidden" name="id" value="<?php echo $editEntry['id']; ?>">
                                    <a href="index.php<?php echo isset($_GET['week_start']) ? '?week_start=' . urlencode($_GET['week_start']) : ''; ?>"
                                        class="btn btn-secondary" style="width: auto;">Batal</a>
                                    <button type="submit" name="update_entry" class="btn btn-success"
                                        style="width: auto;">Simpan Perubahan</button>
                                <?php else: ?>
                                    <button type="submit" name="add_entry" class="btn btn-success"
                                        style="width: auto;">Simpan Jurnal</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <style>
                    .form-actions {
                        margin-top: 2rem;
                        display: flex;
                        justify-content: flex-end;
                        gap: 1rem;
                    }

                    .btn-success {
                        background-color: #10b981;
                        /* Success Green */
                        color: white !important;
                        border: none;
                        transition: all 0.2s;
                    }

                    .btn-success:hover {
                        background-color: #059669;
                        transform: translateY(-1px);
                        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                    }

                    @media (max-width: 768px) {
                        .form-actions {
                            /* Ensure right alignment on mobile */
                            flex-direction: row;
                            justify-content: flex-end;
                            width: 100%;
                        }

                        .form-actions .btn {
                            width: auto !important;
                            /* Prevent full width stretching */
                            padding-left: 1.5rem;
                            padding-right: 1.5rem;
                        }
                    }
                </style>

                <!-- List Section -->
                <div class="list-section-container">
                    <div class="card">
                        <h3 class="mb-2"><i class="fas fa-history"
                                style="margin-right: 8px; color: var(--secondary);"></i> Riwayat Minggu Ini</h3>
                        <p class="mb-2" style="font-size: 0.875rem; color: var(--text-muted);">
                            <?php echo date('d M Y', strtotime($weekStart)); ?> -
                            <?php echo date('d M Y', strtotime($weekEnd)); ?>
                        </p>

                        <?php if (empty($recentEntries)): ?>
                            <div
                                style="text-align: center; padding: 2rem; color: var(--text-muted); border: 1px dashed var(--border); border-radius: var(--radius);">
                                Belum ada entri jurnal.
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($recentEntries as $entry): ?>
                                    <div
                                        style="border: 1px solid var(--border); border-radius: 0.5rem; padding: 1rem; background: var(--surface);">
                                        <div
                                            style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                            <div>
                                                <h4 style="margin: 0; color: var(--primary); font-size: 1rem;">
                                                    <?php echo htmlspecialchars($entry['subject']); ?>
                                                </h4>
                                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                    <?php echo date('D, d M', strtotime($entry['entry_date'])); ?> &bull; Jam Ke
                                                    <?php echo $entry['hour']; ?> &bull; JP
                                                    <?php echo htmlspecialchars($entry['jumlah_jp']); ?>
                                                </div>
                                            </div>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <a href="?edit=<?php echo $entry['id'];
                                                echo isset($_GET['week_start']) ? '&week_start=' . urlencode($_GET['week_start']) : ''; ?>"
                                                    style="color: var(--warning); padding: 4px;" title="Edit"><i
                                                        class="fas fa-edit"></i></a>
                                                <a href="#"
                                                    onclick="showDeleteModal(<?php echo $entry['id']; ?>, '<?php echo htmlspecialchars($entry['subject']); ?>', '<?php echo $entry['entry_date']; ?>')"
                                                    style="color: var(--danger); padding: 4px;" title="Hapus"><i
                                                        class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--text-main);">
                                            <strong>Pokok Materi:</strong><br>
                                            <?php
                                            // Show truncated materi
                                            $materi = htmlspecialchars($entry['pokok_materi']);
                                            echo (strlen($materi) > 100) ? substr($materi, 0, 100) . '...' : $materi;
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="modal">
        <div class="modal-content large-modal">
            <div class="modal-header">
                <h3>Kelola Jadwal</h3>
                <span class="close" onclick="closeScheduleModal()">&times;</span>
            </div>
            <div class="modal-body">
                <h4>Jadwal Saat Ini</h4>
                <div class="schedule-list">
                    <?php
                    $days = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];
                    foreach ($days as $num => $ind):
                        $daySchedules = array_filter($schedules, fn($s) => $s['day'] == $num);
                        ?>
                        <div class="day-schedule">
                            <h5><?php echo $ind; ?></h5>
                            <?php if (empty($daySchedules)): ?>
                                <p style="color: var(--text-muted); font-size: 0.875rem;">Tidak ada jadwal.</p>
                            <?php else: ?>
                                <ul>
                                    <?php foreach ($daySchedules as $sch): ?>
                                        <li>
                                            <span>Jam <?php echo $sch['hour']; ?> (JP <?php echo $sch['jumlah_jp']; ?>) -
                                                <strong><?php echo htmlspecialchars($sch['subject']); ?></strong></span>
                                            <a href="?delete_schedule=<?php echo $sch['id']; ?>" class="delete-small"
                                                onclick="return confirm('Hapus jadwal ini?')"><i class="fas fa-trash"></i></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h4 class="mt-2">Tambah Jadwal Baru</h4>
                <form method="POST" action="index.php">
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: end;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Hari</label>
                            <select name="day" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($days as $num => $ind): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $ind; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Jam Ke</label>
                            <select name="hour" class="form-control" required>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <option value="<?php echo $i; ?>">Jam <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">JP</label>
                            <select name="jumlah_jp" class="form-control" required>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>">JP <?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Mapel</label>
                            <select name="subject" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($allSubjects as $subj): ?>
                                    <option value="<?php echo htmlspecialchars($subj); ?>">
                                        <?php echo htmlspecialchars($subj); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <button type="submit" name="add_schedule" class="btn btn-primary"
                                style="width: 100%;">Tambah</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Konfirmasi Hapus</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus entri ini?</p>
                <div style="background: #fee2e2; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <p style="margin: 0;"><strong>Mapel:</strong> <span id="delete-subject"></span></p>
                    <p style="margin: 0;"><strong>Tanggal:</strong> <span id="delete-date"></span></p>
                </div>
                <p style="color: var(--danger); font-size: 0.875rem;"><i class="fas fa-exclamation-triangle"></i>
                    Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()" style="width: auto;">Batal</button>
                <a id="confirmDeleteBtn" href="#" class="btn btn-primary"
                    style="background: var(--danger); width: auto;">Hapus</a>
            </div>
        </div>
    </div>

    <script>
        // Sidebar & Layout Check
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
            const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
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

        // Journal Logic
        function addManualEntry(field) {
            const container = document.getElementById('manual-' + field);
            const entryDiv = document.createElement('div');
            entryDiv.className = 'manual-entry';
            entryDiv.innerHTML = `
                <textarea name="${field}[]" placeholder="Masukan manual..." class="manual-textarea"></textarea>
                <button type="button" class="remove-manual-btn" onclick="removeManualEntry('${field}', this)">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(entryDiv);
        }

        function removeManualEntry(field, button) {
            button.parentElement.remove();
        }

        function toggleCollapsible(field) {
            const header = document.querySelector(`.collapsible-header[onclick="toggleCollapsible('${field}')"]`);
            const content = document.getElementById(`collapsible-${field}`);
            const icon = header.querySelector('.toggle-icon i');

            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                header.classList.remove('expanded');
                icon.className = 'fas fa-chevron-right';
            } else {
                content.classList.add('expanded');
                header.classList.add('expanded');
                icon.className = 'fas fa-chevron-down';
            }
        }

        function showDeleteModal(entryId, subject, date) {
            document.getElementById('delete-subject').textContent = subject;
            document.getElementById('delete-date').textContent = date;
            let deleteUrl = '?delete=' + entryId;
            <?php if (isset($_GET['week_start'])): ?>
                deleteUrl += '&week_start=<?php echo urlencode($_GET['week_start']); ?>';
            <?php endif; ?>
            document.getElementById('confirmDeleteBtn').href = deleteUrl;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function openScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'block';
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('deleteModal')) {
                closeDeleteModal();
            }
            if (event.target == document.getElementById('scheduleModal')) {
                closeScheduleModal();
            }
        }

        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast' + (isError ? ' error' : '');
            toast.innerHTML = (isError ? '<i class="fas fa-exclamation-circle"></i> ' : '<i class="fas fa-check-circle"></i> ') + message + '<span class="close-btn" onclick="this.parentElement.remove()">&times;</span>';
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        const urlParams = new URLSearchParams(window.location.search);
        const success = urlParams.get('success');
        const scheduleSuccess = urlParams.get('schedule_success');

        if (success || scheduleSuccess) {
            let message = 'Operasi berhasil.';
            if (success) {
                switch (success) {
                    case 'add': message = 'Entri berhasil ditambahkan!'; break;
                    case 'update': message = 'Entri berhasil diperbarui!'; break;
                    case 'delete': message = 'Entri berhasil dihapus!'; break;
                }
            } else if (scheduleSuccess) {
                switch (scheduleSuccess) {
                    case 'add': message = 'Jadwal berhasil ditambahkan!'; break;
                    case 'delete': message = 'Jadwal berhasil dihapus!'; break;
                }
            }
            showToast(message);
            const newUrl = window.location.pathname + (urlParams.get('week_start') ? '?week_start=' + urlParams.get('week_start') : '');
            window.history.replaceState({}, document.title, newUrl);
        }

        function validateForm() {
            if (!document.getElementById('entry_date').value || !document.getElementById('hour').value || !document.getElementById('jumlah_jp').value || !document.getElementById('subject').value) {
                showToast('Harap lengkapi semua field utama.', true);
                return false;
            }
            // Check content fields
            const fields = ['capaian_pembelajaran', 'pokok_materi', 'pencapaian', 'permasalahan', 'solusi', 'catatan_pembelajaran'];
            for (let field of fields) {
                const checkboxes = document.querySelectorAll(`input[name="${field}_checkbox[]"]:checked`);
                const textareas = document.querySelectorAll(`textarea[name="${field}[]"]`);
                let filled = checkboxes.length > 0;
                if (!filled) {
                    for (let ta of textareas) {
                        if (ta.value.trim()) { filled = true; break; }
                    }
                }
                if (!filled) {
                    showToast(`Field ${field.replace(/_/g, ' ')} harus diisi.`, true);
                    return false;
                }
            }
            return true;
        }
    </script>
</body>

</html>