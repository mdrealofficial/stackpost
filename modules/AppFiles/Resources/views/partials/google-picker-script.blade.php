@once
    <script>
        (() => {
            if (window.__appGooglePicker) {
                return;
            }

            const GOOGLE_API_SRC = 'https://apis.google.com/js/api.js';
            const GOOGLE_GIS_SRC = 'https://accounts.google.com/gsi/client';

            const loadScript = (src) => new Promise((resolve, reject) => {
                const existing = document.querySelector(`script[src="${src}"]`);

                if (existing) {
                    if (existing.dataset.loaded === 'true') {
                        resolve();
                        return;
                    }

                    existing.addEventListener('load', () => resolve(), { once: true });
                    existing.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.defer = true;
                script.addEventListener('load', () => {
                    script.dataset.loaded = 'true';
                    resolve();
                }, { once: true });
                script.addEventListener('error', () => reject(new Error(`Failed to load ${src}`)), { once: true });
                document.head.appendChild(script);
            });

            window.__appGooglePicker = {
                scriptsPromise: null,
                pickerPromise: null,
                debugEnabled: true,
                debug(...args) {
                    if (!this.debugEnabled) return;
                    console.log('[AppGooglePicker]', ...args);
                },
                docField(doc, ...keys) {
                    if (!doc || typeof doc !== 'object') {
                        return '';
                    }

                    for (const key of keys) {
                        const value = doc[key];

                        if (typeof value === 'string' && value.trim() !== '') {
                            return value.trim();
                        }
                    }

                    return '';
                },
                normalizeDoc(doc) {
                    const documentKeys = window.google?.picker?.Document || {};

                    return {
                        id: this.docField(doc, documentKeys.ID, 'id', 'ID', 'documentId'),
                        name: this.docField(doc, documentKeys.NAME, 'name', 'NAME', 'title'),
                        mimeType: this.docField(doc, documentKeys.MIME_TYPE, 'mimeType', 'MIME_TYPE', 'type'),
                        resourceKey: this.docField(doc, documentKeys.RESOURCE_KEY, 'resourceKey', 'RESOURCE_KEY'),
                    };
                },
                async ensureScripts() {
                    this.debug('ensureScripts:start');
                    if (!this.scriptsPromise) {
                        this.scriptsPromise = Promise.all([
                            loadScript(GOOGLE_API_SRC),
                            loadScript(GOOGLE_GIS_SRC),
                        ]);
                    }

                    return this.scriptsPromise;
                },
                async ensurePickerApi() {
                    this.debug('ensurePickerApi:start');
                    await this.ensureScripts();

                    if (!this.pickerPromise) {
                        this.pickerPromise = new Promise((resolve) => {
                            window.gapi.load('picker', { callback: resolve });
                        });
                    }

                    return this.pickerPromise;
                },
                async requestAccessToken(clientId) {
                    this.debug('requestAccessToken:start', { clientId, origin: window.location.origin });
                    await this.ensureScripts();

                    return new Promise((resolve, reject) => {
                        const tokenClient = window.google?.accounts?.oauth2?.initTokenClient?.({
                            client_id: clientId,
                            scope: 'https://www.googleapis.com/auth/drive.readonly',
                            callback: (response) => {
                                this.debug('requestAccessToken:callback', response);
                                if (response?.access_token) {
                                    resolve(response.access_token);
                                    return;
                                }

                                reject(new Error('Google access token was not returned.'));
                            },
                            error_callback: () => reject(new Error('Google authentication failed.')),
                        });

                        if (!tokenClient) {
                            reject(new Error('Google Identity Services is not available.'));
                            return;
                        }

                        tokenClient.requestAccessToken({ prompt: 'consent' });
                    });
                },
                async pick(config = {}) {
                    const clientId = String(config.clientId || '').trim();
                    const apiKey = String(config.apiKey || '').trim();
                    this.debug('pick:start', { clientId, apiKeyPreview: apiKey ? `${apiKey.slice(0, 6)}...` : '', multiple: !!config.multiple, mimeTypes: config.mimeTypes || '', origin: window.location.origin });

                    if (!clientId || !apiKey) {
                        throw new Error('Google Picker is not configured.');
                    }

                    await this.ensurePickerApi();

                    const accessToken = await this.requestAccessToken(clientId);

                    return new Promise((resolve, reject) => {
                        const view = new window.google.picker.DocsView(window.google.picker.ViewId.DOCS)
                            .setIncludeFolders(false)
                            .setSelectFolderEnabled(false);

                        if (config.mimeTypes) {
                            view.setMimeTypes(config.mimeTypes);
                        }

                        const picker = new window.google.picker.PickerBuilder()
                            .setDeveloperKey(apiKey)
                            .setOAuthToken(accessToken)
                            .addView(view)
                            .setCallback((data) => {
                                this.debug('picker:callback', data);
                                if (data.action === window.google.picker.Action.PICKED) {
                                    const normalizedDocs = (Array.isArray(data.docs) ? data.docs : [])
                                        .map((doc) => this.normalizeDoc(doc))
                                        .filter((doc) => doc.id !== '');
                                    this.debug('picker:normalizedDocs', normalizedDocs);

                                    if (normalizedDocs.length === 0) {
                                        reject(new Error('The selected Google Drive file could not be read.'));
                                        return;
                                    }

                                    resolve({
                                        accessToken,
                                        docs: normalizedDocs,
                                    });
                                    return;
                                }

                                if (data.action === window.google.picker.Action.CANCEL) {
                                    reject(new Error('Google Picker was cancelled.'));
                                }
                            });

                        if (config.multiple) {
                            picker.enableFeature(window.google.picker.Feature.MULTISELECT_ENABLED);
                        }

                        picker.build().setVisible(true);
                    });
                },
            };
        })();
    </script>
@endonce
