<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    #reader {
        width: 100%;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
    }
    #reader video {
        border-radius: 10px;
    }
    /* Sembunyikan tombol switch camera bawaan library — kita pakai kamera belakang saja */
    #reader__dashboard_section_swaplink,
    #reader__dashboard_section_csr span {
        display: none !important;
    }
    .scan-status {
        text-align: center;
        padding: 10px 14px;
        font-weight: 600;
        border-radius: 8px;
        margin-bottom: 12px;
        font-size: 0.95rem;
    }
    .scan-status.scanning { background: #e8f5e9; color: #2e7d32; }
    .scan-status.success  { background: #c8e6c9; color: #1b5e20; }
    .scan-status.error    { background: #ffebee; color: #c62828; }

    .zoom-bar {
        display: none;
        align-items: center;
        gap: 10px;
        background: #f5f5f5;
        padding: 10px 14px;
        border-radius: 8px;
        margin-top: 10px;
    }
    .zoom-bar.visible { display: flex; }
    .zoom-bar input[type="range"] {
        flex: 1;
        height: 6px;
        accent-color: #1976d2;
    }
    .zoom-bar .zoom-val {
        font-size: 0.9rem;
        font-weight: 700;
        min-width: 48px;
        text-align: center;
    }
    @media (max-width: 768px) {
        .col-scanner { order: -1; }
        #reader { min-height: 380px; }
    }
</style>

<div class="row g-3">
    <!-- SCANNER -->
    <div class="col-12 col-scanner">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="mb-2">📷 Scan QR Absensi</h5>
                <div id="scanStatus" class="scan-status scanning">Mengaktifkan kamera…</div>
                <div id="reader"></div>
                <!-- ZOOM -->
                <div class="zoom-bar" id="zoomBar">
                    <span>🔍</span>
                    <span style="font-size:.8rem;color:#888;">1x</span>
                    <input type="range" id="zoomRange" min="1" max="5" step="0.1" value="1" disabled>
                    <span style="font-size:.8rem;color:#888;">5x</span>
                    <span id="zoomVal" class="zoom-val">1.0x</span>
                    <button type="button" id="zoomReset" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;padding:3px 10px;">Reset</button>
                </div>
                <small class="text-muted d-block mt-2 text-center">Arahkan kamera ke QR Code yang ditampilkan dosen</small>
            </div>
        </div>
    </div>

    <!-- FORM -->
    <div class="col-12 col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Detail Absensi</h6>
                <form action="<?= base_url('/student/scan/submit') ?>" method="post" id="absenForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="meeting_id" id="meeting_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Token QR</label>
                        <input type="text" name="token_qr" id="token_qr" class="form-control" placeholder="Scan QR atau ketik manual">
                        <div id="tokenError" class="text-danger small d-none mt-1"><strong>⚠ Token QR salah, masukkan dengan benar !</strong></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" id="latitude" class="form-control" readonly placeholder="-">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" id="longitude" class="form-control" readonly placeholder="-">
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" id="geoBtn" class="btn btn-outline-primary flex-fill">📍 Ambil Lokasi</button>
                        <button type="submit" id="submitBtn" class="btn btn-primary flex-fill" disabled>✅ Kirim Absensi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    const meetingInput = document.getElementById('meeting_id');
    const tokenInput   = document.getElementById('token_qr');
    const submitBtn    = document.getElementById('submitBtn');
    const geoBtn       = document.getElementById('geoBtn');
    const scanStatus   = document.getElementById('scanStatus');
    const zoomRange    = document.getElementById('zoomRange');
    const zoomVal      = document.getElementById('zoomVal');
    const zoomBar      = document.getElementById('zoomBar');
    const zoomResetBtn = document.getElementById('zoomReset');
    const tokenError   = document.getElementById('tokenError');

    let html5QrCode = null;
    let isScanning  = false;
    let scanned     = false;
    let trackRef    = null;

    // ── Tampilkan / sembunyikan error token ──
    function showTokenError() {
        tokenInput.classList.add('is-invalid');
        tokenError.classList.remove('d-none');
        window.alert('Token QR salah, masukkan dengan benar !');
    }

    function clearTokenError() {
        tokenInput.classList.remove('is-invalid');
        tokenError.classList.add('d-none');
    }

    // ── Validasi Token QR: harus ada meeting_id + token hex 32 karakter ──
    function isTokenValid() {
        const mid = meetingInput.value.trim();
        const tok = tokenInput.value.trim();
        return mid !== '' && tok !== '' && /^[a-f0-9]+$/i.test(tok);
    }

    // ── Cek kesiapan form: semua field harus terisi ──
    function checkReady() {
        const lat = document.getElementById('latitude').value.trim();
        const lng = document.getElementById('longitude').value.trim();
        submitBtn.disabled = !(isTokenValid() && lat !== '' && lng !== '');
    }

    // ── Reset lokasi (dipakai saat token berubah) ──
    function resetLocation() {
        document.getElementById('latitude').value  = '';
        document.getElementById('longitude').value = '';
        geoBtn.disabled = false;
        geoBtn.innerHTML = '📍 Ambil Lokasi';
        geoBtn.classList.remove('btn-outline-success');
        geoBtn.classList.add('btn-outline-primary');
        checkReady();
    }

    // ── Jika token diubah manual → reset state tapi jangan hapus meeting_id ──
    tokenInput.addEventListener('input', function () {
        clearTokenError();
        scanned = false;
        tokenInput.classList.remove('bg-light');
        resetLocation();
    });

    // ── Tombol Ambil Lokasi ──
    geoBtn.addEventListener('click', async function () {
        var btn = this;
        var tok = tokenInput.value.trim();

        // Tolak jika token kosong
        if (tok === '') {
            showTokenError();
            return;
        }

        // Auto-parse jika user ketik payload lengkap: "meeting_id|token_qr"
        if (tok.indexOf('|') !== -1) {
            var parts = tok.split('|');
            if (parts.length === 2 && parts[0].trim() !== '' && parts[1].trim() !== '') {
                meetingInput.value = parts[0].trim();
                tokenInput.value   = parts[1].trim();
                tok = tokenInput.value;
            }
        }

        // ── JALUR MANUAL: meeting_id belum ada → validasi token via AJAX ──
        if (meetingInput.value.trim() === '') {
            btn.disabled = true;
            btn.innerHTML = '⏳ Memvalidasi token...';

            try {
                var url  = '<?= base_url('student/scan/lookup-token') ?>?token_qr=' + encodeURIComponent(tok);
                var resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                var data = await resp.json();

                if (! data.found) {
                    showTokenError();
                    btn.disabled = false;
                    btn.innerHTML = '📍 Ambil Lokasi';
                    return;                          // BATALKAN — token tidak cocok
                }

                // Token cocok → simpan meeting_id dari server
                meetingInput.value = data.meeting_id;
                clearTokenError();

            } catch (e) {
                window.alert('Gagal menghubungi server. Periksa koneksi dan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '📍 Ambil Lokasi';
                return;
            }
        }

        // ── Token valid (scan atau manual) → ambil koordinat GPS ──
        if (! navigator.geolocation) {
            window.alert('Browser tidak mendukung geolokasi.');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '⏳ Mengambil lokasi...';

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                document.getElementById('latitude').value  = pos.coords.latitude;
                document.getElementById('longitude').value = pos.coords.longitude;
                btn.innerHTML = '✅ Lokasi Didapat';
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-outline-success');
                checkReady();
            },
            function (err) {
                window.alert('Gagal mengambil lokasi: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '📍 Ambil Lokasi';
                btn.classList.remove('btn-outline-success');
                btn.classList.add('btn-outline-primary');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    });


    // ── Zoom via MediaStreamTrack ──
    function applyZoom(val) {
        if (!trackRef) return;
        try {
            trackRef.applyConstraints({ advanced: [{ zoom: val }] });
            zoomVal.textContent = val.toFixed(1) + 'x';
        } catch (e) { console.warn('Zoom failed:', e); }
    }
    zoomRange.addEventListener('input', function () { applyZoom(parseFloat(zoomRange.value)); });
    zoomResetBtn.addEventListener('click', function () { zoomRange.value = 1; applyZoom(1); });

    // ── Callback QR berhasil discan ──
    function onScanSuccess(decodedText) {
        if (scanned) return;
        var chunks = decodedText.split('|');
        if (chunks.length !== 2) {
            scanStatus.className = 'scan-status error';
            scanStatus.textContent = '❌ Format QR tidak valid.';
            return;
        }
        var mId   = chunks[0].trim();
        var token = chunks[1].trim();
        if (!mId || !token) {
            scanStatus.className = 'scan-status error';
            scanStatus.textContent = '❌ Format QR tidak valid.';
            return;
        }

        scanned = true;
        meetingInput.value = mId;
        tokenInput.value   = token;
        tokenInput.classList.remove('is-invalid');
        tokenInput.classList.add('bg-light');   // visual: sudah di-scan (tidak readOnly)
        clearTokenError();
        resetLocation();   // reset lokasi — user harus klik Ambil Lokasi

        if (html5QrCode && isScanning) {
            html5QrCode.stop().catch(function () {});
            isScanning = false;
        }
        scanStatus.className = 'scan-status success';
        scanStatus.textContent = '✅ QR berhasil di-scan! Silakan klik "📍 Ambil Lokasi".';
        document.getElementById('absenForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        checkReady();
    }

    // ── Start Scanner ──
    async function startScanner() {
        html5QrCode = new Html5Qrcode('reader');

        try {
            const devices = await Html5Qrcode.getCameras();
            if (!devices || devices.length === 0) {
                scanStatus.className = 'scan-status error';
                scanStatus.textContent = '❌ Tidak ada kamera ditemukan.';
                return;
            }

            let deviceId = devices[0].id;
            for (const d of devices) {
                const lbl = (d.label || '').toLowerCase();
                if (lbl.includes('back') || lbl.includes('rear') || lbl.includes('environment') || lbl.includes('belakang')) {
                    deviceId = d.id;
                    break;
                }
            }
            if (devices.length > 1) deviceId = devices[devices.length - 1].id;

            await html5QrCode.start(
                deviceId,
                { fps: 15, qrbox: { width: 260, height: 260 }, aspectRatio: 1.0, disableFlip: false },
                onScanSuccess,
                function () {}
            );

            isScanning = true;
            scanStatus.textContent = '📷 Kamera aktif — arahkan ke QR Code';

            try {
                const videoEl = document.querySelector('#reader video');
                if (videoEl && videoEl.srcObject) {
                    const tracks = videoEl.srcObject.getVideoTracks();
                    if (tracks.length > 0) {
                        trackRef = tracks[0];
                        const caps = trackRef.getCapabilities();
                        if (caps && caps.zoom && caps.zoom.max > 1) {
                            zoomRange.min   = caps.zoom.min || 1;
                            zoomRange.max   = caps.zoom.max;
                            zoomRange.value = caps.zoom.min || 1;
                            zoomRange.disabled = false;
                            zoomBar.classList.add('visible');
                            zoomVal.textContent = (caps.zoom.min || 1).toFixed(1) + 'x';
                        }
                    }
                }
            } catch (e) { /* Zoom tidak didukung — abaikan */ }

        } catch (err) {
            console.error('Camera error:', err);
            scanStatus.className = 'scan-status error';
            let msg = err.message || 'Terjadi kesalahan tidak diketahui.';
            if (err.name === 'NotAllowedError') {
                msg = 'Izin kamera ditolak. Mohon izinkan akses kamera di pengaturan browser.';
            } else if (err.name === 'NotFoundError') {
                msg = 'Kamera tidak ditemukan pada perangkat ini.';
            } else if (err.name === 'NotReadableError') {
                msg = 'Kamera sedang digunakan aplikasi lain.';
            }
            scanStatus.textContent = '❌ Gagal mengakses kamera: ' + msg;
        }
    }

    document.addEventListener('DOMContentLoaded', startScanner);
    window.addEventListener('beforeunload', function () {
        if (html5QrCode && isScanning) html5QrCode.stop().catch(function () {});
    });
</script>
<?= $this->endSection() ?>
