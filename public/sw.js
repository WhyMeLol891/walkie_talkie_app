/**
 * Walkie-Talkie Service Worker
 * Caches static shell, styles, and scripts.
 * Explicitly excludes dynamic signaling APIs and private communication data.
 */

const CACHE_NAME = 'wt-shell-v1.0.0';
const SHELL_ASSETS = [
    './',
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/js/audio-level.js',
    'assets/js/webrtc.js',
    'assets/js/signaling.js',
    'assets/js/ptt.js',
    'assets/js/pwa.js',
    'assets/js/qrcode.min.js',
    'assets/icons/favicon.svg',
    'manifest.webmanifest',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

// Install: Cache core assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(SHELL_ASSETS).catch((err) => {
                console.warn('Failed to cache some shell assets during install:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: Prune stale caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: Network-first for dynamic navigation, Cache-first for assets, NEVER cache signaling
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // 1. NEVER cache signaling API, POST requests, or dynamic backend endpoints
    if (event.request.method !== 'GET' ||
        url.pathname.includes('/api/') ||
        url.pathname.includes('/signal') ||
        url.pathname.includes('/leave') ||
        url.pathname.includes('/join')) {
        return; // Let browser perform normal direct network request
    }

    // 2. Navigation requests: Network first with cache fallback
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match('./') || caches.match(event.request);
            })
        );
        return;
    }

    // 3. Static assets: Cache first with network fallback
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(event.request).then((response) => {
                // If valid response and static asset, cache a copy
                if (response && response.status === 200 && response.type === 'basic') {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return response;
            });
        })
    );
});
