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
