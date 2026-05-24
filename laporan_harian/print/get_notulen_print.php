<?php
require_once '../config/db_connect.php';

$id = $_GET['id'] ?? '';
if (!$id)
    die("ID tidak ditemukan");

// Fetch Notulen
$stmt = $db->prepare("SELECT * FROM school_logs WHERE id = ?");
$stmt->execute([$id]);
$notu = $stmt->fetch();

if (!$notu)
    die("Data tidak ditemukan");

// Fetch School Data
$stmt = $db->query("SELECT * FROM settings LIMIT 1");
$setting = $stmt->fetch();
$school_name = $setting['school_name'] ?? 'SEKOLAH ...';
$school_logo = $setting['school_logo'] ?? '';

// Calculate Academic Year
$date = strtotime($notu['date']);
$year = date('Y', $date);
$month = date('n', $date);
$semester = ($month >= 7) ? 'Ganjil' : 'Genap';
$tp = ($month >= 7) ? "$year/" . ($year + 1) : ($year - 1) . "/$year";

// Parse JSONs
$attendees = json_decode($notu['attendees_json'] ?? '[]', true);
$decisions = json_decode($notu['decisions_json'] ?? '[]', true);

// Format Text
function formatLines($text)
{
    return nl2br(htmlspecialchars($text));
}

