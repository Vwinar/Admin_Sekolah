<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$db = new SQLite3('absen.db');

$error = '';
$success = '';


$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

$fullname = '';
if ($user_id !== null) {
    $user_stmt = $db->prepare('SELECT * FROM users WHERE id = :user_id');
    $user_stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $user_result = $user_stmt->execute();
    $user_row = $user_result->fetchArray(SQLITE3_ASSOC);
    if ($user_row) {
        // Check for full name column "full_name"
        if (!empty($user_row['full_name'])) {
            $fullname = $user_row['full_name'];
        } else {
            // Fallback to formatted username
            $username_raw = $user_row['username'] ?? '';
            $fullname = ucwords(str_replace('_', ' ', $username_raw));
        }
    } else {
        $fullname = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
    }
} else {
    $fullname = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
}
//



// Handle delete user
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt_delete = $db->prepare('DELETE FROM users WHERE id = :id');
    $stmt_delete->bindValue(':id', $delete_id, SQLITE3_INTEGER);
    $result_delete = $stmt_delete->execute();
    if ($result_delete) {
        $success = 'User deleted successfully.';
    } else {
        $error = 'Failed to delete user.';
    }
}

// Handle add or edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $nip = trim($_POST['nip'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'guru';

    if ($full_name === '' || $username === '' || $role === '' || ($id === '' && $password === '') || $nip === '') {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if username already exists (for new or different user)
        $stmt_check = $db->prepare('SELECT id FROM users WHERE username = :username');
        $stmt_check->bindValue(':username', $username, SQLITE3_TEXT);
        $result_check = $stmt_check->execute();
        $existing_user = $result_check->fetchArray(SQLITE3_ASSOC);

        if ($existing_user && $existing_user['id'] != $id) {
            $error = 'Username already exists.';
        } else {
            if ($id === '') {
                // Add new user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_insert = $db->prepare('INSERT INTO users (full_name, username, nip, password_hash, role) VALUES (:full_name, :username, :nip, :password_hash, :role)');
                $stmt_insert->bindValue(':full_name', $full_name, SQLITE3_TEXT);
                $stmt_insert->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt_insert->bindValue(':nip', $nip, SQLITE3_TEXT);
                $stmt_insert->bindValue(':password_hash', $password_hash, SQLITE3_TEXT);
                $stmt_insert->bindValue(':role', $role, SQLITE3_TEXT);
                $result_insert = $stmt_insert->execute();

                if ($result_insert) {
                    $success = ucfirst($role) . ' added successfully.';
                } else {
                    $error = 'Failed to add user.';
                }
            } else {
                // Edit existing user
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_update = $db->prepare('UPDATE users SET full_name = :full_name, username = :username, nip = :nip, password_hash = :password_hash, role = :role WHERE id = :id');
                    $stmt_update->bindValue(':password_hash', $password_hash, SQLITE3_TEXT);
                } else {
                    $stmt_update = $db->prepare('UPDATE users SET full_name = :full_name, username = :username, nip = :nip, role = :role WHERE id = :id');
                }
                $stmt_update->bindValue(':full_name', $full_name, SQLITE3_TEXT);
                $stmt_update->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt_update->bindValue(':nip', $nip, SQLITE3_TEXT);
                $stmt_update->bindValue(':role', $role, SQLITE3_TEXT);
                $stmt_update->bindValue(':id', $id, SQLITE3_INTEGER);
                $result_update = $stmt_update->execute();

                if ($result_update) {
                    $success = ucfirst($role) . ' updated successfully.';
                } else {
                    $error = 'Failed to update user.';
                }
            }
        }
    }
}

// Fetch all users
$users = [];
$result = $db->query('SELECT id, full_name, username, nip, role FROM users ORDER BY id ASC');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

