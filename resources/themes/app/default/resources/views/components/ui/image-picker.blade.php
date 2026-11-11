@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'value' => null,
    'preview' => null,
    'context' => 'auto',
    'valueField' => 'url',
    'ownerUser' => null,
    'dialogTitle' => null,
    'dialogDescription' => null,
    'buttonLabel' => null,
    'emptyLabel' => null,
    'layout' => 'default',
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

    $resolvedContext = in_array($context, ['portal', 'admin'], true)
        ? $context
        : (request()->routeIs('portal.*') ? 'portal' : 'admin');
    $isPortal = $resolvedContext === 'portal';
    $initialValue = old($name ?? '', $value);
    $initialPreview = $normalizeUrl(old(($name ? $name.'_preview' : ''), $preview ?? ($valueField === 'url' ? $initialValue : null)));
    $pickerEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-media' : 'admin-files.picker-images', [], false));
    $pickerUploadEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-upload' : 'admin-files.picker-upload', [], false));
    $pickerUploadFromUrlEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-upload-from-url' : 'admin-files.picker-upload-from-url', [], false));
    $pickerCreateFolderEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-create-folder' : 'admin-files.picker-create-folder', [], false));
    $pickerImportGoogleDriveEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-import-google-drive' : 'admin-files.picker-import-google-drive', [], false));
    $pickerImportAdobeExpressEndpoint = $normalizeUrl(route($isPortal ? 'portal.files.picker-import-adobe-express' : 'admin-files.picker-import-adobe-express', [], false));
    $fileManager = app(\Modules\AppFiles\Support\FileManager::class);
    $storageHealth = app(\App\Support\Storage\StorageDriverManager::class)->diskHealth();
    $actionToggles = [
        'upload_from_url' => $fileManager->isActionEnabled('upload_from_url', auth()->user()),
        'google_drive' => $fileManager->isActionEnabled('google_drive', auth()->user()),
        'dropbox' => $fileManager->isActionEnabled('dropbox', auth()->user()),
        'onedrive' => $fileManager->isActionEnabled('onedrive', auth()->user()),
        'adobe_express' => $fileManager->isActionEnabled('adobe_express', auth()->user()),
    ];
    $googlePickerConfig = $fileManager->googlePickerConfig(auth()->user());
    $dropboxChooserConfig = $fileManager->dropboxChooserConfig(auth()->user());
    $oneDrivePickerConfig = $fileManager->oneDrivePickerConfig(auth()->user());
    $adobeExpressConfig = $fileManager->adobeExpressConfig(auth()->user());
    $adobeExpressEnabled = $adobeExpressConfig['enabled'] ?? false;
    $compactLayout = $layout === 'compact';
@endphp

@include('appfiles::partials.google-picker-script')
@include('appfiles::partials.dropbox-chooser-script')
@include('appfiles::partials.onedrive-picker-script')
@include('appfiles::partials.adobe-express-script')

