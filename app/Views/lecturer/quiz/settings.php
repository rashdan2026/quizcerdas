<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-gear me-2"></i>Pengaturan Kuis: <?= esc($quiz['title']) ?></h4>
        <p class="text-muted small mb-0 mt-1">Jadwal, durasi, status, dan Kode Kuis</p>
    </div>
    <a href="<?= base_url('/lecturer/quiz/' . $quiz['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali ke Bank Soal</a>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-calendar-event me-2"></i>Pelaksanaan Kuis</div>
    <div class="card-body">
        <form method="post" action="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/settings') ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                <input type="datetime-local" name="starts_at" class="form-control"
                       value="<?= $quiz['starts_at'] ? esc(date('Y-m-d\TH:i', strtotime($quiz['starts_at']))) : '' ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Durasi Pengerjaan (menit)</label>
                <input type="number" name="duration_minutes" class="form-control" min="1" max="1440" value="<?= (int) $quiz['duration_minutes'] ?>">
                <div class="form-text">Default 60 menit.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php foreach (['draft', 'active', 'closed', 'archived'] as $st): ?>
                        <option value="<?= $st ?>" <?= $quiz['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div>
        </form>
        <div class="form-text">
            Waktu berakhir dihitung otomatis oleh sistem (Waktu Mulai + Durasi):
            <strong><?= $quiz['ends_at'] ? esc(date('d-m-Y H:i', strtotime($quiz['ends_at']))) : '-' ?></strong>.
            Timer mahasiswa menggunakan waktu server. Satu kode = satu percobaan per mahasiswa; mahasiswa dapat mengikuti kuis ini lagi dengan kode yang di-regenerate.
        </div>
    </div>
</div>

<div class="card mb-4 text-center" style="background: linear-gradient(135deg,#3730A3 0%,#4F46E5 100%);border:none;border-radius:14px;">
    <div class="card-body py-4 text-white">
        <div class="small text-uppercase" style="letter-spacing:3px;opacity:.9;">Kode Kuis Aktif</div>
        <div id="accessCode" class="fw-bold" style="font-size:3.2rem;letter-spacing:12px;line-height:1.2;"><?= esc($quiz['access_code']) ?></div>
        <button type="button" class="btn btn-light btn-sm mt-2" onclick="copyCode(this)">
            <i class="bi bi-clipboard me-1"></i>Salin Kode
        </button>
        <div class="form-text mt-2 mb-0" style="color:rgba(255,255,255,.85);">
            Kode aktif untuk attempt baru. Kode dibuat otomatis saat kuis disimpan dan tidak berubah ketika pengaturan diedit.
        </div>
        <form action="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/regenerate-code') ?>" method="post" class="mt-2"
              onsubmit="return confirm('Regenerasi Kode Kuis?\n\nKode lama tidak berlaku untuk attempt baru.\nHistori kode dan hasil mahasiswa tetap tersimpan.')">
            <?= csrf_field() ?>
            <button class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Regenerate Kode Kuis</button>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Histori Kode Kuis</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Kode</th><th class="text-center">Status</th><th>Diterbitkan</th><th>Dinonaktifkan</th></tr></thead>
        <tbody>
        <?php foreach ($codeHistory as $c): ?>
            <tr>
                <td><code class="fw-bold" style="color:#4F46E5;"><?= esc($c['code']) ?></code></td>
                <td class="text-center">
                    <?php if ((int) $c['is_active'] === 1): ?>
                        <span class="badge bg-success rounded-pill">Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary rounded-pill">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?= esc((string) $c['created_at']) ?></td>
                <td class="small"><?= esc((string) ($c['deactivated_at'] ?? '-')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <div class="card-footer small text-muted">
        Kode lama tetap tersimpan sebagai histori dan terhubung dengan hasil percobaan mahasiswa, namun tidak dapat digunakan untuk memulai attempt baru.
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Ringkasan</div>
    <div class="card-body small">
        Total soal aktif: <strong><?= (int) $total ?></strong> &middot;
        Skor maksimum: <strong><?= (int) $maxScore ?></strong> (<?= (int) $total ?> soal &times; poin benar <?= (int) $quiz['correct_points'] ?>).
        <?php if ((int) $total === 0): ?>
            <div class="alert alert-warning mt-2 mb-0 py-2 small">Belum ada soal. <a href="<?= base_url('/lecturer/quiz/' . $quiz['id']) ?>">Kelola bank soal</a> untuk menambahkan soal.</div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function copyCode(btn) {
    var code = document.getElementById('accessCode').textContent.trim();
    var done = function () {
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Disalin!';
        var notif = document.createElement('div');
        notif.className = 'alert alert-success small position-fixed';
        notif.style.cssText = 'bottom:16px;left:50%;transform:translateX(-50%);z-index:2000;padding:.5rem 1rem;';
        notif.textContent = 'Kode Kuis berhasil disalin';
        document.body.appendChild(notif);
        setTimeout(function () { btn.innerHTML = old; notif.remove(); }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done);
    } else {
        var ta = document.createElement('textarea');
        ta.value = code; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); ta.remove(); done();
    }
}
</script>
<?= $this->endSection() ?>
