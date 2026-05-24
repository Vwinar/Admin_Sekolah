<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

// Fetch all students
$sql = "SELECT id, full_name, profile_photo as photo, assigned_class as class FROM users WHERE role = 'siswa'";
$params = [];

if ($_SESSION['role'] === 'guru') {
    $guruClass = getGuruClass($_SESSION['user_id']);
    if ($guruClass) {
        $sql .= " AND assigned_class = ?";
        $params[] = $guruClass;
    } else {
        $sql .= " AND 1=0";
    }
}

$sql .= " ORDER BY full_name";
$stmt = $laporanPdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title
$pageTitle = "Literasi dan Numerasi - Admin Dashboard";

// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
        background-color: white;
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .profile-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        margin-bottom: 1rem;
        border: 3px solid #f8f9fa;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .nav-tabs .nav-link.active {
        background-color: #4361ee;
        color: white;
        font-weight: 600;
    }

    .nav-tabs .nav-link {
        color: #4361ee;
        font-weight: 500;
    }
</style>

<div class="container py-4">
    <div class="row g-4">
        <?php if (empty($students)): ?>
            <div class="col-12">
                <div class="card text-center p-5">
                    <i class="fas fa-user-graduate text-muted mb-3" style="font-size: 3rem;"></i>
                    <h4 class="text-muted">Belum ada siswa terdaftar</h4>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card text-center p-4"
                        onclick="window.location.href='view_literasi_numerasi.php?id=<?php echo $student['id']; ?>'">
                        <?php
                        $photoFilename = isset($student['photo']) ? trim($student['photo']) : '';
                        // Use the same path logic as view_student.php
                        $photoPath = $photoFilename ? '../../laporan_harian/uploads/profile/' . $photoFilename : '';
                        $absolutePath = $photoFilename ? __DIR__ . '/../../laporan_harian/uploads/profile/' . $photoFilename : '';

                        if ($photoFilename && file_exists($absolutePath)) {
                            $imgSrc = $photoPath;
                        } else {
                            $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=' . substr(md5($student['id']), 0, 6);
                        }
                        ?>
                        <img src="<?php echo $imgSrc; ?>" class="profile-image"
                            alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                        <h5 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h5>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'footer.php'; ?>