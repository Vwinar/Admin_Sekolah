<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$action = $_POST['action'] ?? '';
$msg = '';
$msgType = '';

// --- CONFIGURATION ---
// Table for Rooms
$db->exec("CREATE TABLE IF NOT EXISTS school_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    category TEXT, -- Kelas, Kantor, Lab, Penunjang, Lainnya
    area TEXT, -- Luas
    condition TEXT, -- Baik, Rusak Ringan, Rusak Berat
    pic TEXT, -- Person In Charge
    photo_path TEXT,
    notes TEXT
)");

// Table for Room Needs (Kebutuhan)
$db->exec("CREATE TABLE IF NOT EXISTS room_needs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER,
    item_name TEXT,
    quantity INTEGER,
    priority TEXT, -- Tinggi, Sedang, Rendah
    status TEXT, -- Pengajuan, Disetujui, Terealisasi
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(room_id) REFERENCES school_rooms(id) ON DELETE CASCADE
)");

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_room' || $action === 'edit_room') {
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $area = $_POST['area'] ?? '';
        $condition = $_POST['condition'] ?? '';
        $pic = $_POST['pic'] ?? '';
        $notes = $_POST['notes'] ?? '';

        $photo_path = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/rooms/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $fileName);
            $photo_path = $uploadDir . $fileName;
        } elseif ($action === 'edit_room' && isset($_POST['existing_photo'])) {
            $photo_path = $_POST['existing_photo'];
        }

        if ($action === 'add_room') {
            $stmt = $db->prepare("INSERT INTO school_rooms (name, category, area, condition, pic, photo_path, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $category, $area, $condition, $pic, $photo_path, $notes]);
            $msg = "Ruangan berhasil ditambahkan.";
        } else {
            $id = $_POST['id'];
            $stmt = $db->prepare("UPDATE school_rooms SET name=?, category=?, area=?, condition=?, pic=?, photo_path=?, notes=? WHERE id=?");
            $stmt->execute([$name, $category, $area, $condition, $pic, $photo_path, $notes, $id]);
            $msg = "Data ruangan berhasil diperbarui.";
        }
        $msgType = 'success';
    } elseif ($action === 'delete_room') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM school_rooms WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Ruangan berhasil dihapus.";
        $msgType = 'success';
    } elseif ($action === 'add_need') {
        $room_id = $_POST['room_id'];
        $item_name = $_POST['item_name'];
        $quantity = $_POST['quantity'];
        $priority = $_POST['priority'];
        $notes = $_POST['notes'];

        $stmt = $db->prepare("INSERT INTO room_needs (room_id, item_name, quantity, priority, status, notes) VALUES (?, ?, ?, ?, 'Pengajuan', ?)");
        $stmt->execute([$room_id, $item_name, $quantity, $priority, $notes]);
        $msg = "Kebutuhan berhasil ditambahkan.";
        $msgType = 'success';
    } elseif ($action === 'delete_need') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM room_needs WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Kebutuhan dihapus.";
        $msgType = 'success';
    } elseif ($action === 'upload_doc') {
        // Handle Master Plan Documents
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            $uploadDir = '../uploads/administrasi/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $fileName = time() . '_' . basename($_FILES['file_upload']['name']);
            move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadDir . $fileName);

            $stmt = $db->prepare("INSERT INTO school_documents (type, title, description, file_path) VALUES ('master_plan', ?, ?, ?)");
            $stmt->execute([$title, $desc, $uploadDir . $fileName]);
            $msg = "Dokumen berhasil diunggah.";
            $msgType = 'success';
        }
    } elseif ($action === 'delete_doc') {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM school_documents WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Dokumen dihapus.";
        $msgType = 'success';
    }
}

// Fetch Users for PIC
$users = $db->query("SELECT id, full_name, role FROM users WHERE role IN ('admin', 'guru') ORDER BY full_name ASC")->fetchAll();

// Fetch Data
$rooms = $db->query("SELECT * FROM school_rooms ORDER BY category, name")->fetchAll();
$documents = $db->query("SELECT * FROM school_documents WHERE type = 'master_plan' ORDER BY id DESC")->fetchAll();

