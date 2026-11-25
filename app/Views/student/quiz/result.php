<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card text-center">
    <div class="card-body py-5">
        <i class="bi bi-trophy display-4 mb-3" style="color:#4F46E5;"></i>
        <h4 class="fw-bold"><?= $attempt['status'] === 'timeout' ? 'Waktu Habis' : 'Attempt Selesai' ?></h4>
        <p class="text-muted">Kuis: <?= esc($quiz['title'] ?? '') ?></p>
        <p class="small text-muted mb-0">Kode Kuis: <code><?= esc($attempt['access_code'] ?? '-') ?></code> &middot; Percobaan #<?= (int) ($attempt['attempt_no'] ?? 1) ?></p>
        <hr class="mx-auto" style="width:60px;">
        <div class="row justify-content-center g-3 mt-2">
            <div class="col-md-4">
                <div class="text-muted small">Nilai (Skor)</div>
                <div class="display-6 fw-bold"><?= (int) $attempt['raw_score'] ?></div>
                <div class="small text-muted">dari maksimum <?= (int) $attempt['max_score'] ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Persentase</div>
                <div class="display-6 fw-bold"><?= $attempt['percentage'] !== null ? round((float) $attempt['percentage'], 2) . '%' : '-' ?></div>
            </div>
        </div>
        <p class="text-muted small mt-4 mb-0">Attempt Anda telah selesai. Rincian jawaban per soal hanya tersedia bagi dosen.</p>
        <a href="<?= base_url('/student/quiz') ?>" class="btn btn-primary mt-3">Kembali ke Kuis</a>
    </div>
</div>
<?= $this->endSection() ?>