<x-ui.field :label="$label" :help="$help" :error="$error">
    <div
        x-data="{
            imageUrl: @js($initialValue),
            imagePreview: @js($initialPreview),
            pickerOpen: false,
            valueField: @js($valueField),
            search: '',
            sort: 'newest',
            remoteUrl: '',
            remoteImportSource: 'generic',
            showRemoteUrlForm: false,
            showNewFolderForm: false,
            endpoint: @js($pickerEndpoint),
            uploadEndpoint: @js($pickerUploadEndpoint),
            uploadFromUrlEndpoint: @js($pickerUploadFromUrlEndpoint),
            createFolderEndpoint: @js($pickerCreateFolderEndpoint),
            importGoogleDriveEndpoint: @js($pickerImportGoogleDriveEndpoint),
            importAdobeExpressEndpoint: @js($pickerImportAdobeExpressEndpoint),
            storageHealth: @js($storageHealth ?? ['valid' => true, 'title' => null, 'message' => null]),
            actionToggles: @js($actionToggles),
            googlePickerConfig: @js($googlePickerConfig),
            dropboxChooserConfig: @js($dropboxChooserConfig),
            oneDrivePickerConfig: @js($oneDrivePickerConfig),
            adobeExpressConfig: @js($adobeExpressConfig),
            oneDrivePickerCallbackUrl: @js(route('appfiles.onedrive-callback')),
            folders: [],
            breadcrumbs: [],
            currentFolder: null,
            files: [],
            loadingFiles: false,
            uploadingFiles: false,
            importingUrl: false,
            creatingFolder: false,
            initialized: false,
            hasMore: true,
            page: 1,
            searchTimer: null,
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            newFolderName: '',
            init() {
                this.$watch('search', () => {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.reloadFiles(), 250);
                });
                this.$watch('sort', () => this.reloadFiles());
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
                            label: @js(__('Image URL')),
                            placeholder: 'https://example.com/image.jpg',
                            helper: @js(__('Direct image URLs and supported share links from Google Drive, Dropbox, and OneDrive can be used here.')),
                        };
                }
            },
            openRemoteImport(source = 'generic') {
                if (!this.ensureStorageReady()) {
                    return;
                }

                this.remoteImportSource = source;
                this.showRemoteUrlForm = true;
            },
            ensureStorageReady() {
                if (this.storageHealth?.valid !== false) {
                    return true;
                }

                this.notify('warning', this.storageHealth?.message || @js(__('The selected storage disk is not ready for uploads.')), this.storageHealth?.title || @js(__('Storage needs attention')));

                return false;
            },
            filteredFiles() {
                return this.files;
            },
            async ensureFilesLoaded() {
                if (this.initialized) return;
                this.initialized = true;
                await this.reloadFiles();
            },
            async reloadFiles() {
                this.files = [];
                this.folders = [];
                this.page = 1;
                this.hasMore = true;
                await this.loadMore();
            },
            openFolder(folder) {
                if (!folder?.idSecure) return;
                this.currentFolder = folder;
                this.reloadFiles();
            },
            goToRoot() {
                this.currentFolder = null;
                this.reloadFiles();
            },
            goToBreadcrumb(folder) {
                this.currentFolder = folder?.idSecure ? folder : null;
                this.reloadFiles();
            },
            async loadMore() {
                if (this.loadingFiles || !this.hasMore || !this.endpoint) return;

                this.loadingFiles = true;

                try {
                    const url = new URL(this.endpoint, window.location.origin);
                    url.searchParams.set('page', String(this.page));
                    url.searchParams.set('sort', this.sort);
                    url.searchParams.set('type', 'image');

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
                        throw new Error('Failed to load images.');
                    }

                    const payload = await response.json();
                    const items = Array.isArray(payload.data) ? payload.data : [];
                    this.folders = this.page === 1 ? (Array.isArray(payload.folders) ? payload.folders : []) : this.folders;
                    this.breadcrumbs = Array.isArray(payload.breadcrumbs) ? payload.breadcrumbs : [];
                    this.currentFolder = payload.current_folder || this.currentFolder;
                    this.files = [...this.files, ...items];
                    this.hasMore = !!payload.has_more;
                    this.page = payload.next_page || (payload.has_more ? this.page + 1 : this.page);
                } catch (error) {
                    this.hasMore = false;
                } finally {
                    this.loadingFiles = false;
                }
            },
            handleListScroll(target) {
                if (this.loadingFiles || !this.hasMore) return;
                if ((target.scrollTop + target.clientHeight) >= (target.scrollHeight - 180)) {
                    this.loadMore();
                }
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
                formData.append('type', 'image');

                if (this.currentFolder?.idSecure) {
                    formData.append('folder', this.currentFolder.idSecure);
                }

                this.uploadingFiles = true;

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

                    if (items[0]) {
                        this.choose(items[0]);
                    }
                } catch (error) {
                } finally {
                    this.uploadingFiles = false;
                    event.target.value = '';
                }
            },
            async importFromUrl() {
                if (!this.remoteUrl.trim() || !this.uploadFromUrlEndpoint) return;

                this.importingUrl = true;

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
                            type: 'image',
                            folder: this.currentFolder?.idSecure || '',
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Import failed.');
                    }

                    if (payload.data) {
                        this.files = [payload.data, ...this.files];
                        this.choose(payload.data);
                    }

                    this.remoteUrl = '';
                    this.showRemoteUrlForm = false;
                } catch (error) {
                    this.notify('error', error?.message || @js(__('Import failed.')));
                } finally {
                    this.importingUrl = false;
                }
            },
            async createFolder() {
                if (!this.newFolderName.trim() || !this.createFolderEndpoint) return;

                this.creatingFolder = true;

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
                            folder: this.currentFolder?.idSecure || '',
                            name: this.newFolderName.trim(),
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(this.responseErrorMessage(payload, 'Create folder failed.'));
                    }

                    if (payload.data) {
                        this.folders = [payload.data, ...this.folders];
                    }

                    this.newFolderName = '';
                    this.showNewFolderForm = false;
                    this.notify('success', @js(__('Folder created successfully.')), @js(__('Folder')));
                } catch (error) {
                    this.notify('error', error?.message || @js(__('Create folder failed.')), @js(__('Folder')));
                } finally {
                    this.creatingFolder = false;
                }
            },
            choose(file) {
                this.debug('choose:start', { file, valueField: this.valueField });
                const nextValue = this.valueField === 'id'
                    ? String(file.id || '')
                    : (this.valueField === 'idSecure'
                        ? String(file.idSecure || '')
                        : (file.url || ''));

                this.imageUrl = nextValue;
                this.imagePreview = file.previewUrl || file.url || '';
                this.$refs.input.value = this.imageUrl;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                window.dispatchEvent(new CustomEvent('image-picker:change', {
                    detail: {
                        name: @js($name),
                        value: nextValue,
                        previewUrl: this.imagePreview,
                        file: file,
                    },
                }));
                this.debug('choose:done', { nextValue, preview: this.imagePreview });
            },
            clear() {
                this.imageUrl = '';
                this.imagePreview = '';
                this.$refs.input.value = '';
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
                window.dispatchEvent(new CustomEvent('image-picker:change', {
                    detail: {
                        name: @js($name),
                        value: '',
                        previewUrl: '',
                        file: null,
                    },
                }));
            },
            notify(type, message, title = '') {
                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: { type, title, message },
                }));
            },
            async openAdobeExpress() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (!this.adobeExpressConfig?.enabled) {
                    this.notify('error', @js(__('Adobe Express is not configured.')), @js(__('Adobe Express')));
                    return;
                }

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

                    this.choose(payload.data);
                    this.notify('success', @js(__('Imported image from Adobe Express successfully.')), @js(__('Adobe Express')));
                    await this.reloadFiles();
                } catch (error) {
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Adobe Express import failed.')), @js(__('Adobe Express')));
                    }
                }
            },
            debug(...args) {
                console.log('[ImagePicker]', ...args);
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
                    this.remoteUrl = '';
                    return;
                }

                this.importingUrl = true;

                try {
                    const result = await window.__appGooglePicker.pick({
                        clientId: this.googlePickerConfig.clientId,
                        apiKey: this.googlePickerConfig.apiKey,
                        multiple: false,
                        mimeTypes: 'image/*',
                    });

                    const firstDoc = Array.isArray(result?.docs) ? result.docs[0] : null;
                    this.debug('googlePicker:selected', { result, firstDoc, currentFolder: this.currentFolder });

                    if (!firstDoc?.id) {
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
                            file_id: String(firstDoc.id),
                            access_token: String(result.accessToken || ''),
                            type: 'image',
                            folder: this.currentFolder?.idSecure || '',
                            name: String(firstDoc.name || ''),
                            mime_type: String(firstDoc.mimeType || ''),
                            resource_key: String(firstDoc.resourceKey || ''),
                        }),
                    });

                    const payload = await response.json();
                    this.debug('googlePicker:importResponse', {
                        status: response.status,
                        ok: response.ok,
                        endpoint: this.importGoogleDriveEndpoint,
                        payload,
                    });

                    if (!response.ok) {
                        throw new Error(this.responseErrorMessage(payload, 'Google Drive import failed.'));
                    }

                    if (payload.data) {
                        this.files = [payload.data, ...this.files];
                        this.choose(payload.data);
                    }
                } catch (error) {
                    this.debug('googlePicker:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Google Drive import failed.')), @js(__('Google Drive')));
                    }
                } finally {
                    this.importingUrl = false;
                }
            },
            async importFromDropboxChooser() {
                if (!this.actionToggles?.dropbox) {
                    return;
                }

                if (!this.ensureStorageReady()) {
                    return;
                }

                if (!this.dropboxChooserConfig?.enabled) {
                    this.openRemoteImport('dropbox');
                    this.remoteUrl = '';
                    return;
                }

                if (!window.__appDropboxChooser?.pick) {
                    this.notify('error', @js(__('Dropbox Chooser is unavailable.')), @js(__('Dropbox')));
                    return;
                }

                this.importingUrl = true;

                try {
                    const result = await window.__appDropboxChooser.pick({
                        appKey: this.dropboxChooserConfig.appKey,
                        linkType: 'direct',
                        multiselect: false,
                    });

                    const firstFile = Array.isArray(result?.files) ? result.files[0] : null;

                    if (!firstFile?.link) {
                        throw new Error(@js(__('The selected Dropbox file could not be read.')));
                    }

                    this.remoteImportSource = 'dropbox';
                    this.remoteUrl = String(firstFile.link || '');
                    await this.importFromUrl();
                } catch (error) {
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('Dropbox import failed.')), @js(__('Dropbox')));
                    }
                } finally {
                    this.importingUrl = false;
                }
            },
            async importFromOneDrivePicker() {
                if (!this.actionToggles?.onedrive) {
                    return;
                }

                if (!this.ensureStorageReady()) {
                    return;
                }

                if (!this.oneDrivePickerConfig?.enabled) {
                    this.openRemoteImport('onedrive');
                    this.remoteUrl = '';
                    return;
                }

                if (!window.__appOneDrivePicker?.pick) {
                    this.notify('error', @js(__('OneDrive Picker is unavailable.')), @js(__('OneDrive')));
                    return;
                }

                this.importingUrl = true;

                try {
                    const result = await window.__appOneDrivePicker.pick({
                        clientId: this.oneDrivePickerConfig.clientId,
                        redirectUri: this.oneDrivePickerCallbackUrl,
                        action: 'query',
                        multiSelect: false,
                        advanced: {
                            filter: '.jpg,.jpeg,.png,.gif,.webp,.bmp,.svg',
                        },
                    });

                    const firstFile = Array.isArray(result?.value) ? result.value[0] : null;
                    const downloadUrl = firstFile?.['@microsoft.graph.downloadUrl'] || firstFile?.downloadUrl || '';

                    if (!downloadUrl) {
                        throw new Error(@js(__('The selected OneDrive file could not be read.')));
                    }

                    this.remoteImportSource = 'onedrive';
                    this.remoteUrl = String(downloadUrl);
                    await this.importFromUrl();
                } catch (error) {
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.notify('error', error?.message || @js(__('OneDrive import failed.')), @js(__('OneDrive')));
                    }
                } finally {
                    this.importingUrl = false;
                }
            },
        }"
        x-on:image-picker:set.window="if (($event.detail?.name || '') === @js($name)) { choose($event.detail?.file || { url: $event.detail?.url || '', previewUrl: $event.detail?.previewUrl || $event.detail?.url || '', name: $event.detail?.name || '' }) }"
        class="space-y-3"
    >
        <input
            x-ref="input"
            type="hidden"
            @if ($name) name="{{ $name }}" @endif
            value="{{ $initialValue }}"
        >

        <div @class([
            'rounded-[1.35rem] border p-4' => ! $compactLayout,
            'rounded-[1rem] border p-3' => $compactLayout,
        ]) style="border-color: var(--theme-border-color); background-color: var(--theme-surface-soft);">
            <div @class([
                'mx-auto flex max-w-[12rem] flex-col items-center gap-3' => ! $compactLayout,
                'flex items-center gap-3' => $compactLayout,
            ])>
                <div @class([
                    'relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-[1.15rem] border' => ! $compactLayout,
                    'relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-[0.85rem] border' => $compactLayout,
                ]) style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent);">
                    <template x-if="imagePreview">
                        <img
                            x-bind:src="imagePreview"
                            alt=""
                            class="h-full w-full object-cover"
                            x-on:error="imagePreview = '';"
                        >
                    </template>

                    <template x-if="!imagePreview">
                        <div class="flex h-full w-full items-center justify-center">
                            <div @class([
                                'flex h-24 w-24 items-center justify-center rounded-[1.35rem] border border-dashed' => ! $compactLayout,
                                'flex h-10 w-10 items-center justify-center rounded-[0.75rem] border border-dashed' => $compactLayout,
                            ]) style="border-color: color-mix(in srgb, var(--theme-border-color) 80%, transparent); color: var(--theme-muted-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 72%, transparent);">
                                <i @class(['fa-light fa-plus', 'text-4xl' => ! $compactLayout, 'text-xl' => $compactLayout])></i>
                            </div>
                        </div>
                    </template>
                </div>

                <div @class(['min-w-0 flex-1 space-y-2' => $compactLayout])>
                    @if ($compactLayout && $label)
                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $label }}</p>
                    @endif

                    <button
                        type="button"
                        @class([
                            'inline-flex items-center justify-center gap-2 rounded-[0.85rem] border px-4 py-2.5 text-sm font-semibold transition-colors',
                            'w-full' => ! $compactLayout,
                        ])
                        style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base); color: var(--theme-header-text-color);"
                        x-on:click="pickerOpen = true; ensureFilesLoaded()"
                    >
                        {{ $buttonLabel ?: __('Choose image') }}
                    </button>
                </div>

                <template x-if="imageUrl">
                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="clear()">
                        {{ __('Remove image') }}
                    </x-ui.button>
                </template>
            </div>

        </div>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="pickerOpen"
                class="fixed inset-0 z-[9999] flex items-center justify-center px-4 py-6 sm:px-6"
                x-on:keydown.escape.window="pickerOpen = false"
            >
                <div class="absolute inset-0 bg-slate-950/30 backdrop-blur-[2px]" x-on:click="pickerOpen = false"></div>
                <div
                    class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-[1.35rem] border shadow-[0_30px_90px_-42px_rgba(15,23,42,0.68)]"
                    style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);"
                    x-show="pickerOpen"
                >
                    <div class="flex items-start justify-between gap-4 px-5 py-4 sm:px-6" style="border-bottom: 1px solid var(--theme-border-color);">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold" style="color: var(--theme-header-text-color);">
                                {{ $dialogTitle ?: __('Choose an image') }}
                            </h3>
                            <p class="mt-1 text-sm leading-5" style="color: var(--theme-muted-text-color);">
                                {{ $dialogDescription ?: __('Select an image from your file library and use it as the post thumbnail.') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border transition hover:-translate-y-0.5"
                            style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 76%, transparent);"
                            x-on:click="pickerOpen = false"
                        >
                            <i class="fa-light fa-xmark"></i>
                        </button>
                    </div>

                    <div class="overflow-y-auto px-5 py-5 sm:px-6">
                        <div class="space-y-4">
                        <input x-ref="fileInput" type="file" accept="image/*" multiple class="hidden" x-on:change="uploadSelectedFiles($event)">

                        @if (!($storageHealth['valid'] ?? true))
                            <x-ui.alert
                                inline
                                variant="warning"
                                :title="$storageHealth['title'] ?? __('Storage needs attention')"
                                :description="$storageHealth['message'] ?? __('The selected storage disk is not ready for uploads.')"
                            />
                        @endif

                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_auto]">
                            <x-ui.input x-model="search" :label="__('Search')" :placeholder="__('Search images...')" />

                            <x-ui.select x-model="sort" :label="__('Sort')">
                                <option value="newest">{{ __('Newest first') }}</option>
                                <option value="oldest">{{ __('Oldest first') }}</option>
                                <option value="name_asc">{{ __('Name A-Z') }}</option>
                                <option value="name_desc">{{ __('Name Z-A') }}</option>
                            </x-ui.select>

                            <div class="flex items-end">
                                <x-ui.dropdown-menu align="right" width="auto" class="w-full">
                                    <x-slot:trigger>
                                        <x-ui.button type="button" variant="outline" class="w-full justify-center lg:w-auto">
                                            <i class="fa-light fa-bolt text-xs"></i>
                                            {{ __('Actions') }}
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    <x-ui.dropdown-menu-item icon="fa-light fa-upload" x-on:click="if (ensureStorageReady()) $refs.fileInput.click()">
                                        {{ __('Upload file') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-item icon="fa-light fa-folder-plus" x-on:click="showNewFolderForm = true; showRemoteUrlForm = false">
                                        {{ __('New folder') }}
                                    </x-ui.dropdown-menu-item>
                                    <x-ui.dropdown-menu-divider />
                                    @if ($actionToggles['upload_from_url'])
                                        <x-ui.dropdown-menu-item icon="fa-light fa-link" x-on:click="openRemoteImport('generic')">
                                            {{ __('Upload From URL') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    @if ($actionToggles['google_drive'])
                                        <x-ui.dropdown-menu-item icon="fa-brands fa-google-drive" x-on:click="importFromGoogleDrivePicker()">
                                            {{ __('Google Drive') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    @if ($actionToggles['dropbox'])
                                        <x-ui.dropdown-menu-item icon="fa-brands fa-dropbox" x-on:click="importFromDropboxChooser()">
                                            {{ __('Dropbox') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    @if ($actionToggles['onedrive'])
                                        <x-ui.dropdown-menu-item icon="fa-light fa-cloud" x-on:click="importFromOneDrivePicker()">
                                            {{ __('OneDrive') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    @if ($actionToggles['adobe_express'] && $adobeExpressEnabled)
                                        <x-ui.dropdown-menu-item icon="fa-light fa-wand-magic-sparkles" x-on:click="openAdobeExpress()">
                                            {{ __('Create for Adobe Express') }}
                                        </x-ui.dropdown-menu-item>
                                    @endif
                                    <x-ui.dropdown-menu-divider />
                                    <x-ui.dropdown-menu-item icon="fa-light fa-rotate-right" x-on:click="reloadFiles()">
                                        {{ __('Refresh') }}
                                    </x-ui.dropdown-menu-item>
                                </x-ui.dropdown-menu>
                            </div>
                        </div>

                        <template x-if="showRemoteUrlForm">
                            <div class="rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
                                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                    <x-ui.input x-model="remoteUrl" x-bind:label="remoteImportContext().label" x-bind:placeholder="remoteImportContext().placeholder" />
                                    <div class="flex items-end gap-2">
                                        <x-ui.button type="button" variant="outline" x-on:click="showRemoteUrlForm = false; remoteUrl = ''">
                                            {{ __('Cancel') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" x-on:click="importFromUrl()" x-bind:disabled="importingUrl">
                                            <span x-show="!importingUrl">{{ __('Import') }}</span>
                                            <span x-show="importingUrl">{{ __('Importing...') }}</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                                <p class="mt-2 text-xs leading-5" style="color: var(--theme-muted-text-color);" x-text="remoteImportContext().helper"></p>
                            </div>
                        </template>

                        <template x-if="showNewFolderForm">
                            <div class="rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
                                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                                    <x-ui.input x-model="newFolderName" :label="__('Folder name')" :placeholder="__('New folder')" />
                                    <div class="flex items-end gap-2">
                                        <x-ui.button type="button" variant="outline" x-on:click="showNewFolderForm = false; newFolderName = ''">
                                            {{ __('Cancel') }}
                                        </x-ui.button>
                                        <x-ui.button type="button" x-on:click="createFolder()" x-bind:disabled="creatingFolder">
                                            <span x-show="!creatingFolder">{{ __('Create') }}</span>
                                            <span x-show="creatingFolder">{{ __('Creating...') }}</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-wrap items-center gap-2" x-show="breadcrumbs.length || currentFolder">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition hover:-translate-y-0.5"
                                style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);"
                                x-on:click="goToRoot()"
                            >
                                <i class="fa-light fa-house"></i>
                                <span>{{ __('Root') }}</span>
                            </button>

                            <template x-for="folder in breadcrumbs" :key="`crumb-${folder.idSecure || folder.id}`">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition hover:-translate-y-0.5"
                                    style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);"
                                    x-on:click="goToBreadcrumb(folder)"
                                >
                                    <i class="fa-light fa-chevron-right text-[10px]"></i>
                                    <span x-text="folder.name"></span>
                                </button>
                            </template>
                        </div>

                        <div class="max-h-[30rem] overflow-y-auto pr-1" x-on:scroll.passive="handleListScroll($event.target)">
                            <template x-if="folders.length">
                                <div class="mb-5">
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Folders') }}</p>
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <template x-for="folder in folders" :key="`folder-${folder.idSecure || folder.id}`">
                                            <button
                                                type="button"
                                                class="flex items-center gap-3 rounded-[1rem] border px-4 py-3 text-left transition hover:-translate-y-0.5"
                                                style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 97%, transparent);"
                                                x-on:click="openFolder(folder)"
                                            >
                                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.9rem]" style="background-color: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                                    <i class="fa-light fa-folder-open text-lg"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="folder.name"></p>
                                                    <p class="mt-1 truncate text-xs" style="color: var(--theme-muted-text-color);" x-text="folder.note || '{{ __('Open folder') }}'"></p>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="filteredFiles().length">
                                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                    <template x-for="file in filteredFiles()" :key="file.id">
                                        <button
                                            type="button"
                                            class="overflow-hidden rounded-[1rem] border text-left transition hover:-translate-y-0.5"
                                            style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);"
                                            x-on:click="choose(file); pickerOpen = false"
                                        >
                                            <div class="aspect-square overflow-hidden" style="background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent);">
                                                <img x-bind:src="file.previewUrl || file.url" x-bind:alt="file.name" class="h-full w-full object-cover" x-on:error="$el.src = file.url || ''">
                                            </div>
                                            <div class="space-y-1 px-3 py-3">
                                                <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="file.name"></p>
                                                <p class="text-xs" style="color: var(--theme-muted-text-color);" x-text="file.size"></p>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <template x-if="!filteredFiles().length && !loadingFiles">
                                <x-ui.empty
                                    icon="fa-light fa-image"
                                    :title="__('No images found.')"
                                    :description="__('Upload images in your file library first, then return here to choose one.')"
                                />
                            </template>

                            <template x-if="loadingFiles && !filteredFiles().length">
                                <div class="flex items-center justify-center py-8">
                                    <div class="inline-flex items-center gap-3 text-sm" style="color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Loading images...') }}</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="uploadingFiles">
                                <div class="flex items-center justify-center py-4">
                                    <div class="inline-flex items-center gap-3 text-sm" style="color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Uploading images...') }}</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="loadingFiles && filteredFiles().length">
                                <div class="flex items-center justify-center py-4">
                                    <div class="inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Loading...') }}</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="filteredFiles().length && hasMore && !loadingFiles">
                                <div class="py-4 text-center text-xs" style="color: var(--theme-muted-text-color);">
                                    {{ __('Scroll to load more') }}
                                </div>
                            </template>
                        </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        </div>
</x-ui.field>
