<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-layout-text-window me-2"></i>Dashboard Mahasiswa</h4>
        <p class="text-muted small mb-0 mt-1">Selamat datang, <strong><?= esc(session('user_name')) ?></strong></p>
    </div>
    <a href="<?= base_url('/student/scan') ?>" class="btn btn-primary d-flex align-items-center gap-2">
        <i class="bi bi-qr-code-scan"></i> Scan Absensi
    </a>
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
