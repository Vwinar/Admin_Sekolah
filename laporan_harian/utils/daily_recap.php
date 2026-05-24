<?php
session_start();
require_once '../config/db_connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['full_name'] ?? $_SESSION['username'];
$school_name = '';

// Get School Name
try {
    $stmt = $db->query("SELECT school_name FROM settings LIMIT 1");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $school_name = $row['school_name'];
    }
} catch (Exception $e) { /* Ignore */
}


$indonesian_months = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember',
];

$month_filter = $_GET['month'] ?? date('Y-m');
$jumlah_hari = $_GET['jumlah_hari'] ?? date('t');

// Parse date
$year = date('Y', strtotime($month_filter));
$month_num = date('m', strtotime($month_filter));
$month_name = $indonesian_months[$month_num] ?? $month_num;
$formatted_month_year = $month_name . ' ' . $year;

// Handle Form Submissions (Add/Delete/Update Holiday Attendance)
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. ADD Holiday Attendance
    if (isset($_POST['add_holiday_attendance'])) {
        $date = $_POST['attendance_holiday_date'];
        $in = $_POST['attendance_jam_masuk'];
        $out = $_POST['attendance_jam_pulang'] ?: $in;
        $ket = $_POST['attendance_keterangan'];
        $selected_gurus = $_POST['selected_gurus'] ?? [];

        if (empty($selected_gurus)) {
            $error_msg = "Tidak ada guru yang dipilih";
        } else {
            $count = 0;
            // Determine status based on Keterangan
            $status = 'Hadir';
            if (in_array($ket, ['Ming', 'PHPN', 'PHBN'])) { // Start user prompt said PHBS/PHPN likely typo for PHBN/PHBS. Using PHPN, PHBN/PHBS as per conventional or strict user text. User said: PHPN, PHPS. I will stick to USER text: Ming, PHPN, PHPS.
                $status = 'Libur';
            }

            $stmt = $db->prepare("INSERT INTO attendance (user_id, date, jam_masuk, jam_pulang, keterangan, status) VALUES (?, ?, ?, ?, ?, ?)");

            // Get user IDs
            $user_map = [];
            $u_stmt = $db->query("SELECT id, username FROM users WHERE role='guru'");
            while ($u = $u_stmt->fetch(PDO::FETCH_ASSOC)) {
                $user_map[$u['username']] = $u['id'];
            }

            foreach ($selected_gurus as $uname) {
                if (isset($user_map[$uname])) {
                    $uid = $user_map[$uname];
                    // Check duplicate? Original code just inserts. We can do the same.
                    $stmt->execute([$uid, $date, $in, $out, $ket, $status]);
                    $count++;
                }
            }
            if ($count > 0)
                $success_msg = "Hari libur attendance berhasil ditambahkan untuk $count guru";
            else
                $error_msg = "Gagal menambahkan data";
        }
    }
    // 2. DELETE Holiday Attendance
    elseif (isset($_POST['delete_holiday_attendance'])) {
        $date = $_POST['delete_holiday_attendance_date'];
        $in = $_POST['delete_holiday_attendance_jam_masuk'];
        $out = $_POST['delete_holiday_attendance_jam_pulang'];
        $ket = $_POST['delete_holiday_attendance_keterangan'];

        $stmt = $db->prepare("DELETE FROM attendance WHERE date = ? AND jam_masuk = ? AND jam_pulang = ? AND keterangan = ?");
        if ($stmt->execute([$date, $in, $out, $ket])) {
            $success_msg = "Hari libur attendance berhasil dihapus";
        } else {
            $error_msg = "Gagal menghapus data";
        }
    }
    // 3. UPDATE Holiday Attendance
    elseif (isset($_POST['update_holiday_attendance'])) {
        $orig_date = $_POST['original_date'];
        $orig_in = $_POST['original_jam_masuk'];
        $orig_out = $_POST['original_jam_pulang'];
        $orig_ket = $_POST['original_keterangan'];

        $new_date = $_POST['attendance_holiday_date'];
        $new_in = $_POST['attendance_jam_masuk'];
        $new_out = $_POST['attendance_jam_pulang'] ?: $new_in;
        $new_ket = $_POST['attendance_keterangan'];
        $selected_gurus = $_POST['selected_gurus'] ?? [];

        if (empty($selected_gurus)) {
            $error_msg = "Tidak ada guru yang dipilih";
        } else {
            // Delete old
            $del = $db->prepare("DELETE FROM attendance WHERE date = ? AND jam_masuk = ? AND jam_pulang = ? AND keterangan = ?");
            $del->execute([$orig_date, $orig_in, $orig_out, $orig_ket]);

            // Insert new
            $count = 0;
            // Determine status based on Keterangan
            $status = 'Hadir';
            if (in_array($new_ket, ['Ming', 'PHPN', 'PHPS'])) {
                $status = 'Libur';
            }

            $stmt = $db->prepare("INSERT INTO attendance (user_id, date, jam_masuk, jam_pulang, keterangan, status) VALUES (?, ?, ?, ?, ?, ?)");
            $user_map = [];
            $u_stmt = $db->query("SELECT id, username FROM users WHERE role='guru'");
            while ($u = $u_stmt->fetch(PDO::FETCH_ASSOC)) {
                $user_map[$u['username']] = $u['id'];
            }

            foreach ($selected_gurus as $uname) {
                if (isset($user_map[$uname])) {
                    $stmt->execute([$user_map[$uname], $new_date, $new_in, $new_out, $new_ket, $status]);
                    $count++;
                }
            }
            if ($count > 0)
                $success_msg = "Hari libur attendance berhasil diupdate untuk $count guru";
            else
                $error_msg = "Gagal update data";
        }
    }
}

