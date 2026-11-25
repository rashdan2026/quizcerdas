<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-lightbulb me-2"></i>Kuis</h4>
        <p class="text-muted small mb-0 mt-1">Kelola kuis dan bank soal Anda</p>
    </div>
    <a href="<?= base_url('/lecturer/quiz/create') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Buat Kuis
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Judul</th><th>Mata Kuliah</th><th class="text-center">Status</th>
                <th class="text-center">Jumlah Soal</th><th class="text-center">Skor Maks</th><th class="text-center">Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($quizzes as $q): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= esc($q['title']) ?></div>
                        <code class="small" style="color:#4F46E5;">Kode Kuis: <?= esc($q['access_code']) ?></code>
                    </td>
                    <td><span class="badge" style="background:#EEF2FF;color:#3730A3;"><?= esc($q['kode_mk']) ?></span> <span class="small text-muted"><?= esc($q['nama_mk']) ?></span></td>
                    <td class="text-center">
                        <?php $colors = ['draft' => 'secondary', 'scheduled' => 'info', 'active' => 'success', 'closed' => 'warning', 'archived' => 'dark']; ?>
                        <span class="badge bg-<?= $colors[$q['status']] ?? 'secondary' ?> rounded-pill"><?= esc(ucfirst($q['status'])) ?></span>
                    </td>
                    <td class="text-center"><?= (int) $q['question_count'] ?></td>
                    <td class="text-center"><?= (int) $q['question_count'] * (int) $q['correct_points'] ?></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-primary" href="<?= base_url('/lecturer/quiz/' . $q['id']) ?>"><i class="bi bi-gear"></i> Kelola</a>
                            <a class="btn btn-info text-white" href="<?= base_url('/lecturer/quiz/' . $q['id'] . '/nilai') ?>"><i class="bi bi-clipboard-data me-1"></i> Nilai</a>
                            <a class="btn btn-success" href="<?= base_url('/lecturer/quiz/' . $q['id'] . '/nilai/export') ?>"><i class="bi bi-file-earmark-excel"></i></a>
                            <a class="btn btn-warning text-white" href="<?= base_url('/lecturer/quiz/' . $q['id'] . '/analytics') ?>"><i class="bi bi-bar-chart"></i></a>
                            <form action="<?= base_url('/lecturer/quiz/' . $q['id'] . '/delete') ?>" method="post" onsubmit="return confirm('Hapus kuis ini?')">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada kuis. <a href="<?= base_url('/lecturer/quiz/create') ?>">Buat kuis pertama</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
