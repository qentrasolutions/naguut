// Naguut – Quiz-Editor
(function () {
    'use strict';
    var I18N = JSON.parse(document.getElementById('i18n').textContent);
    var cfg = JSON.parse(document.getElementById('editor-config').textContent);
    var quiz = JSON.parse(document.getElementById('quiz-data').textContent);
    var SYMBOLS = ['▲', '◆', '●', '■'];

    function L(key, vars) {
        var s = I18N[key] || key;
        if (vars) Object.keys(vars).forEach(function (k) { s = s.split('{' + k + '}').join(vars[k]); });
        return s;
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    var $title = document.getElementById('q-title');
    var $desc = document.getElementById('q-desc');
    var $lang = document.getElementById('q-lang');
    var $visRadios = document.querySelectorAll('#q-visibility input[name=visibility]');
    var $cover = document.getElementById('cover-box');
    var $list = document.getElementById('questions');
    var $state = document.getElementById('save-state');
    var $errors = document.getElementById('editor-errors');
    var $picker = document.getElementById('file-picker');
    var $saveBtn = document.getElementById('save-btn');
    var $music = document.getElementById('music-box');
    var $soundPicker = document.getElementById('sound-picker');

    var changes = 0;      // Zähler für Änderungen
    var savedAt = 0;      // Stand beim letzten erfolgreichen Speichern
    var saving = null;    // laufendes Speichern (Promise)
    var pickTarget = null;

    // ---------- Modell ----------

    function blankEstimate() {
        return { min: 0, max: 100, step: 1, correct: 50, tolerance: 10, unit: '' };
    }

    function blankQuestion(type) {
        var blank = function (n, ok) { var a = []; for (var k = 0; k < n; k++) a.push({ text: '', is_correct: !!ok }); return a; };
        var answers = { tf: [{ text: L('Wahr'), is_correct: true }, { text: L('Falsch'), is_correct: false }],
            est: [], map: [], txt: blank(1, true), ord: blank(4, true) }[type] || blank(4, false);
        return {
            type: type, text: '', image: null, time_limit_s: (type === 'est' || type === 'map' || type === 'ord') ? 30 : 20, points_factor: 1,
            answers: answers,
            estimate: type === 'est' ? blankEstimate() : null,
            map: type === 'map' ? { lat: null, lng: null, radius_km: 50, view: null, label: '' } : null
        };
    }

    // ---------- Antwort eingeben / Puzzle ----------

    function listHtml(q, kind) {
        var max = kind === 'txt' ? 5 : 4, min = kind === 'txt' ? 1 : 2;
        var rows = q.answers.map(function (a, ai) {
            var label = kind === 'txt' ? L('Richtige Antwort {n}', { n: ai + 1 }) : L('Begriff {n}', { n: ai + 1 });
            return '<div class="ans list-' + kind + '">' +
                '<span class="ans-sym ans-num" aria-hidden="true">' + (kind === 'ord' ? (ai + 1) + '.' : '✓') + '</span>' +
                '<input type="text" data-a="' + ai + '" maxlength="' + (kind === 'txt' ? cfg.maxTyped : cfg.maxAnswer) + '" value="' + esc(a.text) + '" placeholder="' + esc(label) + '" aria-label="' + esc(label) + '">' +
                (q.answers.length > min ? '<button type="button" class="ans-remove" data-act="ans-remove" data-ai="' + ai + '" aria-label="' + esc(L('Entfernen')) + '" title="' + esc(L('Entfernen')) + '">×</button>' : '<span></span>') +
                '</div>';
        }).join('');
        if (q.answers.length < max) {
            rows += '<button type="button" class="btn btn-small btn-outline ans-add" data-act="ans-add">' +
                esc(kind === 'txt' ? L('Weitere Schreibweise') : L('Begriff hinzufügen')) + '</button>';
        }
        var note = kind === 'txt'
            ? L('Zählt nur bei exakter Übereinstimmung. Groß-/Kleinschreibung und Leerzeichen am Rand sind egal. Trage Schreibvarianten als weitere richtige Antworten ein.')
            : L('In der richtigen Reihenfolge eintragen. Die Teilnehmenden bekommen die Begriffe gemischt und tippen sie der Reihe nach an.');
        return rows + '<p class="answers-note">' + esc(note) + '</p>';
    }

    // ---------- Karte ----------

    var maps = {}; // Kartenobjekte je Frage (Index)

    function mapHtml(q) {
        var m = q.map;
        return '<div class="map-editor">' +
            '<div class="map-box" data-mapbox></div>' +
            '<div class="map-fields">' +
            '<p class="map-target" data-map-target>' + esc(m.lat == null ? L('Tippe auf die Karte, um den richtigen Ort zu setzen.') : L('Richtiger Ort: {lat}, {lng}', { lat: m.lat, lng: m.lng })) + '</p>' +
            '<label class="field"><span>' + esc(L('Ortsname (wird bei der Auflösung gezeigt)')) + '</span><input type="text" data-mapf="label" maxlength="60" value="' + esc(m.label || '') + '" placeholder="' + esc(L('z. B. Stephansdom')) + '"></label>' +
            '<label class="field"><span>' + esc(L('Umkreis für Punkte (km)')) + '</span><input type="text" inputmode="decimal" data-mapf="radius_km" value="' + esc(shown(m.radius_km)) + '"></label>' +
            '<p class="est-hint">' + esc(L('Volltreffer = volle Punkte, am Rand des Umkreises die Hälfte, außerhalb keine.')) + '</p>' +
            '<label class="check"><input type="checkbox" data-mapf="ownview"' + (m.view ? ' checked' : '') + '><span>' + esc(L('Eigener Kartenausschnitt für diese Frage')) +
            '<small>' + esc(L('Sonst gilt der Standard-Ausschnitt aus der Verwaltung.')) + '</small></span></label>' +
            '<button type="button" class="btn btn-small btn-outline" data-act="map-view">' + esc(L('Aktuellen Ausschnitt übernehmen')) + '</button>' +
            '</div></div>';
    }

    function initMaps() {
        maps = {};
        if (!window.NaguutMap) return;
        quiz.questions.forEach(function (q, i) {
            if (q.type !== 'map') return;
            var box = $list.querySelector('[data-i="' + i + '"] [data-mapbox]');
            if (!box) return;
            var v = q.map.view || cfg.map.default_view;
            var pin = null, circ = null;
            var mp = new NaguutMap(box, { lat: v.lat, lng: v.lng, zoom: v.zoom, tiles: cfg.map.tiles, onTap: function (lat, lng) {
                q.map.lat = lat; q.map.lng = lng;
                draw();
                box.closest('.map-editor').querySelector('[data-map-target]').textContent = L('Richtiger Ort: {lat}, {lng}', { lat: lat, lng: lng });
                changed();
            } });
            function draw() {
                if (q.map.lat == null) return;
                var km = num(q.map.radius_km) > 0 ? num(q.map.radius_km) : 0;
                if (!pin) pin = mp.marker(q.map.lat, q.map.lng, { cls: 'target' }); else pin.move(q.map.lat, q.map.lng);
                if (!circ) circ = mp.circle(q.map.lat, q.map.lng, km); else circ.update(q.map.lat, q.map.lng, km);
            }
            draw();
            maps[i] = { map: mp, draw: draw };
        });
    }

    // ---------- Schätzfrage ----------

    var EST_FIELDS = [
        ['min', 'Von'], ['max', 'Bis'], ['step', 'Schrittweite'],
        ['correct', 'Richtiger Wert'], ['tolerance', 'Toleranz ±'], ['unit', 'Einheit']
    ];

    function num(v) {
        if (typeof v === 'number') return v;
        var s = String(v == null ? '' : v).trim().replace(/\s/g, '');
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        else if (/^-?[1-9]\d{0,2}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
        return s === '' || isNaN(Number(s)) ? NaN : Number(s);
    }
    function shown(v) {
        return typeof v === 'number' ? String(v).replace('.', ',') : String(v == null ? '' : v);
    }
    function decimalsOf(step) {
        return Math.min(4, (String(num(step)).split('.')[1] || '').length);
    }
    /** wie am Server: 1.250,5 */
    function fmt(v, e) {
        if (isNaN(v)) return '?';
        var d = decimalsOf(e.step), parts = Math.abs(v).toFixed(d).split('.');
        return (v < 0 ? '-' : '') + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + (parts[1] ? ',' + parts[1] : '') + (e.unit ? ' ' + e.unit : '');
    }

    /** Vorschau: Skala mit richtigem Wert und Toleranzbereich */
    function estPreview(e) {
        var min = num(e.min), max = num(e.max), c = num(e.correct), tol = Math.max(0, num(e.tolerance) || 0), step = num(e.step);
        if (isNaN(min) || isNaN(max) || isNaN(c) || min >= max) {
            return '<p class="est-hint">' + esc(L('Gib „Von“, „Bis“ und den richtigen Wert als Zahlen ein.')) + '</p>';
        }
        if (step > 0) { // wie der Server: richtigen Wert auf die Schrittweite runden
            c = Math.max(min, Math.min(max, min + Math.round((c - min) / step) * step));
            c = Number(c.toFixed(decimalsOf(step)));
        }
        var pct = function (v) { return Math.max(0, Math.min(100, (v - min) / (max - min) * 100)); };
        return '<div class="est-scale" aria-hidden="true">' +
            '<span class="est-band" style="left:' + pct(c - tol) + '%;width:' + (pct(c + tol) - pct(c - tol)) + '%"></span>' +
            '<span class="est-mark" style="left:' + pct(c) + '%"></span></div>' +
            '<div class="est-ends"><span>' + esc(fmt(min, e)) + '</span><span>' + esc(fmt(max, e)) + '</span></div>' +
            '<p class="est-hint">' + esc(tol > 0
                ? L('Volle Punkte bei {c}. Punkte gibt es zwischen {a} und {b}, je näher, desto mehr.', { c: fmt(c, e), a: fmt(Math.max(min, c - tol), e), b: fmt(Math.min(max, c + tol), e) })
                : L('Punkte gibt es nur für genau {c}.', { c: fmt(c, e) })) + '</p>';
    }

    function estimateHtml(q) {
        var e = q.estimate || (q.estimate = blankEstimate());
        return '<div class="est-editor">' +
            '<div class="est-grid">' + EST_FIELDS.map(function (f) {
                var isUnit = f[0] === 'unit';
                return '<label class="field"><span>' + esc(L(f[1])) + '</span>' +
                    '<input type="text" data-est="' + f[0] + '" value="' + esc(shown(e[f[0]])) + '"' +
                    (isUnit ? ' maxlength="12" placeholder="' + esc(L('z. B. m, €, km/h')) + '"' : ' inputmode="decimal"') + '></label>';
            }).join('') + '</div>' +
            '<div class="est-preview" data-est-preview>' + estPreview(e) + '</div></div>';
    }

    // ---------- Formatierung (Fragen und Antworten) ----------

    function fmtBarHtml() {
        return '<div class="fmtbar" role="toolbar" aria-label="' + esc(L('Formatierung')) + '">' +
            [['strong', '<b>B</b>', 'Fett'], ['em', '<i>I</i>', 'Kursiv'], ['u', '<u>U</u>', 'Unterstrichen'],
             ['sup', 'x<sup>2</sup>', 'Hochgestellt'], ['sub', 'x<sub>2</sub>', 'Tiefgestellt'], ['br', '↵', 'Zeilenumbruch']]
                .map(function (b) {
                    return '<button type="button" class="fmtbtn" data-fmt="' + b[0] + '" title="' + esc(L(b[2])) + '" aria-label="' + esc(L(b[2])) + '">' + b[1] + '</button>';
                }).join('') + '<span class="fmt-tip">' + esc(L('wirkt auf das zuletzt angeklickte Feld')) + '</span></div>';
    }

    function changed() {
        changes++;
        updateState();
    }

    function isDirty() { return changes !== savedAt; }

    function updateState(mode) {
        $state.classList.remove('dirty', 'error');
        if (mode === 'saving') { $state.textContent = L('Wird gespeichert …'); return; }
        if (mode === 'error') { $state.textContent = L('Speichern fehlgeschlagen'); $state.classList.add('error'); return; }
        if (isDirty()) { $state.textContent = L('Nicht gespeicherte Änderungen'); $state.classList.add('dirty'); }
        else { $state.textContent = L('Gespeichert'); }
    }

    // ---------- Einstellungen ----------

    $title.value = quiz.title;
    $desc.value = quiz.description;
    $lang.value = quiz.language;
    $visRadios.forEach(function (r) { r.checked = (r.value === 'public') === !!quiz.is_public; });
    var $devices = document.getElementById('q-devices');
    $devices.checked = !!quiz.show_on_devices;
    $devices.addEventListener('change', function () { quiz.show_on_devices = $devices.checked; changed(); });
    $title.addEventListener('input', function () { quiz.title = $title.value; changed(); });
    $desc.addEventListener('input', function () { quiz.description = $desc.value; changed(); });
    $lang.addEventListener('change', function () { quiz.language = $lang.value; changed(); });

    // ---------- Kategorie und Stichwörter ----------
    var $cat = document.getElementById('q-category');
    $cat.value = quiz.category_id ? String(quiz.category_id) : '';
    if ($cat.value === '' && quiz.category_id) quiz.category_id = null; // Kategorie wurde gelöscht
    $cat.addEventListener('change', function () { quiz.category_id = $cat.value ? parseInt($cat.value, 10) : null; changed(); });

    quiz.tags = quiz.tags || [];
    var $tagChips = document.getElementById('q-tag-chips');
    var $tagInput = document.getElementById('q-tag-input');
    function normTag(t) {
        return t.replace(/^#+/, '').replace(/\s+/g, ' ').replace(/,/g, '').trim().toLowerCase().slice(0, 30);
    }
    function renderTags() {
        $tagChips.innerHTML = quiz.tags.map(function (t, k) {
            return '<span class="tag-chip">' + esc(t) + '<button type="button" data-tag-rm="' + k + '" aria-label="' + esc(L('Stichwort entfernen: {t}', { t: t })) + '">×</button></span>';
        }).join('');
    }
    function addTags(text) {
        var added = false;
        text.split(/[,;]+/).forEach(function (raw) {
            var t = normTag(raw);
            if (!t || quiz.tags.indexOf(t) !== -1) return;
            if (quiz.tags.length >= cfg.maxTags) { showErrors([L('Höchstens {n} Stichwörter pro Quiz.', { n: cfg.maxTags })]); return; }
            quiz.tags.push(t); added = true;
        });
        if (added) { renderTags(); changed(); }
    }
    $tagInput.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ',') {
            ev.preventDefault();
            addTags($tagInput.value); $tagInput.value = '';
        } else if (ev.key === 'Backspace' && $tagInput.value === '' && quiz.tags.length) {
            quiz.tags.pop(); renderTags(); changed();
        }
    });
    $tagInput.addEventListener('blur', function () { if ($tagInput.value.trim()) { addTags($tagInput.value); $tagInput.value = ''; } });
    $tagInput.addEventListener('paste', function () { setTimeout(function () { if ($tagInput.value.indexOf(',') !== -1) { addTags($tagInput.value); $tagInput.value = ''; } }, 0); });
    document.getElementById('q-tags').addEventListener('click', function (ev) {
        var rm = ev.target.closest('[data-tag-rm]');
        if (rm) { quiz.tags.splice(parseInt(rm.getAttribute('data-tag-rm'), 10), 1); renderTags(); changed(); $tagInput.focus(); }
        else if (ev.target === this || ev.target === $tagChips) $tagInput.focus();
    });
    renderTags();
    $visRadios.forEach(function (r) { r.addEventListener('change', function () { quiz.is_public = r.value === 'public' && r.checked; changed(); }); });

    function imgBoxHtml(target, file, label) {
        if (file) {
            return '<div class="imgbox has-image" style="background-image:url(\'' + esc(cfg.uploadBase + file) + '\')">' +
                '<button type="button" class="img-pick" data-img="' + target + '"><span class="visually-hidden">' + esc(label) + '</span></button>' +
                '<button type="button" class="img-remove" data-img-remove="' + target + '">' + esc(L('Bild entfernen')) + '</button></div>';
        }
        return '<div class="imgbox"><button type="button" class="img-pick" data-img="' + target + '">' + esc(L('Bild hinzufügen')) + '</button></div>';
    }

    function renderCover() {
        $cover.innerHTML = imgBoxHtml('cover', quiz.cover_image, L('Bild hinzufügen'));
    }

    // ---------- Musik ----------

    function renderMusic(busy) {
        if (busy) {
            $music.innerHTML = '<p class="muted">' + esc(L('Musik wird hochgeladen …')) + '</p>';
            return;
        }
        if (quiz.music) {
            $music.innerHTML = '<audio controls preload="none" src="' + esc(cfg.uploadBase + quiz.music) + '"></audio>' +
                '<div class="btn-row"><button type="button" class="btn btn-small btn-outline" data-music-pick>' + esc(L('Andere Datei wählen')) + '</button>' +
                '<button type="button" class="btn btn-small btn-ghost" data-music-remove>' + esc(L('Musik entfernen')) + '</button></div>';
        } else {
            $music.innerHTML = '<button type="button" class="btn btn-small btn-outline" data-music-pick>' + esc(L('Musik hochladen')) + '</button>';
        }
    }

    $music.addEventListener('click', function (ev) {
        if (ev.target.closest('[data-music-pick]')) {
            $soundPicker.value = '';
            $soundPicker.click();
        } else if (ev.target.closest('[data-music-remove]')) {
            quiz.music = null;
            renderMusic();
            changed();
        }
    });

    $soundPicker.addEventListener('change', function () {
        var file = $soundPicker.files[0];
        if (!file) return;
        if (file.size > cfg.maxSoundMb * 1024 * 1024) {
            showErrors([L('Die Sounddatei darf höchstens {n} MB groß sein.', { n: cfg.maxSoundMb })]);
            return;
        }
        renderMusic(true);
        var fd = new FormData();
        fd.append('sound', file);
        fetch(cfg.soundUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' } })
            .then(function (res) { return res.json().catch(function () { return {}; }).then(function (d) { if (!res.ok) throw new Error(d.error || ('HTTP ' + res.status)); return d; }); })
            .then(function (d) { quiz.music = d.file; showErrors(null); renderMusic(); changed(); })
            .catch(function (err) { showErrors([err.message]); renderMusic(); });
    });

    // ---------- Fragen ----------

    function questionHtml(q, i) {
        var n = quiz.questions.length;
        var times = cfg.timeLimits.map(function (t) {
            return '<option value="' + t + '"' + (t === q.time_limit_s ? ' selected' : '') + '>' + t + ' ' + esc(L('Sekunden')) + '</option>';
        }).join('');
        var points = [[1, L('Standard')], [2, L('Doppelt')], [0, L('Keine')]].map(function (p) {
            return '<option value="' + p[0] + '"' + (p[0] === q.points_factor ? ' selected' : '') + '>' + esc(p[1]) + '</option>';
        }).join('');

        var answers;
        if (q.type === 'txt' || q.type === 'ord') {
            answers = listHtml(q, q.type);
        } else if (q.type === 'map') {
            answers = mapHtml(q);
        } else if (q.type === 'est') {
            answers = estimateHtml(q);
        } else if (q.type === 'tf') {
            answers = q.answers.map(function (a, ai) {
                return '<div class="ans a' + ai + (a.is_correct ? ' is-correct' : '') + '">' +
                    '<span class="ans-sym" aria-hidden="true">' + SYMBOLS[ai] + '</span>' +
                    '<span class="ans-tf">' + esc(ai === 0 ? L('Wahr') : L('Falsch')) + '</span>' +
                    '<label class="ans-correct"><input type="radio" name="tf-' + i + '" data-c="' + ai + '"' + (a.is_correct ? ' checked' : '') + '> ' + esc(L('richtig')) + '</label></div>';
            }).join('');
        } else {
            answers = q.answers.map(function (a, ai) {
                return '<div class="ans a' + ai + (a.is_correct ? ' is-correct' : '') + '">' +
                    '<span class="ans-sym" aria-hidden="true">' + SYMBOLS[ai] + '</span>' +
                    '<input type="text" data-a="' + ai + '" maxlength="' + cfg.maxAnswer + '" value="' + esc(a.text) + '" placeholder="' + esc(L('Antwort {n}', { n: ai + 1 })) + '" aria-label="' + esc(L('Antwort {n}', { n: ai + 1 })) + '">' +
                    '<label class="ans-correct"><input type="checkbox" data-c="' + ai + '"' + (a.is_correct ? ' checked' : '') + '> ' + esc(L('richtig')) + '</label>' +
                    (q.answers.length > 2 ? '<button type="button" class="ans-remove" data-act="ans-remove" data-ai="' + ai + '" aria-label="' + esc(L('Antwort entfernen')) + '" title="' + esc(L('Antwort entfernen')) + '">×</button>' : '<span></span>') +
                    '</div>';
            }).join('');
            if (q.answers.length < 4) {
                answers += '<button type="button" class="btn btn-small btn-outline ans-add" data-act="ans-add">' + esc(L('Antwort hinzufügen')) + '</button>';
            }
        }

        return '<li class="qcard" data-i="' + i + '">' +
            '<div class="qhead">' +
            '<button type="button" class="qhandle" title="' + esc(L('Zum Verschieben ziehen')) + '" aria-label="' + esc(L('Zum Verschieben ziehen')) + '">⠿</button>' +
            '<span class="qnum">' + esc(L('Frage')) + ' ' + (i + 1) + '</span>' +
            '<label class="qtype"><select data-f="type" aria-label="Fragetyp">' +
            '<option value="mc"' + (q.type === 'mc' ? ' selected' : '') + '>' + esc(L('Multiple Choice')) + '</option>' +
            '<option value="tf"' + (q.type === 'tf' ? ' selected' : '') + '>' + esc(L('Wahr oder Falsch')) + '</option>' +
            '<option value="est"' + (q.type === 'est' ? ' selected' : '') + '>' + esc(L('Schätzfrage (Schieberegler)')) + '</option>' +
            '<option value="txt"' + (q.type === 'txt' ? ' selected' : '') + '>' + esc(L('Antwort eingeben')) + '</option>' +
            '<option value="ord"' + (q.type === 'ord' ? ' selected' : '') + '>' + esc(L('Puzzle (Reihenfolge)')) + '</option>' +
            '<option value="map"' + (q.type === 'map' ? ' selected' : '') + '>' + esc(L('Karte (Pin setzen)')) + '</option></select></label>' +
            '<div class="qtools">' +
            '<button type="button" class="iconbtn" data-act="up" title="' + esc(L('Nach oben')) + '" aria-label="' + esc(L('Nach oben')) + '"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
            '<button type="button" class="iconbtn" data-act="down" title="' + esc(L('Nach unten')) + '" aria-label="' + esc(L('Nach unten')) + '"' + (i === n - 1 ? ' disabled' : '') + '>↓</button>' +
            '<button type="button" class="iconbtn" data-act="dup">' + esc(L('Duplizieren')) + '</button>' +
            '<button type="button" class="iconbtn danger" data-act="del">' + esc(L('Löschen')) + '</button>' +
            '</div></div>' +
            '<div class="qbody">' +
            '<div class="qtext">' + fmtBarHtml() + '<textarea data-f="text" rows="3" maxlength="' + cfg.maxText + '" placeholder="' + esc(L('Fragetext')) + '" aria-label="' + esc(L('Fragetext')) + '">' + esc(q.text) + '</textarea>' +
            '<div class="qcount" data-count>' + q.text.length + ' / ' + cfg.maxText + '</div></div>' +
            '<div class="qside">' +
            '<div class="cover-q">' + imgBoxHtml('q:' + i, q.image, L('Bild der Frage')) + '</div>' +
            '<label class="field"><span>' + esc(L('Zeitlimit')) + '</span><select data-f="time_limit_s">' + times + '</select></label>' +
            '<label class="field"><span>' + esc(L('Punkte')) + '</span><select data-f="points_factor">' + points + '</select></label>' +
            '</div>' +
            '<div class="answers">' + answers + '</div>' +
            '</div></li>';
    }

    function renderQuestions(focusIndex) {
        if (!quiz.questions.length) {
            $list.innerHTML = '<li class="qempty">' + esc(L('Noch keine Fragen. Füge unten die erste hinzu.')) + '</li>';
            return;
        }
        $list.innerHTML = quiz.questions.map(questionHtml).join('');
        initMaps();
        if (focusIndex !== undefined) {
            var card = $list.querySelector('[data-i="' + focusIndex + '"]');
            if (card) {
                card.scrollIntoView({ block: 'center', behavior: 'smooth' });
                var ta = card.querySelector('textarea');
                if (ta) ta.focus({ preventScroll: true });
            }
        }
    }

    function cardIndex(el) {
        var card = el.closest('.qcard');
        return card ? parseInt(card.getAttribute('data-i'), 10) : -1;
    }

    function move(from, to) {
        if (to < 0 || to >= quiz.questions.length || from === to) return;
        var q = quiz.questions.splice(from, 1)[0];
        quiz.questions.splice(to, 0, q);
        changed();
        renderQuestions();
        var btn = $list.querySelector('[data-i="' + to + '"] .qhandle');
        if (btn) btn.focus();
    }

    $list.addEventListener('input', function (ev) {
        var t = ev.target, i = cardIndex(t);
        if (i < 0) return;
        var q = quiz.questions[i];
        if (t.matches('[data-f="text"]')) {
            q.text = t.value;
            t.closest('.qtext').querySelector('[data-count]').textContent = t.value.length + ' / ' + cfg.maxText;
            changed();
        } else if (t.matches('[data-est]')) {
            var key = t.getAttribute('data-est');
            q.estimate[key] = t.value;
            t.closest('.est-editor').querySelector('[data-est-preview]').innerHTML = estPreview(q.estimate);
            changed();
        } else if (t.matches('[data-a]')) {
            q.answers[parseInt(t.getAttribute('data-a'), 10)].text = t.value;
            changed();
        } else if (t.matches('[data-mapf="label"]')) {
            q.map.label = t.value; changed();
        } else if (t.matches('[data-mapf="radius_km"]')) {
            q.map.radius_km = t.value;
            if (maps[i]) maps[i].draw();
            changed();
        }
    });

    $list.addEventListener('change', function (ev) {
        var t = ev.target, i = cardIndex(t);
        if (i < 0) return;
        var q = quiz.questions[i];
        if (t.matches('[data-f="type"]')) {
            var fresh = blankQuestion(t.value);
            if (t.value === 'mc' && q.type === 'tf') {
                fresh.answers[0].text = L('Wahr');
                fresh.answers[1].text = L('Falsch');
                fresh.answers = fresh.answers.slice(0, 2);
                fresh.answers[0].is_correct = q.answers[0].is_correct;
                fresh.answers[1].is_correct = q.answers[1].is_correct;
            }
            q.type = t.value;
            q.answers = fresh.answers;
            q.estimate = fresh.estimate;
            q.map = fresh.map;
            if ((t.value === 'est' || t.value === 'map' || t.value === 'ord') && q.time_limit_s < 20) q.time_limit_s = 30;
            changed();
            renderQuestions();
        } else if (t.matches('[data-mapf="ownview"]')) {
            q.map.view = t.checked && maps[i] ? maps[i].map.getView() : null;
            changed();
        } else if (t.matches('[data-f="time_limit_s"]')) {
            q.time_limit_s = parseInt(t.value, 10); changed();
        } else if (t.matches('[data-f="points_factor"]')) {
            q.points_factor = parseInt(t.value, 10); changed();
        } else if (t.matches('[data-c]')) {
            var ai = parseInt(t.getAttribute('data-c'), 10);
            if (q.type === 'tf') {
                q.answers.forEach(function (a, k) { a.is_correct = k === ai; });
            } else {
                q.answers[ai].is_correct = t.checked;
            }
            t.closest('.answers').querySelectorAll('.ans').forEach(function (el, k) {
                el.classList.toggle('is-correct', q.answers[k].is_correct);
            });
            changed();
        }
    });

    var lastField = null;
    $list.addEventListener('focusin', function (ev) {
        if (ev.target.matches('[data-f="text"], [data-a]')) lastField = ev.target;
    });
    $list.addEventListener('mousedown', function (ev) {
        if (ev.target.closest('[data-fmt]')) ev.preventDefault();
    });
    $list.addEventListener('click', function (ev) {
        var fb = ev.target.closest('[data-fmt]');
        if (!fb) return;
        var card = fb.closest('.qcard');
        var el = lastField && card.contains(lastField) ? lastField : card.querySelector('[data-f="text"]');
        window.NaguutRT.apply(el, fb.getAttribute('data-fmt'));
    });

    $list.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-act]');
        if (!btn) return;
        var i = cardIndex(btn), q = quiz.questions[i];
        switch (btn.getAttribute('data-act')) {
            case 'up': move(i, i - 1); break;
            case 'down': move(i, i + 1); break;
            case 'dup':
                quiz.questions.splice(i + 1, 0, JSON.parse(JSON.stringify(q)));
                changed(); renderQuestions(i + 1); break;
            case 'del':
                if (String(q.text).trim() === '' || confirm(L('Diese Frage löschen?'))) {
                    quiz.questions.splice(i, 1); changed(); renderQuestions();
                }
                break;
            case 'map-view':
                if (maps[i]) {
                    q.map.view = maps[i].map.getView();
                    btn.closest('.map-editor').querySelector('[data-mapf="ownview"]').checked = true;
                    changed();
                }
                break;
            case 'ans-add':
                q.answers.push({ text: '', is_correct: q.type === 'txt' || q.type === 'ord' }); changed(); renderQuestions();
                var inputs = $list.querySelectorAll('[data-i="' + i + '"] [data-a]');
                if (inputs.length) inputs[inputs.length - 1].focus();
                break;
            case 'ans-remove':
                q.answers.splice(parseInt(btn.getAttribute('data-ai'), 10), 1); changed(); renderQuestions(); break;
        }
    });

    document.querySelectorAll('[data-add]').forEach(function (b) {
        b.addEventListener('click', function () {
            quiz.questions.push(blankQuestion(b.getAttribute('data-add')));
            changed();
            renderQuestions(quiz.questions.length - 1);
        });
    });

    // ---------- Ziehen und Ablegen ----------

    var dragFrom = -1;
    $list.addEventListener('mousedown', function (ev) {
        var h = ev.target.closest('.qhandle');
        if (h) h.closest('.qcard').setAttribute('draggable', 'true');
    });
    $list.addEventListener('dragstart', function (ev) {
        var card = ev.target.closest('.qcard');
        if (!card) return;
        dragFrom = parseInt(card.getAttribute('data-i'), 10);
        card.classList.add('dragging');
        ev.dataTransfer.effectAllowed = 'move';
        try { ev.dataTransfer.setData('text/plain', String(dragFrom)); } catch (e) { /* IE */ }
    });
    function clearDrop() {
        $list.querySelectorAll('.drop-before, .drop-after').forEach(function (el) { el.classList.remove('drop-before', 'drop-after'); });
    }
    $list.addEventListener('dragover', function (ev) {
        if (dragFrom < 0) return;
        var card = ev.target.closest('.qcard');
        if (!card) return;
        ev.preventDefault();
        clearDrop();
        var r = card.getBoundingClientRect();
        card.classList.add(ev.clientY < r.top + r.height / 2 ? 'drop-before' : 'drop-after');
    });
    $list.addEventListener('drop', function (ev) {
        if (dragFrom < 0) return;
        var card = ev.target.closest('.qcard');
        ev.preventDefault();
        if (!card) return;
        var to = parseInt(card.getAttribute('data-i'), 10);
        if (card.classList.contains('drop-after')) to++;
        if (dragFrom < to) to--;
        var from = dragFrom;
        dragFrom = -1;
        move(from, to);
    });
    $list.addEventListener('dragend', function () {
        dragFrom = -1;
        clearDrop();
        $list.querySelectorAll('.qcard').forEach(function (c) { c.classList.remove('dragging'); c.removeAttribute('draggable'); });
    });

    // ---------- Bilder ----------

    document.addEventListener('click', function (ev) {
        var pick = ev.target.closest('[data-img]');
        if (pick) {
            pickTarget = pick.getAttribute('data-img');
            $picker.value = '';
            $picker.click();
            return;
        }
        var rm = ev.target.closest('[data-img-remove]');
        if (rm) {
            setImage(rm.getAttribute('data-img-remove'), null);
        }
    });

    function setImage(target, file) {
        if (target === 'cover') {
            quiz.cover_image = file;
            renderCover();
        } else {
            var i = parseInt(target.split(':')[1], 10);
            quiz.questions[i].image = file;
            var box = $list.querySelector('[data-i="' + i + '"] .cover-q');
            if (box) box.innerHTML = imgBoxHtml(target, file, L('Bild der Frage'));
        }
        changed();
    }

    $picker.addEventListener('change', function () {
        var file = $picker.files[0];
        var target = pickTarget;
        if (!file || !target) return;
        if (file.size > 2 * 1024 * 1024) {
            showErrors([L('Das Bild ist größer als 2 MB.')]);
            return;
        }
        var box = target === 'cover' ? $cover.querySelector('.imgbox') : $list.querySelector('[data-i="' + target.split(':')[1] + '"] .imgbox');
        if (box) { box.classList.add('busy'); box.querySelector('.img-pick').textContent = L('Bild wird hochgeladen …'); }
        var fd = new FormData();
        fd.append('image', file);
        fetch(cfg.imageUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' } })
            .then(function (res) { return res.json().then(function (d) { if (!res.ok) throw new Error(d.error || 'Upload'); return d; }); })
            .then(function (d) { setImage(target, d.file); })
            .catch(function (err) {
                showErrors([err.message]);
                if (target === 'cover') renderCover(); else renderQuestions();
            });
    });

    // ---------- Fragen importieren ----------

    var $importBtn = document.getElementById('import-btn');
    var $importPicker = document.getElementById('import-picker');
    var $importChoose = document.getElementById('import-choose');
    var $importPick = document.getElementById('import-pick');
    var $importResult = document.getElementById('import-result');
    var importFile = null;

    function importBusy(busy) {
        $importBtn.disabled = busy;
        $importBtn.textContent = busy ? L('Wird importiert …') : L('Fragen importieren …');
        document.getElementById('import-pick-btn').disabled = busy;
    }

    function showImportResult(html, kind) {
        $importResult.className = 'import-result flash flash-' + (kind || 'success');
        $importResult.innerHTML = html;
        $importResult.hidden = !html;
        if (html) $importResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function adoptMeta(m) {
        if (m.title) { quiz.title = m.title; $title.value = m.title; }
        if (m.description) { quiz.description = m.description; $desc.value = m.description; }
        if (m.language) { quiz.language = m.language; $lang.value = m.language; }
        if (m.tags && m.tags.length) { quiz.tags = m.tags.slice(0, cfg.maxTags); renderTags(); }
        if (m.category_id) { $cat.value = String(m.category_id); if ($cat.value) quiz.category_id = m.category_id; }
        if (m.cover_image) { quiz.cover_image = m.cover_image; renderCover(); }
        if (m.show_on_devices) { quiz.show_on_devices = true; $devices.checked = true; }
    }

    function runImport(pick) {
        if (!importFile) return;
        var room = cfg.maxQuestions - quiz.questions.length;
        if (room <= 0) {
            showImportResult('<p>' + esc(L('Das Quiz ist schon voll ({n} Fragen).', { n: cfg.maxQuestions })) + '</p>', 'error');
            return;
        }
        var adopt = !quiz.questions.length && (!quiz.title.trim() || quiz.title === 'Neues Quiz');
        var fd = new FormData();
        fd.append('file', importFile);
        if (pick !== null) fd.append('pick', String(pick));
        if (adopt) fd.append('adopt', '1');
        importBusy(true);
        showErrors(null);
        fetch(cfg.importUrl, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' } })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (d) {
                    if (!res.ok) { var err = new Error(d.error || ('HTTP ' + res.status)); err.list = d.errors; throw err; }
                    return d;
                });
            })
            .then(function (d) {
                if (d.choose) {
                    $importPick.innerHTML = d.choose.map(function (c) {
                        return '<option value="' + c.index + '">' + esc(L(c.count === 1 ? '{title} (1 Frage)' : '{title} ({n} Fragen)', { title: c.title, n: c.count })) + '</option>';
                    }).join('');
                    $importChoose.hidden = false;
                    showImportResult('');
                    $importPick.focus();
                    return;
                }
                $importChoose.hidden = true;
                var add = d.questions.slice(0, room);
                var first = quiz.questions.length;
                add.forEach(function (q) {
                    q.estimate = q.estimate || null;
                    q.map = q.map || null;
                    quiz.questions.push(q);
                });
                if (adopt && d.meta) adoptMeta(d.meta);
                changed();
                renderQuestions();
                var html = '<p><strong>' + esc(add.length === 1 ? L('1 Frage importiert.') : L('{n} Fragen importiert.', { n: add.length })) + '</strong> ' +
                    esc(L('Klicke auf „Speichern“, um sie zu behalten.')) + '</p>';
                if (adopt && d.meta && d.meta.title) html += '<p>' + esc(L('Titel, Beschreibung und Stichwörter wurden aus der Datei übernommen.')) + '</p>';
                if (d.questions.length > add.length) {
                    html += '<p>' + esc(L('Ein Quiz hat höchstens {n} Fragen. {m} Fragen aus der Datei haben keinen Platz mehr.', { n: cfg.maxQuestions, m: d.questions.length - add.length })) + '</p>';
                }
                var notes = (d.errors || []).concat(d.warnings || []);
                if (d.errors && d.errors.length) {
                    html += '<p>' + esc(L('{n} Zeilen bzw. Fragen wurden übersprungen:', { n: d.error_count || d.errors.length })) + '</p>';
                }
                if (notes.length) {
                    html += '<ul>' + notes.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') +
                        ((d.error_count || 0) > d.errors.length ? '<li>' + esc(L('und {n} weitere', { n: d.error_count - d.errors.length })) + '</li>' : '') + '</ul>';
                }
                showImportResult(html, d.errors && d.errors.length ? 'info' : 'success');
                var card = $list.querySelector('[data-i="' + first + '"]');
                if (card) card.classList.add('just-imported');
            })
            .catch(function (err) {
                var msg = err.message === 'Failed to fetch' ? L('Die Verbindung zum Server ist unterbrochen. Prüfe das Internet und speichere erneut.') : err.message;
                showImportResult('<p><strong>' + esc(msg) + '</strong></p>' + (err.list && err.list.length
                    ? '<ul>' + err.list.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>' : ''), 'error');
            })
            .then(function () { importBusy(false); });
    }

    $importBtn.addEventListener('click', function () { $importPicker.value = ''; $importPicker.click(); });
    $importPicker.addEventListener('change', function () {
        importFile = $importPicker.files[0] || null;
        $importChoose.hidden = true;
        runImport(null);
    });
    document.getElementById('import-pick-btn').addEventListener('click', function () { runImport(parseInt($importPick.value, 10)); });
    document.getElementById('import-cancel').addEventListener('click', function () { $importChoose.hidden = true; importFile = null; });

    // ---------- Speichern ----------

    function showErrors(list) {
        if (!list || !list.length) { $errors.hidden = true; $errors.innerHTML = ''; return; }
        $errors.innerHTML = list.length === 1 ? '<p>' + esc(list[0]) + '</p>' : '<ul>' + list.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>';
        $errors.hidden = false;
        $errors.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function save() {
        if (saving) return saving;
        var startedAt = changes;
        updateState('saving');
        $saveBtn.disabled = true;
        var body = {
            title: quiz.title, description: quiz.description, language: quiz.language,
            category_id: quiz.category_id, tags: quiz.tags.concat($tagInput.value.trim() ? [normTag($tagInput.value)] : []),
            cover_image: quiz.cover_image, music: quiz.music, is_public: quiz.is_public, show_on_devices: quiz.show_on_devices, questions: quiz.questions
        };
        saving = fetch(cfg.saveUrl, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': cfg.csrf, 'X-HTTP-Method-Override': 'PUT' },
            body: JSON.stringify(body)
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (d) {
                if (!res.ok) {
                    var err = new Error(d.error || ('HTTP ' + res.status));
                    err.list = d.errors;
                    throw err;
                }
                return d;
            });
        }).then(function () {
            savedAt = startedAt;
            showErrors(null);
            updateState();
            return true;
        }).catch(function (err) {
            updateState('error');
            showErrors(err.list || [err.message === 'Failed to fetch' ? L('Die Verbindung zum Server ist unterbrochen. Prüfe das Internet und speichere erneut.') : err.message]);
            return false;
        }).then(function (ok) {
            saving = null;
            $saveBtn.disabled = false;
            return ok;
        });
        return saving;
    }

    $saveBtn.addEventListener('click', save);
    document.addEventListener('keydown', function (ev) {
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
            ev.preventDefault();
            save();
        }
    });

    // Vor dem Spielstart ungespeicherte Änderungen sichern
    var playForm = document.getElementById('play-form');
    playForm.addEventListener('submit', function (ev) {
        if (!isDirty() && quiz.questions.length) return;
        ev.preventDefault();
        save().then(function (ok) {
            if (ok) {
                window.removeEventListener('beforeunload', warn);
                playForm.submit();
            }
        });
    });

    function warn(ev) {
        if (isDirty()) { ev.preventDefault(); ev.returnValue = ''; }
    }
    window.addEventListener('beforeunload', warn);

    renderCover();
    renderMusic();
    renderQuestions();
    if (!quiz.questions.length && quiz.title === 'Neues Quiz') {
        $title.select();
    }
})();
