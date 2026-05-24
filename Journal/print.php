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

$nama_guru = $_SESSION['full_name'] ?? '';
$nip_guru = $current_user['nip'] ?? '';
// Fetch Principal (Headmaster) automatically - User with role 'admin'
$stmt_admin = $db->prepare("SELECT full_name, nip FROM users WHERE role = 'admin' LIMIT 1");
$stmt_admin->execute();
$admin_user = $stmt_admin->fetch();

$nama_kepala_sekolah = $admin_user ? $admin_user['full_name'] : '';
$nip_kepala_sekolah = $admin_user ? $admin_user['nip'] : '';

$startDate = date('Y-m-d', strtotime('monday this week'));
$endDate = date('Y-m-d', strtotime('sunday this week 23:59:59'));
$entries = getEntriesForWeek($startDate, $endDate);

// Group entries logic
$groupedEntries = [];
$allDates = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['print_week'])) {
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $nama_guru = $_POST['nama_guru'] ?? '';
    $nip_guru = $_POST['nip_guru'] ?? '';
    $nama_kepala_sekolah = $_POST['nama_kepala_sekolah'] ?? '';
    $nip_kepala_sekolah = $_POST['nip_kepala_sekolah'] ?? '';

    $entries = getEntriesForWeek($startDate, $endDate);
}

// Process entries
foreach ($entries as $entry) {
    $date = $entry['entry_date'];
    if (!isset($groupedEntries[$date])) {
        $groupedEntries[$date] = [];
    }
    $groupedEntries[$date][] = $entry;
}

// Generate dates
$currentDate = strtotime($startDate);
$endDateTime = strtotime($endDate);
while ($currentDate <= $endDateTime) {
    $dateStr = date('Y-m-d', $currentDate);
    $allDates[] = $dateStr;
    $currentDate = strtotime('+1 day', $currentDate);
}

function formatIndonesianDate($date)
{
    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];
    $timestamp = strtotime($date);
    $dayName = date('l', $timestamp);
    $day = date('d', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);

    return ($days[$dayName] ?? $dayName) . ", {$day} " . ($months[$month] ?? $month) . " {$year}";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Jurnal Mingguan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        /* Print Layout Overrides */
        @media print {
            @page {
                size: portrait;
                margin: 1cm;
            }

            *,
            *::before,
            *::after {
                text-shadow: none !important;
                box-shadow: none !important;
            }

            html,
            body {
                background-color: white !important;
                background: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                font-family: 'Arial Narrow', Arial, sans-serif !important;
                line-height: 1.1 !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                border: none !important;
                font-size: 10pt !important;
            }

            /* Hide Dashboard/Layout wrappers to prevent background leakage */
            .dashboard-layout,
            .main-content {
                display: block !important;
                background: white !important;
                background-color: white !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                width: 100% !important;
                position: static !important;
                overflow: visible !important;
            }

            /* Hide potential overlays */
            .dashboard-layout::before,
            .dashboard-layout::after,
            body::before,
            body::after {
                display: none !important;
                content: none !important;
            }

            .sidebar,
            .header,
            .btn,
            .no-print,
            .toast,
            .form-section-container,
            .card>form {
                display: none !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: white !important;
            }

            /* Print Specific Header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid black;
                padding-bottom: 10px;
            }

            .print-header h1 {
                font-size: 18pt;
                margin: 0;
                text-transform: uppercase;
                color: black !important;
            }

            .print-header p {
                margin: 5px 0 0;
                color: black !important;
            }

            /* Signature Section */
            .signature-section {
                display: flex !important;
                margin-top: 50px;
                page-break-inside: avoid;
            }

            /* Ensure colors print for headers if background graphics enabled */
            .entry-header {
                background-color: #4f46e5 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #4f46e5 !important;
            }
        }

        .print-header,
        .signature-section {
            display: none;
        }

        .entry-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        .entry-table td {
            padding: 0.5rem;
            vertical-align: top;
            border: 1px solid #e2e8f0;
            text-align: justify;
            padding: 0.25rem !important;
            /* Reduced padding */
        }

        .entry-table ul {
            margin: 0;
            padding-left: 1rem;
        }

        .entry-table li {
            margin-bottom: 0px;
            /* Reduced list item spacing */
        }

        /* Form Styling Fixes */
        .search-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }

            /* Responsive Header for Back Button */
            .header {
                position: relative;
            }

            .header-left {
                display: flex;
                align-items: center;
                gap: 1rem;
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
                background: white;
                box-shadow: var(--shadow-sm);
            }

            .back-btn .btn-text {
                display: none;
            }

            .back-btn i {
                margin-right: 0 !important;
            }
        }
    </style>
</head>

