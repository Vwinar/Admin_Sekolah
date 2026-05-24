<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_GET['id'])) {
    die('ID tidak valid');
}

$id = $_GET['id'];

// Fetch coaching record with teacher and principal info
$stmt = $db->prepare("
    SELECT tc.*, u.full_name, u.nip, u.subject, u.assigned_class
    FROM teacher_coaching tc
    LEFT JOIN users u ON tc.teacher_id = u.id
    WHERE tc.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die('Data tidak ditemukan');
}

// Get principal info
$principal = $db->query("SELECT full_name, nip FROM users WHERE role = 'admin' LIMIT 1")->fetch();

// Get school settings
$settings = $db->query("SELECT school_name FROM settings LIMIT 1")->fetch();
$schoolName = $settings['school_name'] ?? 'SD NEGERI CONTOH';

// Parse JSON data
$programs = json_decode($record['program_data'] ?? '[]', true) ?: [];
$progress = json_decode($record['progress_data'] ?? '[]', true) ?: [];

// Parse competency focus
$competencies = explode(', ', $record['competency_focus'] ?? '');
$competencyChecks = [
    'Pedagogik' => in_array('Pedagogik', $competencies) ? '☑' : '☐',
    'Kepribadian' => in_array('Kepribadian', $competencies) ? '☑' : '☐',
    'Sosial' => in_array('Sosial', $competencies) ? '☑' : '☐',
    'Profesional' => in_array('Profesional', $competencies) ? '☑' : '☐',
];

// Parse coaching type
$coachingTypes = [
    'In Service' => $record['coaching_type'] === 'In Service' ? '☑' : '☐',
    'On Service' => $record['coaching_type'] === 'On Service' ? '☑' : '☐',
    'Induksi' => $record['coaching_type'] === 'Induksi' ? '☑' : '☐',
];

