<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    #reader {
        width: 100%;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
        min-height: 320px;
    }
    #reader video {
        border-radius: 10px;
    }
    #reader__dashboard_section_swaplink,
    #reader__dashboard_section_csr span {
        display: none !important;
    }
    .scan-status {
        padding: 8px 12px;
        font-weight: 500;
        border-radius: 8px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .scan-status.scanning { background: #FFF3CD; color: #856404; }
    .scan-status.success  { background: #D1E7DD; color: #0F5132; }
    .scan-status.error    { background: #F8D7DA; color: #842029; }

    .status-card {
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        transition: all .2s;
    }
    .status-card .icon-circle {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .status-card .label { font-size: .7rem; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; }
    .status-card .value { font-weight: 600; font-size: .92rem; }
    .status-card.pending { background: #FFF3CD; border: 1px solid #FFE69C; }
    .status-card.pending .icon-circle { background: #FFF3CD; color: #997404; }
    .status-card.ok { background: #D1E7DD; border: 1px solid #A3CFBA; }
    .status-card.ok .icon-circle { background: #198754; color: #fff; }
    .status-card.error { background: #F8D7DA; border: 1px solid #F1AEB5; }
    .status-card.error .icon-circle { background: #DC3545; color: #fff; }

    .help-tile {
        background: #F8F9FA;
        border: 1px solid #E9ECEF;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 8px;
    }
    .help-tile .bi { font-size: 1.4rem; }

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
    .device-status-row .col-6 + .col-6 {
        border-left: 1px dashed #DEE2E6;
    }
    @media (max-width: 768px) {
        .col-scanner { order: -1; }
        #reader { min-height: 380px; }
        .device-status-row .col-6 + .col-6 {
            border-left: none;
            border-top: 1px dashed #DEE2E6;
            padding-top: 8px;
            margin-top: 8px;
        }
    }
</style>

<div class="row g-3">
    <!-- SCANNER -->
    <div class="col-12 col-scanner">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">📷 Scan QR Absensi</h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="bi bi-question-circle me-1"></i>Lihat Petunjuk
                    </button>
                </div>

                <!-- Status Perangkat (kamera + lokasi) -->
                <div class="row device-status-row g-0">
                    <div class="col-6 py-2 px-2">
                        <div id="cameraStatusCard" class="status-card pending">
                            <div class="icon-circle"><i class="bi bi-camera-video"></i></div>
                            <div class="flex-grow-1">
                                <div class="label">Kamera</div>
                                <div class="value" id="cameraStatusText">Menunggu izin</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 py-2 px-2">
                        <div id="locationStatusCard" class="status-card pending">
                            <div class="icon-circle"><i class="bi bi-geo-alt"></i></div>
                            <div class="flex-grow-1">
                                <div class="label">Lokasi</div>
                                <div class="value" id="locationStatusText">Belum diambil</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Area Scanner + pesan inline -->
                <div id="reader" class="mt-2"></div>

                <!-- Pesan inline di bawah scanner (untuk error friendly) -->
                <div id="inlineError" class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none" style="font-size:.88rem;"></div>

                <!-- ZOOM -->
                <div class="zoom-bar" id="zoomBar">
                    <span>🔍</span>
                    <span style="font-size:.8rem;color:#888;">1x</span>
                    <input type="range" id="zoomRange" min="1" max="5" step="0.1" value="1" disabled>
                    <span style="font-size:.8rem;color:#888;">5x</span>
                    <span id="zoomVal" class="zoom-val">1.0x</span>
                    <button type="button" id="zoomReset" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;padding:3px 10px;">Reset</button>
                </div>
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
                        <div id="tokenError" class="text-danger small d-none mt-1"><strong>⚠ Token QR salah, masukkan dengan benar!</strong></div>
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

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL PETUNJUK — Android / iPhone / Desktop
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#EEF2FF,#DBEAFE);">
                <h5 class="modal-title"><i class="bi bi-life-preserver me-2 text-primary"></i>Panduan Scan Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-android"><i class="bi bi-android2 me-1"></i>Android</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-iphone"><i class="bi bi-apple me-1"></i>iPhone Safari</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-desktop"><i class="bi bi-laptop me-1"></i>Desktop / Laptop</button></li>
                </ul>

                <!-- Langkah Umum -->
                <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.88rem;">
                    <strong>Langkah Absensi:</strong> Izinkan kamera → Scan QR Code → Nyalakan GPS → Ambil Lokasi → Kirim Absensi
                </div>

                <div class="tab-content">
                    <!-- ANDROID -->
                    <div class="tab-pane fade show active" id="tab-android">
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-camera-video text-primary"></i>
                                <div>
                                    <strong>Izinkan Kamera (Chrome Android)</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Saat pop-up izin muncul, pilih <strong>"Izinkan / Allow"</strong></li>
                                        <li>Jika pop-up tidak muncul, klik ikon <strong>gembok 🔒</strong> di address bar</li>
                                        <li>Pilih <strong>"Izin / Permissions"</strong> → aktifkan <strong>Kamera</strong></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt text-primary"></i>
                                <div>
                                    <strong>Aktifkan GPS & Izinkan Lokasi</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Buka <strong>Settings / Pengaturan</strong> HP</li>
                                        <li>Cari <strong>"Lokasi / Location"</strong> dan nyalakan</li>
                                        <li>Saat pop-up izin browser muncul, pilih <strong>"Izinkan / Allow"</strong></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IPHONE -->
                    <div class="tab-pane fade" id="tab-iphone">
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-camera-video text-primary"></i>
                                <div>
                                    <strong>Izinkan Kamera (Safari iOS)</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Buka <strong>Settings / Pengaturan</strong> iPhone</li>
                                        <li>Pilih <strong>Safari</strong> → <strong>Kamera</strong> → pilih <strong>"Ask" atau "Allow"</strong></li>
                                        <li>Atau saat pop-up browser muncul, pilih <strong>"Allow / Izinkan"</strong></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt text-primary"></i>
                                <div>
                                    <strong>Aktifkan Lokasi (Safari iOS)</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Buka <strong>Settings / Pengaturan</strong> iPhone</li>
                                        <li>Pilih <strong>Privacy & Security</strong> → <strong>Location Services</strong> → aktifkan</li>
                                        <li>Pilih <strong>Safari Websites</strong> → <strong>"Ask Next Time" atau "While Using"</strong></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DESKTOP -->
                    <div class="tab-pane fade" id="tab-desktop">
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-camera-video text-primary"></i>
                                <div>
                                    <strong>Izinkan Kamera (Chrome / Edge Desktop)</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Saat pop-up izin muncul di address bar, klik <strong>"Allow / Izinkan"</strong></li>
                                        <li>Jika terblokir, klik ikon <strong>gembok 🔒</strong> di address bar</li>
                                        <li>Pilih <strong>"Site settings"</strong> → aktifkan <strong>Camera</strong></li>
                                        <li>Refresh halaman (F5)</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="help-tile">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt text-primary"></i>
                                <div>
                                    <strong>Izinkan Lokasi (Chrome / Edge Desktop)</strong>
                                    <ol class="small mb-0 mt-1 ps-3">
                                        <li>Saat pop-up browser muncul, klik <strong>"Allow / Izinkan"</strong></li>
                                        <li>Pastikan GPS / Location service di OS Anda aktif</li>
                                        <li>Jika ditolak, cek ikon <strong>gembok 🔒</strong> di address bar → aktifkan Location</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL: Izin Kamera
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="cameraPermissionModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;">
            <div class="modal-body text-center p-4 p-md-5">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#FEF3C7,#FCD34D);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-camera-video" style="font-size:2.5rem;color:#92400E;"></i>
                </div>
                <h4 class="fw-bold mb-2" style="color:#92400E;">Kamera belum aktif</h4>
                <p class="text-muted mb-3" style="font-size:.95rem;">
                    Untuk memindai QR Code, izinkan akses kamera terlebih dahulu.
                    Setelah izin diberikan, scanner akan menyala otomatis.
                </p>
                <div class="help-tile text-start" style="margin-bottom:1rem;">
                    <div class="small">
                        <strong>Langkah singkat:</strong>
                        <ol class="mb-0 ps-3">
                            <li>Klik tombol <strong>Izinkan Kamera</strong> di bawah</li>
                            <li>Pada pop-up browser, pilih <strong>Allow / Izinkan</strong></li>
                            <li>Arahkan kamera ke QR Code</li>
                        </ol>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="cameraModalHelpBtn">
                        <i class="bi bi-question-circle me-1"></i>Lihat Petunjuk
                    </button>
                    <button type="button" class="btn btn-primary" id="cameraModalRetryBtn">
                        <i class="bi bi-camera-video me-1"></i>Izinkan Kamera
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL: Lokasi Tidak Tersedia
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="locationPermissionModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;">
            <div class="modal-body text-center p-4 p-md-5">
                <div style="width:80px;height:80px;margin:0 auto 20px;background:linear-gradient(135deg,#FEE2E2,#FCA5A5);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-geo-alt-fill" style="font-size:2.5rem;color:#991B1B;"></i>
                </div>
                <h4 class="fw-bold mb-2" style="color:#991B1B;" id="locationModalTitle">Lokasi belum tersedia</h4>
                <p class="text-muted mb-3" style="font-size:.95rem;" id="locationModalDesc">
                    Untuk melengkapi absensi, aktifkan GPS dan izinkan browser
                    mengakses lokasi Anda. Setelah itu tekan <strong>Ambil Lokasi</strong> lagi.
                </p>
                <div class="help-tile text-start" style="margin-bottom:1rem;">
                    <div class="small">
                        <strong>Langkah singkat:</strong>
                        <ol class="mb-0 ps-3">
                            <li>Nyalakan <strong>GPS / Location</strong> di perangkat</li>
                            <li>Klik tombol <strong>Izinkan Lokasi</strong> di bawah</li>
                            <li>Pada pop-up browser, pilih <strong>Allow / Izinkan</strong></li>
                            <li>Tekan <strong>Ambil Lokasi</strong> lagi</li>
                        </ol>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" id="locationModalHelpBtn">
                        <i class="bi bi-question-circle me-1"></i>Lihat Petunjuk
                    </button>
                    <button type="button" class="btn btn-danger" id="locationModalRetryBtn">
                        <i class="bi bi-geo-alt me-1"></i>Izinkan Lokasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL: Scan Gagal (existing, unchanged)
     ═══════════════════════════════════════════════════════════════════════════ -->
<?php $errMsg = session()->getFlashdata('error'); if ($errMsg): ?>
<div class="modal fade" id="scanErrorModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:18px;overflow:hidden;box-shadow:0 25px 60px rgba(220,38,38,.4);">
            <div class="modal-body text-center p-4 p-md-5">
                <div style="width:90px;height:90px;margin:0 auto 20px;background:linear-gradient(135deg,#FEE2E2,#FCA5A5);border-radius:50%;display:flex;align-items:center;justify-content:center;animation:shakeX .55s ease-in-out;">
                    <i class="bi bi-x-circle-fill" style="font-size:3.5rem;color:#DC2626;"></i>
                </div>
                <h4 class="fw-bold mb-2" style="color:#7F1D1D;">Scan Gagal</h4>
                <div class="alert alert-danger border-0 mb-3 py-2" style="background:#FEE2E2;color:#991B1B;border-radius:10px;font-size:.92rem;">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= esc($errMsg) ?>
                </div>
                <div class="text-muted small mb-4" style="line-height:1.55;">
                    <i class="bi bi-info-circle me-1"></i> Kemungkinan penyebab:
                    <ul class="text-start small mb-0 mt-1" style="padding-left:1.4rem;">
                        <li>QR Code sudah <strong>kedaluwarsa</strong> (refresh otomatis tiap 45 detik di layar dosen)</li>
                        <li>Anda scan QR Code dari <strong>pertemuan yang sudah selesai</strong></li>
                        <li>QR Code tidak valid / format salah</li>
                    </ul>
                </div>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light px-3" data-bs-dismiss="modal" onclick="window.history.length > 1 ? window.history.back() : (window.location.href='<?= base_url('/student/scan') ?>')">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </button>
                    <button type="button" class="btn btn-danger px-3" onclick="restartScanner()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Coba Scan Lagi
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
@keyframes shakeX {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-8px); }
    20%, 40%, 60%, 80% { transform: translateX(8px); }
}
</style>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    const meetingInput = document.getElementById('meeting_id');
    const tokenInput   = document.getElementById('token_qr');
    const submitBtn    = document.getElementById('submitBtn');
    const geoBtn       = document.getElementById('geoBtn');
    const inlineError  = document.getElementById('inlineError');
    const tokenError   = document.getElementById('tokenError');
    const cameraCard   = document.getElementById('cameraStatusCard');
    const cameraText   = document.getElementById('cameraStatusText');
    const locationCard = document.getElementById('locationStatusCard');
    const locationText = document.getElementById('locationStatusText');
    const zoomRange    = document.getElementById('zoomRange');
    const zoomVal      = document.getElementById('zoomVal');
    const zoomBar      = document.getElementById('zoomBar');
    const zoomResetBtn = document.getElementById('zoomReset');

    let html5QrCode = null;
    let isScanning  = false;
    let scanned     = false;
    let trackRef    = null;

    // ── Update status kamera (kuning=pending, hijau=ok, merah=error) ──
    function setCameraStatus(state, text) {
        cameraCard.classList.remove('pending', 'ok', 'error');
        cameraCard.classList.add(state);
        cameraText.textContent = text;
    }

    // ── Update status lokasi ──
    function setLocationStatus(state, text) {
        locationCard.classList.remove('pending', 'ok', 'error');
        locationCard.classList.add(state);
        locationText.textContent = text;
    }

    // ── Inline error message (untuk error scanner di dalam card) ──
    function showInlineError(msg) {
        inlineError.textContent = msg;
        inlineError.classList.remove('d-none');
    }
    function clearInlineError() {
        inlineError.classList.add('d-none');
    }

    // ── Modal helpers ──
    function showCameraModal() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cameraPermissionModal')).show();
    }
    function hideCameraModal() {
        const m = bootstrap.Modal.getInstance(document.getElementById('cameraPermissionModal'));
        if (m) m.hide();
    }
    function showLocationModal() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('locationPermissionModal')).show();
    }
    function hideLocationModal() {
        const m = bootstrap.Modal.getInstance(document.getElementById('locationPermissionModal'));
        if (m) m.hide();
    }
    function showHelpModal() {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('helpModal')).show();
    }

    // ── Tombol "Lihat Petunjuk" di dalam modal izin → buka modal petunjuk ──
    document.getElementById('cameraModalHelpBtn').addEventListener('click', function () {
        hideCameraModal();
        setTimeout(showHelpModal, 300);
    });
    document.getElementById('locationModalHelpBtn').addEventListener('click', function () {
        hideLocationModal();
        setTimeout(showHelpModal, 300);
    });

    // ── Tombol "Izinkan Kamera" di modal → restart scanner (akan trigger prompt izin browser) ──
    document.getElementById('cameraModalRetryBtn').addEventListener('click', function () {
        hideCameraModal();
        setTimeout(startScanner, 400);
    });

    // ── Tombol "Izinkan Lokasi" di modal → retry geolocation ──
    document.getElementById('locationModalRetryBtn').addEventListener('click', function () {
        hideLocationModal();
        setTimeout(requestLocation, 400);
    });

    // ── Tampilkan / sembunyikan error token ──
    function showTokenError() {
        tokenInput.classList.add('is-invalid');
        tokenError.classList.remove('d-none');
        window.alert('Token QR salah, masukkan dengan benar!');
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

    // ── Cek kesiapan form ──
    function checkReady() {
        const lat = document.getElementById('latitude').value.trim();
        const lng = document.getElementById('longitude').value.trim();
        submitBtn.disabled = !(isTokenValid() && lat !== '' && lng !== '');
    }

    // ── Reset lokasi ──
    function resetLocation() {
        document.getElementById('latitude').value  = '';
        document.getElementById('longitude').value = '';
        geoBtn.disabled = false;
        geoBtn.innerHTML = '📍 Ambil Lokasi';
        geoBtn.classList.remove('btn-outline-success');
        geoBtn.classList.add('btn-outline-primary');
        setLocationStatus('pending', 'Belum diambil');
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
                    return;
                }

                meetingInput.value = data.meeting_id;
                clearTokenError();

            } catch (e) {
                window.alert('Gagal menghubungi server. Periksa koneksi dan coba lagi.');
                btn.disabled = false;
                btn.innerHTML = '📍 Ambil Lokasi';
                return;
            }
        }

        requestLocation();
    });

    // ── Request lokasi (terpisah agar bisa dipanggil ulang) ──
    function requestLocation() {
        if (! navigator.geolocation) {
            document.getElementById('locationModalTitle').textContent = 'Browser tidak mendukung GPS';
            document.getElementById('locationModalDesc').textContent =
                'Browser Anda tidak mendukung fitur lokasi. Silakan gunakan browser modern (Chrome, Edge, Safari, Firefox).';
            showLocationModal();
            return;
        }

        geoBtn.disabled = true;
        geoBtn.innerHTML = '⏳ Mengambil lokasi...';
        setLocationStatus('pending', 'Mengambil...');

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                document.getElementById('latitude').value  = pos.coords.latitude;
                document.getElementById('longitude').value = pos.coords.longitude;
                geoBtn.innerHTML = '✅ Lokasi Berhasil Diambil';
                geoBtn.classList.remove('btn-outline-primary');
                geoBtn.classList.add('btn-outline-success');
                setLocationStatus('ok', 'Berhasil diambil');
                checkReady();
            },
            function (err) {
                geoBtn.disabled = false;
                geoBtn.innerHTML = '📍 Ambil Lokasi';
                geoBtn.classList.remove('btn-outline-success');
                geoBtn.classList.add('btn-outline-primary');

                // Pesan friendly berdasarkan error code
                let title = 'Lokasi belum tersedia';
                let desc  = 'Untuk melengkapi absensi, aktifkan GPS dan izinkan browser mengakses lokasi Anda. Setelah itu tekan Ambil Lokasi lagi.';
                if (err.code === err.PERMISSION_DENIED) {
                    title = 'Izin lokasi ditolak';
                    desc  = 'Browser tidak diizinkan mengakses lokasi. Silakan buka pengaturan browser lalu aktifkan izin lokasi untuk situs ini.';
                } else if (err.code === err.POSITION_UNAVAILABLE) {
                    title = 'GPS tidak aktif';
                    desc  = 'Sinyal GPS tidak terdeteksi. Pastikan GPS perangkat Anda aktif, lalu coba lagi.';
                } else if (err.code === err.TIMEOUT) {
                    title = 'Waktu habis';
                    desc  = 'Pengambilan lokasi terlalu lama. Pastikan sinyal GPS bagus, lalu coba lagi.';
                }
                document.getElementById('locationModalTitle').textContent = title;
                document.getElementById('locationModalDesc').textContent  = desc;
                setLocationStatus('error', title);
                showLocationModal();
                console.warn('Geolocation error:', err);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

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
            clearInlineError();
            return;
        }
        var mId   = chunks[0].trim();
        var token = chunks[1].trim();
        if (!mId || !token) {
            clearInlineError();
            return;
        }

        scanned = true;
        meetingInput.value = mId;
        tokenInput.value   = token;
        tokenInput.classList.remove('is-invalid');
        tokenInput.classList.add('bg-light');
        clearTokenError();
        clearInlineError();
        resetLocation();

        if (html5QrCode && isScanning) {
            html5QrCode.stop().catch(function () {});
            isScanning = false;
        }
        setCameraStatus('ok', 'Aktif · QR terbaca');
    }

    // ── Start Scanner ──
    async function startScanner() {
        // Tutup modal izin jika terbuka
        hideCameraModal();

        if (typeof Html5Qrcode === 'undefined') {
            setCameraStatus('error', 'Library QR belum dimuat');
            showInlineError('Gagal memuat library QR Code. Refresh halaman (F5).');
            return;
        }

        html5QrCode = new Html5Qrcode('reader');

        try {
            const devices = await Html5Qrcode.getCameras();
            if (!devices || devices.length === 0) {
                setCameraStatus('error', 'Kamera tidak ditemukan');
                showCameraModal();
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
            setCameraStatus('ok', 'Aktif · arahkan ke QR');
            clearInlineError();

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
            } catch (e) { /* Zoom tidak didukung */ }

        } catch (err) {
            console.error('Camera error:', err);
            // Pesan friendly berdasarkan error type
            let title = 'Kamera belum aktif';
            let desc  = 'Untuk memindai QR Code, izinkan akses kamera terlebih dahulu. Setelah izin diberikan, scanner akan menyala otomatis.';
            if (err.name === 'NotAllowedError' || err.name === 'SecurityError') {
                title = 'Izin kamera ditolak';
                desc  = 'Browser tidak diizinkan mengakses kamera. Silakan buka pengaturan browser lalu aktifkan izin kamera untuk situs ini.';
            } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                title = 'Kamera tidak ditemukan';
                desc  = 'Perangkat Anda tidak memiliki kamera, atau kamera sedang digunakan aplikasi lain. Coba tutup aplikasi lain lalu refresh halaman.';
            } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
                title = 'Kamera sedang sibuk';
                desc  = 'Kamera sedang digunakan aplikasi lain (Zoom, Meet, dll). Tutup aplikasi tersebut lalu coba lagi.';
            }
            setCameraStatus('error', title);
            // Tutup modal scan error jika ada, buka modal izin friendly
            const scanErrorModalEl = document.getElementById('scanErrorModal');
            if (scanErrorModalEl) {
                const m = bootstrap.Modal.getInstance(scanErrorModalEl);
                if (m) m.hide();
            }
            // Update modal content & show
            const modalTitleEl = document.getElementById('cameraModalTitle') || document.querySelector('#cameraPermissionModal .modal-title');
            if (modalTitleEl) modalTitleEl.textContent = title;
            showCameraModal();
        }
    }

    document.addEventListener('DOMContentLoaded', startScanner);
    window.addEventListener('beforeunload', function () {
        if (html5QrCode && isScanning) html5QrCode.stop().catch(function () {});
    });

    // ── Auto-tampilkan modal scan error jika ada flashdata error ──
    (function () {
        const modalEl = document.getElementById('scanErrorModal');
        if (! modalEl) return;
        if (html5QrCode && isScanning) {
            html5QrCode.stop().catch(function () {});
            isScanning = false;
        }
        const m = new bootstrap.Modal(modalEl);
        m.show();
        modalEl.addEventListener('hidden.bs.modal', function () {
            startScanner();
        });
    })();

    // ── Restart scanner (untuk tombol "Coba Scan Lagi" di modal error) ──
    function restartScanner() {
        const modalEl = document.getElementById('scanErrorModal');
        if (modalEl) {
            const m = bootstrap.Modal.getInstance(modalEl);
            if (m) m.hide();
        }
        scanned = false;
        meetingInput.value = '';
        tokenInput.value   = '';
        tokenInput.classList.remove('is-invalid', 'bg-light');
        clearTokenError();
        resetLocation();
    }
</script>
<?= $this->endSection() ?>
