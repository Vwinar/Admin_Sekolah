<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_subject'])) {
        $name = trim($_POST['subject_name']);
        if ($name) {
            try {
                $db->prepare("INSERT INTO subjects (name) VALUES (?)")->execute([$name]);
                $success = "Mata Pelajaran berhasil ditambahkan.";
            } catch (PDOException $e) {
                $error = "Gagal menambah mata pelajaran (mungkin duplikat).";
            }
        }
    } elseif (isset($_POST['add_class'])) {
        $name = trim($_POST['class_name']);
        if ($name) {
            try {
                $db->prepare("INSERT INTO classes (name) VALUES (?)")->execute([$name]);
                $success = "Kelas berhasil ditambahkan.";
            } catch (PDOException $e) {
                $error = "Gagal menambah kelas (mungkin duplikat).";
            }
        }
    } elseif (isset($_POST['delete_subject'])) {
        $id = $_POST['subject_id'];
        $db->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
        $success = "Mata Pelajaran dihapus.";
    } elseif (isset($_POST['delete_class'])) {
        $id = $_POST['class_id'];
        $db->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
        $success = "Kelas dihapus.";
    } elseif (isset($_POST['update_attendance_settings'])) {
        $waktu_masuk = $_POST['waktu_masuk'];
        $waktu_pulang = $_POST['waktu_pulang'];
        $radius = $_POST['radius'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $school_name = $_POST['school_name'];
        $waktu_pulang_otomatis = $_POST['waktu_pulang_otomatis'];

        $db->prepare("UPDATE settings SET waktu_masuk=?, waktu_pulang=?, radius=?, latitude=?, longitude=?, school_name=?, waktu_pulang_otomatis=? WHERE id=1")
            ->execute([$waktu_masuk, $waktu_pulang, $radius, $latitude, $longitude, $school_name, $waktu_pulang_otomatis]);
        $success = "Pengaturan Absensi berhasil disimpan.";
    }
}

