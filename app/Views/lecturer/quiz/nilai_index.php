<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-clipboard-data me-2"></i>Nilai Mahasiswa</h4>
        <p class="text-muted small mb-0 mt-1">Pilih kuis untuk melihat nilai mahasiswanya</p>
    </div>
    <a href="<?= base_url('/lecturer/quiz') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-collection me-1"></i>Pengelolaan Kuis</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th>Kuis</th><th>Mata Kuliah</th><th>Kode Kuis</th>
                <th class="text-center">Jumlah Soal</th><th class="text-center">Sudah Dikerjakan</th><th class="text-center">Aksi</th>
            </tr></thead>
            <tbody>
            <?php foreach ($quizzes as $q): ?>
                <tr>
                    <td class="fw-semibold"><?= esc($q['title']) ?></td>
                    <td><span class="badge" style="background:#EEF2FF;color:#3730A3;"><?= esc($q['kode_mk']) ?></span></td>
                    <td><code class="fw-bold" style="color:#4F46E5;"><?= esc($q['access_code']) ?></code></td>
                    <td class="text-center"><?= (int) $q['question_count'] ?></td>
                    <td class="text-center"><?= (int) $q['graded_count'] ?></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-primary" href="<?= base_url('/lecturer/quiz/' . $q['id'] . '/nilai') ?>"><i class="bi bi-clipboard-data me-1"></i>Nilai</a>
                            <a class="btn btn-outline-success" href="<?= base_url('/lecturer/quiz/' . $q['id'] . '/nilai/export') ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada kuis.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
