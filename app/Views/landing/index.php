<?php
/** @var string $title */
$title = $title ?? 'KelasMerdeka.com – Platform Perkuliahan Digital untuk Dosen dan Mahasiswa';
$description = 'Platform pembelajaran digital untuk membantu dosen dan mahasiswa mengelola materi, presensi QR Code, kuis online berbantuan AI, dan hasil belajar dalam satu tempat.';
$base = rtrim(base_url(), '/');

// Status formulir kontak (disiapkan Home::index / ContactService).
$contact     = is_array($contact ?? null) ? $contact : [];
$cEnabled    = ! empty($contact['enabled']);
$cSuccess    = ! empty($contact['success']);
$cBlocked    = ! empty($contact['blocked']);
$cErrors     = is_array($contact['errors'] ?? null) ? $contact['errors'] : [];
$cValues     = is_array($contact['values'] ?? null) ? $contact['values'] : [];
$cToken      = (string) ($contact['form_token'] ?? '');
$cMaxLength  = max(200, (int) ($contact['max_length'] ?? 2000));
$cRetryText  = trim((string) ($contact['retry_text'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title) ?></title>
    <meta name="description" content="<?= esc($description) ?>">
    <meta name="theme-color" content="#2563EB">
    <link rel="canonical" href="<?= esc($base . '/', 'attr') ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="KelasMerdeka.com">
    <meta property="og:title" content="<?= esc($title, 'attr') ?>">
    <meta property="og:description" content="<?= esc($description, 'attr') ?>">
    <meta property="og:url" content="<?= esc($base . '/', 'attr') ?>">
    <meta property="og:locale" content="id_ID">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= esc($title, 'attr') ?>">
    <meta name="twitter:description" content="<?= esc($description, 'attr') ?>">

    <link rel="icon" href="<?= base_url('/favicon.ico') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('/assets/css/landing.css') ?>">
</head>
<body class="km-landing">

<a class="km-skip" href="#beranda">Lewati ke konten utama</a>

<!-- ===== SVG icon sprite (inline, tanpa dependensi eksternal) ===== -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <symbol id="i-graduation" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9.5"/></symbol>
    <symbol id="i-arrow-right" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="M9 12l2 2 4-4"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/></symbol>
    <symbol id="i-qr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v3M14 20h3M20 20h1"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></symbol>
    <symbol id="i-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M9 7h7M9 11h5"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h12M9 12h12M9 18h12"/><path d="M4 6h.01M4 12h.01M4 18h.01"/></symbol>
    <symbol id="i-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 21 2l-2 5 2 2-3 1-1 3-3-3"/></symbol>
    <symbol id="i-table" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10M15 10v10"/></symbol>
    <symbol id="i-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M10 8.5l6 3.5-6 3.5v-7Z" fill="currentColor" stroke="none"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.4"/><path d="M2.5 20c.8-3.2 3.4-5 6.5-5s5.7 1.8 6.5 5"/><path d="M16 4.6a3.4 3.4 0 0 1 0 6.8M18 15.3c2 .7 3.3 2.3 3.8 4.7"/></symbol>
    <symbol id="i-laptop" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></symbol>
    <symbol id="i-heart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1Z"/></symbol>
    <symbol id="i-shield-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></symbol>
</svg>

<!-- ==================== NAVBAR ==================== -->
<header class="km-nav" id="navbar">
    <div class="km-container km-nav-inner">
        <a href="#beranda" class="km-brand" aria-label="KelasMerdeka.com - Beranda">
            <span class="km-brand-mark"><svg aria-hidden="true"><use href="#i-graduation"/></svg></span>
            <span>KelasMerdeka.com<small>Platform Perkuliahan Digital</small></span>
        </a>

        <nav class="km-nav-links" aria-label="Navigasi utama">
            <a href="#beranda">Beranda</a>
            <a href="#fitur-dosen">Fitur Dosen</a>
            <a href="#fitur-mahasiswa">Fitur Mahasiswa</a>
            <a href="#cara-kerja">Cara Kerja</a>
            <a href="#tentang">Tentang</a>
            <a href="#kontak">Kontak</a>
        </nav>

        <div class="km-nav-actions">
            <a href="<?= base_url('/auth') ?>" class="km-btn km-btn-outline km-btn-sm">Login</a>
            <a href="<?= base_url('/register') ?>" class="km-btn km-btn-primary km-btn-sm">Daftar</a>
        </div>

        <button class="km-nav-toggle" id="navToggle" aria-controls="mobileMenu" aria-expanded="false" aria-label="Buka menu navigasi">
            <svg id="navIconOpen" aria-hidden="true"><use href="#i-menu"/></svg>
            <svg id="navIconClose" style="display:none" aria-hidden="true"><use href="#i-close"/></svg>
        </button>
    </div>

    <div class="km-mobile-menu" id="mobileMenu">
        <a href="#beranda" data-close-menu>Beranda</a>
        <a href="#fitur-dosen" data-close-menu>Fitur Dosen</a>
        <a href="#fitur-mahasiswa" data-close-menu>Fitur Mahasiswa</a>
        <a href="#cara-kerja" data-close-menu>Cara Kerja</a>
        <a href="#tentang" data-close-menu>Tentang</a>
        <a href="#kontak" data-close-menu>Kontak</a>
        <a href="<?= base_url('/auth') ?>" class="km-btn km-btn-outline">Login</a>
        <a href="<?= base_url('/register') ?>" class="km-btn km-btn-primary">Daftar</a>
    </div>
</header>

<main id="beranda">

<!-- ==================== HERO ==================== -->
<section class="km-hero">
    <div class="km-container km-hero-grid">
        <div>
            <span class="km-eyebrow"><svg aria-hidden="true"><use href="#i-graduation"/></svg>Platform Perkuliahan Digital</span>
            <h1>Mengajar Lebih Mudah,<br><span>Mengelola Kelas Lebih Cerdas.</span></h1>
            <p class="km-hero-sub">
                Platform pembelajaran digital untuk membantu Dosen dan Mahasiswa menjalankan
                aktivitas perkuliahan secara lebih praktis, terstruktur, dan terintegrasi.
            </p>

            <div class="km-hero-cta">
                <a href="<?= base_url('/register') ?>" class="km-btn km-btn-primary">Daftar</a>
                <a href="#fitur-dosen" class="km-btn km-btn-outline">Jelajahi Fitur</a>
            </div>
            <p class="km-cost-note">
                <svg aria-hidden="true"><use href="#i-check-circle"/></svg>
                Tersedia tanpa biaya langganan bulanan untuk dosen dan mahasiswa.
            </p>
        </div>

        <div class="km-hero-visual" aria-hidden="true">
            <div class="km-mock km-hero-main">
                <div class="km-mock-head">
                    <span class="km-dot r"></span><span class="km-dot y"></span><span class="km-dot g"></span>
                    <span class="km-mock-title">Dashboard Dosen</span>
                </div>
                <div class="km-mock-body">
                    <div class="km-stats">
                        <div class="km-stat"><b>4</b><span>Mata Kuliah</span></div>
                        <div class="km-stat"><b>12</b><span>Pertemuan</span></div>
                        <div class="km-stat"><b>3</b><span>Kuis Aktif</span></div>
                    </div>
                    <div class="km-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <div class="km-chart-label"><span>Nilai rata-rata kelas</span><span>82%</span></div>
                </div>
            </div>

            <div class="km-float km-float-quiz">
                <div class="km-q">Soal 3 dari 10</div>
                <div class="km-opt is-correct"><i>✓</i> Jawaban A</div>
                <div class="km-opt"><i></i> Jawaban B</div>
                <div class="km-opt"><i></i> Jawaban C</div>
                <span class="km-timer-chip"><svg aria-hidden="true"><use href="#i-clock"/></svg>00:45</span>
            </div>

            <div class="km-float km-float-ai">
                <span class="km-ai-ico"><svg aria-hidden="true"><use href="#i-spark"/></svg></span>
                <span>AI menyusun soal dari PDF<small>Dosen tetap memeriksa &amp; mengedit</small></span>
            </div>

            <div class="km-float km-float-qr">
                <span class="km-qr">
                    <svg viewBox="0 0 48 48" aria-hidden="true">
                        <rect width="48" height="48" rx="6" fill="#fff"/>
                        <g fill="#0F172A">
                            <rect x="5" y="5" width="12" height="12" rx="2"/><rect x="31" y="5" width="12" height="12" rx="2"/>
                            <rect x="5" y="31" width="12" height="12" rx="2"/>
                            <rect x="8" y="8" width="6" height="6" fill="#fff"/><rect x="34" y="8" width="6" height="6" fill="#fff"/>
                            <rect x="8" y="34" width="6" height="6" fill="#fff"/>
                            <rect x="21" y="5" width="3" height="3"/><rect x="26" y="9" width="3" height="3"/>
                            <rect x="21" y="14" width="3" height="3"/><rect x="21" y="21" width="26" height="3"/>
                            <rect x="14" y="21" width="3" height="3"/><rect x="5" y="21" width="3" height="3"/>
                            <rect x="21" y="27" width="3" height="3"/><rect x="27" y="27" width="3" height="3"/>
                            <rect x="33" y="27" width="3" height="3"/><rect x="39" y="27" width="3" height="3"/>
                            <rect x="21" y="33" width="3" height="3"/><rect x="27" y="33" width="3" height="3"/>
                            <rect x="33" y="33" width="3" height="3"/><rect x="39" y="33" width="3" height="3"/>
                            <rect x="21" y="39" width="3" height="3"/><rect x="33" y="39" width="3" height="3"/>
                        </g>
                    </svg>
                </span>
                <span><b>QR Absensi</b><small>Scan kehadiran</small></span>
            </div>
        </div>
    </div>
</section>

<!-- ==================== KENAPA ==================== -->
<section class="km-section" id="kenapa">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">Kenapa KelasMerdeka</span>
            <h2>Satu Platform, Banyak Urusan Perkuliahan Jadi Lebih Praktis.</h2>
            <p>Dari menyiapkan materi sampai melihat hasil kuis mahasiswa, semuanya bisa dikelola dari satu tempat.</p>
        </div>

        <div class="km-grid-4">
            <article class="km-why-card">
                <span class="km-ico"><svg aria-hidden="true"><use href="#i-book"/></svg></span>
                <h3>Materi Lebih Terorganisir</h3>
                <p>Simpan dan kelola materi pembelajaran agar mahasiswa dapat mengakses bahan perkuliahan melalui aplikasi.</p>
            </article>
            <article class="km-why-card">
                <span class="km-ico green"><svg aria-hidden="true"><use href="#i-spark"/></svg></span>
                <h3>Bikin Soal Lebih Cepat</h3>
                <p>Manfaatkan AI untuk membantu menyusun soal kuis berdasarkan materi PDF. Dosen tetap dapat memeriksa dan mengedit soal sebelum digunakan.</p>
            </article>
            <article class="km-why-card">
                <span class="km-ico amber"><svg aria-hidden="true"><use href="#i-qr"/></svg></span>
                <h3>Absensi Lebih Praktis</h3>
                <p>Gunakan QR Code untuk memudahkan proses absensi perkuliahan dan pengelolaan kehadiran mahasiswa.</p>
            </article>
            <article class="km-why-card">
                <span class="km-ico rose"><svg aria-hidden="true"><use href="#i-chart"/></svg></span>
                <h3>Hasil Belajar Lebih Terukur</h3>
                <p>Lihat hasil kuis, nilai mahasiswa, dan analisis performa soal untuk membantu evaluasi pembelajaran.</p>
            </article>
        </div>

        <p class="km-why-note"><strong>Satu alur kerja untuk materi, presensi, kuis, dan evaluasi hasil belajar.</strong></p>
    </div>
</section>

<!-- ==================== FITUR DOSEN ==================== -->
<section class="km-section alt" id="fitur-dosen">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">Untuk Dosen</span>
            <h2>Dosen Punya Lebih Banyak Waktu untuk Mengajar, Bukan Sibuk Urusan Administrasi.</h2>
            <p>KelasMerdeka menyediakan berbagai fitur yang membantu dosen mengelola kegiatan perkuliahan dengan lebih praktis — dari materi hingga evaluasi hasil belajar.</p>
        </div>

        <div class="km-feature-grid">

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico"><svg aria-hidden="true"><use href="#i-lock"/></svg></span>
                    <div>
                        <span class="km-tag">Materi</span>
                        <h3>Materi PDF Tetap di Aplikasi, Lebih Terjaga.</h3>
                    </div>
                </div>
                <p>Bagikan materi PDF kepada mahasiswa tanpa harus membagikan file aslinya. Materi dapat dibaca langsung melalui aplikasi sehingga akses terhadap bahan pembelajaran lebih terkontrol.</p>
                <ul class="km-checks">
                    <li>Materi dibaca melalui aplikasi.</li>
                    <li>Tidak menyediakan tombol unduh file PDF kepada mahasiswa.</li>
                    <li>Akses file memakai token sesi mahasiswa yang sah.</li>
                    <li>Dosen dapat mengelola materi per pertemuan.</li>
                </ul>
                <div class="km-feature-note">Akses file dibatasi melalui aplikasi. Perlindungan mengikuti mekanisme aplikasi, bukan jaminan bahwa file tidak dapat disimpan melalui perangkat pembaca.</div>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico green"><svg aria-hidden="true"><use href="#i-spark"/></svg></span>
                    <div>
                        <span class="km-tag">AI Pembuat Soal</span>
                        <h3>Bikin Soal Kuis? Serahkan Bagian Awalnya ke AI.</h3>
                    </div>
                </div>
                <p>Tidak perlu menyusun semua soal dari nol. Unggah materi PDF, tentukan jumlah soal dan instruksi yang diinginkan, lalu AI membantu membuatkan soal berdasarkan materi tersebut.</p>
                <ul class="km-checks">
                    <li>Generate soal dari materi PDF.</li>
                    <li>Pilihan jumlah soal 5, 10, 15, atau 20.</li>
                    <li>Instruksi tambahan untuk AI dan pengaturan komposisi kesulitan.</li>
                    <li>Panel Periksa: preview hasil pembacaan PDF.</li>
                    <li>Soal AI dapat diperiksa dan diedit sebelum dipakai.</li>
                    <li>Soal manual juga tetap tersedia.</li>
                </ul>
                <div class="km-ai-callout">
                    <svg aria-hidden="true"><use href="#i-spark"/></svg>
                    <span>AI membantu menyusun soal, keputusan akhir tetap di tangan dosen. Penggunaan mengikuti batas teknis aplikasi dan kebijakan layanan AI.</span>
                </div>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico amber"><svg aria-hidden="true"><use href="#i-qr"/></svg></span>
                    <div>
                        <span class="km-tag">Absensi</span>
                        <h3>Absensi Tinggal Scan, Kehadiran Lebih Terorganisir.</h3>
                    </div>
                </div>
                <p>Permudah pencatatan kehadiran mahasiswa menggunakan QR Code. Dosen dapat menyiapkan pertemuan dan membuka QR absensi, sementara mahasiswa melakukan pemindaian melalui perangkat mereka.</p>
                <ul class="km-checks">
                    <li>QR Code absensi per pertemuan.</li>
                    <li>QR diperbarui berkala sesuai mekanisme aplikasi.</li>
                    <li>Data kehadiran tersimpan dan dapat direkap.</li>
                    <li>Dosen dapat melihat detail kehadiran mahasiswa.</li>
                    <li>Tersedia pencatatan absensi manual bila diperlukan.</li>
                </ul>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico"><svg aria-hidden="true"><use href="#i-link"/></svg></span>
                    <div>
                        <span class="km-tag">Materi Tambahan</span>
                        <h3>Materi Tidak Harus PDF Saja.</h3>
                    </div>
                </div>
                <p>Tambahkan referensi pembelajaran dari website dan video YouTube agar mahasiswa bisa belajar dari berbagai sumber.</p>
                <ul class="km-checks">
                    <li>Tambahkan link referensi website.</li>
                    <li>Sisipkan link video YouTube (maksimal 3 link per pertemuan).</li>
                    <li>Video YouTube dapat ditampilkan sebagai embed.</li>
                    <li>Materi disusun berdasarkan pertemuan.</li>
                </ul>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico rose"><svg aria-hidden="true"><use href="#i-chart"/></svg></span>
                    <div>
                        <span class="km-tag">Analitik</span>
                        <h3>Bukan Sekadar Melihat Nilai, Pahami Performa Mahasiswa.</h3>
                    </div>
                </div>
                <p>Lihat bagaimana mahasiswa mengerjakan kuis dan evaluasi kualitas soal melalui analisis yang tersedia.</p>
                <ul class="km-checks">
                    <li>Nilai dan persentase hasil kuis.</li>
                    <li>Jumlah peserta dan rata-rata nilai.</li>
                    <li>Analisis per soal.</li>
                    <li>Persentase jawaban benar, salah, dan tidak dijawab.</li>
                    <li>Rata-rata waktu pengerjaan soal.</li>
                </ul>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico"><svg aria-hidden="true"><use href="#i-list"/></svg></span>
                    <div>
                        <span class="km-tag">Bank Soal</span>
                        <h3>Soal Tersimpan Rapi, Bisa Dikelola Kapan Saja.</h3>
                    </div>
                </div>
                <p>Gabungkan soal yang dibuat secara manual dan soal hasil AI dalam satu bank soal. Dosen bisa memperbaiki, mengedit, atau menghapus soal sesuai kebutuhan.</p>
                <ul class="km-checks">
                    <li>Soal manual dan soal hasil AI.</li>
                    <li>Editor soal dengan format teks.</li>
                    <li>Mendukung tabel dan blok kode.</li>
                    <li>Pengaturan kunci jawaban dan durasi per soal.</li>
                    <li>Pengelolaan bank soal per kuis.</li>
                </ul>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico green"><svg aria-hidden="true"><use href="#i-key"/></svg></span>
                    <div>
                        <span class="km-tag">Kode Kuis</span>
                        <h3>Bagikan Kuis Cukup dengan Satu Kode.</h3>
                    </div>
                </div>
                <p>Setelah kuis siap, sistem menyediakan Kode Kuis yang dapat dibagikan kepada mahasiswa. Mahasiswa tinggal memasukkan kode tersebut untuk membuka kuis.</p>
                <ul class="km-checks">
                    <li>Kode Kuis dibuat otomatis.</li>
                    <li>Kode dapat disalin dengan mudah.</li>
                    <li>Kode dapat diganti (regenerate) bila diperlukan.</li>
                    <li>Histori kode dan percobaan tetap tersimpan.</li>
                </ul>
            </article>

            <article class="km-feature">
                <div class="km-feature-head">
                    <span class="km-ico amber"><svg aria-hidden="true"><use href="#i-table"/></svg></span>
                    <div>
                        <span class="km-tag">Nilai</span>
                        <h3>Nilai Siap Direkap, Tidak Perlu Menghitung Ulang.</h3>
                    </div>
                </div>
                <p>Semua hasil kuis mahasiswa tersusun dalam tabel nilai. Jika diperlukan untuk laporan akademik, dosen dapat mengunduh rekap dalam format Excel.</p>
                <ul class="km-checks">
                    <li>Tabel nilai mahasiswa.</li>
                    <li>Pencarian berdasarkan nama atau NPM.</li>
                    <li>Riwayat percobaan kuis.</li>
                    <li>Export nilai ke Excel (.xlsx).</li>
                </ul>
            </article>

        </div>

        <div class="km-cta-band">
            <div>
                <h3>Saatnya Mengelola Perkuliahan dengan Lebih Praktis.</h3>
                <p>Manfaatkan fitur KelasMerdeka untuk menyiapkan materi, presensi, kuis, dan evaluasi dalam satu alur kerja.</p>
            </div>
            <a href="<?= base_url('/register') ?>" class="km-btn km-btn-light">Daftar</a>
        </div>
    </div>
</section>

<!-- ==================== FITUR MAHASISWA ==================== -->
<section class="km-section" id="fitur-mahasiswa">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">Untuk Mahasiswa</span>
            <h2>Belajar dan Mengikuti Perkuliahan Jadi Lebih Praktis.</h2>
            <p>Mahasiswa bisa mengakses materi, mengikuti absensi, mengerjakan kuis, dan melihat hasil belajar melalui satu aplikasi.</p>
        </div>

        <div class="km-split">
            <div class="km-split-media">
                <img src="<?= base_url('/assets/img/landing/student-learning.svg') ?>"
                     alt="Ilustrasi mahasiswa mengikuti perkuliahan melalui laptop dan smartphone"
                     loading="lazy" width="560" height="420">
            </div>

            <div>
                <div class="km-student-grid">
                    <article class="km-student-card">
                        <span class="km-ico"><svg aria-hidden="true"><use href="#i-book"/></svg></span>
                        <div><h3>Akses Materi Perkuliahan</h3><p>Materi tersedia dalam aplikasi. Mahasiswa dapat membaca PDF dan membuka referensi yang dibagikan dosen.</p></div>
                    </article>
                    <article class="km-student-card">
                        <span class="km-ico amber"><svg aria-hidden="true"><use href="#i-qr"/></svg></span>
                        <div><h3>Absensi dengan QR Code</h3><p>Cukup buka fitur pemindai dan scan QR Code yang ditampilkan dosen saat perkuliahan.</p></div>
                    </article>
                    <article class="km-student-card">
                        <span class="km-ico green"><svg aria-hidden="true"><use href="#i-key"/></svg></span>
                        <div><h3>Kuis Online dengan Kode</h3><p>Masukkan Kode Kuis dari dosen untuk mengikuti kuis online tanpa mencari tautan panjang.</p></div>
                    </article>
                    <article class="km-student-card">
                        <span class="km-ico rose"><svg aria-hidden="true"><use href="#i-clock"/></svg></span>
                        <div><h3>Mengerjakan Kuis dengan Timer</h3><p>Soal ditampilkan bertahap dengan batas waktu pengerjaan per soal.</p></div>
                    </article>
                    <article class="km-student-card">
                        <span class="km-ico"><svg aria-hidden="true"><use href="#i-chart"/></svg></span>
                        <div><h3>Melihat Hasil Kuis</h3><p>Setelah kuis selesai, mahasiswa dapat melihat hasil pengerjaan dan nilai sesuai fitur aplikasi.</p></div>
                    </article>
                    <article class="km-student-card">
                        <span class="km-ico green"><svg aria-hidden="true"><use href="#i-laptop"/></svg></span>
                        <div><h3>Belajar Lebih Terorganisir</h3><p>Materi, pertemuan, absensi, dan kuis dalam satu platform sehingga mudah diikuti.</p></div>
                    </article>
                </div>
                <p class="km-student-cta">
                    Belum punya akun? <a href="<?= base_url('/register') ?>">Daftar</a>
                    &nbsp;·&nbsp; Sudah punya akun? <a href="<?= base_url('/auth') ?>">Login di sini</a>.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== TANPA BIAYA ==================== -->
<section class="km-section alt" id="biaya">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">Informasi Layanan</span>
            <h2>Gunakan Fitur KelasMerdeka Tanpa Beban Langganan.</h2>
            <p>Mulai gunakan berbagai fitur KelasMerdeka tanpa biaya berlangganan saat ini. Nikmati kemudahan pengelolaan pembelajaran melalui fitur yang tersedia, tanpa biaya langganan bulanan.</p>
        </div>

        <div class="km-price-card">
            <div class="km-price-top">
                <span class="km-price-badge"><svg aria-hidden="true"><use href="#i-check-circle"/></svg>Informasi Biaya</span>
                <h3>Fokus pada pembelajaran, bukan pada biaya langganan.</h3>
                <p>KelasMerdeka dapat digunakan untuk mendukung kegiatan perkuliahan tanpa paket langganan bulanan maupun tahunan.</p>
            </div>
            <ul class="km-price-list">
                <li><svg aria-hidden="true"><use href="#i-check"/></svg>Tidak ada biaya langganan bulanan.</li>
                <li><svg aria-hidden="true"><use href="#i-check"/></svg>Tidak ada biaya langganan tahunan.</li>
                <li><svg aria-hidden="true"><use href="#i-check"/></svg>Tidak ada paket berbayar untuk membuka fitur.</li>
                <li><svg aria-hidden="true"><use href="#i-check"/></svg>Penggunaan fitur mengikuti fungsi dan ketentuan layanan aplikasi.</li>
            </ul>
            <div class="km-price-foot">
                <p>Informasi ini disampaikan sebagai bagian dari transparansi layanan KelasMerdeka.com.</p>
            </div>
        </div>
    </div>
</section>

<!-- ==================== CARA KERJA ==================== -->
<section class="km-section" id="cara-kerja">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">Cara Kerja</span>
            <h2>Mulai Menggunakan KelasMerdeka Itu Gampang.</h2>
            <p>Dua alur sederhana: untuk dosen yang menyiapkan perkuliahan, dan untuk mahasiswa yang mengikuti kegiatan.</p>
        </div>

        <div class="km-flows">
            <div class="km-flow">
                <div class="km-flow-head">
                    <span class="km-ico"><svg aria-hidden="true"><use href="#i-graduation"/></svg></span>
                    <div><h3>Alur Dosen</h3><p>Menyiapkan dan mengelola perkuliahan</p></div>
                </div>
                <ol class="km-steps">
                    <li><strong>Daftar sebagai Dosen</strong><span>Gunakan email institusi berakhiran .ac.id.</span></li>
                    <li><strong>Verifikasi email institusi</strong><span>Masukkan kode verifikasi yang dikirim ke email.</span></li>
                    <li><strong>Lengkapi konfirmasi profil</strong><span>Periksa data profil pada login pertama.</span></li>
                    <li><strong>Kelola mata kuliah dan pertemuan</strong><span>Susun jadwal dan materi perkuliahan.</span></li>
                    <li><strong>Tambahkan materi pembelajaran</strong><span>PDF, referensi website, dan video YouTube.</span></li>
                    <li><strong>Buat kuis</strong><span>Soal manual atau dibantu AI dari materi PDF.</span></li>
                    <li><strong>Bagikan Kode Kuis</strong><span>Mahasiswa membuka kuis dengan kode tersebut.</span></li>
                    <li><strong>Pantau nilai dan analisis</strong><span>Lihat hasil, analitik soal, dan unduh rekap Excel.</span></li>
                </ol>
            </div>

            <div class="km-flow">
                <div class="km-flow-head">
                    <span class="km-ico green"><svg aria-hidden="true"><use href="#i-users"/></svg></span>
                    <div><h3>Alur Mahasiswa</h3><p>Mengikuti perkuliahan dan kuis</p></div>
                </div>
                <ol class="km-steps">
                    <li><strong>Daftar sebagai Mahasiswa</strong><span>Lengkapi data diri dan verifikasi email.</span></li>
                    <li><strong>Login ke aplikasi</strong><span>Gunakan akun mahasiswa Anda.</span></li>
                    <li><strong>Akses materi dan pertemuan</strong><span>Baca materi PDF dan referensi dari dosen.</span></li>
                    <li><strong>Scan QR untuk absensi</strong><span>Lakukan saat QR ditampilkan di kelas.</span></li>
                    <li><strong>Masukkan Kode Kuis</strong><span>Gunakan kode yang dibagikan dosen.</span></li>
                    <li><strong>Kerjakan kuis online</strong><span>Soal tampil bertahap dengan timer.</span></li>
                    <li><strong>Lihat hasil kuis</strong><span>Pantau hasil dan nilai Anda.</span></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<!-- ==================== TENTANG ==================== -->
<section class="km-section alt" id="tentang">
    <div class="km-container">
        <div class="km-about">
            <div>
                <span class="km-kicker">Tentang</span>
                <h2>Teknologi yang Membantu Proses Belajar Mengajar.</h2>
                <p>KelasMerdeka.com hadir untuk membantu dosen dan mahasiswa menjalankan aktivitas perkuliahan secara lebih praktis, terstruktur, dan digital.</p>
                <p class="km-quote">Kami percaya teknologi seharusnya membantu pekerjaan menjadi lebih mudah, bukan menambah kerumitan.</p>
                <p>Platform perkuliahan digital untuk membantu dosen dan mahasiswa menjalankan kegiatan akademik sehari-hari.</p>
            </div>

            <div class="km-about-media">
                <div class="km-about-list">
                    <div>
                        <svg aria-hidden="true"><use href="#i-book"/></svg>
                        <span><b>Materi dan pertemuan terpusat</b><span>Mahasiswa menemukan bahan kuliah langsung di aplikasi.</span></span>
                    </div>
                    <div>
                        <svg aria-hidden="true"><use href="#i-spark"/></svg>
                        <span><b>AI sebagai pembantu, bukan pengganti</b><span>Dosen tetap memegang kendali atas kualitas soal.</span></span>
                    </div>
                    <div>
                        <svg aria-hidden="true"><use href="#i-chart"/></svg>
                        <span><b>Evaluasi berbasis data</b><span>Nilai dan analitik membantu perbaikan pembelajaran.</span></span>
                    </div>
                    <div>
                        <svg aria-hidden="true"><use href="#i-shield-check"/></svg>
                        <span><b>Akses sesuai peran</b><span>Fitur dosen dan mahasiswa dipisahkan sesuai hak akses.</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ==================== FAQ ==================== -->
<section class="km-section" id="faq">
    <div class="km-container">
        <div class="km-section-head">
            <span class="km-kicker">FAQ</span>
            <h2>Pertanyaan yang Sering Diajukan</h2>
        </div>

        <div class="km-faq">
            <details>
                <summary>Apa itu KelasMerdeka.com?</summary>
                <div class="km-faq-body">KelasMerdeka.com adalah platform digital untuk membantu dosen dan mahasiswa mengelola kegiatan perkuliahan, mulai dari materi, absensi, hingga kuis online.</div>
            </details>
            <details>
                <summary>Apakah KelasMerdeka.com berbayar?</summary>
                <div class="km-faq-body">KelasMerdeka tidak menerapkan biaya langganan bulanan maupun tahunan untuk fitur yang tersedia. Penggunaan layanan mengikuti fungsi dan ketentuan aplikasi.</div>
            </details>
            <details>
                <summary>Apakah dosen dapat membuat soal menggunakan AI?</summary>
                <div class="km-faq-body">Ya. Dosen dapat mengunggah materi PDF dan menggunakan bantuan AI untuk membuat soal kuis. Soal yang dihasilkan tetap dapat diperiksa dan diedit sebelum digunakan.</div>
            </details>
            <details>
                <summary>Apakah soal kuis harus dibuat menggunakan AI?</summary>
                <div class="km-faq-body">Tidak. Dosen dapat membuat soal secara manual maupun menggunakan bantuan AI.</div>
            </details>
            <details>
                <summary>Apakah mahasiswa bisa mengunduh materi PDF?</summary>
                <div class="km-faq-body">Materi PDF dirancang untuk dibaca melalui aplikasi. Akses dan perlindungan file mengikuti mekanisme yang diterapkan aplikasi.</div>
            </details>
            <details>
                <summary>Apakah mahasiswa memerlukan Kode Kuis?</summary>
                <div class="km-faq-body">Ya. Mahasiswa menggunakan Kode Kuis yang dibagikan dosen untuk membuka kuis.</div>
            </details>
            <details>
                <summary>Apakah hasil kuis dapat diunduh?</summary>
                <div class="km-faq-body">Dosen dapat mengunduh rekap nilai dalam format Excel melalui fitur yang tersedia.</div>
            </details>
            <details>
                <summary>Bagaimana cara mendaftar sebagai dosen?</summary>
                <div class="km-faq-body">Klik tombol Daftar, pilih Pendaftaran Dosen, isi formulir, lalu verifikasi email institusi.</div>
            </details>
            <details>
                <summary>Apakah mahasiswa juga dapat menggunakan aplikasi?</summary>
                <div class="km-faq-body">Ya. Mahasiswa dapat mendaftar dan menggunakan fasilitas yang tersedia sesuai hak aksesnya.</div>
            </details>
        </div>
    </div>
</section>

<!-- ==================== KONTAK ==================== -->
<section class="km-section alt km-contact-section" id="kontak">
    <div class="km-container">
        <div class="km-contact-wrap">
            <div class="km-contact-intro">
                <span class="km-kicker">Kontak</span>
                <h2>Hubungi Tim KelasMerdeka</h2>
                <p>Punya pertanyaan, masukan, atau membutuhkan informasi lebih lanjut mengenai KelasMerdeka? Silakan kirimkan pesan kepada tim kami.</p>
                <ul class="km-contact-points">
                    <li><svg aria-hidden="true"><use href="#i-search"/></svg> Pertanyaan &amp; informasi</li>
                    <li><svg aria-hidden="true"><use href="#i-heart"/></svg> Masukan &amp; saran</li>
                    <li><svg aria-hidden="true"><use href="#i-shield-check"/></svg> Laporan kendala</li>
                </ul>
                <p class="km-contact-privacy-line">
                    <svg aria-hidden="true"><use href="#i-shield"/></svg>
                    Data Anda hanya digunakan untuk menindaklanjuti pesan ini.
                </p>
            </div>

            <div class="km-contact">
                <?php if ($cSuccess): ?>
                    <div class="km-contact-panel is-success" role="status">
                        <span class="km-contact-panel-icon"><svg aria-hidden="true"><use href="#i-check-circle"/></svg></span>
                        <h3>&#10003; Pesan Berhasil Dikirim</h3>
                        <p>Kami akan menghubungi Anda kembali.</p>
                        <p>Terima kasih telah menghubungi Tim KelasMerdeka.</p>
                        <p class="km-contact-note">
                            Formulir dinonaktifkan sementara untuk perangkat ini.
                            <?= $cRetryText !== '' ? esc($cRetryText) : 'Anda dapat mengirimkan pesan baru setelah 24 jam.' ?>
                        </p>
                    </div>
                <?php elseif ($cBlocked): ?>
                    <div class="km-contact-panel is-locked" role="status">
                        <span class="km-contact-panel-icon"><svg aria-hidden="true"><use href="#i-clock"/></svg></span>
                        <h3>Pesan Sudah Pernah Dikirim</h3>
                        <p>Anda sudah mengirimkan pesan kepada Tim KelasMerdeka. Kami akan menghubungi Anda kembali.</p>
                        <p class="km-contact-note">
                            <?= $cRetryText !== '' ? esc($cRetryText) : 'Anda dapat mengirimkan pesan baru setelah 24 jam.' ?>
                        </p>
                    </div>
                <?php elseif (! $cEnabled): ?>
                    <div class="km-contact-panel is-locked" role="status">
                        <span class="km-contact-panel-icon"><svg aria-hidden="true"><use href="#i-lock"/></svg></span>
                        <h3>Formulir Sedang Tidak Tersedia</h3>
                        <p>Silakan coba lagi beberapa saat lagi.</p>
                    </div>
                <?php else: ?>
                    <form class="km-contact-form" id="contactForm" method="post" action="<?= base_url('/contact/send') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="contact_form_token" value="<?= esc($cToken, 'attr') ?>">

                        <div class="km-hp" aria-hidden="true">
                            <label for="contactWebsite">Website</label>
                            <input type="text" id="contactWebsite" name="website" value="" tabindex="-1" autocomplete="off">
                        </div>

                        <?php if ($cErrors !== []): ?>
                            <div class="km-contact-alert" role="alert">
                                <strong>Pesan belum berhasil dikirim.</strong>
                                <ul>
                                    <?php foreach ($cErrors as $error): ?>
                                        <li><?= esc((string) $error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="km-contact-grid">
                            <div class="km-field">
                                <label for="contactName">Nama Lengkap</label>
                                <input type="text" id="contactName" name="contact_name" value="<?= esc((string) ($cValues['contact_name'] ?? ''), 'attr') ?>"
                                       maxlength="80" minlength="3" autocomplete="name" placeholder="Nama lengkap" required>
                            </div>

                            <div class="km-field">
                                <label for="contactEmail">Alamat Email</label>
                                <input type="email" id="contactEmail" name="contact_email" value="<?= esc((string) ($cValues['contact_email'] ?? ''), 'attr') ?>"
                                       maxlength="120" autocomplete="email" placeholder="nama@contoh.com" required>
                            </div>

                            <div class="km-field km-field-full">
                                <label for="contactWhatsapp">Nomor WhatsApp</label>
                                <input type="tel" id="contactWhatsapp" name="contact_whatsapp" value="<?= esc((string) ($cValues['contact_whatsapp'] ?? ''), 'attr') ?>"
                                       maxlength="24" autocomplete="tel" inputmode="tel" placeholder="081234567890" required>
                                <small>Contoh: 081234567890 atau +6281234567890.</small>
                            </div>

                            <div class="km-field km-field-full">
                                <label for="contactMessage">Pesan Anda</label>
                                <textarea id="contactMessage" name="contact_message" rows="4" maxlength="<?= $cMaxLength ?>" minlength="5"
                                          placeholder="Tuliskan pesan Anda..." required><?= esc((string) ($cValues['contact_message'] ?? '')) ?></textarea>
                                <small class="km-char-count"><span id="contactMsgCount">0</span>/<?= $cMaxLength ?> karakter</small>
                            </div>

                            <div class="km-field km-field-full">
                                <label for="contactCaptcha">Masukkan karakter pada gambar</label>
                                <div class="km-captcha-row">
                                    <img id="contactCaptchaImage" class="km-captcha-img"
                                         src="<?= base_url('/contact/captcha') ?>?t=<?= time() ?>"
                                         alt="Gambar CAPTCHA" width="240" height="80">
                                    <input type="text" id="contactCaptcha" name="contact_captcha" value=""
                                           maxlength="5" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                           placeholder="5 karakter" required>
                                    <button type="button" class="km-captcha-refresh" id="contactCaptchaRefresh">Muat ulang</button>
                                </div>
                            </div>
                        </div>

                        <div class="km-contact-actions">
                            <button type="submit" class="km-btn km-btn-primary" id="contactSubmit">Kirim Pesan</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ==================== CTA PENUTUP ==================== -->
<section class="km-final">
    <div class="km-container">
        <h2>Yuk, Mulai Kelola Perkuliahan dengan Lebih Cerdas!</h2>
        <p>Materi lebih tertata, soal lebih mudah dibuat, presensi lebih praktis, dan hasil belajar lebih mudah dievaluasi — untuk dosen dan mahasiswa.</p>
        <div class="km-final-cta">
            <a href="<?= base_url('/register') ?>" class="km-btn km-btn-light">Daftar</a>
            <a href="<?= base_url('/auth') ?>" class="km-btn km-btn-ghost-light">Login ke KelasMerdeka</a>
        </div>
    </div>
</section>

</main>

<!-- ==================== FOOTER ==================== -->
<footer class="km-footer">
    <div class="km-container">
        <div class="km-footer-grid">
            <div>
                <a href="#beranda" class="km-brand">
                    <span class="km-brand-mark"><svg aria-hidden="true"><use href="#i-graduation"/></svg></span>
                    <span>KelasMerdeka.com<small>Platform Perkuliahan Digital</small></span>
                </a>
                <p>Platform digital untuk membantu pengelolaan kegiatan perkuliahan dosen dan mahasiswa.</p>
            </div>
            <div>
                <h4>Navigasi</h4>
                <ul>
                    <li><a href="#beranda">Beranda</a></li>
                    <li><a href="#fitur-dosen">Fitur Dosen</a></li>
                    <li><a href="#fitur-mahasiswa">Fitur Mahasiswa</a></li>
                    <li><a href="#cara-kerja">Cara Kerja</a></li>
                </ul>
            </div>
            <div>
                <h4>Akun</h4>
                <ul>
                    <li><a href="<?= base_url('/auth') ?>">Login</a></li>
                    <li><a href="<?= base_url('/register') ?>">Daftar</a></li>
                </ul>
            </div>
            <div>
                <h4>Informasi</h4>
                <ul>
                    <li><a href="#biaya">Informasi Biaya</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <li><a href="#tentang">Tentang</a></li>
                    <li><a href="#kontak">Kontak</a></li>
                </ul>
            </div>
        </div>
        <div class="km-footer-bottom">
            <span>&copy; <?= date('Y') ?> KelasMerdeka.com. Seluruh hak cipta dilindungi.</span>
            <span>Platform perkuliahan digital untuk dosen dan mahasiswa.</span>
        </div>
    </div>
</footer>

<script src="<?= base_url('/assets/js/landing.js') ?>" defer></script>
</body>
</html>
