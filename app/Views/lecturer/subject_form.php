<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Tambah Matakuliah</h5>
                <form action="<?= base_url('/lecturer/subjects/create') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Kode MK</label>
                        <input type="text" name="kode_mk" class="form-control" value="<?= esc(old('kode_mk')) ?>" maxlength="10" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama MK</label>
                        <input type="text" name="nama_mk" class="form-control" value="<?= esc(old('nama_mk')) ?>" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                        <label class="form-check-label">Aktif</label>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('/lecturer/subjects') ?>" class="btn btn-outline-secondary">Kembali</a>
                        <button class="btn btn-primary" type="submit">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
