<?php
$reason = $reason ?? 'invalid';
$title  = $reason === 'expired' ? 'Link Reset Password Kedaluwarsa' : 'Link Reset Password Tidak Berlaku';
$text   = $reason === 'expired'
    ? 'Link pengaturan ulang password Anda sudah tidak berlaku. Silakan kembali ke halaman Lupa Password untuk meminta link yang baru.'
    : 'Link pengaturan ulang password ini sudah digunakan atau tidak lagi berlaku. Silakan kembali ke halaman Lupa Password untuk meminta link yang baru.';
?>
<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3"
                 style="width:64px;height:64px;background:linear-gradient(135deg,#B45309,#F59E0B);">
                <i class="bi bi-hourglass-split text-white fs-3"></i>
            </div>
            <h5 class="fw-700 mb-0" style="color:#1E293B;"><?= esc($title) ?></h5>
        </div>

        <div class="card">
            <div class="card-body p-4 text-center">
                <p class="text-muted mb-4"><?= esc($text) ?></p>
                <a href="<?= base_url('/auth/forgot-password') ?>" class="btn btn-primary w-100 py-2 fw-600">
                    <i class="bi bi-arrow-repeat me-1"></i>Kembali ke Lupa Password
                </a>
                <a href="<?= base_url('/auth') ?>" class="text-decoration-none small d-inline-block mt-3" style="color:#4F46E5;">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login
                </a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
