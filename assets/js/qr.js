// Naguut – QR-Code-Generator (Byte-Modus, Fehlerkorrektur M, Version 1–10)
// Eigene, schlanke Umsetzung nach ISO/IEC 18004, damit keine fremde
// Bibliothek oder externer Dienst nötig ist.
// Verwendung: NaguutQR.svg('https://…') → SVG-Markup
(function (global) {
    'use strict';

    // Fehlerkorrektur-Stufe M, Index = Version
    var ECC_PER_BLOCK = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    var NUM_BLOCKS = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
    var ECL_FORMAT_BITS = 0; // M

    function rawDataModules(ver) {
        var r = (16 * ver + 128) * ver + 64;
        if (ver >= 2) {
            var n = Math.floor(ver / 7) + 2;
            r -= (25 * n - 10) * n - 55;
            if (ver >= 7) r -= 36;
        }
        return r;
    }
    function dataCodewords(ver) {
        return Math.floor(rawDataModules(ver) / 8) - ECC_PER_BLOCK[ver] * NUM_BLOCKS[ver];
    }

    // Galois-Feld GF(256) und Reed-Solomon
    function gfMul(x, y) {
        var z = 0;
        for (var i = 7; i >= 0; i--) {
            z = (z << 1) ^ ((z >>> 7) * 0x11D);
            z ^= ((y >>> i) & 1) * x;
        }
        return z & 0xFF;
    }
    function rsDivisor(degree) {
        var result = new Array(degree).fill(0);
        result[degree - 1] = 1;
        var root = 1;
        for (var i = 0; i < degree; i++) {
            for (var j = 0; j < result.length; j++) {
                result[j] = gfMul(result[j], root);
                if (j + 1 < result.length) result[j] ^= result[j + 1];
            }
            root = gfMul(root, 0x02);
        }
        return result;
    }
    function rsRemainder(data, divisor) {
        var result = divisor.map(function () { return 0; });
        data.forEach(function (b) {
            var factor = b ^ result.shift();
            result.push(0);
            divisor.forEach(function (coef, i) { result[i] ^= gfMul(coef, factor); });
        });
        return result;
    }

    function utf8Bytes(str) {
        var out = [];
        var enc = unescape(encodeURIComponent(str));
        for (var i = 0; i < enc.length; i++) out.push(enc.charCodeAt(i));
        return out;
    }

    function encode(text) {
        var bytes = utf8Bytes(text);
        var ver, capBits;
        for (ver = 1; ver <= 10; ver++) {
            capBits = dataCodewords(ver) * 8;
            var ccBits = ver <= 9 ? 8 : 16;
            if (4 + ccBits + bytes.length * 8 <= capBits) break;
        }
        if (ver > 10) throw new Error('Text zu lang für den QR-Code');

        // Bitstrom: Modus 0100, Längenfeld, Daten
        var bits = [];
        function append(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
        append(4, 4);
        append(bytes.length, ver <= 9 ? 8 : 16);
        bytes.forEach(function (b) { append(b, 8); });
        append(0, Math.min(4, capBits - bits.length));
        append(0, (8 - bits.length % 8) % 8);
        for (var pad = 0xEC; bits.length < capBits; pad ^= 0xEC ^ 0x11) append(pad, 8);
        var data = [];
        for (var i = 0; i < bits.length; i += 8) {
            var byte = 0;
            for (var j = 0; j < 8; j++) byte = (byte << 1) | bits[i + j];
            data.push(byte);
        }

        // Blöcke mit Fehlerkorrektur bilden und verschränken
        var numBlocks = NUM_BLOCKS[ver], eccLen = ECC_PER_BLOCK[ver];
        var rawCodewords = Math.floor(rawDataModules(ver) / 8);
        var numShort = numBlocks - rawCodewords % numBlocks;
        var shortLen = Math.floor(rawCodewords / numBlocks);
        var divisor = rsDivisor(eccLen);
        var blocks = [];
        for (var b = 0, k = 0; b < numBlocks; b++) {
            var dat = data.slice(k, k + shortLen - eccLen + (b < numShort ? 0 : 1));
            k += dat.length;
            var ecc = rsRemainder(dat, divisor);
            if (b < numShort) dat.push(0);
            blocks.push(dat.concat(ecc));
        }
        var codewords = [];
        for (var c = 0; c < blocks[0].length; c++) {
            blocks.forEach(function (blk, idx) {
                if (c !== shortLen - eccLen || idx >= numShort) codewords.push(blk[c]);
            });
        }

        // Matrix aufbauen
        var size = ver * 4 + 17;
        var modules = [], isFunc = [];
        for (var y = 0; y < size; y++) {
            modules.push(new Array(size).fill(false));
            isFunc.push(new Array(size).fill(false));
        }
        function setF(x, y, dark) { modules[y][x] = dark; isFunc[y][x] = true; }

        for (var t = 0; t < size; t++) { setF(6, t, t % 2 === 0); setF(t, 6, t % 2 === 0); }
        function finder(cx, cy) {
            for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) {
                var d = Math.max(Math.abs(dx), Math.abs(dy)), xx = cx + dx, yy = cy + dy;
                if (xx >= 0 && xx < size && yy >= 0 && yy < size) setF(xx, yy, d !== 2 && d !== 4);
            }
        }
        finder(3, 3); finder(size - 4, 3); finder(3, size - 4);

        var align = [];
        if (ver > 1) {
            var na = Math.floor(ver / 7) + 2;
            var step = Math.ceil((ver * 4 + 4) / (na * 2 - 2)) * 2;
            align = [6];
            for (var pos = size - 7; align.length < na; pos -= step) align.splice(1, 0, pos);
        }
        align.forEach(function (ax, ia) {
            align.forEach(function (ay, ib) {
                var last = align.length - 1;
                if ((ia === 0 && ib === 0) || (ia === 0 && ib === last) || (ia === last && ib === 0)) return;
                for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) {
                    setF(ax + dx, ay + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
                }
            });
        });

        function drawFormat(mask) {
            var d = (ECL_FORMAT_BITS << 3) | mask, rem = d;
            for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
            var fb = ((d << 10) | rem) ^ 0x5412;
            function bit(i) { return ((fb >>> i) & 1) !== 0; }
            for (var a = 0; a <= 5; a++) setF(8, a, bit(a));
            setF(8, 7, bit(6)); setF(8, 8, bit(7)); setF(7, 8, bit(8));
            for (var b2 = 9; b2 < 15; b2++) setF(14 - b2, 8, bit(b2));
            for (var c2 = 0; c2 < 8; c2++) setF(size - 1 - c2, 8, bit(c2));
            for (var e = 8; e < 15; e++) setF(8, size - 15 + e, bit(e));
            setF(8, size - 8, true);
        }
        drawFormat(0);

        if (ver >= 7) {
            var rem = ver;
            for (var vi = 0; vi < 12; vi++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
            var vbits = (ver << 12) | rem;
            for (var vb = 0; vb < 18; vb++) {
                var bitv = ((vbits >>> vb) & 1) !== 0;
                var aa = size - 11 + vb % 3, bb = Math.floor(vb / 3);
                setF(aa, bb, bitv); setF(bb, aa, bitv);
            }
        }

        // Daten im Zickzack eintragen
        var bi = 0;
        for (var right = size - 1; right >= 1; right -= 2) {
            if (right === 6) right = 5;
            for (var vert = 0; vert < size; vert++) {
                for (var jj = 0; jj < 2; jj++) {
                    var x = right - jj;
                    var upward = ((right + 1) & 2) === 0;
                    var yy = upward ? size - 1 - vert : vert;
                    if (!isFunc[yy][x] && bi < codewords.length * 8) {
                        modules[yy][x] = ((codewords[bi >>> 3] >>> (7 - (bi & 7))) & 1) !== 0;
                        bi++;
                    }
                }
            }
        }

        function maskHit(m, x, y) {
            switch (m) {
                case 0: return (x + y) % 2 === 0;
                case 1: return y % 2 === 0;
                case 2: return x % 3 === 0;
                case 3: return (x + y) % 3 === 0;
                case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
                case 5: return x * y % 2 + x * y % 3 === 0;
                case 6: return (x * y % 2 + x * y % 3) % 2 === 0;
                default: return ((x + y) % 2 + x * y % 3) % 2 === 0;
            }
        }
        function applyMask(m) {
            for (var y = 0; y < size; y++) for (var x = 0; x < size; x++) {
                if (!isFunc[y][x] && maskHit(m, x, y)) modules[y][x] = !modules[y][x];
            }
        }
        // Vereinfachte Strafpunkte: lange Läufe, 2×2-Blöcke, Hell-Dunkel-Verhältnis
        function penalty() {
            var p = 0, dark = 0;
            for (var y = 0; y < size; y++) {
                var runX = 1, runY = 1;
                for (var x = 0; x < size; x++) {
                    if (modules[y][x]) dark++;
                    if (x > 0) {
                        if (modules[y][x] === modules[y][x - 1]) { runX++; if (runX === 5) p += 3; else if (runX > 5) p++; } else runX = 1;
                        if (modules[x][y] === modules[x - 1][y]) { runY++; if (runY === 5) p += 3; else if (runY > 5) p++; } else runY = 1;
                    }
                    if (x > 0 && y > 0) {
                        var c = modules[y][x];
                        if (c === modules[y][x - 1] && c === modules[y - 1][x] && c === modules[y - 1][x - 1]) p += 3;
                    }
                }
            }
            var total = size * size;
            p += Math.floor(Math.abs(dark * 20 - total * 10) / total) * 10;
            return p;
        }
        var best = 0, bestScore = Infinity;
        for (var m = 0; m < 8; m++) {
            applyMask(m); drawFormat(m);
            var sc = penalty();
            if (sc < bestScore) { bestScore = sc; best = m; }
            applyMask(m);
        }
        applyMask(best); drawFormat(best);
        return modules;
    }

    function svg(text, opts) {
        opts = opts || {};
        var mods = encode(text), size = mods.length, border = 4, full = size + border * 2;
        var path = '';
        for (var y = 0; y < size; y++) for (var x = 0; x < size; x++) {
            if (mods[y][x]) path += 'M' + (x + border) + ',' + (y + border) + 'h1v1h-1z';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + full + ' ' + full + '" shape-rendering="crispEdges" role="img" aria-label="' +
            (opts.label || 'QR-Code') + '"><rect width="100%" height="100%" fill="#fff"/><path d="' + path + '" fill="' + (opts.color || '#131B52') + '"/></svg>';
    }

    global.NaguutQR = { encode: encode, svg: svg };
})(typeof window !== 'undefined' ? window : globalThis);
