<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak.");
}

$id = $_GET['id'] ?? null;
if (!$id)
    die("ID Supervisi tidak ditemukan.");

// Fetch Supervision Data
$stmt = $db->prepare("SELECT s.*, u.full_name as teacher_name, u.nip as teacher_nip, u.assigned_class as u_class 
                      FROM academic_supervision s 
                      LEFT JOIN users u ON s.teacher_id = u.id 
                      WHERE s.id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data)
    die("Data tidak ditemukan.");

// Fetch Supervisor/Headmaster Info
$supervisor_id = $data['supervisor_id'] ?: $_SESSION['user_id'];
$stmt_sup = $db->prepare("SELECT full_name, nip FROM users WHERE id = ?");
$stmt_sup->execute([$supervisor_id]);
$supervisor = $stmt_sup->fetch();
$sup_name = $supervisor['full_name'] ?? '.........................';
$sup_nip = $supervisor['nip'] ?? '-';

// Fetch School Info
$stmt_sett = $db->query("SELECT * FROM settings LIMIT 1");
$sett = $stmt_sett->fetch();
$school_name = $sett['school_name'] ?? ($data['school_name'] ?: 'SEKOLAH DASAR');
$school_address = $sett['address'] ?? '';

// Helper for JSON
function get_json($json_str)
{
    $arr = json_decode($json_str, true);
    return is_array($arr) ? $arr : [];
}

