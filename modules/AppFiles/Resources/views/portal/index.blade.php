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

    $storageFormatter = static function (int $bytes): string {
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' B';
    };
    $buildPortalUrl = static function (array $query = []): string {
        $url = request()->url();

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    };

    $folderItems = $folderItems ?? collect($items ?? [])->filter(fn ($item) => $item->is_folder);
    $fileItems = $fileItems ?? collect($items ?? [])->reject(fn ($item) => $item->is_folder);
    $totalNodes = max(1, (int) $summary['files'] + (int) $summary['folders']);
    $fileShare = max(8, min(100, (int) round(($summary['files'] / $totalNodes) * 100)));
    $folderShare = max(8, min(100, (int) round(($summary['folders'] / $totalNodes) * 100)));
    $avgFileBytes = $summary['files'] > 0 ? (int) floor($summary['storage'] / max(1, $summary['files'])) : 0;
    $categoryCards = collect([
        ['key' => 'video', 'label' => __('Videos'), 'icon' => 'fa-file-video', 'color' => '#06b6d4'],
        ['key' => 'image', 'label' => __('Images'), 'icon' => 'fa-image', 'color' => '#22c55e'],
        ['key' => 'document', 'label' => __('Documents'), 'icon' => 'fa-file-lines', 'color' => '#fbbf24'],
        ['key' => 'spreadsheet', 'label' => __('Documents'), 'icon' => 'fa-file-spreadsheet', 'color' => '#fbbf24'],
        ['key' => 'pdf', 'label' => __('Documents'), 'icon' => 'fa-file-pdf', 'color' => '#fbbf24'],
        ['key' => 'other', 'label' => __('Other Files'), 'icon' => 'fa-file', 'color' => '#8b5cf6'],
        ['key' => 'unknown', 'label' => __('Unknown File'), 'icon' => 'fa-file-circle-question', 'color' => '#94a3b8'],
    ])
    ->map(function (array $item) use ($storageByCategory) {
        $bytes = match ($item['key']) {
            'document' => (int) (($storageByCategory['document'] ?? 0) + ($storageByCategory['spreadsheet'] ?? 0) + ($storageByCategory['pdf'] ?? 0)),
            'unknown' => (int) ($storageByCategory['archive'] ?? 0),
            default => (int) ($storageByCategory[$item['key']] ?? 0),
        };

        return $item + ['bytes' => $bytes];
    })
    ->unique('label')
    ->filter(fn (array $item) => $item['bytes'] > 0)
    ->values();
@endphp

@php
    $backTarget = $currentFolder?->parent?->id_secure ?? '';
    $softBorder = 'color-mix(in srgb, var(--theme-border-color) 52%, transparent)';
    $panelBorder = 'color-mix(in srgb, var(--theme-border-color) 60%, transparent)';
@endphp

@include('appfiles::partials.google-picker-script')
@include('appfiles::partials.dropbox-chooser-script')
@include('appfiles::partials.onedrive-picker-script')
@include('appfiles::partials.adobe-express-script')

