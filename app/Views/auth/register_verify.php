<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#7C3AED,#A78BFA);">
                <i class="bi bi-envelope-check-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Verifikasi Email</h5>
            <p class="text-muted small mt-1">Masukkan kode verifikasi yang telah dikirim ke:</p>
            <p class="fw-600 text-primary"><?= esc($email) ?></p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/register/verify-email') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-4 text-center">
                        <label class="form-label fw-600">Kode Verifikasi (6 Digit)</label>
                        <input type="text" name="otp_code" id="otpField" class="form-control text-center fs-4 fw-bold tracking-widest" maxlength="6" placeholder="------" autocomplete="off" required style="letter-spacing:8px;">
                        <?php if (isset($dev_reg_otp)): ?>
                            <div class="alert alert-warning mt-2 py-2 small">
                                <strong>DEV MODE:</strong> Kode OTP Anda adalah <span class="fw-bold text-danger"><?= $dev_reg_otp ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($otp_valid && $expired_at): ?>
                        <div class="text-center mb-3">
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i>
                                Berlaku hingga: <?= date('d M Y, H:i', strtotime($expired_at)) ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
                        <i class="bi bi-check-circle me-1"></i>Verifikasi
                    </button>
                </form>

                <hr class="my-4">

                <div class="text-center">
                    <p class="text-muted small mb-2">Tidak menerima kode?</p>
                    <form action="<?= base_url('/register/resend-otp') ?>" method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-clockwise me-1"></i>Kirim Ulang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="<?= base_url('/register') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Ubah Data Pendaftaran
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpField = document.getElementById('otpField');
    if (otpField) {
        otpField.focus();
        otpField.addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
});
</script>
<?= $this->endSection() ?>
