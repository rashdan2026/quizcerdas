<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#047857,#10B981);">
                <i class="bi bi-check-circle-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Password Berhasil Diperbarui</h5>
        </div>

        <div class="card">
            <div class="card-body p-4 text-center">
                <p class="text-muted mb-4">
                    Password akun Anda telah berhasil diperbarui.
                    Silakan login menggunakan password baru.
                </p>
                <a href="<?= base_url('/auth') ?>" class="btn btn-primary w-100 py-2 fw-600">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login Sekarang
                </a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
