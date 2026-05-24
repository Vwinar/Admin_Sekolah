<?php
$db_file = __DIR__ . '/../database/laporan.db';
try {
    $db = new PDO('sqlite:' . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create Users Table
    $queryUsers = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        full_name TEXT NOT NULL,
        subject TEXT,
        assigned_class TEXT
    )";
    $db->exec($queryUsers);

    // Create Subjects Table
    $db->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    )");

    // Create Classes Table
    $db->exec("CREATE TABLE IF NOT EXISTS classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    )");

    // Create Reports Table
    $queryReports = "CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        report_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        subject TEXT,
        class_name TEXT,
        material_taught TEXT,
        attendance TEXT,
        achievement TEXT,
        obstacles TEXT,
        solution TEXT,
        plan_material TEXT,
        plan_media TEXT,
        plan_method TEXT,
        plan_goal TEXT,
        reflection TEXT,
        improvement_notes TEXT,
        school_suggestions TEXT,
        module_file TEXT,
        evaluation_file TEXT,
        status TEXT DEFAULT 'pending',
        admin_comment TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )";
    $db->exec($queryReports);

    // Seed initial users (check first if they exist)
    // Changed to check by USERNAME instead of ROLE to prevent auto-recreation of deleted users
    // Only auto-create admin 'vaywinar' - demo users 'guru' and 'siswa' removed
    $adminCheck = $db->query("SELECT COUNT(*) FROM users WHERE username = 'vaywinar'")->fetchColumn();
    if ($adminCheck == 0) {
        $passAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, ?, ?, ?)")
            ->execute(['vaywinar', $passAdmin, 'admin', 'Kepala Sekolah']);
    }

    // Demo users (guru 'Budi Santoso' and siswa 'Ahmad Dhani') removed from auto-seeding
    // They can be added manually via users.php and won't be recreated after deletion

    // Migration Check (Quick & Dirty for SQLite columns)
    $cols = $db->query("PRAGMA table_info(reports)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('module_file', $cols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN module_file TEXT");
    }
    if (!in_array('evaluation_file', $cols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN evaluation_file TEXT");
    }

    // Add profile_photo column to users table if not exists
    $userCols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('profile_photo', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_photo TEXT");
    }

    // Add Rencana Hari Ini columns to reports table if not exists
    $reportCols = $db->query("PRAGMA table_info(reports)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('plan_date', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_date TEXT");
    }
    if (!in_array('plan_subject', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_subject TEXT");
    }
    if (!in_array('plan_class', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_class TEXT");
    }
    if (!in_array('plan_time', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_time TEXT");
    }
    if (!in_array('plan_topic', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_topic TEXT");
    }
    if (!in_array('plan_learning_objective', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_learning_objective TEXT");
    }
    if (!in_array('plan_method_type', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_method_type TEXT");
    }
    if (!in_array('plan_media_used', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_media_used TEXT");
    }
    if (!in_array('plan_assessment_used', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_assessment_used TEXT");
    }
    if (!in_array('plan_notes', $reportCols)) {
        $db->exec("ALTER TABLE reports ADD COLUMN plan_notes TEXT");
    }

    // Create Students Table
    $db->exec("CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        class_name TEXT NOT NULL
    )");

    // Create Student Attendance Table
    $db->exec("CREATE TABLE IF NOT EXISTS student_attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        date DATE NOT NULL,
        status TEXT NOT NULL,
        subject TEXT,
        teacher_id INTEGER,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // --- ADMINISTRASI KELAS TABLES ---

    // 1. Student Details (Extended info)
    $db->exec("CREATE TABLE IF NOT EXISTS student_details (
        student_id INTEGER PRIMARY KEY,
        nis TEXT,
        birth_place TEXT,
        birth_date DATE,
        address TEXT,
        parent_name TEXT,
        parent_contact TEXT,
        photo TEXT,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 2. Student Mutation
    $db->exec("CREATE TABLE IF NOT EXISTS student_mutation (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        type TEXT NOT NULL, -- 'masuk', 'keluar', 'lulus', 'dropout'
        date DATE NOT NULL,
        reason TEXT,
        from_school TEXT, -- for 'masuk'
        to_school TEXT,   -- for 'keluar'
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 3. Achievements & Violations
    $db->exec("CREATE TABLE IF NOT EXISTS student_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'prestasi', 'pelanggaran'
        category TEXT, -- e.g. 'akademik', 'non-akademik', 'tata-tertib'
        description TEXT NOT NULL,
        date DATE NOT NULL,
        point INTEGER DEFAULT 0, -- for violations or achievement weight
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 4. Class Guest Book
    $db->exec("CREATE TABLE IF NOT EXISTS class_guest_book (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        guest_name TEXT NOT NULL,
        purpose TEXT NOT NULL,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        teacher_id INTEGER
    )");

    // 5. Class Inventory
    $db->exec("CREATE TABLE IF NOT EXISTS class_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        item_name TEXT NOT NULL,
        quantity INTEGER DEFAULT 1,
        condition TEXT, -- 'baik', 'rusak', 'hilang'
        notes TEXT
    )");

    // 6. Student Health Records
    $db->exec("CREATE TABLE IF NOT EXISTS student_health (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        history TEXT, -- riwayat penyakit
        allergy TEXT,
        vaccination TEXT,
        recent_illness TEXT,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 7. Books (Teacher & Student Handbooks)
    $db->exec("CREATE TABLE IF NOT EXISTS class_books (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        type TEXT NOT NULL, -- 'guru', 'siswa'
        title TEXT NOT NULL,
        author TEXT,
        publisher TEXT,
        subject TEXT
    )");

    // 8. Consultations (Guidance & Parent)
    $db->exec("CREATE TABLE IF NOT EXISTS consultations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        type TEXT NOT NULL, -- 'siswa', 'orang_tua'
        date DATE NOT NULL,
        problem TEXT,
        solution TEXT,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 9. Class Management (Rules, Picket, Groups - Generic structure or specific)
    // Specific tables for better structure
    $db->exec("CREATE TABLE IF NOT EXISTS class_rules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        content TEXT NOT NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS cleaning_schedule (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        day_name TEXT NOT NULL, -- Senin, Selasa, etc.
        student_names TEXT NOT NULL -- Comma separated or JSON
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS study_groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        group_name TEXT NOT NULL,
        student_names TEXT NOT NULL
    )");

    // 10. Exam Analysis & Learning Interest
    $db->exec("CREATE TABLE IF NOT EXISTS exam_analysis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        semester TEXT NOT NULL, -- '1', '2'
        subject TEXT NOT NULL,
        type TEXT NOT NULL, -- 'UTS', 'UAS', 'Minat'
        data_values TEXT NOT NULL -- JSON storing avg, target, absorption, OR interest notes
    )");

    // 11. Extracurriculars
    $db->exec("CREATE TABLE IF NOT EXISTS student_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        activity_name TEXT NOT NULL,
        role TEXT,
        achievement TEXT,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // 12. Seating & Gallery (Files/Images)
    $db->exec("CREATE TABLE IF NOT EXISTS class_files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        class_name TEXT NOT NULL,
        type TEXT NOT NULL, -- 'denah', 'bukti_admin', 'tanda_tangan_raport'
        file_path TEXT NOT NULL,
        description TEXT
    )");

    // Seed dummy students if empty (DISABLED - students should be added manually)
    // Students can be managed through the admin panel
    /*
    $stmtC = $db->query("SELECT COUNT(*) FROM students");
    if ($stmtC->fetchColumn() == 0) {
        $students = [
            ['Ahmad Dhani', 'X-A'],
            ['Budi Utomo', 'X-A'],
            ['Citra Kirana', 'X-A'],
            ['Dewi Persik', 'X-A'],
            ['Eko Patrio', 'X-A'],
            ['Fajar Sadboy', 'X-B'],
            ['Gisel', 'X-B'],
            ['Hesti Purwadinata', 'X-B']
        ];
        $insert = $db->prepare("INSERT INTO students (name, class_name) VALUES (?, ?)");
        foreach ($students as $s) {
            $insert->execute($s);
        }
    }
    */

    // --- ATTENDANCE SYSTEM TABLES ---

    // 13. Settings for Attendance
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        latitude REAL,
        longitude REAL,
        radius REAL,
        waktu_masuk TEXT,
        waktu_pulang TEXT,
        waktu_pulang_otomatis TEXT,
        school_logo TEXT,
        school_name TEXT
    )");

    // Initialize default settings if empty
    $settingsCount = $db->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingsCount == 0) {
        $db->exec("INSERT INTO settings (latitude, longitude, radius, waktu_masuk, waktu_pulang, school_name) 
                   VALUES (-6.200000, 106.816666, 100, '07:00:00', '14:00:00', 'SD NEGERI CONTOH')");
    }

    // 14. Attendance Records
    $db->exec("CREATE TABLE IF NOT EXISTS attendance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        jam_masuk TEXT,
        jam_pulang TEXT,
        status TEXT,
        durasi TEXT,
        lokasi_lat REAL,
        lokasi_lng REAL,
        jarak REAL,
        keterangan TEXT,
        foto_masuk TEXT,
        foto_pulang TEXT,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // 15. Izin Records
    $db->exec("CREATE TABLE IF NOT EXISTS izin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        jenis_izin TEXT NOT NULL CHECK(jenis_izin IN ('izin', 'sakit')),
        keterangan TEXT,
        foto TEXT,
        status TEXT DEFAULT 'pending',
        lokasi_lat REAL,
        lokasi_lng REAL,
        jarak REAL,
        FOREIGN KEY(user_id) REFERENCES users(id)
    )");

    // --- SCHOOL ADMINISTRATION TABLES (KEPALA SEKOLAH) ---

    // 16. School Documents (RKAS, Kalender, SK, DLL)
    $db->exec("CREATE TABLE IF NOT EXISTS school_documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL, -- 'rkas', 'kalender', 'program_ks', 'laporan', 'akreditasi', 'eds', 'sk', 'peraturan'
        title TEXT NOT NULL,
        file_path TEXT,
        description TEXT,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 17. School Logs (Diary, Notulen, Pembinaan, Supervisi, Catatan Khusus)
    $db->exec("CREATE TABLE IF NOT EXISTS school_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL, -- 'diary_ks', 'notulen', 'pembinaan_guru', 'supervisi_admin', 'catatan_khusus'
        date DATE NOT NULL,
        subject TEXT, -- Person name or Topic
        details TEXT, -- Main content
        notes TEXT, -- Additional notes/action
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 18. School Finance (BKU)
    $db->exec("CREATE TABLE IF NOT EXISTS school_finance (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date DATE NOT NULL,
        type TEXT NOT NULL, -- 'masuk', 'keluar'
        amount REAL DEFAULT 0,
        category TEXT, -- 'BOS', 'APBD', 'Komite', 'Lainnya'
        description TEXT,
        proof_file TEXT, -- Path to receipt/invoice
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 19. School Inventory (Sarpras)
    $db->exec("CREATE TABLE IF NOT EXISTS school_inventory (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_name TEXT NOT NULL,
        quantity INTEGER DEFAULT 0,
        condition TEXT, -- 'baik', 'rusak_ringan', 'rusak_berat'
        location TEXT,
        acquisition_date DATE,
        price REAL,
        notes TEXT
    )");

    // 20. School Correspondence (Surat Menurat)
    $db->exec("CREATE TABLE IF NOT EXISTS school_correspondence (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL, -- 'masuk', 'keluar'
        reference_number TEXT, -- Nomor Surat
        date DATE NOT NULL,
        sender TEXT, -- Pengirim (for masuk) or Tujuan (for keluar)
        subject TEXT, -- Perihal
        file_path TEXT,
        disposition TEXT, -- Disposisi (for masuk)
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 21. School Guest Book
    $db->exec("CREATE TABLE IF NOT EXISTS school_guest_book (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date DATE NOT NULL,
        name TEXT NOT NULL,
        organization TEXT,
        purpose TEXT,
        pic_school TEXT, -- Who they met
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Add extended columns for Guest Book
    $gbCols = $db->query("PRAGMA table_info(school_guest_book)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $newGbCols = [
        'position',
        'phone',
        'address',
        'time_in',
        'time_out',
        'result',
        'status',
        'notes',
        'gender'
    ];
    foreach ($newGbCols as $col) {
        if (!in_array($col, $gbCols)) {
            $db->exec("ALTER TABLE school_guest_book ADD COLUMN $col TEXT");
        }
    }

    // Add 'nip' column to users if not exists
    $userCols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('nip', $userCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN nip TEXT");
    }

    // --- MIGRATION FOR NOTULEN ---
    $logCols = $db->query("PRAGMA table_info(school_logs)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $newNotulenCols = ['book_number', 'meeting_time', 'location', 'leader_name', 'leader_nip', 'notulis_name', 'notulis_nip', 'moderator_name', 'attendees_json', 'decisions_json', 'agenda_header'];
    foreach ($newNotulenCols as $col) {
        if (!in_array($col, $logCols)) {
            $db->exec("ALTER TABLE school_logs ADD COLUMN $col TEXT");
        }
    }

    // --- MIGRATION FOR DIARY KS ---
    if (!in_array('school_year', $logCols)) {
        $db->exec("ALTER TABLE school_logs ADD COLUMN school_year TEXT");
    }
    if (!in_array('diary_content', $logCols)) {
        $db->exec("ALTER TABLE school_logs ADD COLUMN diary_content TEXT");
    }

    // 22. Teacher Coaching (Pembinaan Profesi Guru)
    $db->exec("CREATE TABLE IF NOT EXISTS teacher_coaching (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER NOT NULL,
        teacher_rank TEXT, -- A: Pangkat/Golongan
        years_of_service INTEGER, -- A: Masa Kerja (Tahun)
        school_year TEXT NOT NULL,
        semester TEXT NOT NULL,
        coaching_type TEXT, -- 'In Service', 'On Service', 'Induksi'
        analysis_strengths TEXT, -- B1: Aspek yang kuat
        analysis_improvements TEXT, -- B1: Aspek yang perlu pengembangan
        competency_focus TEXT, -- B2: Pedagogik, Kepribadian, Sosial, Profesional (comma separated)
        coaching_goal TEXT, -- C: Tujuan Pembinaan
        program_data TEXT, -- C: JSON array of program activities
        progress_data TEXT, -- D: JSON array of progress notes
        achievement_level TEXT, -- E1: Level capaian
        teacher_feedback TEXT, -- E2: Feedback dari guru
        principal_analysis TEXT, -- E3: Kesimpulan umum
        recommendations_maintain TEXT, -- E3: Rekomendasi untuk dipertahankan
        recommendations_improve TEXT, -- E3: Rekomendasi untuk ditingkatkan
        followup_actions TEXT, -- E3: Rencana tindak lanjut
        completion_date DATE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(teacher_id) REFERENCES users(id)
    )");

    // Add new columns to existing teacher_coaching table if not exists
    $tcCols = $db->query("PRAGMA table_info(teacher_coaching)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('teacher_rank', $tcCols)) {
        $db->exec("ALTER TABLE teacher_coaching ADD COLUMN teacher_rank TEXT");
    }
    if (!in_array('years_of_service', $tcCols)) {
        $db->exec("ALTER TABLE teacher_coaching ADD COLUMN years_of_service INTEGER");
    }

    // 23. Student Grades Table for Academic Data
    $db->exec("CREATE TABLE IF NOT EXISTS student_grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        subject TEXT NOT NULL,
        semester TEXT NOT NULL,
        exam_type TEXT NOT NULL, -- 'UTS', 'UAS', 'Rapor', 'UN/USBN'
        nilai REAL NOT NULL,
        ranking INTEGER,
        year TEXT,
        FOREIGN KEY(student_id) REFERENCES students(id)
    )");

    // Add gender column to student_details if not exists
    $sdCols = $db->query("PRAGMA table_info(student_details)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('gender', $sdCols)) {
        $db->exec("ALTER TABLE student_details ADD COLUMN gender TEXT");
    }
    if (!in_array('religion', $sdCols)) {
        $db->exec("ALTER TABLE student_details ADD COLUMN religion TEXT");
    }
    if (!in_array('status', $sdCols)) {
        $db->exec("ALTER TABLE student_details ADD COLUMN status TEXT"); // KIP, Jalur Masuk, etc
    }



    // --- MIGRATION FOR SK DOCUMENTS ---
    $docCols = $db->query("PRAGMA table_info(school_documents)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('category', $docCols)) {
        $db->exec("ALTER TABLE school_documents ADD COLUMN category TEXT");
    }
    if (!in_array('related_user_id', $docCols)) {
        $db->exec("ALTER TABLE school_documents ADD COLUMN related_user_id INTEGER");
    }
    if (!in_array('class_name', $docCols)) {
        $db->exec("ALTER TABLE school_documents ADD COLUMN class_name TEXT");
    }
    if (!in_array('semester', $docCols)) {
        $db->exec("ALTER TABLE school_documents ADD COLUMN semester TEXT");
    }

    // 24. Academic Supervision (Supervisi Akademik)
    $db->exec("CREATE TABLE IF NOT EXISTS academic_supervision (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        teacher_id INTEGER,
        school_name TEXT,
        date DATE,               -- Pra Observasi Date
        observation_date DATE,   -- Observasi Date
        post_date DATE,          -- Pasca Observasi Date
        subject TEXT,
        class_name TEXT,
        topic TEXT,
        time_allocation TEXT,
        kd TEXT,
        indicators TEXT, -- Multiline
        objectives TEXT, -- Multiline 
        methods TEXT,
        media TEXT,
        focus_aspects TEXT,
        special_needs TEXT,
        obs_time_start TEXT,
        obs_time_end TEXT,
        students_present INTEGER,
        planning_scores TEXT, -- JSON
        execution_scores TEXT, -- JSON
        assessment_scores TEXT, -- JSON
        planning_notes TEXT, -- JSON
        execution_notes TEXT, -- JSON
        assessment_notes TEXT, -- JSON
        strengths TEXT,
        areas_for_improvement TEXT,
        total_score INTEGER,
        max_score INTEGER,
        percentage REAL,
        recommendation TEXT,
        post_reflection TEXT,
        post_feedback_strengths TEXT,
        post_feedback_improvements TEXT,
        post_action_plan_targets TEXT,
        post_action_plan_support TEXT,
        post_timeline TEXT,
        post_next_date DATE,
        photo_path TEXT,
        supervisor_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(teacher_id) REFERENCES users(id)
    )");

    // Ensure observation_date and post_date exist (Migration fix)
    try {
        $db->query("SELECT observation_date FROM academic_supervision LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("ALTER TABLE academic_supervision ADD COLUMN observation_date DATE");
        $db->exec("ALTER TABLE academic_supervision ADD COLUMN post_date DATE");
    }

    // Ensure document_path exists
    try {
        $db->query("SELECT document_path FROM academic_supervision LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("ALTER TABLE academic_supervision ADD COLUMN document_path TEXT");
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
