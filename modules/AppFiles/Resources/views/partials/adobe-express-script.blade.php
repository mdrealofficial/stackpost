<script>
window.__appAdobeExpress = window.__appAdobeExpress || {
    scriptPromise: null,
    sdkPromise: null,
    sdkConfigKey: null,
    async ensureLoaded() {
        if (window.CCEverywhere) {
            return window.CCEverywhere;
        }

        if (this.scriptPromise) {
            return this.scriptPromise;
        }

        this.scriptPromise = new Promise((resolve, reject) => {
            const existing = document.querySelector('script[data-app-adobe-express]');

            if (existing) {
                existing.addEventListener('load', () => resolve(window.CCEverywhere), { once: true });
                existing.addEventListener('error', () => reject(new Error('Adobe Express SDK failed to load.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cc-embed.adobe.com/sdk/v4/CCEverywhere.js';
            script.async = true;
            script.defer = true;
            script.dataset.appAdobeExpress = 'true';
            script.onload = () => resolve(window.CCEverywhere);
            script.onerror = () => reject(new Error('Adobe Express SDK failed to load.'));
            document.head.appendChild(script);
        });

        return this.scriptPromise;
    },
    async ensureInitialized(config = {}) {
        const clientId = String(config.clientId || '').trim();
        const appName = String(config.appName || '').trim();

        if (!clientId || !appName) {
            throw new Error('Adobe Express is not configured.');
        }

        await this.ensureLoaded();

        const nextKey = `${clientId}:${appName}`;

        if (this.sdkPromise && this.sdkConfigKey === nextKey) {
            return this.sdkPromise;
        }

        this.sdkConfigKey = nextKey;
        this.sdkPromise = window.CCEverywhere.initialize({
            clientId,
            appName,
        });

        return this.sdkPromise;
    },
    normalizePublishedAsset(publishParams) {
        const asset = Array.isArray(publishParams?.asset) ? publishParams.asset[0] : null;
        const dataUrl = String(asset?.data || '');
        const name = String(asset?.title || publishParams?.title || 'adobe-express-image.png');

        if (!dataUrl.startsWith('data:')) {
            throw new Error('Adobe Express did not return an image payload.');
        }

        return {
            dataUrl,
            name,
        };
    },
    async createImage(config = {}) {
        const sdk = await this.ensureInitialized(config);

        return await new Promise((resolve, reject) => {
            let settled = false;

            const finish = (callback) => (value) => {
                if (settled) {
                    return;
                }

                settled = true;
                callback(value);
            };

            const rejectOnce = finish(reject);
            const resolveOnce = finish(resolve);

            const editorConfig = {
                callbacks: {
                    onCancel: () => rejectOnce(new Error('cancelled')),
                    onError: (error) => rejectOnce(error instanceof Error ? error : new Error(String(error || 'Adobe Express failed.'))),
                    onPublish: (_intent, publishParams) => {
                        try {
                            resolveOnce(this.normalizePublishedAsset(publishParams));
                        } catch (error) {
                            rejectOnce(error instanceof Error ? error : new Error(String(error || 'Adobe Express publish failed.')));
                        }
                    },
                },
            };

            const exportConfig = [{
                id: 'save-to-host-app',
                label: 'Save',
                action: {
                    target: 'publish',
                },
                style: {
                    uiType: 'button',
                    variant: 'primary',
                },
            }];

            const containerConfig = {
                zIndex: 260,
                iframeTitle: 'Adobe Express',
            };

            try {
                sdk.module.createImageFromText(editorConfig, exportConfig, containerConfig);
            } catch (error) {
                rejectOnce(error instanceof Error ? error : new Error(String(error || 'Adobe Express launch failed.')));
            }
        });
    },
};
</script>
<style>
iframe[src*="cc-embed.adobe.com"],
iframe[src*="express.adobe.com"],
.cc-everywhere-root,
.cc-everywhere-modal,
.cc-everywhere-iframe {
    z-index: 260 !important;
}
</style>
