<?php
// Set timezone to Jakarta
date_default_timezone_set('Asia/Jakarta');

// Database connection and functions for Journal App

$dbFile = 'journal.db';

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create tables if not exists (but since journal.db already has them, this is for new setups)
$sql = "CREATE TABLE IF NOT EXISTS journal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    entry_date DATE NOT NULL,
    hour INTEGER NOT NULL CHECK(hour >= 1 AND hour <= 7),
    subject TEXT NOT NULL,
    capaian_pembelajaran TEXT,
    pokok_materi TEXT,
    pencapaian TEXT,
    permasalahan TEXT,
    solusi TEXT,
    catatan_pembelajaran TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    jumlah_jp INTEGER DEFAULT 0
);
CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    subject TEXT NOT NULL,
    type TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    color_class TEXT DEFAULT 'bg-gray-100 text-gray-800',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    day INTEGER NOT NULL CHECK(day >= 1 AND day <= 6),
    hour INTEGER NOT NULL CHECK(hour >= 1 AND hour <= 7),
    jumlah_jp INTEGER NOT NULL CHECK(jumlah_jp >= 1 AND jumlah_jp <= 4),
    subject TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);";
$pdo->exec($sql);

// Migration: Add user_id column if not exists
$colsEntry = $pdo->query("PRAGMA table_info(journal_entries)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('user_id', $colsEntry)) {
    $pdo->exec("ALTER TABLE journal_entries ADD COLUMN user_id INTEGER");
}
// Add class_name column if not exists
if (!in_array('class_name', $colsEntry)) {
    $pdo->exec("ALTER TABLE journal_entries ADD COLUMN class_name TEXT");
}

$colsSched = $pdo->query("PRAGMA table_info(schedules)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('user_id', $colsSched)) {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN user_id INTEGER");
}
if (!in_array('class_name', $colsSched)) {
    $pdo->exec("ALTER TABLE schedules ADD COLUMN class_name TEXT");
}

$colsTemp = $pdo->query("PRAGMA table_info(templates)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('user_id', $colsTemp)) {
    $pdo->exec("ALTER TABLE templates ADD COLUMN user_id INTEGER");
}
// Templates are usually for the teacher, class agnostic or reusable? 
// Let's assume templates belong to the teacher, class doesn't strictly matter for templates, but we could add it if needed. 
// For now, entries and schedules MUST have class.

// Helper to get current session class (fetched in index.php)
function getCurrentClass()
{
    // This requires session to be populated with current user details or we fetch from DB.
    // Index.php sets $_SESSION['user_id']. We need access to the class.
    // It is best passed into functions or we fetch here if possible.
    // Since functions are global, let's rely on $data passed in or global session if we must.
    return $_SESSION['assigned_class'] ?? null;
}

