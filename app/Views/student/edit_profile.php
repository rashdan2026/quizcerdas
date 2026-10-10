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
                <form action="<?= base_url('/student/dashboard/edit-profile') ?>" method="post" id="editProfileForm" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">NPM <span class="text-danger">*</span></label>
                            <input type="text" name="npm" id="epNpm" class="form-control" maxlength="9" placeholder="9 digit NPM" value="<?= esc($student['npm']) ?>" required inputmode="numeric">
                            <small class="text-muted">Contoh: 22090101</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <input type="text" name="kelas" id="epKelas"
                                   class="form-control text-uppercase"
                                   maxlength="20"
                                   placeholder="Contoh: A1"
                                   value="<?= esc($student['kelas']) ?>"
                                   required
                                   pattern="^[A-Z]+$"
                                   style="letter-spacing:1px;font-weight:600;">
                            <small class="text-muted">Hanya huruf besar A-Z, tanpa angka atau simbol.</small>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="epNama" class="form-control" maxlength="100" placeholder="Nama sesuai KTP" value="<?= esc($student['nama']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="jenkel" id="epJenkel" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <option value="Laki-Laki" <?= ($student['jenkel'] ?? '') === 'Laki-Laki' ? 'selected' : '' ?>>Laki-Laki</option>
                                <option value="Perempuan"  <?= ($student['jenkel'] ?? '') === 'Perempuan'  ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="no_whatsapp" id="epWhatsapp" class="form-control" maxlength="15" placeholder="08xxxxxxxxxx" value="<?= esc($student['no_whatsapp']) ?>" required inputmode="numeric">
                        </div>
                    </div>
                    <div id="epError" class="alert alert-danger py-2 px-3 mt-3" style="display:none;font-size:.82rem;"></div>
                    <div class="alert alert-info mt-3 mb-0">
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

<?= $this->section('scripts') ?>
<script>
(function () {
    var kelasEl = document.getElementById('epKelas');
    if (kelasEl) {
        kelasEl.addEventListener('input', function () {
            var pos = kelasEl.selectionStart;
            kelasEl.value = kelasEl.value.toUpperCase().replace(/[^A-Z]/g, '');
            kelasEl.setSelectionRange(pos, pos);
        });
    }

    var form = document.getElementById('editProfileForm');
    var errBox = document.getElementById('epError');
    function showError(m) { errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + m; errBox.style.display = 'flex'; }

    if (form) {
        form.addEventListener('submit', function (e) {
            var kelas  = (document.getElementById('epKelas').value || '').trim();
            var jenkel = document.getElementById('epJenkel').value;
            var npm    = (document.getElementById('epNpm').value || '').trim();
            var nama   = (document.getElementById('epNama') ? document.getElementById('epNama').value : '').trim();
            var wa     = (document.getElementById('epWhatsapp') ? document.getElementById('epWhatsapp').value : '').trim();

            if (!/^[A-Z]+$/.test(kelas)) {
                e.preventDefault();
                showError('Kelas wajib diisi dengan huruf besar A-Z saja, tanpa angka atau simbol.');
                return;
            }
            if (jenkel !== 'Laki-Laki' && jenkel !== 'Perempuan') {
                e.preventDefault();
                showError('Jenis kelamin wajib dipilih.');
                return;
            }
            if (!/^\d{9}$/.test(npm)) {
                e.preventDefault();
                showError('NPM harus 9 digit angka.');
                return;
            }
            if (nama && (nama.length < 3 || nama.length > 100)) {
                e.preventDefault();
                showError('Nama wajib diisi 3-100 karakter.');
                return;
            }
            if (wa === '' || wa.length > 15) {
                e.preventDefault();
                showError('No. WhatsApp wajib diisi, maksimal 15 digit.');
                return;
            }
        });
    }
})();
</script>
<?= $this->endSection() ?>
