<?php
// Include auth and DB logic from laporan_harian ecosystem
require_once '../config/db_connect.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

// Variables required by the template (mapped from laporan_harian context)
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Include Attendance Core Logic (handles DB interactions, auto-pulang, etc.)
// This file sets variables like $date, $time, $late, $success, $error, $disable_absen_masuk, etc.
require_once '../utils/attendance_logic.php';

// ADAPTER: Ensure variables match what user_home.php HTML expects
$month_filter = date('Y-m');
$rekap_records = [];

// Fetch Attendance
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC");
$stmt->execute([$user_id, $month_filter]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rekap_records[] = [
        'date' => $row['date'],
        'jam_masuk' => $row['jam_masuk'],
        'jam_pulang' => $row['jam_pulang'],
        'durasi' => $row['durasi'] ?? '-',
        'status' => $row['status'],
        'keterangan' => $row['keterangan'],
        'type' => 'absen'
    ];
}

// Fetch Izin
$stmtIzin = $db->prepare("SELECT * FROM izin WHERE user_id = ? AND strftime('%Y-%m', date) = ? ORDER BY date DESC");
$stmtIzin->execute([$user_id, $month_filter]);
while ($row = $stmtIzin->fetch(PDO::FETCH_ASSOC)) {
    $rekap_records[] = [
        'date' => $row['date'],
        'jam_masuk' => null,
        'jam_pulang' => null,
        'durasi' => '-',
        'status' => $row['status'],
        'keterangan' => $row['keterangan'],
        'type' => 'izin'
    ];
}

// Sort combined records
usort($rekap_records, function ($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});


// Logic for Tab States
$action_type = $_SESSION['action_type'] ?? '';
$disable_absen_masuk_tab = false;
$disable_absen_pulang_tab = true;
$disable_izin_tab = false;
$disable_rekap_tab = true;
$izin_success = false;
$izin_submitted = ($izin_exists ?? false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'izin' && $success) {
    $izin_success = true;
}

if ($action_type === 'izin') {
    $disable_absen_masuk_tab = true;
}

if ($izin_exists) {
    $disable_absen_masuk_tab = true;
    $disable_absen_pulang_tab = true;
    $disable_izin_tab = true;
    $disable_rekap_tab = false;
} elseif ($izin_success) {
    $disable_absen_masuk_tab = true;
    $disable_absen_pulang_tab = true;
    $disable_izin_tab = true;
    $disable_rekap_tab = false;
} elseif (!$disable_absen_masuk && !$disable_absen_pulang && !$izin_submitted) {
    $disable_absen_pulang_tab = true;
    $disable_rekap_tab = true;
} elseif ($disable_absen_masuk && !$disable_absen_pulang && !$izin_submitted) {
    $disable_absen_masuk_tab = true;
    $disable_izin_tab = true;
    $disable_rekap_tab = true;
    $disable_absen_pulang_tab = false;
} elseif ($disable_absen_pulang || $izin_submitted) {
    $disable_absen_pulang_tab = true;
    $disable_absen_masuk_tab = true;
    $disable_izin_tab = true;
    $disable_rekap_tab = false;
}

