<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#4F46E5,#7C3AED);">
                <i class="bi bi-envelope-check-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Verifikasi Email Institusi</h5>
            <p class="text-muted small mt-1">Masukkan kode verifikasi yang telah dikirim ke:</p>
            <p class="fw-600 text-primary"><?= esc($email) ?></p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/register/dosen/verify-email') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-4 text-center">
                        <label class="form-label fw-600">Kode Verifikasi (6 Digit)</label>
                        <input type="text" name="verify_code" id="otpField" class="form-control text-center fs-4 fw-bold" maxlength="6" inputmode="numeric" placeholder="------" autocomplete="off" required style="letter-spacing:8px;">
                        <?php if (! empty($dev_dosen_otp)): ?>
                            <div class="alert alert-warning mt-2 py-2 small">
                                <strong>DEV MODE:</strong> Kode verifikasi Anda adalah <span class="fw-bold text-danger"><?= $dev_dosen_otp ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (! empty($code_valid) && $expired_at): ?>
                        <div class="text-center mb-3">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Berlaku hingga: <?= date('d M Y, H:i', strtotime($expired_at)) ?>
                                &middot; Sisa percobaan: <?= $attempts_left ?>
                            </small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 text-center small mb-3">
                            Kode verifikasi sudah kedaluwarsa. Silakan minta kode baru di bawah.
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
                        <i class="bi bi-check-circle me-1"></i>Verifikasi &amp; Aktifkan Akun
                    </button>
                </form>

                <hr class="my-3" style="border-color:#E2E8F0;">
                <div class="text-center">
                    <?php if (! empty($resend['exhausted'])): ?>
                        <div class="alert alert-warning py-2 small mb-2">
                            Anda telah mencapai batas pengiriman ulang email hari ini. Silakan coba kembali besok.
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                            <i class="bi bi-arrow-clockwise me-1"></i>Kirim Ulang Email
                        </button>
                    <?php else: ?>
                        <button type="button" id="btnResend" class="btn btn-outline-secondary btn-sm"
                            <?= empty($resend['allowed']) ? 'disabled' : '' ?>
                            data-wait="<?= (int) ($resend['wait_seconds'] ?? 0) ?>"
                            onclick="resendCode()">
                            <i class="bi bi-arrow-clockwise me-1"></i><span id="resendLabel">Kirim Ulang Email<?= ! empty($resend['wait_seconds']) ? ' (' . gmdate('i:s', (int) $resend['wait_seconds']) . ')' : '' ?></span>
                        </button>
                        <small class="text-muted d-block mt-1">Sisa kirim ulang hari ini: <?= (int) ($resend['remaining_today'] ?? 4) ?>x</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke halaman login
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script src="<?= base_url('/assets/js/resend-countdown.js') ?>"></script>
<script>
function resendCode() {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '<?= base_url('/register/dosen/resend') ?>';
    // Salin CSRF token dari form verifikasi utama
    var token = document.querySelector('form input[name^="csrf_"]');
    if (token) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = token.name;
        hidden.value = token.value;
        form.appendChild(hidden);
    }
    document.body.appendChild(form);
    form.submit();
}
document.addEventListener('DOMContentLoaded', function () {
    if (window.kmResendCountdown && document.getElementById('btnResend')) {
        window.kmResendCountdown('btnResend', 'resendLabel');
    }
});
</script>
<?= $this->endSection() ?>
