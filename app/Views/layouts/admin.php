<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title ?? 'Admin Panel') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #0F172A;
            --admin-accent: #DC2626;
            --admin-accent-dark: #991B1B;
            --admin-bg: #0B1220;
            --admin-panel: #111827;
            --admin-border: #1F2937;
            --admin-text: #E5E7EB;
            --admin-text-muted: #9CA3AF;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--admin-bg); color: var(--admin-text); font-size: .9rem; min-height: 100vh; display: flex; }
        .admin-sidebar {
            width: 250px;
            background: var(--admin-panel);
            border-right: 1px solid var(--admin-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; bottom: 0; left: 0;
            z-index: 100;
            transition: transform .25s;
        }
        .admin-sidebar .brand {
            padding: 18px 20px;
            border-bottom: 1px solid var(--admin-border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-sidebar .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--admin-accent), #B91C1C);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 1.1rem;
            box-shadow: 0 0 14px rgba(220,38,38,.4);
        }
        .admin-sidebar .brand-text { font-weight: 700; color: #fff; font-size: 1rem; letter-spacing: .3px; }
        .admin-sidebar .brand-text small { display: block; font-size: .65rem; font-weight: 500; color: #D1D5DB; letter-spacing: 1px; text-transform: uppercase; }
        .admin-sidebar .nav-section { padding: 14px 14px 6px; font-size: .68rem; color: #93C5FD; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; }
        .admin-sidebar .nav-link {
            color: #E5E7EB;
            padding: 10px 16px;
            margin: 2px 10px;
            border-radius: 8px;
            font-size: .88rem;
            font-weight: 500;
            display: flex; align-items: center; gap: 12px;
            transition: all .15s;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .admin-sidebar .nav-link i { font-size: 1.05rem; width: 18px; text-align: center; }
        .admin-sidebar .nav-link:hover { background: rgba(255,255,255,.06); color: #fff; border-left-color: rgba(147,197,253,.4); }
        .admin-sidebar .nav-link.active { background: linear-gradient(135deg, var(--admin-accent), #B91C1C); color: #fff; box-shadow: 0 4px 12px rgba(220,38,38,.35); border-left-color: #FCA5A5; font-weight: 600; }
        .admin-sidebar .nav-link.active i { color: #fff; }
        .admin-sidebar .nav-link.danger { color: #FCA5A5; }
        .admin-sidebar .nav-link.danger:hover { background: rgba(220,38,38,.12); color: #FECACA; }
        .admin-sidebar .user-box {
            margin-top: auto;
            padding: 14px 16px;
            border-top: 1px solid var(--admin-border);
            display: flex; align-items: center; gap: 10px;
        }
        .admin-sidebar .user-box .avatar {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--admin-accent), #B91C1C);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 700; font-size: .9rem;
        }
        .admin-sidebar .user-box .info { flex: 1; min-width: 0; }
        .admin-sidebar .user-box .info .name { font-size: .85rem; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .admin-sidebar .user-box .info .role { font-size: .7rem; color: var(--admin-text-muted); }
        .admin-sidebar .user-box .logout {
            color: var(--admin-text-muted); font-size: 1.05rem;
            background: transparent; border: none; padding: 6px;
            border-radius: 6px; transition: all .15s;
        }
        .admin-sidebar .user-box .logout:hover { color: var(--admin-accent); background: rgba(220,38,38,.12); }

        .admin-main { margin-left: 250px; flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .admin-topbar {
            background: var(--admin-panel);
            border-bottom: 1px solid var(--admin-border);
            padding: 12px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px;
        }
        .admin-topbar .page-title { font-size: 1.1rem; font-weight: 700; color: #fff; margin: 0; }
        .admin-topbar .page-subtitle { font-size: .78rem; color: var(--admin-text-muted); margin: 0; }
        .admin-topbar .right-meta { display: flex; align-items: center; gap: 12px; font-size: .78rem; color: var(--admin-text-muted); }
        .admin-topbar .right-meta .badge-priv { background: rgba(220,38,38,.18); color: #FCA5A5; border: 1px solid rgba(220,38,38,.3); padding: 3px 9px; border-radius: 6px; font-size: .68rem; font-weight: 600; letter-spacing: .5px; }

        .admin-content { padding: 24px; flex: 1; }

        /* ── Text contrast fixes ── */
        .text-muted, small.text-muted, .small.text-muted { color: #9CA3AF !important; }
        .form-text, .help-block { color: #9CA3AF; }
        code { color: #93C5FD; background: rgba(147,197,253,.1); padding: 1px 5px; border-radius: 4px; font-size: .85em; }
        hr { border-color: var(--admin-border); opacity: .5; }

        /* ── Pagination dark theme (Bootstrap 5 markup) ── */
        .pagination { gap: 4px; }
        .pagination .page-link {
            background: var(--admin-panel);
            border: 1px solid var(--admin-border);
            color: #E5E7EB;
            border-radius: 6px !important;
            padding: .45rem .75rem;
            font-size: .85rem;
            font-weight: 500;
            min-width: 38px;
            text-align: center;
            transition: all .15s;
        }
        .pagination .page-link:hover { background: rgba(220,38,38,.15); border-color: var(--admin-accent); color: #fff; }
        .pagination .page-item.active .page-link { background: linear-gradient(135deg, var(--admin-accent), #B91C1C); border-color: var(--admin-accent); color: #fff; box-shadow: 0 2px 8px rgba(220,38,38,.35); }
        .pagination .page-item.disabled .page-link { background: rgba(255,255,255,.02); color: #6B7280; border-color: var(--admin-border); cursor: not-allowed; }
        .pagination .page-item i { font-size: .85rem; }

        /* ── Card footer & misc ── */
        .card-footer { background: rgba(255,255,255,.02); border-top: 1px solid var(--admin-border); padding: .75rem 1.25rem; color: #9CA3AF; }

        /* ── Modal ── */
        .modal-content { background: var(--admin-panel); border: 1px solid var(--admin-border); color: var(--admin-text); }
        .modal-header, .modal-footer { border-color: var(--admin-border); }
        .modal-backdrop.show { opacity: .7; }

        /* ── Dropdown ── */
        .dropdown-menu { background: var(--admin-panel); border: 1px solid var(--admin-border); color: var(--admin-text); box-shadow: 0 8px 24px rgba(0,0,0,.4); }
        .dropdown-item { color: var(--admin-text); }
        .dropdown-item:hover, .dropdown-item:focus { background: rgba(220,38,38,.12); color: #fff; }
        .dropdown-divider { border-color: var(--admin-border); }

        /* ── Tooltip / Popover ── */
        .tooltip-inner { background: #1F2937; color: #fff; border: 1px solid var(--admin-border); }
        .popover { background: var(--admin-panel); border-color: var(--admin-border); color: var(--admin-text); }
        .popover-header { background: rgba(255,255,255,.04); border-bottom-color: var(--admin-border); color: #fff; }

        /* ── Spinner ── */
        .spinner-border, .spinner-grow { color: var(--admin-accent); }

        /* ── Progress ── */
        .progress { background: rgba(255,255,255,.05); }
        .progress-bar { background-color: var(--admin-accent); }

        /* ── Empty state helper ── */
        .empty-state { padding: 3rem 1rem; text-align: center; color: #9CA3AF; }
        .empty-state i { font-size: 2.5rem; color: #4B5563; display: block; margin-bottom: .5rem; }

        /* ── Inputs on dark cards ── */
        .input-group-text { color: #D1D5DB; }
        .form-check-input { background-color: #0B1220; border-color: var(--admin-border); }
        .form-check-input:checked { background-color: var(--admin-accent); border-color: var(--admin-accent); }

        .card { background: var(--admin-panel); border: 1px solid var(--admin-border); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
        .card-body { background: var(--admin-panel); }
        .card-header { background: rgba(220,38,38,.08); border-bottom: 1px solid var(--admin-border); padding: 1rem 1.25rem; font-weight: 600; color: #fff; border-radius: 12px 12px 0 0 !important; }
        .card-header i { color: var(--admin-accent); }
        .table { color: var(--admin-text); font-size: .86rem; margin: 0; --bs-table-bg: transparent; --bs-table-color: var(--admin-text); --bs-table-border-color: var(--admin-border); }
        .table > :not(caption) > * > * { background-color: transparent !important; color: var(--admin-text); }
        .table thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; color: var(--admin-text-muted); border-bottom: 1px solid var(--admin-border); padding: .8rem 1rem; white-space: nowrap; background: rgba(255,255,255,.03) !important; font-weight: 600; }
        .table tbody td { padding: .8rem 1rem; vertical-align: middle; border-color: var(--admin-border); background-color: transparent !important; }
        .table tbody tr { background-color: transparent; }
        .table tbody tr:nth-child(odd) { background-color: rgba(255,255,255,.015); }
        .table-hover tbody tr:hover { background-color: rgba(220,38,38,.08) !important; }
        .table code { color: #93C5FD; background: rgba(147,197,253,.1); padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
        .table .text-muted, .table small.text-muted { color: #9CA3AF !important; }

        .form-control, .form-select { background: #0B1220; border: 1px solid var(--admin-border); color: var(--admin-text); border-radius: 8px; font-size: .875rem; padding: .55rem .85rem; }
        .form-control:focus, .form-select:focus { background: #0B1220; border-color: var(--admin-accent); color: #fff; box-shadow: 0 0 0 .2rem rgba(220,38,38,.15); }
        .form-control::placeholder { color: #6B7280; }
        .input-group-text { background: #0B1220; border-color: var(--admin-border); color: var(--admin-text-muted); }
        .form-label { font-weight: 500; font-size: .84rem; color: #D1D5DB; margin-bottom: .3rem; }
        .form-text { color: var(--admin-text-muted); }

        .btn { border-radius: 8px; font-weight: 500; font-size: .865rem; }
        .btn-sm { font-size: .8rem; border-radius: 6px; }
        .btn-admin { background: linear-gradient(135deg, var(--admin-accent), #B91C1C); color: #fff; border: none; }
        .btn-admin:hover { background: linear-gradient(135deg, #B91C1C, var(--admin-accent-dark)); color: #fff; }
        .btn-outline-light { border-color: var(--admin-border); color: var(--admin-text); }
        .btn-outline-light:hover { background: rgba(255,255,255,.06); border-color: var(--admin-border); color: #fff; }

        .alert { border-radius: 10px; font-size: .87rem; border: 1px solid transparent; }
        .alert-danger { background: rgba(220,38,38,.12); color: #FCA5A5; border-color: rgba(220,38,38,.3); }
        .alert-success { background: rgba(16,185,129,.12); color: #6EE7B7; border-color: rgba(16,185,129,.3); }
        .alert-info { background: rgba(59,130,246,.12); color: #93C5FD; border-color: rgba(59,130,246,.3); }
        .alert-warning { background: rgba(245,158,11,.12); color: #FCD34D; border-color: rgba(245,158,11,.3); }

        .badge { font-weight: 600; border-radius: 6px; padding: .35em .65em; font-size: .73rem; }
        .bg-success-soft { background: rgba(16,185,129,.22); color: #6EE7B7; border: 1px solid rgba(16,185,129,.3); }
        .bg-danger-soft { background: rgba(220,38,38,.22); color: #FCA5A5; border: 1px solid rgba(220,38,38,.3); }
        .bg-secondary-soft { background: rgba(156,163,175,.22); color: #D1D5DB; border: 1px solid rgba(156,163,175,.3); }
        .bg-primary-soft { background: rgba(59,130,246,.22); color: #93C5FD; border: 1px solid rgba(59,130,246,.3); }
        .bg-info-soft { background: rgba(14,165,233,.22); color: #7DD3FC; border: 1px solid rgba(14,165,233,.3); }

        .stat-card { background: var(--admin-panel); border: 1px solid var(--admin-border); border-radius: 12px; padding: 18px; display: flex; align-items: center; gap: 14px; }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .stat-card .icon.red { background: rgba(220,38,38,.18); color: #FCA5A5; }
        .stat-card .icon.blue { background: rgba(59,130,246,.18); color: #93C5FD; }
        .stat-card .icon.green { background: rgba(16,185,129,.18); color: #6EE7B7; }
        .stat-card .icon.amber { background: rgba(245,158,11,.18); color: #FCD34D; }
        .stat-card .label { font-size: .75rem; color: var(--admin-text-muted); text-transform: uppercase; letter-spacing: .5px; }
        .stat-card .value { font-size: 1.6rem; font-weight: 700; color: #fff; line-height: 1.1; margin-top: 2px; }

        .admin-mobile-toggle { display: none; background: var(--admin-panel); border: 1px solid var(--admin-border); color: #fff; border-radius: 8px; padding: 7px 10px; }
        .admin-mobile-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 99; }

        @media (max-width: 991.98px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-mobile-toggle { display: inline-flex; }
            .admin-mobile-backdrop.show { display: block; }
            .admin-main { margin-left: 0; }
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--admin-bg); }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 3px; }
    </style>
</head>
<body>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">
        <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <div class="brand-text">Admin Panel<small>Absensi Kampus</small></div>
    </div>

    <div class="nav-section">Utama</div>
    <a class="nav-link <?= (uri_string() === 'admin/dashboard') ? 'active' : '' ?>" href="<?= base_url('/admin/dashboard') ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>

    <div class="nav-section">Manajemen</div>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/ads') ? 'active' : '' ?>" href="<?= base_url('/admin/ads') ?>">
        <i class="bi bi-badge-ad"></i> Iklan
    </a>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/lecturers') ? 'active' : '' ?>" href="<?= base_url('/admin/lecturers') ?>">
        <i class="bi bi-person-workspace"></i> Dosen
    </a>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/students') ? 'active' : '' ?>" href="<?= base_url('/admin/students') ?>">
        <i class="bi bi-mortarboard"></i> Mahasiswa
    </a>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/impressions') ? 'active' : '' ?>" href="<?= base_url('/admin/impressions') ?>">
        <i class="bi bi-bar-chart-line"></i> Kuota Iklan
    </a>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/settings') ? 'active' : '' ?>" href="<?= base_url('/admin/settings') ?>">
        <i class="bi bi-sliders"></i> Pengaturan
    </a>

    <div class="nav-section">Akun</div>
    <a class="nav-link <?= str_starts_with(uri_string(), 'admin/change-password') ? 'active' : '' ?>" href="<?= base_url('/admin/change-password') ?>">
        <i class="bi bi-key"></i> Ganti Password
    </a>

    <div class="user-box">
        <div class="avatar"><?= strtoupper(substr(session('user_name') ?? 'A', 0, 1)) ?></div>
        <div class="info">
            <div class="name"><?= esc(session('user_name') ?? 'Admin') ?></div>
            <div class="role">Administrator</div>
        </div>
        <a class="logout" href="<?= base_url('/admin/logout') ?>" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</aside>

<div class="admin-mobile-backdrop" id="adminBackdrop" onclick="document.getElementById('adminSidebar').classList.remove('show');this.classList.remove('show');"></div>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="admin-mobile-toggle" onclick="document.getElementById('adminSidebar').classList.add('show');document.getElementById('adminBackdrop').classList.add('show');"><i class="bi bi-list"></i></button>
            <div>
                <h1 class="page-title"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
                <?php if (! empty($pageSubtitle)): ?>
                    <p class="page-subtitle"><?= esc($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="right-meta">
            <span class="badge-priv"><i class="bi bi-shield-check me-1"></i>PRIVILEGED</span>
            <span class="d-none d-md-inline"><?= esc(session('user_email') ?? '') ?></span>
        </div>
    </header>

    <main class="admin-content">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('info')): ?>
            <div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-info-circle-fill"></i>
                <span><?= esc(session()->getFlashdata('info')) ?></span>
            </div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('warning')): ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span><?= esc(session()->getFlashdata('warning')) ?></span>
            </div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
