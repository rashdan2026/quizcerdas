<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<h4 class="page-title mb-3"><i class="bi bi-plus-circle me-2"></i>Buat Kuis Baru</h4>

<?php if (! empty($usage)): ?>
    <div class="alert <?= $usage['remaining']['quizzes'] === 0 ? 'alert-danger' : 'alert-info' ?> py-2 small">
        <i class="bi bi-speedometer2 me-1"></i>Kuis yang telah dibuat: <?= (int) $usage['used']['quizzes'] ?> dari <?= (int) $usage['limits']['quizzes'] ?>.
        <?php if ($usage['remaining']['quizzes'] === 0): ?>
            Anda telah mencapai batas maksimal pembuatan kuis. Silakan hubungi Admin apabila membutuhkan penambahan kuota.
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card"><div class="card-body">
    <?php if (! empty($usage) && $usage['remaining']['quizzes'] === 0): ?>
        <button type="submit" class="btn btn-primary" disabled>Tidak dapat membuat kuis — kuota habis</button>
    <?php endif; ?>
    <form method="post" action="<?= base_url('/lecturer/quiz/create') ?>" <?= (! empty($usage) && $usage['remaining']['quizzes'] === 0) ? 'hidden' : '' ?>>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Mata Kuliah <span class="text-danger">*</span></label>
            <select name="subject_id" class="form-select" required>
                <option value="">-- Pilih mata kuliah milik Anda --</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= esc($s['kode_mk']) ?> - <?= esc($s['nama_mk']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Hanya mata kuliah yang Anda ampu ditampilkan.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Judul Kuis <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" maxlength="255" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Deskripsi (opsional)</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Poin Jawaban Benar (1-5)</label>
                <input type="number" name="correct_points" class="form-control" min="1" max="5" value="5">
            </div>
            <div class="col-md-6">
                <label class="form-label">Penalti Jawaban Salah (0-5)</label>
                <input type="number" name="wrong_penalty" class="form-control" min="0" max="5" value="1">
                <div class="form-text">Jawaban "Tidak tahu" (E) dan tidak dijawab bernilai 0.</div>
            </div>
        </div>

        <h6 class="mt-2 mb-2"><i class="bi bi-calendar-event me-1"></i>Pelaksanaan Kuis</h6>
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                <input type="datetime-local" name="starts_at" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Durasi Pengerjaan (menit)</label>
                <input type="number" name="duration_minutes" class="form-control" min="1" max="1440" value="60">
                <div class="form-text">Default 60 menit. Waktu berakhir dihitung otomatis: Waktu Mulai + Durasi.</div>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Status Kuis</label>
            <select name="status" class="form-select">
                <option value="draft">Draft</option>
                <option value="active">Aktif</option>
            </select>
            <div class="form-text">Kode Kuis dibuat otomatis oleh sistem saat kuis disimpan, dan tetap sama saat Anda mengedit pengaturan.</div>
        </div>

        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Buat Kuis</button>
        <a href="<?= base_url('/lecturer/quiz') ?>" class="btn btn-outline-secondary">Batal</a>
    </form>
</div></div>
<?= $this->endSection() ?>
