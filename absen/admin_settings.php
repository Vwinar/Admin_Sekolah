<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = new SQLite3('absen.db');

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $radius = $_POST['radius'] ?? '';
    $waktu_masuk = $_POST['waktu_masuk'] ?? '';
    $waktu_pulang = $_POST['waktu_pulang'] ?? '';
    $waktu_pulang_otomatis = $_POST['waktu_pulang_otomatis'] ?? '';
    $school_name = $_POST['school_name'] ?? '';

    // Handle logo upload
    $logo_path = '';
    $existing_logo_path = '';
    $settings_result = $db->query('SELECT school_logo FROM settings WHERE id = 1');
    $existing_settings = $settings_result->fetchArray(SQLITE3_ASSOC);
    if ($existing_settings) {
        $existing_logo_path = $existing_settings['school_logo'] ?? '';
    }
    if (isset($_FILES['school_logo']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $tmp_name = $_FILES['school_logo']['tmp_name'];
        $name = basename($_FILES['school_logo']['name']);
        $target_file = $upload_dir . uniqid() . '_' . $name;
        if (move_uploaded_file($tmp_name, $target_file)) {
            $logo_path = $target_file;
        }
    } else {
        $logo_path = $existing_logo_path;
    }

    // Check if settings row exists
    $settings_result = $db->query('SELECT * FROM settings WHERE id = 1');
    $existing_settings = $settings_result->fetchArray(SQLITE3_ASSOC);

    if ($existing_settings) {
        // Update existing settings
        $update_stmt = $db->prepare('UPDATE settings SET latitude = :latitude, longitude = :longitude, radius = :radius, waktu_masuk = :waktu_masuk, waktu_pulang = :waktu_pulang, waktu_pulang_otomatis = :waktu_pulang_otomatis, school_name = :school_name, school_logo = :school_logo WHERE id = 1');
        $update_stmt->bindValue(':latitude', $latitude);
        $update_stmt->bindValue(':longitude', $longitude);
        $update_stmt->bindValue(':radius', $radius);
        $update_stmt->bindValue(':waktu_masuk', $waktu_masuk);
        $update_stmt->bindValue(':waktu_pulang', $waktu_pulang);
        $update_stmt->bindValue(':waktu_pulang_otomatis', $waktu_pulang_otomatis);
        $update_stmt->bindValue(':school_name', $school_name);
        $update_stmt->bindValue(':school_logo', $logo_path);
        $update_stmt->execute();
    } else {
        // Insert new settings
        $insert_stmt = $db->prepare('INSERT INTO settings (id, latitude, longitude, radius, waktu_masuk, waktu_pulang, waktu_pulang_otomatis, school_name, school_logo) VALUES (1, :latitude, :longitude, :radius, :waktu_masuk, :waktu_pulang, :waktu_pulang_otomatis, :school_name, :school_logo)');
        $insert_stmt->bindValue(':latitude', $latitude);
        $insert_stmt->bindValue(':longitude', $longitude);
        $insert_stmt->bindValue(':radius', $radius);
        $insert_stmt->bindValue(':waktu_masuk', $waktu_masuk);
        $insert_stmt->bindValue(':waktu_pulang', $waktu_pulang);
        $insert_stmt->bindValue(':waktu_pulang_otomatis', $waktu_pulang_otomatis);
        $insert_stmt->bindValue(':school_name', $school_name);
        $insert_stmt->bindValue(':school_logo', $logo_path);
        $insert_stmt->execute();
    }

    $message = 'Settings saved successfully.';
}

// Load current settings
$settings_result = $db->query('SELECT * FROM settings WHERE id = 1');
$settings = $settings_result->fetchArray(SQLITE3_ASSOC);

