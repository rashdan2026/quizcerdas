<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-lock-fill me-2"></i>Ganti Password Admin
            </div>
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                         style="width:64px;height:64px;background:linear-gradient(135deg,#DC2626,#991B1B);">
                        <i class="bi bi-key-fill text-white fs-3"></i>
                    </div>
                    <h5 class="fw-700 mb-0" style="color:#fff;"><?= esc($admin['nama']) ?></h5>
                    <small class="text-muted"><?= esc($admin['email']) ?></small>
                </div>

                <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.8rem;">
                    <i class="bi bi-info-circle me-1"></i>
                    Setelah password berhasil diubah, password baru akan dikirim ke
                    <strong><?= esc($admin['email']) ?></strong> sebagai catatan agar Anda tidak lupa.
                </div>

                <form action="<?= base_url('/admin/change-password') ?>" method="post" id="changePasswordForm" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Password Lama <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="current_password" id="currentPassword"
                                   class="form-control" placeholder="Masukkan password saat ini" required>
                            <button class="btn btn-outline-light" type="button" data-toggle-pw="#currentPassword" title="Tampilkan/sembunyikan">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <hr style="border-color: var(--admin-border); opacity:.5;">

                    <div class="mb-3">
                        <label class="form-label">Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key"></i></span>
                            <input type="password" name="new_password" id="newPassword"
                                   class="form-control" placeholder="Minimal 8 karakter" required minlength="8">
                            <button class="btn btn-outline-light" type="button" data-toggle-pw="#newPassword" title="Tampilkan/sembunyikan">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimal 8 karakter. Disarankan kombinasi huruf besar, kecil, angka, dan simbol.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" name="confirm_password" id="confirmPassword"
                                   class="form-control" placeholder="Ulangi password baru" required minlength="8">
                            <button class="btn btn-outline-light" type="button" data-toggle-pw="#confirmPassword" title="Tampilkan/sembunyikan">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div id="cpError" class="alert alert-danger py-2 px-3 mb-3" style="display:none;font-size:.82rem;"></div>

                    <div class="d-flex justify-content-between gap-2">
                        <a href="<?= base_url('/admin/dashboard') ?>" class="btn btn-outline-light">
                            <i class="bi bi-arrow-left me-1"></i>Batal
                        </a>
                        <button type="submit" class="btn btn-admin" id="cpSubmitBtn">
                            <i class="bi bi-check-circle me-1"></i>Simpan Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    // Toggle show/hide password
    document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sel  = btn.getAttribute('data-toggle-pw');
            var inp  = document.querySelector(sel);
            if (!inp) { return; }
            var icon = btn.querySelector('i');
            if (inp.type === 'password') {
                inp.type = 'text';
                if (icon) { icon.className = 'bi bi-eye-slash'; }
            } else {
                inp.type = 'password';
                if (icon) { icon.className = 'bi bi-eye'; }
            }
        });
    });

    var form     = document.getElementById('changePasswordForm');
    var curEl    = document.getElementById('currentPassword');
    var newEl    = document.getElementById('newPassword');
    var confEl   = document.getElementById('confirmPassword');
    var errBox   = document.getElementById('cpError');
    var submit   = document.getElementById('cpSubmitBtn');

    function showError(msg) {
        if (!errBox) { return; }
        errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + msg;
        errBox.style.display = 'flex';
    }
    function clearError() {
        if (!errBox) { return; }
        errBox.style.display = 'none';
        errBox.innerHTML = '';
    }
    [curEl, newEl, confEl].forEach(function (el) {
        if (el) { el.addEventListener('input', clearError); }
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            clearError();
            var cur   = (curEl.value  || '').trim();
            var npass = (newEl.value  || '').trim();
            var cpass = (confEl.value || '').trim();

            if (cur === '') {
                e.preventDefault();
                showError('Password lama wajib diisi.');
                curEl.focus();
                return;
            }
            if (npass.length < 8) {
                e.preventDefault();
                showError('Password baru minimal 8 karakter.');
                newEl.focus();
                return;
            }
            if (npass !== cpass) {
                e.preventDefault();
                showError('Konfirmasi password tidak sama dengan password baru.');
                confEl.focus();
                return;
            }
            if (npass === cur) {
                e.preventDefault();
                showError('Password baru tidak boleh sama dengan password lama.');
                newEl.focus();
                return;
            }

            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
        });
    }
})();
</script>
<?= $this->endSection() ?>
