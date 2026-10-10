<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-layout-text-window me-2"></i>Dashboard Mahasiswa</h4>
        <p class="text-muted small mb-0 mt-1">Selamat datang, <strong><?= esc(session('user_name')) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($canEditProfile): ?>
            <a href="<?= base_url('/student/dashboard/edit-profile') ?>" class="btn btn-outline-success d-flex align-items-center gap-2">
                <i class="bi bi-pencil"></i> Edit Profil
            </a>
        <?php else: ?>
            <span class="btn btn-outline-secondary d-flex align-items-center gap-2" title="Edit profil hanya bisa dilakukan 1x per minggu">
                <i class="bi bi-lock"></i> Edit Profil (1x/minggu)
            </span>
        <?php endif; ?>
        <a href="<?= base_url('/student/scan') ?>" class="btn btn-primary d-flex align-items-center gap-2">
            <i class="bi bi-qr-code-scan"></i> Scan Absensi
        </a>
    </div>
</div>

<!-- Profile Info -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;background:linear-gradient(135deg,#4F46E5,#7C3AED);">
                        <i class="bi bi-person-fill text-white fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-600"><?= esc($student['nama']) ?></h6>
                        <small class="text-muted"><?= esc($student['email']) ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-8 mt-3 mt-md-0">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="fw-600 text-primary"><?= esc($student['npm']) ?></div>
                        <small class="text-muted">NPM</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-600 text-primary"><?= esc($student['kelas'] ?? '-') ?></div>
                        <small class="text-muted">Kelas</small>
                    </div>
                    <div class="col-4">
                        <div class="fw-600 text-primary"><?= esc($student['no_whatsapp'] ?? '-') ?></div>
                        <small class="text-muted">WhatsApp</small>
                    </div>
                </div>
            </div>
        </div>
        <?php if (!empty($student['profile_updated_at'])): ?>
            <div class="mt-3 pt-3 border-top">
                <small class="text-muted">
                    <i class="bi bi-clock me-1"></i>
                    Terakhir edit profil: <?= date('d M Y, H:i', strtotime($student['profile_updated_at'])) ?>
                    &bull; Dapat diedit lagi sejak: <?= date('d M Y, H:i', strtotime($student['profile_updated_at'] . ' +1 week')) ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="stat-icon" style="background:#EEF2FF;">
                    <i class="bi bi-calendar-check" style="color:#4F46E5;"></i>
                </div>
                <div>
                    <div class="fw-700 fs-4" style="color:#1E293B;"><?= count($rows) ?></div>
                    <div class="text-muted" style="font-size:.78rem;">Total Absensi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body d-flex align-items-center gap-3 p-3">
                <div class="stat-icon" style="background:#F0FDF4;">
                    <i class="bi bi-check-circle" style="color:#10B981;"></i>
                </div>
                <div>
                    <div class="fw-700 fs-4" style="color:#1E293B;"><?= count(array_unique(array_column($rows, 'kode_mk'))) ?></div>
                    <div class="text-muted" style="font-size:.78rem;">Matakuliah</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Riwayat -->
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Absensi</span>
        <span class="badge bg-primary rounded-pill"><?= count($rows) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Matakuliah</th>
                <th>Pertemuan</th>
                <th>Waktu Absen</th>
                <th class="text-center">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <span class="badge mb-1" style="background:#EEF2FF;color:#4F46E5;"><?= esc($row['kode_mk']) ?></span>
                        <div class="text-muted small"><?= esc($row['nama_mk']) ?></div>
                    </td>
                    <td>
                        <span class="badge bg-secondary rounded-pill me-1">#<?= esc($row['pertemuan_ke']) ?></span>
                        <span class="text-muted small"><?= esc($row['judul']) ?></span>
                    </td>
                    <td><small class="text-muted"><i class="bi bi-clock me-1"></i><?= esc($row['waktu_absen']) ?></small></td>
                    <td class="text-center">
                        <a href="<?= base_url('/student/detail/' . $row['meeting_id']) ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>Detail
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5">
                        <i class="bi bi-inbox display-6 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-2">Belum ada riwayat absensi.</p>
                        <a href="<?= base_url('/student/scan') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-qr-code-scan me-1"></i>Scan Sekarang
                        </a>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! empty($dashboardAd)): ?>
<?= view('partials/ad_modal', ['ad' => $dashboardAd, 'lockSeconds' => $adLockSec, 'placement' => 'dashboard']) ?>
<?php endif; ?>

