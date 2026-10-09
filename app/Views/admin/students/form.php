<?php
/** @var array $mhs */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<form action="<?= base_url('/admin/students/save') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="email" value="<?= esc($mhs['email']) ?>">

    <div class="card">
        <div class="card-header"><i class="bi bi-person-vcard me-2"></i>Edit Mahasiswa</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">NPM</label>
                    <input type="text" name="npm" class="form-control" maxlength="9" value="<?= esc(old('npm', $mhs['npm'])) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" class="form-control" maxlength="100" value="<?= esc(old('nama', $mhs['nama'])) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kelas</label>
                    <input type="text" name="kelas" class="form-control" maxlength="20" value="<?= esc(old('kelas', $mhs['kelas'])) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">No WhatsApp</label>
                    <input type="text" name="no_whatsapp" class="form-control" maxlength="15" value="<?= esc(old('no_whatsapp', $mhs['no_whatsapp'])) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password Baru <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                    <input type="text" name="password" class="form-control" minlength="6">
                </div>
            </div>
        </div>
        <div class="card-body d-flex justify-content-between" style="border-top:1px solid #1F2937;">
            <a href="<?= base_url('/admin/students') ?>" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Batal</a>
            <button type="submit" class="btn btn-admin"><i class="bi bi-save me-1"></i> Simpan</button>
        </div>
    </div>
</form>

<?= $this->endSection() ?>
