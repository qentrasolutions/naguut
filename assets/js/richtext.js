// Naguut – Formatierungsleiste für Textfelder.
// Fügt grundlegende HTML-Elemente um die Auswahl ein. Der Server lässt nur
// erlaubte Elemente durch; alles andere erscheint als normaler Text.
(function (global) {
    'use strict';

    /** Setzt before/after um die Auswahl in einem Textfeld (oder fügt einen Platzhalter ein) */
    function wrap(el, before, after, placeholder) {
        if (!el) return;
        var start = el.selectionStart, end = el.selectionEnd, v = el.value;
        var sel = v.slice(start, end) || placeholder || '';
        el.value = v.slice(0, start) + before + sel + after + v.slice(end);
        el.focus();
        var s = start + before.length;
        el.setSelectionRange(s, s + sel.length);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /** Macht aus markierten Zeilen eine Liste */
    function list(el, tag, itemPlaceholder) {
        var start = el.selectionStart, end = el.selectionEnd, v = el.value;
        var sel = v.slice(start, end);
        var lines = sel ? sel.split('\n').filter(function (l) { return l.trim() !== ''; }) : [itemPlaceholder, itemPlaceholder];
        var html = '<' + tag + '>\n' + lines.map(function (l) { return '  <li>' + l.replace(/^\s*[-*]\s+/, '') + '</li>'; }).join('\n') + '\n</' + tag + '>';
        el.value = v.slice(0, start) + html + v.slice(end);
        el.focus();
        el.setSelectionRange(start, start + html.length);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function insert(el, text) {
        var start = el.selectionStart, v = el.value;
        el.value = v.slice(0, start) + text + v.slice(el.selectionEnd);
        el.focus();
        el.setSelectionRange(start + text.length, start + text.length);
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /** Führt einen Befehl der Leiste aus. cmd: z. B. "strong", "ul", "link", "br", "hr" */
    function apply(el, cmd, labels) {
        labels = labels || {};
        switch (cmd) {
            case 'ul': case 'ol': list(el, cmd, labels.item || 'Punkt'); break;
            case 'br': insert(el, '<br>'); break;
            case 'hr': insert(el, '\n<hr>\n'); break;
            case 'link':
                var url = global.prompt(labels.linkPrompt || 'Adresse des Links (https://…)', 'https://');
                if (!url || url === 'https://') { el.focus(); return; }
                wrap(el, '<a href="' + url.replace(/"/g, '%22') + '">', '</a>', labels.linkText || 'Linktext');
                break;
            case 'p': wrap(el, '<p>', '</p>', labels.text || 'Text'); break;
            case 'h2': case 'h3': wrap(el, '<' + cmd + '>', '</' + cmd + '>', labels.heading || 'Überschrift'); break;
            default: wrap(el, '<' + cmd + '>', '</' + cmd + '>', labels.text || 'Text');
        }
    }

    // Statische Leisten (z. B. Rechtstexte): <div data-rt-toolbar="textarea-id"> mit Buttons data-rt="strong" usw.
    document.addEventListener('mousedown', function (ev) {
        if (ev.target.closest('[data-rt]')) ev.preventDefault(); // Fokus und Auswahl im Textfeld behalten
    });
    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-rt]');
        var bar = btn && btn.closest('[data-rt-toolbar]');
        if (!bar || !bar.getAttribute('data-rt-toolbar')) return;
        var el = document.getElementById(bar.getAttribute('data-rt-toolbar'));
        apply(el, btn.getAttribute('data-rt'), bar.dataset);
    });

    global.NaguutRT = { wrap: wrap, apply: apply };
})(window);
