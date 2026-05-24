<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new SQLite3('absen.db');

// Map numeric month to Indonesian month name
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

$school_name = '';

// Check if "setting" table exists
$table_check_stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
$table_check_result = $table_check_stmt->execute();
$table_exists = $table_check_result->fetchArray(SQLITE3_ASSOC);

// Check if "holidays" table exists, create if not
$holiday_table_check_stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='holidays'");
$holiday_table_check_result = $holiday_table_check_stmt->execute();
$holiday_table_exists = $holiday_table_check_result->fetchArray(SQLITE3_ASSOC);

if (!$holiday_table_exists) {
    $create_holidays_table = "
        CREATE TABLE holidays (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $db->exec($create_holidays_table);
}

// Check if "tanggal_cetak" table exists, create if not
$tanggal_cetak_table_check_stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='tanggal_cetak'");
$tanggal_cetak_table_check_result = $tanggal_cetak_table_check_stmt->execute();
$tanggal_cetak_table_exists = $tanggal_cetak_table_check_result->fetchArray(SQLITE3_ASSOC);

if (!$tanggal_cetak_table_exists) {
    $create_tanggal_cetak_table = "
        CREATE TABLE tanggal_cetak (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            tanggal TEXT
        )
    ";
    $db->exec($create_tanggal_cetak_table);
    // Insert default row
    $db->exec("INSERT INTO tanggal_cetak (id, tanggal) VALUES (1, '')");
}

// Check if izin table has location columns, add if not
$izin_columns_check = $db->prepare("PRAGMA table_info(izin)");
$izin_columns = [];
$result = $izin_columns_check->execute();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $izin_columns[] = $row['name'];
}
if (!in_array('lokasi_lat', $izin_columns)) {
    $db->exec("ALTER TABLE izin ADD COLUMN lokasi_lat REAL");
}
if (!in_array('lokasi_lng', $izin_columns)) {
    $db->exec("ALTER TABLE izin ADD COLUMN lokasi_lng REAL");
}
if (!in_array('jarak', $izin_columns)) {
    $db->exec("ALTER TABLE izin ADD COLUMN jarak REAL");
}

// Handle POST to save tanggal_cetak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_tanggal_cetak') {
    $tanggal = $_POST['tanggal'] ?? '';
    $stmt = $db->prepare("UPDATE tanggal_cetak SET tanggal = :tanggal WHERE id = 1");
    $stmt->bindValue(':tanggal', $tanggal, SQLITE3_TEXT);
    $result = $stmt->execute();
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update tanggal_cetak']);
    }
    exit();
}

// Load current tanggal_cetak
$current_tanggal_cetak = '';
$stmt = $db->prepare("SELECT tanggal FROM tanggal_cetak WHERE id = 1");
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row) {
    $current_tanggal_cetak = $row['tanggal'];
}

// Format tanggal_cetak to "d F Y" with Indonesian month
$formatted_tanggal_cetak = '';
if (!empty($current_tanggal_cetak)) {
    $date_obj = date_create($current_tanggal_cetak);
    if ($date_obj) {
        $day = date_format($date_obj, 'j');
        $month_num = date_format($date_obj, 'm');
        $year = date_format($date_obj, 'Y');
        $month_name = $indonesian_months[$month_num] ?? $month_num;
        $formatted_tanggal_cetak = $day . ' ' . $month_name . ' ' . $year;
    } else {
        $formatted_tanggal_cetak = $current_tanggal_cetak;
    }
} else {
    // If no tanggal_cetak set, use current date formatted
    $date_obj = date_create();
    $day = date_format($date_obj, 'j');
    $month_num = date_format($date_obj, 'm');
    $year = date_format($date_obj, 'Y');
    $month_name = $indonesian_months[$month_num] ?? $month_num;
    $formatted_tanggal_cetak = $day . ' ' . $month_name . ' ' . $year;
}

if ($table_exists) {
    $setting_stmt = $db->prepare('SELECT school_name FROM settings LIMIT 1');
    if ($setting_stmt) {
        $setting_result = $setting_stmt->execute();
        if ($setting_result) {
            $setting_row = $setting_result->fetchArray(SQLITE3_ASSOC);
            if ($setting_row && !empty($setting_row['school_name'])) {
                $school_name = $setting_row['school_name'];
            }
        }
    }
}

$user_id = $_SESSION['user_id'];

$fullname = '';
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = :user_id');
$user_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$user_result = $user_stmt->execute();
$user_row = $user_result->fetchArray(SQLITE3_ASSOC);
if ($user_row) {
    // Check for full name column "full_name"
    if (!empty($user_row['full_name'])) {
        $fullname = $user_row['full_name'];
    } else {
        // Fallback to formatted username
        $username_raw = $user_row['username'] ?? '';
        $fullname = ucwords(str_replace('_', ' ', $username_raw));
    }
} else {
    $fullname = $_SESSION['username'];
}



$date_filter = $_GET['date'] ?? date('Y-m-d');
$month_filter = $_GET['month'] ?? date('Y-m');

// Calculate the number of days in the selected month first
if (preg_match('/^(\\d{4})-(\\d{2})$/', $month_filter, $matches)) {
    $year = $matches[1];
    $month_num = $matches[2];
} else {
    $year = date('Y');
    $month_num = date('m');
}
$days_in_month = date('t', mktime(0, 0, 0, intval($month_num), 1, intval($year)));

$jumlah_hari = $_GET['jumlah_hari'] ?? $days_in_month;

if (preg_match('/^(\\d{4})-(\\d{2})$/', $month_filter, $matches)) {
    $year = $matches[1];
    $month_num = $matches[2];
} else {
    // Fallback to current year and month if format is invalid
    $year = date('Y');
    $month_num = date('m');
}

// Calculate the number of days in the selected month
$days_in_month = date('t', mktime(0, 0, 0, intval($month_num), 1, intval($year)));

// Get Indonesian month name or fallback to month number
$month_name = $indonesian_months[$month_num] ?? $month_num;

// Formatted month-year string
$formatted_month_year = $month_name . ' ' . $year;
$error = '';
$success = '';



// Auto checkout logic: update attendance records where jam_pulang is NULL and time is past 13:00
//$current_time = date('H:i:s');
//if ($current_time >= '13:00:00') {
    //$stmt = $db->prepare("UPDATE attendance SET jam_pulang = '13:00:00', keterangan = 'Auto checkout', status = 'Auto checkout', durasi = (strftime('%s', '13:00:00') - strftime('%s', jam_masuk)) WHERE date = :date AND jam_pulang IS NULL AND jam_masuk IS NOT NULL");
    //$stmt->bindValue(':date', $date_filter, SQLITE3_TEXT);
    //$stmt->execute();
//}

// Fetch all guru users
$query_guru = "SELECT DISTINCT username, full_name, nip FROM users WHERE role = 'guru' ORDER BY username";
$stmt_guru = $db->prepare($query_guru);
$results_guru = $stmt_guru->execute();
$guru_users = [];
while ($row = $results_guru->fetchArray(SQLITE3_ASSOC)) {
    $guru_users[$row['username']] = $row;
}

// Fetch attendance records for the month filter with user info, including all gurus
$query_absen = "
SELECT u.username, u.full_name, u.nip, a.date, a.jam_masuk, a.jam_pulang, a.status, a.durasi, a.lokasi_lat, a.lokasi_lng, a.jarak, a.keterangan, a.foto_masuk, 'absen' AS type
FROM users u
LEFT JOIN attendance a ON u.id = a.user_id AND strftime('%Y-%m', a.date) = :month
WHERE u.role = 'guru'
";

$stmt_absen = $db->prepare($query_absen);
$stmt_absen->bindValue(':month', $month_filter, SQLITE3_TEXT);
$results_absen = $stmt_absen->execute();

