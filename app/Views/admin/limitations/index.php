<?php
/** @var array $settings */
/** @var array $rows */
/** @var array $auditLogs */
/** @var string $q */
/** @var string $status */
/** @var string $weekStart */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<!-- ===== Default global ===== -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-sliders me-2"></i>Limitasi Default (untuk akun dosen baru)</div>
    <div class="card-body">
        <form action="<?= base_url('/admin/limitations/save-default') ?>" method="post" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-6 col-md-3">
                <label class="form-label">Maks Mata Kuliah <small class="text-muted">per dosen</small></label>
                <input type="number" name="max_subjects" class="form-control" min="1" max="100"
                       value="<?= (int) $settings['subjects'] ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Maks Pertemuan <small class="text-muted">per mata kuliah</small></label>
                <input type="number" name="max_meetings" class="form-control" min="1" max="200"
                       value="<?= (int) $settings['meetings'] ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Maks Generate AI <small class="text-muted">soal/minggu/dosen</small></label>
                <input type="number" name="max_ai" class="form-control" min="0" max="5000"
                       value="<?= (int) $settings['ai'] ?>" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Maks Kuis <small class="text-muted">per dosen</small></label>
                <input type="number" name="max_quizzes" class="form-control" min="1" max="100"
                       value="<?= (int) $settings['quizzes'] ?>" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-admin btn-sm"><i class="bi bi-save me-1"></i>Simpan Limitasi Default</button>
                <small class="text-muted ms-2">Perubahan default tidak mengubah limitasi akun dosen yang sudah terdaftar (snapshot akun).</small>
            </div>
        </form>
    </div>
</div>

<!-- ===== Daftar dosen + limitasi khusus ===== -->
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-people me-2"></i>Dosen &amp; Penggunaan Kuota</span>
        <form method="get" class="d-flex gap-2 flex-wrap">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama/email…" value="<?= esc($q) ?>" style="min-width:180px;">
            <select name="status" class="form-select form-select-sm">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="default" <?= $status === 'default' ? 'selected' : '' ?>>Limitasi Default</option>
                <option value="custom" <?= $status === 'custom' ? 'selected' : '' ?>>Limitasi Khusus</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-light"><i class="bi bi-search"></i></button>
        </form>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="min-width:1100px;">
            <thead>
                <tr>
                    <th>Dosen</th>
                    <th>Mata Kuliah</th>
                    <th>Kuis</th>
                    <th>Generate AI (Minggu Ini)</th>
                    <th>Pertemuan per MK</th>
                    <th>Status</th>
                    <th style="width:280px;">Limitasi Khusus</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <strong><?= esc($r['nama']) ?></strong><br>
                        <small class="text-muted"><?= esc($r['email']) ?></small>
                    </td>
                    <td><span class="badge bg-<?= $r['used_subjects'] >= $r['eff_subjects'] ? 'danger' : 'secondary' ?>"><?= (int) $r['used_subjects'] ?> / <?= (int) $r['eff_subjects'] ?></span></td>
                    <td><span class="badge bg-<?= $r['used_quizzes'] >= $r['eff_quizzes'] ? 'danger' : 'secondary' ?>"><?= (int) $r['used_quizzes'] ?> / <?= (int) $r['eff_quizzes'] ?></span></td>
                    <td><span class="badge bg-<?= $r['used_ai'] >= $r['eff_ai'] ? 'danger' : 'info' ?>"><?= (int) $r['used_ai'] ?> / <?= (int) $r['eff_ai'] ?></span></td>
                    <td><small><?= esc($r['meetings_summary']) ?></small></td>
                    <td>
                        <?= $r['is_custom']
                            ? '<span class="badge bg-warning text-dark">Khusus</span>'
                            : '<span class="badge bg-secondary">Default</span>' ?>
                    </td>
                    <td>
                        <form action="<?= base_url('/admin/limitations/override/save') ?>" method="post" class="d-flex gap-1 flex-wrap align-items-center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="lecturer_id" value="<?= (int) $r['id'] ?>">
                            <input type="number" name="max_subjects" class="form-control form-control-sm" style="width:66px;" title="Maks mata kuliah" min="1" max="100" value="<?= (int) $r['eff_subjects'] ?>" required>
                            <input type="number" name="max_meetings" class="form-control form-control-sm" style="width:66px;" title="Maks pertemuan per MK" min="1" max="200" value="<?= (int) $r['eff_meetings'] ?>" required>
                            <input type="number" name="max_ai" class="form-control form-control-sm" style="width:66px;" title="Maks soal AI/minggu" min="0" max="5000" value="<?= (int) $r['eff_ai'] ?>" required>
                            <input type="number" name="max_quizzes" class="form-control form-control-sm" style="width:66px;" title="Maks kuis" min="1" max="100" value="<?= (int) $r['eff_quizzes'] ?>" required>
                            <button type="submit" class="btn btn-sm btn-admin" title="Simpan limitasi khusus"><i class="bi bi-save"></i></button>
                        </form>
                        <?php if ($r['is_custom']): ?>
                        <form action="<?= base_url('/admin/limitations/override/' . (int) $r['id'] . '/remove') ?>" method="post" class="mt-1"
                              onsubmit="return confirm('Hapus limitasi khusus dosen ini dan kembalikan ke limitasi akun/default?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset ke Default</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada dosen yang cocok.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <small class="text-muted d-block mt-2">Minggu berjalan dimulai: <?= esc(date('d M Y H:i', strtotime($weekStart))) ?> WIB (reset Senin 00.00 WIB).</small>
    </div>
</div>

<!-- ===== Audit log ===== -->
<div class="card">
    <div class="card-header"><i class="bi bi-journal-text me-2"></i>Audit Log Perubahan Limitasi (20 terakhir)</div>
    <div class="card-body table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Dosen</th><th>Nilai Lama</th><th>Nilai Baru</th></tr></thead>
            <tbody>
            <?php foreach ($auditLogs as $log): ?>
                <tr>
                    <td class="small"><?= esc($log['created_at']) ?></td>
                    <td class="small"><?= esc($log['admin_email'] ?? '-') ?></td>
                    <td class="small"><?= esc($log['action']) ?></td>
                    <td class="small"><?= $log['lecturer_id'] ? '#' . (int) $log['lecturer_id'] : '—' ?></td>
                    <td class="small"><code><?= esc($log['old_value'] ?? '-') ?></code></td>
                    <td class="small"><code><?= esc($log['new_value'] ?? '-') ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($auditLogs)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada perubahan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