// Get Room Detail if requested
$detail_room = null;
$room_inventory = [];
$room_maintenance = [];
$room_needs = [];
if (isset($_GET['room_id'])) {
    $roomId = $_GET['room_id'];
    $stmt = $db->prepare("SELECT * FROM school_rooms WHERE id = ?");
    $stmt->execute([$roomId]);
    $detail_room = $stmt->fetch();

    if ($detail_room) {
        // Fetch Inventory (Matching Location Name)
        $stmtInv = $db->prepare("SELECT * FROM school_inventory WHERE location LIKE ? OR location = ?");
        $stmtInv->execute(["%{$detail_room['name']}%", $detail_room['name']]);
        $room_inventory = $stmtInv->fetchAll();

        // Fetch Maintenance (Matching Details/Subject)
        $stmtMaint = $db->prepare("SELECT * FROM school_logs WHERE type='pemeliharaan' AND (subject LIKE ? OR details LIKE ?) ORDER BY date DESC");
        $stmtMaint->execute(["%{$detail_room['name']}%", "%{$detail_room['name']}%"]);
        $room_maintenance = $stmtMaint->fetchAll();

        // Fetch Needs
        $stmtNeeds = $db->prepare("SELECT * FROM room_needs WHERE room_id = ? ORDER BY created_at DESC");
        $stmtNeeds->execute([$roomId]);
        $room_needs = $stmtNeeds->fetchAll();
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Plan & Ruangan Sekolah</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .nav-tabs {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #ddd;
            margin-bottom: 1.5rem;
        }

        .nav-tab {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }

        .nav-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .room-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: transform 0.2s;
            cursor: pointer;
        }

        .room-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .room-img {
            height: 160px;
            background: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .room-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .room-info {
            padding: 1rem;
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .bg-success-light {
            background: #d1fae5;
            color: #065f46;
        }

        .bg-warning-light {
            background: #fef3c7;
            color: #92400e;
        }

        .bg-danger-light {
            background: #fee2e2;
            color: #991b1b;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-muted);
        }
    </style>
</head>

<body>
    <div class="dashboard-layout">
        <?php include '../layout/admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <span></span><span></span><span></span>
                    </button>
                    <div>
                        <h1>Master Plan & Sarpras</h1>
                        <p style="color: var(--text-muted)">Pengelolaan Ruangan, Denah, dan Kebutuhan Sarpras.</p>
                    </div>
                </div>
            </header>

            <div class="card">
                <div style="margin-bottom: 1rem;">
                    <a href="admin_administrasi.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i>
                        Kembali</a>
                </div>

                <?php if ($msg): ?>
                    <div
                        style="background: <?= $msgType === 'success' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $msgType === 'success' ? '#065f46' : '#991b1b' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <?= $msg ?>
                    </div>
                <?php endif; ?>

                <div class="nav-tabs">
                    <div class="nav-tab <?= !isset($_GET['room_id']) ? 'active' : '' ?>" onclick="switchTab('rooms')">
                        Daftar Ruangan</div>
                    <div class="nav-tab" onclick="switchTab('documents')">Denah & Dokumen</div>
                </div>

                <!-- ROOMS TAB -->
                <div id="tab-rooms" style="<?= isset($_GET['room_id']) ? 'display:none;' : '' ?>">
                    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
                        <button class="btn btn-primary" onclick="openRoomModal('add')"><i class="bi bi-plus-lg"></i>
                            Tambah Ruangan</button>
                    </div>

                    <div class="room-grid">
                        <?php foreach ($rooms as $room): ?>
                            <div class="room-card" onclick="window.location.href='?room_id=<?= $room['id'] ?>'">
                                <div class="room-img">
                                    <?php if ($room['photo_path']): ?>
                                        <img src="<?= htmlspecialchars($room['photo_path']) ?>" alt="Foto Ruangan">
                                    <?php else: ?>
                                        <i class="bi bi-building" style="font-size: 3rem; color: #ccc;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="room-info">
                                    <h3 style="margin:0 0 0.5rem; font-size:1.1rem; font-weight:700;">
                                        <?= htmlspecialchars($room['name']) ?>
                                    </h3>
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span
                                            style="font-size:0.9rem; color:var(--text-muted);"><?= htmlspecialchars($room['category']) ?></span>
                                        <?php
                                        $badgeClass = 'bg-success-light';
                                        if ($room['condition'] == 'Rusak Ringan')
                                            $badgeClass = 'bg-warning-light';
                                        if ($room['condition'] == 'Rusak Berat')
                                            $badgeClass = 'bg-danger-light';
                                        ?>
                                        <span
                                            class="badge <?= $badgeClass ?>"><?= htmlspecialchars($room['condition']) ?></span>
                                    </div>
                                    <p style="font-size:0.8rem; color:var(--text-muted); margin-top:0.5rem;">
                                        <i class="bi bi-person"></i> PIC: <?= htmlspecialchars($room['pic'] ?: '-') ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- DOCUMENTS TAB -->
                <div id="tab-documents" style="display:none;">
                    <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
                        <button class="btn btn-primary" onclick="openDocModal()"><i class="bi bi-upload"></i> Upload
                            Master Plan</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul Dokumen</th>
                                <th>Deskripsi</th>
                                <th>File</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $i => $doc): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($doc['title']) ?></td>
                                    <td><?= htmlspecialchars($doc['description']) ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank"
                                            class="btn btn-sm btn-outline-primary">Lihat</a>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Hapus dokumen?');">
                                            <input type="hidden" name="action" value="delete_doc">
                                            <input type="hidden" name="id" value="<?= $doc['id'] ?>">
                                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- ROOM DETAIL VIEW -->
                <?php if ($detail_room): ?>
                    <div id="room-detail">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid #eee; padding-bottom:1rem;">
                            <h2><?= htmlspecialchars($detail_room['name']) ?></h2>
                            <button class="btn btn-secondary" onclick="window.location.href='admin_master_plan.php'">Tutup
                                Detail</button>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                            <!-- Left: Info -->
                            <div>
                                <div class="room-img" style="border-radius:12px; margin-bottom:1rem; height:200px;">
                                    <?php if ($detail_room['photo_path']): ?>
                                        <img src="<?= htmlspecialchars($detail_room['photo_path']) ?>" alt="Foto Ruangan">
                                    <?php else: ?>
                                        <i class="bi bi-building" style="font-size: 4rem; color: #ccc;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="card" style="background:#f9fafb; border:none;">
                                    <div class="info-row"><span class="info-label">Kategori</span>
                                        <span><?= htmlspecialchars($detail_room['category']) ?></span>
                                    </div>
                                    <div class="info-row"><span class="info-label">Luas</span>
                                        <span><?= htmlspecialchars($detail_room['area']) ?> m²</span>
                                    </div>
                                    <div class="info-row"><span class="info-label">Kondisi</span> <span
                                            class="badge <?= $badgeClass ?>"><?= htmlspecialchars($detail_room['condition']) ?></span>
                                    </div>
                                    <div class="info-row"><span class="info-label">PIC</span>
                                        <span><?= htmlspecialchars($detail_room['pic']) ?></span>
                                    </div>
                                    <div style="margin-top:1rem;">
                                        <strong>Catatan:</strong><br>
                                        <p style="font-size:0.9rem; color:#666;">
                                            <?= nl2br(htmlspecialchars($detail_room['notes'])) ?>
                                        </p>
                                    </div>
                                    <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                                        <button class="btn btn-sm btn-secondary"
                                            onclick='openRoomModal("edit", <?= json_encode($detail_room) ?>)'
                                            style="width:100%;">Edit Info</button>
                                        <form method="POST" onsubmit="return confirm('Hapus ruangan ini?');"
                                            style="width:100%;">
                                            <input type="hidden" name="action" value="delete_room">
                                            <input type="hidden" name="id" value="<?= $detail_room['id'] ?>">
                                            <button class="btn btn-sm btn-danger" style="width:100%;">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Related Data -->
                            <div>
                                <!-- Needs / Kebutuhan -->
                                <div style="margin-bottom: 2rem;">
                                    <div class="section-header">
                                        <h3><i class="bi bi-cart"></i> Yang Dibutuhkan / Pengajuan</h3>
                                        <button class="btn btn-sm btn-primary" onclick="openNeedModal()"><i
                                                class="bi bi-plus"></i> Tambah</button>
                                    </div>
                                    <?php if (empty($room_needs)): ?>
                                        <p class="text-muted">Tidak ada data kebutuhan.</p>
                                    <?php else: ?>
                                        <table style="width:100%; font-size:0.9rem;">
                                            <thead style="background:#f3f4f6;">
                                                <tr>
                                                    <th>Barang/Kebutuhan</th>
                                                    <th>Jumlah</th>
                                                    <th>Prioritas</th>
                                                    <th>Status</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($room_needs as $need): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($need['item_name']) ?></td>
                                                        <td><?= $need['quantity'] ?></td>
                                                        <td>
                                                            <span
                                                                class="badge <?= $need['priority'] == 'Tinggi' ? 'bg-danger-light' : 'bg-success-light' ?>">
                                                                <?= $need['priority'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $need['status'] ?></td>
                                                        <td>
                                                            <form method="POST" style="margin:0;"
                                                                onsubmit="return confirm('Hapus?');">
                                                                <input type="hidden" name="action" value="delete_need">
                                                                <input type="hidden" name="id" value="<?= $need['id'] ?>">
                                                                <input type="hidden" name="room_id" value="<?= $roomId ?>">
                                                                <!-- Maintain view -->
                                                                <button class="btn btn-sm btn-outline-danger"
                                                                    style="padding:0.2rem 0.5rem;">x</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Inventory -->
                                <div style="margin-bottom: 2rem;">
                                    <div class="section-header">
                                        <h3><i class="bi bi-box-seam"></i> Inventaris (Terkoneksi)</h3>
                                        <a href="admin_administrasi_managedata.php?type=inventaris"
                                            class="btn btn-sm btn-outline-primary">Kelola Inventaris</a>
                                    </div>
                                    <?php if (empty($room_inventory)): ?>
                                        <p class="text-muted">Tidak ada barang inventaris tercatat di lokasi ini (nama lokasi:
                                            "<?= htmlspecialchars($detail_room['name']) ?>").</p>
                                    <?php else: ?>
                                        <ul>
                                            <?php foreach ($room_inventory as $inv): ?>
                                                <li><?= htmlspecialchars($inv['item_name']) ?> (<?= $inv['quantity'] ?>
                                                    <?= htmlspecialchars($inv['condition']) ?>)
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>

                                <!-- Maintenance -->
                                <div>
                                    <div class="section-header">
                                        <h3><i class="bi bi-tools"></i> Riwayat Pemeliharaan</h3>
                                    </div>
                                    <?php if (empty($room_maintenance)): ?>
                                        <p class="text-muted">Tidak ada riwayat pemeliharaan.</p>
                                    <?php else: ?>
                                        <?php foreach ($room_maintenance as $log): ?>
                                            <div
                                                style="border-left: 3px solid var(--primary); padding-left: 1rem; margin-bottom: 1rem;">
                                                <small class="text-muted"><?= htmlspecialchars($log['date']) ?></small>
                                                <div style="font-weight:600;"><?= htmlspecialchars($log['subject']) ?></div>
                                                <p style="font-size:0.9rem; margin:0;"><?= htmlspecialchars($log['details']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- ROOM MODAL -->
    <div id="roomModal" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div class="modal-content"
            style="background:white; margin:2rem auto; padding:2rem; width:90%; max-width:600px; border-radius:12px;">
            <h3 id="roomModalTitle">Tambah Ruangan</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="roomAction" value="add_room">
                <input type="hidden" name="id" id="roomId">
                <div class="form-group">
                    <label class="form-label">Nama Ruangan</label>
                    <input type="text" name="name" id="roomName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="roomCategory" class="form-control">
                        <option value="Ruang Kelas">Ruang Kelas</option>
                        <option value="Kantor/Guru">Kantor/Guru</option>
                        <option value="Laboratorium">Laboratorium</option>
                        <option value="Perpustakaan">Perpustakaan</option>
                        <option value="Fasilitas Umum">Fasilitas Umum (Toilet/Musholla)</option>
                        <option value="Gudang">Gudang</option>
                        <option value="Lainnya">Lainnya</option>
                    </select>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Luas (m²)</label>
                        <input type="text" name="area" id="roomArea" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kondisi</label>
                        <select name="condition" id="roomCondition" class="form-control">
                            <option value="Baik">Baik</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                            <option value="Rusak Berat">Rusak Berat</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Penanggung Jawab (PIC)</label>
                    <select name="pic" id="roomPic" class="form-control">
                        <option value="">-- Pilih Penanggung Jawab --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['full_name']) ?>">
                                <?= htmlspecialchars($u['full_name']) ?> (<?= ucfirst($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Foto Ruangan</label>
                    <input type="file" name="photo" class="form-control">
                    <input type="hidden" name="existing_photo" id="existingPhoto">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" id="roomNotes" class="form-control" rows="3"></textarea>
                </div>
                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('roomModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- NEED MODAL -->
    <div id="needModal" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div class="modal-content"
            style="background:white; margin:5rem auto; padding:2rem; width:90%; max-width:500px; border-radius:12px;">
            <h3>Tambah Kebutuhan</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_need">
                <input type="hidden" name="room_id" value="<?= $roomId ?? '' ?>">
                <div class="form-group">
                    <label class="form-label">Nama Barang / Perbaikan</label>
                    <input type="text" name="item_name" class="form-control" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="quantity" class="form-control" value="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prioritas</label>
                        <select name="priority" class="form-control">
                            <option value="Rendah">Rendah</option>
                            <option value="Sedang">Sedang</option>
                            <option value="Tinggi">Tinggi</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('needModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DOC MODAL -->
    <div id="docModal" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
        <div class="modal-content"
            style="background:white; margin:5rem auto; padding:2rem; width:90%; max-width:500px; border-radius:12px;">
            <h3>Upload Master Plan</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_doc">
                <div class="form-group">
                    <label class="form-label">Judul Dokumen</label>
                    <input type="text" name="title" class="form-control" required placeholder="Misal: Denah Lantai 1">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">File (PDF/Gambar)</label>
                    <input type="file" name="file_upload" class="form-control" required>
                </div>
                <div style="margin-top:1.5rem; text-align:right;">
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('docModal').style.display='none'">Batal</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.getElementById('tab-rooms').style.display = 'none';
            document.getElementById('tab-documents').style.display = 'none';
            document.querySelectorAll('.nav-tab').forEach(el => el.classList.remove('active'));

            document.getElementById('tab-' + tabName).style.display = 'block';
            event.target.classList.add('active');
        }

        function openRoomModal(mode, data = null) {
            document.getElementById('roomModal').style.display = 'block';
            if (mode === 'add') {
                document.getElementById('roomModalTitle').innerText = 'Tambah Ruangan';
                document.getElementById('roomAction').value = 'add_room';
                document.getElementById('roomId').value = '';
                document.getElementById('roomName').value = '';
                document.getElementById('roomPic').value = '';
                document.getElementById('roomNotes').value = '';
            } else {
                document.getElementById('roomModalTitle').innerText = 'Edit Ruangan';
                document.getElementById('roomAction').value = 'edit_room';
                document.getElementById('roomId').value = data.id;
                document.getElementById('roomName').value = data.name;
                document.getElementById('roomCategory').value = data.category;
                document.getElementById('roomArea').value = data.area;
                document.getElementById('roomCondition').value = data.condition;
                document.getElementById('roomPic').value = data.pic;
                document.getElementById('roomNotes').value = data.notes;
                document.getElementById('existingPhoto').value = data.photo_path;
            }
        }

        function openNeedModal() {
            document.getElementById('needModal').style.display = 'block';
        }

        function openDocModal() {
            document.getElementById('docModal').style.display = 'block';
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }

        <?php if (!isset($_GET['room_id'])): ?>
            // Default select Rooms tab
            // Already handled by HTML logic
        <?php endif; ?>
    </script>
</body>

</html>
