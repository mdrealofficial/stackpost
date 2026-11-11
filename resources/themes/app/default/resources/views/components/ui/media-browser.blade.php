@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'value' => [],
    'context' => 'auto',
    'type' => 'all',
    'multiple' => true,
    'layout' => 'split',
    'emptyLabel' => null,
    'libraryTitle' => null,
    'selectionTitle' => null,
    'frameless' => false,
    'showLibraryHeader' => true,
    'compactToolbar' => false,
])

@php
    $basePath = rtrim((string) request()->getBaseUrl(), '/');
    $normalizeUrl = static function (?string $url) use ($basePath): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($url, ['http://', 'https://'])) {
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $query = parse_url($url, PHP_URL_QUERY);

            if ($path !== '') {
                $relative = $query ? $path.'?'.$query : $path;

                if ($basePath !== '' && str_starts_with($relative, '/') && ! str_starts_with($relative, $basePath.'/') && $relative !== $basePath) {
                    return $basePath.$relative;
                }

                return $relative;
            }
        }

        if ($basePath !== '' && str_starts_with($url, '/') && ! str_starts_with($url, $basePath.'/') && $url !== $basePath) {
            return $basePath.$url;
        }

        return $url;
    };

    $initialValue = collect(is_array($value) ? $value : [])
        ->map(fn ($file) => is_array($file) ? array_merge($file, [
            'url' => $normalizeUrl($file['url'] ?? null),
            'previewUrl' => $normalizeUrl($file['previewUrl'] ?? ($file['url'] ?? null)),
            'embedUrl' => $normalizeUrl($file['embedUrl'] ?? ($file['url'] ?? null)),
        ]) : null)
        ->filter()
        ->values()
        ->all();

    $resolvedContext = in_array($context, ['portal', 'admin'], true)
        ? $context
        : (request()->routeIs('portal.*') ? 'portal' : 'admin');
    $isPortal = $resolvedContext === 'portal';
    $pickerEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-media' : 'admin-files.picker-images', [], false));
    $pickerUploadEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-upload' : 'admin-files.picker-upload', [], false));
    $pickerUploadFromUrlEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-upload-from-url' : 'admin-files.picker-upload-from-url', [], false));
    $pickerCreateFolderEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-create-folder' : 'admin-files.picker-create-folder', [], false));
    $pickerDestroyEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-destroy' : 'admin-files.picker-destroy', ['fileKey' => '__FILE__'], false));
    $pickerSearchOnlineEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-search-online' : 'admin-files.picker-search-online', [], false));
    $pickerImportGoogleDriveEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-import-google-drive' : 'admin-files.picker-import-google-drive', [], false));
    $pickerImportAdobeExpressEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-import-adobe-express' : 'admin-files.picker-import-adobe-express', [], false));
    $fileManager = app(\Modules\AppFiles\Support\FileManager::class);
    $actionToggles = [
        'upload_from_url' => $fileManager->isActionEnabled('upload_from_url', auth()->user()),
        'google_drive' => $fileManager->isActionEnabled('google_drive', auth()->user()),
        'dropbox' => $fileManager->isActionEnabled('dropbox', auth()->user()),
        'onedrive' => $fileManager->isActionEnabled('onedrive', auth()->user()),
        'adobe_express' => $fileManager->isActionEnabled('adobe_express', auth()->user()),
    ];
    $searchMediaOnlineEnabled = ! $isPortal || $fileManager->searchMediaOnlineEnabled(auth()->user());
    $onlineMediaProviders = $searchMediaOnlineEnabled
        ? app(\Modules\AppFiles\Support\OnlineMediaSearchService::class)->providers($type)
        : [];
    $storageHealth = app(\App\Support\Storage\StorageDriverManager::class)->diskHealth();
    $googlePickerConfig = $fileManager->googlePickerConfig(auth()->user());
    $dropboxChooserConfig = $fileManager->dropboxChooserConfig(auth()->user());
    $oneDrivePickerConfig = $fileManager->oneDrivePickerConfig(auth()->user());
    $adobeExpressConfig = $fileManager->adobeExpressConfig(auth()->user());
    $wireModel = $attributes->get('wire:model.live') ?: $attributes->get('wire:model');
@endphp

@include('appfiles::partials.google-picker-script')
@include('appfiles::partials.dropbox-chooser-script')
@include('appfiles::partials.onedrive-picker-script')
@include('appfiles::partials.adobe-express-script')

