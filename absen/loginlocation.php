<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: user_home.php');
    exit();
}

$error = '';
$success = '';

try {
    $db = new SQLite3('absen.db');

    // Fetch school settings for logo and name
    $settings_result = $db->query('SELECT school_name, school_logo, latitude, longitude, radius FROM settings WHERE id = 1');
    $settings = $settings_result->fetchArray(SQLITE3_ASSOC);
    $school_name = $settings['school_name'] ?? 'SD NEGERI WARUKULON';
    $school_logo = $settings['school_logo'] ?? 'https://vaywinar.web.id/wk1.png';
    $school_lat = $settings['latitude'] ?? 0;
    $school_lng = $settings['longitude'] ?? 0;
    $school_radius = $settings['radius'] ?? 0;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($username && $password && $role) {
            
            $stmt = $db->prepare('SELECT id, password_hash, role FROM users WHERE username = :username AND role = :role');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $user['role'];
                $success = 'Login successful. Redirecting...';
                if ($user['role'] === 'admin') {
                    header('Refresh: 2; URL=admin_home.php');
                } else {
                    header('Refresh: 2; URL=user_home.php');
                }
            } else {
                $error = 'Invalid username, password, or role.';
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
} finally {
    if ($db) {
        $db->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Absen App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }
        
        .card {
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: 12px;
            border: 1px solid rgba(209, 213, 219, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #4338ca 0%, #6d28d9 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }
        
        .input-field {
            transition: all 0.3s ease;
        }
        
        .input-field:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-container {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            /* background-color: #16a34a; */
            padding: 10px;
            margin-bottom: 1.5rem;
            border: 3px solid #16a34a;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 50%;
            display: block;
        }
        
        .header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #4f46e5;
            margin-bottom: 0;
        }
        
        @media (max-width: 640px) {
            .logo-container {
                width: 80px;
                height: 80px;
            }
            
            .header h2 {
                font-size: 1.1rem;
            }
            
            .header h1 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="card w-full max-w-md p-8">
        <div class="header">
            <div class="logo-container">
                <a href="https://vaywinar.web.id" target="_blank">
                    <img src="<?= htmlspecialchars($school_logo) ?>" alt="<?= htmlspecialchars($school_name) ?> Logo" class="logo">
                </a>
            </div>
            <h2><?= htmlspecialchars($school_name) ?></h2>
            <h1>Sistem Absensi</h1>            
        </div>
        
        <form method="POST" action="login.php" class="space-y-5" id="loginForm">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="username" name="username" required 
                           class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500" 
                           placeholder="Enter your username">
                </div>
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="password-container relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required 
                           class="input-field w-full pl-10 pr-10 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500" 
                           placeholder="Enter your password">
                    <span class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user-tag text-gray-400"></i>
                    </div>
                    <select id="role" name="role" required
                            class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-indigo-500 appearance-none">
                        <option value="">Select Role</option>
                        <option value="guru">Guru</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Koordinat Lokasi</label>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" id="latitude" readonly class="input-field w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" placeholder="Latitude">
                    <input type="text" id="longitude" readonly class="input-field w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100" placeholder="Longitude">
                </div>
                <p id="distanceDisplay" class="text-sm text-gray-600 mt-1">Jarak dari sekolah: - m</p>
                <p id="locationError" class="text-red-500 text-sm mt-1 hidden">Lokasi tidak dapat dideteksi. Login tidak dapat dilakukan.</p>
            </div>

            <button type="submit" class="btn-primary w-full text-white py-2.5 rounded-lg font-medium text-sm" disabled>
                Login
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Password toggle functionality
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        // Show notifications
        <?php if ($success): ?>
        Toastify({
            text: "<?= htmlspecialchars($success) ?>",
            duration: 3000,
            gravity: "top",
            position: "center",
            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
            className: "toastify-success",
            stopOnFocus: true,
        }).showToast();
        <?php endif; ?>

        <?php if ($error): ?>
        Toastify({
            text: "<?= htmlspecialchars($error) ?>",
            duration: 3000,
            gravity: "top",
            position: "center",
            backgroundColor: "linear-gradient(to right, #ff416c, #ff4b2b)",
            className: "toastify-error",
            stopOnFocus: true,
        }).showToast();
        <?php endif; ?>

        // School settings
        const schoolLat = <?= $school_lat ?>;
        const schoolLng = <?= $school_lng ?>;
        const schoolRadius = <?= $school_radius ?>;

        // Haversine distance calculation in meters
        function getDistance(lat1, lng1, lat2, lng2) {
            const R = 6371000; // Radius of the Earth in meters
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            const distance = R * c;
            return distance;
        }

        // Geolocation functionality
        let userLat = null;
        let userLng = null;

        function checkLoginEligibility() {
            const loginBtn = document.querySelector('button[type="submit"]');
            const errorMsg = document.getElementById('locationError');
            const role = document.getElementById('role').value;

            if (!userLat || !userLng) {
                // Location not detected yet
                return;
            }

            if (role === 'admin') {
                loginBtn.disabled = false;
                errorMsg.classList.add('hidden');
                document.getElementById('distanceDisplay').textContent = 'Admin: Login tanpa batas jarak.';
            } else {
                const distance = getDistance(schoolLat, schoolLng, userLat, userLng);
                document.getElementById('distanceDisplay').textContent = `Jarak dari sekolah: ${distance.toFixed(2)} m`;

                if (schoolRadius == 0 || distance <= schoolRadius) {
                    loginBtn.disabled = false;
                    errorMsg.classList.add('hidden');
                } else {
                    errorMsg.textContent = `Jarak anda ${distance.toFixed(2)} m dari sekolah.`;
                    errorMsg.classList.remove('hidden');
                    loginBtn.disabled = true;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const loginBtn = document.querySelector('button[type="submit"]');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const errorMsg = document.getElementById('locationError');
            const roleSelect = document.getElementById('role');

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    userLat = position.coords.latitude;
                    userLng = position.coords.longitude;
                    latInput.value = userLat;
                    lngInput.value = userLng;

                    checkLoginEligibility();
                }, function(error) {
                    console.error('Error getting location:', error);
                    errorMsg.textContent = 'Lokasi tidak dapat dideteksi. Login tidak dapat dilakukan.';
                    errorMsg.classList.remove('hidden');
                    loginBtn.disabled = true;
                });
            } else {
                errorMsg.textContent = 'Geolocation tidak didukung oleh browser ini.';
                errorMsg.classList.remove('hidden');
                loginBtn.disabled = true;
            }

            // Re-check when role changes
            roleSelect.addEventListener('change', checkLoginEligibility);
        });
    </script>
</body>
</html>