<script>
    (function () {
        if (window.__appDropboxChooser) {
            return;
        }

        let chooserScriptPromise = null;
        const debug = (...args) => console.log('[AppDropboxChooser]', ...args);

        const ensureChooserScript = (appKey) => {
            const normalizedAppKey = String(appKey || '');
            const existing = document.getElementById('dropboxjs');
            const existingAppKey = String(existing?.getAttribute('data-app-key') || '');

            if (window.Dropbox?.choose && existing && existingAppKey === normalizedAppKey) {
                debug('ensureScript:alreadyLoaded', {
                    appKeyPreview: normalizedAppKey.slice(0, 6),
                    origin: window.location.origin,
                });
                return Promise.resolve();
            }

            if (chooserScriptPromise) {
                chooserScriptPromise = null;
            }

            chooserScriptPromise = new Promise((resolve, reject) => {
                const currentScript = document.getElementById('dropboxjs');
                const currentAppKey = String(currentScript?.getAttribute('data-app-key') || '');

                if (currentScript) {
                    debug('ensureScript:existing', {
                        existingAppKeyPreview: currentAppKey.slice(0, 6),
                        requestedAppKeyPreview: normalizedAppKey.slice(0, 6),
                        origin: window.location.origin,
                    });
                    currentScript.remove();
                }

                try {
                    delete window.Dropbox;
                } catch (error) {
                    window.Dropbox = undefined;
                }

                const script = document.createElement('script');
                script.id = 'dropboxjs';
                script.setAttribute('data-app-key', normalizedAppKey);
                script.src = 'https://www.dropbox.com/static/api/2/dropins.js';
                debug('ensureScript:create', {
                    appKeyPreview: normalizedAppKey.slice(0, 6),
                    origin: window.location.origin,
                });
                script.onload = () => {
                    debug('ensureScript:loaded', {
                        appKeyPreview: normalizedAppKey.slice(0, 6),
                        hasDropbox: Boolean(window.Dropbox?.choose),
                    });
                    resolve();
                };
                script.onerror = () => reject(new Error('Could not load Dropbox Chooser.'));
                (document.body || document.documentElement).appendChild(script);
            });

            return chooserScriptPromise;
        };

        window.__appDropboxChooser = {
            async pick({ appKey, multiple = true, extensions = [] } = {}) {
                if (!appKey) {
                    throw new Error('Dropbox Chooser is not configured.');
                }

                debug('pick:start', {
                    appKeyPreview: String(appKey || '').slice(0, 6),
                    origin: window.location.origin,
                    multiple,
                });

                await ensureChooserScript(appKey);

                if (!window.Dropbox?.choose) {
                    throw new Error('Dropbox Chooser is unavailable.');
                }

                return await new Promise((resolve, reject) => {
                    try {
                        window.Dropbox.choose({
                            linkType: 'direct',
                            multiselect: multiple,
                            extensions: Array.isArray(extensions) ? extensions : [],
                            success(files) {
                                debug('pick:success', { count: Array.isArray(files) ? files.length : 0 });
                                const normalizedFiles = (Array.isArray(files) ? files : [])
                                    .map((file) => ({
                                        link: String(file?.link || ''),
                                        name: String(file?.name || ''),
                                        bytes: Number(file?.bytes || 0),
                                        icon: String(file?.icon || ''),
                                        thumbnailLink: String(file?.thumbnailLink || ''),
                                    }))
                                    .filter((file) => file.link !== '');

                                if (!normalizedFiles.length) {
                                    reject(new Error('The selected Dropbox file could not be read.'));
                                    return;
                                }

                                resolve({ files: normalizedFiles });
                            },
                            cancel() {
                                debug('pick:cancel');
                                reject(new Error('Dropbox chooser cancelled.'));
                            },
                        });
                    } catch (error) {
                        debug('pick:error', error);
                        reject(error instanceof Error ? error : new Error('Dropbox chooser failed.'));
                    }
                });
            },
        };
    })();
</script>
