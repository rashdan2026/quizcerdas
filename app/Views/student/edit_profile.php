<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-6">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#059669,#10B981);">
                <i class="bi bi-person-lines-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Edit Profil</h5>
            <p class="text-muted small mt-1">Perbarui data profil Anda</p>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/student/dashboard/edit-profile') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">NPM <span class="text-danger">*</span></label>
                            <input type="text" name="npm" class="form-control" maxlength="9" placeholder="9 digit NPM" value="<?= esc($student['npm']) ?>" required>
                            <small class="text-muted">Contoh: 22090101</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" class="form-control" maxlength="20" placeholder="Contoh: A1" value="<?= esc($student['kelas']) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control" maxlength="100" placeholder="Nama sesuai KTP" value="<?= esc($student['nama']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. WhatsApp</label>
                        <input type="text" name="no_whatsapp" class="form-control" maxlength="15" placeholder="08xxxxxxxxxx" value="<?= esc($student['no_whatsapp']) ?>">
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Informasi:</strong> Anda hanya dapat mengedit profil 1x dalam 1 minggu. Pastikan data sudah benar sebelum disimpan.
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-600">
                            <i class="bi bi-check-circle me-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-3">
            <a href="<?= base_url('/student/dashboard') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
