<?php

namespace Modules\AppFiles\Http\Controllers;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Http\Controllers\Concerns\HandlesOnlineMediaSearch;
use Modules\AppFiles\Http\Controllers\Concerns\ManagesImageEditor;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use Modules\AppFiles\Support\OnlineMediaSearchService;

class PortalFileController extends Controller
{
    use HandlesOnlineMediaSearch;
    use ManagesImageEditor;

    public function __construct(
        protected FileManager $files,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    protected function cleanUtf8(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($cleaned === false) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        return $cleaned;
    }

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->files->filesEnabled($user), 404);
        $currentFolder = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        $items = AppFile::query()
            ->ownedBy($user)
            ->with(['owner:id,name,username', 'team:id,name', 'parent:id,id_secure,name'])
            ->search($request->string('q')->toString())
            ->when(
                $request->filled('category') && $request->input('category') !== 'all',
                fn ($query) => $query->where('category', $request->input('category'))
            )
            ->inFolder($currentFolder?->id)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        $summaryQuery = AppFile::query()->ownedBy($user);

        return view('appfiles::portal.index', [
            'items' => $items,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $currentFolder?->breadcrumbChain() ?? [],
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->input('category', 'all'),
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'files' => (clone $summaryQuery)->where('is_folder', false)->count(),
                'folders' => (clone $summaryQuery)->where('is_folder', true)->count(),
                'storage' => (clone $summaryQuery)->sum('size_bytes'),
            ],
            'maxUploadMb' => $this->files->maxUploadMb($user),
            'allowedExtensions' => $this->files->allowedExtensions(),
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->files->canUpload($user), 403);
        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if (AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->exists()) {
            return back()->withErrors(['name' => __('A folder with this name already exists.')])->withInput();
        }

        $folder = $this->files->storeFolder($user, $validated['name'], $parent, $validated['note'] ?? null);

        log_activity('portal.files.create_folder', 'Created a user folder.', [
            'subject' => $folder,
            'metadata' => ['name' => $folder->name],
        ]);

