<?php
if (!isset($db) || !isset($user_id)) {
    return;
}

date_default_timezone_set('Asia/Jakarta');
$date = date('Y-m-d');
$time = date('H:i:s');

// Fetch settings
$stmt = $db->query("SELECT * FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$waktuMasukSetting = $settings['waktu_masuk'] ?? '07:00:00';
$waktuPulangSetting = $settings['waktu_pulang'] ?? '12:00:00';
$radiusSetting = $settings['radius'] ?? 100;
$waktuPulangOtomatisSetting = $settings['waktu_pulang_otomatis'] ?? $waktuPulangSetting;

$late = ($time > $waktuMasukSetting);

$error = '';
$success = '';
$disable_absen_masuk = false;
$disable_absen_pulang = false;
$toast_message = '';

// Ensure uploads directory exists
$uploadDir = dirname(__DIR__) . '/uploads/absensi';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Fetch attendance and izin status for today
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $date]);
$attendance_row = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT COUNT(*) FROM izin WHERE user_id = ? AND date = ?");
$stmt->execute([$user_id, $date]);
$izin_count = $stmt->fetchColumn();

if ($attendance_row) {
    if (!empty($attendance_row['jam_masuk']))
        $disable_absen_masuk = true;
    if (!empty($attendance_row['jam_pulang']))
        $disable_absen_pulang = true;
}

$izin_exists = $izin_count > 0;
if ($izin_exists) {
    $toast_message = 'Anda sudah melakukan izin hari ini.';
}

$absen_pulang_status = ($time < $waktuPulangSetting) ? 'Pulang Cepat' : 'Pulang Tepat Waktu';

// Auto Pulang Logic
// --- Bagian 1: Otomatis pulang jika waktu >= waktu_pulang_otomatis ---
if (strtotime($time) >= strtotime($waktuPulangOtomatisSetting) && !$disable_absen_pulang && $disable_absen_masuk) {
    // Safety Check: Only auto-pulang if they clocked in BEFORE the auto-pulang time.
    // If they clocked in LATE (after the auto-pulang time), valid 'Overtime' or Shift logic implies we shouldn't auto-close immediately.
    $should_auto_pulang = false;
    if (isset($attendance_row['jam_masuk']) && !empty($attendance_row['jam_masuk'])) {
        $masuk_ts = strtotime($attendance_row['jam_masuk']);
        $limit_ts = strtotime($waktuPulangOtomatisSetting);
        if ($masuk_ts < $limit_ts) {
            $should_auto_pulang = true;
        }
    }

    if ($should_auto_pulang) {
        // Randomize time: 0 to 30 minutes before the auto setting
        $rand_seconds = rand(0, 30 * 60);
        $final_auto_time = date('H:i:s', strtotime($waktuPulangOtomatisSetting) - $rand_seconds);

        // Calculate Duration for Auto Pulang
        $durasi_auto = null;
        if (isset($attendance_row['jam_masuk']) && !empty($attendance_row['jam_masuk'])) {
            $start_time = new DateTime($attendance_row['jam_masuk']);
            $end_time = new DateTime($final_auto_time);
            $interval = $start_time->diff($end_time);
            $durasi_auto = $interval->format('%H:%I');
        }

        $stmt = $db->prepare("UPDATE attendance SET jam_pulang = ?, keterangan = ?, durasi = ? WHERE user_id = ? AND date = ? AND jam_pulang IS NULL");
        $stmt->execute([$final_auto_time, 'Otomatis Pulang (Sistem)', $durasi_auto, $user_id, $date]);

        // Check if row updated
        if ($stmt->rowCount() > 0) {
            $disable_absen_pulang = true;
            $toast_message = "Sistem otomatis mengisi jam pulang Anda hari ini pada pukul $final_auto_time karena melewati batas waktu.";
        }
    }
}

