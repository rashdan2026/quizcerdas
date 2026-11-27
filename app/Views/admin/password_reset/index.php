<?php
/** @var array $settings */
/** @var array $audit */
?>
<?= $this->extend('layouts/admin') ?>
<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-shield-lock me-2"></i>Reset Password &amp; Pembatasan Email</div>
    <div class="card-body">
        <form action="<?= base_url('/admin/password-reset-settings/save') ?>" method="post" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Maks. permintaan</label>
                <input type="number" name="max_requests" class="form-control" min="1" max="10" value="<?= (int) $settings['max_requests'] ?>" required>
                <div class="form-text">per 24 jam / email</div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Jeda ke-2 (menit)</label>
                <input type="number" name="delay_2_minutes" class="form-control" min="1" max="120" value="<?= (int) $settings['delay_2_minutes'] ?>" required>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Jeda ke-3 (menit)</label>
                <input type="number" name="delay_3_minutes" class="form-control" min="1" max="120" value="<?= (int) $settings['delay_3_minutes'] ?>" required>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Jeda ke-4 (menit)</label>
                <input type="number" name="delay_4_minutes" class="form-control" min="1" max="120" value="<?= (int) $settings['delay_4_minutes'] ?>" required>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Masa berlaku link</label>
                <input type="number" name="token_ttl_minutes" class="form-control" min="5" max="60" value="<?= (int) $settings['token_ttl_minutes'] ?>" required>
                <div class="form-text">menit (token baru)</div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label class="form-label">Limit per IP</label>
                <input type="number" name="ip_max_per_hour" class="form-control" min="5" max="1000" value="<?= (int) $settings['ip_max_per_hour'] ?>" required>
                <div class="form-text">permintaan/jam</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-admin btn-sm"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>
                <small class="text-muted ms-2">Perubahan tidak membatalkan token yang sudah aktif; token tetap mengikuti masa berlaku saat diterbitkan.</small>
            </div>
            <div class="col-12">
                <div class="alert alert-info py-2 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Reset password memakai token sekali pakai (hash di database), berlaku <?= (int) $settings['token_ttl_minutes'] ?> menit.
                    Password lama <strong>tidak berubah</strong> sampai pemilik akun menyelesaikan proses reset.
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-journal-text me-2"></i>Audit Reset Password &amp; Perubahan Pengaturan</div>
    <div class="card-body table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Event</th><th>Peran</th><th>Akun/Ref</th><th>IP</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $a): ?>
                <tr>
                    <td class="small"><?= esc($a['created_at']) ?></td>
                    <td class="small"><?= esc($a['event']) ?></td>
                    <td class="small"><?= esc($a['role'] ?? '-') ?></td>
                    <td class="small"><?= esc($a['email'] ?? $a['user_ref'] ?? '-') ?></td>
                    <td class="small"><?= esc($a['ip'] ?? '-') ?></td>
                    <td class="small"><?= esc($a['status'] ?? '-') ?></td>
                    <td class="small"><code style="font-size:.7rem;"><?= esc(mb_substr((string) ($a['detail'] ?? '-'), 0, 140)) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($audit)): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">Belum ada catatan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
