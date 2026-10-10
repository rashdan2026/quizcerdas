<?php
/** @var array|null $ad */
/** @var int $maxFileMb */
$isEdit = ! empty($ad);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form action="<?= base_url('/admin/ads/save') ?>" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $ad['id'] ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Detail Iklan</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input type="text" name="title" class="form-control" maxlength="150" value="<?= esc(old('title', $ad['title'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target URL (affiliate / Shopee)</label>
                        <input type="url" name="target_url" class="form-control" maxlength="500" value="<?= esc(old('target_url', $ad['target_url'] ?? '')) ?>" placeholder="https://shopee.co.id/..." required>
                        <div class="form-text">URL akan dibuka di tab baru saat banner diklik.</div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= old('is_active', $ad['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Target Gender</label>
                            <select name="target_gender" class="form-select">
                                <?php
                                $currentGender = old('target_gender', $ad['target_gender'] ?? 'Both');
                                $genderOptions = [
                                    'Both'      => 'Semua (Both)',
                                    'Laki-Laki' => 'Laki-Laki saja',
                                    'Perempuan' => 'Perempuan saja',
                                ];
                                foreach ($genderOptions as $val => $label):
                                ?>
                                    <option value="<?= esc($val) ?>" <?= $currentGender === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">User tanpa gender (belum lengkap profil) hanya melihat iklan <code>Both</code>.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority Score</label>
                            <input type="number" name="priority_score" class="form-control" min="0" step="1"
                                   value="<?= esc(old('priority_score', $ad['priority_score'] ?? 0)) ?>">
                            <div class="form-text">Bobot untuk weighted-random. Berkurang 1 setiap tampil. Jika semua kandidat score 0 → random biasa.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-image me-2"></i>File GIF</div>
                <div class="card-body">
                    <?php if ($isEdit && ! empty($ad['file_name'])): ?>
                        <div class="mb-2 text-center">
                            <img src="<?= base_url('/media/gfx/' . $ad['file_name']) ?>" alt="" style="max-width:100%;max-height:180px;border-radius:8px;border:1px solid #1F2937;">
                        </div>
                        <div class="small text-muted mb-3">File saat ini: <code><?= esc($ad['file_name']) ?></code> (<?= number_format(($ad['file_size'] ?? 0) / 1024, 1) ?> KB)</div>
                    <?php endif; ?>
                    <input type="file" name="gif_file" class="form-control" accept="image/gif">
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle me-1"></i>Format: GIF • Maks <?= (int) $maxFileMb ?> MB • MIME harus <code>image/gif</code>
                        <br><i class="bi bi-aspect-ratio me-1"></i>Rekomendasi ukuran: <strong>800 × 1000 pixel</strong> (portrait, pas di modal popup tanpa crop)
                        <?php if (! $isEdit): ?><br><strong>Wajib diisi untuk iklan baru.</strong><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="small text-muted">
                        <div><i class="bi bi-clock me-1"></i>Modal lock: <strong><?= (int) $lockSeconds ?> detik</strong></div>
                        <div><i class="bi bi-calendar-day me-1"></i>Maks tampil: <strong><?= (int) $dailyMax ?>× per hari per user</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <a href="<?= base_url('/admin/ads') ?>" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Batal</a>
        <button type="submit" class="btn btn-admin"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Iklan' ?></button>
    </div>
</form>

<?= $this->endSection() ?>
