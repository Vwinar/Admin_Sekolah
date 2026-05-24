<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    // Clear session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();
    header('Location: login.php');
    exit();
}

$db = new SQLite3('absen.db');

$user_id = $_SESSION['user_id'];
$action_type = $_SESSION['action_type'] ?? 'absen';

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

date_default_timezone_set('Asia/Jakarta');
$date = date('Y-m-d');
$time = date('H:i:s');
$waktuMasukSetting = '07:00:00'; // default fallback
$waktuPulangSetting = '12:00:00'; // default fallback
$radiusSetting = 25; // default fallback radius in meters
$setting_stmt = $db->prepare('SELECT waktu_masuk, waktu_pulang, radius FROM settings WHERE id = 1');
$setting_result = $setting_stmt->execute();
$setting_row = $setting_result->fetchArray(SQLITE3_ASSOC);
if ($setting_row) {
    if (!empty($setting_row['waktu_masuk'])) {
        $waktuMasukSetting = $setting_row['waktu_masuk'];
    }
    if (!empty($setting_row['waktu_pulang'])) {
        $waktuPulangSetting = $setting_row['waktu_pulang'];
    }
    if (!empty($setting_row['radius'])) {
        $radiusSetting = floatval($setting_row['radius']);
    }
}
$late = ($time > $waktuMasukSetting);

$error = '';
$success = '';
$disable_absen_masuk = false;
$disable_absen_pulang = false;
$toast_message = '';

// Ensure uploads directory exists
$upload_dir = __DIR__ . '/uploads';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fetch attendance and izin status for today
$attendance_stmt = $db->prepare('SELECT jam_masuk, jam_pulang, status, keterangan FROM attendance WHERE user_id = :user_id AND date = :date');
$attendance_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$attendance_stmt->bindValue(':date', $date, SQLITE3_TEXT);
$attendance_result = $attendance_stmt->execute();
$attendance_row = $attendance_result->fetchArray(SQLITE3_ASSOC);

$izin_stmt = $db->prepare('SELECT COUNT(*) as count FROM izin WHERE user_id = :user_id AND date = :date');
$izin_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$izin_stmt->bindValue(':date', $date, SQLITE3_TEXT);
$izin_result = $izin_stmt->execute();
$izin_row = $izin_result->fetchArray(SQLITE3_ASSOC);

$disable_absen_masuk = $attendance_row && $attendance_row['jam_masuk'] ? true : false;
$disable_absen_pulang = $attendance_row && $attendance_row['jam_pulang'] ? true : false;
$absen_masuk_status = $attendance_row ? $attendance_row['status'] : 'Tepat Waktu';
$absen_masuk_keterangan = $attendance_row ? $attendance_row['keterangan'] : 'Tepat Waktu';

$izin_exists = $izin_row && $izin_row['count'] > 0;
if ($izin_exists) {
    $toast_message = 'Anda sudah melakukan izin hari ini.';
}

// Set status and keterangan for absen pulang based on current time
if ($time < $waktuPulangSetting) {
    $absen_pulang_status = 'Pulang Cepat';
    $absen_pulang_keterangan = 'Pulang Cepat';
} else {
    $absen_pulang_status = 'Pulang Tepat Waktu';
    $absen_pulang_keterangan = 'Pulang Tepat Waktu';
}

// Disable izin if user already absen masuk or absen pulang
$izin_submitted = false;
if ($toast_message) {
    $izin_submitted = true;
}
if ($disable_absen_masuk || $disable_absen_pulang) {
    $toast_message = 'Anda sudah melakukan absen, tidak dapat mengajukan izin.';
}


//awal kode perbaikian tanggal 6 gabungan fungsi

$waktuPulangOtomatisSetting = $waktuPulangSetting; // default to waktu_pulang
$setting_otomatis_stmt = $db->prepare('SELECT waktu_pulang_otomatis FROM settings WHERE id = 1');
$setting_otomatis_result = $setting_otomatis_stmt->execute();
$setting_otomatis_row = $setting_otomatis_result->fetchArray(SQLITE3_ASSOC);
if ($setting_otomatis_row && !empty($setting_otomatis_row['waktu_pulang_otomatis'])) {
    $waktuPulangOtomatisSetting = $setting_otomatis_row['waktu_pulang_otomatis'];
}

