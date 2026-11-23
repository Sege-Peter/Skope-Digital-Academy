/* Skope Digital Academy service worker: installable app, fast repeat visits, offline fallback.
 * - Pages: network first (always fresh). Public pages are kept as a fallback for offline use;
 *   signed-in areas (admin, tutor, student, api, sign-in) are NEVER cached, so nothing private is stored.
 * - Static files (CSS, JS, images, fonts): served from cache, refreshed in the background.
 * Bump VERSION when shipping changes to the precached files.
 */
const VERSION = 'sda-v2';
const STATIC_CACHE = VERSION + '-static';
const PAGE_CACHE = VERSION + '-pages';
const SCOPE = self.registration.scope;                      // e.g. https://skopedigital.co.ke/
const OFFLINE_URL = new URL('offline.html', SCOPE).href;
const PRECACHE = ['offline.html', 'manifest.webmanifest', 'assets/pwa/icon-192.png', 'assets/pwa/icon-512.png'].map((p) => new URL(p, SCOPE).href);
const PRIVATE = /\/(admin|tutor|student|api|lab\/api)\/|\/(login|register|logout|google-auth|checkout|enroll|verify-payment|paystack-webhook|certificate-pay|forgot-password|reset-password|force_password_change|email-preferences|calendar-feed|cron|impersonate|sitemap\.xml)(\?|$|\/)/;
const STATIC_HOSTS = ['cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'cdn.jsdelivr.net'];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(STATIC_CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const key of await caches.keys()) if (!key.startsWith(VERSION)) await caches.delete(key);
    if (self.registration.navigationPreload) await self.registration.navigationPreload.enable();
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;                           // forms, payments, uploads go straight to the network
  const url = new URL(req.url);

  // Pages
  if (req.mode === 'navigate') {
    const isPrivate = url.origin !== location.origin || PRIVATE.test(url.pathname + (url.search ? url.search : ''));
    event.respondWith((async () => {
      try {
        const res = (await event.preloadResponse) || await fetch(req);
        if (!isPrivate && res.ok && res.type === 'basic') {
          const copy = res.clone();
          caches.open(PAGE_CACHE).then((c) => c.put(req, copy)).then(() => trim(PAGE_CACHE, 40));
        }
        return res;
      } catch (e) {
        if (!isPrivate) { const hit = await caches.match(req, { ignoreVary: true }); if (hit) return hit; }
        return (await caches.match(OFFLINE_URL)) || new Response('You are offline.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
      }
    })());
    return;
  }

  // Static files: same-origin assets/uploads and known CDNs
  const isStatic = (url.origin === location.origin && /\.(css|js|png|jpe?g|webp|gif|svg|ico|woff2?|ttf)$/i.test(url.pathname) && !PRIVATE.test(url.pathname))
                || STATIC_HOSTS.includes(url.hostname);
  if (isStatic) {
    event.respondWith((async () => {
      const cache = await caches.open(STATIC_CACHE);
      const hit = await cache.match(req);
      const refresh = fetch(req).then((res) => { if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone()).then(() => trim(STATIC_CACHE, 200)); return res; }).catch(() => null);
      return hit || (await refresh) || new Response('', { status: 504 });
    })());
  }
});

async function trim(name, max) {
  const cache = await caches.open(name);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i]);
}

self.addEventListener('message', (event) => { if (event.data === 'skipWaiting') self.skipWaiting(); });