$settings = $db->query("SELECT * FROM settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$subjects = $db->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$classes = $db->query("SELECT * FROM classes ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Data</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        /* Desktop: Proper button sizing */
        @media (min-width: 769px) {
            .header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                flex-direction: row !important;
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

            /* Grid layout responsive */
            div[style*="grid-template-columns: 1fr 1fr"] {
                display: block !important;
            }

            .card {
                margin-bottom: 1rem !important;
                padding: 1rem !important;
            }

            .card h3 {
                font-size: 1rem !important;
            }

            /* Form responsive */
            .form-group {
                margin-bottom: 0.75rem !important;
            }

            .form-label {
                font-size: 0.8rem !important;
            }

            .form-control {
                font-size: 0.8rem !important;
                padding: 0.5rem !important;
            }

            /* Button "Tambah" icon only on mobile - with higher specificity */
            .card form button[name="add_subject"].btn.btn-primary,
            .card form button[name="add_class"].btn.btn-primary {
                min-width: 2rem !important;
                width: 2rem !important;
                max-width: 2rem !important;
                height: 2rem !important;
                padding: 0.3rem !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                flex-shrink: 0 !important;
                font-size: 1rem !important;
                line-height: 1 !important;
            }

            button[name="add_subject"] .btn-text,
            button[name="add_class"] .btn-text {
                display: none !important;
            }

            /* Submit button in settings form */
            .btn {
                font-size: 0.75rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            /* List items */
            ul li {
                font-size: 0.8rem !important;
            }

            /* Delete button in list */
            button[name="delete_subject"],
            button[name="delete_class"] {
                font-size: 0.75rem !important;
                padding: 0.25rem 0.5rem !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 0.25rem !important;
                white-space: nowrap !important;
            }

            /* Hide delete button text on mobile, show icon only */
            button[name="delete_subject"] .delete-text,
            button[name="delete_class"] .delete-text {
                display: none !important;
            }

            /* Better layout for subject and class sections */
            ul[style*="list-style: none"] {
                max-height: 300px !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            ul[style*="list-style: none"] li {
                padding: 0.75rem 0 !important;
                border-bottom: 1px solid #e5e7eb !important;
                display: flex !important;
                flex-direction: row !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.5rem !important;
                flex-wrap: nowrap !important;
            }

            ul[style*="list-style: none"] li span {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                max-width: none !important;
                word-break: normal !important;
                overflow-wrap: break-word !important;
                text-align: left !important;
                white-space: normal !important;
            }

            ul[style*="list-style: none"] li form {
                flex: 0 0 auto !important;
                flex-shrink: 0 !important;
                margin: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                width: auto !important;
                max-width: 2rem !important;
            }

            /* Form input for add subject/class */
            form[style*="display: flex"] {
                flex-wrap: nowrap !important;
                gap: 0.5rem !important;
            }

            form[style*="display: flex"] .form-control {
                flex: 1 !important;
                min-width: 0 !important;
            }

            /* Pengaturan Absensi: Keep 2 columns on mobile */
            .card h3+form div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"] {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }

            /* Make sure form groups fit well in 2 columns */
            .card h3+form div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"] .form-group {
                margin-bottom: 0 !important;
            }

            /* Smaller text for labels in settings form */
            .card h3+form div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"] .form-label {
                font-size: 0.7rem !important;
                margin-bottom: 0.3rem !important;
            }

            /* Smaller input fields in settings form */
            .card h3+form div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"] .form-control {
                font-size: 0.75rem !important;
                padding: 0.4rem !important;
            }

            /* Mata Pelajaran dan Kelas: Keep as 1 column (stacked) on mobile */
            div[style*="display: grid"][style*="grid-template-columns: 1fr 1fr"]>.card {
                display: block !important;
                width: 100% !important;
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
                        <h1>Data Master: Mapel & Kelas</h1>
                    </div>
                </div>
            </header>

            <?php if ($success): ?>
                <div
                    style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Attendance Settings -->
                <div class="card">
                    <h3>Pengaturan Absensi</h3>
                    <form method="POST">
                        <input type="hidden" name="update_attendance_settings" value="1">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label">Nama Sekolah</label>
                                <input type="text" name="school_name" class="form-control"
                                    value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Radius (meter)</label>
                                <input type="number" name="radius" class="form-control"
                                    value="<?= htmlspecialchars($settings['radius'] ?? 100) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Masuk (Batas Terlambat)</label>
                                <input type="time" name="waktu_masuk" class="form-control"
                                    value="<?= htmlspecialchars($settings['waktu_masuk'] ?? '07:00:00') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Pulang (Awal)</label>
                                <input type="time" name="waktu_pulang" class="form-control"
                                    value="<?= htmlspecialchars($settings['waktu_pulang'] ?? '12:00:00') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Otomatis Pulang </label>
                                <input type="time" name="waktu_pulang_otomatis" class="form-control"
                                    value="<?= htmlspecialchars($settings['waktu_pulang_otomatis'] ?? '23:59:00') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Latitude</label>
                                <input type="text" name="latitude" id="latitude" class="form-control"
                                    value="<?= htmlspecialchars($settings['latitude'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude</label>
                                <input type="text" name="longitude" id="longitude" class="form-control"
                                    value="<?= htmlspecialchars($settings['longitude'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div id="map"
                            style="height: 400px; width: 100%; margin-top: 1rem; border-radius: 0.5rem; z-index: 0;">
                        </div>
                        <div style="margin-top: 1rem;">
                            <a href="https://www.google.com/maps" target="_blank"
                                style="font-size: 0.9rem; margin-right: 1rem;">Buka Google Maps untuk cari koordinat</a>
                            <button type="submit" class="btn btn-primary" style="width: auto;">Simpan
                                Pengaturan</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

                <!-- Subjects -->
                <div class="card">
                    <h3>Mata Pelajaran</h3>
                    <form method="POST" style="display: flex; gap: 0.5rem; margin: 1rem 0;">
                        <input type="text" name="subject_name" class="form-control" placeholder="Nama Mapel" required>
                        <button type="submit" name="add_subject" class="btn btn-primary" title="Tambah Mata Pelajaran">➕
                            <span class="btn-text">Tambah</span></button>
                    </form>
                    <ul style="list-style: none;">
                        <?php foreach ($subjects as $s): ?>
                            <li
                                style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                <span><?= htmlspecialchars($s['name']) ?></span>
                                <form method="POST" onsubmit="return confirm('Hapus mapel ini?');">
                                    <input type="hidden" name="subject_id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="delete_subject"
                                        style="background: none; border: none; color: var(--danger); cursor: pointer;"
                                        title="Hapus">🗑️ <span class="delete-text">Hapus</span></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Classes -->
                <div class="card">
                    <h3>Kelas</h3>
                    <form method="POST" style="display: flex; gap: 0.5rem; margin: 1rem 0;">
                        <input type="text" name="class_name" class="form-control" placeholder="Nama Kelas" required>
                        <button type="submit" name="add_class" class="btn btn-primary" title="Tambah Kelas">➕ <span
                                class="btn-text">Tambah</span></button>
                    </form>
                    <ul style="list-style: none;">
                        <?php foreach ($classes as $c): ?>
                            <li
                                style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                                <span><?= htmlspecialchars($c['name']) ?></span>
                                <form method="POST" onsubmit="return confirm('Hapus kelas ini?');">
                                    <input type="hidden" name="class_id" value="<?= $c['id'] ?>">
                                    <button type="submit" name="delete_class"
                                        style="background: none; border: none; color: var(--danger); cursor: pointer;"
                                        title="Hapus">🗑️ <span class="delete-text">Hapus</span></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
        // Initialize Map
        document.addEventListener('DOMContentLoaded', function () {
            var latInput = document.getElementById('latitude');
            var lngInput = document.getElementById('longitude');

            // Default to Jakarta if no coordinates set
            var initialLat = parseFloat(latInput.value) || -6.2088;
            var initialLng = parseFloat(lngInput.value) || 106.8456;

            var map = L.map('map').setView([initialLat, initialLng], 15);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(map);

            var marker = L.marker([initialLat, initialLng], {
                draggable: true
            }).addTo(map);

            // Update inputs when marker is dragged
            marker.on('dragend', function (event) {
                var position = marker.getLatLng();
                latInput.value = position.lat.toFixed(6);
                lngInput.value = position.lng.toFixed(6);
                map.panTo(position);
            });

            // Move marker and update inputs on map click
            map.on('click', function (e) {
                marker.setLatLng(e.latlng);
                latInput.value = e.latlng.lat.toFixed(6);
                lngInput.value = e.latlng.lng.toFixed(6);
                map.panTo(e.latlng);
            });

            // Update marker if inputs are changed manually
            function updateMarkerFromInputs() {
                var lat = parseFloat(latInput.value);
                var lng = parseFloat(lngInput.value);

                if (!isNaN(lat) && !isNaN(lng)) {
                    var newLatLng = new L.LatLng(lat, lng);
                    marker.setLatLng(newLatLng);
                    map.panTo(newLatLng);
                }
            }

            latInput.addEventListener('change', updateMarkerFromInputs);
            lngInput.addEventListener('change', updateMarkerFromInputs);

            // Force map resize logic if sidebar toggles or tab changes (just effectively resizing on load)
            setTimeout(function () { map.invalidateSize(); }, 300);
        });
    </script>
</body>

</html>