// If editing, fetch user data
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt_edit = $db->prepare('SELECT id, full_name, username, nip, role FROM users WHERE id = :id');
    $stmt_edit->bindValue(':id', $edit_id, SQLITE3_INTEGER);
    $res_edit = $stmt_edit->execute();
    $edit_user = $res_edit->fetchArray(SQLITE3_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Users - Absen App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
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
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        .input-field {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .input-field:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            min-width: 640px;
        }
        
        .toast {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
    </style>
</head>
<body class="min-h-screen p-4 md:p-6">
    <div class="card w-full max-w-6xl mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Manage Users, <?= htmlspecialchars($fullname) ?> </h1>
              	
                <p class="text-gray-600">Add, edit or remove system users</p>
            </div>
            <a href="admin_home.php" class="mt-4 md:mt-0 inline-flex items-center justify-center bg-gray-800 text-white py-2 px-4 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
        </div>

        <?php if ($error): ?>
            <div class="toast mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="toast mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <p><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800"><?= $edit_user ? 'Edit User' : 'Add New User' ?></h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="id" value="<?= $edit_user['id'] ?? '' ?>" />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="full_name">Full Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($edit_user['full_name'] ?? '') ?>" 
                                   placeholder="Full name" class="input-field w-full pl-10 pr-3 py-2 border border-gray-300" required />
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="username">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-at text-gray-400"></i>
                            </div>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" 
                                   placeholder="Username" class="input-field w-full pl-10 pr-3 py-2 border border-gray-300" required />
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="nip">NIP</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-id-card text-gray-400"></i>
                            </div>
                            <input type="text" id="nip" name="nip" value="<?= htmlspecialchars($edit_user['nip'] ?? '') ?>" 
                                   placeholder="NIP" class="input-field w-full pl-10 pr-3 py-2 border border-gray-300" required />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="role">Role</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user-tag text-gray-400"></i>
                            </div>
                            <select id="role" name="role" class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 appearance-none" required>
                                <option value="guru" <?= (isset($edit_user['role']) && $edit_user['role'] === 'guru') ? 'selected' : '' ?>>Guru</option>
                                <option value="admin" <?= (isset($edit_user['role']) && $edit_user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" for="password">
                            Password <?= $edit_user ? '<span class="text-gray-500 text-xs">(leave blank to keep current)</span>' : '' ?>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input type="password" id="password" name="password" placeholder="Password" 
                                   class="input-field w-full pl-10 pr-3 py-2 border border-gray-300" <?= $edit_user ? '' : 'required' ?> />
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4 pt-2">
                    <button type="submit" class="btn-primary text-white py-2.5 px-6 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i><?= $edit_user ? 'Update User' : 'Add User' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="manage_guru.php" class="text-gray-600 hover:text-gray-800 hover:underline">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800">User List</h2>
                <div class="mt-2 md:mt-0 text-sm text-gray-500">
                    Total: <?= count($users) ?> user(s)
                </div>
            </div>
            
            <?php if (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                            <th class="p-3 font-medium text-gray-700 rounded-tl-lg">No</th>
                            <th class="p-3 font-medium text-gray-700">Full Name</th>
                            <th class="p-3 font-medium text-gray-700">Username</th>
                            <th class="p-3 font-medium text-gray-700">NIP</th>
                            <th class="p-3 font-medium text-gray-700">Role</th>
                            <th class="p-3 font-medium text-gray-700 rounded-tr-lg">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                            <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-gray-100">
                                <td class="p-3 border-b border-gray-200"><?= $index + 1 ?></td>
                                <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($user['full_name']) ?></td>
                                <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="p-3 border-b border-gray-200"><?= htmlspecialchars($user['nip']) ?></td>
                                <td class="p-3 border-b border-gray-200">
                                    <span class="inline-block px-2 py-1 text-xs rounded-full 
                                        <?= $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' ?>">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td class="p-3 border-b border-gray-200 action-buttons">
                                        <a href="manage_guru.php?edit=<?= $user['id'] ?>" 
                                           class="inline-flex items-center text-blue-600 hover:text-blue-800 mr-4">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                        <button 
                                           class="inline-flex items-center btn-danger text-white py-1 px-3 rounded text-sm"
                                           onclick="showDeleteConfirmation(<?= $user['id'] ?>)">
                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-users-slash text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No users found. Add your first user above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-hide toast messages after 5 seconds
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast');
            toasts.forEach(toast => {
                toast.style.opacity = '0';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
    <script>
        function showDeleteConfirmation(userId) {
            const confirmation = confirm('Are you sure you want to delete this user?');
            if (confirmation) {
                window.location.href = 'manage_guru.php?delete=' + userId;
            }
        }
    </script>
</body>
</html>
