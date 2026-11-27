<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h4 class="page-title mb-0"><?= esc($quiz['title']) ?> <span class="badge bg-info">Attempt #<?= (int) $attempt['attempt_no'] ?></span></h4>
    <div class="small text-muted">Soal <strong><?= (int) $index ?></strong> dari <strong><?= (int) $total ?></strong>
        &middot; Terjawab: <?= (int) $answered ?></div>
</div>

<div class="card mx-auto no-copy" id="questionCard" style="max-width: 760px;">
    <div class="card-body text-center py-3 border-bottom" style="background:#F8FAFC;border-radius:12px 12px 0 0;">
        <div id="countdown" class="fw-bold" style="font-size:3.2rem;line-height:1;letter-spacing:2px;color:#4F46E5;">--</div>
        <div class="text-muted small mt-1">detik tersisa untuk soal ini</div>
    </div>
    <div class="card-body">
        <div class="progress mb-3" style="height:6px;">
            <div class="progress-bar" id="progressBar" style="width:<?= $total > 0 ? round(($index - 1) / $total * 100) : 0 ?>%"></div>
        </div>
        <div class="quiz-content fw-semibold mb-3 quiz-protect" style="font-size:1.05rem;"><?= \App\Libraries\Quiz\HtmlSanitizer::render($question['text']) ?></div>
        <div id="optionsBox">
            <?php foreach ($question['options'] as $opt): ?>
                <div class="form-check option-row quiz-protect py-3 ps-4 border rounded mb-2" data-qid="<?= $question['id'] ?>" data-letter="<?= $opt['letter'] ?>" style="cursor:pointer;font-size:1rem;">
                    <input class="form-check-input mt-1" type="radio" name="answer"
                           id="opt<?= $opt['letter'] ?>" value="<?= $opt['letter'] ?>" style="width:1.3em;height:1.3em;">
                    <label class="form-check-label" for="opt<?= $opt['letter'] ?>">
                        <strong><?= $opt['letter'] ?>.</strong> <?= \App\Libraries\Quiz\HtmlSanitizer::render($opt['text']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <button type="button" id="nextBtn" class="btn btn-primary btn-lg w-100" disabled style="padding:.75rem;">
                <i class="bi bi-arrow-right me-1"></i><?= (int) $index < (int) $total ? 'Berikutnya' : 'Selesaikan Kuis' ?>
            </button>
            <div class="form-text mt-2">Jawaban tersimpan otomatis di server setiap Anda memilih opsi. Opsi E berarti "Tidak tahu" (nilai 0).</div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
<?= $this->section('scripts') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/highlight/github-dark.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/quiz-content.css') ?>">
<script src="<?= base_url('assets/vendor/highlight/highlight.min.js') ?>"></script>
<script src="<?= base_url('assets/js/quiz-content.js') ?>"></script>
<script>
const CSRF_NAME = '<?= csrf_token() ?>';
const CSRF_HASH = '<?= csrf_hash() ?>';
const ANSWER_URL = '<?= base_url('/student/quiz/attempt/' . $attempt['id'] . '/answer') ?>';
const RELOAD_URL = '<?= base_url('/student/quiz/attempt/' . $attempt['id']) ?>';
let remaining = <?= (int) $remainingSec ?>;
let answered = false;
let navigating = false;

const cd = document.getElementById('countdown');

function renderTimer() {
    cd.textContent = remaining;
    if (remaining <= 10) {
        cd.style.color = '#DC2626';
        cd.style.transform = 'scale(1.12)';
        cd.classList.add('pulse');
    } else if (remaining <= 30) {
        cd.style.color = '#F59E0B';
    }
}
renderTimer();

const timer = setInterval(function () {
    remaining--;
    if (remaining <= 0) {
        clearInterval(timer);
        // Waktu habis: server yang memvalidasi; muat ulang agar soal dilewati otomatis.
        cd.textContent = '0';
        navigating = true;
        window.location.href = RELOAD_URL;
        return;
    }
    renderTimer();
}, 1000);

document.querySelectorAll('.option-row').forEach(function (row) {
    row.addEventListener('click', function () {
        if (answered || navigating) return;
        const input = this.querySelector('input');
        input.checked = true;
        document.querySelectorAll('.option-row').forEach(r => r.classList.remove('border-primary', 'bg-light'));
        this.classList.add('border-primary', 'bg-light');
        document.getElementById('nextBtn').disabled = false;
    });
});

document.getElementById('nextBtn').addEventListener('click', function () {
    const selected = document.querySelector('input[name="answer"]:checked');
    if (!selected || answered || navigating) return;
    answered = true;
    navigating = true;
    const qid = document.querySelector('.option-row').dataset.qid;
    const letter = selected.value;

    fetch(ANSWER_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_HASH, '<?= csrf_header() ?>': CSRF_HASH },
        body: new URLSearchParams({ '<?= csrf_token() ?>': CSRF_HASH, question_id: qid, selected_option: letter })
    }).then(r => r.json()).then(function (res) {
        if (res.ok) {
            clearInterval(timer);
            window.location.href = res.finished ? res.redirect : res.next;
        } else {
            answered = false; navigating = false;
            if (res.redirect) { window.location.href = res.redirect; }
            else { alert('Jawaban gagal disimpan, coba lagi.'); }
        }
    }).catch(function () {
        answered = false; navigating = false;
        alert('Koneksi terputus. Jawaban belum tersimpan — pilih ulang opsi Anda.');
    });
});

// Safety net: bila tab kembali fokus dan waktu sudah lewat menurut hitungan lokal,
// biarkan server memutuskan via reload.
document.addEventListener('visibilitychange', function () {
    if (!document.hidden && remaining <= 0 && !navigating) {
        navigating = true;
        window.location.href = RELOAD_URL;
    }
});
</script>
<style>
@keyframes pulseAnim { 0%,100% { opacity:1; } 50% { opacity:.55; } }
.pulse { animation: pulseAnim .7s infinite; }
@media (max-width: 575px) { #countdown { font-size: 2.4rem !important; } }

/* Anti copy-paste: hanya pada halaman pengerjaan quiz */
.no-copy, .quiz-protect {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-touch-callout: none;
}
.quiz-protect { -webkit-user-drag: none; }
</style>
<script>
// Cegah copy/cut/klik-kanan/drag pada area soal (halaman attempt saja).
(function () {
    var card = document.getElementById('questionCard');
    if (! card) return;

    ['copy', 'cut'].forEach(function (evt) {
        card.addEventListener(evt, function (e) { e.preventDefault(); });
    });
    card.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    card.addEventListener('dragstart', function (e) { e.preventDefault(); });
    document.addEventListener('keydown', function (e) {
        // Ctrl+C / Ctrl+X / Ctrl+A pada halaman attempt
        if ((e.ctrlKey || e.metaKey) && ['c', 'x', 'a', 'u'].includes((e.key || '').toLowerCase())) {
            e.preventDefault();
        }
        // PrintScreen: hapus clipboard (upaya pengurangan, bukan perlindungan mutlak)
        if (e.key === 'PrintScreen') {
            navigator.clipboard && navigator.clipboard.writeText('');
        }
    });
})();
</script>
<?= $this->endSection() ?>
