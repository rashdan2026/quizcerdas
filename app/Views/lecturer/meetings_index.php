<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-collection me-2"></i>Data Pertemuan</h4>
        <p class="text-muted small mb-0 mt-1">Kelola pertemuan dan token QR absensi</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/lecturer/subjects') ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-book"></i> Matakuliah
        </a>
        <a href="<?= base_url('/lecturer/meetings/create') ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i> Tambah Pertemuan
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Matakuliah</th>
                <th>Pertemuan</th>
                <th>Judul</th>
                <th class="text-center">Jumlah Absen</th>
                <th>Expired Token</th>
                <th class="text-center" style="width:200px;">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <tr>
                    <td>
                        <span class="fw-500"><?= esc($meeting['nama_mk']) ?></span>
                    </td>
                    <td>
                        <span class="badge rounded-pill" style="background:#EEF2FF;color:#4F46E5;">#<?= esc($meeting['pertemuan_ke']) ?></span>
                    </td>
                    <td><?= esc($meeting['judul']) ?></td>
                    <td class="text-center">
                        <?php if ((int)$meeting['jumlah_absen'] > 0): ?>
                            <span class="badge bg-success rounded-pill px-3"><?= (int)$meeting['jumlah_absen'] ?> <span class="fw-400">mahasiswa</span></span>
                        <?php else: ?>
                            <span class="badge rounded-pill px-3" style="background:#F1F5F9;color:#94A3B8;">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= esc($meeting['expired_at']) ?></small>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a href="<?= base_url('/lecturer/meetings/' . $meeting['id']) ?>" class="btn btn-outline-primary" title="QR Code">
                                <i class="bi bi-qr-code"></i> QR
                            </a>
                            <a href="<?= base_url('/lecturer/meetings/edit/' . $meeting['id']) ?>" class="btn btn-outline-warning" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-outline-danger" title="Hapus"
                                    onclick="confirmDelete(<?= $meeting['id'] ?>, '<?= esc($meeting['judul'], 'js') ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($meetings)): ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-inbox display-6 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-2">Belum ada pertemuan.</p>
                        <a href="<?= base_url('/lecturer/meetings/create') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Buat Pertemuan
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header border-0 pb-0">
                <div class="w-100 text-center pt-2">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
                         style="width:52px;height:52px;background:#FFF1F2;">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                    </div>
                    <h6 class="modal-title fw-600">Konfirmasi Hapus</h6>
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center px-4">
                <p id="deleteMsg" class="text-muted mb-0 small"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
                <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger btn-sm px-4">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    function confirmDelete(id, judul) {
        document.getElementById('deleteMsg').textContent = 'Hapus pertemuan "' + judul + '"?';
        document.getElementById('deleteForm').action = "<?= base_url('/lecturer/meetings/delete/') ?>" + id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>
<?= $this->endSection() ?>
