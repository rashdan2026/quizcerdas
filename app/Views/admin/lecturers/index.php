<?php
/** @var array $items */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3">
    <div></div>
    <a href="<?= base_url('/admin/lecturers/create') ?>" class="btn btn-admin"><i class="bi bi-plus-circle me-1"></i> Tambah Dosen</a>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-person-workspace me-2"></i>Daftar Dosen</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada dosen.</td></tr>
                <?php else: foreach ($items as $i => $r): ?>
                    <tr>
                        <td><small class="text-muted"><?= $i + 1 ?></small></td>
                        <td class="fw-600"><?= esc($r['nama']) ?></td>
                        <td><small><?= esc($r['email']) ?></small></td>
                        <td>
                            <span class="badge bg-<?= $r['is_active'] ? 'success' : 'secondary' ?>-soft">
                                <i class="bi bi-<?= $r['is_active'] ? 'check-circle' : 'pause-circle' ?> me-1"></i><?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= $r['last_login'] ? esc(date('d M Y H:i', strtotime($r['last_login']))) : '-' ?></small></td>
                        <td class="text-end">
                            <form action="<?= base_url('/admin/lecturers/' . $r['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light" title="Aktif/Nonaktifkan"><i class="bi bi-<?= $r['is_active'] ? 'pause' : 'play' ?>-fill"></i></button>
                            </form>
                            <a href="<?= base_url('/admin/lecturers/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <form action="<?= base_url('/admin/lecturers/' . $r['id'] . '/reset-password') ?>" method="post" class="d-inline" onsubmit="return confirm('Reset password dosen ini? Password baru akan ditampilkan.');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light" title="Reset Password"><i class="bi bi-key"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
