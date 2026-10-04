<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-6">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#059669,#10B981);">
                <i class="bi bi-person-plus-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Pendaftaran Mahasiswa Baru</h5>
            <p class="text-muted small mt-1">Isi form di bawah untuk mendaftar</p>
            <?php if (!$can_register): ?>
                <div class="alert alert-warning py-2 mt-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Anda telah mencapai batas maksimal <?= $max_attempts ?>x pendaftaran per hari.
                </div>
            <?php elseif ($attempts_today > 0): ?>
                <small class="text-muted">Sisa kesempatan: <?= $max_attempts - $attempts_today ?>x lagi hari ini</small>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if (!$can_register): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-hourglass-split display-5 text-muted d-block mb-3"></i>
                        <p class="text-muted">Pendaftaran akan tersedia kembali besok.</p>
                        <a href="<?= base_url('/auth') ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </div>
                <?php else: ?>
                    <form action="<?= base_url('/register') ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">NPM <span class="text-danger">*</span></label>
                                <input type="text" name="npm" class="form-control" maxlength="9" placeholder="9 digit NPM" value="<?= esc(old('npm')) ?>" required>
                                <small class="text-muted">Contoh: 22090101</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kelas <span class="text-muted">(opsional)</span></label>
                                <input type="text" name="kelas" class="form-control" maxlength="20" placeholder="Contoh: A1" value="<?= esc(old('kelas')) ?>">
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="nama" class="form-control" maxlength="100" placeholder="Nama sesuai KTP" value="<?= esc(old('nama')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" maxlength="100" placeholder="email@domain.com" value="<?= esc(old('email')) ?>" required>
                            <small class="text-muted">Kode verifikasi akan dikirim ke email ini</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">No. WhatsApp <span class="text-muted">(opsional)</span></label>
                            <input type="text" name="no_whatsapp" class="form-control" maxlength="20" placeholder="08xxxxxxxxxx" value="<?= esc(old('no_whatsapp')) ?>">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="pwdField" class="form-control" placeholder="Minimal 8 karakter" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" tabindex="-1">
                                        <i class="bi bi-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" name="passwd" class="form-control" placeholder="Ulangi password" required>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-success w-100 py-2 fw-600">
                                <i class="bi bi-envelope-check me-1"></i>Daftar & Verifikasi Email
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Sudah punya akun? Login di sini
            </a>
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
<?= $this->endSection() ?>