<div
        wire:key="portal-files-root"
        data-appfiles-portal-root
        x-data="{
            livewireId: @js($this->getId()),
            fileView: 'grid',
            compactHeader: false,
            uploadUrlDialogOpen: false,
            googlePickerConfig: @js($googlePickerConfig ?? ['enabled' => false, 'clientId' => '', 'apiKey' => '']),
            dropboxChooserConfig: @js($dropboxChooserConfig ?? ['enabled' => false, 'appKey' => '']),
            oneDrivePickerConfig: @js($oneDrivePickerConfig ?? ['enabled' => false, 'clientId' => '']),
            adobeExpressConfig: @js($adobeExpressConfig ?? ['enabled' => false, 'clientId' => '', 'appName' => '']),
            storageHealth: @js($storageHealth ?? ['valid' => true, 'title' => null, 'message' => null]),
            oneDrivePickerCallbackUrl: @js(route('appfiles.onedrive-callback')),
            googlePickerImporting: false,
            dropboxChooserImporting: false,
            oneDrivePickerImporting: false,
            adobeExpressImporting: false,
            remoteUrlSubmitting: false,
            remoteUrlValue: '',
            remoteUrlError: '',
            pendingUploadHandler: null,
            csrf: document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '',
            uploadEndpoint: @js($normalizeUrl(route('portal.files.picker-upload', [], false))),
            browseEndpoint: @js($normalizeUrl(route('portal.files.picker-media', [], false))),
            googlePickerImportEndpoint: @js($normalizeUrl(route('portal.files.picker-import-google-drive', [], false))),
            adobeExpressImportEndpoint: @js($normalizeUrl(route('portal.files.picker-import-adobe-express', [], false))),
            dropboxImportEndpoint: @js($normalizeUrl(route('portal.files.picker-upload-from-url', [], false))),
            currentFolderId: @js($currentFolder?->id_secure ?? ''),
            hasMoreItems: @js((bool) $hasMoreItems),
            allowedExtensions: @js(array_map(static fn (string $extension): string => '.'.ltrim($extension, '.'), $allowedExtensions)),
            newFolderDialogOpen: false,
            creatingFolder: false,
            newFolderName: '',
            newFolderNote: '',
            newFolderParent: @js($currentFolder?->id_secure ?? ''),
            searchQuery: @js((string) ($filters['q'] ?? '')),
            searchDebounceTimer: null,
            filterCategory: @js((string) ($filters['category'] ?? 'all')),
            editFolderDialogOpen: false,
            updatingFolder: false,
            editFolderId: '',
            editFolderName: '',
            editFolderNote: '',
            deleteDialogOpen: false,
            deleteTargetId: '',
            deleteTargetName: '',
            deleteTargetLabel: @js(__('Delete item')),
            deleteTargetDescription: @js(__('This action will permanently remove the selected file from your library.')),
            deleteTargetButton: @js(__('Delete')),
            playVideoDialogOpen: false,
            playVideoUrl: '',
            playVideoName: '',
            loadMoreInFlight: false,
            autoLoadMoreTicking: false,
            autoLoadMoreIdleTimer: null,
            autoLoadMoreCooldownUntil: 0,
            ajaxRequestInFlight: 0,
            ajaxLoadRequestId: 0,
            ajaxFilesPage: @js((int) max(1, (int) ceil(max(1, $fileItems->count()) / 20))),
            ajaxNextPage: @js($hasMoreItems ? ((int) max(1, (int) ceil(max(1, $fileItems->count()) / 20)) + 1) : null),
            ajaxFilesHasMore: @js((bool) $hasMoreItems),
            ajaxVisibleCount: @js((int) $fileItems->count()),
            renderedFileIds: @js($fileItems->pluck('id_secure')->values()->all()),
            selectedItemIds: [],
            livewire() {
                const componentId = String(this.livewireId || '').trim();

                if (componentId === '') {
                    return null;
                }

                return window.Livewire?.find(componentId) || null;
            },
            async triggerLoadMore(source = 'manual') {
                if (this.loadMoreInFlight || !this.hasMoreItems || this.ajaxRequestInFlight !== 0) {
                    return;
                }

                if (source === 'auto' && Date.now() < Number(this.autoLoadMoreCooldownUntil || 0)) {
                    return;
                }

                if (!window.AppFilesPortal?.loadMore) {
                    this.debug('triggerLoadMore:no-loader');
                    return;
                }

                this.loadMoreInFlight = true;
                this.debug('triggerLoadMore:start', { loadMoreInFlight: this.loadMoreInFlight, source });

                try {
                    await window.AppFilesPortal.loadMore(this);
                    this.hasMoreItems = Boolean(this.ajaxFilesHasMore);
                    if (source === 'auto') {
                        this.autoLoadMoreCooldownUntil = Date.now() + 420;
                    }
                } catch (error) {
                    this.debug('triggerLoadMore:error', error);
                } finally {
                    this.loadMoreInFlight = false;
                    this.debug('triggerLoadMore:end', { loadMoreInFlight: this.loadMoreInFlight, source });
                    if (this.hasMoreItems) {
                        this.$nextTick(() => this.checkAutoLoadMore('append'));
                    }
                }
            },
            loadedItemIds() {
                return [...new Set(
                    Array.from(this.$root.querySelectorAll('[data-select-checkbox][data-select-id]'))
                        .map((node) => String(node.dataset.selectId || '').trim())
                        .filter(Boolean)
                )];
            },
            selectedCount() {
                return this.selectedItemIds.length;
            },
            allLoadedItemsSelected() {
                const ids = this.loadedItemIds();
                return ids.length > 0 && ids.every((id) => this.selectedItemIds.includes(id));
            },
            syncSelectionUi() {
                const selected = new Set(this.selectedItemIds);

                this.$root.querySelectorAll('[data-select-checkbox][data-select-id]').forEach((node) => {
                    node.checked = selected.has(String(node.dataset.selectId || '').trim());
                });
            },
            toggleItemSelection(id, checked) {
                const targetId = String(id || '').trim();

                if (targetId === '') {
                    return;
                }

                if (checked) {
                    if (!this.selectedItemIds.includes(targetId)) {
                        this.selectedItemIds = [...this.selectedItemIds, targetId];
                    }
                } else {
                    this.selectedItemIds = this.selectedItemIds.filter((itemId) => itemId !== targetId);
                }

                this.syncSelectionUi();
            },
            toggleSelectAllLoadedItems() {
                const ids = this.loadedItemIds();

                if (ids.length === 0) {
                    this.selectedItemIds = [];
                    this.syncSelectionUi();
                    return;
                }

                if (this.allLoadedItemsSelected()) {
                    this.selectedItemIds = this.selectedItemIds.filter((id) => !ids.includes(id));
                } else {
                    this.selectedItemIds = [...new Set([...this.selectedItemIds, ...ids])];
                }

                this.syncSelectionUi();
            },
            clearClientSelection() {
                this.selectedItemIds = [];
                this.syncSelectionUi();
            },
            async deleteSelectedItemsFromSelection() {
                if (this.selectedItemIds.length === 0) {
                    return;
                }

                try {
                    await this.livewire()?.call('deleteItemsByIds', this.selectedItemIds);
                    this.clearClientSelection();
                    await this.refreshFilesDom();
                } catch (error) {
                    this.debug('deleteSelectedItems:error', error);
                    this.pushToast('error', @js(__('The selected file(s) could not be deleted. Please try again.')));
                }
            },
            folderUrl(folderId = '') {
                const url = new URL(window.location.href);
                const normalizedFolderId = String(folderId || '').trim();

                if (normalizedFolderId !== '') {
                    url.searchParams.set('folder', normalizedFolderId);
                } else {
                    url.searchParams.delete('folder');
                }

                return url.toString();
            },
            applyFilters() {
                try {
                    const url = new URL(window.location.href);
                    const category = String(this.filterCategory || 'all').trim();

                    if (category !== '' && category !== 'all') {
                        url.searchParams.set('category', category);
                    } else {
                        url.searchParams.delete('category');
                    }

                    window.location.href = url.toString();
                } catch (error) {
                    this.debug('applyFilters:error', error);
                    this.pushToast('error', @js(__('The selected filters could not be applied. Please try again.')));
                }
            },
            queueSearch() {
                window.clearTimeout(this.searchDebounceTimer);
                this.searchDebounceTimer = window.setTimeout(() => {
                    this.applySearch();
                }, 300);
            },
            applySearch() {
                try {
                    const url = new URL(window.location.href);
                    const query = String(this.searchQuery || '').trim();

                    if (query !== '') {
                        url.searchParams.set('q', query);
                    } else {
                        url.searchParams.delete('q');
                    }

                    window.location.href = url.toString();
                } catch (error) {
                    this.debug('applySearch:error', error);
                    this.pushToast('error', @js(__('The search query could not be applied. Please try again.')));
                }
            },
            handleUploadPickerChange(files) {
                const selectedFiles = Array.from(files || []);
                const handler = this.pendingUploadHandler;

                this.pendingUploadHandler = null;

                if (selectedFiles.length === 0) {
                    return;
                }

                if (typeof handler === 'function') {
                    handler(selectedFiles);
                    return;
                }

                this.uploadFiles(selectedFiles);
            },
            openUploadPicker(handler = null) {
                if (!this.ensureStorageReady()) {
                    return;
                }

                const componentId = String(this.livewireId || '').trim();
                const input = componentId !== ''
                    ? document.querySelector('[data-livewire-upload-input=' + JSON.stringify(componentId) + ']')
                    : document.querySelector('[data-livewire-upload-input]');

                if (!input) {
                    this.debug('openUploadPicker:no-input');
                    this.pushToast('error', @js(__('Upload input could not be found. Please refresh the page and try again.')));
                    return;
                }

                this.pendingUploadHandler = typeof handler === 'function' ? handler : null;
                input.click();
            },
            createUploadDialogState() {
                const parent = this;

                return {
                    progress: 0,
                    uploading: false,
                    dragging: false,
                    queue: [],
                    pickFiles() {
                        if (this.uploading) {
                            return;
                        }

                        this.$refs.modalUploadInput?.click();
                    },
                    formatSize(bytes = 0) {
                        const value = Number(bytes || 0);

                        if (value >= 1048576) {
                            return `${(value / 1048576).toFixed(2)} MB`;
                        }

                        if (value >= 1024) {
                            return `${(value / 1024).toFixed(2)} KB`;
                        }

                        return `${value} B`;
                    },
                    updateOverallProgress() {
                        if (this.queue.length === 0) {
                            this.progress = 0;
                            return;
                        }

                        const total = this.queue.reduce((sum, item) => sum + Number(item.progress || 0), 0);
                        this.progress = Math.round(total / this.queue.length);
                    },
                    updateQueueItem(id, updates = {}) {
                        this.queue = this.queue.map((item) => item.id === id ? { ...item, ...updates } : item);
                        this.updateOverallProgress();
                    },
                    async uploadDropped(files) {
                        const selectedFiles = Array.from(files || []);

                        if (selectedFiles.length === 0 || this.uploading) {
                            return;
                        }

                        const batch = selectedFiles.map((file, index) => ({
                            id: `${Date.now()}-${index}-${Math.random().toString(36).slice(2, 8)}`,
                            file,
                        }));

                        this.queue = batch.map(({ id, file }) => ({
                            id,
                            name: file.name,
                            sizeLabel: this.formatSize(file.size),
                            progress: 0,
                            status: 'queued',
                            message: @js(__('Waiting to upload...')),
                        }));
                        this.uploading = true;
                        this.dragging = false;
                        this.updateOverallProgress();

                        await parent.uploadFiles(batch.map((item) => item.file), {
                            fileStart: (_file, index) => {
                                const current = batch[index];

                                if (current) {
                                    this.updateQueueItem(current.id, {
                                        status: 'uploading',
                                        progress: 0,
                                        message: @js(__('Uploading...')),
                                    });
                                }
                            },
                            fileProgress: (event, _file, index) => {
                                const current = batch[index];

                                if (current) {
                                    this.updateQueueItem(current.id, {
                                        status: 'uploading',
                                        progress: Number(event?.detail?.progress ?? event?.progress ?? 0),
                                        message: @js(__('Uploading...')),
                                    });
                                }
                            },
                            fileSuccess: (_file, index) => {
                                const current = batch[index];

                                if (current) {
                                    this.updateQueueItem(current.id, {
                                        status: 'success',
                                        progress: 100,
                                        message: @js(__('Uploaded successfully.')),
                                    });
                                }
                            },
                            fileError: (error, _file, index) => {
                                const current = batch[index];

                                if (current) {
                                    this.updateQueueItem(current.id, {
                                        status: 'error',
                                        progress: 100,
                                        message: String(error?.message || @js(__('Upload failed.'))),
                                    });
                                }
                            },
                            complete: () => {
                                this.uploading = false;
                                this.dragging = false;
                                this.updateOverallProgress();
                            },
                        });
                    },
                };
            },
            init() {
                const savedView = window.localStorage.getItem('portal-files-view');
                if (savedView === 'grid' || savedView === 'list') {
                    this.fileView = savedView;
                }

                const compactThreshold = 140;
                const expandThreshold = 96;
                const syncHeaderState = () => {
                    const scrollTop = window.scrollY;

                    if (! this.compactHeader && scrollTop > compactThreshold) {
                        this.compactHeader = true;
                        return;
                    }

                    if (this.compactHeader && scrollTop < expandThreshold) {
                        this.compactHeader = false;
                    }
                };

                syncHeaderState();
                window.addEventListener('scroll', syncHeaderState, { passive: true });
                window.addEventListener('scroll', () => this.queueAutoLoadMoreCheck(), { passive: true });
                window.addEventListener('resize', () => this.queueAutoLoadMoreCheck(), { passive: true });
                this.$nextTick(() => this.checkAutoLoadMore('auto'));
                this.$nextTick(() => this.syncSelectionUi());
            },
            queueAutoLoadMoreCheck() {
                window.clearTimeout(this.autoLoadMoreIdleTimer);
                this.autoLoadMoreIdleTimer = window.setTimeout(() => this.checkAutoLoadMore('auto'), 180);

                if (this.autoLoadMoreTicking) {
                    return;
                }

                this.autoLoadMoreTicking = true;

                requestAnimationFrame(() => {
                    this.autoLoadMoreTicking = false;
                    this.checkAutoLoadMore('auto');
                });
            },
            checkAutoLoadMore(source = 'auto') {
                if (this.loadMoreInFlight || !this.hasMoreItems || this.ajaxRequestInFlight !== 0) {
                    return;
                }

                if (source === 'auto' && Date.now() < Number(this.autoLoadMoreCooldownUntil || 0)) {
                    return;
                }

                const sentinel = this.$root.querySelector('[data-load-more-sentinel]');

                if (!sentinel) {
                    return;
                }

                const rect = sentinel.getBoundingClientRect();
                const viewportBottom = window.innerHeight + 320;
                const documentBottom = document.documentElement.scrollHeight - (window.scrollY + window.innerHeight);

                if (rect.top <= viewportBottom || documentBottom <= 280) {
                    this.triggerLoadMore(source);
                }
            },
            setFileView(view) {
                this.fileView = view;
                window.localStorage.setItem('portal-files-view', view);
            },
            openNewFolderDialog(parentId = null) {
                this.newFolderName = '';
                this.newFolderNote = '';
                this.newFolderParent = String(parentId ?? this.currentFolderId ?? '').trim();
                this.newFolderDialogOpen = true;

                this.$nextTick(() => {
                    this.$root.querySelector('[data-create-folder-name]')?.focus();
                });
            },
            closeNewFolderDialog() {
                if (this.creatingFolder) {
                    return;
                }

                this.newFolderDialogOpen = false;
            },
            async confirmCreateFolder() {
                if (this.creatingFolder) {
                    return;
                }

                const wire = this.livewire();
                this.debug('createFolder:click', {
                    hasWire: Boolean(wire),
                    name: this.newFolderName,
                    noteLength: this.newFolderNote.length,
                    parent: this.newFolderParent,
                });

                if (!wire) {
                    this.pushToast('error', @js(__('The folder form could not connect to Livewire. Please refresh the page and try again.')));
                    return;
                }

                this.creatingFolder = true;

                try {
                    await wire.call('createFolderFromDialog', this.newFolderName, this.newFolderNote, this.newFolderParent || '');
                    this.debug('createFolder:request-finished');
                } catch (error) {
                    this.debug('createFolder:error', error);
                    this.pushToast('error', @js(__('The folder could not be created. Please try again.')));
                } finally {
                    this.creatingFolder = false;
                }
            },
            openEditFolderDialog(id, name, note = '') {
                this.editFolderId = String(id || '').trim();
                this.editFolderName = String(name || '');
                this.editFolderNote = String(note || '');
                this.editFolderDialogOpen = this.editFolderId !== '';

                this.$nextTick(() => {
                    this.$root.querySelector('[data-edit-folder-name]')?.focus();
                });
            },
            closeEditFolderDialog() {
                if (this.updatingFolder) {
                    return;
                }

                this.editFolderDialogOpen = false;
                this.editFolderId = '';
                this.editFolderName = '';
                this.editFolderNote = '';
            },
            async confirmUpdateFolder() {
                if (this.updatingFolder || !this.editFolderId) {
                    return;
                }

                const wire = this.livewire();

                if (!wire) {
                    this.pushToast('error', @js(__('The folder form could not connect to Livewire. Please refresh the page and try again.')));
                    return;
                }

                this.updatingFolder = true;

                try {
                    await wire.call('updateFolderFromDialog', this.editFolderId, this.editFolderName, this.editFolderNote);
                } catch (error) {
                    this.debug('updateFolder:error', error);
                    this.pushToast('error', @js(__('The folder could not be updated. Please try again.')));
                } finally {
                    this.updatingFolder = false;
                }
            },
            applyFolderUpdate(detail = {}) {
                const folderId = String(detail?.id || '').trim();

                if (folderId === '') {
                    return;
                }

                const name = String(detail?.name || '');
                const note = String(detail?.note || '');
                const fallbackNote = @js(__('Open this folder to manage its files.'));

                this.$root.querySelectorAll(`[data-folder-card-id='${folderId}']`).forEach((card) => {
                    card.querySelectorAll('[data-folder-card-name]').forEach((node) => {
                        node.textContent = name;
                    });

                    card.querySelectorAll('[data-folder-card-note]').forEach((node) => {
                        node.textContent = note !== '' ? note : fallbackNote;
                    });

                    card.querySelectorAll('[data-folder-edit-button]').forEach((button) => {
                        button.dataset.folderName = name;
                        button.dataset.folderNote = note;
                    });
                });

                if (this.currentFolderId === folderId) {
                    this.currentFolderId = folderId;
                }
            },
            openDeleteDialog(id, name, options = {}) {
                this.deleteTargetId = String(id || '').trim();
                this.deleteTargetName = String(name || '').trim();
                this.deleteTargetLabel = String(options.title || @js(__('Delete item')));
                this.deleteTargetDescription = String(options.description || @js(__('This action will permanently remove the selected file from your library.')));
                this.deleteTargetButton = String(options.button || @js(__('Delete')));
                this.deleteDialogOpen = this.deleteTargetId !== '';
            },
            closeDeleteDialog() {
                this.deleteDialogOpen = false;
                this.deleteTargetId = '';
                this.deleteTargetName = '';
                this.deleteTargetLabel = @js(__('Delete item'));
                this.deleteTargetDescription = @js(__('This action will permanently remove the selected file from your library.'));
                this.deleteTargetButton = @js(__('Delete'));
            },
            async confirmDelete() {
                if (!this.deleteTargetId) {
                    return;
                }

                const targetId = this.deleteTargetId;
                this.closeDeleteDialog();

                try {
                    await this.livewire()?.call('deleteItem', targetId);
                    await this.refreshFilesDom();
                } catch (error) {
                    this.debug('confirmDelete:error', error);
                    this.pushToast('error', @js(__('The selected file could not be deleted. Please try again.')));
                }
            },
            promptPlayVideo(url, name) {
                this.playVideoUrl = url;
                this.playVideoName = name;
                this.playVideoDialogOpen = true;
            },
            pushToast(type, message, title = '') {
                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: { type, title, message },
                }));
            },
            ensureStorageReady() {
                if (this.storageHealth?.valid !== false) {
                    return true;
                }

                this.pushToast('warning', this.storageHealth?.message || @js(__('The selected storage disk is not ready for uploads.')), this.storageHealth?.title || @js(__('Storage needs attention')));

                return false;
            },
            async openRemoteImport(source = 'generic') {
                if (!this.ensureStorageReady()) {
                    return;
                }

                this.uploadUrlDialogOpen = true;
                this.remoteUrlError = '';
                const wire = this.livewire();

                if (!wire) {
                    this.debug('openRemoteImport:no-wire');
                    return;
                }

                await wire.set('remote_import_source', source);
            },
            async submitRemoteUrlImport() {
                if (this.remoteUrlSubmitting) {
                    return;
                }

                const remoteUrl = String(this.remoteUrlValue || '').trim();
                this.remoteUrlError = '';

                if (remoteUrl === '') {
                    this.remoteUrlError = @js(__('The file URL field is required.'));
                    return;
                }

                this.remoteUrlSubmitting = true;

                try {
                    const response = await fetch(this.dropboxImportEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': this.csrf,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            remote_url: remoteUrl,
                            folder: this.currentFolderId || '',
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    this.debug('remoteUrlImport:afterFetch', { payload, ok: response.ok });

                    if (!response.ok || !payload?.data) {
                        this.remoteUrlError = String(payload?.errors?.remote_url?.[0] || payload?.message || @js(__('The file could not be imported from the provided URL.')));
                        return;
                    }

                    this.remoteUrlValue = '';
                    this.uploadUrlDialogOpen = false;
                    this.pushToast('success', @js(__('Imported the file from URL successfully.')));
                    await this.refreshFilesDom();
                } catch (error) {
                    this.debug('remoteUrlImport:error', error);
                    this.remoteUrlError = error?.message || @js(__('The file could not be imported from the provided URL.'));
                } finally {
                    this.remoteUrlSubmitting = false;
                }
            },
            async refreshFilesDom() {
                window.location.reload();
            },
            notifyUploadFinished() {
                window.dispatchEvent(new CustomEvent('appfiles-upload-finished'));
            },
            removeDeletedItem(itemId = '') {
                const targetId = String(itemId || '').trim();

                if (targetId === '') {
                    return;
                }

                this.$root.querySelectorAll(`[data-folder-card-id='${targetId}'], [data-item-id='${targetId}']`).forEach((node) => {
                    node.remove();
                });

                this.selectedItemIds = this.selectedItemIds.filter((id) => id !== targetId);
                this.renderedFileIds = this.renderedFileIds.filter((id) => id !== targetId);
                this.ajaxVisibleCount = Math.max(0, Number(this.ajaxVisibleCount || 0) - 1);
                this.syncSelectionUi();
            },
            removeDeletedItems(itemIds = []) {
                (Array.isArray(itemIds) ? itemIds : []).forEach((itemId) => {
                    this.removeDeletedItem(itemId);
                });
            },
            debug(...args) {
                console.log('[PortalFiles]', ...args);
            },
            async uploadFiles(files, callbacks = {}) {
                const selectedFiles = Array.from(files || []);

                if (selectedFiles.length === 0) {
                    return false;
                }

                if (!this.ensureStorageReady()) {
                    return false;
                }

                if (!this.uploadEndpoint) {
                    this.debug('uploadFiles:no-endpoint');
                    this.pushToast('error', @js(__('Upload could not start. Please refresh the page and try again.')));
                    return false;
                }

                this.debug('uploadFiles:selected', selectedFiles.map((file) => ({
                    name: file.name,
                    size: file.size,
                    type: file.type,
                })));

                let successCount = 0;
                let nextIndex = 0;
                const concurrency = Math.min(3, selectedFiles.length);
                const uploadSingle = (file, index) => new Promise((resolve) => {
                    const request = new XMLHttpRequest();
                    const formData = new FormData();

                    formData.append('files[]', file, file.name);

                    if (String(this.currentFolderId || '').trim() !== '') {
                        formData.append('folder', String(this.currentFolderId || '').trim());
                    }

                    if (typeof callbacks.fileStart === 'function') {
                        callbacks.fileStart(file, index, selectedFiles);
                    }

                    request.open('POST', this.uploadEndpoint, true);
                    request.setRequestHeader('Accept', 'application/json');
                    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    if (this.csrf) {
                        request.setRequestHeader('X-CSRF-TOKEN', this.csrf);
                    }

                    request.upload.addEventListener('progress', (event) => {
                        const progress = event?.lengthComputable
                            ? Math.round((event.loaded / Math.max(event.total, 1)) * 100)
                            : 0;
                        const progressEvent = { detail: { progress }, progress };

                        if (typeof callbacks.progress === 'function') {
                            callbacks.progress(progressEvent, file, index, selectedFiles);
                        }

                        if (typeof callbacks.fileProgress === 'function') {
                            callbacks.fileProgress(progressEvent, file, index, selectedFiles);
                        }
                    });

                    request.addEventListener('load', () => {
                        let payload = {};

                        try {
                            payload = JSON.parse(request.responseText || '{}');
                        } catch (error) {
                            payload = {};
                        }

                        if (request.status >= 200 && request.status < 300) {
                            if (typeof callbacks.fileSuccess === 'function') {
                                callbacks.fileSuccess(file, index, selectedFiles, payload);
                            }

                            resolve(true);
                            return;
                        }

                        const errorMessage = payload?.message
                            || payload?.errors?.files?.[0]
                            || payload?.errors?.['files.0']?.[0]
                            || @js(__('The selected file could not be uploaded.'));

                        if (typeof callbacks.fileError === 'function') {
                            callbacks.fileError({ message: errorMessage, payload, status: request.status }, file, index, selectedFiles);
                        }

                        resolve(false);
                    });

                    request.addEventListener('error', () => {
                        const errorMessage = @js(__('The upload request failed. Please try again.'));

                        if (typeof callbacks.fileError === 'function') {
                            callbacks.fileError({ message: errorMessage, status: 0 }, file, index, selectedFiles);
                        }

                        resolve(false);
                    });

                    request.send(formData);
                });

                const workers = Array.from({ length: concurrency }, async () => {
                    while (nextIndex < selectedFiles.length) {
                        const currentIndex = nextIndex;
                        nextIndex += 1;

                        const uploaded = await uploadSingle(selectedFiles[currentIndex], currentIndex);

                        if (uploaded) {
                            successCount += 1;
                        }
                    }
                });

                await Promise.all(workers);

                const result = {
                    total: selectedFiles.length,
                    successCount,
                    failedCount: selectedFiles.length - successCount,
                };

                if (result.successCount > 0) {
                    this.pushToast('success', @js(__('Uploaded :count file(s) successfully.')).replace(':count', String(result.successCount)));
                }

                if (result.failedCount > 0) {
                    this.pushToast(
                        result.successCount > 0 ? 'warning' : 'error',
                        @js(__(':count file(s) failed to upload.')).replace(':count', String(result.failedCount))
                    );
                }

                if (typeof callbacks.complete === 'function') {
                    callbacks.complete(result);
                }

                this.notifyUploadFinished();

                if (result.successCount > 0) {
                    if (typeof callbacks.success === 'function') {
                        callbacks.success(result);
                    }
                } else if (typeof callbacks.error === 'function') {
                    callbacks.error(result);
                }

                return result.failedCount === 0;
            },
            async openGooglePicker() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (this.googlePickerImporting) {
                    return;
                }

                if (!this.googlePickerConfig?.enabled) {
                    const wire = this.livewire();
                    if (wire) {
                        await wire.prepareRemoteImport('google_drive');
                    }
                    return;
                }

                this.googlePickerImporting = true;

                try {
                    const result = await window.__appGooglePicker.pick({
                        clientId: this.googlePickerConfig.clientId,
                        apiKey: this.googlePickerConfig.apiKey,
                        multiple: true,
                    });
                    this.debug('googlePicker:selected', result);

                    const docs = Array.isArray(result?.docs) ? result.docs : [];
                    let importedCount = 0;

                    for (const doc of docs) {
                        if (!doc?.id) {
                            continue;
                        }

                        this.debug('googlePicker:beforeFetchImport', doc);
                        const response = await fetch(this.googlePickerImportEndpoint, {
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
                                folder: this.currentFolderId || '',
                                name: String(doc.name || ''),
                                mime_type: String(doc.mimeType || ''),
                                resource_key: String(doc.resourceKey || ''),
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        this.debug('googlePicker:afterFetchImport', { doc, payload, ok: response.ok });

                        if (!response.ok || !payload?.data) {
                            throw new Error(payload?.message || 'Google Drive import failed.');
                        }

                        importedCount += 1;
                    }

                    if (importedCount > 0) {
                        this.pushToast('success', @js(__('Imported file from Google Drive successfully.')), @js(__('Google Drive')));
                        await this.refreshFilesDom();
                    }
                } catch (error) {
                    this.debug('googlePicker:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.pushToast('error', error?.message || @js(__('Google Drive import failed.')), @js(__('Google Drive')));
                    }
                } finally {
                    this.debug('googlePicker:done');
                    this.googlePickerImporting = false;
                }
            },
            async openDropboxChooser() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (this.dropboxChooserImporting) {
                    return;
                }

                if (!this.dropboxChooserConfig?.enabled) {
                    const wire = this.livewire();
                    if (wire) {
                        await wire.prepareRemoteImport('dropbox');
                    }
                    return;
                }

                this.dropboxChooserImporting = true;

                try {
                    const result = await window.__appDropboxChooser.pick({
                        appKey: this.dropboxChooserConfig.appKey,
                        multiple: true,
                    });
                    this.debug('dropboxChooser:selected', result);

                    const files = Array.isArray(result?.files) ? result.files : [];
                    let importedCount = 0;

                    for (const file of files) {
                        if (!file?.link) {
                            continue;
                        }

                        this.debug('dropboxChooser:beforeFetchImport', file);
                        const response = await fetch(this.dropboxImportEndpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                remote_url: String(file.link),
                                folder: this.currentFolderId || '',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        this.debug('dropboxChooser:afterFetchImport', { file, payload, ok: response.ok });

                        if (!response.ok || !payload?.data) {
                            throw new Error(payload?.message || 'Dropbox import failed.');
                        }

                        importedCount += 1;
                    }

                    if (importedCount > 0) {
                        this.pushToast('success', @js(__('Imported file from Dropbox successfully.')), @js(__('Dropbox')));
                        await this.refreshFilesDom();
                    }
                } catch (error) {
                    this.debug('dropboxChooser:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.pushToast('error', error?.message || @js(__('Dropbox import failed.')), @js(__('Dropbox')));
                    }
                } finally {
                    this.debug('dropboxChooser:done');
                    this.dropboxChooserImporting = false;
                }
            },
            async openOneDrivePicker() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (this.oneDrivePickerImporting) {
                    return;
                }

                if (!this.oneDrivePickerConfig?.enabled) {
                    this.pushToast('error', @js(__('OneDrive Picker is not configured. Please check the Client ID.')), @js(__('OneDrive')));
                    return;
                }

                this.oneDrivePickerImporting = true;

                try {
                    const result = await window.__appOneDrivePicker.pick({
                        clientId: this.oneDrivePickerConfig.clientId,
                        redirectUri: this.oneDrivePickerCallbackUrl,
                        multiple: true,
                        extensions: this.allowedExtensions,
                    });
                    this.debug('oneDrivePicker:selected', result);

                    const files = Array.isArray(result?.files) ? result.files : [];
                    let importedCount = 0;

                    for (const file of files) {
                        if (!file?.link) {
                            continue;
                        }

                        this.debug('oneDrivePicker:beforeFetchImport', file);
                        const response = await fetch(this.dropboxImportEndpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({
                                remote_url: String(file.link),
                                folder: this.currentFolderId || '',
                            }),
                        });
                        const payload = await response.json().catch(() => ({}));
                        this.debug('oneDrivePicker:afterFetchImport', { file, payload, ok: response.ok });

                        if (!response.ok || !payload?.data) {
                            throw new Error(payload?.message || 'OneDrive import failed.');
                        }

                        importedCount += 1;
                    }

                    if (importedCount > 0) {
                        this.pushToast('success', @js(__('Imported file from OneDrive successfully.')), @js(__('OneDrive')));
                        await this.refreshFilesDom();
                    }
                } catch (error) {
                    this.debug('oneDrivePicker:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.pushToast('error', error?.message || @js(__('OneDrive import failed.')), @js(__('OneDrive')));
                    }
                } finally {
                    this.debug('oneDrivePicker:done');
                    this.oneDrivePickerImporting = false;
                }
            },
            async openAdobeExpress() {
                if (!this.ensureStorageReady()) {
                    return;
                }

                if (this.adobeExpressImporting) {
                    return;
                }

                if (!this.adobeExpressConfig?.enabled) {
                    this.pushToast('error', @js(__('Adobe Express is not configured.')), @js(__('Adobe Express')));
                    return;
                }

                this.adobeExpressImporting = true;

                try {
                    const adobeExpress = window.__appAdobeExpress;

                    if (!adobeExpress || typeof adobeExpress.createImage !== 'function') {
                        throw new Error('Adobe Express launcher is unavailable.');
                    }

                    const result = await adobeExpress.createImage({
                        clientId: this.adobeExpressConfig.clientId,
                        appName: this.adobeExpressConfig.appName,
                    });

                    const response = await fetch(this.adobeExpressImportEndpoint, {
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
                            folder: this.currentFolderId || '',
                        }),
                    });
                    const payload = await response.json().catch(() => ({}));
                    this.debug('adobeExpress:afterFetchImport', { payload, ok: response.ok });

                    if (!response.ok || !payload?.data) {
                        throw new Error(payload?.message || 'Adobe Express import failed.');
                    }

                    this.pushToast('success', @js(__('Imported image from Adobe Express successfully.')), @js(__('Adobe Express')));
                    await this.refreshFilesDom();
                } catch (error) {
                    this.debug('adobeExpress:error', error);
                    if (!String(error?.message || '').includes('cancelled')) {
                        this.pushToast('error', error?.message || @js(__('Adobe Express import failed.')), @js(__('Adobe Express')));
                    }
                } finally {
                    this.adobeExpressImporting = false;
                }
            }
        }"
        x-on:appfiles-debug.window="console.log('[PortalFiles:server]', $event.detail)"
        x-on:appfiles-open-remote-import.window="uploadUrlDialogOpen = true"
        x-on:appfiles-folder-created.window="newFolderDialogOpen = false; currentFolderId = String(newFolderParent || currentFolderId || ''); refreshFilesDom()"
        x-on:appfiles-folder-opened.window="currentFolderId = String($event.detail.id || ''); newFolderParent = currentFolderId"
        x-on:appfiles-folder-updated.window="editFolderDialogOpen = false; applyFolderUpdate($event.detail); refreshFilesDom()"
        x-on:appfiles-item-deleted.window="removeDeletedItem($event.detail.id)"
        x-on:appfiles-items-deleted.window="removeDeletedItems($event.detail.ids || [])"
        x-on:appfiles-upload-finished.window="refreshFilesDom()"
        class="min-h-[calc(100vh-3.5rem)] space-y-8 bg-[var(--theme-surface-base)] px-3 py-5 sm:px-4 xl:px-5 2xl:px-6"
        style="background: var(--theme-surface-base);"
    >
        <input
            x-ref="livewireUploadInput"
            data-livewire-upload-input="{{ $this->getId() }}"
            type="file"
            multiple
            class="sr-only"
            x-on:change="
                handleUploadPickerChange($event.target.files);
                $event.target.value = '';
            "
        >
        <header
            class="sticky top-[78px] z-30 space-y-2 border-b pb-2 pt-2 transition-all duration-150 ease-out"
            style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);"
        >
            <div class="flex flex-wrap items-start justify-between gap-4 overflow-visible">
                <div class="min-w-0 flex-1 self-start transition-all duration-150 ease-out" :class="compactHeader ? 'space-y-0 flex min-h-[40px] items-center' : 'space-y-1.5'">
                    <div class="flex min-w-0 items-center gap-3 whitespace-nowrap">
                        <h1
                            class="font-semibold tracking-[-0.05em] transition-all duration-150 ease-out"
                            :class="compactHeader ? 'text-[1.3rem]' : 'text-[1.5rem]'"
                            style="color: var(--theme-header-text-color);"
                        >{{ __('Files') }}</h1>
                        <span class="text-[13px] font-medium" style="color: var(--theme-muted-text-color);">({{ number_format($summary['total']) }} {{ __('records') }})</span>
                    </div>
                    <p
                        class="hidden max-w-3xl overflow-hidden text-sm leading-7 transition-all duration-[35ms] ease-linear sm:block"
                        :class="compactHeader ? 'max-h-0 -translate-y-1 opacity-0' : 'max-h-20 translate-y-0 opacity-100'"
                        style="color: var(--theme-muted-text-color);"
                    >{{ __('Personal file workspace for organizing folders, browsing assets, and keeping uploads in one clean user library.') }}</p>
                </div>

                <div class="flex min-w-0 flex-wrap items-center justify-end gap-2.5">
                    <x-ui.icon-input
                        icon="fa-light fa-magnifying-glass"
                        type="text"
                        x-model="searchQuery"
                        x-on:input="queueSearch()"
                        x-on:keydown.enter.prevent="applySearch()"
                        :placeholder="__('Search files, mime type, extension...')"
                        wrapperClass="hidden 2xl:block 2xl:w-[340px]"
                    />

                    <x-ui.drawer class="2xl:hidden" side="right" width="lg" :title="__('File Menu')" :description="__('Search, filter, upload, and review your workspace.')" x-on:appfiles-close-menu.window="open = false">
                        <x-slot:trigger>
                            <x-ui.button variant="outline" class="h-10 shrink-0 justify-center px-4 text-[13px]">
                                <i class="fa-light fa-bars-staggered text-xs"></i>
                                {{ __('Menu') }}
                            </x-ui.button>
                        </x-slot:trigger>

                        <div class="space-y-4">
                            <x-ui.icon-input
                                icon="fa-light fa-magnifying-glass"
                                type="text"
                                x-model="searchQuery"
                                x-on:input="queueSearch()"
                                x-on:keydown.enter.prevent="applySearch()"
                                :placeholder="__('Search files, mime type, extension...')"
                            />

                            <div class="space-y-5 rounded-[1.35rem] border p-4" style="border-color: {{ $softBorder }}; background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-base) 92%, white 8%), var(--theme-surface-base));">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Control center') }}</p>
                                        <p class="mt-1 text-[1.05rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Browse and manage') }}</p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]" style="background: color-mix(in srgb, var(--theme-accent) 10%, transparent); color: var(--theme-accent);">
                                        {{ __('Live') }}
                                    </span>
                                </div>

                                <div class="rounded-[1.05rem] border p-3" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft);">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Filters') }}</p>
                                        <span class="text-[11px] font-medium uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('1 active') }}</span>
                                    </div>
                                    <div class="mt-3">
                                        <x-ui.select x-model="filterCategory" x-on:change="applyFilters()" :label="__('Category')">
                                            <option value="all">{{ __('All') }}</option>
                                            @foreach (['folder', 'image', 'video', 'audio', 'document', 'spreadsheet', 'pdf', 'archive', 'other'] as $category)
                                                <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </div>
                                </div>

                                <div class="rounded-[1.05rem] border p-3" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft);">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Quick actions') }}</p>
                                        <i class="fa-light fa-bolt text-xs" style="color: var(--theme-muted-text-color);"></i>
                                    </div>

                                    <div class="mt-3 space-y-3">
                                        <div class="grid grid-cols-2 gap-3">
                                        <x-ui.modal width="md" :title="__('Upload files')" :description="__('Drop files into the current folder. Upload starts automatically as soon as you select them.')" closeEvent="appfiles-upload-finished">
                                            <x-slot:trigger>
                                                <x-ui.button type="button" block class="h-12 rounded-[1rem]" x-on:click="window.dispatchEvent(new CustomEvent('appfiles-close-menu'))">
                                                    <i class="fa-light fa-upload text-sm"></i>
                                                    {{ __('Upload') }}
                                                </x-ui.button>
                                            </x-slot:trigger>

                                            <div x-data="createUploadDialogState()" class="space-y-5">
                                                <form class="space-y-5">
                                                    <input
                                                        x-ref="modalUploadInput"
                                                        type="file"
                                                        multiple
                                                        class="sr-only"
                                                        x-on:change="uploadDropped($event.target.files); $event.target.value = ''"
                                                    >
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]" style="border-color: rgba(var(--theme-border-color-rgb),0.72); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                                            <i class="fa-light fa-bolt text-[10px]"></i>
                                                            {{ __('Auto upload') }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]" style="border-color: rgba(var(--theme-border-color-rgb),0.72); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                                            <i class="fa-light fa-gauge-simple-max text-[10px]"></i>
                                                            {{ __('Max :size', ['size' => $maxUploadMb.' MB']) }}
                                                        </span>
                                                    </div>

                                                    <div
                                                        class="cursor-pointer rounded-[1.75rem] border border-dashed px-7 py-8 text-center transition hover:-translate-y-0.5 hover:border-[rgba(var(--theme-accent-rgb),0.30)]"
                                                        role="button"
                                                        tabindex="0"
                                                        x-on:click="pickFiles()"
                                                        x-on:keydown.enter.prevent="pickFiles()"
                                                        x-on:keydown.space.prevent="pickFiles()"
                                                        x-on:dragenter.prevent="dragging = true"
                                                        x-on:dragover.prevent="dragging = true"
                                                        x-on:dragleave.prevent="dragging = false"
                                                        x-on:drop.prevent="dragging = false; uploadDropped($event.dataTransfer.files)"
                                                        x-bind:style="dragging
                                                            ? 'border-color: rgba(var(--theme-accent-rgb),0.34); background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.16), transparent 58%), linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.06), rgba(var(--theme-border-color-rgb),0.05));'
                                                            : 'border-color: rgba(var(--theme-border-color-rgb),0.72); background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.10), transparent 58%), linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.03), rgba(var(--theme-border-color-rgb),0.04));'"
                                                    >
                                                        <span class="pointer-events-none mx-auto flex h-20 w-20 items-center justify-center rounded-[1.6rem] shadow-inner" style="background: linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.08), rgba(var(--theme-accent-rgb),0.16)); color: var(--theme-accent);">
                                                            <i class="fa-light fa-arrow-up-from-bracket text-[1.7rem]"></i>
                                                        </span>
                                                        <span class="pointer-events-none mt-5 block text-[1.2rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Drag files here or browse') }}</span>
                                                        <span class="pointer-events-none mx-auto mt-2 block max-w-[24rem] text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Images, PDFs, archives, and documents will be attached to this folder right away.') }}</span>
                                                        <button
                                                            type="button"
                                                            class="mt-5 inline-flex cursor-pointer items-center gap-2 rounded-[0.95rem] border px-4 py-2.5 text-sm font-semibold transition hover:opacity-90"
                                                            style="border-color: rgba(var(--theme-accent-rgb),0.22); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);"
                                                            x-on:click.stop="pickFiles()"
                                                        >
                                                            <i class="fa-light fa-folder-arrow-up text-sm"></i>
                                                            {{ __('Choose files') }}
                                                        </button>
                                                    </div>

                                                    <div x-show="queue.length > 0" class="space-y-3">
                                                        <template x-for="item in queue" :key="item.id">
                                                            <div class="rounded-[1.15rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb),0.68); background: linear-gradient(180deg, var(--theme-surface-base), rgba(var(--theme-border-color-rgb),0.035)); box-shadow: 0 10px 26px -22px rgba(15, 23, 42, 0.14);">
                                                                <div class="flex items-center gap-3">
                                                                    <span class="flex h-11 w-11 items-center justify-center rounded-[0.95rem] text-[11px] font-semibold uppercase" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);" x-text="item.name.split('.').pop().slice(0, 6) || 'FILE'"></span>
                                                                    <div class="min-w-0 flex-1">
                                                                        <div class="flex items-start justify-between gap-3">
                                                                            <div class="min-w-0">
                                                                                <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="item.name"></p>
                                                                                <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);" x-text="item.sizeLabel"></p>
                                                                            </div>
                                                                            <div class="text-right">
                                                                                <p class="text-sm font-semibold tabular-nums" style="color: var(--theme-header-text-color);" x-text="`${item.progress}%`"></p>
                                                                            </div>
                                                                        </div>

                                                                        <div class="mt-3 h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.26);">
                                                                            <div class="h-full rounded-full transition-all duration-300" :style="`width: ${item.progress}%; background: ${item.status === 'error' ? 'linear-gradient(90deg, rgba(244,63,94,0.72), rgb(244,63,94))' : 'linear-gradient(90deg, rgba(var(--theme-accent-rgb),0.7), var(--theme-accent))'}`"></div>
                                                                        </div>
                                                                        <div class="mt-2 flex items-center justify-between gap-3 text-xs font-medium">
                                                                            <span :style="item.status === 'error' ? 'color: rgb(225,29,72);' : 'color: var(--theme-muted-text-color);'" x-text="item.message"></span>
                                                                            <span x-show="item.status === 'success'" style="color: rgb(34, 197, 94);">{{ __('Done') }}</span>
                                                                            <span x-show="item.status === 'uploading'" style="color: var(--theme-muted-text-color);">{{ __('Uploading') }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </form>
                                            </div>
                                        </x-ui.modal>

                                        <x-ui.button type="button" variant="outline" block class="h-12 rounded-[1rem]" x-on:click="openNewFolderDialog(); open = false">
                                            <i class="fa-light fa-folder-plus text-sm"></i>
                                            {{ __('Folder') }}
                                        </x-ui.button>
                                    </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            @if ($actionToggles['upload_from_url'])
                                                <x-ui.button type="button" variant="outline" block class="h-12 rounded-[1rem]" x-on:click="uploadUrlDialogOpen = true; open = false">
                                                    <i class="fa-light fa-link text-sm"></i>
                                                    {{ __('From URL') }}
                                                </x-ui.button>
                                            @endif
                                            @if ($actionToggles['quick_delete_action'])
                                                <x-ui.button type="button" variant="outline" block class="h-12 rounded-[1rem]" wire:click="deleteSelectedItems">
                                                    <i class="fa-light fa-trash text-sm"></i>
                                                    {{ __('Delete') }}
                                                </x-ui.button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3 rounded-[1.2rem] border p-4" style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Workspace') }}</p>
                                    <p class="mt-2 text-[1.35rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('My files') }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $currentFolder?->name ?? __('Root level') }}</p>
                                </div>
                                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em]" style="background: var(--theme-surface-soft); color: var(--theme-header-text-color);">
                                    {{ number_format($summary['total']) }} {{ __('items') }}
                                </span>
                            </div>

                            <div class="rounded-[1.2rem] border p-4" style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Overview') }}</p>
                                    <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Live') }}</span>
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <div class="rounded-[1rem] px-3 py-3" style="background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 88%, white 12%), var(--theme-surface-soft));">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Files') }}</p>
                                        <p class="mt-2 text-[1.45rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['files']) }}</p>
                                    </div>
                                    <div class="rounded-[1rem] px-3 py-3" style="background: linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 88%, white 12%), var(--theme-surface-soft));">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Folders') }}</p>
                                        <p class="mt-2 text-[1.45rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['folders']) }}</p>
                                    </div>
                                </div>
                            </div>

                            @if ($categoryCards->isNotEmpty())
                                <div class="rounded-[1.2rem] border p-4" style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Storage types') }}</p>
                                        <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $storageFormatter($summary['storage']) }}</span>
                                    </div>
                                    <div class="mt-4 space-y-4">
                                        @foreach ($categoryCards as $card)
                                            @php($ratio = max(14, min(100, (int) round(($card['bytes'] / max(1, $summary['storage'])) * 100))))
                                            <div class="space-y-2">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="flex items-center gap-2.5 text-sm font-medium" style="color: var(--theme-header-text-color);">
                                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-[0.8rem]" style="background: color-mix(in srgb, {{ $card['color'] }} 12%, white); color: {{ $card['color'] }};">
                                                            <i class="fa-light {{ $card['icon'] }} text-xs"></i>
                                                        </span>
                                                        <span>{{ $card['label'] }}</span>
                                                    </div>
                                                    <span class="text-sm font-medium" style="color: var(--theme-muted-text-color);">{{ $storageFormatter($card['bytes']) }}</span>
                                                </div>
                                                <div class="h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.2);">
                                                    <div class="h-full rounded-full" style="width: {{ $ratio }}%; background: {{ $card['color'] }};"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                    </x-ui.drawer>

                    <div class="hidden 2xl:flex 2xl:items-center 2xl:gap-2.5">
                    <x-ui.dropdown-menu align="right" width="auto" panel-class="min-w-[18rem]">
                        <x-slot:trigger>
                            <x-ui.button variant="outline" class="h-10 shrink-0 justify-center px-4 text-[13px]">
                                <i class="fa-light fa-filter text-xs"></i>
                                {{ __('Filters') }}
                            </x-ui.button>
                        </x-slot:trigger>

                        <div class="space-y-4 p-4">
                            <x-ui.select x-model="filterCategory" x-on:change="applyFilters()" :label="__('Category')">
                                <option value="all">{{ __('All') }}</option>
                                @foreach (['folder', 'image', 'video', 'audio', 'document', 'spreadsheet', 'pdf', 'archive', 'other'] as $category)
                                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                                @endforeach
                            </x-ui.select>

                        </div>
                    </x-ui.dropdown-menu>

                    <x-ui.dropdown-menu align="right" width="auto" panel-class="min-w-[13rem]">
                        <x-slot:trigger>
                            <x-ui.button variant="outline" class="h-10 shrink-0 justify-center px-4 text-[13px]">
                                <i class="fa-light fa-grid-2 text-xs"></i>
                                {{ __('Actions') }}
                            </x-ui.button>
                        </x-slot:trigger>

                        @if ($actionToggles['upload_from_url'])
                            <x-ui.dropdown-menu-item icon="fa-light fa-link" class="w-full" x-on:click.prevent="openRemoteImport('generic')">
                                {{ __('Upload From URL') }}
                            </x-ui.dropdown-menu-item>
                        @endif

                        @if ($actionToggles['google_drive'])
                            <x-ui.dropdown-menu-item icon="fa-brands fa-google-drive" class="w-full" x-on:click.prevent="openGooglePicker()">
                                {{ __('Google Drive') }}
                            </x-ui.dropdown-menu-item>
                        @endif

                        @if ($actionToggles['dropbox'])
                            <x-ui.dropdown-menu-item icon="fa-brands fa-dropbox" class="w-full" x-on:click.prevent="openDropboxChooser()">
                                {{ __('Dropbox') }}
                            </x-ui.dropdown-menu-item>
                        @endif

                        @if ($actionToggles['onedrive'])
                            <x-ui.dropdown-menu-item icon="fa-light fa-cloud" class="w-full" x-on:click.prevent="openOneDrivePicker()">
                                {{ __('OneDrive') }}
                            </x-ui.dropdown-menu-item>
                        @endif

                        @if ($actionToggles['adobe_express'])
                            <x-ui.dropdown-menu-item icon="fa-light fa-wand-magic-sparkles" class="w-full" x-on:click.prevent="openAdobeExpress()">
                                {{ __('Create for Adobe Express') }}
                            </x-ui.dropdown-menu-item>
                        @endif

                        @if ($actionToggles['upload_from_url'] || $actionToggles['google_drive'] || $actionToggles['dropbox'] || $actionToggles['onedrive'] || $actionToggles['adobe_express'])
                            <x-ui.dropdown-menu-divider />
                        @endif

                        <x-ui.dropdown-menu-item icon="fa-light fa-folder-plus" class="w-full" x-on:click.stop="openNewFolderDialog(); open = false">
                            {{ __('New folder') }}
                        </x-ui.dropdown-menu-item>

                        @if ($actionToggles['quick_delete_action'])
                            <x-ui.dropdown-menu-divider />
                            <x-ui.dropdown-menu-item icon="fa-light fa-trash" variant="danger" class="w-full" wire:click="deleteSelectedItems">
                                {{ __('Delete') }}
                            </x-ui.dropdown-menu-item>
                        @endif
                    </x-ui.dropdown-menu>

                    <x-ui.modal width="md" :title="__('Upload files')" :description="__('Drop files into the current folder. Upload starts automatically as soon as you select them.')" closeEvent="appfiles-upload-finished">
                        <x-slot:trigger>
                            <x-ui.button class="h-10 shrink-0 justify-center px-4 text-[13px] shadow-none">
                                <i class="fa-light fa-upload text-xs"></i>
                                {{ __('Upload') }}
                            </x-ui.button>
                        </x-slot:trigger>

                            <div x-data="createUploadDialogState()" class="space-y-5">
                                <form class="space-y-5">
                                <input
                                    x-ref="modalUploadInput"
                                    type="file"
                                    multiple
                                    class="sr-only"
                                    x-on:change="uploadDropped($event.target.files); $event.target.value = ''"
                                >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]" style="border-color: rgba(var(--theme-border-color-rgb),0.72); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-bolt text-[10px]"></i>
                                        {{ __('Auto upload') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em]" style="border-color: rgba(var(--theme-border-color-rgb),0.72); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-gauge-simple-max text-[10px]"></i>
                                        {{ __('Max :size', ['size' => $maxUploadMb.' MB']) }}
                                    </span>
                                </div>

                                <div
                                    class="cursor-pointer rounded-[1.75rem] border border-dashed px-7 py-8 text-center transition hover:-translate-y-0.5 hover:border-[rgba(var(--theme-accent-rgb),0.30)]"
                                    role="button"
                                    tabindex="0"
                                    x-on:click="pickFiles()"
                                    x-on:keydown.enter.prevent="pickFiles()"
                                    x-on:keydown.space.prevent="pickFiles()"
                                    x-on:dragenter.prevent="dragging = true"
                                    x-on:dragover.prevent="dragging = true"
                                    x-on:dragleave.prevent="dragging = false"
                                    x-on:drop.prevent="dragging = false; uploadDropped($event.dataTransfer.files)"
                                    x-bind:style="dragging
                                        ? 'border-color: rgba(var(--theme-accent-rgb),0.34); background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.16), transparent 58%), linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.06), rgba(var(--theme-border-color-rgb),0.05));'
                                        : 'border-color: rgba(var(--theme-border-color-rgb),0.72); background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.10), transparent 58%), linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.03), rgba(var(--theme-border-color-rgb),0.04));'"
                                >
                                    <span class="pointer-events-none mx-auto flex h-20 w-20 items-center justify-center rounded-[1.6rem] shadow-inner" style="background: linear-gradient(180deg, rgba(var(--theme-accent-rgb),0.08), rgba(var(--theme-accent-rgb),0.16)); color: var(--theme-accent);">
                                        <i class="fa-light fa-arrow-up-from-bracket text-[1.7rem]"></i>
                                    </span>
                                    <span class="pointer-events-none mt-5 block text-[1.2rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Drag files here or browse') }}</span>
                                    <span class="pointer-events-none mx-auto mt-2 block max-w-[24rem] text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Images, PDFs, archives, and documents will be attached to this folder right away.') }}</span>
                                    <button
                                        type="button"
                                        class="mt-5 inline-flex cursor-pointer items-center gap-2 rounded-[0.95rem] border px-4 py-2.5 text-sm font-semibold transition hover:opacity-90"
                                        style="border-color: rgba(var(--theme-accent-rgb),0.22); background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);"
                                        x-on:click.stop="pickFiles()"
                                    >
                                        <i class="fa-light fa-folder-arrow-up text-sm"></i>
                                        {{ __('Choose files') }}
                                    </button>
                                </div>

                                <div x-show="queue.length > 0" class="space-y-3">
                                    <template x-for="item in queue" :key="item.id">
                                        <div class="rounded-[1.15rem] border px-4 py-3.5" style="border-color: rgba(var(--theme-border-color-rgb),0.68); background: linear-gradient(180deg, var(--theme-surface-base), rgba(var(--theme-border-color-rgb),0.035)); box-shadow: 0 10px 26px -22px rgba(15, 23, 42, 0.14);">
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-11 w-11 items-center justify-center rounded-[0.95rem] text-[11px] font-semibold uppercase" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);" x-text="item.name.split('.').pop().slice(0, 6) || 'FILE'"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="item.name"></p>
                                                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);" x-text="item.sizeLabel"></p>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-sm font-semibold tabular-nums" style="color: var(--theme-header-text-color);" x-text="`${item.progress}%`"></p>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3 h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.26);">
                                                        <div class="h-full rounded-full transition-all duration-300" :style="`width: ${item.progress}%; background: ${item.status === 'error' ? 'linear-gradient(90deg, rgba(244,63,94,0.72), rgb(244,63,94))' : 'linear-gradient(90deg, rgba(var(--theme-accent-rgb),0.7), var(--theme-accent))'}`"></div>
                                                    </div>
                                                    <div class="mt-2 flex items-center justify-between gap-3 text-xs font-medium">
                                                        <span :style="item.status === 'error' ? 'color: rgb(225,29,72);' : 'color: var(--theme-muted-text-color);'" x-text="item.message"></span>
                                                        <span x-show="item.status === 'success'" style="color: rgb(34, 197, 94);">{{ __('Done') }}</span>
                                                        <span x-show="item.status === 'uploading'" style="color: var(--theme-muted-text-color);">{{ __('Uploading') }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center justify-between gap-3 rounded-[1rem] border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb),0.68); background: var(--theme-surface-soft); color: var(--theme-muted-text-color);">
                                    <p>{{ __('Uploads start automatically after selection.') }}</p>
                                    <p x-show="uploading" class="font-semibold" x-text="`${progress}%`"></p>
                                </div>
                            </form>
                        </div>
                    </x-ui.modal>
                    </div>
                </div>
            </div>
        </header>

        <div
            x-ref="filesRefreshRegion"
            data-files-refresh-region
            data-livewire-component-id="{{ $this->getId() }}"
            data-debug-load-limit="{{ $debugRender['loadLimit'] ?? $loadLimit }}"
            data-debug-folder-count="{{ $debugRender['folderItemsCount'] ?? $folderItems->count() }}"
            data-debug-file-count="{{ $debugRender['fileItemsCount'] ?? $fileItems->count() }}"
            data-debug-matching-file-count="{{ $debugRender['matchingFileItemsCount'] ?? $fileItems->count() }}"
            data-debug-has-more="{{ ($debugRender['hasMoreItems'] ?? $hasMoreItems) ? '1' : '0' }}"
            wire:key="portal-files-refresh-{{ $loadLimit }}-{{ $refreshNonce }}"
        >
        <div class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)] xl:gap-8">
            <aside class="order-2 hidden space-y-4 xl:order-1 xl:block xl:sticky xl:top-[9.5rem] xl:space-y-6 xl:self-start">
                <section class="space-y-4">
                    <div class="rounded-[1.25rem] border px-4 py-4 sm:px-5 sm:py-5" style="border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Workspace') }}</p>
                                <p class="mt-3 text-[1.42rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('My files') }}</p>
                            </div>
                            <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em]" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft); color: var(--theme-header-text-color);">
                                {{ number_format($summary['total']) }} {{ __('items') }}
                            </span>
                        </div>

                        <div class="mt-5 border-t pt-4" style="border-color: {{ $softBorder }};">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Current folder') }}</p>
                                    <p class="mt-1.5 truncate text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $currentFolder?->name ?? __('Root level') }}</p>
                                </div>
                                @if ($currentFolder)
                                    <a href="{{ $buildPortalUrl() }}" class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition hover:opacity-80" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft); color: var(--theme-header-text-color);">{{ __('Root') }}</a>
                                @endif
                            </div>
                        </div>

                        @if (! empty($breadcrumbs))
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @foreach ($breadcrumbs as $crumb)
                                    <a href="{{ $buildPortalUrl(['folder' => $crumb->id_secure]) }}" class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-medium transition hover:opacity-80" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft); color: var(--theme-header-text-color);">
                                        {{ $crumb->name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="rounded-[1.25rem] border px-4 py-4 sm:px-5 sm:py-5" style="border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Overview') }}</p>
                            <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ __('Live') }}</span>
                        </div>

                        <div class="mt-4 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-[1rem] px-3 py-3" style="background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Files') }}</p>
                                    <p class="mt-2 text-[1.45rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['files']) }}</p>
                                </div>
                                <div class="rounded-[1rem] px-3 py-3" style="background: var(--theme-surface-soft);">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Folders') }}</p>
                                    <p class="mt-2 text-[1.45rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ number_format($summary['folders']) }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span style="color: var(--theme-header-text-color);">{{ __('File ratio') }}</span>
                                        <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ $fileShare }}%</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.2);">
                                        <div class="h-full rounded-full" style="width: {{ $fileShare }}%; background: linear-gradient(90deg, rgba(var(--theme-accent-rgb),0.58), var(--theme-accent));"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                        <span style="color: var(--theme-header-text-color);">{{ __('Storage') }}</span>
                                        <span class="font-semibold" style="color: var(--theme-header-text-color);">{{ $storageFormatter($summary['storage']) }}</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.2);">
                                        <div class="h-full rounded-full" style="width: 100%; background: linear-gradient(90deg, rgba(var(--theme-border-color-rgb),0.42), rgba(var(--theme-border-color-rgb),0.9));"></div>
                                    </div>
                                    <p class="mt-2 text-xs" style="color: var(--theme-muted-text-color);">{{ __('Average file size: :size', ['size' => $storageFormatter($avgFileBytes)]) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($categoryCards->isNotEmpty())
                        <div class="rounded-[1.25rem] border px-4 py-4 sm:px-5 sm:py-5" style="border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Storage types') }}</p>
                                <span class="text-xs font-medium" style="color: var(--theme-muted-text-color);">{{ $storageFormatter($summary['storage']) }}</span>
                            </div>

                            <div class="mt-4 space-y-4">
                                @foreach ($categoryCards as $card)
                                    @php($ratio = max(14, min(100, (int) round(($card['bytes'] / max(1, $summary['storage'])) * 100))))
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2.5 text-sm font-medium" style="color: var(--theme-header-text-color);">
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-[0.7rem]" style="background: color-mix(in srgb, {{ $card['color'] }} 12%, white); color: {{ $card['color'] }};">
                                                    <i class="fa-light {{ $card['icon'] }} text-xs"></i>
                                                </span>
                                                <span>{{ $card['label'] }}</span>
                                            </div>
                                            <span class="text-sm font-medium" style="color: var(--theme-muted-text-color);">{{ $storageFormatter($card['bytes']) }}</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full" style="background: rgba(var(--theme-border-color-rgb),0.2);">
                                            <div class="h-full rounded-full" style="width: {{ $ratio }}%; background: {{ $card['color'] }};"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>
            </aside>

            <section data-files-panel class="order-1 space-y-8 xl:order-2 xl:space-y-10">
                @if (!($storageHealth['valid'] ?? true))
                    <x-ui.alert
                        inline
                        variant="warning"
                        :title="$storageHealth['title'] ?? __('Storage needs attention')"
                        :description="$storageHealth['message'] ?? __('The selected storage disk is not ready for uploads.')"
                    />
                @endif

                @if ($currentFolder || ! empty($breadcrumbs))
                    <section class="flex flex-col gap-3 rounded-[1.25rem] border px-4 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft);">
                        <div class="flex min-w-0 flex-wrap items-center gap-2 text-sm" style="color: var(--theme-muted-text-color);">
                            <a href="{{ $buildPortalUrl() }}" class="inline-flex items-center rounded-full border px-3 py-1.5 font-medium transition hover:opacity-80" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color); background: var(--theme-surface-base);">
                                {{ __('Root') }}
                            </a>
                            @foreach ($breadcrumbs as $crumb)
                                <i class="fa-light fa-chevron-right text-[10px]"></i>
                                <a href="{{ $buildPortalUrl(['folder' => $crumb->id_secure]) }}" class="truncate font-medium transition hover:opacity-75" style="color: var(--theme-header-text-color);">
                                    {{ $crumb->name }}
                                </a>
                            @endforeach
                            @if ($currentFolder)
                                <i class="fa-light fa-chevron-right text-[10px]"></i>
                                <span class="truncate font-semibold" style="color: var(--theme-header-text-color);">{{ $currentFolder->name }}</span>
                            @endif
                        </div>

                        @if ($currentFolder)
                            <a href="{{ $backTarget !== '' ? $buildPortalUrl(['folder' => $backTarget]) : $buildPortalUrl() }}" class="inline-flex items-center gap-2 rounded-[0.9rem] border px-3 py-2 text-sm font-medium transition hover:opacity-80" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color); background: var(--theme-surface-base);">
                                <i class="fa-light fa-arrow-left text-xs"></i>
                                {{ __('Back') }}
                            </a>
                        @endif
                    </section>
                @endif

                @if ($folderItems->isNotEmpty())
                    <section class="space-y-4">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Folder layer') }}</p>
                                <h2 class="mt-1 text-[1.7rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Folders') }}</h2>
                            </div>
                            <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ number_format($folderItems->count()) }} {{ __('items') }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($folderItems as $item)
                                <article wire:key="portal-folder-{{ $item->id_secure }}" data-folder-card-id="{{ $item->id_secure }}" class="group w-full rounded-[1.4rem] border px-5 py-5 transition hover:-translate-y-0.5 hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:shadow-[0_20px_40px_-34px_rgba(15,23,42,0.24)]" style="border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-[1rem]" style="background: rgba(var(--theme-accent-rgb),0.08); color: var(--theme-accent);">
                                            <i class="fa-light fa-folder-open text-[1.35rem]"></i>
                                        </span>
                                        <div class="flex items-center gap-2">
                                            <button type="button" data-folder-edit-button data-folder-id="{{ $item->id_secure }}" data-folder-name="{{ $item->name }}" data-folder-note="{{ (string) ($item->note ?? '') }}" x-on:click.prevent="openEditFolderDialog($el.dataset.folderId, $el.dataset.folderName, $el.dataset.folderNote)" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.85rem] border transition hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-pen text-xs"></i>
                                            </button>

                                            <button type="button" x-on:click.prevent="openDeleteDialog(@js($item->id_secure), @js($item->name), { title: @js(__('Delete folder')), description: @js(__('This action will permanently remove the folder and all files inside it.')), button: @js(__('Delete folder')) })" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.85rem] border transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-trash text-xs"></i>
                                            </button>

                                            <a href="{{ $buildPortalUrl(['folder' => $item->id_secure]) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-[0.85rem] border transition group-hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-arrow-right text-xs"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <p data-folder-card-name class="text-lg font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ $item->name }}</p>
                                        <p data-folder-card-note class="mt-2 line-clamp-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $item->note ?: __('Open this folder to manage its files.') }}</p>
                                    </div>

                                    <div class="mt-5 flex items-center justify-between text-sm" style="color: var(--theme-muted-text-color);">
                                        <span>{{ __('Updated') }} {{ $item->updated_at?->format('M d') }}</span>
                                        <span>{{ $item->humanSize() }}</span>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="space-y-4">
                    @if ($folderItems->isNotEmpty() || $fileItems->isNotEmpty())
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Asset grid') }}</p>
                                <h2 class="mt-1 text-[1.7rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">{{ __('Files') }}</h2>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="inline-flex rounded-[0.95rem] border p-1" style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);">
                                    <button type="button" @click="setFileView('grid')" :aria-pressed="fileView === 'grid'" class="inline-flex items-center gap-2 rounded-[0.7rem] px-3 py-2 text-sm font-medium transition" :style="fileView === 'grid' ? 'background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'color: var(--theme-muted-text-color);'">
                                        <i class="fa-light fa-grid-2 text-xs"></i>
                                        {{ __('Grid') }}
                                    </button>
                                    <button type="button" @click="setFileView('list')" :aria-pressed="fileView === 'list'" class="inline-flex items-center gap-2 rounded-[0.7rem] px-3 py-2 text-sm font-medium transition" :style="fileView === 'list' ? 'background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);' : 'color: var(--theme-muted-text-color);'">
                                        <i class="fa-light fa-list text-xs"></i>
                                        {{ __('List') }}
                                    </button>
                                </div>
                                <p class="text-sm" style="color: var(--theme-muted-text-color);">{{ number_format($fileItems->count()) }} {{ __('items') }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($fileItems->isNotEmpty())
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[1.1rem] border px-4 py-3" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft);">
                            <div class="flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    x-on:click="toggleSelectAllLoadedItems()"
                                    class="inline-flex items-center gap-2 rounded-[0.85rem] border px-3 py-2 text-sm font-medium transition hover:opacity-80"
                                    style="border-color: {{ $softBorder }}; background: var(--theme-surface-base); color: var(--theme-header-text-color);"
                                >
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-[0.4rem] border shadow-[0_1px_2px_rgba(15,23,42,0.06)]"
                                          :style="allLoadedItemsSelected() ? 'border-color: var(--theme-accent); background: var(--theme-accent); color: white;' : 'border-color: rgba(203,213,225,0.85); background: rgba(255,255,255,0.92); color: transparent;'">
                                        <i class="fa-light fa-check text-[11px]"></i>
                                    </span>
                                    <span x-text="allLoadedItemsSelected() ? @js(__('Uncheck all')) : @js(__('Check all'))"></span>
                                </button>

                                <button x-show="selectedCount() > 0" type="button" x-on:click="clearClientSelection()" class="inline-flex items-center gap-2 rounded-[0.85rem] border px-3 py-2 text-sm font-medium transition hover:opacity-80" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color); background: var(--theme-surface-base);">
                                        <i class="fa-light fa-rotate-left text-xs"></i>
                                        {{ __('Clear') }}
                                    </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-sm font-medium" style="color: var(--theme-muted-text-color);">
                                    <span x-text="selectedCount()"></span> {{ __('selected') }}
                                </span>

                                @if ($actionToggles['quick_delete_action'])
                                    <button type="button" x-on:click="deleteSelectedItemsFromSelection()" x-bind:disabled="selectedCount() === 0" class="inline-flex items-center gap-2 rounded-[0.85rem] border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50" style="border-color: rgba(244,63,94,0.22); color: rgb(225,29,72); background: rgba(255,241,242,0.72);">
                                        <i class="fa-light fa-trash-can text-xs"></i>
                                        {{ __('Delete selected') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div x-show="fileView === 'grid'" x-ref="fileGrid" data-file-grid class="grid auto-rows-max content-start items-start gap-4 [grid-template-columns:repeat(2,minmax(0,1fr))] sm:gap-5 sm:[grid-template-columns:repeat(auto-fill,minmax(220px,1fr))] 2xl:[grid-template-columns:repeat(auto-fill,minmax(240px,1fr))]">
                        @forelse ($fileItems as $item)
                            <article wire:key="portal-file-grid-{{ $item->id_secure }}" data-file-grid-item data-item-id="{{ $item->id_secure }}" class="group relative z-0 overflow-visible rounded-[1.35rem] border transition hover:z-10 focus-within:z-[80] hover:-translate-y-0.5 hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:shadow-[0_22px_42px_-34px_rgba(15,23,42,0.24)]" style="z-index: 0; border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                                <div class="relative aspect-[1/0.78] overflow-hidden" style="background: var(--theme-surface-soft);">
                                        @if ($item->is_image)
                                            <div wire:ignore data-image-shell x-data="{ init() { const image = this.$refs.image; const placeholder = this.$refs.placeholder; const fallback = this.$refs.fallback; const reveal = () => { image.classList.remove('opacity-0'); placeholder.classList.add('hidden'); }; const fail = () => { if (image.dataset.retried !== 'true') { image.dataset.retried = 'true'; image.src = `${image.src}${image.src.includes('?') ? '&' : '?'}retry=${Date.now()}`; return; } image.remove(); placeholder.classList.add('hidden'); fallback.classList.remove('hidden'); fallback.classList.add('flex'); }; if (image.complete) { if (image.naturalWidth > 0) { reveal(); } else { fail(); } return; } image.addEventListener('load', reveal, { once: true }); image.addEventListener('error', fail, { once: true }); } }" class="relative h-full w-full">
                                            <div x-ref="placeholder" data-image-placeholder class="absolute inset-3 z-0 flex animate-pulse items-center justify-center rounded-[1rem]" style="background: linear-gradient(135deg, rgba(var(--theme-border-color-rgb),0.12), rgba(var(--theme-accent-rgb),0.08));">
                                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-full" style="background: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);">
                                                    <i class="fa-light fa-loader animate-spin text-sm"></i>
                                                </span>
                                            </div>
                                            <div x-ref="fallback" data-image-fallback class="absolute inset-0 z-10 hidden items-center justify-center">
                                                <span class="inline-flex h-20 w-20 items-center justify-center rounded-[1.35rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                                    <i class="fa-light fa-image text-4xl"></i>
                                                </span>
                                            </div>
                                            <img x-ref="image" src="{{ route('portal.files.preview', $item) }}" alt="{{ $item->name }}" loading="lazy" decoding="async" class="relative z-[1] h-full w-full object-cover opacity-0 transition duration-300 group-hover:scale-[1.03]">
                                        </div>
                                        @elseif ($item->category === 'video')
                                            <div class="relative h-full w-full">
                                                <video
                                                    preload="metadata"
                                                    muted
                                                    playsinline
                                                    class="h-full w-full object-cover"
                                                    x-data
                                                    x-init="$el.addEventListener('loadeddata', () => { try { $el.currentTime = 0.1 } catch (e) {} }, { once: true })"
                                                    x-on:mouseenter="$el.play().catch(() => {})"
                                                    x-on:mouseleave="$el.pause(); try { $el.currentTime = 0.1 } catch (e) {}"
                                                >
                                                    <source src="{{ route('portal.files.preview', $item) }}" type="{{ $item->mime_type ?: 'video/mp4' }}">
                                                </video>
                                                <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-full shadow-sm" style="background: rgba(255,255,255,0.82); color: var(--theme-header-text-color);">
                                                        <i class="fa-solid fa-play ml-1 text-sm"></i>
                                                    </span>
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex h-full items-center justify-center">
                                                <span class="inline-flex h-20 w-20 items-center justify-center rounded-[1.35rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                                    <i class="fa-light {{ match($item->category) { 'video' => 'fa-film', 'audio' => 'fa-waveform-lines', 'pdf' => 'fa-file-pdf', 'spreadsheet' => 'fa-file-spreadsheet', 'document' => 'fa-file-lines', 'archive' => 'fa-file-zipper', default => 'fa-file' } }} text-4xl"></i>
                                            </span>
                                        </div>
                                    @endif

                                    <div class="absolute left-3 top-3 z-10">
                                        <span
                                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]"
                                            style="{{ $item->is_image
                                                ? 'border-color: rgba(var(--theme-accent-rgb),0.42); background: rgba(255,255,255,0.94); color: rgb(var(--theme-accent-rgb)); box-shadow: 0 6px 18px -12px rgba(15,23,42,0.35);'
                                                : 'border-color: rgba(148,163,184,0.38); background: rgba(255,255,255,0.94); color: rgb(51 65 85); box-shadow: 0 6px 18px -12px rgba(15,23,42,0.35);' }}"
                                        >
                                            {{ $item->typeLabel() }}
                                        </span>
                                    </div>

                                    <x-ui.checkbox
                                        :id="'portal-file-grid-select-'.$item->id_secure"
                                        name="selected_files"
                                        :value="$item->id_secure"
                                        data-select-checkbox
                                        :data-select-id="$item->id_secure"
                                        class="absolute right-3 top-3 z-10 gap-0 border-0 bg-transparent p-0 shadow-none [&>span:first-of-type]:border-slate-300/85 dark:[&>span:first-of-type]:border-slate-600/85 [&>span:first-of-type]:shadow-none [&>span:first-of-type]:bg-white/92 dark:[&>span:first-of-type]:bg-slate-900/92 [&>input:checked+span]:border-[color:var(--theme-accent)] [&>input:checked+span]:bg-[color:var(--theme-accent)] [&>input:checked+span]:text-white"
                                    >
                                        <span class="sr-only">{{ __('Select :name', ['name' => $item->name]) }}</span>
                                    </x-ui.checkbox>
                                </div>

                                <div class="space-y-3 px-4 py-4">
                                    <div>
                                        <p class="truncate text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $item->name }}</p>
                                        <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $item->mime_type ?: __('file asset') }}</p>
                                    </div>

                                    <div class="flex items-center justify-between text-sm" style="color: var(--theme-muted-text-color);">
                                        <span>{{ $item->humanSize() }}</span>
                                        <span>{{ $item->updated_at?->format('M d') }}</span>
                                    </div>

                                    <div class="flex items-center justify-between border-t pt-3" style="border-color: {{ $softBorder }};">
                                        <div class="flex items-center gap-2">
                                            @if ($item->category === 'video')
                                                <button type="button" class="hidden items-center gap-2 text-sm font-medium transition hover:opacity-70 sm:inline-flex" style="color: var(--theme-header-text-color);" x-on:click="promptPlayVideo('{{ route('portal.files.preview', $item) }}', @js($item->name))">
                                                    <i class="fa-light fa-play text-xs"></i>
                                                    {{ __('Play') }}
                                                </button>
                                            @endif

                                            @if ($imageEditorEnabled && $item->isEditableImage())
                                                <a href="{{ route('portal.files.edit-image', ['file' => $item, 'return' => request()->fullUrl()]) }}" class="inline-flex items-center gap-2 text-sm font-medium transition hover:opacity-70" style="color: var(--theme-header-text-color);">
                                                    <i class="fa-light fa-pen-ruler text-xs"></i>
                                                    <span>{{ __('Edit image') }}</span>
                                                </a>
                                            @endif
                                            <a href="{{ route('portal.files.download', $item) }}" class="hidden items-center gap-2 text-sm font-medium transition hover:opacity-70 sm:inline-flex" style="color: var(--theme-header-text-color);">
                                                <i class="fa-light fa-download text-xs"></i>
                                                {{ __('Download') }}
                                            </a>
                                        </div>

                                        <div class="ml-auto flex items-center gap-2">
                                            <details class="relative z-[60] sm:hidden" ontoggle="const card=this.closest('[data-file-grid-item]'); if(card){card.style.zIndex=this.open?'80':'0';}">
                                                <summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-[0.8rem] border transition hover:opacity-80 [&::-webkit-details-marker]:hidden" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                                    <i class="fa-light fa-ellipsis text-sm"></i>
                                                </summary>

                                                <div class="absolute right-0 top-full z-[70] mt-2 flex min-w-[10rem] flex-col overflow-hidden rounded-[0.9rem] border shadow-[0_18px_38px_-24px_rgba(15,23,42,0.28)]" style="border-color: {{ $softBorder }}; background: var(--theme-surface-base);">
                                                    <a href="{{ route('portal.files.download', $item) }}" class="inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium transition hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="color: var(--theme-header-text-color);">
                                                        <i class="fa-light fa-download text-xs"></i>
                                                        {{ __('Download') }}
                                                    </a>
                                                    <button type="button" x-on:click.prevent="openDeleteDialog(@js($item->id_secure), @js($item->name)); $el.closest('details')?.removeAttribute('open')" class="inline-flex items-center gap-2 px-3 py-2.5 text-left text-sm font-medium transition hover:bg-rose-50 hover:text-rose-600" style="color: var(--theme-muted-text-color);">
                                                        <i class="fa-light fa-trash text-xs"></i>
                                                        {{ __('Delete') }}
                                                    </button>
                                                </div>
                                            </details>

                                            <button type="button" x-on:click.prevent="openDeleteDialog(@js($item->id_secure), @js($item->name))" class="hidden h-9 w-9 items-center justify-center rounded-[0.8rem] border transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 sm:inline-flex" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div
                                x-data="{
                                    dragging: false,
                                    uploadDropped(files) {
                                        if (!files || files.length === 0) return;
                                        uploadFiles(files);
                                        this.dragging = false;
                                    }
                                }"
                                class="col-span-full rounded-[1.5rem] border border-dashed px-6 py-12 text-center"
                                x-on:dragenter.prevent="dragging = true"
                                x-on:dragover.prevent="dragging = true"
                                x-on:dragleave.prevent="dragging = false"
                                x-on:drop.prevent="uploadDropped($event.dataTransfer.files)"
                                x-bind:style="dragging
                                    ? 'border-color: rgba(var(--theme-accent-rgb),0.34); background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.14), transparent 58%), var(--theme-surface-soft);'
                                    : 'border-color: rgba(var(--theme-border-color-rgb),0.72); background: var(--theme-surface-soft);'"
                            >
                                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-[1.25rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                    <i class="fa-light fa-arrow-up-from-bracket text-2xl"></i>
                                </span>
                                <p class="mt-5 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Drag and drop files here to upload') }}</p>
                                <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Or choose files from your device and upload them into this folder right away.') }}</p>
                                <div class="mt-5">
                                    <input
                                        x-ref="emptyUploadInput"
                                        type="file"
                                        multiple
                                        class="sr-only"
                                        x-on:change="
                                            uploadFiles($event.target.files);
                                            $event.target.value = '';
                                        "
                                    >
                                    <x-ui.button type="button" x-on:click="$refs.emptyUploadInput.click()">
                                        {{ __('Upload files') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    <div x-show="fileView === 'list'" class="overflow-hidden rounded-[1.5rem] border" style="border-color: {{ $panelBorder }}; background: var(--theme-surface-base);">
                        @if ($fileItems->isNotEmpty())
                            <div class="hidden grid-cols-[44px_minmax(0,1.8fr)_140px_180px_120px] items-center gap-4 border-b px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] lg:grid" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color); background: var(--theme-surface-soft);">
                                <div>{{ __('Pick') }}</div>
                                <div>{{ __('File') }}</div>
                                <div>{{ __('Type') }}</div>
                                <div>{{ __('Updated') }}</div>
                                <div class="text-right">{{ __('Actions') }}</div>
                            </div>
                        @endif

                        <div x-ref="fileList" data-file-list>
                        @forelse ($fileItems as $item)
                            <article wire:key="portal-file-list-{{ $item->id_secure }}" data-file-list-item data-item-id="{{ $item->id_secure }}" class="group border-b px-4 py-4 transition last:border-b-0 hover:bg-[rgba(15,23,42,0.02)] lg:px-5" style="border-color: {{ $softBorder }};">
                                <div class="flex flex-col gap-4 lg:grid lg:grid-cols-[44px_minmax(0,1.8fr)_140px_180px_120px] lg:items-center lg:gap-4">
                                    <div class="flex items-center lg:justify-center">
                                        <x-ui.checkbox
                                            :id="'portal-file-list-select-'.$item->id_secure"
                                            name="selected_files"
                                            :value="$item->id_secure"
                                            data-select-checkbox
                                            :data-select-id="$item->id_secure"
                                            class="gap-0 [&>span:first-of-type]:border-slate-300/85 dark:[&>span:first-of-type]:border-slate-600/85 [&>span:first-of-type]:shadow-none [&>span:first-of-type]:bg-white/92 dark:[&>span:first-of-type]:bg-slate-900/92 [&>input:checked+span]:border-[color:var(--theme-accent)] [&>input:checked+span]:bg-[color:var(--theme-accent)] [&>input:checked+span]:text-white"
                                        >
                                            <span class="sr-only">{{ __('Select :name', ['name' => $item->name]) }}</span>
                                        </x-ui.checkbox>
                                    </div>

                                    <div class="flex min-w-0 items-center gap-4">
                                        <div class="relative h-14 w-14 overflow-hidden rounded-[1rem] border" style="border-color: {{ $softBorder }}; background: var(--theme-surface-soft);">
                                            @if ($item->is_image)
                                                <div wire:ignore data-image-shell x-data="{ init() { const image = this.$refs.image; const placeholder = this.$refs.placeholder; const fallback = this.$refs.fallback; const reveal = () => { image.classList.remove('opacity-0'); placeholder.classList.add('hidden'); }; const fail = () => { if (image.dataset.retried !== 'true') { image.dataset.retried = 'true'; image.src = `${image.src}${image.src.includes('?') ? '&' : '?'}retry=${Date.now()}`; return; } image.remove(); placeholder.classList.add('hidden'); fallback.classList.remove('hidden'); fallback.classList.add('flex'); }; if (image.complete) { if (image.naturalWidth > 0) { reveal(); } else { fail(); } return; } image.addEventListener('load', reveal, { once: true }); image.addEventListener('error', fail, { once: true }); } }" class="relative h-full w-full">
                                                    <div x-ref="placeholder" data-image-placeholder class="absolute inset-2 z-0 flex animate-pulse items-center justify-center rounded-[0.8rem]" style="background: linear-gradient(135deg, rgba(var(--theme-border-color-rgb),0.12), rgba(var(--theme-accent-rgb),0.08));">
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full" style="background: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);">
                                                            <i class="fa-light fa-loader animate-spin text-[10px]"></i>
                                                        </span>
                                                    </div>
                                                    <div x-ref="fallback" data-image-fallback class="absolute inset-0 z-10 hidden items-center justify-center">
                                                        <i class="fa-light fa-image text-lg" style="color: var(--theme-accent);"></i>
                                                    </div>
                                                <img x-ref="image" src="{{ route('portal.files.preview', $item) }}" alt="{{ $item->name }}" loading="lazy" decoding="async" class="relative z-[1] h-full w-full object-cover opacity-0 transition duration-300">
                                            </div>
                                            @elseif ($item->category === 'video')
                                                <div class="relative h-full w-full">
                                                    <video
                                                        preload="metadata"
                                                        muted
                                                        playsinline
                                                        class="h-full w-full object-cover"
                                                        x-data
                                                        x-init="$el.addEventListener('loadeddata', () => { try { $el.currentTime = 0.1 } catch (e) {} }, { once: true })"
                                                        x-on:mouseenter="$el.play().catch(() => {})"
                                                        x-on:mouseleave="$el.pause(); try { $el.currentTime = 0.1 } catch (e) {}"
                                                    >
                                                        <source src="{{ route('portal.files.preview', $item) }}" type="{{ $item->mime_type ?: 'video/mp4' }}">
                                                    </video>
                                                    <span class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full shadow-sm" style="background: rgba(255,255,255,0.82); color: var(--theme-header-text-color);">
                                                            <i class="fa-solid fa-play ml-0.5 text-[9px]"></i>
                                                        </span>
                                                    </span>
                                                </div>
                                            @else
                                                <div class="flex h-full items-center justify-center">
                                                    <i class="fa-light {{ match($item->category) { 'video' => 'fa-film', 'audio' => 'fa-waveform-lines', 'pdf' => 'fa-file-pdf', 'spreadsheet' => 'fa-file-spreadsheet', 'document' => 'fa-file-lines', 'archive' => 'fa-file-zipper', default => 'fa-file' } }} text-lg" style="color: var(--theme-accent);"></i>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">{{ $item->name }}</p>
                                                <x-ui.badge variant="{{ $item->is_image ? 'primary' : 'neutral' }}">{{ $item->typeLabel() }}</x-ui.badge>
                                            </div>
                                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm" style="color: var(--theme-muted-text-color);">
                                                <span>{{ $item->mime_type ?: __('file asset') }}</span>
                                                <span class="hidden sm:inline">•</span>
                                                <span>{{ $item->humanSize() }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-sm" style="color: var(--theme-muted-text-color);">
                                        <span class="mr-2 inline lg:hidden">{{ __('Type:') }}</span>
                                        {{ $item->typeLabel() }}
                                    </div>

                                    <div class="text-sm" style="color: var(--theme-muted-text-color);">
                                        <span class="mr-2 inline lg:hidden">{{ __('Updated:') }}</span>
                                        {{ $item->updated_at?->format('M d, Y') }}
                                    </div>

                                    <div class="flex items-center justify-end gap-2">
                                        @if ($item->category === 'video')
                                            <button type="button" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color);" x-on:click="promptPlayVideo('{{ route('portal.files.preview', $item) }}', @js($item->name))">
                                                <i class="fa-light fa-play text-xs"></i>
                                            </button>
                                        @endif
                                        @if ($imageEditorEnabled && $item->isEditableImage())
                                            <a href="{{ route('portal.files.edit-image', ['file' => $item, 'return' => request()->fullUrl()]) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color);">
                                                <i class="fa-light fa-pen-ruler text-xs"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('portal.files.download', $item) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color);">
                                            <i class="fa-light fa-download text-xs"></i>
                                        </a>
                                        <button type="button" x-on:click.prevent="openDeleteDialog(@js($item->id_secure), @js($item->name))" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" style="border-color: {{ $softBorder }}; color: var(--theme-muted-text-color);">
                                            <i class="fa-light fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div
                                x-data="{
                                    dragging: false,
                                    uploadDropped(files) {
                                        if (!files || files.length === 0) return;
                                        uploadFiles(files);
                                        this.dragging = false;
                                    }
                                }"
                                class="px-6 py-12 text-center"
                                x-on:dragenter.prevent="dragging = true"
                                x-on:dragover.prevent="dragging = true"
                                x-on:dragleave.prevent="dragging = false"
                                x-on:drop.prevent="uploadDropped($event.dataTransfer.files)"
                                x-bind:style="dragging
                                    ? 'background: radial-gradient(circle at top, rgba(var(--theme-accent-rgb),0.14), transparent 58%), var(--theme-surface-soft);'
                                    : 'background: var(--theme-surface-soft);'"
                            >
                                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-[1.25rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);">
                                    <i class="fa-light fa-arrow-up-from-bracket text-2xl"></i>
                                </span>
                                <p class="mt-5 text-base font-semibold" style="color: var(--theme-header-text-color);">{{ __('Drag and drop files here to upload') }}</p>
                                <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Or choose files from your device and upload them into this folder right away.') }}</p>
                                <div class="mt-5">
                                    <input
                                        x-ref="emptyListUploadInput"
                                        type="file"
                                        multiple
                                        class="sr-only"
                                        x-on:change="
                                            uploadFiles($event.target.files);
                                            $event.target.value = '';
                                        "
                                    >
                                    <x-ui.button type="button" x-on:click="$refs.emptyListUploadInput.click()">
                                        {{ __('Upload files') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @endforelse
                        </div>
                    </div>
                </section>
            </section>
        </div>

        @if ($hasMoreItems)
        <div
            wire:key="portal-load-more-{{ $loadLimit }}"
            data-load-more-sentinel
            x-show="hasMoreItems"
            class="flex h-20 flex-col items-center justify-center gap-1"
        >
            <div x-show="loadMoreInFlight" class="inline-flex items-center gap-2 text-sm" style="color: var(--theme-muted-text-color);">
                <i class="fa-light fa-loader animate-spin text-xs"></i>
                {{ __('Loading more files...') }}
            </div>
            <button
                type="button"
                x-show="!loadMoreInFlight"
                x-on:click="triggerLoadMore('manual')"
                class="inline-flex items-center gap-2 rounded-[0.9rem] border px-4 py-2 text-sm font-medium transition hover:opacity-80"
                style="border-color: {{ $softBorder }}; color: var(--theme-header-text-color); background: var(--theme-surface-base);"
            >
                <i class="fa-light fa-arrow-down"></i>
                {{ __('Load more files') }}
            </button>
        </div>
        @endif
        </div>

@once
    <script>
        (() => {
            if (window.AppFilesPortal) {
                return;
            }

            const logRenderState = (root) => {
                const scopes = Array.from(document.querySelectorAll('[data-files-refresh-region]'));

                if (scopes.length === 0) {
                    console.log('[PortalFiles:render]', { missing: 'filesRefreshRegion' });
                    return;
                }

                console.log('[PortalFiles:render:all]', scopes.map((scope, index) => ({
                    index,
                    componentId: scope.dataset.livewireComponentId || null,
                    loadLimit: Number(scope.dataset.debugLoadLimit || 0),
                    folderCountFromServer: Number(scope.dataset.debugFolderCount || 0),
                    fileCountFromServer: Number(scope.dataset.debugFileCount || 0),
                    matchingFileCountFromServer: Number(scope.dataset.debugMatchingFileCount || 0),
                    hasMoreFromServer: scope.dataset.debugHasMore === '1',
                    gridDomCount: scope.querySelectorAll('[data-file-grid-item]').length,
                    listDomCount: scope.querySelectorAll('[data-file-list-item]').length,
                    folderDomCount: scope.querySelectorAll('[data-folder-card-id]').length,
                    isConnected: scope.isConnected,
                })));

                const scope = root?.closest?.('[data-files-refresh-region]')
                    || root?.querySelector?.('[data-files-refresh-region]')
                    || scopes[0];

                console.log('[PortalFiles:render]', {
                    componentId: scope.dataset.livewireComponentId || null,
                    loadLimit: Number(scope.dataset.debugLoadLimit || 0),
                    folderCountFromServer: Number(scope.dataset.debugFolderCount || 0),
                    fileCountFromServer: Number(scope.dataset.debugFileCount || 0),
                    matchingFileCountFromServer: Number(scope.dataset.debugMatchingFileCount || 0),
                    hasMoreFromServer: scope.dataset.debugHasMore === '1',
                    gridDomCount: scope.querySelectorAll('[data-file-grid-item]').length,
                    listDomCount: scope.querySelectorAll('[data-file-list-item]').length,
                    folderDomCount: scope.querySelectorAll('[data-folder-card-id]').length,
                });
            };

            const escapeHtml = (value = '') => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const iconClassForCategory = (category = '') => {
                switch (String(category || '').trim()) {
                    case 'video': return 'fa-film';
                    case 'audio': return 'fa-waveform-lines';
                    case 'pdf': return 'fa-file-pdf';
                    case 'spreadsheet': return 'fa-file-spreadsheet';
                    case 'document': return 'fa-file-lines';
                    case 'archive': return 'fa-file-zipper';
                    default: return 'fa-file';
                }
            };

            const renderTypeBadge = (item, surface = 'overlay') => {
                const label = escapeHtml(item?.typeLabel || item?.category || 'File');
                const isImage = Boolean(item?.isImage);
                const style = isImage
                    ? surface === 'overlay'
                        ? 'border-color: rgba(var(--theme-accent-rgb),0.42); background: rgba(255,255,255,0.94); color: rgb(var(--theme-accent-rgb)); box-shadow: 0 6px 18px -12px rgba(15,23,42,0.35);'
                        : 'border-color: rgba(var(--theme-accent-rgb),0.28); background: rgba(var(--theme-accent-rgb),0.12); color: rgb(var(--theme-accent-rgb));'
                    : surface === 'soft'
                        ? 'border-color: rgb(226 232 240); background: rgb(248 250 252); color: rgb(71 85 105);'
                        : 'border-color: rgba(148,163,184,0.38); background: rgba(255,255,255,0.94); color: rgb(51 65 85); box-shadow: 0 6px 18px -12px rgba(15,23,42,0.35);';

                return `<span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" style="${style}">${label}</span>`;
            };

            const getRootState = (node) => node?.closest('[data-appfiles-portal-root]')?._x_dataStack?.[0] || null;

            const initImageShells = (scope = document) => {
                scope.querySelectorAll('[data-image-shell]:not([data-image-shell-bound])').forEach((shell) => {
                    shell.dataset.imageShellBound = 'true';

                    const image = shell.querySelector('[data-image-node]');
                    const placeholder = shell.querySelector('[data-image-placeholder]');
                    const fallback = shell.querySelector('[data-image-fallback]');

                    if (!image || !placeholder || !fallback) {
                        return;
                    }

                    const reveal = () => {
                        image.classList.remove('opacity-0');
                        placeholder.classList.add('hidden');
                    };

                    const fail = () => {
                        if (image.dataset.retried !== 'true') {
                            image.dataset.retried = 'true';
                            image.src = `${image.src}${image.src.includes('?') ? '&' : '?'}retry=${Date.now()}`;
                            return;
                        }

                        image.remove();
                        placeholder.classList.add('hidden');
                        fallback.classList.remove('hidden');
                        fallback.classList.add('flex');
                    };

                    if (image.complete) {
                        if (image.naturalWidth > 0) {
                            reveal();
                        } else {
                            fail();
                        }

                        return;
                    }

                    image.addEventListener('load', reveal, { once: true });
                    image.addEventListener('error', fail, { once: true });
                });
            };

            const hydrateNewNodes = () => {
                initImageShells(document);

                if (window.livewire?.rescan) {
                    window.livewire.rescan();
                } else if (window.Livewire?.rescan) {
                    window.Livewire.rescan();
                }
            };

            const renderPreview = (item, compact = false) => {
                if (item?.isImage) {
                    return compact
                        ? `<div data-image-shell class="relative h-full w-full"><div data-image-placeholder class="absolute inset-2 z-0 flex animate-pulse items-center justify-center rounded-[0.8rem]" style="background: linear-gradient(135deg, rgba(var(--theme-border-color-rgb),0.12), rgba(var(--theme-accent-rgb),0.08));"><span class="inline-flex h-7 w-7 items-center justify-center rounded-full" style="background: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);"><i class="fa-light fa-loader animate-spin text-[10px]"></i></span></div><div data-image-fallback class="absolute inset-0 z-10 hidden items-center justify-center"><i class="fa-light fa-image text-lg" style="color: var(--theme-accent);"></i></div><img data-image-node src="${escapeHtml(item.previewUrl || item.url || '')}" alt="${escapeHtml(item.name || '')}" loading="lazy" decoding="async" class="relative z-[1] h-full w-full object-cover opacity-0 transition duration-300"></div>`
                        : `<div data-image-shell class="relative h-full w-full"><div data-image-placeholder class="absolute inset-3 z-0 flex animate-pulse items-center justify-center rounded-[1rem]" style="background: linear-gradient(135deg, rgba(var(--theme-border-color-rgb),0.12), rgba(var(--theme-accent-rgb),0.08));"><span class="inline-flex h-11 w-11 items-center justify-center rounded-full" style="background: rgba(var(--theme-surface-base-rgb,255,255,255),0.78); color: var(--theme-muted-text-color);"><i class="fa-light fa-loader animate-spin text-sm"></i></span></div><div data-image-fallback class="absolute inset-0 z-10 hidden items-center justify-center"><span class="inline-flex h-20 w-20 items-center justify-center rounded-[1.35rem]" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);"><i class="fa-light fa-image text-4xl"></i></span></div><img data-image-node src="${escapeHtml(item.previewUrl || item.url || '')}" alt="${escapeHtml(item.name || '')}" loading="lazy" decoding="async" class="relative z-[1] h-full w-full object-cover opacity-0 transition duration-300 group-hover:scale-[1.03]"></div>`;
                }

                if (item?.category === 'video') {
                    return `<div class="relative h-full w-full"><video preload="metadata" muted playsinline class="h-full w-full object-cover"><source src="${escapeHtml(item.previewUrl || item.url || '')}" type="${escapeHtml(item.mimeType || 'video/mp4')}"></video><span class="pointer-events-none absolute inset-0 flex items-center justify-center"><span class="inline-flex ${compact ? 'h-7 w-7' : 'h-14 w-14'} items-center justify-center rounded-full shadow-sm" style="background: rgba(255,255,255,0.82); color: var(--theme-header-text-color);"><i class="fa-solid fa-play ${compact ? 'ml-0.5 text-[9px]' : 'ml-1 text-sm'}"></i></span></span></div>`;
                }

                return `<div class="flex h-full items-center justify-center"><span class="inline-flex ${compact ? 'h-10 w-10 rounded-[0.9rem]' : 'h-20 w-20 rounded-[1.35rem]'} items-center justify-center" style="background: rgba(var(--theme-accent-rgb),0.10); color: var(--theme-accent);"><i class="fa-light ${iconClassForCategory(item?.category)} ${compact ? 'text-lg' : 'text-4xl'}"></i></span></div>`;
            };

                const renderGridCard = (item) => {
                const playAction = item?.category === 'video'
                    ? `<button type="button" data-appfiles-play-url="${escapeHtml(item.previewUrl || item.url || '')}" data-appfiles-play-name="${escapeHtml(item.name || '')}" class="inline-flex items-center gap-2 text-sm font-medium transition hover:opacity-70" style="color: var(--theme-header-text-color);"><i class="fa-light fa-play text-xs"></i> Play</button>`
                    : '';
                const editAction = item?.editImageUrl
                    ? `<a href="${escapeHtml(item.editImageUrl)}" class="inline-flex items-center gap-2 text-sm font-medium transition hover:opacity-70" style="color: var(--theme-header-text-color);"><i class="fa-light fa-pen-ruler text-xs"></i> Edit</a>`
                    : '';
                const mobileDownloadAction = `<a href="${escapeHtml(item.downloadUrl || item.url || '#')}" class="inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium transition hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="color: var(--theme-header-text-color);"><i class="fa-light fa-download text-xs"></i> Download</a>`;
                const mobileDeleteAction = `<button type="button" data-appfiles-delete-id="${escapeHtml(item.idSecure)}" data-appfiles-delete-name="${escapeHtml(item.name || '')}" class="inline-flex items-center gap-2 px-3 py-2.5 text-left text-sm font-medium transition hover:bg-rose-50 hover:text-rose-600" style="color: var(--theme-muted-text-color);"><i class="fa-light fa-trash text-xs"></i> Delete</button>`;

                return `<article data-file-grid-item data-item-id="${escapeHtml(item.idSecure)}" class="group relative z-0 overflow-visible rounded-[1.35rem] border transition hover:z-10 focus-within:z-[80] hover:-translate-y-0.5 hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:shadow-[0_22px_42px_-34px_rgba(15,23,42,0.24)]" style="z-index: 0; border-color: color-mix(in srgb, var(--theme-border-color) 60%, transparent); background: var(--theme-surface-base);"><div class="relative aspect-[1/0.78] overflow-hidden" style="background: var(--theme-surface-soft);">${renderPreview(item, false)}<div class="absolute left-3 top-3 z-10">${renderTypeBadge(item, 'overlay')}</div><label class="absolute right-3 top-3 z-10 inline-flex items-center"><input type="checkbox" class="sr-only" name="selected_files" value="${escapeHtml(item.idSecure)}" data-select-checkbox data-select-id="${escapeHtml(item.idSecure)}"><span class="flex h-5 w-5 items-center justify-center rounded-md border bg-white/92 shadow-none" style="border-color: rgba(203,213,225,0.85);"></span></label></div><div class="space-y-3 px-4 py-4"><div><p class="truncate text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">${escapeHtml(item.name || '')}</p><p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">${escapeHtml(item.mimeType || 'file asset')}</p></div><div class="flex items-center justify-between text-sm" style="color: var(--theme-muted-text-color);"><span>${escapeHtml(item.size || '')}</span><span>${escapeHtml(item.updatedShortLabel || item.updatedLabel || '')}</span></div><div class="flex items-center justify-between border-t pt-3" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);"><div class="flex items-center gap-2">${item?.category === 'video' ? `<button type="button" data-appfiles-play-url="${escapeHtml(item.previewUrl || item.url || '')}" data-appfiles-play-name="${escapeHtml(item.name || '')}" class="hidden items-center gap-2 text-sm font-medium transition hover:opacity-70 sm:inline-flex" style="color: var(--theme-header-text-color);"><i class="fa-light fa-play text-xs"></i> Play</button>` : ''}${editAction ? `<a href="${escapeHtml(item.editImageUrl)}" class="inline-flex items-center gap-2 text-sm font-medium transition hover:opacity-70" style="color: var(--theme-header-text-color);"><i class="fa-light fa-pen-ruler text-xs"></i><span>Edit image</span></a>` : ''}<a href="${escapeHtml(item.downloadUrl || item.url || '#')}" class="hidden items-center gap-2 text-sm font-medium transition hover:opacity-70 sm:inline-flex" style="color: var(--theme-header-text-color);"><i class="fa-light fa-download text-xs"></i> Download</a></div><div class="ml-auto flex items-center gap-2"><details class="relative z-[60] sm:hidden" ontoggle="const card=this.closest('[data-file-grid-item]'); if(card){card.style.zIndex=this.open?'80':'0';}"><summary class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-[0.8rem] border transition hover:opacity-80 [&::-webkit-details-marker]:hidden" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-muted-text-color);"><i class="fa-light fa-ellipsis text-sm"></i></summary><div class="absolute right-0 top-full z-[70] mt-2 flex min-w-[10rem] flex-col overflow-hidden rounded-[0.9rem] border shadow-[0_18px_38px_-24px_rgba(15,23,42,0.28)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); background: var(--theme-surface-base);">${mobileDownloadAction}${mobileDeleteAction}</div></details><button type="button" data-appfiles-delete-id="${escapeHtml(item.idSecure)}" data-appfiles-delete-name="${escapeHtml(item.name || '')}" class="hidden h-9 w-9 items-center justify-center rounded-[0.8rem] border transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 sm:inline-flex" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-muted-text-color);"><i class="fa-light fa-trash text-xs"></i></button></div></div></div></article>`;
            };

            const renderListRow = (item) => {
                const playAction = item?.category === 'video'
                    ? `<button type="button" data-appfiles-play-url="${escapeHtml(item.previewUrl || item.url || '')}" data-appfiles-play-name="${escapeHtml(item.name || '')}" class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-header-text-color);"><i class="fa-light fa-play text-xs"></i></button>`
                    : '';
                const editAction = item?.editImageUrl
                    ? `<a href="${escapeHtml(item.editImageUrl)}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-header-text-color);"><i class="fa-light fa-pen-ruler text-xs"></i></a>`
                    : '';

                return `<article data-file-list-item data-item-id="${escapeHtml(item.idSecure)}" class="group border-b px-4 py-4 transition last:border-b-0 hover:bg-[rgba(15,23,42,0.02)] lg:px-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);"><div class="flex flex-col gap-4 lg:grid lg:grid-cols-[44px_minmax(0,1.8fr)_140px_180px_120px] lg:items-center lg:gap-4"><div class="flex items-center lg:justify-center"><label class="inline-flex items-center"><input type="checkbox" class="sr-only" name="selected_files" value="${escapeHtml(item.idSecure)}" data-select-checkbox data-select-id="${escapeHtml(item.idSecure)}"><span class="flex h-5 w-5 items-center justify-center rounded-md border bg-white/92 shadow-none" style="border-color: rgba(203,213,225,0.85);"></span></label></div><div class="flex min-w-0 items-center gap-4"><div class="relative h-14 w-14 overflow-hidden rounded-[1rem] border" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); background: var(--theme-surface-soft);">${renderPreview(item, true)}</div><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><p class="truncate text-[15px] font-semibold tracking-[-0.02em]" style="color: var(--theme-header-text-color);">${escapeHtml(item.name || '')}</p>${renderTypeBadge(item, 'soft')}</div><div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm" style="color: var(--theme-muted-text-color);"><span>${escapeHtml(item.mimeType || 'file asset')}</span><span class="hidden sm:inline">&bull;</span><span>${escapeHtml(item.size || '')}</span></div></div></div><div class="text-sm" style="color: var(--theme-muted-text-color);">${escapeHtml(item.typeLabel || item.category || '')}</div><div class="text-sm" style="color: var(--theme-muted-text-color);">${escapeHtml(item.updatedLabel || '')}</div><div class="flex items-center justify-end gap-2">${playAction}${editAction}<a href="${escapeHtml(item.downloadUrl || item.url || '#')}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-[rgba(var(--theme-accent-rgb),0.24)] hover:bg-[rgba(var(--theme-accent-rgb),0.06)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-header-text-color);"><i class="fa-light fa-download text-xs"></i></a><button type="button" data-appfiles-delete-id="${escapeHtml(item.idSecure)}" data-appfiles-delete-name="${escapeHtml(item.name || '')}" class="inline-flex h-10 w-10 items-center justify-center rounded-[0.85rem] border transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent); color: var(--theme-muted-text-color);"><i class="fa-light fa-trash text-xs"></i></button></div></div></article>`;
            };

            window.AppFilesPortal = {
                async loadMore(ctx) {
                    if (!ctx?.browseEndpoint) {
                        throw new Error('Browse endpoint is not available.');
                    }

                    if (ctx.ajaxRequestInFlight !== 0) {
                        return;
                    }

                    const query = new URLSearchParams();
                    const requestId = Number(ctx.ajaxLoadRequestId || 0) + 1;
                    ctx.ajaxLoadRequestId = requestId;
                    ctx.ajaxRequestInFlight = requestId;
                    const nextPage = Number(ctx.ajaxNextPage || (Number(ctx.ajaxFilesPage || 1) + 1));
                    const category = String(ctx.filterCategory || 'all').trim();
                    const search = String(ctx.searchQuery || '').trim();
                    const folder = String(ctx.currentFolderId || '').trim();

                    try {
                        query.set('page', String(nextPage));
                        query.set('per_page', '20');

                        if (folder !== '') {
                            query.set('folder', folder);
                        }

                        if (search !== '') {
                            query.set('q', search);
                        }

                        if (category === 'image' || category === 'video') {
                            query.set('type', category);
                        } else if (category !== '' && category !== 'all') {
                            query.set('category', category);
                        }

                        const response = await fetch(`${ctx.browseEndpoint}?${query.toString()}`, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });
                        const payload = await response.json().catch(() => ({}));

                        console.log('[PortalFiles:ajax]', { payload, ok: response.ok, nextPage, requestId });

                        if (!response.ok) {
                            throw new Error(payload?.message || 'The next files could not be loaded.');
                        }

                        const freshItems = Array.isArray(payload?.data)
                            ? payload.data.filter((item) => {
                                const itemId = String(item?.idSecure || '').trim();
                                return itemId !== '' && !ctx.renderedFileIds.includes(itemId);
                            })
                            : [];

                        if (freshItems.length > 0) {
                            ctx.$refs.fileGrid?.insertAdjacentHTML('beforeend', freshItems.map(renderGridCard).join(''));
                            ctx.$refs.fileList?.insertAdjacentHTML('beforeend', freshItems.map(renderListRow).join(''));
                            ctx.renderedFileIds = [...ctx.renderedFileIds, ...freshItems.map((item) => item.idSecure)];
                            ctx.ajaxVisibleCount += freshItems.length;
                            hydrateNewNodes();
                            ctx.checkAutoLoadMore?.('append');
                        }

                        ctx.ajaxFilesPage = Number(payload?.current_page || nextPage);
                        ctx.ajaxNextPage = payload?.next_page ? Number(payload.next_page) : null;
                        ctx.ajaxFilesHasMore = Boolean(payload?.has_more);
                        ctx.hasMoreItems = Boolean(payload?.has_more);

                        // If a page contains only already-rendered items, continue advancing
                        // instead of getting stuck with visible whitespace at the bottom.
                        if (freshItems.length === 0 && ctx.ajaxFilesHasMore && ctx.ajaxNextPage) {
                            ctx.ajaxRequestInFlight = 0;
                            await window.AppFilesPortal.loadMore(ctx);
                            return;
                        }
                    } finally {
                        if (ctx.ajaxRequestInFlight === requestId) {
                            ctx.ajaxRequestInFlight = 0;
                        }

                        ctx.checkAutoLoadMore?.('append');
                    }
                },
            };

            document.addEventListener('click', (event) => {
                const playButton = event.target.closest('[data-appfiles-play-url]');

                if (playButton) {
                    const state = getRootState(playButton);

                    if (state) {
                        state.promptPlayVideo(playButton.dataset.appfilesPlayUrl || '', playButton.dataset.appfilesPlayName || '');
                    }

                    return;
                }

                const deleteButton = event.target.closest('[data-appfiles-delete-id]');

                if (deleteButton) {
                    const state = getRootState(deleteButton);

                    if (state) {
                        state.openDeleteDialog(deleteButton.dataset.appfilesDeleteId || '', deleteButton.dataset.appfilesDeleteName || '');
                    }
                }
            });

            document.addEventListener('change', (event) => {
                const checkbox = event.target.closest?.('[data-select-checkbox][data-select-id]');

                if (!checkbox) {
                    return;
                }

                const state = getRootState(checkbox);

                if (state) {
                    state.toggleItemSelection(checkbox.dataset.selectId || '', Boolean(checkbox.checked));
                }
            });

            window.addEventListener('appfiles-files-appended', (event) => {
                const detail = event.detail?.[0] || event.detail || {};
                const state = document.querySelector('[data-appfiles-portal-root]')?._x_dataStack?.[0] || null;

                if (!state) {
                    console.log('[PortalFiles:append]', { missing: 'root-state', detail });
                    return;
                }

                const items = Array.isArray(detail.items) ? detail.items : [];

                if (items.length > 0) {
                    state.$refs.fileGrid?.insertAdjacentHTML('beforeend', items.map(renderGridCard).join(''));
                    state.$refs.fileList?.insertAdjacentHTML('beforeend', items.map(renderListRow).join(''));
                }

                state.hasMoreItems = Boolean(detail.hasMoreItems);
                state.loadMoreInFlight = false;
                state.queueAutoLoadMoreCheck?.();

                console.log('[PortalFiles:append]', {
                    appendedCount: items.length,
                    hasMoreItems: state.hasMoreItems,
                    loadLimit: detail.loadLimit,
                    matchingFileItemsCount: detail.matchingFileItemsCount,
                    gridDomCount: state.$refs.fileGrid?.querySelectorAll('[data-file-grid-item]').length || 0,
                    listDomCount: state.$refs.fileList?.querySelectorAll('[data-file-list-item]').length || 0,
                });
            });

            document.addEventListener('livewire:init', () => {
                logRenderState(document);

                if (!window.Livewire?.hook) {
                    return;
                }

                window.Livewire.hook('morph.updated', ({ el, component }) => {
                    console.log('[PortalFiles:morph.updated]', {
                        componentId: component?.id || null,
                        tag: el?.tagName || null,
                        hasRegionSelf: Boolean(el?.matches?.('[data-files-refresh-region]')),
                        hasRegionChild: Boolean(el?.querySelector?.('[data-files-refresh-region]')),
                    });

                    if (el?.matches?.('[data-files-refresh-region]') || el?.querySelector?.('[data-files-refresh-region]')) {
                        logRenderState(el);
                    }
                });

                window.Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                    console.log('[PortalFiles:commit:start]', {
                        componentId: component?.id || null,
                        snapshotEncodedLength: String(commit?.snapshotEncoded || '').length,
                    });

                    respond(() => {
                        console.log('[PortalFiles:commit:respond]', {
                            componentId: component?.id || null,
                        });
                    });

                    succeed(() => {
                        console.log('[PortalFiles:commit:succeed]', {
                            componentId: component?.id || null,
                        });
                    });

                    fail(() => {
                        console.log('[PortalFiles:commit:fail]', {
                            componentId: component?.id || null,
                        });
                    });
                });
            });
        })();
    </script>
