<?php

namespace Modules\AppFiles\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;

#[Title('Admin Files')]
class AdminIndex extends Component
{
    use WithFileUploads;

    protected FileManager $files;

    public string $q = '';

    public string $category = 'all';

    public string $owner = '';

    public string $folder = '';

    public string $filter_owner = '';

    public string $filter_category = 'all';

    public int $loadLimit = 25;

    public int $refreshNonce = 0;

    public string $parent_folder = '';

    public ?int $owner_user_id = null;

    public string $name = '';

    public string $note = '';

    public string $remote_url = '';

    public string $remote_import_source = 'generic';

    public string $editing_folder = '';

    public string $edit_name = '';

    public string $edit_note = '';

    public bool $showEditFolderDialog = false;

    /** @var array<int, mixed> */
    public array $uploads = [];

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

    public function boot(FileManager $files): void
    {
        $this->files = $files;
    }

    protected function queryString(): array
    {
        return [
            'q' => ['except' => ''],
            'category' => ['except' => 'all'],
            'owner' => ['except' => ''],
            'folder' => ['except' => ''],
        ];
    }

    public function mount(): void
    {
        if ($this->owner !== '') {
            $this->owner_user_id = (int) $this->owner;
        } else {
            $this->owner_user_id = null;
        }

        $this->filter_owner = $this->owner;
        $this->filter_category = $this->category;
        $this->parent_folder = $this->folder;
    }

    public function updatedQ(): void
    {
        $this->resetLoadWindow();
    }

    public function updatedCategory(): void
    {
        $this->resetLoadWindow();
    }

