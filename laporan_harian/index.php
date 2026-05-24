<?php
session_start();
require_once 'config/db_connect.php';

$error = '';

// Load school settings for logo and name
$stmt = $db->query('SELECT school_name, school_logo FROM settings WHERE id = 1');
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$school_name = $settings['school_name'] ?? 'Sistem Laporan Harian Sekolah';
$school_logo = $settings['school_logo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Store additional user info for linum
        $_SESSION['photo'] = isset($user['profile_photo']) ? $user['profile_photo'] : '';
        $_SESSION['assigned_class'] = isset($user['assigned_class']) ? $user['assigned_class'] : '';

        // Route based on role
        if ($user['role'] === 'admin') {
            // Kepala Sekolah
            header('Location: admin/dashboard_admin.php');
        } elseif ($user['role'] === 'guru') {
            // Guru
            header('Location: guru/dashboard_guru.php');
        } elseif ($user['role'] === 'siswa') {
            // Siswa - redirect to linum
            header('Location: ../linum/siswa/dashboard.php');
        } else {
            // Default fallback
            header('Location: guru/dashboard_guru.php');
        }
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Laporan Harian</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #00b4d8 0%, #06d6a0 100%);
            position: relative;
        }

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.2rem;
            background: linear-gradient(135deg, #00b4d8 0%, #06d6a0 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 24px rgba(0, 180, 216, 0.3);
        }

        .login-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            background: linear-gradient(135deg, #00b4d8 0%, #06d6a0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.4rem;
        }

        .login-header p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #00b4d8;
            font-size: 1.2rem;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8fafc;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #00b4d8;
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 180, 216, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.2s;
            font-size: 1.1rem;
            z-index: 3;
        }

        .password-toggle:hover {
            color: #00b4d8;
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #00b4d8 0%, #06d6a0 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 24px rgba(0, 180, 216, 0.3);
            margin-top: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0, 180, 216, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            color: white;
            padding: 0.9rem;
            border-radius: 10px;
            margin-bottom: 1.2rem;
            text-align: center;
            font-weight: 600;
            font-size: 0.9rem;
            animation: shake 0.5s;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .footer-text {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.95);
            font-size: 0.85rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .footer-text a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 2px solid rgba(255, 255, 255, 0.6);
            transition: border-color 0.3s;
        }

        .footer-text a:hover {
            border-bottom-color: white;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 2rem 1.5rem;
            }

            .login-header h1 {
                font-size: 1.4rem;
            }

            .logo {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <?php if ($school_logo && file_exists($school_logo)): ?>
                        <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo"
                            style="width: 100%; height: 100%; object-fit: contain; border-radius: 16px;">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap"></i>
                    <?php endif; ?>
                </div>
                <h1>Selamat Datang</h1>
                <p><?= htmlspecialchars($school_name) ?></p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" id="username" name="username" class="form-control"
                            placeholder="Masukkan username Anda" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="Masukkan password Anda" required>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>
        </div>

        <div class="footer-text">
            © 2024 Sistem Laporan Harian • Powered by <a href="https://vaywinar.web.id" target="_blank">Vaywinar</a>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            if (type === 'password') {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });

        // Add focus animation
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function () {
                // Find the input-icon in the input-wrapper (parent or grandparent)
                const wrapper = this.closest('.input-wrapper');
                const icon = wrapper ? wrapper.querySelector('.input-icon') : null;
                if (icon) {
                    icon.style.color = '#667eea';
                }
            });

            input.addEventListener('blur', function () {
                // Find the input-icon in the input-wrapper (parent or grandparent)
                const wrapper = this.closest('.input-wrapper');
                const icon = wrapper ? wrapper.querySelector('.input-icon') : null;
                if (icon) {
                    icon.style.color = '#a0aec0';
                }
            });
        });
    </script>
</body>

</html>
