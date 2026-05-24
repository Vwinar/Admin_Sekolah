<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// View Logic
$view = $_GET['view'] ?? 'reports';

// Default Filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$teacher_id = $_GET['teacher_id'] ?? '';
$class_filter = $_GET['class_filter'] ?? '';

// Build Query Logic based on View
$reports = [];
$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
$attendance_records = [];

if ($view === 'reports') {
    $params = [];
    $whereClauses = ["1=1"];

    if (!empty($start_date)) {
        $whereClauses[] = "DATE(r.report_date) >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {
        $whereClauses[] = "DATE(r.report_date) <= ?";
        $params[] = $end_date;
    }

    if (!empty($teacher_id)) {
        $whereClauses[] = "r.user_id = ?";
        $params[] = $teacher_id;
    }

    $whereSql = implode(' AND ', $whereClauses);

    // Export Handler
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rekap_laporan_' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        // Header
        fputcsv($out, ['Tanggal', 'Guru', 'Mata Pelajaran', 'Kelas', 'Status', 'Ringkasan Materi', 'Kelengkapan']);

        // Data
        $sql = "SELECT r.*, u.full_name as teacher_name 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE $whereSql 
                ORDER BY r.report_date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Kelengkapan Logic (same as before)
            $parts = [];
            if (!empty($row['plan_learning_objective']) || !empty($row['plan_method_type']) || !empty($row['module_file']))
                $parts[] = 'Rencana Hari Ini';
            if (!empty($row['attendance']) || !empty($row['achievement']) || !empty($row['obstacles']))
                $parts[] = 'Hari Ini';
            if (!empty($row['reflection']) || !empty($row['improvement_notes']) || !empty($row['evaluation_file']))
                $parts[] = 'Evaluasi';
            if (!empty($row['plan_material']) || !empty($row['plan_goal']))
                $parts[] = 'Rencana Besok';

            $kelengkapan = empty($parts) ? '-' : implode(', ', $parts);
            $subject = $row['subject'] ?: ($row['plan_subject'] ?? '-');
            $class = $row['class_name'] ?: ($row['plan_class'] ?? '-');

            fputcsv($out, [
                $row['report_date'],
                $row['teacher_name'],
                $subject,
                $class,
                ucfirst($row['status']),
                $row['material_taught'],
                $kelengkapan
            ]);
        }
        fclose($out);
        exit;
    }

    // Stats Queries
    $sqlStats = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM reports r
        WHERE $whereSql";
    $stmtStats = $db->prepare($sqlStats);
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // List Query
    $sqlList = "SELECT r.*, u.full_name as teacher_name 
                FROM reports r 
                JOIN users u ON r.user_id = u.id 
                WHERE $whereSql 
                ORDER BY r.report_date DESC";
    $stmtList = $db->prepare($sqlList);
    $stmtList->execute($params);
    $reports = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} elseif ($view === 'attendance') {
    $period = $_GET['period'] ?? 'daily';

    // Attendance Query Logic
    $attParams = [];
    $attWhere = ["1=1"];

    if (!empty($start_date)) {
        $attWhere[] = "sa.date >= ?";
        $attParams[] = $start_date;
    }
    if (!empty($end_date)) {
        $attWhere[] = "sa.date <= ?";
        $attParams[] = $end_date;
    }
    if (!empty($class_filter)) {
        $attWhere[] = "s.class_name = ?";
        $attParams[] = $class_filter;
    }

    $attWhereSql = implode(' AND ', $attWhere);

    if ($period === 'daily') {
        $sqlAtt = "SELECT 
                    sa.date,
                    s.class_name,
                    s.name as student_name,
                    GROUP_CONCAT(DISTINCT sa.status) as status_summary
                  FROM student_attendance sa
                  JOIN students s ON sa.student_id = s.id
                  WHERE $attWhereSql
                  GROUP BY sa.date, s.id
                  ORDER BY sa.date ASC, s.class_name ASC, s.name ASC";
    } elseif ($period === 'weekly') {
        // Group by Year-Week
        // SQLite strftime('%W', date) returns week number 00-53
        $sqlAtt = "SELECT 
                    strftime('%Y-W%W', sa.date) as period_display,
                    s.class_name,
                    s.name as student_name,
                    COUNT(CASE WHEN sa.status = 'Hadir' THEN 1 END) as count_hadir,
                    COUNT(CASE WHEN sa.status = 'Sakit' THEN 1 END) as count_sakit,
                    COUNT(CASE WHEN sa.status = 'Izin' THEN 1 END) as count_izin,
                    COUNT(CASE WHEN sa.status = 'Alpha' THEN 1 END) as count_alpha
                  FROM student_attendance sa
                  JOIN students s ON sa.student_id = s.id
                  WHERE $attWhereSql
                  GROUP BY period_display, s.id
                  ORDER BY period_display ASC, s.class_name ASC, s.name ASC";
    } elseif ($period === 'monthly') {
        // Group by Year-Month
        $sqlAtt = "SELECT 
                    strftime('%Y-%m', sa.date) as period_display,
                    s.class_name,
                    s.name as student_name,
                    COUNT(CASE WHEN sa.status = 'Hadir' THEN 1 END) as count_hadir,
                    COUNT(CASE WHEN sa.status = 'Sakit' THEN 1 END) as count_sakit,
                    COUNT(CASE WHEN sa.status = 'Izin' THEN 1 END) as count_izin,
                    COUNT(CASE WHEN sa.status = 'Alpha' THEN 1 END) as count_alpha
                  FROM student_attendance sa
                  JOIN students s ON sa.student_id = s.id
                  WHERE $attWhereSql
                  GROUP BY period_display, s.id
                  ORDER BY period_display ASC, s.class_name ASC, s.name ASC";
    }

    $stmtAtt = $db->prepare($sqlAtt);
    $stmtAtt->execute($attParams);
    $attendance_records = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
}

