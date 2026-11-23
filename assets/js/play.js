// Naguut – Spieler-Ansicht am Handy
(function () {
    'use strict';
    var N = window.Naguut, L = N.L, esc = N.esc, AV = window.NaguutAvatars;
    var cfg = JSON.parse(document.getElementById('game-config').textContent);
    var stage = document.getElementById('stage');
    var me = document.getElementById('player-me');
    var pin = cfg.pin || '';
    var token = null;
    var poller = null, countdown = null;
    var shownKey = '';
    var current = null;
    var sending = false;

    // ---------- Speicher für den Spielschlüssel (Wiederverbinden nach Neuladen) ----------
    function storeKey() { return 'naguut.player.' + pin; }
    function loadToken() { try { return localStorage.getItem(storeKey()); } catch (e) { return null; } }
    function saveToken(t) { try { localStorage.setItem(storeKey(), t); } catch (e) { /* privat */ } }
    function clearToken() { try { localStorage.removeItem(storeKey()); } catch (e) { /* privat */ } }

    function setBody(cls) {
        document.body.classList.remove('player-body-ok', 'player-body-bad');
        if (cls) document.body.classList.add(cls);
    }

    function showError(form, msg) {
        var box = form.querySelector('.player-error');
        box.textContent = msg;
        box.hidden = !msg;
    }

    // ---------- Schritt 1: PIN ----------
    function pinForm(error) {
        stopPolling();
        setBody(null);
        me.textContent = '';
        stage.innerHTML =
            '<form class="player-card" novalidate>' +
            '<h1>' + esc(L('Game-PIN')) + '</h1>' +
            '<p>' + esc(L('Gib die sechsstellige PIN ein.')) + '</p>' +
            '<p class="player-error" role="alert" hidden></p>' +
            '<label class="visually-hidden" for="pin">' + esc(L('Game-PIN')) + '</label>' +
            '<input id="pin" class="pin-input" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="000000" value="' + esc(pin) + '">' +
            '<button type="submit" class="btn btn-sun btn-large btn-block">' + esc(L('Weiter')) + '</button>' +
            '</form>';
        var form = stage.querySelector('form');
        var input = form.querySelector('input');
        input.addEventListener('input', function () { input.value = input.value.replace(/\D/g, '').slice(0, 6); });
        showError(form, error || '');
        input.focus();
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var v = input.value.trim();
            if (!/^\d{6}$/.test(v)) { showError(form, L('Gib die sechsstellige PIN ein.')); return; }
            form.querySelector('button').disabled = true;
            N.api('GET', cfg.apiBase + v + '/check').then(function () {
                pin = v;
                history.replaceState(null, '', '?pin=' + v);
                start();
            }).catch(function (err) {
                form.querySelector('button').disabled = false;
                showError(form, err.message);
            });
        });
    }

    // ---------- Schritt 2: Nickname ----------
    function nickForm(error) {
        stopPolling();
        setBody(null);
        me.textContent = '';
        var last = '', lastAv = '';
        try { last = localStorage.getItem('naguut.lastNick') || ''; lastAv = localStorage.getItem('naguut.lastAvatar') || ''; } catch (e) { /* privat */ }
        var chosen = AV.has(lastAv) ? lastAv : AV.random();
        var grid = AV.list.map(function (a) {
            return '<label class="avatar-opt" title="' + esc(a.name) + '"><input type="radio" name="avatar" value="' + a.id + '"' +
                (a.id === chosen ? ' checked' : '') + '>' + AV.html(a.id, 56) + '</label>';
        }).join('');
        stage.innerHTML =
            '<form class="player-card" novalidate>' +
            '<h1>' + esc(L('Nickname')) + '</h1>' +
            '<p>' + esc(L('Wähle einen Nickname, den die Klasse sehen darf.')) + '</p>' +
            '<p class="player-error" role="alert" hidden></p>' +
            '<label class="visually-hidden" for="nick">' + esc(L('Nickname')) + '</label>' +
            '<input id="nick" class="nick" maxlength="15" autocomplete="off" autocapitalize="words" spellcheck="false" value="' + esc(last) + '">' +
            '<p class="player-sub">' + esc(L('Bitte keinen vollen echten Namen.')) + '</p>' +
            '<fieldset class="avatar-pick"><legend>' + esc(L('Wähle deinen Avatar')) + '</legend>' +
            '<div class="avatar-grid">' + grid + '</div></fieldset>' +
            '<button type="submit" class="btn btn-sun btn-large btn-block">' + esc(L('Los geht’s')) + '</button>' +
            '</form>';
        var form = stage.querySelector('form');
        var input = form.querySelector('input');
        showError(form, error || '');
        input.focus();
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var btn = form.querySelector('button');
            btn.disabled = true;
            var av = form.querySelector('input[name=avatar]:checked');
            N.api('POST', cfg.apiBase + pin + '/join', { nickname: input.value, avatar: av ? av.value : '' }).then(function (res) {
                token = res.token;
                saveToken(token);
                try { localStorage.setItem('naguut.lastNick', res.nickname); localStorage.setItem('naguut.lastAvatar', res.avatar); } catch (e) { /* privat */ }
                startPolling();
            }).catch(function (err) {
                btn.disabled = false;
                if (err.status === 404) { pinForm(err.message); return; }
                showError(form, err.message);
            });
        });
    }

    function start() {
        token = loadToken();
        if (token) startPolling(); else nickForm();
    }

    function startPolling() {
        stopPolling();
        shownKey = '';
        stage.innerHTML = '<p class="player-msg pulse">…</p>';
        poller = new N.Poller(cfg.apiBase + pin + '/state', { 'X-Player-Token': token }, render, function (ok) {
            var c = document.getElementById('conn-msg');
            if (!ok && !c) {
                stage.insertAdjacentHTML('afterbegin', '<p id="conn-msg" class="player-error">' + esc(L('Verbindung unterbrochen – versuche es weiter …')) + '</p>');
            } else if (ok && c) {
                c.remove();
            }
        });
        countdown = new N.Countdown(poller, updateTimer);
        poller.start();
    }

    function stopPolling() {
        if (poller) poller.stop();
        if (countdown) countdown.stop();
        poller = null;
    }

    // ---------- Darstellung ----------

    function render(s) {
        if (s.status === 'gone') {
            if (s.code === 401) { clearToken(); token = null; nickForm(); }
            else { clearToken(); pinForm(L('Zu dieser PIN läuft gerade kein Spiel.')); }
            return;
        }
        current = s;
        if (s.nickname) me.innerHTML = AV.html(s.avatar, 26) + '<span>' + esc(s.nickname) + '</span>';
        var key = s.status + ':' + s.question_no + ':' + (s.show_on_devices ? 'remote' : 'room') + (s.status === 'question' ? ':' + (s.my_answer === null ? 'open' : 'done') : '');
        if (key === shownKey) {
            if (s.status === 'lobby') {
                var c = stage.querySelector('[data-count]');
                if (c) c.textContent = L('{n} dabei', { n: s.player_count });
            }
            return;
        }
        shownKey = key;
        if (countdown) countdown.stop();
        setBody(null);
        ({ lobby: lobby, question: question, reveal: reveal, leaderboard: rank, finished: finished, kicked: kicked })[s.status](s);
    }

    function lobby(s) {
        stage.innerHTML =
            '<div class="player-card">' +
            '<h1>' + esc(L('Du bist dabei!')) + '</h1>' +
            '<div class="player-avatar">' + AV.html(s.avatar, 120) + '</div>' +
            '<p class="player-big">' + esc(s.nickname) + '</p>' +
            '<p>' + esc(L('Siehst du deinen Namen am Beamer?')) + '</p>' +
            '<p class="pulse">' + esc(L('Warte auf den Start …')) + '</p>' +
            '<p class="player-sub" data-count>' + esc(L('{n} dabei', { n: s.player_count })) + '</p>' +
            '<button type="button" class="btn btn-ghost-light btn-small" data-rename>' + esc(L('Nickname ändern')) + '</button>' +
            '</div>';
        stage.querySelector('[data-rename]').addEventListener('click', function () {
            // Alten Eintrag abmelden, dann mit neuem Namen beitreten
            var old = token;
            N.api('POST', cfg.apiBase + pin + '/leave', {}, { 'X-Player-Token': old }).catch(function () { /* egal */ }).then(function () {
                clearToken();
                token = null;
                nickForm();
            });
        });
    }

    function headHtml(s) {
        return '<div class="p-head"><span>' + esc(L('Frage {n} von {total}', { n: s.question_no + 1, total: s.question_count })) + '</span>' +
            '<span class="p-time" data-time></span></div>' + remoteQuestionHtml(s);
    }

    /** Vorlauf vor der Frage: verfügbare Zeit und Countdown bis zum Start */
    function readyHtml(s) {
        var n = s.question.time_limit_s;
        return '<div class="player-card p-ready" data-ready>' +
            '<p>' + esc(L('Du hast')) + '</p>' +
            '<p class="p-ready-time"><strong>' + n + '</strong> ' + esc(L('Sekunden')) + '</p>' +
            '<p class="p-ready-go">' + esc(L('Los geht’s in')) + ' <strong data-ready-num></strong></p></div>';
    }

    /** Distanz-Modus: Frage (und Bild) auch am Handy. text_html ist vom Server bereinigt. */
    function remoteQuestionHtml(s) {
        var q = s.question;
        if (!s.show_on_devices || !q || !q.text_html) return '';
        return '<div class="p-qtext">' + (q.image ? '<img class="p-qimg" src="' + esc(q.image) + '" alt="">' : '') +
            '<h1>' + q.text_html + '</h1></div>';
    }

    function remoteRanksHtml(s) {
        if (!s.show_on_devices || !s.top || !s.top.length) return '';
        return '<ol class="p-ranks">' + s.top.map(function (p, i) {
            return '<li' + (p.nickname === s.nickname ? ' class="me"' : '') + '><span>' + (i + 1) + '.</span>' +
                AV.html(p.avatar, 30) + '<span class="p-rname">' + esc(p.nickname) + '</span><span>' + N.fmtNum(p.score) + '</span></li>';
        }).join('') + '</ol>';
    }

    function question(s) {
        var q = s.question;
        if (s.my_answer !== null) {
            stage.innerHTML = '<div class="player-card"><h1>' + esc(L('Antwort gesendet')) + '</h1>' +
                '<p class="pulse">' + esc(L('Warte auf die anderen …')) + '</p></div>';
            return;
        }
        if (q.type === 'est') { estimateQuestion(s); return; }
        if (q.type === 'txt') { typeQuestion(s); return; }
        if (q.type === 'ord') { puzzleQuestion(s); return; }
        if (q.type === 'map') { mapQuestion(s); return; }
        var labels = q.type === 'tf' ? [L('Wahr'), L('Falsch')] : null;
        var withText = s.show_on_devices && q.answers_html && q.type !== 'tf';
        var cls = (q.type === 'tf' ? 'tf' : 'n' + q.answer_count) + (withText ? ' with-text' : '');
        var btns = '';
        for (var i = 0; i < q.answer_count; i++) {
            if (withText) {
                btns += '<button type="button" class="p-btn a' + i + '" data-answer="' + i + '" disabled>' +
                    '<span class="p-sym" aria-hidden="true">' + N.SYMBOLS[i] + '</span><span class="p-atext">' + q.answers_html[i] + '</span></button>';
                continue;
            }
            btns += '<button type="button" class="p-btn a' + i + '" data-answer="' + i + '" aria-label="' +
                esc(labels ? labels[i] : L('Antwort {n}', { n: i + 1 })) + '" disabled>' +
                '<span aria-hidden="true">' + N.SYMBOLS[i] + (labels ? '<span class="p-label">' + esc(labels[i]) + '</span>' : '') + '</span></button>';
        }
        stage.innerHTML =
            '<div class="p-question">' + headHtml(s) +
            readyHtml(s) +
            '<div class="p-buttons ' + cls + '" data-buttons hidden>' + btns + '</div>' +
            '</div>';
        stage.querySelectorAll('[data-answer]').forEach(function (b) {
            b.addEventListener('click', function () { answer(s, parseInt(b.getAttribute('data-answer'), 10), b); });
        });
        countdown.run(q.starts_ms, q.ends_ms);
    }

    // ---------- Schätzfrage mit Schieberegler ----------

    function estFmt(v, e) {
        return N.fmtNum(v, e.decimals) + (e.unit ? ' ' + e.unit : '');
    }
    function estSnap(v, e) {
        var n = Math.round((v - e.min) / e.step);
        var x = Math.max(e.min, Math.min(e.max, e.min + n * e.step));
        return Number(x.toFixed(e.decimals));
    }

    function estimateQuestion(s) {
        var e = s.question.estimate;
        var start = estSnap((e.min + e.max) / 2, e);
        stage.innerHTML =
            '<div class="p-question">' + headHtml(s) +
            readyHtml(s) +
            '<div class="p-est" data-buttons hidden>' +
            '<p class="p-est-label">' + esc(L('Deine Schätzung')) + '</p>' +
            '<output class="p-est-value" data-est-out aria-live="polite">' + esc(estFmt(start, e)) + '</output>' +
            '<input type="range" class="p-est-range" data-est-range min="' + e.min + '" max="' + e.max + '" step="' + e.step + '" value="' + start + '" aria-label="' + esc(L('Deine Schätzung')) + '">' +
            '<div class="p-est-ends"><span>' + esc(estFmt(e.min, e)) + '</span><span>' + esc(estFmt(e.max, e)) + '</span></div>' +
            '<div class="p-est-fine">' +
            '<button type="button" class="p-est-nudge" data-nudge="-1" aria-label="' + esc(L('Weniger')) + '">−</button>' +
            '<button type="button" class="btn btn-sun btn-large p-est-send" data-est-send>' + esc(L('Antwort senden')) + '</button>' +
            '<button type="button" class="p-est-nudge" data-nudge="1" aria-label="' + esc(L('Mehr')) + '">+</button>' +
            '</div></div></div>';
        var range = stage.querySelector('[data-est-range]');
        var out = stage.querySelector('[data-est-out]');
        var value = start;
        function set(v) {
            value = estSnap(v, e);
            range.value = value;
            out.textContent = estFmt(value, e);
            var p = (value - e.min) / (e.max - e.min) * 100;
            range.style.setProperty('--fill', p + '%');
        }
        set(start);
        range.addEventListener('input', function () { set(parseFloat(range.value)); });
        // Plus/Minus: antippen = ein Schritt, gedrückt halten = schneller
        stage.querySelectorAll('[data-nudge]').forEach(function (b) {
            var dir = parseInt(b.getAttribute('data-nudge'), 10), timer = null, ticks = 0;
            function stop() { clearInterval(timer); timer = null; }
            b.addEventListener('pointerdown', function (ev) {
                ev.preventDefault();
                ticks = 0;
                set(value + dir * e.step);
                stop();
                timer = setInterval(function () {
                    ticks++;
                    var mult = ticks > 25 ? Math.max(1, Math.round((e.max - e.min) / e.step / 60)) : (ticks > 8 ? 5 : 1);
                    set(value + dir * e.step * mult);
                }, 90);
            });
            ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (n) { b.addEventListener(n, stop); });
            b.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); set(value + dir * e.step); } });
        });
        stage.querySelector('[data-est-send]').addEventListener('click', function () {
            if (sending) return;
            sending = true;
            stage.querySelectorAll('[data-buttons] button, [data-est-range]').forEach(function (b) { b.disabled = true; });
            N.api('POST', cfg.apiBase + pin + '/answer', { question_no: s.question_no, value: value }, { 'X-Player-Token': token })
                .then(function () { if (poller) poller.refresh(); })
                .catch(function (err) {
                    stage.insertAdjacentHTML('afterbegin', '<p class="player-error">' + esc(err.message) + '</p>');
                    if (poller) poller.refresh();
                })
                .then(function () { sending = false; });
        });
        countdown.run(s.question.starts_ms, s.question.ends_ms);
    }

    // ---------- Antwort eingeben, Puzzle, Karte ----------

    function sendValue(s, value) {
        if (sending) return;
        sending = true;
        stage.querySelectorAll('[data-buttons] button, [data-buttons] input').forEach(function (b) { b.disabled = true; });
        N.api('POST', cfg.apiBase + pin + '/answer', { question_no: s.question_no, value: value }, { 'X-Player-Token': token })
            .then(function () { if (poller) poller.refresh(); })
            .catch(function (err) {
                stage.insertAdjacentHTML('afterbegin', '<p class="player-error">' + esc(err.message) + '</p>');
                stage.querySelectorAll('[data-buttons] button, [data-buttons] input').forEach(function (b) { b.disabled = false; });
                if (poller) poller.refresh();
            })
            .then(function () { sending = false; });
    }

    function shell(s, inner, cls) {
        stage.innerHTML =
            '<div class="p-question">' + headHtml(s) +
            readyHtml(s) +
            '<div class="' + cls + '" data-buttons hidden>' + inner + '</div></div>';
    }

    function typeQuestion(s) {
        shell(s,
            '<form class="p-type" data-type-form>' +
            '<label class="p-est-label" for="type-answer">' + esc(L('Deine Antwort')) + '</label>' +
            '<input id="type-answer" class="p-type-input" maxlength="' + (s.question.max_len || 40) + '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="send">' +
            '<button type="submit" class="btn btn-sun btn-large btn-block">' + esc(L('Antwort senden')) + '</button></form>', 'p-type-wrap');
        var form = stage.querySelector('[data-type-form]'), input = form.querySelector('input');
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (input.value.trim() === '') { input.focus(); return; }
            sendValue(s, input.value);
        });
        countdown.run(s.question.starts_ms, s.question.ends_ms);
    }

    function puzzleQuestion(s) {
        var q = s.question, n = q.answer_count, order = [];
        var withText = s.show_on_devices && q.answers_html;
        var btns = '';
        for (var i = 0; i < n; i++) {
            btns += '<button type="button" class="p-btn a' + i + (withText ? ' with-label' : '') + '" data-slot="' + i + '" aria-label="' + esc(L('Antwort {n}', { n: i + 1 })) + '">' +
                '<span class="p-sym" aria-hidden="true">' + N.SYMBOLS[i] + '</span>' + (withText ? '<span class="p-atext">' + q.answers_html[i] + '</span>' : '') +
                '<span class="p-badge" data-badge></span></button>';
        }
        shell(s,
            '<p class="p-est-label">' + esc(L('Tippe in der richtigen Reihenfolge')) + '</p>' +
            '<div class="p-order" data-order aria-live="polite"></div>' +
            '<div class="p-buttons p-puzzle n' + n + (withText ? ' with-text' : '') + '">' + btns + '</div>' +
            '<div class="p-est-fine p-puzzle-actions"><button type="button" class="btn btn-ghost-light" data-reset>' + esc(L('Neu ordnen')) + '</button>' +
            '<button type="button" class="btn btn-sun btn-large p-est-send" data-send disabled>' + esc(L('Antwort senden')) + '</button></div>', 'p-ord');
        var wrap = stage.querySelector('[data-buttons]');
        function draw() {
            wrap.querySelectorAll('[data-slot]').forEach(function (b) {
                var pos = order.indexOf(parseInt(b.getAttribute('data-slot'), 10));
                b.classList.toggle('picked', pos !== -1);
                b.querySelector('[data-badge]').textContent = pos === -1 ? '' : String(pos + 1);
            });
            wrap.querySelector('[data-order]').innerHTML = order.map(function (sl, k) {
                return '<span class="p-order-item a' + sl + '">' + (k + 1) + '. ' + N.SYMBOLS[sl] + '</span>';
            }).join('') || '&nbsp;';
            wrap.querySelector('[data-send]').disabled = order.length !== n || sending;
        }
        wrap.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-slot]');
            if (b && !b.disabled) {
                var sl = parseInt(b.getAttribute('data-slot'), 10), at = order.indexOf(sl);
                if (at === -1) order.push(sl); else order.splice(at); // erneut antippen = ab hier neu
                draw();
            }
            if (ev.target.closest('[data-reset]')) { order = []; draw(); }
            if (ev.target.closest('[data-send]') && order.length === n) sendValue(s, order.slice());
        });
        draw();
        countdown.run(q.starts_ms, q.ends_ms);
    }

    var playerMap = null;
    function mapQuestion(s) {
        var q = s.question, pinPos = null, marker = null;
        shell(s,
            '<p class="p-est-label">' + esc(L('Tippe auf die Karte, um deinen Pin zu setzen')) + '</p>' +
            '<div class="p-map" data-pmap></div>' +
            '<button type="button" class="btn btn-sun btn-large btn-block" data-send disabled>' + esc(L('Antwort senden')) + '</button>', 'p-mapwrap');
        var wrap = stage.querySelector('[data-buttons]'), send = wrap.querySelector('[data-send]');
        var v = q.view || cfg.map.default_view;
        var started = false;
        // Die Karte erst aufbauen, wenn sie sichtbar ist (sonst kennt sie ihre Größe nicht)
        var obs = new MutationObserver(function () {
            if (started || wrap.hidden) return;
            started = true;
            obs.disconnect();
            playerMap = new NaguutMap(wrap.querySelector('[data-pmap]'), { lat: v.lat, lng: v.lng, zoom: v.zoom, tiles: cfg.map.tiles, onTap: function (lat, lng) {
                if (send.disabled && sending) return;
                pinPos = { lat: lat, lng: lng };
                if (!marker) marker = playerMap.marker(lat, lng, { cls: 'mine' }); else marker.move(lat, lng);
                send.disabled = false;
            } });
        });
        obs.observe(wrap, { attributes: true, attributeFilter: ['hidden'] });
        send.addEventListener('click', function () { if (pinPos) sendValue(s, pinPos); });
        countdown.run(q.starts_ms, q.ends_ms);
    }

    function resultExtraHtml(s) {
        var q = s.question, r = s.result;
        if (!q) return '';
        if (q.type === 'txt' && s.accepted) {
            return '<p class="result-est">' + (r.text ? esc(L('Deine Antwort: {v}', { v: r.text })) + '<br>' : '') +
                '<strong>' + esc(L('Richtig: {v}', { v: s.accepted[0] })) + '</strong></p>';
        }
        if (q.type === 'ord' && s.correct_order) {
            var line = function (ord) { return ord.map(function (sl) { return '<span class="sym-a' + sl + '">' + N.SYMBOLS[sl] + '</span>'; }).join(' '); };
            var named = q.answers_html ? '<ol class="p-solution">' + s.correct_order.map(function (sl) { return '<li>' + N.SYMBOLS[sl] + ' ' + q.answers_html[sl] + '</li>'; }).join('') + '</ol>' : '';
            return '<p class="result-est">' + (r.order ? esc(L('Deine Reihenfolge:')) + ' ' + line(r.order) + '<br>' : '') +
                '<strong>' + esc(L('Richtig:')) + '</strong> ' + line(s.correct_order) + '</p>' + (s.show_on_devices ? named : '');
        }
        if (q.type === 'map' && s.target) {
            return '<p class="result-est">' + (r.distance ? esc(L('{v} vom Ziel entfernt', { v: r.distance })) + '<br>' : '') +
                (s.target.label ? '<strong>' + esc(s.target.label) + '</strong>' : '') + '</p><div class="p-map p-map-result" data-rmap></div>';
        }
        return '';
    }

    function mountResultMap(s) {
        var el = stage.querySelector('[data-rmap]');
        if (!el) return;
        var t = s.target, v = s.question.view || cfg.map.default_view;
        var m = new NaguutMap(el, { lat: v.lat, lng: v.lng, zoom: v.zoom, tiles: cfg.map.tiles });
        m.circle(t.lat, t.lng, t.radius_km);
        var pts = NaguutMap.circleBounds(t.lat, t.lng, t.radius_km);
        if (s.result.pin) { m.marker(s.result.pin.lat, s.result.pin.lng, { cls: 'mine' }); pts.push(s.result.pin); }
        m.marker(t.lat, t.lng, { cls: 'target' });
        m.fit(pts, 12);
    }

    function updateTimer(t) {
        var ready = stage.querySelector('[data-ready]');
        var buttons = stage.querySelector('[data-buttons]');
        var time = stage.querySelector('[data-time]');
        if (!buttons) return;
        if (t.ready) {
            stage.querySelector('[data-ready-num]').textContent = Math.ceil(t.readyLeft / 1000);
        } else if (!ready.hidden) {
            ready.hidden = true;
            buttons.hidden = false;
            buttons.querySelectorAll('button:not([data-send])').forEach(function (b) { b.disabled = false; });
            if (navigator.vibrate) { try { navigator.vibrate(30); } catch (e) { /* egal */ } }
        }
        if (time) {
            var sec = Math.ceil(t.left / 1000);
            time.textContent = t.ready ? '' : sec;
            time.classList.toggle('urgent', !t.ready && sec <= 5);
            time.setAttribute('aria-label', t.ready ? '' : L('noch {n} Sekunden', { n: sec }));
        }
        if (t.left === 0 && !t.ready) {
            buttons.querySelectorAll('button, input').forEach(function (b) { b.disabled = true; });
            setTimeout(function () { if (poller) poller.refresh(); }, 200);
        }
    }

    function answer(s, idx, btn) {
        if (sending) return;
        sending = true;
        var all = stage.querySelectorAll('[data-answer]');
        all.forEach(function (b) { b.classList.add('locked'); b.disabled = true; });
        btn.classList.add('chosen');
        N.api('POST', cfg.apiBase + pin + '/answer', { question_no: s.question_no, answer_idx: idx }, { 'X-Player-Token': token })
            .then(function () {
                if (poller) poller.refresh();
            })
            .catch(function (err) {
                stage.insertAdjacentHTML('afterbegin', '<p class="player-error">' + esc(err.message) + '</p>');
                if (poller) poller.refresh();
            })
            .then(function () { sending = false; });
    }

    function reveal(s) {
        var r = s.result;
        var kind = !r.answered ? 'none' : (r.correct ? 'ok' : 'bad');
        setBody(kind === 'ok' ? 'player-body-ok' : (kind === 'bad' ? 'player-body-bad' : null));
        var title = kind === 'ok' ? L('Richtig!') : (kind === 'bad' ? L('Leider falsch') : L('Keine Antwort'));
        var est = s.question && s.question.type === 'est' ? s.question.estimate : null;
        var estLine = '';
        if (est) {
            if (kind === 'ok') title = Math.abs(r.value - est.correct) < est.step / 1000 ? L('Volltreffer!') : L('Gut geschätzt!');
            if (kind === 'bad') title = L('Daneben');
            estLine = '<p class="result-est">' + (r.value !== null ? esc(L('Deine Schätzung: {v}', { v: estFmt(r.value, est) })) + '<br>' : '') +
                '<strong>' + esc(L('Richtig: {v}', { v: estFmt(est.correct, est) })) + '</strong></p>';
        }
        if (s.question && s.question.type === 'map') {
            if (kind === 'ok') title = L('Im Umkreis!');
            if (kind === 'bad') title = L('Daneben');
        }
        var mark = kind === 'ok' ? '✓' : (kind === 'bad' ? '✗' : '–');
        stage.innerHTML =
            '<div class="result ' + kind + '">' +
            '<div class="result-mark" aria-hidden="true">' + mark + '</div>' +
            '<p class="player-big">' + esc(title) + '</p>' + estLine + resultExtraHtml(s) + remoteCorrectHtml(s) +
            (r.points ? '<p class="result-points">' + esc(L('+{n} Punkte', { n: N.fmtNum(r.points) })) + '</p>' : '') +
            '<p class="result-rank">' + esc(L('Platz {n} von {total}', { n: s.rank, total: s.player_total })) + ', ' +
            esc(L('{n} Punkte', { n: N.fmtNum(s.score) })) + '</p>' +
            '</div>';
        mountResultMap(s);
    }

    function remoteCorrectHtml(s) {
        var q = s.question;
        if (!s.show_on_devices || !q || !q.answers_html || (q.type !== 'mc' && q.type !== 'tf') || !s.correct) return '';
        var parts = s.correct.map(function (i) {
            return '<span class="p-correct-item"><span aria-hidden="true">' + N.SYMBOLS[i] + '</span> ' + (q.type === 'tf' ? esc(i === 0 ? L('Wahr') : L('Falsch')) : q.answers_html[i]) + '</span>';
        }).join('');
        return '<p class="result-est"><strong>' + esc(L('Richtig:')) + '</strong> ' + parts + '</p>';
    }

    function rank(s) {
        stage.innerHTML =
            '<div class="player-card">' +
            '<h1>' + esc(L('Rangliste')) + '</h1>' +
            '<p class="player-big">' + esc(L('Platz {n} von {total}', { n: s.rank, total: s.player_total })) + '</p>' +
            '<p>' + esc(L('{n} Punkte', { n: N.fmtNum(s.score) })) + '</p>' +
            remoteRanksHtml(s) +
            '</div>';
    }

    function finished(s) {
        stopPolling();
        clearToken();
        var podium = s.rank > 0 && s.rank <= 3;
        if (podium) setBody('player-body-ok');
        stage.innerHTML =
            '<div class="player-card">' +
            '<h1>' + esc(L('Endergebnis')) + '</h1>' +
            '<div class="player-avatar">' + AV.html(s.avatar, 96) + '</div>' +
            '<p class="player-big">' + esc(L('Platz {n} von {total}', { n: s.rank, total: s.player_total })) + '</p>' +
            '<p>' + esc(L('{n} Punkte', { n: N.fmtNum(s.score) })) + '</p>' +
            '<p>' + esc(podium ? L('Du bist auf dem Podest!') : L('Danke fürs Mitspielen!')) + '</p>' +
            '<a class="btn btn-ghost-light" href="' + esc(cfg.homeUrl) + '">' + esc(L('Zur Startseite')) + '</a>' +
            '</div>';
    }

    function kicked() {
        stopPolling();
        clearToken();
        token = null;
        stage.innerHTML =
            '<div class="player-card"><h1>' + esc(L('Du wurdest aus dem Spiel entfernt.')) + '</h1>' +
            '<button type="button" class="btn btn-sun btn-large" data-rejoin>' + esc(L('Neu beitreten')) + '</button></div>';
        stage.querySelector('[data-rejoin]').addEventListener('click', function () { nickForm(); });
    }

    // Wenn das Handy aus dem Standby kommt, sofort nachfragen
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && poller) poller.refresh();
    });

    if (/^\d{6}$/.test(pin)) {
        // PIN aus QR-Code oder Startseite: erst prüfen, dann Nickname
        N.api('GET', cfg.apiBase + pin + '/check').then(start).catch(function (err) {
            if (loadToken()) { start(); return; }
            pinForm(err.message);
        });
    } else {
        pinForm();
    }
})();
