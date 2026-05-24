<?php
session_start();
require_once '../config/db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

// Handle POST to save tanggal_cetak (must be before redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tanggal_cetak') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    $tanggal = $_POST['tanggal'] ?? '';
    $stmt = $db->prepare("UPDATE tanggal_cetak SET tanggal = :tanggal WHERE id = 1");
    $stmt->bindValue(':tanggal', $tanggal, PDO::PARAM_STR);
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to update tanggal_cetak']);
    }
    exit();
}

// Handle Delete Request (must be before redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_record') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit();
    }

    $date = $_POST['date'] ?? '';
    $username = $_POST['username'] ?? '';
    $type = $_POST['type'] ?? '';

    if ($date && $username && $type) {
        // Get user id
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_db_id = $user['id'];

            // First, get the photo filenames to delete them from uploads folder
            if ($type === 'absen') {
                $fetch_stmt = $db->prepare("SELECT foto_masuk, foto_pulang FROM attendance WHERE user_id = ? AND date = ?");
                $fetch_stmt->execute([$user_db_id, $date]);
                $photo_data = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

                // Delete photo files if they exist
                if ($photo_data) {
                    if (!empty($photo_data['foto_masuk'])) {
                        $path = $photo_data['foto_masuk'];
                        // Try standard path
                        $target = (strpos($path, '../') === 0) ? $path : '../' . $path;
                        if (file_exists($target)) {
                            unlink($target);
                        } else {
                            // Try legacy path (utils/uploads) if standard fails
                            $legacy = '../utils/' . $path;
                            if (file_exists($legacy)) {
                                unlink($legacy);
                            }
                        }
                    }
                    if (!empty($photo_data['foto_pulang'])) {
                        $path = $photo_data['foto_pulang'];
                        // Try standard path
                        $target = (strpos($path, '../') === 0) ? $path : '../' . $path;
                        if (file_exists($target)) {
                            unlink($target);
                        } else {
                            // Try legacy path (utils/uploads) if standard fails
                            $legacy = '../utils/' . $path;
                            if (file_exists($legacy)) {
                                unlink($legacy);
                            }
                        }
                    }
                }

                // Delete database record
                $del = $db->prepare("DELETE FROM attendance WHERE user_id = ? AND date = ?");
                $del->execute([$user_db_id, $date]);

            } elseif ($type === 'izin') {
                $fetch_stmt = $db->prepare("SELECT foto FROM izin WHERE user_id = ? AND date = ?");
                $fetch_stmt->execute([$user_db_id, $date]);
                $photo_data = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

                // Delete photo file if it exists
                if ($photo_data && !empty($photo_data['foto'])) {
                    $path = $photo_data['foto'];
                    // Try standard path
                    $target = (strpos($path, '../') === 0) ? $path : '../' . $path;
                    if (file_exists($target)) {
                        unlink($target);
                    } else {
                        // Try legacy path (utils/uploads) if standard fails
                        $legacy = '../utils/' . $path;
                        if (file_exists($legacy)) {
                            unlink($legacy);
                        }
                    }
                }

                // Delete database record
                $del = $db->prepare("DELETE FROM izin WHERE user_id = ? AND date = ?");
                $del->execute([$user_db_id, $date]);
            }

            // Return success JSON
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit();
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Check admin role for page access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'] ?? $_SESSION['username'];

// Fetch admin NIP from database
$admin_nip = '';
$stmt_nip = $db->prepare('SELECT nip FROM users WHERE id = :user_id');
$stmt_nip->bindValue(':user_id', $user_id, PDO::PARAM_INT);
$stmt_nip->execute();
$result_nip = $stmt_nip->fetch(PDO::FETCH_ASSOC);
if ($result_nip) {
    $admin_nip = $result_nip['nip'] ?? '';
}

// Fetch settings for school name
$settings_stmt = $db->query("SELECT school_name FROM settings LIMIT 1");
$settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
$school_name = $settings['school_name'] ?? 'Nama Sekolah Tidak Ditemukan';

$indonesian_months = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember',
];