    public function updatedOwner(string $value): void
    {
        $this->owner_user_id = $value !== '' ? (int) $value : null;
        $this->parent_folder = '';
        $this->resetLoadWindow();
        $this->folder = '';
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

    public function loadMore(): void
    {
        $before = $this->loadLimit;
        $hasMore = $this->hasMoreItems();

        $this->dispatch('appfiles-debug', source: 'admin.loadMore:before', loadLimit: $before, hasMore: $hasMore);

        if (! $hasMore) {
            return;
        }

        $this->loadLimit += 25;
        $this->refreshNonce++;

        $this->dispatch('appfiles-debug', source: 'admin.loadMore:after', loadLimit: $this->loadLimit);
    }

    public function refreshListing(): void
    {
        $this->refreshNonce++;
        $this->resetLoadWindow();
    }

    public function resetFilters(): void
    {
        $this->reset('q', 'owner', 'folder', 'parent_folder');
        $this->category = 'all';
        $this->filter_owner = '';
        $this->filter_category = 'all';
        $this->owner_user_id = null;
        $this->refreshListing();
    }

    public function applyFilters(): void
    {
        $this->owner = trim($this->filter_owner);
        $this->category = trim($this->filter_category) !== '' ? trim($this->filter_category) : 'all';
        $this->owner_user_id = $this->owner !== '' ? (int) $this->owner : null;
        $this->folder = '';
        $this->parent_folder = '';
        $this->refreshListing();
    }

    public function openFolder(string $idSecure = ''): void
    {
        $this->folder = $idSecure;
        $this->parent_folder = $idSecure;
        $this->resetLoadWindow();
    }

    public function createFolder(): void
    {
        $validated = $this->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $owner = User::query()->findOrFail($validated['owner_user_id']);
        $parent = $this->selectedParentFolder();

        if ($parent && $parent->owner_user_id !== $owner->id) {
            $this->addError('owner_user_id', __('Folder owner does not match the selected user.'));

            return;
        }

        if (AppFile::query()
            ->where('owner_user_id', $owner->id)
            ->where('is_folder', true)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->exists()) {
            $this->addError('name', __('A folder with this name already exists.'));

            return;
        }

        $folder = $this->files->storeFolder($owner, $validated['name'], $parent, $validated['note'] ?: null);

        log_activity('admin.files.create_folder', 'Created an admin-managed folder.', [
            'subject' => $folder,
            'metadata' => ['owner_user_id' => $owner->id],
        ]);

        $this->reset('name', 'note');
        $this->parent_folder = $this->folder;
        $this->notifySuccess(__('Folder created successfully.'));
        $this->refreshListing();
        $this->dispatch('appfiles-admin-folder-created');
    }

    public function createFolderFromDialog(string $name = '', string $note = '', string $parentFolder = '', string $ownerUserId = ''): void
    {
        $this->name = $name;
        $this->note = $note;
        $this->parent_folder = $parentFolder;
        $this->owner_user_id = $ownerUserId !== '' ? (int) $ownerUserId : null;

        $this->createFolder();
    }

    public function updateFolderFromDialog(string $idSecure = '', string $name = '', string $note = ''): void
    {
        $this->editing_folder = $idSecure;
        $this->edit_name = $name;
        $this->edit_note = $note;

        $this->updateFolder();
    }

    public function applyFiltersFromDialog(string $owner = '', string $category = 'all'): void
    {
        $this->owner = trim($owner);
        $this->owner_user_id = $this->owner !== '' ? (int) $this->owner : null;
        $this->category = trim($category) !== '' ? trim($category) : 'all';
        $this->folder = '';
        $this->parent_folder = '';
        $this->refreshListing();
    }

    public function startEditFolder(string $idSecure): void
    {
        $folder = AppFile::query()
            ->where('is_folder', true)
            ->where('id_secure', $idSecure)
            ->firstOrFail();

        $this->editing_folder = $folder->id_secure;
        $this->edit_name = $folder->name;
        $this->edit_note = (string) ($folder->note ?? '');
        $this->showEditFolderDialog = true;
        $this->resetErrorBag(['edit_name', 'edit_note', 'editing_folder']);
    }

    public function closeEditFolderDialog(): void
    {
        $this->showEditFolderDialog = false;
        $this->reset('editing_folder', 'edit_name', 'edit_note');
        $this->resetErrorBag(['edit_name', 'edit_note', 'editing_folder']);
    }

    public function updateFolder(): void
    {
        $validated = $this->validate([
            'editing_folder' => ['required', 'string'],
            'edit_name' => ['required', 'string', 'max:255'],
            'edit_note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'edit_name' => __('folder name'),
            'edit_note' => __('note'),
        ]);

        $folder = AppFile::query()
            ->where('is_folder', true)
            ->where('id_secure', $validated['editing_folder'])
            ->firstOrFail();

        if (AppFile::query()
            ->where('is_folder', true)
            ->where('owner_user_id', $folder->owner_user_id)
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

        log_activity('admin.files.update_folder', 'Updated a folder from admin.', [
            'subject' => $folder,
            'metadata' => ['owner_user_id' => $folder->owner_user_id],
        ]);

        $this->showEditFolderDialog = false;
        $this->reset('editing_folder', 'edit_name', 'edit_note');
        $this->notifySuccess(__('Folder updated successfully.'));
        $this->refreshListing();
        $this->dispatch(
            'appfiles-admin-folder-updated',
            id: $folder->id_secure,
            name: $folder->name,
            note: (string) ($folder->note ?? '')
        );
    }

    public function uploadSelectedFiles(): void
    {
        $this->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'uploads' => ['required', 'array', 'min:1'],
            'uploads.*' => [
                'required',
                File::types($this->files->allowedExtensions())->max($this->files->maxUploadMb() * 1024),
            ],
        ]);

        $owner = User::query()->findOrFail((int) $this->owner_user_id);
        $parent = $this->currentFolder();

        if ($parent && $parent->owner_user_id !== $owner->id) {
            $this->addError('owner_user_id', __('Folder owner does not match the selected user.'));

            return;
        }

        try {
            $stored = $this->files->storeUploads($owner, $this->uploads, $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'uploads' => $exception->getMessage(),
            ]);
        }

        log_activity('admin.files.upload', 'Uploaded files from the admin manager.', [
            'metadata' => ['count' => $stored->count(), 'owner_user_id' => $owner->id],
        ]);