// --- Bagian 1: Otomatis pulang jika waktu >= waktu_pulang_otomatis ---
if (strtotime($time) >= strtotime($waktuPulangOtomatisSetting) && !$disable_absen_pulang && $disable_absen_masuk) {
    $stmt = $db->prepare('
        UPDATE attendance 
        SET jam_pulang = :jam_pulang, keterangan = :keterangan 
        WHERE user_id = :user_id AND date = :date AND jam_pulang IS NULL
    ');
    $stmt->bindValue(':jam_pulang', $time, SQLITE3_TEXT);
    $stmt->bindValue(':keterangan', 'Tidak Absen Pulang', SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $stmt->execute();
    
    $disable_absen_pulang = true;
    $success = 'Anda telah dipulangkan secara otomatis pada pukul ' . $time . '.';
}
$auto_pulang_after_14 = false;


// --- Bagian 2: Perbaiki data absensi terlewat saat absen masuk ---
if (!$disable_absen_masuk) {
    // Cari tanggal terakhir kali user absen masuk
    $stmtLast = $db->prepare("
        SELECT MAX(date) as last_date 
        FROM attendance 
        WHERE user_id = :user_id AND jam_masuk IS NOT NULL AND date < :current_date
    ");
    $stmtLast->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmtLast->bindValue(':current_date', $date, SQLITE3_TEXT);
    $resultLast = $stmtLast->execute();
    $rowLast = $resultLast->fetchArray(SQLITE3_ASSOC);

    $lastDate = $rowLast['last_date'];

    if ($lastDate) {
        // Cek apakah ada tanggal antara lastDate dan kemarin yang belum absen pulang
        $stmtMissing = $db->prepare("
            SELECT date FROM attendance 
            WHERE user_id = :user_id 
              AND date BETWEEN :start_date AND DATE(:current_date, '-1 day') 
              AND jam_pulang IS NULL
        ");
        $stmtMissing->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmtMissing->bindValue(':start_date', $lastDate, SQLITE3_TEXT);
        $stmtMissing->bindValue(':current_date', $date, SQLITE3_TEXT);
        $resultMissing = $stmtMissing->execute();

        while ($row = $resultMissing->fetchArray(SQLITE3_ASSOC)) {
            $missingDate = $row['date'];
            
            // Update setiap tanggal yang belum absen pulang
            $stmtUpdate = $db->prepare("
                UPDATE attendance
                SET jam_pulang = :jam_pulang, keterangan = 'Tidak Absen Pulang'
                WHERE user_id = :user_id AND date = :missing_date
            ");
            $stmtUpdate->bindValue(':jam_pulang', $waktuPulangOtomatisSetting, SQLITE3_TEXT);
            $stmtUpdate->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
            $stmtUpdate->bindValue(':missing_date', $missingDate, SQLITE3_TEXT);
            $stmtUpdate->execute();
        }

        $success = 'Data absensi yang terlewat telah diperbarui secara otomatis.';
    }
}


//akhir kode perbaikian tanggal 6 gabungan fungsi



// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'absen_masuk') {
            if ($disable_absen_masuk) {
                $error = 'Anda sudah melakukan absen masuk hari ini.';
            } elseif ($izin_exists) {
                $error = 'Anda sudah melakukan izin hari ini.';
            } else {
                $foto = $_FILES['foto'] ?? null;
                $lokasi_lat = $_POST['lokasi_lat'] ?? null;
                $lokasi_lng = $_POST['lokasi_lng'] ?? null;
                $jarak = floatval($_POST['jarak'] ?? 0);

                if (empty($lokasi_lat) || empty($lokasi_lng)) {
                    $error = 'Lokasi harus dikirim.';
                } elseif ($jarak > $radiusSetting) {
                    $error = 'Anda berada di luar radius ' . $radiusSetting . ' meter, tidak dapat absen masuk.';
                    $disable_absen_masuk = true;
                } else {
                    $foto_path = null;
                    if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
                        $filename = 'masuk_' . $user_id . '_' . time() . '.' . $ext;
                        $filepath = __DIR__ . '/uploads/' . $filename;
                        $db_path = 'uploads/' . $filename;
                        if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                            $foto_path = $db_path;
                        } else {
                            $error = 'Gagal mengupload foto.';
                        }
                    }
                    if (!$error) {
                        $status = $late ? 'Terlambat' : 'Tepat Waktu';
                        $keterangan = $late ? 'Terlambat' : 'Tepat Waktu';
                        $stmt = $db->prepare('INSERT INTO attendance (user_id, date, jam_masuk, status, keterangan, lokasi_lat, lokasi_lng, jarak, foto_masuk) VALUES (:user_id, :date, :jam_masuk, :status, :keterangan, :lokasi_lat, :lokasi_lng, :jarak, :foto_masuk)');
                        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $stmt->bindValue(':date', $date, SQLITE3_TEXT);
                        $stmt->bindValue(':jam_masuk', $time, SQLITE3_TEXT);
                        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                        $stmt->bindValue(':keterangan', $keterangan, SQLITE3_TEXT);
                        $stmt->bindValue(':lokasi_lat', $lokasi_lat, SQLITE3_FLOAT);
                        $stmt->bindValue(':lokasi_lng', $lokasi_lng, SQLITE3_FLOAT);
                        $stmt->bindValue(':jarak', $jarak, SQLITE3_FLOAT);
                        $stmt->bindValue(':foto_masuk', $foto_path, SQLITE3_TEXT);
                        $result = $stmt->execute();
                        if ($result) {
                            $success = 'Absen Masuk berhasil disimpan.';
                            $disable_absen_masuk = true;
                            // Reset absen pulang disable to allow pulang after masuk
                            $disable_absen_pulang = false;
                        } else {
                            $error = 'Gagal menyimpan Absen Masuk.';
                        }
                    }
            }

        }

} elseif ($action === 'absen_pulang') {
        if ($disable_absen_pulang) {
            $error = 'Anda sudah melakukan absen pulang hari ini.';
        } elseif ($izin_exists) {
            $error = 'Anda sudah melakukan izin hari ini.';
        } else {
            $foto = $_FILES['foto'] ?? null;
            $lokasi_lat = $_POST['lokasi_lat'] ?? null;
            $lokasi_lng = $_POST['lokasi_lng'] ?? null;
            $jarak = floatval($_POST['jarak'] ?? 0);

            if (empty($lokasi_lat) || empty($lokasi_lng)) {
                $error = 'Lokasi harus dikirim.';
            } elseif ($jarak > $radiusSetting) {
                $error = 'Anda berada di luar radius ' . $radiusSetting . ' meter, tidak dapat absen pulang.';
                $disable_absen_pulang = true;
            } else {
                $foto_path = null;
                if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
                    $filename = 'pulang_' . $user_id . '_' . time() . '.' . $ext;
                    $filepath = __DIR__ . '/uploads/' . $filename;
                    $db_path = 'uploads/' . $filename;
                    if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                        $foto_path = $db_path;
                    } else {
                        $error = 'Gagal mengupload foto.';
                    }
                }
                if (!$error) {
                    // Tambahkan logika untuk keterangan
                    $timeObj = new DateTime($time); // format $time harus seperti 'H:i:s'
                    $batasWaktu = new DateTime($waktuPulangSetting);

                    if ($timeObj < $batasWaktu) {
                        $keterangan = 'Pulang Cepat';
                    } else {
                        $keterangan = 'Pulang Tepat Waktu';
                    }
                    $stmt = $db->prepare('UPDATE attendance SET jam_pulang = :jam_pulang, foto_pulang = :foto_pulang, keterangan = :keterangan, lokasi_lat = :lokasi_lat, lokasi_lng = :lokasi_lng, jarak = :jarak WHERE user_id = :user_id AND date = :date');
                    $stmt->bindValue(':jam_pulang', $time, SQLITE3_TEXT);
                    $stmt->bindValue(':foto_pulang', $foto_path, SQLITE3_TEXT);
                    $stmt->bindValue(':keterangan', $keterangan, SQLITE3_TEXT);
                    $stmt->bindValue(':lokasi_lat', $lokasi_lat, SQLITE3_FLOAT);
                    $stmt->bindValue(':lokasi_lng', $lokasi_lng, SQLITE3_FLOAT);
                    $stmt->bindValue(':jarak', $jarak, SQLITE3_FLOAT);
                    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':date', $date, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    if ($result) {
                        $success = 'Absen Pulang berhasil disimpan.';
                        $disable_absen_pulang = true;
                    } else {
                        $error = 'Gagal menyimpan Absen Pulang.';
                    }
                }
            }
        }
    } elseif ($action === 'izin') {
        if ($toast_message) {
            $error = $toast_message;
        } else {
            $tanggal_izin = $_POST['tanggal_izin'] ?? null;
            $alasan_izin = $_POST['alasan_izin'] ?? null;
            $foto = $_FILES['foto'] ?? null;
            $lokasi_lat = $_POST['lokasi_lat'] ?? null;
            $lokasi_lng = $_POST['lokasi_lng'] ?? null;
            $jarak = floatval($_POST['jarak'] ?? 0);

            if ($tanggal_izin && $alasan_izin) {
                if (empty($lokasi_lat) || empty($lokasi_lng)) {
                    $error = 'Lokasi harus dikirim.';
                } else {
                    $foto_path = null;
                    if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                        if (!in_array($ext, $allowed_ext)) {
                            $error = 'Format file foto tidak didukung. Gunakan jpg, jpeg, png, gif, bmp, atau webp.';
                        } else {
                            $filename = 'izin_' . $user_id . '_' . time() . '.' . $ext;
                            $filepath = __DIR__ . '/uploads/' . $filename;
                            $db_path = 'uploads/' . $filename;

                            if (!is_dir(__DIR__ . '/uploads')) {
                                mkdir(__DIR__ . '/uploads', 0777, true);
                            }

                            if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                                $foto_path = $db_path;
                            } else {
                                $error = 'Gagal mengupload foto izin.';
                            }
                        }
                    }
                    if (!$error) {
                        $stmt = $db->prepare('INSERT INTO izin (user_id, date, jenis_izin, keterangan, foto, status, lokasi_lat, lokasi_lng, jarak) VALUES (:user_id, :date, :jenis_izin, :keterangan, :foto, :status, :lokasi_lat, :lokasi_lng, :jarak)');
                        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                        $stmt->bindValue(':date', $tanggal_izin, SQLITE3_TEXT);
                        $stmt->bindValue(':jenis_izin', 'izin', SQLITE3_TEXT);
                        $stmt->bindValue(':keterangan', $alasan_izin, SQLITE3_TEXT);
                        $stmt->bindValue(':foto', $foto_path, SQLITE3_TEXT);
                        $stmt->bindValue(':status', 'approved', SQLITE3_TEXT);
                        $stmt->bindValue(':lokasi_lat', $lokasi_lat, SQLITE3_FLOAT);
                        $stmt->bindValue(':lokasi_lng', $lokasi_lng, SQLITE3_FLOAT);
                        $stmt->bindValue(':jarak', $jarak, SQLITE3_FLOAT);
                        $result = $stmt->execute();
                        if ($result) {
                            $success = 'Izin berhasil diajukan.';
                            $toast_message = 'Anda sudah mengajukan izin hari ini.';
                        } else {
                            $error = 'Gagal mengajukan izin. Error DB: ' . $db->lastErrorMsg();
                        }
                    }
                }
            } else {
                $error = 'Tanggal dan alasan izin wajib diisi.';
            }
        }
    }
}

