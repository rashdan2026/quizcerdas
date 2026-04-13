<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-circle me-2"></i>Informasi Profil
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                         style="width:80px;height:80px;background:linear-gradient(135deg,#4F46E5,#3730A3);">
                        <i class="bi bi-person-fill text-white" style="font-size:2.5rem;"></i>
                    </div>
                    <h5 class="fw-700 mb-1"><?= esc($user['nama']) ?></h5>
                    <span class="badge" style="background:#4F46E5;color:#fff;">
                        <?= $role === 'lecturer' ? 'Dosen' : 'Mahasiswa' ?>
                    </span>
                </div>
                <hr>
                <div class="mb-3">
                    <label class="text-muted small mb-1">
                        <i class="bi bi-envelope me-1"></i>Email
                    </label>
                    <div class="fw-500"><?= esc($user['email']) ?></div>
                </div>
                <?php if ($role === 'student' && !empty($user['npm'])): ?>
                <div class="mb-3">
                    <label class="text-muted small mb-1">
                        <i class="bi bi-card-text me-1"></i>NPM
                    </label>
                    <div class="fw-500"><?= esc($user['npm']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($user['kelas'])): ?>
                <div class="mb-3">
                    <label class="text-muted small mb-1">
                        <i class="bi bi-mortarboard me-1"></i>Kelas
                    </label>
                    <div class="fw-500"><?= esc($user['kelas']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($user['no_whatsapp'])): ?>
                <div class="mb-3">
                    <label class="text-muted small mb-1">
                        <i class="bi bi-whatsapp me-1"></i>No. WhatsApp
                    </label>
                    <div class="fw-500"><?= esc($user['no_whatsapp']) ?></div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="text-muted small mb-1">
                        <i class="bi bi-clock me-1"></i>Login Terakhir
                    </label>
                    <div class="fw-500">
                        <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Belum pernah login' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-lock me-2"></i>Ganti Password
            </div>
            <div class="card-body">
                <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div>
                        <strong>Panduan Password:</strong>
                        <ul class="mb-0 mt-2 small">
                            <li>Password minimal 8 karakter</li>
                            <li>Gunakan kombinasi huruf, angka, dan simbol untuk keamanan</li>
                            <li>Jangan gunakan password yang sama dengan sebelumnya</li>
                        </ul>
                    </div>
                </div>

                <form action="<?= base_url('/profile/change-password') ?>" method="post" id="changePasswordForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">
                            <i class="bi bi-lock me-1"></i>Password Saat Ini <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="bi bi-lock-fill me-1"></i>Password Baru <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimal 8 karakter</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">
                            <i class="bi bi-check-circle me-1"></i>Konfirmasi Password Baru <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMismatch" class="invalid-feedback">Password tidak cocok</div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>Ubah Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, button) {
    const field = document.getElementById(fieldId);
    const icon = button.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Client-side validation for password match
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const confirmField = document.getElementById('confirm_password');

    if (newPassword !== confirmPassword) {
        e.preventDefault();
        confirmField.classList.add('is-invalid');
        confirmField.focus();
        return false;
    } else {
        confirmField.classList.remove('is-invalid');
    }

    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password baru minimal 8 karakter');
        return false;
    }
});

// Remove invalid class on input
document.getElementById('confirm_password').addEventListener('input', function() {
    this.classList.remove('is-invalid');
});
</script>
<?= $this->endSection() ?>