// Teachers List for Dropdown (Always needed for filters if switching views or staying on reports)
$teachers = $db->query("SELECT id, full_name FROM users WHERE role = 'guru' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// Calculate percentages safely (Reports specific)
$total = ($stats['total'] ?? 0) > 0 ? $stats['total'] : 1;

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap & Analitik</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem !important;
            }

            body {
                font-size: 0.8rem !important;
            }

            .header {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.5rem !important;
                margin-bottom: 1rem !important;
                height: 4rem !important;
            }

            .header-left {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.6rem !important;
                flex: 1 !important;
            }

            .header h1 {
                font-size: 1rem !important;
                margin: 0 !important;
                line-height: 1.2 !important;
            }

            .sidebar-toggle {
                width: 2rem !important;
                height: 2rem !important;
                padding: 0.4rem !important;
            }

            .tabs {
                gap: 0.5rem !important;
            }

            .tab-btn {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.75rem !important;
            }

            .card {
                padding: 1rem !important;
            }

            .table-container {
                overflow-x: auto !important;
            }

            table {
                font-size: 0.7rem !important;
                min-width: 700px !important;
            }

            .form-control {
                font-size: 0.8rem !important;
            }

            .btn {
                font-size: 0.75rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            /* Stats grid 1 row (4 columns) on mobile - FORCE */
            body .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                grid-template-rows: 1fr !important;
                gap: 0.4rem !important;
                margin-bottom: 1.25rem !important;
                width: 100% !important;
                overflow: visible !important;
            }

            body .stat-card {
                padding: 0.75rem 0.5rem !important;
                text-align: center !important;
                min-height: auto !important;
            }

            body .stat-label,
            body .stat-card .stat-label {
                font-size: 0.6rem !important;
                line-height: 1.2 !important;
                margin-bottom: 0.3rem !important;
                white-space: normal !important;
            }

            body .stat-value,
            body .stat-card .stat-value {
                font-size: 1.25rem !important;
                font-weight: 700 !important;
                margin-bottom: 0 !important;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1>Rekap & Analitik</h1>
                </div>
            </header>

            <!-- View Toggle Tabs -->
            <div class="tabs">
                <a href="laporan.php?view=reports" class="tab-btn <?= $view === 'reports' ? 'active' : '' ?>">
                    Laporan Pembelajaran
                </a>
                <a href="laporan.php?view=attendance" class="tab-btn <?= $view === 'attendance' ? 'active' : '' ?>">
                    Rekap Absensi Siswa
                </a>
            </div>

            <?php if ($view === 'reports'): ?>
                <!-- Filters for Reports -->
                <div class="card">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="view" value="reports">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                            <label class="form-label">Guru</label>
                            <select name="teacher_id" class="form-control">
                                <option value="">Semua Guru</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $teacher_id == $t['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0;">
                            <button type="submit" class="chip-btn chip-btn-blue">Filter</button>
                            <a href="?export=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&teacher_id=<?= $teacher_id ?>"
                                class="chip-btn chip-btn-purple">
                                Export CSV
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Laporan (Periode Ini)</div>
                        <div class="stat-value"><?= $stats['total'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Disetujui</div>
                        <div class="stat-value" style="color: var(--success);"><?= $stats['approved'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Menunggu Review</div>
                        <div class="stat-value" style="color: var(--warning);"><?= $stats['pending'] ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Perlu Revisi/Ditolak</div>
                        <div class="stat-value" style="color: var(--danger);"><?= $stats['rejected'] ?></div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="card">
                    <h3 class="mb-2">Data Laporan Terfilter</h3>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Guru</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($reports) === 0): ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                            Tidak ada data yang cocok dengan filter.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($reports as $row): ?>
                                        <?php
                                        // Determine filled sections
                                        $parts = [];
                                        if (!empty($row['plan_learning_objective']) || !empty($row['plan_method_type']) || !empty($row['module_file'])) {
                                            $parts[] = 'Rencana Hari Ini';
                                        }
                                        if (!empty($row['attendance']) || !empty($row['achievement']) || !empty($row['obstacles'])) {
                                            $parts[] = 'Hari Ini';
                                        }
                                        if (!empty($row['reflection']) || !empty($row['improvement_notes']) || !empty($row['evaluation_file'])) {
                                            $parts[] = 'Evaluasi';
                                        }
                                        if (!empty($row['plan_material']) || !empty($row['plan_goal'])) {
                                            $parts[] = 'Rencana Besok';
                                        }
                                        $keterangan = empty($parts) ? '-' : implode(', ', $parts);

                                        // Subject Fallback
                                        $subjectDisplay = $row['subject'] ?: ($row['plan_subject'] ?? '-');

                                        // Class Fallback
                                        $classDisplay = $row['class_name'] ?: ($row['plan_class'] ?? '-');
                                        ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                            <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                            <td><?= htmlspecialchars($subjectDisplay) ?></td>
                                            <td><?= htmlspecialchars($classDisplay) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $row['status'] ?>">
                                                    <?= ucfirst($row['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="badge-container">
                                                    <?php
                                                    if (empty($parts)) {
                                                        echo '<span class="badge badge-empty">Kosong</span>';
                                                    } else {
                                                        foreach ($parts as $part) {
                                                            if ($part === 'Rencana Hari Ini') echo '<span class="badge badge-rencana-hari-ini">Rencana Hari Ini</span>';
                                                            if ($part === 'Hari Ini') echo '<span class="badge badge-hari-ini">Hari Ini</span>';
                                                            if ($part === 'Evaluasi') echo '<span class="badge badge-evaluasi">Evaluasi</span>';
                                                            if ($part === 'Rencana Besok') echo '<span class="badge badge-rencana-besok">Rencana Besok</span>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="review_laporan.php?id=<?= $row['id'] ?>" class="chip-btn chip-btn-blue btn-review">Detail</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($view === 'attendance'): ?>
                <!-- Period Selector -->
                <?php $period = $_GET['period'] ?? 'daily'; ?>
                <div class="card p-4 mb-4">
                    <div class="flex flex-wrap gap-2 items-center justify-between">
                        <div class="flex gap-2">
                            <a href="laporan.php?view=attendance&period=daily&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&class_filter=<?= $class_filter ?>"
                                class="chip-btn <?= $period === 'daily' ? 'chip-btn-blue' : 'chip-btn-purple' ?>">
                                Harian
                            </a>
                            <a href="laporan.php?view=attendance&period=weekly&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&class_filter=<?= $class_filter ?>"
                                class="chip-btn <?= $period === 'weekly' ? 'chip-btn-blue' : 'chip-btn-purple' ?>">
                                Mingguan
                            </a>
                            <a href="laporan.php?view=attendance&period=monthly&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&class_filter=<?= $class_filter ?>"
                                class="chip-btn <?= $period === 'monthly' ? 'chip-btn-blue' : 'chip-btn-purple' ?>">
                                Bulanan
                            </a>
                        </div>
                    </div>
                </div>

                <?php
                // Calculate Attendance Stats for the current filtered view
                $total_hadir = 0;
                $total_sakit = 0;
                $total_izin = 0;
                $total_alpha = 0;

                if (!empty($attendance_records)) {
                    if ($period === 'daily') {
                        foreach ($attendance_records as $att) {
                            $statuses = explode(',', $att['status_summary']);
                            foreach ($statuses as $st) {
                                $st = trim($st);
                                if ($st == 'Hadir')
                                    $total_hadir++;
                                if ($st == 'Sakit')
                                    $total_sakit++;
                                if ($st == 'Izin')
                                    $total_izin++;
                                if ($st == 'Alpha')
                                    $total_alpha++;
                            }
                        }
                    } else {
                        foreach ($attendance_records as $att) {
                            $total_hadir += $att['count_hadir'];
                            $total_sakit += $att['count_sakit'];
                            $total_izin += $att['count_izin'];
                            $total_alpha += $att['count_alpha'];
                        }
                    }
                }
                ?>

                <!-- Attendance Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Hadir</div>
                        <div class="stat-value" style="color: var(--success);"><?= $total_hadir ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Sakit</div>
                        <div class="stat-value" style="color: var(--warning);"><?= $total_sakit ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Izin</div>
                        <div class="stat-value" style="color: var(--info);"><?= $total_izin ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Alpha</div>
                        <div class="stat-value" style="color: var(--danger);"><?= $total_alpha ?></div>
                    </div>
                </div>

                <!-- Filters for Attendance -->
                <div class="card">
                    <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                        <input type="hidden" name="view" value="attendance">
                        <input type="hidden" name="period" value="<?= htmlspecialchars($period) ?>">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                            <label class="form-label">Kelas</label>
                            <?php
                            $allClasses = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();
                            $class_filter = $_GET['class_filter'] ?? '';
                            ?>
                            <select name="class_filter" class="form-control">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($allClasses as $c): ?>
                                    <option value="<?= htmlspecialchars($c['name']) ?>" <?= $class_filter === $c['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0;">
                            <button type="submit" class="chip-btn chip-btn-blue">Filter</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3 class="mb-2">
                        <?php
                        if ($period === 'weekly')
                            echo "Rekap Absensi (Mingguan)";
                        elseif ($period === 'monthly')
                            echo "Rekap Absensi (Bulanan)";
                        else
                            echo "Rekap Absensi (Harian)";
                        ?>
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                        <?php
                        if ($period === 'daily')
                            echo "Data menampilkan status kehadiran siswa per hari.";
                        else
                            echo "Data menampilkan total kehadiran, sakit, izin, dan alpha siswa dalam periode terpilih.";
                        ?>
                    </p>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <?php
                                        if ($period === 'weekly')
                                            echo "Minggu Ke (Tahun-Minggu)";
                                        elseif ($period === 'monthly')
                                            echo "Bulan (Tahun-Bulan)";
                                        else
                                            echo "Tanggal";
                                        ?>
                                    </th>
                                    <th>Kelas</th>
                                    <th>Nama Siswa</th>
                                    <?php if ($period === 'daily'): ?>
                                        <th>Status Kehadiran</th>
                                    <?php else: ?>
                                        <th style="color: var(--success);">Hadir</th>
                                        <th style="color: var(--warning);">Sakit</th>
                                        <th style="color: var(--info);">Izin</th>
                                        <th style="color: var(--danger);">Alpha</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendance_records)): ?>
                                    <tr>
                                        <td colspan="<?= $period === 'daily' ? 4 : 7 ?>"
                                            style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                            Tidak ada data absensi untuk periode ini.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attendance_records as $att): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                if ($period === 'daily') {
                                                    echo date('d M Y', strtotime($att['date']));
                                                } elseif ($period === 'weekly') {
                                                    $parts = explode('-W', $att['period_display']);
                                                    echo "Minggu ke-" . ($parts[1] ?? '?') . ", " . ($parts[0] ?? '');
                                                } else {
                                                    echo date('F Y', strtotime($att['period_display'] . '-01'));
                                                }
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($att['class_name']) ?></td>
                                            <td style="font-weight: 500;"><?= htmlspecialchars($att['student_name']) ?></td>

                                            <?php if ($period === 'daily'): ?>
                                                <td>
                                                    <?php
                                                    $statuses = explode(',', $att['status_summary']);
                                                    ?>
                                                    <div class="badge-container">
                                                        <?php foreach ($statuses as $st): ?>
                                                            <?php 
                                                            $st = trim($st);
                                                            $badge_class = 'badge-empty';
                                                            if ($st === 'Hadir') $badge_class = 'badge-approved';
                                                            elseif ($st === 'Sakit') $badge_class = 'badge-pending';
                                                            elseif ($st === 'Izin') $badge_class = 'badge-revision';
                                                            elseif ($st === 'Alpha') $badge_class = 'badge-rejected';
                                                            ?>
                                                            <span class="badge <?= $badge_class ?>"><?= $st ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </td>
                                            <?php else: ?>
                                                <td><?= $att['count_hadir'] ?></td>
                                                <td><?= $att['count_sakit'] ?></td>
                                                <td><?= $att['count_izin'] ?></td>
                                                <td><?= $att['count_alpha'] ?></td>
                                            <?php endif; ?>
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
</body>

</html>