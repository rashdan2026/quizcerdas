<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#3730A3,#4F46E5);">
                <i class="bi bi-person-plus-fill text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;">Pilih Jenis Pendaftaran</h5>
            <p class="text-muted small mt-1">Pilih jenis akun yang ingin Anda daftarkan</p>
        </div>

        <form id="choiceForm" action="#" method="get">
            <!-- Kotak pilihan: disusun vertikal, seluruh kotak dapat diklik -->
            <label class="card reg-choice d-flex flex-row align-items-start gap-3 p-3 mb-3"
                   style="cursor:pointer;border:2px solid #E2E8F0;border-radius:12px;transition:all .15s;">
                <input type="radio" name="reg_type" value="mahasiswa" class="form-check-input mt-2 ms-1" autocomplete="off">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                      style="width:48px;height:48px;background:linear-gradient(135deg,#059669,#10B981);">
                    <i class="bi bi-mortarboard-fill text-white fs-4"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="d-block fw-700" style="color:#1E293B;">PENDAFTARAN MAHASISWA</span>
                    <span class="d-block text-muted mt-1" style="font-size:.85rem;line-height:1.5;">
                        Mendaftar sebagai mahasiswa. Menggunakan akun mahasiswa, mengikuti kuis
                        dengan Kode Kuis, serta melihat hasil dan riwayat pengerjaan kuis.
                    </span>
                </span>
                <i class="bi bi-check-circle-fill text-success fs-4 mt-1 reg-check d-none"></i>
            </label>

            <label class="card reg-choice d-flex flex-row align-items-start gap-3 p-3 mb-3"
                   style="cursor:pointer;border:2px solid #E2E8F0;border-radius:12px;transition:all .15s;">
                <input type="radio" name="reg_type" value="dosen" class="form-check-input mt-2 ms-1" autocomplete="off">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0"
                      style="width:48px;height:48px;background:linear-gradient(135deg,#4F46E5,#7C3AED);">
                    <i class="bi bi-person-workspace text-white fs-4"></i>
                </span>
                <span class="flex-grow-1">
                    <span class="d-block fw-700" style="color:#1E293B;">PENDAFTARAN DOSEN</span>
                    <span class="d-block text-muted mt-1" style="font-size:.85rem;line-height:1.5;">
                        Mendaftar sebagai dosen menggunakan email institusi (.ac.id). Membuat dan
                        mengelola kuis beserta soalnya (manual maupun AI), mengelola Kode Kuis,
                        serta memantau hasil dan nilai mahasiswa.
                    </span>
                </span>
                <i class="bi bi-check-circle-fill text-success fs-4 mt-1 reg-check d-none"></i>
            </label>

            <button type="submit" id="btnLanjut" class="btn btn-primary w-100 py-2 fw-600" disabled>
                <i class="bi bi-arrow-right-circle me-1"></i>Lanjutkan
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= base_url('/auth') ?>" class="text-decoration-none small" style="color:#4F46E5;">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke halaman login
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('input[name="reg_type"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('btnLanjut').disabled = false;
        document.querySelectorAll('.reg-choice').forEach(function (box) {
            box.classList.remove('is-selected');
            box.style.borderColor = '#E2E8F0';
            box.style.background = '#FFFFFF';
            box.querySelector('.reg-check').classList.add('d-none');
        });
        var selected = radio.closest('.reg-choice');
        selected.classList.add('is-selected');
        selected.style.borderColor = '#4F46E5';
        selected.style.background = '#EEF2FF';
        selected.querySelector('.reg-check').classList.remove('d-none');
    });
});

document.getElementById('choiceForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var val = (document.querySelector('input[name="reg_type"]:checked') || {}).value;
    if (val === 'mahasiswa') {
        window.location.href = '<?= base_url('/register/mahasiswa') ?>';
    } else if (val === 'dosen') {
        window.location.href = '<?= base_url('/register/dosen') ?>';
    }
});
</script>
<?= $this->endSection() ?>
