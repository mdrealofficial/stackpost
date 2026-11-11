@props([
    'label' => null,
    'help' => null,
    'error' => null,
    'name' => null,
    'value' => null,
    'context' => 'auto',
    'valueField' => 'url',
    'pickerType' => 'all',
    'accept' => 'image/*,video/*,.csv,.doc,.docx,.xls,.xlsx,.pdf,.txt',
    'dialogTitle' => null,
    'dialogDescription' => null,
    'buttonLabel' => null,
    'placeholder' => null,
])

@php
    use Illuminate\Support\Str;

    $basePath = rtrim((string) request()->getBaseUrl(), '/');
    $normalizeUrl = static function (?string $url) use ($basePath): ?string {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
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
    $initialValue = $normalizeUrl(old($name ?? '', $value));
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
    $wireModel = $attributes->get('wire:model.live') ?: $attributes->get('wire:model');
@endphp

@include('appfiles::partials.google-picker-script')
@include('appfiles::partials.dropbox-chooser-script')
@include('appfiles::partials.onedrive-picker-script')
@include('appfiles::partials.adobe-express-script')

<x-ui.field :label="$label" :help="$help" :error="$error" {{ $attributes->only('class') }}>
    <div
        x-data="{
            fileValue: @js($initialValue),
            basePath: @js($basePath),
            pickerType: @js(in_array($pickerType, ['video', 'image'], true) ? $pickerType : 'all'),
            valueField: @js($valueField),
            search: '',
            sort: 'newest',
            remoteUrl: '',
            showRemoteUrlForm: false,
            showNewFolderForm: false,
            createFolderEndpoint: @js($pickerCreateFolderEndpoint),
            endpoint: @js($pickerEndpoint),
            uploadEndpoint: @js($pickerUploadEndpoint),
            uploadFromUrlEndpoint: @js($pickerUploadFromUrlEndpoint),
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
            listObserver: null,
            remoteImportSource: 'generic',
            newFolderName: '',
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            wireModel: @js($wireModel),
            suppressShellLoading(duration = 4000) {
                const shell = window.__appShellCurrent;

                if (shell && typeof shell.suppressLoadingFor === 'function') {
                    shell.suppressLoadingFor(duration);
                    return;
                }

                window.__appShellLoadingOptOutUntil = Date.now() + duration;
            },
            normalizeStoredValue(url) {
                const value = String(url || '').trim();

                if (!value) {
                    return '';
                }

                if (/^https?:\/\//i.test(value)) {
                    try {
                        const parsed = new URL(value);

                        if (this.basePath && parsed.origin === window.location.origin && parsed.pathname.startsWith('/') && !parsed.pathname.startsWith(`${this.basePath}/`) && parsed.pathname !== this.basePath) {
                            return `${this.basePath}${parsed.pathname}${parsed.search || ''}`;
                        }

                        return value;
                    } catch (error) {
                        return value;
                    }
                }

                if (this.basePath && value.startsWith('/') && !value.startsWith(`${this.basePath}/`) && value !== this.basePath) {
                    return `${this.basePath}${value}`;
                }

                return value;
            },
            init() {
                this.fileValue = this.normalizeStoredValue(this.fileValue);
                this.$watch('search', () => {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = setTimeout(() => this.reloadFiles(), 250);
                });

                this.$watch('sort', () => this.reloadFiles());
                this.$nextTick(() => this.observeLoadMore());
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
                            placeholder: 'https://example.com/file.png',
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
            },
            ensureStorageReady() {
                if (this.storageHealth?.valid !== false) {
                    return true;
                }

                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: {
                        type: 'warning',
                        title: this.storageHealth?.title || @js(__('Storage needs attention')),
                        message: this.storageHealth?.message || @js(__('The selected storage disk is not ready for uploads.')),
                    },
                }));

                return false;
            },
            observeLoadMore() {
                if (this.listObserver) {
                    this.listObserver.disconnect();
                    this.listObserver = null;
                }

                if (!('IntersectionObserver' in window) || !this.$refs.loadMoreSentinel) {
                    return;
                }

                const root = this.$refs.listScroller || null;

                this.listObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            this.loadMore();
                        }
                    });
                }, {
                    root,
                    rootMargin: '0px 0px 240px 0px',
                    threshold: 0.01,
                });

                this.listObserver.observe(this.$refs.loadMoreSentinel);
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
                this.$nextTick(() => this.observeLoadMore());
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
                    this.suppressShellLoading();
                    const url = new URL(this.endpoint, window.location.origin);
                    url.searchParams.set('page', String(this.page));
                    url.searchParams.set('sort', this.sort);
                    url.searchParams.set('type', this.pickerType);

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
                        throw new Error('Failed to load files.');
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
            async uploadSelectedFiles(event) {
                if (!this.ensureStorageReady()) {
                    event.target.value = '';
                    return;
                }

                const files = Array.from(event.target.files || []);
                if (!files.length || !this.uploadEndpoint) return;

                const formData = new FormData();
                files.forEach((file) => formData.append('files[]', file));
                formData.append('type', this.pickerType);

                if (this.currentFolder?.idSecure) {
                    formData.append('folder', this.currentFolder.idSecure);
                }

                this.uploadingFiles = true;

                try {
                    this.suppressShellLoading();
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
                    this.suppressShellLoading();
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
                            type: this.pickerType,
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
                    this.suppressShellLoading();
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
                        : (this.valueField === 'path'
                            ? String(file.path || '')
                            : (file.url || '')));

                const normalizedValue = this.valueField === 'id' || this.valueField === 'idSecure'
                    ? nextValue
                    : this.normalizeStoredValue(nextValue);

                this.fileValue = normalizedValue;
                this.$refs.input.value = normalizedValue;
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));

                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, normalizedValue, true);
                }

                this.debug('choose:done', {
                    normalizedValue,
                    inputValue: this.$refs.input?.value || '',
                    wireModel: this.wireModel || '',
                });

                window.dispatchEvent(new CustomEvent('input-file-picker:change', {
                    detail: {
                        name: @js($name),
                        value: normalizedValue,
                        file: file,
                    },
                }));
            },
            clear() {
                this.fileValue = '';
                this.$refs.input.value = '';
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));

                if (this.wireModel && this.$wire) {
                    this.$wire.set(this.wireModel, '', true);
                }
            },
            notify(type, message, title = '') {
                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: { type, title, message },
                }));
            },
            async openAdobeExpress() {
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
                console.log('[InputFilePicker]', ...args);
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
                if (this.pickerType === 'video') {
                    return 'video/*';
                }

                if (this.pickerType === 'image') {
                    return 'image/*';
                }

                return [
                    'image/*',
                    'video/*',
                    'text/csv',
                    'text/plain',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ].join(',');
            },
            async importFromGoogleDrivePicker() {
                if (!this.actionToggles?.google_drive) {
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
                    this.suppressShellLoading();
                    const result = await window.__appGooglePicker.pick({
                        clientId: this.googlePickerConfig.clientId,
                        apiKey: this.googlePickerConfig.apiKey,
                        multiple: false,
                        mimeTypes: this.googlePickerMimeTypes(),
                    });

                    const firstDoc = Array.isArray(result?.docs) ? result.docs[0] : null;
                    this.debug('googlePicker:selected', { result, firstDoc, pickerType: this.pickerType, currentFolder: this.currentFolder });

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
                            type: this.pickerType,
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
                    } else {
                        this.debug('googlePicker:noPayloadData', payload);
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
                    this.suppressShellLoading();
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
                    this.suppressShellLoading();
                    const result = await window.__appOneDrivePicker.pick({
                        clientId: this.oneDrivePickerConfig.clientId,
                        redirectUri: this.oneDrivePickerCallbackUrl,
                        action: 'query',
                        multiSelect: false,
                        advanced: {
                            filter: this.pickerType === 'video'
                                ? '.mp4,.mov,.webm,.avi,.mkv'
                                : (this.pickerType === 'image' ? '.jpg,.jpeg,.png,.gif,.webp,.bmp,.svg' : undefined),
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
        class="space-y-3"
    >
        <div class="flex items-stretch overflow-hidden rounded-[var(--theme-input-radius,0.75rem)] border shadow-[0_1px_2px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background-color: var(--theme-input-surface); color: var(--theme-input-text);">
            <input
                x-ref="input"
                x-model="fileValue"
                @if ($name) name="{{ $name }}" @endif
                {{ $attributes->except('class')->merge([
                    'type' => 'text',
                    'placeholder' => $placeholder ?: __('Select from files or enter a path/URL'),
                    'style' => 'background-color: transparent; color: var(--theme-input-text);',
                ])->class('flex h-11 min-w-0 flex-1 border-0 px-4 text-sm font-medium outline-none placeholder:font-normal placeholder:text-[var(--theme-input-placeholder)]') }}
            >

            <x-ui.modal
                width="xl"
                :title="$dialogTitle ?: __('Choose a file')"
                :description="$dialogDescription ?: __('Select a file from your file library and insert its value into this field.')"
                body-class="p-0"
                dismissible
            >
                <x-slot:trigger>
                    <button
                        type="button"
                        data-no-loading
                        class="inline-flex h-11 shrink-0 items-center gap-2 border-l px-4 text-sm font-medium transition hover:bg-slate-50/70 dark:hover:bg-white/5"
                        style="border-color: var(--theme-border-color); color: var(--theme-input-text);"
                        x-on:click="ensureFilesLoaded()"
                    >
                        <i class="fa-light fa-folder-open"></i>
                        <span>{{ $buttonLabel ?: __('Files') }}</span>
                    </button>
                </x-slot:trigger>

                <div class="space-y-0" data-no-loading>
                    <input x-ref="fileInput" type="file" accept="{{ $accept }}" multiple class="hidden" x-on:change="uploadSelectedFiles($event)">

                    <div class="border-t px-5 py-5 sm:px-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        @if (!($storageHealth['valid'] ?? true))
                            <div class="mb-4">
                                <x-ui.alert
                                    inline
                                    variant="warning"
                                    :title="$storageHealth['title'] ?? __('Storage needs attention')"
                                    :description="$storageHealth['message'] ?? __('The selected storage disk is not ready for uploads.')"
                                />
                            </div>
                        @endif

                        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_auto]">
                            <x-ui.input x-model="search" :label="__('Search')" :placeholder="__('Search files...')" />

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
                                        <x-ui.dropdown-menu-item icon="fa-light fa-link" x-on:click="showRemoteUrlForm = !showRemoteUrlForm">
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
                            <div class="mt-4 rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
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
                            <div class="mt-4 rounded-[1rem] border p-3" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 72%, transparent);">
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

                        <div class="mt-4 flex flex-wrap items-center gap-2" x-show="breadcrumbs.length || currentFolder">
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

                        <div x-ref="listScroller" class="mt-5 max-h-[32rem] overflow-y-auto pr-1">
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

                            <template x-if="files.length">
                                <div>
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Files') }}</p>
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <template x-for="file in files" :key="file.id">
                                            <button
                                                type="button"
                                                class="overflow-hidden rounded-[1rem] border text-left transition hover:-translate-y-0.5"
                                                style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-base) 96%, transparent);"
                                                x-on:click="choose(file); open = false"
                                            >
                                                <div
                                                    x-data="{ imageLoaded: false, imageFailed: false }"
                                                    class="relative aspect-[1/1] overflow-hidden"
                                                    style="background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent);"
                                                >
                                                    <div
                                                        x-show="!imageLoaded && !imageFailed"
                                                        class="absolute inset-0 flex items-center justify-center"
                                                        style="background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent); color: var(--theme-muted-text-color);"
                                                    >
                                                        <i class="fa-light fa-spinner animate-spin text-xl"></i>
                                                    </div>

                                                    <template x-if="file.previewUrl || file.url">
                                                        <img
                                                            x-bind:src="file.previewUrl || file.url"
                                                            x-bind:alt="file.name"
                                                            loading="lazy"
                                                            decoding="async"
                                                            class="h-full w-full object-cover transition-opacity duration-200"
                                                            x-bind:class="imageLoaded ? 'opacity-100' : 'opacity-0'"
                                                            x-on:load="imageLoaded = true"
                                                            x-on:error="imageFailed = true; $el.style.display = 'none'"
                                                        >
                                                    </template>

                                                    <template x-if="imageFailed || !(file.previewUrl || file.url)">
                                                        <div class="flex h-full w-full items-center justify-center">
                                                            <i class="fa-light fa-file text-2xl" style="color: var(--theme-muted-text-color);"></i>
                                                        </div>
                                                    </template>
                                                </div>

                                                <div class="space-y-1 px-3 py-3">
                                                    <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="file.name"></p>
                                                    <p class="text-xs" style="color: var(--theme-muted-text-color);" x-text="file.size || (file.url || '')"></p>
                                                </div>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!folders.length && !files.length && !loadingFiles">
                                <x-ui.empty
                                    icon="fa-light fa-image"
                                    :title="__('No files found.')"
                                    :description="__('Upload files in your library first, then return here to choose one.')"
                                />
                            </template>

                            <template x-if="loadingFiles && !files.length && !folders.length">
                                <div class="flex items-center justify-center py-10">
                                    <div class="inline-flex items-center gap-3 text-sm" style="color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Loading files...') }}</span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="uploadingFiles">
                                <div class="flex items-center justify-center py-4">
                                    <div class="inline-flex items-center gap-3 text-sm" style="color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Uploading files...') }}</span>
                                    </div>
                                </div>
                            </template>

                            <div x-ref="loadMoreSentinel" class="flex flex-col items-center justify-center gap-3 py-4">
                                <template x-if="loadingFiles && (files.length || folders.length)">
                                    <div class="inline-flex items-center gap-3 rounded-full border px-4 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-soft) 82%, transparent); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Loading more files...') }}</span>
                                    </div>
                                </template>

                                <template x-if="!loadingFiles && hasMore && (files.length || folders.length)">
                                    <x-ui.button type="button" variant="outline" x-on:click="loadMore()">
                                        {{ __('Load more') }}
                                    </x-ui.button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.modal>
        </div>

        <div class="flex items-center justify-end">
            <button
                x-cloak
                x-show="fileValue"
                type="button"
                class="text-sm font-medium transition hover:opacity-80"
                style="color: var(--theme-muted-text-color);"
                x-on:click="clear()"
            >
                {{ __('Clear') }}
            </button>
        </div>
    </div>
</x-ui.field>