// Ensure tables exist
try {
    $db->query("SELECT 1 FROM holidays LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false) {
        $db->exec("CREATE TABLE holidays (id INTEGER PRIMARY KEY AUTOINCREMENT, date TEXT NOT NULL UNIQUE, description TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    }
}
try {
    $db->query("SELECT 1 FROM tanggal_cetak LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'no such table') !== false) {
        $db->exec("CREATE TABLE tanggal_cetak (id INTEGER PRIMARY KEY CHECK (id = 1), tanggal TEXT)");
        $db->exec("INSERT INTO tanggal_cetak (id, tanggal) VALUES (1, '')");
    }
}

// Get current print date
$stmt = $db->query("SELECT tanggal FROM tanggal_cetak WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$current_tanggal_cetak = $row['tanggal'] ?? '';

$formatted_tanggal_cetak = '';
if (!empty($current_tanggal_cetak)) {
    $ts = strtotime($current_tanggal_cetak);
    $day = date('j', $ts);
    $month_num = date('m', $ts);
    $year = date('Y', $ts);
    $month_name = $indonesian_months[$month_num] ?? $month_num;
    $formatted_tanggal_cetak = $day . ' ' . $month_name . ' ' . $year;
} else {
    $ts = time();
    $day = date('j', $ts);
    $month_num = date('m', $ts);
    $year = date('Y', $ts);
    $month_name = $indonesian_months[$month_num] ?? $month_num;
    $formatted_tanggal_cetak = $day . ' ' . $month_name . ' ' . $year;
}

// Filters
$month_filter = $_GET['month'] ?? date('Y-m');
$jumlah_hari = $_GET['jumlah_hari'] ?? date('t', strtotime($month_filter));

$year = date('Y', strtotime($month_filter));
$month_num_filter = date('m', strtotime($month_filter));
$month_name = $indonesian_months[$month_num_filter] ?? $month_num_filter;
$formatted_month_year = $month_name . ' ' . $year;

// Fetch Holidays
$holidays = [];
$stmt = $db->prepare("SELECT date, description FROM holidays WHERE strftime('%Y-%m', date) = ?");
$stmt->execute([$month_filter]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $holidays[$row['date']] = $row['description'];
}

// Fetch Gurus
$guru_users = [];
$stmt = $db->query("SELECT username, full_name, nip FROM users WHERE role = 'guru' ORDER BY username");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $guru_users[$row['username']] = $row;
}

// Fetch Attendance
$records = [];
// Absen records
$stmt = $db->prepare("
    SELECT u.username, u.full_name, u.nip, a.date, a.jam_masuk, a.jam_pulang, a.status, a.durasi, 
           a.lokasi_lat, a.lokasi_lng, a.jarak, a.keterangan, a.foto_masuk, a.foto_pulang, 'absen' AS type
    FROM users u
    JOIN attendance a ON u.id = a.user_id 
    WHERE u.role = 'guru' AND strftime('%Y-%m', a.date) = ?
");
$stmt->execute([$month_filter]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['full_name'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
    $row['nip'] = $row['nip'] ?? '-';
    $records[] = $row;
}
// Izin records
$stmt = $db->prepare("
    SELECT u.username, u.full_name, u.nip, i.date, NULL AS jam_masuk, NULL AS jam_pulang,
           'Izin' AS status,
           NULL AS durasi, i.lokasi_lat, i.lokasi_lng, i.jarak,
           i.keterangan, i.foto AS foto_masuk, 'izin' AS type
    FROM users u
    JOIN izin i ON u.id = i.user_id 
    WHERE u.role = 'guru' AND strftime('%Y-%m', i.date) = ? AND i.status = 'approved'
");
$stmt->execute([$month_filter]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['full_name'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
    $row['nip'] = $row['nip'] ?? '-';
    $records[] = $row;
}

// Sort records: Date ASC, then Username ASC
usort($records, function ($a, $b) {
    if ($a['date'] === $b['date']) {
        return strnatcasecmp($a['username'], $b['username']);
    }
    return strtotime($a['date']) - strtotime($b['date']);
});

// Flatten for Daily Summary
$daily_summary = [];
foreach ($guru_users as $user) {
    $daily_summary[$user['username']] = [
        'name' => $user['full_name'] ?? $user['username'],
        'nip' => $user['nip'] ?? '-',
        'days' => array_fill(1, 31, ['jam_masuk' => 'ABSEN', 'jam_pulang' => 'ABSEN'])
    ];
}

foreach ($records as $r) {
    $u = $r['username'];
    $d = (int) date('d', strtotime($r['date']));
    if (isset($daily_summary[$u])) {
        if ($r['type'] === 'absen') {
            $daily_summary[$u]['days'][$d] = ['jam_masuk' => $r['jam_masuk'], 'jam_pulang' => $r['jam_pulang']];
        } else {
            $daily_summary[$u]['days'][$d] = ['jam_masuk' => 'izin', 'jam_pulang' => 'izin'];
        }
    }
}

// Apply Holidays
for ($i = 1; $i <= 31; $i++) {
    $d_str = sprintf('%04d-%02d-%02d', $year, $month_num_filter, $i);
    if (isset($holidays[$d_str])) {
        foreach ($daily_summary as &$u) {
            if ($u['days'][$i]['jam_masuk'] === 'ABSEN') {
                $u['days'][$i] = ['jam_masuk' => $holidays[$d_str], 'jam_pulang' => $holidays[$d_str]];
            }
        }
    }
}
ksort($daily_summary);

// Stats
$summary = [];
$on_time_threshold = strtotime('07:00:00');
foreach ($guru_users as $user) {
    $summary[$user['username']] = [
        'name' => $user['full_name'] ?? $user['username'],
        'nip' => $user['nip'] ?? '-',
        'tepat_waktu' => 0,
        'terlambat' => 0,
        'izin' => 0,
        'libur' => 0
    ];
}
foreach ($records as $rec) {
    $u = $rec['username'];
    if (isset($summary[$u])) {
        if ($rec['type'] === 'absen') {
            if (isset($rec['status']) && $rec['status'] === 'Libur') {
                $summary[$u]['libur']++;
                continue;
            }
            if (!empty($rec['jam_masuk'])) {
                if (strtotime($rec['jam_masuk']) <= $on_time_threshold)
                    $summary[$u]['tepat_waktu']++;
                else
                    $summary[$u]['terlambat']++;
            } else {
                $summary[$u]['terlambat']++;
            }
        } elseif ($rec['type'] === 'izin') {
            $summary[$u]['izin']++;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Rekap Absensi - Exact Copy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        black: '#1E293B',
                        white: '#F9FAFB',
                        success: '#A7F3D0',
                        warning: '#FDE68A',
                        secondary: '#E0E7FF',
                        primary: '#BFDBFE',
                        danger: '#FCA5A5',
                        gray: { 100: '#F3F4F6' }
                    },
                    boxShadow: {
                        card: '0 4px 6px -1px rgba(147, 197, 253, 0.5), 0 2px 4px -1px rgba(147, 197, 253, 0.3)',
                        'card-hover': '0 10px 15px -3px rgba(147, 197, 253, 0.6), 0 4px 6px -2px rgba(147, 197, 253, 0.4)',
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 5mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                -webkit-print-color-adjust: exact;
                padding: 0 !important;
                margin: 0 !important;
            }

            .print-container {
                width: 100% !important;
                max-width: 100% !important;
                border: none !important;
                box-shadow: none !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 9px !important;
            }

            th,
            td {
                border: 1px solid black !important;
                padding: 1px 2px !important;
            }

            th {
                background-color: transparent !important;
                font-weight: bold !important;
                text-align: center !important;
            }

            /* Remove background colors for print */
            tr,
            td,
            th {
                background: transparent !important;
                background-color: transparent !important;
            }

            /* Print Header */
            .print-header {
                display: block !important;
                text-align: center;
                margin-top: 10px;
                margin-bottom: 15px;
                border-bottom: 2px solid black;
                padding-bottom: 5px;
            }

            .print-header h1 {
                font-size: 12pt;
                font-weight: bold;
                margin: 0;
                line-height: 1.2;
                text-transform: uppercase;
            }

            .print-header h2 {
                font-size: 11pt;
                font-weight: bold;
                margin: 0;
                line-height: 1.2;
            }

            .page-break {
                page-break-before: always;
                display: block;
                height: 0;
                overflow: hidden;
            }
        }

        .status-indicator {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>

<body class="min-h-screen bg-white font-sans text-black">
    <!-- Header -->
    <header class="bg-black text-white shadow-md no-print">
        <div class="max-w-7xl mx-auto px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold">Rekap Absensi, <?= htmlspecialchars($fullname) ?></h1>
                </div>
                <div class="flex items-center gap-3">
                    <a href="admin_absensi.php"
                        class="bg-white/10 hover:bg-white/20 text-white border border-white/20 py-1.5 px-3 rounded-md text-sm transition-all">Dashboard</a>
                    <a href="../logout.php"
                        class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 py-1.5 px-3 rounded-md text-sm transition-all">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <main class="p-4 md:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Filter -->
            <div class="bg-white rounded-xl shadow-card p-4 no-print">
                <h2 class="text-lg font-semibold mb-3 text-black">Filter Data</h2>
                <form method="GET" class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="block text-xs font-medium mb-1">Pilih Bulan</label>
                        <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>"
                            class="border rounded px-2 py-1 text-sm bg-white text-black border-black">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1">Jumlah Hari</label>
                        <input type="number" name="jumlah_hari" value="<?= htmlspecialchars($jumlah_hari) ?>"
                            class="border rounded px-2 py-1 text-sm w-20 bg-white text-black border-black">
                    </div>
                    <button type="submit"
                        class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Filter</button>
                    <button type="button" onclick="window.location.reload()"
                        class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Refresh</button>
                    <!-- Added Daily Recap Button -->
                    <a href="../utils/daily_recap.php"
                        class="bg-green-50 text-green-600 border border-green-200 hover:bg-green-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Daily
                        Recap</a>
                    <button type="button" id="selectPrintDateBtn"
                        class="bg-orange-50 text-orange-600 border border-orange-200 hover:bg-orange-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Pilih
                        Tanggal Cetak</button>
                    <button type="button" onclick="window.print()"
                        class="bg-purple-50 text-purple-600 border border-purple-200 hover:bg-purple-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors ml-auto">Print</button>
                </form>
            </div>

            <!-- Stats -->
            <!-- Stats -->
            <?php
            $tot_h = array_sum(array_column($summary, 'tepat_waktu'));
            $tot_t = array_sum(array_column($summary, 'terlambat'));
            $tot_i = array_sum(array_column($summary, 'izin'));
            $tot_l = array_sum(array_column($summary, 'libur'));
            ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 no-print">
                <div class="bg-white p-4 rounded-xl shadow-card border-l-4 border-green-500">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Tepat Waktu</p>
                    <p class="text-3xl font-bold text-green-600 mt-1"><?= $tot_h ?></p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-card border-l-4 border-yellow-500">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Terlambat</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-1"><?= $tot_t ?></p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-card border-l-4 border-blue-500">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Izin</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1"><?= $tot_i ?></p>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-card border-l-4 border-purple-500">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Libur</p>
                    <p class="text-3xl font-bold text-purple-600 mt-1"><?= $tot_l ?></p>
                </div>
            </div>



            <!-- Print Only Header (Hidden on Screen) -->
            <div class="print-header hidden">
                <h1>PEMERINTAH KABUPATEN LAMONGAN</h1>
                <h1>DINAS PENDIDIKAN</h1>
                <h2><?= htmlspecialchars(strtoupper($school_name)) ?></h2>
            </div>

            <!-- Wide Summary Table -->
            <div class="bg-white rounded-xl shadow-card overflow-hidden print-container">
                <div class="px-4 py-3 border-b border-black">
                    <h2 class="text-lg font-bold text-black">Rekap Detail Bulan
                        <?= htmlspecialchars($formatted_month_year) ?>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-black"
                        style="font-family: Arial; font-size: 11px;">
                        <thead>
                            <tr>
                                <th rowspan="3" class="border border-black p-1 w-6">No</th>
                                <th rowspan="3" class="border border-black p-1 w-32 text-left">Nama Guru</th>
                                <th rowspan="3" class="border border-black p-1 w-8">Ket</th>
                                <th colspan="<?= intval($jumlah_hari) ?>" class="border border-black p-1 text-center">
                                    Tanggal</th>
                            </tr>
                            <tr>
                                <?php for ($d = 1; $d <= intval($jumlah_hari); $d++): ?>
                                    <th class="border border-black p-0 text-center font-normal w-6"><?= $d ?></th>
                                <?php endfor; ?>
                            </tr>

                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($daily_summary as $k => $u): ?>
                                <tr>
                                    <td rowspan="3" class="border border-black text-center"><?= $no++ ?></td>
                                    <td class="border border-black text-left p-1 font-bold truncate max-w-[120px]"
                                        title="<?= htmlspecialchars($u['name']) ?>"><?= htmlspecialchars($u['name']) ?></td>
                                    <td class="border border-black text-center p-0.5 text-[10px]">DTG</td>
                                    <?php for ($d = 1; $d <= intval($jumlah_hari); $d++):
                                        $val = $u['days'][$d]['jam_masuk'] ?? 'ABSEN';
                                        $disp = ($val !== 'ABSEN' && $val !== 'izin') ? substr($val, 0, 5) : ($val === 'izin' ? 'IZIN' : 'ABS');
                                        // Handle overflow for print
                                        if (strlen($disp) > 5)
                                            $disp = substr($disp, 0, 4);
                                        ?>
                                        <td class="border border-black text-center p-0 text-[9px]">
                                            <?= htmlspecialchars($disp) ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="border border-black text-left p-1 text-[10px]">
                                        NIP. <?= htmlspecialchars($u['nip']) ?>
                                    </td>
                                    <td class="border border-black text-center p-0.5 text-[10px]">PLG</td>
                                    <?php for ($d = 1; $d <= intval($jumlah_hari); $d++):
                                        $val = $u['days'][$d]['jam_pulang'] ?? 'ABSEN';
                                        $disp = ($val !== 'ABSEN' && $val !== 'izin') ? substr($val, 0, 5) : ($val === 'izin' ? 'IZIN' : 'ABS');
                                        if (strlen($disp) > 5)
                                            $disp = substr($disp, 0, 4);
                                        ?>
                                        <td class="border border-black text-center p-0 text-[9px]">
                                            <?= htmlspecialchars($disp) ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="border border-black p-0">&nbsp;</td>
                                    <td class="border border-black text-center p-0 text-[9px]">TTD</td>
                                    <?php for ($d = 1; $d <= intval($jumlah_hari); $d++): ?>
                                        <td class="border border-black">&nbsp;</td><?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Signature for Page 1 -->
            <div class="hidden print:block mt-8">
                <div class="flex justify-end w-full">
                    <div class="mr-20 text-left text-[11px]">
                        <p class="mb-1">Lamongan, <?= $formatted_tanggal_cetak ?></p>
                        <p class="mb-8">Kepala Sekolah</p>
                        <div class="h-10"></div>
                        <p class="font-bold"><?= htmlspecialchars($fullname) ?></p>
                        <p class="mt-0">NIP. <?= htmlspecialchars($admin_nip ?: '...........................') ?></p>
                    </div>
                </div>
            </div>

            <div class="page-break hidden print:block"></div>

            <!-- Header for Page 2 -->
            <div class="print-header hidden">
                <h1>PEMERINTAH KABUPATEN LAMONGAN</h1>
                <h1>DINAS PENDIDIKAN</h1>
                <h2><?= htmlspecialchars(strtoupper($school_name)) ?></h2>
            </div>

            <!-- Rekap Detail Bulan Table -->
            <div class="bg-white rounded-xl shadow-card overflow-hidden animate-fade-in">
                <div class="px-6 py-4 border-b border-black">
                    <h2 class="text-xl font-bold text-black">
                        Rekap Detail Bulan <?= htmlspecialchars($formatted_month_year) ?>
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table id="summaryTable" class="min-w-full divide-y divide-black border border-black">
                        <thead>
                            <tr class="bg-white border-b border-black">
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                    No</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                    Nama Guru</th>
                                <th scope="col"
                                    class="px-3 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Tepat Waktu
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Terlambat
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Izin
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Libur
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Tidak Absen
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    Total
                                </th>
                                <th scope="col"
                                    class="px-6 py-3 text-center text-xs font-medium text-black uppercase tracking-wider">
                                    TTD</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-black">
                            <?php $no = 1; ?>
                            <?php if (count($summary) === 0): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-4 text-center text-black italic">
                                        <div class="flex flex-col items-center justify-center py-6">
                                            <p>Tidak ada data rekap untuk bulan ini.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($summary as $user_key => $data): ?>
                                    <?php
                                    // Hitung tidak absen
                                    $jumlah_hari_int = intval($jumlah_hari);
                                    $tidak_absen = $jumlah_hari_int - $data['tepat_waktu'] - $data['terlambat'] - $data['izin'] - $data['libur'];
                                    if ($tidak_absen < 0) {
                                        $tidak_absen = 0;
                                    }
                                    $total = $jumlah_hari_int;
                                    $nip = $data['nip'] ?? '-';
                                    $name = $data['name'] ?? '-';
                                    ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?= $no++ ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-black text-left">
                                            <?= htmlspecialchars($name) ?><br /><small class="text-xs text-gray-600">NIP:
                                                <?= htmlspecialchars($nip) ?></small>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 text-center">
                                            <?= $data['tepat_waktu'] ?>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-yellow-600 text-center">
                                            <?= $data['terlambat'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-blue-600 text-center">
                                            <?= $data['izin'] ?>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-purple-600 text-center">
                                            <?= $data['libur'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-400 text-center">
                                            <?= $tidak_absen ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-black text-center">
                                            <?= $total ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap w-64">
                                            <div class="border-b border-gray-400 w-12 mx-auto h-8"></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detail List Table (Visible on Desktop) -->
            <div class="bg-white rounded-xl shadow-card overflow-hidden no-print hidden md:block">
                <div class="px-6 py-4 border-b border-black">
                    <h2 class="text-xl font-bold text-black">Data Absensi Detil</h2>
                </div>
                <div class="overflow-x-auto max-h-96 overflow-y-auto">
                    <table class="min-w-full divide-y divide-black">
                        <thead>
                            <tr class="bg-white">
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Nama Guru</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Jam</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Durasi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-black uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($records as $idx => $r): ?>
                                <tr class="hover:bg-gray-100 transition-colors">
                                    <td class="px-6 py-4 text-sm text-black"><?= $idx + 1 ?></td>
                                    <td class="px-6 py-4 text-sm text-black font-medium">
                                        <?= htmlspecialchars($r['full_name']) ?><br>
                                        <span class="text-xs text-gray-500">NIP: <?= htmlspecialchars($r['nip']) ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-black"><?= htmlspecialchars($r['date']) ?></td>
                                    <td class="px-6 py-4 text-sm text-black">
                                        IN: <?= substr($r['jam_masuk'] ?? '', 0, 5) ?: '-' ?><br>
                                        OUT: <?= substr($r['jam_pulang'] ?? '', 0, 5) ?: '-' ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-black"><?= htmlspecialchars($r['durasi'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php
                                        $s = $r['status'] ?? '-';
                                        $cls = 'bg-gray-100 text-gray-800'; // Default soft gray
                                        if ($s == 'Hadir')
                                            $cls = 'bg-green-100 text-green-800 border border-green-200';
                                        elseif ($s == 'Terlambat')
                                            $cls = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                        elseif ($s == 'Izin' || $s == 'Sakit')
                                            $cls = 'bg-blue-100 text-blue-800 border border-blue-200';
                                        elseif ($s == 'Libur')
                                            $cls = 'bg-purple-100 text-purple-800 border border-purple-200';
                                        echo "<span class='status-indicator $cls'>$s</span>";
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm flex gap-2">
                                        <button onclick='showDetailModal(<?= json_encode($r) ?>)'
                                            class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Detail</button>
                                        <button onclick='confirmDelete(<?= json_encode($r) ?>)'
                                            class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal Detail -->
    <div id="detailModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg max-w-3xl w-full p-6 space-y-4 relative max-h-[90vh] overflow-y-auto">
            <button onclick="document.getElementById('detailModal').classList.add('hidden')"
                class="absolute top-4 right-4 text-gray-500 hover:text-black">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <h3 class="text-xl font-bold text-black border-b pb-2">Detail Absensi</h3>
            <div id="modalContent" class="space-y-3 text-sm text-black">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div id="deleteModal" class="fixed inset-0 bg-black/70 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6 space-y-4 relative">
            <h3 class="text-xl font-bold text-black border-b pb-2">Konfirmasi Hapus</h3>
            <p class="text-black">Apakah Anda yakin ingin menghapus data absensi ini?</p>
            <div id="deleteInfo" class="text-sm text-gray-600 bg-gray-100 p-2 rounded"></div>
            <div class="flex justify-end gap-3 mt-4">
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')"
                    class="px-4 py-2 border rounded hover:bg-gray-50">Batal</button>
                <button id="confirmDeleteBtn"
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Hapus</button>
            </div>
        </div>
    </div>

    <!-- Footer Signature (Print) -->
    <div class="hidden print:block mt-8">
        <div class="flex justify-end w-full">
            <div class="mr-20 text-left text-[11px]">
                <p class="mb-1">Lamongan, <?= $formatted_tanggal_cetak ?></p>
                <p class="mb-8">Kepala Sekolah</p>
                <div class="h-10"></div>
                <p class="font-bold"><?= htmlspecialchars($fullname) ?></p>
                <p class="mt-0">NIP. <?= htmlspecialchars($admin_nip ?: '...........................') ?></p>
            </div>
        </div>
    </div>

    <!-- Modal for selecting print date -->
    <div id="printDateModal"
        class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 no-print">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4 text-black">Pilih Tanggal Cetak</h3>
            <input type="date" id="printDateInput" class="w-full p-2 border border-gray-300 rounded mb-4">
            <div class="flex justify-end gap-2">
                <button id="cancelPrintDateBtn"
                    class="px-4 py-2 bg-gray-300 text-black rounded hover:bg-gray-400">Batal</button>
                <button id="confirmPrintDateBtn"
                    class="px-4 py-2 bg-black text-white rounded hover:bg-gray-800">Konfirmasi</button>
            </div>
        </div>
    </div>

    <script>
        function showDetailModal(data) {
            const container = document.getElementById('modalContent');

            // Fix photo paths - check if already contains '../'
            const fotoMasukPath = data.foto_masuk ?
                (data.foto_masuk.startsWith('../') ? data.foto_masuk : '../' + data.foto_masuk) : null;
            const fotoPulangPath = data.foto_pulang ?
                (data.foto_pulang.startsWith('../') ? data.foto_pulang : '../' + data.foto_pulang) : null;

            // Create embedded map iframe if location exists
            const mapHtml = data.lokasi_lat ?
                `<iframe 
                    width="100%" 
                    height="250" 
                    frameborder="0" 
                    style="border:0; border-radius: 8px;" 
                    src="https://maps.google.com/maps?q=${data.lokasi_lat},${data.lokasi_lng}&z=15&output=embed"
                    allowfullscreen>
                </iframe>`
                : '<p class="text-gray-500 italic">Tidak ada data lokasi</p>';

            const html = `
                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="font-bold text-gray-700">Nama:</div><div class="text-gray-900">${data.full_name || data.username}</div>
                    <div class="font-bold text-gray-700">NIP:</div><div class="text-gray-900">${data.nip}</div>
                    <div class="font-bold text-gray-700">Tanggal:</div><div class="text-gray-900">${data.date}</div>
                    <div class="font-bold text-gray-700">Status:</div><div class="text-gray-900">${data.status}</div>
                    <div class="font-bold text-gray-700">Jam Masuk:</div><div class="text-gray-900">${data.jam_masuk ? data.jam_masuk.substring(0, 5) : '-'}</div>
                    <div class="font-bold text-gray-700">Jam Pulang:</div><div class="text-gray-900">${data.jam_pulang ? data.jam_pulang.substring(0, 5) : '-'}</div>
                    <div class="font-bold text-gray-700">Durasi:</div><div class="text-gray-900">${data.durasi || '-'}</div>
                    <div class="font-bold text-gray-700">Jarak:</div><div class="text-gray-900">${data.jarak ? Math.round(data.jarak) + ' meter' : '-'}</div>
                    <div class="font-bold text-gray-700">Keterangan:</div><div class="text-gray-900">${data.keterangan || '-'}</div>
                </div>
                
                <div class="mb-4">
                    <p class="font-bold text-gray-700 mb-2">Lokasi:</p>
                    ${mapHtml}
                </div>
                
                ${fotoMasukPath ? `
                    <div class="mb-4">
                        <p class="font-bold text-gray-700 mb-2">Foto Masuk:</p>
                        <img src="${fotoMasukPath}" class="w-full max-w-md rounded-lg border shadow-sm" alt="Foto Masuk" 
                            onload="console.log('Foto masuk loaded:', '${fotoMasukPath}')"
                            onerror="console.error('Foto masuk failed:', '${fotoMasukPath}'); this.parentElement.innerHTML = '<p class=\\'text-red-500\\'>Foto tidak ditemukan: ${fotoMasukPath}</p>';">
                    </div>
                ` : '<div class="mb-4"><p class="text-gray-500 italic">Tidak ada foto masuk</p></div>'}
                
                ${fotoPulangPath ? `
                    <div class="mb-4">
                        <p class="font-bold text-gray-700 mb-2">Foto Pulang:</p>
                        <img src="${fotoPulangPath}" class="w-full max-w-md rounded-lg border shadow-sm" alt="Foto Pulang" 
                            onload="console.log('Foto pulang loaded:', '${fotoPulangPath}')"
                            onerror="console.error('Foto pulang failed:', '${fotoPulangPath}'); this.parentElement.innerHTML = '<p class=\\'text-red-500\\'>Foto tidak ditemukan: ${fotoPulangPath}</p>';">
                    </div>
                ` : '<div class="mb-4"><p class="text-gray-500 italic">Tidak ada foto pulang</p></div>'}
            `;
            container.innerHTML = html;
            document.getElementById('detailModal').classList.remove('hidden');
        }

        let recordToDelete = null;

        function confirmDelete(data) {
            recordToDelete = data;
            const info = document.getElementById('deleteInfo');
            info.innerHTML = `
                <strong>${data.full_name}</strong><br>
                Tanggal: ${data.date}<br>
                Status: ${data.status}
            `;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        document.getElementById('confirmDeleteBtn').onclick = function () {
            if (!recordToDelete) return;

            const formData = new FormData();
            formData.append('action', 'delete_record');
            formData.append('date', recordToDelete.date);
            formData.append('username', recordToDelete.username);
            formData.append('type', recordToDelete.type); // 'absen' or 'izin'

            fetch('rekap_absensi.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        location.reload();
                    } else {
                        alert('Gagal menghapus data');
                    }
                })
                .catch(err => console.error(err));
        };

        // Tanggal Cetak functionality
        const tanggalCetak = '<?= htmlspecialchars($current_tanggal_cetak) ?>';

        document.getElementById('selectPrintDateBtn').addEventListener('click', function () {
            document.getElementById('printDateModal').classList.remove('hidden');
            document.getElementById('printDateInput').value = tanggalCetak;
        });

        document.getElementById('cancelPrintDateBtn').addEventListener('click', function () {
            document.getElementById('printDateModal').classList.add('hidden');
        });

        document.getElementById('confirmPrintDateBtn').addEventListener('click', function () {
            const selectedDate = document.getElementById('printDateInput').value;
            if (selectedDate) {
                fetch('rekap_absensi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=save_tanggal_cetak&tanggal=' + encodeURIComponent(selectedDate)
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Tanggal cetak berhasil disimpan!');
                            location.reload();
                        } else {
                            alert('Gagal menyimpan tanggal cetak');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Terjadi kesalahan');
                    });
            }
            document.getElementById('printDateModal').classList.add('hidden');
        });
    </script>

</body>

</html>