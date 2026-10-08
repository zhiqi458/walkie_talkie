export function registerPwa() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        const baseUrl = window.__WALKIE__?.baseUrl || '';
        navigator.serviceWorker.register(`${baseUrl}/public/sw.js`).catch(() => {});
    });
}
