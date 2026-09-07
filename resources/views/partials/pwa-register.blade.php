@php
    $pwaThemeColor = $pwaThemeColor ?? '#dc2626';
@endphp

<style>
    .pwa-install-button {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        border: 0;
        border-radius: 999px;
        background: {{ $pwaThemeColor }};
        color: #ffffff;
        padding: 0.72rem 1rem;
        font-weight: 700;
        font-size: 0.875rem;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.25);
    }

    .pwa-install-button:hover {
        filter: brightness(0.95);
    }

    .pwa-install-button:disabled {
        opacity: 0.72;
        cursor: wait;
    }
</style>

<script>
    (() => {
        if (window.__schoolPwaBootstrapped) {
            return;
        }

        window.__schoolPwaBootstrapped = true;

        const isInstalled = () =>
            window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            document.referrer.startsWith('android-app://');

        let deferredInstallPrompt = null;
        let installButton = null;

        const hideInstallButton = () => {
            if (installButton) {
                installButton.style.display = 'none';
            }
        };

        const showInstallButton = () => {
            if (!installButton) {
                return;
            }

            if (deferredInstallPrompt && !isInstalled()) {
                installButton.style.display = 'inline-flex';
            }
        };

        const ensureInstallButton = () => {
            if (installButton || !document.body || isInstalled()) {
                return;
            }

            installButton = document.createElement('button');
            installButton.type = 'button';
            installButton.id = 'pwa-install-button';
            installButton.className = 'pwa-install-button';
            installButton.setAttribute('aria-label', 'Install app');
            installButton.textContent = 'Install App';

            installButton.addEventListener('click', async () => {
                if (!deferredInstallPrompt) {
                    return;
                }

                const promptEvent = deferredInstallPrompt;
                deferredInstallPrompt = null;
                installButton.disabled = true;

                try {
                    await promptEvent.prompt();
                    await promptEvent.userChoice;
                } catch (error) {
                    console.warn('Install prompt failed.', error);
                } finally {
                    installButton.disabled = false;
                    hideInstallButton();
                    showInstallButton();
                }
            });

            document.body.appendChild(installButton);
            showInstallButton();
        };

        if (document.readyState === 'loading') {
            window.addEventListener('DOMContentLoaded', ensureInstallButton, { once: true });
        } else {
            ensureInstallButton();
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            // Use our Install App button; Chrome may log that its automatic banner was suppressed.
            // prompt() must remain inside the user click handler.
            event.preventDefault();
            deferredInstallPrompt = event;
            ensureInstallButton();
            showInstallButton();
        });

        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            hideInstallButton();
        });

        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', async () => {
                try {
                    const registration = await navigator.serviceWorker.register(@js(route('pwa.service-worker')));
                    registration.update().catch(() => undefined);
                } catch (error) {
                    console.warn('Service worker registration failed.', error);
                }
            });
        }
    })();
</script>
