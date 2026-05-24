<?php
ob_start(); // Buffer output to prevent header errors

session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
require_once '../utils/attendance_logic.php';

// Get counts
$stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_reports = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$approved_reports = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_reports = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY report_date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <style>
        .map-container {
            height: 300px;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            width: 100%;
            z-index: 1;
        }

        .hidden {
            display: none;
        }

        .photo-preview {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: var(--radius);
            display: none;
            margin-bottom: 1rem;
        }

        .camera-btn {
            text-align: center;
            border: 2px dashed var(--border);
            padding: 2rem;
            border-radius: var(--radius);
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .camera-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Responsive Header for Dashboard */
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Desktop: Standard Button */
        .btn-create-report {
            width: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        /* STATS GRID STYLING - MODIFIED TO SINGLE ROW */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
            width: 100%;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-diff {
            font-size: 0.8rem;
            color: var(--success);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .stat-diff.negative {
            color: var(--danger);
        }

        /* Specific color for each stat card */
        .stats-grid>.stat-card:nth-child(1) {
            border-top: 4px solid var(--primary);
        }

        .stats-grid>.stat-card:nth-child(2) {
            border-top: 4px solid var(--success);
        }

        .stats-grid>.stat-card:nth-child(3) {
            border-top: 4px solid var(--warning);
        }

        @media (max-width: 768px) {
            .header {
                position: relative;
                padding-right: 0;
            }

            .header-right {
                position: absolute;
                top: 0;
                right: 0;
                margin-top: 0.25rem;
            }

            .btn-create-report {
                width: 40px !important;
                height: 40px;
                padding: 0 !important;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background: var(--primary) !important;
                box-shadow: var(--shadow-sm);
                font-size: 1.25rem;
                /* Larger plus icon */
            }

            .btn-create-report span.btn-text {
                display: none;
            }

            .btn-create-report i {
                margin: 0 !important;
            }

            /* Hide the original desktop class logic which might conflict */
            .btn-desktop-only {
                display: flex !important;
                /* Force show as it becomes the mobile button too */
            }

            /* Mobile responsive stats grid */
            .stats-grid {
                grid-template-columns: 1fr 1fr 1fr !important;
                gap: 0.5rem !important;
                margin-bottom: 1.5rem;
            }

            .stat-card {
                padding: 1rem 0.5rem !important;
                min-height: 90px;
            }

            .stat-value {
                font-size: 1.5rem !important;
                margin-bottom: 0.25rem;
            }

            .stat-label {
                font-size: 0.75rem !important;
                white-space: normal;
                line-height: 1.2;
                margin-bottom: 0.25rem;
            }

            .stat-diff {
                display: none !important;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                gap: 0.35rem !important;
            }

            .stat-card {
                padding: 0.75rem 0.25rem !important;
            }

            .stat-value {
                font-size: 1.3rem !important;
            }

            .stat-label {
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>

<body class="dashboard-page">
    <div class="dashboard-layout">
        <?php include '../layout/user_sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div>
                        <h1>Halo, <?= htmlspecialchars($_SESSION['full_name']) ?> 👋</h1>
                        <p style="color: var(--text-muted)">Berikut ringkasan aktivitas Anda.</p>
                    </div>
                </div>
                <div class="header-right">
                    <a href="laporan_baru.php" class="chip-btn chip-btn-blue btn-create-report">
                        <i class="fas fa-plus"></i> <span class="btn-text">Buat Laporan</span>
                    </a>
                </div>
            </header>

            <!-- Stats Grid - Now in one row -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Laporan</div>
                    <div class="stat-value"><?= $total_reports ?></div>
                    <div class="stat-diff">Total Keseluruhan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Laporan Disetujui</div>
                    <div class="stat-value" style="color: var(--success);"><?= $approved_reports ?></div>
                    <div class="stat-diff">
                        <i class="fas fa-arrow-up"></i>
                        <?php if ($total_reports > 0): ?>
                            <?= round(($approved_reports / $total_reports) * 100) ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Menunggu Review</div>
                    <div class="stat-value" style="color: var(--warning);"><?= $pending_reports ?></div>
                    <div class="stat-diff">
                        <?php if ($total_reports > 0): ?>
                            <?= round(($pending_reports / $total_reports) * 100) ?>% dari total
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="mb-2">Laporan Terakhir</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Mata Pelajaran</th>
                                <th>Materi</th>
                                <th>Keterangan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_reports) === 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted);">Belum ada laporan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_reports as $rpt): ?>
                                    <?php
                                    $stages = [];
                                    // Check Rencana Hari Ini
                                    if (!empty($rpt['plan_topic']) || !empty($rpt['plan_learning_objective']) || !empty($rpt['plan_method_type'])) {
                                        $stages[] = ['label' => 'Rencana', 'class' => 'badge-revision'];
                                    }
                                    // Check Hari Ini
                                    if (!empty($rpt['material_taught']) || !empty($rpt['attendance'])) {
                                        $stages[] = ['label' => 'Hari Ini', 'class' => 'badge-approved'];
                                    }
                                    // Check Evaluasi
                                    if (!empty($rpt['reflection']) || !empty($rpt['evaluation_file'])) {
                                        $stages[] = ['label' => 'Evaluasi', 'class' => 'badge-warning', 'style' => 'background-color: #fef3c7; color: #d97706;'];
                                    }
                                    // Check Rencana Besok
                                    if (!empty($rpt['plan_material']) || !empty($rpt['plan_goal'])) {
                                        $stages[] = ['label' => 'Besok', 'class' => 'badge-pending'];
                                    }
                                    ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($rpt['report_date'])) ?></td>
                                        <td><?= htmlspecialchars($rpt['subject'] ?: ($rpt['plan_subject'] ?? '-')) ?></td>
                                        <td><?= htmlspecialchars(substr($rpt['material_taught'] ?? $rpt['plan_topic'] ?? '-', 0, 50)) ?>...
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                <?php foreach ($stages as $stage): ?>
                                                    <span class="badge <?= $stage['class'] ?>" <?= isset($stage['style']) ? 'style="' . $stage['style'] . '"' : '' ?>>
                                                        <?= $stage['label'] ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (empty($stages)): ?>
                                                    <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $rpt['status'] ?>">
                                                <?= ucfirst($rpt['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="laporan_detail.php?id=<?= $rpt['id'] ?>" class="chip-btn chip-btn-blue btn-review">Lihat</a>
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
    <script>
        // Digital Clock (Sidebar)
        setInterval(() => {
            const clockEl = document.getElementById('digital-clock');
            if (clockEl) clockEl.innerText = new Date().toLocaleTimeString('id-ID');
        }, 1000);
    </script>
</body>

</html>