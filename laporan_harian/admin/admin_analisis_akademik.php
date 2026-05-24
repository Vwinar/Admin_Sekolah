<?php
session_start();
require_once '../config/db_connect.php';

// Ensure user is logged in as admin/kepala sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get filter parameters
$filter_class = $_GET['class'] ?? '';
$filter_semester = $_GET['semester'] ?? '';
$filter_subject = $_GET['subject'] ?? '';

// Fetch all classes for filter from Master Data
$all_classes = [];
$check_table_c = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='classes'");
if ($check_table_c->fetch()) {
    $stmt_c = $db->query("SELECT name FROM classes ORDER BY name ASC");
    $all_classes = $stmt_c->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Fallback to students table if classes table doesn't exist
    $classes_query = $db->query("SELECT DISTINCT class_name FROM students ORDER BY class_name");
    $all_classes = $classes_query->fetchAll(PDO::FETCH_COLUMN);
}

// Fetch Teachers Mapping (Class -> Teacher Name)
$class_teacher_map = [];
$stmt_t = $db->query("SELECT full_name, assigned_class FROM users WHERE role IN ('admin', 'guru') AND assigned_class IS NOT NULL AND assigned_class != ''");
while ($row_t = $stmt_t->fetch()) {
    $class_teacher_map[$row_t['assigned_class']] = $row_t['full_name'];
}

// Fetch all subjects for filter
$subjects_query = $db->query("SELECT DISTINCT name FROM subjects ORDER BY name");
$all_subjects = $subjects_query->fetchAll();

// Build query with filters
$query = "SELECT * FROM exam_analysis WHERE 1=1";
$params = [];

if ($filter_class) {
    $query .= " AND class_name = ?";
    $params[] = $filter_class;
}

if ($filter_semester) {
    $query .= " AND semester = ?";
    $params[] = $filter_semester;
}

if ($filter_subject) {
    $query .= " AND subject = ?";
    $params[] = $filter_subject;
}

$query .= " ORDER BY class_name, semester, subject";

$stmt = $db->prepare($query);
$stmt->execute($params);
$analysis_data = $stmt->fetchAll();

// Calculate statistics
$total_records = count($analysis_data);
$avg_achievement = 0;
$avg_absorption = 0;

