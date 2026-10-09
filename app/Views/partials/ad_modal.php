<?php
/** @var array|null $ad */
/** @var int $lockSeconds */
/** @var string $placement */
if (empty($ad)) {
    return;
}
$imgUrl = base_url('/media/gfx/' . $ad['file_name']);
$target = $ad['target_url'];
$title  = $ad['title'];
?>
<div class="modal fade" id="mediaModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 25px 60px rgba(0,0,0,.4);">
            <a href="<?= base_url('ad/click/' . $ad['id']) ?>" target="_blank" rel="noopener nofollow" id="mediaLink" style="display:block;width:fit-content;max-width:100%;margin:0 auto;position:relative;background:#000;line-height:0;">
                <img src="<?= esc($imgUrl) ?>" alt="<?= esc($title) ?>" style="display:block;width:auto;height:auto;max-width:min(500px,90vw);max-height:75vh;object-fit:contain;"
                     onerror="this.style.display='none';var f=document.getElementById('mediaFallback');if(f)f.style.display='flex';">
                <span id="mediaFallback" style="display:none;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:220px;color:#fff;padding:24px;text-align:center;">
                    <i class="bi bi-image" style="font-size:2.4rem;"></i>
                    <strong style="font-size:1.05rem;"><?= esc($title) ?></strong>
                    <span style="font-size:.8rem;color:#CBD5E1;">Klik area ini untuk membuka tautan</span>
                </span>
                <span style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,.55);color:#fff;font-size:.65rem;padding:2px 6px;border-radius:4px;letter-spacing:.3px;">Iklan</span>
            </a>
            <button type="button" id="mediaCloseBtn" aria-label="Tutup iklan"
                style="position:absolute;top:10px;right:10px;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;padding:0;background:rgba(0,0,0,0.35);color:#fff;border:1.5px solid rgba(255,255,255,0.55);box-shadow:0 2px 6px rgba(0,0,0,0.3);opacity:0.65;cursor:pointer;pointer-events:none;z-index:10;transition:opacity .25s ease, transform .15s ease, background .25s ease, color .25s ease, width .25s ease, height .25s ease, border-color .25s ease, box-shadow .25s ease;">
                <i class="bi bi-x-lg" style="font-size:1.1rem;color:#fff;line-height:1;"></i>
            </button>
            <div class="text-center small text-muted py-2" id="mediaCountdown" style="background:#F8FAFC;">
                Tutup dalam <strong id="mediaCountdownNum"><?= (int) $lockSeconds ?></strong> detik
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var lock = <?= (int) $lockSeconds ?>;
    var btn  = document.getElementById('mediaCloseBtn');
    var lbl  = document.getElementById('mediaCountdownNum');
    var box  = document.getElementById('mediaCountdown');
    var modalEl = document.getElementById('mediaModal');
    var fallbackBackdrop = null;

    if (!modalEl || !btn) {
        return;
    }

    function showModal() {
        if (window.bootstrap && bootstrap.Modal) {
            var m = new bootstrap.Modal(modalEl);
            m.show();
            return;
        }

        modalEl.style.display = 'block';
        modalEl.classList.add('show');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');

        fallbackBackdrop = document.createElement('div');
        fallbackBackdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(fallbackBackdrop);
    }

    document.body.style.overflow = 'hidden';
    showModal();

    var timer = setInterval(function(){
        lock--;
        if (lock <= 0) {
            clearInterval(timer);
            btn.style.pointerEvents = 'auto';
            btn.style.width = '38px';
            btn.style.height = '38px';
            btn.style.opacity = '1';
            btn.style.background = 'rgba(220,38,38,0.95)';
            btn.style.borderColor = '#fff';
            btn.style.boxShadow = '0 3px 10px rgba(0,0,0,0.45)';
            if (box) box.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Anda bisa menutup iklan ini';
        } else if (lbl) {
            lbl.textContent = lock;
        }
    }, 1000);

    btn.addEventListener('mouseenter', function(){
        if (lock <= 0) btn.style.transform = 'scale(1.1)';
    });
    btn.addEventListener('mouseleave', function(){
        if (lock <= 0) btn.style.transform = 'scale(1)';
    });

    btn.addEventListener('click', function(){
        if (window.bootstrap && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) {
                inst.hide();
                return;
            }
        }

        modalEl.classList.remove('show');
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        if (fallbackBackdrop && fallbackBackdrop.parentNode) {
            fallbackBackdrop.parentNode.removeChild(fallbackBackdrop);
        }
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });

    modalEl.addEventListener('hidden.bs.modal', function(){
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
})();
</script>
