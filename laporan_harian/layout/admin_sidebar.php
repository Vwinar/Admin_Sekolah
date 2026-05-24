<?php
// Admin Sidebar Component
// Include this file in all admin pages to ensure uniform sidebar
?>
<aside class="sidebar" id="sidebar">
    <a href="dashboard_admin.php" class="sidebar-brand">
        <span style="font-size: 1.25rem; font-weight: 700;">Kepala Sekolah</span>
    </a>

    <?php
    // Fetch user profile info for sidebar
    $current_page = basename($_SERVER['PHP_SELF']);

    // Get profile photo if exists
    $user_photo = $_SESSION['profile_photo'] ?? '';
    $user_name = $_SESSION['full_name'] ?? 'Admin';

    // Determine profile image
    $profile_img_path = '';
    if (!empty($user_photo) && file_exists(__DIR__ . '/../uploads/profile/' . $user_photo)) {
        $profile_img_path = '../uploads/profile/' . $user_photo;
    }
    ?>

    <!-- Profile Section -->
    <div class="sidebar-profile">
        <?php if ($profile_img_path): ?>
            <img src="<?= $profile_img_path ?>" alt="Profile" class="sidebar-profile-img">
        <?php else: ?>
            <div class="sidebar-profile-avatar">
                <?= strtoupper(substr($user_name, 0, 2)) ?>
            </div>
        <?php endif; ?>
        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="sidebar-profile-role">Kepala Sekolah</div>
        </div>
    </div>

    <nav>
        <a href="dashboard_admin.php" class="nav-link <?= $current_page === 'dashboard_admin.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4zM3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707zM2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10zm9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5zm.754-4.246a.389.389 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.389.389 0 0 0-.029-.518z" />
                <path fill-rule="evenodd"
                    d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 1.602-2.932 1.25C11.309 13.488 9.475 13 8 13c-1.474 0-3.31.488-4.615.911-1.087.352-2.49.003-2.932-1.25A7.988 7.988 0 0 1 0 10zm8-7a7 7 0 0 0-6.603 9.329c.203.575.923.876 1.68.63C4.397 12.533 6.358 12 8 12s3.604.532 4.923.96c.757.245 1.477-.056 1.68-.631A7 7 0 0 0 8 3z" />
            </svg>
            <span>Dashboard</span>
        </a>

        <a href="monitoring.php" class="nav-link <?= $current_page === 'monitoring.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z" />
            </svg>
            <span>Monitoring Laporan</span>
        </a>

        <a href="rekap_absensi.php" class="nav-link <?= $current_page === 'rekap_absensi.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2zm0 4a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2zm0 4a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2z" />
            </svg>
            <span>Rekap Absensi</span>
        </a>

        <a href="users.php" class="nav-link <?= $current_page === 'users.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8Zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022ZM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0ZM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816ZM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0Zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" />
            </svg>
            <span>Manajemen User</span>
        </a>

        <a href="admin_settings.php" class="nav-link <?= $current_page === 'admin_settings.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z" />
            </svg>
            <span>Data Master</span>
        </a>

        <a href="admin_administrasi.php"
            class="nav-link <?= $current_page === 'admin_administrasi.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zM2.5 2a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zM1 10.5A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3zm6.5.5A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3zm1.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5h-3z" />
            </svg>
            <span>Administrasi Sekolah</span>
        </a>

        <a href="laporan.php" class="nav-link <?= $current_page === 'laporan.php' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M4 11H2v3h2v-3zm5-4H7v7h2V7zm5-5v12h-2V2h2zm-2-1a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1h-2zM6 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm-5 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3z" />
            </svg>
            <span>Rekap & Analitik</span>
        </a>

        <div class="nav-divider"></div>

        <a href="../logout.php" class="nav-link nav-link-danger">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd"
                    d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z" />
                <path fill-rule="evenodd"
                    d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z" />
            </svg>
            <span>Keluar</span>
        </a>
    </nav>
</aside>

<style>
    /* Enhanced Sidebar Styles with Proper Background */
    .sidebar {
        background: linear-gradient(180deg, #114e17ff 0%, #aaddb5ff 50%, #3ba77dff 100%);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar-brand {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        padding: 1.25rem 1rem;
        margin-bottom: 0.5rem;
        color: white !important;
        text-decoration: none;
        display: block;
        border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        text-align: center;
    }

    .sidebar-brand span {
        color: white !important;
    }

    .sidebar-brand:hover {
        background: rgba(255, 255, 255, 0.15);
        color: white !important;
    }

    .sidebar-profile {
        padding: 1.5rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-profile-img {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .sidebar-profile-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        color: white;
        border: 3px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .sidebar-profile-info {
        flex: 1;
        min-width: 0;
    }

    .sidebar-profile-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: white;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .sidebar-profile-role {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.85);
        margin-top: 0.125rem;
        font-weight: 500;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        transition: all 0.2s ease;
        border-left: 3px solid transparent;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0.25rem 0;
    }

    .nav-link svg {
        flex-shrink: 0;
        opacity: 0.9;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border-left-color: #fbbf24;
        padding-left: 1.25rem;
    }

    .nav-link:hover svg {
        opacity: 1;
        transform: scale(1.1);
    }

    .nav-link.active {
        background: rgba(255, 255, 255, 0.25);
        color: white;
        border-left-color: #fbbf24;
        font-weight: 600;
        box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
    }

    .nav-link.active svg {
        opacity: 1;
    }

    .nav-link-danger {
        color: #fca5a5;
    }

    .nav-link-danger:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #fef2f2;
        border-left-color: #ef4444;
    }

    .nav-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.2);
        margin: 0.75rem 0.5rem;
    }

    /* Collapsed sidebar styles */
    .sidebar-collapsed .sidebar-profile-info,
    .sidebar-collapsed .nav-link span {
        display: none;
    }

    .sidebar-collapsed .sidebar-profile {
        justify-content: center;
        padding: 1rem;
    }

    .sidebar-collapsed .nav-link {
        justify-content: center;
        padding: 0.875rem;
    }

    .sidebar-collapsed .nav-link:hover {
        padding-left: 0.875rem;
    }

    @media (max-width: 768px) {
        .sidebar-profile-name {
            font-size: 0.85rem;
        }

        .sidebar-profile-role {
            font-size: 0.7rem;
        }

        .nav-link {
            font-size: 0.85rem;
            padding: 0.75rem 1rem;
        }
    }
</style>