<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>OpenRouter adalah satu-satunya AI Provider untuk Modul Quiz.
    </div>
    <a href="<?= base_url('/admin/quiz-ai/logs') ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-journal-text me-1"></i> Log AI</a>
</div>

<?php
$p = null;
foreach ($providers as $row) {
    if ($row['provider_code'] === 'openrouter') { $p = $row; break; }
}
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-plug me-1"></i>OpenRouter</span>
                <?php if ($p): ?>
                    <span class="badge bg-<?= (int) $p['is_enabled'] === 1 ? 'success' : 'secondary' ?>"><?= (int) $p['is_enabled'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($p && $p['last_test_at']): ?>
                    <div class="alert alert-<?= str_starts_with((string) $p['last_test_status'], 'success') ? 'success' : 'danger' ?> small py-1 px-2">
                        Uji terakhir: <?= esc((string) $p['last_test_status']) ?> @ <?= esc((string) $p['last_test_at']) ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= base_url('/admin/quiz-ai/providers/save') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="provider_code" value="openrouter">
                    <div class="mb-2">
                        <label class="form-label">Base URL</label>
                        <input type="url" name="base_url" class="form-control form-control-sm" required
                               value="<?= esc($p['base_url'] ?? 'https://openrouter.ai/api/v1') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Model</label>
                        <input type="text" name="model_name" class="form-control form-control-sm" maxlength="100"
                               value="<?= esc($p['model_name'] ?? '') ?>" placeholder="contoh: openai/gpt-4o-mini">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="password" name="api_key" class="form-control form-control-sm" autocomplete="new-password"
                               placeholder="<?= $p && $p['has_key'] ? 'Tersimpan: ' . esc($p['key_masked']) . ' (isi untuk mengganti)' : 'Masukkan API key baru' ?>">
                        <div class="form-text">Key disimpan terenkripsi dan tidak pernah ditampilkan penuh.</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-admin btn-sm"><i class="bi bi-save me-1"></i>Simpan</button>
                        <?php if ($p): ?>
                        <button type="submit" form="toggleForm<?= (int) $p['id'] ?>" class="btn btn-<?= (int) $p['is_enabled'] === 1 ? 'outline-warning' : 'outline-success' ?> btn-sm">
                            <i class="bi bi-power me-1"></i><?= (int) $p['is_enabled'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                        <button type="submit" form="testForm<?= (int) $p['id'] ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-wifi me-1"></i>Uji Koneksi</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (! empty($providers)): foreach ($providers as $p): ?>
    <form id="toggleForm<?= (int) $p['id'] ?>" method="post" action="<?= base_url('/admin/quiz-ai/providers/' . $p['id'] . '/toggle') ?>"><?= csrf_field() ?></form>
    <form id="testForm<?= (int) $p['id'] ?>" method="post" action="<?= base_url('/admin/quiz-ai/providers/' . $p['id'] . '/test') ?>"><?= csrf_field() ?></form>
<?php endforeach; endif; ?>
<?= $this->endSection() ?>
