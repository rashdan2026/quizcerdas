<?php
/** @var array|null $dosen */
$isEdit = ! empty($dosen);
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form action="<?= base_url('/admin/lecturers/save') ?>" method="post">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $dosen['id'] ?>">
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-person-vcard me-2"></i><?= $isEdit ? 'Edit' : 'Tambah' ?> Dosen</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" class="form-control" maxlength="100" value="<?= esc(old('nama', $dosen['nama'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" maxlength="100" value="<?= esc(old('email', $dosen['email'] ?? '')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <?= $isEdit ? '<small class="text-muted">(kosongkan jika tidak diubah)</small>' : '' ?></label>
                    <input type="text" name="password" class="form-control" minlength="6" <?= $isEdit ? '' : 'required' ?>>
                    <?php if (! $isEdit): ?><div class="form-text">Password akan di-hash dengan bcrypt.</div><?php endif; ?>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= old('is_active', $dosen['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Akun Aktif</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body d-flex justify-content-between" style="border-top:1px solid #1F2937;">
            <a href="<?= base_url('/admin/lecturers') ?>" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Batal</a>
            <button type="submit" class="btn btn-admin"><i class="bi bi-save me-1"></i> <?= $isEdit ? 'Simpan' : 'Tambah' ?></button>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
