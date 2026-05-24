<?php
// Ensure session is started
if (!isset($_SESSION)) {
    session_start();
}
?>
<aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <i class="fas fa-book-reader me-2"></i>Linum
    </a>

    <div class="sidebar-profile">
        <?php
        $photoPath = isset($_SESSION['photo']) ? '../siswa/uploads/' . $_SESSION['photo'] : '';
        // Fix path for checking if it exists relative to this file
        $checkPath = isset($_SESSION['photo']) ? __DIR__ . '/../siswa/uploads/' . $_SESSION['photo'] : '';

        if (isset($_SESSION['photo']) && !empty($_SESSION['photo']) && file_exists($checkPath)) {
            $imgSrc = $photoPath;
        } else {
            $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['full_name'] ?? 'Admin') . '&background=4f46e5&color=fff';
        }
        ?>
        <img src="<?php echo $imgSrc; ?>" class="profile-photo" alt="Profile">
        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></div>
        <div
            style="margin-top: 0.5rem; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #bae6fd;">
            <?= ucfirst($_SESSION['role'] ?? 'admin') ?>
            <?php if (!empty($_SESSION['assigned_class'])): ?>
                <span style="margin: 0 4px; opacity: 0.6;">|</span>
                <?= htmlspecialchars($_SESSION['assigned_class']) ?>
            <?php endif; ?>
        </div>
    </div>

    <nav>
        <a href="dashboard.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="literasi_numerasi.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'literasi_numerasi.php' || basename($_SERVER['PHP_SELF']) == 'view_literasi_numerasi.php' ? 'active' : ''; ?>">
            <i class="fas fa-book-open"></i> Literasi & Numerasi
        </a>
        <a href="manage_users.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' || basename($_SERVER['PHP_SELF']) == 'edit_user.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Manajemen User
        </a>
        <a href="manage_books.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_books.php' || basename($_SERVER['PHP_SELF']) == 'edit_book.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> Manajemen Buku
        </a>
        <a href="manage_db.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_db.php' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i> Database
        </a>
        <a href="profile.php"
            class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i> Profil Saya
        </a>
        <a href="../../laporan_harian/guru/dashboard_guru.php" class="nav-link" style="color: var(--primary);">
            <i class="fas fa-arrow-left"></i> Kembali ke Laporan
        </a>
        <a href="../logout.php" class="nav-link" style="color: var(--danger);">
            <i class="fas fa-sign-out-alt"></i> Keluar
        </a>
    </nav>
</aside>