$month_filter = date('Y-m'); // current year-month filter

// Fetch user's attendance and izin records for rekap filtered by current month only
$rekap_query = "
SELECT date, jam_masuk, jam_pulang, status, keterangan, 'absen' AS type
FROM attendance
WHERE user_id = :user_id AND strftime('%Y-%m', date) = :month_filter
UNION ALL
SELECT date, NULL AS jam_masuk, NULL AS jam_pulang, status, keterangan, 'izin' AS type
FROM izin
WHERE user_id = :user_id AND strftime('%Y-%m', date) = :month_filter
ORDER BY date DESC
";

$rekap_stmt = $db->prepare($rekap_query);
$rekap_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
$rekap_stmt->bindValue(':month_filter', $month_filter, SQLITE3_TEXT);
$rekap_results = $rekap_stmt->execute();

$rekap_records = [];
while ($row = $rekap_results->fetchArray(SQLITE3_ASSOC)) {
    $rekap_records[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Absen App - Guru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#1e40af',
                        danger: '#ef4444',
                        success: '#10b981',
                        warning: '#f59e0b',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
        }

        .card {
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .btn {
            transition: all 0.3s ease;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #3b82f6;
        }

        .btn-primary:hover {
            background-color: #2563eb;
        }

        .btn-danger {
            background-color: #ef4444;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            animation: slideIn 0.5s forwards, fadeOut 0.5s 3s forwards;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .tab-active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }

        .map-container {
            height: 300px;
            border-radius: 12px;
            overflow: hidden;
        }

        .loading-spinner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-content {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            .mobile-flex-col {
                flex-direction: column;
            }

            .mobile-w-full {
                width: 100% !important;
            }

            .map-container {
                height: 250px;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Loading Spinner -->
    <div id="loadingSpinner" class="loading-spinner hidden">
        <div class="spinner-content">
            <div class="spinner"></div>
            <p class="text-gray-700 font-medium">Memproses...</p>
        </div>
    </div>

    <!-- Notification Toast at the top -->
    <?php if ($error): ?>
        <div class="toast">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="toast">
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm"><?= htmlspecialchars($success) ?></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Absen App</h1>
                <p class="text-gray-600">Selamat datang, <span class="font-semibold text-primary"><?= htmlspecialchars($fullname) ?></span></p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-center bg-blue-50 px-4 py-2 rounded-lg">
                    <p class="text-sm text-gray-500">Tanggal</p>
                    <p class="font-semibold text-primary"><?= date('d M Y') ?></p>
                </div>
                <div class="text-center bg-blue-50 px-4 py-2 rounded-lg">
                    <p class="text-sm text-gray-500">Jam</p>
                    <p class="font-semibold text-primary" id="liveClock"><?= date('H:i:s') ?></p>
                </div>
                <a href="logout.php" class="btn btn-danger text-white px-4 py-2 flex items-center gap-2">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden md:inline">Logout</span>
                </a>
                <button id="refreshButton" class="btn btn-primary text-white px-4 py-2 flex items-center gap-2 ml-2">
                    <i class="fas fa-sync-alt"></i>
                    <span class="hidden md:inline">Refresh</span>
                </button>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex overflow-x-auto mb-6 border-b border-gray-200">
            <?php
            // Determine tab states based on user attendance and izin status
            $disable_absen_masuk_tab = false;
            $disable_absen_pulang_tab = true;
            $disable_izin_tab = false;
            $disable_rekap_tab = true;

            // New flag to indicate izin success
            $izin_success = false;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'izin' && $success) {
                $izin_success = true;
            }

            // If login with izin action, disable absen masuk
            if ($action_type === 'izin') {
                $disable_absen_masuk_tab = true;
            }

            if ($izin_exists) {
                // If izin exists for today: enable rekap, disable absen masuk, absen pulang, and izin
                $disable_absen_masuk_tab = true;
                $disable_absen_pulang_tab = true;
                $disable_izin_tab = true;
                $disable_rekap_tab = false;
            } elseif ($izin_success) {
                // After izin success: enable rekap, disable absen masuk and absen pulang
                $disable_absen_masuk_tab = true;
                $disable_absen_pulang_tab = true;
                $disable_izin_tab = true;
                $disable_rekap_tab = false;
            } elseif (!$disable_absen_masuk && !$disable_absen_pulang && !$izin_submitted) {
                // First time: disable absen pulang and rekap
                $disable_absen_pulang_tab = true;
                $disable_rekap_tab = true;
            } elseif ($disable_absen_masuk && !$disable_absen_pulang && !$izin_submitted) {
                // After absen masuk: disable absen masuk, izin, and rekap
                $disable_absen_masuk_tab = true;
                $disable_izin_tab = true;
                $disable_rekap_tab = true;
                $disable_absen_pulang_tab = false;
            } elseif ($disable_absen_pulang || $izin_submitted) {
                // After absen pulang or izin submitted: disable absen pulang and izin, enable rekap only
                $disable_absen_pulang_tab = true;
                $disable_absen_masuk_tab = true;
                $disable_izin_tab = true;
                $disable_rekap_tab = false;
            }
            ?>

            <button onclick="showTab('absen_masuk')" id="tab_absen_masuk" class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_absen_masuk_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_absen_masuk_tab ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_absen_masuk_tab ? 'disabled' : '' ?>>
                <i class="fas fa-sign-in-alt mr-2"></i>Absen Masuk
            </button>
            <button onclick="showTab('absen_pulang')" id="tab_absen_pulang" class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_absen_pulang_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_absen_pulang_tab ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_absen_pulang_tab ? 'disabled' : '' ?>>
                <i class="fas fa-sign-out-alt mr-2"></i>Absen Pulang
            </button>
            <button onclick="showTab('izin')" id="tab_izin" class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_izin_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_izin_tab ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_izin_tab ? 'disabled' : '' ?>>
                <i class="fas fa-envelope mr-2"></i>Izin
            </button>
            <button onclick="showTab('rekap')" id="tab_rekap" class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_rekap_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_rekap_tab ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_rekap_tab ? 'disabled' : '' ?>>
                <i class="fas fa-history mr-2"></i>Rekap
            </button>
        </div>

        <!-- Tab Contents -->
        <div class="space-y-6">
            <!-- Absen Masuk Tab -->
            <div id="absen_masuk_content" class="tab-content">
                <div class="card bg-white p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-sign-in-alt text-primary"></i>
                        Absen Masuk
                    </h2>
                    
                    <form method="POST" action="user_home.php" enctype="multipart/form-data" id="absenMasukForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                <input type="text" value="<?= $date ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam</label>
                                <input type="text" value="<?= $time ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <input type="text" value="<?= $late ? 'Terlambat' : 'Tepat Waktu' ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent <?= $late ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>" />
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang</label>
                            <div id="mapMasuk" class="map-container"></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah (meter)</label>
                            <input type="text" id="jarakMasuk" name="jarak" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Selfie</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition" onclick="document.getElementById('fotoMasuk').click()">
                                        <input type="file" id="fotoMasuk" accept="image/*" capture="environment" name="foto" class="hidden" onchange="previewFoto(event, 'fotoPreviewMasuk')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Pastikan wajah terlihat jelas</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewMasuk" src="#" alt="Preview Foto" class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoMasuk" class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="lokasi_lat" id="lokasiLatMasuk" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngMasuk" />
                        
                        <button type="submit" name="action" value="absen_masuk" class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $disable_absen_masuk ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_absen_masuk ? 'disabled' : '' ?>>
                            <i class="fas fa-check-circle"></i>
                            Submit Absen Masuk
                        </button>
                    </form>
                </div>
            </div>

            <!-- Absen Pulang Tab -->
            <div id="absen_pulang_content" class="tab-content hidden">
                <div class="card bg-white p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-sign-out-alt text-primary"></i>
                        Absen Pulang
                    </h2>
                    
                    <form method="POST" action="user_home.php" enctype="multipart/form-data" id="absenPulangForm" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                <input type="text" value="<?= $date ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam</label>
                                <input type="text" value="<?= $time ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <input type="text" value="<?= htmlspecialchars($absen_pulang_status) ?>" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent <?= $absen_pulang_status === 'Pulang Cepat' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>" />
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang</label>
                            <div id="mapPulang" class="map-container"></div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah (meter)</label>
                            <input type="text" id="jarakPulang" name="jarak" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Selfie</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition" onclick="document.getElementById('fotoPulang').click()">
                                        <input type="file" id="fotoPulang" accept="image/*" capture="environment" name="foto" class="hidden" onchange="previewFoto(event, 'fotoPreviewPulang')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Pastikan wajah terlihat jelas</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewPulang" src="#" alt="Preview Foto" class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoPulang" class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="lokasi_lat" id="lokasiLatPulang" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngPulang" />
                        
                        <button type="submit" name="action" value="absen_pulang" class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $disable_absen_pulang ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $disable_absen_pulang ? 'disabled' : '' ?>>
                            <i class="fas fa-check-circle"></i>
                            Submit Absen Pulang
                        </button>
                    </form>
                </div>
            </div>

            <!-- Izin Tab -->
            <div id="izin_content" class="tab-content hidden">
                <div class="card bg-white p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-envelope text-primary"></i>
                        Pengajuan Izin
                    </h2>
                    
                    <?php if ($toast_message): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-info-circle text-blue-500"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm"><?= htmlspecialchars($toast_message) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="user_home.php" enctype="multipart/form-data" id="izinForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Izin</label>
                            <input type="date" name="tanggal_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="<?= $date ?>" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Izin</label>
                            <textarea name="alasan_izin" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" rows="4" placeholder="Masukkan alasan izin Anda"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang</label>
                            <div id="mapIzin" class="map-container"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah (meter)</label>
                            <input type="text" id="jarakIzin" name="jarak" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Bukti</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition" onclick="document.getElementById('fotoIzin').click()">
                                        <input type="file" id="fotoIzin" accept="image/*" capture="environment" name="foto" class="hidden" onchange="previewFoto(event, 'fotoPreviewIzin')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Foto bukti yang relevan</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewIzin" src="#" alt="Preview Foto" class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoIzin" class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="lokasi_lat" id="lokasiLatIzin" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngIzin" />
                        
                        <button type="submit" name="action" value="izin" class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $toast_message ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= $toast_message ? 'disabled' : '' ?>>
                            <i class="fas fa-paper-plane"></i>
                            Ajukan Izin
                        </button>
                    </form>
                </div>
            </div>

            <!-- Rekap Tab -->
            <div id="rekap_content" class="tab-content hidden">
                <div class="card bg-white p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-800 flex items-center gap-2">
                        <i class="fas fa-history text-primary"></i>
                        Rekap Absensi dan Izin
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Masuk</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam Pulang</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($rekap_records) === 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Tidak ada data absensi atau izin.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rekap_records as $record): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($record['date'] ?? '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($record['jam_masuk'] ?? '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($record['jam_pulang'] ?? '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= ($record['status'] === 'Tepat Waktu') ? 'bg-green-100 text-green-800' : '' ?>
                                                    <?= ($record['status'] === 'Terlambat') ? 'bg-red-100 text-red-800' : '' ?>
                                                    <?= ($record['status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                                    <?= ($record['status'] === 'approved') ? 'bg-blue-100 text-blue-800' : '' ?>
                                                    <?= ($record['status'] === 'rejected') ? 'bg-gray-100 text-gray-800' : '' ?>">
                                                    <?= htmlspecialchars($record['status'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($record['keterangan'] ?? '-') ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= ($record['type'] === 'absen') ? 'bg-purple-100 text-purple-800' : 'bg-indigo-100 text-indigo-800' ?>">
                                                    <?= htmlspecialchars($record['type'] ?? '-') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update live clock
        function updateClock() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show selected tab content
            document.getElementById(`${tabName}_content`).classList.remove('hidden');
            
            // Update active tab button
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active', 'text-primary');
                btn.classList.add('text-gray-500');
            });
            
            document.getElementById(`tab_${tabName}`).classList.add('tab-active', 'text-primary');
            document.getElementById(`tab_${tabName}`).classList.remove('text-gray-500');
            
            // Refresh maps when tab is shown
            if (tabName === 'absen_masuk' || tabName === 'absen_pulang' || tabName === 'izin') {
                setTimeout(() => {
                    let section;
                    if (tabName === 'absen_masuk') section = 'Masuk';
                    else if (tabName === 'absen_pulang') section = 'Pulang';
                    else if (tabName === 'izin') section = 'Izin';
                    setLocation(section);
                }, 100);
            }
        }

        // Show default tab based on time and availability
        window.onload = function() {
            if (actionType === 'izin') {
                showTab('izin');
                return;
            }

            const now = new Date();
            const hours = now.getHours();

            // Check if izin was successful
            const izinSuccess = <?= json_encode($izin_success) ?>;
            const izinExists = <?= json_encode($izin_exists) ?>;

            if (izinSuccess || izinExists) {
                // Enable rekap tab, disable absen masuk and absen pulang tabs
                const tabRekap = document.getElementById('tab_rekap');
                const tabAbsenMasuk = document.getElementById('tab_absen_masuk');
                const tabAbsenPulang = document.getElementById('tab_absen_pulang');
                const tabIzin = document.getElementById('tab_izin');

                if (tabRekap) {
                    tabRekap.disabled = false;
                    tabRekap.classList.remove('opacity-50', 'cursor-not-allowed', 'text-gray-500');
                    tabRekap.classList.add('tab-active', 'text-primary');
                }
                if (tabAbsenMasuk) {
                    tabAbsenMasuk.disabled = true;
                    tabAbsenMasuk.classList.add('opacity-50', 'cursor-not-allowed', 'text-gray-500');
                    tabAbsenMasuk.classList.remove('tab-active', 'text-primary');
                }
                if (tabAbsenPulang) {
                    tabAbsenPulang.disabled = true;
                    tabAbsenPulang.classList.add('opacity-50', 'cursor-not-allowed', 'text-gray-500');
                    tabAbsenPulang.classList.remove('tab-active', 'text-primary');
                }
                if (tabIzin) {
                    tabIzin.disabled = true;
                    tabIzin.classList.add('opacity-50', 'cursor-not-allowed', 'text-gray-500');
                    tabIzin.classList.remove('tab-active', 'text-primary');
                }

                // Show rekap tab content
                showTab('rekap');
            } else {
                // Default to masuk tab if before 12pm and not yet absen masuk
                if (hours < 12 && !<?= $disable_absen_masuk ? 'true' : 'false' ?>) {
                    showTab('absen_masuk');
                } 
                // Default to pulang tab if after 12pm and not yet absen pulang
                else if (hours >= 12 && !<?= $disable_absen_pulang ? 'true' : 'false' ?>) {
                    showTab('absen_pulang');
                }
                // Otherwise show rekap
                else {
                    showTab('rekap');
                }
            }
        };

        // Photo preview function
        function previewFoto(event, previewId) {
            const input = event.target;
            const preview = document.getElementById(previewId);
            const noFoto = document.getElementById(`no${previewId.replace('Preview', '')}`);
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    noFoto.classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.src = '#';
                preview.classList.add('hidden');
                noFoto.classList.remove('hidden');
            }
        }

        // Location and map functions
        <?php
        // Fetch latitude and longitude from settings table
        $loc_stmt = $db->prepare('SELECT latitude, longitude FROM settings WHERE id = 1');
        $loc_result = $loc_stmt->execute();
        $loc_row = $loc_result->fetchArray(SQLITE3_ASSOC);
        $target_lat = $loc_row['latitude'] ?? -7.099067729138387;
        $target_lng = $loc_row['longitude'] ?? 112.28058418581023;
        ?>
        const TARGET_LAT = <?php echo json_encode($target_lat); ?>;
        const TARGET_LNG = <?php echo json_encode($target_lng); ?>;
        const SCHOOL_MARKER_ICON = L.icon({
            iconUrl: 'https://cdn0.iconfinder.com/data/icons/small-n-flat/24/678111-map-marker-512.png',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
            const R = 6371000; // Radius of the earth in meters
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const d = R * c; // Distance in meters
            return d;
        }

        function setLocation(section) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const distance = getDistanceFromLatLonInM(lat, lng, TARGET_LAT, TARGET_LNG);

                    // Update form fields
                    document.getElementById(`lokasiLat${section}`).value = lat;
                    document.getElementById(`lokasiLng${section}`).value = lng;
                    document.getElementById(`jarak${section}`).value = distance.toFixed(2);

                    // Disable submit button if outside radius (except for Izin)
                    let formId;
                    if (section === 'Masuk') formId = 'absenMasukForm';
                    else if (section === 'Pulang') formId = 'absenPulangForm';
                    else if (section === 'Izin') formId = 'izinForm';
                    const form = document.getElementById(formId);
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (section !== 'Izin' && distance > <?php echo json_encode($radiusSetting); ?>) {
                            submitBtn.disabled = true;
                            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                            submitBtn.classList.remove('bg-primary', 'hover:bg-secondary');
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                            submitBtn.classList.add('bg-primary', 'hover:bg-secondary');
                        }
                    }

                    // Initialize or update map
                    const mapElement = document.getElementById(`map${section}`);
                    if (mapElement && !mapElement._map) {
                        const map = L.map(mapElement).setView([lat, lng], 16);
                        mapElement._map = map;

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap'
                        }).addTo(map);

                        // Marker for user location
                        const userMarker = L.marker([lat, lng]).addTo(map)
                            .bindPopup('Lokasi Anda')
                            .openPopup();

                        // Marker for target location
                        const targetMarker = L.marker([TARGET_LAT, TARGET_LNG], {icon: SCHOOL_MARKER_ICON})
                            .addTo(map)
                            .bindPopup('Lokasi Sekolah');

                        // Line connecting user and school
                        const line = L.polyline([[lat, lng], [TARGET_LAT, TARGET_LNG]], {
                            color: 'blue',
                            dashArray: '5, 5'
                        }).addTo(map);
                        // Circle for 50 radius
                        const circle = L.circle([TARGET_LAT, TARGET_LNG], {
                            color: '#3b82f6',
                            fillColor: '#3b82f6',
                            fillOpacity: 0.1,
                            radius: <?php echo json_encode($radiusSetting); ?>
                        }).addTo(map);
                        // Fit map to show both locations
                        map.fitBounds([
                            [lat, lng],
                            [TARGET_LAT, TARGET_LNG]
                        ], {padding: [50, 50]});
                    } else if (mapElement._map) {
                        const map = mapElement._map;
                        map.setView([lat, lng]);
                        map.eachLayer(layer => {
                            if (layer instanceof L.Marker && layer.getLatLng().lat === TARGET_LAT) {
                                // Skip school marker
                                return;
                            }
                            if (layer instanceof L.Marker) {
                                layer.setLatLng([lat, lng]);
                            } else if (layer instanceof L.Polyline) {
                                layer.setLatLngs([[lat, lng], [TARGET_LAT, TARGET_LNG]]);
                            }
                        });
                        // Always center map on user location
                        map.panTo(new L.LatLng(lat, lng));
                    }
                }, function(error) {
                    console.error('Error getting location:', error);
                    alert('Gagal mendapatkan lokasi. Pastikan lokasi diaktifkan dan izin diberikan.');
                    // Disable submit button for absen masuk or izin if location not active
                    if (section === 'Masuk' || section === 'Izin') {
                        let formId;
                        if (section === 'Masuk') formId = 'absenMasukForm';
                        else if (section === 'Pulang') formId = 'absenPulangForm';
                        else if (section === 'Izin') formId = 'izinForm';
                        const form = document.getElementById(formId);
                        if (form) {
                            const submitBtn = form.querySelector('button[type="submit"]');
                            submitBtn.disabled = true;
                            submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                            submitBtn.classList.remove('bg-primary', 'hover:bg-secondary');
                        }
                    }
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alert('Geolocation tidak didukung oleh browser Anda.');
                // Disable submit button for absen masuk or izin if geolocation not supported
                if (section === 'Masuk' || section === 'Izin') {
                    const form = document.getElementById(`absen${section}Form`);
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.disabled = true;
                        submitBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                        submitBtn.classList.remove('bg-primary', 'hover:bg-secondary');
                    }
                }
            }
        }

        // Initialize maps for all sections
        document.addEventListener('DOMContentLoaded', function() {
            setLocation('Masuk');
            setLocation('Pulang');
            setLocation('Izin');

            // Add event listener for refresh button
            const refreshButton = document.getElementById('refreshButton');
            if (refreshButton) {
                refreshButton.addEventListener('click', function() {
                    location.reload();
                });
            }
        });

        // Show loading spinner on form submit
        document.getElementById('absenMasukForm').addEventListener('submit', function() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
        });
        document.getElementById('absenPulangForm').addEventListener('submit', function() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
        });
        document.getElementById('izinForm').addEventListener('submit', function() {
            document.getElementById('loadingSpinner').classList.remove('hidden');
        });
    </script>
    <script>
        const actionType = '<?= $action_type ?>';
    </script>
</body>
</html>
