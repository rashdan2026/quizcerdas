<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#3730A3,#4F46E5);">
                <i class="bi bi-key-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Lupa Password</h5>
            <p class="text-muted small mt-1">Masukkan email akun Mahasiswa atau Dosen Anda</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success d-flex align-items-start gap-2 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill flex-shrink-0 mt-1"></i>
                        <span><?= session()->getFlashdata('success') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                        <span><?= esc(session()->getFlashdata('error')) ?></span>
                    </div>
                <?php endif; ?>

                <form action="<?= base_url('/auth/forgot-password') ?>" method="post" id="forgotForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-envelope me-1"></i>Email</label>
                        <input type="email" name="email" id="resetEmailInput" class="form-control"
                               value="<?= esc(old('email', $resetEmail ?? '')) ?>" placeholder="nama@kampus.ac.id" required>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>Kami akan mengirim tautan untuk membuat password baru. Password lama tetap berlaku sampai proses selesai.
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-shield-check me-1"></i>Captcha</label>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-2" style="background:#F8FAFC;border:1.5px solid #E2E8F0;">
                            <span class="px-3 py-1 rounded-2 fw-700 fs-5 text-white" style="background:linear-gradient(135deg,#3730A3,#4F46E5);letter-spacing:4px;font-family:monospace;"><?= esc($captcha) ?></span>
                            <small class="text-muted"><i class="bi bi-arrow-left"></i> Ketik 5 karakter ini</small>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase text-center fw-600 ls-3" maxlength="5" placeholder="XXXXX" autocomplete="off" required style="letter-spacing:6px;">
                    </div>

                    <?php
                        $wait   = (int) ($resetState['wait_seconds'] ?? 0);
                        $maxed  = ! empty($resetState) && ($resetState['remaining'] ?? 0) === 0;
                        $window = (int) ($resetState['window_reset_seconds'] ?? 0);
                    ?>

                    <button type="submit" id="btnReset" class="btn btn-primary w-100 py-2 fw-600"
                        <?= ($wait > 0 || $maxed) ? 'disabled' : '' ?>
                        data-wait="<?= $wait ?>">
                        <i class="bi bi-send me-1"></i><span id="resetLabel">Kirim Link Reset<?= $wait > 0 ? ' (' . gmdate('i:s', $wait) . ')' : '' ?></span>
                    </button>

                    <?php if ($maxed): ?>
                        <div class="alert alert-warning py-2 mt-3 mb-0 small">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Anda telah mencapai batas permintaan reset password. Silakan coba kembali setelah
                            <?= $window >= 3600 ? ceil($window / 3600) . ' jam' : max(1, ceil($window / 60)) . ' menit' ?>.
                        </div>
                    <?php elseif ($wait > 0): ?>
                        <div class="alert alert-info py-2 mt-3 mb-0 small" id="resetWaitInfo">
                            <i class="bi bi-clock me-1"></i>Anda dapat meminta link reset password kembali dalam <span id="resetWaitText"><?= gmdate('i:s', $wait) ?></span>.
                        </div>
                    <?php endif; ?>
                </form>

                <div class="text-center mt-3">
                    <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Login
                    </a>
                </div>
            </div>
        </div>

        <p class="text-center text-muted mt-3 mb-0" style="font-size:.78rem;">
            Demi keamanan, respons halaman ini sama untuk semua alamat email.
        </p>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('/assets/js/resend-countdown.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnReset');
    if (!btn) return;

    // Countdown dari sisa detik SERVER (bukan jam perangkat).
    if (window.kmResendCountdown) {
        window.kmResendCountdown('btnReset', 'resetLabel', 'Kirim Link Reset', 'resetWaitText', 'resetWaitInfo');
    }

    // Cegah double-click.
    document.getElementById('forgotForm').addEventListener('submit', function () {
        if (!btn.disabled) {
            btn.disabled = true;
            setTimeout(function () { btn.disabled = false; }, 4000);
        }
    });
});
</script>
<?= $this->endSection() ?>