@endonce

        <template x-teleport="body">
            <div
                x-cloak
                x-show="uploadUrlDialogOpen"
                class="fixed inset-0 z-[120] flex items-center justify-center p-6"
                x-on:keydown.escape.window="uploadUrlDialogOpen = false"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="uploadUrlDialogOpen = false"></div>

                <div x-show="uploadUrlDialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[26rem]">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ $remoteImportContext['title'] }}</h3>
                                <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400">{{ $remoteImportContext['description'] }}</p>
                            </div>

                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="uploadUrlDialogOpen = false">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <form x-on:submit.prevent="submitRemoteUrlImport()" class="space-y-4">
                                <x-ui.input x-model="remoteUrlValue" name="remote_url" :label="$remoteImportContext['label']" :value="$remote_url" :error="null" :placeholder="$remoteImportContext['placeholder']" />
                                <p x-show="remoteUrlError" class="text-sm leading-6 text-rose-500" x-text="remoteUrlError"></p>
                                <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $remoteImportContext['helper'] }}</p>
                                <div class="flex justify-end">
                                    <x-ui.button type="submit" x-bind:disabled="remoteUrlSubmitting">
                                        <span x-show="!remoteUrlSubmitting">{{ __('Import file') }}</span>
                                        <span x-show="remoteUrlSubmitting">{{ __('Importing...') }}</span>
                                    </x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div
            x-cloak
            x-show="newFolderDialogOpen"
            class="fixed inset-0 z-[120] flex items-center justify-center p-6"
            x-on:keydown.escape.window="closeNewFolderDialog()"
        >
            <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeNewFolderDialog()"></div>

            <div x-show="newFolderDialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[26rem]" x-on:click.stop>
                <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                    <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        <div class="min-w-0">
                            <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Create Folder') }}</h3>
                            <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400">{{ __('Create a new folder inside the current workspace.') }}</p>
                        </div>

                        <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeNewFolderDialog()">
                            <i class="fa-light fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="px-5 py-4 sm:px-6 sm:py-5">
                        <div class="space-y-4">
                            <x-ui.input x-model="newFolderName" data-create-folder-name name="name" :label="__('Folder name')" :error="$errors->first('name')" required />
                            <x-ui.input x-model="newFolderNote" name="note" :label="__('Note')" :error="$errors->first('note')" />
                            <div class="space-y-3">
                                <p class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Parent folder') }}</p>
                                <div class="overflow-hidden rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb),0.68); background: var(--theme-surface-soft);">
                                    <div class="border-b px-4 py-3" style="border-color:var(--theme-border-color)"><x-ui.radio name="new-folder-parent" x-model="newFolderParent" value="" :label="__('Root folder')" /></div>
                                    @foreach ($availableFolders as $folderOption)
                                        <div class="border-b px-4 py-3 last:border-b-0" style="border-color:var(--theme-border-color)"><x-ui.radio name="new-folder-parent" x-model="newFolderParent" :value="$folderOption->id_secure" :label="$folderOption->name" /></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <x-ui.button type="button" x-on:click.prevent="confirmCreateFolder()" x-bind:disabled="creatingFolder">
                                    <span x-show="!creatingFolder">{{ __('Create Folder') }}</span>
                                    <span x-show="creatingFolder">{{ __('Creating...') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="editFolderDialogOpen"
            class="fixed inset-0 z-[120] flex items-center justify-center p-6"
            x-on:keydown.escape.window="closeEditFolderDialog()"
        >
            <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeEditFolderDialog()"></div>

            <div x-show="editFolderDialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[26rem]" x-on:click.stop>
                <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                    <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                        <div class="min-w-0">
                            <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Edit folder') }}</h3>
                            <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400">{{ __('Rename this folder or update its note.') }}</p>
                        </div>

                        <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeEditFolderDialog()">
                            <i class="fa-light fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <div class="px-5 py-4 sm:px-6 sm:py-5">
                        <div class="space-y-4">
                            <x-ui.input x-model="editFolderName" data-edit-folder-name name="edit_name" :label="__('Folder name')" :error="$errors->first('edit_name')" required />
                            <x-ui.input x-model="editFolderNote" name="edit_note" :label="__('Note')" :error="$errors->first('edit_note')" />
                            <div class="flex justify-end">
                                <x-ui.button type="button" x-on:click.prevent="confirmUpdateFolder()" x-bind:disabled="updatingFolder">
                                    <span x-show="!updatingFolder">{{ __('Save changes') }}</span>
                                    <span x-show="updatingFolder">{{ __('Saving...') }}</span>
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="deleteDialogOpen"
                class="fixed inset-0 z-[120] flex items-center justify-center p-6"
                x-on:keydown.escape.window="closeDeleteDialog()"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeDeleteDialog()"></div>

                <div x-show="deleteDialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[26rem]">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white" x-text="deleteTargetLabel"></h3>
                                <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400" x-text="deleteTargetDescription"></p>
                            </div>
                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeDeleteDialog()">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <div class="space-y-4">
                                <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                    {{ __('Delete') }}
                                    "<span class="font-semibold" style="color: var(--theme-header-text-color);" x-text="deleteTargetName"></span>"?
                                </p>
                                <div class="flex justify-end gap-3">
                                    <x-ui.button type="button" variant="outline" x-on:click="closeDeleteDialog()">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="button" variant="danger" x-on:click="confirmDelete()" x-bind:disabled="!deleteTargetId">
                                        <span x-text="deleteTargetButton"></span>
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="playVideoDialogOpen"
                class="fixed inset-0 z-[120] flex items-center justify-center p-6"
                x-on:keydown.escape.window="playVideoDialogOpen = false"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="playVideoDialogOpen = false"></div>

                <div x-show="playVideoDialogOpen" x-transition.opacity.scale.90 class="relative w-full max-w-2xl">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Play video') }}</h3>
                                <p class="mt-2 truncate text-[15px] leading-7 text-slate-500 dark:text-slate-400" x-text="playVideoName"></p>
                            </div>
                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="playVideoDialogOpen = false">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <div class="overflow-hidden rounded-[1.2rem]" style="background: #000;">
                                <video controls playsinline preload="metadata" class="aspect-video w-full" x-bind:src="playVideoUrl"></video>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

</div>
