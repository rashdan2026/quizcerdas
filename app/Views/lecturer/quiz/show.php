<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="bi bi-lightbulb me-2"></i><?= esc($quiz['title']) ?></h4>
        <p class="text-muted small mb-0 mt-1">
            <?= esc($quiz['description'] ?: '-') ?> &middot;
            <span class="badge bg-secondary"><?= esc($quiz['status']) ?></span> &middot;
            Kode Kuis: <code class="fw-bold" style="color:#4F46E5;"><?= esc($quiz['access_code']) ?></code>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/lecturer/quiz') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <a href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/analytics') ?>" class="btn btn-info text-white btn-sm"><i class="bi bi-bar-chart me-1"></i>Analitik</a>
    </div>
</div>

<div class="alert alert-info small">
    Skor maksimum kuis: <strong><?= (int) $maxScore ?></strong>
    (<?= (int) $stats['total'] ?> soal &times; poin benar <?= (int) $quiz['correct_points'] ?>).
    Total soal: <strong><?= (int) $stats['total'] ?></strong> (Manual: <?= (int) $stats['manual'] ?>, AI: <?= (int) $stats['ai_pdf'] ?>; Mudah <?= (int) $stats['easy'] ?> / Sedang <?= (int) $stats['medium'] ?> / Sulit <?= (int) $stats['hard'] ?>).
</div>

<!-- ===================== Kode Kuis (besar & menonjol) ===================== -->
<div class="card mb-4 text-center" style="background: linear-gradient(135deg,#3730A3 0%,#4F46E5 100%);border:none;border-radius:14px;">
    <div class="card-body py-4 text-white">
        <div class="small text-uppercase" style="letter-spacing:3px;opacity:.9;">Kode Kuis Aktif</div>
        <div id="accessCode" class="fw-bold" style="font-size:3.2rem;letter-spacing:12px;line-height:1.2;"><?= esc($quiz['access_code']) ?></div>
        <div class="d-flex gap-2 justify-content-center mt-2">
            <button type="button" class="btn btn-light btn-sm" onclick="copyCode(this)">
                <i class="bi bi-clipboard me-1"></i>Salin Kode
            </button>
            <form id="regenForm" action="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/regenerate-code') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-light btn-sm" id="regenBtn"><i class="bi bi-arrow-repeat me-1"></i>Regenerate Kode Kuis</button>
            </form>
        </div>
        <div id="regenMsg" class="form-text mt-2 mb-0 d-none" style="color:#FEF08A;"></div>
        <div class="form-text mt-1 mb-0" style="color:rgba(255,255,255,.85);">Kode aktif untuk attempt baru. Satu kode = satu percobaan per mahasiswa. Mahasiswa dapat mengikuti kuis ini lagi dengan kode baru.</div>
    </div>
</div>

