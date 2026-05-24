<?php
session_start();
require_once '../config/db_connect.php';

// Ensure user is logged in as admin/kepala sekolah
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Data Structure for Administration Menus
$admin_menu = [
    // --- 1. KURIKULUM & PEMBELAJARAN ---
    'kurikulum' => [
        'title' => '1. KURIKULUM & PEMBELAJARAN',
        'icon' => 'journal-bookmark',
        'items' => [
            ['name' => 'Kalender Pendidikan', 'desc' => 'Jadwal kegiatan akademik tahunan.', 'link' => 'admin_administrasi_managedata.php?type=kalender'],
            ['name' => 'Jadwal Pelajaran & Piket', 'desc' => 'Alokasi waktu mapel dan piket guru.', 'link' => 'admin_administrasi_managedata.php?type=jadwal_pelajaran'],
            ['name' => 'Dokumen Kurikulum (KTSP)', 'desc' => 'KTSP, Kurikulum, Muatan Lokal.', 'link' => 'admin_administrasi_managedata.php?type=ktsp'],
            ['name' => 'Administrasi Guru', 'desc' => 'Silabus, RPP, Bahan Ajar.', 'link' => 'admin_administrasi_managedata.php?type=admin_guru'],
            ['name' => 'Analisis & Pemetaan', 'desc' => 'SK-KD, KKM, Prota, Promes.', 'link' => 'admin_administrasi_managedata.php?type=pemetaan_sk_kd'],
            ['name' => 'Analisis Akademik Guru', 'desc' => 'Rekap Analisis UTS/UAS dari Seluruh Kelas.', 'link' => 'admin_analisis_akademik.php'],
            ['name' => 'Supervisi Akademik', 'desc' => 'Jadwal, Instrumen & Laporan Supervisi Kelas.', 'link' => 'admin_supervisi_akademik.php'],
            ['name' => 'Asesmen & Penilaian', 'desc' => 'Legger, Analisis Hasil, Remedial.', 'link' => 'admin_administrasi_managedata.php?type=legger'],
            ['name' => 'Laporan Hasil Belajar', 'desc' => 'Arsip Raport, SKHUN, Ijazah.', 'link' => 'admin_administrasi_managedata.php?type=ijazah']
        ]
    ],

    // --- 2. KESISWAAN ---
    'kesiswaan' => [
        'title' => '2. ADMINISTRASI KESISWAAN',
        'icon' => 'mortarboard',
        'items' => [
            ['name' => 'Buku Induk & Klapper', 'desc' => 'Database lengkap siswa.', 'link' => 'users.php'],
            ['name' => 'Data Nilai Per Kelas', 'desc' => 'Rekap nilai siswa terkelompok per kelas.', 'link' => 'admin_nilai_perkelas.php'],
            ['name' => 'Mutasi Siswa', 'desc' => 'Catatan siswa masuk dan keluar.', 'link' => 'admin_administrasi_managedata.php?type=mutasi'],
            ['name' => 'Kenaikan & Kelulusan', 'desc' => 'Daftar kelulusan dan kenaikan kelas.', 'link' => 'admin_administrasi_managedata.php?type=kelulusan'],
            ['name' => 'Prestasi & Karya', 'desc' => 'Arsip prestasi dan karya siswa.', 'link' => 'admin_administrasi_managedata.php?type=prestasi_siswa'],
            ['name' => 'Statistik Siswa', 'desc' => 'Grafik dan rekapitulasi jumlah siswa.', 'link' => 'admin_administrasi_managedata.php?type=statistik'],
            ['name' => 'Tata Tertib & Program 7K', 'desc' => 'Aturan dan program lingkungan sekolah.', 'link' => 'admin_administrasi_managedata.php?type=tata_tertib']
        ]
    ],

    // --- 3. PENDIDIK & TENAGA KEPENDIDIKAN ---
    'ptk' => [
        'title' => '3. PENDIDIK & TENAGA KEPENDIDIKAN',
        'icon' => 'people-fill',
        'items' => [
            ['name' => 'Data & File PTK', 'desc' => 'Buku Induk dan Arsip File Guru/Staf.', 'link' => 'admin_administrasi_managedata.php?type=file_ptk'],
            ['name' => 'SK Pembagian Tugas', 'desc' => 'SK Mengajar dan Tugas Tambahan.', 'link' => 'admin_administrasi_managedata.php?type=sk'],
            ['name' => 'Presensi PTK', 'desc' => 'Rekap kehadiran guru dan staf.', 'link' => 'admin_absensi.php'],
            ['name' => 'Pembinaan & PKG', 'desc' => 'Pembinaan Profesi, PKG, PKB, SKP.', 'link' => 'admin_pembinaan_guru.php'],
            ['name' => 'Supervisi Administrasi', 'desc' => 'Pemeriksaan kelengkapan administrasi guru.', 'link' => 'admin_supervisi_admin.php']
        ]
    ],

    // --- 4. KEUANGAN & SARANA PRASARANA ---
    'keuangan_sarpras' => [
        'title' => '4. KEUANGAN & SARPRAS',
        'icon' => 'building',
        'items' => [
            ['name' => 'Manajemen Keuangan', 'desc' => 'RKAS, BKU, Kas Pembantu, Pajak.', 'link' => 'admin_administrasi_managedata.php?type=bku'],
            ['name' => 'Laporan & Realisasi', 'desc' => 'LPJ, Realisasi Anggaran.', 'link' => 'admin_administrasi_managedata.php?type=lpj'],
            ['name' => 'Inventaris & Aset', 'desc' => 'Buku Inventaris, Aset, Log Barang.', 'link' => 'admin_administrasi_managedata.php?type=inventaris'],
            ['name' => 'Pemeliharaan', 'desc' => 'Program dan jadwal perbaikan.', 'link' => 'admin_administrasi_managedata.php?type=pemeliharaan'],
            ['name' => 'Denah & Master Plan', 'desc' => 'Peta sekolah dan dokumen kepemilikan.', 'link' => 'admin_administrasi_managedata.php?type=master_plan']
        ]
    ],

    // --- 5. MANAJEMEN & HUMAS ---
    'manajemen' => [
        'title' => '5. MANAJEMEN & HUMAS',
        'icon' => 'briefcase',
        'items' => [
            ['name' => 'Visi, Misi & Program KS', 'desc' => 'Arah kebijakan dan program kerja.', 'link' => 'admin_administrasi_managedata.php?type=program_ks'],
            ['name' => 'Buku Harian KS', 'desc' => 'Catatan harian aktivitas Kepala Sekolah.', 'link' => 'admin_diary_ks.php'],
            ['name' => 'Catatan Khusus', 'desc' => 'Anecdotal Record (Siswa/Guru).', 'link' => 'admin_catatan_khusus.php'],
            ['name' => 'Persuratan', 'desc' => 'Surat Masuk dan Surat Keluar.', 'link' => 'admin_administrasi_managedata.php?type=surat_masuk'],
            ['name' => 'Notulen Rapat', 'desc' => 'Catatan hasil rapat dinas/sekolah.', 'link' => 'admin_administrasi_notulen.php'],
            ['name' => 'Buku Tamu', 'desc' => 'Pencatatan tamu sekolah.', 'link' => 'admin_administrasi_managedata.php?type=buku_tamu'],
            ['name' => 'Hubungan Masyarakat', 'desc' => 'Komite Sekolah, MoU Kemitraan.', 'link' => 'admin_administrasi_managedata.php?type=komite'],
            ['name' => 'Evaluasi & Akreditasi', 'desc' => 'EDS dan Dokumen Akreditasi.', 'link' => 'admin_administrasi_managedata.php?type=akreditasi']
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrasi Sekolah - Kepala Sekolah</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .admin-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border-color: var(--primary);
        }

        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .admin-card:hover::before {
            opacity: 1;
        }

        .admin-card-icon {
            font-size: 1.75rem;
            margin-bottom: 1rem;
            color: var(--primary);
            width: 50px;
            height: 50px;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-card h3 {
            font-size: 1.1rem;
            margin: 0 0 0.5rem 0;
            color: var(--text-main);
            font-weight: 700;
        }

        .admin-card p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.5;
            flex-grow: 1;
        }

        .section-separator {
            margin-top: 3rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-separator h2 {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-separator .icon-wrapper {
            background: var(--primary);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .badge-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 4px;
            background: #f3f4f6;
            color: #6b7280;
            margin-top: 1rem;
            align-self: flex-start;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {

            /* Force header to stay horizontal - Override style.css */
            .main-content .header,
            main.main-content .header,
            .header {
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.75rem !important;
            }

            .header>div:first-child {
                flex-direction: row !important;
                display: flex !important;
                align-items: center !important;
            }

            /* Grid adjustments for mobile - 3 columns */
            .admin-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.5rem;
                margin-top: 0.5rem;
            }

            /* Card adjustments - very compact, only show title */
            .admin-card {
                padding: 0.625rem;
                border-radius: 8px;
                text-align: center;
            }

            .admin-card h3 {
                font-size: 0.7rem;
                margin-bottom: 0;
                line-height: 1.3;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                word-break: break-word;
            }

            /* Hide description on mobile */
            .admin-card p {
                display: none;
            }

            /* Hide or make icon smaller */
            .admin-card-icon {
                width: 28px;
                height: 28px;
                font-size: 1rem;
                margin: 0 auto 0.4rem;
            }

            /* Hide badge on mobile */
            .badge-status {
                display: none;
            }

            /* Section separator adjustments - more compact */
            .section-separator {
                margin-top: 1.25rem;
                margin-bottom: 0.625rem;
                flex-wrap: wrap;
                padding-bottom: 0.375rem;
            }

            .section-separator h2 {
                font-size: 0.85rem;
                letter-spacing: 0.3px;
            }

            .section-separator .icon-wrapper {
                padding: 0.3rem;
                font-size: 0.75rem;
                width: 24px;
                height: 24px;
            }

            .section-separator:first-of-type {
                margin-top: 0.625rem;
            }

            /* Header adjustments for mobile */
            .header {
                margin-bottom: 1rem !important;
            }

            .header>div {
                width: 100% !important;
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                gap: 0.75rem !important;
            }

            .header div>div {
                flex: 1 !important;
                min-width: 0 !important;
            }

            .header h1 {
                font-size: 1rem !important;
                margin: 0 !important;
                line-height: 1.3 !important;
                white-space: normal !important;
                word-break: break-word !important;
            }

            .header p {
                font-size: 0.75rem !important;
                margin: 0.125rem 0 0 0 !important;
                line-height: 1.3 !important;
            }

            .sidebar-toggle {
                flex-shrink: 0 !important;
                width: 2.25rem !important;
                height: 2.25rem !important;
            }

            .main-content {
                padding: 0.875rem !important;
            }
        }

        /* Small mobile devices - extra compact */
        @media (max-width: 480px) {
            .admin-grid {
                gap: 0.375rem;
            }

            .admin-card {
                padding: 0.5rem;
                border-radius: 6px;
            }

            .admin-card h3 {
                font-size: 0.625rem;
                line-height: 1.25;
                -webkit-line-clamp: 3;
            }

            .admin-card-icon {
                width: 24px;
                height: 24px;
                font-size: 0.875rem;
                margin-bottom: 0.3rem;
            }

            .section-separator h2 {
                font-size: 0.75rem;
            }

            .section-separator .icon-wrapper {
                width: 22px;
                height: 22px;
                padding: 0.25rem;
                font-size: 0.7rem;
            }

            /* Header extra compact for small devices */
            .header h1 {
                font-size: 0.9rem !important;
            }

            .header p {
                font-size: 0.7rem !important;
                display: none;
                /* Hide description on very small screens */
            }

            .sidebar-toggle {
                width: 2rem;
                height: 2rem;
            }

            .main-content {
                padding: 0.625rem;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div>
                        <h1>Administrasi Sekolah</h1>
                        <p style="color: var(--text-muted)">Pusat pengelolaan dokumen dan administrasi Kepala Sekolah.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Render ALL Categories -->
            <?php foreach ($admin_menu as $key => $menu): ?>
                <div class="section-separator" id="<?= $key ?>">
                    <div class="icon-wrapper">
                        <i class="bi bi-<?= $menu['icon'] ?>"></i>
                    </div>
                    <h2><?= str_replace('ADMINISTRASI ', '', $menu['title']) // Simplify title ?></h2>
                </div>

                <div class="admin-grid">
                    <?php foreach ($menu['items'] as $item): ?>
                        <?php
                        $hasLink = $item['link'] !== '#';
                        $onClick = $hasLink ? "window.location.href='{$item['link']}'" : "alert('Fitur " . addslashes($item['name']) . " sedang dalam pengembangan.')";
                        ?>
                        <div class="admin-card" onclick="<?= $onClick ?>">
                            <div class="admin-card-icon">
                                <i class="bi bi-folder2-open"></i>
                            </div>
                            <h3><?= htmlspecialchars($item['name']) ?></h3>
                            <p><?= htmlspecialchars($item['desc']) ?></p>
                            <?php if (!$hasLink): ?>
                                <span class="badge-status">Segera Hadir</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

        </main>
    </div>

    <!-- Sidebar Toggle Script -->
    <script src="../assets/admin-sidebar.js"></script>

</body>

</html>