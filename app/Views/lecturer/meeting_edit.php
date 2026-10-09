<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    .pdf-search-input { border-radius: 6px 0 0 6px; }
    .pdf-search-btn { border-radius: 0 6px 6px 0; }
    .pdf-table-row { cursor: pointer; transition: background 0.15s; }
    .pdf-table-row:hover { background: #e8f4fd; }
    .pdf-table-row.selected { background: #d1e7dd; }
    .selected-file-info {
        background: #f0f7ff; border: 1px solid #b6d4fe;
        border-radius: 6px; padding: 10px 14px; margin-top: 8px;
    }
</style>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">✏️ Edit Pertemuan</h5>
                <form action="<?= base_url('/lecturer/meetings/edit/' . $meeting['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Matakuliah</label>
                        <input type="text" class="form-control" value="<?= esc($meeting['kode_mk']) ?> - <?= esc($meeting['nama_mk']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pertemuan ke-</label>
                        <input type="text" class="form-control" value="<?= esc($meeting['pertemuan_ke']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul Pertemuan</label>
                        <input type="text" name="judul" class="form-control" value="<?= esc(old('judul', $meeting['judul'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="deskripsi" class="form-control" rows="3"><?= esc(old('deskripsi', $meeting['deskripsi'])) ?></textarea>
                    </div>
                    
                    <!-- PDF Selection (Modal Search) -->
                    <div class="mb-3">
                        <label class="form-label">Materi PDF <span class="text-muted">(opsional)</span></label>
                        <input type="hidden" name="pdf_file_id" id="pdf_file_id" value="<?= esc(old('pdf_file_id', $meeting['pdf_file_id'] ?? '')) ?>">
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

                    <!-- Link Referensi (maks 3) -->
                    <div class="mb-3">
                        <label class="form-label">Link Referensi <span class="text-muted">(opsional, maks <?= (int) ($maxLinks ?? 3) ?>)</span></label>
                        <div id="linksContainer"></div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addLinkBtn" onclick="addLinkRow()">
                            <i class="bi bi-plus-circle me-1"></i>Tambah Link
                        </button>
                        <small class="text-muted d-block mt-2">
                            Tipe <strong>YouTube</strong> akan di-embed langsung di halaman mahasiswa. Tipe <strong>File</strong> akan terbuka di tab baru.
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-outline-secondary">Kembali</a>
                        <button class="btn btn-primary" type="submit">💾 Simpan Perubahan</button>
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
                            <tr><td colspan="4" class="text-center text-muted py-4">Belum ada file PDF.</td></tr>
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
                showSelectedPdf(hid.value, row.dataset.title.replace(/^./, c => c.toUpperCase()), row.dataset.desc.replace(/^./, c => c.toUpperCase()));
            }
        }
    });

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
            const match = !query || row.dataset.title.includes(query) || row.dataset.desc.includes(query);
            row.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        document.getElementById('pdfCountInfo').textContent =
            query ? `Ditemukan ${visibleCount} file` : `Menampilkan ${rows.length} file`;
    }

    // ── Link Referensi: dynamic add/remove (maks 3) ──
    const MAX_LINKS = <?= (int) ($maxLinks ?? 3) ?>;
    let linkIdx = 0;
    function addLinkRow(type = 'youtube', url = '') {
        const container = document.getElementById('linksContainer');
        if (container.children.length >= MAX_LINKS) {
            updateLinkBtn();
            return;
        }
        const idx = linkIdx++;
        const row = document.createElement('div');
        row.className = 'input-group mb-2 link-row';
        row.dataset.idx = idx;
        row.innerHTML = `
            <select name="links[${idx}][link_type]" class="form-select" style="max-width:140px;">
                <option value="youtube" ${type === 'youtube' ? 'selected' : ''}>📺 YouTube</option>
                <option value="file" ${type === 'file' ? 'selected' : ''}>📄 File</option>
            </select>
            <input type="url" name="links[${idx}][url]" class="form-control" placeholder="https://..." value="${escapeAttr(url)}">
            <button type="button" class="btn btn-outline-danger" onclick="removeLinkRow(this)" title="Hapus baris ini">
                <i class="bi bi-x-lg"></i>
            </button>
        `;
        container.appendChild(row);
        updateLinkBtn();
    }
    function removeLinkRow(btn) {
        btn.closest('.link-row').remove();
        updateLinkBtn();
    }
    function updateLinkBtn() {
        const container = document.getElementById('linksContainer');
        const btn = document.getElementById('addLinkBtn');
        btn.disabled = container.children.length >= MAX_LINKS;
        btn.innerHTML = container.children.length >= MAX_LINKS
            ? '<i class="bi bi-x-circle me-1"></i>Maks ' + MAX_LINKS + ' link tercapai'
            : '<i class="bi bi-plus-circle me-1"></i>Tambah Link';
    }
    function escapeAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    // Pre-populate existing links (mode edit)
    <?php if (! empty($existingLinks)): ?>
        <?php foreach ($existingLinks as $lk): ?>
            addLinkRow(<?= json_encode($lk['link_type']) ?>, <?= json_encode($lk['url']) ?>);
        <?php endforeach; ?>
    <?php else: ?>
        addLinkRow();
    <?php endif; ?>
</script>
<?= $this->endSection() ?>
