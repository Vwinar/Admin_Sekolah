<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$stmt = $db->prepare("SELECT r.*, u.full_name as teacher_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$stmt->execute([$id]);
$report = $stmt->fetch();

if (!$report) {
    die("Laporan tidak ditemukan.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'];
    $comment = $_POST['admin_comment'];

    $update = $db->prepare("UPDATE reports SET status = ?, admin_comment = ? WHERE id = ?");
    $update->execute([$status, $comment, $id]);

    header('Location: dashboard_admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Laporan</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.85);
        }

        .modal-content {
            background-color: #383838;
            margin: 2% auto;
            padding: 0;
            width: 80%;
            height: 90%;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #2b2b2b;
            color: white;
            border-radius: 8px 8px 0 0;
        }

        .close-btn {
            color: #ccc;
            font-size: 28px;
            cursor: pointer;
        }

        .close-btn:hover {
            color: #fff;
        }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: #383838;
        }

        canvas {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            margin-bottom: 20px;
            max-width: 100%;
        }

        #loading {
            color: white;
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 100%; padding: 2rem;">
        <a href="monitoring.php" style="display: inline-block; margin-bottom: 1rem;">&larr; Kembali ke
            Monitoring</a>

        <header class="header">
            <div>
                <h1>Review Laporan: <?= htmlspecialchars($report['teacher_name']) ?></h1>
                <p style="color: var(--text-muted)">Tanggal: <?= date('d M Y', strtotime($report['report_date'])) ?></p>
            </div>
            <span class="badge badge-<?= $report['status'] ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                <?= ucfirst($report['status']) ?>
            </span>
        </header>

        <?php
        // Determine which tabs have data
        $has_plan_today = !empty($report['plan_topic']) || !empty($report['plan_subject']) || !empty($report['module_file']);
        $has_report_today = !empty($report['material_taught']) || !empty($report['attendance']);
        $has_evaluation = !empty($report['reflection']) || !empty($report['evaluation_file']);
        $has_plan_tomorrow = !empty($report['plan_material']);

        // Set default active tab logic
        // Priority: Report (Hari Ini) > Plan Today > Evaluation > Plan Tomorrow
        // We want to open the *first relevant* tab or the Report tab if it exists.
        $active_tab = '';
        if ($has_report_today) {
            $active_tab = 'view2';
        } elseif ($has_plan_today) {
            $active_tab = 'view1';
        } elseif ($has_evaluation) {
            $active_tab = 'view3';
        } elseif ($has_plan_tomorrow) {
            $active_tab = 'view4';
        } else {
            $active_tab = 'view2'; // Fallback
        }
        ?>

        <style>
            .review-grid {
                display: flex;
                gap: 1.5rem;
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .report-content {
                flex: 1;
                min-width: 0;
            }

            .admin-sidebar {
                width: 300px;
                flex-shrink: 0;
            }

            @media (max-width: 768px) {
                .review-grid {
                    flex-direction: column;
                }

                .admin-sidebar {
                    width: 100%;
                }
            }
        </style>
        <div class="review-grid">
            <div class="card report-content">
                <?php if ($has_plan_today || $has_report_today || $has_evaluation || $has_plan_tomorrow): ?>
                    <div class="tabs">
                        <?php if ($has_plan_today): ?>
                            <button type="button" class="tab-btn <?= $active_tab == 'view1' ? 'active' : '' ?>"
                                onclick="openTab(event, 'view1')">Rencana Hari Ini</button>
                        <?php endif; ?>

                        <?php if ($has_report_today): ?>
                            <button type="button" class="tab-btn <?= $active_tab == 'view2' ? 'active' : '' ?>"
                                onclick="openTab(event, 'view2')">Laporan Harian</button>
                        <?php endif; ?>

                        <?php if ($has_evaluation): ?>
                            <button type="button" class="tab-btn <?= $active_tab == 'view3' ? 'active' : '' ?>"
                                onclick="openTab(event, 'view3')">Evaluasi</button>
                        <?php endif; ?>

                        <?php if ($has_plan_tomorrow): ?>
                            <button type="button" class="tab-btn <?= $active_tab == 'view4' ? 'active' : '' ?>"
                                onclick="openTab(event, 'view4')">Rencana Besok</button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="p-3">Belum ada data laporan yang diisi.</div>
                <?php endif; ?>

                <div id="view1" class="tab-content <?= $active_tab == 'view1' ? 'active' : '' ?>">
                    <h3 class="mb-2">Rencana Hari Ini</h3>
                    <div class="mb-2"><strong>Mapel / Kelas:</strong>
                        <p><?= htmlspecialchars($report['plan_subject'] ?? $report['subject']) ?> /
                            <?= htmlspecialchars($report['class_name']) ?>
                        </p>
                    </div>
                    <?php if (!empty($report['plan_time'])): ?>
                        <div class="mb-2"><strong>Waktu:</strong>
                            <p><?= htmlspecialchars($report['plan_time']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_topic'])): ?>
                        <div class="mb-2"><strong>Topik:</strong>
                            <p><?= htmlspecialchars($report['plan_topic']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_learning_objective'])): ?>
                        <div class="mb-2"><strong>Tujuan Pembelajaran:</strong>
                            <p><?= nl2br(htmlspecialchars($report['plan_learning_objective'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_method_type'])): ?>
                        <div class="mb-2"><strong>Metode:</strong>
                            <p><?= htmlspecialchars($report['plan_method_type']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_media_used'])): ?>
                        <div class="mb-2"><strong>Media:</strong>
                            <p><?= htmlspecialchars($report['plan_media_used']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_assessment_used'])): ?>
                        <div class="mb-2"><strong>Asesmen:</strong>
                            <p><?= htmlspecialchars($report['plan_assessment_used']) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($report['plan_notes'])): ?>
                        <div class="mb-2"><strong>Catatan Lain:</strong>
                            <p><?= nl2br(htmlspecialchars($report['plan_notes'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($report['module_file']):
                        $curFile = __DIR__ . '/../uploads/' . $report['module_file'];
                        if (file_exists($curFile)): ?>
                            <div class="mb-2"
                                style="margin-top: 1.5rem; padding: 1rem; border: 1px dashed var(--border); border-radius: 0.5rem; background: #f8fafc;">
                                <strong>Modul Ajar (PDF):</strong>
                                <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 1rem;">
                                    <span style="font-size: 2rem;">📄</span>
                                    <div>
                                        <p style="font-weight: 500; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($report['module_file']) ?>
                                        </p>
                                        <button type="button"
                                            onclick="previewPDF('<?= htmlspecialchars($report['module_file']) ?>')"
                                            class="chip-btn chip-btn-blue" style="cursor: pointer;">
                                            Buka Preview PDF
                                        </button>
                                        <a href="../print/get_pdf_content.php?file=<?= urlencode($report['module_file']) ?>&download=1"
                                            class="chip-btn chip-btn-purple" style="margin-left: 0.5rem;">Download</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-2"
                                style="margin-top: 1.5rem; padding: 1rem; border: 1px dashed #ef4444; border-radius: 0.5rem; background: #fef2f2; color: #b91c1c;">
                                <strong>Error:</strong> File PDF tidak ditemukan di server.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="mb-2"
                            style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; color: #64748b;">
                            Tidak ada modul yang diupload.</div>
                    <?php endif; ?>
                </div>

                <div id="view2" class="tab-content <?= $active_tab == 'view2' ? 'active' : '' ?>">
                    <h3 class="mb-2">Laporan Pembelajaran</h3>
                    <div class="mb-2"><strong>Mapel / Kelas:</strong>
                        <p><?= htmlspecialchars($report['subject']) ?> / <?= htmlspecialchars($report['class_name']) ?>
                        </p>
                    </div>
                    <div class="mb-2"><strong>Materi Diajarkan:</strong>
                        <p><?= nl2br(htmlspecialchars($report['material_taught'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Kehadiran:</strong>
                        <p><?= htmlspecialchars($report['attendance']) ?></p>
                    </div>
                    <div class="mb-2"><strong>Pencapaian:</strong>
                        <p><?= nl2br(htmlspecialchars($report['achievement'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Kendala:</strong>
                        <p><?= nl2br(htmlspecialchars($report['obstacles'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Solusi:</strong>
                        <p><?= nl2br(htmlspecialchars($report['solution'])) ?></p>
                    </div>
                </div>

                <div id="view3" class="tab-content <?= $active_tab == 'view3' ? 'active' : '' ?>">
                    <h3 class="mb-2">Evaluasi Diri</h3>
                    <?php if ($report['evaluation_file']): ?>
                        <div class="mb-2">
                            <strong>Bukti Evaluasi / Foto:</strong>
                            <div style="margin-top: 0.5rem;">
                                <img src="../uploads/<?= htmlspecialchars($report['evaluation_file']) ?>" alt="Evaluasi"
                                    style="max-width: 100%; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-2"><strong>Refleksi:</strong>
                        <p><?= nl2br(htmlspecialchars($report['reflection'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Catatan Perbaikan:</strong>
                        <p><?= nl2br(htmlspecialchars($report['improvement_notes'])) ?></p>
                    </div>
                </div>

                <div id="view4" class="tab-content <?= $active_tab == 'view4' ? 'active' : '' ?>">
                    <h3 class="mb-2">Rencana Besok</h3>
                    <div class="mb-2"><strong>Materi:</strong>
                        <p><?= nl2br(htmlspecialchars($report['plan_material'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Media:</strong>
                        <p><?= htmlspecialchars($report['plan_media']) ?></p>
                    </div>
                    <div class="mb-2"><strong>Metode:</strong>
                        <p><?= htmlspecialchars($report['plan_method']) ?></p>
                    </div>
                    <div class="mb-2"><strong>Tujuan:</strong>
                        <p><?= nl2br(htmlspecialchars($report['plan_goal'])) ?></p>
                    </div>
                </div>
            </div>

            <div class="card admin-sidebar">
                <h3 class="mb-2">Tindakan Admin</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Status Persetujuan</label>
                        <select name="status" class="form-control" required style="max-width: 300px;">
                            <option value="approved" <?= $report['status'] == 'approved' ? 'selected' : '' ?>>Setujui
                                Laporan
                            </option>
                            <option value="revision" <?= $report['status'] == 'revision' ? 'selected' : '' ?>>Minta Revisi
                            </option>
                            <option value="rejected" <?= $report['status'] == 'rejected' ? 'selected' : '' ?>>Tolak Laporan
                            </option>
                            <option value="pending" <?= $report['status'] == 'pending' ? 'selected' : '' ?>>Tunda review
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Komentar / Catatan (Opsional)</label>
                        <textarea name="admin_comment" class="form-control" rows="3"
                            placeholder="Tambahkan catatan untuk guru..."><?= htmlspecialchars($report['admin_comment']) ?></textarea>
                    </div>
                    <button type="submit" class="chip-btn chip-btn-blue" style="cursor: pointer;">Simpan Hasil
                        Review</button>
                </form>
            </div>
        </div>
    </div>

    <!-- PDF MODAL -->
    <div id="pdfModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span>Preview PDF</span>
                    <span id="page_info" style="font-size: 0.8rem; margin-left: 1rem; color: #ccc;"></span>
                </div>
                <span class="close-btn" onclick="closePdfModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="loading">Memuat Dokumen...</div>
                <div id="pdf-container"></div>
            </div>
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let pageNum = 1;
        let pageRendering = false;
        let pageNumPending = null;
        const scale = 1.5;
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

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

        async function previewPDF(filename) {
            const modal = document.getElementById('pdfModal');
            const container = document.getElementById('pdf-container');
            const loading = document.getElementById('loading');

            modal.style.display = "block";
            loading.style.display = 'block';
            loading.innerText = 'Memuat Dokumen...';
            container.innerHTML = '';

            container.appendChild(canvas);

            const controls = document.createElement('div');
            controls.id = 'pdf-controls';
            controls.style.marginTop = '15px';
            controls.style.textAlign = 'center';
            controls.style.display = 'none';
            controls.innerHTML = `
                <button id="prevBtn" style="margin-right: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">← Sebelumnya</button>
                <span>Halaman <span id="page_num_display">1</span> / <span id="page_count">--</span></span>
                <button id="nextBtn" style="margin-left: 15px; padding: 6px 15px; background: #4b5563; color: white; border: none; border-radius: 4px; cursor: pointer;">Selanjutnya →</button>
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
                loading.style.display = 'none';
                loading.style.color = '#ff6b6b';
                loading.innerText = 'Gagal memuat PDF: ' + err.message;
                loading.style.display = 'block';
            }
        }

        function closePdfModal() {
            document.getElementById('pdfModal').style.display = "none";
            if (ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
            pdfDoc = null;
            pageNum = 1;
        }

        window.onclick = function (event) {
            if (event.target == document.getElementById('pdfModal')) closePdfModal();
        }
    </script>
</body>

</html>