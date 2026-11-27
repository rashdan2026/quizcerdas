<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<meta name="referrer" content="no-referrer">
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#3730A3,#4F46E5);">
                <i class="bi bi-shield-lock-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Atur Ulang Password</h5>
            <p class="text-muted small mt-1">Buat password baru untuk <strong><?= esc($email) ?></strong></p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                        <span><?= esc(session()->getFlashdata('error')) ?></span>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info py-2 small d-flex align-items-center justify-content-between" role="status">
                    <span><i class="bi bi-clock me-1"></i>Link berlaku</span>
                    <strong id="expiryText"><?= gmdate('i:s', max(0, (int) $seconds_left)) ?></strong>
                </div>

                <form action="<?= base_url('/reset-password/' . $token) ?>" method="post" id="resetForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="newPassword">Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="newPassword" class="form-control"
                                   placeholder="Minimal 8 karakter" minlength="8" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('newPassword', this)" tabindex="-1" aria-label="Tampilkan/sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Gunakan kombinasi huruf, angka, dan simbol.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="newPasswordConfirm">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password_confirm" id="newPasswordConfirm" class="form-control"
                                   placeholder="Ulangi password baru" minlength="8" autocomplete="new-password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('newPasswordConfirm', this)" tabindex="-1" aria-label="Tampilkan/sembunyikan password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" id="btnSave" class="btn btn-primary w-100 py-2 fw-600">
                        <i class="bi bi-check-circle me-1"></i>Simpan Password Baru
                    </button>
                </form>

                <div id="expiredBox" class="alert alert-warning small mt-3 d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>Link reset password sudah kedaluwarsa. Silakan minta link baru.
                    <div class="mt-2">
                        <a href="<?= base_url('/auth/forgot-password') ?>" class="btn btn-sm btn-outline-primary">Kembali ke Lupa Password</a>
                    </div>
                </div>

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
<?= $this->section('scripts') ?>
<script>
function togglePwd(id, btn) {
    var f = document.getElementById(id);
    var i = btn.querySelector('i');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}

document.addEventListener('DOMContentLoaded', function () {
    var seconds = <?= (int) $seconds_left ?>;
    var expiryEl = document.getElementById('expiryText');
    var form = document.getElementById('resetForm');
    var btn = document.getElementById('btnSave');
    var expiredBox = document.getElementById('expiredBox');
    var deadline = Date.now() + seconds * 1000;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    var timer = setInterval(function () {
        var left = Math.round((deadline - Date.now()) / 1000);
        if (left <= 0) {
            clearInterval(timer);
            if (expiryEl) expiryEl.textContent = '00:00';
            if (form) form.classList.add('d-none');
            if (btn) btn.disabled = true;
            if (expiredBox) expiredBox.classList.remove('d-none');
            return;
        }
        if (expiryEl) expiryEl.textContent = pad(Math.floor(left / 60)) + ':' + pad(left % 60);
    }, 1000);
});
</script>
<?= $this->endSection() ?>