<!-- ===================== Histori Kode Kuis ===================== -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-clock-history me-2"></i>Histori Kode Kuis</div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Kode</th><th class="text-center">Status</th><th>Diterbitkan</th><th>Dinonaktifkan</th></tr></thead>
        <tbody id="codeHistoryBody">
        <?php foreach ($codeHistory as $c): ?>
            <tr>
                <td><code class="fw-bold" style="color:#4F46E5;"><?= esc($c['code']) ?></code></td>
                <td class="text-center">
                    <?php if ((int) $c['is_active'] === 1): ?>
                        <span class="badge bg-success rounded-pill">Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary rounded-pill">Nonaktif</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?= esc((string) $c['created_at']) ?></td>
                <td class="small"><?= esc((string) ($c['deactivated_at'] ?? '-')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($codeHistory)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Belum ada histori kode.</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>

<!-- ===================== Ringkasan Pelaksanaan (form ada di halaman Pengaturan) ===================== -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-calendar-event me-2"></i>Pelaksanaan</span>
        <a href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/settings') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-gear me-1"></i>Pengaturan Kuis</a>
    </div>
    <div class="card-body small">
        Mulai: <strong><?= $quiz['starts_at'] ? esc(date('d-m-Y H:i', strtotime($quiz['starts_at']))) : '-' ?></strong> &middot;
        Berakhir (otomatis): <strong><?= $quiz['ends_at'] ? esc(date('d-m-Y H:i', strtotime($quiz['ends_at']))) : '-' ?></strong> &middot;
        Durasi: <strong><?= (int) $quiz['duration_minutes'] ?> menit</strong> &middot; Status: <strong><?= esc($quiz['status']) ?></strong>
    </div>
</div>

<!-- ===================== Generate AI dari PDF ===================== -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-magic me-2"></i>Generate Soal AI dari PDF (satu PDF = satu batch)</div>
    <div class="card-body">
        <?php
        // Pilihan jumlah soal HANYA 5/10/15/20. Pilihan di atas sisa kapasitas dinonaktifkan
        // (peringatan sebelum proses dimulai); backend tetap menolak nilai ilegal.
        $defaultCount = null;
        foreach ($counts as $c) {
            if ($c <= $capacity) {
                $defaultCount = $c;
            }
        }
        if (in_array(10, $counts, true) && 10 <= $capacity) {
            $defaultCount = 10;
        }
        $formLocked = $capacity < 5 || $activeBatch !== null;
        ?>
        <form id="genForm" method="post" action="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/generate') ?>" enctype="multipart/form-data" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label">File PDF (maks 10 MB)</label>
                <input type="file" name="pdf_file" class="form-control" accept="application/pdf" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Judul sumber (opsional)</label>
                <input type="text" name="source_title" class="form-control" maxlength="255">
            </div>
            <div class="col-md-2">
                <label class="form-label">Jumlah soal</label>
                <select name="question_count" class="form-select" id="qcount">
                    <?php foreach ($counts as $c):
                        $optionAttrs = ($c === $defaultCount ? ' selected' : '') . ($c > $capacity ? ' disabled' : ''); ?>
                        <option value="<?= $c ?>"<?= $optionAttrs ?>><?= $c ?> soal</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Komposisi kesulitan (total 100%)</label>
                <div class="d-flex gap-2">
                    <input type="number" name="difficulty_easy_pct" class="form-control" min="0" max="100" value="30" title="Mudah">
                    <input type="number" name="difficulty_medium_pct" class="form-control" min="0" max="100" value="50" title="Sedang">
                    <input type="number" name="difficulty_hard_pct" class="form-control" min="0" max="100" value="20" title="Sulit">
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Instruksi Tambahan untuk AI <span class="text-danger">*</span></label>
                <textarea name="ai_instruction" id="aiInstruction" class="form-control" rows="3"
                    placeholder="Contoh: Buat soal berbasis studi kasus, prioritaskan analisis dan penerapan konsep, gunakan bahasa sederhana yang mudah dipahami mahasiswa..."></textarea>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <div class="invalid-feedback d-block text-danger small d-none" id="aiInstructionError"></div>
                    <span class="form-text ms-auto" id="aiInstructionCounter">0/500</span>
                </div>
            </div>
            <div class="col-12">
                <div class="form-text mb-1" id="allocPreview"></div>
                <div class="form-text mb-2">
                    Bank soal: <strong><?= (int) $stats['total'] ?> / <?= (int) $maxQuestions ?></strong> &middot;
                    Sisa kapasitas: <strong><?= (int) $capacity ?> soal</strong>.
                    <?php if ($capacity >= 5 && $capacity < max($counts)): ?>
                        Pilihan di atas sisa kapasitas dinonaktifkan.
                    <?php endif; ?>
                </div>
                <?php if ($capacity < 5): ?>
                    <div class="alert alert-warning small py-2 mb-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>Kapasitas soal tinggal <?= (int) $capacity ?> (kurang dari 5). Hapus beberapa soal terlebih dahulu sebelum generate lagi.
                    </div>
                <?php endif; ?>
                <button class="btn btn-primary" id="genBtn"<?= $formLocked ? ' disabled' : '' ?> onclick="return validateComposition(event)"><i class="bi bi-stars me-1"></i>Generate</button>
            </div>
        </form>

        <div id="genBox" class="alert alert-info mt-3 mb-0 d-none" role="status" aria-live="polite">
            <div class="d-flex align-items-start gap-2">
                <div class="spinner-border spinner-border-sm text-primary flex-shrink-0 mt-1" id="genSpinner"></div>
                <i class="bi bi-check-circle-fill flex-shrink-0 mt-1 d-none" id="genIcon"></i>
                <div>
                    <div class="fw-semibold" id="genTitle"></div>
                    <div class="small" id="genDetail"></div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2 d-none" id="genActions">
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('/lecturer/quiz/' . $quiz['id']) ?>"><i class="bi bi-arrow-clockwise me-1"></i>Muat ulang daftar soal</a>
        </div>

        <?php if (! empty($batches)): ?>
        <hr>
        <h6 class="mb-2">Riwayat Batch</h6>
        <div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>#</th><th>File</th><th>Diminta</th><th>Unik</th><th>Duplikat</th><th>Status</th><th>Pesan</th></tr></thead>
            <tbody>
            <?php foreach ($batches as $b): ?>
                <tr>
                    <td><?= (int) $b['id'] ?></td>
                    <td class="small"><?= esc($b['original_filename']) ?> <span class="text-muted">(<?= number_format((int) $b['file_size_bytes'] / 1048576, 2) ?> MB)</span></td>
                    <td><?= (int) $b['requested_count'] ?></td>
                    <td><?= (int) $b['generated_count'] ?></td>
                    <td><?= (int) $b['duplicate_count'] ?></td>
                    <td><span class="badge bg-<?= ['completed' => 'success', 'partial' => 'warning text-dark', 'failed' => 'danger', 'processing' => 'info', 'quarantine' => 'secondary', 'queued' => 'secondary'][$b['status']] ?? 'secondary' ?>"><?= esc($b['status']) ?></span></td>
                    <td class="small"><?= esc((string) ($b['status_message'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== Tambah Soal Manual ===================== -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Tambah Soal Manual</div>
    <div class="card-body">
        <div class="alert alert-warning small py-2">Soal manual tidak menjalankan pemeriksaan duplikasi dan tidak memiliki tingkat kesulitan. Opsi E selalu berarti "Tidak tahu" dan tidak boleh menjadi kunci.</div>
        <form method="post" action="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '/questions/manual') ?>" class="row g-3" data-ajax-save>
            <?= csrf_field() ?>
            <input type="hidden" name="page" value="<?= (int) $page ?>">
            <input type="hidden" name="q" value="<?= esc($search) ?>">
            <div class="col-12">
                <label class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                <textarea name="question_text" class="form-control rich-editor" rows="3" required></textarea>
                <div class="form-text small">Gunakan toolbar untuk formatting, tabel, dan blok kode. Kode program ditampilkan dengan syntax highlighting ke mahasiswa.</div>
            </div>
            <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
            <div class="col-md-6">
                <label class="form-label">Opsi <?= strtoupper($opt) ?> <span class="text-danger">*</span></label>
                <input type="text" name="option_<?= $opt ?>" class="form-control" maxlength="300" required>
            </div>
            <?php endforeach; ?>
            <div class="col-md-4">
                <label class="form-label">Kunci Jawaban</label>
                <select name="correct_option" class="form-select" required>
                    <option>A</option><option>B</option><option>C</option><option>D</option>
                </select>
                <div class="form-text">Hanya A/B/C/D. E = "Tidak tahu".</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Durasi Pengerjaan</label>
                <select name="duration_seconds" class="form-select">
                    <option value="30">30 detik</option>
                    <option value="60" selected>60 detik</option>
                    <option value="90">90 detik</option>
                    <option value="120">120 detik</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Penjelasan (opsional)</label>
                <textarea name="explanation" class="form-control" rows="1"></textarea>
            </div>
            <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Simpan Soal</button></div>
        </form>
    </div>
</div>

<!-- ===================== Bank Soal ===================== -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-collection me-2"></i>Bank Soal (<?= (int) $stats['total'] ?> soal &middot; maks. 100)</span>
        <form method="get" class="d-flex gap-2">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Cari soal..." value="<?= esc($search) ?>">
            <button class="btn btn-sm btn-outline-primary">Cari</button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($questions)): ?>
            <p class="text-muted mb-0"><?= $search !== '' ? 'Tidak ada soal yang cocok dengan pencarian.' : 'Belum ada soal.' ?></p>
        <?php endif; ?>
        <?php foreach ($questions as $q): ?>
        <div class="border rounded p-3 mb-3">
            <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                <span class="fw-bold">#<?= (int) ($globalNumbers[(int) $q['id']] ?? 0) ?></span>
                <span class="badge bg-<?= $q['source_type'] === 'manual' ? 'secondary' : 'primary' ?>">
                    <?= $q['source_type'] === 'manual' ? 'Manual' : 'AI PDF' ?>
                </span>
                <?php if ($q['source_type'] === 'ai_pdf'): ?>
                    <span class="badge bg-<?= ['easy' => 'success', 'medium' => 'warning text-dark', 'hard' => 'danger'][$q['difficulty_level']] ?? 'light text-dark' ?>">Kesulitan: <?= esc(ucfirst($q['difficulty_level'] ?? '-')) ?><?= $q['ai_difficulty_level'] !== $q['difficulty_level'] ? ' (awal AI: ' . esc((string) $q['ai_difficulty_level']) . ')' : '' ?></span>
                <?php else: ?>
                    <span class="badge bg-light text-dark">Kesulitan: Tidak ditentukan</span>
                <?php endif; ?>
                <?php if ($q['source_filename']): ?>
                    <span class="small text-muted"><i class="bi bi-file-earmark-pdf"></i> <?= esc($q['source_filename']) ?><?= $q['source_section'] ? ' &middot; ' . esc($q['source_section']) : '' ?></span>
                <?php endif; ?>
            </div>
            <form method="post" action="<?= base_url('/lecturer/quiz/questions/' . $q['id'] . '/update') ?>" data-ajax-save>
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <input type="hidden" name="q" value="<?= esc($search) ?>">
                <textarea name="question_text" class="form-control rich-editor mb-2" rows="2" required><?= esc($q['question_text']) ?></textarea>
                <div class="row g-2 mb-2">
                    <?php foreach (['a', 'b', 'c', 'd'] as $k): ?>
                    <div class="col-md-6"><div class="input-group input-group-sm">
                        <span class="input-group-text"><?= strtoupper($k) ?></span>
                        <input type="text" name="option_<?= $k ?>" class="form-control" maxlength="300" value="<?= esc($q['option_' . $k]) ?>" required>
                    </div></div>
                    <?php endforeach; ?>
                    <div class="col-md-6"><div class="input-group input-group-sm">
                        <span class="input-group-text">E</span><input class="form-control" value="Tidak tahu" readonly>
                    </div></div>
                </div>
                <div class="row g-2 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Kunci</label>
                        <select name="correct_option" class="form-select form-select-sm">
                            <?php foreach (['A', 'B', 'C', 'D'] as $c): ?>
                                <option <?= $q['correct_option'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Durasi</label>
                        <select name="duration_seconds" class="form-select form-select-sm">
                            <?php
                            $eff = (int) ($q['effective_duration'] ?? 60);
                            $isDefault = ($q['duration_seconds'] === null || $q['duration_seconds'] === '');
                            foreach ([30, 60, 90, 120] as $d): ?>
                                <option value="<?= $d ?>" <?= $eff === $d ? 'selected' : '' ?>><?= $d ?> dtk<?= $isDefault && $eff === $d ? ' (auto)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isDefault): ?>
                            <div class="form-text small mb-0">Belum diatur manual — memakai default otomatis.</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($q['source_type'] === 'ai_pdf'): ?>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Kesulitan (hanya soal AI)</label>
                        <select name="difficulty_level" class="form-select form-select-sm">
                            <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
                                <option value="<?= $d ?>" <?= $q['difficulty_level'] === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <label class="form-label small mb-0">Penjelasan</label>
                        <input type="text" name="explanation" class="form-control form-control-sm" value="<?= esc($q['explanation'] ?? '') ?>">
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
            <form method="post" action="<?= base_url('/lecturer/quiz/questions/' . $q['id'] . '/delete') ?>" class="mt-1" data-ajax-delete
                  onsubmit="return confirm('Hapus soal ini dari bank soal?')">
                <?= csrf_field() ?>
                <input type="hidden" name="page" value="<?= (int) $page ?>">
                <input type="hidden" name="q" value="<?= esc($search) ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Hapus Soal</button>
            </form>
        </div>
        <?php endforeach; ?>

        <!-- Pagination Bank Soal -->
        <?php if ($totalPage > 1 || $search !== ''): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <span class="small text-muted">Halaman <?= (int) $page ?> dari <?= (int) $totalPage ?> (total <?= (int) $totalQ ?> soal)</span>
            <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary <?= $page <= 1 ? 'disabled' : '' ?>"
                   href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '?page=' . max(1, $page - 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">&laquo; Previous</a>
                <a class="btn btn-outline-secondary <?= $page >= $totalPage ? 'disabled' : '' ?>"
                   href="<?= base_url('/lecturer/quiz/' . $quiz['id'] . '?page=' . min($totalPage, $page + 1) . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>">Next &raquo;</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Nilai mahasiswa kini di menu Kuis → Nilai Mahasiswa (data attempt tetap tersimpan di database). -->
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/quill/quill.snow.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/quiz-editor.css') ?>">
<script src="<?= base_url('assets/vendor/quill/quill.js') ?>"></script>
<script src="<?= base_url('assets/js/quiz-editor.js') ?>"></script>
<script>
// ===================== AJAX Simpan/Hapus Soal =====================
// Simpan soal TANPA reload halaman: posisi pagination, pencarian, dan scroll
// tetap terjaga. Backend tetap melakukan validasi + sanitasi (Bukan bypass).
(function () {
    'use strict';

    // ---- Notifikasi toast (tanpa reload) ----
    function ensureToastHost() {
        var host = document.getElementById('ajaxToastHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'ajaxToastHost';
            host.style.cssText = 'position:fixed;top:70px;right:16px;z-index:2000;display:flex;flex-direction:column;gap:8px;max-width:340px;';
            document.body.appendChild(host);
        }
        return host;
    }
    function notify(kind, message) {
        var colors = { success: ['#F0FDF4', '#166534', '#22C55E'], danger: ['#FFF1F2', '#BE123C', '#EF4444'] };
        var c = colors[kind] || colors.danger;
        var el = document.createElement('div');
        el.className = 'alert mb-0 shadow-sm';
        el.setAttribute('role', 'alert');
        el.style.cssText = 'background:' + c[0] + ';color:' + c[1] + ';border-left:4px solid ' + c[2] + ';font-size:.86rem;';
        el.textContent = message;
        ensureToastHost().appendChild(el);
        setTimeout(function () {
            el.style.transition = 'opacity .4s'; el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    }

    function setBusy(form, btn, busy) {
        if (form.dataset.busy === undefined) form.dataset.busy = '';
        if (busy) {
            if (form.dataset.busy === '1') return false; // cegah klik ganda
            form.dataset.busy = '1';
            if (btn) {
                btn.dataset.origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...';
            }
        } else {
            form.dataset.busy = '';
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.origHtml || btn.innerHTML;
            }
        }
        return true;
    }

    async function postForm(form, btn) {
        if (!setBusy(form, btn, true)) return; // sedang berjalan: abaikan klik
        try {
            var res = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: new FormData(form) // sudah memuat CSRF token (csrf_field)
            });
            // Sesi berakhir / redirect login: server balas HTML, bukan JSON.
            var ct = (res.headers.get('content-type') || '');
            if (!ct.includes('application/json')) {
                notify('danger', 'Sesi Anda telah berakhir. Silakan login ulang, lalu coba simpan lagi.');
                return;
            }
            var d = await res.json();
            if (d && d.success) {
                notify('success', d.message || 'Soal berhasil disimpan.');
                return d;
            }
            notify('danger', (d && d.message) || 'Gagal menyimpan soal. Silakan periksa isian dan coba lagi.');
        } catch (e) {
            // Jaringan terputus / server error: isi editor TIDAK dihapus.
            notify('danger', 'Gagal terhubung ke server. Periksa koneksi internet Anda lalu coba simpan lagi.');
        } finally {
            setBusy(form, btn, false);
        }
    }

    // ---- pasang pada form tambah soal & form edit/hapus per soal (bukan form lain) ----
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var isSave = form.matches('form[data-ajax-save]');
        var isDelete = form.matches('form[data-ajax-delete]');
        if (!isSave && !isDelete) return;
        // Konfirmasi hapus via onsubmit inline dibatalkan -> jangan kirim apa pun.
        if (e.defaultPrevented) return;

        e.preventDefault(); // cegah submit biasa (tidak ada reload)

        var btn = (e.submitter || form.querySelector('button[type="submit"], button:not([type])'));
        var isCardRemoval = isDelete;
        var btnHtml = btn ? btn.innerHTML : '';

        postForm(form, btn).then(function (d) {
            if (d && d.success && isCardRemoval) {
                // Hapus kartu soal dari daftar tanpa reload (pagination tetap).
                var card = form.closest('.border.rounded');
                if (card) card.remove();
            }
            if (isCardRemoval && btn) btn.innerHTML = btnHtml; // label asli kembali
        });
    });
})();
</script>
<script>
function copyCode(btn) {
    var code = document.getElementById('accessCode').textContent.trim();
    var done = function () {
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Disalin!';
        var notif = document.createElement('div');
        notif.className = 'alert alert-success small position-fixed';
        notif.style.cssText = 'bottom:16px;left:50%;transform:translateX(-50%);z-index:2000;padding:.5rem 1rem;';
        notif.textContent = 'Kode Kuis berhasil disalin';
        document.body.appendChild(notif);
        setTimeout(function () { btn.innerHTML = old; notif.remove(); }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code).then(done);
    } else {
        // Fallback browser lama
        var ta = document.createElement('textarea');
        ta.value = code; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); ta.remove(); done();
    }
}
// ===================== Instruksi tambahan untuk AI (maks 1000 karakter) =====================
const AI_INSTRUCTION_MAX = 500;
var aiField = document.getElementById('aiInstruction');
var aiCounter = document.getElementById('aiInstructionCounter');
var aiError = document.getElementById('aiInstructionError');

function aiInstructionError(msg) {
    aiError.textContent = msg || '';
    aiError.classList.toggle('d-none', !msg);
    aiField.classList.toggle('is-invalid', !!msg);
}
function aiInstructionTrimmed() { return aiField.value.trim(); }

// Penghitung karakter real-time saat dosen mengetik.
aiField.addEventListener('input', function () {
    var len = aiField.value.length;
    aiCounter.textContent = len + '/' + AI_INSTRUCTION_MAX;
    aiCounter.classList.toggle('text-danger', len > AI_INSTRUCTION_MAX);
    aiCounter.classList.toggle('fw-bold', len > AI_INSTRUCTION_MAX);
    // Pesan error dibersihkan saat dosen memperbaiki input.
    if (aiError && !aiError.classList.contains('d-none')) {
        if (len > 0 && len <= AI_INSTRUCTION_MAX && aiInstructionTrimmed() !== '') aiInstructionError('');
    }
});

// Validasi instruksi: wajib diisi (spasi saja = kosong) dan maks 1000 karakter.
function validateAiInstruction() {
    var trimmed = aiInstructionTrimmed();
    if (trimmed === '') {
        aiInstructionError('Instruksi tambahan untuk AI wajib diisi.');
        aiField.focus();
        return false;
    }
    if (trimmed.length > AI_INSTRUCTION_MAX) {
        aiInstructionError('Instruksi tambahan untuk AI maksimal ' + AI_INSTRUCTION_MAX + ' karakter (saat ini ' + trimmed.length + ' karakter). Kurangi isinya.');
        aiField.focus();
        return false;
    }
    aiInstructionError('');
    return true;
}

// Validasi frontend jumlah soal: HANYA 5/10/15/20 dan tidak melebihi sisa kapasitas
// (backend mengulang validasi yang sama).
const GEN_ALLOWED_COUNTS = <?= json_encode(array_map('intval', $counts)) ?>;
const GEN_CAPACITY = <?= (int) $capacity ?>;
function validateComposition(e) {
    if (!validateAiInstruction()) return false;
    const f = e.target.closest('form');
    const n = parseInt(f.question_count.value, 10);
    if (GEN_ALLOWED_COUNTS.indexOf(n) === -1) {
        alert('Jumlah soal hanya boleh 5, 10, 15, atau 20 soal untuk satu kali generate.');
        return false;
    }
    if (n > GEN_CAPACITY) {
        alert('Jumlah yang diminta (' + n + ') melebihi sisa kapasitas kuis (' + GEN_CAPACITY + ' soal, batas total 100). Pilih jumlah yang lebih kecil atau hapus beberapa soal terlebih dahulu.');
        return false;
    }
    const [e1, e2, e3] = [
        parseInt(f.difficulty_easy_pct.value, 10),
        parseInt(f.difficulty_medium_pct.value, 10),
        parseInt(f.difficulty_hard_pct.value, 10)
    ];
    if (e1 + e2 + e3 !== 100) {
        alert('Komposisi tingkat kesulitan wajib berjumlah tepat 100%.');
        return false;
    }
    const easy = Math.floor(n * e1 / 100), med = Math.floor(n * e2 / 100), hard = Math.floor(n * e3 / 100);
    let rem = n - easy - med - hard, order = [[e1, easy], [e2, med], [e3, hard]].sort((a, b) => b[0] - a[0]);
    const alloc = { easy, medium: med, hard };
    let i = 0;
    while (rem > 0) { alloc[order[i % 3][0] === e1 ? 'easy' : order[i % 3][0] === e2 ? 'medium' : 'hard']++; rem--; i++; }
    document.getElementById('allocPreview').textContent =
        'Alokasi sebelum generate: Mudah ' + alloc.easy + ', Sedang ' + alloc.medium + ', Sulit ' + alloc.hard + ' (total ' + n + ')';
    return true;
}
document.getElementById('qcount').addEventListener('change', function () {
    document.getElementById('allocPreview').textContent = '';
});

// ===================== Generate AI (satu request + polling status) =====================
// SATU request POST /generate memvalidasi unggahan lalu memproses generate
// sampai selesai — tidak ada antrean; gagal berarti gagal (batch ditandai
// failed, form aktif kembali). Selama request berjalan, progres dipantau
// lewat polling status batch terbaru kuis ini. Jika koneksi terputus, proses
// tetap berjalan di server dan halaman terus memantau sampai status final.
(function () {
    var form = document.getElementById('genForm');
    if (!form) return;
    var btn = document.getElementById('genBtn');
    var box = document.getElementById('genBox');
    var spinner = document.getElementById('genSpinner');
    var icon = document.getElementById('genIcon');
    var titleEl = document.getElementById('genTitle');
    var detailEl = document.getElementById('genDetail');
    var actions = document.getElementById('genActions');
    var quizUrl = <?= json_encode(base_url('/lecturer/quiz/' . (int) $quiz['id'])) ?>;
    var batchesUrl = <?= json_encode(base_url('/lecturer/quiz/batches')) ?>;
    var active = <?= json_encode($activeBatch) ?>;

    var busy = false, currentBatch = null, pollTimer = null, clock = null, clockStart = 0, lastStatus = null, done = false;

    function headers() { return {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}; }
    function elapsed() {
        var s = Math.max(0, Math.floor((Date.now() - clockStart) / 1000));
        return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    }
    function label(status) {
        return {processing: 'Sedang diproses…', completed: 'Selesai', partial: 'Selesai sebagian', failed: 'Gagal'}[status] || status;
    }
    function setBox(kind, title, detail) {
        box.className = 'alert mt-3 mb-0 alert-' + kind;
        var running = kind === 'info';
        spinner.classList.toggle('d-none', !running);
        icon.classList.toggle('d-none', running);
        if (!running) {
            icon.className = 'bi flex-shrink-0 mt-1 ' + (kind === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill');
        }
        titleEl.textContent = title;
        detailEl.textContent = detail;
    }
    function startClock() { clockStart = Date.now(); clearInterval(clock); clock = setInterval(tick, 1000); }
    function stopClock() { clearInterval(clock); clock = null; }
    function tick() {
        if (done) return;
        if (lastStatus && !lastStatus.final) showProgress(lastStatus);
    }
    function showProgress(d) {
        var parts = ['Target ' + d.requested + ' soal'];
        if (d.generated) parts.push(d.generated + ' soal unik tersusun');
        parts.push('berjalan ' + elapsed());
        setBox('info', d.message || label(d.status), parts.join(' · '));
    }
    function setBusy(on) {
        busy = on;
        btn.disabled = on || GEN_CAPACITY < 5;
    }
    function schedulePoll(ms) { clearTimeout(pollTimer); pollTimer = setTimeout(poll, ms || 2500); }
    function poll() {
        if (done || (currentBatch === null && !busy)) return;
        // Belum tahu batch-nya → pantau batch terbaru kuis; sudah tahu → pantau batch itu.
        var url = currentBatch !== null ? batchesUrl + '/' + currentBatch + '/status' : quizUrl + '/generate-status';
        fetch(url, {headers: headers()})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var b = currentBatch !== null ? d : (d && d.batch);
                if (!b || !b.status) { schedulePoll(); return; }
                if (currentBatch === null) {
                    if (b.final) { schedulePoll(); return; }  // hasil batch lama; tunggu batch baru muncul
                    currentBatch = b.batch_id;                // batch generate ini sedang diproses
                }
                lastStatus = b;
                if (b.final) { finish(b); return; }
                showProgress(b);
                schedulePoll();
            })
            .catch(function () { schedulePoll(4000); });
    }
    function finish(d) {
        done = true;
        clearTimeout(pollTimer);
        stopClock();
        setBusy(false);
        var kind = d.status === 'completed' ? 'success' : (d.status === 'partial' ? 'warning' : 'danger');
        setBox(kind, d.message || label(d.status), '');
        if (kind === 'danger') {
            actions.classList.remove('d-none');
            return;
        }
        // Muat ulang agar soal baru tampil di bank soal; pesan hasil ditampilkan sekali.
        try { sessionStorage.setItem('quizGenNotice', JSON.stringify({kind: kind, text: d.message || ''})); } catch (e) {}
        setTimeout(function () { window.location.reload(); }, kind === 'success' ? 1500 : 4000);
    }
    function fail(message) {
        done = true;
        clearTimeout(pollTimer);
        stopClock();
        setBusy(false);
        currentBatch = null;
        setBox('danger', 'Generate gagal', message);
        actions.classList.remove('d-none');
    }

    // Kirim form: satu request dari unggah sampai hasil akhir.
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy || !validateComposition(e)) return;
        if (!form.pdf_file.files || !form.pdf_file.files.length) {
            setBox('danger', 'File PDF belum dipilih', 'Pilih file PDF materi terlebih dahulu.');
            return;
        }
        setBusy(true);
        done = false;
        currentBatch = null;
        lastStatus = null;
        startClock();
        setBox('info', 'Mengunggah PDF & memeriksa keamanan…', '');
        schedulePoll(1500);
        fetch(form.action, {method: 'POST', headers: headers(), body: new FormData(form)})
            .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
            .then(function (res) {
                var d = res.data || {};
                if (res.ok && d.ok) { finish(d); return; }
                if (d.message && d.message.indexOf('Masih ada proses generate') !== -1) {
                    // Generate lain sedang berjalan untuk kuis ini: pantau prosesnya.
                    setBox('info', d.message, 'Memantau proses yang sedang berjalan…');
                    startClock();
                    schedulePoll(600);
                    return;
                }
                fail(d.message || 'Generate tidak dapat dimulai. Silakan coba lagi.');
            })
            .catch(function () {
                // Koneksi ke server terputus: proses tetap berjalan di server
                // (request tidak dibatalkan) dan polling terus memantau sampai final.
                setBox('info', 'Koneksi terputus — proses tetap berjalan di server…', 'Memantau status…');
            });
    });

    // Pesan hasil setelah muat ulang halaman.
    try {
        var raw = sessionStorage.getItem('quizGenNotice');
        if (raw) {
            sessionStorage.removeItem('quizGenNotice');
            var notice = JSON.parse(raw);
            var alertBox = document.createElement('div');
            alertBox.className = 'alert alert-' + notice.kind + ' d-flex align-items-center gap-2 mb-3';
            alertBox.setAttribute('role', 'alert');
            alertBox.textContent = notice.text;
            var anchor = document.querySelector('main.container');
            if (anchor) { anchor.insertBefore(alertBox, anchor.firstChild); }
        }
    } catch (e) {}

    // Halaman dibuka/di-refresh saat generate masih berjalan: pantau proses itu
    // sampai final — tidak membuat batch baru.
    if (active) {
        setBusy(true);
        currentBatch = active.id;
        startClock();
        setBox('info', 'Batch #' + active.id + ' sedang diproses…', '');
        schedulePoll(600);
    }
})();

