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

$school_name = '';

// Check if "setting" table exists
$table_check_stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
$table_check_result = $table_check_stmt->execute();
$table_exists = $table_check_result->fetchArray(SQLITE3_ASSOC);



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
$jumlah_hari = $_GET['jumlah_hari'] ?? 30;

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

// Extract year and month parts
$year = '';
$month_num = '';
if (preg_match('/^(\\d{4})-(\\d{2})$/', $month_filter, $matches)) {
    $year = $matches[1];
    $month_num = $matches[2];
} else {
    // Fallback to current year and month if format is invalid
    $year = date('Y');
    $month_num = date('m');
}

// Get Indonesian month name or fallback to month number
$month_name = $indonesian_months[$month_num] ?? $month_num;

// Formatted month-year string
$formatted_month_year = $month_name . ' ' . $year;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_holiday_attendance'])) {
        $attendance_holiday_date = $_POST['attendance_holiday_date'];
        $attendance_jam_masuk = $_POST['attendance_jam_masuk'];
        $attendance_jam_pulang = $_POST['attendance_jam_pulang'] ?: $attendance_jam_masuk;
        $attendance_keterangan = $_POST['attendance_keterangan'];
        $selected_gurus = $_POST['selected_gurus'] ?? [];

        if (empty($selected_gurus)) {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&error=Tidak ada guru yang dipilih");
            exit();
        }

        $success_count = 0;
        foreach ($selected_gurus as $guru_username) {
            // Get user_id from username
            $user_stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
            $user_stmt->bindValue(':username', $guru_username, SQLITE3_TEXT);
            $user_result = $user_stmt->execute();
            $user_row = $user_result->fetchArray(SQLITE3_ASSOC);
            if ($user_row) {
                $user_id = $user_row['id'];
                // Insert into attendance
                $stmt = $db->prepare("INSERT INTO attendance (user_id, date, jam_masuk, jam_pulang, keterangan) VALUES (:user_id, :date, :jam_masuk, :jam_pulang, :keterangan)");
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':date', $attendance_holiday_date, SQLITE3_TEXT);
                $stmt->bindValue(':jam_masuk', $attendance_jam_masuk, SQLITE3_TEXT);
                $stmt->bindValue(':jam_pulang', $attendance_jam_pulang, SQLITE3_TEXT);
                $stmt->bindValue(':keterangan', $attendance_keterangan, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $success_count++;
                }
            }
        }

        if ($success_count > 0) {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&success=Hari libur attendance berhasil ditambahkan untuk $success_count guru");
        } else {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&error=Gagal menambahkan hari libur attendance");
        }
        exit();
    } elseif (isset($_POST['delete_holiday_attendance'])) {
        $delete_date = $_POST['delete_holiday_attendance_date'];
        $delete_jam_masuk = $_POST['delete_holiday_attendance_jam_masuk'];
        $delete_jam_pulang = $_POST['delete_holiday_attendance_jam_pulang'];
        $delete_keterangan = $_POST['delete_holiday_attendance_keterangan'];

        $stmt = $db->prepare("DELETE FROM attendance WHERE date = :date AND jam_masuk = :jam_masuk AND jam_pulang = :jam_pulang AND keterangan = :keterangan");
        $stmt->bindValue(':date', $delete_date, SQLITE3_TEXT);
        $stmt->bindValue(':jam_masuk', $delete_jam_masuk, SQLITE3_TEXT);
        $stmt->bindValue(':jam_pulang', $delete_jam_pulang, SQLITE3_TEXT);
        $stmt->bindValue(':keterangan', $delete_keterangan, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result) {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&success=Hari libur attendance berhasil dihapus");
        } else {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&error=Gagal menghapus hari libur attendance");
        }
        exit();
    } elseif (isset($_GET['get_holiday_gurus'])) {
        $date = $_GET['date'];
        $jam_masuk = $_GET['jam_masuk'];
        $jam_pulang = $_GET['jam_pulang'];
        $keterangan = $_GET['keterangan'];

        $stmt = $db->prepare("SELECT username FROM users u JOIN attendance a ON u.id = a.user_id WHERE a.date = :date AND a.jam_masuk = :jam_masuk AND a.jam_pulang = :jam_pulang AND a.keterangan = :keterangan");
        $stmt->bindValue(':date', $date, SQLITE3_TEXT);
        $stmt->bindValue(':jam_masuk', $jam_masuk, SQLITE3_TEXT);
        $stmt->bindValue(':jam_pulang', $jam_pulang, SQLITE3_TEXT);
        $stmt->bindValue(':keterangan', $keterangan, SQLITE3_TEXT);
        $result = $stmt->execute();

        $gurus = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $gurus[] = $row['username'];
        }

        header('Content-Type: application/json');
        echo json_encode(['gurus' => $gurus]);
        exit();
    } elseif (isset($_POST['update_holiday_attendance'])) {
        $original_date = $_POST['original_date'];
        $original_jam_masuk = $_POST['original_jam_masuk'];
        $original_jam_pulang = $_POST['original_jam_pulang'];
        $original_keterangan = $_POST['original_keterangan'];
        $new_date = $_POST['attendance_holiday_date'];
        $new_jam_masuk = $_POST['attendance_jam_masuk'];
        $new_jam_pulang = $_POST['attendance_jam_pulang'] ?: $new_jam_masuk;
        $new_keterangan = $_POST['attendance_keterangan'];
        $selected_gurus = $_POST['selected_gurus'] ?? [];

        if (empty($selected_gurus)) {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&error=Tidak ada guru yang dipilih");
            exit();
        }

        // Delete old records
        $delete_stmt = $db->prepare("DELETE FROM attendance WHERE date = :date AND jam_masuk = :jam_masuk AND jam_pulang = :jam_pulang AND keterangan = :keterangan");
        $delete_stmt->bindValue(':date', $original_date, SQLITE3_TEXT);
        $delete_stmt->bindValue(':jam_masuk', $original_jam_masuk, SQLITE3_TEXT);
        $delete_stmt->bindValue(':jam_pulang', $original_jam_pulang, SQLITE3_TEXT);
        $delete_stmt->bindValue(':keterangan', $original_keterangan, SQLITE3_TEXT);
        $delete_stmt->execute();

        // Insert new records
        $success_count = 0;
        foreach ($selected_gurus as $guru_username) {
            $user_stmt = $db->prepare('SELECT id FROM users WHERE username = :username');
            $user_stmt->bindValue(':username', $guru_username, SQLITE3_TEXT);
            $user_result = $user_stmt->execute();
            $user_row = $user_result->fetchArray(SQLITE3_ASSOC);
            if ($user_row) {
                $user_id = $user_row['id'];
                $stmt = $db->prepare("INSERT INTO attendance (user_id, date, jam_masuk, jam_pulang, keterangan) VALUES (:user_id, :date, :jam_masuk, :jam_pulang, :keterangan)");
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':date', $new_date, SQLITE3_TEXT);
                $stmt->bindValue(':jam_masuk', $new_jam_masuk, SQLITE3_TEXT);
                $stmt->bindValue(':jam_pulang', $new_jam_pulang, SQLITE3_TEXT);
                $stmt->bindValue(':keterangan', $new_keterangan, SQLITE3_TEXT);
                $result = $stmt->execute();
                if ($result) {
                    $success_count++;
                }
            }
        }

        if ($success_count > 0) {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&success=Hari libur attendance berhasil diupdate untuk $success_count guru");
        } else {
            header("Location: daily_recap.php?month=$month_filter&jumlah_hari=$jumlah_hari&error=Gagal mengupdate hari libur attendance");
        }
        exit();
    }
}

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
       NULL AS durasi, NULL AS lokasi_lat, NULL AS lokasi_lng, NULL AS jarak,
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

