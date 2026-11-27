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

    // ==================== Formulir kontak ====================
    var contactForm = document.getElementById('contactForm');
    if (contactForm) {
        var message = document.getElementById('contactMessage');
        var counter = document.getElementById('contactMsgCount');

        if (message && counter) {
            var updateCounter = function () {
                counter.textContent = String(message.value.length);
            };
            message.addEventListener('input', updateCounter);
            updateCounter();
        }

        var captchaImg = document.getElementById('contactCaptchaImage');
        var captchaRefresh = document.getElementById('contactCaptchaRefresh');
        if (captchaImg && captchaRefresh) {
            captchaRefresh.addEventListener('click', function () {
                var base = captchaImg.src.split('?')[0];
                captchaImg.src = base + '?new=1&t=' + Date.now();
            });
        }

        contactForm.addEventListener('submit', function () {
            var submit = document.getElementById('contactSubmit');
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Mengirim...';
            }
        });
    }
})();