// Regenerate Kode Kuis via AJAX: kode baru tampil tanpa refresh halaman.
document.getElementById('regenForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!confirm('Regenerate Kode Kuis?\n\nKode lama tidak berlaku untuk attempt baru.\nHistori kode dan hasil mahasiswa tetap tersimpan.')) return;
    var btn = document.getElementById('regenBtn');
    btn.disabled = true;
    fetch(this.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(this)
    }).then(function (r) { return r.json(); }).then(function (d) {
        btn.disabled = false;
        var msg = document.getElementById('regenMsg');
        if (d.ok) {
            document.getElementById('accessCode').textContent = d.code;
            msg.classList.remove('d-none');
            msg.style.color = '#86EFAC';
            msg.textContent = 'Kode baru diterbitkan: ' + d.code + '. Kode lama tidak berlaku untuk attempt baru.';
            setTimeout(function () { window.location.reload(); }, 2500);
        } else {
            msg.classList.remove('d-none');
            msg.style.color = '#FCA5A5';
            msg.textContent = 'Gagal menghasilkan kode baru. Silakan coba lagi.';
        }
    }).catch(function () {
        btn.disabled = false;
        var msg = document.getElementById('regenMsg');
        msg.classList.remove('d-none');
        msg.style.color = '#FCA5A5';
        msg.textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
    });
});
</script>
<?= $this->endSection() ?>
