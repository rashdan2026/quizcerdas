<?php
/** @var array $rows */
/** @var string $period */
/** @var array $periods */
/** @var string $label */
/** @var int $days */
/** @var string $startDate */
/** @var string $endDate */
/** @var int $totalClicks */
/** @var int $totalImpressions */
/** @var float $totalCtr */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="text-muted small">
        <i class="bi bi-calendar-range me-1"></i>
        Periode: <strong><?= esc($startDate) ?></strong> s/d <strong><?= esc($endDate) ?></strong> (<?= (int) $days ?> hari)
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/admin/ads') ?>" class="btn btn-sm btn-outline-light">
            <i class="bi bi-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <ul class="nav nav-pills">
            <?php foreach ($periods as $key => $p): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $period === $key ? 'active' : '' ?>"
                       href="<?= base_url('/admin/ads/click-report?period=' . $key) ?>">
                        <?= esc($p['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card h-100" style="background:rgba(59,130,246,0.10);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Klik</div>
                        <div class="fs-2 fw-700" style="color:#93C5FD;"><?= number_format($totalClicks) ?></div>
                    </div>
                    <div class="stat-icon" style="background:rgba(59,130,246,0.25);color:#93C5FD;">
                        <i class="bi bi-cursor-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100" style="background:rgba(156,163,175,0.10);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">Total Impressions</div>
                        <div class="fs-2 fw-700" style="color:#D1D5DB;"><?= number_format($totalImpressions) ?></div>
                    </div>
                    <div class="stat-icon" style="background:rgba(156,163,175,0.25);color:#D1D5DB;">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card h-100" style="background:rgba(16,185,129,0.10);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small mb-1">CTR (Click-Through Rate)</div>
                        <div class="fs-2 fw-700" style="color:#6EE7B7;"><?= number_format($totalCtr, 2) ?>%</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(16,185,129,0.25);color:#6EE7B7;">
                        <i class="bi bi-percent"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-bar-chart-line me-2"></i>Klik per Iklan — <?= esc($label) ?></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Preview</th>
                        <th>Judul / Target URL</th>
                        <th class="text-end">Klik</th>
                        <th class="text-end">Impressions</th>
                        <th class="text-end">CTR</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada iklan.</td></tr>
                <?php else: $i = 1; foreach ($rows as $r): ?>
                    <tr>
                        <td><small class="text-muted"><?= $i++ ?></small></td>
                        <td>
                            <?php if (! empty($r['ad']['file_name'])): ?>
                                <img src="<?= base_url('/media/gfx/' . $r['ad']['file_name']) ?>" alt="" style="width:80px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #1F2937;">
                            <?php else: ?>
                                <span class="text-muted small">no file</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-600"><?= esc($r['ad']['title']) ?></div>
                            <a href="<?= esc($r['ad']['target_url']) ?>" target="_blank" rel="noopener" class="small text-muted text-truncate d-inline-block" style="max-width:340px;"><?= esc($r['ad']['target_url']) ?></a>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-primary-soft fs-6"><?= number_format($r['clicks']) ?></span>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-secondary-soft"><?= number_format($r['impressions']) ?></span>
                        </td>
                        <td class="text-end fw-600"><?= number_format($r['ctr'], 2) ?>%</td>
                        <td>
                            <span class="badge bg-<?= $r['ad']['is_active'] ? 'success' : 'secondary' ?>-soft">
                                <i class="bi bi-<?= $r['ad']['is_active'] ? 'check-circle' : 'pause-circle' ?> me-1"></i><?= $r['ad']['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