function tgl_indo($tanggal, $withDay = true)
{
    if (!$tanggal)
        return '-';
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];
    $ts = strtotime($tanggal);
    $d = date('j', $ts);
    $m = $bulan[(int) date('n', $ts)];
    $y = date('Y', $ts);
    $dayName = $hari[date('l', $ts)];

    if ($withDay) {
        return "$dayName, $d $m $y";
    } else {
        return "$d $m $y";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Notulen Rapat - <?= htmlspecialchars($notu['subject']) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            line-height: 1.5;
            padding: 2cm;
        }

        @page {
            size: A4;
            margin: 2cm;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .uppercase {
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1em;
        }

        th,
        td {
            padding: 4px 8px;
            vertical-align: top;
        }

        .bordered {
            border: 1px solid black;
        }

        .bordered th,
        .bordered td {
            border: 1px solid black;
        }

        .header-title {
            font-size: 16pt;
            margin-bottom: 5px;
        }

        .sub-header {
            font-size: 12pt;
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .indent {
            padding-left: 20px;
        }

        .signature-box {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .sign-col {
            text-align: center;
            width: 45%;
        }

        .sign-space {
            height: 80px;
        }

        @media print {
            body {
                padding: 0;
            }

            button {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <!-- TITLE PAGE / HEADER -->
    <div class="text-center">
        <div class="header-title bold">BUKU NOTULEN RAPAT</div>
        <div class="sub-header">
            <div class="bold uppercase">SEKOLAH: <?= htmlspecialchars($school_name) ?></div>
            <div class="bold">TAHUN AJARAN: <?= $tp ?></div>
            <div class="bold">JENIS RAPAT: <?= htmlspecialchars($notu['subject']) ?></div>
            <div class="bold">Nomor Buku: <?= htmlspecialchars($notu['book_number'] ?? '....') ?></div>
        </div>
    </div>

    <hr style="border: 2px double black; margin-bottom: 20px;">

    <!-- FORMAT HALAMAN PER RAPAT -->
    <div style="text-align: right; font-style: italic; margin-bottom: 10px;">HALAMAN: [Nomor Halaman]</div>

    <div class="section-title">1. KOP SURAT/IDENTITAS RAPAT</div>
    <table>
        <tr>
            <td width="20%">Jenis Rapat</td>
            <td width="2%">:</td>
            <td><?= htmlspecialchars($notu['subject']) ?></td>
        </tr>
        <tr>
            <td>Hari/Tanggal</td>
            <td>:</td>
            <td><?= tgl_indo($notu['date']) ?></td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>:</td>
            <td><?= htmlspecialchars($notu['meeting_time']) ?></td>
        </tr>
        <tr>
            <td>Tempat</td>
            <td>:</td>
            <td><?= htmlspecialchars($notu['location']) ?></td>
        </tr>
        <tr>
            <td>Agenda</td>
            <td>:</td>
            <td><?= formatLines($notu['agenda_header']) ?></td>
        </tr>
    </table>

    <div class="section-title">2. DAFTAR HADIR PESERTA RAPAT</div>
    <table class="bordered">
        <thead>
            <tr style="background: #eee;">
                <th width="5%">No.</th>
                <th>Nama Lengkap</th>
                <th>Jabatan / Peran</th>
                <th>Tanda Tangan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($attendees)): ?>
                <tr>
                    <td colspan="5" class="text-center">- Data Kosong -</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendees as $i => $a): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?>.</td>
                        <td><?= htmlspecialchars($a['name']) ?></td>
                        <td><?= htmlspecialchars($a['position']) ?></td>
                        <td></td>
                        <td class="text-center"><?= htmlspecialchars($a['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="border: none; text-align: right; padding-top: 5px;">
                    Jumlah Peserta yang Hadir: <b><?= count($attendees) ?></b> Orang
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="section-title">3. PEMIMPIN/DALAM RAPAT</div>
    <table>
        <tr>
            <td width="20%">Pemimpin Rapat</td>
            <td width="2%">:</td>
            <td><?= htmlspecialchars($notu['leader_name']) ?></td>
        </tr>
        <tr>
            <td>Notulis</td>
            <td>:</td>
            <td><?= htmlspecialchars($notu['notulis_name']) ?></td>
        </tr>
        <tr>
            <td>Moderator</td>
            <td>:</td>
            <td><?= htmlspecialchars($notu['moderator_name']) ?></td>
        </tr>
    </table>

    <div class="section-title">4. PROSES / ISI PEMBAHASAN (NOTULEN)</div>
    <div style="text-align: justify; margin-bottom: 20px;">
        <!-- User wants narrative text here. We put the 'details' content. -->
        <?= formatLines($notu['details']) ?>
    </div>

    <div class="section-title">5. KESIMPULAN, KEPUTUSAN, DAN TINDAK LANJUT RAPAT</div>
    <table class="bordered">
        <thead>
            <tr style="background: #eee;">
                <th width="5%">No.</th>
                <th>Poin Keputusan / Tugas</th>
                <th>Penanggung Jawab (PIC)</th>
                <th>Target Waktu</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($decisions)): ?>
                <tr>
                    <td colspan="5" class="text-center">- Tidak ada keputusan khusus -</td>
                </tr>
            <?php else: ?>
                <?php foreach ($decisions as $i => $d): ?>
                    <tr>
                        <td class="text-center"><?= $i + 1 ?>.</td>
                        <td><?= htmlspecialchars($d['point']) ?></td>
                        <td><?= htmlspecialchars($d['pic']) ?></td>
                        <td><?= htmlspecialchars($d['target']) ?></td>
                        <td><?= htmlspecialchars($d['note']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="section-title">6. PENUTUP</div>
    <div style="margin-bottom: 20px;">
        Rapat diakhiri pada pukul [Waktu Akhir] dengan doa bersama. Tidak ada hal lain yang dibahas.
    </div>

    <div class="section-title">7. PENGESAHAN</div>
    <div class="signature-box">
        <div class="sign-col">
            <div>Mengetahui/Mengesahkan,<br>Pemimpin Rapat</div>
            <div class="sign-space"></div>
            <div class="bold"><?= htmlspecialchars($notu['leader_name']) ?></div>
            <div>NIP. <?= $notu['leader_nip'] ? htmlspecialchars($notu['leader_nip']) : '...........................' ?>
            </div>
        </div>
        <div class="sign-col">
            <div>........................., <?= tgl_indo($notu['date'], false) ?><br>Notulis,</div>
            <div class="sign-space"></div>
            <div class="bold"><?= htmlspecialchars($notu['notulis_name']) ?></div>
            <div>NIP.
                <?= $notu['notulis_nip'] ? htmlspecialchars($notu['notulis_nip']) : '...........................' ?>
            </div>
        </div>
    </div>

</body>

</html>
