/**
 * Countdown tombol permintaan email (reset password / kirim ulang).
 * Sisa waktu diambil dari SERVER (data-wait = detik), bukan dari jam perangkat,
 * sehingga mengubah jam browser tidak memperpanjang/memperpendek batas.
 * Server tetap memvalidasi ulang setiap permintaan.
 *
 * Pemakaian:
 *   kmResendCountdown(btnId, labelId)
 *   kmResendCountdown(btnId, labelId, 'Kirim Link Reset', waitTextId, waitInfoId)
 */
(function () {
    'use strict';

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmt(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return pad(m) + ':' + pad(s);
    }

    window.kmResendCountdown = function (btnId, labelId, originalLabel, waitTextId, waitInfoId) {
        var btn = document.getElementById(btnId);
        if (!btn || btn.disabled) return;

        var initial = parseInt(btn.getAttribute('data-wait') || '0', 10);
        if (initial <= 0) return;

        var label    = document.getElementById(labelId) || btn.querySelector('span');
        var waitText = waitTextId ? document.getElementById(waitTextId) : null;
        var waitInfo = waitInfoId ? document.getElementById(waitInfoId) : null;
        var original = originalLabel || 'Kirim Ulang Email';

        // Baseline waktu: momen halaman dirender (server memberi sisa detik).
        var deadline = Date.now() + initial * 1000;

        btn.disabled = true;

        function render(secondsLeft) {
            if (label) label.textContent = original + ' (' + fmt(secondsLeft) + ')';
            else btn.textContent = original + ' (' + fmt(secondsLeft) + ')';
            if (waitText) waitText.textContent = fmt(secondsLeft);
        }

        var timer = setInterval(function () {
            var left = Math.round((deadline - Date.now()) / 1000);
            if (left <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                if (label) label.textContent = original;
                else btn.textContent = original;
                btn.removeAttribute('data-wait');
                if (waitInfo) waitInfo.style.display = 'none';
                return;
            }
            render(left);
        }, 1000);

        render(initial);
    };

    // Cegah double-submit: tombol dinonaktifkan segera saat form dikirim.
    document.addEventListener('submit', function (e) {
        var btn = e.target && e.target.querySelector ? e.target.querySelector('#btnResend, #btnReset, button[id^="btnResend"], button[id^="btnReset"]') : null;
        if (btn && !btn.disabled) {
            btn.disabled = true;
            setTimeout(function () { btn.disabled = false; }, 4000);
        }
    });
})();
