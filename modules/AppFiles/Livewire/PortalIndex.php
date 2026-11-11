<?php

namespace Modules\AppFiles\Livewire;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;

#[Title('Files')]
class PortalIndex extends Component
{
    use WithFileUploads;

    protected FileManager $files;

    protected StorageDriverManager $storageDriverManager;

    public string $q = '';

    public string $category = 'all';

    public string $folder = '';

    public int $loadLimit = 20;

    public int $refreshNonce = 0;

    public string $parent_folder = '';

    public string $name = '';

    public string $note = '';

    public string $remote_url = '';

    public string $remote_import_source = 'generic';

    public string $editing_folder = '';

    public string $edit_name = '';

    public string $edit_note = '';

    /** @var array<int, mixed> */
    public array $uploads = [];

    /** @var array<int, string> */
    public array $selected_files = [];

    public ?string $statusMessage = null;

    public ?string $errorMessage = null;

    protected function notifySuccess(string $message): void
    {
        $this->statusMessage = $message;
        $this->errorMessage = null;
        $this->dispatch('app-toast', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->errorMessage = $message;
        $this->statusMessage = null;
        $this->dispatch('app-toast', type: 'error', message: $message);
    }

    public function boot(FileManager $files, StorageDriverManager $storageDriverManager): void
    {
        $this->files = $files;
        $this->storageDriverManager = $storageDriverManager;
    }

    public function mount(): void
    {
        abort_unless($this->files->filesEnabled($this->user()), 404);

        $this->parent_folder = $this->folder;
    }

    protected function queryString(): array
    {
        return [
            'q' => ['except' => ''],
            'category' => ['except' => 'all'],
            'folder' => ['except' => ''],
        ];
    }

    public function updatedQ(): void
    {
        $this->clearSelection();
        $this->resetLoadWindow();
    }

    public function updatedCategory(): void
    {
        $this->clearSelection();
        $this->resetLoadWindow();
    }

    public function openFolder(string $idSecure = ''): void
    {
        $this->folder = $idSecure;
        $this->parent_folder = $idSecure;
        $this->clearSelection();
        $this->refreshListing();
        $this->dispatch('appfiles-folder-opened', id: $idSecure);
    }

    public function updatedUploads(): void
    {
        if ($this->uploads === []) {
            return;
        }

        $this->resetErrorBag(['uploads', 'uploads.*']);

        try {
            $this->uploadSelectedFiles();
        } catch (ValidationException $exception) {
            $message = $exception->validator?->errors()?->first('uploads')
                ?: $exception->validator?->errors()?->first('uploads.*')
                ?: __('The selected files could not be uploaded.');

            $this->reset('uploads');
            $this->notifyError($message);

            throw $exception;
        }
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'category');
        $this->category = 'all';
        $this->clearSelection();
        $this->resetLoadWindow();
    }

    public function updatedSelectedFiles(): void
    {
        $this->selected_files = array_values(array_unique(array_filter(
            $this->selected_files,
            static fn ($value): bool => is_string($value) && $value !== ''
        )));
    }

    public function loadMore(): void
    {
        $before = $this->loadLimit;
        $hasMore = $this->hasMoreItems();
        $user = $this->user();
        $currentFolder = $this->currentFolder($user);

        Log::info('portal.files.loadMore.before', [
            'user_id' => $user->id ?? null,
            'load_limit' => $before,
            'has_more' => $hasMore,
            'folder' => $this->folder,
            'category' => $this->category,
            'query' => $this->q,
        ]);

        $this->dispatch('appfiles-debug', source: 'portal.loadMore:before', loadLimit: $before, hasMore: $hasMore);

        if (! $hasMore) {
            return;
        }

        $nextItems = $this->fileItemsQuery($user, $currentFolder?->id)
            ->skip($before)
            ->take(20)
            ->get();

        $this->loadLimit += 20;
        $this->refreshNonce++;
        $matchingFileItemsCount = $this->matchingFileItemsCount($user, $currentFolder?->id);
        $hasMoreAfterAppend = $this->loadLimit < $matchingFileItemsCount;

        Log::info('portal.files.loadMore.after', [
            'user_id' => $user->id ?? null,
            'load_limit' => $this->loadLimit,
            'refresh_nonce' => $this->refreshNonce,
            'appended_count' => $nextItems->count(),
            'has_more_after_append' => $hasMoreAfterAppend,
        ]);

        $this->dispatch('appfiles-debug', source: 'portal.loadMore:after', loadLimit: $this->loadLimit);
        $this->dispatch('appfiles-files-appended', [
            'items' => $this->serializeFilesForAppend($nextItems),
            'loadLimit' => $this->loadLimit,
            'hasMoreItems' => $hasMoreAfterAppend,
            'matchingFileItemsCount' => $matchingFileItemsCount,
        ]);
        $this->skipRender();
    }

