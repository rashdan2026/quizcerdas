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
            <p class="text-muted small mt-1">Masukkan email untuk reset password</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
                        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                        <span><?= esc(session()->getFlashdata('success')) ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                        <span><?= esc(session()->getFlashdata('error')) ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="<?= base_url('/auth/forgot-password') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person-badge me-1"></i>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= esc(old('email')) ?>" placeholder="Masukkan Email Anda" required>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>Password baru akan dikirim ke email Anda
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-shield-check me-1"></i>Captcha</label>
                        <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded-2" style="background:#F8FAFC;border:1.5px solid #E2E8F0;">
                            <span class="px-3 py-1 rounded-2 fw-700 fs-5 text-white" style="background:linear-gradient(135deg,#3730A3,#4F46E5);letter-spacing:4px;font-family:monospace;"><?= esc($captcha) ?></span>
                            <small class="text-muted">← Ketik 5 karakter ini</small>
                        </div>
                        <input type="text" name="captcha" class="form-control text-uppercase text-center fw-600 ls-3" maxlength="5" placeholder="XXXXX" autocomplete="off" required style="letter-spacing:6px;">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
                        <i class="bi bi-send me-1"></i>Kirim Reset Password
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                        <i class="bi bi-arrow-left me-1"></i>Kembali ke Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