$records = [];
$added = [];
while ($row = $results_absen->fetchArray(SQLITE3_ASSOC)) {
    // Use full_name from database, fallback to username if empty
    $row['full_name'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
    $row['nip'] = $row['nip'] ?? '-';
    // Only add if there is an attendance record (date is not null)
    if (!empty($row['date']) && !in_array($row['username'] . '_' . $row['date'], $added)) {
        $records[] = $row;
        $added[] = $row['username'] . '_' . $row['date'];
    }
}

// Fetch izin records for the month filter with user info, including all gurus
$query_izin = "
SELECT u.username, u.full_name, u.nip, i.date, NULL AS jam_masuk, NULL AS jam_pulang,
       CASE i.jenis_izin
           WHEN 'izin' THEN 'Izin'
           WHEN 'sakit' THEN 'Sakit'
           ELSE 'Izin'
       END AS status,
       NULL AS durasi, i.lokasi_lat, i.lokasi_lng, i.jarak,
       i.keterangan, i.foto, 'izin' AS type
FROM users u
LEFT JOIN izin i ON u.id = i.user_id AND strftime('%Y-%m', i.date) = :month
WHERE u.role = 'guru'
";

$stmt_izin = $db->prepare($query_izin);
$stmt_izin->bindValue(':month', $month_filter, SQLITE3_TEXT);
$results_izin = $stmt_izin->execute();

while ($row = $results_izin->fetchArray(SQLITE3_ASSOC)) {
    $row['full_name'] = !empty($row['full_name']) ? $row['full_name'] : $row['username'];
    $row['nip'] = $row['nip'] ?? '-';
    // Only add if there is an izin record (date is not null)
    if (!empty($row['date']) && !in_array($row['username'] . '_' . $row['date'], $added)) {
        // Map 'foto' field to 'foto_masuk' for consistency
        $row['foto_masuk'] = $row['foto'];
        unset($row['foto']);
        $records[] = $row;
        $added[] = $row['username'] . '_' . $row['date'];
    }
}



// Sort records by date and username
usort($records, function($a, $b) {
    if ($a['date'] === $b['date']) {
        return strcmp($a['username'], $b['username']);
    }
    return strcmp($a['date'], $b['date']);
});

// Build daily summary for harian table
$daily_summary = [];
// Initialize for all guru users
foreach ($guru_users as $user) {
    $user_key = $user['username'];
    $daily_summary[$user_key] = [
        'name' => $user['full_name'] ?? $user['username'],
        'nip' => $user['nip'] ?? '-',
        'days' => []
    ];
}
// Add records to daily summary
foreach ($guru_users as $user) {
    $user_key = $user['username'];
    for ($day = 1; $day <= $days_in_month; $day++) {
        $daily_summary[$user_key]['days'][$day] = [
            'jam_masuk' => 'ABSEN',
            'jam_pulang' => 'ABSEN'
        ];
    }
}
foreach ($records as $record) {
    $user_key = $record['username'];
    $day = (int)date('j', strtotime($record['date']));
    if ($record['type'] === 'absen') {
        $daily_summary[$user_key]['days'][$day] = [
            'jam_masuk' => $record['jam_masuk'],
            'jam_pulang' => $record['jam_pulang']
        ];
    } elseif ($record['type'] === 'izin') {
        // Mark izin days with special status
        $daily_summary[$user_key]['days'][$day] = [
            'jam_masuk' => 'izin',
            'jam_pulang' => 'izin'
        ];
    }
}

// Add holiday markings to daily summary
for ($i = 1; $i <= 31; $i++) {
    $date_str = sprintf('%04d-%02d-%02d', $year, $month_num, $i);
    if (isset($holidays[$date_str])) {
        foreach ($daily_summary as $user_key => &$user) {
            if (!isset($user['days'][$i]) || $user['days'][$i]['jam_masuk'] === 'Tidak Absen') {
                $user['days'][$i] = [
                    'jam_masuk' => $holidays[$date_str],
                    'jam_pulang' => $holidays[$date_str]
                ];
            }
        }
    }
}

// Sort users by username
if (is_array($daily_summary)) {
    ksort($daily_summary);
}

// Function to calculate duration
function calculateDuration($jam_masuk, $jam_pulang) {
    if (empty($jam_masuk) || empty($jam_pulang)) {
        return '-';
    }
    $start = strtotime($jam_masuk);
    $auto_checkout_time = strtotime('13:00:00');
    $end = strtotime($jam_pulang);
    // If jam_pulang is greater than auto checkout time, set end to auto checkout time
    if ($end > $auto_checkout_time) {
        $end = $auto_checkout_time;
    }
    // If jam_masuk is after auto checkout time, duration = 0
    if ($start > $auto_checkout_time) {
        $diff = 0;
    } else {
        $diff = $end - $start;
        if ($diff < 0) {
            $diff = 0;
        }
    }
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Calculate summary for each teacher for the month
$summary = [];
$on_time_threshold = strtotime('07:00:00'); // Define on-time threshold time
$jumlah_hari_int = intval($jumlah_hari);

// Initialize summary for all guru
foreach ($guru_users as $user) {
    $user_key = $user['username'];
    $name = $user['full_name'] ?? $user['username'];
    $nip = $user['nip'] ?? '-';
    $summary[$user_key] = [
        'name' => $name,
        'nip' => $nip,
        'tepat_waktu' => 0,
        'terlambat' => 0,
        'izin' => 0,
    ];
}

// Add counts from records
foreach ($records as $record) {
    $user_key = $record['username'] ?? 'Unknown';
    if (isset($summary[$user_key])) {
        if ($record['type'] === 'absen' && !empty($record['status'])) {
            // Only consider attendance records for on-time and late
            if (!empty($record['jam_masuk'])) {
                $jam_masuk_time = strtotime($record['jam_masuk']);
                if ($jam_masuk_time <= $on_time_threshold) {
                    $summary[$user_key]['tepat_waktu']++;
                } else {
                    $summary[$user_key]['terlambat']++;
                }
            } else {
                // If jam_masuk is empty, count as late
                $summary[$user_key]['terlambat']++;
            }
        }
        // Count izin and sakit from izin records
        if ($record['status'] === 'Izin' || $record['status'] === 'Sakit') {
            $summary[$user_key]['izin']++;
        }
    }
}

// Sort summary by full name
$summary_array = [];
foreach ($summary as $key => $data) {
    $summary_array[] = ['key' => $key, 'data' => $data];
}
usort($summary_array, function($a, $b) {
    return strcmp($a['data']['name'], $b['data']['name']);
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Rekap Absensi - Absen App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
    tailwind.config = {
        theme: {
            extend: {
                animation: {
                    'spin-slow': 'spin 2s linear infinite',
                    'fade-in': 'fadeIn 0.3s ease-in-out',
                    'slide-in': 'slideIn 0.3s ease-in-out',
                    'slide-up': 'slideUp 0.3s ease-in-out',
                },
                keyframes: {
                    fadeIn: {
                        '0%': {
                            opacity: '0'
                        },
                        '100%': {
                            opacity: '1'
                        },
                    },
                    slideIn: {
                        '0%': {
                            transform: 'translateX(100%)'
                        },
                        '100%': {
                            transform: 'translateX(0)'
                        },
                    },
                    slideUp: {
                        '0%': {
                            transform: 'translateY(20px)',
                            opacity: '0'
                        },
                        '100%': {
                            transform: 'translateY(0)',
                            opacity: '1'
                        },
                    },
                },
                boxShadow: {
                    card: '0 4px 6px -1px rgba(147, 197, 253, 0.5), 0 2px 4px -1px rgba(147, 197, 253, 0.3)',
                    'card-hover': '0 10px 15px -3px rgba(147, 197, 253, 0.6), 0 4px 6px -2px rgba(147, 197, 253, 0.4)',
                },
                colors: {
                    black: '#1E293B', // Dark slate blue for text instead of pure black
                    white: '#F9FAFB', // Off-white background
                    gray: {
                        100: '#F3F4F6',
                        300: '#D1D5DB',
                        500: '#6B7280',
                        700: '#374151',
                        900: '#111827',
                    },
                    success: '#A7F3D0',    // Light pastel green
                    warning: '#FDE68A',    // Light pastel yellow
                    secondary: '#E0E7FF',  // Light pastel blue
                    primary: '#BFDBFE',    // Pastel blue
                    danger: '#FCA5A5',     // Pastel red
                },
            },
        },
    }
    </script>
    <style>
    /* Toast notification */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        transition: all 0.3s ease;
        background-color: #000000;
        color: #ffffff;
        padding: 12px 24px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        font-weight: 600;
        font-family: 'Inter', sans-serif;
    }

    .slide-in {
        transform: translateX(0);
    }

    .slide-out {
        transform: translateX(150%);
    }

    /* Modal animations */
    .modal-content {
        animation: modalFade 0.2s ease-out;
        border-radius: 1rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    @keyframes modalFade {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    /* Responsive views */
    @media (max-width: 768px) {
        .desktop-table {
            display: none;
        }

        .mobile-cards {
            display: block;
        }
    }

    @media (min-width: 769px) {
        .desktop-table {
            display: table;
        }

        .mobile-cards {
            display: none;
        }
    }

    /* Print styles */
    @media print {
        @page {
            size: A4;
            margin: 10mm;
        }

        .no-print {
            display: none !important;
        }

        body {
            font-size: 10px !important;
            color: black !important;
            background-color: white !important;
            font-family: Arial, sans-serif !important;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        table,
        th,
        td {
            font-size: 10px !important;
            border: 1px solid black !important;
            white-space: nowrap !important;
        }

        th,
        td {
            padding: 2px !important;
            text-align: left !important;
        }

        th {
            font-weight: 600 !important;
            text-align: center !important;
        }

        /* Specific styles for summary table (Rekap Detail Bulan) */
        #harianTable th:nth-child(2),
        #harianTable td:nth-child(2) {
            width: 200px !important;
            text-align: left !important;
            white-space: nowrap !important;
            font-size: 10px !important;
        }

        #harianTable th:nth-child(1),
        #harianTable td:nth-child(1) {
            width: 30px !important;
            text-align: center !important;
            white-space: nowrap !important;
            font-size: 10px !important;
        }

        #harianTable td:nth-child(2) small {
            font-size: 9px !important;
        }

        /* Specific styles for harian table columns */
        .harian-table th:nth-child(n+4),
        .harian-table td:nth-child(n+4) {
            min-width: 8px !important;
            width: auto !important;
        }
    }

    .vertical-text {
        /* Remove vertical text orientation for print and preview */
        writing-mode: horizontal-tb !important;
        text-orientation: initial !important;
        transform: none !important;
        white-space: normal !important;
    }

    /* Preview modal styles for datang and pulang columns */
    #harianModal table td:nth-child(3n+3),
    #harianModal table td:nth-child(3n+4) {
        font-size: 12px !important;
        height: 30px !important;
        line-height: 30px !important;
    }

    #harianModal table th:nth-child(3n+3),
    #harianModal table th:nth-child(3n+4) {
        font-size: 12px !important;
        color: black !important;
    }
    </style>
</head>

<body class="min-h-screen bg-white font-sans text-black">
    <script>
    const tanggalCetak = '<?= htmlspecialchars($formatted_tanggal_cetak) ?>';
    </script>
    <!-- Toast Notification -->
    <div id="toast" class="toast hidden animate-slide-in no-print">
        <div class="bg-black text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
            <span id="toast-message">Operation successful</span>
        </div>
    </div>

    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-black text-white shadow-md no-print">
            <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight">Rekap Absensi,</h1>
                        <h1 class="text-2xl font-bold tracking-tight"><?= htmlspecialchars($fullname) ?></h1>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                                <a href="admin_home.php"
                                    class="w-full sm:w-auto bg-white/20 hover:bg-primary text-white py-2 px-4 rounded-lg transition-all flex items-center justify-center gap-2">
                                    <span class="sm:inline">Dashboard</span>
                                </a>
                                <a href="logout.php"
                                    class="w-full sm:w-auto bg-primary hover:bg-secondary text-white py-2 px-4 rounded-lg transition-all flex items-center justify-center gap-2">
                                    <span class="sm:inline">Logout</span>
                                </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow">
            <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8 space-y-8">
                <!-- Filter Form -->
                <div class="bg-white rounded-xl shadow-card p-6 animate-fade-in">
                    <h2 class="text-xl font-semibold text-black mb-4 flex items-center gap-2">

                        Filter Data
                    </h2>
                    <form method="GET" action="rekap_absensi.php" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="col-span-1">
                                <label for="month" class="block text-sm font-medium text-black mb-1">Pilih
                                    Bulan</label>
                                <input type="month" id="month" name="month"
                                    value="<?= htmlspecialchars($month_filter) ?>"
                                    class="w-full px-4 py-2 border border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-all" />
                            </div>
                            <div class="col-span-1">
                                <label for="jumlah_hari" class="block text-sm font-medium text-black mb-1">Jumlah
                                    Hari</label>
                                <input type="number" id="jumlah_hari" name="jumlah_hari"
                                    value="<?= htmlspecialchars($jumlah_hari) ?>" min="1" max="31"
                                    class="w-full px-4 py-2 border border-black rounded-lg focus:ring-2 focus:ring-black focus:border-black transition-all" />
                            </div>
                            <div class="col-span-2 flex items-end space-x-3 no-print">
                                <button type="submit"
                                    class="flex-1 md:flex-none bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">

                                    <span>Filter</span>
                                </button>
                                <button type="button" id="refreshBtn"
                                    class="flex-1 md:flex-none bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">

                                    <span>Refresh</span>
                                </button>
                                <button type="button" id="previewBtn"
                                    class="flex-1 md:flex-none bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">
                                    <span>Preview</span>
                                </button>
                                <a href="daily_recap.php"
                                    class="flex-1 md:flex-none bg-primary hover:bg-secondary text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">
                                    <span>Daily Recap</span>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                    <?php
                    $total_hadir = 0;
                    $total_terlambat = 0;
                    $total_izin = 0;
                    
                    foreach ($summary as $data) {
                        $total_hadir += $data['tepat_waktu'];
                        $total_terlambat += $data['terlambat'];
                        $total_izin += $data['izin'];
                    }
                    ?>
                    <div
                        class="bg-white p-6 rounded-xl shadow-card hover:shadow-card-hover transition-all border-l-4 border-black">
                        <div class="flex items-start justify-between">
                            <div>
                <p class="text-sm font-medium text-primary">Total Tepat Waktu</p>
                <p class="text-3xl font-bold text-primary mt-1">
                    <?= $total_hadir ?>
                </p>
            </div>
            <div class="p-3 bg-success rounded-full border border-success text-white text-xl font-bold text-center">
                OK
            </div>
        </div>
    </div>
    <div
        class="bg-white p-6 rounded-xl shadow-card hover:shadow-card-hover transition-all border-l-4 border-warning">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-warning">Total Terlambat</p>
                <p class="text-3xl font-bold text-warning mt-1">
                    <?= $total_terlambat ?>
                </p>
            </div>
            <div class="p-3 bg-warning rounded-full border border-warning text-black text-xl font-bold text-center">
                Late
            </div>
        </div>
    </div>
    <div
        class="bg-white p-6 rounded-xl shadow-card hover:shadow-card-hover transition-all border-l-4 border-secondary">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm font-medium text-secondary">Total Izin</p>
                <p class="text-3xl font-bold text-secondary mt-1">
                    <?= $total_izin ?>
                </p>
            </div>
            <div class="p-3 bg-secondary rounded-full border border-secondary text-white text-xl font-bold text-center">
                Izin
            </div>
        </div>
    </div>
</div>

                <!-- Summary Table -->
                <div class="bg-white rounded-xl shadow-card overflow-hidden animate-fade-in">
                    <div class="px-6 py-4 border-b border-black">
                        <h2 class="text-xl font-bold text-black">
                            Rekap Detail Bulan <?= htmlspecialchars($formatted_month_year) ?>
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="harianTable" class="min-w-full divide-y divide-black border border-black">
                            <thead>
                                <tr class="bg-white border-b border-black">
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        No</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Nama Guru</th>
                                    <th scope="col"
                                        class="px-3 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Tepat Waktu
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Terlambat
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Izin
                                    </th>
                                    <!-- Kolom baru: Tidak Absen -->
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Tidak Absen
                                    </th>
                                    <!-- Kolom baru: Total -->
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Total
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
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
                                // Hitung total per baris
                                $tidak_absen = $jumlah_hari_int - $data['tepat_waktu'] - $data['terlambat'] - $data['izin'];
                                if ($tidak_absen < 0) {
                                    $tidak_absen = 0;
                                }
                                $total = $jumlah_hari_int;
                                $nip = $data['nip'] ?? '-';
                                $name = $data['name'] ?? '-';
                            ?>
                                <tr class="hover:bg-primary/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?= $no++ ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-black text-left">
                                        <?= htmlspecialchars($name) ?><br /><small class="text-xs text-black">NIP:
                                            <?= htmlspecialchars($nip) ?></small></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-success">
                                            <?= $data['tepat_waktu'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-warning">
                                            <?= $data['terlambat'] ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-secondary">
                                            <?= $data['izin'] ?>
                                        </td>
                                        <!-- Kolom Tidak Absen -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-400">
                                            <?= $tidak_absen ?>
                                        </td>
                                        <!-- Kolom Total -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-black">
                                            <?= $total ?>
                                        </td>
                                        <!-- Kolom TTD -->
                                        <td class="px-6 py-4 whitespace-nowrap w-64">
                                            <div class="border-b border-gray-400 w-12 mx-auto h-8"></div>
                                        </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- Baris Total Keseluruhan (Opsional) -->
                                <?php
                                        $total_tepat_waktu = array_sum(array_column($summary, 'tepat_waktu'));
                                        $total_terlambat = array_sum(array_column($summary, 'terlambat'));
                                        $total_izin = array_sum(array_column($summary, 'izin'));
                                        $tidak_absen_total = 0;
                                        foreach ($summary as $data) {
                                            $tidak_absen = $jumlah_hari_int - $data['tepat_waktu'] - $data['terlambat'] - $data['izin'];
                                            if ($tidak_absen < 0) $tidak_absen = 0;
                                            $tidak_absen_total += $tidak_absen;
                                        }
                                        $total_semua = $jumlah_hari_int * count($summary);
                                    ?>
                                <tr class="bg-white font-semibold">
                                    <td colspan="2" class="px-6 py-4 text-left text-sm text-black">Total Semua</td>
                                    <td class="px-6 py-4 text-sm text-black"><?= $total_tepat_waktu ?></td>
                                    <td class="px-6 py-4 text-sm text-black"><?= $total_terlambat ?></td>
                                    <td class="px-6 py-4 text-sm text-black"><?= $total_izin ?></td>
                                    <td class="px-6 py-4 text-sm text-black"><?= $tidak_absen_total ?></td>
                                    <td class="px-6 py-4 text-sm text-black"><?= $total_semua ?></td>
                                    <td class="px-6 py-4 text-sm text-black"></td>
                                </tr>

                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Desktop Table View -->
                <div class="desktop-table bg-white rounded-xl shadow-card overflow-hidden animate-fade-in">
                    <div class="px-6 py-4 border-b border-black">
                        <h2 class="text-xl font-bold text-black flex items-center gap-2">

                            Data Absensi
                        </h2>
                    </div>
                    <div class="overflow-x-auto max-h-96 overflow-y-auto">
                        <table class="min-w-full divide-y divide-black harian-table">
                            <thead>
                                <tr class="bg-white">
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        No</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Nama Guru</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Tanggal</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Jam</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Status</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Durasi</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        Aksi</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-black uppercase tracking-wider">
                                        TTD</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-black">
                                <?php if (count($records) === 0): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-black italic">
                                        <div class="flex flex-col items-center justify-center py-6">

                                            <p>Tidak ada data absensi untuk bulan ini.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($records as $index => $record): ?>
                                <tr class="table-row-alternating hover:bg-white transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?= $index + 1 ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap w-64">
                                        <div class="text-sm font-medium text-black">
                                            <?= htmlspecialchars($record['full_name'] ?? ($record['username'] ?? '-')) ?><br /><small>NIP:
                                                <?= htmlspecialchars($record['nip'] ?? '-') ?></small></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-black">
                                        <?= htmlspecialchars($record['date'] ?? '-') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap w-64">
                                        <div class="text-sm text-black space-y-1">
                                            <div class="flex items-center gap-2">

                                                <span><?= htmlspecialchars(substr($record['jam_masuk'] ?? '', 0, 5) ?: '-') ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">

                                                <span><?= htmlspecialchars(substr($record['jam_pulang'] ?? '', 0, 5) ?: '-') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap w-64">
                                        <?php
                                        $on_time_threshold = strtotime('07:00:00');
                                        $status_display = htmlspecialchars($record['status'] ?? '-');
                                        $jam_masuk_time = !empty($record['jam_masuk']) ? strtotime($record['jam_masuk']) : null;
                                        if ($status_display === 'Hadir') {
                                            if ($jam_masuk_time !== null && $jam_masuk_time <= $on_time_threshold) {
                                                // Tepat Waktu badge with icon
                                                echo '<span class="status-indicator bg-success text-white font-semibold">Tepat Waktu</span>';
                                            } else {
                                                // Terlambat badge
                                                echo '<span class="status-indicator bg-warning text-black">Terlambat</span>';
                                            }
                                        } elseif ($status_display === 'Terlambat') {
                                            // Terlambat badge
                                            echo '<span class="status-indicator bg-warning text-black">Terlambat</span>';
                                        } elseif ($status_display === 'Izin' || $status_display === 'Sakit') {
                                            // Izin badge with icon
                                            echo '<span class="status-indicator bg-secondary text-white">' . $status_display . '</span>';
                                        } else {
                                            echo '<span class="status-indicator bg-lightgray text-black">' . $status_display . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-black">
                                        <?= calculateDuration($record['jam_masuk'], $record['jam_pulang']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap no-print">
                                        <div class="flex gap-2">
                                            <button
                                                onclick="showDetailModal(<?= htmlspecialchars(json_encode($record)) ?>)"
                                                class="text-primary hover:text-white hover:bg-primary p-2 rounded-full transition-colors flex items-center justify-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Detail
                                            </button>
                                            <?php if (($record['type'] === 'absen' || $record['type'] === 'izin') && !empty($record['date'])): ?>
                                            <button
                                                onclick="confirmDelete('<?= urlencode($record['username']) ?>', '<?= urlencode($record['date']) ?>', '<?= $record['type'] ?>')"
                                                class="text-danger hover:text-white hover:bg-danger p-2 rounded-full transition-colors flex items-center justify-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                Hapus
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap w-64"></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rekap Absensi Harian -->
                <div class="bg-white rounded-xl shadow-card overflow-hidden animate-fade-in">
                    <div class="px-6 py-4 border-b border-black">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-bold text-black flex items-center gap-2">

                                Rekap Absensi Harian Bulan, diurutkan berdasarkan username
                                <?= htmlspecialchars($formatted_month_year) ?>
                            </h2>
                            <div class="flex gap-2 no-print">
                                <button id="selectPrintDateBtn"
                                    class="bg-primary hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-all flex items-center gap-2 text-sm">
                                    <span id="selectPrintDateBtnText">Pilih Tanggal Cetak</span>
                                </button>
                                <button id="previewBtnHarian"
                                    class="bg-primary hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-all flex items-center gap-2 text-sm">
                                    <span>Preview</span>
                                </button>
                                <button id="printBtnHarian"
                                    class="bg-primary hover:bg-blue-700 text-white px-3 py-2 rounded-lg transition-all flex items-center gap-2 text-sm">
                                    <span>Print</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-black" style="border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th rowspan="3" style="border: 1px solid black; padding: 4px; width: 30px; text-align: center; font-family: Arial, sans-serif; font-size: 12px;">No</th>
                                    <th rowspan="3" style="border: 1px solid black; padding: 4px; width: 150px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;">Nama Guru</th>
                                    <th rowspan="3" style="border: 1px solid black; padding: 1px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">Ket</th>
                                    <th colspan="<?= htmlspecialchars($jumlah_hari) ?>" style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">Tanggal</th>
                                </tr>
                                <tr>
                                    <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                                    <th style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;"><?= $day ?></th>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <th colspan="<?= htmlspecialchars($jumlah_hari) ?>" style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px;">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($daily_summary as $user_key => $user): ?>
                                <tr>
                                    <td rowspan="3" style="border: 1px solid black; padding: 4px; text-align: center; font-family: Arial, sans-serif; font-size: 12px;"><?= $no++ ?></td>
                                    <td class="sub-label" style="border: 1px solid black; padding: 4px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;"><?= htmlspecialchars($user['name']) ?></td>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">DATANG</td>
                                    <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">
                                        <?= isset($user['days'][$day]['jam_masuk']) && $user['days'][$day]['jam_masuk'] !== 'ABSEN' ? htmlspecialchars(substr($user['days'][$day]['jam_masuk'], 0, 5)) : 'ABS' ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="sub-label" style="border: 1px solid black; padding: 4px; text-align: center; font-family: Arial, sans-serif; font-size: 12px;"><?= htmlspecialchars($user['nip']) ?></td>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">PULANG</td>
                                    <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">
                                        <?= isset($user['days'][$day]['jam_pulang']) && $user['days'][$day]['jam_pulang'] !== 'ABSEN' ? htmlspecialchars(substr($user['days'][$day]['jam_pulang'], 0, 5)) : 'ABS' ?>
                                    </td>
                                    <?php endfor; ?>
                                </tr>
                                <tr>
                                    <td class="sub-label" style="border: 1px solid black; padding: 4px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;">&nbsp;</td>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">TTD</td>
                                    <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                                    <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px;">&nbsp;</td>
                                    <?php endfor; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Card View -->
                <div class="mobile-cards space-y-4 animate-fade-in">
                    <?php if (count($records) === 0): ?>
                    <div class="bg-white rounded-xl shadow-card p-6 text-center">
                        <div class="flex flex-col items-center justify-center py-6">

                            <p class="text-black italic">Tidak ada data absensi untuk bulan ini.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($records as $index => $record): ?>
                    <div class="bg-white rounded-xl shadow-card overflow-hidden hover:shadow-card-hover transition-all border-l-4 
                                <?= $record['status'] === 'Izin' ? 'border-black' : 
                                   ($record['status'] === 'Terlambat' ? 'border-black' : 
                                   'border-black') ?>">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-semibold text-black">
                                        <?= htmlspecialchars($record['full_name'] ?? ($record['username'] ?? '-')) ?>
                                    </h3>
                                    <p class="text-sm text-black">NIP: <?= htmlspecialchars($record['nip'] ?? '-') ?>
                                    </p>
                                    <p class="text-sm text-black flex items-center gap-1">
                                        Tanggal: <?= htmlspecialchars($record['date'] ?? '-') ?>
                                    </p>
                                </div>
                                <div>
                                    <?php if ($record['status'] === 'Hadir'): ?>
                                    <span class="status-indicator bg-white text-black">
                                        Hadir
                                    </span>
                                    <?php elseif ($record['status'] === 'Terlambat'): ?>
                                    <span class="status-indicator bg-white text-black">
                                        Terlambat
                                    </span>
                                    <?php elseif ($record['status'] === 'Izin' || $record['status'] === 'Sakit'): ?>
                                    <span class="status-indicator bg-black text-black">
                                        <?= $record['status'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="status-indicator bg-white text-black">

                                        <?= htmlspecialchars($record['status'] ?? '-') ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="bg-white p-2 rounded-lg">
                                    <p class="text-xs text-black">Jam Masuk</p>
                                    <p class="font-medium text-black flex items-center gap-1">

                                        <?= htmlspecialchars(substr($record['jam_masuk'] ?? '', 0, 5) ?: '-') ?>
                                    </p>
                                </div>
                                <div class="bg-white p-2 rounded-lg">
                                    <p class="text-xs text-black">Jam Pulang</p>
                                    <p class="font-medium text-black flex items-center gap-1">

                                        <?= htmlspecialchars(substr($record['jam_pulang'] ?? '', 0, 5) ?: '-') ?>
                                    </p>
                                </div>

                                <?php if ($record['type'] === 'absen'): ?>
                                <div class="bg-white p-2 rounded-lg">
                                    <p class="text-xs text-black">Durasi</p>
                                    <p class="font-medium text-black flex items-center gap-1">

                                        <?= calculateDuration($record['jam_masuk'], $record['jam_pulang']) ?>
                                    </p>
                                </div>
                                <div class="bg-white p-2 rounded-lg">
                                    <p class="text-xs text-black">Jarak</p>
                                    <p class="font-medium text-black flex items-center gap-1">

                                        <?= htmlspecialchars($record['jarak'] ?? '-') ?> m
                                    </p>
                                </div>
                                <?php else: ?>
                                <div class="col-span-2 bg-white p-2 rounded-lg">
                                    <p class="text-xs text-black">Keterangan</p>
                                    <p class="font-medium text-black">
                                        <?= htmlspecialchars($record['keterangan'] ?? '-') ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex justify-end gap-2 mt-2 no-print">
                                <button onclick="showDetailModal(<?= htmlspecialchars(json_encode($record)) ?>)"
                                    class="px-3 py-1.5 text-sm text-black border border-black hover:bg-gray-700 rounded-lg transition-colors flex items-center gap-1">

                                    <span>Detail</span>
                                </button>
                                <?php if (($record['type'] === 'absen' || $record['type'] === 'izin') && !empty($record['date'])): ?>
                                <button
                                    onclick="confirmDelete('<?= urlencode($record['username']) ?>', '<?= urlencode($record['date']) ?>', '<?= $record['type'] ?>')"
                                    class="px-3 py-1.5 text-sm text-black border border-black hover:bg-white rounded-lg transition-colors flex items-center gap-1">

                                    <span>Hapus</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>


            </div>
        </main>

        <!-- Footer -->
        <footer class="mt-auto bg-white border-t border-black no-print">
            <div class="max-w-7xl mx-auto px-4 py-6">
                            <p class="text-sm text-center text-black">
                                &copy; <?= date('Y') ?> Absen App. All rights reserved.
                            </p>
                        </div>
                    </footer>
                </div>

    <!-- Detail Modal -->
    <!-- Detail Modal -->
<div id="detailModal" class="fixed inset-0 bg-black/70 flex items-center justify-center hidden z-50 p-4">
    <div class="modal-content bg-white rounded-xl shadow-lg max-w-md w-full max-h-[90vh] overflow-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6 pb-3 border-b border-primary/70">
                <h3 class="text-xl font-bold text-gray-700 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Detail Absensi
                </h3>
                <button onclick="closeDetailModal()"
                    class="text-gray-700 hover:text-primary p-1 rounded-full hover:bg-primary/30 transition-colors" aria-label="Close detail modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-5 text-gray-700">
                <div class="mb-3">
                    <p class="text-sm font-medium flex items-center gap-2 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Nama Guru
                    </p>
                    <p id="detail-name" class="text-lg font-semibold"></p>
                </div>
                <div class="mb-3">
                    <p class="text-sm font-medium flex items-center gap-2 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                        NIP
                    </p>
                    <p id="detail-nip" class="text-lg font-semibold"></p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Tanggal
                        </p>
                        <p id="detail-date" class="font-medium"></p>
                    </div>
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Status
                        </p>
                        <p id="detail-status" class="font-medium"></p>
                    </div>
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Jam Masuk
                        </p>
                        <p id="detail-jam-masuk" class="font-medium flex items-center gap-1">
                            <span></span>
                        </p>
                    </div>
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Jam Pulang
                        </p>
                        <p id="detail-jam-pulang" class="font-medium flex items-center gap-1">
                            <span></span>
                        </p>
                    </div>
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Durasi
                        </p>
                        <p id="detail-durasi" class="font-medium flex items-center gap-1">
                            <span></span>
                        </p>
                    </div>
                    <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                        <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Jarak
                        </p>
                        <p id="detail-jarak" class="font-medium flex items-center gap-1">
                            <span></span>
                        </p>
                    </div>
                </div>

                <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                    <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Lokasi
                    </p>
                    <div id="detail-lokasi" class="mt-1"></div>
                </div>

                <div>
                    <p class="text-xs flex items-center gap-1 mb-2 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Foto
                    </p>
                    <div id="detail-foto" class="rounded-lg overflow-hidden bg-primary/20 p-3 border border-primary/40"></div>
                </div>

                <div class="bg-primary/20 p-3 rounded-lg border border-primary/40">
                    <p class="text-xs flex items-center gap-1 mb-1 text-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                        Keterangan
                    </p>
                    <p id="detail-keterangan" class="font-medium"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhance detail status display with indicator for Tepat Waktu
document.addEventListener('DOMContentLoaded', function() {
    const detailStatus = document.getElementById('detail-status');
    if (detailStatus) {
        const statusText = detailStatus.textContent.trim();
        const jamMasuk = document.getElementById('detail-jam-masuk').textContent.trim();
        const onTimeThreshold = '07:00:00';
        
        if (statusText === 'Hadir' && jamMasuk && jamMasuk <= onTimeThreshold) {
            detailStatus.innerHTML = '<span class="status-indicator bg-primary text-white font-semibold flex items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Tepat Waktu</span>';
        } else if (statusText === 'Hadir' && jamMasuk && jamMasuk > onTimeThreshold) {
            detailStatus.innerHTML = '<span class="status-indicator bg-warning text-black flex items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Terlambat</span>';
        } else if (statusText === 'Terlambat') {
            detailStatus.innerHTML = '<span class="status-indicator bg-warning text-black flex items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Terlambat</span>';
        } else if (statusText === 'Izin' || statusText === 'Sakit') {
            const icon = statusText === 'Izin' ? 
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' :
                '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>';
            detailStatus.innerHTML = `<span class="status-indicator bg-secondary text-white flex items-center gap-1">${icon}${statusText}</span>`;
        }
    }
});
</script>
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white rounded-xl shadow-lg max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-black flex items-center gap-2">

                        Konfirmasi Hapus
                    </h3>
                    <button onclick="closeDeleteModal()"
                        class="text-black hover:text-black p-1 rounded-full hover:bg-black transition-colors">

                    </button>
                </div>

                <div class="py-3">
                    <div class="bg-white text-black p-4 rounded-lg mb-4">
                        <p>Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
                        <button onclick="closeDeleteModal()"
                            class="px-4 py-2 text-black rounded-lg border border-black hover:bg-white transition-colors">
                            Batal
                        </button>
                        <a id="deleteLink" href="#"
                            class="px-4 py-2 bg-black text-white rounded-lg hover:bg-black transition-colors flex items-center gap-2">

                            <span>Hapus</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="dataModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white rounded-xl shadow-lg max-w-7xl w-full max-h-[90vh] overflow-auto relative">
            <div class="sticky top-0 bg-white border-b border-black px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-black flex items-center gap-2">

                    Preview Rekap Bulan <?= htmlspecialchars($formatted_month_year) ?>
                </h2>
                <div class="flex items-center gap-3">
                    <button id="printBtn"
                        class="bg-black 600 text-white px-4 py-2 rounded-lg hover:bg-black 700 transition-colors flex items-center gap-2">
                        <span>Print</span>
                    </button>
                    <button id="closeModalBtn"
                        class="bg-black 600 text-white hover:text-black p-2 rounded-full hover:bg-white transition-colors">

                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full border border-black rounded-lg overflow-hidden harian-table">
                        <thead class="bg-black text-white">
                            <tr>
                                <th class="px-4 py-3 text-left">No</th>
                                <th class="px-4 py-3 text-left">Nama Guru</th>
                                <th class="px-4 py-3 text-left">Tanggal</th>
                                <th class="px-4 py-3 text-left">Jam Masuk</th>
                                <th class="px-4 py-3 text-left">Jam Pulang</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Durasi</th>
                                <th class="px-4 py-3 text-left">Lokasi</th>
                                <th class="px-4 py-3 text-left">Foto Masuk</th>
                                <th class="px-4 py-3 text-left">Jarak (m)</th>
                                <th class="px-4 py-3 text-left">Keterangan</th>
                                <th class="px-4 py-3 text-left">TTD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black">
                            <?php foreach ($records as $index => $record): ?>
                            <tr class="table-row-alternating hover:bg-white">
                                <td class="px-4 py-3 text-center"><?= $index + 1 ?></td>
                                <td class="px-4 py-3">
                                    <div><?= htmlspecialchars($record['full_name'] ?? ($record['username'] ?? '-')) ?>
                                    </div>
                                    <div class="text-xs text-black">NIP:
                                        <?= htmlspecialchars($record['nip'] ?? '-') ?></div>
                                </td>
                                <td class="px-4 py-3 text-center"><?= htmlspecialchars($record['date'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?= htmlspecialchars(substr($record['jam_masuk'] ?? '', 0, 5) ?: '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?= htmlspecialchars(substr($record['jam_pulang'] ?? '', 0, 5) ?: '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($record['status'] === 'Hadir'): ?>
                                    <span class="status-indicator bg-white text-black">
                                        Hadir
                                    </span>
                                    <?php elseif ($record['status'] === 'Terlambat'): ?>
                                    <span class="status-indicator bg-white text-black">
                                        Terlambat
                                    </span>
                                    <?php elseif ($record['status'] === 'Izin' || $record['status'] === 'Sakit'): ?>
                                    <span class="status-indicator bg-black text-black">
                                        <?= $record['status'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="status-indicator bg-white text-black">

                                        <?= htmlspecialchars($record['status'] ?? '-') ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?= calculateDuration($record['jam_masuk'], $record['jam_pulang']) ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if (isset($record['lokasi_lat'], $record['lokasi_lng']) && $record['lokasi_lat'] && $record['lokasi_lng']): ?>
                                    <button
                                        onclick="openLocationModal(<?= htmlspecialchars($record['lokasi_lat']) ?>, <?= htmlspecialchars($record['lokasi_lng']) ?>)"
                                        class="text-black hover:text-black p-1 rounded-full hover:bg-black transition-colors">

                                    </button>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($record['type'] === 'absen' && !empty($record['foto_masuk'])): ?>
                                    <button onclick="openModal('<?= htmlspecialchars($record['foto_masuk']) ?>')"
                                        class="text-black hover:text-black p-1 rounded-full hover:bg-black transition-colors">

                                    </button>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center"><?= htmlspecialchars($record['jarak'] ?? '-') ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($record['keterangan'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="border-b border-black w-12 mx-auto h-8"></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photoModal" class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content max-w-3xl w-full max-h-[90vh] overflow-auto relative">
            <button onclick="closeModal()"
                class="absolute top-4 right-4 text-white hover:text-black p-2 rounded-full bg-black/30 hover:bg-black/50 transition-colors z-10">

            </button>
            <img id="modalImage" src="" alt="Foto Masuk" class="w-full h-auto rounded-lg" />
        </div>
    </div>

    <!-- Location Modal -->
    <div id="locationModal"
        class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white p-4 rounded-xl shadow-lg max-w-3xl w-full h-[80vh] relative">
            <button onclick="closeLocationModal()"
                class="absolute top-4 right-4 text-black hover:text-black p-2 rounded-full bg-white shadow-md hover:bg-white transition-colors z-10">

            </button>
            <iframe id="locationMap" src="" class="w-full h-full rounded-lg" frameborder="0" allowfullscreen=""
                aria-hidden="false" tabindex="0"></iframe>
        </div>
    </div>

    <!-- Harian Preview Modal -->
    <div id="harianModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white rounded-xl shadow-lg max-w-7xl w-full max-h-[90vh] overflow-auto relative">
            <div class="sticky top-0 bg-white border-b border-black px-6 py-4 flex justify-between items-center">
                <h2 class="text-xl font-bold text-black flex items-center gap-2">
                    Preview Rekap Absensi Harian Bulan <?= htmlspecialchars($formatted_month_year) ?>
                </h2>
                <div class="flex items-center gap-3">
                    <button id="printHarianModalBtn"
                        class="bg-black 600 text-white px-4 py-2 rounded-lg hover:bg-black700 transition-colors flex items-center gap-2">
                        <span>Print</span>
                    </button>
                    <button id="closeHarianModalBtn"
                        class="bg-black 600 text-white hover:text-black p-2 rounded-full hover:bg-white transition-colors">
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                <table class="min-w-full border border-black rounded-lg overflow-hidden harian-table">
                    <thead>
                        <tr>
                            <th rowspan="3" style="border: 1px solid black; padding: 4px; width: 30px; text-align: center; font-family: Arial, sans-serif; font-size: 12px;">No</th>
                            <th rowspan="3" style="border: 1px solid black; padding: 4px; width: 150px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;">Nama Guru</th>
                            <th rowspan="3" style="border: 1px solid black; padding: 2px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">Ket</th>
                            <th colspan="31" style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">Tanggal</th>
                        </tr>
                        <tr>
                            <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                            <th style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;"><?= $day ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($daily_summary as $user_key => $user): ?>
                        <tr>
                            <td rowspan="3" style="border: 1px solid black; padding: 4px; text-align: center; font-family: Arial, sans-serif; font-size: 12px;"><?= $no++ ?></td>
                            <td class="sub-label" style="border: 1px solid black; padding: 4px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;"><?= htmlspecialchars($user['name']) ?></td>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">DAT</td>
                            <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">
                                <?= isset($user['days'][$day]['jam_masuk']) && $user['days'][$day]['jam_masuk'] !== 'ABSEN' ? htmlspecialchars(substr($user['days'][$day]['jam_masuk'], 0, 5)) : 'ABS' ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td class="sub-label" style="border: 1px solid black; padding: 4px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;">NIP:<?= htmlspecialchars($user['nip']) ?></td>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">PUL</td>
                            <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">
                                <?= isset($user['days'][$day]['jam_pulang']) && $user['days'][$day]['jam_pulang'] !== 'ABSEN' ? htmlspecialchars(substr($user['days'][$day]['jam_pulang'], 0, 5)) : 'ABS' ?>
                            </td>
                            <?php endfor; ?>
                        </tr>
                        <tr>
                            <td class="sub-label" style="border: 1px solid black; padding: 1px; text-align: left; font-family: Arial, sans-serif; font-size: 12px;">-</td>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px; text-align: center;">TTD</td>
                            <?php for ($day = 1; $day <= intval($jumlah_hari); $day++): ?>
                            <td style="border: 1px solid black; padding: 4px; font-family: Arial, sans-serif; font-size: 12px;">&nbsp;</td>
                            <?php endfor; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');

        toastMessage.textContent = message;

        // Set toast color based on type
        if (type === 'success') {
            toast.querySelector('div').className =
                'bg-black text-white px-6 py-3 rounded-lg shadow-lg flex items-center';
        } else {
            toast.querySelector('div').className =
                'bg-red-600 text-white px-6 py-3 rounded-lg shadow-lg flex items-center';
        }

        toast.classList.remove('hidden');
        toast.classList.add('slide-in');

        setTimeout(() => {
            toast.classList.remove('slide-in');
            toast.classList.add('slide-out');
            setTimeout(() => toast.classList.add('hidden'), 300);
        }, 3000);
    }

    // Detail modal functions
    function showDetailModal(record) {
        const modal = document.getElementById('detailModal');
        document.getElementById('detail-name').textContent = record.full_name || record.username || '-';
        document.getElementById('detail-nip').textContent = record.nip || '-';
        document.getElementById('detail-date').textContent = record.date || '-';

        // Set status with appropriate styling
        const statusElement = document.getElementById('detail-status');
        const jamMasuk = record.jam_masuk ? record.jam_masuk.substring(0,5) : null;
        const onTimeThreshold = '07:00';
        if (record.status === 'Hadir' && jamMasuk && jamMasuk <= onTimeThreshold) {
            statusElement.innerHTML = '<span class="status-indicator bg-success text-white font-semibold flex items-center gap-1">  Tepat Waktu</span>';
        } else if (record.status === 'Hadir' && jamMasuk && jamMasuk > onTimeThreshold) {
            statusElement.innerHTML = '<span class="status-indicator bg-warning text-black flex items-center gap-1">  Terlambat</span>';
        } else if (record.status === 'Terlambat') {
            statusElement.innerHTML = '<span class="status-indicator bg-warning text-black flex items-center gap-1">  Terlambat</span>';
        } else if (record.status === 'Izin' || record.status === 'Sakit') {
            statusElement.innerHTML = '<span class="status-indicator bg-secondary text-white flex items-center gap-1">  ' + record.status + '</span>';
        } else {
            statusElement.innerHTML = '<span class="status-indicator bg-gray-100 text-black">' + (record.status || '-') + '</span>';
        }

        // Set time fields
        document.getElementById('detail-jam-masuk').innerHTML =
            ` <span>${record.jam_masuk ? record.jam_masuk.substring(0,5) : '-'}</span>`;
        document.getElementById('detail-jam-pulang').innerHTML =
            ` <span>${record.jam_pulang ? record.jam_pulang.substring(0,5) : '-'}</span>`;

        // Calculate and set duration
        let duration = '-';
        if (record.jam_masuk && record.jam_pulang) {
            const start = new Date(`1970-01-01T${record.jam_masuk}Z`);
            const end = new Date(`1970-01-01T${record.jam_pulang}Z`);
            const cutoff = new Date(`1970-01-01T13:00:00Z`); // 13:00 cutoff time

            // Use the earlier of either jam_pulang or 13:00 as the end time
            const effectiveEnd = end > cutoff ? cutoff : end;
            const diff = (effectiveEnd - start) / 1000; // in seconds

            if (diff > 0) {
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;
                duration =
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                duration = '00:00:00';
            }
        }
        document.getElementById('detail-durasi').innerHTML = ` <span>${duration}</span>`;

        // Set jarak
        document.getElementById('detail-jarak').innerHTML = ` <span>${record.jarak ? record.jarak + ' m' : '-'}</span>`;

        // Set keterangan
        document.getElementById('detail-keterangan').textContent = record.keterangan || '-';

        // Set location button if coordinates exist
        const locationDiv = document.getElementById('detail-lokasi');
        if (record.lokasi_lat && record.lokasi_lng) {
            locationDiv.innerHTML = `
                    <button onclick="openLocationModal(${record.lokasi_lat}, ${record.lokasi_lng})" 
                        class="text-black hover:text-black flex items-center gap-1 px-3 py-1.5 border border-black rounded-lg hover:bg-black transition-colors">
                        
                        <span>Lihat Lokasi</span>
                    </button>
                `;
        } else {
            locationDiv.textContent = '-';
        }

        // Set photo button if exists
        const fotoDiv = document.getElementById('detail-foto');
        if (record.foto_masuk) {
            fotoDiv.innerHTML = `
                    <button onclick="openModal('${record.foto_masuk}')" 
                        class="text-black hover:text-black flex items-center gap-1 px-3 py-1.5 border border-black rounded-lg hover:bg-black transition-colors">
                        
                        <span>Lihat Foto</span>
                    </button>
                `;
        } else {
            fotoDiv.textContent = '-';
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
    }

    function closeDetailModal() {
        document.getElementById('detailModal').classList.add('hidden');
        document.body.style.overflow = ''; // Re-enable scrolling
    }

    // Delete confirmation
    function confirmDelete(username, date, type) {
        const modal = document.getElementById('deleteModal');
        const deleteLink = document.getElementById('deleteLink');

        // Set the appropriate delete URL based on type
        if (type === 'absen') {
            deleteLink.href = `delete_absen.php?username=${username}&date=${date}`;
        } else {
            deleteLink.href = `delete_izin.php?username=${username}&date=${date}`;
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.style.overflow = ''; // Re-enable scrolling
    }

    // Photo modal
    function openModal(src) {
        const modal = document.getElementById('photoModal');
        const modalImage = document.getElementById('modalImage');

        // Show loading state
        modalImage.src = '';
        modalImage.alt = 'Loading...';
        modal.classList.remove('hidden');

        // Set image source
        modalImage.onload = function() {
            modalImage.alt = 'Foto Absensi';
        };

        modalImage.src = src;
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    function closeModal() {
        document.getElementById('photoModal').classList.add('hidden');
        document.body.style.overflow = ''; // Re-enable scrolling
    }

    // Location modal
    function openLocationModal(lat, lng) {
        const modal = document.getElementById('locationModal');
        const locationMap = document.getElementById('locationMap');
        const src =
            `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01}%2C${lat-0.01}%2C${lng+0.01}%2C${lat+0.01}&layer=mapnik&marker=${lat}%2C${lng}`;

        locationMap.src = src;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }

    function closeLocationModal() {
        document.getElementById('locationModal').classList.add('hidden');
        document.body.style.overflow = ''; // Re-enable scrolling
    }

    // Close modals on outside click
    window.onclick = function(event) {
        const modals = [{
                element: document.getElementById('photoModal'),
                close: closeModal
            },
            {
                element: document.getElementById('locationModal'),
                close: closeLocationModal
            },
            {
                element: document.getElementById('detailModal'),
                close: closeDetailModal
            },
            {
                element: document.getElementById('deleteModal'),
                close: closeDeleteModal
            },
            {
                element: document.getElementById('dataModal'),
                close: function() {
                    document.getElementById('dataModal').classList.add('hidden');
                    document.body.style.overflow = '';
                }
            }
        ];

        modals.forEach(modal => {
            if (event.target === modal.element) {
                modal.close();
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        let selectedPrintDate = null;

        // Preview modal
        document.getElementById('previewBtn').addEventListener('click', function() {
            document.getElementById('dataModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        document.getElementById('closeModalBtn').addEventListener('click', function() {
            document.getElementById('dataModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
        });

        // Print functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            const originalContents = document.body.innerHTML;
            const table = document.querySelector('#dataModal table');
            const clonedTable = table.cloneNode(true);

            // Remove location and photo columns for printing
            const removeCols = [7, 8]; // Location and Photo columns

            // Remove header columns
            const theadRow = clonedTable.querySelector('thead tr');
            removeCols.slice().reverse().forEach(colIndex => {
                if (theadRow.children[colIndex]) {
                    theadRow.removeChild(theadRow.children[colIndex]);
                }
            });

            // Remove body columns
            clonedTable.querySelectorAll('tbody tr').forEach(row => {
                removeCols.slice().reverse().forEach(colIndex => {
                    if (row.children[colIndex]) {
                        row.removeChild(row.children[colIndex]);
                    }
                });
            });

            // Add signature line to TTD column (index 9 after removal)
            clonedTable.querySelectorAll('tbody tr').forEach(row => {
                if (row.children[9]) {
                    row.children[9].innerHTML =
                        '<div style="border-bottom: 1px solid #9ca3af; width: 24px; margin: 0 auto; height: 32px;"></div>';
                }
            });


            // Apply print styling
            clonedTable.style.fontSize = '10px'; // uniform font size for printing
            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #ddd';
                cell.style.padding = '4px';
                cell.style.textAlign = 'center';
                cell.style.fontSize = '10px';
            });

            // Adjust width for "Nama Guru" column (2nd column)
            clonedTable.querySelectorAll('thead tr th:nth-child(2), tbody tr td:nth-child(2)').forEach(
                cell => {
                    cell.style.width = '350px';
                    cell.style.textAlign = 'left';
                    cell.style.whiteSpace = 'nowrap';
                    cell.style.fontSize = '11px';
                });

            // Create summary table HTML
            const summaryTable = document.createElement('table');
            summaryTable.style.width = '100%';
            summaryTable.style.borderCollapse = 'collapse';
            summaryTable.style.marginBottom = '20px';
            summaryTable.style.fontSize = '11px';

            // Add header to summary table
            const summaryHeader = document.createElement('thead');
            summaryHeader.innerHTML = `
                    <tr>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">No</th>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">Nama Guru & NIP</th>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">
                            <span class="status-indicator bg-white text-black" style="display: inline-flex; align-items: center;">
                                 Tepat Waktu
                            </span>
                        </th>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">
                            <span class="status-indicator bg-white text-black" style="display: inline-flex; align-items: center;">
                                 Terlambat
                            </span>
                        </th>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">
                            <span class="status-indicator bg-black text-black" style="display: inline-flex; align-items: center;">
                                 Izin
                            </span>
                        </th>
                        <th style="border: 1px solid #ddd; font-size: 12px; padding: 8px; background-color: #f9fafb; text-align: left;">
                            <span class="status-indicator bg-white text-black" style="display: inline-flex; align-items: center;">
                                 Absen
                            </span>
                        </th>
                        <th style="border: 1px solid #ddd; padding: 8px; background-color: #f9fafb; text-align: left;">
                            <span class="status-indicator bg-white text-black" style="display: inline-flex; align-items: center;">
                                 Total
                            </span>
                        </th>
                        <th style="border: 1px solid #ddd; padding: 8px; background-color: #f9fafb; text-align: left;">TTD</th>
                    </tr>
                `;
            summaryTable.appendChild(summaryHeader);

            // Add body to summary table
            const summaryBody = document.createElement('tbody');

            // Create summary data array from PHP
            const summaryData = [
                <?php foreach ($summary as $user_key => $data): ?> {
                    name: <?= json_encode($data['name']) ?>,
                    nip: <?= json_encode($data['nip']) ?>,
                    tepatWaktu: <?= $data['tepat_waktu'] ?>,
                    terlambat: <?= $data['terlambat'] ?>,
                    izin: <?= $data['izin'] ?>,
                    tidakAbsen: <?= $jumlah_hari_int - $data['tepat_waktu'] - $data['terlambat'] - $data['izin'] ?>
                },
                <?php endforeach; ?>
            ];

            // Add rows to summary table
            summaryData.forEach((data, index) => {
                const summaryRow = document.createElement('tr');
                summaryRow.innerHTML = `
                    <td style="border: 1px solid #ddd; padding: 5px;">${index + 1}</td>
                    <td style="border: 1px solid #ddd; padding: 5px;">
                        <div style="text-align: left; font-size: 12px; color: #6b7280;">${data.name}</div>
                        <div style="text-align: left; font-size: 12px; color: #6b7280;">NIP: ${data.nip}</div>
                    </td>
                    <td style="border: 1px solid #ddd; padding: 5px;">${data.tepatWaktu}</td>
                    <td style="border: 1px solid #ddd; padding: 5px;">${data.terlambat}</td>
                    <td style="border: 1px solid #ddd; padding: 5px;">${data.izin}</td>
                    <td style="border: 1px solid #ddd; padding: 5px;">${data.tidakAbsen}</td>
                    <td style="border: 1px solid #ddd; padding: 5px; font-weight: bold;">
                        ${data.tepatWaktu + data.terlambat + data.izin + data.tidakAbsen}
                    </td>
                    <td style="border: 1px solid #ddd; padding: 5px; text-align: center;">
                        <div style="border-bottom: 1px solid #9ca3af; width: 48px; margin: 0 auto; height: 32px;"></div>
                    </td>
                `;
                summaryBody.appendChild(summaryRow);
            });

            summaryTable.appendChild(summaryBody);

            // Create print document
            document.body.innerHTML = `
                    <style>
                        @media print {
                            @page {
                                size: A4;
                                margin: 10mm;
                            }
                            body {
                                font-size: 8px !important;
                                color: black !important;
                                background-color: white !important;
                                font-family: Arial, sans-serif !important;
                            }
                            table {
                                border-collapse: collapse;
                                width: 100%;
                            }
                            table, th, td {
                                font-size: 12px !important;
                                border: 1px solid black !important;
                            }
                            th, td {
                                padding: 1px !important;
                                text-align: center !important;
                            }
                            th {
                                font-weight: 600 !important;
                            }
                        }
                    </style>
                    <div style="padding: 20px;">
                <div style="text-align: center; font-size: 20px; margin-bottom: 10px; font-weight: bold;">
                    ABSEN <?= htmlspecialchars($school_name) ?>
                </div>
                <h1 style="text-align: center; font-size: 16px; margin-bottom: 20px;">Rekap Absensi Bulan <?= htmlspecialchars($formatted_month_year) ?></h1>

                        <h2 style="font-size: 14px; margin: 15px 0;">Rekap Detail</h2>
                        ${summaryTable.outerHTML}

                        <div style="margin-top: 40px; margin-left: 10cm;">
                            <p style="text-align: left; font-size: 12px;">Lamongan, ${tanggalCetak}</p>
                            <p style="text-align: left; font-size: 12px;">Kepala Sekolah</p>
                            <div style="height: 60px;"></div>
                            <p style="text-align: left; font-size: 12px;"><?= htmlspecialchars($fullname) ?></p>
                            <p style="text-align: left; font-size: 12px;">NIP. <?= htmlspecialchars($user_row['nip'] ?? '-') ?></p>
                        </div>

                        <div style="page-break-before: always;"></div>

                        <h2 style="font-size: 14px; margin: 15px 0;">Data Absensi</h2>
                        ${clonedTable.outerHTML}
                    </div>
                `


            // Print and restore
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.add('animate-spin');
            }
            // Reload the page immediately without delay
            window.location.reload();
        });

        // Preview button
        document.getElementById('previewBtn').addEventListener('click', function() {
            const modal = document.getElementById('dataModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            }
        });

        // Close preview modal button
        document.getElementById('closeModalBtn').addEventListener('click', function() {
            const modal = document.getElementById('dataModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable scrolling
            }
        });

        // Harian preview modal
        document.getElementById('previewBtnHarian').addEventListener('click', function() {
            document.getElementById('harianModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        document.getElementById('closeHarianModalBtn').addEventListener('click', function() {
            document.getElementById('harianModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
        });

        // Print harian functionality
        document.getElementById('printHarianModalBtn').addEventListener('click', function() {
            const originalContents = document.body.innerHTML;
            const table = document.querySelector('#harianModal table');
            const clonedTable = table.cloneNode(true);

            // Apply uniform print styling for consistent font sizes
            clonedTable.style.fontSize = '10px'; // Uniform font size for all elements
            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.style.transform = 'none';

            // Apply uniform styling to all table cells (headers and data)
            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #ddd';
                cell.style.padding = '1px 1px';
                cell.style.textAlign = 'left';
                cell.style.fontSize = '12px'; // Updated font size to 12px for datang and pulang columns
                cell.style.fontWeight = 'normal'; // Remove bold for consistency
                cell.style.whiteSpace = 'normal';
                cell.style.overflow = 'visible';
                cell.style.textOverflow = 'clip';
                // Set header and subheader text color to black
                if (cell.tagName.toLowerCase() === 'th') {
                    cell.style.color = 'black';
                    cell.style.fontWeight = 'bold';
                }
            });

            // Specific adjustments for "Nama Guru" column (2nd column) to handle text wrapping
            clonedTable.querySelectorAll('tbody tr td:nth-child(2)').forEach(cell => {
                cell.style.textAlign = 'left';
                cell.style.width =
                    '30px'; // Increased width for better fit as per user request
            });

            // Ensure NIP text in "Nama Guru" column is consistent
            clonedTable.querySelectorAll('tbody tr td:nth-child(2) small').forEach(cell => {
                cell.style.fontSize = '10px';
                cell.style.display = 'inline';
            });

            // Adjust sub-columns (Dtng, Plng, TTD) for uniform width
            for (let i = 3; i <= 31 * 3 + 2; i++) {
                const subColIndex = (i - 3) % 3;
                clonedTable.querySelectorAll(
                        `thead tr:nth-child(2) th:nth-child(${i + 1}), tbody tr td:nth-child(${i + 1})`)
                    .forEach(cell => {
                        cell.style.width = '15px'; // Uniform width for sub-columns
                        cell.style.fontSize = '12px'; // Updated font size to 12px for datang and pulang columns
                        cell.style.padding = '1px 1px';
                        
                    });
            }

            // Get current date for signature
            const printDate = new Date();
            const formattedDate = printDate.toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Create print document with A4 landscape page size
            document.body.innerHTML = `
                    <style>
                        @page {
                            size: A4 landscape;
                            margin: 10mm;
                        }
                        body {
                            font-family: Arial, sans-serif;
                            font-size: 10px;
                        }
                        table {
                            font-size: 10px;
                        }
                        th, td {
                            font-size: 8.5px !important;
                        }
                    </style>
                    <div style="padding: 1px;">
                        <div style="text-align: center; font-size: 14px; margin-bottom: 10px; font-weight: bold;">
                            ABSEN <?= htmlspecialchars($school_name) ?>
                        </div>
                        <h1 style="text-align: center; font-size: 14px; margin-bottom: 10px; font-weight: bold;">Rekap Absensi Harian Bulan <?= htmlspecialchars($formatted_month_year) ?></h1>
                        ${clonedTable.outerHTML}

                        <div style="margin-top: 40px; margin-left: 20cm;">
                            <p style="text-align: left; font-size: 12px;">Lamongan, ${tanggalCetak}</p>
                            <p style="text-align: left; font-size: 12px;">Kepala Sekolah</p>
                            <div style="height: 60px;"></div>
                            <p style="text-align: left; font-size: 12px;"><?= htmlspecialchars($fullname) ?></p>
                            <p style="text-align: left; font-size: 12px;">NIP. <?= htmlspecialchars($user_row['nip'] ?? '-') ?></p>
                        </div>
                    </div>
                `;

            // Print and restore
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });



        // Print harian from button outside modal
        document.getElementById('printBtnHarian').addEventListener('click', function() {
            const originalContents = document.body.innerHTML;
            // Select the harian table container explicitly by id or class
            const harianTableContainer = document.querySelector(
                '.bg-white.rounded-xl.shadow-card.overflow-hidden.animate-fade-in:nth-of-type(3)');
            const table = harianTableContainer ? harianTableContainer.querySelector('table') : null;
            if (!table) {
                alert('Tabel rekap absensi harian tidak ditemukan.');
                return;
            }
            const clonedTable = table.cloneNode(true);

            // Apply print styling
            clonedTable.style.fontSize = '8px';
            clonedTable.style.width = '100%';
            clonedTable.style.borderCollapse = 'collapse';
            clonedTable.style.transform = 'none';
            clonedTable.querySelectorAll('th, td').forEach(cell => {
                cell.style.border = '1px solid #ddd';
                cell.style.padding = '2px 4px';
                cell.style.textAlign = 'center';
            });

            // Get current date for signature
            const printDate = new Date();
            const formattedDate = printDate.toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            // Create print document
            document.body.innerHTML = `
                    <div style="padding: 10px 10px 20px 10px;">
                        <div style="text-align: center; font-size: 18px; margin-bottom: 10px; font-weight: bold;">
                            ABSEN <?= htmlspecialchars($school_name) ?>
                        </div>
                        <h1 style="text-align: center; font-size: 14px; margin-bottom: 20px;">Rekap Absensi Harian Bulan <?= htmlspecialchars($formatted_month_year) ?></h1>
                        ${clonedTable.outerHTML}

                        <div style="margin-top: 40px; margin-left: 10cm;">
                            <p style="text-align: left; font-size: 12px;">Lamongan, ${tanggalCetak}</p>
                            <p style="text-align: left; font-size: 12px;">Kepala Sekolah</p>
                            <div style="height: 60px;"></div>
                            <p style="text-align: left; font-size: 12px;"><?= htmlspecialchars($fullname) ?></p>
                            <p style="text-align: left; font-size: 12px;">NIP. <?= htmlspecialchars($user_row['nip'] ?? '-') ?></p>
                        </div>
                    </div>
                `;

            // Print and restore
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        });

        // Event listener for selectPrintDateBtn to show modal
        document.getElementById('selectPrintDateBtn').addEventListener('click', function() {
            document.getElementById('printDateModal').classList.remove('hidden');
            document.getElementById('printDateInput').value = tanggalCetak; // Set current value
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        // Event listener for cancelPrintDateBtn to hide modal
        document.getElementById('cancelPrintDateBtn').addEventListener('click', function() {
            document.getElementById('printDateModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
        });

        // Event listener for confirmPrintDateBtn to hide modal and set selected date
        document.getElementById('confirmPrintDateBtn').addEventListener('click', function() {
            const selectedDate = document.getElementById('printDateInput').value;
            if (selectedDate) {
                // Save selected date to database via AJAX
                fetch('rekap_absensi.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=save_tanggal_cetak&tanggal=' + encodeURIComponent(
                            selectedDate),
                        credentials: 'include'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Tanggal cetak berhasil disimpan: ' + selectedDate,
                            'success');
                            // Update global variable
                            tanggalCetak = selectedDate;
                        } else {
                            showToast('Gagal menyimpan tanggal cetak: ' + (data.error ||
                                'Unknown error'), 'error');
                        }
                    })
                    .catch(() => {
                        showToast('Gagal menyimpan tanggal cetak', 'error');
                    });
            }
            document.getElementById('printDateModal').classList.add('hidden');
            document.body.style.overflow = ''; // Re-enable scrolling
        });
    });

    // Show toast if success message in URL
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            showToast(urlParams.get('success'));
        }

        // Apply status colors to the status cells
        document.querySelectorAll('#detail-status').forEach(element => {
            const status = element.textContent.trim();
            if (status === 'Hadir') {
                element.classList.add('text-black 600');
            } else if (status === 'Terlambat') {
                element.classList.add('text-black 600');
            } else if (status === 'Izin' || status === 'Sakit') {
                element.classList.add('text-black 600');
            }
        });

        // Apply animations to cards
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.05}s`;
            card.classList.add('animate-slide-up');
        });
    });
    </script>

    <!-- Modal for selecting print date -->
    <div id="printDateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Pilih Tanggal Cetak</h3>
            <input type="date" id="printDateInput" class="w-full p-2 border border-black rounded mb-4">
            <div class="flex justify-end gap-2">
            <button id="cancelPrintDateBtn"
                class="px-4 py-2 bg-white text-black rounded hover:bg-black">Batal</button>
            <button id="confirmPrintDateBtn"
                class="px-4 py-2 bg-black text-white rounded hover:bg-black">Konfirmasi</button>
            </div>
        </div>
    </div>

</body>

</html>