        return redirect()
            ->route('portal.files.index', array_filter(['folder' => $parent?->id_secure]))
            ->with('status', __('Folder created successfully.'));
    }

    public function upload(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($this->files->canUpload($user), 403);
        $maxKb = $this->files->maxUploadMb($user) * 1024;
        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                File::types($this->files->allowedExtensions())->max($maxKb),
            ],
        ]);

        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);
        $stored = $this->files->storeUploads($user, $request->file('files', []), $parent);

        log_activity('portal.files.upload', 'Uploaded files from the user portal.', [
            'metadata' => ['count' => $stored->count()],
        ]);

        return redirect()
            ->route('portal.files.index', array_filter(['folder' => $parent?->id_secure]))
            ->with('status', __('Uploaded :count file(s) successfully.', ['count' => $stored->count()]));
    }

    public function download(Request $request, AppFile $file)
    {
        abort_if($file->is_folder, 404);
        abort_unless(AppFile::query()->ownedBy($request->user())->whereKey($file->id)->exists(), 404);
        abort_unless(filled($file->path) && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function preview(Request $request, AppFile $file)
    {
        abort_if($file->is_folder, 404);
        abort_unless(AppFile::query()->ownedBy($request->user())->whereKey($file->id)->exists(), 404);
        abort_unless($this->fileIsPreviewable($file), 404);

        return Storage::disk($file->disk)->response($file->path, $file->name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
        ]);
    }

    public function publishPreview(AppFile $file)
    {
        abort_if($file->is_folder, 404);
        abort_unless(filled($file->path) && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->response($file->path, $file->name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function pickerMedia(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->filesEnabled($user), 404);
        $imageEditorEnabled = $this->files->imageEditorEnabled($user);

        $type = $request->string('type')->toString();
        $sort = $request->string('sort')->toString();
        $category = $request->string('category')->toString();
        $perPage = max(1, min(100, (int) $request->integer('per_page', 30)));
        $currentFolder = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        $folders = AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('parent_id', $currentFolder?->id)
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->toString().'%')
            )
            ->orderBy('name')
            ->get()
            ->map(fn (AppFile $folder) => [
                'id' => $folder->id,
                'idSecure' => $folder->id_secure,
                'name' => $this->cleanUtf8($folder->name),
                'note' => $this->cleanUtf8($folder->note),
                'isFolder' => true,
            ])
            ->values();

        $editorReturnUrl = route('appfiles.editor-callback');

        $files = AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', false)
            ->where('parent_id', $currentFolder?->id)
            ->when($type === 'video', fn ($query) => $query->where('category', 'video'))
            ->when($type === 'image', fn ($query) => $query->where('is_image', true))
            ->when(
                $category !== '' && $category !== 'all' && ! in_array($category, ['image', 'video'], true),
                fn ($query) => $query->where('category', $category)
            )
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where('name', 'like', '%'.$request->string('q')->toString().'%')
            )
            ->when($sort === 'oldest', fn ($query) => $query->oldest('created_at')->oldest('id'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name')->orderByDesc('created_at')->orderByDesc('id'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name')->orderByDesc('created_at')->orderByDesc('id'))
            ->when(! in_array($sort, ['oldest', 'name_asc', 'name_desc'], true), fn ($query) => $query->latest('created_at')->latest('id'))
            ->paginate($perPage)
            ->through(function (AppFile $file) use ($editorReturnUrl, $imageEditorEnabled) {
                $previewUrl = route('portal.files.preview', $file);
                $fileUrl = $this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path) ?: $previewUrl;
                $editImageUrl = $imageEditorEnabled && $file->isEditableImage()
                    ? route('portal.files.edit-image', [
                        'file' => $file,
                        'return' => $editorReturnUrl,
                    ])
                    : null;

                return [
                    'id' => $file->id,
                    'idSecure' => $file->id_secure,
                    'name' => $this->cleanUtf8($file->name),
                    'note' => $this->cleanUtf8($file->note),
                    'path' => ltrim((string) ($file->path ?? ''), '/'),
                    'url' => $fileUrl,
                    'previewUrl' => $previewUrl,
                    'embedUrl' => $previewUrl,
                    'downloadUrl' => route('portal.files.download', $file),
                    'size' => $this->cleanUtf8($file->humanSize()),
                    'category' => $this->cleanUtf8($file->category),
                    'mimeType' => $this->cleanUtf8($file->mime_type),
                    'extension' => $this->cleanUtf8($file->extension),
                    'isImage' => (bool) $file->is_image,
                    'isEditableImage' => $file->isEditableImage(),
                    'typeLabel' => $this->cleanUtf8($file->typeLabel()),
                    'updatedShortLabel' => $file->updated_at?->format('M d'),
                    'updatedLabel' => $file->updated_at?->format('M d, Y'),
                    'editImageUrl' => $editImageUrl,
                ];
            });

        return response()->json([
            'folders' => $folders,
            'data' => $files->items(),
            'current_folder' => $currentFolder ? [
                'id' => $currentFolder->id,
                'idSecure' => $currentFolder->id_secure,
                'name' => $currentFolder->name,
            ] : null,
            'breadcrumbs' => collect($currentFolder?->breadcrumbChain() ?? [])
                ->push($currentFolder)
                ->filter()
                ->map(fn (AppFile $folder) => [
                    'id' => $folder->id,
                    'idSecure' => $folder->id_secure,
                    'name' => $this->cleanUtf8($folder->name),
                ])
                ->values(),
            'current_page' => $files->currentPage(),
            'next_page' => $files->hasMorePages() ? $files->currentPage() + 1 : null,
            'has_more' => $files->hasMorePages(),
        ]);
    }

    public function pickerUpload(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->canUpload($user), 403);
        $type = $request->string('type')->toString();
        $parent = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', File::types($this->files->allowedExtensions())->max($this->files->maxUploadMb($user) * 1024)],
        ]);

        $stored = $this->files->storeUploads($user, $request->file('files', []), $parent)
            ->filter(function (AppFile $file) use ($type) {
                return match ($type) {
                    'video' => $file->category === 'video',
                    'image' => $file->is_image,
                    default => true,
                };
            })
            ->values();

        return response()->json([
            'message' => __('Uploaded :count file(s).', ['count' => $stored->count()]),
            'data' => $stored->map(fn (AppFile $file) => $this->serializePickerFile($file, $request))->all(),
        ]);
    }

    public function pickerCreateFolder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->canUpload($user), 403);

        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if (AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => __('A folder with this name already exists.'),
            ]);
        }

        $folder = $this->files->storeFolder($user, $validated['name'], $parent, $validated['note'] ?? null);

        return response()->json([
            'message' => __('Folder created successfully.'),
            'data' => [
                'id' => $folder->id,
                'idSecure' => $folder->id_secure,
                'name' => $this->cleanUtf8($folder->name),
                'note' => $this->cleanUtf8($folder->note),
                'isFolder' => true,
            ],
        ]);
    }

    public function pickerUploadFromUrl(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->canUpload($user), 403);
        $type = $request->string('type')->toString();
        $parent = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        if (! $this->files->isActionEnabled('upload_from_url', $user)) {
            throw ValidationException::withMessages([
                'remote_url' => __('Upload from URL is currently disabled.'),
            ]);
        }

        $validated = $request->validate([
            'remote_url' => ['required', 'url', 'max:2048'],
            'source_provider' => ['nullable', 'in:unsplash,pexels,pixabay'],
            'source_author' => ['nullable', 'string', 'max:255'],
            'source_page' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        try {
            $stored = $this->files->storeRemoteFile($user, $validated['remote_url'], $parent);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'remote_url' => $exception->getMessage(),
            ]);
        }

        if ($type === 'video' && $stored->category !== 'video') {
            $this->files->deleteTree($stored);

            throw ValidationException::withMessages([
                'remote_url' => __('The imported file is not a video.'),
            ]);
        }

        if ($type === 'image' && ! $stored->is_image) {
            $this->files->deleteTree($stored);

            throw ValidationException::withMessages([
                'remote_url' => __('The imported file is not an image.'),
            ]);
        }

        if (!empty($validated['source_provider'])) {
            $stored->update(['note' => implode("\n", array_filter([
                'Source: '.ucfirst($validated['source_provider']),
                !empty($validated['source_author']) ? 'Author: '.$validated['source_author'] : null,
                $validated['source_page'] ?? null,
            ]))]);
        }

        return response()->json([
            'message' => __('Imported file successfully.'),
            'data' => $this->serializePickerFile($stored, $request),
        ]);
    }

    public function pickerImportGoogleDrive(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->canUpload($user), 403);
        abort_unless($this->files->isActionEnabled('google_drive', $user), 404);

        $validated = $request->validate([
            'file_id' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'max:4000'],
            'type' => ['nullable', 'string', 'max:20'],
            'folder' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'resource_key' => ['nullable', 'string', 'max:255'],
        ]);

        $type = (string) ($validated['type'] ?? '');
        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        try {
            $stored = $this->files->storeGoogleDriveFile($user, $validated['file_id'], $validated['access_token'], $parent, $validated);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'google_drive' => $exception->getMessage(),
            ]);
        }

        if ($type === 'video' && $stored->category !== 'video') {
            $this->files->deleteTree($stored);

            throw ValidationException::withMessages([
                'google_drive' => __('The selected Google Drive file is not a video.'),
            ]);
        }

        if ($type === 'image' && ! $stored->is_image) {
            $this->files->deleteTree($stored);

            throw ValidationException::withMessages([
                'google_drive' => __('The selected Google Drive file is not an image.'),
            ]);
        }

        return response()->json([
            'message' => __('Imported file from Google Drive successfully.'),
            'data' => $this->serializePickerFile($stored, $request),
        ]);
    }

    public function pickerImportAdobeExpress(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->canUpload($user), 403);
        abort_unless($this->files->isActionEnabled('adobe_express', $user), 404);

        $validated = $request->validate([
            'data_url' => ['required', 'string'],
            'folder' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        try {
            $stored = $this->files->storeDataUrlFile($user, $validated['data_url'], $parent, $validated['name'] ?? null);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'adobe_express' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => __('Imported image from Adobe Express successfully.'),
            'data' => $this->serializePickerFile($stored, $request),
        ]);
    }

    public function pickerSearchOnline(Request $request, OnlineMediaSearchService $search): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->searchMediaOnlineEnabled($user), 404);

        return $this->performOnlineMediaSearch($request, $search);
    }

    public function searchOnlinePage(Request $request, OnlineMediaSearchService $search): View
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->searchMediaOnlineEnabled($user), 404);

        return view('appfiles::portal.search-online', [
            'providers' => $search->providers('all'),
            'uploadFromUrlEndpoint' => route('portal.files.picker-upload-from-url'),
            'searchOnlineEndpoint' => route('portal.files.picker-search-online'),
        ]);
    }

    public function edit(Request $request, AppFile $file): View
    {
        abort_unless($this->files->imageEditorEnabled($request->user()), 404);
        abort_unless(AppFile::query()->ownedBy($request->user())->whereKey($file->id)->exists(), 404);

        $backUrl = $this->sanitizeReturnUrl(
            $request,
            route('portal.files.index', array_filter(['folder' => $file->parent?->id_secure]))
        );
        session(['appfiles.image_editor_return_url' => $backUrl]);

        return $this->renderImageEditor(
            $file,
            route('portal.files.preview', $file),
            route('portal.files.update-image', ['file' => $file, 'return' => $request->query('return')]),
            $backUrl,
            __('Edit image')
        );
    }

    public function updateImage(Request $request, AppFile $file): JsonResponse
    {
        abort_unless($this->files->imageEditorEnabled($request->user()), 404);

        return $this->updateEditedImage(
            $request,
            $file,
            $this->files->maxUploadMb($request->user()),
            fn (AppFile $record) => abort_unless(AppFile::query()->ownedBy($request->user())->whereKey($record->id)->exists(), 404)
        );
    }

    public function destroy(Request $request, AppFile $file): RedirectResponse
    {
        abort_unless(AppFile::query()->ownedBy($request->user())->whereKey($file->id)->exists(), 404);

        $parentSecure = $file->parent?->id_secure;

        $this->files->deleteTree($file);

        log_activity('portal.files.delete', 'Deleted a file manager item from the user portal.', [
            'metadata' => ['file' => $file->id_secure],
        ]);

        return redirect()
            ->route('portal.files.index', array_filter(['folder' => $parentSecure]))
            ->with('status', __('Item deleted successfully.'));
    }

    public function pickerDestroy(Request $request, string $fileKey): JsonResponse
    {
        $file = AppFile::query()
            ->ownedBy($request->user())
            ->where(function ($query) use ($fileKey) {
                $query->where('id_secure', $fileKey);

                if (ctype_digit($fileKey)) {
                    $query->orWhere('id', (int) $fileKey);
                }
            })
            ->firstOrFail();

        $deletedId = $file->id;
        $deletedSecureId = $file->id_secure;

        $this->files->deleteTree($file);

        return response()->json([
            'message' => __('Item deleted successfully.'),
            'data' => [
                'id' => $deletedId,
                'idSecure' => $deletedSecureId,
            ],
        ]);
    }

    protected function serializePickerFile(AppFile $file, Request $request): array
    {
        $previewUrl = route('portal.files.preview', $file);
        $fileUrl = $this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path) ?: $previewUrl;
        $editorReturnUrl = route('appfiles.editor-callback');
        $editImageUrl = $this->files->imageEditorEnabled($request->user()) && $file->isEditableImage()
            ? route('portal.files.edit-image', [
                'file' => $file,
                'return' => $editorReturnUrl,
            ])
            : null;

        return [
            'id' => $file->id,
            'idSecure' => $file->id_secure,
            'name' => $this->cleanUtf8($file->name),
            'note' => $this->cleanUtf8($file->note),
            'path' => ltrim((string) ($file->path ?? ''), '/'),
            'url' => $fileUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'size' => $this->cleanUtf8($file->humanSize()),
            'category' => $this->cleanUtf8($file->category),
            'mimeType' => $this->cleanUtf8($file->mime_type),
            'extension' => $this->cleanUtf8($file->extension),
            'isImage' => (bool) $file->is_image,
            'isEditableImage' => $file->isEditableImage(),
            'editImageUrl' => $editImageUrl,
        ];
    }

    protected function fileIsPreviewable(AppFile $file): bool
    {
        if (! filled($file->path)) {
            return false;
        }

        try {
            return Storage::disk((string) $file->disk)->exists((string) $file->path);
        } catch (\Throwable) {
            return false;
        }
    }
}
