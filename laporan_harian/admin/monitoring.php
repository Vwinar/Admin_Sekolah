<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$sql = "SELECT r.*, u.full_name as teacher_name 
        FROM reports r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.report_date DESC";
$list = $db->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Laporan</title>
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

            /* Table responsive */
            .table-container {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            table {
                font-size: 0.75rem !important;
                min-width: 700px !important;
            }

            table th,
            table td {
                padding: 0.5rem 0.4rem !important;
            }

            .card {
                padding: 1rem !important;
            }

            .badge {
                font-size: 0.65rem !important;
                padding: 0.2rem 0.5rem !important;
            }

            .btn {
                padding: 0.3rem 0.5rem !important;
                font-size: 0.7rem !important;
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
                        <h1>Monitoring Semua Laporan</h1>
                    </div>
                </div>
            </header>

            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Guru</th>
                                <th>Mata Pelajaran</th>
                                <th>Keterangan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($list) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">Tidak ada data.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($list as $row): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['report_date'])) ?></td>
                                        <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                        <td><?= htmlspecialchars($row['subject'] ?: ($row['plan_subject'] ?? '-')) ?></td>
                                        <td>
                                            <div class="badge-container">
                                                <?php
                                                $keterangan_html = '';
                                                if (!empty($row['plan_topic']) || !empty($row['plan_subject']) || !empty($row['module_file'])) {
                                                    $keterangan_html .= '<span class="badge badge-rencana-hari-ini">Rencana Hari Ini</span>';
                                                }
                                                if (!empty($row['material_taught']) || !empty($row['attendance'])) {
                                                    $keterangan_html .= '<span class="badge badge-hari-ini">Hari Ini</span>';
                                                }
                                                if (!empty($row['reflection']) || !empty($row['evaluation_file'])) {
                                                    $keterangan_html .= '<span class="badge badge-evaluasi">Evaluasi</span>';
                                                }
                                                if (!empty($row['plan_material'])) {
                                                    $keterangan_html .= '<span class="badge badge-rencana-besok">Rencana Besok</span>';
                                                }
                                                if (empty($keterangan_html)) {
                                                    $keterangan_html = '<span class="badge badge-empty">Kosong</span>';
                                                }
                                                echo $keterangan_html;
                                                ?>
                                            </div>
                                        </td>
                                        <td><span
                                                class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                                        </td>
                                        <td><a href="review_laporan.php?id=<?= $row['id'] ?>" class="chip-btn chip-btn-blue btn-review">Detail</a>
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