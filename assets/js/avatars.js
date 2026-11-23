// Naguut – Avatare (eigene Zeichnungen, SVG 64×64)
// Die IDs müssen mit NAGUUT_AVATARS in app/lib/game.php übereinstimmen.
(function (global) {
    'use strict';
    var K = '#1B1B2F'; // Augen und Linien
    function eyes(x1, x2, y, r) {
        r = r || 2.8;
        return '<circle cx="' + x1 + '" cy="' + y + '" r="' + r + '" fill="' + K + '"/><circle cx="' + x2 + '" cy="' + y + '" r="' + r + '" fill="' + K + '"/>' +
            '<circle cx="' + (x1 + 1) + '" cy="' + (y - 1) + '" r="' + (r / 3) + '" fill="#fff"/><circle cx="' + (x2 + 1) + '" cy="' + (y - 1) + '" r="' + (r / 3) + '" fill="#fff"/>';
    }
    function cheeks(x1, x2, y) {
        return '<circle cx="' + x1 + '" cy="' + y + '" r="2.8" fill="#F49AB0" opacity=".75"/><circle cx="' + x2 + '" cy="' + y + '" r="2.8" fill="#F49AB0" opacity=".75"/>';
    }
    function smile(x, y, w) {
        return '<path d="M' + (x - w) + ' ' + y + 'q' + w + ' ' + (w * 0.8) + ' ' + (w * 2) + ' 0" stroke="' + K + '" stroke-width="1.8" fill="none" stroke-linecap="round"/>';
    }

    var LIST = [
        { id: 'katze', name: 'Katze', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFE3C2"/>' +
            '<path d="M13 26 L16 8 L29 18Z M51 26 L48 8 L35 18Z" fill="#F08C2E"/><path d="M17 22 L18 13 L24 18Z M47 22 L46 13 L40 18Z" fill="#FFB3A1"/>' +
            '<ellipse cx="32" cy="36" rx="20" ry="18" fill="#F08C2E"/><path d="M26 20 l2 6 M32 19 v7 M38 20 l-2 6" stroke="#C9661A" stroke-width="2" stroke-linecap="round"/>' +
            '<ellipse cx="32" cy="43" rx="10" ry="7.5" fill="#FFE3C2"/>' + eyes(24, 40, 33, 3.2) +
            '<path d="M29.5 39h5l-2.5 3z" fill="#D9485F"/>' + smile(32, 43, 3) +
            '<path d="M6 39h13 M7 45l12-3 M58 39H45 M57 45l-12-3" stroke="' + K + '" stroke-width="1.2" stroke-linecap="round" opacity=".6"/>' },
        { id: 'hund', name: 'Hund', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFF0DA"/>' +
            '<ellipse cx="32" cy="35" rx="18" ry="19" fill="#E0B27A"/>' +
            '<path d="M16 17 Q5 21 8 39 Q14 44 19 34Z M48 17 Q59 21 56 39 Q50 44 45 34Z" fill="#7A4E2D"/>' +
            '<circle cx="40" cy="28" r="6" fill="#C98F55"/>' +
            '<ellipse cx="32" cy="43" rx="10" ry="8" fill="#F6DDBA"/><ellipse cx="32" cy="39" rx="4" ry="3" fill="' + K + '"/>' +
            '<path d="M32 42v3" stroke="' + K + '" stroke-width="1.5"/><path d="M28.5 45.5 Q32 55 35.5 45.5Z" fill="#E5484D"/>' + eyes(25, 39, 30) },
        { id: 'frosch', name: 'Frosch', svg:
            '<circle cx="32" cy="32" r="32" fill="#DDF3D0"/>' +
            '<ellipse cx="32" cy="41" rx="24" ry="15" fill="#3DB25A"/><circle cx="20" cy="25" r="9" fill="#3DB25A"/><circle cx="44" cy="25" r="9" fill="#3DB25A"/>' +
            '<circle cx="20" cy="25" r="5.5" fill="#fff"/><circle cx="44" cy="25" r="5.5" fill="#fff"/><circle cx="21" cy="26" r="3" fill="' + K + '"/><circle cx="43" cy="26" r="3" fill="' + K + '"/>' +
            '<path d="M17 42 Q32 53 47 42" stroke="' + K + '" stroke-width="2.2" fill="none" stroke-linecap="round"/>' + cheeks(14, 50, 41) },
        { id: 'eule', name: 'Eule', svg:
            '<circle cx="32" cy="32" r="32" fill="#E9E2FA"/>' +
            '<path d="M13 15 L21 25 L43 25 L51 15 L49 44 Q32 60 15 44Z" fill="#8A5A3C"/><ellipse cx="32" cy="46" rx="11" ry="8" fill="#C99A72"/>' +
            '<circle cx="24" cy="32" r="8.5" fill="#FFD25A"/><circle cx="40" cy="32" r="8.5" fill="#FFD25A"/>' + eyes(24, 40, 32, 4.2) +
            '<path d="M28 38h8l-4 7z" fill="#F08C2E"/>' },
        { id: 'panda', name: 'Panda', svg:
            '<circle cx="32" cy="32" r="32" fill="#DDEBFF"/>' +
            '<circle cx="15" cy="18" r="7.5" fill="' + K + '"/><circle cx="49" cy="18" r="7.5" fill="' + K + '"/><ellipse cx="32" cy="36" rx="21" ry="19" fill="#fff"/>' +
            '<ellipse cx="23" cy="33" rx="5.5" ry="7.5" transform="rotate(25 23 33)" fill="' + K + '"/><ellipse cx="41" cy="33" rx="5.5" ry="7.5" transform="rotate(-25 41 33)" fill="' + K + '"/>' +
            '<circle cx="24" cy="32" r="2" fill="#fff"/><circle cx="40" cy="32" r="2" fill="#fff"/>' +
            '<ellipse cx="32" cy="42" rx="3.5" ry="2.5" fill="' + K + '"/>' + smile(32, 46, 4) + cheeks(18, 46, 43) },
        { id: 'schwein', name: 'Schwein', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFE4EC"/>' +
            '<path d="M13 22 L15 8 L27 17Z M51 22 L49 8 L37 17Z" fill="#F28CA8"/><ellipse cx="32" cy="35" rx="21" ry="19" fill="#F7A8BE"/>' +
            '<ellipse cx="32" cy="42" rx="9.5" ry="7" fill="#F28CA8"/><ellipse cx="28.5" cy="42" rx="2" ry="2.8" fill="#9C3A55"/><ellipse cx="35.5" cy="42" rx="2" ry="2.8" fill="#9C3A55"/>' +
            eyes(24, 40, 30, 3) },
        { id: 'pinguin', name: 'Pinguin', svg:
            '<circle cx="32" cy="32" r="32" fill="#D6EEF7"/>' +
            '<ellipse cx="32" cy="36" rx="21" ry="22" fill="#26304D"/><path d="M32 24 Q20 15 17 30 Q16 48 32 52 Q48 48 47 30 Q44 15 32 24Z" fill="#fff"/>' +
            eyes(25, 39, 32, 3) + '<path d="M27 38 Q32 35 37 38 Q32 43.5 27 38Z" fill="#F7A23A"/>' + cheeks(20, 44, 41) },
        { id: 'fuchs', name: 'Fuchs', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFE7D6"/>' +
            '<path d="M11 9 L25 21 L14 29Z M53 9 L39 21 L50 29Z" fill="#E8692A"/><path d="M14 13 L21 20 L15 24Z M50 13 L43 20 L49 24Z" fill="#5A2E14"/>' +
            '<path d="M9 27 Q32 11 55 27 Q51 45 32 55 Q13 45 9 27Z" fill="#E8692A"/>' +
            '<path d="M12 31 Q24 36 32 55 Q17 47 12 31Z M52 31 Q40 36 32 55 Q47 47 52 31Z" fill="#fff"/>' +
            eyes(24, 40, 31) + '<ellipse cx="32" cy="49" rx="3.5" ry="2.6" fill="' + K + '"/>' },
        { id: 'baer', name: 'Bär', svg:
            '<circle cx="32" cy="32" r="32" fill="#F2E6DA"/>' +
            '<circle cx="15" cy="17" r="8" fill="#8B5E3C"/><circle cx="49" cy="17" r="8" fill="#8B5E3C"/><circle cx="15" cy="17" r="4" fill="#C79A72"/><circle cx="49" cy="17" r="4" fill="#C79A72"/>' +
            '<circle cx="32" cy="35" r="20" fill="#8B5E3C"/><ellipse cx="32" cy="42" rx="9.5" ry="7.5" fill="#C79A72"/>' +
            '<ellipse cx="32" cy="39" rx="3.5" ry="2.5" fill="' + K + '"/>' + smile(32, 44, 3) + eyes(25, 39, 30) },
        { id: 'kueken', name: 'Küken', svg:
            '<circle cx="32" cy="32" r="32" fill="#E4F2FF"/>' +
            '<circle cx="32" cy="37" r="20" fill="#FFD43B"/>' +
            '<path d="M13 27 l5-6 l5 5 l4-6 l5 6 l5-6 l4 6 l5-5 l5 6 Q32 4 13 27Z" fill="#FFF8EE" stroke="#E2D3B5" stroke-width="1"/>' +
            eyes(26, 38, 36) + '<path d="M28.5 41 L35.5 41 L32 46Z" fill="#F7A23A"/>' + cheeks(20, 44, 42) },
        { id: 'krake', name: 'Krake', svg:
            '<circle cx="32" cy="32" r="32" fill="#F6E3FF"/>' +
            '<path d="M12 34 Q12 10 32 10 Q52 10 52 34 L52 45 Q49 51 46 45 Q43 51 40 45 Q37 51 34 45 Q31 51 28 45 Q25 51 22 45 Q19 51 16 45 Q13 51 12 45Z" fill="#A45BD9"/>' +
            '<circle cx="25" cy="29" r="4.5" fill="#fff"/><circle cx="39" cy="29" r="4.5" fill="#fff"/><circle cx="26" cy="30" r="2.4" fill="' + K + '"/><circle cx="40" cy="30" r="2.4" fill="' + K + '"/>' +
            smile(32, 37, 4) + '<circle cx="20" cy="19" r="2" fill="#C68CEB"/><circle cx="44" cy="20" r="1.6" fill="#C68CEB"/><circle cx="38" cy="15" r="1.2" fill="#C68CEB"/>' },
        { id: 'dino', name: 'Dino', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFEACC"/>' +
            '<path d="M18 16 l4-8 l4 6 l3-8 l3 8 l3-8 l3 8 l4-6 l4 8Z" fill="#F2994A"/>' +
            '<path d="M12 36 Q12 14 32 14 Q52 14 52 36 Q52 54 32 54 Q12 54 12 36Z" fill="#5BBF6A"/>' +
            '<circle cx="24" cy="31" r="5.5" fill="#fff"/><circle cx="40" cy="31" r="5.5" fill="#fff"/><circle cx="25" cy="32" r="2.8" fill="' + K + '"/><circle cx="41" cy="32" r="2.8" fill="' + K + '"/>' +
            '<path d="M19 41 Q32 52 45 41Z" fill="' + K + '"/><path d="M24 43 l2 3 l2-2.2Z M36 44.5 l2 1.8 l2-3Z" fill="#fff"/>' +
            '<circle cx="28" cy="21" r="1.6" fill="#3E9A4E"/><circle cx="37" cy="22" r="1.3" fill="#3E9A4E"/>' },
        { id: 'monster', name: 'Monster', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFF1BF"/>' +
            '<path d="M19 17 L14 5 L27 13Z M45 17 L50 5 L37 13Z" fill="#F4EEDC"/>' +
            '<path d="M12 34 Q12 12 32 12 Q52 12 52 34 Q52 55 32 55 Q12 55 12 34Z" fill="#2EB5A6"/>' +
            '<circle cx="32" cy="29" r="9.5" fill="#fff"/><circle cx="33" cy="30" r="4.8" fill="' + K + '"/><circle cx="34.6" cy="28.4" r="1.5" fill="#fff"/>' +
            '<path d="M20 42 Q32 52 44 42Z" fill="' + K + '"/><path d="M25 43.2 l2 3 l2-2.6Z M35 44.4 l2 2.6 l2-3Z" fill="#fff"/>' +
            '<circle cx="17" cy="31" r="1.8" fill="#23998C"/><circle cx="47" cy="38" r="2.2" fill="#23998C"/>' },
        { id: 'alien', name: 'Alien', svg:
            '<circle cx="32" cy="32" r="32" fill="#E4F6E8"/>' +
            '<path d="M24 16 L19 8 M40 16 L45 8" stroke="#7CCB5B" stroke-width="2.4" stroke-linecap="round"/><circle cx="19" cy="8" r="3" fill="#F2C94C"/><circle cx="45" cy="8" r="3" fill="#F2C94C"/>' +
            '<path d="M32 13 Q52 13 50 32 Q48 52 32 56 Q16 52 14 32 Q12 13 32 13Z" fill="#7CCB5B"/>' +
            '<ellipse cx="24" cy="32" rx="6" ry="8.5" transform="rotate(-30 24 32)" fill="' + K + '"/><ellipse cx="40" cy="32" rx="6" ry="8.5" transform="rotate(30 40 32)" fill="' + K + '"/>' +
            '<circle cx="22" cy="29" r="1.8" fill="#fff"/><circle cx="38" cy="29" r="1.8" fill="#fff"/>' + smile(32, 46, 3) },
        { id: 'roboter', name: 'Roboter', svg:
            '<circle cx="32" cy="32" r="32" fill="#E2E6F3"/>' +
            '<path d="M32 15V9" stroke="#5A6280" stroke-width="2.4"/><circle cx="32" cy="8" r="3.2" fill="#E5484D"/>' +
            '<rect x="10" y="27" width="5" height="11" rx="2.5" fill="#5A6280"/><rect x="49" y="27" width="5" height="11" rx="2.5" fill="#5A6280"/>' +
            '<rect x="14" y="15" width="36" height="34" rx="9" fill="#9AA4C2"/><rect x="19" y="21" width="26" height="14" rx="5" fill="#26304D"/>' +
            '<circle cx="26" cy="28" r="3.2" fill="#5BE0D0"/><circle cx="38" cy="28" r="3.2" fill="#5BE0D0"/>' +
            '<rect x="22" y="39" width="20" height="6" rx="3" fill="#fff"/><path d="M27 39v6 M32 39v6 M37 39v6" stroke="#9AA4C2" stroke-width="1.2"/>' },
        { id: 'gespenst', name: 'Gespenst', svg:
            '<circle cx="32" cy="32" r="32" fill="#E5E1FF"/>' +
            '<path d="M16 51 V30 Q16 12 32 12 Q48 12 48 30 V51 l-4-4 l-4 4 l-4-4 l-4 4 l-4-4 l-4 4 l-4-4Z" fill="#fff"/>' +
            '<ellipse cx="26" cy="30" rx="3" ry="4.2" fill="' + K + '"/><ellipse cx="38" cy="30" rx="3" ry="4.2" fill="' + K + '"/>' +
            '<ellipse cx="32" cy="40" rx="3.5" ry="4.5" fill="' + K + '"/>' + cheeks(21, 43, 36) },
        { id: 'kaktus', name: 'Kaktus', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFE9D2"/>' +
            '<path d="M14 26 Q14 20 18 20 Q22 20 22 26 V34 H24 V40 H18 Q14 40 14 34Z M50 22 Q50 16 46 16 Q42 16 42 22 V30 H40 V36 H46 Q50 36 50 30Z" fill="#3E9A4E"/>' +
            '<rect x="22" y="10" width="20" height="42" rx="10" fill="#5BBF6A"/><circle cx="32" cy="9" r="4" fill="#F25F9C"/>' +
            '<rect x="16" y="50" width="32" height="10" rx="3" fill="#D9734E"/>' + eyes(27, 37, 27, 2.6) + smile(32, 34, 3) + cheeks(25, 39, 32) },
        { id: 'pizza', name: 'Pizza', svg:
            '<circle cx="32" cy="32" r="32" fill="#FFF0D6"/>' +
            '<path d="M9 16 Q32 4 55 16 L32 58Z" fill="#F7C35A"/><path d="M9 16 Q32 4 55 16 L53 21 Q32 10 11 21Z" fill="#C9822E"/>' +
            '<circle cx="22" cy="24" r="3.6" fill="#D9485F"/><circle cx="42" cy="25" r="3.2" fill="#D9485F"/><circle cx="31" cy="45" r="3" fill="#D9485F"/>' +
            eyes(27, 37, 30, 2.6) + smile(32, 36, 3) },
        { id: 'einhorn', name: 'Einhorn', svg:
            '<circle cx="32" cy="32" r="32" fill="#FCE4F4"/>' +
            '<path d="M29 15 L32 1 L35 15Z" fill="#F2C94C"/><path d="M30 11 l4-1.5 M29.5 7 l3.5-1.2" stroke="#E0A92E" stroke-width="1.2"/>' +
            '<path d="M16 20 L18 10 L25 17Z M48 20 L46 10 L39 17Z" fill="#fff" stroke="#E6D9F0" stroke-width="1"/>' +
            '<ellipse cx="32" cy="35" rx="18" ry="19" fill="#fff"/>' +
            '<path d="M18 22 Q14 34 20 44 Q16 32 24 22Z" fill="#9B7BF2"/><path d="M22 18 Q24 28 30 30 Q26 22 28 16Z" fill="#F57EB6"/><path d="M27 16 Q33 24 38 22 Q33 18 34 14Z" fill="#6CC4F0"/>' +
            eyes(26, 40, 34) + '<ellipse cx="33" cy="45" rx="8" ry="5" fill="#FBD3E6"/><circle cx="30.5" cy="45" r="1" fill="#C26A92"/><circle cx="35.5" cy="45" r="1" fill="#C26A92"/>' },
        { id: 'faultier', name: 'Faultier', svg:
            '<circle cx="32" cy="32" r="32" fill="#E9F2E1"/>' +
            '<circle cx="32" cy="34" r="21" fill="#9C7B5B"/><ellipse cx="32" cy="36" rx="16" ry="14" fill="#E6D3B8"/>' +
            '<path d="M17 34 Q20 26 29 31 Q24 38 17 34Z M47 34 Q44 26 35 31 Q40 38 47 34Z" fill="#5C4330"/>' +
            '<path d="M21 33 q3 -2 5 0 M38 33 q3 -2 5 0" stroke="' + K + '" stroke-width="1.8" fill="none" stroke-linecap="round"/>' +
            '<ellipse cx="32" cy="39" rx="3.4" ry="2.4" fill="' + K + '"/>' + smile(32, 43, 3.5) },
    ];

    var MAP = {};
    LIST.forEach(function (a) { MAP[a.id] = a; });

    /** SVG-Markup eines Avatars; unbekannte IDs ergeben einen neutralen Kreis */
    function html(id, size, extraClass) {
        var a = MAP[id];
        var inner = a ? a.svg : '<circle cx="32" cy="32" r="32" fill="#D9DCEA"/>';
        var label = a ? a.name : '';
        return '<svg class="avatar' + (extraClass ? ' ' + extraClass : '') + '" viewBox="0 0 64 64" width="' + (size || 40) + '" height="' + (size || 40) +
            '" role="img" aria-label="' + label + '" focusable="false">' + inner + '</svg>';
    }
    function random() { return LIST[Math.floor(Math.random() * LIST.length)].id; }

    global.NaguutAvatars = { list: LIST, html: html, random: random, has: function (id) { return !!MAP[id]; } };
})(window);
