(function () {
    'use strict';

    // dialogs: [data-open="id"] opens, [data-close] closes
    document.addEventListener('click', function (ev) {
        var opener = ev.target.closest('[data-open]');
        if (opener) {
            var dlg = document.getElementById(opener.getAttribute('data-open'));
            if (dlg && dlg.showModal) { dlg.showModal(); }
            return;
        }
        var closer = ev.target.closest('[data-close]');
        if (closer) { var d = closer.closest('dialog'); if (d) { d.close(); } return; }
        if (ev.target.tagName === 'DIALOG') { ev.target.close(); }   // click on the backdrop
    });

    // confirm before destructive submits
    document.addEventListener('submit', function (ev) {
        var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { ev.preventDefault(); }
    });

    // theme toggle
    var t = document.querySelector('[data-theme-toggle]');
    if (t) {
        t.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('hhh-admin-theme', next); } catch (e) { /* private mode */ }
        });
    }

    // mobile sidebar
    var side = document.getElementById('sidebar');
    var btn = document.querySelector('.side-toggle');
    if (side && btn) {
        btn.addEventListener('click', function () {
            var open = side.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.otp-input').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, '').slice(0, 6); });
    });
})();
