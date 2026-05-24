<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
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

if ($_SESSION['role'] === 'guru' && $report['user_id'] != $_SESSION['user_id']) {
    die("Akses ditolak.");
}

// DELETE LOGIC
if (isset($_POST['delete_report'])) {
    if (!empty($report['module_file'])) {
        $filePath = __DIR__ . '/../uploads/' . $report['module_file'];
        if (file_exists($filePath))
            unlink($filePath);
    }
    if (!empty($report['evaluation_file'])) {
        $imgPath = __DIR__ . '/../uploads/' . $report['evaluation_file'];
        if (file_exists($imgPath))
            unlink($imgPath);
    }

    $del = $db->prepare("DELETE FROM reports WHERE id = ?");
    $del->execute([$id]);

    $redirectUrl = ($_SESSION['role'] === 'admin') ? 'dashboard_admin.php' : 'riwayat.php';
    header("Location: " . $redirectUrl . "?msg=deleted");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan</title>
    <link rel="stylesheet" href="../assets/style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        /* Smaller font for details */
        .tab-content {
            font-size: 0.9rem;
        }

        .tab-content p,
        .tab-content div,
        .tab-content li {
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .tab-content strong {
            font-size: 0.9rem;
            color: var(--text-main);
        }

        .tab-content h3 {
            font-size: 1.1rem;
        }

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

        /* Mobile Responsive Styles for Detail Page */
        @media (max-width: 768px) {

            /* Top Navigation Bar */
            .detail-nav-container {
                flex-direction: column;
                gap: 10px;
                align-items: stretch !important;
            }

            .detail-nav-container a,
            .detail-nav-container form {
                width: 100%;
            }

            .detail-nav-container button {
                width: 100%;
                padding: 0.5rem 1rem !important;
                font-size: 0.875rem !important;
            }

            .detail-nav-container a {
                font-size: 0.875rem;
                padding: 0.5rem;
                text-align: center;
                display: block;
            }

            /* Modal Adjustments */
            .modal-content {
                width: 95%;
                height: 95%;
                margin: 2.5% auto;
            }

            .modal-header {
                padding: 0.75rem;
                flex-wrap: wrap;
            }

            .modal-header>div {
                font-size: 0.875rem;
            }

            #page_info {
                font-size: 0.7rem !important;
                margin-left: 0.5rem !important;
            }

            .close-btn {
                font-size: 24px;
            }

            .modal-body {
                padding: 10px;
            }

            /* PDF Preview Controls */
            #pdf-controls {
                display: flex !important;
                flex-direction: column;
                gap: 10px;
            }

            #pdf-controls button {
                width: 100%;
                padding: 8px 12px !important;
                font-size: 0.813rem !important;
                margin: 0 !important;
            }

            #pdf-controls span {
                text-align: center;
                font-size: 0.875rem;
                order: -1;
                /* Move page info to top */
            }

            /* Content Sections */
            .tab-content p {
                font-size: 0.875rem;
                line-height: 1.6;
            }

            .tab-content strong {
                font-size: 0.938rem;
            }

            /* PDF File Display */
            .mb-2[style*="margin-top: 1.5rem"] {
                margin-top: 1rem !important;
                padding: 0.75rem !important;
            }

            .mb-2[style*="margin-top: 1.5rem"] strong {
                font-size: 0.875rem;
            }

            .mb-2[style*="margin-top: 1.5rem"] p {
                font-size: 0.813rem;
            }

            .mb-2[style*="margin-top: 1.5rem"] .btn {
                font-size: 0.813rem !important;
                padding: 0.5rem 0.75rem !important;
                margin-bottom: 0.5rem;
                width: 100%;
            }

            .mb-2[style*="margin-top: 1.5rem"] a.btn {
                margin-left: 0 !important;
                margin-top: 0.5rem;
            }

            .mb-2[style*="margin-top: 1.5rem"]>div[style*="display: flex"] {
                flex-direction: column;
                gap: 0.75rem;
            }

            .mb-2[style*="margin-top: 1.5rem"] span[style*="font-size: 2rem"] {
                font-size: 1.5rem !important;
            }

            /* Image in Evaluasi Tab */
            .tab-content img {
                border-radius: 0.375rem;
            }
        }

        /* Extra Small Devices */
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.25rem;
            }

            .badge {
                font-size: 0.75rem;
                padding: 0.375rem 0.75rem;
            }

            .tab-btn {
                font-size: 0.813rem;
                padding: 0.5rem 0.75rem;
            }
        }
    </style>
</head>

