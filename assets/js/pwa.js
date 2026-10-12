/**
 * PWA Registration & Install Handler
 * Registers service worker and provides install prompt banner UX.
 */
(function() {
    'use strict';

    // 1. Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            // Determine sw path relative to application root
            const swPath = (window.WALKIE_CONFIG && window.WALKIE_CONFIG.homeUrl)
                ? `${window.WALKIE_CONFIG.homeUrl.replace(/\/+$/, '')}/sw.js`
                : 'sw.js';

            navigator.serviceWorker.register(swPath)
                .then((registration) => {
                    console.log('Walkie-Talkie ServiceWorker registered with scope:', registration.scope);
                })
                .catch((err) => {
                    console.warn('ServiceWorker registration skipped or failed:', err);
                });
        });
    }

    // 2. Install Prompt Handling
    let deferredPrompt = null;
    const banner = document.getElementById('pwaInstallBanner');
    const btnInstall = document.getElementById('btnInstallPwa');
    const btnDismiss = document.getElementById('btnDismissPwa');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent default mini-infobar on mobile Chrome
        e.preventDefault();
        deferredPrompt = e;

        // Check if user previously dismissed
        if (sessionStorage.getItem('wt_pwa_dismissed') !== 'true' && banner) {
            banner.classList.remove('d-none');
        }
    });

    if (btnInstall) {
        btnInstall.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            banner.classList.add('d-none');
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User PWA install response: ${outcome}`);
            deferredPrompt = null;
        });
    }

    if (btnDismiss) {
        btnDismiss.addEventListener('click', () => {
            if (banner) {
                banner.classList.add('d-none');
            }
            sessionStorage.setItem('wt_pwa_dismissed', 'true');
        });
    }

    window.addEventListener('appinstalled', () => {
        if (banner) {
            banner.classList.add('d-none');
        }
        deferredPrompt = null;
        console.log('Walkie-Talkie PWA successfully installed.');
    });
})();