<x-ui.field :label="$label" :help="$help" :error="$error" class="{{ $layout === 'library' ? 'h-full min-h-0' : '' }}">
    <div
        data-no-loading
        x-data="{
            files: [],
            folders: [],
            breadcrumbs: [],
            currentFolder: null,
            selected: @js($initialValue),
            search: '',
            sort: 'newest',
            endpoint: @js($pickerEndpoint),
            uploadEndpoint: @js($pickerUploadEndpoint),
            uploadFromUrlEndpoint: @js($pickerUploadFromUrlEndpoint),
            createFolderEndpoint: @js($pickerCreateFolderEndpoint),
            destroyEndpointTemplate: @js($pickerDestroyEndpoint),
            searchOnlineEndpoint: @js($searchMediaOnlineEnabled ? $pickerSearchOnlineEndpoint : null),
            importGoogleDriveEndpoint: @js($pickerImportGoogleDriveEndpoint),
            importAdobeExpressEndpoint: @js($pickerImportAdobeExpressEndpoint),
            actionToggles: @js($actionToggles),
            onlineMediaProviders: @js($onlineMediaProviders),
            storageHealth: @js($storageHealth ?? ['valid' => true, 'title' => null, 'message' => null]),
            googlePickerConfig: @js($googlePickerConfig),
            dropboxChooserConfig: @js($dropboxChooserConfig),
            oneDrivePickerConfig: @js($oneDrivePickerConfig),
            adobeExpressConfig: @js($adobeExpressConfig),
            oneDrivePickerCallbackUrl: @js(route('appfiles.onedrive-callback')),
            type: @js($type),
            multiple: @js((bool) $multiple),
            loading: false,
            uploading: false,
            importingUrl: false,
            creatingFolder: false,
            deletingItem: false,
            deleteConfirmOpen: false,
            pendingDeleteItem: null,
            editImageDialogOpen: false,
            editImageDialogUrl: '',
            editImageDialogName: '',
            remoteUrl: '',
            remoteImportSource: 'generic',
            showRemoteUrlForm: false,
            showNewFolderForm: false,
            showOnlineSearchForm: false,
            onlineKeyword: '',
            onlineSource: @js((string) array_key_first($onlineMediaProviders)),
            onlineResults: [],
            onlineSearching: false,
            newFolderName: '',
            compactToolbar: @js((bool) $compactToolbar),
            searchBarVisible: true,
            lastListScrollTop: 0,
            page: 1,
            hasMore: true,
            initialized: false,
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            wireModel: @js($wireModel),
            syncTimer: null,
            shellLoadingSuppressCount: 0,
            imageLoadStates: {},
            importDropdownOpen: false,
            init() {
                this.reload();

                this.$watch('search', () => {
                    clearTimeout(this.syncTimer);
                    this.syncTimer = setTimeout(() => this.reload(), 250);
                });

                this.$watch('sort', () => this.reload());

                window.addEventListener('media-browser:set-selected', (event) => {
                    if (event?.detail?.model !== this.wireModel) {
                        return;
                    }

                    this.selected = Array.isArray(event.detail.items) ? event.detail.items : [];
                });

                window.addEventListener('publishing-media-updated', (event) => {
                    if (event?.detail?.model !== this.wireModel) {
                        return;
                    }

                    const nextItem = event.detail?.item;
                    const nextItems = Array.isArray(event.detail?.items) ? event.detail.items : [];

                    this.selected = nextItems;

                    if (nextItem && typeof nextItem === 'object') {
                        const nextKey = String(nextItem.idSecure || nextItem.id || '');
                        const withoutDuplicate = this.files.filter((file) => String(file.idSecure || file.id || '') !== nextKey);
                        this.files = [nextItem, ...withoutDuplicate];
                    }
                });

                window.addEventListener('message', (event) => {
                    if (event.origin !== window.location.origin) {
                        return;
                    }

                    if (event?.data?.type !== 'appfiles:image-editor-saved') {
                        return;
                    }

                    this.closeEditImageDialog(false);
                    this.reload();
                    this.notify('success', @js(__('Edited image saved as a new file.')), @js(__('Edit image')));
                });
            },
            isRenderableImage(file) {
                if (!file) {
                    return false;
                }

                if (file.isImage === true) {
                    return true;
                }

                const mime = String(file.mimeType || '').toLowerCase();
                const category = String(file.category || '').toLowerCase();
                const extension = String(file.extension || '').toLowerCase();
                const preview = String(file.previewUrl || file.url || '').toLowerCase();

                return mime.startsWith('image/')
                    || category === 'image'
                    || ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'].includes(extension)
                    || /\.(jpg|jpeg|png|webp|gif|bmp|svg)(\?.*)?$/.test(preview);
            },
            isRenderableVideo(file) {
                if (!file) {
                    return false;
                }

                const mime = String(file.mimeType || '').toLowerCase();
                const category = String(file.category || '').toLowerCase();
                const extension = String(file.extension || '').toLowerCase();
                const preview = String(file.previewUrl || file.url || '').toLowerCase();

                return mime.startsWith('video/')
                    || category === 'video'
                    || ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'].includes(extension)
                    || /\.(mp4|mov|webm|m4v|avi|mkv)(\?.*)?$/.test(preview);
            },
            imageStateKey(file, scope = 'files') {
                const fileKey = String(file?.idSecure || file?.id || file?.previewUrl || file?.url || file?.name || '');

                return `${scope}:${fileKey}`;
            },
            isImageLoaded(file, scope = 'files') {
                if (!this.isRenderableImage(file)) {
                    return true;
                }

                return this.imageLoadStates[this.imageStateKey(file, scope)] === true;
            },
            isImageErrored(file, scope = 'files') {
                if (!this.isRenderableImage(file)) {
                    return false;
                }

                return this.imageLoadStates[this.imageStateKey(file, scope)] === 'error';
            },
            syncImageLoadFromElement(file, element, scope = 'files') {
                if (!this.isRenderableImage(file)) {
                    return;
                }

                const key = this.imageStateKey(file, scope);

                if (this.imageLoadStates[key] !== true) {
                    this.imageLoadStates[key] = false;
                }

                if (element?.complete && element?.naturalWidth > 0) {
                    this.imageLoadStates[key] = true;
                }
            },
            markImageLoaded(file, scope = 'files') {
                this.imageLoadStates[this.imageStateKey(file, scope)] = true;
            },
            markImageError(file, scope = 'files') {
                this.imageLoadStates[this.imageStateKey(file, scope)] = 'error';
            },
            hiddenPayload() {
                return JSON.stringify(this.selected.map((file) => ({
                    id: file.id,
                    idSecure: file.idSecure,
                    name: file.name,
                    url: file.url,
                    previewUrl: file.previewUrl,
                    embedUrl: file.embedUrl,
                    size: file.size,
                    category: file.category,
                    mimeType: file.mimeType,
                    extension: file.extension,
                    isImage: this.isRenderableImage(file),
                })));
            },
            syncValue() {
                const payload = JSON.parse(this.hiddenPayload());

                if (this.$refs.hiddenInput) {
                    this.$refs.hiddenInput.value = JSON.stringify(payload);
                    this.$refs.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    this.$refs.hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                window.dispatchEvent(new CustomEvent('media-browser:change', {
                    detail: {
                        model: this.wireModel,
                        items: payload,
                    },
                }));

                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, payload, false);
                }
            },
            beginShellLoadingSuppression() {
                this.shellLoadingSuppressCount += 1;
                window.__appShellSuppressLoading = true;
            },
            endShellLoadingSuppression() {
                this.shellLoadingSuppressCount = Math.max(0, this.shellLoadingSuppressCount - 1);

                if (this.shellLoadingSuppressCount === 0) {
                    window.__appShellSuppressLoading = false;
                }
            },
            remoteImportContext() {
                switch (this.remoteImportSource) {
                    case 'google_drive':
                        return {
                            label: @js(__('Google Drive share link')),
                            placeholder: 'https://drive.google.com/file/d/...',
                            helper: @js(__('Public or accessible Google Drive share links are supported.')),
                        };
                    case 'dropbox':
                        return {
                            label: @js(__('Dropbox share link')),
                            placeholder: 'https://www.dropbox.com/s/...',
                            helper: @js(__('Shared Dropbox links are converted to a direct download automatically.')),
                        };
                    case 'onedrive':
                        return {
                            label: @js(__('OneDrive share link')),
                            placeholder: 'https://1drv.ms/...',
                            helper: @js(__('Shared OneDrive links are converted to a direct download automatically.')),
                        };
                    default:
                        return {
                            label: @js(__('Remote file URL')),
                            placeholder: 'https://example.com/file.jpg',
                            helper: @js(__('Direct file URLs and supported share links from Google Drive, Dropbox, and OneDrive can be used here.')),
                        };
                }
            },
            openRemoteImport(source = 'generic') {
                if (!this.ensureStorageReady()) {
                    return;
                }

                this.remoteImportSource = source;
                this.showRemoteUrlForm = true;
                this.showOnlineSearchForm = false;
            },
            openOnlineSearch() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (!Object.keys(this.onlineMediaProviders || {}).length) {
                    this.notify('warning', @js(__('No online media provider is enabled.')), @js(__('Search Media Online')));
                    return;
                }

                this.showOnlineSearchForm = true;
                this.showRemoteUrlForm = false;
                this.showNewFolderForm = false;
            },
            async searchOnlineMedia() {
                if (!String(this.onlineKeyword || '').trim() || !this.searchOnlineEndpoint || !this.onlineSource) {
                    return;
                }

                this.onlineSearching = true;

                try {
                    const response = await fetch(this.searchOnlineEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            keyword: String(this.onlineKeyword || '').trim(),
                            source: this.onlineSource,
                            type: this.type,
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(this.responseErrorMessage(payload, 'Online media search failed.'));
                    }

                    this.onlineResults = Array.isArray(payload?.data) ? payload.data : [];
                } catch (error) {
                    this.onlineResults = [];
                    this.notify('error', error?.message || @js(__('Online media search failed.')), @js(__('Search Media Online')));
                } finally {
                    this.onlineSearching = false;
                }
            },
            async importOnlineMedia(media) {
                if (!media?.full) {
                    return;
                }

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    await this.importRemoteFileLink(media.full, 'Online media import failed.');
                    this.notify('success', @js(__('Imported media successfully.')), @js(__('Search Media Online')));
                } catch (error) {
                    this.notify('error', error?.message || @js(__('Online media import failed.')), @js(__('Search Media Online')));
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            notify(type, message, title = '') {
                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: { type, title, message },
                }));
            },
            ensureStorageReady() {
                if (this.storageHealth?.valid !== false) {
                    return true;
                }

                this.notify('warning', this.storageHealth?.message || @js(__('The selected storage disk is not ready for uploads.')), this.storageHealth?.title || @js(__('Storage needs attention')));

                return false;
            },
            debug(...args) {
                console.log('[MediaBrowser]', ...args);
            },
            responseErrorMessage(payload, fallback) {
                if (typeof payload?.message === 'string' && payload.message.trim() !== '' && payload.message !== 'The given data was invalid.') {
                    return payload.message;
                }

                const errors = payload?.errors || {};

                for (const key of Object.keys(errors)) {
                    const value = errors[key];

                    if (Array.isArray(value) && value[0]) {
                        return String(value[0]);
                    }
                }

                return fallback;
            },
            googlePickerMimeTypes() {
                if (this.type === 'image') {
                    return 'image/*';
                }

                if (this.type === 'video') {
                    return 'video/*';
                }

                return '';
            },
            async importFromGoogleDrivePicker() {
                if (!this.actionToggles?.google_drive) {
                    return;
                }

                if (!this.ensureStorageReady()) {
                    return;
                }

                if (!this.importGoogleDriveEndpoint) {
                    return;
                }

                if (!this.googlePickerConfig?.enabled) {
                    this.openRemoteImport('google_drive');
                    return;
                }

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    const result = await window.__appGooglePicker.pick({
                        clientId: this.googlePickerConfig.clientId,
                        apiKey: this.googlePickerConfig.apiKey,
                        multiple: this.multiple,
                        mimeTypes: this.googlePickerMimeTypes(),
                    });

                    const docs = Array.isArray(result?.docs) ? result.docs : [];
                    this.debug('googlePicker:selected', { result, docs, type: this.type, currentFolder: this.currentFolder });
                    const importedItems = [];

                    for (const doc of docs) {
                        if (!doc?.id) {
                            throw new Error(@js(__('The selected Google Drive file could not be read.')));
                        }

                        const response = await fetch(this.importGoogleDriveEndpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                file_id: String(doc.id),
                                access_token: String(result.accessToken || ''),
                                type: this.type,
                                folder: this.currentFolder?.idSecure || '',
                                name: String(doc.name || ''),
                                mime_type: String(doc.mimeType || ''),
                                resource_key: String(doc.resourceKey || ''),
                            }),
                        });

                        const payload = await response.json();
                        this.debug('googlePicker:importResponse', {
                            status: response.status,
                            ok: response.ok,
                            endpoint: this.importGoogleDriveEndpoint,
                            doc,
                            payload,
                        });

                        if (!response.ok) {
                            throw new Error(this.responseErrorMessage(payload, 'Google Drive import failed.'));
                        }

                        if (payload.data) {
                            importedItems.push(payload.data);
                        }
                    }

                    if (importedItems.length) {
                        this.files = [...importedItems, ...this.files];

                        importedItems.forEach((file) => {
                            if (!this.multiple && this.selected.length) {
                                return;
                            }

                            if (!this.isSelected(file)) {
                                this.selected = this.multiple ? [...this.selected, file] : [file];
                            }
                        });

                        this.syncValue();
                    }
                } catch (error) {
                    this.debug('googlePicker:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Google Drive import failed.')), @js(__('Google Drive')));
                    }
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            async importRemoteFileLink(link, fallbackMessage) {
                const response = await fetch(this.uploadFromUrlEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        remote_url: String(link || ''),
                        type: this.type,
                        folder: this.currentFolder?.idSecure || '',
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(this.responseErrorMessage(payload, fallbackMessage));
                }

                if (payload?.data) {
                    this.files = [payload.data, ...this.files];

                    if (!this.multiple) {
                        this.selected = [payload.data];
                    } else if (!this.isSelected(payload.data)) {
                        this.selected = [...this.selected, payload.data];
                    }

                    this.syncValue();
                }
            },
            async importFromDropboxChooser() {
                if (!this.actionToggles?.dropbox) {
                    return;
                }

                if (!this.dropboxChooserConfig?.enabled) {
                    this.openRemoteImport('dropbox');
                    return;
                }

                if (!window.__appDropboxChooser?.pick) {
                    this.notify('error', @js(__('Dropbox Chooser is unavailable.')), @js(__('Dropbox')));
                    return;
                }

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    const result = await window.__appDropboxChooser.pick({
                        appKey: this.dropboxChooserConfig.appKey,
                        multiple: this.multiple,
                        extensions: [],
                    });

                    const files = Array.isArray(result?.files) ? result.files : [];

                    for (const file of files) {
                        if (!file?.link) {
                            throw new Error(@js(__('The selected Dropbox file could not be read.')));
                        }

                        await this.importRemoteFileLink(file.link, 'Dropbox import failed.');
                    }

                    if (files.length) {
                        this.notify('success', @js(__('Imported file from Dropbox successfully.')), @js(__('Dropbox')));
                    }
                } catch (error) {
                    this.debug('dropboxChooser:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Dropbox import failed.')), @js(__('Dropbox')));
                    }
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            async importFromOneDrivePicker() {
                if (!this.actionToggles?.onedrive) {
                    return;
                }

                if (!this.oneDrivePickerConfig?.enabled) {
                    this.openRemoteImport('onedrive');
                    return;
                }

                if (!window.__appOneDrivePicker?.pick) {
                    this.notify('error', @js(__('OneDrive Picker is unavailable.')), @js(__('OneDrive')));
                    return;
                }

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    const result = await window.__appOneDrivePicker.pick({
                        clientId: this.oneDrivePickerConfig.clientId,
                        redirectUri: this.oneDrivePickerCallbackUrl,
                        multiple: this.multiple,
                        extensions: [],
                    });

                    const files = Array.isArray(result?.files) ? result.files : [];

                    for (const file of files) {
                        if (!file?.link) {
                            throw new Error(@js(__('The selected OneDrive file could not be read.')));
                        }

                        await this.importRemoteFileLink(file.link, 'OneDrive import failed.');
                    }

                    if (files.length) {
                        this.notify('success', @js(__('Imported file from OneDrive successfully.')), @js(__('OneDrive')));
                    }
                } catch (error) {
                    this.debug('oneDrivePicker:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('OneDrive import failed.')), @js(__('OneDrive')));
                    }
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            async importFromAdobeExpress() {
                if (!this.adobeExpressConfig?.enabled) {
                    this.notify('error', @js(__('Adobe Express is not configured.')), @js(__('Adobe Express')));
                    return;
                }

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    const adobeExpress = window.__appAdobeExpress;

                    if (!adobeExpress || typeof adobeExpress.createImage !== 'function') {
                        throw new Error('Adobe Express launcher is unavailable.');
                    }

                    const result = await adobeExpress.createImage({
                        clientId: this.adobeExpressConfig.clientId,
                        appName: this.adobeExpressConfig.appName,
                    });

                    const response = await fetch(this.importAdobeExpressEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            data_url: String(result?.dataUrl || ''),
                            name: String(result?.name || ''),
                            folder: this.currentFolder?.idSecure || '',
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || !payload?.data) {
                        throw new Error(payload?.message || 'Adobe Express import failed.');
                    }

                    this.upsertFile(payload.data);
                    this.choose(payload.data);
                    this.notify('success', @js(__('Imported image from Adobe Express successfully.')), @js(__('Adobe Express')));
                } catch (error) {
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Adobe Express import failed.')), @js(__('Adobe Express')));
                    }
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            async reload(folder = null) {
                if (folder !== null) {
                    this.currentFolder = folder;
                }

                this.files = [];
                this.folders = [];
                this.page = 1;
                this.hasMore = true;
                await this.loadMore(true);
            },
            async loadMore(reset = false) {
                if (this.loading || (!this.hasMore && !reset) || !this.endpoint) return;

                this.loading = true;
                this.beginShellLoadingSuppression();

                try {
                    const url = new URL(this.endpoint, window.location.origin);
                    url.searchParams.set('page', String(this.page));
                    url.searchParams.set('sort', this.sort);
                    url.searchParams.set('type', this.type);

                    if (this.search.trim() !== '') {
                        url.searchParams.set('q', this.search.trim());
                    }

                    if (this.currentFolder?.idSecure) {
                        url.searchParams.set('folder', this.currentFolder.idSecure);
                    }

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load media.');
                    }

                    const payload = await response.json();
                    this.folders = Array.isArray(payload.folders) ? payload.folders : [];
                    this.files = reset ? (payload.data || []) : [...this.files, ...(payload.data || [])];
                    this.breadcrumbs = Array.isArray(payload.breadcrumbs) ? payload.breadcrumbs : [];
                    this.currentFolder = payload.current_folder || null;
                    this.hasMore = !!payload.has_more;
                    this.page = payload.next_page || (payload.has_more ? this.page + 1 : this.page);
                } catch (error) {
                    this.hasMore = false;
                } finally {
                    this.loading = false;
                    this.endShellLoadingSuppression();
                }
            },
            isSelected(file) {
                return this.selected.some((item) => String(item.id) === String(file.id));
            },
            choose(file) {
                if (this.isSelected(file)) {
                    this.remove(file.id);
                    return;
                }

                if (this.multiple) {
                    this.selected = [...this.selected, file];
                } else {
                    this.selected = [file];
                }

                this.syncValue();
            },
            dragPayload(file) {
                return JSON.stringify({
                    id: file.id,
                    idSecure: file.idSecure,
                    name: file.name,
                    url: file.url,
                    previewUrl: file.previewUrl,
                    embedUrl: file.embedUrl,
                    size: file.size,
                    category: file.category,
                    mimeType: file.mimeType,
                    extension: file.extension,
                    isImage: this.isRenderableImage(file),
                });
            },
            handleDragStart(file, event) {
                if (!event?.dataTransfer) {
                    return;
                }

                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('application/json', this.dragPayload(file));
                event.dataTransfer.setData('text/plain', this.dragPayload(file));
            },
            remove(id) {
                this.selected = this.selected.filter((item) => String(item.id) !== String(id));
                this.syncValue();
            },
            openFolder(folder) {
                this.reload(folder);
            },
            goRoot() {
                this.currentFolder = null;
                this.breadcrumbs = [];
                this.reload();
            },
            async uploadSelectedFiles(event) {
                if (!this.ensureStorageReady()) {
                    event.target.value = '';
                    return;
                }

                const files = Array.from(event.target.files || []);
                if (!files.length || !this.uploadEndpoint) return;

                const formData = new FormData();
                files.forEach((file) => formData.append('files[]', file));
                formData.append('type', this.type);

                if (this.currentFolder?.idSecure) {
                    formData.append('folder', this.currentFolder.idSecure);
                }

                this.uploading = true;
                this.beginShellLoadingSuppression();

                try {
                    const response = await fetch(this.uploadEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Upload failed.');
                    }

                    const items = Array.isArray(payload.data) ? payload.data : [];
                    this.files = [...items, ...this.files];

                    items.forEach((file) => {
                        if (!this.multiple && this.selected.length) {
                            return;
                        }

                        if (!this.isSelected(file)) {
                            this.selected = this.multiple ? [...this.selected, file] : [file];
                        }
                    });

                    this.syncValue();
                } catch (error) {
                } finally {
                    this.uploading = false;
                    this.endShellLoadingSuppression();
                    event.target.value = '';
                }
            },
            async importFromUrl() {
                if (!this.remoteUrl.trim() || !this.uploadFromUrlEndpoint) return;

                this.importingUrl = true;
                this.beginShellLoadingSuppression();

                try {
                    const response = await fetch(this.uploadFromUrlEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            remote_url: this.remoteUrl.trim(),
                            type: this.type,
                            folder: this.currentFolder?.idSecure || '',
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload?.data) {
                        throw new Error(payload?.message || 'Import failed.');
                    }

                    this.files = [payload.data, ...this.files];
                    if (!this.isSelected(payload.data)) {
                        this.selected = this.multiple ? [...this.selected, payload.data] : [payload.data];
                    }

                    this.remoteUrl = '';
                    this.remoteImportSource = 'generic';
                    this.showRemoteUrlForm = false;
                    this.syncValue();
                } catch (error) {
                } finally {
                    this.importingUrl = false;
                    this.endShellLoadingSuppression();
                }
            },
            async createFolder() {
                if (!this.newFolderName.trim() || !this.createFolderEndpoint) return;

                this.creatingFolder = true;
                this.beginShellLoadingSuppression();

                try {
                    const response = await fetch(this.createFolderEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            name: this.newFolderName.trim(),
                            folder: this.currentFolder?.idSecure || '',
                        }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || !payload?.data) {
                        throw new Error(this.responseErrorMessage(payload, 'Folder creation failed.'));
                    }

                    this.folders = [payload.data, ...this.folders];
                    this.newFolderName = '';
                    this.showNewFolderForm = false;
                    this.notify('success', @js(__('Folder created successfully.')), @js(__('Folder')));
                } catch (error) {
                    this.notify('error', error?.message || @js(__('Folder creation failed.')), @js(__('Folder')));
                } finally {
                    this.creatingFolder = false;
                    this.endShellLoadingSuppression();
                }
            },
            destroyEndpoint(file) {
                return String(this.destroyEndpointTemplate || '').replace('__FILE__', String(file?.id || ''));
            },
            async deleteItem(file) {
                if (!file?.id) return;

                this.deletingItem = true;
                this.beginShellLoadingSuppression();

                try {
                    const response = await fetch(this.destroyEndpoint(file), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(this.responseErrorMessage(payload, 'Delete failed.'));
                    }

                    const nextKey = String(file.idSecure || file.id || '');
                    this.files = this.files.filter((item) => String(item.idSecure || item.id || '') !== nextKey);
                    this.folders = this.folders.filter((item) => String(item.idSecure || item.id || '') !== nextKey);
                    this.selected = this.selected.filter((item) => String(item.idSecure || item.id || '') !== nextKey);
                    this.syncValue();
                    this.notify('success', @js(__('Item deleted successfully.')), @js(__('Delete')));
                } catch (error) {
                    this.notify('error', error?.message || @js(__('Delete failed.')), @js(__('Delete')));
                } finally {
                    this.deletingItem = false;
                    this.endShellLoadingSuppression();
                }
            },
            promptDeleteItem(file) {
                this.pendingDeleteItem = file || null;
                this.deleteConfirmOpen = Boolean(file?.id);
            },
            async confirmDeleteItem() {
                if (!this.pendingDeleteItem?.id) {
                    this.deleteConfirmOpen = false;
                    return;
                }

                const target = this.pendingDeleteItem;
                this.deleteConfirmOpen = false;
                this.pendingDeleteItem = null;
                await this.deleteItem(target);
            },
            openEditImage(file) {
                if (!file?.editImageUrl) {
                    return;
                }

                this.editImageDialogUrl = String(file.editImageUrl);
                this.editImageDialogName = String(file?.name || '');
                this.editImageDialogOpen = true;
            },
            closeEditImageDialog(shouldReload = true) {
                this.editImageDialogOpen = false;

                setTimeout(() => {
                    this.editImageDialogUrl = '';
                    this.editImageDialogName = '';

                    if (shouldReload) {
                        this.reload();
                    }
                }, 120);
            },
            handleListScroll(target) {
                if (this.loading || !this.hasMore) return;
                if ((target.scrollTop + target.clientHeight) >= (target.scrollHeight - 180)) {
                    this.loadMore();
                }
            },
            handleLibraryScroll(target) {
                if (this.compactToolbar) {
                    const currentTop = target.scrollTop;

                    if (currentTop <= 12) {
                        this.searchBarVisible = true;
                    } else if (currentTop > this.lastListScrollTop + 6) {
                        this.searchBarVisible = false;
                    } else if (currentTop < this.lastListScrollTop - 6) {
                        this.searchBarVisible = true;
                    }

                    this.lastListScrollTop = currentTop;
                }

                this.handleListScroll(target);
            },
        }"
        class="{{ $frameless ? 'h-full min-h-0' : 'h-full min-h-0 overflow-hidden rounded-[1.2rem] border' }}"
        style="{{ $frameless ? '' : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);' }}"
    >
        @if ($name)
            <input x-ref="hiddenInput" type="hidden" name="{{ $name }}">
        @else
            <input x-ref="hiddenInput" type="hidden">
        @endif

        @if ($layout === 'library')
            <div class="flex h-full min-h-0 flex-col">
                @if ($showLibraryHeader)
                    <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $libraryTitle ?: __('Media Library') }}</p>
                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Browse folders and choose assets from your file workspace.') }}</p>
                    </div>
                @endif

                <div class="space-y-3 {{ $showLibraryHeader || ! $frameless ? 'border-b' : '' }} {{ $compactToolbar ? 'px-3 py-3' : 'px-4 py-4' }}" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); {{ $frameless ? '' : 'background-color: rgba(var(--theme-border-color-rgb), 0.03);' }}">
                    @if (!($storageHealth['valid'] ?? true))
                        <x-ui.alert
                            inline
                            variant="warning"
                            :title="$storageHealth['title'] ?? __('Storage needs attention')"
                            :description="$storageHealth['message'] ?? __('The selected storage disk is not ready for uploads.')"
                        />
                    @endif

                    @if ($compactToolbar)
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[1.05rem] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $libraryTitle ?: __('Media') }}</p>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border transition hover:bg-slate-900/5"
                                    style="color: var(--theme-header-text-color); border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.05);"
                                    x-on:click="if (ensureStorageReady()) $refs.fileInput.click()"
                                    title="{{ __('Upload') }}"
                                >
                                    <i class="fa-light fa-arrow-up-from-bracket"></i>
                                </button>
                                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                                    <button
                                        type="button"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border transition hover:bg-slate-900/5"
                                        style="color: var(--theme-header-text-color); border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.05);"
                                        x-on:click="open = !open"
                                        title="{{ __('Cloud import') }}"
                                    >
                                        <i class="fa-light fa-grid-2"></i>
                                    </button>

                                    <div
                                        x-cloak
                                        x-show="open"
                                        x-transition.origin.top.right
                                        class="absolute right-0 top-11 z-30 w-56 rounded-[1rem] border p-2 shadow-xl"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);"
                                    >
                                        @if ($actionToggles['google_drive'])
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; importFromGoogleDrivePicker()">
                                                <i class="fa-brands fa-google-drive w-4 text-center"></i>
                                                <span>{{ __('Google Drive') }}</span>
                                            </button>
                                        @endif
                                        @if ($actionToggles['dropbox'])
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; importFromDropboxChooser()">
                                                <i class="fa-brands fa-dropbox w-4 text-center"></i>
                                                <span>{{ __('Dropbox') }}</span>
                                            </button>
                                        @endif
                                        @if ($actionToggles['onedrive'])
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; importFromOneDrivePicker()">
                                                <i class="fa-light fa-cloud w-4 text-center"></i>
                                                <span>{{ __('OneDrive') }}</span>
                                            </button>
                                        @endif
                                        @if ($actionToggles['adobe_express'])
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; importFromAdobeExpress()">
                                                <i class="fa-light fa-wand-magic-sparkles w-4 text-center"></i>
                                                <span>{{ __('Create for Adobe Express') }}</span>
                                            </button>
                                        @endif
                                        @if ($actionToggles['upload_from_url'])
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; openRemoteImport('generic')">
                                                <i class="fa-light fa-link w-4 text-center"></i>
                                                <span>{{ __('Upload From URL') }}</span>
                                            </button>
                                        @endif
                                        @if ($searchMediaOnlineEnabled)
                                            <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; openOnlineSearch()">
                                                <i class="fa-light fa-photo-film-music w-4 text-center"></i>
                                                <span>{{ __('Search Media Online') }}</span>
                                            </button>
                                        @endif
                                        <button type="button" class="flex w-full items-center gap-3 rounded-[0.8rem] px-3 py-2 text-left text-sm transition hover:bg-slate-900/5" x-on:click="open = false; showNewFolderForm = true; showRemoteUrlForm = false; showOnlineSearchForm = false">
                                            <i class="fa-light fa-folder-plus w-4 text-center"></i>
                                            <span>{{ __('New folder') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex gap-2">
                            <x-ui.button type="button" variant="outline" size="sm" x-on:click="if (ensureStorageReady()) $refs.fileInput.click()">
                                <i class="fa-light fa-upload"></i>
                                {{ __('Upload') }}
                            </x-ui.button>
                            <x-ui.dropdown-menu align="right" width="auto" panel-class="min-w-[14rem]">
                                <x-slot:trigger>
                                    <x-ui.button type="button" variant="outline" size="sm">
                                        <i class="fa-light fa-grid-2"></i>
                                        {{ __('Import') }}
                                    </x-ui.button>
                                </x-slot:trigger>

                                @if ($actionToggles['google_drive'])
                                    <x-ui.dropdown-menu-item icon="fa-brands fa-google-drive" class="w-full" x-on:click="importFromGoogleDrivePicker()">
                                        {{ __('Google Drive') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                @if ($actionToggles['dropbox'])
                                    <x-ui.dropdown-menu-item icon="fa-brands fa-dropbox" class="w-full" x-on:click="importFromDropboxChooser()">
                                        {{ __('Dropbox') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                @if ($actionToggles['onedrive'])
                                    <x-ui.dropdown-menu-item icon="fa-light fa-cloud" class="w-full" x-on:click="importFromOneDrivePicker()">
                                        {{ __('OneDrive') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                @if ($actionToggles['upload_from_url'])
                                    <x-ui.dropdown-menu-item icon="fa-light fa-link" class="w-full" x-on:click="openRemoteImport('generic')">
                                        {{ __('Upload From URL') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                @if ($searchMediaOnlineEnabled)
                                    <x-ui.dropdown-menu-item icon="fa-light fa-photo-film-music" class="w-full" x-on:click="openOnlineSearch()">
                                        {{ __('Search Media Online') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                @if ($actionToggles['adobe_express'])
                                    <x-ui.dropdown-menu-item icon="fa-light fa-wand-magic-sparkles" class="w-full" x-on:click="importFromAdobeExpress()">
                                        {{ __('Create for Adobe Express') }}
                                    </x-ui.dropdown-menu-item>
                                @endif
                                <x-ui.dropdown-menu-item icon="fa-light fa-folder-plus" class="w-full" x-on:click="showNewFolderForm = true; showRemoteUrlForm = false; showOnlineSearchForm = false">
                                    {{ __('New folder') }}
                                </x-ui.dropdown-menu-item>
                            </x-ui.dropdown-menu>
                        </div>
                    @endif

                    <input x-ref="fileInput" type="file" class="hidden" multiple x-on:change="uploadSelectedFiles($event)" x-bind:accept="type === 'video' ? 'video/*' : (type === 'image' ? 'image/*' : 'image/*,video/*')">

                    <template x-if="showRemoteUrlForm">
                        <div class="space-y-2 rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94);">
                            <input x-model="remoteUrl" type="url" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" x-bind:placeholder="remoteImportContext().placeholder">
                            <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);" x-text="remoteImportContext().helper"></p>
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" x-on:click="showRemoteUrlForm = false; remoteUrl = ''">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" x-on:click="importFromUrl()" x-bind:disabled="importingUrl">
                                    <span x-show="!importingUrl">{{ __('Import') }}</span>
                                    <span x-show="importingUrl">{{ __('Importing...') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </template>

                    <template x-if="showOnlineSearchForm">
                        <div class="space-y-3 rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94);">
                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                                <input x-model="onlineKeyword" x-on:keydown.enter.prevent="searchOnlineMedia()" type="text" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Enter keyword') }}">
                                <select x-model="onlineSource" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
                                    <template x-for="[providerKey, providerLabel] in Object.entries(onlineMediaProviders || {})" :key="providerKey">
                                        <option :value="providerKey" x-text="providerLabel"></option>
                                    </template>
                                </select>
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="button" variant="outline" size="sm" x-on:click="showOnlineSearchForm = false; onlineResults = []">{{ __('Close') }}</x-ui.button>
                                    <x-ui.button type="button" size="sm" x-on:click="searchOnlineMedia()" x-bind:disabled="onlineSearching">
                                        <span x-show="!onlineSearching">{{ __('Search') }}</span>
                                        <span x-show="onlineSearching">{{ __('Searching...') }}</span>
                                    </x-ui.button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" x-show="onlineResults.length">
                                <template x-for="media in onlineResults" :key="`${media.provider}-${media.id}`">
                                    <div class="overflow-hidden rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);">
                                        <div class="aspect-square overflow-hidden" style="background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                            <img x-bind:src="media.thumbnail || media.full" x-bind:alt="media.author || media.source || 'media'" class="h-full w-full object-cover">
                                        </div>
                                        <div class="space-y-2 px-3 py-3">
                                            <p class="truncate text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);" x-text="media.source"></p>
                                            <p class="truncate text-sm" style="color: var(--theme-header-text-color);" x-text="media.author || media.full"></p>
                                            <div class="flex items-center justify-between gap-2">
                                                <a x-bind:href="media.link || media.full" target="_blank" rel="noopener noreferrer" class="text-xs font-medium" style="color: var(--theme-accent);">{{ __('Preview') }}</a>
                                                <x-ui.button type="button" size="sm" x-on:click="importOnlineMedia(media)" x-bind:disabled="importingUrl">
                                                    {{ __('Import') }}
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);" x-show="showOnlineSearchForm && !onlineSearching && !onlineResults.length">{{ __('Search results will appear here.') }}</p>
                        </div>
                    </template>

                    <template x-if="showNewFolderForm">
                        <div class="space-y-2 rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94);">
                            <input x-model="newFolderName" type="text" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Folder name') }}">
                            <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);">{{ __('Create a new folder in the current location.') }}</p>
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" x-on:click="showNewFolderForm = false; newFolderName = ''">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" x-on:click="createFolder()" x-bind:disabled="creatingFolder">
                                    <span x-show="!creatingFolder">{{ __('Create Folder') }}</span>
                                    <span x-show="creatingFolder">{{ __('Creating...') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </template>

                    @if ($compactToolbar)
                        <div
                            x-cloak
                            x-show="searchBarVisible"
                            x-transition:enter="transition ease-out duration-180"
                            x-transition:enter-start="-translate-y-2 opacity-0"
                            x-transition:enter-end="translate-y-0 opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="translate-y-0 opacity-100"
                            x-transition:leave-end="-translate-y-2 opacity-0"
                            class="space-y-2 overflow-hidden"
                        >
                            <input x-model="search" type="text" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Search files...') }}">
                        </div>
                    @else
                        <div class="space-y-2">
                            <input x-model="search" type="text" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Search files...') }}">
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 text-xs">
                        <button type="button" class="rounded-full border px-2.5 py-1 font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);" x-on:click="goRoot()">{{ __('Root') }}</button>
                        <template x-for="crumb in breadcrumbs" :key="'crumb-'+crumb.idSecure">
                            <button type="button" class="rounded-full border px-2.5 py-1 font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click="openFolder(crumb)" x-text="crumb.name"></button>
                        </template>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto {{ $compactToolbar ? 'px-3 py-3' : 'px-4 py-4' }}" x-on:scroll.passive="handleLibraryScroll($event.target)">
                    <template x-if="folders.length">
                        <div class="{{ $compactToolbar ? 'mb-3' : 'mb-4' }}">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Folders') }}</p>
                            <div class="{{ $compactToolbar ? 'grid grid-cols-3 gap-2.5' : 'space-y-3' }}">
                                <template x-for="folder in folders" :key="'folder-'+folder.idSecure">
                                    <div class="group relative {{ $compactToolbar ? 'rounded-[1rem] border p-2.5 transition hover:-translate-y-0.5' : 'rounded-[1rem] border px-3 py-3 transition hover:-translate-y-0.5' }}" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);">
                                        <div class="absolute right-2 top-2 z-10 opacity-0 transition group-hover:opacity-100" x-data="{ open: false }" x-on:click.outside="open = false">
                                            <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full border bg-white/92 shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click.prevent.stop="open = !open">
                                                <i class="fa-light fa-ellipsis text-xs"></i>
                                            </button>
                                            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-8 w-28 rounded-[0.8rem] border p-1 shadow-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);">
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[13px] transition hover:bg-slate-900/5" x-on:click.prevent.stop="open = false; promptDeleteItem(folder)">
                                                    <i class="fa-light fa-trash w-4 text-center" style="color: rgb(225,29,72);"></i>
                                                    <span>{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button type="button" class="{{ $compactToolbar ? 'flex w-full flex-col items-start gap-2 text-left' : 'flex w-full items-center gap-3 text-left' }}" x-on:click="openFolder(folder)">
                                            <span class="inline-flex {{ $compactToolbar ? 'h-10 w-10' : 'h-11 w-11' }} items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                                <i class="fa-light fa-folder-open text-lg"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="folder.name"></span>
                                                <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);">{{ __('Open folder') }}</span>
                                            </span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Files') }}</p>
                        <template x-if="files.length">
                            <div class="grid {{ $compactToolbar ? 'grid-cols-3 gap-2.5' : 'gap-3 sm:grid-cols-2' }}">
                                <template x-for="file in files" :key="'file-'+file.id">
                                    <div
                                        class="group relative rounded-[1rem] border text-left transition hover:-translate-y-0.5"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);"
                                    >
                                        <div class="absolute right-2 top-2 z-10 opacity-0 transition group-hover:opacity-100" x-data="{ open: false }" x-on:click.outside="open = false">
                                            <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full border bg-white/92 shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click.prevent.stop="open = !open">
                                                <i class="fa-light fa-ellipsis text-xs"></i>
                                            </button>
                                            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-8 w-[6.9rem] rounded-[0.8rem] border p-1 shadow-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);">
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[11.5px] transition hover:bg-slate-900/5" x-show="file.isEditableImage && file.editImageUrl" x-on:click.prevent.stop="open = false; openEditImage(file)">
                                                    <i class="fa-light fa-pen-to-square w-4 text-center"></i>
                                                    <span>{{ __('Edit image') }}</span>
                                                </button>
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[11.5px] transition hover:bg-slate-900/5" x-on:click.prevent.stop="open = false; promptDeleteItem(file)">
                                                    <i class="fa-light fa-trash w-4 text-center" style="color: rgb(225,29,72);"></i>
                                                    <span>{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            draggable="true"
                                            class="block w-full text-left"
                                            x-on:click="choose(file)"
                                            x-on:dragstart="handleDragStart(file, $event)"
                                        >
                                        <div class="relative aspect-square overflow-hidden rounded-t-[1rem]" style="background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                            <template x-if="isRenderableImage(file)">
                                                <div class="relative h-full w-full overflow-hidden rounded-t-[1rem]">
                                                    <div
                                                        x-show="!isImageLoaded(file, 'library-grid') && !isImageErrored(file, 'library-grid')"
                                                        x-transition.opacity.duration.150ms
                                                        class="absolute inset-0 flex items-center justify-center rounded-t-[1rem]"
                                                        style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                    >
                                                        <i class="fa-light fa-loader animate-spin text-lg"></i>
                                                    </div>
                                                    <div
                                                        x-show="isImageErrored(file, 'library-grid')"
                                                        x-transition.opacity.duration.150ms
                                                        class="absolute inset-0 flex items-center justify-center rounded-t-[1rem]"
                                                        style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                    >
                                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                                            <i class="fa-light fa-image text-lg"></i>
                                                        </span>
                                                    </div>
                                                    <img
                                                        x-bind:src="file.previewUrl || file.url"
                                                        x-bind:alt="file.name"
                                                        x-init="syncImageLoadFromElement(file, $el, 'library-grid')"
                                                        x-on:load="markImageLoaded(file, 'library-grid')"
                                                        x-on:error="markImageError(file, 'library-grid')"
                                                        x-bind:class="isImageLoaded(file, 'library-grid') ? 'opacity-100' : 'opacity-0'"
                                                        loading="lazy"
                                                        decoding="async"
                                                        class="h-full w-full rounded-t-[1rem] object-cover transition duration-300 group-hover:scale-[1.03]"
                                                    >
                                                </div>
                                            </template>
                                            <template x-if="!isRenderableImage(file) && isRenderableVideo(file)">
                                                <video x-bind:src="file.previewUrl || file.url" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>
                                            </template>
                                            <template x-if="!isRenderableImage(file) && !isRenderableVideo(file)">
                                                <div class="flex h-full w-full items-center justify-center">
                                                    <i class="fa-light fa-circle-play text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                                </div>
                                            </template>

                                            <span x-show="isSelected(file)" class="absolute left-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-white shadow-lg" style="background-color: var(--theme-accent);">
                                                <i class="fa-light fa-check text-xs"></i>
                                            </span>
                                        </div>
                                        <div class="space-y-1 {{ $compactToolbar ? 'px-2.5 py-2.5' : 'px-3 py-3' }}">
                                            <p class="truncate {{ $compactToolbar ? 'text-[11px]' : 'text-sm' }} font-semibold leading-4" style="color: var(--theme-header-text-color);" x-text="file.name"></p>
                                            <p class="{{ $compactToolbar ? 'text-[8px]' : 'text-xs' }} leading-3.5" style="color: var(--theme-muted-text-color);" x-text="`${file.size || ''} | ${(file.category || '').toUpperCase()}`"></p>
                                        </div>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!files.length && !loading">
                            <div class="rounded-[1rem] border border-dashed px-4 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                                <i class="fa-light fa-photo-film text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                <p class="mt-3 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No media files found in this folder.') }}</p>
                            </div>
                        </template>

                        <template x-if="loading && !files.length">
                            <div class="rounded-[1rem] border border-dashed px-4 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color);">
                                <i class="fa-light fa-loader animate-spin text-3xl"></i>
                                <p class="mt-3 text-sm">{{ __('Loading media...') }}</p>
                            </div>
                        </template>

                        <template x-if="loading && files.length">
                            <div class="flex items-center justify-center py-4">
                                <div class="inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent); color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-loader animate-spin"></i>
                                    <span>{{ __('Loading...') }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @else
        <div class="grid xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="border-r" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $libraryTitle ?: __('Media Library') }}</p>
                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Browse folders and choose assets from your file workspace.') }}</p>
                </div>

                <div class="space-y-3 px-4 py-4">
                    <div class="flex gap-2">
                        <x-ui.button type="button" variant="outline" size="sm" x-on:click="if (ensureStorageReady()) $refs.fileInput.click()">
                            <i class="fa-light fa-upload"></i>
                            {{ __('Upload') }}
                        </x-ui.button>
                        <x-ui.button type="button" variant="outline" size="sm" x-on:click="openRemoteImport('generic')">
                            <i class="fa-light fa-link"></i>
                            {{ __('Upload URL') }}
                        </x-ui.button>
                    </div>

                    <input x-ref="fileInput" type="file" class="hidden" multiple x-on:change="uploadSelectedFiles($event)" x-bind:accept="type === 'video' ? 'video/*' : (type === 'image' ? 'image/*' : 'image/*,video/*')">

                    <template x-if="showRemoteUrlForm">
                        <div class="space-y-2 rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94);">
                            <input x-model="remoteUrl" type="url" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" x-bind:placeholder="remoteImportContext().placeholder">
                            <p class="text-xs leading-5" style="color: var(--theme-muted-text-color);" x-text="remoteImportContext().helper"></p>
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="outline" size="sm" x-on:click="showRemoteUrlForm = false; remoteUrl = ''">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" size="sm" x-on:click="importFromUrl()" x-bind:disabled="importingUrl">
                                    <span x-show="!importingUrl">{{ __('Import') }}</span>
                                    <span x-show="importingUrl">{{ __('Importing...') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </template>

                    <div class="space-y-2">
                        <input x-model="search" type="text" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);" placeholder="{{ __('Search files...') }}">
                        <select x-model="sort" class="flex h-10 w-full rounded-[0.75rem] border px-3 text-sm" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
                            <option value="newest">{{ __('Newest first') }}</option>
                            <option value="oldest">{{ __('Oldest first') }}</option>
                            <option value="name_asc">{{ __('Name A-Z') }}</option>
                            <option value="name_desc">{{ __('Name Z-A') }}</option>
                        </select>
                    </div>
                </div>

                <div class="border-t px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex flex-wrap gap-2 text-xs">
                        <button type="button" class="rounded-full border px-2.5 py-1 font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);" x-on:click="goRoot()">{{ __('Root') }}</button>
                        <template x-for="crumb in breadcrumbs" :key="'crumb-'+crumb.idSecure">
                            <button type="button" class="rounded-full border px-2.5 py-1 font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click="openFolder(crumb)" x-text="crumb.name"></button>
                        </template>
                    </div>
                </div>
            </aside>

            <section class="min-w-0">
                <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $selectionTitle ?: __('Selected Media') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Selected assets will be available to the composer or any module using this browser.') }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);" x-text="`${selected.length} {{ __('selected') }}`"></span>
                    </div>

                    <div class="mt-4 min-h-[5.5rem] rounded-[1rem] border border-dashed px-3 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                        <template x-if="selected.length">
                            <div class="flex flex-wrap gap-3">
                                <template x-for="file in selected" :key="'selected-media-'+file.id">
                                    <div class="relative h-20 w-20 overflow-hidden rounded-[0.95rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                        <template x-if="isRenderableImage(file)">
                                            <div class="relative h-full w-full">
                                                <div
                                                    x-show="!isImageLoaded(file, 'selected-grid') && !isImageErrored(file, 'selected-grid')"
                                                    x-transition.opacity.duration.150ms
                                                    class="absolute inset-0 flex items-center justify-center"
                                                    style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                >
                                                    <i class="fa-light fa-loader animate-spin text-sm"></i>
                                                </div>
                                                <div
                                                    x-show="isImageErrored(file, 'selected-grid')"
                                                    x-transition.opacity.duration.150ms
                                                    class="absolute inset-0 flex items-center justify-center"
                                                    style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                >
                                                    <i class="fa-light fa-image text-base" style="color: var(--theme-accent);"></i>
                                                </div>
                                                <img
                                                    x-bind:src="file.previewUrl || file.url"
                                                    x-bind:alt="file.name"
                                                    x-init="syncImageLoadFromElement(file, $el, 'selected-grid')"
                                                    x-on:load="markImageLoaded(file, 'selected-grid')"
                                                    x-on:error="markImageError(file, 'selected-grid')"
                                                    x-bind:class="isImageLoaded(file, 'selected-grid') ? 'opacity-100' : 'opacity-0'"
                                                    loading="lazy"
                                                    decoding="async"
                                                    class="h-full w-full object-cover"
                                                >
                                            </div>
                                        </template>
                                        <template x-if="!isRenderableImage(file) && isRenderableVideo(file)">
                                            <video x-bind:src="file.previewUrl || file.url" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>
                                        </template>
                                        <template x-if="!isRenderableImage(file) && !isRenderableVideo(file)">
                                            <div class="flex h-full w-full items-center justify-center text-xs font-semibold" style="color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-circle-play text-lg"></i>
                                            </div>
                                        </template>
                                        <button type="button" class="absolute right-1 top-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow" x-on:click="remove(file.id)">
                                            <i class="fa-light fa-xmark text-xs"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!selected.length">
                            <div class="flex h-full min-h-[4rem] items-center justify-center text-sm" style="color: var(--theme-muted-text-color);">
                                {{ $emptyLabel ?: __('No media selected yet.') }}
                            </div>
                        </template>
                    </div>
                </div>

                <div class="max-h-[34rem] overflow-y-auto px-4 py-4" x-on:scroll.passive="handleListScroll($event.target)">
                    <template x-if="folders.length">
                        <div class="mb-4">
                            <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Folders') }}</p>
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="folder in folders" :key="'folder-'+folder.idSecure">
                                    <div class="group relative rounded-[1rem] border px-3 py-3 transition hover:-translate-y-0.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);">
                                        <div class="absolute right-2 top-2 z-10 opacity-0 transition group-hover:opacity-100" x-data="{ open: false }" x-on:click.outside="open = false">
                                            <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full border bg-white/92 shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click.prevent.stop="open = !open">
                                                <i class="fa-light fa-ellipsis text-xs"></i>
                                            </button>
                                            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-8 w-28 rounded-[0.8rem] border p-1 shadow-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);">
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[13px] transition hover:bg-slate-900/5" x-on:click.prevent.stop="open = false; promptDeleteItem(folder)">
                                                    <i class="fa-light fa-trash w-4 text-center" style="color: rgb(225,29,72);"></i>
                                                    <span>{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button type="button" class="flex w-full items-center gap-3 text-left" x-on:click="openFolder(folder)">
                                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                                <i class="fa-light fa-folder-open text-lg"></i>
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="folder.name"></span>
                                                <span class="block truncate text-xs" style="color: var(--theme-muted-text-color);">{{ __('Open folder') }}</span>
                                            </span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="relative">
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Files') }}</p>
                        <template x-if="files.length">
                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <template x-for="file in files" :key="'file-'+file.id">
                                    <div
                                        class="group relative rounded-[1rem] border text-left transition hover:-translate-y-0.5"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.96);"
                                    >
                                        <div class="absolute right-2 top-2 z-10 opacity-0 transition group-hover:opacity-100" x-data="{ open: false }" x-on:click.outside="open = false">
                                            <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full border bg-white/92 shadow-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click.prevent.stop="open = !open">
                                                <i class="fa-light fa-ellipsis text-xs"></i>
                                            </button>
                                            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 top-8 w-[6.9rem] rounded-[0.8rem] border p-1 shadow-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);">
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[11.5px] transition hover:bg-slate-900/5" x-show="file.isEditableImage && file.editImageUrl" x-on:click.prevent.stop="open = false; openEditImage(file)">
                                                    <i class="fa-light fa-pen-to-square w-4 text-center"></i>
                                                    <span>{{ __('Edit image') }}</span>
                                                </button>
                                                <button type="button" class="flex w-full items-center gap-2 rounded-[0.6rem] px-2 py-1.5 text-left text-[11.5px] transition hover:bg-slate-900/5" x-on:click.prevent.stop="open = false; promptDeleteItem(file)">
                                                    <i class="fa-light fa-trash w-4 text-center" style="color: rgb(225,29,72);"></i>
                                                    <span>{{ __('Delete') }}</span>
                                                </button>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            draggable="true"
                                            class="block w-full text-left"
                                            x-on:click="choose(file)"
                                            x-on:dragstart="handleDragStart(file, $event)"
                                        >
                                        <div class="relative aspect-square overflow-hidden rounded-t-[1rem]" style="background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                            <template x-if="isRenderableImage(file)">
                                                <div class="relative h-full w-full overflow-hidden rounded-t-[1rem]">
                                                    <div
                                                        x-show="!isImageLoaded(file, 'split-grid') && !isImageErrored(file, 'split-grid')"
                                                        x-transition.opacity.duration.150ms
                                                        class="absolute inset-0 flex items-center justify-center rounded-t-[1rem]"
                                                        style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                    >
                                                        <i class="fa-light fa-loader animate-spin text-lg"></i>
                                                    </div>
                                                    <div
                                                        x-show="isImageErrored(file, 'split-grid')"
                                                        x-transition.opacity.duration.150ms
                                                        class="absolute inset-0 flex items-center justify-center rounded-t-[1rem]"
                                                        style="background-color: rgba(var(--theme-border-color-rgb), 0.10); color: var(--theme-muted-text-color);"
                                                    >
                                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                                                            <i class="fa-light fa-image text-lg"></i>
                                                        </span>
                                                    </div>
                                                    <img
                                                        x-bind:src="file.previewUrl || file.url"
                                                        x-bind:alt="file.name"
                                                        x-init="syncImageLoadFromElement(file, $el, 'split-grid')"
                                                        x-on:load="markImageLoaded(file, 'split-grid')"
                                                        x-on:error="markImageError(file, 'split-grid')"
                                                        x-bind:class="isImageLoaded(file, 'split-grid') ? 'opacity-100' : 'opacity-0'"
                                                        loading="lazy"
                                                        decoding="async"
                                                        class="h-full w-full rounded-t-[1rem] object-cover transition duration-300 group-hover:scale-[1.03]"
                                                    >
                                                </div>
                                            </template>
                                            <template x-if="!isRenderableImage(file) && isRenderableVideo(file)">
                                                <video x-bind:src="file.previewUrl || file.url" preload="metadata" muted playsinline class="h-full w-full object-cover"></video>
                                            </template>
                                            <template x-if="!isRenderableImage(file) && !isRenderableVideo(file)">
                                                <div class="flex h-full w-full items-center justify-center">
                                                    <i class="fa-light fa-circle-play text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                                </div>
                                            </template>

                                            <span x-show="isSelected(file)" class="absolute left-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-white shadow-lg" style="background-color: var(--theme-accent);">
                                                <i class="fa-light fa-check text-xs"></i>
                                            </span>
                                        </div>
                                        <div class="space-y-1 {{ $compactToolbar ? 'px-2.5 py-2.5' : 'px-3 py-3' }}">
                                            <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="file.name"></p>
                                            <p class="{{ $compactToolbar ? 'text-[10px]' : 'text-xs' }}" style="color: var(--theme-muted-text-color);" x-text="`${file.size || ''} | ${(file.category || '').toUpperCase()}`"></p>
                                        </div>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="!files.length && !loading">
                            <div class="rounded-[1rem] border border-dashed px-4 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                                <i class="fa-light fa-photo-film text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                <p class="mt-3 text-sm" style="color: var(--theme-muted-text-color);">{{ __('No media files found in this folder.') }}</p>
                            </div>
                        </template>

                        <template x-if="loading && !files.length">
                            <div class="rounded-[1rem] border border-dashed px-4 py-10 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color);">
                                <i class="fa-light fa-loader animate-spin text-3xl"></i>
                                <p class="mt-3 text-sm">{{ __('Loading media...') }}</p>
                            </div>
                        </template>

                        <template x-if="loading && files.length">
                            <div class="flex items-center justify-center py-4">
                                <div class="inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent); color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-loader animate-spin"></i>
                                    <span>{{ __('Loading...') }}</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </div>
        @endif

        <div
            x-cloak
            x-show="deleteConfirmOpen"
            x-transition.opacity
            class="fixed inset-0 z-[120] flex items-center justify-center p-4"
            x-on:keydown.escape.window="deleteConfirmOpen = false; pendingDeleteItem = null"
        >
            <div class="absolute inset-0 bg-black/25 backdrop-blur-[2px]" x-on:click="deleteConfirmOpen = false; pendingDeleteItem = null"></div>
            <div x-show="deleteConfirmOpen" x-transition.scale.95 class="relative w-full max-w-sm rounded-[1.1rem] border p-5 shadow-2xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: var(--theme-surface-base);">
                <div class="space-y-2">
                    <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Delete item?') }}</h3>
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                        {{ __('This action will permanently remove this file or folder from the current library.') }}
                    </p>
                    <p class="truncate text-sm font-medium" style="color: var(--theme-header-text-color);" x-text="pendingDeleteItem?.name || ''"></p>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <x-ui.button type="button" variant="outline" size="sm" x-on:click="deleteConfirmOpen = false; pendingDeleteItem = null">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button type="button" size="sm" x-on:click="confirmDeleteItem()">
                        {{ __('Delete') }}
                    </x-ui.button>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="editImageDialogOpen"
            x-transition.opacity
            class="fixed inset-0 z-[125] flex items-center justify-center"
            x-on:keydown.escape.window="closeEditImageDialog()"
        >
            <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" x-on:click="closeEditImageDialog()"></div>
            <div
                x-show="editImageDialogOpen"
                x-transition.scale.95
                class="relative flex h-screen w-screen flex-col overflow-hidden"
                style="background: var(--theme-surface-base);"
            >
                <button
                    type="button"
                    class="absolute right-5 top-5 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full border text-base shadow-sm transition hover:bg-slate-900/5"
                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: rgba(var(--theme-surface-base-rgb,255,255,255),0.92); color: var(--theme-header-text-color);"
                    x-on:click="closeEditImageDialog()"
                >
                    <i class="fa-light fa-xmark"></i>
                </button>
                <div class="min-h-0 flex-1 bg-white">
                    <iframe
                        x-bind:src="editImageDialogUrl"
                        class="h-full w-full border-0"
                        loading="lazy"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
</x-ui.field>