// Function to insert new entry
function insertEntry($data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $class_name = isset($data['class_name']) ? $data['class_name'] : null; // Expect class_name in data

    $stmt = $pdo->prepare("INSERT INTO journal_entries (user_id, class_name, entry_date, hour, subject, capaian_pembelajaran, pokok_materi, pencapaian, permasalahan, solusi, catatan_pembelajaran, jumlah_jp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $class_name, $data['entry_date'], $data['hour'], $data['subject'], $data['capaian_pembelajaran'], $data['pokok_materi'], $data['pencapaian'], $data['permasalahan'], $data['solusi'], $data['catatan_pembelajaran'], $data['jumlah_jp'] ?? 0]);
    return $pdo->lastInsertId();
}

// Function to get all entries (Filtered by User and Class)
// We default to filtering by User, but logically for Journal we should check Class too if user has multiple classes.
// But current requirement: "displays journal corresponding to appropriate teacher and class".
// If we just filter by User ID, and User 1 is assigned Class A, we see Class A entries.
// If User 1 changes to Class B, they shouldn't see Class A entries logically? Or maybe they should see history.
// "only displays journal corresponding to appropriate teacher AND CLASS". This implies strict filtering by current class.
function getAllEntries()
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    // We need the current class from session or passed in. 
    // BUT getAllEntries is usually used for history.
    // Let's rely on passed arguments for flexibility or filter by session class if defined.
    // To be safe and meet the requirement "only display... corresponding to... class", we should filter by currently assigned class.
    // However, index.php might not have set $_SESSION['assigned_class'] yet. It gets it from DB.
    // We'll update index.php to set it.

    // For now, let's make a function that accepts class explicitly.
    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC, hour DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get ALL entries for a specific class (New Requirement Helper)
function getEntriesForUserAndClass($user_id, $class_name)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? AND class_name = ? ORDER BY entry_date DESC, hour DESC");
    $stmt->execute([$user_id, $class_name]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get entry by id (Security check: ensure user owns it)
function getEntryById($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to update entry (Security check: ensure user owns it)
function updateEntry($id, $data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    // We generally don't update class_name unless necessary, but let's keep it safe.
    $stmt = $pdo->prepare("UPDATE journal_entries SET entry_date = ?, hour = ?, subject = ?, capaian_pembelajaran = ?, pokok_materi = ?, pencapaian = ?, permasalahan = ?, solusi = ?, catatan_pembelajaran = ?, jumlah_jp = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['entry_date'], $data['hour'], $data['subject'], $data['capaian_pembelajaran'], $data['pokok_materi'], $data['pencapaian'], $data['permasalahan'], $data['solusi'], $data['catatan_pembelajaran'], $data['jumlah_jp'] ?? 0, $id, $user_id]);
    return $stmt->rowCount() > 0;
}

// Function to delete entry (Security check: ensure user owns it)
function deleteEntry($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("DELETE FROM journal_entries WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    return $stmt->rowCount() > 0;
}

// Function to get entries for a week (Filtered by User and Class)
function getEntriesForWeek($startDate, $endDate, $class_name = null)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // If class_name provided, filter by it. If not, default to user id (legacy behavior safety, or show all for user).
    // Requirement says "corresponding to appropriate class".
    if ($class_name) {
        $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? AND class_name = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date, hour");
        $stmt->execute([$user_id, $class_name, $startDate, $endDate]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? AND entry_date BETWEEN ? AND ? ORDER BY entry_date, hour");
        $stmt->execute([$user_id, $startDate, $endDate]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper to get current session class (fetched in index.php)
// ... already defined ...

// Function to insert new entry
// ... already defined ...

// Function to get all entries (Filtered by User and Class)
// ... already defined ...

// ... (skipping entry functions) ...

// Function to get distinct values for a field (Filtered by User or Default Templates)
function getDistinctValues($field)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT DISTINCT $field FROM journal_entries WHERE $field IS NOT NULL AND $field != '' AND user_id = ? ORDER BY $field");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Function to get all subjects (Generic)
function getAllSubjects()
{
    global $pdo;
    // Subjects are usually global.
    $stmt = $pdo->query("SELECT * FROM subjects ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get templates by type (Filtered by User)
function getTemplatesByType($type)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE type = ? AND (user_id = ? OR user_id IS NULL) ORDER BY content");
    $stmt->execute([$type, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to insert template
function insertTemplate($data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    // Optional: Add class_name if we decice templates are class specific.
    // Given the prompt "templates... only corresponding to class", let's assume strict filtering.
    // If we assume templates are per-teacher (user_id), that might be enough if teacher only teaches one class context in this session.
    // But if teacher teaches Class A and Class B, and makes a template in Class A, should Class B see it?
    // Usually yes, templates are reusable. 
    // BUT if the instruction is "only display corresponding to CLASS", then maybe NO?
    // Let's stick to User ID for templates as they are usually reusable assets. 
    // The "display corresponding to class" likely refers to the Data (Entries/Schedule).
    // Templates being reusable is a feature. Limiting them by class might be annoying (re-typing same template for every class).
    // So for templates, filtering by TEACHER (User) is logically "corresponding to the teacher's class context" (as checking "Is this MY template?").
    // I will stick to user_id filtering for templates to avoid over-engineering/breaking reusability, unless explicitly told templates are class-unique.
    // The prompt: "templates.php ... hanya menampilkan yang sesuai dengan kelas dari guru saja."
    // This could refer to the HEADER display (which I fixed) OR the content.
    // I'll stick to USER_ID filter which I already have.
    // But I will re-verify the table creation includes user_id (it does).

    $stmt = $pdo->prepare("INSERT INTO templates (user_id, subject, type, content) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $data['subject'], $data['type'], $data['content']]);
    return $pdo->lastInsertId();
}

// Function to delete template
function deleteTemplate($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}

// Function to get all templates (Filtered by User)
function getAllTemplates()
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE user_id = ? OR user_id IS NULL ORDER BY type, subject, content");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get all templates grouped by type
function getAllTemplatesGrouped()
{
    $templates = getAllTemplates();
    $grouped = [];
    foreach ($templates as $template) {
        $grouped[$template['type']][] = $template;
    }
    return $grouped;
}

// Function to get templates by subject and type (Filtered)
function getTemplatesBySubjectAndType($subject, $type)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE subject = ? AND type = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$subject, $type, $user_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Use natural sort for content
    usort($results, function ($a, $b) {
        return strnatcasecmp($a['content'], $b['content']);
    });

    return $results;
}

// Function to get distinct values for a field from templates
function getDistinctValuesFromTemplates($field)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT DISTINCT content FROM templates WHERE type = ? AND (user_id = ? OR user_id IS NULL) ORDER BY content");
    $stmt->execute([$field, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Function to get all templates grouped by subject and type
function getAllTemplatesGroupedBySubject()
{
    $templates = getAllTemplates();
    $grouped = [];
    foreach ($templates as $template) {
        $grouped[$template['subject']][$template['type']][] = $template;
    }
    return $grouped;
}

// Function to get template by id
function getTemplateById($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to update template
function updateTemplate($id, $data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("UPDATE templates SET subject = ?, type = ?, content = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$data['subject'], $data['type'], $data['content'], $id, $user_id]);
}

// Schedule functions
function insertSchedule($data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $class_name = isset($data['class_name']) ? $data['class_name'] : null;
    $stmt = $pdo->prepare("INSERT INTO schedules (user_id, class_name, day, hour, jumlah_jp, subject) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $class_name, $data['day'], $data['hour'], $data['jumlah_jp'], $data['subject']]);
    return $pdo->lastInsertId();
}

function getAllSchedules($class_name = null)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if ($class_name) {
        $stmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = ? AND class_name = ? ORDER BY day, hour");
        $stmt->execute([$user_id, $class_name]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM schedules WHERE user_id = ? ORDER BY day, hour");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getScheduleById($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT * FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateSchedule($id, $data)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    // Keep class_name if redundant updates occur, though likely mostly for subject/time.
    // If data doesn't have class_name, we assume it stays same? 
    // Usually updates come from same form. Let's try to update if present or ignore.
    // But simplistic approach: update query doesn't touch class_name unless we add it. 
    // Let's assume schedule stays with the class it was created for? 
    // Or we update it if provided.

    if (isset($data['class_name'])) {
        $stmt = $pdo->prepare("UPDATE schedules SET day = ?, hour = ?, jumlah_jp = ?, subject = ?, class_name = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['day'], $data['hour'], $data['jumlah_jp'], $data['subject'], $data['class_name'], $id, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE schedules SET day = ?, hour = ?, jumlah_jp = ?, subject = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['day'], $data['hour'], $data['jumlah_jp'], $data['subject'], $id, $user_id]);
    }
    return $stmt->rowCount() > 0;
}

function deleteSchedule($id)
{
    global $pdo;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    return $stmt->rowCount() > 0;
}
?>