<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: login.php');
    exit();
}

$db = new SQLite3('absen.db');

$user_id = $_SESSION['user_id'];
$date = date('Y-m-d');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis_izin = $_POST['jenis_izin'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';
    $foto = $_FILES['foto'] ?? null;

    if (!$jenis_izin) {
        $error = 'Jenis izin harus dipilih.';
    } elseif (!$foto || $foto['error'] !== UPLOAD_ERR_OK) {
        $error = 'Foto wajib diupload.';
    } else {
        $foto_path = null;
        $ext = pathinfo($foto['name'], PATHINFO_EXTENSION);
        $foto_path = 'uploads/izin_' . $user_id . '_' . time() . '.' . $ext;
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }
        move_uploaded_file($foto['tmp_name'], $foto_path);

        $stmt = $db->prepare('INSERT INTO izin (user_id, date, jenis_izin, keterangan, foto) VALUES (:user_id, :date, :jenis_izin, :keterangan, :foto)');
        $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        $stmt->bindValue(':date', $date, SQLITE3_TEXT);
        $stmt->bindValue(':jenis_izin', $jenis_izin, SQLITE3_TEXT);
        $stmt->bindValue(':keterangan', $keterangan, SQLITE3_TEXT);
        $stmt->bindValue(':foto', $foto_path, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result) {
            $success = 'Izin berhasil diajukan.';
        } else {
            $error = 'Gagal mengajukan izin.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Izin - Absen App</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">
    <div class="bg-white p-6 rounded shadow-md w-full max-w-md">
        <h1 class="text-xl font-bold mb-4">Form Izin</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST" action="izin.php" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label for="jenis_izin" class="block font-semibold mb-1">Jenis Izin</label>
                <select id="jenis_izin" name="jenis_izin" required class="w-full border border-gray-300 rounded px-3 py-2">
                    <option value="">Pilih Jenis Izin</option>
                    <option value="izin">Izin</option>
                    <option value="sakit">Sakit</option>
                </select>
            </div>
            <div>
                <label for="keterangan" class="block font-semibold mb-1">Keterangan</label>
                <textarea id="keterangan" name="keterangan" rows="3" class="w-full border border-gray-300 rounded px-3 py-2"></textarea>
            </div>
            <div>
                <label for="foto" class="block font-semibold mb-1">Foto (kamera/galeri)</label>
                <input type="file" accept="image/*" capture="environment" id="foto" name="foto" required class="w-full" />
            </div>
            <button type="submit" class="w-full bg-black text-white py-2 rounded hover:bg-gray-800 transition">Ajukan Izin</button>
        </form>
    </div>
</body>
</html>
