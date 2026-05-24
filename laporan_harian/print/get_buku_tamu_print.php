<?php
session_start();
require_once '../config/db_connect.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Fetch Data for the specific month/year
$query = "SELECT * FROM school_guest_book 
          WHERE strftime('%m', date) = ? AND strftime('%Y', date) = ? 
          ORDER BY date ASC, time_in ASC";
$stmt = $db->prepare($query);
$stmt->execute([sprintf("%02d", $month), $year]);
$guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch School Settings
$querySettings = "SELECT * FROM settings LIMIT 1";
$stmtSettings = $db->query($querySettings);
$settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);

$schoolName = $settings['school_name'] ?? 'SEKOLAH CONTOH';
// Assuming address/phone are in settings or hardcoded if not available
$schoolAddress = "Alamat Sekolah Belum Diatur";
$schoolPhone = "00000000";
$schoolEmail = "email@sekolah.com";

// Helper for Indonesian Days/Months
function indonesianDate($date)
{
    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    $timestamp = strtotime($date);
    $d = date('j', $timestamp);
    $m = $months[date('n', $timestamp)];
    $y = date('Y', $timestamp);
    $dayName = $days[date('w', $timestamp)];

    return "$dayName, $d $m $y";
}

$monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$currentMonthName = $monthNames[(int) $month];

// Recap Calculation
$recapData = [
    'Dinas Pendidikan' => 0,
    'Komite Sekolah' => 0,
    'Orang Tua/Wali' => 0,
    'Instansi Mitra' => 0,
    'Tamu Umum' => 0
];