$plan_scores = get_json($data['planning_scores']);
$plan_notes = get_json($data['planning_notes']);
$exec_scores = get_json($data['execution_scores']);
$exec_notes = get_json($data['execution_notes']);
$assess_scores = get_json($data['assessment_scores']);
$assess_notes = get_json($data['assessment_notes']);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dokumen Supervisi Akademik</title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            margin: 0;
            padding: 20px;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
            font-size: 12pt;
        }

        .sub-header {
            margin-bottom: 10px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 20px;
            text-decoration: underline;
            margin-bottom: 5px;
        }

        .sub-section-title {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 4px;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            text-align: center;
        }

        .no-border td {
            border: none;
            padding: 2px 0;
        }

        .signature-table {
            width: 100%;
            border: none;
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            width: 50%;
        }

        .sign-space {
            height: 80px;
        }

        @media print {
            @page {
                size: A4;
                margin: 1.5cm;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header">
        DOKUMEN SUPERVISI AKADEMIK<br>
        <?= htmlspecialchars(strtoupper($school_name)) ?>
    </div>

    <div class="sub-header">
        SEKOLAH: <?= htmlspecialchars($school_name) ?><br>
        TANGGAL: <?= date('d - m - Y', strtotime($data['date'])) ?>
    </div>

    <!-- BAGIAN 1: PRA-OBSERVASI -->
    <div class="section-title">BAGIAN 1: PRA-OBSERVASI (Pertemuan Awal)</div>
    <div style="font-style:italic; margin-bottom:10px;">(Dilakukan sebelum observasi kelas)</div>

    <table class="no-border">
        <tr>
            <td width="200">Nama Guru</td>
            <td>: <?= htmlspecialchars($data['teacher_name']) ?></td>
        </tr>
        <tr>
            <td>Mata Pelajaran</td>
            <td>: <?= htmlspecialchars($data['subject']) ?></td>
        </tr>
        <tr>
            <td>Kelas/Fase</td>
            <td>: <?= htmlspecialchars($data['class_name']) ?></td>
        </tr>
        <tr>
            <td>Topik / Modul Ajar</td>
            <td>: <?= htmlspecialchars($data['topic']) ?></td>
        </tr>
        <tr>
            <td>Alokasi Waktu</td>
            <td>: <?= htmlspecialchars($data['time_allocation']) ?></td>
        </tr>
    </table>

    <table class="no-border">
        <tr>
            <td width="200" style="vertical-align:top">Capaian Pembelajaran (CP)</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['kd']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Tujuan Pembelajaran (TP)</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['indicators']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Indikator / KKTP</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['objectives']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Pendekatan / Strategi</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['methods']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Media/Sumber Belajar</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['media']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Fokus Observasi (Perilaku)</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['focus_aspects']) ?></td>
        </tr>
        <tr>
            <td style="vertical-align:top">Kesiapan Belajar Murid</td>
            <td style="white-space: pre-wrap;">: <?= htmlspecialchars($data['special_needs']) ?></td>
        </tr>
    </table>

    <table class="signature-table" style="margin-top:10px; margin-bottom:20px;">
        <tr>
            <td>
                Guru<br>
                <div class="sign-space"></div>
                <b><u><?= htmlspecialchars($data['teacher_name']) ?></u></b><br>
                NIP. <?= htmlspecialchars($data['teacher_nip'] ?: '-') ?>
            </td>
            <td>
                Supervisor / Kepala Sekolah<br>
                <div class="sign-space"></div>
                <b><u><?= htmlspecialchars($sup_name) ?></u></b><br>
                NIP. <?= htmlspecialchars($sup_nip) ?>
            </td>
        </tr>
    </table>

    <!-- BAGIAN 2: INSTRUMEN OBSERVASI -->
    <div class="section-title" style="page-break-before:always;">BAGIAN 2: OBSERVASI PEMBELAJARAN (Kurikulum Merdeka)
    </div>
    <div style="font-style:italic; margin-bottom:10px;">(Diisi selama observasi di kelas)</div>

    <div style="border:1px solid #ccc; padding:5px; font-size:10pt; background:#f9f9f9; margin-bottom:10px;">
        <b>Kriteria Penilaian:</b> 4=Sangat Baik (Membudaya), 3=Baik (Efektif), 2=Cukup (Mulai Terlihat), 1=Kurang
        (Belum Terlihat)
    </div>

    <div style="margin-bottom:10px;">
        Waktu Observasi: <?= htmlspecialchars($data['obs_time_start']) ?> s.d.
        <?= htmlspecialchars($data['obs_time_end']) ?><br>
        Jumlah Siswa Hadir: <?= htmlspecialchars($data['students_present']) ?>
    </div>

    <!-- A. PERENCANAAN -->
    <div class="sub-section-title">A. PERENCANAAN & LINGKUNGAN BELAJAR</div>
    <table>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Aspek Observasi</th>
            <th width="10%">Skor</th>
            <th width="40%">Catatan Bukti Dukung</th>
        </tr>
        <?php
        $aspects_a = [
            1 => 'Ketersediaan Modul Ajar/RPP',
            2 => 'Lingkungan Kelas Aman & Nyaman',
            3 => 'Kesiapan Diagnosa Kebutuhan'
        ];
        $subA = 0;
        foreach ($aspects_a as $k => $txt) {
            $s = $plan_scores[$k] ?? 0;
            $n = $plan_notes[$k] ?? '';
            if ($s)
                $subA += (int) $s;
            echo "<tr><td align='center'>$k</td><td>" . htmlspecialchars($txt) . "</td><td align='center'>" . ($s ?: '-') . "</td><td>" . htmlspecialchars($n) . "</td></tr>";
        }
        ?>
        <tr>
            <td colspan="2" align="right"><b>Subtotal A</b></td>
            <td align="center"><b><?= $subA ?: '' ?></b></td>
            <td></td>
        </tr>
    </table>

    <!-- B. PELAKSANAAN -->
    <div class="sub-section-title">B. PELAKSANAAN PEMBELAJARAN</div>

    <div><b>B.1. Pendahuluan</b></div>
    <table>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Aspek Observasi</th>
            <th width="10%">Skor</th>
            <th width="40%">Catatan</th>
        </tr>
        <?php
        $aspects_b1 = [
            1 => 'Mengkondisikan Suasana Belajar',
            2 => 'Apersepsi & Motivasi',
            3 => 'Menyampaikan TP & Manfaat'
        ];
        $subB1 = 0;
        foreach ($aspects_b1 as $k => $txt) {
            $s = $exec_scores['b1'][$k] ?? 0;
            $n = $exec_notes['b1'][$k] ?? '';
            if ($s)
                $subB1 += (int) $s;
            echo "<tr><td align='center'>$k</td><td>" . htmlspecialchars($txt) . "</td><td align='center'>" . ($s ?: '-') . "</td><td>" . htmlspecialchars($n) . "</td></tr>";
        }
        ?>
    </table>

    <div><b>B.2. Kegiatan Inti (Student Centered)</b></div>
    <table>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Aspek Observasi</th>
            <th width="10%">Skor</th>
            <th width="40%">Catatan</th>
        </tr>
        <?php
        $aspects_b2 = [
            1 => 'Pembelajaran Berdiferensiasi',
            2 => 'Penerapan KSE',
            3 => 'Melibatkan Murid Aktif',
            4 => 'Metode/Strategi Variatif',
            5 => 'Penggunaan Media/Teknologi',
            6 => 'Pengelolaan Kelas',
            7 => 'Asesmen Formatif',
            8 => 'Pengembangan Karakter'
        ];
        $subB2 = 0;
        foreach ($aspects_b2 as $k => $txt) {
            $s = $exec_scores['b2'][$k] ?? 0;
            $n = $exec_notes['b2'][$k] ?? '';
            if ($s)
                $subB2 += (int) $s;
            echo "<tr><td align='center'>$k</td><td>" . htmlspecialchars($txt) . "</td><td align='center'>" . ($s ?: '-') . "</td><td>" . htmlspecialchars($n) . "</td></tr>";
        }
        ?>
    </table>

    <div><b>B.3. Penutup</b></div>
    <table>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Aspek Observasi</th>
            <th width="10%">Skor</th>
            <th width="40%">Catatan</th>
        </tr>
        <?php
        $aspects_b3 = [
            1 => 'Refleksi Murid dan Guru',
            2 => 'Penyimpulan Pembelajaran',
            3 => 'Rencana Tindak Lanjut'
        ];
        $subB3 = 0;
        foreach ($aspects_b3 as $k => $txt) {
            $s = $exec_scores['b3'][$k] ?? 0;
            $n = $exec_notes['b3'][$k] ?? '';
            if ($s)
                $subB3 += (int) $s;
            echo "<tr><td align='center'>$k</td><td>" . htmlspecialchars($txt) . "</td><td align='center'>" . ($s ?: '-') . "</td><td>" . htmlspecialchars($n) . "</td></tr>";
        }
        ?>
    </table>

    <!-- C. ASESMEN -->
    <div class="sub-section-title">C. ASESMEN HASIL BELAJAR</div>
    <table>
        <tr>
            <th width="5%">No</th>
            <th width="45%">Aspek Observasi</th>
            <th width="10%">Skor</th>
            <th width="40%">Catatan</th>
        </tr>
        <?php
        $aspects_c = [
            1 => 'Teknik Penilaian',
            2 => 'Kriteria Penilaian',
            3 => 'Keautentikan'
        ];
        $subC = 0;
        foreach ($aspects_c as $k => $txt) {
            $s = $assess_scores[$k] ?? 0;
            $n = $assess_notes[$k] ?? '';
            if ($s)
                $subC += (int) $s;
            echo "<tr><td align='center'>$k</td><td>" . htmlspecialchars($txt) . "</td><td align='center'>" . ($s ?: '-') . "</td><td>" . htmlspecialchars($n) . "</td></tr>";
        }
        ?>
        <tr>
            <td colspan="2" align="right"><b>Subtotal C</b></td>
            <td align="center"><b><?= $subC ?: '' ?></b></td>
            <td></td>
        </tr>
    </table>

    <!-- D. CATATAN KHUSUS -->
    <div class="sub-section-title">D. CATATAN KHUSUS SELAMA OBSERVASI</div>
    <div style="display:flex; gap:20px; border:1px solid black; padding:10px;">
        <div style="flex:1;">
            <b>Hal-hal yang sudah baik:</b>
            <div style="white-space: pre-wrap;"><?= htmlspecialchars($data['strengths']) ?></div>
        </div>
        <div style="flex:1; border-left:1px solid black; padding-left:10px;">
            <b>Hal-hal yang perlu dikembangkan:</b>
            <div style="white-space: pre-wrap;"><?= htmlspecialchars($data['areas_for_improvement']) ?></div>
        </div>
    </div>

    <div style="margin-top:20px; font-weight:bold;">
        Total Skor: <?= $data['total_score'] ?> / 80<br>
        Persentase: <?= number_format($data['percentage'], 1) ?> %<br>
        Predikat: [ <?= ($data['recommendation'] == 'Sangat Baik' ? 'X' : ' ') ?> ] Sangat Baik [
        <?= ($data['recommendation'] == 'Baik' ? 'X' : ' ') ?> ] Baik [
        <?= ($data['recommendation'] == 'Cukup' ? 'X' : ' ') ?> ] Cukup [
        <?= ($data['recommendation'] == 'Perlu Perbaikan' ? 'X' : ' ') ?> ] Perlu Perbaikan
    </div>

    <!-- BAGIAN 3: PASCA-OBSERVASI -->
    <div class="section-title" style="page-break-before:always;">BAGIAN 3: PASCA-OBSERVASI (Umpan Balik & Coaching)
    </div>
    <div style="font-style:italic; margin-bottom:10px;">(Dilaksanakan setelah observasi)</div>

    <table>
        <tr>
            <th width="30%">Aspek Diskusi</th>
            <th>Hasil Refleksi Bersama</th>
        </tr>
        <tr>
            <td>Refleksi Guru (Perasaan & Evaluasi Diri)</td>
            <td style="white-space: pre-wrap; text-align:left;"><?= htmlspecialchars($data['post_reflection']) ?></td>
        </tr>
        <tr>
            <td>Umpan Balik Supervisor</td>
            <td style="text-align:left;">
                <b>Apresiasi (Kekuatan):</b><br><span
                    style="white-space: pre-wrap;"><?= htmlspecialchars($data['post_feedback_strengths']) ?></span><br><br>
                <b>Saran Konstruktif:</b><br><span
                    style="white-space: pre-wrap;"><?= htmlspecialchars($data['post_feedback_improvements']) ?></span>
            </td>
        </tr>
        <tr>
            <td>Rencana Pengembangan Diri</td>
            <td style="text-align:left;">
                <b>Target / Tujuan:</b><br><span
                    style="white-space: pre-wrap;"><?= htmlspecialchars($data['post_action_plan_targets']) ?></span><br><br>
                <b>Dukungan yang Dibutuhkan:</b><br><span
                    style="white-space: pre-wrap;"><?= htmlspecialchars($data['post_action_plan_support']) ?></span><br><br>
                <b>Timeline:</b> <?= htmlspecialchars($data['post_timeline']) ?>
            </td>
        </tr>
        <tr>
            <td>Jadwal Berikutnya (Tindak Lanjut)</td>
            <td style="text-align:left;">Tanggal Supervisi Berikutnya:
                <?= htmlspecialchars($data['post_next_date'] ? date('d-m-Y', strtotime($data['post_next_date'])) : '-') ?>
            </td>
        </tr>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                Guru<br>
                <div class="sign-space"></div>
                <b><u><?= htmlspecialchars($data['teacher_name']) ?></u></b><br>
                NIP. <?= htmlspecialchars($data['teacher_nip'] ?: '-') ?>
                <br><br>
                Tanggal:
                <?= $data['post_date'] ? date('d-m-Y', strtotime($data['post_date'])) : '.........................' ?>
            </td>
            <td>
                Supervisor / Kepala Sekolah<br>
                <div class="sign-space"></div>
                <b><u><?= htmlspecialchars($sup_name) ?></u></b><br>
                NIP. <?= htmlspecialchars($sup_nip) ?>
                <br><br>
                Tanggal:
                <?= $data['post_date'] ? date('d-m-Y', strtotime($data['post_date'])) : '.........................' ?>
            </td>
        </tr>
    </table>

    <?php if ($data['photo_path']): ?>
        <div class="section-title">LAMPIRAN FOTO</div>
        <div style="text-align:center; margin-top:20px;">
            <img src="<?= htmlspecialchars($data['photo_path']) ?>"
                style="max-width:100%; max-height:500px; border:1px solid #ccc;">
        </div>
    <?php endif; ?>

</body>

</html>