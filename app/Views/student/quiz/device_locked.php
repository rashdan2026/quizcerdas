<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="card text-center">
    <div class="card-body py-5">
        <i class="bi bi-shield-lock display-4 text-danger mb-3"></i>
        <h4 class="fw-bold">Attempt Terikat pada Perangkat Lain</h4>
        <p class="text-muted mb-0">Satu kode kuis hanya dapat dijalankan pada satu perangkat aktif.
        Pemulihan akses hanya dapat dilakukan oleh dosen/administrator dan akan tercatat.</p>
        <a href="<?= base_url('/student/quiz') ?>" class="btn btn-outline-secondary mt-3">Kembali</a>
    </div>
</div>
<?= $this->endSection() ?>
