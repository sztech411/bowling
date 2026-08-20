/**
 * Service worker PIKO TAZ.
 *
 * Aplikasi ini dijana di pelayan (PHP) dan setiap halaman membawa token CSRF
 * unik pada sesi semasa — jadi HALAMAN HTML TIDAK DICACHE. Hanya aset statik
 * (CSS, ikon, manifest) dicache supaya aplikasi boleh dipasang dan kekal
 * responsif; navigasi sentiasa cuba rangkaian dahulu dan hanya jatuh kembali
 * kepada offline.html apabila benar-benar tiada sambungan.
 */

const CACHE = 'piko-taz-v1';

const PRECACHE = [
  'assets/style.css',
  'assets/icons/icon-192.png',
  'assets/icons/icon-512.png',
  'assets/icons/icon-maskable-512.png',
  'assets/icons/apple-touch-icon.png',
  'assets/icons/favicon-32.png',
  'manifest.webmanifest',
  'offline.html',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return url.origin === self.location.origin && (
    url.pathname.indexOf('/assets/') !== -1 ||
    url.pathname.endsWith('manifest.webmanifest')
  );
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return; // borang POST (kehadiran, skor, log masuk) sentiasa terus ke rangkaian
  }

  const url = new URL(req.url);

  // Navigasi halaman (index.php dsb.) — rangkaian dahulu, kembali offline.html jika gagal.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('offline.html'))
    );
    return;
  }

  // Aset statik same-origin — cache dahulu, rangkaian sebagai sandaran, kemas cache.
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const network = fetch(req).then((res) => {
          if (res && res.ok) {
            const copy = res.clone();
            caches.open(CACHE).then((cache) => cache.put(req, copy));
          }
          return res;
        }).catch(() => cached);
        return cached || network;
      })
    );
    return;
  }

  // Semua permintaan lain (data.php, eksport CSV, dll.) — terus ke rangkaian.
});
