<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Sistem Absensi') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #3730A3;
            --primary-light: #EEF2FF;
        }
        body { font-family: 'Inter', sans-serif; background: #F1F5F9; font-size: .9rem; min-height: 100vh; display: flex; flex-direction: column; }
        /* Navbar */
        .app-navbar { background: linear-gradient(135deg, #3730A3 0%, #4F46E5 100%); box-shadow: 0 2px 12px rgba(79,70,229,.35); }
        .app-navbar .navbar-brand { font-weight: 700; font-size: 1.05rem; color: #fff !important; letter-spacing: .3px; }
        .app-navbar .navbar-brand .brand-icon { background: rgba(255,255,255,.2); border-radius: 8px; padding: 5px 9px; margin-right: 8px; font-size: .9rem; }
        .app-navbar .nav-link { color: rgba(255,255,255,.82) !important; font-weight: 500; font-size: .85rem; border-radius: 7px; padding: .38rem .75rem; transition: all .18s; }
        .app-navbar .nav-link:hover, .app-navbar .nav-link.active { color: #fff !important; background: rgba(255,255,255,.16); }
        .app-navbar .navbar-toggler { border: 1.5px solid rgba(255,255,255,.35); border-radius: 8px; padding: 5px 8px; }
        .app-navbar .navbar-toggler-icon { filter: brightness(0) invert(1); }
        .app-navbar .user-chip { background: rgba(255,255,255,.15); color: #fff; border-radius: 20px; padding: 4px 12px; font-size: .78rem; font-weight: 500; border: 1px solid rgba(255,255,255,.25); }
        .app-navbar .btn-logout { background: rgba(255,255,255,.12); color: #fff !important; border: 1px solid rgba(255,255,255,.3); border-radius: 8px; font-size: .8rem; padding: .35rem .85rem; font-weight: 500; transition: all .2s; text-decoration: none; }
        .app-navbar .btn-logout:hover { background: rgba(255,255,255,.22); }
        /* Main */
        main { flex: 1; }
        /* Cards */
        .card { border: none !important; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 16px rgba(0,0,0,.04); }
        .card-header { background: #fff; border-bottom: 1.5px solid #F1F5F9; padding: .9rem 1.25rem .75rem; font-weight: 600; border-radius: 12px 12px 0 0 !important; }
        /* Tables */
        .table { font-size: .865rem; }
        .table thead th { font-weight: 600; font-size: .77rem; text-transform: uppercase; letter-spacing: .5px; color: #64748B; border-bottom: 2px solid #E2E8F0; background: #F8FAFC; padding: .75rem 1rem; white-space: nowrap; }
        .table tbody td { padding: .75rem 1rem; vertical-align: middle; border-color: #F1F5F9; }
        .table-hover tbody tr:hover { background: #F8FAFC; }
        /* Buttons */
        .btn { border-radius: 8px; font-weight: 500; font-size: .865rem; }
        .btn-sm { border-radius: 7px; font-size: .8rem; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        /* Form */
        .form-control, .form-select { border-radius: 8px; border: 1.5px solid #E2E8F0; font-size: .875rem; padding: .5rem .85rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(79,70,229,.13); }
        .form-label { font-weight: 500; font-size: .84rem; color: #374151; margin-bottom: .3rem; }
        /* Badges */
        .badge { font-weight: 500; border-radius: 6px; letter-spacing: .2px; }
        /* Alerts */
        .alert { border: none; border-radius: 10px; font-size: .875rem; }
        .alert-danger { background: #FFF1F2; color: #BE123C; }
        .alert-success { background: #F0FDF4; color: #166534; }
        .alert-info { background: #F0F9FF; color: #0369A1; }
        .alert-warning { background: #FFFBEB; color: #92400E; }
        /* Page title */
        .page-title { font-size: 1.15rem; font-weight: 700; color: #1E293B; }
        /* Stat cards */
        .stat-card { border-radius: 14px; overflow: hidden; transition: transform .18s, box-shadow .18s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.1) !important; }
        .stat-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; }
        /* Mobile */
        @media (max-width: 575px) {
            .container { padding-left: 14px; padding-right: 14px; }
            .card { border-radius: 10px; }
            .page-title { font-size: 1rem; }
            .table thead th { font-size: .72rem; padding: .6rem .75rem; }
            .table tbody td { padding: .6rem .75rem; }
        }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #F1F5F9; }
        ::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 3px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= base_url('/') ?>">
            <span class="brand-icon">📋</span>Absensi Kampus
        </a>

        <?php if (session('logged_in')): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <?php if (session('role') === 'lecturer'): ?>
            <ul class="navbar-nav me-auto gap-1 mt-2 mt-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/lecturer/meetings') ?>"><i class="bi bi-collection me-1"></i>Pertemuan</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/lecturer/subjects') ?>"><i class="bi bi-book me-1"></i>Matakuliah</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/lecturer/report') ?>"><i class="bi bi-bar-chart me-1"></i>Rekap</a></li>
            </ul>
            <?php elseif (session('role') === 'student'): ?>
            <ul class="navbar-nav me-auto gap-1 mt-2 mt-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/student/dashboard') ?>"><i class="bi bi-house me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/student/scan') ?>"><i class="bi bi-qr-code-scan me-1"></i>Scan Absensi</a></li>
            </ul>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                <span class="user-chip d-none d-lg-inline-flex align-items-center gap-1">
                    <i class="bi bi-person-circle"></i> <?= esc(session('user_name')) ?>
                    <span class="badge ms-1" style="background:rgba(255,255,255,.2);font-size:.7rem;"><?= esc(session('role') === 'lecturer' ? 'Dosen' : 'Mahasiswa') ?></span>
                </span>
                <a href="<?= base_url('/logout') ?>" class="btn-logout d-flex align-items-center gap-1">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container py-4">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
            <span><?= esc(session()->getFlashdata('success')) ?></span>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('info')): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-info-circle-fill flex-shrink-0"></i>
            <span><?= esc(session()->getFlashdata('info')) ?></span>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><?= esc(session()->getFlashdata('warning')) ?></span>
        </div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</main>

<footer class="text-center py-3 mt-2" style="font-size:.75rem;color:#94A3B8;border-top:1px solid #E2E8F0;background:#fff;">
    <i class="bi bi-mortarboard me-1"></i>Sistem Absensi Mahasiswa &copy; <?= date('Y') ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
