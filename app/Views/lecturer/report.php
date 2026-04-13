<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<style>
    .meeting-card { border-left:4px solid #4F46E5 !important; transition:transform .18s, box-shadow .18s; }
    .meeting-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.1) !important; }
    .count-badge { font-size:1.1rem; font-weight:700; min-width:52px; padding:6px 14px; border-radius:10px; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>Rekap Absensi</h4>
        <p class="text-muted small mb-0 mt-1">Pantau kehadiran mahasiswa per pertemuan</p>
    </div>
    <button type="button" class="btn btn-success d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addManualModal" <?= empty($meetings) ? 'disabled' : '' ?>>
        <i class="bi bi-person-plus"></i> Tambah Absen Manual
    </button>
</div>

<?php if (empty($meetings)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox display-5 text-muted d-block mb-3"></i>
            <p class="text-muted">Belum ada pertemuan yang dibuat.</p>
            <a href="<?= base_url('/lecturer/meetings/create') ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Buat Pertemuan
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($meetings as $m): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card meeting-card h-100">
                <div class="card-body d-flex flex-column p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge mb-1" style="background:#EEF2FF;color:#4F46E5;"><?= esc($m['kode_mk']) ?></span>
                            <h6 class="mb-0 fw-600 text-dark" style="font-size:.9rem;"><?= esc($m['nama_mk']) ?></h6>
                        </div>
                        <span class="badge bg-success count-badge"><?= esc($m['total_absen']) ?></span>
                    </div>
                    <p class="text-muted mb-1" style="font-size:.85rem;">
                        <i class="bi bi-hash me-1"></i>Pertemuan <?= esc($m['pertemuan_ke']) ?> — <?= esc($m['judul']) ?>
                    </p>
                    <small class="text-muted mb-3"><i class="bi bi-clock me-1"></i><?= esc($m['expired_at']) ?></small>
                    <div class="mt-auto d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm flex-fill"
                                onclick="showDetail(<?= $m['id'] ?>, '<?= esc($m['kode_mk'], 'js') ?>', 'Pertemuan #<?= esc($m['pertemuan_ke'], 'js') ?>', '<?= esc($m['judul'], 'js') ?>')">
                            <i class="bi bi-eye me-1"></i>Detail Absensi
                        </button>
                        <a href="<?= base_url('/lecturer/meetings/' . $m['id']) ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-qr-code"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Detail Absensi -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header" style="border-bottom:1.5px solid #F1F5F9;">
                <h5 class="modal-title fw-600" id="detailModalTitle">Detail Absensi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted small mt-2">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer justify-content-between" style="border-top:1.5px solid #F1F5F9;">
                <div id="modalTotalInfo"></div>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Absen Manual -->
<div class="modal fade" id="addManualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="<?= base_url('/lecturer/report/add') ?>" method="post">
            <?= csrf_field() ?>
            <div class="modal-content" style="border-radius:14px;">
                <div class="modal-header" style="border-bottom:1.5px solid #F1F5F9;">
                    <h5 class="modal-title fw-600"><i class="bi bi-person-plus me-2 text-success"></i>Tambah Absensi Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-collection me-1"></i>Pertemuan</label>
                        <select name="meeting_id" class="form-select" required>
                            <option value="">— Pilih Pertemuan —</option>
                            <?php foreach ($meetings as $m): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= esc($m['kode_mk']) ?> - P<?= esc($m['pertemuan_ke']) ?> - <?= esc($m['judul']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="bi bi-envelope me-1"></i>Email Mahasiswa</label>
                        <input type="email" name="email" class="form-control" placeholder="Contoh: mahasiswa@kampus.ac.id" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label"><i class="bi bi-geo me-1"></i>Latitude <span class="text-muted fw-400">(opsional)</span></label>
                            <input type="text" name="latitude" class="form-control" placeholder="-6.200000">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><i class="bi bi-geo me-1"></i>Longitude <span class="text-muted fw-400">(opsional)</span></label>
                            <input type="text" name="longitude" class="form-control" placeholder="106.816666">
                        </div>
                    </div>
                    <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>Koordinat boleh dikosongkan untuk absensi manual.</div>
                </div>
                <div class="modal-footer" style="border-top:1.5px solid #F1F5F9;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-4"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<script>
    // Global variable to store current modal data
    let currentModalData = null;
    let currentMeetingId = null;
    let currentKodeMk = null;
    let currentPertemuan = null;
    let currentJudul = null;

    function showDetail(meetingId, kodeMk, pertemuan, judul) {
        const modal   = new bootstrap.Modal(document.getElementById('detailModal'));
        const titleEl = document.getElementById('detailModalTitle');
        const bodyEl  = document.getElementById('detailModalBody');
        const totalInfoEl = document.getElementById('modalTotalInfo');
        
        titleEl.textContent = kodeMk + ' — ' + pertemuan + ': ' + judul;
        bodyEl.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="text-muted small mt-2">Memuat data...</p></div>';
        totalInfoEl.innerHTML = '';
        modal.show();

        // Store current meeting info
        currentMeetingId = meetingId;
        currentKodeMk = kodeMk;
        currentPertemuan = pertemuan;
        currentJudul = judul;

        // Fetch ALL data once (WITHOUT kelas filter - we'll filter client-side)
        const url = "<?= base_url('/lecturer/report/detail') ?>/" + meetingId;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    bodyEl.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                    return;
                }

                // Store data globally for filtering
                currentModalData = data;

                // Render modal content with ALL data
                renderModalBody(data, meetingId, kodeMk, pertemuan, judul);
            })
            .catch(() => {
                bodyEl.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat data.</div>';
            });
    }

    function renderModalBody(data, meetingId, kodeMk, pertemuan, judul) {
        const bodyEl = document.getElementById('detailModalBody');
        const totalInfoEl = document.getElementById('modalTotalInfo');

        let html = '';

        // Filter Kelas dropdown
        if (data.kelasList && data.kelasList.length > 0) {
            html += '<div class="mb-3 p-3 rounded-3" style="background:#F8FAFC;border:1.5px solid #E2E8F0;">';
            html += '<div class="d-flex align-items-center gap-2 flex-wrap">';
            html += '<label class="form-label mb-0 fw-600"><i class="bi bi-funnel me-1"></i>Filter Kelas:</label>';
            html += '<select id="modalFilterKelas" class="form-select form-select-sm" style="min-width:180px;flex:1;">';
            html += '<option value="">Semua Kelas</option>';
            data.kelasList.forEach(k => {
                html += '<option value="' + k + '">' + k + '</option>';
            });
            html += '</select>';
            html += '</div></div>';
        }

        // Tabel absensi dengan data-row-kelas attribute
        if (data.rows.length === 0) {
            html += '<div class="text-center py-4"><i class="bi bi-inbox display-6 text-muted d-block mb-2"></i><p class="text-muted">Belum ada mahasiswa yang absen.</p></div>';
        } else {
            html += '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0" id="attendanceTable"><thead><tr><th>#</th><th>Email</th><th>Nama</th><th>Kelas</th><th>Waktu</th><th>Lokasi</th></tr></thead><tbody>';
            data.rows.forEach((r, i) => {
                const lok = (r.latitude && r.longitude)
                    ? '<a href="https://www.google.com/maps?q=' + r.latitude + ',' + r.longitude + '" target="_blank" class="btn btn-xs btn-outline-success" style="font-size:.75rem;padding:2px 8px;border-radius:5px;"><i class="bi bi-geo-alt"></i> Maps</a>'
                    : '<span class="text-muted small">-</span>';
                const kelasAttr = r.kelas ? ' data-row-kelas="' + r.kelas + '"' : ' data-row-kelas=""';
                html += '<tr' + kelasAttr + '><td class="text-muted row-number">' + (i+1) + '</td><td><code class="small">' + r.email + '</code></td><td class="fw-500">' + r.nama + '</td><td><span class="badge bg-secondary">' + (r.kelas||'-') + '</span></td><td><small class="text-muted">' + r.waktu_absen + '</small></td><td>' + lok + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }

        bodyEl.innerHTML = html;

        // Setup filter dropdown
        setupFilterDropdown();
    }

    function setupFilterDropdown() {
        const dropdown = document.getElementById('modalFilterKelas');
        if (dropdown) {
            // Use onchange - simple and direct
            dropdown.onchange = function() {
                const kelas = this.value;
                console.log('Filter changed to:', kelas);
                doFilter(kelas);
            };
        }

        // Show all rows initially
        updateTotalCount('');
    }

    function doFilter(kelas) {
        console.log('Filtering by kelas:', kelas);
        
        // Update URL without reload
        const newUrl = window.location.pathname + (kelas ? '?kelas=' + encodeURIComponent(kelas) : '');
        history.pushState({}, '', newUrl);

        // Filter table rows using JavaScript (hide/show only)
        const table = document.getElementById('attendanceTable');
        if (!table) {
            console.log('Table not found!');
            return;
        }
        
        const rows = table.querySelectorAll('tbody tr');
        let visibleCount = 0;

        console.log('Total rows:', rows.length);
        
        rows.forEach((row, index) => {
            const rowKelas = row.getAttribute('data-row-kelas') || '';
            
            if (!kelas || rowKelas === kelas) {
                row.style.display = '';
                visibleCount++;
                console.log('Showing row', index, 'kelas:', rowKelas);
            } else {
                row.style.display = 'none';
                console.log('Hiding row', index, 'kelas:', rowKelas);
            }
        });

        // Update row numbers
        let rowNum = 1;
        rows.forEach(row => {
            if (row.style.display !== 'none') {
                const numCell = row.querySelector('.row-number');
                if (numCell) numCell.textContent = rowNum++;
            }
        });

        // Update badge if shown
        updateActiveKelasBadge(kelas);
        
        // Update total count
        updateTotalCount(kelas);
        
        console.log('Visible count:', visibleCount);
    }

    function updateTotalCount(selectedKelas) {
        const totalInfoEl = document.getElementById('modalTotalInfo');
        const table = document.getElementById('attendanceTable');
        
        let visibleCount = 0;
        
        if (table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                // Count only visible rows (display is not 'none')
                if (row.style.display !== 'none') {
                    visibleCount++;
                }
            });
        } else if (currentModalData) {
            visibleCount = currentModalData.rows.length;
        }

        const totalText = selectedKelas ? 'Total Absen (' + selectedKelas + '):' : 'Total Absen:';
        totalInfoEl.innerHTML = '<span class="badge bg-success px-3 py-2" style="font-size:.9rem;"><i class="bi bi-people-fill me-1"></i>' + totalText + ' ' + visibleCount + ' mahasiswa</span>';
    }

    function updateActiveKelasBadge(selectedKelas) {
        // Remove existing badge if any
        const existingBadge = document.querySelector('#detailModalBody .badge.bg-info.ms-1');
        if (existingBadge) {
            existingBadge.remove();
        }

        // Add new badge if kelas is selected
        if (selectedKelas) {
            const filterContainer = document.querySelector('#detailModalBody .d-flex.align-items-center.gap-2');
            if (filterContainer) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-info ms-1';
                badge.innerHTML = '<i class="bi bi-filter-circle me-1"></i>' + selectedKelas;
                filterContainer.appendChild(badge);
            }
        }
    }
</script>
<?= $this->endSection() ?>