<body>
    <div class="container" style="max-width: 1000px; padding-top: 2rem;">
        <div class="detail-nav-container"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="dashboard_admin.php">&larr; Kembali ke Dashboard</a>
            <?php else: ?>
                <a href="riwayat.php">&larr; Kembali ke Riwayat</a>
            <?php endif; ?>

            <form method="POST" onsubmit="return confirm('Yakin hapus laporan ini?');">
                <input type="hidden" name="delete_report" value="true">
                <button type="submit" class="chip-btn"
                    style="background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5;">
                    Hapus Laporan
                </button>
            </form>
        </div>

        <header class="header">
            <div>
                <h1>Detail Laporan: <?= htmlspecialchars($report['teacher_name']) ?></h1>
                <p style="color: var(--text-muted)">Tanggal: <?= date('d M Y', strtotime($report['report_date'])) ?></p>
            </div>
            <span class="badge badge-<?= $report['status'] ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                <?= ucfirst($report['status']) ?>
            </span>
        </header>

        <?php
        // Determine available data for tabs
        $hasTab1 = !empty($report['plan_topic']) || !empty($report['plan_learning_objective']) || !empty($report['module_file']);
        $hasTab2 = !empty($report['material_taught']) || !empty($report['attendance']);
        $hasTab3 = !empty($report['reflection']) || !empty($report['evaluation_file']);
        $hasTab4 = !empty($report['plan_goal']) || !empty($report['plan_material']) || !empty($report['plan_method']);

        // Determine active tab
        $showOnlyOne = isset($_GET['tab']);

        if ($showOnlyOne) {
            $activeTab = $_GET['tab'];
        } else {
            // Pick first available tab
            if ($hasTab1)
                $activeTab = 'tab1';
            elseif ($hasTab2)
                $activeTab = 'tab2';
            elseif ($hasTab3)
                $activeTab = 'tab3';
            elseif ($hasTab4)
                $activeTab = 'tab4';
            else
                $activeTab = 'tab2'; // Fallback
        }
        ?>

        <div class="card">
            <div class="tabs">
                <?php if (!$showOnlyOne): ?>
                    <?php if ($hasTab1): ?>
                        <button type="button" class="tab-btn <?= $activeTab == 'tab1' ? 'active' : '' ?>"
                            onclick="openTab(event, 'tab1')">1. Rencana Hari Ini</button>
                    <?php endif; ?>
                    <?php if ($hasTab2): ?>
                        <button type="button" class="tab-btn <?= $activeTab == 'tab2' ? 'active' : '' ?>"
                            onclick="openTab(event, 'tab2')">2. Laporan Harian</button>
                    <?php endif; ?>
                    <?php if ($hasTab3): ?>
                        <button type="button" class="tab-btn <?= $activeTab == 'tab3' ? 'active' : '' ?>"
                            onclick="openTab(event, 'tab3')">3. Evaluasi</button>
                    <?php endif; ?>
                    <?php if ($hasTab4): ?>
                        <button type="button" class="tab-btn <?= $activeTab == 'tab4' ? 'active' : '' ?>"
                            onclick="openTab(event, 'tab4')">4. Rencana Besok</button>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                    $titles = [
                        'tab1' => '📝 Rencana Hari Ini',
                        'tab2' => '📅 Laporan Harian',
                        'tab3' => '⭐ Evaluasi',
                        'tab4' => '🚀 Rencana Besok'
                    ];
                    ?>
                    <button type="button" class="tab-btn active"
                        style="cursor: default;"><?= $titles[$activeTab] ?? 'Detail' ?></button>
                <?php endif; ?>
            </div>


            <?php if ((!$showOnlyOne && $hasTab1) || $activeTab === 'tab1'): ?>
                <div id="tab1" class="tab-content <?= $activeTab === 'tab1' ? 'active' : '' ?>">
                    <h3 class="mb-2">Rencana Hari Ini</h3>

                    <div
                        style="background-color: #f0f9ff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bae6fd;">
                        <h4 style="margin-bottom: 0.5rem; color: #0284c7; font-size: 1rem;">Informasi Umum</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div><strong>Tanggal Rencana:</strong>
                                <div>
                                    <?= !empty($report['plan_date']) ? date('d M Y', strtotime($report['plan_date'])) : '-' ?>
                                </div>
                            </div>
                            <div><strong>Waktu:</strong>
                                <div><?= htmlspecialchars($report['plan_time'] ?? '-') ?></div>
                            </div>
                            <div><strong>Kelas:</strong>
                                <div><?= htmlspecialchars($report['plan_class'] ?? '-') ?></div>
                            </div>
                            <div><strong>Mata Pelajaran:</strong>
                                <div><?= htmlspecialchars($report['plan_subject'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="mb-2"><strong>Topik:</strong>
                            <p><?= htmlspecialchars($report['plan_topic'] ?? '-') ?></p>
                        </div>
                        <div class="mb-2"><strong>Tujuan Pembelajaran:</strong>
                            <p><?= nl2br(htmlspecialchars($report['plan_learning_objective'] ?? '-')) ?></p>
                        </div>
                        <div class="mb-2"><strong>Metode:</strong>
                            <p><span
                                    class="badge badge-revision"><?= htmlspecialchars($report['plan_method_type'] ?? '-') ?></span>
                            </p>
                        </div>
                    </div>

                    <div
                        style="background-color: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
                        <h4 style="margin-bottom: 0.5rem; color: #475569; font-size: 1rem;">Detail Pelaksanaan</h4>
                        <div
                            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                            <div><strong>Media yang Digunakan:</strong>
                                <p><?= htmlspecialchars($report['plan_media_used'] ?? '-') ?></p>
                            </div>
                            <div><strong>Asesmen:</strong>
                                <p><?= htmlspecialchars($report['plan_assessment_used'] ?? '-') ?></p>
                            </div>
                        </div>
                        <div style="margin-top: 1rem;"><strong>Catatan Tambahan:</strong>
                            <p><?= nl2br(htmlspecialchars($report['plan_notes'] ?? '-')) ?></p>
                        </div>
                    </div>

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
                                        <button onclick="previewPDF('<?= htmlspecialchars($report['module_file']) ?>')"
                                            class="btn chip-btn chip-btn-blue" style="width: auto;">
                                            Buka Preview PDF
                                        </button>
                                        <a href="../utils/get_pdf_content.php?file=<?= urlencode($report['module_file']) ?>&download=1"
                                            class="chip-btn chip-btn-purple"
                                            style="width: auto; margin-left: 0.5rem;">Download</a>
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
                        <div class="mb-2" style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem; color: #64748b;">
                            Tidak ada modul yang diupload.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ((!$showOnlyOne && $hasTab2) || $activeTab === 'tab2'): ?>
                <div id="tab2" class="tab-content <?= $activeTab === 'tab2' ? 'active' : '' ?>">
                    <h3 class="mb-2">Laporan Pelaksanaan</h3>
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
            <?php endif; ?>

            <?php if ((!$showOnlyOne && $hasTab3) || $activeTab === 'tab3'): ?>
                <div id="tab3" class="tab-content <?= $activeTab === 'tab3' ? 'active' : '' ?>">
                    <h3 class="mb-2">Evaluasi Diri</h3>
                    <?php if ($report['evaluation_file']): ?>
                        <div class="mb-2">
                            <strong>Bukti Evaluasi / Foto:</strong>
                            <div style="margin-top: 0.5rem;">
                                <img src="../uploads/<?= htmlspecialchars($report['evaluation_file']) ?>"
                                    style="max-width: 100%; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="mb-2"><strong>Refleksi:</strong>
                        <p><?= nl2br(htmlspecialchars($report['reflection'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Perbaikan:</strong>
                        <p><?= nl2br(htmlspecialchars($report['improvement_notes'])) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ((!$showOnlyOne && $hasTab4) || $activeTab === 'tab4'): ?>
                <div id="tab4" class="tab-content <?= $activeTab === 'tab4' ? 'active' : '' ?>">
                    <h3 class="mb-2">Rencana Besok</h3>
                    <div class="mb-2"><strong>Target Besok:</strong>
                        <p><?= nl2br(htmlspecialchars($report['plan_goal'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Materi:</strong>
                        <p><?= nl2br(htmlspecialchars($report['plan_material'])) ?></p>
                    </div>
                    <div class="mb-2"><strong>Media:</strong>
                        <p><?= htmlspecialchars($report['plan_media']) ?></p>
                    </div>
                    <div class="mb-2"><strong>Metode:</strong>
                        <p><?= htmlspecialchars($report['plan_method']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
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
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            if (evt) evt.currentTarget.classList.add("active");
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

        // Tab switching function (safe for conditional rendering)
        function openTab(evt, tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(btn => {
                btn.classList.remove('active');
            });

            // Show the selected tab (only if it exists)
            const selectedTab = document.getElementById(tabName);
            if (selectedTab) {
                selectedTab.style.display = 'block';
                selectedTab.classList.add('active');
            }

            // Add active class to clicked button
            if (evt && evt.currentTarget) {
                evt.currentTarget.classList.add('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Only call openTab if we have multiple tabs (not conditional single tab view)
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            // If there's only one tab button, it means we're in single-tab mode (from riwayat)
            // Just make sure the active tab is visible
            if (tabButtons.length === 1 && tabContents.length === 1) {
                tabContents[0].style.display = 'block';
            } else if (tabButtons.length > 1) {
                // Multiple tabs - activate the first one or the active one
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab) {
                    activeTab.style.display = 'block';
                } else {
                    openTab(null, 'tab2');
                }
            }
        });
    </script>
</body>

</html>