    public function refreshListing(): void
    {
        $this->refreshNonce++;
        $this->resetLoadWindow();
    }

    public function syncFolderContextAndRefresh(string $folderIdSecure = ''): void
    {
        $this->folder = $folderIdSecure;
        $this->parent_folder = $folderIdSecure;
        $this->clearSelection();
        $this->refreshListing();
    }

    public function createFolder(): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $this->user();
        $parent = $this->selectedParentFolder($user);

        if (AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->exists()) {
            $this->addError('name', __('A folder with this name already exists.'));

            return;
        }

        $folder = $this->files->storeFolder($user, $validated['name'], $parent, $validated['note'] ?: null);

        log_activity('portal.files.create_folder', 'Created a user folder.', [
            'subject' => $folder,
            'metadata' => ['name' => $folder->name],
        ]);

        $this->reset('name', 'note');
        $this->parent_folder = $this->folder;
        $this->notifySuccess(__('Folder created successfully.'));
        $this->refreshListing();
        $this->dispatch('appfiles-folder-created');
    }

    public function createFolderFromDialog(string $name = '', string $note = '', string $parentFolder = ''): void
    {
        $this->name = $name;
        $this->note = $note;
        $this->parent_folder = $parentFolder;

        $this->createFolder();
    }

    public function startEditFolder(string $idSecure): void
    {
        $folder = AppFile::query()
            ->ownedBy($this->user())
            ->where('is_folder', true)
            ->where('id_secure', $idSecure)
            ->firstOrFail();

        $this->editing_folder = $folder->id_secure;
        $this->edit_name = $folder->name;
        $this->edit_note = (string) ($folder->note ?? '');
        $this->resetErrorBag(['edit_name', 'edit_note', 'editing_folder']);
    }

    public function updateFolderFromDialog(string $idSecure = '', string $name = '', string $note = ''): void
    {
        $this->editing_folder = $idSecure;
        $this->edit_name = $name;
        $this->edit_note = $note;

        $this->updateFolder();
    }

    public function updateFolder(): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $validated = $this->validate([
            'editing_folder' => ['required', 'string'],
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'edit_name' => __('folder name'),
            'edit_note' => __('note'),
        ]);

        $user = $this->user();
        $folder = AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('id_secure', $validated['editing_folder'])
            ->firstOrFail();

        if (AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('parent_id', $folder->parent_id)
            ->whereKeyNot($folder->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['edit_name']))])
            ->exists()) {
            $this->addError('edit_name', __('A folder with this name already exists.'));

            return;
        }

        $folder->forceFill([
            'name' => trim($validated['edit_name']),
            'note' => $validated['edit_note'] !== '' ? $validated['edit_note'] : null,
        ])->save();

        log_activity('portal.files.update_folder', 'Updated a user folder.', [
            'subject' => $folder,
            'metadata' => ['name' => $folder->name],
        ]);

        $updatedFolderId = $folder->id_secure;
        $updatedFolderName = $folder->name;
        $updatedFolderNote = (string) ($folder->note ?? '');

        $this->reset('editing_folder', 'edit_name', 'edit_note');
        $this->notifySuccess(__('Folder updated successfully.'));
        $this->refreshListing();
        $this->dispatch('appfiles-folder-updated', id: $updatedFolderId, name: $updatedFolderName, note: $updatedFolderNote);
    }

    public function uploadFromUrl(): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        if (! $this->files->isActionEnabled('upload_from_url', $this->user())) {
            $this->notifyError(__('Upload from URL is currently disabled.'));

            return;
        }

        $validated = $this->validate([
            'remote_url' => ['required', 'url', 'max:2048'],
        ]);

        $user = $this->user();
        $parent = $this->currentFolder($user);

        try {
            $stored = $this->files->storeRemoteFile($user, $validated['remote_url'], $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'remote_url' => $exception->getMessage(),
            ]);
        }

        log_activity('portal.files.upload_from_url', 'Imported a remote file from URL in user portal.', [
            'subject' => $stored,
        ]);

        $this->remote_url = '';
        $this->remote_import_source = 'generic';
        $this->notifySuccess(__('Imported the file from URL successfully.'));
        $this->refreshListing();
    }

    public function prepareRemoteImport(string $source = 'generic'): void
    {
        $allowedSources = ['generic', 'google_drive', 'dropbox', 'onedrive'];
        $this->remote_import_source = in_array($source, $allowedSources, true) ? $source : 'generic';
        $this->resetErrorBag(['remote_url']);
        $this->dispatch('appfiles-open-remote-import');
    }

    public function importGoogleDriveFile(string $fileId, string $accessToken, array $pickerDocument = []): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $user = $this->user();
        $parent = $this->currentFolder($user);
        $this->dispatch('appfiles-debug', scope: 'portal', step: 'start', payload: [
            'user_id' => $user->id,
            'file_id' => $fileId,
            'parent_id' => $parent?->id,
            'picker_document' => $pickerDocument,
        ]);

        try {
            $stored = $this->files->storeGoogleDriveFile($user, $fileId, $accessToken, $parent, $pickerDocument);
        } catch (\Throwable $exception) {
            $this->dispatch('appfiles-debug', scope: 'portal', step: 'error', payload: [
                'user_id' => $user->id,
                'file_id' => $fileId,
                'parent_id' => $parent?->id,
                'message' => $exception->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'google_drive' => $exception->getMessage(),
            ]);
        }
        $this->dispatch('appfiles-debug', scope: 'portal', step: 'saved', payload: [
            'user_id' => $user->id,
            'stored_id' => $stored->id,
            'stored_name' => $stored->name,
            'stored_owner_user_id' => $stored->owner_user_id,
            'stored_team_id' => $stored->team_id,
            'stored_parent_id' => $stored->parent_id,
        ]);

        log_activity('portal.files.google_drive_import', 'Imported a Google Drive file in the user portal.', [
            'subject' => $stored,
        ]);

        $this->notifySuccess(__('Imported the Google Drive file successfully.'));
        $this->refreshListing();
    }

    public function actionNotice(string $label): void
    {
        $this->notifySuccess(__(':label is available from the action menu, but still requires a manual export flow.', ['label' => $label]));
    }

    public function toggleSelectAllVisibleFiles(): void
    {
        $visibleIds = $this->visibleFileIds($this->user());

        if ($visibleIds === []) {
            $this->selected_files = [];

            return;
        }

        $selectedVisibleCount = count(array_intersect($this->selected_files, $visibleIds));

        if ($selectedVisibleCount === count($visibleIds)) {
            $this->selected_files = array_values(array_diff($this->selected_files, $visibleIds));

            return;
        }

        $this->selected_files = array_values(array_unique([...$this->selected_files, ...$visibleIds]));
    }

    public function clearSelection(): void
    {
        $this->selected_files = [];
    }

    public function deleteSelectedItems(): void
    {
        $selectedIds = array_values(array_unique(array_filter($this->selected_files)));

        $this->deleteItemsByIds($selectedIds);
    }

    public function deleteItemsByIds(array $selectedIds = []): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $selectedIds = array_values(array_unique(array_filter($selectedIds)));

        if ($selectedIds === []) {
            return;
        }

        $user = $this->user();

        $files = AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', false)
            ->whereIn('id_secure', $selectedIds)
            ->get();

        if ($files->isEmpty()) {
            $this->clearSelection();

            return;
        }

        foreach ($files as $file) {
            $this->files->deleteTree($file);
        }

        log_activity('portal.files.bulk_delete', 'Deleted multiple file manager items from the user portal.', [
            'metadata' => ['count' => $files->count()],
        ]);

        $this->clearSelection();
        $this->notifySuccess(__('Deleted :count selected file(s) successfully.', ['count' => $files->count()]));
        $deletedIds = $files->pluck('id_secure')->values()->all();
        $this->dispatch('appfiles-items-deleted', ids: $deletedIds);
        $this->skipRender();
    }

    public function uploadSelectedFiles(): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $user = $this->user();
        $maxKb = $this->files->maxUploadMb($user) * 1024;

        $this->validate([
            'uploads' => ['required', 'array', 'min:1'],
            'uploads.*' => [
                'required',
                File::types($this->files->allowedExtensions())->max($maxKb),
            ],
        ]);

        $parent = $this->currentFolder($user);
        try {
            $stored = $this->files->storeUploads($user, $this->uploads, $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'uploads' => $exception->getMessage(),
            ]);
        }

        log_activity('portal.files.upload', 'Uploaded files from the user portal.', [
            'metadata' => ['count' => $stored->count()],
        ]);

        $this->reset('uploads');
        $this->clearSelection();
        $this->notifySuccess(__('Uploaded :count file(s) successfully.', ['count' => $stored->count()]));
        $this->dispatch('appfiles-upload-finished');
        $this->refreshListing();
    }

    public function deleteItem(string $idSecure): void
    {
        abort_unless($this->files->canUpload($this->user()), 403);

        $user = $this->user();
        $file = AppFile::query()->ownedBy($user)->where('id_secure', $idSecure)->firstOrFail();
        $deletedId = $file->id_secure;

        $this->files->deleteTree($file);

        log_activity('portal.files.delete', 'Deleted a file manager item from the user portal.', [
            'metadata' => ['file' => $file->id_secure],
        ]);

        $this->notifySuccess(__('Item deleted successfully.'));
        $this->clearSelection();
        $this->dispatch('appfiles-items-deleted', ids: [$deletedId]);
        $this->dispatch('appfiles-item-deleted', id: $deletedId);
        $this->skipRender();
    }

    public function render(): View
    {
        $user = $this->user();
        $currentFolder = $this->currentFolder($user);
        $folderItems = $this->folderItemsQuery($user, $currentFolder?->id)->get();
        $fileItems = $this->fileItemsQuery($user, $currentFolder?->id)
            ->limit($this->loadLimit)
            ->get();
        $summaryQuery = AppFile::query()->ownedBy($user);
        $storageHealth = app(StorageDriverManager::class)->diskHealth();
        $visibleFileIds = $fileItems
            ->pluck('id_secure')
            ->values()
            ->all();
        $selectedVisibleCount = count(array_intersect($this->selected_files, $visibleFileIds));
        $matchingFileItemsCount = $this->matchingFileItemsCount($user, $currentFolder?->id);

        Log::info('portal.files.render', [
            'user_id' => $user->id ?? null,
            'load_limit' => $this->loadLimit,
            'folder_items_count' => $folderItems->count(),
            'file_items_count' => $fileItems->count(),
            'matching_file_items_count' => $matchingFileItemsCount,
            'has_more_items' => $fileItems->count() < $matchingFileItemsCount,
            'folder' => $this->folder,
            'category' => $this->category,
            'query' => $this->q,
            'file_ids_sample' => $fileItems->pluck('id_secure')->take(10)->values()->all(),
        ]);

        return view('appfiles::portal.index', [
            'folderItems' => $folderItems,
            'fileItems' => $fileItems,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $currentFolder?->breadcrumbChain() ?? [],
            'filters' => [
                'q' => $this->q,
                'category' => $this->category,
            ],
            'availableFolders' => AppFile::query()
                ->ownedBy($user)
                ->where('is_folder', true)
                ->orderBy('name')
                ->get(['id', 'id_secure', 'name', 'parent_id']),
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'files' => (clone $summaryQuery)->where('is_folder', false)->count(),
                'folders' => (clone $summaryQuery)->where('is_folder', true)->count(),
                'storage' => (clone $summaryQuery)->sum('size_bytes'),
            ],
            'storageByCategory' => (clone $summaryQuery)
                ->where('is_folder', false)
                ->selectRaw('category, SUM(size_bytes) as total_bytes')
                ->groupBy('category')
                ->pluck('total_bytes', 'category')
                ->all(),
            'maxUploadMb' => $this->files->maxUploadMb($user),
            'allowedExtensions' => $this->files->allowedExtensions(),
            'actionToggles' => [
                'upload_from_url' => $this->files->isActionEnabled('upload_from_url', $user),
                'google_drive' => $this->files->isActionEnabled('google_drive', $user),
                'dropbox' => $this->files->isActionEnabled('dropbox', $user),
                'onedrive' => $this->files->isActionEnabled('onedrive', $user),
                'adobe_express' => $this->files->isActionEnabled('adobe_express', $user),
                'quick_delete_action' => $this->files->isActionEnabled('quick_delete_action', $user),
            ],
            'imageEditorEnabled' => $this->files->imageEditorEnabled($user),
            'maxStorageMb' => $this->files->maxStorageMb($user),
            'storageUsedBytes' => $this->files->storageUsedBytes($user),
            'hasMoreItems' => $fileItems->count() < $matchingFileItemsCount,
            'selectedFiles' => $this->selected_files,
            'visibleFileIds' => $visibleFileIds,
            'selectedVisibleCount' => $selectedVisibleCount,
            'allVisibleFilesSelected' => $visibleFileIds !== [] && $selectedVisibleCount === count($visibleFileIds),
            'debugRender' => [
                'loadLimit' => $this->loadLimit,
                'folderItemsCount' => $folderItems->count(),
                'fileItemsCount' => $fileItems->count(),
                'matchingFileItemsCount' => $matchingFileItemsCount,
                'hasMoreItems' => $fileItems->count() < $matchingFileItemsCount,
            ],
            'googlePickerConfig' => $this->files->googlePickerConfig($user),
            'dropboxChooserConfig' => $this->files->dropboxChooserConfig($user),
            'oneDrivePickerConfig' => $this->files->oneDrivePickerConfig($user),
            'adobeExpressConfig' => $this->files->adobeExpressConfig($user),
            'remoteImportContext' => $this->remoteImportContext(),
            'statusMessage' => $this->statusMessage,
            'errorMessage' => $this->errorMessage,
            'storageHealth' => $storageHealth,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Files'),
            'fullWorkspace' => true,
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function baseItemsQuery(User $user, ?int $folderId): Builder
    {
        return AppFile::query()
            ->ownedBy($user)
            ->with(['owner:id,name,username', 'team:id,name', 'parent:id,id_secure,name'])
            ->search($this->q)
            ->when(
                $this->category !== 'all',
                fn (Builder $query) => $query->where('category', $this->category)
            )
            ->inFolder($folderId);
    }

    protected function folderItemsQuery(User $user, ?int $folderId): Builder
    {
        return $this->baseItemsQuery($user, $folderId)
            ->where('is_folder', true)
            ->latest('created_at')
            ->latest('id');
    }

    protected function fileItemsQuery(User $user, ?int $folderId): Builder
    {
        return $this->baseItemsQuery($user, $folderId)
            ->where('is_folder', false)
            ->latest('created_at')
            ->latest('id');
    }

    protected function matchingFileItemsCount(User $user, ?int $folderId): int
    {
        return (clone $this->fileItemsQuery($user, $folderId))->count();
    }

    protected function hasMoreItems(): bool
    {
        $user = $this->user();

        return $this->loadLimit < $this->matchingFileItemsCount($user, $this->currentFolder($user)?->id);
    }

    /** @return array<int, string> */
    protected function visibleFileIds(User $user): array
    {
        return $this->fileItemsQuery($user, $this->currentFolder($user)?->id)
            ->limit($this->loadLimit)
            ->pluck('id_secure')
            ->all();
    }

    protected function currentFolder(User $user): ?AppFile
    {
        return $this->files->resolveFolderForUser($this->folder ?: null, $user);
    }

    protected function selectedParentFolder(User $user): ?AppFile
    {
        return $this->files->resolveFolderForUser($this->parent_folder ?: null, $user);
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    protected function resetLoadWindow(): void
    {
        $this->loadLimit = 20;
    }

    protected function remoteImportContext(): array
    {
        return match ($this->remote_import_source) {
            'google_drive' => [
                'title' => __('Import from Google Drive'),
                'description' => __('Paste a Google Drive share link and the file will be imported into the current folder.'),
                'label' => __('Google Drive share link'),
                'placeholder' => 'https://drive.google.com/file/d/...',
                'helper' => __('Public or accessible share links are supported. Native Docs, Sheets, or Slides export links may not be downloadable.'),
            ],
            'dropbox' => [
                'title' => __('Import from Dropbox'),
                'description' => __('Paste a Dropbox shared link and the file will be imported into the current folder.'),
                'label' => __('Dropbox share link'),
                'placeholder' => 'https://www.dropbox.com/s/...',
                'helper' => __('Shared Dropbox links are converted to a direct download automatically.'),
            ],
            'onedrive' => [
                'title' => __('Import from OneDrive'),
                'description' => __('Paste a OneDrive shared link and the file will be imported into the current folder.'),
                'label' => __('OneDrive share link'),
                'placeholder' => 'https://1drv.ms/...',
                'helper' => __('Shared OneDrive links are converted to a direct download automatically.'),
            ],
            default => [
                'title' => __('Upload From URL'),
                'description' => __('Import a remote file directly into the current folder.'),
                'label' => __('Remote file URL'),
                'placeholder' => 'https://example.com/file.png',
                'helper' => __('Direct file URLs and supported share links from Google Drive, Dropbox, and OneDrive can be used here.'),
            ],
        };
    }

    protected function serializeFilesForAppend(Collection $files): array
    {
        $imageEditorEnabled = $this->files->imageEditorEnabled($this->user());

        return $files
            ->map(function (AppFile $file) use ($imageEditorEnabled): array {
                $privatePreviewUrl = route('portal.files.preview', $file);
                $displayUrl = $this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path)
                    ?: URL::signedRoute('portal.files.publish-preview', ['file' => $file]);

                return [
                    'idSecure' => $file->id_secure,
                    'name' => (string) $file->name,
                    'mimeType' => (string) ($file->mime_type ?: ''),
                    'size' => (string) $file->humanSize(),
                    'updatedLabel' => (string) ($file->updated_at?->format('M d') ?? ''),
                    'category' => (string) ($file->category ?: ''),
                    'typeLabel' => (string) $file->typeLabel(),
                    'url' => $displayUrl,
                    'previewUrl' => $displayUrl,
                    'privatePreviewUrl' => $privatePreviewUrl,
                    'downloadUrl' => route('portal.files.download', $file),
                    'editImageUrl' => $imageEditorEnabled && $file->isEditableImage()
                        ? route('portal.files.edit-image', ['file' => $file, 'return' => request()->fullUrl()])
                        : null,
                    'isImage' => (bool) $file->is_image,
                ];
            })
            ->values()
            ->all();
    }
}
