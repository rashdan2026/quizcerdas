<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    .pdf-search-input {
        border-radius: 6px 0 0 6px;
    }
    .pdf-search-btn {
        border-radius: 0 6px 6px 0;
    }
    .pdf-table-row {
        cursor: pointer;
        transition: background 0.15s;
    }
    .pdf-table-row:hover {
        background: #e8f4fd;
    }
    .pdf-table-row.selected {
        background: #d1e7dd;
    }
    .selected-file-info {
        background: #f0f7ff;
        border: 1px solid #b6d4fe;
        border-radius: 6px;
        padding: 10px 14px;
        margin-top: 8px;
    }
</style>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">➕ Tambah Pertemuan</h5>
                <form action="<?= base_url('/lecturer/meetings/create') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Matakuliah Aktif</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">Pilih Matakuliah</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject['id'] ?>" <?= old('subject_id') == $subject['id'] ? 'selected' : '' ?>>
                                    <?= esc($subject['kode_mk']) ?> - <?= esc($subject['nama_mk']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul Pertemuan</label>
                        <input type="text" name="judul" class="form-control" value="<?= esc(old('judul')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= esc(old('deskripsi')) ?></textarea>
                    </div>
                    
                    <!-- PDF Selection (Modal Search) -->
                    <div class="mb-3">
                        <label class="form-label">Materi PDF <span class="text-muted">(opsional)</span></label>
                        <input type="hidden" name="pdf_file_id" id="pdf_file_id" value="<?= esc(old('pdf_file_id')) ?>">
                        <div class="input-group">
                            <input type="text" id="pdfSearchInput" class="form-control pdf-search-input" placeholder="Cari judul atau deskripsi PDF…" onkeyup="filterPdfTable()">
                            <button type="button" class="btn btn-outline-primary pdf-search-btn" data-bs-toggle="modal" data-bs-target="#pdfSelectModal">
                                📁 Pilih File
                            </button>
                        </div>
                        <div id="selectedPdfInfo" class="selected-file-info" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="selectedPdfTitle"></strong>
                                    <br><small class="text-muted" id="selectedPdfDesc"></small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearPdfSelection()">✕ Hapus</button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Atau kelola file di <a href="<?= base_url('/lecturer/pdf-manager') ?>">Kelola File PDF</a>.
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-outline-secondary">Kembali</a>
                        <button class="btn btn-primary" type="submit">Simpan Pertemuan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pilih PDF -->
<div class="modal fade" id="pdfSelectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📁 Pilih File PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="pdfTable">
                        <thead class="table-light sticky-top">
                        <tr>
                            <th style="width:40%;">Judul</th>
                            <th style="width:35%;">Deskripsi</th>
                            <th style="width:15%;">Ukuran</th>
                            <th style="width:10%;"></th>
                        </tr>
                        </thead>
                        <tbody id="pdfTableBody">
                            <?php foreach ($pdfFiles as $pf): ?>
                            <tr class="pdf-table-row" data-id="<?= $pf['id'] ?>" data-title="<?= esc(strtolower($pf['judul'])) ?>" data-desc="<?= esc(strtolower($pf['deskripsi'] ?? '')) ?>">
                                <td><strong><?= esc($pf['judul']) ?></strong></td>
                                <td><small class="text-muted"><?= esc($pf['deskripsi'] ?? '-') ?></small></td>
                                <td><small><?= esc(number_format($pf['file_size'] / 1024, 1)) ?> KB</small></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectPdfFile(<?= $pf['id'] ?>, '<?= esc($pf['judul'], 'js') ?>', '<?= esc($pf['deskripsi'] ?? '', 'js') ?>')">
                                        Pilih
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($pdfFiles)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada file PDF. Upload dulu di <a href="<?= base_url('/lecturer/pdf-manager') ?>">Kelola File PDF</a>.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted text-center mt-2 mb-0" id="pdfCountInfo">Menampilkan <?= count($pdfFiles) ?> file</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    // ── Restore previous selection on load ──
    document.addEventListener('DOMContentLoaded', () => {
        const hid = document.getElementById('pdf_file_id');
        if (hid && hid.value) {
            const row = document.querySelector(`.pdf-table-row[data-id="${hid.value}"]`);
            if (row) {
                const title = row.dataset.title;
                const desc = row.dataset.desc;
                showSelectedPdf(hid.value, title.charAt(0).toUpperCase() + title.slice(1), desc === '-' ? '' : desc.charAt(0).toUpperCase() + desc.slice(1));
            }
        }
    });

    // ── Select PDF from modal ──
    function selectPdfFile(id, title, desc) {
        showSelectedPdf(id, title, desc);
        bootstrap.Modal.getInstance(document.getElementById('pdfSelectModal')).hide();
    }

    function showSelectedPdf(id, title, desc) {
        document.getElementById('pdf_file_id').value = id;
        document.getElementById('selectedPdfTitle').textContent = title;
        document.getElementById('selectedPdfDesc').textContent = desc || 'Tidak ada deskripsi';
        document.getElementById('selectedPdfInfo').style.display = 'block';
        document.getElementById('pdfSearchInput').placeholder = '✅ ' + title;
    }

    function clearPdfSelection() {
        document.getElementById('pdf_file_id').value = '';
        document.getElementById('selectedPdfInfo').style.display = 'none';
        document.getElementById('pdfSearchInput').placeholder = 'Cari judul atau deskripsi PDF…';
    }

    // ── Real-time search filter ──
    function filterPdfTable() {
        const query = document.getElementById('pdfSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#pdfTableBody .pdf-table-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const title = row.dataset.title;
            const desc = row.dataset.desc;
            const match = !query || title.includes(query) || desc.includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });

        document.getElementById('pdfCountInfo').textContent = 
            query ? `Ditemukan ${visibleCount} file` : `Menampilkan ${rows.length} file`;
    }
</script>
<?= $this->endSection() ?>