<?php if (! empty($needsProfileComplete)): ?>
<?= view('partials/profile_modal', ['student' => $student]) ?>
<script>
(function () {
    var adModalEl     = document.getElementById('mediaModal');
    var profileModalEl = document.getElementById('profileCompleteModal');
    if (!profileModalEl) { return; }

    function showProfileModal() {
        var m = bootstrap.Modal.getOrCreateInstance(profileModalEl);
        m.show();
    }

    if (adModalEl) {
        adModalEl.addEventListener('hidden.bs.modal', function () {
            showProfileModal();
        }, { once: true });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            showProfileModal();
        });
    }

    var form        = document.getElementById('profileCompleteForm');
    var npmEl       = document.getElementById('pcNpm');
    var namaEl      = document.getElementById('pcNama');
    var kelasEl     = document.getElementById('pcKelas');
    var jenkelEl    = document.getElementById('pcJenkel');
    var whatsappEl  = document.getElementById('pcWhatsapp');
    var errBox      = document.getElementById('pcError');
    var submitBtn   = document.getElementById('pcSubmitBtn');

    function showError(msg) {
        if (!errBox) { return; }
        errBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>' + msg;
        errBox.style.display = 'flex';
    }

    function clearError() {
        if (!errBox) { return; }
        errBox.style.display = 'none';
        errBox.innerHTML = '';
    }

    if (kelasEl) {
        kelasEl.addEventListener('input', function () {
            var pos = kelasEl.selectionStart;
            kelasEl.value = kelasEl.value.toUpperCase().replace(/[^A-Z]/g, '');
            kelasEl.setSelectionRange(pos, pos);
            clearError();
        });
    }
    if (npmEl) {
        npmEl.addEventListener('input', function () {
            var pos = npmEl.selectionStart;
            npmEl.value = npmEl.value.replace(/\D/g, '').slice(0, 9);
            npmEl.setSelectionRange(pos, pos);
            clearError();
        });
    }
    if (whatsappEl) {
        whatsappEl.addEventListener('input', function () {
            var pos = whatsappEl.selectionStart;
            whatsappEl.value = whatsappEl.value.replace(/\D/g, '').slice(0, 15);
            whatsappEl.setSelectionRange(pos, pos);
            clearError();
        });
    }
    if (jenkelEl) {
        jenkelEl.addEventListener('change', clearError);
    }
    if (namaEl) {
        namaEl.addEventListener('input', clearError);
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearError();

            var npm       = (npmEl.value      || '').trim();
            var nama      = (namaEl.value     || '').trim();
            var kelas     = (kelasEl.value    || '').trim();
            var jenkel    = jenkelEl.value;
            var whatsapp  = (whatsappEl.value || '').trim();

            if (!/^\d{9}$/.test(npm)) {
                showError('NPM wajib diisi 9 digit angka.');
                npmEl.focus();
                return;
            }
            if (nama.length < 3 || nama.length > 100) {
                showError('Nama wajib diisi 3-100 karakter.');
                namaEl.focus();
                return;
            }
            if (!/^[A-Z]+$/.test(kelas)) {
                showError('Kelas wajib diisi dengan huruf besar A-Z saja, tanpa angka atau simbol.');
                kelasEl.focus();
                return;
            }
            if (jenkel !== 'Laki-Laki' && jenkel !== 'Perempuan') {
                showError('Jenis kelamin wajib dipilih.');
                jenkelEl.focus();
                return;
            }
            if (whatsapp === '' || whatsapp.length > 15) {
                showError('No. WhatsApp wajib diisi, maksimal 15 digit.');
                whatsappEl.focus();
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';

            var fd = new FormData(form);

            fetch('<?= base_url('/student/dashboard/update-profile') ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (fd.get('<?= csrf_token() ?>') || '')
                },
                body: fd
            }).then(function (r) {
                return r.json().then(function (j) { return { status: r.status, body: j }; });
            }).then(function (resp) {
                if (resp.status === 200 && resp.body && resp.body.ok) {
                    var m = bootstrap.Modal.getInstance(profileModalEl);
                    if (m) { m.hide(); }
                    location.reload();
                } else {
                    showError((resp.body && resp.body.message) ? resp.body.message : 'Gagal menyimpan profil.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Simpan & Lanjutkan';
                }
            }).catch(function () {
                showError('Terjadi kesalahan jaringan. Silakan coba lagi.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Simpan & Lanjutkan';
            });
        });
    }
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
