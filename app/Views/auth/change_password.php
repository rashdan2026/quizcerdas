<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-4 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#D97706,#F59E0B);">
                <i class="bi bi-shield-lock-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Ganti Password</h5>
            <p class="text-muted small mt-1">Buat password baru yang kuat untuk akun Anda</p>
        </div>
        <div class="card">
            <div class="card-body p-4">
                <form action="<?= base_url('/auth/change-password') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-lock me-1"></i>Password Baru</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min. 8 karakter" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label"><i class="bi bi-lock-fill me-1"></i>Konfirmasi Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password baru" required>
                    </div>
                    <button class="btn btn-warning w-100 py-2 fw-600 text-white" type="submit">
                        <i class="bi bi-check-circle me-1"></i>Simpan Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
