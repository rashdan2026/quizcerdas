<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h4 class="page-title mb-3"><i class="bi bi-lightbulb me-2"></i>Quiz</h4>

<div class="card mb-4 mx-auto" style="max-width: 640px;">
    <div class="card-body">
        <h6 class="mb-3 fw-bold" style="letter-spacing:1px;">KODE KUIS</h6>
        <form method="post" action="<?= base_url('/student/quiz/info') ?>">
            <?= csrf_field() ?>
            <input type="text" name="access_code" class="form-control form-control-lg text-center fw-bold"
                   placeholder="6 karakter" maxlength="6" required
                   style="text-transform:uppercase;letter-spacing:6px;font-size:1.4rem;"
                   autocomplete="off" autocapitalize="characters" inputmode="text" spellcheck="false"
                   pattern="[A-Za-z0-9]{6,16}" title="6 huruf kapital/angka">
            <div class="form-text text-center mt-2">Masukkan kode kuis dari dosen Anda.</div>
            <button class="btn btn-primary w-100 mt-3" style="padding:.7rem;">Buka Kuis</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Hasil Kuis Saya</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>No</th><th>Kuis</th><th>MK</th><th class="text-center">Skor</th><th class="text-center">Persentase</th><th>Waktu</th></tr></thead>
        <tbody>
        <?php $no = 1; foreach ($results as $r): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= esc($r['title']) ?>
                    <div class="small text-muted">Kode: <code><?= esc($r['access_code'] ?? '-') ?></code> &middot; Percobaan #<?= (int) $r['attempt_no'] ?></div>
                </td>
                <td><?= esc($r['kode_mk']) ?></td>
                <td class="text-center fw-bold"><?= (int) $r['raw_score'] ?> / <?= (int) $r['max_score'] ?></td>
                <td class="text-center"><?= $r['percentage'] !== null ? round((float) $r['percentage'], 2) . '%' : '-' ?></td>
                <td class="small"><?= esc((string) $r['submitted_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($results)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada hasil kuis.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
    <div class="card-footer small text-muted">Rincian per soal hanya tersedia untuk dosen.</div>
</div>
<?= $this->endSection() ?>
