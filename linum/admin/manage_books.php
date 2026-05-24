<?php
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'guru')) {
    header("Location: ../login.php");
    exit();
}

$errors = [];
$successMsg = '';
$previewLinksHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete request
    if (isset($_POST['delete_id'])) {
        $deleteId = intval($_POST['delete_id']);
        try {
            // Get pdf_path to delete file
            $stmt = $pdo->prepare("SELECT pdf_path FROM books WHERE id = :id");
            $stmt->execute([':id' => $deleteId]);
            $book = $stmt->fetch();

            if ($book) {
                if (!empty($book['pdf_path'])) {
                    $pdfFile = __DIR__ . '/../' . $book['pdf_path'];
                    if (file_exists($pdfFile)) {
                        unlink($pdfFile);
                    }
                }
                // Delete book record
                $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
                $stmt->execute([':id' => $deleteId]);
                $successMsg = "Buku berhasil dihapus.";
            } else {
                $errors[] = "Buku tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error saat menghapus: " . $e->getMessage();
        }
    } else {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $publisher = trim($_POST['publisher']);
        $link = !empty($_POST['link']) ? trim($_POST['link']) : null;
        $pdfPath = null;

        // Validate required fields
        if (empty($title))
            $errors[] = "Judul buku wajib diisi.";
        if (empty($author))
            $errors[] = "Penulis wajib diisi.";
        if (empty($publisher))
            $errors[] = "Penerbit wajib diisi.";

        // Ensure at least one of 'link' or 'PDF file' is provided
        if (empty($link) && empty($_FILES['pdf']['name'])) {
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
                        $pdfPath = 'uploads/books/' . $newFileName; // store relative path
                    }
                }
            } else {
                $errors[] = "Terjadi kesalahan saat mengunggah file PDF.";
            }
        }

        // If no errors, insert the record
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO books (title, author, publisher, link, pdf_path) 
                               VALUES (:title, :author, :publisher, :link, :pdf_path)");
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':publisher' => $publisher,
                    ':link' => $link,
                    ':pdf_path' => $pdfPath
                ]);
                $successMsg = "Buku berhasil ditambahkan.";

                // Prepare preview links for display after adding book
                $previewLinks = [];
                if (!empty($link)) {
                    $previewLinks[] = '<a href="' . htmlspecialchars($link) . '" target="_blank" class="btn btn-outline-primary me-2">Preview Link</a>';
                }
                if (!empty($pdfPath)) {
                    $previewLinks[] = '<a href="../' . htmlspecialchars($pdfPath) . '" target="_blank" class="btn btn-outline-danger">Preview PDF</a>';
                }
                $previewLinksHtml = implode('', $previewLinks);

            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch all books
try {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY created_at DESC");
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error fetching books: " . $e->getMessage();
    $books = [];
}

// Set page title
$pageTitle = "Manajemen Buku - Admin";

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

<div class="container">
    <h1 class="mb-3"><i class="fas fa-book"></i> Manajemen Buku</h1>
    <img src="https://images.pexels.com/photos/590493/pexels-photo-590493.jpeg?auto=compress&cs=tinysrgb&dpr=2&h=650&w=940"
        alt="Books Banner" class="img-fluid rounded mb-4" />

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
            <?php if (!empty($previewLinksHtml)): ?>
                <div class="mt-3">
                    <?php echo $previewLinksHtml; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="mb-5">
        <div class="mb-3">
            <label for="title" class="form-label">Judul Buku <span class="text-danger">*</span></label>
            <input type="text" name="title" id="title" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="author" class="form-label">Penulis <span class="text-danger">*</span></label>
            <input type="text" name="author" id="author" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="publisher" class="form-label">Penerbit <span class="text-danger">*</span></label>
            <input type="text" name="publisher" id="publisher" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="link" class="form-label">Link Buku (Optional)</label>
            <input type="url" name="link" id="link" class="form-control" placeholder="https://" />
        </div>
        <div class="mb-3">
            <label for="pdf" class="form-label">Unggah File PDF (Optional)</label>
            <input type="file" name="pdf" id="pdf" class="form-control" accept="application/pdf" />
        </div>
        <button type="submit" class="chip-btn chip-btn-blue">
            <i class="fas fa-upload me-1"></i> Unggah Buku
        </button>
    </form>

    <h2 class="mb-3">Daftar Buku</h2>
    <?php if (empty($books)): ?>
        <p>Tidak ada buku yang diunggah sejauh ini.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle text-nowrap">
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Penulis</th>
                        <th>Penerbit</th>
                        <th>Link / File</th>
                        <th>Diupload</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['publisher']); ?></td>
                            <td>
                                <?php if (!empty($book['link'])): ?>
                                    <a href="<?php echo htmlspecialchars($book['link']); ?>" target="_blank">Lihat Link</a>
                                <?php elseif (!empty($book['pdf_path'])): ?>
                                    <a href="#" class="view-pdf" data-pdf="../<?php echo htmlspecialchars($book['pdf_path']); ?>"
                                        data-bs-toggle="modal" data-bs-target="#pdfModal">
                                        <i class="fas fa-file-pdf text-danger"></i> PDF
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date("d-m-Y H:i", strtotime($book['created_at'])); ?></td>
                            <td>
                                <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="chip-btn chip-btn-yellow chip-btn-sm me-1">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button type="button" class="chip-btn chip-btn-red chip-btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#deleteModal" data-book-id="<?php echo $book['id']; ?>">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="delete_id" id="delete_id" />
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Apakah Anda yakin ingin menghapus buku ini?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="chip-btn chip-btn-red">Hapus</button>
                    </div>
                </div>
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
                        <button type="button" class="chip-btn chip-btn-gray chip-btn-sm" id="prevBtn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="small fw-bold">Halaman <span id="page_num_display">1</span> / <span
                                id="page_count">--</span></span>
                        <button type="button" class="chip-btn chip-btn-gray chip-btn-sm" id="nextBtn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div>
                        <button type="button" class="chip-btn chip-btn-gray" data-bs-dismiss="modal">Tutup</button>
                        <a id="downloadPdf" href="#" class="chip-btn chip-btn-blue" download>
                            <i class="fas fa-download me-1"></i> Unduh PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var deleteModal = document.getElementById('deleteModal');
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button.getAttribute('data-book-id')) { // Ensure it's the delete button
                var bookId = button.getAttribute('data-book-id');
                var deleteIdInput = document.getElementById('delete_id');
                deleteIdInput.value = bookId;
            }
        });

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
</div>
<?php require_once 'footer.php'; ?>