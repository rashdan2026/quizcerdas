<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h4 class="page-title mb-3"><i class="bi bi-lightbulb me-2"></i><?= esc($quiz['title']) ?></h4>

<div class="card"><div class="card-body">
    <p class="text-muted"><?= esc($quiz['description'] ?: '-') ?></p>
    <ul class="small mb-3">
        <li>Kode Kuis: <strong><?= esc($code['code']) ?></strong></li>
        <li>Mulai: <?= esc((string) $quiz['starts_at']) ?></li>
        <li>Berakhir: <?= esc((string) $quiz['ends_at']) ?></li>
        <li>Durasi pengerjaan: <?= (int) $quiz['duration_minutes'] ?> menit (dihitung dari waktu server)</li>
        <li>Poin benar: +<?= (int) $quiz['correct_points'] ?>, penalti salah: -<?= (int) $quiz['wrong_penalty'] ?>, "Tidak tahu"/tidak dijawab: 0</li>
        <li>Skor maksimum: <?= (int) $quiz['correct_points'] ?> poin per soal</li>
        <li><strong>Satu kode hanya untuk satu kali percobaan.</strong> Untuk mengikuti kuis ini lagi, gunakan kode baru dari dosen — hasil percobaan sebelumnya tetap tersimpan.</li>
        <li><strong>Satu kode hanya boleh aktif pada satu perangkat.</strong> Jika keluar saat ujian, ujian tetap dianggap berjalan.</li>
    </ul>
    <form method="post" action="<?= base_url('/student/quiz/' . $quiz['id'] . '/start') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-lg"><i class="bi bi-play-fill me-1"></i> <?= $active ? 'Lanjutkan Attempt Aktif' : 'Mulai Percobaan #' . $attemptNo ?></button>
    </form>
    <a href="<?= base_url('/student/quiz') ?>" class="btn btn-outline-secondary btn-sm mt-2">Kembali</a>
</div></div>
<?= $this->endSection() ?>