// Fetch holidays for the month
$query_holidays = "
SELECT date, description
FROM holidays
WHERE strftime('%Y-%m', date) = :month
";
$stmt_holidays = $db->prepare($query_holidays);
$stmt_holidays->bindValue(':month', $month_filter, SQLITE3_TEXT);
$results_holidays = $stmt_holidays->execute();
$holidays = [];
while ($row = $results_holidays->fetchArray(SQLITE3_ASSOC)) {
    $holidays[$row['date']] = $row['description'];
}

// Sort records by date and username
usort($records, function($a, $b) {
    if ($a['date'] === $b['date']) {
        return strcmp($a['username'], $b['username']);
    }
    return strcmp($a['date'], $b['date']);
});


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Daily Recap - Absen App</title>
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
                    card: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                    'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                },
            },
        },
    }
    </script>
    <style>
    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .animate-spin {
        animation: spin 1s linear infinite;
    }

    /* Toast notification */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        transition: all 0.3s ease;
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
            display: block;
        }

        .mobile-cards {
            display: none;
        }
    }



    /* Smooth transitions */
    .transition-all {
        transition-property: all;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 150ms;
    }

    /* Card styles */
    .status-indicator {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.5;
    }

    /* Improve table readability */
    .table-row-alternating:nth-child(odd) {
        background-color: #f9f9f9;
    }

    /* Vertical text for table headers */
    .vertical-text {
        transform: rotate(-90deg);
        white-space: nowrap;
    }

    /* Print styles */
    @media print {
        body * {
            visibility: hidden;
        }
        #previewModal, #previewModal * {
            visibility: visible;
        }
        #previewModal {
            position: absolute;
            left: 0;
            top: 0;
            z-index: 9999;
            background: white;
            padding-top: 1.5rem; /* Add padding to top */
        }
        .modal-content {
            box-shadow: none;
            border: none;
            max-width: none;
            width: 100%;
        }
        #previewModal .flex.justify-between.items-center {
            display: none;
        }
        #previewModal .mb-4.text-center h4 {
            display: none;
        }
        .fas {
            display: none;
        }
    }
    </style>
