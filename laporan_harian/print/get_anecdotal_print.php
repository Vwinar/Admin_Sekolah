<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$id = $_GET['id'] ?? 0;

// Fetch record
$stmt = $db->prepare("
    SELECT ar.*, u.full_name, u.nip, u.subject, u.assigned_class
    FROM anecdotal_records ar
    LEFT JOIN users u ON ar.teacher_id = u.id
    WHERE ar.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Data tidak ditemukan");
}

// Fetch principal info
$principal = $db->query("SELECT full_name, nip FROM users WHERE role = 'admin' LIMIT 1")->fetch();

// Fetch school info
$schoolInfo = $db->query("SELECT school_name FROM settings LIMIT 1")->fetch();
$defaultSchoolName = $schoolInfo['school_name'] ?? 'SD NEGERI CONTOH';

// Parse actions data
$actions = [];
if ($record['actions_data']) {
    try {
        $actions = json_decode($record['actions_data'], true);
    } catch (Exception $e) {
        $actions = [];
    }
}

// Determine acknowledged person name and details
$acknowledgedName = '';
$acknowledgedNip = '';
$acknowledgedRole = '';

// Check what type of record this is first
if (!empty($record['student_name'])) {
    // This is a student record
    $acknowledgedRole = 'siswa';

    // Use student_name directly (it's already the student's full name from users table)
    $acknowledgedName = $record['student_name'];

} elseif (!empty($record['teacher_id'])) {
    // This is a teacher record
    $acknowledgedRole = 'guru';

    // PRIORITY 1: Use the joined full_name from users table
    if (!empty($record['full_name'])) {
        $acknowledgedName = $record['full_name'];
        $acknowledgedNip = $record['nip'] ?? '';
    }
    // PRIORITY 2: Check if acknowledged_by field has properly formatted data
    elseif (!empty($record['acknowledged_by'])) {
        $acknowledgedBy = $record['acknowledged_by'];

        // Only use acknowledged_by if it contains "NIP:" (properly formatted)
        if (strpos($acknowledgedBy, ' - NIP:') !== false) {
            // Split to get name and NIP separately
            $parts = explode(' - NIP:', $acknowledgedBy);
            $acknowledgedName = trim($parts[0]);
            if (isset($parts[1])) {
                $acknowledgedNip = trim($parts[1]);
            }
        } else {
            // acknowledged_by doesn't have proper format, ignore it
            // Try to get teacher data directly from users table as fallback
            $teacherStmt = $db->prepare("SELECT full_name, nip FROM users WHERE id = ? LIMIT 1");
            $teacherStmt->execute([$record['teacher_id']]);
            $teacherData = $teacherStmt->fetch();
            if ($teacherData) {
                $acknowledgedName = $teacherData['full_name'];
                $acknowledgedNip = $teacherData['nip'];
            }
        }
    }

} elseif (!empty($record['staff_name'])) {
    // This is other staff
    $acknowledgedRole = 'staff';
    $acknowledgedName = $record['staff_name'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catatan Khusus Kepala Sekolah - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.6;
            padding: 20mm;
            background: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            margin-bottom: 5px;
        }

        .info-label {
            width: 200px;
            font-weight: normal;
        }

        .info-value {
            flex: 1;
            border-bottom: 1px dotted #333;
        }

        .section {
            margin-top: 25px;
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 10px;
            text-decoration: underline;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 10px 0;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox {
            width: 16px;
            height: 16px;
            border: 2px solid #000;
            display: inline-block;
            vertical-align: middle;
        }

        .checkbox.checked::after {
            content: '✓';
            display: block;
            text-align: center;
            font-size: 14px;
            line-height: 12px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table,
        th,
        td {
            border: 1px solid #000;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .field-group {
            margin: 10px 0;
        }

        .field-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .field-value {
            padding: 5px;
            min-height: 40px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            white-space: pre-wrap;
        }

        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 45%;
        }

        .signature-line {
            margin-top: 80px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .impact-section {
            margin-left: 20px;
        }

        @media print {
            body {
                padding: 10mm;
            }

            .no-print {
                display: none !important;
            }
        }

        .print-button-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .btn-print {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-print:hover {
            background: #4338ca;
        }
    </style>
</head>

<body>
    <div class="print-button-container no-print">
        <button class="btn-print" onclick="window.print()">
            <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path
                    d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z" />
                <path
                    d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z" />
            </svg>
            Cetak / Print
        </button>
    </div>

    <div class="header">
        <h1>FORMAT CATATAN KHUSUS KEPALA SEKOLAH</h1>
        <h2>(ANECDOTAL RECORD)</h2>
    </div>

    <div class="info-row">
        <div class="info-label">SD</div>
        <div class="info-value"><?= htmlspecialchars($record['school_name'] ?? $defaultSchoolName) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">Tahun Ajaran</div>
        <div class="info-value"><?= htmlspecialchars($record['school_year'] ?? '') ?></div>
    </div>

    <!-- Section A: IDENTITAS -->
    <div class="section">
        <div class="section-title">A. IDENTIFIKASI</div>

        <?php if ($record['student_name']): ?>
            <div class="info-row">
                <div class="info-label">Nama Siswa</div>
                <div class="info-value"><?= htmlspecialchars($record['student_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Kelas</div>
                <div class="info-value"><?= htmlspecialchars($record['student_class'] ?? '') ?></div>
            </div>
        <?php elseif ($record['teacher_id']): ?>
            <div class="info-row">
                <div class="info-label">Nama Guru/Tenaga Kependidikan</div>
                <div class="info-value"><?= htmlspecialchars($record['full_name'] ?? '') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">NIP</div>
                <div class="info-value"><?= htmlspecialchars($record['nip'] ?? '') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Pangkat / Golongan</div>
                <div class="info-value"><?= htmlspecialchars($record['teacher_rank'] ?? '') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Masa Kerja</div>
                <div class="info-value"><?= htmlspecialchars($record['years_of_service'] ?? '') ?> Tahun</div>
            </div>
        <?php elseif ($record['staff_name']): ?>
            <div class="info-row">
                <div class="info-label">Nama</div>
                <div class="info-value"><?= htmlspecialchars($record['staff_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Jabatan/Posisi</div>
                <div class="info-value"><?= htmlspecialchars($record['staff_position'] ?? '') ?></div>
            </div>
        <?php endif; ?>

        <?php if ($record['subject_field']): ?>
            <div class="info-row">
                <div class="info-label">Bidang/Mata Pelajaran</div>
                <div class="info-value"><?= htmlspecialchars($record['subject_field']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section B: JENIS CATATAN -->
    <div class="section">
        <div class="section-title">B. JENIS CATATAN (centang salah satu)</div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox <?= $record['record_type'] === 'Prestasi/Penghargaan' ? 'checked' : '' ?>"></span>
                <span>Prestasi/Penghargaan</span>
            </div>
            <div class="checkbox-item">
                <span
                    class="checkbox <?= $record['record_type'] === 'Pelanggaran/Kekurangan' ? 'checked' : '' ?>"></span>
                <span>Pelanggaran/Kekurangan</span>
            </div>
            <div class="checkbox-item">
                <span class="checkbox <?= $record['record_type'] === 'Masalah/Problem' ? 'checked' : '' ?>"></span>
                <span>Masalah/Problem</span>
            </div>
        </div>
        <div class="checkbox-group">
            <div class="checkbox-item">
                <span class="checkbox <?= $record['record_type'] === 'Inovasi/Kreativitas' ? 'checked' : '' ?>"></span>
                <span>Inovasi/Kreativitas</span>
            </div>
            <div class="checkbox-item">
                <span class="checkbox <?= $record['record_type'] === 'Kejadian Istimewa' ? 'checked' : '' ?>"></span>
                <span>Kejadian Istimewa</span>
            </div>
        </div>
        <?php if ($record['record_type_other']): ?>
            <div class="info-row" style="margin-top: 10px;">
                <div class="info-label">Lainnya</div>
                <div class="info-value"><?= htmlspecialchars($record['record_type_other']) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section C: DESKRIPSI KEJADIAN -->
    <div class="section">
        <div class="section-title">C. DESKRIPSI KEJADIAN/FAKTA</div>
        <p style="font-style: italic; font-size: 11pt; margin-bottom: 10px;">
            (Tuliskan fakta objektif: tanggal, waktu, tempat, apa yang terjadi, siapa yang terlibat)
        </p>

        <div class="info-row">
            <div class="info-label">Tanggal/Waktu</div>
            <div class="info-value">
                <?= htmlspecialchars($record['event_date'] ?? '') ?>
                <?= $record['event_time'] ? 'pukul ' . htmlspecialchars($record['event_time']) : '' ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Lokasi</div>
            <div class="info-value"><?= htmlspecialchars($record['event_location'] ?? '') ?></div>
        </div>

        <div class="field-group">
            <div class="field-label">Uraian Kejadian:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['event_description'] ?? '')) ?></div>
        </div>
    </div>

    <!-- Section D: ANALISIS & INTERPRETASI -->
    <div class="section">
        <div class="section-title">D. ANALISIS & INTERPRETASI</div>
        <p style="font-style: italic; font-size: 11pt; margin-bottom: 10px;">
            (Analisis penyebab, dampak, dan makna kejadian tersebut)
        </p>

        <div class="field-group">
            <div class="field-label">Faktor Penyebab:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['analysis_cause'] ?? '')) ?></div>
        </div>

        <div class="field-group">
            <div class="field-label">Dampak/Implikasi:</div>
            <div class="impact-section">
                <div class="field-group">
                    <div class="field-label">Bagi individu:</div>
                    <div class="field-value"><?= nl2br(htmlspecialchars($record['impact_individual'] ?? '')) ?></div>
                </div>
                <div class="field-group">
                    <div class="field-label">Bagi tim/kelas:</div>
                    <div class="field-value"><?= nl2br(htmlspecialchars($record['impact_team'] ?? '')) ?></div>
                </div>
                <div class="field-group">
                    <div class="field-label">Bagi sekolah:</div>
                    <div class="field-value"><?= nl2br(htmlspecialchars($record['impact_school'] ?? '')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section E: TINDAKAN YANG SUDAH DILAKUKAN -->
    <div class="section">
        <div class="section-title">E. TINDAKAN YANG SUDAH DILAKUKAN</div>
        <p style="font-style: italic; font-size: 11pt; margin-bottom: 10px;">
            (Tindakan konkret yang telah dilakukan kepala sekolah menanggapi kejadian)
        </p>

        <table>
            <thead>
                <tr>
                    <th width="15%">Tanggal</th>
                    <th width="30%">Tindakan/Kegiatan</th>
                    <th width="25%">Pihak yang Terlibat</th>
                    <th width="30%">Hasil Sementara</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($actions) > 0): ?>
                    <?php foreach ($actions as $action): ?>
                        <tr>
                            <td><?= htmlspecialchars($action['date'] ?? '') ?></td>
                            <td><?= nl2br(htmlspecialchars($action['description'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($action['parties'] ?? '') ?></td>
                            <td><?= nl2br(htmlspecialchars($action['result'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="height: 80px;">&nbsp;</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Section F: REKOMENDASI -->
    <div class="section">
        <div class="section-title">F. REKOMENDASI/RENCANA TINDAK LANJUT</div>

        <div class="field-group">
            <div class="field-label">Untuk individu bersangkutan:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['recommendation_individual'] ?? '')) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Batas waktu</div>
            <div class="info-value"><?= htmlspecialchars($record['recommendation_deadline'] ?? '') ?></div>
        </div>

        <div class="field-group">
            <div class="field-label">Untuk pencegahan/pengembangan sistem:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['recommendation_system'] ?? '')) ?></div>
        </div>

        <div class="info-row">
            <div class="info-label">Penanggung jawab</div>
            <div class="info-value"><?= htmlspecialchars($record['recommendation_pic'] ?? '') ?></div>
        </div>
    </div>

    <!-- Section G: MONITORING & EVALUASI -->
    <div class="section">
        <div class="section-title">G. MONITORING & EVALUASI</div>

        <div class="info-row">
            <div class="info-label">Tanggal Monitoring</div>
            <div class="info-value"><?= htmlspecialchars($record['monitoring_date'] ?? '') ?></div>
        </div>

        <div class="field-group">
            <div class="field-label">Perkembangan:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['monitoring_progress'] ?? '')) ?></div>
        </div>

        <div class="field-group">
            <div class="field-label">Evaluasi:</div>
            <div class="checkbox-group">
                <div class="checkbox-item">
                    <span class="checkbox <?= $record['evaluation_status'] === 'Tuntas' ? 'checked' : '' ?>"></span>
                    <span>Tuntas</span>
                </div>
                <div class="checkbox-item">
                    <span
                        class="checkbox <?= $record['evaluation_status'] === 'Masih Berproses' ? 'checked' : '' ?>"></span>
                    <span>Masih Berproses</span>
                </div>
                <div class="checkbox-item">
                    <span
                        class="checkbox <?= $record['evaluation_status'] === 'Belum Tercapai' ? 'checked' : '' ?>"></span>
                    <span>Belum Tercapai</span>
                </div>
            </div>
        </div>

        <div class="field-group">
            <div class="field-label">Catatan Tambahan:</div>
            <div class="field-value"><?= nl2br(htmlspecialchars($record['additional_notes'] ?? '')) ?></div>
        </div>
    </div>

    <!-- Section H: PENUTUP -->
    <div class="section">
        <div class="section-title">H. PENUTUP</div>

        <div class="signature-area">
            <div class="signature-box">
                <p>Pembuat Catatan,</p>
                <p style="margin-top: 5px;">Kepala Sekolah</p>
                <div class="signature-line">
                    <strong><?= htmlspecialchars($principal['full_name'] ?? '') ?></strong><br>
                    NIP: <?= htmlspecialchars($principal['nip'] ?? '') ?>
                </div>
            </div>

            <div class="signature-box">
                <p>Telah diketahui</p>
                <p>oleh yang bersangkutan,</p>
                <div class="signature-line">
                    <strong>
                        <?= htmlspecialchars($acknowledgedName) ?>
                        <?php if ($acknowledgedRole === 'guru' && !empty($acknowledgedNip)): ?>
                            <br>NIP: <?= htmlspecialchars($acknowledgedNip) ?>
                        <?php endif; ?>
                    </strong><br>
                    <?php if ($acknowledgedRole === 'siswa'): ?>
                        (Siswa)
                    <?php elseif ($acknowledgedRole === 'guru'): ?>
                        (Guru/Tenaga Kependidikan)
                    <?php else: ?>
                        Nama & Tanda Tangan
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; text-align: right;">
            <div class="info-row">
                <div class="info-label">Tanggal</div>
                <div class="info-value">
                    <?= htmlspecialchars($record['acknowledged_date'] ?? $record['completion_date'] ?? '') ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
