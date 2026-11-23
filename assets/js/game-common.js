// Naguut – gemeinsame Teile für Host- und Spieler-Ansicht
(function (global) {
    'use strict';

    var I18N = {};
    try { I18N = JSON.parse(document.getElementById('i18n').textContent); } catch (e) { /* leer */ }

    /** Übersetzen mit Platzhaltern: L('Frage {n}', {n: 3}) */
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

    /** Zahl wie in Österreich üblich: 1.250,5 (gleich wie auf dem Server) */
    function fmtNum(n, decimals) {
        decimals = decimals || 0;
        var neg = Number(n) < 0;
        var parts = Math.abs(Number(n || 0)).toFixed(decimals).split('.');
        var int = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (neg ? '-' : '') + int + (parts[1] ? ',' + parts[1] : '');
    }

    var SYMBOLS = ['▲', '◆', '●', '■'];

    /** JSON-Anfrage; wirft bei Fehlern ein Error-Objekt mit .status und .message */
    function api(method, url, body, headers) {
        var opts = { method: method, headers: Object.assign({ 'Accept': 'application/json' }, headers || {}), credentials: 'same-origin', cache: 'no-store' };
        if (method === 'PUT' || method === 'DELETE') {
            opts.headers['X-HTTP-Method-Override'] = method;
            opts.method = 'POST';
        }
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            if (res.status === 204) return null;
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    var err = new Error((data && data.error) || ('HTTP ' + res.status));
                    err.status = res.status;
                    err.data = data;
                    throw err;
                }
                return data;
            });
        });
    }

    /**
     * Fragt den Spielstatus jede Sekunde ab. Der Server antwortet mit 204,
     * wenn sich seit der letzten Version nichts geändert hat.
     */
    function Poller(url, headers, onState, onConn) {
        this.url = url;
        this.headers = headers || {};
        this.onState = onState;
        this.onConn = onConn || function () {};
        this.version = 0;
        this.offset = 0;       // Serverzeit − lokale Zeit
        this.failures = 0;
        this.timer = null;
        this.stopped = false;
        this.interval = 1000;
    }
    Poller.prototype.start = function () { this.stopped = false; this.tick(); };
    Poller.prototype.stop = function () { this.stopped = true; clearTimeout(this.timer); };
    Poller.prototype.now = function () { return Date.now() + this.offset; };
    Poller.prototype.refresh = function () { clearTimeout(this.timer); this.tick(); };
    Poller.prototype.tick = function () {
        var self = this;
        if (self.inFlight) return;
        self.inFlight = true;
        var sent = Date.now();
        var sep = self.url.indexOf('?') === -1 ? '?' : '&';
        api('GET', self.url + sep + 'since=' + self.version, undefined, self.headers).then(function (state) {
            self.failures = 0;
            self.onConn(true);
            if (state) {
                var rtt = Date.now() - sent;
                self.offset = state.now_ms - (Date.now() - rtt / 2);
                self.version = state.version;
                self.onState(state);
            }
        }).catch(function (err) {
            if (err.status === 401 || err.status === 404) {
                self.stop();
                self.onState({ status: 'gone', error: err.message, code: err.status });
                return;
            }
            self.failures++;
            if (self.failures >= 3) self.onConn(false);
        }).then(function () {
            self.inFlight = false;
            if (!self.stopped) {
                // Bei Problemen etwas langsamer nachfragen, um den Server zu schonen
                var wait = self.failures ? Math.min(5000, self.interval * (1 + self.failures)) : self.interval;
                if (document.hidden) wait = Math.max(wait, 3000);
                self.timer = setTimeout(function () { self.tick(); }, wait);
            }
        });
    };

    /** Countdown-Anzeige pro Animation-Frame aktualisieren */
    function Countdown(poller, cb) {
        this.poller = poller;
        this.cb = cb;
        this.raf = null;
    }
    Countdown.prototype.run = function (startsMs, endsMs) {
        var self = this;
        cancelAnimationFrame(self.raf);
        function frame() {
            var now = self.poller.now();
            var total = endsMs - startsMs;
            var left = Math.max(0, endsMs - now);
            var ready = now < startsMs;
            self.cb({ ready: ready, readyLeft: Math.max(0, startsMs - now), left: left, ratio: ready ? 1 : left / total });
            if (left > 0) self.raf = requestAnimationFrame(frame);
        }
        frame();
    };
    Countdown.prototype.stop = function () { cancelAnimationFrame(this.raf); };

    global.Naguut = { L: L, esc: esc, api: api, Poller: Poller, Countdown: Countdown, SYMBOLS: SYMBOLS, fmtNum: fmtNum };
})(window);
