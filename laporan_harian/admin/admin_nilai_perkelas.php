<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check - Only admin/kepala sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Get filter parameters
$selected_class = $_GET['class'] ?? '';
$selected_subject = $_GET['subject'] ?? '';
$selected_semester = $_GET['semester'] ?? '';
$selected_year = $_GET['year'] ?? '';
$selected_exam_type = $_GET['exam_type'] ?? '';

// Get all available classes
$classesQuery = "SELECT DISTINCT assigned_class FROM users WHERE role = 'siswa' AND assigned_class IS NOT NULL AND assigned_class != '' ORDER BY assigned_class";
$classesStmt = $db->query($classesQuery);
$classes = $classesStmt->fetchAll();

// Get all subjects
$subjectsQuery = "SELECT DISTINCT name FROM subjects ORDER BY name";
$subjectsStmt = $db->query($subjectsQuery);
$subjects = $subjectsStmt->fetchAll();

// Get all years from grades
$yearsQuery = "SELECT DISTINCT year FROM student_grades ORDER BY year DESC";
$yearsStmt = $db->query($yearsQuery);
$years = $yearsStmt->fetchAll();

// Build query for grades
$gradesQuery = "SELECT 
    g.id,
    g.student_id,
    s.name as student_name,
    s.class_name,
    g.subject,
    g.semester,
    g.exam_type,
    g.nilai,
    g.ranking,
    g.year
FROM student_grades g
INNER JOIN students s ON g.student_id = s.id
WHERE 1=1";

$params = [];

if ($selected_class) {
    $gradesQuery .= " AND s.class_name = ?";
    $params[] = $selected_class;
}

if ($selected_subject) {
    $gradesQuery .= " AND g.subject = ?";
    $params[] = $selected_subject;
}

if ($selected_semester) {
    $gradesQuery .= " AND g.semester = ?";
    $params[] = $selected_semester;
}

if ($selected_year) {
    $gradesQuery .= " AND g.year = ?";
    $params[] = $selected_year;
}

if ($selected_exam_type) {
    $gradesQuery .= " AND g.exam_type = ?";
    $params[] = $selected_exam_type;
}

$gradesQuery .= " ORDER BY s.class_name ASC, s.name ASC, g.subject ASC, g.semester ASC, g.exam_type ASC";

$gradesStmt = $db->prepare($gradesQuery);
$gradesStmt->execute($params);
$grades = $gradesStmt->fetchAll();