// --- Bagian 2: Perbaiki data absensi terlewat (Past Open Sessions) ---
if (!$disable_absen_masuk) {
    // Cari semua record masa lalu (sebelum hari ini) yang belum ada jam_pulang
    $stmtMissing = $db->prepare("
        SELECT date, jam_masuk FROM attendance
        WHERE user_id = ?
          AND date < ?
          AND jam_pulang IS NULL
    ");
    $stmtMissing->execute([$user_id, $date]);

    $auto_filled_dates = [];
    while ($row = $stmtMissing->fetch(PDO::FETCH_ASSOC)) {
        $missingDate = $row['date'];

        // Randomize time: 0 to 30 minutes before the auto setting
        $rand_seconds = rand(0, 30 * 60);
        $final_auto_time = date('H:i:s', strtotime($waktuPulangOtomatisSetting) - $rand_seconds);

        // Calculate Duration for Missing Date
        $durasi_missing = null;
        if (!empty($row['jam_masuk'])) {
            $start_time = new DateTime($row['jam_masuk']);
            $end_time = new DateTime($final_auto_time); // Use randomized time
            $interval = $start_time->diff($end_time);
            $durasi_missing = $interval->format('%H:%I');
        }

        // Update setiap tanggal yang belum absen pulang
        $stmtUpdate = $db->prepare("
            UPDATE attendance
            SET jam_pulang = ?, keterangan = 'Tidak Absen Pulang', durasi = ?
            WHERE user_id = ? AND date = ?
        ");
        if ($stmtUpdate->execute([$final_auto_time, $durasi_missing, $user_id, $missingDate])) {
            if ($stmtUpdate->rowCount() > 0) {
                $auto_filled_dates[] = date('d M', strtotime($missingDate));
            }
        }
    }

    if (!empty($auto_filled_dates)) {
        $date_str = implode(', ', $auto_filled_dates);
        $msg = "Absen terlewati pada tanggal $date_str telah diisi otomatis.";
        $toast_message = $toast_message ? $toast_message . ' ' . $msg : $msg;
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'absen_masuk') {
        if ($disable_absen_masuk) {
            $error = 'Anda sudah melakukan absen masuk hari ini.';
        } elseif ($izin_exists) {
            $error = 'Anda sudah melakukan izin hari ini.';
        } else {
            $foto = $_FILES['foto'] ?? null;
            $lokasi_lat = $_POST['lokasi_lat'] ?? null;
            $lokasi_lng = $_POST['lokasi_lng'] ?? null;
            $jarak = floatval($_POST['jarak'] ?? 0);

            if (empty($lokasi_lat) || empty($lokasi_lng)) {
                $error = 'Lokasi harus dikirim.';
            } elseif ($jarak > $radiusSetting) {
                $error = 'Anda berada di luar radius ' . $radiusSetting . ' meter.';
            } else {
                $foto_path = null;
                if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
                    $filename = 'masuk_' . $user_id . '_' . time() . '.' . $ext;
                    $uploadDir = dirname(__DIR__) . '/uploads/absensi';
                    $filepath = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                        $foto_path = 'uploads/absensi/' . $filename;
                    }
                }

                $status = $late ? 'Terlambat' : 'Tepat Waktu';
                $keterangan = $status;

                $stmt = $db->prepare("INSERT INTO attendance (user_id, date, jam_masuk, jam_pulang, status, keterangan, lokasi_lat, lokasi_lng, jarak, foto_masuk) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $date, $time, $status, $keterangan, $lokasi_lat, $lokasi_lng, $jarak, $foto_path])) {
                    $success = 'Absen Masuk berhasil disimpan.';
                    $disable_absen_masuk = true;
                    $disable_absen_pulang = false;
                    // Refresh data
                    $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
                    $stmt->execute([$user_id, $date]);
                    $attendance_row = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Gagal menyimpan data.';
                }
            }
        }
    } elseif ($action === 'absen_pulang') {
        // Refresh attendance data to ensure we have the latest status
        $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$user_id, $date]);
        $current_attn = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($disable_absen_pulang) {
            $error = 'Anda sudah absen pulang.';
        } elseif ($izin_exists) {
            $error = 'Anda izin hari ini.';
        } elseif (!$current_attn || empty($current_attn['jam_masuk'])) {
            $error = 'Anda belum melakukan absen masuk. Silakan absen masuk terlebih dahulu.';
        } else {
            $foto = $_FILES['foto'] ?? null;
            $lokasi_lat = $_POST['lokasi_lat'] ?? null;
            $lokasi_lng = $_POST['lokasi_lng'] ?? null;
            $jarak = floatval($_POST['jarak'] ?? 0);

            if (empty($lokasi_lat)) {
                $error = 'Lokasi harus dikirim.';
            } elseif ($jarak > $radiusSetting) {
                $error = 'Di luar radius.';
            } else {
                $foto_path = null;
                if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
                    $filename = 'pulang_' . $user_id . '_' . time() . '.' . $ext;
                    $uploadDir = dirname(__DIR__) . '/uploads/absensi';
                    $filepath = $uploadDir . '/' . $filename;
                    if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                        $foto_path = 'uploads/absensi/' . $filename;
                    }
                }

                $keterangan = ($time < $waktuPulangSetting) ? 'Pulang Cepat' : 'Pulang Tepat Waktu';

                // Calculate Duration
                $durasi = null;
                if (!empty($attendance_row['jam_masuk'])) {
                    $start_time = new DateTime($attendance_row['jam_masuk']);
                    $end_time = new DateTime($time);
                    $interval = $start_time->diff($end_time);
                    $durasi = $interval->format('%H:%I'); // Format HH:MM
                }

                $stmt = $db->prepare("UPDATE attendance SET jam_pulang = ?, foto_pulang = ?, keterangan = ?, lokasi_lat = ?, lokasi_lng = ?, jarak = ?, durasi = ? WHERE user_id = ? AND date = ?");
                if ($stmt->execute([$time, $foto_path, $keterangan, $lokasi_lat, $lokasi_lng, $jarak, $durasi, $user_id, $date])) {
                    $success = 'Absen Pulang berhasil disimpan.';
                    $disable_absen_pulang = true;
                    // Refresh data
                    $stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
                    $stmt->execute([$user_id, $date]);
                    $attendance_row = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Gagal menyimpan.';
                }
            }
        }
    } elseif ($action === 'izin') {
        $tanggal_izin = $_POST['tanggal_izin'] ?? null;
        $alasan_izin = $_POST['alasan_izin'] ?? null;
        $lokasi_lat = $_POST['lokasi_lat'] ?? null;
        $lokasi_lng = $_POST['lokasi_lng'] ?? null;
        $jarak = floatval($_POST['jarak'] ?? 0);
        $foto = $_FILES['foto'] ?? null;

        if ($tanggal_izin && $alasan_izin && $lokasi_lat) {
            $foto_path = null;
            if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
                $filename = 'izin_' . $user_id . '_' . time() . '.' . $ext;
                $uploadDir = dirname(__DIR__) . '/uploads/absensi';
                $filepath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($foto['tmp_name'], $filepath)) {
                    $foto_path = 'uploads/absensi/' . $filename;
                }
            }

            $stmt = $db->prepare("INSERT INTO izin (user_id, date, jenis_izin, keterangan, foto, status, lokasi_lat, lokasi_lng, jarak) VALUES (?, ?, 'izin', ?, ?, 'approved', ?, ?, ?)");
            if ($stmt->execute([$user_id, $tanggal_izin, $alasan_izin, $foto_path, $lokasi_lat, $lokasi_lng, $jarak])) {
                $success = 'Izin berhasil diajukan.';
                $izin_exists = true;
            } else {
                $error = 'Gagal mengajukan izin.';
            }
        } else {
            $error = 'Data tidak lengkap.';
        }
    }
}

// Rekap Data (Simple list for user)
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 5");
$stmt->execute([$user_id]);
$rekap_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
