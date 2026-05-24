<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Ambil data demografi siswa dari tabel users (role = 'siswa')
$demoQuery = "SELECT 
    assigned_class as class_name,
    COUNT(*) as jumlah_siswa
FROM users 
WHERE role = 'siswa' AND assigned_class IS NOT NULL AND assigned_class != ''
GROUP BY assigned_class 
ORDER BY assigned_class";
$demoStmt = $db->query($demoQuery);
$dataPerKelas = $demoStmt->fetchAll();

// Total siswa dari tabel users
$totalSiswa = $db->query("SELECT COUNT(*) FROM users WHERE role = 'siswa'")->fetchColumn();

// Data detail siswa (gender, agama, dll)
// Get gender distribution from student_details joined with students and users
$dataGender = ['Laki-laki' => 0, 'Perempuan' => 0];

// Query gender dari student_details yang di-link dengan students dan users
$genderQuery = "SELECT 
    sd.gender, 
    COUNT(*) as count 
FROM student_details sd
INNER JOIN students s ON sd.student_id = s.id
INNER JOIN users u ON u.full_name = s.name AND u.assigned_class = s.class_name
WHERE u.role = 'siswa' AND sd.gender IS NOT NULL AND sd.gender != ''
GROUP BY sd.gender";

try {
    $genderStmt = $db->query($genderQuery);
    $genderResults = $genderStmt->fetchAll();

    if (!empty($genderResults)) {
        foreach ($genderResults as $row) {
            $dataGender[$row['gender']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    // If query fails, keep default values
}

// Query untuk data per kelas berdasarkan gender (untuk stacked bar chart)
$dataPerKelasGender = [];
$genderPerKelasQuery = "SELECT 
    u.assigned_class as class_name,
    sd.gender,
    COUNT(*) as count
FROM users u
INNER JOIN students s ON u.full_name = s.name AND u.assigned_class = s.class_name
INNER JOIN student_details sd ON s.id = sd.student_id
WHERE u.role = 'siswa' AND u.assigned_class IS NOT NULL AND u.assigned_class != ''
    AND sd.gender IS NOT NULL AND sd.gender != ''
GROUP BY u.assigned_class, sd.gender
ORDER BY u.assigned_class, sd.gender";

try {
    $genderPerKelasStmt = $db->query($genderPerKelasQuery);
    $genderPerKelasResults = $genderPerKelasStmt->fetchAll();

    // Organize data untuk chart
    foreach ($genderPerKelasResults as $row) {
        $className = $row['class_name'];
        $gender = $row['gender'];

        if (!isset($dataPerKelasGender[$className])) {
            $dataPerKelasGender[$className] = ['Laki-laki' => 0, 'Perempuan' => 0];
        }
        $dataPerKelasGender[$className][$gender] = $row['count'];
    }
} catch (PDOException $e) {
    // If query fails, keep default values
}

// Data akademik
$nilaiRataQuery = "SELECT 
    subject,
    AVG(nilai) as rata_rata
FROM student_grades
GROUP BY subject
ORDER BY subject";

try {
    $nilaiRataStmt = $db->query($nilaiRataQuery);
    $dataNilaiRata = $nilaiRataStmt->fetchAll();
} catch (PDOException $e) {
    $dataNilaiRata = [];
}

// Data kehadiran
$kehadiranQuery = "SELECT 
    status,
    COUNT(*) as jumlah
FROM student_attendance
GROUP BY status";

try {
    $kehadiranStmt = $db->query($kehadiranQuery);
    $dataKehadiran = $kehadiranStmt->fetchAll();
} catch (PDOException $e) {
    $dataKehadiran = [
        ['status' => 'Hadir', 'jumlah' => 0],
        ['status' => 'Sakit', 'jumlah' => 0],
        ['status' => 'Izin', 'jumlah' => 0],
        ['status' => 'Alpa', 'jumlah' => 0]
    ];
}

// Data ekstrakurikuler
$ekstraQuery = "SELECT 
    activity_name,
    COUNT(*) as jumlah_peserta
FROM student_activities
GROUP BY activity_name
ORDER BY jumlah_peserta DESC
LIMIT 10";

try {
    $ekstraStmt = $db->query($ekstraQuery);
    $dataEkstra = $ekstraStmt->fetchAll();
} catch (PDOException $e) {
    $dataEkstra = [];
}

// Data prestasi
// Data prestasi
$prestasiQuery = "SELECT 
    category,
    COUNT(*) as jumlah
FROM student_notes
WHERE LOWER(type) = 'prestasi'
GROUP BY category";

try {
    $prestasiStmt = $db->query($prestasiQuery);
    $dataPrestasi = $prestasiStmt->fetchAll();
} catch (PDOException $e) {
    $dataPrestasi = [];
}

// Data pelanggaran
$pelanggaranQuery = "SELECT 
    category,
    COUNT(*) as jumlah
FROM student_notes
WHERE LOWER(type) = 'pelanggaran'
GROUP BY category";

try {
    $pelanggaranStmt = $db->query($pelanggaranQuery);
    $dataPelanggaran = $pelanggaranStmt->fetchAll();
} catch (PDOException $e) {
    $dataPelanggaran = [];
}

// Data Alumni/Mutasi
$mutasiQuery = "SELECT 
    type,
    COUNT(*) as jumlah
FROM student_mutation
GROUP BY type";

try {
    $mutasiStmt = $db->query($mutasiQuery);
    $dataMutasi = $mutasiStmt->fetchAll();
} catch (PDOException $e) {
    $dataMutasi = [];
}

// Data Bimbingan Konseling (Jenis Masalah)
$bkTypeQuery = "SELECT 
    type,
    COUNT(*) as jumlah
FROM consultations
GROUP BY type";

try {
    $bkTypeStmt = $db->query($bkTypeQuery);
    $dataBkType = $bkTypeStmt->fetchAll();
} catch (PDOException $e) {
    $dataBkType = [];
}

// Data Bimbingan Konseling (Tren Bulanan)
$bkTrendQuery = "SELECT 
    strftime('%Y-%m', date) as bulan,
    COUNT(*) as jumlah
FROM consultations
GROUP BY bulan
ORDER BY bulan ASC
LIMIT 12";

try {
    $bkTrendStmt = $db->query($bkTrendQuery);
    $dataBkTrend = $bkTrendStmt->fetchAll();
} catch (PDOException $e) {
    $dataBkTrend = [];
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistik & Grafik Siswa - Data Komprehensif</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        .stat-card h3 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .stat-card .label {
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .chart-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .chart-section h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
            border-left: 4px solid var(--primary);
            padding-left: 1rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 1rem;
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .info-box h4 {
            color: #0369a1;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .info-box p {
            color: #075985;
            font-size: 0.875rem;
            line-height: 1.6;
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

            /* Force 2 Columns for Statistics Cards on Mobile */
            body .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                grid-auto-rows: auto !important;
                gap: 0.75rem !important;
                margin-bottom: 1.5rem !important;
            }

            .stat-card {
                padding: 1rem 0.75rem !important;
                border-radius: 0.75rem !important;
                text-align: center !important;
            }

            .stat-card h3 {
                font-size: 0.7rem !important;
                margin-bottom: 0.4rem !important;
            }

            .stat-card .value {
                font-size: 1.75rem !important;
                margin-bottom: 0.25rem !important;
            }

            .stat-card .label {
                font-size: 0.65rem !important;
            }

            /* Chart adjustments */
            .chart-container {
                height: 250px !important;
            }

            .chart-section {
                padding: 1rem !important;
            }

            .chart-section h2 {
                font-size: 1rem !important;
                margin-bottom: 1rem !important;
            }

            .chart-grid {
                grid-template-columns: 1fr !important;
            }

            .tabs {
                gap: 0.5rem !important;
            }

            .tab {
                padding: 0.5rem 0.75rem !important;
                font-size: 0.75rem !important;
            }

            .info-box {
                padding: 0.75rem 1rem !important;
                font-size: 0.8rem !important;
            }

            .info-box h4 {
                font-size: 0.85rem !important;
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
                    <div class="header-title">
                        <h1>Statistik & Grafik Siswa</h1>
                        <p>Data Komprehensif Siswa</p>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="admin_administrasi.php" class="btn btn-secondary" title="Kembali">
                        <i class="bi bi-arrow-left"></i> <span>Kembali</span>
                    </a>
                </div>
            </header>

            <div class="info-box">
                <h4><i class="bi bi-info-circle"></i> Tentang Halaman Statistik</h4>
                <p>Halaman ini menampilkan data komprehensif siswa yang mencakup <strong>Data Demografi</strong>,
                    <strong>Data Akademik</strong>, <strong>Data Non-Akademik</strong>, <strong>Data Perkembangan &
                        Bimbingan</strong>, dan <strong>Data Alumni/Kelulusan</strong>. Data divisualisasikan dalam
                    bentuk grafik untuk memudahkan analisis dan pengambilan keputusan.
                </p>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Siswa</h3>
                    <div class="value"><?= $totalSiswa ?></div>
                    <div class="label">Siswa Aktif</div>
                </div>
                <div class="stat-card green">
                    <h3>Total Kelas</h3>
                    <div class="value"><?= count($dataPerKelas) ?></div>
                    <div class="label">Rombongan Belajar</div>
                </div>
                <div class="stat-card orange">
                    <h3>Prestasi</h3>
                    <div class="value"><?= array_sum(array_column($dataPrestasi, 'jumlah')) ?></div>
                    <div class="label">Total Prestasi</div>
                </div>
                <div class="stat-card blue">
                    <h3>Ekstrakurikuler</h3>
                    <div class="value"><?= count($dataEkstra) ?></div>
                    <div class="label">Program Aktif</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('demografi')">
                    <i class="bi bi-people"></i> Demografi
                </button>
                <button class="tab" onclick="switchTab('akademik')">
                    <i class="bi bi-book"></i> Akademik
                </button>
                <button class="tab" onclick="switchTab('nonakademik')">
                    <i class="bi bi-trophy"></i> Non-Akademik
                </button>
                <button class="tab" onclick="switchTab('bimbingan')">
                    <i class="bi bi-person-badge"></i> Bimbingan
                </button>
                <button class="tab" onclick="switchTab('alumni')">
                    <i class="bi bi-mortarboard"></i> Alumni
                </button>
            </div>

            <!-- Tab Content: Demografi -->
            <div id="tab-demografi" class="tab-content active">
                <div class="chart-section">
                    <h2><i class="bi bi-pie-chart"></i> Data Demografi Siswa</h2>
                    <div class="chart-grid">
                        <div>
                            <h3 style="text-align: center; margin-bottom: 1rem;">Jumlah Siswa Per Kelas</h3>
                            <div class="chart-container">
                                <canvas id="chartPerKelas"></canvas>
                            </div>
                        </div>
                        <div>
                            <h3 style="text-align: center; margin-bottom: 1rem;">Distribusi Jenis Kelamin</h3>
                            <div class="chart-container">
                                <canvas id="chartGender"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Content: Akademik -->
            <div id="tab-akademik" class="tab-content">
                <div class="chart-section">
                    <h2><i class="bi bi-graph-up-arrow"></i> Data Akademik</h2>
                    <?php if (count($dataNilaiRata) > 0): ?>
                        <div>
                            <h3 style="text-align: center; margin-bottom: 1rem;">Nilai Rata-rata Per Mata Pelajaran</h3>
                            <div class="chart-container">
                                <canvas id="chartNilaiRata"></canvas>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data nilai akademik belum tersedia. Silakan tambahkan data nilai siswa terlebih dahulu.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-section">
                    <h2><i class="bi bi-award"></i> Prestasi Akademik</h2>
                    <?php if (count($dataPrestasi) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartPrestasi"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data prestasi akademik belum tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content: Non-Akademik -->
            <div id="tab-nonakademik" class="tab-content">
                <div class="chart-section">
                    <h2><i class="bi bi-calendar-check"></i> Data Kehadiran</h2>
                    <div class="chart-container">
                        <canvas id="chartKehadiran"></canvas>
                    </div>
                </div>

                <div class="chart-section">
                    <h2><i class="bi bi-star"></i> Ekstrakurikuler</h2>
                    <?php if (count($dataEkstra) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartEkstra"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data ekstrakurikuler belum tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="chart-section">
                    <h2><i class="bi bi-exclamation-triangle"></i> Pelanggaran Siswa</h2>
                    <?php if (count($dataPelanggaran) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartPelanggaran"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data pelanggaran belum tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content: Bimbingan -->
            <div id="tab-bimbingan" class="tab-content">
                <div class="chart-section">
                    <h2><i class="bi bi-clipboard-heart"></i> Data Perkembangan & Bimbingan</h2>

                    <?php if (count($dataBkType) > 0 || count($dataBkTrend) > 0): ?>
                        <div class="chart-grid">
                            <div>
                                <h3 style="text-align: center; margin-bottom: 1rem;">Jenis Masalah Konsultasi</h3>
                                <div class="chart-container">
                                    <canvas id="chartBkType"></canvas>
                                </div>
                            </div>
                            <div>
                                <h3 style="text-align: center; margin-bottom: 1rem;">Tren Konsultasi Bulanan</h3>
                                <div class="chart-container">
                                    <canvas id="chartBkTrend"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data bimbingan konseling belum tersedia. Silakan input data melalui menu Administrasi Kelas.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tab Content: Alumni -->
            <div id="tab-alumni" class="tab-content">
                <div class="chart-section">
                    <h2><i class="bi bi-mortarboard-fill"></i> Data Alumni & Kelulusan</h2>
                    <?php if (count($dataMutasi) > 0): ?>
                        <div class="chart-container">
                            <canvas id="chartMutasi"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="info-box">
                            <h4>Data Belum Tersedia</h4>
                            <p>Data kelulusan dan mutasi siswa belum tersedia.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Tab Switching
        function switchTab(tabName) {
            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            // Remove active from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.add('active');
            // Activate selected tab
            event.target.classList.add('active');
        }

        // Sidebar Toggle - Enhanced for Mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');

        // Check if mobile
        const isMobile = () => window.innerWidth <= 768;

        // Initialize sidebar state
        function initSidebar() {
            if (isMobile()) {
                // On mobile, start collapsed
                dashboardLayout.classList.add('sidebar-collapsed');
                sidebarToggle.classList.remove('active');
            } else {
                // On desktop, use saved state
                const sidebarState = localStorage.getItem('sidebarCollapsed');
                if (sidebarState === 'true') {
                    dashboardLayout.classList.add('sidebar-collapsed');
                    sidebarToggle.classList.add('active');
                } else {
                    dashboardLayout.classList.remove('sidebar-collapsed');
                    sidebarToggle.classList.remove('active');
                }
            }
        }

        // Toggle sidebar
        function toggleSidebar() {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');

            // Only save state on desktop
            if (!isMobile()) {
                localStorage.setItem('sidebarCollapsed', dashboardLayout.classList.contains('sidebar-collapsed'));
            }
        }

        // Add click listeners
        sidebarToggle.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking overlay (mobile only)
        dashboardLayout.addEventListener('click', function (e) {
            if (isMobile() && !dashboardLayout.classList.contains('sidebar-collapsed')) {
                if (e.target === dashboardLayout) {
                    toggleSidebar();
                }
            }
        });

        // Initialize and handle resize
        initSidebar();
        window.addEventListener('resize', initSidebar);

        // Chart Colors
        const colors = {
            primary: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#11998e', '#38ef7d'],
            success: ['#11998e', '#38ef7d'],
            warning: ['#f093fb', '#f5576c'],
            info: ['#4facfe', '#00f2fe']
        };

        // Chart Per Kelas (Stacked Bar Chart - by Gender)
        <?php if (count($dataPerKelas) > 0): ?>
            <?php
            // Prepare data for stacked chart
            $classLabels = array_column($dataPerKelas, 'class_name');
            $lakiLakiData = [];
            $perempuanData = [];

            foreach ($classLabels as $className) {
                if (isset($dataPerKelasGender[$className])) {
                    $lakiLakiData[] = $dataPerKelasGender[$className]['Laki-laki'];
                    $perempuanData[] = $dataPerKelasGender[$className]['Perempuan'];
                } else {
                    $lakiLakiData[] = 0;
                    $perempuanData[] = 0;
                }
            }
            ?>

            new Chart(document.getElementById('chartPerKelas'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($classLabels) ?>,
                    datasets: [
                        {
                            label: 'Laki-laki',
                            data: <?= json_encode($lakiLakiData) ?>,
                            backgroundColor: '#667eea',
                            borderRadius: 8,
                            barThickness: 40
                        },
                        {
                            label: 'Perempuan',
                            data: <?= json_encode($perempuanData) ?>,
                            backgroundColor: '#f5576c',
                            borderRadius: 8,
                            barThickness: 40
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                padding: 15,
                                font: { size: 14 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            padding: 12,
                            borderRadius: 8,
                            callbacks: {
                                footer: function (tooltipItems) {
                                    let total = 0;
                                    tooltipItems.forEach(function (tooltipItem) {
                                        total += tooltipItem.parsed.y;
                                    });
                                    return 'Total: ' + total;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { stepSize: 5 }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Gender (Pie Chart)
        new Chart(document.getElementById('chartGender'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($dataGender)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($dataGender)) ?>,
                    backgroundColor: ['#667eea', '#f5576c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20, font: { size: 14 } }
                    }
                }
            }
        });

        // Chart Nilai Rata-rata (Bar Chart)
        <?php if (count($dataNilaiRata) > 0): ?>
            new Chart(document.getElementById('chartNilaiRata'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($dataNilaiRata, 'subject')) ?>,
                    datasets: [{
                        label: 'Nilai Rata-rata',
                        data: <?= json_encode(array_column($dataNilaiRata, 'rata_rata')) ?>,
                        backgroundColor: colors.primary,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { stepSize: 10 }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Kehadiran (Pie Chart)
        <?php if (count($dataKehadiran) > 0): ?>
            new Chart(document.getElementById('chartKehadiran'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($dataKehadiran, 'status')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($dataKehadiran, 'jumlah')) ?>,
                        backgroundColor: ['#38ef7d', '#f5576c', '#00f2fe', '#764ba2'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Ekstrakurikuler (Horizontal Bar)
        <?php if (count($dataEkstra) > 0): ?>
            new Chart(document.getElementById('chartEkstra'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($dataEkstra, 'activity_name')) ?>,
                    datasets: [{
                        label: 'Jumlah Peserta',
                        data: <?= json_encode(array_column($dataEkstra, 'jumlah_peserta')) ?>,
                        backgroundColor: colors.info,
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Prestasi (Doughnut)
        <?php if (count($dataPrestasi) > 0): ?>
            new Chart(document.getElementById('chartPrestasi'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($dataPrestasi, 'category')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($dataPrestasi, 'jumlah')) ?>,
                        backgroundColor: colors.primary,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Pelanggaran (Bar)
        <?php if (count($dataPelanggaran) > 0): ?>
            new Chart(document.getElementById('chartPelanggaran'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($dataPelanggaran, 'category')) ?>,
                    datasets: [{
                        label: 'Jumlah Pelanggaran',
                        data: <?= json_encode(array_column($dataPelanggaran, 'jumlah')) ?>,
                        backgroundColor: colors.warning,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        <?php endif; ?>

        // Chart Bimbingan Konseling (Pie & Line)
        <?php if (count($dataBkType) > 0 || count($dataBkTrend) > 0): ?>
            // Jenis Masalah (Pie)
            <?php if (count($dataBkType) > 0): ?>
                new Chart(document.getElementById('chartBkType'), {
                    type: 'pie',
                    data: {
                        labels: <?= json_encode(array_column($dataBkType, 'type')) ?>,
                        datasets: [{
                            data: <?= json_encode(array_column($dataBkType, 'jumlah')) ?>,
                            backgroundColor: colors.info,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { padding: 20 } }
                        }
                    }
                });
            <?php endif; ?>

            // Tren Bulanan (Line)
            <?php if (count($dataBkTrend) > 0): ?>
                new Chart(document.getElementById('chartBkTrend'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_column($dataBkTrend, 'bulan')) ?>,
                        datasets: [{
                            label: 'Jumlah Konsultasi',
                            data: <?= json_encode(array_column($dataBkTrend, 'jumlah')) ?>,
                            borderColor: '#764ba2',
                            backgroundColor: 'rgba(118, 75, 162, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } }
                        }
                    }
                });
            <?php endif; ?>
        <?php endif; ?>

        // Chart Mutasi/Alumni (Doughnut)
        <?php if (count($dataMutasi) > 0): ?>
            new Chart(document.getElementById('chartMutasi'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($dataMutasi, 'type')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($dataMutasi, 'jumlah')) ?>,
                        backgroundColor: colors.primary,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>