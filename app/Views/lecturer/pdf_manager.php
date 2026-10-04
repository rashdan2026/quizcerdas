<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">📁 Kelola File PDF</h4>
        <small class="text-danger">Total Ukuran File : <?= number_format($totalSize / 1024 / 1024, 2) ?> MB</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-outline-secondary btn-sm">Pertemuan</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            ➕ Upload PDF
        </button>
    </div>
</div>

<?php if (empty($files)): ?>
    <div class="alert alert-info text-center py-4">
        Belum ada file PDF yang diupload.<br>
        <small>Klik tombol "Upload PDF" untuk menambahkan.</small>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Matakuliah</th>
                    <th>Judul</th>
                    <th>Deskripsi</th>
                    <th>Ukuran</th>
                    <th>Tanggal Upload</th>
                    <th class="text-end" style="width:100px;">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($files as $f): ?>
                    <tr>
                        <td>
                            <span class="text-muted"><?= esc($f['kode_mk'] ?? '-') ?></span>
                            <br><strong><?= esc($f['nama_mk'] ?? '-') ?></strong>
                        </td>
                        <td><?= esc($f['judul']) ?></td>
                        <td><small class="text-muted"><?= esc($f['deskripsi'] ?? '-') ?></small></td>
                        <td><small><?= esc(number_format($f['file_size'] / 1024, 1)) ?> KB</small></td>
                        <td><small><?= esc(date('d M Y', strtotime($f['created_at']))) ?></small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    onclick="confirmDelete(<?= $f['id'] ?>, '<?= esc($f['judul'], 'js') ?>')"
                                    title="Hapus">🗑️</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Upload PDF -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= base_url('/lecturer/pdf-manager/upload') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Upload File PDF</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Matakuliah <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">— Pilih Matakuliah —</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= esc($s['kode_mk']) ?> - <?= esc($s['nama_mk']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Judul File <span class="text-danger">*</span></label>
                        <input type="text" name="judul" class="form-control" maxlength="30" placeholder="Contoh: Pengenalan Database" required>
                        <small class="text-muted">Maksimal 30 karakter.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi <span class="text-muted">(opsional)</span></label>
                        <textarea name="deskripsi" class="form-control" rows="2" maxlength="200" placeholder="Deskripsi singkat tentang materi"></textarea>
                        <small class="text-muted">Maksimal 200 karakter.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File PDF <span class="text-danger">*</span></label>
                        <input type="file" name="pdf_file" class="form-control" accept="application/pdf" required>
                        <small class="text-muted">Format: PDF. Maksimal 10 MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">💾 Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">⚠️ Konfirmasi Hapus</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="deleteMsg" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
    function confirmDelete(id, judul) {
        document.getElementById('deleteMsg').textContent = 'Hapus file "' + judul + '"?';
        document.getElementById('deleteForm').action = "<?= base_url('/lecturer/pdf-manager/delete/') ?>" + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
<?= $this->endSection() ?>
