<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-clipboard-data me-2"></i>Nilai Mahasiswa: <?= esc($quiz['title']) ?></h4>
        <p class="text-muted small mb-0 mt-1">Kode Kuis aktif: <code class="fw-bold" style="color:#4F46E5;"><?= esc($quiz['access_code']) ?></code> &middot; kolom kode pada tabel menunjukkan kode yang digunakan tiap percobaan</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('/lecturer/quiz/nilai') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Semua Kuis</a>
        <a href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/nilai/export') ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Daftar Nilai (<?= (int) $total ?> mahasiswa)</span>
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Cari NPM / nama..." value="<?= esc($search) ?>">
            <button class="btn btn-sm btn-outline-primary">Cari</button>
            <?php if ($search !== ''): ?><a href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/nilai') ?>" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr>
                <th>No</th><th>NPM</th><th>Nama</th><th class="text-center">Kelas</th><th class="text-center">Percobaan</th><th class="text-center">Status</th>
                <th class="text-center">Skor</th><th class="text-center">Persentase</th><th>Waktu</th><th>Kode Kuis</th>
            </tr></thead>
            <tbody>
            <?php $no = 1; foreach ($grades as $g): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><code><?= esc((string) ($g['npm'] ?? '-')) ?></code></td>
                    <td><?= esc($g['nama'] ?? $g['student_email']) ?></td>
                    <td class="text-center"><?= esc((string) ($g['kelas'] ?: '-')) ?></td>
                    <td class="text-center"><?= (int) ($g['attempt_no'] ?? 1) ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $g['status'] === 'submitted' ? 'success' : 'warning text-dark' ?>">
                            <?= $g['status'] === 'submitted' ? 'Selesai' : 'Tidak Selesai (Waktu Habis)' ?>
                        </span>
                    </td>
                    <td class="text-center fw-bold"><?= (int) $g['raw_score'] ?> / <?= (int) $g['max_score'] ?></td>
                    <td class="text-center"><?= $g['percentage'] !== null ? round((float) $g['percentage'], 2) . '%' : '-' ?></td>
                    <td class="small"><?= esc((string) $g['submitted_at']) ?></td>
                    <td><code class="fw-bold" style="color:#4F46E5;"><?= esc($g['access_code'] ?? $quiz['access_code']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($grades)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">Belum ada mahasiswa yang mengerjakan kuis ini<?= $search !== '' ? ' (sesuai pencarian)' : '' ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPage > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="small text-muted">Halaman <?= (int) $page ?> dari <?= (int) $totalPage ?></span>
        <div class="btn-group btn-group-sm">
            <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>"
               href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/nilai?page=' . max(1, $page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&laquo; Previous</a>
            <a class="btn btn-outline-secondary <?= $page >= $totalPage ? 'disabled' : '' ?>"
               href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/nilai?page=' . min($totalPage, $page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Next &raquo;</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
