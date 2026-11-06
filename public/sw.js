/*
 * Service worker sederhana untuk PWA.
 * - Halaman (HTML) selalu diambil dari server supaya data dan sesi tetap terbaru.
 *   Kalau tidak ada koneksi, tampilkan halaman offline.html.
 * - File statis (css, js, gambar) disimpan agar aplikasi cepat dibuka.
 */
var VERSI = 'scp-v3';
var OFFLINE = new URL('offline.html', self.registration.scope).href;
var STATIS = /\/(css|js|images)\//;

self.addEventListener('install', function (e) {
    e.waitUntil(caches.open(VERSI).then(function (c) { return c.add(OFFLINE); }));
    self.skipWaiting();
});

self.addEventListener('activate', function (e) {
    e.waitUntil(
        caches.keys().then(function (nama) {
            return Promise.all(nama.filter(function (n) { return n !== VERSI; })
                .map(function (n) { return caches.delete(n); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (e) {
    var req = e.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== location.origin) return;

    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE); }));
        return;
    }

    if (STATIS.test(url.pathname)) {
        // css/js: utamakan versi terbaru dari server, cache hanya cadangan saat offline.
        // gambar: cache dulu supaya cepat (jarang berubah).
        var kodeAsli = /\/(css|js)\//.test(url.pathname);
        e.respondWith(
            caches.open(VERSI).then(function (cache) {
                if (kodeAsli) {
                    return fetch(req).then(function (res) {
                        if (res && res.ok) cache.put(req, res.clone());
                        return res;
                    }).catch(function () { return cache.match(req); });
                }
                return cache.match(req).then(function (ada) {
                    var ambil = fetch(req).then(function (res) {
                        if (res && res.ok) cache.put(req, res.clone());
                        return res;
                    }).catch(function () { return ada; });
                    return ada || ambil;
                });
            })
        );
    }
});