</head>

<body class="min-h-screen bg-white font-sans text-black">
    <!-- Toast Notification -->
    <div id="toast" class="toast hidden animate-slide-in">
        <div class="bg-black text-white px-6 py-3 rounded-lg shadow-lg flex items-center">
            <span id="toast-message">Operation successful</span>
        </div>
    </div>

    <div class="flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-black text-white shadow-md">
            <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight">Daily Recap,</h1>
                        <h1 class="text-2xl font-bold tracking-tight"><?= htmlspecialchars($fullname) ?></h1>
                    </div>
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <a href="rekap_absensi.php"
                            class="w-full sm:w-auto bg-white/20 hover:bg-white/30 text-white py-2 px-4 rounded-lg transition-all flex items-center justify-center gap-2">
                            <span class="sm:inline">Rekap Absensi</span>
                        </a>
                        <a href="logout.php"
                            class="w-full sm:w-auto bg-black hover:bg-gray-900 text-white py-2 px-4 rounded-lg transition-all flex items-center justify-center gap-2">
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
                <div class="bg-white rounded-xl shadow-card p-6 animate-fade-in no-print">
                    <h2 class="text-xl font-semibold text-black mb-4 flex items-center gap-2">
                        Filter Data
                    </h2>
                    <form method="GET" action="daily_recap.php" class="space-y-4">
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
    <div class="col-span-2 flex items-end space-x-3">
        <button type="submit"
            class="flex-1 md:flex-none bg-black hover:bg-gray-900 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">
            <span>Filter</span>
        </button>
        <button type="button" id="refreshBtn"
            class="flex-1 md:flex-none bg-black hover:bg-gray-900 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">
            <span>Refresh</span>
        </button>

        <button type="button" id="holidayAttendanceBtn"
            class="flex-1 md:flex-none bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 md:px-4 md:py-2 rounded-lg transition-all flex items-center justify-center gap-1 md:gap-2 text-sm md:text-base">
            <span>Hari Libur Attendance</span>
        </button>
    </div>
