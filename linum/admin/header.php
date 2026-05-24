<?php
if (!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Panel'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="theme-color" content="#4f46e5" />

    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />

    <!-- Custom Layout CSS -->
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">

    <style>
        /* overrides for bootstrap conflicts if any */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <div class="overflow-hidden">
                        <h1 class="h4 mb-0 text-truncate">
                            <?php echo isset($pageTitle) ? explode(' - ', $pageTitle)[0] : 'Admin Dashboard'; ?>
                        </h1>
                        <p class="mb-0 text-muted small text-truncate">Sistem Literasi & Numerasi</p>
                    </div>
                </div>
                <?php if (isset($headerAction))
                    echo $headerAction; ?>
            </header>

            <!-- Content begins here -->