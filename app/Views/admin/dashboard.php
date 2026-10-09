<?php
/** @var array $stats */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon blue"><i class="bi bi-mortarboard"></i></div>
            <div>
                <div class="label">Mahasiswa</div>
                <div class="value"><?= number_format($stats['students'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon green"><i class="bi bi-person-workspace"></i></div>
            <div>
                <div class="label">Dosen</div>
                <div class="value"><?= number_format($stats['lecturers'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon red"><i class="bi bi-badge-ad"></i></div>
            <div>
                <div class="label">Iklan Aktif</div>
                <div class="value"><?= number_format($stats['ads_active'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="icon amber"><i class="bi bi-collection"></i></div>
            <div>
                <div class="label">Pertemuan</div>
                <div class="value"><?= number_format($stats['meetings'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-activity me-2"></i>Aktivitas Terbaru</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentLogins)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4">Belum ada aktivitas login tercatat.</td></tr>
                        <?php else: foreach ($recentLogins as $r): ?>
                            <tr>
                                <td><small class="text-muted"><?= esc($r['when']) ?></small></td>
                                <td><?= esc($r['email']) ?></td>
                                <td><span class="badge bg-<?= $r['role'] === 'admin' ? 'danger' : 'secondary' ?>-soft"><?= esc(ucfirst($r['role'])) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-link-45deg me-2"></i>Akses Cepat</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= base_url('/admin/ads/create') ?>" class="btn btn-admin"><i class="bi bi-plus-circle me-1"></i> Tambah Iklan Baru</a>
                    <a href="<?= base_url('/admin/lecturers/create') ?>" class="btn btn-outline-light"><i class="bi bi-person-plus me-1"></i> Tambah Dosen</a>
                    <a href="<?= base_url('/admin/settings') ?>" class="btn btn-outline-light"><i class="bi bi-sliders me-1"></i> Pengaturan Aplikasi</a>
                </div>
                <hr style="border-color:#1F2937;">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i>Login sebagai <strong><?= esc(session('user_name')) ?></strong> pada <?= esc(date('d M Y H:i')) ?>.
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
