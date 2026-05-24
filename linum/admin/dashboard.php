<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

// Set page title
$pageTitle = "Dashboard - Sistem Laporan Resume";

// Fetch all students and their summaries
// Fetch all students and their summaries
// Connection to Laporan DB is $laporanPdo. Connection to Linum DB is $pdo.

// 1. Fetch Students from Laporan Harian DB
$sqlUsers = "SELECT id, full_name, profile_photo as photo, assigned_class as class FROM users WHERE role = 'siswa'";
$paramsUsers = [];

if ($_SESSION['role'] === 'guru') {
    $guruClass = getGuruClass($_SESSION['user_id']); // This function currently looks at local file functions.php which checks Laporan DB.
    // Ensure getGuruClass uses the correct path to Laporan DB or we query here directly.
    // getGuruClass in functions.php actually makes a NEW PDO connection. That works but is inefficient.
    // Since we have $laporanPdo, let's use it or trust the function.
    // Let's manually filter here for consistency with new $laporanPdo
    if ($guruClass) {
        $sqlUsers .= " AND assigned_class = ?";
        $paramsUsers[] = $guruClass;
    } else {
        $sqlUsers .= " AND 1=0"; // No class assigned
    }
}
$sqlUsers .= " ORDER BY full_name";

$stmtUsers = $laporanPdo->prepare($sqlUsers);
$stmtUsers->execute($paramsUsers);
$studentsRaw = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Summary Counts from Linum DB
$sqlSummaries = "SELECT user_id, COUNT(*) as count FROM summaries GROUP BY user_id";
$stmtSummaries = $pdo->query($sqlSummaries);
$summaryCounts = $stmtSummaries->fetchAll(PDO::FETCH_KEY_PAIR); // [user_id => count]

// 3. Merge Data
$students = [];
foreach ($studentsRaw as $s) {
    $s['summary_count'] = isset($summaryCounts[$s['id']]) ? $summaryCounts[$s['id']] : 0;
    $students[] = $s;
}


// Include common header
require_once 'include_header.php';
?>

<style>
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }

    .student-card {
        cursor: pointer;
        border-left: 4px solid var(--primary-color);
    }

    .student-image {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 1.5rem;
    }

    .welcome-card {
        background: linear-gradient(135deg, #4361ee 0%, #3f37c9 100%);
        color: white;
        border-radius: 12px;
    }

    .welcome-card .card-title {
        font-weight: 600;
    }


    .summary-count {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
    }

    .student-name {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    @media (max-width: 768px) {
        .student-image {
            width: 80px;
            height: 80px;
        }
    }
</style>

<div class="container py-4">
    <!-- Welcome Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="welcome-card p-4">
                <div
                    class="d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start">
                    <div class="mb-3 mb-md-0">
                        <h2 class="card-title mb-2 display-6 fw-bold" style="font-size: clamp(1.5rem, 4vw, 2rem);">
                            <i class="fas fa-hand-wave me-2"></i>Selamat Datang,
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </h2>
                        <p class="mb-0 opacity-75 fs-6">Anda login sebagai Administrator</p>
                    </div>
                    <div class="text-center text-md-end">
                        <h4 class="mb-0 fw-bold" style="font-size: clamp(1.2rem, 3vw, 1.5rem);">
                            <i class="fas fa-users me-2"></i><?php echo count($students); ?> Siswa
                        </h4>
                        <small class="opacity-75">Total siswa terdaftar</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Grid -->
    <div class="row g-3 g-md-4">
        <?php if (empty($students)): ?>
            <div class="col-12">
                <div class="card text-center p-5">
                    <i class="fas fa-user-graduate text-muted mb-3" style="font-size: 3rem;"></i>
                    <h4 class="text-muted">Belum ada siswa terdaftar</h4>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($students as $student): ?>
                <div class="col-6 col-md-6 col-lg-4 col-xl-3">
                    <div class="card student-card h-100 position-relative">
                        <div class="card-body text-center p-3 p-md-4">
                            <?php
                            // Photo is from Laporan Harian uploads
                            $photoPath = isset($student['photo']) && $student['photo'] ? '../../laporan_harian/uploads/profile/' . $student['photo'] : '';
                            if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)) {
                                $imgSrc = $photoPath;
                            } else {
                                $imgSrc = 'https://ui-avatars.com/api/?name=' . urlencode($student['full_name']) . '&background=' . substr(md5($student['id']), 0, 6);
                            }
                            ?>
                            <img src="<?php echo $imgSrc; ?>" class="student-image mb-3"
                                alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                            <h5 class="student-name text-truncate w-100 mb-2" style="font-size: clamp(1rem, 2.5vw, 1.25rem);">
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </h5>
                            <div class="summary-count mb-3 fs-6">
                                <i class="fas fa-book me-2"></i><?php echo $student['summary_count']; ?> <span
                                    class="d-none d-sm-inline">Rangkuman</span>
                            </div>
                            <a href="view_student.php?id=<?php echo $student['id']; ?>"
                                class="chip-btn chip-btn-outline-blue chip-btn-sm w-100 stretched-link">
                                <i class="fas fa-eye me-1"></i> Detail
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<script>
    // Add animation to cards when they come into view
    document.addEventListener('DOMContentLoaded', function () {
        const cards = document.querySelectorAll('.student-card');

        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
        });

        setTimeout(() => {
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }, 300);
    });
</script>
<?php require_once 'footer.php'; ?>