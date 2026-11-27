/* Peningkatan tampilan konten soal: syntax highlighting (highlight.js lokal,
   sudah berjalan otomatis) + tombol salin pada setiap blok kode.
   Tanpa request eksternal — semua aset lokal. */
(function () {
    'use strict';
    function enhance() {
        document.querySelectorAll('.quiz-content pre code').forEach(function (code) {
            // Syntax highlighting (ringan; class language-xxx dari sanitizer).
            try {
                if (window.hljs && !code.dataset.hlDone) {
                    window.hljs.highlightElement(code);
                    code.dataset.hlDone = '1';
                }
            } catch (e) { /* highlighting gagal: kode tetap terbaca polos */ }

            // Tombol salin kode (satu per pre).
            var pre = code.parentElement;
            if (!pre || pre.querySelector('.code-copy-btn')) return;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-copy-btn';
            btn.textContent = 'Salin';
            btn.addEventListener('click', function () {
                var text = code.textContent;
                function done(ok) {
                    btn.textContent = ok ? 'Tersalin!' : 'Gagal';
                    setTimeout(function () { btn.textContent = 'Salin'; }, 1500);
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = text; document.body.appendChild(ta); ta.select();
                    var ok = false;
                    try { ok = document.execCommand('copy'); } catch (e) {}
                    ta.remove(); done(ok);
                }
            });
            pre.appendChild(btn);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhance);
    } else {
        enhance();
    }
})();
