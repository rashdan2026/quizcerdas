<?php
/** @var array $rows */
/** @var int $totalStudents */
/** @var int $totalViewsAll */
/** @var string $today */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Hanya mahasiswa yang <strong>view_count &gt; 0 hari ini (<?= $today ?>)</strong> yang ditampilkan.
        <span class="ms-2">Total: <strong><?= $totalStudents ?></strong> mahasiswa • <strong><?= $totalViewsAll ?></strong> impresi</span>
    </div>
    <a href="<?= base_url('/admin/ads/reset-impressions') ?>"
       class="btn btn-sm btn-outline-warning"
       onclick="return confirm('Reset SEMUA impresi untuk SEMUA user hari ini? Tindakan ini tidak dapat dibatalkan.');">
        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Semua Hari Ini
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart-line me-2"></i>Impresi Hari Ini per Mahasiswa</span>
        <small class="text-muted"><?= date('d M Y', strtotime($today)) ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Mahasiswa</th>
                        <th>Kelas</th>
                        <th class="text-center" style="width:80px">Views</th>
                        <th class="text-center" style="width:80px">Ads</th>
                        <th>Iklan yang Dilihat</th>
                        <th class="text-end" style="width:160px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            Tidak ada mahasiswa yang melihat iklan hari ini (<?= $today ?>).
                            <br>
                            <small>Mahasiswa akan muncul di sini setelah mereka melihat iklan di dashboard atau detail pertemuan.</small>
                        </td>
                    </tr>
                <?php else:
                    $no = 1;
                    foreach ($rows as $r):
                        $email = (string) $r['email'];
                        $nama  = $r['nama']  ?? '(mahasiswa tidak ditemukan)';
                        $npm   = $r['npm']   ?? '-';
                        $kelas = $r['kelas'] ?? '-';
                        $totalViews = (int) $r['total_views'];
                        $adCount    = (int) $r['ad_count'];
                        $adsSeen    = (string) ($r['ads_seen'] ?? '');
                ?>
                    <tr>
                        <td><small class="text-muted"><?= $no++ ?></small></td>
                        <td>
                            <div class="fw-600"><?= esc($nama) ?></div>
                            <small class="text-muted"><code><?= esc($email) ?></code></small>
                            <br><small class="text-muted">NPM: <?= esc($npm) ?></small>
                        </td>
                        <td><span class="badge bg-secondary-soft"><?= esc($kelas) ?></span></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $totalViews >= 3 ? 'danger' : 'primary' ?>-soft fs-6">
                                <?= $totalViews ?>x
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-soft"><?= $adCount ?></span>
                        </td>
                        <td>
                            <small class="text-muted" title="<?= esc($adsSeen) ?>">
                                <?= esc(strlen($adsSeen) > 80 ? substr($adsSeen, 0, 80) . '…' : $adsSeen) ?>
                            </small>
                        </td>
                        <td class="text-end">
                            <form action="<?= base_url('/admin/impressions/reset') ?>" method="post" class="d-inline"
                                  onsubmit="return confirm('Reset view_count mahasiswa <?= esc($email) ?> ke 0 untuk hari ini? Kuota harian akan fresh kembali.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="email" value="<?= esc($email) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        title="Set view_count = 0 untuk impresi hari ini">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info mt-3 py-2 px-3" style="font-size:.82rem;">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Catatan:</strong>
    <ul class="mb-0 mt-1">
        <li>Reset hanya mengubah <code>view_count = 0</code> untuk impresi <strong>hari ini</strong>. Histori <code>view_date</code> lampau tidak terganggu.</li>
        <li>Besok (saat <code>view_date = tanggal baru</code>), row impresi baru akan dibuat (<code>view_count = 1</code>) saat mahasiswa pertama kali melihat iklan.</li>
        <li>Kuota harian mengikuti setting <code>app_settings.daily_ad_max_display</code> (saat ini = <?= (new \App\Models\AppSettingModel())->getValue('daily_ad_max_display', 3) ?>) — global untuk semua placement (login/dashboard/pdf).</li>
    </ul>
</div>

<?= $this->endSection() ?>
