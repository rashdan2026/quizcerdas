<?php
/** @var array $student */
$studentName      = $student['nama']         ?? '';
$studentEmail     = $student['email']        ?? '';
$studentNpm       = $student['npm']          ?? '';
$studentKelas     = $student['kelas']        ?? '';
$studentJenkel    = $student['jenkel']       ?? '';
$studentWhatsapp  = $student['no_whatsapp']  ?? '';
?>
<div class="modal fade" id="profileCompleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 25px 60px rgba(0,0,0,.4);">
            <div class="modal-header" style="background:linear-gradient(135deg,#4F46E5,#7C3AED);color:#fff;border:none;padding:1.1rem 1.25rem;">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center" style="width:36px;height:36px;background:rgba(255,255,255,.18);border-radius:8px;">
                        <i class="bi bi-person-vcard-fill fs-5"></i>
                    </span>
                    <div>
                        <h6 class="mb-0 fw-700">Lengkapi Profil Mahasiswa</h6>
                        <small style="opacity:.85;">Wajib diisi sebelum melanjutkan</small>
                    </div>
                </div>
            </div>
            <form id="profileCompleteForm" autocomplete="off" novalidate>
                <?= csrf_field() ?>
                <div class="modal-body" style="padding:1.25rem 1.25rem .75rem;">
                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size:.8rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        Halo <strong><?= esc($studentName) ?></strong>, mohon lengkapi semua data profil Anda. Semua field wajib diisi.
                        Setelah disimpan, profil tidak dapat diedit lagi sampai 1 minggu ke depan.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-500">NPM <span class="text-danger">*</span></label>
                            <input type="text" name="npm" id="pcNpm"
                                   class="form-control"
                                   maxlength="9"
                                   placeholder="9 digit NPM"
                                   value="<?= esc($studentNpm) ?>"
                                   required
                                   inputmode="numeric">
                            <small class="text-muted">9 digit angka.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-500">Kelas <span class="text-danger">*</span></label>
                            <input type="text" name="kelas" id="pcKelas"
                                   class="form-control text-uppercase"
                                   maxlength="20"
                                   placeholder="Contoh: A"
                                   value="<?= esc($studentKelas) ?>"
                                   required
                                   pattern="^[A-Za-z]+$"
                                   style="letter-spacing:1px;font-weight:600;">
                            <small class="text-muted">Hanya huruf besar A-Z.</small>
                        </div>
                    </div>

                    <div class="mb-2 mt-3">
                        <label class="form-label fw-500">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" id="pcNama"
                               class="form-control"
                               maxlength="100"
                               placeholder="Contoh: Afiq Khalifi"
                               value="<?= esc($studentName) ?>"
                               required
                               style="text-transform:none;">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Petunjuk:</strong> Ketik nama lengkap Anda — sistem akan otomatis merapikan ke format <em>Title Case</em>
                            (huruf awal setiap kata kapital, sisanya kecil). Contoh: <code>afiq khalifi</code> → tersimpan sebagai
                            <code>Afiq Khalifi</code>, <code>MUHAMMAD SYAFIQ</code> → <code>Muhammad Syafiq</code>.
                        </small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-500">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="jenkel" id="pcJenkel" class="form-select" required>
                                <option value="">-- Pilih Jenis Kelamin --</option>
                                <option value="Laki-Laki" <?= $studentJenkel === 'Laki-Laki' ? 'selected' : '' ?>>Laki-Laki</option>
                                <option value="Perempuan"  <?= $studentJenkel === 'Perempuan'  ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-500">No. WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="no_whatsapp" id="pcWhatsapp"
                                   class="form-control"
                                   maxlength="15"
                                   placeholder="08xxxxxxxxxx"
                                   value="<?= esc($studentWhatsapp) ?>"
                                   required
                                   inputmode="numeric">
                            <small class="text-muted">Maks 15 digit, tidak boleh kosong.</small>
                        </div>
                    </div>

                    <div id="pcError" class="alert alert-danger py-2 px-3 mb-0 mt-3" style="display:none;font-size:.82rem;"></div>
                </div>
        <div class="modal-footer" style="border-top:1px solid #F1F5F9;padding:.9rem 1.25rem;">
            <button type="submit" id="pcSubmitBtn" class="btn btn-primary w-100 fw-600 py-2">
                <i class="bi bi-check-circle me-1"></i>Simpan &amp; Lanjutkan
            </button>
        </div>
    </form>
    </div>
</div>

<script>
(function () {
    // Auto-format nama ke Title Case pada event blur (ketika user selesai
    // mengetik). Server-side normalizeName() di Student\Dashboard akan
    // memvalidasi ulang, tapi preview UI ini membantu user langsung melihat
    // hasil sebelum submit.
    var namaEl = document.getElementById('pcNama');
    if (!namaEl) { return; }
    namaEl.addEventListener('blur', function () {
        var raw = (namaEl.value || '').trim();
        if (raw === '') { return; }
        // Collapse whitespace
        var cleaned = raw.replace(/\s+/g, ' ');
        // Title Case: huruf pertama tiap kata kapital, sisanya kecil
        var titled = cleaned.toLowerCase().replace(/(^|\s|[-'])(\p{L})/gu, function (_, sep, ch) {
            return sep + ch.toUpperCase();
        });
        if (titled !== cleaned) {
            namaEl.value = titled;
        }
    });
})();
</script>
</div>
