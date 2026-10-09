<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Maintenance — <?= esc($appName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-1: #0B1220;
            --bg-2: #1E293B;
            --surface: rgba(30, 41, 59, 0.55);
            --border: rgba(71, 85, 105, 0.35);
            --text-1: #F8FAFC;
            --text-2: #CBD5E1;
            --text-3: #94A3B8;
            --text-4: #64748B;
            --primary: #4F46E5;
            --primary-2: #7C3AED;
            --info: #60A5FA;
            --success: #34D399;
        }

        html, body { height: 100%; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background:
                radial-gradient(circle at 20% 20%, rgba(79,70,229,0.18), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(124,58,237,0.15), transparent 50%),
                linear-gradient(135deg, var(--bg-1) 0%, var(--bg-2) 50%, var(--bg-1) 100%);
            background-attachment: fixed;
            color: var(--text-1);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .wrap {
            max-width: 560px;
            width: 100%;
            text-align: center;
        }

        .logo {
            width: 80px; height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            color: #fff;
            font-size: 2.2rem;
            box-shadow: 0 20px 50px rgba(79,70,229,0.45);
            animation: pulse 3s ease-in-out infinite;
        }
        @media (min-width: 640px) {
            .logo { width: 96px; height: 96px; font-size: 2.6rem; border-radius: 24px; margin-bottom: 24px; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 20px 50px rgba(79,70,229,0.45); }
            50%      { transform: scale(1.06); box-shadow: 0 28px 70px rgba(79,70,229,0.55); }
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-1);
            letter-spacing: -0.02em;
        }
        @media (min-width: 640px) { h1 { font-size: 2rem; } }

        .subtitle {
            color: var(--text-3);
            margin-bottom: 24px;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        @media (min-width: 640px) { .subtitle { margin-bottom: 32px; } }

        .card {
            background: var(--surface);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 14px;
            text-align: left;
        }
        @media (min-width: 640px) { .card { padding: 24px; border-radius: 16px; margin-bottom: 16px; } }

        .card-title {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 8px 0;
            font-size: 0.92rem;
            color: var(--text-2);
        }
        .item + .item { border-top: 1px solid rgba(71, 85, 105, 0.18); }
        .item i { color: var(--info); width: 18px; text-align: center; flex-shrink: 0; margin-top: 2px; }
        .item strong { color: var(--text-1); font-weight: 600; }

        .spinner-box {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 0;
        }
        .spinner {
            display: inline-block;
            width: 18px; height: 18px;
            border: 2.5px solid rgba(96, 165, 250, 0.2);
            border-top-color: var(--info);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .footer {
            margin-top: 20px;
            font-size: 0.78rem;
            color: var(--text-4);
            line-height: 1.5;
        }
        @media (min-width: 640px) { .footer { margin-top: 28px; } }

        .pulse-dot {
            display: inline-block;
            width: 8px; height: 8px;
            background: var(--success);
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
            animation: blink 1.5s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.35; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="logo"><i class="bi bi-tools"></i></div>

        <h1>Sedang Dalam Pemeliharaan</h1>
        <p class="subtitle">
            Mohon maaf atas ketidaknyamanannya. Aplikasi sedang dalam pemeliharaan rutin untuk meningkatkan kualitas layanan.
        </p>

        <div class="card">
            <div class="card-title"><i class="bi bi-info-circle"></i> Informasi</div>
            <div class="item">
                <i class="bi bi-wrench-adjustable-circle"></i>
                <span>Sistem sedang dalam perbaikan</span>
            </div>
            <div class="item">
                <i class="bi bi-clock-history"></i>
                <span>Estimasi kembali: <strong>30 – 60 menit</strong></span>
            </div>
            <div class="item">
                <i class="bi bi-shield-check"></i>
                <span>Data Anda aman dan tersimpan dengan baik</span>
            </div>
        </div>

        <div class="card">
            <div class="card-title"><i class="bi bi-activity"></i> Status</div>
            <div class="spinner-box">
                <span class="spinner"></span>
                <span style="color: var(--text-2); font-size: 0.92rem;">Sedang memperbarui sistem...</span>
            </div>
            <div class="spinner-box" style="margin-top: 6px;">
                <span class="pulse-dot"></span>
                <span style="color: var(--success); font-size: 0.88rem; font-weight: 500;">Server dalam mode pemeliharaan</span>
            </div>
        </div>

        <p class="footer">
            Butuh bantuan mendesak? Hubungi administrator melalui kanal resmi kampus.<br>
            &copy; <?= date('Y') ?> <?= esc($appName) ?>
        </p>
    </div>
</body>
</html>
