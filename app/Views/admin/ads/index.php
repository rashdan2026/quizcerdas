<?php
/** @var array $items */
/** @var array $pager */
/** @var int $totalImpressions */
/** @var string $sort */
/** @var array $sortOptions */
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

<!-- Sort bar -->
<div class="card mb-3">
    <div class="card-body py-2 px-3 d-flex align-items-center flex-wrap gap-2">
        <span class="text-muted small me-2"><i class="bi bi-sort-down me-1"></i>Urutkan:</span>
        <?php foreach ($sortOptions as $key => $cfg): ?>
            <?php
            $isActive = $key === $sort;
            $href = base_url('admin/ads') . '?sort=' . $key;
            $cls = $isActive ? 'btn-admin' : 'btn-outline-light';
            ?>
            <a href="<?= $href ?>" class="btn btn-sm <?= $cls ?>">
                <?= esc($cfg['label']) ?>
                <?php if ($isActive): ?>
                    <i class="bi bi-check2 ms-1"></i>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-badge-ad me-2"></i>Daftar Iklan</span>
        <small class="text-muted">
            <?php
                $current = ($pager['currentPage'] - 1) * $pager['perPage'] + 1;
                $end     = $current + count($items) - 1;
                if ($pager['total'] === 0) {
                    echo 'Tidak ada iklan.';
                } else {
                    echo 'Menampilkan ' . number_format($current) . ' – ' . number_format($end) . ' dari ' . number_format($pager['total']) . ' iklan';
                }
            ?>
        </small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Preview</th>
                        <th>Judul / Target</th>
                        <th>Status</th>
                        <th>Target</th>
                        <th class="text-end">Score</th>
                        <th>Views Hari Ini</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada iklan. Klik "Tambah Iklan" untuk membuat.</td></tr>
                <?php else:
                    $no = ($pager['currentPage'] - 1) * $pager['perPage'] + 1;
                    foreach ($items as $ad):
                        $tg = (string) ($ad['target_gender'] ?? 'Both');
                        $tgBadge = $tg === 'Laki-Laki' ? 'info' : ($tg === 'Perempuan' ? 'warning' : 'secondary');
                        $viewsToday = (int) ($ad['views_today'] ?? 0);
                ?>
                    <tr>
                        <td><small class="text-muted"><?= $no++ ?></small></td>
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
                        <td>
                            <span class="badge bg-<?= $tgBadge ?>-soft" title="Target gender">
                                <i class="bi bi-gender-ambiguous me-1"></i><?= esc($tg) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-dark-soft" title="Priority score (berkurang 1 setiap tampil)">
                                <?= (int) ($ad['priority_score'] ?? 0) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-primary-soft"><?= $viewsToday ?>x</span>
                        </td>
                        <td class="text-end">
                            <form action="<?= base_url('/admin/ads/' . (int) $ad['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-light" type="submit" title="Aktif/Nonaktifkan">
                                    <i class="bi bi-<?= $ad['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                </button>
                            </form>
                            <a href="<?= base_url('/admin/ads/' . (int) $ad['id'] . '/edit') ?>" class="btn btn-sm btn-outline-light"><i class="bi bi-pencil"></i></a>
                            <form action="<?= base_url('/admin/ads/' . (int) $ad['id'] . '/delete') ?>" method="post" class="d-inline" onsubmit="return confirm('Hapus iklan ini? File GIF juga akan dihapus.');">
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
    <?php if ($pager['pageCount'] > 1): ?>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Halaman <?= $pager['currentPage'] ?> dari <?= $pager['pageCount'] ?>
                (<?= $pager['perPage'] ?> baris / halaman)
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $baseUrl  = $pager['baseUrl'];
                    $curPage  = $pager['currentPage'];
                    $pageCount = $pager['pageCount'];

                    // Previous
                    if ($curPage > 1):
                        $prevUrl = $baseUrl . '&page=' . ($curPage - 1);
                    ?>
                        <li class="page-item"><a class="page-link" href="<?= $prevUrl ?>"><i class="bi bi-chevron-left"></i></a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
                    <?php endif; ?>

                    <?php
                    // Numbered pages (show window ±2 around current)
                    $start = max(1, $curPage - 2);
                    $end   = min($pageCount, $curPage + 2);
                    if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=1' ?>">1</a></li>
                        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif;
                    for ($p = $start; $p <= $end; $p++):
                        if ($p === $curPage): ?>
                            <li class="page-item active"><span class="page-link"><?= $p ?></span></li>
                        <?php else: ?>
                            <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=' . $p ?>"><?= $p ?></a></li>
                        <?php endif;
                    endfor;
                    if ($end < $pageCount): ?>
                        <?php if ($end < $pageCount - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="<?= $baseUrl . '&page=' . $pageCount ?>"><?= $pageCount ?></a></li>
                    <?php endif; ?>

                    <?php if ($curPage < $pageCount):
                        $nextUrl = $baseUrl . '&page=' . ($curPage + 1); ?>
                        <li class="page-item"><a class="page-link" href="<?= $nextUrl ?>"><i class="bi bi-chevron-right"></i></a></li>
                    <?php else: ?>
                        <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
