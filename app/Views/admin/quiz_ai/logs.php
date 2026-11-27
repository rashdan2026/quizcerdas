<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>Menampilkan 200 aktivitas AI terakhir (terbaru di atas).
    </div>
    <a href="<?= base_url('/admin/quiz-ai') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr>
            <th>Waktu</th><th class="text-center">Batch</th><th class="text-center">Quiz</th><th class="text-center">Dosen</th>
            <th>Provider</th><th>Model</th><th class="text-center">Attempt</th><th class="text-center">Fallback</th>
            <th class="text-center">Status</th><th>Error</th><th class="text-center">HTTP</th><th class="text-center">Latensi</th>
            <th class="text-center">Diminta</th><th class="text-center">Terima</th><th class="text-center">Duplikat</th>
            <th class="text-center">Tokens (in/out)</th>
        </tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td class="small"><?= esc((string) $l['created_at']) ?></td>
                <td class="text-center"><?= $l['batch_id'] ? (int) $l['batch_id'] : '-' ?></td>
                <td class="text-center"><?= $l['quiz_id'] ? (int) $l['quiz_id'] : '-' ?></td>
                <td class="text-center"><?= $l['lecturer_id'] ? (int) $l['lecturer_id'] : '-' ?></td>
                <td class="small"><?= esc((string) ($l['provider_snapshot'] ?? '-')) ?></td>
                <td class="small"><?= esc((string) ($l['model_snapshot'] ?? '-')) ?></td>
                <td class="text-center"><?= (int) $l['attempt_number'] ?></td>
                <td class="text-center"><?= (int) $l['is_fallback'] === 1 ? '<span class="badge bg-warning text-dark">Ya</span>' : 'No' ?></td>
                <td class="text-center"><span class="badge bg-<?= $l['status'] === 'success' ? 'success' : 'danger' ?>"><?= esc($l['status']) ?></span></td>
                <td class="small"><?= esc((string) ($l['error_category'] ?? '-')) ?></td>
                <td class="text-center"><?= $l['http_status'] ?? '-' ?></td>
                <td class="text-center"><?= (int) $l['latency_ms'] ?> ms</td>
                <td class="text-center"><?= (int) $l['requested_count'] ?></td>
                <td class="text-center"><?= (int) $l['received_count'] ?></td>
                <td class="text-center"><?= (int) $l['duplicate_count'] ?></td>
                <td class="text-center small"><?= (int) $l['token_prompt'] ?> / <?= (int) $l['token_completion'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr><td colspan="16" class="text-center text-muted py-4">Belum ada log.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>
<?= $this->endSection() ?>
