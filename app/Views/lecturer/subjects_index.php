<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-book me-2"></i>Data Matakuliah</h4>
        <p class="text-muted small mb-0 mt-1">Kelola matakuliah yang Anda ampu</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-collection"></i> Pertemuan
        </a>
        <a href="<?= base_url('/lecturer/report') ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-bar-chart"></i> Rekap
        </a>
        <a href="<?= base_url('/lecturer/subjects/create') ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i> Tambah
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Kode MK</th>
                <th>Nama Matakuliah</th>
                <th class="text-center">Status</th>
                <th class="text-center">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><code class="fw-600" style="color:#4F46E5;"><?= esc($subject['kode_mk']) ?></code></td>
                    <td><?= esc($subject['nama_mk']) ?></td>
                    <td class="text-center">
                        <?php if ($subject['is_active']): ?>
                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                        <?php else: ?>
                            <span class="badge rounded-pill px-3" style="background:#F1F5F9;color:#64748B;"><i class="bi bi-pause-circle me-1"></i>Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <form action="<?= base_url('/lecturer/subjects/' . $subject['id'] . '/toggle') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm <?= $subject['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit">
                                <i class="bi <?= $subject['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i>
                                <?= $subject['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <i class="bi bi-book display-6 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-2">Belum ada matakuliah.</p>
                        <a href="<?= base_url('/lecturer/subjects/create') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Matakuliah
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
