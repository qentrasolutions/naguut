// Naguut – schlanke OpenStreetMap-Karte ohne fremde Bibliothek
// Web-Mercator-Kacheln (256 px), Verschieben mit Maus/Finger, Zoomen mit Mausrad,
// Knöpfen und zwei Fingern. Antippen liefert Koordinaten (für Pins).
//
//   var map = new NaguutMap(element, { lat, lng, zoom, tiles: {url, attribution, attribution_url, max_zoom}, onTap(lat, lng) })
//   map.setView(lat, lng, zoom) · map.getView() · map.marker(lat, lng, opts) · map.circle(lat, lng, km) · map.clearOverlays()
(function (global) {
    'use strict';
    var TILE = 256;

    function project(lat, lng, z) {
        var s = TILE * Math.pow(2, z);
        var sin = Math.sin(Math.max(-85.0511, Math.min(85.0511, lat)) * Math.PI / 180);
        return { x: (lng + 180) / 360 * s, y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * s };
    }
    function unproject(x, y, z) {
        var s = TILE * Math.pow(2, z);
        var lng = x / s * 360 - 180;
        var n = Math.PI - 2 * Math.PI * y / s;
        var lat = 180 / Math.PI * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
        lng = ((lng + 180) % 360 + 360) % 360 - 180;
        return { lat: lat, lng: lng };
    }

    function NaguutMap(el, opts) {
        this.el = el;
        this.opts = opts || {};
        this.tiles = this.opts.tiles || {};
        this.maxZoom = this.tiles.max_zoom || 18;
        this.minZoom = 2;
        this.z = Math.round(this.opts.zoom || 7);
        this.c = project(this.opts.lat || 47.6, this.opts.lng || 13.4, this.z); // Mittelpunkt in Weltpixeln
        this.overlays = [];
        this.cache = {};
        el.classList.add('nmap');
        el.innerHTML = '<div class="nmap-tiles"></div><div class="nmap-layer"></div>' +
            '<div class="nmap-zoom"><button type="button" data-z="1" aria-label="Hineinzoomen">+</button><button type="button" data-z="-1" aria-label="Herauszoomen">−</button></div>' +
            '<div class="nmap-attr"></div>';
        this.tileEl = el.querySelector('.nmap-tiles');
        this.layerEl = el.querySelector('.nmap-layer');
        var attr = el.querySelector('.nmap-attr');
        if (this.tiles.attribution) {
            attr.innerHTML = this.tiles.attribution_url
                ? '<a href="' + this.tiles.attribution_url.replace(/"/g, '') + '" target="_blank" rel="noopener">' + escapeHtml(this.tiles.attribution) + '</a>'
                : escapeHtml(this.tiles.attribution);
        }
        this._bind();
        var self = this;
        this._ro = global.ResizeObserver ? new ResizeObserver(function () { self.render(); }) : null;
        if (this._ro) this._ro.observe(el); else global.addEventListener('resize', function () { self.render(); });
        this.render();
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    NaguutMap.prototype.size = function () {
        return { w: this.el.clientWidth || 1, h: this.el.clientHeight || 1 };
    };

    NaguutMap.prototype.getView = function () {
        var ll = unproject(this.c.x, this.c.y, this.z);
        return { lat: Math.round(ll.lat * 1e5) / 1e5, lng: Math.round(ll.lng * 1e5) / 1e5, zoom: this.z };
    };

    NaguutMap.prototype.setView = function (lat, lng, zoom) {
        this.z = Math.max(this.minZoom, Math.min(this.maxZoom, Math.round(zoom == null ? this.z : zoom)));
        this.c = project(lat, lng, this.z);
        this.render();
    };

    /** Zoomen um einen Bildschirmpunkt (px relativ zur Karte), der dabei an Ort und Stelle bleibt */
    NaguutMap.prototype.zoomBy = function (dz, px, py) {
        var nz = Math.max(this.minZoom, Math.min(this.maxZoom, this.z + dz));
        if (nz === this.z) return;
        var sz = this.size();
        if (px == null) { px = sz.w / 2; py = sz.h / 2; }
        var wx = this.c.x - sz.w / 2 + px, wy = this.c.y - sz.h / 2 + py;
        var f = Math.pow(2, nz - this.z);
        this.c = { x: wx * f - (px - sz.w / 2), y: wy * f - (py - sz.h / 2) };
        this.z = nz;
        this.render();
    };

    NaguutMap.prototype.toScreen = function (lat, lng) {
        var sz = this.size(), p = project(lat, lng, this.z), world = TILE * Math.pow(2, this.z);
        var x = p.x - this.c.x;
        // bei weit herausgezoomter Karte die nächstgelegene Weltkopie nehmen
        if (x > world / 2) x -= world; else if (x < -world / 2) x += world;
        return { x: x + sz.w / 2, y: p.y - this.c.y + sz.h / 2 };
    };

    NaguutMap.prototype.fromScreen = function (px, py) {
        var sz = this.size();
        return unproject(this.c.x - sz.w / 2 + px, this.c.y - sz.h / 2 + py, this.z);
    };

    NaguutMap.prototype.render = function () {
        var sz = this.size(), z = this.z, n = Math.pow(2, z), world = TILE * n;
        // senkrecht nicht über die Welt hinaus schieben
        this.c.y = Math.max(Math.min(sz.h / 2, world / 2), Math.min(world - Math.min(sz.h / 2, world / 2), this.c.y));
        var x0 = this.c.x - sz.w / 2, y0 = this.c.y - sz.h / 2;
        var tx0 = Math.floor(x0 / TILE), ty0 = Math.max(0, Math.floor(y0 / TILE));
        var tx1 = Math.floor((x0 + sz.w) / TILE), ty1 = Math.min(n - 1, Math.floor((y0 + sz.h) / TILE));
        var want = {};
        for (var ty = ty0; ty <= ty1; ty++) {
            for (var tx = tx0; tx <= tx1; tx++) {
                var wx = ((tx % n) + n) % n;
                var key = z + '/' + tx + '/' + ty;
                want[key] = true;
                var img = this.cache[key];
                if (!img) {
                    img = document.createElement('img');
                    img.alt = '';
                    img.draggable = false;
                    img.referrerPolicy = 'strict-origin-when-cross-origin'; // OSM verlangt einen Referer
                    img.className = 'nmap-tile';
                    img.onload = function () { this.classList.add('ok'); };
                    img.src = (this.tiles.url || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png')
                        .replace('{s}', 'abc'.charAt((wx + ty) % 3)).replace('{z}', z).replace('{x}', wx).replace('{y}', ty);
                    this.cache[key] = img;
                    this.tileEl.appendChild(img);
                }
                img.style.transform = 'translate(' + Math.round(tx * TILE - x0) + 'px,' + Math.round(ty * TILE - y0) + 'px)';
            }
        }
        for (var k in this.cache) {
            if (!want[k]) { this.cache[k].remove(); delete this.cache[k]; }
        }
        this._placeOverlays();
    };

    NaguutMap.prototype._placeOverlays = function () {
        var self = this;
        this.overlays.forEach(function (o) {
            var p = self.toScreen(o.lat, o.lng);
            if (o.type === 'circle') {
                var pxPerKm = TILE * Math.pow(2, self.z) / (40075.016686 * Math.cos(o.lat * Math.PI / 180));
                var r = Math.max(4, o.km * pxPerKm);
                o.el.style.width = o.el.style.height = (2 * r) + 'px';
                o.el.style.transform = 'translate(' + (p.x - r) + 'px,' + (p.y - r) + 'px)';
            } else {
                o.el.style.transform = 'translate(' + p.x + 'px,' + p.y + 'px)';
            }
        });
    };

    /** Pin setzen. opts: {cls, html, label} → gibt ein Objekt mit move(lat,lng) und remove() zurück */
    NaguutMap.prototype.marker = function (lat, lng, opts) {
        opts = opts || {};
        var el = document.createElement('div');
        el.className = 'nmap-pin ' + (opts.cls || '');
        el.innerHTML = opts.html || '<span class="nmap-pin-dot"></span>';
        if (opts.label) el.setAttribute('aria-label', opts.label);
        this.layerEl.appendChild(el);
        var o = { type: 'pin', lat: lat, lng: lng, el: el };
        this.overlays.push(o);
        this._placeOverlays();
        var self = this;
        return {
            move: function (la, ln) { o.lat = la; o.lng = ln; self._placeOverlays(); },
            remove: function () { el.remove(); self.overlays = self.overlays.filter(function (x) { return x !== o; }); }
        };
    };

    NaguutMap.prototype.circle = function (lat, lng, km, cls) {
        var el = document.createElement('div');
        el.className = 'nmap-circle ' + (cls || '');
        this.layerEl.insertBefore(el, this.layerEl.firstChild);
        var o = { type: 'circle', lat: lat, lng: lng, km: km, el: el };
        this.overlays.push(o);
        this._placeOverlays();
        var self = this;
        return {
            update: function (la, ln, k) { o.lat = la; o.lng = ln; o.km = k; self._placeOverlays(); },
            remove: function () { el.remove(); self.overlays = self.overlays.filter(function (x) { return x !== o; }); }
        };
    };

    /** Punkte nördlich, südlich, östlich und westlich eines Umkreises (für fit) */
    NaguutMap.circleBounds = function (lat, lng, km) {
        var dLat = km / 110.574, dLng = km / (111.32 * Math.max(0.1, Math.cos(lat * Math.PI / 180)));
        return [{ lat: lat + dLat, lng: lng }, { lat: lat - dLat, lng: lng }, { lat: lat, lng: lng + dLng }, { lat: lat, lng: lng - dLng }];
    };

    NaguutMap.prototype.clearOverlays = function () {
        this.overlays.forEach(function (o) { o.el.remove(); });
        this.overlays = [];
    };

    /** Ausschnitt so wählen, dass alle Punkte sichtbar sind */
    NaguutMap.prototype.fit = function (points, maxZoom) {
        if (!points.length) return;
        var sz = this.size(), z;
        for (z = Math.min(maxZoom || 14, this.maxZoom); z > this.minZoom; z--) {
            var ps = points.map(function (p) { return project(p.lat, p.lng, z); });
            var xs = ps.map(function (p) { return p.x; }), ys = ps.map(function (p) { return p.y; });
            if (Math.max.apply(null, xs) - Math.min.apply(null, xs) < sz.w - 80 && Math.max.apply(null, ys) - Math.min.apply(null, ys) < sz.h - 80) break;
        }
        var q = points.map(function (p) { return project(p.lat, p.lng, z); });
        this.z = z;
        this.c = { x: (Math.min.apply(null, q.map(function (p) { return p.x; })) + Math.max.apply(null, q.map(function (p) { return p.x; }))) / 2,
                   y: (Math.min.apply(null, q.map(function (p) { return p.y; })) + Math.max.apply(null, q.map(function (p) { return p.y; }))) / 2 };
        this.render();
    };

    NaguutMap.prototype._bind = function () {
        var self = this, el = this.el, pointers = {}, start = null, pinch = null, moved = false;
        el.addEventListener('click', function (ev) {
            var b = ev.target.closest('[data-z]');
            if (b) { ev.preventDefault(); self.zoomBy(parseInt(b.getAttribute('data-z'), 10)); }
        });
        el.addEventListener('wheel', function (ev) {
            ev.preventDefault();
            var r = el.getBoundingClientRect();
            var now = Date.now();
            if (self._lastWheel && now - self._lastWheel < 180) return; // ein Schritt pro Drehbewegung
            self._lastWheel = now;
            self.zoomBy(ev.deltaY < 0 ? 1 : -1, ev.clientX - r.left, ev.clientY - r.top);
        }, { passive: false });
        el.addEventListener('dblclick', function (ev) {
            if (ev.target.closest('.nmap-zoom, .nmap-attr')) return;
            var r = el.getBoundingClientRect();
            self.zoomBy(1, ev.clientX - r.left, ev.clientY - r.top);
        });
        el.addEventListener('pointerdown', function (ev) {
            if (ev.target.closest('.nmap-zoom, .nmap-attr')) return;
            el.setPointerCapture(ev.pointerId);
            pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
            var ids = Object.keys(pointers);
            if (ids.length === 1) {
                start = { x: ev.clientX, y: ev.clientY, cx: self.c.x, cy: self.c.y };
                moved = false;
            } else if (ids.length === 2) {
                var a = pointers[ids[0]], b = pointers[ids[1]];
                pinch = { d: Math.hypot(a.x - b.x, a.y - b.y) };
                moved = true;
            }
        });
        el.addEventListener('pointermove', function (ev) {
            if (!pointers[ev.pointerId]) return;
            pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
            var ids = Object.keys(pointers);
            if (ids.length === 2 && pinch) {
                var a = pointers[ids[0]], b = pointers[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
                var r = el.getBoundingClientRect();
                if (d / pinch.d > 1.6 || d / pinch.d < 0.62) {
                    self.zoomBy(d > pinch.d ? 1 : -1, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top);
                    pinch.d = d;
                }
                return;
            }
            if (!start) return;
            var dx = ev.clientX - start.x, dy = ev.clientY - start.y;
            if (!moved && Math.hypot(dx, dy) < 6) return;
            moved = true;
            self.c = { x: start.cx - dx, y: start.cy - dy };
            self.render();
        });
        function end(ev) {
            if (!pointers[ev.pointerId]) return;
            delete pointers[ev.pointerId];
            var left = Object.keys(pointers).length;
            if (left === 0) {
                if (!moved && start && ev.type === 'pointerup' && self.opts.onTap) {
                    var r = el.getBoundingClientRect();
                    var ll = self.fromScreen(ev.clientX - r.left, ev.clientY - r.top);
                    self.opts.onTap(Math.round(ll.lat * 1e5) / 1e5, Math.round(ll.lng * 1e5) / 1e5);
                }
                start = null; pinch = null;
            } else if (left === 1) {
                var p = pointers[Object.keys(pointers)[0]];
                start = { x: p.x, y: p.y, cx: self.c.x, cy: self.c.y };
                pinch = null;
            }
        }
        el.addEventListener('pointerup', end);
        el.addEventListener('pointercancel', end);
    };

    global.NaguutMap = NaguutMap;
})(window);