        $this->reset('uploads');
        $this->notifySuccess(__('Uploaded :count file(s) successfully.', ['count' => $stored->count()]));
        $this->dispatch('appfiles-upload-finished');
        $this->refreshListing();
    }

    public function uploadFromUrl(): void
    {
        if (! $this->files->isActionEnabled('upload_from_url')) {
            $this->notifyError(__('Upload from URL is currently disabled.'));

            return;
        }

        $validated = $this->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'remote_url' => ['required', 'url', 'max:2048'],
        ]);

        $owner = User::query()->findOrFail((int) $validated['owner_user_id']);
        $parent = $this->currentFolder();

        if ($parent && $parent->owner_user_id !== $owner->id) {
            $this->addError('owner_user_id', __('Folder owner does not match the selected user.'));

            return;
        }

        try {
            $stored = $this->files->storeRemoteFile($owner, $validated['remote_url'], $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'remote_url' => $exception->getMessage(),
            ]);
        }

        log_activity('admin.files.upload_from_url', 'Imported a remote file from URL in admin manager.', [
            'subject' => $stored,
            'metadata' => ['owner_user_id' => $owner->id],
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
        $this->resetErrorBag(['remote_url', 'owner_user_id']);
        $this->dispatch('appfiles-open-remote-import');
    }

    public function importGoogleDriveFile(string $fileId, string $accessToken, array $pickerDocument = []): void
    {
        $this->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $owner = User::query()->findOrFail((int) $this->owner_user_id);
        $parent = $this->currentFolder();
        $this->dispatch('appfiles-debug', scope: 'admin', step: 'start', payload: [
            'actor_user_id' => auth()->id(),
            'owner_user_id' => $owner->id,
            'file_id' => $fileId,
            'parent_id' => $parent?->id,
            'picker_document' => $pickerDocument,
        ]);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            $this->addError('owner_user_id', __('Folder owner does not match the selected user.'));

            return;
        }

        try {
            $stored = $this->files->storeGoogleDriveFile($owner, $fileId, $accessToken, $parent, $pickerDocument);
        } catch (\Throwable $exception) {
            $this->dispatch('appfiles-debug', scope: 'admin', step: 'error', payload: [
                'actor_user_id' => auth()->id(),
                'owner_user_id' => $owner->id,
                'file_id' => $fileId,
                'parent_id' => $parent?->id,
                'message' => $exception->getMessage(),
            ]);
            throw ValidationException::withMessages([
                'google_drive' => $exception->getMessage(),
            ]);
        }
        $this->dispatch('appfiles-debug', scope: 'admin', step: 'saved', payload: [
            'actor_user_id' => auth()->id(),
            'owner_user_id' => $owner->id,
            'stored_id' => $stored->id,
            'stored_name' => $stored->name,
            'stored_owner_user_id' => $stored->owner_user_id,
            'stored_team_id' => $stored->team_id,
            'stored_parent_id' => $stored->parent_id,
        ]);

        log_activity('admin.files.google_drive_import', 'Imported a Google Drive file in admin manager.', [
            'subject' => $stored,
            'metadata' => ['owner_user_id' => $owner->id],
        ]);

        $this->notifySuccess(__('Imported the Google Drive file successfully.'));
        $this->refreshListing();
    }

    public function actionNotice(string $label): void
    {
        $this->notifySuccess(__(':label is available from the action menu, but still requires a manual export flow.', ['label' => $label]));
    }

    public function deleteItem(string $idSecure): void
    {
        $file = AppFile::query()->where('id_secure', $idSecure)->firstOrFail();
        $ownerId = $file->owner_user_id;
        $deletedId = $file->id_secure;

        $this->files->deleteTree($file);

        log_activity('admin.files.delete', 'Deleted a file manager item from admin.', [
            'metadata' => ['owner_user_id' => $ownerId],
        ]);

        $this->notifySuccess(__('Item deleted successfully.'));
        $this->refreshListing();
        $this->dispatch('appfiles-admin-item-deleted', id: $deletedId);
    }

    public function render(): View
    {
        $currentFolder = $this->currentFolder();
        $itemsQuery = $this->itemsQuery($currentFolder?->id);
        $items = $itemsQuery->limit($this->loadLimit)->get();
        $summaryQuery = AppFile::query()
            ->when($this->owner !== '', fn (Builder $query) => $query->where('owner_user_id', (int) $this->owner));
        $ownerValue = old('owner_user_id', $this->owner_user_id ?: $this->owner ?: $currentFolder?->owner_user_id);
        $selectedOwner = User::query()->find($this->owner !== '' ? (int) $this->owner : (int) ($currentFolder?->owner_user_id ?? 0));
        $storageHealth = app(\App\Support\Storage\StorageDriverManager::class)->diskHealth();

        return view('appfiles::admin.index', [
            'items' => $items,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $currentFolder?->breadcrumbChain() ?? [],
            'filters' => [
                'q' => $this->q,
                'category' => $this->category,
                'owner' => $this->owner,
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'files' => (clone $summaryQuery)->where('is_folder', false)->count(),
                'folders' => (clone $summaryQuery)->where('is_folder', true)->count(),
                'storage' => (clone $summaryQuery)->sum('size_bytes'),
            ],
            'storageByCategory' => (clone $summaryQuery)
                ->selectRaw('category, COALESCE(SUM(size_bytes), 0) as aggregate')
                ->groupBy('category')
                ->pluck('aggregate', 'category')
                ->toArray(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'availableFolders' => AppFile::query()
                ->when($this->owner !== '', fn (Builder $query) => $query->where('owner_user_id', (int) $this->owner))
                ->where('is_folder', true)
                ->orderBy('name')
                ->get(['id', 'id_secure', 'name', 'parent_id', 'owner_user_id']),
            'maxUploadMb' => $this->files->maxUploadMb(),
            'allowedExtensions' => $this->files->allowedExtensions(),
            'actionToggles' => [
                'upload_from_url' => $this->files->isActionEnabled('upload_from_url'),
                'google_drive' => $this->files->isActionEnabled('google_drive'),
                'dropbox' => $this->files->isActionEnabled('dropbox'),
                'onedrive' => $this->files->isActionEnabled('onedrive'),
                'adobe_express' => $this->files->isActionEnabled('adobe_express'),
                'quick_delete_action' => $this->files->isActionEnabled('quick_delete_action'),
            ],
            'ownerValue' => $ownerValue,
            'selectedOwner' => $selectedOwner,
            'googlePickerConfig' => $this->files->googlePickerConfig(auth()->user()),
            'dropboxChooserConfig' => $this->files->dropboxChooserConfig(auth()->user()),
            'oneDrivePickerConfig' => $this->files->oneDrivePickerConfig(auth()->user()),
            'adobeExpressConfig' => $this->files->adobeExpressConfig(auth()->user()),
            'remoteImportContext' => $this->remoteImportContext(),
            'hasMoreItems' => $items->count() < $this->matchingItemsCount($currentFolder?->id),
            'statusMessage' => $this->statusMessage,
            'errorMessage' => $this->errorMessage,
            'storageHealth' => $storageHealth,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Admin Files'),
            'fullWorkspace' => true,
            'showLoadingBackdrop' => false,
        ]);
    }

    protected function currentFolder(): ?AppFile
    {
        return $this->files->resolveFolderForAdmin($this->folder ?: null);
    }

    protected function itemsQuery(?int $folderId): Builder
    {
        return AppFile::query()
            ->with(['owner:id,name,username,email', 'team:id,name', 'parent:id,id_secure,name'])
            ->search($this->q)
            ->when($this->category !== 'all', function (Builder $query): void {
                if ($this->category === 'folder') {
                    $query->where('is_folder', true);

                    return;
                }

                $query->where('is_folder', false)->where('category', $this->category);
            })
            ->when($this->owner !== '', fn (Builder $query) => $query->where('owner_user_id', (int) $this->owner))
            ->inFolder($folderId)
            ->ordered();
    }

    protected function matchingItemsCount(?int $folderId): int
    {
        return (clone $this->itemsQuery($folderId))->count();
    }

    protected function hasMoreItems(): bool
    {
        return $this->loadLimit < $this->matchingItemsCount($this->currentFolder()?->id);
    }

    protected function selectedParentFolder(): ?AppFile
    {
        return $this->files->resolveFolderForAdmin($this->parent_folder ?: null);
    }

    protected function resetLoadWindow(): void
    {
        $this->loadLimit = 25;
    }

    protected function remoteImportContext(): array
    {
        return match ($this->remote_import_source) {
            'google_drive' => [
                'title' => __('Import from Google Drive'),
                'description' => __('Paste a Google Drive share link and the file will be imported into the selected owner scope.'),
                'label' => __('Google Drive share link'),
                'placeholder' => 'https://drive.google.com/file/d/...',
                'helper' => __('Public or accessible share links are supported. Native Docs, Sheets, or Slides export links may not be downloadable.'),
            ],
            'dropbox' => [
                'title' => __('Import from Dropbox'),
                'description' => __('Paste a Dropbox shared link and the file will be imported into the selected owner scope.'),
                'label' => __('Dropbox share link'),
                'placeholder' => 'https://www.dropbox.com/s/...',
                'helper' => __('Shared Dropbox links are converted to a direct download automatically.'),
            ],
            'onedrive' => [
                'title' => __('Import from OneDrive'),
                'description' => __('Paste a OneDrive shared link and the file will be imported into the selected owner scope.'),
                'label' => __('OneDrive share link'),
                'placeholder' => 'https://1drv.ms/...',
                'helper' => __('Shared OneDrive links are converted to a direct download automatically.'),
            ],
            default => [
                'title' => __('Upload from URL'),
                'description' => __('Import a remote file into the selected owner scope.'),
                'label' => __('File URL'),
                'placeholder' => 'https://example.com/file.png',
                'helper' => __('Direct file URLs and supported share links from Google Drive, Dropbox, and OneDrive can be used here.'),
            ],
        };
    }
}
