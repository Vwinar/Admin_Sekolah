<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$year = $_GET['year'] ?? date('Y');

// Fetch Data: type = 'lpj', joined with users to get officer name
$query = "SELECT d.*, u.full_name as officer_name, u.nip as officer_nip 
          FROM school_documents d 
          LEFT JOIN users u ON d.related_user_id = u.id
          WHERE d.type = 'lpj' 
          AND strftime('%Y', d.uploaded_at) = ? 
          ORDER BY d.uploaded_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$year]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch School Settings
$querySettings = "SELECT * FROM settings LIMIT 1";
$stmtSettings = $db->query($querySettings);
$settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);

$schoolName = $settings['school_name'] ?? 'SEKOLAH CONTOH';
$schoolAddress = $settings['address'] ?? "Alamat Sekolah Belum Diatur";
$schoolCity = $settings['school_city'] ?? 'Kota';

// --- LOGIC: EXTRACT CITY FROM SCHOOL NAME (INDEX 2) & FORMAT ---
// Example: "SD Negeri Warukulon" -> "Warukulon"
$schoolNameParts = explode(' ', $schoolName);
$rawCity = isset($schoolNameParts[2]) ? $schoolNameParts[2] : $schoolCity;
$extractedCity = ucwords(strtolower($rawCity));

// --- LOGIC: FETCH PETUGAS ADMINISTRASI (FROM LATEST LPJ RECORD) ---
$adminName = '.........................';
$adminNip = '.........................';

// Try to get from the latest document if available
if (!empty($documents)) {
    $latestDoc = $documents[0];
    if (!empty($latestDoc['officer_name'])) {
        $adminName = $latestDoc['officer_name'];
        $adminNip = $latestDoc['officer_nip'] ?? '-';
    }
}

// --- LOGIC: FETCH LOGGED IN ADMIN FOR HEADMASTER (LEFT SIDE) ---
$headmasterName = '.........................';
$headmasterNip = '.........................';

if (isset($_SESSION['user_id'])) {
    $stmtAdmin = $db->prepare("SELECT full_name, nip FROM users WHERE id = ?");
    $stmtAdmin->execute([$_SESSION['user_id']]);
    $currAdmin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
    if ($currAdmin) {
        $headmasterName = $currAdmin['full_name'];
        $headmasterNip = $currAdmin['nip'] ?? '-';
    }
}


// Helper for Indonesian Date
function indonesianDate($date)
{
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[date('n', $timestamp)];
    $y = date('Y', $timestamp);

    return "$d $m $y";
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Pertanggungjawaban (LPJ) - <?= $year ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Times+New+Roman:wght@400;700&display=swap');

        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
            page-break-after: always;
        }

        @media print {
            body {
                background: none;
                margin: 0;
            }

            .page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: auto;
                break-after: page;
                page-break-after: always;
            }

            .no-print {
                display: none;
            }
        }

        /* Header */
        .report-header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .school-name {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
            text-decoration: underline;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        /* Signature */
        .signature-box {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding: 0 20px;
        }

        .sig {
            text-align: center;
            width: 250px;
        }

        .sig-line {
            margin-top: 70px;
            border-bottom: 1px solid #000;
        }
    </style>
</head>

<body>

    <div class="no-print"
        style="position: fixed; top: 10px; right: 10px; z-index: 1000; background: white; padding: 10px; border: 1px solid #ccc; box-shadow: 0 0 5px rgba(0,0,0,0.2);">
        <button onclick="window.print()"
            style="padding: 5px 15px; background: #007bff; color: white; border: none; cursor: pointer;">Cetak</button>
        <button onclick="window.close()"
            style="padding: 5px 15px; background: #6c757d; color: white; border: none; cursor: pointer;">Tutup</button>
    </div>

    <!-- PAGE 1: LIST -->
    <div class="page">
        <div class="report-header">
            <div class="school-name"><?= $schoolName ?></div>
            <div><?= $schoolAddress ?></div>
        </div>

        <div class="report-title">DAFTAR LAPORAN PERTANGGUNGJAWABAN (LPJ) KEUANGAN<br>TAHUN <?= $year ?></div>

        <table>
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="15%">TANGGAL UPLOAD</th>
                    <th width="30%">JUDUL DOKUMEN</th>
                    <th width="15%">KATEGORI</th>
                    <th width="35%">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($documents) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 20px;">Belum ada data LPJ untuk tahun ini.</td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1;
                    foreach ($documents as $doc): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="text-center"><?= indonesianDate($doc['uploaded_at']) ?></td>
                            <td>
                                <?= htmlspecialchars($doc['title']) ?>
                                <?php if ($doc['file_path']): ?>
                                    <br><small style="font-style:italic;">(File Tersedia)</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= htmlspecialchars($doc['category'] ?? '-') ?></td>
                            <td><?= nl2br(htmlspecialchars($doc['description'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="signature-box">
            <!-- LEFT: HEADMASTER (Logged in Admin) -->
            <div class="sig">
                Mengetahui,<br>
                Kepala Sekolah
                <div class="sig-line"></div>
                <b><?= $headmasterName ?></b><br>
                NIP. <?= $headmasterNip ?>
            </div>

            <!-- RIGHT: PETUGAS ADMINISTRASI (From Form Input) -->
            <div class="sig">
                <?= $extractedCity ?>, <?= indonesianDate(date('Y-m-d')) ?><br>
                Petugas Administrasi
                <div class="sig-line"></div>
                <b><?= $adminName ?></b><br>
                NIP. <?= $adminNip ?>
            </div>
        </div>
    </div>

</body>

</html>