</div>
                    </form>
                </div>



        <!-- Footer -->
        <footer class="mt-auto bg-white border-t border-gray-200">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <p class="text-sm text-center text-gray-500">
                    &copy; <?= date('Y') ?> Absen App. All rights reserved.
                </p>
            </div>
        </footer>
    </div>



    <!-- Holiday Attendance Modal -->
    <div id="holidayAttendanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white rounded-xl shadow-lg max-w-4xl w-full max-h-[90vh] overflow-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6 pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-calendar-times"></i>
                        Kelola Hari Libur Attendance
                    </h3>
                    <button onclick="closeHolidayAttendanceModal()"
                        class="bg-gray-500 text-white hover:bg-gray-600 p-2 rounded-full hover:bg-gray-100 transition-colors"
                        title="Close">
                        <i class="fas fa-times fa-lg"></i>
                    </button>
                </div>

                <!-- Add Holiday Attendance Form -->
                <div class="mb-6">
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">Tambah Hari Libur Attendance</h4>
                    <form id="holidayAttendanceForm" method="POST" action="daily_recap.php?month=<?= htmlspecialchars($month_filter) ?>&jumlah_hari=<?= htmlspecialchars($jumlah_hari) ?>" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="attendance_holiday_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" id="attendance_holiday_date" name="attendance_holiday_date"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                        required>
                </div>
                <div>
                    <label for="attendance_jam_masuk" class="block text-sm font-medium text-gray-700 mb-1">Jam Masuk</label>
                    <input type="text" id="attendance_jam_masuk" name="attendance_jam_masuk"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                        required>
                </div>
                <div>
                    <label for="attendance_jam_pulang" class="block text-sm font-medium text-gray-700 mb-1">Jam Pulang</label>
                    <input type="text" id="attendance_jam_pulang" name="attendance_jam_pulang"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                        placeholder="Kosongkan jika sama dengan Jam Masuk">
                </div>
                <div>
                    <label for="attendance_keterangan" class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                    <input type="text" id="attendance_keterangan" name="attendance_keterangan"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all"
                        placeholder="Contoh: Hari Raya Idul Fitri" required>
                </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Guru</label>
                            <div class="mb-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="selectAllGurus" class="mr-2">
                                    <label for="selectAllGurus" class="text-sm font-medium">Pilih Semua Guru</label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto border border-gray-300 rounded-lg p-4">
                                <?php foreach ($guru_users as $user): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" id="guru_<?= htmlspecialchars($user['username']) ?>" name="selected_gurus[]" value="<?= htmlspecialchars($user['username']) ?>"
                                        class="guru-checkbox mr-2">
                                    <label for="guru_<?= htmlspecialchars($user['username']) ?>" class="text-sm">
                                        <?= htmlspecialchars($user['full_name'] ?? $user['username']) ?> (<?= htmlspecialchars($user['nip'] ?? '-') ?>)
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <button type="submit" name="add_holiday_attendance"
                                class="flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-save"></i>
                                <span>Simpan Hari Libur Attendance</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Existing Holiday Attendance List -->
                <div>
                    <h4 class="text-lg font-semibold text-gray-700 mb-3">Daftar Hari Libur Attendance Bulan <?= htmlspecialchars($formatted_month_year) ?></h4>
                    <div class="space-y-3 max-h-60 overflow-y-auto">
                        <?php
                        // Fetch holiday attendance records for the month
                        $query_holiday_attendance = "
                        SELECT date, jam_masuk, jam_pulang, keterangan, COUNT(*) as guru_count
                        FROM attendance
                        WHERE strftime('%Y-%m', date) = :month
                        AND keterangan != ''
                        GROUP BY date, jam_masuk, jam_pulang, keterangan
                        ORDER BY date DESC
                        ";
                        $stmt_holiday_attendance = $db->prepare($query_holiday_attendance);
                        $stmt_holiday_attendance->bindValue(':month', $month_filter, SQLITE3_TEXT);
                        $results_holiday_attendance = $stmt_holiday_attendance->execute();
                        $holiday_attendance_records = [];
                        while ($row = $results_holiday_attendance->fetchArray(SQLITE3_ASSOC)) {
                            $holiday_attendance_records[] = $row;
                        }
                        ?>

                        <?php if (empty($holiday_attendance_records)): ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fas fa-calendar-times text-3xl mb-2"></i>
                            <p>Belum ada hari libur attendance untuk bulan ini</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($holiday_attendance_records as $record): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg holiday-attendance-item cursor-pointer" data-date="<?= htmlspecialchars($record['date']) ?>" data-jam-masuk="<?= htmlspecialchars($record['jam_masuk']) ?>" data-jam-pulang="<?= htmlspecialchars($record['jam_pulang']) ?>" data-keterangan="<?= htmlspecialchars($record['keterangan']) ?>">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-day text-green-600"></i>
                                <div>
                                    <p class="font-medium text-gray-900">
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($record['date']))) ?>
                                    </p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($record['jam_masuk']) ?> - <?= htmlspecialchars($record['jam_pulang']) ?>, <?= htmlspecialchars($record['keterangan']) ?> (<?= $record['guru_count'] ?> guru)</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="editHolidayAttendance('<?= htmlspecialchars($record['date']) ?>', '<?= htmlspecialchars($record['jam_masuk']) ?>', '<?= htmlspecialchars($record['jam_pulang']) ?>', '<?= htmlspecialchars($record['keterangan']) ?>')"
                                    class="bg-blue-600 text-white hover:bg-blue-700 p-3 rounded-full transition-colors"
                                    title="Edit hari libur attendance">
                                    <i class="fa fa-pen-to-square fa-lg"></i>
                                </button>
                                <button type="button" onclick="openDeleteModal('<?= htmlspecialchars($record['date']) ?>', '<?= htmlspecialchars($record['jam_masuk']) ?>', '<?= htmlspecialchars($record['jam_pulang']) ?>', '<?= htmlspecialchars($record['keterangan']) ?>')"
                                    class="bg-red-600 text-white hover:bg-red-700 p-3 rounded-full transition-colors"
                                    title="Hapus hari libur attendance">
                                    <i class="fa fa-trash fa-lg"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 p-4">
        <div class="modal-content bg-white rounded-xl shadow-lg max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4 pb-3 border-b">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                        Konfirmasi Hapus
                    </h3>
                    <button onclick="closeDeleteModal()"
                        class="text-gray-500 hover:text-gray-700 p-1 rounded-full hover:bg-gray-100 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="mb-6">
                    <p class="text-gray-700">Apakah Anda yakin ingin menghapus hari libur attendance ini?</p>
                    <p id="deleteDetails" class="text-sm text-gray-500 mt-2"></p>
                </div>
                <div class="flex space-x-3">
                    <button onclick="closeDeleteModal()"
                        class="flex-1 bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition-all">
                        Batal
                    </button>
                    <button id="confirmDeleteBtn"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-all">
                        Hapus
                    </button>
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
        toast.classList.remove('hidden');
        toast.classList.add('animate-slide-in');
        setTimeout(() => {
            toast.classList.add('slide-out');
            setTimeout(() => {
                toast.classList.add('hidden');
                toast.classList.remove('slide-out');
            }, 300);
        }, 3000);
    }



    // Holiday Attendance Modal functions
    function openHolidayAttendanceModal() {
        document.getElementById('holidayAttendanceModal').classList.remove('hidden');
    }

    function closeHolidayAttendanceModal() {
        document.getElementById('holidayAttendanceModal').classList.add('hidden');
    }

    // Delete Confirmation Modal functions
    function openDeleteModal(date, jam_masuk, jam_pulang, keterangan) {
        document.getElementById('deleteDetails').textContent = `Tanggal: ${date}, Jam: ${jam_masuk} - ${jam_pulang}, Keterangan: ${keterangan}`;
        document.getElementById('deleteModal').classList.remove('hidden');
        // Set up the confirm button to submit the form
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.onclick = function() {
            // Create a form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'daily_recap.php?month=<?= htmlspecialchars($month_filter) ?>&jumlah_hari=<?= htmlspecialchars($jumlah_hari) ?>';
            const dateInput = document.createElement('input');
            dateInput.type = 'hidden';
            dateInput.name = 'delete_holiday_attendance_date';
            dateInput.value = date;
            const jamMasukInput = document.createElement('input');
            jamMasukInput.type = 'hidden';
            jamMasukInput.name = 'delete_holiday_attendance_jam_masuk';
            jamMasukInput.value = jam_masuk;
            const jamPulangInput = document.createElement('input');
            jamPulangInput.type = 'hidden';
            jamPulangInput.name = 'delete_holiday_attendance_jam_pulang';
            jamPulangInput.value = jam_pulang;
            const keteranganInput = document.createElement('input');
            keteranganInput.type = 'hidden';
            keteranganInput.name = 'delete_holiday_attendance_keterangan';
            keteranganInput.value = keterangan;
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'delete_holiday_attendance';
            submitInput.value = '1';
            form.appendChild(dateInput);
            form.appendChild(jamMasukInput);
            form.appendChild(jamPulangInput);
            form.appendChild(keteranganInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        };
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }



    // Event listeners
    if (document.getElementById('holidayAttendanceBtn')) {
        document.getElementById('holidayAttendanceBtn').addEventListener('click', openHolidayAttendanceModal);
    }

    // Select all functionality for guru checkboxes
    if (document.getElementById('selectAllGurus')) {
        document.getElementById('selectAllGurus').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.guru-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Update select all checkbox when individual checkboxes change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('guru-checkbox')) {
            const selectAllCheckbox = document.getElementById('selectAllGurus');
            if (selectAllCheckbox) {
                const checkboxes = document.querySelectorAll('.guru-checkbox');
                const checkedBoxes = document.querySelectorAll('.guru-checkbox:checked');

                selectAllCheckbox.checked = checkboxes.length === checkedBoxes.length;
                selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
            }
        }
    });

    // Edit holiday attendance function
    function editHolidayAttendance(date, jam_masuk, jam_pulang, keterangan) {
        // Populate form with existing data
        document.getElementById('attendance_holiday_date').value = date;
        document.getElementById('attendance_jam_masuk').value = jam_masuk;
        document.getElementById('attendance_jam_pulang').value = jam_pulang;
        document.getElementById('attendance_keterangan').value = keterangan;

        // Fetch and check the gurus for this holiday
        fetch('daily_recap.php?get_holiday_gurus=1&date=' + encodeURIComponent(date) + '&jam_masuk=' + encodeURIComponent(jam_masuk) + '&jam_pulang=' + encodeURIComponent(jam_pulang) + '&keterangan=' + encodeURIComponent(keterangan))
            .then(response => response.json())
            .then(data => {
                // Uncheck all first
                document.querySelectorAll('.guru-checkbox').forEach(cb => cb.checked = false);

                // Check the relevant gurus
                data.gurus.forEach(username => {
                    const checkbox = document.getElementById('guru_' + username);
                    if (checkbox) checkbox.checked = true;
                });

                // Update select all state
                updateSelectAllState();
            })
            .catch(error => console.error('Error fetching holiday gurus:', error));

        // Change form to edit mode
        const form = document.getElementById('holidayAttendanceForm');
        const submitBtn = document.querySelector('#holidayAttendanceForm button[name="add_holiday_attendance"]');
        const formTitle = document.querySelector('#holidayAttendanceModal h4');

        formTitle.textContent = 'Edit Hari Libur Attendance';
        submitBtn.innerHTML = '<i class="fas fa-save"></i><span>Update Hari Libur Attendance</span>';
        submitBtn.name = 'update_holiday_attendance';

        // Add hidden inputs for original data
        if (!document.getElementById('original_date')) {
            const originalDateInput = document.createElement('input');
            originalDateInput.type = 'hidden';
            originalDateInput.id = 'original_date';
            originalDateInput.name = 'original_date';
            form.appendChild(originalDateInput);
        }
        if (!document.getElementById('original_jam_masuk')) {
            const originalJamMasukInput = document.createElement('input');
            originalJamMasukInput.type = 'hidden';
            originalJamMasukInput.id = 'original_jam_masuk';
            originalJamMasukInput.name = 'original_jam_masuk';
            form.appendChild(originalJamMasukInput);
        }
        if (!document.getElementById('original_jam_pulang')) {
            const originalJamPulangInput = document.createElement('input');
            originalJamPulangInput.type = 'hidden';
            originalJamPulangInput.id = 'original_jam_pulang';
            originalJamPulangInput.name = 'original_jam_pulang';
            form.appendChild(originalJamPulangInput);
        }
        if (!document.getElementById('original_keterangan')) {
            const originalKeteranganInput = document.createElement('input');
            originalKeteranganInput.type = 'hidden';
            originalKeteranganInput.id = 'original_keterangan';
            originalKeteranganInput.name = 'original_keterangan';
            form.appendChild(originalKeteranganInput);
        }

        document.getElementById('original_date').value = date;
        document.getElementById('original_jam_masuk').value = jam_masuk;
        document.getElementById('original_jam_pulang').value = jam_pulang;
        document.getElementById('original_keterangan').value = keterangan;

        // Scroll to form
        document.querySelector('#holidayAttendanceModal .modal-content').scrollTop = 0;
    }

    // Update select all state
    function updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('selectAllGurus');
        const checkboxes = document.querySelectorAll('.guru-checkbox');
        const checkedBoxes = document.querySelectorAll('.guru-checkbox:checked');

        selectAllCheckbox.checked = checkboxes.length === checkedBoxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
    }

    // Close modals when clicking outside
    document.getElementById('holidayModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeHolidayModal();
        }
    });

    document.getElementById('holidayAttendanceModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeHolidayAttendanceModal();
        }
    });

    if (document.getElementById('deleteModal')) {
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    }

    // Show toast if success or error
    <?php if (isset($_GET['success'])): ?>
    showToast('<?= htmlspecialchars($_GET['success']) ?>', 'success');
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    showToast('<?= htmlspecialchars($_GET['error']) ?>', 'error');
    <?php endif; ?>
    </script>
