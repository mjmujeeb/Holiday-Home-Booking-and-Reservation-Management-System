/* HappyHolidayHome — small progressive enhancements.
 * Everything works without this file; it only makes the forms nicer.
 * Server-side checks are the real rules. */
(function () {
    'use strict';

    var fmtDate = function (d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    };
    var parseDate = function (v) {
        var p = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
        return p ? new Date(+p[1], +p[2] - 1, +p[3]) : null;
    };
    var plusDays = function (d, n) { var c = new Date(d.getTime()); c.setDate(c.getDate() + n); return c; };

    // ---- mobile navigation ------------------------------------------------
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // ---- check-in / check-out pairs, plus a live price summary ------------
    document.querySelectorAll('[data-date-pair]').forEach(function (form) {
        var inEl = form.querySelector('[data-checkin]');
        var outEl = form.querySelector('[data-checkout]');
        if (!inEl || !outEl) { return; }

        var price = parseFloat(form.getAttribute('data-price') || '');
        var currency = form.getAttribute('data-currency') || '';
        var box = form.querySelector('[data-summary]');

        var update = function () {
            var a = parseDate(inEl.value);
            if (a) {
                var min = plusDays(a, 1);
                outEl.min = fmtDate(min);
                var b0 = parseDate(outEl.value);
                if (b0 && b0 <= a) { outEl.value = fmtDate(min); }
            }
            var b = parseDate(outEl.value);
            if (box && a && b && b > a && !isNaN(price)) {
                var nights = Math.round((b - a) / 86400000);
                box.hidden = false;
                box.querySelector('[data-sum-label]').textContent =
                    currency + price.toLocaleString('en-IN') + ' × ' + nights + (nights === 1 ? ' night' : ' nights');
                box.querySelector('[data-sum-sub]').textContent = currency + (price * nights).toLocaleString('en-IN');
                box.querySelector('[data-sum-total]').textContent = currency + (price * nights).toLocaleString('en-IN');
            } else if (box) {
                box.hidden = true;
            }
            // keep the "log in to reserve" link carrying the chosen dates
            var link = document.querySelector('[data-login-link]');
            if (link) {
                var next = link.getAttribute('data-next-base');
                if (inEl.value && outEl.value) {
                    next += '&checkIn=' + encodeURIComponent(inEl.value) + '&checkOut=' + encodeURIComponent(outEl.value);
                }
                link.setAttribute('href', link.getAttribute('data-login-url') + '?next=' + encodeURIComponent(next));
            }
        };
        inEl.addEventListener('change', update);
        outEl.addEventListener('change', update);
        update();
    });

    // ---- password strength hint (advice only; the server enforces the rules) ----
    document.querySelectorAll('[data-strength]').forEach(function (input) {
        var meter = document.querySelector(input.getAttribute('data-strength'));
        if (!meter) { return; }
        var text = meter.querySelector('.pw-text');
        var labels = ['At least 10 characters, with letters and numbers.', 'Too weak', 'Getting there', 'Good', 'Strong'];
        input.addEventListener('input', function () {
            var v = input.value, score = 0;
            if (v.length >= 10) { score++; }
            if (v.length >= 14) { score++; }
            if (/[A-Za-z]/.test(v) && /\d/.test(v)) { score++; }
            if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) { score++; }
            if (v.length < 10) { score = Math.min(score, 1); }
            meter.setAttribute('data-level', v ? String(Math.max(1, score)) : '0');
            text.textContent = v ? labels[Math.max(1, score)] : labels[0];
        });
    });

    // ---- one-time code: digits only ---------------------------------------
    document.querySelectorAll('.otp-input').forEach(function (el) {
        el.addEventListener('input', function () { el.value = el.value.replace(/\D/g, '').slice(0, 6); });
    });

    // ---- confirm before destructive form submits --------------------------
    document.addEventListener('submit', function (ev) {
        var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { ev.preventDefault(); }
    });

    // ---- close the account menu when clicking elsewhere -------------------
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('.account-menu[open]').forEach(function (d) {
            if (!d.contains(ev.target)) { d.removeAttribute('open'); }
        });
    });
})();
