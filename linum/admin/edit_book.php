<?php
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$successMsg = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_books.php");
    exit();
}

$bookId = intval($_GET['id']);

// Fetch existing book data
try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
    $stmt->execute([':id' => $bookId]);
    $book = $stmt->fetch();

    if (!$book) {
        header("Location: manage_books.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $publisher = trim($_POST['publisher']);
    $link = !empty($_POST['link']) ? trim($_POST['link']) : null;
    $pdfPath = $book['pdf_path'];

    // Validate required fields
    if (empty($title))
        $errors[] = "Judul buku wajib diisi.";
    if (empty($author))
        $errors[] = "Penulis wajib diisi.";
    if (empty($publisher))
        $errors[] = "Penerbit wajib diisi.";

    // Ensure at least one of 'link' or 'PDF file' is provided
    if (empty($link) && empty($_FILES['pdf']['name']) && empty($pdfPath)) {
        $errors[] = "Harap berikan link atau unggah file PDF.";
    }

    // Handling file upload if provided
    if (!empty($_FILES['pdf']['name'])) {
        if ($_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['pdf']['tmp_name'];
            $fileName = $_FILES['pdf']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Validate PDF file
            if ($fileExtension !== 'pdf') {
                $errors[] = "Hanya file PDF yang diperbolehkan.";
            }

            if (empty($errors)) {
                $newFileName = uniqid('book_', true) . '.pdf';
                $destination = __DIR__ . '/../uploads/books/' . $newFileName;

                if (!move_uploaded_file($fileTmpPath, $destination)) {
                    $errors[] = "Gagal mengunggah file PDF.";
                } else {
                    // Delete old PDF file if exists
                    if (!empty($pdfPath)) {
                        $oldFile = __DIR__ . '/../' . $pdfPath;
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    $pdfPath = 'uploads/books/' . $newFileName; // store relative path
                }
            }
        } else {
            $errors[] = "Terjadi kesalahan saat mengunggah file PDF.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE books SET title = :title, author = :author, publisher = :publisher, link = :link, pdf_path = :pdf_path, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([
                ':title' => $title,
                ':author' => $author,
                ':publisher' => $publisher,
                ':link' => $link,
                ':pdf_path' => $pdfPath,
                ':id' => $bookId
            ]);
            $successMsg = "Buku berhasil diperbarui.";

            // Refresh book data
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
            $stmt->execute([':id' => $bookId]);
            $book = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Set page title
$pageTitle = "Edit Buku - Admin";

// Include common header
// Include common header
require_once 'include_header.php';
?>
<!-- PDF.js Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
    /* Styling for PDF Viewer */
    #pdf-container {
        min-height: 400px;
        background-color: #525659;
    }

    #loading {
        display: none;
    }
</style>

<!-- Back Button -->
<div class="container py-2">
    <a href="manage_books.php" class="btn btn-outline-primary">
        <i class="fas fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<div class="container">
    <h1 class="mb-3"><i class="fas fa-edit"></i> Edit Buku</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="mb-5">
        <div class="mb-3">
            <label for="title" class="form-label">Judul Buku <span class="text-danger">*</span></label>
            <input type="text" name="title" id="title" class="form-control" required
                value="<?php echo htmlspecialchars($book['title']); ?>" />
        </div>
        <div class="mb-3">
            <label for="author" class="form-label">Penulis <span class="text-danger">*</span></label>
            <input type="text" name="author" id="author" class="form-control" required
                value="<?php echo htmlspecialchars($book['author']); ?>" />
        </div>
        <div class="mb-3">
            <label for="publisher" class="form-label">Penerbit <span class="text-danger">*</span></label>
            <input type="text" name="publisher" id="publisher" class="form-control" required
                value="<?php echo htmlspecialchars($book['publisher']); ?>" />
        </div>
        <div class="mb-3">
            <label for="link" class="form-label">Link Buku (Optional)</label>
            <input type="url" name="link" id="link" class="form-control" placeholder="https://"
                value="<?php echo htmlspecialchars($book['link'] ?? ''); ?>" />
        </div>
        <div class="mb-3">
            <label for="pdf" class="form-label">Unggah File PDF Baru (Optional)</label>
            <input type="file" name="pdf" id="pdf" class="form-control" accept="application/pdf" />
            <?php if (!empty($book['pdf_path'])): ?>
                <small class="form-text text-muted">File PDF saat ini:
                    <a href="#" class="view-pdf" data-pdf="../<?php echo htmlspecialchars($book['pdf_path']); ?>"
                        data-bs-toggle="modal" data-bs-target="#pdfModal">
                        Lihat PDF
                    </a>
                </small>
            <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Simpan Perubahan
        </button>
        <a href="manage_books.php" class="btn btn-secondary ms-2">Batal</a>
    </form>
</div>
</div>

<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">Preview PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh; overflow-y: auto; background-color: #525659;">
                <div id="loading" class="text-center text-white pt-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat Dokumen...</p>
                </div>
                <div id="pdf-container" class="d-flex flex-column align-items-center py-4"></div>
            </div>
            <div class="modal-footer justify-content-between bg-light">
                <div id="pdf-controls" style="display: none;" class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="prevBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="small fw-bold">Halaman <span id="page_num_display">1</span> / <span
                            id="page_count">--</span></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="nextBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <a id="downloadPdf" href="#" class="btn btn-primary" download>
                        <i class="fas fa-download me-1"></i> Unduh PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // PDF.js Logic
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

            // Make canvas responsive
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';

            const renderTask = page.render({
                canvasContext: ctx,
                viewport: viewport
            });

            renderTask.promise.then(function () {
                pageRendering = false;
                const numDisplay = document.getElementById('page_num_display');
                if (numDisplay) numDisplay.textContent = num;

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

    async function previewPDF(fileUrl) {
        const container = document.getElementById('pdf-container');
        const loading = document.getElementById('loading');
        const controls = document.getElementById('pdf-controls');
        const downloadBtn = document.getElementById('downloadPdf');

        // Extract filename
        const filename = fileUrl.split('/').pop();

        loading.style.display = 'block';
        container.innerHTML = '';
        controls.style.display = 'none';

        if (downloadBtn) downloadBtn.href = fileUrl;

        container.appendChild(canvas);

        try {
            // Fetch from admin helper
            const response = await fetch('get_book_pdf.php?file=' + encodeURIComponent(filename));
            if (!response.ok) throw new Error('Gagal menghubungi server');

            const json = await response.json();
            if (json.error) throw new Error(json.error);

            const binaryString = atob(json.content);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            const loadingTask = pdfjsLib.getDocument({ data: bytes });
            pdfDoc = await loadingTask.promise;

            document.getElementById('page_count').textContent = pdfDoc.numPages;
            controls.style.display = 'flex';
            loading.style.display = 'none';

            document.getElementById('prevBtn').onclick = onPrevPage;
            document.getElementById('nextBtn').onclick = onNextPage;

            pageNum = 1;
            renderPage(pageNum);

        } catch (err) {
            console.error("PDF Load Error:", err);
            loading.innerHTML = '<div class="text-danger p-3"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Gagal memuat PDF:<br>' + err.message + '</div>';
        }
    }

    var pdfModal = document.getElementById('pdfModal');
    pdfModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        // Only act if it's the view-pdf button
        if (button.classList.contains('view-pdf')) {
            var pdfSrc = button.getAttribute('data-pdf');
            previewPDF(pdfSrc);
        }
    });

    pdfModal.addEventListener('hidden.bs.modal', function () {
        if (ctx && canvas.width > 0) ctx.clearRect(0, 0, canvas.width, canvas.height);
        pdfDoc = null;
        pageNum = 1;
        document.getElementById('pdf-container').innerHTML = '';
    });
</script>
<?php require_once 'footer.php'; ?>