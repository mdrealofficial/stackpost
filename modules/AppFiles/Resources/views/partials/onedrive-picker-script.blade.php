<script>
    (function () {
        if (window.__appOneDrivePicker) {
            return;
        }

        let scriptPromise = null;
        const debug = (...args) => console.log('[AppOneDrivePicker]', ...args);

        const ensureScript = () => {
            if (window.OneDrive?.open) {
                return Promise.resolve();
            }

            if (scriptPromise) {
                return scriptPromise;
            }

            scriptPromise = new Promise((resolve, reject) => {
                const existing = document.getElementById('app-onedrivejs');

                if (existing) {
                    existing.addEventListener('load', () => resolve(), { once: true });
                    existing.addEventListener('error', () => reject(new Error('Could not load OneDrive Picker.')), { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.id = 'app-onedrivejs';
                script.src = 'https://js.live.net/v7.2/OneDrive.js';
                script.async = true;
                script.defer = true;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Could not load OneDrive Picker.'));
                document.body.appendChild(script);
            });

            return scriptPromise;
        };

        window.__appOneDrivePicker = {
            async pick({ clientId, redirectUri, multiple = true, extensions = [] } = {}) {
                if (!clientId) {
                    throw new Error('OneDrive Picker is not configured.');
                }

                await ensureScript();

                if (!window.OneDrive?.open) {
                    throw new Error('OneDrive Picker is unavailable.');
                }

                debug('pick:start', {
                    clientIdPreview: String(clientId).slice(0, 6),
                    redirectUri,
                    multiple,
                    origin: window.location.origin,
                });

                return await new Promise((resolve, reject) => {
                    try {
                        window.OneDrive.open({
                            clientId: String(clientId),
                            action: 'download',
                            multiSelect: Boolean(multiple),
                            advanced: {
                                redirectUri: String(redirectUri || (window.location.origin + window.location.pathname)),
                                filter: Array.isArray(extensions) ? extensions.join(',') : '',
                            },
                            success(files) {
                                const items = Array.isArray(files?.value) ? files.value : [];
                                debug('pick:success', { count: items.length });

                                const normalizedFiles = items
                                    .map((file) => ({
                                        link: String(file?.['@microsoft.graph.downloadUrl'] || ''),
                                        name: String(file?.name || ''),
                                    }))
                                    .filter((file) => file.link !== '');

                                if (!normalizedFiles.length) {
                                    reject(new Error('The selected OneDrive file could not be read.'));
                                    return;
                                }

                                resolve({ files: normalizedFiles, accessToken: String(files?.accessToken || '') });
                            },
                            cancel() {
                                debug('pick:cancel');
                                reject(new Error('OneDrive picker cancelled.'));
                            },
                            error(error) {
                                debug('pick:error', error);
                                reject(new Error(error?.message || 'OneDrive picker failed.'));
                            },
                        });
                    } catch (error) {
                        reject(error instanceof Error ? error : new Error('OneDrive picker failed.'));
                    }
                });
            },
        };
    })();
</script>
