/**
 * Countdown tombol "Kirim Ulang Email".
 * Sisa waktu diambil dari SERVER (data-wait = detik), bukan dari jam perangkat,
 * sehingga mengubah jam browser tidak memperpanjang/memperpendek batas.
 * Server tetap memvalidasi ulang setiap permintaan resend.
 */
(function () {
    'use strict';

    function pad(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function fmt(sec) {
        var m = Math.floor(sec / 60);
        var s = sec % 60;
        return pad(m) + ':' + pad(s);
    }

    window.kmResendCountdown = function (btnId, labelId) {
        var btn = document.getElementById(btnId);
        if (!btn || btn.disabled) return;

        var initial = parseInt(btn.getAttribute('data-wait') || '0', 10);
        if (initial <= 0) return;

        var label = document.getElementById(labelId) || btn.querySelector('span');
        var original = 'Kirim Ulang Email';

        // Baseline waktu: momen halaman dirender (server memberi sisa detik).
        var deadline = Date.now() + initial * 1000;

        btn.disabled = true;

        var timer = setInterval(function () {
            var left = Math.round((deadline - Date.now()) / 1000);
            if (left <= 0) {
                clearInterval(timer);
                btn.disabled = false;
                if (label) label.textContent = original;
                else btn.textContent = original;
                btn.removeAttribute('data-wait');
                return;
            }
            if (label) label.textContent = original + ' (' + fmt(left) + ')';
            else btn.textContent = original + ' (' + fmt(left) + ')';
        }, 1000);

        // Render langsung nilai awal.
        if (label) label.textContent = original + ' (' + fmt(initial) + ')';
        else btn.textContent = original + ' (' + fmt(initial) + ')';
    };

    // Cegah double-submit: tombol dinonaktifkan segera saat form dikirim.
    document.addEventListener('submit', function (e) {
        var btn = e.target && e.target.querySelector ? e.target.querySelector('#btnResend, button[id^="btnResend"]') : null;
        if (btn && !btn.disabled) {
            btn.disabled = true;
            setTimeout(function () { btn.disabled = false; }, 4000); // pemulihan bila submit gagal (validasi CSRF)
        }
    });
})();
