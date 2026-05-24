<?php
session_start();
require_once '../config/db_connect.php';

// Validasi Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'] ?? $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard - Absen App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col md:flex-row">
        <!-- Sidebar -->
        <div class="bg-primary-800 text-white w-full md:w-64 p-4 md:min-h-screen z-20">
            <div class="flex items-center justify-between md:justify-center md:flex-col mb-8">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-calendar-check text-2xl"></i>
                    <h1 class="text-xl font-bold">Absen App</h1>
                </div>
                <button class="md:hidden text-white focus:outline-none" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <nav class="space-y-2 hidden md:block" id="sidebar-menu">
                <div class="px-4 py-2 bg-primary-700 rounded-lg mb-4">
                    <p class="text-sm text-primary-200">Welcome back,</p>
                    <p class="font-medium"><?= htmlspecialchars($fullname) ?></p>
                </div>

                <a href="#" class="flex items-center space-x-3 px-4 py-3 bg-primary-700 rounded-lg text-white">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="rekap_absensi.php"
                    class="flex items-center space-x-3 px-4 py-3 hover:bg-primary-700 rounded-lg text-white transition-colors">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Rekap Absensi</span>
                </a>
                <a href="manage_guru_absensi.php"
                    class="flex items-center space-x-3 px-4 py-3 hover:bg-primary-700 rounded-lg text-white transition-colors">
                    <i class="fas fa-users-cog"></i>
                    <span>Manage Guru</span>
                </a>
                <a href="settings_absensi.php"
                    class="flex items-center space-x-3 px-4 py-3 hover:bg-primary-700 rounded-lg text-white transition-colors">
                    <i class="fas fa-cogs"></i>
                    <span>Setting</span>
                </a>
                <!-- Link back to Main Dashboard -->
                <a href="dashboard_admin.php"
                    class="flex items-center space-x-3 px-4 py-3 hover:bg-primary-700 rounded-lg text-white transition-colors mt-4 border-t border-primary-700">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Laporan</span>
                </a>
                <a href="../logout.php"
                    class="flex items-center space-x-3 px-4 py-3 hover:bg-primary-700 rounded-lg text-white transition-colors">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 p-6">
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Admin Dashboard</h1>
                <p class="text-gray-600">Welcome to the Absen App administration panel</p>
            </div>

            <!-- Primary Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <a href="rekap_absensi.php"
                    class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center">
                    <div class="bg-blue-100 p-4 rounded-full mb-4">
                        <i class="fas fa-clipboard-list text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Rekap Absensi</h3>
                    <p class="text-gray-500 text-sm">View and manage attendance records</p>
                </a>

                <a href="manage_guru_absensi.php"
                    class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow flex flex-col items-center text-center">
                    <div class="bg-green-100 p-4 rounded-full mb-4">
                        <i class="fas fa-users-cog text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Manage Guru</h3>
                    <p class="text-gray-500 text-sm">Teacher management system</p>
                </a>
            </div>
        </div>
    </div>

    <!-- FAB Buttons -->
    <div class="fixed bottom-6 right-6 flex flex-col space-y-4">
        <!-- Clear Uploads FAB -->
        <button onclick="showUploadsConfirmDialog()"
            class="bg-orange-600 hover:bg-orange-700 w-14 h-14 rounded-full flex items-center justify-center text-white shadow-lg transition-colors">
            <i class="fas fa-folder text-xl"></i>
        </button>
        <!-- Reset Database FAB -->
        <button onclick="showConfirmDialog()"
            class="bg-red-600 hover:bg-red-700 w-14 h-14 rounded-full flex items-center justify-center text-white shadow-lg transition-colors">
            <i class="fas fa-trash-alt text-xl"></i>
        </button>
    </div>

    <!-- Reset Database Confirmation Dialog -->
    <div id="confirmDialog"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm mx-4">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-4xl mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Reset Data Absensi</h3>
                <p class="text-gray-600 mb-6">Warning: Ini akan menghapus SEMUA data absensi, izin, dan foto bukti. Data
                    user dan laporan TIDAK akan dihapus. Aksi ini tidak dapat dibatalkan.</p>
                <div class="flex justify-center space-x-4">
                    <button onclick="hideConfirmDialog()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button onclick="proceedToReset()"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                        Reset Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Uploads Confirmation Dialog -->
    <div id="uploadsConfirmDialog"
        class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm mx-4">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-orange-600 text-4xl mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Clear Uploads Folder</h3>
                <p class="text-gray-600 mb-6">Warning: This action will delete all files in the uploads folder. This
                    action cannot be undone.</p>
                <div class="flex justify-center space-x-4">
                    <button onclick="hideUploadsConfirmDialog()"
                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button onclick="proceedToClearUploads()"
                        class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700 transition-colors">
                        Clear Uploads
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        document.getElementById('menu-toggle').addEventListener('click', function () {
            const menu = document.getElementById('sidebar-menu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('block');
        });

        // Database reset confirmation dialog functions
        function showConfirmDialog() {
            document.getElementById('confirmDialog').classList.remove('hidden');
        }

        function hideConfirmDialog() {
            document.getElementById('confirmDialog').classList.add('hidden');
        }

        function proceedToReset() {
            // Call API/Script to reset attendance data only
            window.location.href = 'reset_absensi_data.php';
        }

        // Uploads clear confirmation dialog functions
        function showUploadsConfirmDialog() {
            document.getElementById('uploadsConfirmDialog').classList.remove('hidden');
        }

        function hideUploadsConfirmDialog() {
            document.getElementById('uploadsConfirmDialog').classList.add('hidden');
        }

        function proceedToClearUploads() {
            // Reuse logic or create new script
            window.location.href = 'clear_uploads_absensi.php';
        }
    </script>
</body>

</html>
