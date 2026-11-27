(function () {
    'use strict';

    var toggle = document.getElementById('navToggle');
    var menu   = document.getElementById('mobileMenu');
    var iconO  = document.getElementById('navIconOpen');
    var iconC  = document.getElementById('navIconClose');

    function setMenu(open) {
        if (!menu || !toggle) return;
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        if (iconO && iconC) {
            iconO.style.display = open ? 'none' : '';
            iconC.style.display = open ? '' : 'none';
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            setMenu(!menu.classList.contains('is-open'));
        });
    }

    // Tutup menu setelah memilih tautan
    Array.prototype.forEach.call(document.querySelectorAll('[data-close-menu]'), function (link) {
        link.addEventListener('click', function () { setMenu(false); });
    });

    // Tutup menu saat layar diperbesar ke desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) setMenu(false);
    });

    // Tutup menu dengan tombol Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMenu(false);
    });
})();
