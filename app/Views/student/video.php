<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= esc($meeting['judul']) ?></h4>
    <a href="<?= base_url('/student/scan') ?>" class="btn btn-outline-secondary btn-sm">Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="ratio ratio-16x9">
            <iframe
                src="<?= esc($meeting['link_video']) ?>"
                referrerpolicy="no-referrer"
                sandbox="allow-scripts allow-same-origin allow-presentation"
                allowfullscreen
            ></iframe>
        </div>
        <small class="text-muted d-block mt-2">Video hanya dapat diakses dari sesi login aplikasi.</small>
    </div>
</div>
<?= $this->endSection() ?>
