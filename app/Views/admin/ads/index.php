<?php
/** @var array $items */
/** @var int $totalImpressions */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>Total impression hari ini: <strong><?= $totalImpressions ?? 0 ?></strong>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/admin/ads/click-report') ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-bar-chart-line me-1"></i> Laporan Klik</a>
        <form action="<?= base_url('/admin/ads/reset-impressions') ?>" method="post" onsubmit="return confirm('Reset SEMUA impression? Semua user akan melihat iklan lagi mulai sekarang.');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i> Reset Impressions</button>
        </form>
        <a href="<?= base_url('/admin/ads/create') ?>" class="btn btn-admin"><i class="bi bi-plus-circle me-1"></i> Tambah Iklan</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-badge-ad me-2"></i>Daftar Iklan</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Preview</th>
                        <th>Judul / Target</th>
                        <th>Status</th>
                        <th>Views Hari Ini</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada iklan. Klik "Tambah Iklan" untuk membuat.</td></tr>
                <?php else: foreach ($items as $i => $ad): ?>
                    <tr>
                        <td><small class="text-muted"><?= $i + 1 ?></small></td>
                        <td>
                            <?php if (! empty($ad['file_name'])): ?>
                                <img src="<?= base_url('/media/gfx/' . $ad['file_name']) ?>" alt="" style="width:80px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #1F2937;">
                            <?php else: ?>
                                <span class="text-muted small">no file</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-600"><?= esc($ad['title']) ?></div>
                            <a href="<?= esc($ad['target_url']) ?>" target="_blank" rel="noopener" class="small text-muted text-truncate d-inline-block" style="max-width:240px;"><?= esc($ad['target_url']) ?></a>
                        </td>
                        <td>
                            <span class="badge bg-<?= $ad['is_active'] ? 'success' : 'secondary' ?>-soft">
                                <i class="bi bi-<?= $ad['is_active'] ? 'check-circle' : 'pause-circle' ?> me-1"></i><?= $ad['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td><span class="badge bg-primary-soft"><?= (int) ($ad['views_today'] ?? 0) ?>x</span></td>
                        <td class="text-end">
                            <form action="<?= base_url('/admin/ads/' . $ad['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light" type="submit" title="Aktif/Nonaktifkan">
                                    <i class="bi bi-<?= $ad['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                </button>
                            </form>
                            <a href="<?= base_url('/admin/ads/' . $ad['id'] . '/edit') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <form action="<?= base_url('/admin/ads/' . $ad['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus iklan ini? File GIF juga akan dihapus.');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light" type="submit" title="Hapus"><i class="bi bi-trash" style="color:#FCA5A5;"></i></button>
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
