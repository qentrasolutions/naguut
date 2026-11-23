// Naguut – Standard-Kartenausschnitt in der Verwaltung
(function () {
    'use strict';
    var box = document.getElementById('admin-map');
    if (!box || !window.NaguutMap) return;
    var v = JSON.parse(box.getAttribute('data-view'));
    var map = new NaguutMap(box, { lat: v.lat, lng: v.lng, zoom: v.zoom, tiles: JSON.parse(box.getAttribute('data-tiles')) });
    document.getElementById('admin-map-form').addEventListener('submit', function () {
        var cur = map.getView();
        this.lat.value = cur.lat;
        this.lng.value = cur.lng;
        this.zoom.value = cur.zoom;
    });
})();
