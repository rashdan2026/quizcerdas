<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-book me-2"></i>Data Matakuliah</h4>
        <p class="text-muted small mb-0 mt-1">Kelola matakuliah yang Anda ampu</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-collection"></i> Pertemuan
        </a>
        <a href="<?= base_url('/lecturer/report') ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-bar-chart"></i> Rekap
        </a>
        <?php $subjectLimitReached = $usage['used']['subjects'] >= $usage['limits']['subjects']; ?>
        <?php if (! $subjectLimitReached): ?>
        <a href="<?= base_url('/lecturer/subjects/create') ?>" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i> Tambah
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Ringkasan penggunaan resource (v10.0) -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-speedometer2 me-2"></i>Penggunaan Resource</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-600"><i class="bi bi-book me-1"></i>Mata Kuliah</span>
                    <span class="small text-muted"><?= (int) $usage['used']['subjects'] ?> / <?= (int) $usage['limits']['subjects'] ?></span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar <?= $usage['remaining']['subjects'] === 0 ? 'bg-danger' : '' ?>" style="width: <?= min(100, round($usage['used']['subjects'] / max(1, $usage['limits']['subjects']) * 100)) ?>%"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-600"><i class="bi bi-lightbulb me-1"></i>Kuis</span>
                    <span class="small text-muted"><?= (int) $usage['used']['quizzes'] ?> / <?= (int) $usage['limits']['quizzes'] ?></span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar <?= $usage['remaining']['quizzes'] === 0 ? 'bg-danger' : '' ?>" style="width: <?= min(100, round($usage['used']['quizzes'] / max(1, $usage['limits']['quizzes']) * 100)) ?>%"></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-600"><i class="bi bi-cpu me-1"></i>Generate AI Minggu Ini</span>
                    <span class="small text-muted"><?= (int) $usage['used']['ai'] ?> / <?= (int) $usage['limits']['ai'] ?> soal</span>
                </div>
                <div class="progress" style="height:8px;">
                    <div class="progress-bar <?= $usage['remaining']['ai'] === 0 ? 'bg-danger' : ($usage['remaining']['ai'] <= ($usage['limits']['ai'] * 0.2) ? 'bg-warning' : '') ?>" style="width: <?= min(100, round($usage['used']['ai'] / max(1, $usage['limits']['ai']) * 100)) ?>%"></div>
                </div>
                <small class="text-muted">Sisa: <?= (int) $usage['remaining']['ai'] ?> soal · reset Senin 00.00 WIB</small>
            </div>
        </div>
        <?php if ($subjectLimitReached): ?>
            <div class="alert alert-warning py-2 mt-3 mb-0 small">
                Anda telah mencapai batas maksimal <?= (int) $usage['limits']['subjects'] ?> mata kuliah. Silakan hubungi Admin apabila membutuhkan penambahan kuota.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Kode MK</th>
                <th>Nama Matakuliah</th>
                <th class="text-center">Pertemuan</th>
                <th class="text-center">Status</th>
                <th class="text-center">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($subjects as $subject): ?>
                <?php $mCount = $meetingCounts[(int) $subject['id']] ?? 0; $mFull = $mCount >= $usage['limits']['meetings']; ?>
                <tr>
                    <td><code class="fw-600" style="color:#4F46E5;"><?= esc($subject['kode_mk']) ?></code></td>
                    <td><?= esc($subject['nama_mk']) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $mFull ? 'bg-danger' : 'bg-info' ?> rounded-pill"><?= (int) $mCount ?> / <?= (int) $usage['limits']['meetings'] ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($subject['is_active']): ?>
                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle me-1"></i>Aktif</span>
                        <?php else: ?>
                            <span class="badge rounded-pill px-3" style="background:#F1F5F9;color:#64748B;"><i class="bi bi-pause-circle me-1"></i>Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-info text-white" onclick="exportSubject(<?= $subject['id'] ?>, '<?= esc($subject['kode_mk'], 'js') ?>', '<?= esc($subject['nama_mk'], 'js') ?>')" title="Export XLS">
                                <i class="bi bi-file-earmark-excel"></i> Export
                            </button>
                            <form action="<?= base_url('/lecturer/subjects/' . $subject['id'] . '/toggle') ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm <?= $subject['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>" type="submit">
                                    <i class="bi <?= $subject['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i>
                                    <?= $subject['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($subjects)): ?>
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <i class="bi bi-book display-6 text-muted d-block mb-2"></i>
                        <p class="text-muted mb-2">Belum ada matakuliah.</p>
                        <a href="<?= base_url('/lecturer/subjects/create') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Matakuliah
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
<script>
    function exportSubject(subjectId, kodeMk, namaMk) {
        // Show loading indicator
        const btn = event.target.closest('button');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Exporting...';
        btn.disabled = true;
        
        // Trigger download
        const url = '<?= base_url('/lecturer/subjects/export') ?>/' + subjectId;
        window.location.href = url;
        
        // Restore button after 2 seconds
        setTimeout(() => {
            btn.innerHTML = originalContent;
            btn.disabled = false;
        }, 2000);
    }
</script>
<?= $this->endSection() ?>