foreach ($guests as $g) {
    // Basic categorization based on organization or position
    $org = strtolower($g['organization'] ?? '');
    $pos = strtolower($g['position'] ?? '');

    if (strpos($org, 'dinas') !== false || strpos($pos, 'pengawas') !== false) {
        $recapData['Dinas Pendidikan']++;
    } elseif (strpos($org, 'komite') !== false) {
        $recapData['Komite Sekolah']++;
    } elseif (strpos($org, 'orang tua') !== false || strpos($org, 'wali') !== false) {
        $recapData['Orang Tua/Wali']++;
    } elseif (strpos($org, 'pt') !== false || strpos($org, 'cv') !== false || strpos($org, 'yayasan') !== false) {
        $recapData['Instansi Mitra']++;
    } else {
        $recapData['Tamu Umum']++;
    }
}
$totalVisits = count($guests);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Buku Tamu - <?= $currentMonthName ?> <?= $year ?></title>
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

        /* Cover Styles */
        .cover-content {
            text-align: center;
            top: 50%;
            position: relative;
            transform: translateY(-50%);
        }

        .cover-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 2rem;
            /* Placeholder styling if no image */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border: 2px solid #000;
            border-radius: 50%;
        }

        .cover-title {
            font-size: 36pt;
            font-weight: bold;
            margin-bottom: 3rem;
            letter-spacing: 5px;
        }

        .cover-school {
            font-size: 24pt;
            font-weight: bold;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }

        .cover-address {
            font-size: 14pt;
            margin-bottom: 4rem;
        }

        .cover-year {
            font-size: 18pt;
            font-weight: bold;
            border-top: 3px double #000;
            border-bottom: 3px double #000;
            padding: 10px 40px;
            display: inline-block;
        }

        /* Instructions */
        .instruction-title {
            text-align: center;
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 2rem;
            text-decoration: underline;
        }

        .instruction-list {
            margin-left: 20px;
        }

        .instruction-list li {
            margin-bottom: 10px;
            font-size: 14pt;
        }

        /* Content Table */
        .content-header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
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
        }

        .sig {
            text-align: center;
            width: 200px;
        }

        .sig-line {
            margin-top: 60px;
            border-bottom: 1px solid #000;
        }

        /* Recap */
        .recap-title {
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="no-print"
        style="position: fixed; top: 10px; right: 10px; z-index: 1000; background: white; padding: 10px; border: 1px solid #ccc; box-shadow: 0 0 5px rgba(0,0,0,0.2);">
        <button onclick="window.print()"
            style="padding: 5px 15px; background: #007bff; color: white; border: none; cursor: pointer;">Cetak /
            Print</button>
        <button onclick="window.close()"
            style="padding: 5px 15px; background: #6c757d; color: white; border: none; cursor: pointer;">Tutup</button>
    </div>

    <!-- PAGE 1: COVER -->
    <div class="page">
        <div class="cover-content">
            <div class="cover-logo">
                <?php if (!empty($settings['school_logo'])): ?>
                    <img src="<?= $settings['school_logo'] ?>" alt="Logo"
                        style="max-width: 100%; max-height: 100%; border-radius: 50%;">
                <?php else: ?>
                    <span style="font-size: 30pt; font-weight: bold;">LOGO</span>
                <?php endif; ?>
            </div>

            <div class="cover-title">BUKU TAMU</div>

            <div class="cover-school"><?= $schoolName ?></div>

            <div class="cover-address">
                <?= $schoolAddress ?><br>
                Telepon: <?= $schoolPhone ?> | Email: <?= $schoolEmail ?>
            </div>

            <div class="cover-year">TAHUN: <?= $year ?></div>
        </div>
    </div>

    <!-- PAGE 2: INSTRUCTIONS -->
    <div class="page">
        <div class="instruction-title">PETUNJUK PENGISIAN</div>

        <ol class="instruction-list">
            <li>Isilah data diri dengan lengkap dan jelas pada kolom yang tersedia.</li>
            <li>Tuliskan tujuan kunjungan secara spesifik agar mudah ditindaklanjuti.</li>
            <li>Cantumkan nama jelas dan asal instansi dengan benar.</li>
            <li>Mohon mengisi kolom tanda tangan sebagai bukti kehadiran.</li>
            <li>Buku ini digunakan untuk mencatat semua tamu yang berkunjung ke sekolah untuk keperluan dinas maupun
                pribadi.</li>
            <li>Dimohon untuk menjaga kerapian buku tamu ini.</li>
        </ol>

        <div style="margin-top: 50px; text-align: center; font-style: italic;">
            "Tamu adalah Raja, layanilah dengan Sepenuh Hati"
        </div>
    </div>

    <!-- PAGE 3+: CONTENT -->
    <div class="page">
        <div class="content-header">
            DAFTAR TAMU BULAN <?= strtoupper($currentMonthName) ?> <?= $year ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="5%">NO</th>
                    <th width="15%">WAKTU</th>
                    <th width="20%">NAMA LENGKAP</th>
                    <th width="15%">INSTANSI/ASAL</th>
                    <th width="15%">JABATAN</th>
                    <th width="20%">TUJUAN</th>
                    <th width="10%">TANDA TANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $counter = 1;
                $currentDate = '';
                foreach ($guests as $g):
                    $dateStr = indonesianDate($g['date']);
                    if ($currentDate !== $dateStr) {
                        $currentDate = $dateStr;
                        // Separator row for date
                        echo "<tr><td colspan='7' style='background:#f9f9f9; font-weight:bold; padding: 5px 10px;'>Hari/Tanggal: $currentDate</td></tr>";
                    }
                    ?>
                    <tr>
                        <td class="text-center"><?= $counter++ ?>.</td>
                        <td>
                            Masuk: <?= substr($g['time_in'] ?? '-', 0, 5) ?><br>
                            Keluar: <?= substr($g['time_out'] ?? '-', 0, 5) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($g['name']) ?><br>
                            <small>HP: <?= htmlspecialchars($g['phone'] ?? '-') ?></small>
                        </td>
                        <td><?= htmlspecialchars($g['organization']) ?></td>
                        <td><?= htmlspecialchars($g['position'] ?? '-') ?></td>
                        <td>
                            <strong>Tujuan:</strong> <?= htmlspecialchars($g['purpose']) ?><br>
                            <strong>Bertemu:</strong> <?= htmlspecialchars($g['pic_school'] ?? '-') ?>
                        </td>
                        <td class="text-center" style="vertical-align: middle;">[ &nbsp;&nbsp;&nbsp;&nbsp; ]</td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($guests) === 0): ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 20px;">Belum ada data tamu pada bulan ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="signature-box">
            <div class="sig">
                <br>
                Mengetahui,<br>
                Kepala Sekolah
                <div class="sig-line"></div>
                <b>______________________</b><br>
                NIP.
            </div>
            <div class="sig">
                <?= $schoolName ?>, <?= date('d') ?> <?= $currentMonthName ?> <?= $year ?><br>
                Petugas Penerima Tamu
                <div class="sig-line"></div>
                <b>______________________</b>
            </div>
        </div>
    </div>

    <!-- PAGE 4: RECAP -->
    <div class="page">
        <div class="recap-title">HALAMAN REKAPITULASI BULANAN</div>
        <p class="text-center">BULAN: <?= strtoupper($currentMonthName) ?> TAHUN: <?= $year ?></p>

        <table>
            <thead>
                <tr>
                    <th width="10%">NO</th>
                    <th width="40%">JENIS TAMU</th>
                    <th width="20%">JUMLAH</th>
                    <th width="20%">PERSENTASE</th>
                    <th width="10%">KET</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                foreach ($recapData as $type => $count):
                    $percent = $totalVisits > 0 ? round(($count / $totalVisits) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= $type ?></td>
                        <td class="text-center"><?= $count ?></td>
                        <td class="text-center"><?= $percent ?>%</td>
                        <td>-</td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="2" class="text-center"><strong>TOTAL</strong></td>
                    <td class="text-center"><strong><?= $totalVisits ?></strong></td>
                    <td class="text-center"><strong>100%</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; border: 1px solid #000; padding: 10px; height: 150px;">
            <strong>Analisis Singkat:</strong><br>
            <p style="color: #666; font-style: italic;">(Tuliskan analisis singkat mengenai kunjungan tamu bulan ini...)
            </p>
        </div>

        <div class="signature-box">
            <div class="sig">
                Mengetahui,<br>
                Kepala Sekolah
                <div class="sig-line"></div>
            </div>
            <div class="sig">
                Petugas Administrasi
                <div class="sig-line"></div>
            </div>
        </div>
    </div>

</body>

</html>