$latitude = $settings['latitude'] ?? '';
$longitude = $settings['longitude'] ?? '';
$radius = $settings['radius'] ?? '';
$waktu_masuk = $settings['waktu_masuk'] ?? '';
$waktu_pulang = $settings['waktu_pulang'] ?? '';
$waktu_pulang_otomatis = $settings['waktu_pulang_otomatis'] ?? '';
$school_name = $settings['school_name'] ?? '';
$school_logo = $settings['school_logo'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Settings - Absen App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #map {
            height: 400px;
            width: 100%;
            margin: 1rem 0;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-6">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded shadow">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Admin Settings</h1>
            <a href="admin_home.php" class="mt-4 md:mt-0 inline-flex items-center justify-center bg-gray-800 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($message): ?>
            <div id="toast" class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg opacity-0 transition-opacity duration-300">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6" id="settings-form">
            <div>
                <label class="block font-medium mb-1">Lokasi Sekolah</label>
                <div id="map"></div>
                <p class="text-sm text-gray-500 mb-4">Klik pada peta untuk menentukan lokasi atau masukkan koordinat manual</p>
            </div>

            <div>
                <label for="latitude" class="block font-medium mb-1">Latitude</label>
                <div class="flex space-x-2 items-center">
                    <input type="text" id="latitude" name="latitude" value="<?= htmlspecialchars($latitude) ?>" class="flex-1 border border-gray-300 rounded p-2" required />
                    <button type="button" id="btn-get-location" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition" title="Get Current Location">
                        Dapatkan Lokasi
                    </button>
                </div>
                <p id="location-status" class="text-sm text-gray-500 mt-1"></p>
            </div>

            <div>
                <label for="longitude" class="block font-medium mb-1">Longitude</label>
                <input type="text" id="longitude" name="longitude" value="<?= htmlspecialchars($longitude) ?>" class="w-full border border-gray-300 rounded p-2" required />
            </div>

            <div>
                <label for="radius" class="block font-medium mb-1">Radius (meters)</label>
                <input type="number" id="radius" name="radius" value="<?= htmlspecialchars($radius) ?>" class="w-full border border-gray-300 rounded p-2" required min="0" />
            </div>

            <div>
                <label for="waktu_masuk" class="block font-medium mb-1">Waktu Masuk (Time In) - Format 24 Jam</label>
                <input type="time" id="waktu_masuk" name="waktu_masuk" value="<?= htmlspecialchars($waktu_masuk) ?>" class="w-full border border-gray-300 rounded p-2" required step="1" />
            </div>

            <div>
                <label for="waktu_pulang" class="block font-medium mb-1">Waktu Pulang (Time Out) - Format 24 Jam</label>
                <input type="time" id="waktu_pulang" name="waktu_pulang" value="<?= htmlspecialchars($waktu_pulang) ?>" class="w-full border border-gray-300 rounded p-2" required step="1" />
            </div>

            <div>
                <label for="waktu_pulang_otomatis" class="block font-medium mb-1">Waktu Pulang Otomatis (Automatic Time Out) - Format 24 Jam</label>
                <input type="time" id="waktu_pulang_otomatis" name="waktu_pulang_otomatis" value="<?= htmlspecialchars($waktu_pulang_otomatis) ?>" class="w-full border border-gray-300 rounded p-2" step="1" />
            </div>

            <div>
                <label for="school_name" class="block font-medium mb-1">School Name</label>
                <input type="text" id="school_name" name="school_name" value="<?= htmlspecialchars($school_name) ?>" class="w-full border border-gray-300 rounded p-2" required />
            </div>

            <div>
                <label for="school_logo" class="block font-medium mb-1">School Logo</label>
                <?php if ($school_logo): ?>
                    <div class="relative inline-block mb-2">
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="h-24" id="logo-preview" />
                        <button type="button" id="btn-remove-logo" class="absolute top-0 right-10 bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-700 transition" title="Hapus Gambar">&times;</button>
                        <lab for="school_logo" class="absolute top-0 right-0 bg-blue-600 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-blue-700 transition cursor-pointer" title="Ganti Gambar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 11l6 6m-6-6l-3 3m3-3l3-3m-3 3v6" />
                            </svg>
                        </label>
                    </div>
                <?php else: ?>
                    <img src="" alt="Logo Preview" class="h-24 mb-2 hidden" id="logo-preview" />
                <?php endif; ?>
                <input type="file" id="school_logo" name="school_logo" accept="image/*" class="hidden" />
            </div>

            <div class="flex space-x-4">
                <button type="submit" class="bg-blue-700 text-white px-4 py-2 rounded hover:bg-red-800 transition">Simpan</button>
                <button type="button" id="btn-batal" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500 transition">Batal</button>
            </div>
        </form>
    </div>

    <script>
        // Show toast notification if present
        window.addEventListener('DOMContentLoaded', () => {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.classList.remove('opacity-0');
                setTimeout(() => {
                    toast.classList.add('opacity-0');
                }, 3000);
            }
        });

        // Initialize map
        const defaultLat = <?= $latitude && is_numeric($latitude) ? htmlspecialchars($latitude) : '-6.2088' ?>;
        const defaultLng = <?= $longitude && is_numeric($longitude) ? htmlspecialchars($longitude) : '106.8456' ?>;
        
        const map = L.map('map').setView([defaultLat, defaultLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Add marker
        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);

        // Update marker position and inputs when map is clicked
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
        });

        // Update marker position when marker is dragged
        marker.on('dragend', function(e) {
            const position = marker.getLatLng();
            document.getElementById('latitude').value = position.lat.toFixed(6);
            document.getElementById('longitude').value = position.lng.toFixed(6);
        });

        // Update marker position when inputs change
        document.getElementById('latitude').addEventListener('change', updateMarkerFromInputs);
        document.getElementById('longitude').addEventListener('change', updateMarkerFromInputs);

        function updateMarkerFromInputs() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng]);
            }
        }

        // Update map when getting current location
        document.getElementById('btn-get-location').addEventListener('click', function() {
            const locationStatus = document.getElementById('location-status');
            if (!navigator.geolocation) {
                locationStatus.textContent = 'Geolocation is not supported by your browser.';
                return;
            }
            locationStatus.textContent = 'Mendapatkan lokasi...';
            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                document.getElementById('latitude').value = lat.toFixed(6);
                document.getElementById('longitude').value = lng.toFixed(6);
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 15);
                locationStatus.textContent = 'Lokasi berhasil didapatkan.';
            }, function() {
                locationStatus.textContent = 'Gagal mendapatkan lokasi.';
            });
        });

        // Set locale to Indonesian and 24-hour format for time inputs
        function setIndonesianTimeFormat() {
            // No direct locale setting for input type time, but browsers use 24-hour by default
            // Just ensure step attribute is set for seconds precision (already set)
        }

        document.getElementById('btn-batal').addEventListener('click', function() {
            const form = document.getElementById('settings-form');
            form.reset();
            // Hide logo preview and remove button on reset
            const logoPreview = document.getElementById('logo-preview');
            const removeLogoBtn = document.getElementById('btn-remove-logo');
            if (logoPreview) {
                logoPreview.src = '';
                logoPreview.classList.add('hidden');
            }
            if (removeLogoBtn) {
                removeLogoBtn.style.display = 'none';
            }
            // Clear location status
            const locationStatus = document.getElementById('location-status');
            if (locationStatus) {
                locationStatus.textContent = '';
            }
        });

        // Logo preview and remove button
        const schoolLogoInput = document.getElementById('school_logo');
        const logoPreview = document.getElementById('logo-preview');
        const removeLogoBtn = document.getElementById('btn-remove-logo');

        if (schoolLogoInput) {
            schoolLogoInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        logoPreview.src = e.target.result;
                        logoPreview.classList.remove('hidden');
                        if (removeLogoBtn) {
                            removeLogoBtn.style.display = 'flex';
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    logoPreview.src = '';
                    logoPreview.classList.add('hidden');
                    if (removeLogoBtn) {
                        removeLogoBtn.style.display = 'none';
                    }
                }
            });
        }

        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', function() {
                schoolLogoInput.value = '';
                logoPreview.src = '';
                logoPreview.classList.add('hidden');
                removeLogoBtn.style.display = 'none';
            });
        }
    </script>         
</body>
</html>
