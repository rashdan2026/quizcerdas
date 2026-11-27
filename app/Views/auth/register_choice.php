<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#3730A3,#4F46E5);">
                <i class="bi bi-person-plus-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Pilih Jenis Pendaftaran</h5>
            <p class="text-muted small mt-1 mb-0">Buat akun sesuai peran Anda di KelasMerdeka.com</p>
        </div>

        <div class="row g-3 g-md-4">
            <!-- Mahasiswa -->
            <div class="col-12 col-md-6">
                <div class="card h-100 border-0" style="border:2px solid #E2E8F0;border-radius:14px;">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                                  style="width:48px;height:48px;background:linear-gradient(135deg,#059669,#10B981);">
                                <i class="bi bi-mortarboard-fill text-white fs-4"></i>
                            </span>
                            <div>
                                <h6 class="fw-700 mb-0" style="color:#1E293B;">Pendaftaran Mahasiswa</h6>
                                <small class="text-muted">Untuk mahasiswa</small>
                            </div>
                        </div>
                        <p class="text-muted mb-4" style="font-size:.875rem;line-height:1.6;">
                            Buat akun untuk mengakses materi pembelajaran, mengikuti perkuliahan,
                            mengisi presensi, dan mengerjakan kuis.
                        </p>
                        <a href="<?= base_url('/register/mahasiswa') ?>"
                           class="btn btn-success w-100 mt-auto py-2 fw-600">
                            <i class="bi bi-person-plus me-1"></i>Daftar sebagai Mahasiswa
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dosen -->
            <div class="col-12 col-md-6">
                <div class="card h-100 border-0" style="border:2px solid #E2E8F0;border-radius:14px;">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                                  style="width:48px;height:48px;background:linear-gradient(135deg,#4F46E5,#7C3AED);">
                                <i class="bi bi-person-workspace text-white fs-4"></i>
                            </span>
                            <div>
                                <h6 class="fw-700 mb-0" style="color:#1E293B;">Pendaftaran Dosen</h6>
                                <small class="text-muted">Untuk dosen</small>
                            </div>
                        </div>
                        <p class="text-muted mb-4" style="font-size:.875rem;line-height:1.6;">
                            Buat akun untuk mengelola kelas, membagikan materi, membuat kuis,
                            dan memantau aktivitas pembelajaran mahasiswa.
                        </p>
                        <a href="<?= base_url('/register/dosen') ?>"
                           class="btn btn-primary w-100 mt-auto py-2 fw-600">
                            <i class="bi bi-person-workspace me-1"></i>Daftar sebagai Dosen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke halaman login
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
