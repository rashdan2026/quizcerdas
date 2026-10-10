<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#3730A3,#4F46E5);">
                <i class="bi bi-mortarboard-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Selamat Datang</h5>
            <p class="text-muted small mt-1">Masuk ke Sistem Absensi Kampus</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/auth/login') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person me-1"></i>Role</label>
                        <select name="role" class="form-select" required>
                            <option value="student" <?= old('role') === 'student' ? 'selected' : '' ?>>🎓 Mahasiswa</option>
                            <option value="lecturer" <?= old('role') === 'lecturer' ? 'selected' : '' ?>>👨‍🏫 Dosen</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-person-badge me-1"></i>Email</label>
                        <input type="email" name="identifier" class="form-control" value="<?= esc(old('identifier')) ?>" placeholder="Masukkan Email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="pwdField" class="form-control" placeholder="••••••••" required>
                            <button type="button" id="togglePwdBtn" class="btn btn-outline-secondary" onclick="togglePwd()" tabindex="-1" aria-label="Tampilkan atau sembunyikan password" style="border-radius:0 8px 8px 0;border-left:0;">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
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
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                    </button>
                    <div class="text-center mt-3">
                        <a href="<?= base_url('/auth/forgot-password') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                            <i class="bi bi-key me-1"></i>Lupa Password?
                        </a>
                    </div>
                    <hr class="my-3" style="border-color:#E2E8F0;">
                    <div class="text-center">
                        <span class="text-muted small">Belum punya akun?</span>
                        <a href="<?= base_url('/register') ?>" class="text-decoration-none small ms-1" style="color:#059669;">
                            <i class="bi bi-person-plus me-1"></i>Daftar di sini
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
function togglePwd() {
    var f = document.getElementById('pwdField');
    var i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
<?php if (! empty($loginAd)): ?>
<?= /* v5.8.5: iklan tidak ditampilkan di halaman login (pre-auth & post-logout). */ '' ?>
<?php endif; ?>
<?= $this->endSection() ?>
