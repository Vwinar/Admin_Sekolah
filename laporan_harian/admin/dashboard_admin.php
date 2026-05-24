<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Stats
$total_reports = $db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$pending_reports = $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
$active_teachers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'guru'")->fetchColumn();

// Fetch Pending Reports
$sql = "SELECT r.*, u.full_name as teacher_name 
        FROM reports r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'pending' 
        ORDER BY r.report_date ASC";
$pending_list = $db->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
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

            /* Force 2 Columns for Statistics Cards on Mobile */
            body .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
                margin-bottom: 1.5rem !important;
            }

            .stat-card {
                padding: 1rem 0.75rem !important;
                border-radius: 0.75rem !important;
                text-align: center !important;
            }

            .stat-label {
                font-size: 0.7rem !important;
            }

            .stat-value {
                font-size: 1.75rem !important;
            }

            .stat-diff {
                font-size: 0.65rem !important;
            }

            /* Table responsive */
            .table-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            table {
                font-size: 0.75rem !important;
                min-width: 600px !important;
            }

            table th,
            table td {
                padding: 0.5rem !important;
            }

            .card {
                padding: 1rem !important;
            }

            .card h3 {
                font-size: 1rem !important;
            }

            .absensi-promo-card h4 {
                font-size: 1rem !important;
            }

            .absensi-promo-card p {
                font-size: 0.8rem !important;
            }
        }

        /* Modern Layout & Chips */
        .mt-4 {
            margin-top: 1.5rem !important;
        }

        .quick-actions {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .chip-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .chip-btn:hover {
            transform: translateY(-1px);
            filter: brightness(0.97);
        }

        .chip-btn-green {
            background-color: #e6f7f0;
            border: 1px solid #a7e0ce;
            color: #0d8b68;
        }

        .chip-btn-purple {
            background-color: #f6f0ff;
            border: 1px solid #dfc6ff;
            color: #7339d1;
        }

        .chip-btn-blue {
            background-color: #eff5ff;
            border: 1px solid #b8d4ff;
            color: #2e5192;
        }

        .chip-btn svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
            white-space: nowrap;
            line-height: 1.2;
        }

        .badge-rencana-hari-ini { background-color: #eff5ff; border: 1px solid #b8d4ff; color: #2e5192; }
        .badge-hari-ini { background-color: #e6f7f0; border: 1px solid #a7e0ce; color: #0d8b68; }
        .badge-evaluasi { background-color: #fff2ea; border: 1px solid #ffceb3; color: #d6551b; }
        .badge-rencana-besok { background-color: #f6f0ff; border: 1px solid #dfc6ff; color: #7339d1; }
        .badge-empty { background-color: #f3f4f6; border: 1px solid #e5e7eb; color: #6b7280; }

        .btn-review {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem !important;
            padding: 0.35rem 1rem !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            font-size: 0.75rem !important;
            letter-spacing: 0.025em;
            line-height: 1.2;
            width: auto !important;
            min-width: 0 !important;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-review:hover {
            transform: translateY(-1px);
            filter: brightness(0.97);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .section-header h3 {
            margin-bottom: 0;
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
                        <h1>Dashboard Kepala Sekolah</h1>
                        <p>Ringkasan aktivitas sekolah hari ini</p>
                    </div>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Laporan Masuk</div>
                    <div class="stat-value"><?= $total_reports ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Perlu Review</div>
                    <div class="stat-value" style="color: var(--warning);"><?= $pending_reports ?></div>
                    <div class="stat-diff">Pending Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Guru Aktif</div>
                    <div class="stat-value"><?= $active_teachers ?></div>
                </div>
            </div>

            <div class="section-header mt-4">
                <h3 style="margin-bottom: 0;">Aksi Cepat</h3>
            </div>
            <div class="quick-actions">
                <a href="admin_absensi.php" class="chip-btn chip-btn-green">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0z" />
                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                    </svg>
                    Manajemen Absensi
                </a>
                <a href="users.php" class="chip-btn chip-btn-purple">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7Zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h4.216ZM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
                    </svg>
                    Data Guru
                </a>
                <a href="laporan.php" class="chip-btn chip-btn-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/>
                        <path d="M3 5.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 8.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5zM3 11.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5z"/>
                    </svg>
                    Laporan Lengkap
                </a>
            </div>

            <div class="card">
                <div class="section-header">
                    <h3>Laporan Menunggu Persetujuan</h3>
                    <span class="badge badge-evaluasi" style="font-size: 0.85rem; padding: 0.35rem 0.85rem;"><?= $pending_reports ?> Laporan</span>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Guru</th>
                                <th>Mata Pelajaran</th>
                                <th>Ringkasan Materi</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pending_list) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">Semua laporan
                                        telah direview. Kerja bagus!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_list as $row): ?>
                                    <?php
                                    // Determine filled sections
                                    $keterangan_html = '';
                                    // Check Tab 1 (Rencana Hari Ini) - check unique fields
                                    if (!empty($row['plan_learning_objective']) || !empty($row['plan_method_type']) || !empty($row['module_file'])) {
                                        $keterangan_html .= '<span class="badge badge-rencana-hari-ini">Rencana Hari Ini</span>';
                                    }
                                    // Check Tab 2 (Hari Ini)
                                    if (!empty($row['attendance']) || !empty($row['achievement']) || !empty($row['obstacles'])) {
                                        $keterangan_html .= '<span class="badge badge-hari-ini">Hari Ini</span>';
                                    }
                                    // Check Tab 3 (Evaluasi)
                                    if (!empty($row['reflection']) || !empty($row['improvement_notes']) || !empty($row['evaluation_file'])) {
                                        $keterangan_html .= '<span class="badge badge-evaluasi">Evaluasi</span>';
                                    }
                                    // Check Tab 4 (Rencana Besok)
                                    if (!empty($row['plan_material']) || !empty($row['plan_goal'])) {
                                        $keterangan_html .= '<span class="badge badge-rencana-besok">Rencana Besok</span>';
                                    }
                                    if (empty($keterangan_html)) {
                                        $keterangan_html = '<span class="badge badge-empty">Kosong</span>';
                                    }
                                    
                                    $material = $row['material_taught'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                        <td><strong><?= htmlspecialchars($row['teacher_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['subject'] ?: ($row['plan_subject'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars(mb_substr($material, 0, 40)) ?><?= mb_strlen($material) > 40 ? '...' : '' ?></td>
                                        <td>
                                            <div class="badge-container">
                                                <?= $keterangan_html ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="review_laporan.php?id=<?= $row['id'] ?>" class="chip-btn chip-btn-blue btn-review">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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