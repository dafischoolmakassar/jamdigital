/**
 * Service worker minimal -- syarat teknis supaya browser mau menawarkan
 * "Install App"/"Add to Home Screen" (PWA installable). BELUM melakukan
 * caching offline sungguhan (fetch tetap diteruskan apa adanya ke network).
 * Kalau nanti mau caching offline utk gambar slideshow/aset statis, tambah
 * logic di event 'fetch' di sini.
 */
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', () => {
    // no-op -- biarkan browser fetch seperti biasa langsung ke network
});
