<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login — Sistem Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0B1220; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-shell { width: 100%; max-width: 420px; }
        .login-card { background: #111827; border: 1px solid #1F2937; border-radius: 16px; padding: 32px 28px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        .login-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #DC2626, #B91C1C); border-radius: 16px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.7rem; margin: 0 auto 18px; box-shadow: 0 0 24px rgba(220,38,38,.4); }
        .login-title { color: #fff; font-weight: 700; font-size: 1.3rem; text-align: center; margin: 0; }
        .login-subtitle { color: #9CA3AF; font-size: .82rem; text-align: center; margin: 6px 0 26px; }
        .form-label { color: #D1D5DB; font-weight: 500; font-size: .83rem; }
        .form-control { background: #0B1220; border: 1px solid #1F2937; color: #E5E7EB; border-radius: 8px; font-size: .9rem; padding: .6rem .85rem; }
        .form-control:focus { background: #0B1220; border-color: #DC2626; color: #fff; box-shadow: 0 0 0 .2rem rgba(220,38,38,.18); }
        .form-control::placeholder { color: #6B7280; }
        .btn-admin { background: linear-gradient(135deg, #DC2626, #B91C1C); color: #fff; border: none; border-radius: 9px; font-weight: 600; padding: .65rem; font-size: .9rem; width: 100%; }
        .btn-admin:hover { background: linear-gradient(135deg, #B91C1C, #991B1B); color: #fff; }
        .alert { border-radius: 9px; font-size: .85rem; border: 1px solid transparent; }
        .alert-danger { background: rgba(220,38,38,.12); color: #FCA5A5; border-color: rgba(220,38,38,.3); }
        .alert-info { background: rgba(59,130,246,.12); color: #93C5FD; border-color: rgba(59,130,246,.3); }
        .footer-note { color: #6B7280; font-size: .72rem; text-align: center; margin-top: 18px; letter-spacing: .4px; }
        .badge-priv { display: inline-block; background: rgba(220,38,38,.18); color: #FCA5A5; border: 1px solid rgba(220,38,38,.3); padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 600; letter-spacing: .6px; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="login-shell">
    <div class="login-card">
        <div class="login-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="text-center"><span class="badge-priv"><i class="bi bi-shield-check me-1"></i>RESTRICTED AREA</span></div>
        <h1 class="login-title">Admin Panel</h1>
        <p class="login-subtitle">Masuk untuk mengelola sistem absensi</p>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-3"><i class="bi bi-exclamation-triangle-fill"></i><span><?= esc(session()->getFlashdata('error')) ?></span></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('info')): ?>
            <div class="alert alert-info d-flex align-items-center gap-2 mb-3"><i class="bi bi-info-circle-fill"></i><span><?= esc(session()->getFlashdata('info')) ?></span></div>
        <?php endif; ?>

        <form action="<?= base_url('/admin/login') ?>" method="post" autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-envelope me-1"></i>Email Admin</label>
                <input type="email" name="identifier" class="form-control" value="<?= esc(old('identifier')) ?>" placeholder="admin@kampus.ac.id" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-lock me-1"></i>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-admin mt-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Admin Panel
            </button>
        </form>

        <div class="footer-note">
            <i class="bi bi-c-circle me-1"></i>Sistem Absensi Kampus — Akses terbatas untuk admin terdaftar
        </div>
    </div>
</div>
</body>
</html>
