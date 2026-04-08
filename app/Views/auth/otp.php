<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-4 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#0284C7,#0EA5E9);">
                <i class="bi bi-envelope-check-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Verifikasi OTP</h5>
            <p class="text-muted small mt-1">Masukkan kode 6 digit yang dikirim ke email Anda</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <?php if (session('dev_otp_code')): ?>
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>Mode Development</strong><br>
                            Kode OTP: <code class="fs-5 fw-700"><?= esc(session('dev_otp_code')) ?></code>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (session('otp_email')): ?>
                    <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                        <i class="bi bi-envelope-fill flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>OTP telah dikirim ke:</strong><br>
                            <code class="fs-6"><?= esc(session('otp_email')) ?></code>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form action="<?= base_url('/auth/otp') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-key me-1"></i>Kode OTP (6 digit)</label>
                        <input type="text" name="otp_code" class="form-control text-center fw-700 fs-4"
                               maxlength="6" value="<?= esc(old('otp_code')) ?>"
                               placeholder="000000" autocomplete="one-time-code" required
                               style="letter-spacing:8px;">
                    </div>
                    <button class="btn btn-primary w-100 py-2 fw-600" type="submit">
                        <i class="bi bi-check-circle me-1"></i>Verifikasi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
