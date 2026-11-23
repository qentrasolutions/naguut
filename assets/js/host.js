// Naguut – Host-Ansicht am Beamer
(function () {
    'use strict';
    var N = window.Naguut, L = N.L, esc = N.esc, AV = window.NaguutAvatars;
    var cfg = JSON.parse(document.getElementById('game-config').textContent);
    var stage = document.getElementById('stage');
    var conn = document.getElementById('conn');
    var headers = { 'X-CSRF-Token': cfg.csrf };
    var current = null;     // zuletzt dargestellter Status
    var shownKey = '';      // Phase + Frage, die gerade aufgebaut ist
    var busy = false;

    var poller = new N.Poller(cfg.stateUrl, {}, render, function (ok) {
        conn.hidden = ok;
        conn.textContent = ok ? '' : L('Verbindung unterbrochen – versuche es weiter …');
    });
    var countdown = new N.Countdown(poller, updateTimer);
    poller.start();

    function post(url, body) {
        return N.api('POST', url, body || {}, headers);
    }

    function skip() {
        if (busy || !current) return;
        var msg = { question: 'Diese Frage überspringen? Sie wird nicht gewertet.',
            reveal: 'Diese Frage nicht werten? Bereits vergebene Punkte werden abgezogen.',
            leaderboard: 'Die nächste Frage überspringen?' }[current.status];
        if (!msg || !confirm(L(msg))) return;
        busy = true;
        post(cfg.skipUrl, { from: current.status }).catch(function (err) {
            alert(err.message);
        }).then(function () {
            busy = false;
            poller.refresh();
        });
    }

    // ---------- Musik ----------
    var music = null, musicBtn = document.getElementById('music-btn'), musicWanted = true;
    if (cfg.musicUrl && musicBtn) {
        try { musicWanted = localStorage.getItem('naguut.music') !== 'off'; } catch (e) { /* privat */ }
        music = new Audio(cfg.musicUrl);
        music.loop = true;
        music.preload = 'auto';
        music.volume = 0.6;
        var showMusic = function () {
            var on = !music.paused;
            musicBtn.textContent = on ? L('Ton aus') : L('Ton an');
            musicBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        };
        var playMusic = function () {
            if (!musicWanted) return;
            var p = music.play();
            if (p && p.catch) p.catch(function () { /* Browser wartet auf ersten Klick */ });
        };
        music.addEventListener('play', showMusic);
        music.addEventListener('pause', showMusic);
        musicBtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            musicWanted = music.paused;
            try { localStorage.setItem('naguut.music', musicWanted ? 'on' : 'off'); } catch (e) { /* privat */ }
            if (musicWanted) playMusic(); else music.pause();
        });
        // Autoplay ist meist erst nach einer Bedienung erlaubt
        document.addEventListener('click', function once() {
            playMusic();
            document.removeEventListener('click', once);
        });
        playMusic();
    }
    function musicFor(status) {
        if (!music) return;
        if (status === 'reveal' || status === 'leaderboard') music.volume = 0.25;
        else music.volume = 0.6;
    }

    function next() {
        if (busy || !current) return;
        busy = true;
        post(cfg.nextUrl, { from: current.status }).catch(function (err) {
            alert(err.message);
        }).then(function () {
            busy = false;
            poller.refresh();
        });
    }

    document.getElementById('end-btn').addEventListener('click', function () {
        if (!confirm(L('Das Spiel wirklich beenden? Die bisherigen Ergebnisse bleiben gespeichert.'))) return;
        post(cfg.endUrl).then(function (res) {
            poller.stop();
            location.href = res.report_url;
        }).catch(function (err) { alert(err.message); });
    });

    // Distanz-Modus umschalten
    var devicesBtn = document.getElementById('devices-btn');
    function showDevices(on) {
        devicesBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
        devicesBtn.textContent = on ? L('Fragen am Handy: an') : L('Fragen am Handy: aus');
        devicesBtn.classList.toggle('is-on', on);
        var note = stage.querySelector('[data-remote-note]');
        if (note) note.hidden = !on;
    }
    showDevices(devicesBtn.getAttribute('aria-pressed') === 'true');
    devicesBtn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var on = devicesBtn.getAttribute('aria-pressed') !== 'true';
        showDevices(on);
        post(cfg.devicesUrl, { on: on }).catch(function (err) { showDevices(!on); alert(err.message); }).then(function () { poller.refresh(); });
    });

    document.getElementById('fullscreen-btn').addEventListener('click', function () {
        if (document.fullscreenElement) document.exitFullscreen();
        else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
    });

    // Leertaste / Pfeil rechts schaltet weiter (praktisch mit Presenter)
    document.addEventListener('keydown', function (ev) {
        if (ev.target.closest('input, textarea, select')) return;
        if ((ev.key === ' ' || ev.key === 'ArrowRight' || ev.key === 'PageDown') && current && current.status !== 'lobby' && current.status !== 'finished') {
            ev.preventDefault();
            next();
        }
    });

    stage.addEventListener('click', function (ev) {
        var chip = ev.target.closest('[data-kick]');
        if (chip) {
            if (!confirm(L('{name} aus dem Spiel entfernen?', { name: chip.getAttribute('data-name') }))) return;
            N.api('DELETE', cfg.kickUrl + chip.getAttribute('data-kick'), undefined, headers)
                .catch(function (err) { alert(err.message); })
                .then(function () { poller.refresh(); });
            return;
        }
        var copy = ev.target.closest('[data-copy]');
        if (copy) {
            var text = copy.getAttribute('data-copy');
            var done = function () { copy.textContent = L('Link kopiert'); setTimeout(function () { copy.textContent = L('Link kopieren'); }, 2000); };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, function () { window.prompt(L('Link kopieren'), text); });
            else window.prompt(L('Link kopieren'), text);
            return;
        }
        if (ev.target.closest('[data-next]')) next();
        if (ev.target.closest('[data-skip]')) skip();
    });

    // ---------- Darstellung ----------

    function render(s) {
        if (s.status === 'gone') {
            stage.innerHTML = '<p class="loading">' + esc(s.error) + '</p>';
            return;
        }
        current = s;
        musicFor(s.status);
        if (typeof s.show_on_devices === 'boolean' && (devicesBtn.getAttribute('aria-pressed') === 'true') !== s.show_on_devices) showDevices(s.show_on_devices);
        var key = s.status + ':' + s.question_no;
        if (key !== shownKey) {
            countdown.stop();
            shownKey = key;
            ({ lobby: lobby, question: question, reveal: reveal, leaderboard: leaderboard, finished: finished })[s.status](s);
        } else {
            update(s);
        }
    }

    function update(s) {
        if (s.status === 'lobby') {
            renderPlayers(s);
        } else if (s.status === 'question') {
            var c = stage.querySelector('[data-answered]');
            if (c) c.textContent = s.answered;
        }
    }

    function pinHtml(pin) {
        return esc(pin.slice(0, 3)) + '<span class="pin-gap"></span>' + esc(pin.slice(3));
    }

    function lobby(s) {
        var qr = '';
        try { qr = NaguutQR.svg(s.join_url, { label: L('Beitreten unter') + ' ' + s.join_url }); } catch (e) { qr = ''; }
        stage.innerHTML =
            '<div class="lobby">' +
            '<div class="lobby-join">' +
            '<p class="lobby-label">' + esc(L('Beitreten unter')) + '</p>' +
            '<p class="lobby-url">' + esc(s.join_host) + '</p>' +
            '<p class="lobby-label">' + esc(L('Game-PIN')) + '</p>' +
            '<p class="pin-big" aria-label="' + esc(s.pin.split('').join(' ')) + '">' + pinHtml(s.pin) + '</p>' +
            '</div>' +
            (qr ? '<div class="qr">' + qr + '</div>' : '') +
            '<div class="lobby-players">' +
            '<p class="lobby-remote" data-remote-note' + (s.show_on_devices ? '' : ' hidden') + '>' +
            esc(L('Distanz-Modus: Fragen und Antworten erscheinen auch auf den Handys. Teile den Link z. B. im Videocall.')) +
            ' <button type="button" class="btn btn-small btn-sun" data-copy="' + esc(s.join_url) + '">' + esc(L('Link kopieren')) + '</button></p>' +
            '<div class="lobby-bar"><p class="lobby-count" data-count></p>' +
            '<button type="button" class="btn btn-sun btn-large" data-start>' + esc(L('Spiel starten')) + '</button></div>' +
            '<ul class="chips" data-chips></ul>' +
            '</div></div>';
        stage.querySelector('[data-start]').addEventListener('click', function () {
            if (!current.players.length) { alert(L('Es ist noch niemand beigetreten.')); return; }
            next();
        });
        renderPlayers(s);
    }

    function renderPlayers(s) {
        var n = s.players.length;
        stage.querySelector('[data-count]').innerHTML = n
            ? esc(L('{n} dabei', { n: n })) + '<small>' + esc(L('Antippen zum Entfernen')) + '</small>'
            : esc(L('Warte auf Mitspielende …'));
        stage.querySelector('[data-start]').disabled = n === 0;
        var list = stage.querySelector('[data-chips]');
        var have = {};
        list.querySelectorAll('[data-kick]').forEach(function (el) { have[el.getAttribute('data-kick')] = el; });
        var keep = {};
        s.players.forEach(function (p) {
            keep[p.id] = true;
            if (!have[p.id]) {
                var li = document.createElement('li');
                li.innerHTML = '<button type="button" class="chip" data-kick="' + p.id + '" data-name="' + esc(p.nickname) + '">' +
                    AV.html(p.avatar, 34) + '<span>' + esc(p.nickname) + '</span></button>';
                list.appendChild(li);
            }
        });
        Object.keys(have).forEach(function (id) { if (!keep[id]) have[id].parentNode.remove(); });
    }

    function headHtml(s) {
        var q = s.question;
        var pts = q.points_factor === 2 ? L('doppelte Punkte') : (q.points_factor === 0 ? L('ohne Punkte') : '');
        return '<div class="q-head"><span>' + esc(L('Frage {n} von {total}', { n: s.question_no + 1, total: s.question_count })) + '</span>' +
            '<span>' + esc(pts) + '</span></div>' +
            '<h1 class="q-text">' + q.text_html + '</h1>'; // vom Server bereinigt
    }

    // ---------- Schätzfrage ----------

    function estFmt(v, e) {
        return N.fmtNum(v, e.decimals) + (e.unit ? ' ' + e.unit : '');
    }

    function estScaleHtml(s) {
        var e = s.question.estimate;
        return '<div class="est-host"><p class="est-host-hint">' + esc(L('Schätze mit dem Schieberegler am Handy')) + '</p>' +
            '<div class="est-host-scale"><span>' + esc(estFmt(e.min, e)) + '</span><span class="est-host-line"></span><span>' + esc(estFmt(e.max, e)) + '</span></div></div>';
    }

    function estRevealHtml(s) {
        var e = s.question.estimate, g = s.guesses || [], range = e.max - e.min;
        var pct = function (v) { return Math.max(0, Math.min(100, (v - e.min) / range * 100)); };
        var BINS = 24, bins = new Array(BINS).fill(0);
        g.forEach(function (v) { bins[Math.min(BINS - 1, Math.floor((v - e.min) / range * BINS))]++; });
        var top = Math.max.apply(null, bins.concat([1]));
        var lo = Math.max(e.min, e.correct - e.tolerance), hi = Math.min(e.max, e.correct + e.tolerance);
        var cols = bins.map(function (n, i) {
            var mid = e.min + (i + 0.5) / BINS * range;
            var inTol = mid >= lo - range / BINS / 2 && mid <= hi + range / BINS / 2;
            return '<span class="est-col' + (inTol ? ' in' : '') + '" style="height:0%" data-h="' + (n ? Math.max(6, Math.round(n / top * 100)) : 0) + '">' +
                (n ? '<b>' + n + '</b>' : '') + '</span>';
        }).join('');
        var avg = g.length ? g.reduce(function (a, b) { return a + b; }, 0) / g.length : null;
        return '<div class="est-reveal">' +
            '<div class="est-answer"><span>' + esc(L('Richtig')) + '</span><strong>' + esc(estFmt(e.correct, e)) + '</strong></div>' +
            '<div class="est-chart">' +
            (e.tolerance > 0 ? '<span class="est-tol" style="left:' + pct(lo) + '%;width:' + (pct(hi) - pct(lo)) + '%"></span>' : '') +
            '<div class="est-cols">' + cols + '</div>' +
            '<span class="est-true" style="left:' + pct(e.correct) + '%"></span>' +
            '</div>' +
            '<div class="est-axis"><span>' + esc(estFmt(e.min, e)) + '</span><span>' + esc(estFmt(e.max, e)) + '</span></div>' +
            '<p class="est-stats">' +
            esc(L('{n} von {total} im Toleranzbereich', { n: s.hits, total: g.length })) +
            (avg !== null ? ' · ' + esc(L('Ø Schätzung {v}', { v: estFmt(avg, e) })) : '') + '</p></div>';
    }

    // ---------- Antwort eingeben, Puzzle, Karte ----------

    var hostMap = null;
    function mountMap(s, el) {
        var v = s.question.view || cfg.map.default_view;
        hostMap = new NaguutMap(el, { lat: v.lat, lng: v.lng, zoom: v.zoom, tiles: cfg.map.tiles });
        return hostMap;
    }

    function hintHtml(text) {
        return '<div class="est-host"><p class="est-host-hint">' + esc(text) + '</p></div>';
    }

    function ordTilesHtml(s, order) {
        var q = s.question;
        var idx = order || q.answers_html.map(function (_, i) { return i; });
        return '<ol class="ord-tiles' + (order ? ' solved' : '') + '">' + idx.map(function (slot, pos) {
            return '<li class="tile a' + slot + '">' + (order ? '<span class="ord-pos">' + (pos + 1) + '.</span>' : '') +
                '<span class="tile-sym" aria-hidden="true">' + N.SYMBOLS[slot] + '</span><span class="tile-text">' + q.answers_html[slot] + '</span></li>';
        }).join('') + '</ol>';
    }

    function revealExtraHtml(s) {
        var q = s.question;
        var stat = '<p class="est-stats">' + esc(L('{n} von {total} richtig', { n: s.hits, total: s.answered })) + (s.avg_km ? ' · ' + esc(L('Ø Abstand {v}', { v: s.avg_km })) : '') + '</p>';
        if (q.type === 'txt') {
            var list = (s.top_answers || []).map(function (a) {
                return '<li class="' + (a.correct ? 'ok' : 'bad') + '"><span>' + (a.hidden ? '•••' : esc(a.text)) + '</span><b>' + a.n + '</b></li>';
            }).join('');
            return '<div class="txt-reveal"><div class="est-answer"><span>' + esc(L('Richtig')) + '</span><strong>' + esc(s.accepted[0]) + '</strong></div>' +
                (s.accepted.length > 1 ? '<p class="est-stats">' + esc(L('Auch richtig: {v}', { v: s.accepted.slice(1).join(', ') })) + '</p>' : '') +
                (list ? '<ul class="txt-answers">' + list + '</ul>' : '') + stat + '</div>';
        }
        if (q.type === 'ord') {
            return '<div class="txt-reveal">' + ordTilesHtml(s, s.correct_order) + stat + '</div>';
        }
        if (q.type === 'map') {
            return '<div class="map-reveal"><div class="est-answer">' + (s.target.label ? '<strong class="map-label">' + esc(s.target.label) + '</strong>' : '') + '</div>' +
                '<div class="host-map" data-host-map></div>' + stat + '</div>';
        }
        return '';
    }

    function tilesHtml(s, correct) {
        var q = s.question;
        if (q.type === 'est') return estScaleHtml(s);
        if (q.type === 'txt') return hintHtml(L('Tippe die Antwort am Handy ein'));
        if (q.type === 'ord') return ordTilesHtml(s, null) + '<p class="ord-hint">' + esc(L('Tippe die Symbole am Handy in der richtigen Reihenfolge an')) + '</p>';
        if (q.type === 'map') return '<div class="host-map host-map-q" data-host-map></div><p class="ord-hint">' + esc(L('Setze am Handy einen Pin auf der Karte')) + '</p>';
        return '<div class="tiles' + (q.type === 'tf' ? ' tf' : '') + '">' + q.answers_html.map(function (a, i) {
            var isOk = correct && correct.indexOf(i) !== -1;
            return '<div class="tile a' + i + (correct && !isOk ? ' dim' : '') + '">' +
                '<span class="tile-sym" aria-hidden="true">' + N.SYMBOLS[i] + '</span>' +
                '<span class="tile-text">' + a + '</span>' + // vom Server bereinigt
                (isOk ? '<span class="tile-check" aria-label="' + esc(L('richtig')) + '">✓</span>' : '') +
                '</div>';
        }).join('') + '</div>';
    }

    function question(s) {
        var q = s.question;
        stage.innerHTML =
            '<div class="q-stage">' + headHtml(s) +
            '<div class="q-middle">' +
            '<div class="timer ready" data-timer><span data-timer-num>' + q.time_limit_s + '</span></div>' +
            (q.image ? '<img class="q-image" src="' + esc(q.image) + '" alt="">' : '<div class="q-image-empty"></div>') +
            '<div class="counter"><strong data-answered>' + s.answered + '</strong><span>' + esc(L('Antworten')) + '</span></div>' +
            '</div>' + tilesHtml(s, null) +
            '<div class="ready-card" data-ready-card><p class="ready-time"><strong>' + q.time_limit_s + '</strong> ' + esc(L('Sekunden Zeit')) + '</p>' +
            '<p class="ready-go">' + esc(L('Los geht’s in')) + ' <strong data-ready-num></strong></p></div>' +
            '<div class="stage-foot"><button type="button" class="btn btn-ghost-light" data-skip>' + esc(L('Frage überspringen')) + '</button>' +
            '<button type="button" class="btn btn-ghost-light" data-next>' + esc(L('Frage beenden')) + '</button></div>' +
            '</div>';
        var qm = stage.querySelector('[data-host-map]');
        if (qm) mountMap(s, qm);
        countdown.run(q.starts_ms, q.ends_ms);
    }

    function updateTimer(t) {
        var el = stage.querySelector('[data-timer]');
        if (!el) return;
        var num = el.querySelector('[data-timer-num]');
        el.classList.toggle('ready', t.ready);
        el.style.setProperty('--p', t.ratio.toFixed(4));
        // Im Vorlauf zeigt der Kreis die volle Zeit, die Karte darunter zählt bis zum Start
        var card = stage.querySelector('[data-ready-card]');
        if (t.ready) {
            if (card) card.querySelector('[data-ready-num]').textContent = Math.ceil(t.readyLeft / 1000);
        } else {
            num.textContent = Math.ceil(t.left / 1000);
            if (card) card.parentNode.removeChild(card);
        }
        el.classList.toggle('urgent', !t.ready && t.left <= 5000 && t.left > 0);
        if (t.left === 0) {
            // Zeit um: sofort nachfragen, damit die Auflösung ohne Verzögerung erscheint
            setTimeout(function () { poller.refresh(); }, 150);
        }
    }

    function reveal(s) {
        if (s.question.type === 'txt' || s.question.type === 'ord' || s.question.type === 'map') {
            stage.innerHTML =
                '<div class="q-stage">' + headHtml(s) + revealExtraHtml(s) +
                '<div class="stage-foot"><button type="button" class="btn btn-ghost-light" data-skip>' + esc(L('Nicht werten')) + '</button>' +
                '<button type="button" class="btn btn-sun btn-large" data-next>' + esc(s.is_last ? L('Endergebnis') : L('Weiter')) + '</button></div></div>';
            var mapEl = stage.querySelector('[data-host-map]');
            if (mapEl) {
                var m = mountMap(s, mapEl), t = s.target;
                m.circle(t.lat, t.lng, t.radius_km);
                (s.pins || []).forEach(function (p) {
                    m.marker(p.lat, p.lng, { cls: 'avatar-pin ' + (p.correct ? 'ok' : 'bad'), html: AV.html(p.avatar, 32) });
                });
                m.marker(t.lat, t.lng, { cls: 'target' });
                m.fit(NaguutMap.circleBounds(t.lat, t.lng, t.radius_km).concat(s.pins || []), 13);
            }
            return;
        }
        if (s.question.type === 'est') {
            stage.innerHTML =
                '<div class="q-stage">' + headHtml(s) + estRevealHtml(s) +
                '<div class="stage-foot"><button type="button" class="btn btn-ghost-light" data-skip>' + esc(L('Nicht werten')) + '</button>' +
                '<button type="button" class="btn btn-sun btn-large" data-next>' + esc(s.is_last ? L('Endergebnis') : L('Weiter')) + '</button></div></div>';
            requestAnimationFrame(function () { requestAnimationFrame(function () {
                stage.querySelectorAll('[data-h]').forEach(function (b) { b.style.height = b.getAttribute('data-h') + '%'; });
            }); });
            return;
        }
        var max = Math.max.apply(null, s.distribution.concat([1]));
        var bars = s.distribution.map(function (n, i) {
            var ok = s.correct.indexOf(i) !== -1;
            return '<div class="bar a' + i + (ok ? '' : ' dim') + '">' +
                '<span class="bar-val">' + n + '</span>' +
                '<span class="bar-fill" style="height:0%" data-h="' + Math.round(n / max * 100) + '"></span>' +
                '<span class="bar-sym" aria-hidden="true">' + N.SYMBOLS[i] + (ok ? ' ✓' : '') + '</span></div>';
        }).join('');
        stage.innerHTML =
            '<div class="q-stage">' + headHtml(s) +
            '<div class="bars">' + bars + '</div>' +
            tilesHtml(s, s.correct) +
            '<div class="stage-foot"><button type="button" class="btn btn-ghost-light" data-skip>' + esc(L('Nicht werten')) + '</button>' +
            '<button type="button" class="btn btn-sun btn-large" data-next>' +
            esc(s.is_last ? L('Endergebnis') : L('Weiter')) + '</button></div>' +
            '</div>';
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                stage.querySelectorAll('[data-h]').forEach(function (b) { b.style.height = b.getAttribute('data-h') + '%'; });
            });
        });
    }

    function ranksHtml(top) {
        return '<ol class="ranks">' + top.map(function (p, i) {
            return '<li><span class="r-pos">' + (i + 1) + '.</span><span class="r-name">' + AV.html(p.avatar, 44) + '<span>' + esc(p.nickname) + '</span></span><span class="r-score">' + N.fmtNum(p.score) + '</span></li>';
        }).join('') + '</ol>';
    }

    function leaderboard(s) {
        stage.innerHTML =
            '<div class="board"><h2>' + esc(L('Rangliste')) + '</h2>' + ranksHtml(s.top) + '</div>' +
            '<div class="stage-foot"><button type="button" class="btn btn-ghost-light" data-skip>' + esc(L('Nächste Frage überspringen')) + '</button>' +
            '<button type="button" class="btn btn-sun btn-large" data-next>' + esc(L('Nächste Frage')) + '</button></div>';
    }

    function finished(s) {
        poller.stop();
        var order = [1, 0, 2]; // Platz 2, 1, 3 nebeneinander
        var cols = order.map(function (i) {
            var p = s.top[i];
            if (!p) return '';
            return '<div class="podium-col p' + (i + 1) + '">' +
                AV.html(p.avatar, i === 0 ? 120 : 96, 'podium-avatar') +
                '<span class="podium-name">' + esc(p.nickname) + '</span>' +
                '<span class="podium-score">' + N.fmtNum(p.score) + ' ' + esc(L('Punkte')) + '</span>' +
                '<div class="podium-block">' + (i + 1) + '</div></div>';
        }).join('');
        stage.innerHTML =
            '<div class="podium" aria-label="' + esc(L('Siegerpodest')) + '">' + cols + '</div>' +
            '<div class="stage-foot">' +
            '<a class="btn btn-ghost-light" href="' + esc(cfg.dashboardUrl) + '">' + esc(L('Neues Spiel')) + '</a>' +
            '<a class="btn btn-sun btn-large" href="' + esc(s.report_url) + '">' + esc(L('Zur Auswertung')) + '</a></div>';
        document.getElementById('end-btn').hidden = true;
    }
})();