// Calculate statistics per class
$statsPerClass = [];
if (!empty($grades)) {
    foreach ($grades as $grade) {
        $class = $grade['class_name'];
        if (!isset($statsPerClass[$class])) {
            $statsPerClass[$class] = [
                'total_nilai' => 0,
                'count' => 0,
                'highest' => $grade['nilai'],
                'lowest' => $grade['nilai'],
                'students' => []
            ];
        }

        $statsPerClass[$class]['total_nilai'] += $grade['nilai'];
        $statsPerClass[$class]['count']++;
        $statsPerClass[$class]['highest'] = max($statsPerClass[$class]['highest'], $grade['nilai']);
        $statsPerClass[$class]['lowest'] = min($statsPerClass[$class]['lowest'], $grade['nilai']);
        $statsPerClass[$class]['students'][$grade['student_name']] = true;
    }

    foreach ($statsPerClass as $class => &$stats) {
        $stats['average'] = $stats['total_nilai'] / $stats['count'];
        $stats['student_count'] = count($stats['students']);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Nilai Siswa Per Kelas</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .filter-group select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.875rem;
            background: white;
            transition: all 0.2s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
        }

        .btn-print-special {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            text-decoration: none;
        }

        .btn-print-special:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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
                height: 4rem !important;
                /* Fixed height for consistency */
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

            .filter-actions {
                flex-direction: row !important;
                gap: 0.5rem !important;
            }

            .filter-actions .btn,
            .filter-actions .btn-print-special {
                flex: 1 !important;
                width: auto !important;
                min-width: 0 !important;
                padding: 0.5rem 0.3rem !important;
                font-size: 0.75rem !important;
                justify-content: center !important;
            }

            .filter-actions .btn i {
                font-size: 0.9rem !important;
                margin-right: 0.3rem !important;
            }

            .filter-actions .btn span {
                display: inline !important;
                font-size: 0.75rem !important;
            }

            .data-table {
                font-size: 0.75rem !important;
            }

            .data-table th,
            .data-table td {
                padding: 0.6rem 0.4rem !important;
            }

            .badge {
                font-size: 0.65rem !important;
                padding: 0.15rem 0.4rem !important;
            }

            /* Table horizontal scroll on mobile */
            .table-scroll-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                margin: 0 -1rem !important;
                padding: 0 1rem !important;
            }

            .data-table {
                min-width: 800px !important;
                margin: 0 !important;
            }

            /* Scroll hint shadow */
            .class-group-header {
                position: relative;
            }

            .class-group-header::after {
                content: '\2190\00A0\00A0Geser\00A0\00A0\2192';
                position: absolute;
                right: 1rem;
                font-size: 0.7rem;
                opacity: 0.8;
                font-weight: 500;
            }
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card h4 {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .stat-card .label {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .data-table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .table-scroll-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            position: relative;
        }

        .table-scroll-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-scroll-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .table-scroll-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .table-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text-main);
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-exam {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge-semester {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .nilai-cell {
            font-weight: 700;
            font-size: 1rem;
        }

        .nilai-excellent {
            color: #059669;
        }

        .nilai-good {
            color: #0284c7;
        }

        .nilai-fair {
            color: #d97706;
        }

        .nilai-poor {
            color: #dc2626;
        }

        .class-group {
            margin-bottom: 2rem;
        }

        .class-group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
            font-weight: 700;
            font-size: 1.1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .class-stats {
            font-size: 0.875rem;
            opacity: 0.95;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            color: #e5e7eb;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .print-header {
            display: none;
        }

        @media print {

            /* Show print header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 2rem;
                padding-bottom: 1rem;
                border-bottom: 3px solid #000;
            }

            .print-header h1 {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 0.5rem;
                color: #000;
            }

            .print-header p {
                font-size: 11pt;
                margin: 0.25rem 0;
                color: #000;
            }

            /* Hide non-essential elements */
            .filter-section,
            .filter-actions,
            .btn,
            .btn-print-special,
            .btn-group,
            .sidebar,
            .sidebar-toggle,
            .header,
            .stats-cards,
            .empty-state i {
                display: none !important;
            }

            /* Reset body and layout for print */
            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            .dashboard-layout {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .main-content {
                margin: 0 !important;
                padding: 1cm !important;
                max-width: 100% !important;
            }

            /* Table styling for print */
            .data-table {
                font-size: 9pt;
                page-break-inside: auto;
                table-layout: fixed !important;
                width: 100% !important;
            }

            .data-table thead {
                display: table-header-group;
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .data-table th {
                background: #f0f0f0 !important;
                border: 1px solid #000 !important;
                padding: 8px !important;
                font-weight: bold;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Fixed column widths for consistency */
            .data-table th:nth-child(1),
            .data-table td:nth-child(1) {
                width: 4% !important;
            }

            .data-table th:nth-child(2),
            .data-table td:nth-child(2) {
                width: 20% !important;
            }

            .data-table th:nth-child(3),
            .data-table td:nth-child(3) {
                width: 20% !important;
            }

            .data-table th:nth-child(4),
            .data-table td:nth-child(4) {
                width: 12% !important;
            }

            .data-table th:nth-child(5),
            .data-table td:nth-child(5) {
                width: 12% !important;
            }

            .data-table th:nth-child(6),
            .data-table td:nth-child(6) {
                width: 14% !important;
            }

            .data-table th:nth-child(7),
            .data-table td:nth-child(7) {
                width: 10% !important;
                text-align: center !important;
            }

            .data-table th:nth-child(8),
            .data-table td:nth-child(8) {
                width: 8% !important;
                text-align: center !important;
            }

            .data-table td {
                border: 1px solid #ccc !important;
                padding: 6px 8px !important;
                color: #000 !important;
                overflow: visible !important;
                white-space: normal !important;
            }

            .data-table tbody tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            /* Class group styling */
            .class-group {
                page-break-inside: auto;
                margin-bottom: 1.5rem;
            }

            .class-group-header {
                background: #666 !important;
                color: white !important;
                padding: 10px !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-radius: 0 !important;
            }

            .data-table-wrapper {
                box-shadow: none !important;
                border: 2px solid #000 !important;
                border-radius: 0 !important;
                overflow: visible !important;
            }

            /* Badge styling for print */
            .badge {
                border: 1px solid #000 !important;
                padding: 2px 6px !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .badge-exam {
                background: #e0f2fe !important;
                color: #000 !important;
            }

            .badge-semester {
                background: #f3e8ff !important;
                color: #000 !important;
            }

            /* Nilai color for print */
            .nilai-excellent {
                color: #059669 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .nilai-good {
                color: #0284c7 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .nilai-fair {
                color: #d97706 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .nilai-poor {
                color: #dc2626 !important;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Empty state for print */
            .empty-state {
                padding: 2rem !important;
                text-align: center;
            }

            .empty-state h3 {
                color: #000 !important;
            }

            /* Page breaks */
            .class-group {
                page-break-after: auto;
            }

            @page {
                margin: 1.5cm;
                size: A4 landscape;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <!-- Print Header (only visible when printing) -->
            <div class="print-header">
                <h1>LAPORAN DATA NILAI SISWA PER KELAS</h1>
                <p><strong>Tanggal Cetak:</strong> <?= date('d F Y, H:i') ?> WIB</p>
                <?php
                $filterInfo = [];
                if ($selected_class)
                    $filterInfo[] = "Kelas: " . $selected_class;
                if ($selected_subject)
                    $filterInfo[] = "Mapel: " . $selected_subject;
                if ($selected_semester)
                    $filterInfo[] = "Semester: " . $selected_semester;
                if ($selected_year)
                    $filterInfo[] = "Tahun: " . $selected_year;
                if ($selected_exam_type)
                    $filterInfo[] = "Jenis: " . $selected_exam_type;

                if (!empty($filterInfo)):
                    ?>
                    <p><strong>Filter:</strong> <?= implode(' | ', $filterInfo) ?></p>
                <?php else: ?>
                    <p><strong>Filter:</strong> Semua Data</p>
                <?php endif; ?>
            </div>

            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="header-title">
                        <h1>Data Nilai Siswa Per Kelas</h1>
                        <p>Kelola dan pantau nilai siswa</p>
                    </div>
                </div>
                <div class="header-actions">
                    <button onclick="window.print()" class="btn btn-secondary" title="Cetak">
                        <i class="bi bi-printer"></i> <span>Cetak</span>
                    </button>
                    <a href="admin_administrasi.php#kesiswaan" class="btn btn-secondary" title="Kembali">
                        <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                    </a>
                </div>
            </header>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h3>
                        <i class="bi bi-funnel"></i> Filter Data Nilai
                    </h3>
                </div>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="class">Kelas</label>
                            <select name="class" id="class">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= htmlspecialchars($class['assigned_class']) ?>"
                                        <?= $selected_class === $class['assigned_class'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['assigned_class']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="subject">Mata Pelajaran</label>
                            <select name="subject" id="subject">
                                <option value="">Semua Mapel</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= htmlspecialchars($subject['name']) ?>"
                                        <?= $selected_subject === $subject['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($subject['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="semester">Semester</label>
                            <select name="semester" id="semester">
                                <option value="">Semua Semester</option>
                                <option value="1" <?= $selected_semester === '1' ? 'selected' : '' ?>>Semester 1 (Ganjil)
                                </option>
                                <option value="2" <?= $selected_semester === '2' ? 'selected' : '' ?>>Semester 2 (Genap)
                                </option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="year">Tahun Ajaran</label>
                            <select name="year" id="year">
                                <option value="">Semua Tahun</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?= htmlspecialchars($year['year']) ?>" <?= $selected_year === $year['year'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($year['year']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="exam_type">Jenis Ujian</label>
                            <select name="exam_type" id="exam_type">
                                <option value="">Semua Jenis</option>
                                <option value="PH" <?= $selected_exam_type === 'PH' ? 'selected' : '' ?>>PH (Penilaian
                                    Harian)</option>
                                <option value="PTS" <?= $selected_exam_type === 'PTS' ? 'selected' : '' ?>>PTS (Tengah
                                    Semester)</option>
                                <option value="PAS" <?= $selected_exam_type === 'PAS' ? 'selected' : '' ?>>PAS (Akhir
                                    Semester)</option>
                                <option value="PAT" <?= $selected_exam_type === 'PAT' ? 'selected' : '' ?>>PAT (Akhir
                                    Tahun)</option>
                                <option value="US" <?= $selected_exam_type === 'US' ? 'selected' : '' ?>>US (Ujian Sekolah)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> <span>Terapkan Filter</span>
                        </button>
                        <a href="admin_nilai_perkelas.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> <span>Reset</span>
                        </a>
                    </div>
                </form>
            </div>

            <?php if (!empty($statsPerClass) && $selected_class): ?>
                <!-- Statistics Cards -->
                <div class="stats-cards">
                    <?php foreach ($statsPerClass as $class => $stats): ?>
                        <div class="stat-card">
                            <h4>RATA-RATA KELAS</h4>
                            <div class="value"><?= number_format($stats['average'], 2) ?></div>
                            <div class="label"><?= htmlspecialchars($class) ?></div>
                        </div>
                        <div class="stat-card green">
                            <h4>NILAI TERTINGGI</h4>
                            <div class="value"><?= number_format($stats['highest'], 2) ?></div>
                            <div class="label">Best Score</div>
                        </div>
                        <div class="stat-card orange">
                            <h4>NILAI TERENDAH</h4>
                            <div class="value"><?= number_format($stats['lowest'], 2) ?></div>
                            <div class="label">Lowest Score</div>
                        </div>
                        <div class="stat-card blue">
                            <h4>JUMLAH SISWA</h4>
                            <div class="value"><?= $stats['student_count'] ?></div>
                            <div class="label">Students</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Data Table -->
            <?php if (!empty($grades)): ?>
                <?php
                // Group by class
                $gradesByClass = [];
                foreach ($grades as $grade) {
                    $gradesByClass[$grade['class_name']][] = $grade;
                }
                ?>

                <?php foreach ($gradesByClass as $className => $classGrades): ?>
                    <div class="class-group">
                        <div class="data-table-wrapper">
                            <div class="class-group-header">
                                <span><i class="bi bi-mortarboard"></i> Kelas <?= htmlspecialchars($className) ?></span>
                                <span class="class-stats">
                                    <?= count($classGrades) ?> Data Nilai
                                </span>
                            </div>

                            <div class="table-scroll-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Nama Siswa</th>
                                            <th>Mata Pelajaran</th>
                                            <th>Jenis Ujian</th>
                                            <th>Semester</th>
                                            <th>Tahun Ajaran</th>
                                            <th>Nilai</th>
                                            <th>Ranking</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classGrades as $index => $grade):
                                            $nilaiClass = '';
                                            if ($grade['nilai'] >= 85)
                                                $nilaiClass = 'nilai-excellent';
                                            elseif ($grade['nilai'] >= 70)
                                                $nilaiClass = 'nilai-good';
                                            elseif ($grade['nilai'] >= 60)
                                                $nilaiClass = 'nilai-fair';
                                            else
                                                $nilaiClass = 'nilai-poor';
                                            ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><strong><?= htmlspecialchars($grade['student_name']) ?></strong></td>
                                                <td><?= htmlspecialchars($grade['subject']) ?></td>
                                                <td><span
                                                        class="badge badge-exam"><?= htmlspecialchars($grade['exam_type']) ?></span>
                                                </td>
                                                <td><span class="badge badge-semester">Semester <?= $grade['semester'] ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($grade['year']) ?></td>
                                                <td class="nilai-cell <?= $nilaiClass ?>">
                                                    <?= number_format($grade['nilai'], 2) ?>
                                                </td>
                                                <td><?= $grade['ranking'] ? '#' . $grade['ranking'] : '-' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="data-table-wrapper">
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h3>Tidak Ada Data</h3>
                        <p>Belum ada data nilai yang sesuai dengan filter yang dipilih.<br>
                            Silakan ubah filter atau tambahkan data nilai terlebih dahulu.</p>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');
        const sidebarState = localStorage.getItem('sidebarCollapsed');

        if (sidebarState === 'true') {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        }

        function toggleSidebar() {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');
            localStorage.setItem('sidebarCollapsed', dashboardLayout.classList.contains('sidebar-collapsed'));
        }

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