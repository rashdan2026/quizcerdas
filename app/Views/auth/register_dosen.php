<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-6">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#4F46E5,#7C3AED);">
                <i class="bi bi-person-workspace text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Pendaftaran Dosen</h5>
            <p class="text-muted small mt-1">Gunakan email institusi berakhiran <strong>.ac.id</strong></p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/register/dosen') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" maxlength="100" placeholder="Nama lengkap beserta gelar (opsional)" value="<?= esc(old('nama')) ?>" required>
                        <small class="text-muted">Contoh: Hendra Saputra, M.Kom.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="Laki-Laki" id="jk_l" <?= old('jenis_kelamin') === 'Laki-Laki' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="jk_l">Laki-Laki</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="Perempuan" id="jk_p" <?= old('jenis_kelamin') === 'Perempuan' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="jk_p">Perempuan</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_lahir" class="form-control" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-100 years')) ?>" value="<?= esc(old('tanggal_lahir')) ?>" required>
                        <small class="text-muted">Tanggal lahir tidak boleh di masa depan</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kampus <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kampus" class="form-control" maxlength="100" placeholder="Nama perguruan tinggi tempat mengajar" value="<?= esc(old('nama_kampus')) ?>" required>
                        <small class="text-muted">Contoh: Universitas Islam Riau</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Institusi <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="emailField" class="form-control" maxlength="100" placeholder="dosen@universitas.ac.id" value="<?= esc(old('email')) ?>" required pattern="[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.ac\.id" title="Email harus berakhiran .ac.id">
                        <small class="text-muted">Harus berakhiran <strong>.ac.id</strong> — kode verifikasi dikirim ke email ini. Subdomain seperti <em>nama@fakultas.kampus.ac.id</em> diperbolehkan.</small>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="pwdField" class="form-control" placeholder="Minimal 8 karakter" minlength="8" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()" tabindex="-1">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                            <input type="password" name="passwd" class="form-control" placeholder="Ulangi password" minlength="8" required>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-600">
                            <i class="bi bi-envelope-check me-1"></i>Daftar &amp; Verifikasi Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= base_url('/register') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Pilih jenis pendaftaran lain
            </a>
            <span class="text-muted mx-1">|</span>
            <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#059669;">
                <i class="bi bi-box-arrow-in-right me-1"></i>Sudah punya akun? Login
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
