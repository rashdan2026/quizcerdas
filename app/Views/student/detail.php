<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<style>
    .pdf-frame {
        position: relative;
        width: 100%;
        height: 85vh;
        min-height: 500px;
        background: #1a1a2e;
        border-radius: 8px;
        overflow: hidden;
    }
    .pdf-frame canvas {
        display: block;
        margin: 0 auto;
        max-width: 100%;
        transition: transform 0.15s ease;
        transform-origin: top center;
    }
    .pdf-overlay {
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        z-index: 10;
        pointer-events: none;
    }
    .pdf-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: #2c2c54;
        border-radius: 8px 8px 0 0;
        color: #fff;
        font-size: 0.9rem;
        flex-wrap: wrap;
    }
    .pdf-controls button {
        background: #40407a;
        color: #fff;
        border: none;
        padding: 4px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .pdf-controls button:hover { background: #5757a0; }
    .pdf-controls button:disabled { opacity: 0.4; cursor: default; }
    .pdf-controls .page-info { min-width: 80px; text-align: center; }
    .pdf-controls .zoom-info { min-width: 52px; text-align: center; font-weight: 700; color: #f0c040; }
    .pdf-controls .separator { color: #666; user-select: none; }
    .pdf-loading {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100%;
        color: #aaa;
        font-size: 1.1rem;
    }
</style>

<div class="row g-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">📖 Detail Pertemuan</h4>
            <a href="<?= base_url('/student/dashboard') ?>" class="btn btn-outline-secondary btn-sm">← Kembali</a>
        </div>
    </div>

    <!-- Info Absensi -->
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Info Absensi Anda</h6>
                <table class="table table-borderless mb-0">
                    <tr><th style="width:120px;">Matakuliah</th><td>: <?= esc($meeting['nama_mk']) ?></td></tr>
                    <tr><th>Pertemuan</th><td>: #<?= esc($meeting['pertemuan_ke']) ?></td></tr>
                    <tr><th>Judul</th><td>: <?= esc($meeting['judul']) ?></td></tr>
                    <tr><th>Waktu Absen</th><td>: <?= esc($meeting['waktu_absen']) ?></td></tr>
                    <tr>
                        <th>Lokasi</th>
                        <td>: <?php if ($meeting['latitude'] && $meeting['longitude']): ?>
                            <a href="https://www.google.com/maps?q=<?= esc($meeting['latitude']) ?>,<?= esc($meeting['longitude']) ?>" target="_blank" rel="noopener">
                                📍 Lihat di Maps
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Tidak tersedia</span>
                        <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- PDF Viewer (PDF.js - render as canvas, no toolbar) -->
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">📄 Materi PDF</h6>
                <?php if (!empty($meeting['file_name'])): ?>
                    <div class="pdf-frame" id="pdfFrame" oncontextmenu="return false;">
                        <!-- Custom controls -->
                        <div class="pdf-controls">
                            <button id="prevPage" onclick="changePage(-1)" title="Halaman sebelumnya">◀</button>
                            <span class="page-info" id="pageInfo">-</span>
                            <button id="nextPage" onclick="changePage(1)" title="Halaman berikutnya">▶</button>
                            <span class="separator">|</span>
                            <button onclick="zoomOut()" title="Perkecil">➖</button>
                            <span class="zoom-info" id="zoomInfo">100%</span>
                            <button onclick="zoomIn()" title="Perbesar">➕</button>
                            <button onclick="zoomReset()" title="Reset zoom">↺</button>
                            <span class="separator">|</span>
                            <button onclick="toggleFullscreen()" title="Fullscreen">⛶</button>
                        </div>
                        <!-- Canvas area for PDF rendering -->
                        <div id="pdfContainer" style="overflow:auto;height:calc(100% - 44px);display:flex;justify-content:center;">
                            <div class="pdf-loading" id="pdfLoading">⏳ Memuat dokumen…</div>
                            <canvas id="pdfCanvas" style="display:none;"></canvas>
                        </div>
                        <!-- Overlay: blokir interaksi -->
                        <div class="pdf-overlay" oncontextmenu="return false;"
                             onmousedown="return false;"
                             ondragstart="return false;"
                             onselectstart="return false;"></div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            🔒 PDF hanya dapat dibaca di aplikasi. Download, print, dan save dinonaktifkan.
                        </small>
                    </div>

                    <!-- Hidden data attributes untuk PDF.js -->
                    <div id="pdfConfig"
                         data-url="<?= base_url('/student/pdf/file/' . $meeting['meeting_id']) ?>"
                         data-token="<?= esc($pdfToken) ?>"
                         style="display:none;"></div>
                <?php else: ?>
                    <div class="alert alert-info py-3 text-center">
                        Materi PDF untuk pertemuan ini belum tersedia.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    // ── Konfigurasi PDF.js ──
    const cfg     = document.getElementById('pdfConfig');
    const pdfUrl  = cfg ? cfg.dataset.url : '';
    const pdfToken = cfg ? cfg.dataset.token : '';

    let pdfDoc   = null;
    let pageNum  = 1;
    let pageRendering = false;
    let pageNumPending = null;
    let currentScale = 1.0;   // zoom level (1.0 = 100%)
    let baseScale  = 1.5;      // base render scale for PDF.js
    const minScale = 0.5;      // 50%
    const maxScale = 3.0;      // 300%
    const scaleStep = 0.25;    // 25% per step

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(function(page) {
            const canvas = document.getElementById('pdfCanvas');
            const ctx    = canvas.getContext('2d');
            const vp     = page.getViewport({ scale: baseScale });
            canvas.height = vp.height;
            canvas.width  = vp.width;
            // Clear canvas first
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            page.render({ canvasContext: ctx, viewport: vp }).promise.then(function() {
                pageRendering = false;
                // Apply CSS zoom transform to canvas
                applyZoom();
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            }).catch(function(err) {
                pageRendering = false;
                console.error('Render error:', err);
            });
        }).catch(function(err) {
            pageRendering = false;
            console.error('GetPage error:', err);
        });
        document.getElementById('pageInfo').textContent = num + ' / ' + pdfDoc.numPages;
        document.getElementById('prevPage').disabled = (num <= 1);
        document.getElementById('nextPage').disabled = (num >= pdfDoc.numPages);
    }

    function queueRenderPage(num) {
        if (pageRendering) { pageNumPending = num; }
        else { renderPage(num); }
    }

    function changePage(offset) {
        const next = pageNum + offset;
        if (next < 1 || next > pdfDoc.numPages) return;
        pageNum = next;
        queueRenderPage(pageNum);
    }

    function applyZoom() {
        const canvas = document.getElementById('pdfCanvas');
        canvas.style.transform = 'scale(' + currentScale + ')';
        // Adjust container for scaled content
        document.getElementById('pdfContainer').style.alignItems = 'flex-start';
    }

    function updateZoomDisplay() {
        document.getElementById('zoomInfo').textContent = Math.round(currentScale * 100) + '%';
    }

    function zoomIn() {
        if (currentScale >= maxScale) return;
        currentScale = Math.min(maxScale, +(currentScale + scaleStep).toFixed(2));
        updateZoomDisplay();
        applyZoom();
    }

    function zoomOut() {
        if (currentScale <= minScale) return;
        currentScale = Math.max(minScale, +(currentScale - scaleStep).toFixed(2));
        updateZoomDisplay();
        applyZoom();
    }

    function zoomReset() {
        currentScale = 1.0;
        updateZoomDisplay();
        applyZoom();
    }

    function toggleFullscreen() {
        const el = document.getElementById('pdfFrame');
        if (!document.fullscreenElement) { el.requestFullscreen().catch(() => {}); }
        else { document.exitFullscreen(); }
    }

    // Mouse wheel zoom
    document.addEventListener('DOMContentLoaded', () => {
        const frame = document.getElementById('pdfFrame');
        if (frame) {
            frame.addEventListener('wheel', (e) => {
                if (e.ctrlKey) {
                    e.preventDefault();
                    if (e.deltaY < 0) zoomIn();
                    else zoomOut();
                }
            }, { passive: false });
        }
    });

    // ── Load PDF ──
    if (pdfUrl && pdfToken) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        // Fetch PDF dengan token auth, convert ke blob/url untuk PDF.js
        fetch(pdfUrl + '?t=' + encodeURIComponent(pdfToken), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed to load PDF');
            return r.arrayBuffer();
        })
        .then(data => pdfjsLib.getDocument({ data }).promise)
        .then(pdf => {
            pdfDoc = pdf;
            document.getElementById('pdfLoading').style.display = 'none';
            document.getElementById('pdfCanvas').style.display = 'block';
            renderPage(1);
        })
        .catch(err => {
            document.getElementById('pdfLoading').innerHTML = '❌ Gagal memuat PDF: ' + err.message;
            console.error(err);
        });
    }

    // ── Anti-download: blokir keyboard shortcuts ──
    document.addEventListener('keydown', e => {
        // Ctrl+S, Ctrl+P, Ctrl+U, Ctrl+J, Ctrl+A tetap diblokir
        if (e.ctrlKey && ['s','p','u','j'].includes(e.key.toLowerCase())) e.preventDefault();
        // Ctrl+A hanya blokir di dalam PDF frame
        if (e.ctrlKey && e.key.toLowerCase() === 'a' && e.target.closest('.pdf-frame')) e.preventDefault();
        if (e.key === 'F12') e.preventDefault();
    });
    document.addEventListener('contextmenu', e => { if (e.target.closest('.pdf-frame')) e.preventDefault(); });
</script>
<?= $this->endSection() ?>
