<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-8 col-lg-7">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#4F46E5,#3730A3);">
                <i class="bi bi-person-check-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Konfirmasi Profil Pertama</h5>
            <p class="text-muted small mt-1">
                Periksa data profil Anda, sesuaikan jika perlu, lalu tekan
                <strong>Simpan dan Konfirmasi Profil</strong> untuk masuk ke dashboard dosen.
            </p>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2 py-2 small" role="alert">
            <i class="bi bi-shield-check flex-shrink-0 mt-1"></i>
            <div>
                Email institusi Anda sudah terverifikasi dan <strong>tidak dapat diubah</strong>.
                Setelah konfirmasi ini disimpan, profil menjadi <strong>hanya-baca</strong>.
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/lecturer/confirm-profile/save') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" maxlength="100" value="<?= esc(old('nama', $lecturer['nama'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="Laki-Laki" id="jk_l" <?= old('jenis_kelamin', $lecturer['jenis_kelamin']) === 'Laki-Laki' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="jk_l">Laki-Laki</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="jenis_kelamin" value="Perempuan" id="jk_p" <?= old('jenis_kelamin', $lecturer['jenis_kelamin']) === 'Perempuan' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="jk_p">Perempuan</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_lahir" class="form-control" max="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d', strtotime('-100 years')) ?>" value="<?= esc(old('tanggal_lahir', $lecturer['tanggal_lahir'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kampus <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kampus" class="form-control" maxlength="100" value="<?= esc(old('nama_kampus', $lecturer['nama_kampus'])) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Institusi <span class="badge bg-success-subtle text-success ms-1" style="font-size:.7rem;"><i class="bi bi-patch-check me-1"></i>Terverifikasi</span></label>
                        <input type="email" class="form-control" value="<?= esc($lecturer['email']) ?>" readonly tabindex="-1" style="background:#F1F5F9;color:#64748B;cursor:not-allowed;">
                        <small class="text-muted">Email institusi sudah diverifikasi dan tidak dapat diubah.</small>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary py-2 fw-600">
                            <i class="bi bi-save me-1"></i>Simpan dan Konfirmasi Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="<?= base_url('/logout') ?>" class="text-decoration-none small text-muted">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
