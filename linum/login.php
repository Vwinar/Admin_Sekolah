<?php
require_once 'config.php';
require_once 'functions.php';

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $loginRole = $_POST['role']; // 'siswa' or 'admin' (from form)

    if (empty($username) || empty($password)) {
        $error = "Username dan Password harus diisi.";
    } else {
        // Map Form Role to Database Role in Laporan Harian
        // Form 'admin' = DB 'guru' (because Guru is Admin of Linum)
        // Form 'siswa' = DB 'siswa'
        $dbRole = ($loginRole == 'admin') ? 'guru' : 'siswa';

        try {
            // Check against Laporan Harian Database
            $stmt = $laporanPdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
            $stmt->execute([$username, $dbRole]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role']; // Will be 'guru' or 'siswa'
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['photo'] = isset($user['profile_photo']) ? $user['profile_photo'] : ''; // Laporan DB uses profile_photo column
                // Session 'assigned_class' might be needed for class filtering
                $_SESSION['assigned_class'] = isset($user['assigned_class']) ? $user['assigned_class'] : '';

                if ($loginRole == 'siswa') {
                    header("Location: siswa/dashboard.php");
                } else {
                    // Logged in as Guru (Admin of Linum)
                    header("Location: admin/dashboard.php");
                }
                exit();
            } else {
                $error = "Username atau Password salah.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Laporan Resume Pembelajaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --error-color: #f72585;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            max-width: 480px;
            width: 100%;
            animation: fadeIn 0.5s ease-in-out;
        }

        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            text-align: center;
            border-bottom: none;
        }

        .card-body {
            padding: 40px;
            background-color: white;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        .input-password {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--dark-color);
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .toggle-password:hover {
            opacity: 1;
        }

        .btn-login {
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(67, 97, 238, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 100;
            text-decoration: none;
        }

        .fab:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.4);
        }

        .alert {
            border-radius: 8px;
        }

        @media (max-width: 576px) {

            .card-header,
            .card-body {
                padding: 25px;
            }

            .fab {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-1">Selamat Datang</h3>
                <p class="mb-0">Sistem Laporan Resume Pembelajaran</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Masukkan username" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-password">
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Masukkan password" required>
                            <button type="button" class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="role" class="form-label">Login Sebagai</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="siswa">Siswa</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
            </div>
        </div>
    </div>

    <a href="https://vaywinar.web.id/vay" class="fab" title="Kunjungi Vaywinar">
        <i class="fas fa-external-link-alt"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const eyeIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye-slash');
            eyeIcon.classList.toggle('fa-eye');
        });

        // Add animation to form elements
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach((input, index) => {
                input.style.opacity = 0;
                input.style.transform = 'translateY(10px)';
                input.style.transition = `all 0.3s ease ${index * 0.1}s`;
                setTimeout(() => {
                    input.style.opacity = 1;
                    input.style.transform = 'translateY(0)';
                }, 100);
            });
        });
    </script>
</body>

</html>