$toast_message = '';
if ($izin_exists)
    $toast_message = 'Anda sudah melakukan izin hari ini.';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="mobile-web-app-capable" content="yes" />
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

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
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
            z-index: 100000;
            animation: slideIn 0.5s forwards, fadeOut 0.5s 3s forwards;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }

            to {
                opacity: 0;
            }
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
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

    <!-- Radius Warning Modal -->
    <div id="radiusWarningModal"
        class="fixed inset-0 z-[100000] bg-black bg-opacity-90 flex items-center justify-center hidden">
        <div
            class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 text-center shadow-2xl transform scale-100 transition-transform">
            <div class="mb-4">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-map-marker-alt text-4xl text-red-500"></i>
                </div>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Di Luar Jangkauan</h3>
            <p class="text-gray-600 mb-6">
                Anda berada di luar radius absensi.<br>
                Jarak Anda: <span id="warningDistance" class="font-bold text-red-600 text-lg">0</span> meter.
            </p>
            <button onclick="switchToIzinFromRadius()"
                class="block w-full mb-3 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition duration-300 shadow-lg transform hover:-translate-y-1">
                <i class="fas fa-envelope mr-2"></i> Ajukan Izin
            </button>
            <button
                onclick="document.getElementById('radiusWarningModal').classList.add('hidden'); setLocation('Masuk');"
                class="block w-full mb-3 bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-xl transition duration-300 shadow-lg transform hover:-translate-y-1">
                <i class="fas fa-redo mr-2"></i> Coba Lagi Cari Lokasi
            </button>
            <a href="dashboard_guru.php"
                class="block w-full bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-xl transition duration-300 shadow-lg transform hover:-translate-y-1">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <!-- Location Loading Modal -->
    <div id="loadingLocationModal"
        class="fixed inset-0 z-[100000] bg-black bg-opacity-90 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-8 max-w-sm w-full mx-4 text-center shadow-2xl">
            <div class="mb-4">
                <div class="w-16 h-16 border-4 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto">
                </div>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">Mencari Lokasi...</h3>
            <p class="text-gray-600 mb-4">Mohon tunggu sebentar, sedang mendeteksi posisi GPS Anda.</p>
            <button onclick="window.location.reload()"
                class="mt-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-2 px-6 rounded-lg transition duration-200 flex items-center justify-center mx-auto">
                <i class="fas fa-sync-alt mr-2"></i> Refresh / Cari Lagi
            </button>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <!-- Header -->
        <div
            class="sticky top-0 z-[9999] bg-[#f8fafc] pt-2 pb-3 md:static md:pt-0 md:pb-0 flex flex-col md:flex-row justify-between items-start md:items-end mb-2 gap-4">
            <div class="w-full md:w-auto">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Absen App</h1>
                        <p class="text-gray-600">Selamat datang, <span
                                class="font-semibold text-primary"><?= htmlspecialchars($fullname) ?></span></p>
                    </div>
                    <!-- Mobile Back Button -->
                    <a href="dashboard_guru.php"
                        class="md:hidden flex items-center justify-center w-10 h-10 bg-white border border-gray-200 rounded-full shadow-sm text-gray-600 hover:text-white hover:bg-primary hover:border-primary transition-all duration-300">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
                <!-- Desktop Back Button -->
                <a href="dashboard_guru.php"
                    class="hidden md:inline-flex items-center gap-2 mt-2 text-sm text-gray-500 hover:text-primary font-medium transition-colors">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto overflow-x-auto pb-2 md:pb-0 no-scrollbar">
                <div class="text-center bg-blue-50 px-4 py-2 rounded-lg flex-shrink-0">
                    <p class="text-xs md:text-sm text-gray-500">Tanggal</p>
                    <p class="font-semibold text-primary text-sm md:text-base"><?= date('d M Y') ?></p>
                </div>
                <div class="text-center bg-blue-50 px-4 py-2 rounded-lg flex-shrink-0">
                    <p class="text-xs md:text-sm text-gray-500">Jam</p>
                    <p class="font-semibold text-primary text-sm md:text-base" id="liveClock"><?= date('H:i:s') ?></p>
                </div>
                <!-- Logic for Logout button in user_home.php was separate, here we share session. We will keep Logout or Refresh -->
                <a href="../logout.php"
                    class="btn btn-danger text-white px-4 py-2 flex items-center gap-2 flex-shrink-0">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="hidden md:inline">Logout</span>
                </a>
                <button id="refreshButton"
                    class="btn btn-primary text-white px-4 py-2 flex items-center gap-2 ml-2 flex-shrink-0">
                    <i class="fas fa-sync-alt"></i>
                    <span class="hidden md:inline">Refresh</span>
                </button>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex overflow-x-auto mb-6 border-b border-gray-200">
            <button onclick="showTab('absen_masuk')" id="tab_absen_masuk"
                class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_absen_masuk_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_absen_masuk_tab ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $disable_absen_masuk_tab ? 'disabled' : '' ?>>
                <i class="fas fa-sign-in-alt mr-2"></i>Absen Masuk
            </button>
            <button onclick="showTab('absen_pulang')" id="tab_absen_pulang"
                class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_absen_pulang_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_absen_pulang_tab ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $disable_absen_pulang_tab ? 'disabled' : '' ?>>
                <i class="fas fa-sign-out-alt mr-2"></i>Absen Pulang
            </button>
            <button onclick="showTab('izin')" id="tab_izin"
                class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_izin_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_izin_tab ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $disable_izin_tab ? 'disabled' : '' ?>>
                <i class="fas fa-envelope mr-2"></i>Izin
            </button>
            <button onclick="showTab('rekap')" id="tab_rekap"
                class="tab-btn px-4 py-3 text-sm font-medium text-center whitespace-nowrap border-b-2 border-transparent hover:text-primary transition <?= !$disable_rekap_tab ? 'tab-active' : 'text-gray-500' ?> <?= $disable_rekap_tab ? 'opacity-50 cursor-not-allowed' : '' ?>"
                <?= $disable_rekap_tab ? 'disabled' : '' ?>>
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

                    <form method="POST" enctype="multipart/form-data" id="absenMasukForm" class="space-y-4">
                        <input type="hidden" name="action" value="absen_masuk">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                <input type="text" value="<?= $date ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam</label>
                                <input type="text" value="<?= $time ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <input type="text" value="<?= $late ? 'Terlambat' : 'Tepat Waktu' ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent <?= $late ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang <span
                                    id="gpsStatusMasuk" class="text-xs ml-2 text-orange-500"></span></label>
                            <div id="mapMasuk" class="map-container"></div>
                            <p id="gpsInfoMasuk" class="text-xs text-gray-500 mt-1">Sedang mencari lokasi...</p>
                            <button type="button" id="retryGpsMasuk" onclick="setLocation('Masuk', 0)"
                                class="hidden mt-2 text-xs bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded">
                                <i class="fas fa-redo mr-1"></i>Coba Lagi GPS
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah
                                (meter)</label>
                            <input type="text" id="jarakMasuk" name="jarak" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Selfie</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition"
                                        onclick="document.getElementById('fotoMasuk').click()">
                                        <input type="file" id="fotoMasuk" accept="image/*" capture="environment"
                                            name="foto" class="hidden"
                                            onchange="previewFoto(event, 'fotoPreviewMasuk')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Pastikan wajah terlihat jelas</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewMasuk" src="#" alt="Preview Foto"
                                            class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoMasuk"
                                            class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="lokasi_lat" id="lokasiLatMasuk" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngMasuk" />

                        <button type="submit"
                            class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $disable_absen_masuk ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= $disable_absen_masuk ? 'disabled' : '' ?>>
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

                    <form method="POST" enctype="multipart/form-data" id="absenPulangForm" class="space-y-4">
                        <input type="hidden" name="action" value="absen_pulang">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                                <input type="text" value="<?= $date ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Jam</label>
                                <input type="text" value="<?= $time ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <input type="text" value="<?= htmlspecialchars($absen_pulang_status) ?>" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent <?= $absen_pulang_status === 'Pulang Cepat' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-600' ?>" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang <span
                                    id="gpsStatusPulang" class="text-xs ml-2 text-orange-500"></span></label>
                            <div id="mapPulang" class="map-container"></div>
                            <p id="gpsInfoPulang" class="text-xs text-gray-500 mt-1">Sedang mencari lokasi...</p>
                            <button type="button" id="retryGpsPulang" onclick="setLocation('Pulang', 0)"
                                class="hidden mt-2 text-xs bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded">
                                <i class="fas fa-redo mr-1"></i>Coba Lagi GPS
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah
                                (meter)</label>
                            <input type="text" id="jarakPulang" name="jarak" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Selfie</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition"
                                        onclick="document.getElementById('fotoPulang').click()">
                                        <input type="file" id="fotoPulang" accept="image/*" capture="environment"
                                            name="foto" class="hidden"
                                            onchange="previewFoto(event, 'fotoPreviewPulang')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Pastikan wajah terlihat jelas</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewPulang" src="#" alt="Preview Foto"
                                            class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoPulang"
                                            class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="lokasi_lat" id="lokasiLatPulang" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngPulang" />

                        <button type="submit"
                            class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $disable_absen_pulang ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= $disable_absen_pulang ? 'disabled' : '' ?>>
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

                    <form method="POST" enctype="multipart/form-data" id="izinForm" class="space-y-4">
                        <input type="hidden" name="action" value="izin">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Izin</label>
                            <input type="date" name="tanggal_izin" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                value="<?= date('Y-m-d') ?>" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Izin</label>
                            <textarea name="alasan_izin" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                rows="4" placeholder="Masukkan alasan izin Anda"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi Sekarang <span
                                    id="gpsStatusIzin" class="text-xs ml-2 text-orange-500"></span></label>
                            <div id="mapIzin" class="map-container"></div>
                            <p id="gpsInfoIzin" class="text-xs text-gray-500 mt-1">Sedang mencari lokasi...</p>
                            <button type="button" id="retryGpsIzin" onclick="setLocation('Izin', 0)"
                                class="hidden mt-2 text-xs bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded">
                                <i class="fas fa-redo mr-1"></i>Coba Lagi GPS
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jarak dari lokasi sekolah
                                (meter)</label>
                            <input type="text" id="jarakIzin" name="jarak" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Foto Bukti</label>
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="w-full md:w-1/2">
                                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition"
                                        onclick="document.getElementById('fotoIzin').click()">
                                        <input type="file" id="fotoIzin" accept="image/*" capture="environment"
                                            name="foto" class="hidden"
                                            onchange="previewFoto(event, 'fotoPreviewIzin')" />
                                        <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                                        <p class="text-sm text-gray-500">Klik untuk mengambil foto (opsional)</p>
                                        <p class="text-xs text-gray-400">Foto bukti yang relevan</p>
                                    </div>
                                </div>
                                <div class="w-full md:w-1/2">
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <img id="fotoPreviewIzin" src="#" alt="Preview Foto"
                                            class="w-full h-48 object-cover hidden" />
                                        <div id="noFotoIzin"
                                            class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                            <i class="fas fa-image text-4xl"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="lokasi_lat" id="lokasiLatIzin" />
                        <input type="hidden" name="lokasi_lng" id="lokasiLngIzin" />

                        <button type="submit"
                            class="btn btn-primary text-white w-full py-3 flex items-center justify-center gap-2 <?= $toast_message ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= $toast_message ? 'disabled' : '' ?>>
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
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tanggal</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Jam Masuk</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Jam Pulang</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Durasi</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Keterangan</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Tipe</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($rekap_records) === 0): ?>
                                    <tr>
                                        <td colspan="6"
                                            class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Tidak ada
                                            data absensi atau izin.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rekap_records as $record): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?= htmlspecialchars($record['date'] ?? '-') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['jam_masuk'] ?? '-') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['jam_pulang'] ?? '-') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['durasi'] ?? '-') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= ($record['status'] === 'Tepat Waktu') ? 'bg-green-100 text-green-800' : '' ?>
                                                <?= ($record['status'] === 'Terlambat') ? 'bg-red-100 text-red-800' : '' ?>
                                                <?= ($record['status'] === 'pending') ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                                <?= ($record['status'] === 'approved') ? 'bg-blue-100 text-blue-800' : '' ?>
                                                <?= ($record['status'] === 'rejected') ? 'bg-gray-100 text-gray-800' : '' ?>">
                                                    <?= htmlspecialchars($record['status'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($record['keterangan'] ?? '-') ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
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

        // Constants from PHP
        const SCHOOL_LAT = <?= $settings['latitude'] ?? -6.2 ?>;
        const SCHOOL_LNG = <?= $settings['longitude'] ?? 106.8 ?>;
        const RADIUS_METERS = <?= $radiusSetting ?? 100 ?>;

        function showRadiusWarning(distance) {
            const modal = document.getElementById('radiusWarningModal');
            const distSpan = document.getElementById('warningDistance');
            if (modal && distSpan) {
                distSpan.textContent = Math.round(distance);
                modal.classList.remove('hidden');
            }
        }

        const SCHOOL_MARKER_ICON = L.icon({
            iconUrl: 'https://cdn0.iconfinder.com/data/icons/small-n-flat/24/678111-map-marker-512.png',
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        function getDistanceFromLatLonInM(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            return R * c;
        }

        let gpsRetryCount = {};

        function setLocation(section, retryAttempt = 0) {
            const statusEl = document.getElementById(`gpsStatus${section}`);
            const infoEl = document.getElementById(`gpsInfo${section}`);
            const maxRetries = 2;

            // Initialize retry counter for this section
            if (!gpsRetryCount[section]) {
                gpsRetryCount[section] = 0;
            }

            console.log(`[GPS] Attempting to get location for ${section} (Attempt ${retryAttempt + 1}/${maxRetries + 1})`);

            // Check HTTPS status (required for geolocation on mobile)
            const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost';
            if (!isSecure) {
                console.warn('[GPS] Warning: Geolocation may not work reliably over HTTP on mobile devices');
                if (infoEl) {
                    infoEl.innerHTML = "⚠️ Peringatan: Untuk GPS di HP, gunakan HTTPS atau localhost";
                    infoEl.className = "text-xs text-orange-600 mt-1 font-semibold";
                }
            }

            // UI Loading state
            if (statusEl) statusEl.textContent = retryAttempt > 0 ? "(Mencoba lagi...)" : "(Mencari...)";
            if (infoEl) {
                infoEl.textContent = retryAttempt > 0
                    ? `Mencoba lagi mendapatkan GPS... (${retryAttempt + 1}/${maxRetries + 1})`
                    : "Sedang mencari koordinat GPS... Pastikan GPS/Lokasi HP aktif";
                infoEl.className = "text-xs text-blue-600 mt-1";
            }

            // Show Loading Modal
            document.getElementById('loadingLocationModal').classList.remove('hidden');

            // Check if geolocation is available
            if (!navigator.geolocation) {
                console.error('[GPS] Geolocation not supported');
                if (infoEl) {
                    infoEl.textContent = "❌ Browser Anda tidak mendukung GPS";
                    infoEl.className = "text-xs text-red-600 mt-1 font-semibold";
                }
                if (statusEl) statusEl.textContent = "(Tidak Didukung)";
                alert('Browser Anda tidak mendukung fitur GPS. Silakan gunakan browser yang lebih baru.');
                return;
            }

            // Request current position with optimized settings for mobile
            navigator.geolocation.getCurrentPosition(
                // Success callback
                function (position) {
                    // Hide Loading Modal on Success
                    document.getElementById('loadingLocationModal').classList.add('hidden');

                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const acc = position.coords.accuracy;
                    const distance = getDistanceFromLatLonInM(lat, lng, SCHOOL_LAT, SCHOOL_LNG);

                    console.log(`[GPS] ✓ Location found: Lat=${lat}, Lng=${lng}, Accuracy=${acc}m, Distance=${distance}m`);
                    gpsRetryCount[section] = 0; // Reset retry count on success

                    // Hide retry button on success
                    const retryBtn = document.getElementById(`retryGps${section}`);
                    if (retryBtn) retryBtn.classList.add('hidden');

                    // Update UI Messages
                    if (statusEl) statusEl.textContent = "✓";
                    if (infoEl) {
                        infoEl.textContent = `✓ Lokasi ditemukan (Akurasi: ${Math.round(acc)}m) - Jarak: ${Math.round(distance)}m`;
                        infoEl.classList.remove('text-gray-500', 'text-red-500', 'text-blue-600', 'text-orange-600');
                        infoEl.classList.add('text-green-600', 'font-semibold');
                    }

                    // Update Hidden Inputs
                    const latInput = document.getElementById(`lokasiLat${section}`);
                    const lngInput = document.getElementById(`lokasiLng${section}`);
                    const jarakInput = document.getElementById(`jarak${section}`);

                    if (latInput) latInput.value = lat;
                    if (lngInput) lngInput.value = lng;
                    if (jarakInput) jarakInput.value = distance.toFixed(2);

                    // Map Logic
                    const mapId = `map${section}`;
                    const mapElement = document.getElementById(mapId);

                    if (mapElement) {
                        console.log(`[GPS] Initializing map for ${section}`);
                        let map;

                        // Wait a bit to ensure DOM is ready for mobile
                        setTimeout(() => {
                            if (!mapElement._map) {
                                // Init new map
                                try {
                                    map = L.map(mapElement, {
                                        center: [lat, lng],
                                        zoom: 16,
                                        zoomControl: true,
                                        attributionControl: false
                                    });
                                    mapElement._map = map;

                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        maxZoom: 19,
                                        attribution: '© OpenStreetMap'
                                    }).addTo(map);

                                    console.log('[GPS] Map initialized successfully');
                                } catch (e) {
                                    console.error('[GPS] Map initialization error:', e);
                                    if (infoEl) {
                                        infoEl.innerHTML = infoEl.textContent + "<br>⚠️ Peta gagal dimuat, tapi GPS berhasil";
                                    }
                                    return;
                                }
                            } else {
                                // Use existing map
                                map = mapElement._map;
                                map.setView([lat, lng], 16);
                            }

                            // Clear existing layers
                            map.eachLayer(layer => {
                                if (layer instanceof L.Marker || layer instanceof L.Polyline || layer instanceof L.Circle) {
                                    map.removeLayer(layer);
                                }
                            });

                            // Add Markers
                            const userMarker = L.marker([lat, lng], {
                                title: 'Lokasi Anda'
                            }).addTo(map);
                            userMarker.bindPopup('<b>Lokasi Anda</b>').openPopup();

                            const schoolMarker = L.marker([SCHOOL_LAT, SCHOOL_LNG], {
                                icon: SCHOOL_MARKER_ICON,
                                title: 'Lokasi Sekolah'
                            }).addTo(map);
                            schoolMarker.bindPopup('<b>Lokasi Sekolah</b>');

                            // Add Polyline (connection line)
                            L.polyline([[lat, lng], [SCHOOL_LAT, SCHOOL_LNG]], {
                                color: 'blue',
                                weight: 2,
                                dashArray: '5, 5',
                                opacity: 0.7
                            }).addTo(map);

                            // Add Circle (radius)
                            L.circle([SCHOOL_LAT, SCHOOL_LNG], {
                                color: '#3b82f6',
                                fillColor: '#3b82f6',
                                fillOpacity: 0.1,
                                radius: RADIUS_METERS,
                                weight: 2
                            }).addTo(map);

                            // Fit bounds to show both markers
                            const bounds = L.latLngBounds([
                                [lat, lng],
                                [SCHOOL_LAT, SCHOOL_LNG]
                            ]);
                            map.fitBounds(bounds, {
                                padding: [50, 50],
                                maxZoom: 16
                            });

                            // Force redraw for mobile
                            setTimeout(() => {
                                map.invalidateSize();
                                console.log('[GPS] Map redrawn');
                            }, 300);
                        }, 100);
                    }

                    // Button Logic
                    let formId;
                    if (section === 'Masuk') formId = 'absenMasukForm';
                    else if (section === 'Pulang') formId = 'absenPulangForm';
                    else if (section === 'Izin') formId = 'izinForm';

                    const form = document.getElementById(formId);
                    if (form) {
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            if (section !== 'Izin') {
                                if (distance > RADIUS_METERS) {
                                    submitBtn.disabled = true;
                                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed', 'bg-gray-400');
                                    submitBtn.classList.remove('btn-primary');

                                    if (infoEl) {
                                        infoEl.textContent += " - ⚠️ Di luar radius!";
                                        infoEl.classList.remove('text-green-600');
                                        infoEl.classList.add('text-red-600');
                                    }
                                    showRadiusWarning(distance);
                                } else {
                                    // Enable if inside radius
                                    if (!submitBtn.hasAttribute('data-php-disabled')) {
                                        submitBtn.disabled = false;
                                        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-gray-400');
                                        submitBtn.classList.add('btn-primary');
                                    }
                                }
                            } else {
                                // Izin always enabled if loc found
                                submitBtn.disabled = false;
                                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-gray-400');
                                submitBtn.classList.add('btn-primary');
                            }
                        }
                    }
                },
                // Error callback
                function (error) {
                    let errorMsg = "Gagal mendapatkan lokasi GPS";
                    let detailMsg = "";

                    // Hide Loading Modal on Error
                    if (retryAttempt >= maxRetries) {
                        document.getElementById('loadingLocationModal').classList.add('hidden');
                    }

                    console.error(`[GPS] Error (code ${error.code}): ${error.message}`);

                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = "❌ Izin GPS ditolak";
                            detailMsg = "Silakan izinkan akses Lokasi/GPS di pengaturan browser Anda, lalu refresh halaman ini.";
                            console.error('[GPS] Permission denied by user');
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = "❌ Lokasi tidak tersedia";
                            detailMsg = "GPS tidak dapat menemukan posisi Anda. Pastikan GPS HP aktif dan Anda berada di area terbuka.";
                            console.error('[GPS] Position unavailable');
                            break;
                        case error.TIMEOUT:
                            errorMsg = "⏱️ Waktu habis mencari GPS";
                            detailMsg = "GPS membutuhkan waktu terlalu lama. Coba lagi atau pindah ke area terbuka.";
                            console.error('[GPS] Timeout');
                            break;
                        default:
                            errorMsg = "❌ Error GPS tidak dikenal";
                            detailMsg = error.message;
                    }

                    if (statusEl) statusEl.textContent = "(Error)";

                    // Retry logic for timeout and position unavailable
                    if ((error.code === error.TIMEOUT || error.code === error.POSITION_UNAVAILABLE) && retryAttempt < maxRetries) {
                        console.log(`[GPS] Retrying... (${retryAttempt + 1}/${maxRetries})`);
                        if (infoEl) {
                            infoEl.textContent = `${errorMsg}. Mencoba lagi dalam 2 detik...`;
                            infoEl.className = "text-xs text-orange-600 mt-1";
                        }
                        setTimeout(() => {
                            setLocation(section, retryAttempt + 1);
                        }, 2000);
                        return;
                    }

                    // Final error display
                    if (infoEl) {
                        infoEl.innerHTML = `${errorMsg}<br><small>${detailMsg}</small>`;
                        infoEl.className = "text-xs text-red-600 mt-1 font-semibold";
                    }

                    // Show retry button for user to manually retry
                    const retryBtn = document.getElementById(`retryGps${section}`);
                    if (retryBtn) {
                        retryBtn.classList.remove('hidden');
                    }

                    // Show user-friendly alert ONLY for permission denied
                    if (error.code === error.PERMISSION_DENIED) {
                        let alertMsg = `${errorMsg}\n\n${detailMsg}`;
                        alertMsg += "\n\nCara mengizinkan GPS:\n1. Buka Pengaturan browser\n2. Cari 'Izin Situs' atau 'Permissions'\n3. Aktifkan 'Lokasi' untuk situs ini\n4. Refresh halaman";
                        alert(alertMsg);
                    }
                },
                // Options - optimized for mobile
                {
                    enableHighAccuracy: true,  // Use GPS instead of network location
                    timeout: 30000,            // Increased to 30 seconds for mobile
                    maximumAge: 0              // Don't use cached position
                }
            );
        }

        function previewFoto(event, imgId) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.getElementById(imgId);
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    const container = img.parentElement;
                    const placeholder = container.querySelector('div.bg-gray-100');
                    if (placeholder) placeholder.classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
            const content = document.getElementById(`${tabName}_content`);
            if (content) content.classList.remove('hidden');

            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('tab-active', 'text-primary');
                btn.classList.add('text-gray-500');
            });
            const activeBtn = document.getElementById(`tab_${tabName}`);
            if (activeBtn) {
                activeBtn.classList.add('tab-active', 'text-primary');
                activeBtn.classList.remove('text-gray-500');
            }

            let section;
            if (tabName === 'absen_masuk') section = 'Masuk';
            else if (tabName === 'absen_pulang') section = 'Pulang';
            else if (tabName === 'izin') section = 'Izin';

            if (section) {
                const mapId = `map${section}`;
                const mapElement = document.getElementById(mapId);
                if (mapElement && mapElement._map) {
                    setTimeout(() => { mapElement._map.invalidateSize(); }, 200);
                }

                setLocation(section);
            }


        }

        function switchToIzinFromRadius() {
            document.getElementById('radiusWarningModal').classList.add('hidden');
            showTab('izin');

            const btnMasuk = document.getElementById('tab_absen_masuk');
            if (btnMasuk) {
                btnMasuk.disabled = true;
                btnMasuk.classList.add('opacity-50', 'cursor-not-allowed');
                btnMasuk.classList.remove('hover:text-primary');
            }
        }

        window.onload = function () {
            let activeTab = 'rekap';
            if (document.getElementById('tab_absen_masuk') && document.getElementById('tab_absen_masuk').classList.contains('tab-active')) activeTab = 'absen_masuk';
            else if (document.getElementById('tab_absen_pulang') && document.getElementById('tab_absen_pulang').classList.contains('tab-active')) activeTab = 'absen_pulang';
            else if (document.getElementById('tab_izin') && document.getElementById('tab_izin').classList.contains('tab-active')) activeTab = 'izin';

            showTab(activeTab);

            const refreshButton = document.getElementById('refreshButton');
            if (refreshButton) {
                refreshButton.addEventListener('click', function () {
                    location.reload();
                });
            }
        };
    </script>
</body>

</html>