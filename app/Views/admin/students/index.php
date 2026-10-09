<?php
/** @var array $items */
/** @var string $keyword */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form method="get" class="mb-3">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Cari nama / email / NPM..." value="<?= esc($keyword) ?>">
        <button class="btn btn-admin" type="submit">Cari</button>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>Kolom <strong>Login</strong> menunjukkan jumlah login mahasiswa sejak OTP terakhir. Threshold ada di <a href="<?= base_url('/admin/settings') ?>">Pengaturan</a>.
    </div>
    <form action="<?= base_url('/admin/students/reset-all-otp-counters') ?>" method="post" onsubmit="return confirm('Reset SEMUA counter OTP mahasiswa ke 0? Semua mahasiswa akan kembali ke siklus login awal (tanpa OTP sampai mencapai threshold lagi).');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Counter OTP</button>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-mortarboard me-2"></i>Daftar Mahasiswa</span>
        <small class="text-muted">
            <?php
                $current = ($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1;
                $end     = $current + count($items) - 1;
            ?>
            Menampilkan <?= number_format($current) ?> – <?= number_format($end) ?> dari <?= number_format($pager->getTotal()) ?> mahasiswa
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>NPM</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Kelas</th>
                        <th>WA</th>
                        <th>Login</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                <?php else: $no = $pager->getCurrentPage() * $pager->getPerPage() - $pager->getPerPage() + 1; foreach ($items as $r): ?>
                    <tr>
                        <td><small class="text-muted"><?= $no++ ?></small></td>
                        <td><code><?= esc($r['npm']) ?></code></td>
                        <td class="fw-600"><?= esc($r['nama']) ?></td>
                        <td><small><?= esc($r['email']) ?></small></td>
                        <td><span class="badge bg-secondary-soft"><?= esc($r['kelas']) ?></span></td>
                        <td><small><?= esc($r['no_whatsapp']) ?></small></td>
                        <td><small class="text-muted"><?= (int) ($r['login_count'] ?? 0) ?>x</small></td>
                        <td class="text-end">
                            <a href="<?= base_url('/admin/students/' . rawurlencode($r['email']) . '/edit') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <form action="<?= base_url('/admin/students/' . rawurlencode($r['email']) . '/reset-password') ?>" method="post" class="d-inline" onsubmit="return confirm('Reset password mahasiswa ini?');">
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
    <?php if ($pager->getPageCount() > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Halaman <?= $pager->getCurrentPage() ?> dari <?= $pager->getPageCount() ?>
                (<?= $pager->getPerPage() ?> baris / halaman)
            </small>
            <?= $pager->links() ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
