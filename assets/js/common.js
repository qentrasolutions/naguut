// Naguut – kleine Helfer für alle Seiten
(function () {
    'use strict';
    // Formulare mit data-confirm vor dem Absenden bestätigen lassen
    document.addEventListener('submit', function (ev) {
        var form = ev.target;
        var msg = form.getAttribute && form.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) {
            ev.preventDefault();
        }
    });
    // Buttons mit data-confirm-click (z. B. „Vorlage wiederherstellen“)
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest && ev.target.closest('[data-confirm-click]');
        if (btn && !window.confirm(btn.getAttribute('data-confirm-click'))) {
            ev.preventDefault();
        }
    });
    // Offene "Mehr"-Menüs schließen, wenn man daneben klickt
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('details.more[open]').forEach(function (d) {
            if (!d.contains(ev.target)) d.removeAttribute('open');
        });
    });
    // Auswahlfelder mit data-autosubmit schicken das Formular sofort ab (z. B. Kategorie-Filter)
    document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
        s.addEventListener('change', function () { if (s.form) s.form.submit(); });
    });
    // PIN-Felder: nur Ziffern zulassen
    document.querySelectorAll('.pin-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var v = inp.value.replace(/\D/g, '').slice(0, 6);
            if (v !== inp.value) inp.value = v;
        });
    });
})();
