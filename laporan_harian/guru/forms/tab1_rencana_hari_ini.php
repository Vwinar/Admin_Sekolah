<!-- FORM TAB 1: RENCANA HARI INI -->
<form method="POST" enctype="multipart/form-data" id="tab1"
    class="tab-content <?= $activeTab == 'tab1' ? 'active' : '' ?>">
    <input type="hidden" name="save_section" value="tab1">

    <h3 class="mb-2">Form Input Rencana</h3>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
            <label class="form-label">Tanggal</label>
            <input type="date" name="plan_date" class="form-control"
                value="<?= htmlspecialchars($reportData['plan_date'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Jam Ke / Waktu</label>
            <select name="plan_time" class="form-control" required>
                <option value="">- Pilih Jam -</option>
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <option value="Jam ke-<?= $i ?>" <?= ($reportData['plan_time'] ?? '') === "Jam ke-$i" ? 'selected' : '' ?>>
                        Jam ke-<?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
            <label class="form-label">Mata Pelajaran</label>
            <select name="plan_subject" class="form-control" required>
                <option value="">- Pilih Mapel -</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($reportData['plan_subject'] ?? '') === $s['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Kelas</label>
            <select name="plan_class" class="form-control" required>
                <option value="">- Pilih Kelas -</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= htmlspecialchars($c['name']) ?>" <?= ($reportData['plan_class'] ?? '') === $c['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Topik Pembelajaran</label>
        <input type="text" name="plan_topic" class="form-control"
            value="<?= htmlspecialchars($reportData['plan_topic'] ?? '') ?>"
            placeholder="Contoh: Sistem Pernapasan Manusia" required>
    </div>

    <div class="form-group">
        <label class="form-label">Tujuan Pembelajaran</label>
        <textarea name="plan_learning_objective" class="form-control" rows="3" required
            placeholder="Contoh: Siswa mampu memahami dan menjelaskan proses pernapasan..."><?= htmlspecialchars($reportData['plan_learning_objective'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">Metode/Model Pembelajaran</label>
        <select name="plan_method" class="form-control" required>
            <option value="">- Pilih Metode -</option>
            <?php
            $methods = ['PJBL', 'Diskusi', 'Ceramah', 'Eksperimen', 'Demonstrasi', 'Role Playing', 'Discovery Learning', 'Problem Based Learning'];
            foreach ($methods as $method):
                ?>
                <option value="<?= $method ?>" <?= ($reportData['plan_method_type'] ?? '') === $method ? 'selected' : '' ?>>
                    <?= $method ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Custom Style untuk Checkbox Grid -->
    <style>
        .custom-checkbox-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            /* Default 2 kolom (Mobile) */
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        @media (min-width: 768px) {
            .custom-checkbox-grid {
                grid-template-columns: repeat(4, 1fr);
                /* 4 Kolom di Desktop */
            }
        }

        .checkbox-item-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-item-label:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .checkbox-item-label input[type="checkbox"] {
            accent-color: var(--primary);
            width: 1rem;
            height: 1rem;
        }
    </style>

    <div class="form-group">
        <label class="form-label">Media/Alat yang Digunakan</label>
        <div class="custom-checkbox-grid">
            <?php
            $media_options = ['Proyektor', 'Laptop', 'Alat Praktikum', 'Whiteboard', 'Video/Film', 'Modul/Buku', 'Internet'];
            $selected_media = explode(',', $reportData['plan_media_used'] ?? '');
            foreach ($media_options as $media):
                ?>
                <label class="checkbox-item-label">
                    <input type="checkbox" name="plan_media[]" value="<?= $media ?>" <?= in_array($media, $selected_media) ? 'checked' : '' ?>>
                    <span><?= $media ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Penilaian yang Digunakan</label>
        <div class="custom-checkbox-grid">
            <?php
            $assessment_options = ['Kuis', 'Observasi', 'Tugas', 'Presentasi', 'Praktik', 'Portofolio'];
            $selected_assessment = explode(',', $reportData['plan_assessment_used'] ?? '');
            foreach ($assessment_options as $assessment):
                ?>
                <label class="checkbox-item-label">
                    <input type="checkbox" name="plan_assessment[]" value="<?= $assessment ?>" <?= in_array($assessment, $selected_assessment) ? 'checked' : '' ?>>
                    <span><?= $assessment ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MOVED FROM TAB 2: Upload Modul Ajar -->
    <div class="form-group"
        style="background: #f0f9ff; padding: 1rem; border-radius: 0.5rem; border: 1px dashed #bae6fd;">
        <label class="form-label">Upload Modul Ajar (PDF) - <small>Opsional</small></label>

        <input type="hidden" name="delete_module_file" id="delete_module_file" value="0">

        <?php if (!empty($reportData['module_file'])): ?>
            <div id="existing_file_preview"
                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem; background: white; padding: 5px 10px; border-radius: 4px; border: 1px solid #e0e7ff; width: fit-content;">
                <span style="font-size: 0.8rem; color: #0284c7;">📄 File saat ini: <a href="javascript:void(0)"
                        onclick="previewExistingPdf('../uploads/<?= htmlspecialchars($reportData['module_file']) ?>')"
                        style="font-weight: 500; text-decoration: underline;">Lihat PDF</a></span>
                <button type="button" class="btn btn-sm btn-danger" onclick="markFileForDeletion()"
                    style="padding: 2px 8px; font-size: 0.7rem; border-radius: 4px; background: #ef4444; color: white; border: none; cursor: pointer;"
                    title="Hapus File">
                    ✕ Hapus
                </button>
            </div>
            <div id="delete_file_msg" style="display: none; color: #ef4444; font-size: 0.8rem; margin-bottom: 0.5rem;">
                ⚠️ File akan dihapus saat disimpan.
                <button type="button" onclick="undoDeleteFile()"
                    style="background:none; border:none; text-decoration:underline; color:#0284c7; cursor:pointer; font-size:0.8rem; margin-left:5px;">Batal</button>
            </div>
        <?php endif; ?>

        <input type="file" name="module_file" id="module_input" class="form-control" accept=".pdf"
            onchange="previewPdfUpload(this)">
        <div id="pdf_preview_container"
            style="display:none; margin-top:10px; border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden;">
            <div
                style="background: #f1f5f9; padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 600; color: #64748b; border-bottom: 1px solid #e2e8f0;">
                Preview PDF Baru
                <button type="button" onclick="clearFileUpload()"
                    style="float: right; color: #ef4444; background: none; border: none; cursor: pointer; font-weight: bold;">✕
                    Batal Upload</button>
            </div>
            <div id="pdf_renderer_container"
                style="width:100%; height:400px; overflow-y:auto; display:flex; justify-content:center; background:#525659; align-items:center; padding: 1rem;">
                <div id="pdf_loading_text" style="color:white; display:none;">Memproses file...</div>
                <canvas id="pdf_preview_canvas"
                    style="box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); max-width: 100%;"></canvas>
            </div>
        </div>
    </div>

    <script>
        function markFileForDeletion() {
            document.getElementById('delete_module_file').value = '1';
            const preview = document.getElementById('existing_file_preview');
            if (preview) preview.style.display = 'none';
            const msg = document.getElementById('delete_file_msg');
            if (msg) msg.style.display = 'block';
        }
        function undoDeleteFile() {
            document.getElementById('delete_module_file').value = '0';
            const preview = document.getElementById('existing_file_preview');
            if (preview) preview.style.display = 'flex';
            const msg = document.getElementById('delete_file_msg');
            if (msg) msg.style.display = 'none';
        }
        function clearFileUpload() {
            const input = document.getElementById('module_input');
            input.value = '';
            document.getElementById('pdf_preview_container').style.display = 'none';
        }
    </script>

    <div class="form-group">
        <label class="form-label">Catatan Khusus (Opsional)</label>
        <textarea name="plan_notes" class="form-control" rows="2"
            placeholder="Misal: Ada siswa inklusi yang perlu pendampingan khusus..."><?= htmlspecialchars($reportData['plan_notes'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; flex-wrap: wrap;">
        <button type="submit" name="stay_on_tab" value="1" class="btn chip-btn chip-btn-blue" style="width: auto;">
            <span class="btn-icon">💾</span>
            <span class="btn-text">Simpan Rencana</span>
        </button>
        <button type="submit" class="btn chip-btn chip-btn-orange" style="width: auto;"
            onclick="this.form.stay_on_tab.value='0'">
            <span class="btn-icon">➡️</span>
            <span class="btn-text">Simpan & Lanjut ke Laporan Harian</span>
        </button>
    </div>
</form>