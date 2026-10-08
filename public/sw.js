const CACHE_NAME = 'walkie-talkie-v3';
const BASE_PATH = self.location.pathname.replace(/\/public\/sw\.js$/, '');
const APP_SHELL = [
    `${BASE_PATH}/`,
    `${BASE_PATH}/public/assets/css/app.css`,
    `${BASE_PATH}/public/assets/js/app.js`,
    `${BASE_PATH}/public/assets/js/audio-level.js`,
    `${BASE_PATH}/public/assets/js/ptt.js`,
    `${BASE_PATH}/public/assets/js/signaling.js`,
    `${BASE_PATH}/public/assets/js/webrtc.js`,
    `${BASE_PATH}/public/assets/js/pwa.js`,
    `${BASE_PATH}/public/manifest.webmanifest`,
    `${BASE_PATH}/public/assets/icons/icon.svg`,
    `${BASE_PATH}/public/assets/icons/icon-192.svg`,
    `${BASE_PATH}/public/assets/icons/icon-512.svg`,
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => Promise.all(
                APP_SHELL.map((url) => fetch(url, { cache: 'no-cache' })
                    .then((response) => {
                        if (response.ok) {
                            return cache.put(url, response);
                        }
                        return null;
                    })
                    .catch(() => null))
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const requestUrl = new URL(event.request.url);
    if (requestUrl.pathname.includes('/signal')) {
        return;
    }

    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(event.request).then((response) => {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy)).catch(() => {});
                return response;
            }).catch(() => caches.match(`${BASE_PATH}/`));
        })
    );
});