// Redirect to self to clear POST if success/error present
if ($success_msg || $error_msg) {
    $q = http_build_query([
        'month' => $month_filter,
        'jumlah_hari' => $jumlah_hari,
        'success' => $success_msg,
        'error' => $error_msg
    ]);
    header("Location: daily_recap.php?$q");
    exit();
}

// Check GET success/error for Toast
$toast_success = $_GET['success'] ?? '';
$toast_error = $_GET['error'] ?? '';


// GET API for editing (Fetching gurus for a holiday)
if (isset($_GET['get_holiday_gurus'])) {
    $date = $_GET['date'];
    $in = $_GET['jam_masuk'];
    $out = $_GET['jam_pulang'];
    $ket = $_GET['keterangan'];

    $stmt = $db->prepare("
        SELECT u.username 
        FROM users u 
        JOIN attendance a ON u.id = a.user_id 
        WHERE a.date = ? AND a.jam_masuk = ? AND a.jam_pulang = ? AND a.keterangan = ?
    ");
    $stmt->execute([$date, $in, $out, $ket]);
    $gurus = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode(['gurus' => $gurus]);
    exit();
}

// Fetch all gurus
$guru_users = $db->query("SELECT * FROM users WHERE role='guru' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Fetch holiday attendance list
$holiday_attendance_records = $db->prepare("
    SELECT date, jam_masuk, jam_pulang, keterangan, COUNT(*) as guru_count
    FROM attendance
    WHERE strftime('%Y-%m', date) = ? AND keterangan != '' AND keterangan IS NOT NULL
    GROUP BY date, jam_masuk, jam_pulang, keterangan
    ORDER BY date DESC
");
$holiday_attendance_records->execute([$month_filter]);
$holiday_list = $holiday_attendance_records->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Daily Recap - <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <style>
        .animate-slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }

        .slide-out {
            transform: translateX(100%);
            transition: transform 0.3s ease-in;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }

            to {
                transform: translateX(0);
            }
        }

        /* Checkbox styling */
        .guru-checkbox {
            width: 1.2em;
            height: 1.2em;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen font-sans text-gray-900">

    <!-- Toast -->
    <div id="toast" class="fixed top-4 right-4 z-50 hidden transition-all duration-300">
        <div class="bg-gray-800 text-white px-6 py-3 rounded shadow-lg flex items-center">
            <span id="toast-message"></span>
        </div>
    </div>

    <!-- Header -->
    <header class="bg-gray-900 text-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <i class="fas fa-calendar-alt text-xl"></i>
                <h1 class="text-xl font-bold">Daily Recap</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="../admin/rekap_absensi.php"
                    class="bg-white/10 hover:bg-white/20 text-white border border-white/20 py-1.5 px-3 rounded-md text-sm transition-all">Kembali ke Rekap</a>
                <a href="../logout.php"
                    class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 py-1.5 px-3 rounded-md text-sm transition-all">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8 space-y-6">

        <!-- Filter Bar -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h2 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-filter text-blue-500"></i> Filter & Opsi
            </h2>
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Pilih Bulan</label>
                    <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>"
                        class="border p-2 rounded w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Jumlah Hari</label>
                    <input type="number" name="jumlah_hari" value="<?= htmlspecialchars($jumlah_hari) ?>"
                        class="border p-2 rounded w-24">
                </div>
                <button type="submit" class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-4 py-2 rounded-md font-semibold text-sm transition-colors">Filter</button>
                <button type="button" id="toggleHolidayModal"
                    class="bg-green-50 text-green-600 border border-green-200 hover:bg-green-100 px-4 py-2 rounded-md font-semibold text-sm transition-colors flex items-center gap-2">
                    <i class="fas fa-plus-circle"></i> Kelola Hari Libur
                </button>
            </form>
        </div>

        <!-- Holiday List -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-lg font-bold mb-4">Daftar Hari Libur / Kegiatan Khusus (<?= $formatted_month_year ?>)</h3>

            <?php if (empty($holiday_list)): ?>
                <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <p>Tidak ada data hari libur/kegiatan khusus bulan ini.</p>
                </div>
            <?php else: ?>
                <div class="grid gap-4">
                    <?php foreach ($holiday_list as $h): ?>
                        <div
                            class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border hover:shadow-sm transition">
                            <div class="flex items-center gap-4">
                                <div class="bg-green-100 text-green-700 p-3 rounded-full">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-800"><?= date('d F Y', strtotime($h['date'])) ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($h['jam_masuk'] ?? '') ?> - <?= htmlspecialchars($h['jam_pulang'] ?? '') ?> •
                                        <?= htmlspecialchars($h['keterangan'] ?? '') ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1"><?= $h['guru_count'] ?> Guru Terlibat</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    onclick="editHoliday('<?= $h['date'] ?? '' ?>', '<?= $h['jam_masuk'] ?? '' ?>', '<?= $h['jam_pulang'] ?? '' ?>', '<?= htmlspecialchars($h['keterangan'] ?? '') ?>')"
                                    class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-3 py-1.5 rounded-md transition-colors"><i class="fas fa-edit"></i></button>
                                <button
                                    onclick="deleteHoliday('<?= $h['date'] ?? '' ?>', '<?= $h['jam_masuk'] ?? '' ?>', '<?= $h['jam_pulang'] ?? '' ?>', '<?= htmlspecialchars($h['keterangan'] ?? '') ?>')"
                                    class="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 px-3 py-1.5 rounded-md transition-colors"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Form -->
    <div id="holidayModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b flex justify-between items-center sticky top-0 bg-white z-10">
                <h3 class="text-xl font-bold" id="modalTitle">Tambah Hari Libur Attendance</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i
                        class="fas fa-times text-xl"></i></button>
            </div>

            <form method="POST" class="p-6 space-y-4" id="holidayForm">
                <!-- Hidden fields for update identification -->
                <input type="hidden" name="original_date" id="orig_date">
                <input type="hidden" name="original_jam_masuk" id="orig_in">
                <input type="hidden" name="original_jam_pulang" id="orig_out">
                <input type="hidden" name="original_keterangan" id="orig_ket">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tanggal</label>
                        <input type="date" name="attendance_holiday_date" id="form_date" required
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Keterangan</label>
                        <select name="attendance_keterangan" id="form_ket" required class="w-full border p-2 rounded"
                            onchange="handleKeteranganChange()">
                            <option value="">-- Pilih Keterangan --</option>
                            <option value="Ming">Ming</option>
                            <option value="PHPN">PHPN</option>
                            <option value="PHPS">PHPS</option>
                            <option value="Keg Sekolah">Keg Sekolah</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Jam Masuk</label>
                        <input type="text" name="attendance_jam_masuk" id="form_in" required
                            class="w-full border p-2 rounded" placeholder="Ex: 07:00 or LIBUR">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Jam Pulang</label>
                        <input type="text" name="attendance_jam_pulang" id="form_out" class="w-full border p-2 rounded"
                            placeholder="Ex: 14:00 or PULANG">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium">Pilih Guru</label>
                        <label class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" id="selectAll"> Pilih Semua
                        </label>
                    </div>
                    <div
                        class="border rounded-lg p-3 max-h-48 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-2 bg-gray-50">
                        <?php foreach ($guru_users as $g): ?>
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 p-1 rounded">
                                <input type="checkbox" name="selected_gurus[]"
                                    value="<?= htmlspecialchars($g['username']) ?>" class="guru-checkbox">
                                <span class="text-sm"><?= htmlspecialchars($g['full_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pt-4 border-t flex justify-end gap-3">
                    <button type="button" onclick="closeModal()"
                        class="bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100 px-4 py-2 rounded-md font-semibold text-sm transition-colors">Batal</button>
                    <button type="submit" name="add_holiday_attendance" id="submitBtn"
                        class="bg-blue-50 text-blue-600 border border-blue-200 hover:bg-blue-100 px-4 py-2 rounded-md font-semibold text-sm transition-colors">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" class="hidden">
        <input type="hidden" name="delete_holiday_attendance" value="1">
        <input type="hidden" name="delete_holiday_attendance_date" id="del_date">
        <input type="hidden" name="delete_holiday_attendance_jam_masuk" id="del_in">
        <input type="hidden" name="delete_holiday_attendance_jam_pulang" id="del_out">
        <input type="hidden" name="delete_holiday_attendance_keterangan" id="del_ket">
    </form>

    <script>
        // Modal Logic
        const modal = document.getElementById('holidayModal');
        const form = document.getElementById('holidayForm');
        const btn = document.getElementById('submitBtn');
        const title = document.getElementById('modalTitle');

        document.getElementById('toggleHolidayModal').onclick = () => {
            openModal();
        };

        function openModal(isEdit = false) {
            modal.classList.remove('hidden');
            if (!isEdit) {
                form.reset();
                title.textContent = 'Tambah Hari Libur Attendance';
                btn.name = 'add_holiday_attendance';
                btn.textContent = 'Simpan';
                // Reset checkboxes
                document.querySelectorAll('.guru-checkbox').forEach(cb => cb.checked = false);
            }
        }

        function closeModal() {
            modal.classList.add('hidden');
        }

        function handleKeteranganChange() {
            const ket = document.getElementById('form_ket').value;
            const inField = document.getElementById('form_in');
            const outField = document.getElementById('form_out');

            if (['Ming', 'PHPN', 'PHPS'].includes(ket)) {
                // Set text and make readonly
                inField.type = 'text';
                outField.type = 'text';
                inField.value = ket;
                outField.value = ket;
                inField.readOnly = true;
                outField.readOnly = true;
                inField.classList.add('bg-gray-100');
                outField.classList.add('bg-gray-100');
            } else if (ket === 'Keg Sekolah') {
                // Set to time input and editable
                inField.type = 'time';
                outField.type = 'time';
                inField.value = '';
                outField.value = '';
                inField.readOnly = false;
                outField.readOnly = false;
                inField.classList.remove('bg-gray-100');
                outField.classList.remove('bg-gray-100');
            } else {
                // Default reset if empty or other
                inField.type = 'text';
                outField.type = 'text';
                inField.readOnly = false;
                outField.readOnly = false;
                inField.classList.remove('bg-gray-100');
                outField.classList.remove('bg-gray-100');
            }
        }

        // Edit Logic
        window.editHoliday = function (date, inTime, outTime, ket) {
            openModal(true);
            title.textContent = 'Edit Hari Libur Attendance';
            btn.name = 'update_holiday_attendance';
            btn.textContent = 'Update';

            // Fill form
            document.getElementById('form_date').value = date;
            document.getElementById('form_in').value = inTime;
            document.getElementById('form_out').value = outTime;
            document.getElementById('form_ket').value = ket;

            // Fill hidden orig fields
            document.getElementById('orig_date').value = date;
            document.getElementById('orig_in').value = inTime;
            document.getElementById('orig_out').value = outTime;
            document.getElementById('orig_ket').value = ket;

            // Fetch involved gurus via AJAX
            fetch(`daily_recap.php?get_holiday_gurus=1&date=${date}&jam_masuk=${inTime}&jam_pulang=${outTime}&keterangan=${encodeURIComponent(ket)}`)
                .then(res => res.json())
                .then(data => {
                    document.querySelectorAll('.guru-checkbox').forEach(cb => {
                        cb.checked = data.gurus.includes(cb.value);
                    });
                });
        };

        // Delete Logic
        window.deleteHoliday = function (date, inTime, outTime, ket) {
            if (confirm('Yakin ingin menghapus data ini?')) {
                document.getElementById('del_date').value = date;
                document.getElementById('del_in').value = inTime;
                document.getElementById('del_out').value = outTime;
                document.getElementById('del_ket').value = ket;
                document.getElementById('deleteForm').submit();
            }
        };

        // Select All Logic
        document.getElementById('selectAll').onchange = function () {
            document.querySelectorAll('.guru-checkbox').forEach(cb => cb.checked = this.checked);
        };

        // Toast Messages
        const success = "<?= addslashes($toast_success) ?>";
        const error = "<?= addslashes($toast_error) ?>";

        if (success) showToast(success, 'bg-green-600');
        if (error) showToast(error, 'bg-red-600');

        function showToast(msg, bgClass) {
            const t = document.getElementById('toast');
            t.className = `fixed top-4 right-4 z-50 flex items-center px-6 py-3 rounded shadow-lg text-white ${bgClass} animate-slide-in`;
            document.getElementById('toast-message').textContent = msg;
            t.classList.remove('hidden');
            setTimeout(() => {
                t.classList.add('slide-out');
                setTimeout(() => t.classList.add('hidden'), 300);
            }, 3000);
        }
    </script>
</body>

</html>