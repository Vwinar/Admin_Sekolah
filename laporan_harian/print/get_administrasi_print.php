<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$type = $_GET['type'] ?? '';
$types_map = [
    'ktsp' => 'Dokumen KTSP',
    'admin_guru' => 'Administrasi Guru',
    'pemetaan_sk_kd' => 'Pemetaan SK-KD',
    'legger' => 'Legger Nilai'
];

if (!array_key_exists($type, $types_map)) {
    die("Tipe laporan tidak valid.");
}

$title = $types_map[$type];

// Fetch School Settings
$stmt_settings = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt_settings->fetch();
$school_name = $settings['school_name'] ?? 'SEKOLAH DASAR';
$school_address = $settings['address'] ?? 'Alamat Sekolah'; // Assumes address column exists or use default
$school_logo = $settings['school_logo'] ?? ''; // URL/Path to logo

// Fetch Principal Info (Admin)
// Assuming the current logged in admin is the Principal or we fetch specific role
// Request says: "diambilkan dari data nama dan nip admin" (taken from admin name and nip)
$admin_id = $_SESSION['user_id'];
$stmt_admin = $db->prepare("SELECT full_name, nip FROM users WHERE id = ?");
$stmt_admin->execute([$admin_id]);
$admin = $stmt_admin->fetch();
$kepsek_name = $admin['full_name'] ?? '.........................';
$kepsek_nip = $admin['nip'] ?? '-';

// Fetch Data
// Configuration for fields to display matches admin_administrasi_managedata.php
$fields = [];
if ($type === 'ktsp') {
    $fields = ['title' => 'Komponen Dokumen', 'description' => 'Keterangan'];
} elseif ($type === 'admin_guru') {
    $fields = ['title' => 'Komponen Dokumen', 'class_name' => 'Kelas', 'related_user_id' => 'Guru Kelas', 'description' => 'Keterangan'];
} elseif ($type === 'pemetaan_sk_kd') {
    $fields = ['title' => 'Komponen Dokumen', 'class_name' => 'Kelas', 'related_user_id' => 'Guru Kelas', 'description' => 'Keterangan'];
} elseif ($type === 'legger') {
    $fields = ['title' => 'Tahun Ajaran', 'category' => 'Jenis Data', 'semester' => 'Semester', 'class_name' => 'Kelas', 'related_user_id' => 'Guru Kelas', 'description' => 'Keterangan'];
}

// Helper to fetch teacher name
function getTeacherName($db, $id)
{
    if (!$id)
        return '-';
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() ?: '-';
}

$query = "SELECT * FROM school_documents WHERE type = ? ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute([$type]);
$rows = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Cetak <?= $title ?></title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px double black;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header p {
            margin: 2px 0;
            font-size: 12pt;
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
            text-decoration: underline;
            font-size: 14pt;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
        }

        .signature {
            margin-top: 50px;
            float: right;
            width: 250px;
            text-align: left;
        }

        .signature p {
            margin: 0;
        }

        .signature .name {
            margin-top: 70px;
            font-weight: bold;
            text-decoration: underline;
        }

        @media print {
            @page {
                size: A4;
                margin: 2cm;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">
    <div class="header">
        <h1><?= htmlspecialchars($school_name) ?></h1>
        <p><?= htmlspecialchars($school_address) ?></p>
    </div>

    <div class="title">LAPORAN DATA <?= strtoupper($title) ?></div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <?php foreach ($fields as $key => $label): ?>
                    <th><?= $label ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= count($fields) + 1 ?>" style="text-align: center;">Tidak ada data.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                    <tr>
                        <td style="text-align: center;"><?= $i + 1 ?></td>
                        <?php foreach ($fields as $key => $label): ?>
                            <td>
                                <?php
                                if ($key === 'related_user_id') {
                                    echo htmlspecialchars(getTeacherName($db, $row[$key]));
                                } else {
                                    echo htmlspecialchars($row[$key]);
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="signature">
        <p>Mengetahui,</p>
        <p>Kepala Sekolah</p>
        <div class="name"><?= htmlspecialchars($kepsek_name) ?></div>
        <p>NIP. <?= htmlspecialchars($kepsek_nip) ?></p>
    </div>
</body>

</html>