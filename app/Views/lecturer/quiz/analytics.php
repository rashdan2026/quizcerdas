<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-bar-chart me-2"></i>Analitik: <?= esc($quiz['title']) ?></h4>
        <p class="text-muted small mb-0 mt-1">Waktu respons tidak memengaruhi nilai; hanya untuk evaluasi kualitas soal.</p>
    </div>
    <a href="<?= base_url('/lecturer/quiz/' . $quiz['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Total Soal</div><div class="fs-4 fw-bold"><?= (int) $stats['total'] ?></div>
        <div class="small text-muted">Manual <?= (int) $stats['manual'] ?> / AI <?= (int) $stats['ai_pdf'] ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Distribusi Kesulitan</div>
        <div class="small mt-1">Mudah <?= (int) $stats['easy'] ?> &middot; Sedang <?= (int) $stats['medium'] ?> &middot; Sulit <?= (int) $stats['hard'] ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Peserta</div><div class="fs-4 fw-bold"><?= count($attempts) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card stat-card"><div class="card-body">
        <div class="text-muted small">Rata-rata</div>
        <div class="fw-bold"><?= (float) $avgRaw ?> poin &middot; <?= (float) $avgPct ?>%</div>
    </div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header">Analitik Per Soal</div>
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr>
            <th>Soal</th><th>Sumber</th><th class="text-center">Respons</th><th class="text-center">% Benar</th>
            <th class="text-center">Salah</th><th class="text-center">Tidak Jawab/E</th><th class="text-center">Rata-rata Waktu</th>
        </tr></thead>
        <tbody>
        <?php foreach ($perQuestion as $row): ?>
            <tr>
                <td class="small"><?= esc(mb_substr($row['question']['question_text'], 0, 80)) ?><?= mb_strlen($row['question']['question_text']) > 80 ? '…' : '' ?></td>
                <td><span class="badge bg-<?= $row['question']['source_type'] === 'manual' ? 'secondary' : 'primary' ?>"><?= esc($row['question']['source_type']) ?></span>
                    <?= $row['question']['difficulty_level'] ? '<span class="badge bg-light text-dark">' . esc((string) $row['question']['difficulty_level']) . '</span>' : '' ?></td>
                <td class="text-center"><?= (int) $row['responses'] ?></td>
                <td class="text-center fw-bold"><?= $row['right_pct'] === null ? '-' : $row['right_pct'] . '%' ?></td>
                <td class="text-center"><?= (int) $row['wrong'] ?></td>
                <td class="text-center"><?= (int) $row['blank'] ?></td>
                <td class="text-center"><?= $row['avg_rt_sec'] === null ? '-' : $row['avg_rt_sec'] . ' s' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($perQuestion)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada soal.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <div class="card-footer small text-muted">Urutan berdasarkan soal yang paling lama dijawab.</div>
</div>
<?= $this->endSection() ?>