if ($total_records > 0) {
    $sum_avg = 0;
    $sum_absorption = 0;

    foreach ($analysis_data as $record) {
        $data = json_decode($record['data_values'], true);
        $sum_avg += $data['avg'] ?? 0;
        $sum_absorption += $data['absorption'] ?? 0;
    }

    $avg_achievement = round($sum_avg / $total_records, 2);
    $avg_absorption = round($sum_absorption / $total_records, 2);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Akademik - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Desktop override */
        @media (min-width: 769px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .stat-card.secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.tertiary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .data-table th {
            background-color: #f8fafc;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background-color: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
        }

        .progress-fill.warning {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .progress-fill.danger {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
        }

        @media print {

            /* Reset layout */
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .dashboard-layout {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            /* Hide UI elements */
            .filter-section,
            .btn,
            .sidebar,
            .sidebar-toggle,
            .header,
            .section-header button,
            .section-header a {
                display: none !important;
            }

            /* Show print header */
            .print-header {
                display: block !important;
                margin-bottom: 2rem;
                text-align: center;
                border-bottom: 2px solid #000;
                padding-bottom: 1rem;
            }

            /* Table styling */
            .data-table {
                width: 100% !important;
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }

            .data-table th,
            .data-table td {
                border: 1px solid #000 !important;
                color: #000 !important;
                padding: 8px !important;
            }

            .data-table th {
                background-color: #f0f0f0 !important;
            }

            /* Enable background printing for progress bars */
            .progress-fill,
            .badge,
            .data-table th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            /* Ensure content fits */
            .stats-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 1rem !important;
                margin-bottom: 2rem !important;
                break-inside: avoid;
            }

            .stat-card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
                page-break-inside: avoid;
            }
        }

        /* Hide print header on screen */
        .print-header {
            display: none;
        }
        
        /* Desktop: Proper button sizing */
        @media (min-width: 769px) {
            .header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                flex-direction: row !important;
            }
            
            .header > div {
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
            /* Main content */
            .main-content {
                padding: 1rem !important;
            }
            
            /* Overall text smaller on mobile */
            body {
                font-size: 0.8rem !important;
            }

            /* Strict Horizontal Header for Mobile */
            .header {
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.5rem !important;
                margin-bottom: 1rem !important;
                width: 100% !important;
                padding: 0 !important;
                height: 4rem !important; /* Fixed height for consistency */
                visibility: visible !important;
                opacity: 1 !important;
                background: transparent !important;
            }

            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.6rem !important;
                flex: 1 !important;
                min-width: 0 !important;
                visibility: visible !important;
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
                color: var(--text-main) !important;
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
                margin-left: auto !important;
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

            /* Button responsiveness */
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

            /* Force 3 Columns for Statistics Cards on Mobile - Strict Grid */
            body .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
                grid-template-rows: 1fr !important;
                gap: 0.4rem !important;
                margin-bottom: 1.25rem !important;
                width: 100% !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }

            .stat-card {
                padding: 0.55rem 0.2rem !important;
                border-radius: 0.5rem !important;
                text-align: center !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                box-sizing: border-box !important;
                height: auto !important;
                min-height: 5.5rem !important;
                min-width: 0 !important;
                max-width: 100% !important;
            }

            .stat-card h3 {
                font-size: 0.55rem !important;
                margin-bottom: 0.25rem !important;
                line-height: 1.1 !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                gap: 0.2rem !important;
                width: 100% !important;
                text-transform: none !important;
                letter-spacing: 0 !important;
            }
            
            .stat-card h3 i {
                font-size: 0.85rem !important;
                margin-bottom: 0.15rem !important;
            }

            .stat-card .value {
                font-size: 1.1rem !important;
                font-weight: 800 !important;
                display: block !important;
                line-height: 1 !important;
                margin: 0 !important;
            }

            /* Filter section responsive */
            .filter-section {
                padding: 1rem !important;
            }

            .filter-grid {
                grid-template-columns: 1fr !important;
                gap: 0.8rem !important;
                margin-bottom: 1rem !important;
            }

            /* Filter buttons in one row */
            .filter-section > form > div:last-child {
                display: flex !important;
                flex-direction: row !important;
                gap: 0.5rem !important;
                width: 100% !important;
            }

            .filter-section .btn {
                flex: 1 !important;
                width: auto !important;
                min-width: 0 !important;
                padding: 0.5rem 0.3rem !important;
                font-size: 0.75rem !important;
                justify-content: center !important;
            }

            .filter-section .btn i {
                font-size: 0.9rem !important;
                margin-right: 0.3rem !important;
            }

            .filter-section .btn span {
                display: inline !important;
                font-size: 0.75rem !important;
            }

            /* Table scroll and smaller text */
            .data-table {
                font-size: 0.75rem !important;
            }
            
            .data-table th, .data-table td {
                padding: 0.6rem 0.4rem !important;
            }

            .badge {
                font-size: 0.65rem !important;
                padding: 0.15rem 0.4rem !important;
            }
            
            .section-header h2 {
                font-size: 1rem !important;
            }

            .filter-section h3 {
                font-size: 0.9rem !important;
                margin-bottom: 0.8rem !important;
            }

            .form-control {
                font-size: 0.8rem !important;
                padding: 0.5rem !important;
                height: 2.5rem !important;
            }

            label {
                font-size: 0.75rem !important;
                margin-bottom: 0.3rem !important;
            }
        }

        /* Extra small devices (phones under 360px) */
        @media (max-width: 360px) {
            .stat-card {
                padding: 0.5rem 0.1rem !important;
                min-height: 5rem !important;
            }

            .stat-card h3 {
                font-size: 0.5rem !important;
            }

            .stat-card h3 i {
                font-size: 0.7rem !important;
            }

            .stat-card .value {
                font-size: 0.95rem !important;
            }

            .filter-section .btn span {
                font-size: 0.7rem !important;
            }

            .filter-section .btn i {
                margin-right: 0.2rem !important;
                font-size: 0.8rem !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <!-- Print Header -->
            <div class="print-header">
                <h2>LAPORAN ANALISIS AKADEMIK</h2>
                <p>Dicetak pada: <?= date('d F Y, H:i') ?></p>
            </div>
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="header-title">
                        <h1>Analisis Akademik</h1>
                        <p>Rekap Analisis UTS/UAS</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="window.print()" class="btn btn-secondary" title="Cetak">
                        <i class="bi bi-printer"></i> <span>Cetak</span>
                    </button>
                    <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                        <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                    </a>
                </div>
            </header>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><i class="bi bi-bar-chart"></i> Total Data Analisis</h3>
                    <p class="value"><?= $total_records ?></p>
                </div>
                <div class="stat-card secondary">
                    <h3><i class="bi bi-graph-up"></i> Rata-rata Nilai</h3>
                    <p class="value"><?= $avg_achievement ?></p>
                </div>
                <div class="stat-card tertiary">
                    <h3><i class="bi bi-speedometer2"></i> Daya Serap Rata-rata</h3>
                    <p class="value"><?= $avg_absorption ?>%</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3 style="margin: 0 0 1rem 0; font-weight: 700;">
                    <i class="bi bi-funnel"></i> Filter Data
                </h3>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Kelas</label>
                            <select name="class" class="form-control">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($all_classes as $class_name): ?>
                                    <option value="<?= htmlspecialchars($class_name) ?>"
                                        <?= $filter_class === $class_name ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class_name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Semester</label>
                            <select name="semester" class="form-control">
                                <option value="">Semua Semester</option>
                                <option value="1" <?= $filter_semester === '1' ? 'selected' : '' ?>>Semester 1</option>
                                <option value="2" <?= $filter_semester === '2' ? 'selected' : '' ?>>Semester 2</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Mata
                                Pelajaran</label>
                            <select name="subject" class="form-control">
                                <option value="">Semua Mapel</option>
                                <?php foreach ($all_subjects as $subject): ?>
                                    <option value="<?= htmlspecialchars($subject['name']) ?>"
                                        <?= $filter_subject === $subject['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> <span>Terapkan Filter</span>
                        </button>
                        <a href="admin_analisis_akademik.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> <span>Reset</span>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Data Table -->
            <div class="card">
                <div class="section-header">
                    <h2><i class="bi bi-table"></i> Data Analisis Akademik</h2>
                </div>

                <?php if (empty($analysis_data)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>Tidak Ada Data</h3>
                        <p>Belum ada data analisis akademik yang tersedia dengan filter yang dipilih.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kelas</th>
                                    <th>Guru Kelas</th>
                                    <th>Semester</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Rata-rata Nilai</th>
                                    <th>Target (%)</th>
                                    <th>Daya Serap (%)</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysis_data as $index => $record):
                                    $data = json_decode($record['data_values'], true);
                                    $avg = $data['avg'] ?? 0;
                                    $target = $data['target'] ?? 0;
                                    $absorption = $data['absorption'] ?? 0;

                                    // Determine status based on absorption rate
                                    if ($absorption >= 80) {
                                        $status = 'Sangat Baik';
                                        $badge_class = 'badge-success';
                                        $progress_class = '';
                                    } elseif ($absorption >= 60) {
                                        $status = 'Baik';
                                        $badge_class = 'badge-warning';
                                        $progress_class = 'warning';
                                    } else {
                                        $status = 'Perlu Perbaikan';
                                        $badge_class = 'badge-danger';
                                        $progress_class = 'danger';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($record['class_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($class_teacher_map[$record['class_name']] ?? '-') ?></td>
                                        <td>Semester <?= $record['semester'] ?></td>
                                        <td><?= htmlspecialchars($record['subject']) ?></td>
                                        <td>
                                            <strong><?= $avg ?></strong>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?= $progress_class ?>"
                                                    style="width: <?= min($avg, 100) ?>%"></div>
                                            </div>
                                        </td>
                                        <td><?= $target ?>%</td>
                                        <td>
                                            <strong><?= $absorption ?>%</strong>
                                            <div class="progress-bar">
                                                <div class="progress-fill <?= $progress_class ?>"
                                                    style="width: <?= $absorption ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= $status ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

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

        toggleSidebar = () => {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');
            const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        }

        // Toggle sidebar on button click
        sidebarToggle.addEventListener('click', toggleSidebar);

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
</body>

</html>