// Achievement level
$achievementLevels = [
    'Tercapai seluruhnya' => $record['achievement_level'] === 'Tercapai seluruhnya' ? '☑' : '☐',
    'Tercapai sebagian besar' => $record['achievement_level'] === 'Tercapai sebagian besar' ? '☑' : '☐',
    'Belum tercapai secara signifikan' => $record['achievement_level'] === 'Belum tercapai secara signifikan' ? '☑' : '☐',
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Format Pembinaan Profesi Guru - <?= htmlspecialchars($record['full_name']) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            padding: 20px;
        }

        h1,
        h2,
        h3 {
            text-align: center;
        }

        h1 {
            font-size: 16pt;
            margin-bottom: 0;
        }

        h3 {
            font-size: 14pt;
            margin-top: 5px;
        }

        .info-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 5px;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 30px;
        }

        .info-table td:nth-child(2) {
            width: 250px;
        }

        .info-table td:nth-child(3) {
            width: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .data-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .section-title {
            font-weight: bold;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .checkbox-group {
            margin: 10px 0;
        }

        .signature-area {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            margin-top: 80px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .header-info {
            text-align: left;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

    <div class="header-info">
        <h1>FORMAT PEMBINAAN PROFESI GURU</h1>
        <h3>SEKOLAH DASAR: <?= strtoupper(htmlspecialchars($schoolName)) ?></h3>
        <p style="text-align: center;">
            <strong>TAHUN PELAJARAN: <?= htmlspecialchars($record['school_year']) ?></strong><br>
            <strong>SEMESTER: <?= htmlspecialchars($record['semester']) ?></strong>
        </p>
    </div>

    <div class="section-title">A. IDENTITAS GURU YANG DIBINA</div>
    <table class="info-table">
        <tr>
            <td>1.</td>
            <td>Nama Lengkap Guru</td>
            <td>:</td>
            <td><strong><?= htmlspecialchars($record['full_name']) ?></strong></td>
        </tr>
        <tr>
            <td>2.</td>
            <td>NIP</td>
            <td>:</td>
            <td><?= htmlspecialchars($record['nip'] ?? '-') ?></td>
        </tr>
        <tr>
            <td>3.</td>
            <td>Pangkat / Golongan</td>
            <td>:</td>
            <td><?= htmlspecialchars($record['teacher_rank'] ?? '__________________________') ?></td>
        </tr>
        <tr>
            <td>4.</td>
            <td>Mata Pelajaran / Kelas yang Diampu</td>
            <td>:</td>
            <td><?= htmlspecialchars(($record['subject'] ?? '') . ' / Kelas ' . ($record['assigned_class'] ?? '')) ?>
            </td>
        </tr>
        <tr>
            <td>5.</td>
            <td>Masa Kerja (Tahun)</td>
            <td>:</td>
            <td><?= htmlspecialchars($record['years_of_service'] ?? '__________________________') ?></td>
        </tr>
        <tr>
            <td>6.</td>
            <td>Jenis Pembinaan*</td>
            <td>:</td>
            <td>
                <div class="checkbox-group">
                    <?= $coachingTypes['In Service'] ?> In Service
                    <?= $coachingTypes['On Service'] ?> On Service
                    <?= $coachingTypes['Induksi'] ?> Induksi
                </div>
                <small>*Keterangan: In Service (pelatihan luar sekolah), On Service (pembinaan di sekolah), Induksi
                    (guru
                    pemula).</small>
            </td>
        </tr>
    </table>

    <div class="section-title">B. PETA AWAL KONDISI & KEBUTUHAN GURU</div>

    <p><strong>1. Hasil Analisis Kebutuhan (Dari Observasi, Wawancara, atau PKG Sebelumnya):</strong></p>
    <p><strong>• Aspek yang sudah kuat/unggul:</strong></p>
    <?php if ($record['analysis_strengths']): ?>
        <?php
        $strengths = explode("\n", $record['analysis_strengths']);
        echo "<ul>";
        foreach ($strengths as $s) {
            if (trim($s))
                echo "<li>" . htmlspecialchars(trim($s)) . "</li>";
        }
        echo "</ul>";
        ?>
    <?php else: ?>
        <ul>
            <li>________________________________________</li>
            <li>________________________________________</li>
        </ul>
    <?php endif; ?>

    <p><strong>• Aspek yang memerlukan pengembangan/pembinaan:</strong></p>
    <?php if ($record['analysis_improvements']): ?>
        <?php
        $improvements = explode("\n", $record['analysis_improvements']);
        echo "<ul>";
        foreach ($improvements as $i) {
            if (trim($i))
                echo "<li>" . htmlspecialchars(trim($i)) . "</li>";
        }
        echo "</ul>";
        ?>
    <?php else: ?>
        <ul>
            <li>________________________________________</li>
            <li>________________________________________</li>
        </ul>
    <?php endif; ?>

    <p><strong>2. Jenis Kompetensi yang Menjadi Fokus Pembinaan (Centang √):</strong></p>
    <div class="checkbox-group">
        <p><?= $competencyChecks['Pedagogik'] ?> <strong>Pedagogik:</strong> Penyusunan RPP, Metode Mengajar, Media,
            Evaluasi, dsb.</p>
        <p><?= $competencyChecks['Kepribadian'] ?> <strong>Kepribadian:</strong> Etika, Kedisiplinan, Tanggung Jawab,
            dsb.
        </p>
        <p><?= $competencyChecks['Sosial'] ?> <strong>Sosial:</strong> Komunikasi dengan Siswa/Rekan/Orang Tua,
            Kolaborasi,
            dsb.</p>
        <p><?= $competencyChecks['Profesional'] ?> <strong>Profesional:</strong> Penguasaan Materi, Literasi Teknologi,
            Penelitian Tindakan Kelas (PTK), dsb.</p>
    </div>

    <div class="section-title">C. RENCANA PROGRAM PEMBINAAN INDIVIDU (RPPI)</div>
    <p><strong>Tujuan Pembinaan:</strong>
        <?= htmlspecialchars($record['coaching_goal'] ?? '________________________________________________________') ?>
    </p>
    <p style="font-size: 10pt; font-style: italic;">(Spesifik, Terukur, Berkaitan dengan Kebutuhan yang Teridentifikasi)
    </p>

    <?php if (count($programs) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Bentuk Kegiatan Pembinaan</th>
                    <th>Materi/Topik</th>
                    <th>Indikator Keberhasilan</th>
                    <th>Jadwal</th>
                    <th>Penanggung Jawab</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programs as $idx => $prog): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($prog['activity'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prog['topic'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prog['indicator'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prog['schedule'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($prog['pic'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><em>(Belum ada program yang terdaftar)</em></p>
    <?php endif; ?>

    <div class="section-title" style="page-break-before: always;">D. CATATAN PELAKSANAAN DAN PERKEMBANGAN</div>
    <p style="font-size: 10pt; font-style: italic;">(Diisi setelah setiap tahap kegiatan pembinaan dilaksanakan)</p>

    <?php if (count($progress) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Kegiatan</th>
                    <th>Proses & Temuan</th>
                    <th>Kemajuan yang Terlihat</th>
                    <th>Kendala & Solusi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($progress as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['date'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['activity'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['process'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['progress'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['obstacles'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><em>(Belum ada catatan pelaksanaan)</em></p>
    <?php endif; ?>

    <div class="section-title">E. EVALUASI AKHIR DAN TINDAK LANJUT</div>

    <p><strong>1. Capaian Hasil Pembinaan (Berdasarkan Indikator di RPPI):</strong></p>
    <div class="checkbox-group">
        <p><?= $achievementLevels['Tercapai seluruhnya'] ?> Tercapai seluruhnya</p>
        <p><?= $achievementLevels['Tercapai sebagian besar'] ?> Tercapai sebagian besar</p>
        <p><?= $achievementLevels['Belum tercapai secara signifikan'] ?> Belum tercapai secara signifikan</p>
    </div>

    <p><strong>2. Perubahan/Kemajuan yang Dirasakan oleh Guru:</strong></p>
    <p style="font-size: 10pt; font-style: italic;">(Diisi oleh Guru yang Dibina)</p>
    <?php if ($record['teacher_feedback']): ?>
        <?php
        $feedback = explode("\n", $record['teacher_feedback']);
        echo "<ul>";
        foreach ($feedback as $f) {
            if (trim($f))
                echo "<li>" . htmlspecialchars(trim($f)) . "</li>";
        }
        echo "</ul>";
        ?>
    <?php else: ?>
        <ul>
            <li>________________________________________</li>
            <li>________________________________________</li>
        </ul>
    <?php endif; ?>

    <p><strong>3. Analisis dan Rekomendasi Pembina Berikutnya:</strong></p>
    <p style="font-size: 10pt; font-style: italic;">(Diisi oleh Kepala Sekolah/Pembina)</p>

    <p><strong>• Kesimpulan Umum:</strong></p>
    <p><?= htmlspecialchars($record['principal_analysis'] ?? '___________________________________________') ?></p>

    <p><strong>• Rekomendasi untuk Guru:</strong></p>
    <ul>
        <li><strong>Untuk dipertahankan:</strong>
            <?= htmlspecialchars($record['recommendations_maintain'] ?? '_______________________________________') ?>
        </li>
        <li><strong>Untuk ditingkatkan:</strong>
            <?= htmlspecialchars($record['recommendations_improve'] ?? '________________________________________') ?>
        </li>
    </ul>

    <p><strong>• Rencana Tindak Lanjut Jangka Panjang:</strong></p>
    <?php if ($record['followup_actions']): ?>
        <?php
        $followups = explode("\n", $record['followup_actions']);
        echo "<ul>";
        foreach ($followups as $fu) {
            if (trim($fu))
                echo "<li>" . htmlspecialchars(trim($fu)) . "</li>";
        }
        echo "</ul>";
        ?>
    <?php else: ?>
        <p>☐ Diikutsertakan dalam pelatihan eksternal tentang: ___________</p>
        <p>☐ Dijadikan guru model / pendamping untuk aspek: ____________</p>
        <p>☐ Perlu pembinaan lanjutan dengan fokus: ____________________</p>
        <p>☐ Siap untuk disertakan dalam pengembangan keprofesian berkelanjutan (PKB) mandiri.</p>
    <?php endif; ?>

    <div class="section-title">F. PENUTUP</div>
    <p>Pembinaan ini dilaksanakan dalam rangka peningkatan mutu pembelajaran dan pengembangan keprofesian berkelanjutan.
    </p>

    <div class="signature-area">
        <div class="signature-box">
            <p><strong>Guru yang Dibina,</strong></p>
            <div class="signature-line">
                <p><strong><?= htmlspecialchars($record['full_name']) ?></strong></p>
                <p>NIP: <?= htmlspecialchars($record['nip'] ?? '-') ?></p>
            </div>
        </div>
        <div class="signature-box">
            <p><strong>Pembina / Kepala Sekolah,</strong></p>
            <div class="signature-line">
                <p><strong><?= htmlspecialchars($principal['full_name'] ?? '_______________________') ?></strong></p>
                <p>NIP: <?= htmlspecialchars($principal['nip'] ?? '_______________________') ?></p>
            </div>
        </div>
    </div>

    <p style="text-align: center; margin-top: 30px;">
        <strong>Tanggal Penyelesaian: <?= htmlspecialchars($record['completion_date'] ?? '__/__/______') ?></strong>
    </p>

</body>

</html>