<body class="dashboard-page">
    <div class="dashboard-layout">
        <?php include '../laporan_harian/layout/user_sidebar.php'; ?>

        <main class="main-content">
            <header class="header no-print">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <span></span><span></span><span></span>
                    </button>
                    <div>
                        <h1>Cetak Jurnal</h1>
                        <p style="color: var(--text-muted)">Cetak laporan jurnal mingguan Anda.</p>
                    </div>
                </div>
                <div class="header-right">
                    <a href="index.php" class="btn btn-secondary back-btn"><i class="fas fa-arrow-left me-1"></i> <span
                            class="btn-text">Kembali</span></a>
                </div>
            </header>

            <div class="card no-print">
                <form method="POST">
                    <h3 class="mb-2">Filter Data Cetak</h3>
                    <div class="search-grid">
                        <div class="form-group">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control"
                                value="<?php echo htmlspecialchars($startDate); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tanggal Akhir</label>
                            <input type="date" name="end_date" class="form-control"
                                value="<?php echo htmlspecialchars($endDate); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Guru</label>
                            <input type="text" name="nama_guru" class="form-control"
                                value="<?php echo htmlspecialchars($nama_guru); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">NIP Guru</label>
                            <input type="text" name="nip_guru" class="form-control"
                                value="<?php echo htmlspecialchars($nip_guru); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nama Kepala Sekolah</label>
                            <input type="text" name="nama_kepala_sekolah" class="form-control"
                                value="<?php echo htmlspecialchars($nama_kepala_sekolah); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">NIP Kepala Sekolah</label>
                            <input type="text" name="nip_kepala_sekolah" class="form-control"
                                value="<?php echo htmlspecialchars($nip_kepala_sekolah); ?>">
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="print_week" class="btn btn-primary"><i class="fas fa-search"></i>
                            Tampilkan</button>
                        <button type="button" onclick="window.print()" class="btn btn-success"><i
                                class="fas fa-print"></i> Cetak PDF</button>
                    </div>
                </form>
            </div>

            <!-- Print Content Area -->
            <div id="print-area">
                <div class="print-header">
                    <h1>Jurnal Pembelajaran Mingguan</h1>
                    <p>Periode: <?php echo date('d M Y', strtotime($startDate)); ?> s/d
                        <?php echo date('d M Y', strtotime($endDate)); ?>
                    </p>
                </div>

                <?php if (empty($groupedEntries) && $_SERVER['REQUEST_METHOD'] == 'POST'): ?>
                    <div class="card no-print"
                        style="text-align: center; padding: 2rem; color: var(--text-muted); margin-top: 1rem;">
                        Tidak ada data untuk periode ini.
                    </div>
                <?php endif; ?>

                <?php foreach ($allDates as $date): ?>
                    <?php if (isset($groupedEntries[$date]) && !empty($groupedEntries[$date])): ?>
                        <div style="margin-bottom: 2rem; page-break-inside: avoid;">
                            <h3
                                style="border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem; margin-bottom: 1rem; color: var(--text-main);">
                                <?php echo htmlspecialchars(formatIndonesianDate($date)); ?>
                            </h3>

                            <?php foreach ($groupedEntries[$date] as $entry): ?>
                                <div
                                    style="border: 1px solid #cbd5e1; border-radius: 0.5rem; overflow: hidden; margin-bottom: 1rem;">
                                    <div class="entry-header"
                                        style="background: var(--primary); color: white; padding: 0.75rem 1rem; font-weight: bold; display: flex; justify-content: space-between;">
                                        <span><?php echo htmlspecialchars($entry['subject']); ?></span>
                                        <span style="font-weight: normal; font-size: 0.9em;">Jam <?php echo $entry['hour']; ?>
                                            (<?php echo $entry['jumlah_jp']; ?> JP)</span>
                                    </div>
                                    <div style="padding: 1rem;">
                                        <table class="entry-table">
                                            <tr>
                                                <td colspan="2">
                                                    <strong>Capaian Pembelajaran:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['capaian_pembelajaran']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td width="50%">
                                                    <strong>Tujuan Pembelajaran:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['pencapaian']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                                <td width="50%">
                                                    <strong>Pokok Materi:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['pokok_materi']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong>Permasalahan:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['permasalahan']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                                <td>
                                                    <strong>Solusi:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['solusi']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2">
                                                    <strong>Catatan Pembelajaran:</strong>
                                                    <ul>
                                                        <?php foreach (explode(";\n", $entry['catatan_pembelajaran']) as $item)
                                                            if (trim($item))
                                                                echo "<li>" . htmlspecialchars(trim($item)) . "</li>"; ?>
                                                    </ul>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Check if Saturday (Day 6) to print signature
                    if (date('N', strtotime($date)) == 6):
                        // Indonesian Date for Signature (e.g., 20 Juli 2025)
                        $months = [
                            1 => 'Januari',
                            2 => 'Februari',
                            3 => 'Maret',
                            4 => 'April',
                            5 => 'Mei',
                            6 => 'Juni',
                            7 => 'Juli',
                            8 => 'Agustus',
                            9 => 'September',
                            10 => 'Oktober',
                            11 => 'November',
                            12 => 'Desember'
                        ];
                        $timestamp = strtotime($date);
                        $day = date('d', $timestamp);
                        $month = date('n', $timestamp);
                        $year = date('Y', $timestamp);
                        $signatureDate = "{$day} " . ($months[$month] ?? $month) . " {$year}";
                        ?>
                        <div class="signature-section"
                            style="display: flex; justify-content: space-between; margin-top: 3rem; margin-bottom: 4rem; width: 100%; page-break-inside: avoid;">
                            <div style="text-align: center; width: 40%;">
                                <p>Mengetahui,</p>
                                <p>Kepala Sekolah</p>
                                <br><br><br><br>
                                <p style="font-weight: bold; text-decoration: underline;">
                                    <?php echo $nama_kepala_sekolah ? htmlspecialchars($nama_kepala_sekolah) : '.........................'; ?>
                                </p>
                                <p>NIP.
                                    <?php echo $nip_kepala_sekolah ? htmlspecialchars($nip_kepala_sekolah) : '.........................'; ?>
                                </p>
                            </div>
                            <div style="text-align: center; width: 40%;">
                                <p>Pucuk, <?php echo $signatureDate; ?></p>
                                <p>Guru Kelas</p>
                                <br><br><br><br>
                                <p style="font-weight: bold; text-decoration: underline;">
                                    <?php echo $nama_guru ? htmlspecialchars($nama_guru) : '.........................'; ?>
                                </p>
                                <p>NIP. <?php echo $nip_guru ? htmlspecialchars($nip_guru) : '.........................'; ?></p>
                            </div>
                        </div>
                        <div style="page-break-after: always;"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <script>
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
    </script>
</body>

</html>