<?php
/**
 * Unified Sidebar for Guru Role
 * Include this file in all guru pages for consistent navigation
 * 
 * Required: $db connection and $_SESSION variables must be set before including
 */

// Get current user data for sidebar
// Get current user data for sidebar
// Check for database connection from Linum context (where $db might be $laporanPdo)
if (!isset($db) && isset($laporanPdo)) {
    $db = $laporanPdo;
}

if (!isset($current_user) && isset($db)) {
    $stmt_user = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
}

// Determine current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Function to check if a menu item should be active
function isActive($page, $dir = null)
{
    global $current_page, $current_dir;
    if ($dir && $current_dir === $dir) {
        return true;
    }
    if (is_array($page)) {
        return in_array($current_page, $page);
    }
    return $current_page === $page;
}
?>
<aside class="sidebar" id="sidebar">
    <a href="#" class="sidebar-brand">LaporanApp</a>

    <!-- Profile Photo Section -->
    <div class="sidebar-profile">
        <?php
        // Determine the path prefix for profile photos
        // This logic ensures the path is correct regardless of the current script's directory depth
        $photo_path_prefix = '';
        $current_script_dir = dirname($_SERVER['PHP_SELF']);

        // If the current script is not directly in '/laporan_harian', we need to go up one level
        // or more depending on the directory structure.
        // For example, if in /laporan_harian/Journal, need '../laporan_harian/'
        // If in /laporan_harian/linum/admin, need '../../laporan_harian/'
        
        // A more robust way is to check the path parts as done for navigation links
        $current_script_path = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
        $path_parts_for_photo = explode('/', trim($current_script_path, '/'));

        // Default to relative root if in laporan_harian root
        $uploads_base_url = 'uploads/';

        if (in_array('linum', $path_parts_for_photo) && in_array('admin', $path_parts_for_photo)) {
            $uploads_base_url = '../../laporan_harian/uploads/';
        } elseif (in_array('Journal', $path_parts_for_photo)) {
            $uploads_base_url = '../laporan_harian/uploads/';
        } elseif (in_array('guru', $path_parts_for_photo)) {
            $uploads_base_url = '../uploads/';
        }
        ?>
        <?php if (!empty($current_user['profile_photo']) && file_exists(__DIR__ . '/../uploads/profile/' . $current_user['profile_photo'])): ?>
            <img src="<?= $uploads_base_url ?>profile/<?= htmlspecialchars($current_user['profile_photo']) ?>" alt="Profile"
                class="profile-photo">
        <?php else: ?>
            <div class="profile-photo-placeholder">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
        <?php endif; ?>
        <div class="profile-name"><?= htmlspecialchars($_SESSION['full_name']) ?></div>
        <div
            style="margin-top: 0.5rem; display: inline-flex; align-items: center; justify-content: center; background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; border: 1px solid #bae6fd;">
            <?= ucfirst($_SESSION['role']) ?>
            <?php if (!empty($current_user['assigned_class'])): ?>
                <span style="margin: 0 4px; opacity: 0.6;">|</span>
                <?= htmlspecialchars($current_user['assigned_class']) ?>
            <?php endif; ?>
        </div>
    </div>

    <nav>
        <?php
        // Determine relative path depth
        // laporan_harian files -> depth check not needed (same dir) or check context
        // Journal files -> ../laporan_harian/ (1 level up then down)
        // linum/admin -> ../../laporan_harian/ (2 levels up then down)
        // guru files -> ../laporan_harian/ (but included from guru folder)
        
        $path_parts = explode('/', trim(dirname($_SERVER['PHP_SELF']), '/'));
        // Example: ['laporan_harian'] or ['laporan_harian', 'guru'] or ['Journal'] or ['linum', 'admin']
        
        $in_linum_admin = in_array('linum', $path_parts) && in_array('admin', $path_parts);
        $in_journal = in_array('Journal', $path_parts);
        $in_guru_folder = in_array('guru', $path_parts);
        $in_laporan = in_array('laporan_harian', $path_parts);

        // Define base paths
        if ($in_linum_admin) {
            $base_path = '../../laporan_harian/guru/'; // 2 levels up then into guru
            $journal_path = '../../Journal/';
            $linum_path = ''; // Current dir
            $logout_path = '../logout.php'; // linum/admin -> linum/logout.php
        } elseif ($in_journal) {
            $base_path = '../laporan_harian/guru/'; // 1 level up then into guru
            $journal_path = ''; // Current dir
            $linum_path = '../linum/admin/';
            $logout_path = '../laporan_harian/logout.php'; // Journal -> laporan_harian/logout.php
        } elseif ($in_guru_folder) {
            // Files are in guru/ folder
            $base_path = ''; // Same directory (guru/)
            $journal_path = '../../Journal/';
            $linum_path = '../../linum/admin/';
            $logout_path = '../logout.php'; // guru/ -> laporan_harian/logout.php
        } else {
            // Assume in laporan_harian root (shouldn't happen for guru sidebar)
            $base_path = 'guru/';
            $journal_path = '../Journal/';
            $linum_path = '../linum/admin/';
            $logout_path = 'logout.php'; // Same directory
        }
        ?>

        <a href="<?= $base_path ?>dashboard_guru.php"
            class="nav-link <?= isActive('dashboard_guru.php') ? 'active' : '' ?>">Dashboard</a>
        <a href="<?= $base_path ?>absensi.php" class="nav-link <?= isActive('absensi.php') ? 'active' : '' ?>">Absensi
            Saya</a>
        <a href="<?= $base_path ?>laporan_baru.php"
            class="nav-link <?= isActive(['laporan_baru.php', 'laporan_detail.php']) ? 'active' : '' ?>">Buat
            Laporan</a>
        <a href="<?= $base_path ?>riwayat.php" class="nav-link <?= isActive('riwayat.php') ? 'active' : '' ?>">Riwayat
            Laporan</a>
        <a href="<?= $base_path ?>absen_siswa.php"
            class="nav-link <?= isActive('absen_siswa.php') ? 'active' : '' ?>">Absen Siswa</a>
        <a href="<?= $base_path ?>administrasi.php"
            class="nav-link <?= isActive('administrasi.php') ? 'active' : '' ?>">Administrasi Kelas</a>
        <a href="<?= $journal_path ?>index.php"
            class="nav-link <?= isActive('index.php', 'Journal') || isActive('templates.php', 'Journal') || isActive('print.php', 'Journal') ? 'active' : '' ?>">Journal
            Pembelajaran</a>
        <a href="<?= $linum_path ?>dashboard.php" class="nav-link <?= $in_linum_admin ? 'active' : '' ?>">Literasi &
            Numerasi</a>
        <a href="<?= $base_path ?>profil.php" class="nav-link <?= isActive('profil.php') ? 'active' : '' ?>">Profil
            Saya</a>
        <a href="<?= $logout_path ?>" class="nav-link" style="color: var(--danger);">Keluar</a>
    </nav>
</aside>