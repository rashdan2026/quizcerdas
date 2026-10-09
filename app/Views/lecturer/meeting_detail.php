<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<style>
    .qr-wrapper { display:flex; justify-content:center; align-items:center; background:#fff; padding:20px; border-radius:16px; border:2px dashed #E0E7FF; }
    .qr-wrapper img { max-width:100%; height:auto; image-rendering:crisp-edges; }
    .qr-timer { font-size:1.6rem; font-weight:800; color:#059669; font-family:'Courier New',monospace; letter-spacing:1px; }
    .qr-timer.warning { color:#D97706; }
    .qr-timer.danger  { color:#DC2626; animation:pulse 1s infinite; }
    .qr-timer.expired { color:#94A3B8; font-size:1.1rem; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .token-box { background:#F8FAFC; padding:10px 16px; border-radius:8px; font-family:'Courier New',monospace; word-break:break-all; font-size:.9rem; border:1.5px solid #E2E8F0; }
    @media (min-width:992px) { .qr-wrapper img { width:460px; height:460px; } }
</style>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= base_url('/lecturer/meetings') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-qr-code me-2"></i>QR Absensi</h4>
        <p class="text-muted small mb-0 mt-1"><?= esc($meeting['kode_mk']) ?> — <?= esc($meeting['nama_mk']) ?></p>
    </div>
</div>

<div class="row g-4">
    <!-- QR Card -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-qr-code me-2 text-primary"></i>QR Code Real-time</span>
                <span class="badge bg-success rounded-pill"><i class="bi bi-broadcast me-1"></i>Live</span>
            </div>
            <div class="card-body text-center p-4">
                <div class="qr-wrapper mb-3">
                    <img src="<?= esc($qrUrl) ?>" id="qrImage" alt="QR Token">
                </div>
                <div class="mb-3">
                    <div class="text-muted small mb-1"><i class="bi bi-clock me-1"></i>Token berlaku selama</div>
                    <span id="countdown" class="qr-timer">45 detik</span>
                </div>
                <div>
                    <div class="text-muted small mb-2"><i class="bi bi-key me-1"></i>Payload Token</div>
                    <div class="token-box"><code id="qrPayload"><?= esc($rawToken) ?></code></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Card -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-info-circle me-2 text-primary"></i>Detail Pertemuan
            </div>
            <div class="card-body p-4">
                <div class="row g-0">
                    <?php
                    $ts    = strtotime($meeting['created_at']);
                    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                    $bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                    $tgl   = $hari[date('w',$ts)] . ', ' . date('d',$ts) . ' ' . $bulan[(int)date('n',$ts)-1] . ' ' . date('Y',$ts);
                    $items = [
                        ['bi-person-circle','Dosen', $meeting['nama_dosen'] ?? session('user_name')],
                        ['bi-book','Matakuliah', $meeting['kode_mk'].' — '.$meeting['nama_mk']],
                        ['bi-hash','Pertemuan ke', '#'.$meeting['pertemuan_ke']],
                        ['bi-card-heading','Judul', $meeting['judul']],
                        ['bi-calendar','Tanggal', $tgl],
                    ];
                    foreach ($items as [$icon, $label, $val]):
                    ?>
                    <div class="col-12 py-2" style="border-bottom:1px solid #F1F5F9;">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width:32px;height:32px;background:#EEF2FF;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi <?= $icon ?>" style="color:#4F46E5;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div class="text-muted" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;"><?= $label ?></div>
                                <div class="fw-500 text-dark" style="font-size:.9rem;"><?= esc($val) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="alert alert-info mt-3 mb-0 py-2">
                    <i class="bi bi-arrow-repeat me-1"></i>Token diperbarui otomatis setiap <strong>45 detik</strong>. Mahasiswa scan QR ini untuk absensi.
                </div>
            </div>
        </div>
    </div>

    <?php if (! empty($links)): ?>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-link-45deg me-2 text-primary"></i>Link Referensi (<?= count($links) ?>)
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($links as $lk): ?>
                        <div class="list-group-item d-flex align-items-center gap-3">
                            <span class="badge bg-<?= $lk['link_type'] === 'youtube' ? 'danger' : 'secondary' ?>-soft fs-6">
                                <?= $lk['link_type'] === 'youtube' ? '📺 YouTube' : '📄 File' ?>
                            </span>
                            <a href="<?= esc($lk['url']) ?>" target="_blank" rel="noopener noreferrer" class="text-truncate flex-grow-1">
                                <?= esc($lk['url']) ?>
                            </a>
                            <?php if ($lk['link_type'] === 'youtube'): ?>
                                <small class="text-muted">→ di-embed di halaman mahasiswa</small>
                            <?php else: ?>
                                <small class="text-muted">→ terbuka di tab baru</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    const refreshUrl  = "<?= base_url('/lecturer/meetings/' . $meeting['id'] . '/token') ?>";
    const csrfName    = "<?= csrf_token() ?>";
    let csrfHash      = "<?= csrf_hash() ?>";
    const countdownEl = document.getElementById('countdown');
    const initialLeft = <?= (int) $remaining ?>;
    let timeLeft      = Math.max(1, initialLeft);
    let isRefreshing  = false;

    async function refreshToken() {
        if (isRefreshing) return;
        isRefreshing = true;
        try {
            const body = new URLSearchParams();
            body.append(csrfName, csrfHash);
            const response = await fetch(refreshUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
            if (!response.ok) return;
            const data = await response.json();
            csrfHash = data.csrfHash ?? csrfHash;
            document.getElementById('qrImage').src = data.qr_url;
            document.getElementById('qrPayload').innerText = data.token_qr;
            timeLeft = 45;
            tick();
        } finally {
            isRefreshing = false;
        }
    }

    function tick() {
        if (timeLeft <= 0) {
            countdownEl.textContent = '0 detik';
            countdownEl.className = 'qr-timer expired';
            refreshToken();
            return;
        }
        countdownEl.textContent = timeLeft + ' detik';
        if (timeLeft <= 5) countdownEl.className = 'qr-timer danger';
        else if (timeLeft <= 15) countdownEl.className = 'qr-timer warning';
        else countdownEl.className = 'qr-timer';
        timeLeft--;
    }

    if (initialLeft <= 0) {
        countdownEl.textContent = '0 detik';
        countdownEl.className = 'qr-timer expired';
        refreshToken();
    } else {
        tick();
    }
    setInterval(tick, 1000);
    setInterval(refreshToken, 45000);
</script>
<?= $this->endSection() ?>
