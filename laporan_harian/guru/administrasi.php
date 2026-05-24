<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$view = $_GET['view'] ?? 'main';

// Helper: Get Teacher & Class
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$teacher = $stmt->fetch();
$my_class = $teacher['assigned_class'];

// Helper: Get Students
$stmt_students = $db->prepare("SELECT * FROM students WHERE class_name = ? ORDER BY name ASC");
$stmt_students->execute([$my_class]);
$my_students = $stmt_students->fetchAll();

// --- SETUP NEW TABLES (Lazy Init) ---
$db->exec("CREATE TABLE IF NOT EXISTS class_seating_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT,
    layout_data TEXT
)");
$db->exec("CREATE TABLE IF NOT EXISTS class_study_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    class_name TEXT,
    group_name TEXT,
    student_ids TEXT
)");

// --- HANDLE SUBMISSIONS ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMU
    if ($action === 'add_guest') {
        $guest_name = $_POST['guest_name'];
        $purpose = $_POST['purpose'];
        $stmt = $db->prepare("INSERT INTO class_guest_book (class_name, guest_name, purpose, teacher_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$my_class, $guest_name, $purpose, $user_id]);
        $msg = "Tamu berhasil dicatat.";
    } elseif ($action === 'update_guest') {
        $stmt = $db->prepare("UPDATE class_guest_book SET guest_name=?, purpose=? WHERE id=?");
        $stmt->execute([$_POST['guest_name'], $_POST['purpose'], $_POST['id']]);
        $msg = "Data tamu berhasil diperbarui.";
    } elseif ($action === 'delete_guest') {
        $stmt = $db->prepare("DELETE FROM class_guest_book WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data tamu berhasil dihapus.";
    }
    // 2. INVENTARIS
    elseif ($action === 'add_inventory') {
        $stmt = $db->prepare("INSERT INTO class_inventory (class_name, item_name, quantity, condition, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$my_class, $_POST['item_name'], $_POST['quantity'], $_POST['condition'], $_POST['notes']]);
        $msg = "Barang berhasil ditambahkan.";
    } elseif ($action === 'update_inventory') {
        $stmt = $db->prepare("UPDATE class_inventory SET item_name=?, quantity=?, condition=?, notes=? WHERE id=?");
        $stmt->execute([$_POST['item_name'], $_POST['quantity'], $_POST['condition'], $_POST['notes'], $_POST['id']]);
        $msg = "Data barang berhasil diperbarui.";
    } elseif ($action === 'delete_inventory') {
        $stmt = $db->prepare("DELETE FROM class_inventory WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data barang berhasil dihapus.";
    }
    // 3. MUTASI
    elseif ($action === 'add_mutation') {
        $stmt = $db->prepare("INSERT INTO student_mutation (student_id, type, date, reason, from_school, to_school) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['date'], $_POST['reason'], $_POST['from_school'], $_POST['to_school']]);
        $msg = "Data mutasi berhasil disimpan.";
    } elseif ($action === 'update_mutation') {
        $stmt = $db->prepare("UPDATE student_mutation SET student_id=?, type=?, date=?, reason=?, from_school=?, to_school=? WHERE id=?");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['date'], $_POST['reason'], $_POST['from_school'], $_POST['to_school'], $_POST['id']]);
        $msg = "Data mutasi berhasil diperbarui.";
    } elseif ($action === 'delete_mutation') {
        $stmt = $db->prepare("DELETE FROM student_mutation WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data mutasi berhasil dihapus.";
    }
    // 4. PRESTASI / PELANGGARAN
    elseif ($action === 'add_note') {
        $stmt = $db->prepare("INSERT INTO student_notes (student_id, type, category, description, date, point) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['category'], $_POST['description'], $_POST['date'], $_POST['point'] ?? 0]);
        $msg = "Catatan berhasil disimpan.";
    } elseif ($action === 'update_note') {
        $stmt = $db->prepare("UPDATE student_notes SET student_id=?, type=?, category=?, description=?, date=? WHERE id=?");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['category'], $_POST['description'], $_POST['date'], $_POST['id']]);
        $msg = "Catatan berhasil diperbarui.";
    } elseif ($action === 'delete_note') {
        $stmt = $db->prepare("DELETE FROM student_notes WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Catatan berhasil dihapus.";
    }
    // 5. DETAIL SISWA (Update)
    elseif ($action === 'update_student_detail') {
        $sid = $_POST['student_id'];
        // Check if exists
        $check = $db->prepare("SELECT student_id FROM student_details WHERE student_id = ?");
        $check->execute([$sid]);
        if ($check->fetch()) {
            $sql = "UPDATE student_details SET nis=?, birth_place=?, birth_date=?, address=?, parent_name=?, parent_contact=? WHERE student_id=?";
            $start_idx = 0;
        } else {
            $sql = "INSERT INTO student_details (nis, birth_place, birth_date, address, parent_name, parent_contact, student_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        }
        $stmt = $db->prepare($sql);
        $stmt->execute([$_POST['nis'], $_POST['birth_place'], $_POST['birth_date'], $_POST['address'], $_POST['parent_name'], $_POST['parent_contact'], $sid]);
        $msg = "Data siswa berhasil diperbarui.";
    }
    // 6. KESEHATAN
    elseif ($action === 'save_health') {
        $sid = $_POST['student_id'];
        $check = $db->prepare("SELECT id FROM student_health WHERE student_id = ?");
        $check->execute([$sid]);
        if ($check->fetch()) {
            $stmt = $db->prepare("UPDATE student_health SET history=?, allergy=?, vaccination=?, recent_illness=? WHERE student_id=?");
        } else {
            $stmt = $db->prepare("INSERT INTO student_health (history, allergy, vaccination, recent_illness, student_id) VALUES (?, ?, ?, ?, ?)");
        }
        $stmt->execute([$_POST['history'], $_POST['allergy'], $_POST['vaccination'], $_POST['recent_illness'], $sid]);
        $msg = "Data kesehatan berhasil disimpan.";
    }
    // 7. BUKU
    elseif ($action === 'add_book') {
        $stmt = $db->prepare("INSERT INTO class_books (class_name, type, title, author, publisher, subject) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$my_class, $_POST['type'], $_POST['title'], $_POST['author'], $_POST['publisher'], $_POST['subject']]);
        $msg = "Buku berhasil ditambahkan.";
    } elseif ($action === 'update_book') {
        $stmt = $db->prepare("UPDATE class_books SET type=?, title=?, author=?, publisher=?, subject=? WHERE id=?");
        $stmt->execute([$_POST['type'], $_POST['title'], $_POST['author'], $_POST['publisher'], $_POST['subject'], $_POST['id']]);
        $msg = "Data buku berhasil diperbarui.";
    } elseif ($action === 'delete_book') {
        $stmt = $db->prepare("DELETE FROM class_books WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data buku berhasil dihapus.";
    }
    // 8. BIMBINGAN / KONSULTASI
    elseif ($action === 'add_consultation') {
        $stmt = $db->prepare("INSERT INTO consultations (student_id, type, date, problem, solution) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['date'], $_POST['problem'], $_POST['solution']]);
        $msg = "Data konsultasi berhasil disimpan.";
    } elseif ($action === 'update_consultation') {
        $stmt = $db->prepare("UPDATE consultations SET student_id=?, type=?, date=?, problem=?, solution=? WHERE id=?");
        $stmt->execute([$_POST['student_id'], $_POST['type'], $_POST['date'], $_POST['problem'], $_POST['solution'], $_POST['id']]);
        $msg = "Data konsultasi berhasil diperbarui.";
    } elseif ($action === 'delete_consultation') {
        $stmt = $db->prepare("DELETE FROM consultations WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data konsultasi berhasil dihapus.";
    }
    // 9. PIKET
    elseif ($action === 'save_schedule') {
        $student_names = isset($_POST['student_names']) ? implode(', ', $_POST['student_names']) : '';
        // Check if exists, delete first to overwrite (simulates update)
        $check = $db->prepare("DELETE FROM cleaning_schedule WHERE class_name = ? AND day_name = ?");
        $check->execute([$my_class, $_POST['day_name']]);

        $stmt = $db->prepare("INSERT INTO cleaning_schedule (class_name, day_name, student_names) VALUES (?, ?, ?)");
        $stmt->execute([$my_class, $_POST['day_name'], $student_names]);
        $msg = "Jadwal piket berhasil disimpan.";
    } elseif ($action === 'delete_schedule') {
        $stmt = $db->prepare("DELETE FROM cleaning_schedule WHERE class_name = ? AND day_name = ?");
        $stmt->execute([$my_class, $_POST['day_name']]);
        $msg = "Jadwal piket berhasil dihapus.";
    }
    // 10. UPLOAD FILE (Denah / Bukti)
    elseif ($action === 'upload_file') {
        // Simplified file upload logic (just storing path if implemented, here placeholder)
        $msg = "Fitur upload file akan diimplementasikan penuh nanti.";
    }
    // 11. ANALISIS UTS
    elseif ($action === 'add_exam_analysis') {
        $data = json_encode(['avg' => $_POST['avg'], 'target' => $_POST['target'], 'absorption' => $_POST['absorption']]);
        $stmt = $db->prepare("INSERT INTO exam_analysis (class_name, semester, subject, type, data_values) VALUES (?, ?, ?, 'UTS', ?)");
        $stmt->execute([$my_class, $_POST['semester'], $_POST['subject'], $data]);
        $msg = "Analisis nilai berhasil disimpan.";
    } elseif ($action === 'update_exam_analysis') {
        $data = json_encode(['avg' => $_POST['avg'], 'target' => $_POST['target'], 'absorption' => $_POST['absorption']]);
        $stmt = $db->prepare("UPDATE exam_analysis SET semester=?, subject=?, data_values=? WHERE id=?");
        $stmt->execute([$_POST['semester'], $_POST['subject'], $data, $_POST['id']]);
        $msg = "Analisis nilai berhasil diperbarui.";
    } elseif ($action === 'delete_exam_analysis') {
        $stmt = $db->prepare("DELETE FROM exam_analysis WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Analisis nilai berhasil dihapus.";
    }
    // 12. BAKAT / EKSTRAKURIKULER
    elseif ($action === 'add_activity') {
        $stmt = $db->prepare("INSERT INTO student_activities (student_id, activity_name, role, achievement) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['activity_name'], $_POST['role'], $_POST['achievement']]);
        $msg = "Data kegiatan siswa berhasil disimpan.";
    } elseif ($action === 'update_activity') {
        $stmt = $db->prepare("UPDATE student_activities SET student_id=?, activity_name=?, role=?, achievement=? WHERE id=?");
        $stmt->execute([$_POST['student_id'], $_POST['activity_name'], $_POST['role'], $_POST['achievement'], $_POST['id']]);
        $msg = "Data kegiatan berhasil diperbarui.";
    } elseif ($action === 'delete_activity') {
        $stmt = $db->prepare("DELETE FROM student_activities WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Data kegiatan berhasil dihapus.";
    }
    // 13. TATA TERTIB
    elseif ($action === 'add_rule') {
        $stmt = $db->prepare("INSERT INTO class_rules (class_name, content) VALUES (?, ?)");
        $stmt->execute([$my_class, $_POST['content']]);
        $msg = "Aturan kelas berhasil ditambahkan.";
    } elseif ($action === 'update_rule') {
        $stmt = $db->prepare("UPDATE class_rules SET content=? WHERE id=?");
        $stmt->execute([$_POST['content'], $_POST['id']]);
        $msg = "Aturan kelas berhasil diperbarui.";
    } elseif ($action === 'delete_rule') {
        $stmt = $db->prepare("DELETE FROM class_rules WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Aturan kelas berhasil dihapus.";
    }
    // 14. DENAH DUDUK
    elseif ($action === 'save_seating') {
        $layout_data = json_encode($_POST['seats'] ?? []);
        // Check if exists
        $check = $db->prepare("SELECT id FROM class_seating_plans WHERE class_name = ?");
        $check->execute([$my_class]);
        if ($check->fetch()) {
            $stmt = $db->prepare("UPDATE class_seating_plans SET layout_data = ? WHERE class_name = ?");
            $stmt->execute([$layout_data, $my_class]);
        } else {
            $stmt = $db->prepare("INSERT INTO class_seating_plans (class_name, layout_data) VALUES (?, ?)");
            $stmt->execute([$my_class, $layout_data]);
        }
        $msg = "Denah tempat duduk berhasil disimpan.";
    }
    // 15. KELOMPOK BELAJAR
    elseif ($action === 'add_group') {
        $sids = isset($_POST['student_ids']) ? implode(',', $_POST['student_ids']) : '';
        $stmt = $db->prepare("INSERT INTO class_study_groups (class_name, group_name, student_ids) VALUES (?, ?, ?)");
        $stmt->execute([$my_class, $_POST['group_name'], $sids]);
        $msg = "Kelompok belajar berhasil ditambahkan.";
    } elseif ($action === 'update_group') {
        $sids = isset($_POST['student_ids']) ? implode(',', $_POST['student_ids']) : '';
        $stmt = $db->prepare("UPDATE class_study_groups SET group_name=?, student_ids=? WHERE id=?");
        $stmt->execute([$_POST['group_name'], $sids, $_POST['id']]);
        $msg = "Kelompok belajar berhasil diperbarui.";
    } elseif ($action === 'delete_group') {
        $stmt = $db->prepare("DELETE FROM class_study_groups WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Kelompok belajar berhasil dihapus.";
    }
    // 16. NILAI SISWA (STUDENT GRADES)
    elseif ($action === 'add_grade') {
        $stmt = $db->prepare("INSERT INTO student_grades (student_id, subject, semester, exam_type, nilai, ranking, year) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['subject'], $_POST['semester'], $_POST['exam_type'], $_POST['nilai'], $_POST['ranking'] ?? null, $_POST['year']]);
        $msg = "Nilai siswa berhasil disimpan.";
    } elseif ($action === 'update_grade') {
        $stmt = $db->prepare("UPDATE student_grades SET student_id=?, subject=?, semester=?, exam_type=?, nilai=?, ranking=?, year=? WHERE id=?");
        $stmt->execute([$_POST['student_id'], $_POST['subject'], $_POST['semester'], $_POST['exam_type'], $_POST['nilai'], $_POST['ranking'] ?? null, $_POST['year'], $_POST['id']]);
        $msg = "Nilai siswa berhasil diperbarui.";
    } elseif ($action === 'delete_grade') {
        $stmt = $db->prepare("DELETE FROM student_grades WHERE id=?");
        $stmt->execute([$_POST['id']]);
        $msg = "Nilai siswa berhasil dihapus.";
    }
    // 17. ADMINISTRASI GURU (Kurikulum Merdeka)
    elseif ($action === 'upload_admin_guru') {
        $uploadDir = '../uploads/administrasi/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        $file_path = '';
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['file_upload']['name']);
            move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadDir . $fileName);
            $file_path = $uploadDir . $fileName;
        }

        // Use school_documents table with type='admin_guru'
        // Fields: title (Doc Type), class_name, related_user_id, file_path, description, type
        $stmt = $db->prepare("INSERT INTO school_documents (type, title, class_name, related_user_id, file_path, description) VALUES ('admin_guru', ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['doc_type'], $my_class, $user_id, $file_path, $_POST['description']]);
        $msg = "Dokumen administrasi berhasil diupload.";

    } elseif ($action === 'edit_admin_guru') {
        $uploadDir = '../uploads/administrasi/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0777, true);

        // Update fields
        $sql = "UPDATE school_documents SET title = ?, description = ?";
        $params = [$_POST['doc_type'], $_POST['description']];

        // Handle File Update
        if (isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['file_upload']['name']);
            move_uploaded_file($_FILES['file_upload']['tmp_name'], $uploadDir . $fileName);
            $file_path = $uploadDir . $fileName;

            // Delete old file if exists
            $stmt = $db->prepare("SELECT file_path FROM school_documents WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $oldRow = $stmt->fetch();
            if ($oldRow && file_exists($oldRow['file_path'])) {
                unlink($oldRow['file_path']);
            }

            $sql .= ", file_path = ?";
            $params[] = $file_path;
        }

        $sql .= " WHERE id = ? AND related_user_id = ?";
        $params[] = $_POST['id'];
        $params[] = $user_id;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $msg = "Dokumen berhasil diperbarui.";

    } elseif ($action === 'delete_admin_guru') {
        // Get file path to delete file
        $stmt = $db->prepare("SELECT file_path FROM school_documents WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $row = $stmt->fetch();
        if ($row && file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }

        $stmt = $db->prepare("DELETE FROM school_documents WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $msg = "Dokumen administrasi berhasil dihapus.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrasi Kelas</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        /* PDF Modal Styles */
        #pdfModal {
            display: none;
            position: fixed;
            /* ... (keep existing) ... */
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(5px);
        }

        /* Admin Grid & Cards */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
        }

        .admin-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .admin-card-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            width: 40px;
            height: 40px;
            background: #eef2ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .admin-card h3 {
            font-size: 1rem;
            margin: 0 0 0.25rem 0;
            color: var(--text-dark);
            font-weight: 600;
        }

        .admin-card p {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0;
            line-height: 1.4;
        }

        .section-title {
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .section-title .btn {
            width: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: var(--text-muted);
        }

        /* Form & Button Styles */
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .form-section {
            background: #f9fafb;
            padding: 0.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .action-buttons .btn {
            border-radius: 6px;
            padding: 0.35rem 0.8rem;
            font-weight: 600;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }

        .action-buttons .btn-primary {
            background-color: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .action-buttons .btn-primary:hover {
            background-color: #2563eb;
            color: white;
            border-color: #2563eb;
            transform: translateY(-1px);
        }

        .action-buttons .btn-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .action-buttons .btn-danger:hover {
            background-color: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .header {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: flex-start !important;
                gap: 1rem !important;
                margin-bottom: 1.5rem !important;
            }

            .header h1 {
                font-size: 1.25rem !important;
                line-height: 1.2;
                margin: 0 !important;
            }

            .header p {
                font-size: 0.85rem !important;
                margin: 2px 0 0 0 !important;
            }

            .header>div {
                text-align: left !important;
                flex: 1;
                width: auto !important;
            }

            /* Hide the extra wrapper in case it was cached/stuck */
            .header .sidebar-toggle {
                margin-bottom: 0 !important;
            }

            /* Force 2 columns on mobile */
            .admin-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .admin-card {
                padding: 1rem;
            }

            .admin-card h3 {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body class="dashboard-page">
    <div class="dashboard-layout">
        <?php include '../layout/user_sidebar.php'; ?>

        <main class="main-content">
            <header class="header">
                <button class="sidebar-toggle" id="sidebarToggle"><span></span><span></span><span></span></button>
                <div style="flex: 1; min-width: 0;">
                    <h1>Administrasi Kelas <?= htmlspecialchars($my_class) ?></h1>
                    <p style="color: var(--text-muted)">Pusat pengelolaan dan arsip kelas.</p>
                </div>
            </header>

            <?php if ($msg): ?>
                <div class="alert alert-success"
                    style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:1rem;">
                    <?= $msg ?>
                </div>
            <?php endif; ?>

            <?php if ($view === 'main'): ?>
                <!-- DATA POKOK -->
                <div class="section-title">1. Data Pokok & Kehadiran</div>
                <div class="admin-grid">
                    <a href="?view=data_siswa" class="admin-card">
                        <div class="admin-card-icon">👨‍🎓</div>
                        <h3>Data Lengkap Siswa</h3>
                        <p>NIS, Alamat, Orang Tua, Kontak.</p>
                    </a>
                    <a href="?view=mutasi" class="admin-card">
                        <div class="admin-card-icon">🔄</div>
                        <h3>Buku Mutasi</h3>
                        <p>Siswa Masuk, Keluar, Lulus.</p>
                    </a>
                    <a href="?view=catatan" class="admin-card">
                        <div class="admin-card-icon">📝</div>
                        <h3>Prestasi & Pelanggaran</h3>
                        <p>Catatan perilaku akademik/non-akademik.</p>
                    </a>
                    <a href="?view=kesehatan" class="admin-card">
                        <div class="admin-card-icon">🏥</div>
                        <h3>Riwayat Kesehatan</h3>
                        <p>Rekam medis ringan siswa.</p>
                    </a>
                    <a href="?view=tamu" class="admin-card">
                        <div class="admin-card-icon">📖</div>
                        <h3>Buku Tamu Kelas</h3>
                        <p>Daftar kunjungan ke kelas.</p>
                    </a>
                </div>

                <!-- AKADEMIK -->
                <div class="section-title">2. Akademik & Penilaian</div>
                <div class="admin-grid">
                    <a href="?view=nilai" class="admin-card">
                        <div class="admin-card-icon">💯</div>
                        <h3>Nilai Siswa</h3>
                        <p>Input nilai UTS, UAS, Rapor.</p>
                    </a>
                    <a href="?view=analisis" class="admin-card">
                        <div class="admin-card-icon">📊</div>
                        <h3>Analisis & Nilai</h3>
                        <p>Analisis UTS dan Rekap Nilai.</p>
                    </a>
                    <a href="?view=bakat" class="admin-card">
                        <div class="admin-card-icon">⭐</div>
                        <h3>Minat & Bakat</h3>
                        <p>Pengembangan diri siswa.</p>
                    </a>
                </div>

                <!-- PENGELOLAAN KELAS -->
                <div class="section-title">3. Pengelolaan Kelas</div>
                <div class="admin-grid">
                    <a href="?view=inventaris" class="admin-card">
                        <div class="admin-card-icon">🪑</div>
                        <h3>Inventaris Kelas</h3>
                        <p>Daftar aset dan kondisi barang.</p>
                    </a>
                    <a href="?view=denah" class="admin-card">
                        <div class="admin-card-icon">💺</div>
                        <h3>Denah Tempat Duduk</h3>
                        <p>Layout dan posisi siswa.</p>
                    </a>
                    <a href="?view=kelompok" class="admin-card">
                        <div class="admin-card-icon">👥</div>
                        <h3>Kelompok Belajar</h3>
                        <p>Pembagian kelompok diskusi.</p>
                    </a>
                    <a href="?view=piket" class="admin-card">
                        <div class="admin-card-icon">🧹</div>
                        <h3>Jadwal Piket</h3>
                        <p>Petugas kebersihan harian.</p>
                    </a>
                    <a href="?view=rules" class="admin-card">
                        <div class="admin-card-icon">📄</div>
                        <h3>Tata Tertib</h3>
                        <p>Aturan dan kesepakatan kelas.</p>
                    </a>
                </div>

                <!-- BAHAN AJAR -->
                <div class="section-title">4. Bahan Ajar & Bimbingan</div>
                <div class="admin-grid">
                    <a href="?view=buku" class="admin-card">
                        <div class="admin-card-icon">📚</div>
                        <h3>Buku Pegangan</h3>
                        <p>Buku Guru dan Siswa.</p>
                    </a>
                    <a href="?view=bimbingan" class="admin-card">
                        <div class="admin-card-icon">🤝</div>
                        <h3>Bimbingan Konseling</h3>
                        <p>Catatan konsultasi siswa/ortu.</p>
                    </a>
                </div>

                <!-- ADMINISTRASI -->
                <div class="section-title">5. Administrasi Guru</div>
                <div class="admin-grid">
                    <a href="?view=administrasi_kumer" class="admin-card">
                        <div class="admin-card-icon">📂</div>
                        <h3>Administrasi Kurikulum Merdeka</h3>
                        <p>CP, TP, ATP, Modul Ajar, dll.</p>
                    </a>
                </div>

            <?php // --- VIEW: DATA SISWA ---
            elseif ($view === 'data_siswa'):
                $students_details = $db->prepare("
                    SELECT s.*, d.nis, d.birth_place, d.birth_date, d.address, d.parent_name, d.parent_contact 
                    FROM students s 
                    LEFT JOIN student_details d ON s.id = d.student_id 
                    WHERE s.class_name = ? ORDER BY s.name ASC
                ");
                $students_details->execute([$my_class]);
                $list = $students_details->fetchAll();
                ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Data Lengkap Siswa</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>NIS</th>
                                    <th>TTL</th>
                                    <th>Ortu</th>
                                    <th>Kontak</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($list as $i => $s): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($s['name']) ?></td>
                                        <td><?= htmlspecialchars($s['nis'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s['birth_place'] ?? '-') . ', ' . ($s['birth_date'] ? date('d-m-Y', strtotime($s['birth_date'])) : '-') ?>
                                        </td>
                                        <td><?= htmlspecialchars($s['parent_name'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($s['parent_contact'] ?? '-') ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editStudent(<?= json_encode($s) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Modal (Simplified as a hidden form for now or inline JS) -->
                <div id="editForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc;">
                    <h3>Edit Data Siswa</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_student_detail">
                        <input type="hidden" name="student_id" id="edit_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <input type="text" name="nis" id="edit_nis" placeholder="NIS" class="form-control">
                            <input type="text" name="birth_place" id="edit_bp" placeholder="Tempat Lahir"
                                class="form-control">
                            <input type="date" name="birth_date" id="edit_bd" placeholder="Tgl Lahir" class="form-control">
                            <input type="text" name="address" id="edit_addr" placeholder="Alamat" class="form-control">
                            <input type="text" name="parent_name" id="edit_pn" placeholder="Nama Ortu" class="form-control">
                            <input type="text" name="parent_contact" id="edit_pc" placeholder="Kontak" class="form-control">
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>
                <script>
                    function editStudent(data) {
                        document.getElementById('editForm').style.display = 'block';
                        document.getElementById('edit_id').value = data.id || data.student_id;
                        document.getElementById('edit_nis').value = data.nis || '';
                        document.getElementById('edit_bp').value = data.birth_place || '';
                        document.getElementById('edit_bd').value = data.birth_date || '';
                        document.getElementById('edit_addr').value = data.address || '';
                        document.getElementById('edit_pn').value = data.parent_name || '';
                        document.getElementById('edit_pc').value = data.parent_contact || '';
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: MUTASI ---
            elseif ($view === 'mutasi'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Buku Mutasi Siswa</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_mutation">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div>
                                <label>Siswa</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($my_students as $s)
                                        echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label>Jenis Mutasi</label>
                                <select name="type" class="form-control" required>
                                    <option value="Masuk">Masuk (Pindahan)</option>
                                    <option value="Keluar">Keluar (Pindah)</option>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Drop Out">Drop Out</option>
                                </select>
                            </div>
                            <div>
                                <label>Tanggal</label>
                                <input type="date" name="date" class="form-control" required>
                            </div>
                            <div>
                                <label>Alasan</label>
                                <input type="text" name="reason" class="form-control">
                            </div>
                            <div>
                                <label>Dari Sekolah (Jika Masuk)</label>
                                <input type="text" name="from_school" class="form-control">
                            </div>
                            <div>
                                <label>Ke Sekolah (Jika Keluar)</label>
                                <input type="text" name="to_school" class="form-control">
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Mutasi</button>
                    </form>

                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Siswa</th>
                                    <th>Jenis</th>
                                    <th>Alasan</th>
                                    <th>Detail</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stats = $db->prepare("SELECT m.*, s.name, s.id as student_id FROM student_mutation m JOIN students s ON m.student_id = s.id WHERE s.class_name = ? ORDER BY m.date DESC");
                                $stats->execute([$my_class]);
                                foreach ($stats->fetchAll() as $row): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><span class="badge badge-warning"><?= $row['type'] ?></span></td>
                                        <td><?= htmlspecialchars($row['reason']) ?></td>
                                        <td><?= $row['type'] == 'Masuk' ? 'Dari: ' . htmlspecialchars($row['from_school']) : 'Ke: ' . htmlspecialchars($row['to_school']) ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editMutation(<?= json_encode($row) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delMutForm', 'del_mut_id', <?= $row['id'] ?>, 'Hapus data mutasi <?= htmlspecialchars($row['name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Mutation Modal -->
                <div id="editMutForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Data Mutasi</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_mutation">
                        <input type="hidden" name="id" id="mut_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div>
                                <label>Siswa</label>
                                <select name="student_id" id="edit_mut_sid" class="form-control" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($my_students as $s)
                                        echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label>Jenis Mutasi</label>
                                <select name="type" id="edit_mut_type" class="form-control" required>
                                    <option value="Masuk">Masuk (Pindahan)</option>
                                    <option value="Keluar">Keluar (Pindah)</option>
                                    <option value="Lulus">Lulus</option>
                                    <option value="Drop Out">Drop Out</option>
                                </select>
                            </div>
                            <div>
                                <label>Tanggal</label>
                                <input type="date" name="date" id="edit_mut_date" class="form-control" required>
                            </div>
                            <div>
                                <label>Alasan</label>
                                <input type="text" name="reason" id="edit_mut_reason" class="form-control">
                            </div>
                            <div>
                                <label>Dari Sekolah (Jika Masuk)</label>
                                <input type="text" name="from_school" id="edit_mut_from" class="form-control">
                            </div>
                            <div>
                                <label>Ke Sekolah (Jika Keluar)</label>
                                <input type="text" name="to_school" id="edit_mut_to" class="form-control">
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editMutForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delMutForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_mutation">
                    <input type="hidden" name="id" id="del_mut_id">
                </form>

                <script>
                    function editMutation(data) {
                        document.getElementById('editMutForm').style.display = 'block';
                        document.getElementById('mut_id').value = data.id;
                        document.getElementById('edit_mut_sid').value = data.student_id;
                        document.getElementById('edit_mut_type').value = data.type;
                        document.getElementById('edit_mut_date').value = data.date;
                        document.getElementById('edit_mut_reason').value = data.reason;
                        document.getElementById('edit_mut_from').value = data.from_school;
                        document.getElementById('edit_mut_to').value = data.to_school;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: CATATAN (PRESTASI / PELANGGARAN) ---
            elseif ($view === 'catatan'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Catatan Prestasi & Pelanggaran</h2> <a href="?view=main"
                            class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_note">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div><label>Siswa</label><select name="student_id" class="form-control" required><?php foreach ($my_students as $s)
                                echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select></div>
                            <div><label>Jenis</label><select name="type" class="form-control">
                                    <option value="Prestasi">Prestasi</option>
                                    <option value="Pelanggaran">Pelanggaran</option>
                                </select></div>
                            <div><label>Tanggal</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"
                                    class="form-control"></div>
                            <div><label>Kategori</label><input type="text" name="category"
                                    placeholder="Akademik / Tata Tertib" class="form-control"></div>
                            <div style="grid-column: span 2"><label>Deskripsi</label><textarea name="description"
                                    class="form-control" rows="2"></textarea></div>
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan Catatan</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Siswa</th>
                                    <th>Jenis</th>
                                    <th>Deskripsi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $notes = $db->prepare("SELECT n.*, s.name FROM student_notes n JOIN students s ON n.student_id = s.id WHERE s.class_name = ? ORDER BY n.date DESC");
                                $notes->execute([$my_class]);
                                foreach ($notes->fetchAll() as $n): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($n['date'])) ?></td>
                                        <td><?= htmlspecialchars($n['name']) ?></td>
                                        <td><span class="badge"
                                                style="background:<?= $n['type'] == 'Prestasi' ? '#d1fae5' : '#fee2e2' ?>; color:<?= $n['type'] == 'Prestasi' ? '#065f46' : '#991b1b' ?>"><?= $n['type'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($n['description']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editNote(<?= json_encode($n) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delNoteForm', 'del_note_id', <?= $n['id'] ?>, 'Hapus catatan <?= htmlspecialchars($n['name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Note Modal -->
                <div id="editNoteForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Catatan</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_note">
                        <input type="hidden" name="id" id="note_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div><label>Siswa</label><select name="student_id" id="edit_note_sid" class="form-control"
                                    required><?php foreach ($my_students as $s)
                                        echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?></select>
                            </div>
                            <div><label>Jenis</label><select name="type" id="edit_note_type" class="form-control">
                                    <option value="Prestasi">Prestasi</option>
                                    <option value="Pelanggaran">Pelanggaran</option>
                                </select></div>
                            <div><label>Tanggal</label><input type="date" name="date" id="edit_note_date"
                                    class="form-control">
                            </div>
                            <div><label>Kategori</label><input type="text" name="category" id="edit_note_cat"
                                    placeholder="Akademik / Tata Tertib" class="form-control"></div>
                            <div style="grid-column: span 2"><label>Deskripsi</label><textarea name="description"
                                    id="edit_note_desc" class="form-control" rows="2"></textarea></div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editNoteForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delNoteForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_note">
                    <input type="hidden" name="id" id="del_note_id">
                </form>

                <script>
                    function editNote(data) {
                        document.getElementById('editNoteForm').style.display = 'block';
                        document.getElementById('note_id').value = data.id;
                        document.getElementById('edit_note_sid').value = data.student_id;
                        document.getElementById('edit_note_type').value = data.type;
                        document.getElementById('edit_note_date').value = data.date;
                        document.getElementById('edit_note_cat').value = data.category;
                        document.getElementById('edit_note_desc').value = data.description;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: KESEHATAN ---
            elseif ($view === 'kesehatan'):
                $healths = $db->prepare("SELECT s.id, s.name, h.history, h.allergy, h.vaccination, h.recent_illness FROM students s LEFT JOIN student_health h ON s.id = h.student_id WHERE s.class_name = ? ORDER BY s.name ASC");
                $healths->execute([$my_class]);
                $h_list = $healths->fetchAll();
                ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Riwayat Kesehatan Siswa</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Riwayat Penyakit</th>
                                    <th>Alergi</th>
                                    <th>Vaksinasi</th>
                                    <th>Sakit Terakhir</th>
                                    <th>Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($h_list as $h): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($h['name']) ?></td>
                                        <td><?= htmlspecialchars($h['history'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($h['allergy'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($h['vaccination'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($h['recent_illness'] ?? '-') ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editHealth(<?= json_encode($h) ?>)'
                                                    class="btn btn-primary btn-sm">Update</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div id="healthForm"
                        style="display:none; margin-top:20px; background:#f9fafb; padding:20px; border-radius:8px;">
                        <h3>Update Kesehatan</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_health">
                            <input type="hidden" name="student_id" id="h_sid">
                            <p id="h_sname" style="font-weight:bold;"></p>
                            <label>Riwayat Penyakit:</label><input type="text" name="history" id="h_hist"
                                class="form-control">
                            <label>Alergi:</label><input type="text" name="allergy" id="h_all" class="form-control">
                            <label>Vaksinasi:</label><input type="text" name="vaccination" id="h_vac" class="form-control">
                            <label>Sakit Terakhir:</label><input type="text" name="recent_illness" id="h_rec"
                                class="form-control">
                            <br><button type="submit" class="btn btn-primary">Simpan</button>
                            <button type="button" onclick="document.getElementById('healthForm').style.display='none'"
                                class="btn btn-secondary">Batal</button>
                        </form>
                    </div>
                    <script>
                        function editHealth(data) {
                            document.getElementById('healthForm').style.display = 'block';
                            document.getElementById('h_sid').value = data.id;
                            document.getElementById('h_sname').innerText = "Siswa: " + data.name;
                            document.getElementById('h_hist').value = data.history || '';
                            document.getElementById('h_all').value = data.allergy || '';
                            document.getElementById('h_vac').value = data.vaccination || '';
                            document.getElementById('h_rec').value = data.recent_illness || '';
                            window.scrollTo(0, document.body.scrollHeight);
                        }
                    </script>
                </div>

            <?php // --- VIEW: BUKU ---
            elseif ($view === 'buku'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Daftar Buku Pegangan</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_book">
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                            <select name="type" class="form-control">
                                <option value="Guru">Buku Guru</option>
                                <option value="Siswa">Buku Siswa</option>
                            </select>
                            <input type="text" name="title" placeholder="Judul Buku" class="form-control" required>
                            <input type="text" name="subject" placeholder="Mata Pelajaran" class="form-control">
                            <input type="text" name="author" placeholder="Penulis" class="form-control">
                            <input type="text" name="publisher" placeholder="Penerbit" class="form-control">
                        </div>
                        <br><button type="submit" class="btn btn-primary">Tambah Buku</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Judul</th>
                                    <th>Mapel</th>
                                    <th>Penulis</th>
                                    <th>Penerbit</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $books = $db->prepare("SELECT * FROM class_books WHERE class_name = ? ORDER BY type, title");
                                $books->execute([$my_class]);
                                foreach ($books->fetchAll() as $b): ?>
                                    <tr>
                                        <td><span class="badge"
                                                style="background:<?= $b['type'] == 'Guru' ? '#e0e7ff' : '#d1fae5' ?>; color:<?= $b['type'] == 'Guru' ? '#4338ca' : '#065f46' ?>"><?= $b['type'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($b['title']) ?></td>
                                        <td><?= htmlspecialchars($b['subject']) ?></td>
                                        <td><?= htmlspecialchars($b['author']) ?></td>
                                        <td><?= htmlspecialchars($b['publisher']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editBook(<?= json_encode($b) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delBookForm', 'del_book_id', <?= $b['id'] ?>, 'Hapus buku <?= htmlspecialchars($b['title']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Book Modal -->
                <div id="editBookForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Buku</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_book">
                        <input type="hidden" name="id" id="book_id">
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px;">
                            <select name="type" id="edit_book_type" class="form-control">
                                <option value="Guru">Buku Guru</option>
                                <option value="Siswa">Buku Siswa</option>
                            </select>
                            <input type="text" name="title" id="edit_book_title" placeholder="Judul Buku"
                                class="form-control" required>
                            <input type="text" name="subject" id="edit_book_subject" placeholder="Mata Pelajaran"
                                class="form-control">
                            <input type="text" name="author" id="edit_book_author" placeholder="Penulis"
                                class="form-control">
                            <input type="text" name="publisher" id="edit_book_pub" placeholder="Penerbit"
                                class="form-control">
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editBookForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delBookForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_book">
                    <input type="hidden" name="id" id="del_book_id">
                </form>

                <script>
                    function editBook(data) {
                        document.getElementById('editBookForm').style.display = 'block';
                        document.getElementById('book_id').value = data.id;
                        document.getElementById('edit_book_type').value = data.type;
                        document.getElementById('edit_book_title').value = data.title;
                        document.getElementById('edit_book_subject').value = data.subject;
                        document.getElementById('edit_book_author').value = data.author;
                        document.getElementById('edit_book_pub').value = data.publisher;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: BIMBINGAN ---
            elseif ($view === 'bimbingan'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Bimbingan & Konseling</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_consultation">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <select name="student_id" class="form-control" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($my_students as $s)
                                    echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                            </select>
                            <select name="type" class="form-control">
                                <option value="Siswa">Konsultasi Siswa</option>
                                <option value="Orang Tua">Konsultasi Orang Tua</option>
                            </select>
                            <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                            <input type="text" name="problem" placeholder="Masalah / Topik" class="form-control">
                            <div style="grid-column: span 2"><textarea name="solution" placeholder="Solusi / Tindak Lanjut"
                                    class="form-control"></textarea></div>
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Tipe</th>
                                    <th>Siswa</th>
                                    <th>Masalah</th>
                                    <th>Solusi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cons = $db->prepare("SELECT c.*, s.name FROM consultations c JOIN students s ON c.student_id = s.id WHERE s.class_name = ? ORDER BY c.date DESC");
                                $cons->execute([$my_class]);
                                foreach ($cons->fetchAll() as $row): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                                        <td><?= $row['type'] ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['problem']) ?></td>
                                        <td><?= htmlspecialchars($row['solution']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editConsultation(<?= json_encode($row) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delConsForm', 'del_cons_id', <?= $row['id'] ?>, 'Hapus konsultasi <?= htmlspecialchars($row['name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Consultation Modal -->
                <div id="editConsForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Konsultasi</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_consultation">
                        <input type="hidden" name="id" id="cons_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <select name="student_id" id="edit_cons_sid" class="form-control" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($my_students as $s)
                                    echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                            </select>
                            <select name="type" id="edit_cons_type" class="form-control">
                                <option value="Siswa">Konsultasi Siswa</option>
                                <option value="Orang Tua">Konsultasi Orang Tua</option>
                            </select>
                            <input type="date" name="date" id="edit_cons_date" class="form-control">
                            <input type="text" name="problem" id="edit_cons_prob" placeholder="Masalah / Topik"
                                class="form-control">
                            <div style="grid-column: span 2"><textarea name="solution" id="edit_cons_sol"
                                    placeholder="Solusi / Tindak Lanjut" class="form-control"></textarea></div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editConsForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delConsForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_consultation">
                    <input type="hidden" name="id" id="del_cons_id">
                </form>

                <script>
                    function editConsultation(data) {
                        document.getElementById('editConsForm').style.display = 'block';
                        document.getElementById('cons_id').value = data.id;
                        document.getElementById('edit_cons_sid').value = data.student_id;
                        document.getElementById('edit_cons_type').value = data.type;
                        document.getElementById('edit_cons_date').value = data.date;
                        document.getElementById('edit_cons_prob').value = data.problem;
                        document.getElementById('edit_cons_sol').value = data.solution;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: PIKET ---
            elseif ($view === 'piket'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Jadwal Piket Kebersihan</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="save_schedule">
                        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1rem;">
                            <div>
                                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Hari</label>
                                <select name="day_name" class="form-control">
                                    <option>Senin</option>
                                    <option>Selasa</option>
                                    <option>Rabu</option>
                                    <option>Kamis</option>
                                    <option>Jumat</option>
                                    <option>Sabtu</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; margin-bottom:0.5rem; font-weight:600;">Siswa (Tahan Ctrl untuk
                                    pilih banyak)</label>
                                <select name="student_names[]" class="form-control" multiple size="8"
                                    style="height:auto; min-height:150px;">
                                    <?php foreach ($my_students as $s): ?>
                                        <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Jadwal</button>
                    </form>

                    <!-- Display Schedule Grid -->
                    <?php
                    // Fetch all schedules
                    $scheds = $db->prepare("SELECT * FROM cleaning_schedule WHERE class_name = ?");
                    $scheds->execute([$my_class]);
                    $raw_scheds = $scheds->fetchAll();

                    // Map by day
                    $schedule_map = [];
                    foreach ($raw_scheds as $r) {
                        $schedule_map[$r['day_name']] = $r['student_names'];
                    }

                    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                    ?>
                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:1.5rem; margin-top:2rem;">
                        <?php foreach ($days as $day):
                            $has_schedule = isset($schedule_map[$day]) && $schedule_map[$day];
                            $current_students_str = $has_schedule ? $schedule_map[$day] : '';
                            ?>
                            <div
                                style="background:white; border:1px solid #e2e8f0; border-radius:12px; padding:1.5rem; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative;">
                                <h3
                                    style="margin:0 0 1rem 0; font-size:1.1rem; color:var(--primary-color); border-bottom:2px solid #f1f5f9; padding-bottom:0.5rem; text-align:center;">
                                    <?= $day ?>
                                </h3>
                                <div
                                    style="font-size:0.95rem; color:#475569; min-height:80px; text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <?= $has_schedule ? nl2br(htmlspecialchars(str_replace(', ', "\n", $current_students_str))) : '<span style="color:#ccc; font-style:italic;">Belum ada jadwal</span>' ?>
                                </div>
                                <?php if ($has_schedule): ?>
                                    <div class="action-buttons"
                                        style="margin-top:1rem; justify-content:center; border-top:1px solid #eee; padding-top:0.5rem;">
                                        <button type="button"
                                            onclick="editSchedule('<?= $day ?>', '<?= addslashes($current_students_str) ?>')"
                                            class="btn btn-sm btn-primary">Edit</button>
                                        <button type="button"
                                            onclick="confirmDelete('delSchedForm', 'del_sched_day', '<?= $day ?>', 'Hapus jadwal piket hari <?= $day ?>?')"
                                            class="btn btn-sm btn-danger">Hapus</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Hidden Delete Form for Schedule -->
                <form method="POST" id="delSchedForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_schedule">
                    <input type="hidden" name="day_name" id="del_sched_day">
                </form>

                <script>
                    function editSchedule(day, namesStr) {
                        // 1. Set the day dropdown
                        const daySelect = document.querySelector('select[name="day_name"]');
                        daySelect.value = day;

                        // 2. Select the students in the multi-select
                        const studentSelect = document.querySelector('select[name="student_names[]"]');
                        const namesArray = namesStr.split(', ');

                        // Reset selection first
                        for (let i = 0; i < studentSelect.options.length; i++) {
                            studentSelect.options[i].selected = false;
                        }

                        // Select matching names
                        for (let i = 0; i < studentSelect.options.length; i++) {
                            if (namesArray.includes(studentSelect.options[i].value)) {
                                studentSelect.options[i].selected = true;
                            }
                        }

                        // 3. Scroll to form
                        document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
                    }
                </script>

            <?php // --- DEFAULT VIEWS (TAMU & INVENTARIS - Kept from previous step) ---
            elseif ($view === 'tamu'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Buku Tamu Kelas</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_guest">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div><label>Nama Tamu</label><input type="text" name="guest_name" class="form-control" required
                                    placeholder="Contoh: Orang Tua"></div>
                            <div><label>Tujuan</label><input type="text" name="purpose" class="form-control" required
                                    placeholder="Contoh: Konsultasi"></div>
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Nama Tamu</th>
                                    <th>Tujuan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $guests = $db->prepare("SELECT * FROM class_guest_book WHERE class_name=? ORDER BY date DESC");
                                $guests->execute([$my_class]);
                                foreach ($guests->fetchAll() as $g): ?>
                                    <tr>
                                        <td><?= date('d M Y H:i', strtotime($g['date'])) ?></td>
                                        <td><?= htmlspecialchars($g['guest_name']) ?></td>
                                        <td><?= htmlspecialchars($g['purpose']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editGuest(<?= json_encode($g) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delGuestForm', 'del_guest_id', <?= $g['id'] ?>, 'Hapus tamu <?= htmlspecialchars($g['guest_name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Guest Modal -->
                <div id="editGuestForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Tamu</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_guest">
                        <input type="hidden" name="id" id="guest_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <div><label>Nama Tamu</label><input type="text" name="guest_name" id="edit_guest_name"
                                    class="form-control" required></div>
                            <div><label>Tujuan</label><input type="text" name="purpose" id="edit_purpose"
                                    class="form-control" required></div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editGuestForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delGuestForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_guest">
                    <input type="hidden" name="id" id="del_guest_id">
                </form>

                <script>
                    function editGuest(data) {
                        document.getElementById('editGuestForm').style.display = 'block';
                        document.getElementById('guest_id').value = data.id;
                        document.getElementById('edit_guest_name').value = data.guest_name;
                        document.getElementById('edit_purpose').value = data.purpose;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: ANALISIS ---
            elseif ($view === 'analisis'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Analisis Akademik (UTS)</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_exam_analysis">
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem;">
                            <select name="semester" class="form-control" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                            <select name="subject" class="form-control" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php
                                $subjects = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
                                foreach ($subjects as $sub): ?>
                                    <option value="<?= htmlspecialchars($sub['name']) ?>"><?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.01" name="avg" placeholder="Rata-rata Nilai" class="form-control"
                                required>
                            <input type="number" step="0.01" name="target" placeholder="Target Kurikulum (%)"
                                class="form-control" required>
                            <input type="number" step="0.01" name="absorption" placeholder="Daya Serap (%)"
                                class="form-control" required>
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan Analisis</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Smt</th>
                                    <th>Mapel</th>
                                    <th>Rata-rata</th>
                                    <th>Target</th>
                                    <th>Daya Serap</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $anas = $db->prepare("SELECT * FROM exam_analysis WHERE class_name=? ORDER BY semester, subject");
                                $anas->execute([$my_class]);
                                foreach ($anas->fetchAll() as $a):
                                    $d = json_decode($a['data_values'], true);
                                    ?>
                                    <tr>
                                        <td><?= $a['semester'] ?></td>
                                        <td><?= htmlspecialchars($a['subject']) ?></td>
                                        <td><?= $d['avg'] ?></td>
                                        <td><?= $d['target'] ?>%</td>
                                        <td><?= $d['absorption'] ?>%</td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editAnalysis(<?= json_encode(array_merge($a, $d)) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delAnaForm', 'del_ana_id', <?= $a['id'] ?>, 'Hapus analisis <?= htmlspecialchars($a['subject']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Analysis Modal -->
                <div id="editAnaForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Analisis</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_exam_analysis">
                        <input type="hidden" name="id" id="ana_id">
                        <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap:1rem;">
                            <select name="semester" id="edit_ana_sem" class="form-control" required>
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                            </select>
                            <select name="subject" id="edit_ana_sub" class="form-control" required>
                                <option value="">-- Pilih Mata Pelajaran --</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?= htmlspecialchars($sub['name']) ?>"><?= htmlspecialchars($sub['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" step="0.01" name="avg" id="edit_ana_avg" placeholder="Rata-rata"
                                class="form-control" required>
                            <input type="number" step="0.01" name="target" id="edit_ana_target" placeholder="Target"
                                class="form-control" required>
                            <input type="number" step="0.01" name="absorption" id="edit_ana_abs" placeholder="Daya Serap"
                                class="form-control" required>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editAnaForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delAnaForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_exam_analysis">
                    <input type="hidden" name="id" id="del_ana_id">
                </form>

                <script>
                    function editAnalysis(data) {
                        document.getElementById('editAnaForm').style.display = 'block';
                        document.getElementById('ana_id').value = data.id;
                        document.getElementById('edit_ana_sem').value = data.semester;
                        document.getElementById('edit_ana_sub').value = data.subject;
                        document.getElementById('edit_ana_avg').value = data.avg;
                        document.getElementById('edit_ana_target').value = data.target;
                        document.getElementById('edit_ana_abs').value = data.absorption;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: NILAI SISWA ---
            elseif ($view === 'nilai'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Input Nilai Siswa</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>

                    <!-- Form Input Nilai -->
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_grade">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                            <div>
                                <label>Siswa</label>
                                <select name="student_id" class="form-control" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($my_students as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Mata Pelajaran</label>
                                <select name="subject" class="form-control" required>
                                    <option value="">-- Pilih Mapel --</option>
                                    <?php
                                    $subjects = $db->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
                                    foreach ($subjects as $sub): ?>
                                        <option value="<?= htmlspecialchars($sub['name']) ?>">
                                            <?= htmlspecialchars($sub['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Jenis Ujian</label>
                                <select name="exam_type" class="form-control" required>
                                    <option value="PH">Penilaian Harian</option>
                                    <option value="PTS">PTS (Tengah Semester)</option>
                                    <option value="PAS">PAS (Akhir Semester)</option>
                                    <option value="PAT">PAT (Akhir Tahun)</option>
                                    <option value="US">Ujian Sekolah</option>
                                </select>
                            </div>
                            <div>
                                <label>Semester</label>
                                <select name="semester" class="form-control" required>
                                    <option value="1">Semester 1 (Ganjil)</option>
                                    <option value="2">Semester 2 (Genap)</option>
                                </select>
                            </div>
                            <div>
                                <label>Tahun Ajaran</label>
                                <input type="text" name="year" class="form-control"
                                    value="<?= date('Y') . '/' . (date('Y') + 1) ?>" required>
                            </div>
                            <div>
                                <label>Nilai (0-100)</label>
                                <input type="number" step="0.01" name="nilai" class="form-control" min="0" max="100"
                                    required>
                            </div>
                            <div>
                                <label>Peringkat (Opsional)</label>
                                <input type="number" name="ranking" class="form-control" placeholder="Ranking di kelas">
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Nilai</button>
                    </form>

                    <!-- Tabel Data Nilai -->
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>Mapel</th>
                                    <th>Jenis</th>
                                    <th>Smt</th>
                                    <th>Nilai</th>
                                    <th>Peringkat</th>
                                    <th>Tahun</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $gradesQuery = "SELECT g.*, s.name as student_name 
                                                FROM student_grades g 
                                                JOIN students s ON g.student_id = s.id 
                                                WHERE s.class_name = ? 
                                                ORDER BY g.year DESC, g.semester DESC, s.name ASC, g.subject ASC";
                                $stmtGrades = $db->prepare($gradesQuery);
                                $stmtGrades->execute([$my_class]);
                                $grades = $stmtGrades->fetchAll();

                                foreach ($grades as $index => $g): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($g['student_name']) ?></td>
                                        <td><?= htmlspecialchars($g['subject']) ?></td>
                                        <td><span class="badge"
                                                style="background:#e0f2fe; color:#0369a1;"><?= htmlspecialchars($g['exam_type']) ?></span>
                                        </td>
                                        <td><?= $g['semester'] ?></td>
                                        <td style="font-weight:bold; color:#059669;"><?= $g['nilai'] ?></td>
                                        <td><?= $g['ranking'] ? '#' . $g['ranking'] : '-' ?></td>
                                        <td><?= htmlspecialchars($g['year']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editGrade(<?= json_encode($g) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delGradeForm', 'del_grade_id', <?= $g['id'] ?>, 'Hapus nilai <?= htmlspecialchars($g['student_name']) ?> - <?= htmlspecialchars($g['subject']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Grade Modal -->
                <div id="editGradeForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Nilai Siswa</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_grade">
                        <input type="hidden" name="id" id="edit_grade_id">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                            <div>
                                <label>Siswa</label>
                                <select name="student_id" id="edit_grade_student" class="form-control" required>
                                    <option value="">-- Pilih Siswa --</option>
                                    <?php foreach ($my_students as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Mata Pelajaran</label>
                                <select name="subject" id="edit_grade_subject" class="form-control" required>
                                    <option value="">-- Pilih Mapel --</option>
                                    <?php foreach ($subjects as $sub): ?>
                                        <option value="<?= htmlspecialchars($sub['name']) ?>">
                                            <?= htmlspecialchars($sub['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label>Jenis Ujian</label>
                                <select name="exam_type" id="edit_grade_type" class="form-control" required>
                                    <option value="PH">Penilaian Harian</option>
                                    <option value="PTS">PTS (Tengah Semester)</option>
                                    <option value="PAS">PAS (Akhir Semester)</option>
                                    <option value="PAT">PAT (Akhir Tahun)</option>
                                    <option value="US">Ujian Sekolah</option>
                                </select>
                            </div>
                            <div>
                                <label>Semester</label>
                                <select name="semester" id="edit_grade_semester" class="form-control" required>
                                    <option value="1">Semester 1 (Ganjil)</option>
                                    <option value="2">Semester 2 (Genap)</option>
                                </select>
                            </div>
                            <div>
                                <label>Tahun Ajaran</label>
                                <input type="text" name="year" id="edit_grade_year" class="form-control" required>
                            </div>
                            <div>
                                <label>Nilai (0-100)</label>
                                <input type="number" step="0.01" name="nilai" id="edit_grade_val" class="form-control"
                                    min="0" max="100" required>
                            </div>
                            <div>
                                <label>Peringkat (Opsional)</label>
                                <input type="number" name="ranking" id="edit_grade_rank" class="form-control">
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editGradeForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delGradeForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_grade">
                    <input type="hidden" name="id" id="del_grade_id">
                </form>

                <script>
                    function editGrade(data) {
                        document.getElementById('editGradeForm').style.display = 'block';
                        document.getElementById('edit_grade_id').value = data.id;
                        document.getElementById('edit_grade_student').value = data.student_id;
                        document.getElementById('edit_grade_subject').value = data.subject;
                        document.getElementById('edit_grade_type').value = data.exam_type;
                        document.getElementById('edit_grade_semester').value = data.semester;
                        document.getElementById('edit_grade_year').value = data.year;
                        document.getElementById('edit_grade_val').value = data.nilai;
                        document.getElementById('edit_grade_rank').value = data.ranking;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: BAKAT (ACTIVITIES) ---
            elseif ($view === 'bakat'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Minat, Bakat, & Ekstrakurikuler</h2> <a href="?view=main"
                            class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_activity">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <select name="student_id" class="form-control" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($my_students as $s)
                                    echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                            </select>
                            <input type="text" name="activity_name" placeholder="Nama Kegiatan / Ekskul"
                                class="form-control" required>
                            <input type="text" name="role" placeholder="Peran / Minat" class="form-control">
                            <input type="text" name="achievement" placeholder="Prestasi (Opsional)" class="form-control">
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Siswa</th>
                                    <th>Kegiatan</th>
                                    <th>Peran</th>
                                    <th>Prestasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $acts = $db->prepare("SELECT a.*, s.name FROM student_activities a JOIN students s ON a.student_id=s.id WHERE s.class_name=?");
                                $acts->execute([$my_class]);
                                foreach ($acts->fetchAll() as $ac): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ac['name']) ?></td>
                                        <td><?= htmlspecialchars($ac['activity_name']) ?></td>
                                        <td><?= htmlspecialchars($ac['role']) ?></td>
                                        <td><?= htmlspecialchars($ac['achievement']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editActivity(<?= json_encode($ac) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delActForm', 'del_act_id', <?= $ac['id'] ?>, 'Hapus kegiatan <?= htmlspecialchars($ac['activity_name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Activity Modal -->
                <div id="editActForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Kegiatan</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_activity">
                        <input type="hidden" name="id" id="act_id">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
                            <select name="student_id" id="edit_act_sid" class="form-control" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($my_students as $s)
                                    echo "<option value='{$s['id']}'>{$s['name']}</option>"; ?>
                            </select>
                            <input type="text" name="activity_name" id="edit_act_name" placeholder="Nama Kegiatan"
                                class="form-control" required>
                            <input type="text" name="role" id="edit_act_role" placeholder="Peran" class="form-control">
                            <input type="text" name="achievement" id="edit_act_ach" placeholder="Prestasi"
                                class="form-control">
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editActForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delActForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_activity">
                    <input type="hidden" name="id" id="del_act_id">
                </form>

                <script>
                    function editActivity(data) {
                        document.getElementById('editActForm').style.display = 'block';
                        document.getElementById('act_id').value = data.id;
                        document.getElementById('edit_act_sid').value = data.student_id;
                        document.getElementById('edit_act_name').value = data.activity_name;
                        document.getElementById('edit_act_role').value = data.role;
                        document.getElementById('edit_act_ach').value = data.achievement;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php // --- VIEW: TATA TERTIB ---
            elseif ($view === 'rules'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Tata Tertib Kelas</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_rule">
                        <div style="display:flex; gap:1rem;">
                            <input type="text" name="content" placeholder="Isi Aturan Kelas..." class="form-control"
                                style="flex:1;" required>
                            <button type="submit" class="btn btn-primary" style="white-space:nowrap; width:auto;">Tambah
                                Aturan</button>
                        </div>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Aturan</th>
                                    <th style="width:150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rules = $db->prepare("SELECT * FROM class_rules WHERE class_name=?");
                                $rules->execute([$my_class]);
                                foreach ($rules->fetchAll() as $i => $r): ?>
                                    <tr>
                                        <td style="width:50px; text-align:center;"><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($r['content']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editRule(<?= json_encode($r) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delRuleForm', 'del_rule_id', <?= $r['id'] ?>, 'Hapus aturan ini?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Rule Modal -->
                <div id="editRuleForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Aturan</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_rule">
                        <input type="hidden" name="id" id="rule_id">
                        <div style="display:flex; gap:1rem;">
                            <input type="text" name="content" id="edit_rule_content" class="form-control" style="flex:1;"
                                required>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editRuleForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delRuleForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_rule">
                    <input type="hidden" name="id" id="del_rule_id">
                </form>

                <script>
                    function editRule(data) {
                        document.getElementById('editRuleForm').style.display = 'block';
                        document.getElementById('rule_id').value = data.id;
                        document.getElementById('edit_rule_content').value = data.content;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>

            <?php elseif ($view === 'inventaris'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Inventaris Kelas</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_inventory">
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem;">
                            <input type="text" name="item_name" placeholder="Nama Barang" class="form-control" required>
                            <input type="number" name="quantity" value="1" class="form-control">
                            <select name="condition" class="form-control">
                                <option value="Baik">Baik</option>
                                <option value="Rusak">Rusak</option>
                            </select>
                            <input type="text" name="notes" placeholder="Catatan" class="form-control">
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Barang</th>
                                    <th>Jml</th>
                                    <th>Kondisi</th>
                                    <th>Catatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $items = $db->prepare("SELECT * FROM class_inventory WHERE class_name=?");
                                $items->execute([$my_class]);
                                foreach ($items->fetchAll() as $i): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($i['item_name']) ?></td>
                                        <td><?= $i['quantity'] ?></td>
                                        <td><?= $i['condition'] ?></td>
                                        <td><?= htmlspecialchars($i['notes']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick='editInventory(<?= json_encode($i) ?>)'
                                                    class="btn btn-primary btn-sm">Edit</button>
                                                <button
                                                    onclick="confirmDelete('delInvForm', 'del_inv_id', <?= $i['id'] ?>, 'Hapus barang <?= htmlspecialchars($i['item_name']) ?>?')"
                                                    class="btn btn-danger btn-sm">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Inventory Modal -->
                <div id="editInvForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px;">
                    <h3>Edit Inventaris</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_inventory">
                        <input type="hidden" name="id" id="inv_id">
                        <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem;">
                            <input type="text" name="item_name" id="edit_inv_name" placeholder="Nama Barang"
                                class="form-control" required>
                            <input type="number" name="quantity" id="edit_inv_qty" class="form-control">
                            <select name="condition" id="edit_inv_cond" class="form-control">
                                <option value="Baik">Baik</option>
                                <option value="Rusak">Rusak</option>
                            </select>
                            <input type="text" name="notes" id="edit_inv_notes" placeholder="Catatan" class="form-control">
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        <button type="button" onclick="document.getElementById('editInvForm').style.display='none'"
                            class="btn btn-secondary">Batal</button>
                    </form>
                </div>

                <!-- Hidden Delete Form -->
                <form method="POST" id="delInvForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_inventory">
                    <input type="hidden" name="id" id="del_inv_id">
                </form>

                <script>
                    function editInventory(data) {
                        document.getElementById('editInvForm').style.display = 'block';
                        document.getElementById('inv_id').value = data.id;
                        document.getElementById('edit_inv_name').value = data.item_name;
                        document.getElementById('edit_inv_qty').value = data.quantity;
                        document.getElementById('edit_inv_cond').value = data.condition;
                        document.getElementById('edit_inv_notes').value = data.notes;
                        window.scrollTo(0, document.body.scrollHeight);
                    }
                </script>
            <?php // --- VIEW: DENAH TEMPAT DUDUK ---
            elseif ($view === 'denah'):
                $plan_q = $db->prepare("SELECT layout_data FROM class_seating_plans WHERE class_name = ?");
                $plan_q->execute([$my_class]);
                $plan_row = $plan_q->fetch();
                $layout = $plan_row ? json_decode($plan_row['layout_data'], true) : [];
                ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Denah Tempat Duduk</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_seating">
                        <div
                            style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1rem; margin-top:1rem; overflow-x:auto;">
                            <?php for ($i = 1; $i <= 30; $i++): ?>
                                <div
                                    style="border:1px solid #ccc; padding:0.5rem; border-radius:8px; text-align:center; background:#f9fafb;">
                                    <div style="font-size:0.7rem; color:#64748b; margin-bottom:0.25rem; font-weight:600;">Meja
                                        <?= $i ?>
                                    </div>
                                    <select name="seats[<?= $i ?>]"
                                        style="width:100%; font-size:0.85rem; padding:0.25rem; border:1px solid #e2e8f0; border-radius:4px;">
                                        <option value="">(Kosong)</option>
                                        <?php foreach ($my_students as $s): ?>
                                            <option value="<?= $s['id'] ?>" <?= (isset($layout[$i]) && $layout[$i] == $s['id']) ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endfor; ?>
                        </div>
                        <br><button type="submit" class="btn btn-primary">Simpan Posisi</button>
                    </form>
                </div>

            <?php // --- VIEW: KELOMPOK BELAJAR ---
            elseif ($view === 'kelompok'):
                $groups = $db->prepare("SELECT * FROM class_study_groups WHERE class_name = ?");
                $groups->execute([$my_class]);
                $g_list = $groups->fetchAll();
                ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Kelompok Belajar</h2> <a href="?view=main" class="btn btn-secondary btn-sm">Kembali</a>
                    </div>

                    <form method="POST" class="form-section">
                        <input type="hidden" name="action" value="add_group">
                        <div style="display:grid; grid-template-columns: 1fr 2fr; gap:1rem;">
                            <input type="text" name="group_name" placeholder="Nama Kelompok (mis: Kelompok 1, Tim Mawar)"
                                class="form-control" required>
                            <select name="student_ids[]" class="form-control" multiple size="5"
                                style="height:120px; border:1px solid #ddd;">
                                <?php foreach ($my_students as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="font-size:0.8rem; color:#666; margin-top:0.25rem;">* Tahan tombol Ctrl (Windows) atau
                            Command (Mac) untuk memilih banyak siswa.</div>
                        <br><button type="submit" class="btn btn-primary">Buat Kelompok</button>
                    </form>

                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:1.5rem;">
                        <?php foreach ($g_list as $g):
                            $member_ids = explode(',', $g['student_ids']);
                            ?>
                            <div
                                style="border:1px solid #e2e8f0; border-radius:12px; padding:1.5rem; background:white; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative;">
                                <h3
                                    style="margin-top:0; color:var(--primary-color); border-bottom:1px solid #f1f5f9; padding-bottom:0.5rem; display:flex; justify-content:space-between;">
                                    <?= htmlspecialchars($g['group_name']) ?>
                                    <span
                                        style="font-size:0.8rem; color:#94a3b8; background:#f1f5f9; padding:2px 8px; border-radius:99px; font-weight:normal;"><?= count($member_ids) ?>
                                        Siswa</span>
                                </h3>
                                <ul style="padding-left:1.2rem; margin-bottom:1rem; color:#475569;">
                                    <?php foreach ($member_ids as $mid):
                                        $s_name = "Unknown";
                                        foreach ($my_students as $ms)
                                            if ($ms['id'] == $mid)
                                                $s_name = $ms['name'];
                                        ?>
                                        <li><?= htmlspecialchars($s_name) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="action-buttons"
                                    style="border-top:1px solid #f1f5f9; padding-top:1rem; justify-content:flex-end;">
                                    <button
                                        onclick="confirmDelete('delGroupForm', 'del_grp_id', <?= $g['id'] ?>, 'Hapus kelompok <?= htmlspecialchars($g['group_name']) ?>?')"
                                        class="btn btn-danger btn-sm">Hapus</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Hidden Delete Form -->
                <form method="POST" id="delGroupForm" style="display:none;">
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="id" id="del_grp_id">
                </form>

            <?php // --- VIEW: ADMINISTRASI KURIKULUM MERDEKA ---
            elseif ($view === 'administrasi_kumer'): ?>
                <div class="card">
                    <div class="section-title">
                        <h2>Administrasi Kurikulum Merdeka</h2> <a href="?view=main"
                            class="btn btn-secondary btn-sm">Kembali</a>
                    </div>

                    <form method="POST" class="form-section" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_admin_guru">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
                            <div>
                                <label>Jenis Dokumen</label>
                                <select name="doc_type" class="form-control" required>
                                    <option value="">-- Pilih Dokumen --</option>
                                    <option value="CP">CP (Capaian Pembelajaran)</option>
                                    <option value="TP">TP (Tujuan Pembelajaran)</option>
                                    <option value="ATP">ATP (Alur Tujuan Pembelajaran)</option>
                                    <option value="Prota">Prota (Program Tahunan)</option>
                                    <option value="Promes">Promes (Program Semester)</option>
                                    <option value="Modul Ajar">Modul Ajar</option>
                                    <option value="KKTP">KKTP</option>
                                    <option value="Daftar Nilai">Daftar Nilai</option>
                                    <option value="Analisis Soal">Analisis Soal</option>
                                    <option value="Bank Soal & Kisi-kisi">Bank Soal & Kisi-kisi</option>
                                    <option value="Program Remedial & Pengayaan">Program Remedial & Pengayaan</option>
                                </select>
                            </div>
                            <div>
                                <label>Upload File (PDF)</label>
                                <input type="file" name="file_upload" class="form-control" accept=".pdf" required>
                            </div>
                            <div style="grid-column: 1 / -1;">
                                <label>Keterangan / Catatan Tambahan</label>
                                <textarea name="description" class="form-control" rows="2"
                                    placeholder="Contoh: Modul Ajar Bab 1 Matematika..."></textarea>
                            </div>
                        </div>
                        <br>
                        <button type="submit" class="btn btn-primary">Upload Dokumen</button>
                    </form>

                    <div class="section-title" style="font-size: 1rem; margin-top: 2rem;">Dokumen Saya</div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Jenis Dokumen</th>
                                    <th>Keterangan</th>
                                    <th>File</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Check if table exists first to avoid error on first run if not created
                                $docs = [];
                                try {
                                    $stmt = $db->prepare("SELECT * FROM school_documents WHERE type='admin_guru' AND related_user_id = ? ORDER BY id DESC");
                                    $stmt->execute([$user_id]);
                                    $docs = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    // Table might not exist yet
                                }

                                if (empty($docs)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Belum ada dokumen yang diupload.</td>
                                    </tr>
                                <?php else:
                                    foreach ($docs as $i => $d): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><span class="badge badge-info"><?= htmlspecialchars($d['title']) ?></span></td>
                                            <td><?= htmlspecialchars($d['description']) ?></td>
                                            <td>
                                                <?php if ($d['file_path']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                        style="border: 1px solid var(--primary-color); color: var(--primary-color); background: transparent;"
                                                        onclick="viewPdf('<?= htmlspecialchars($d['file_path']) ?>'); return false;">
                                                        <i class="fas fa-file-pdf"></i> Lihat PDF
                                                    </button>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick='editAdminGuru(<?= json_encode($d) ?>)'
                                                        class="btn btn-primary btn-sm">Edit</button>
                                                    <form method="POST" onsubmit="return confirm('Hapus dokumen ini?');"
                                                        style="display:inline;">
                                                        <input type="hidden" name="action" value="delete_admin_guru">
                                                        <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Edit Admin Guru Modal -->
                <div id="editAdminGuruForm"
                    style="display:none; margin-top:20px; background:white; padding:20px; border:1px solid #ccc; border-radius: 8px; position: fixed; top: 10%; left: 50%; transform: translateX(-50%); width: 90%; max-width: 600px; z-index: 1000; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                    <h3>Edit Dokumen</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_admin_guru">
                        <input type="hidden" name="id" id="edit_adm_id">

                        <label>Jenis Dokumen</label>
                        <select name="doc_type" id="edit_adm_type" class="form-control" required
                            style="margin-bottom: 1rem;">
                            <option value="">-- Pilih Dokumen --</option>
                            <option value="CP">CP (Capaian Pembelajaran)</option>
                            <option value="TP">TP (Tujuan Pembelajaran)</option>
                            <option value="ATP">ATP (Alur Tujuan Pembelajaran)</option>
                            <option value="Prota">Prota (Program Tahunan)</option>
                            <option value="Promes">Promes (Program Semester)</option>
                            <option value="Modul Ajar">Modul Ajar</option>
                            <option value="KKTP">KKTP</option>
                            <option value="Daftar Nilai">Daftar Nilai</option>
                            <option value="Analisis Soal">Analisis Soal</option>
                            <option value="Bank Soal & Kisi-kisi">Bank Soal & Kisi-kisi</option>
                            <option value="Program Remedial & Pengayaan">Program Remedial & Pengayaan</option>
                        </select>

                        <label>Keterangan</label>
                        <textarea name="description" id="edit_adm_desc" class="form-control" rows="2"
                            style="margin-bottom: 1rem;"></textarea>

                        <label>Ganti File PDF (Opsional)</label>
                        <input type="file" name="file_upload" class="form-control" accept=".pdf"
                            style="margin-bottom: 1.5rem;">

                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="button"
                                onclick="document.getElementById('editAdminGuruForm').style.display='none'"
                                class="btn btn-secondary">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>

                <script>
                    function editAdminGuru(data) {
                        document.getElementById('editAdminGuruForm').style.display = 'block';
                        document.getElementById('edit_adm_id').value = data.id;
                        document.getElementById('edit_adm_type').value = data.title;
                        document.getElementById('edit_adm_desc').value = data.description;
                    }
                </script>

            <?php endif; ?>

            <!-- Global Delete Confirmation Modal -->
            <div id="deleteModal"
                style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; backdrop-filter: blur(2px);">
                <div
                    style="background:white; padding:2rem; border-radius:16px; max-width:400px; width:90%; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.1); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <div
                        style="font-size:3rem; margin-bottom:1rem; background:#fee2e2; width:80px; height:80px; line-height:80px; border-radius:50%; margin-left:auto; margin-right:auto; color:#dc2626;">
                        ⚠️</div>
                    <h3 style="margin-bottom:0.5rem; color:#1e293b; font-size:1.5rem; font-weight:700;">Konfirmasi Hapus
                    </h3>
                    <p id="deleteModalMessage"
                        style="color:#64748b; margin-bottom:2rem; line-height:1.5; font-size:1rem;">
                        Apakah Anda yakin ingin menghapus data ini?</p>
                    <div style="display:flex; justify-content:center; gap:1rem;">
                        <button onclick="closeDeleteModal()" class="btn"
                            style="background:#f1f5f9; color:#475569; border:none; padding: 0.75rem 1.5rem; border-radius:8px; font-weight:600; cursor:pointer;">Batal</button>
                        <button id="confirmDeleteBtn" class="btn"
                            style="background:#dc2626; color:white; border:none; padding: 0.75rem 1.5rem; border-radius:8px; font-weight:600; cursor:pointer; box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.3);">Ya,
                            Hapus</button>
                    </div>
                </div>
            </div>

            <style>
                @keyframes popIn {
                    0% {
                        transform: scale(0.8);
                        opacity: 0;
                    }

                    100% {
                        transform: scale(1);
                        opacity: 1;
                    }
                }
            </style>

            <script>
                let deleteTargetForm = null;
                let deleteTargetInput = null;
                let deleteTargetValue = null;

                function confirmDelete(formId, inputId, value, message) {
                    deleteTargetForm = document.getElementById(formId);
                    deleteTargetInput = document.getElementById(inputId);
                    deleteTargetValue = value;

                    document.getElementById('deleteModalMessage').innerText = message || "Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.";
                    document.getElementById('deleteModal').style.display = 'flex';
                }

                document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
                    if (deleteTargetForm && deleteTargetInput) {
                        deleteTargetInput.value = deleteTargetValue;
                        deleteTargetForm.submit();
                    }
                });

                function closeDeleteModal() {
                    document.getElementById('deleteModal').style.display = 'none';
                }

                // Close modal on click outside
                document.getElementById('deleteModal').addEventListener('click', function (e) {
                    if (e.target === this) closeDeleteModal();
                });
            </script>
        </main>
    </div>
    <!-- PDF MODAL -->
    <div id="pdfModal">
        <div class="modal-content-pdf">
            <div class="modal-header-pdf">
                <div>
                    <span style="font-weight: 600; font-size: 1.1rem;">Preview Dokumen</span>
                    <span id="page_info"
                        style="font-size: 0.85rem; margin-left: 1rem; color: #9ca3af; background: rgba(255,255,255,0.1); padding: 2px 8px; border-radius: 4px;"></span>
                </div>
                <button type="button" onclick="closePdfModal()"
                    style="background:none; border:none; color:#9ca3af; font-size:1.5rem; cursor:pointer; padding: 0 0.5rem; transition: color 0.2s;">&times;</button>
            </div>
            <div class="modal-body-pdf">
                <div id="loading" style="display:none;">Memuat Dokumen...</div>
                <div id="pdf-container"></div>
            </div>
        </div>
    </div>

    <!-- PDF JS LOGIC -->
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function (page) {
                const viewport = page.getViewport({ scale: scale });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderTask = page.render({
                    canvasContext: ctx,
                    viewport: viewport
                });

                renderTask.promise.then(function () {
                    pageRendering = false;
                    const numDisplay = document.getElementById('page_num_display');
                    if (numDisplay) numDisplay.textContent = num;
                    const infoDisplay = document.getElementById('page_info');
                    if (infoDisplay) infoDisplay.textContent = `Halaman ${num} dari ${pdfDoc.numPages}`;

                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });
        }

        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        function onPrevPage() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        }

        function onNextPage() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        }

        async function viewPdf(filename) {
            const modal = document.getElementById('pdfModal');
            const container = document.getElementById('pdf-container');
            const loading = document.getElementById('loading');

            modal.style.display = 'block';
            loading.style.display = 'block';
            loading.innerText = 'Memuat Dokumen...';
            container.innerHTML = '';
            container.appendChild(canvas);

            // Controls
            const controls = document.createElement('div');
            controls.id = 'pdf-controls';
            controls.style.position = 'absolute';
            controls.style.bottom = '20px';
            controls.style.left = '50%';
            controls.style.transform = 'translateX(-50%)';
            controls.style.background = 'rgba(0,0,0,0.6)';
            controls.style.padding = '8px 16px';
            controls.style.borderRadius = '20px';
            controls.style.display = 'none';
            controls.style.zIndex = '10';

            controls.innerHTML = `
                <div style="display:flex; align-items:center; gap:15px;">
                    <button id="prevBtn" type="button" style="padding: 4px 12px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s;">&larr;</button>
                    <span style="color:white; font-size:0.9rem; font-weight:500;"><span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                    <button id="nextBtn" type="button" style="padding: 4px 12px; background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s;">&rarr;</button>
                </div>
            `;
            container.appendChild(controls);

            try {
                loading.innerText = 'Mengunduh data...';
                const response = await fetch('../utils/get_pdf_content.php?file=' + encodeURIComponent(filename));
                if (!response.ok) throw new Error('Gagal menghubungi server');

                const json = await response.json();
                if (json.error) throw new Error(json.error);

                loading.innerText = 'Memproses data...';
                const binaryString = atob(json.content);
                const len = binaryString.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }

                const loadingTask = pdfjsLib.getDocument({ data: bytes });
                pdfDoc = await loadingTask.promise;

                document.getElementById('page_count').textContent = pdfDoc.numPages;
                document.getElementById('pdf-controls').style.display = 'block';
                loading.style.display = 'none';

                document.getElementById('prevBtn').onclick = onPrevPage;
                document.getElementById('nextBtn').onclick = onNextPage;

                pageNum = 1;
                renderPage(pageNum);

            } catch (err) {
                console.error("PDF Load Error:", err);
                loading.style.display = 'block';
                loading.style.color = '#ef4444';
                loading.innerText = 'Gagal memuat PDF: ' + err.message;
            }
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = 'none';
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        // Close PDF Modal on outside click
        window.addEventListener('click', function (event) {
            const pdfModal = document.getElementById('pdfModal');
            if (event.target == pdfModal) {
                closePdfModal();
            }
        });
    </script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const dashboardLayout = document.querySelector('.dashboard-layout');

        // Initial state logic
        const isMobile = window.innerWidth <= 768;
        const storedState = localStorage.getItem('sidebarCollapsed');

        // Default to collapsed on mobile if not set, otherwise follow storage or default open on desktop
        if (storedState === 'true' || (isMobile && storedState === null)) {
            dashboardLayout.classList.add('sidebar-collapsed');
        }

        sidebarToggle.addEventListener('click', function (e) {
            e.stopPropagation(); // Prevent immediate closing by document listener
            dashboardLayout.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', dashboardLayout.classList.contains('sidebar-collapsed'));
        });

        // Close sidebar when clicking outside on mobile (overlay click)
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 768 && !dashboardLayout.classList.contains('sidebar-collapsed')) {
                // If click is not inside sidebar and not on the toggle button
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                    dashboardLayout.classList.add('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', 'true');
                }
            }
        });
    </script>
</body>

</html>