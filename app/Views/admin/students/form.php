<?php
/** @var array $mhs */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form action="<?= base_url('/admin/students/save') ?>" method="post" id="editStudentForm" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="email" value="<?= esc($mhs['email']) ?>">

    <div class="card">
        <div class="card-header"><i class="bi bi-person-vcard me-2"></i>Edit Mahasiswa</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">NPM <span class="text-danger">*</span></label>
                    <input type="text" name="npm" id="esNpm" class="form-control" maxlength="9" pattern="\d{9}" value="<?= esc(old('npm', $mhs['npm'])) ?>" required inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="esNama" class="form-control" maxlength="100" value="<?= esc(old('nama', $mhs['nama'])) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kelas <span class="text-danger">*</span></label>
                    <input type="text" name="kelas" id="esKelas" class="form-control text-uppercase" maxlength="20" pattern="^[A-Z]+$" value="<?= esc(old('kelas', $mhs['kelas'])) ?>" required style="letter-spacing:1px;font-weight:600;">
                    <small class="text-muted">Huruf besar A-Z, tanpa angka atau simbol.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenkel" id="esJenkel" class="form-select">
                        <option value="">-- Pilih --</option>
                        <option value="Laki-Laki" <?= ($mhs['jenkel'] ?? '') === 'Laki-Laki' ? 'selected' : '' ?>>Laki-Laki</option>
                        <option value="Perempuan"  <?= ($mhs['jenkel'] ?? '') === 'Perempuan'  ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">No WhatsApp</label>
                    <input type="text" name="no_whatsapp" id="esWa" class="form-control" maxlength="15" value="<?= esc(old('no_whatsapp', $mhs['no_whatsapp'])) ?>" inputmode="numeric">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                    <input type="text" name="password" id="esPassword" class="form-control" minlength="6" autocomplete="new-password" placeholder="Min. 6 karakter (opsional)">
                </div>
            </div>
            <div id="esError" class="alert alert-danger py-2 px-3 mt-3" style="display:none;font-size:.82rem;"></div>
        </div>
        <div class="card-body d-flex justify-content-between" style="border-top:1px solid #1F2937;">
            <a href="<?= base_url('/admin/students') ?>" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Batal</a>
            <button type="submit" class="btn btn-admin" id="esSubmitBtn"><i class="bi bi-save me-1"></i> Simpan</button>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var kelasEl = document.getElementById('esKelas');
    if (kelasEl) {
        kelasEl.addEventListener('input', function () {
            var pos = kelasEl.selectionStart;
            kelasEl.value = kelasEl.value.toUpperCase().replace(/[^A-Z]/g, '');
            kelasEl.setSelectionRange(pos, pos);
        });
    }
    var npmEl = document.getElementById('esNpm');
    if (npmEl) {
        npmEl.addEventListener('input', function () {
            var pos = npmEl.selectionStart;
            npmEl.value = npmEl.value.replace(/\D/g, '').slice(0, 9);
            npmEl.setSelectionRange(pos, pos);
        });
    }
    var waEl = document.getElementById('esWa');
    if (waEl) {
        waEl.addEventListener('input', function () {
            var pos = waEl.selectionStart;
            waEl.value = waEl.value.replace(/\D/g, '').slice(0, 15);
            waEl.setSelectionRange(pos, pos);
        });
    }

    var form    = document.getElementById('editStudentForm');
    var errBox  = document.getElementById('esError');
    var submit  = document.getElementById('esSubmitBtn');
    function showError(m) { errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + m; errBox.style.display = 'flex'; }
    function clearError() { errBox.style.display = 'none'; errBox.innerHTML = ''; }

    if (form) {
        form.addEventListener('submit', function (e) {
            clearError();
            var npm  = (document.getElementById('esNpm').value || '').trim();
            var nama = (document.getElementById('esNama').value || '').trim();
            var kelas = (document.getElementById('esKelas').value || '').trim();
            var pwd  = document.getElementById('esPassword').value || '';

            if (!/^\d{9}$/.test(npm)) {
                e.preventDefault();
                showError('NPM harus 9 digit angka.');
                return;
            }
            if (nama.length < 3 || nama.length > 100) {
                e.preventDefault();
                showError('Nama wajib diisi 3-100 karakter.');
                return;
            }
            if (!/^[A-Z]+$/.test(kelas)) {
                e.preventDefault();
                showError('Kelas wajib huruf besar A-Z saja, tanpa angka atau simbol.');
                return;
            }
            if (pwd && pwd.length < 6) {
                e.preventDefault();
                showError('Password baru minimal 6 karakter (atau kosongkan jika tidak diubah).');
                return;
            }
            // OK — biarkan form submit (server akan re-validate)
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
        });
    }
})();
</script>
<?= $this->endSection() ?>
