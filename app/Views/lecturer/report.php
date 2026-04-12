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

<!-- Filter Kelas -->
<?php if (!empty($kelasList)): ?>
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label class="form-label mb-0 fw-600"><i class="bi bi-funnel me-1"></i>Filter Kelas:</label>
            </div>
            <div class="col-auto">
                <select id="filterKelas" class="form-select form-select-sm" style="min-width:180px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= esc($k) ?>" <?= isset($selectedKelas) && $selectedKelas === $k ? 'selected' : '' ?>><?= esc($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnApplyFilter">
                    <i class="bi bi-check-circle me-1"></i>Terapkan
                </button>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetFilter">
                    <i class="bi bi-x-circle me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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
            <div class="modal-body" id="detailModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted small mt-2">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1.5px solid #F1F5F9;">
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
    function showDetail(meetingId, kodeMk, pertemuan, judul) {
        const modal   = new bootstrap.Modal(document.getElementById('detailModal'));
        const titleEl = document.getElementById('detailModalTitle');
        const bodyEl  = document.getElementById('detailModalBody');
        titleEl.textContent = kodeMk + ' — ' + pertemuan + ': ' + judul;
        bodyEl.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="text-muted small mt-2">Memuat data...</p></div>';
        modal.show();
        fetch("<?= base_url('/lecturer/report/detail') ?>/" + meetingId)
            .then(r => r.json())
            .then(data => {
                if (data.error) { bodyEl.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>'; return; }
                if (data.rows.length === 0) { bodyEl.innerHTML = '<div class="text-center py-4"><i class="bi bi-inbox display-6 text-muted d-block mb-2"></i><p class="text-muted">Belum ada mahasiswa yang absen.</p></div>'; return; }
                let html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>#</th><th>Email</th><th>Nama</th><th>Kelas</th><th>Waktu</th><th>Lokasi</th></tr></thead><tbody>';
                data.rows.forEach((r, i) => {
                    const lok = (r.latitude && r.longitude)
                        ? '<a href="https://www.google.com/maps?q=' + r.latitude + ',' + r.longitude + '" target="_blank" class="btn btn-xs btn-outline-success" style="font-size:.75rem;padding:2px 8px;border-radius:5px;"><i class="bi bi-geo-alt"></i> Maps</a>'
                        : '<span class="text-muted small">-</span>';
                    html += '<tr><td class="text-muted">' + (i+1) + '</td><td><code class="small">' + r.email + '</code></td><td class="fw-500">' + r.nama + '</td><td><span class="badge bg-secondary">' + (r.kelas||'-') + '</span></td><td><small class="text-muted">' + r.waktu_absen + '</small></td><td>' + lok + '</td></tr>';
                });
                html += '</tbody></table></div>';
                html += '<div class="d-flex justify-content-end align-items-center mt-3"><span class="badge bg-primary px-3 py-2">Total: ' + data.rows.length + ' mahasiswa</span></div>';
                bodyEl.innerHTML = html;
            })
            .catch(() => { bodyEl.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Gagal memuat data.</div>'; });
    }

    // Filter Kelas
    const filterKelas = document.getElementById('filterKelas');
    const btnApplyFilter = document.getElementById('btnApplyFilter');
    const btnResetFilter = document.getElementById('btnResetFilter');

    if (btnApplyFilter) {
        btnApplyFilter.addEventListener('click', function() {
            const kelas = filterKelas ? filterKelas.value : '';
            window.location.href = kelas ? '?kelas=' + encodeURIComponent(kelas) : window.location.pathname;
        });
    }

    if (btnResetFilter) {
        btnResetFilter.addEventListener('click', function() {
            if (filterKelas) filterKelas.value = '';
            window.location.href = window.location.pathname;
        });
    }
</script>
<?= $this->endSection() ?>
