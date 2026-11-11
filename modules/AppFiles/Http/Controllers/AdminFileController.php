<?php

namespace Modules\AppFiles\Http\Controllers;

use App\Support\Storage\StorageDriverManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\File;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Http\Controllers\Concerns\HandlesOnlineMediaSearch;
use Modules\AppFiles\Support\FileManager;
use Modules\AppFiles\Support\OnlineMediaSearchService;
use Modules\AppFiles\Http\Controllers\Concerns\ManagesImageEditor;

class AdminFileController extends Controller
{
    use ManagesImageEditor;
    use HandlesOnlineMediaSearch;

    public function __construct(
        protected FileManager $files,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    public function index(Request $request): View
    {
        $currentFolder = $this->files->resolveFolderForAdmin($request->string('folder')->toString());

        $items = AppFile::query()
            ->with(['owner:id,name,username,email', 'team:id,name', 'parent:id,id_secure,name'])
            ->search($request->string('q')->toString())
            ->when(
                $request->filled('category') && $request->input('category') !== 'all',
                fn ($query) => $query->where('category', $request->input('category'))
            )
            ->when($request->filled('owner'), fn ($query) => $query->where('owner_user_id', $request->integer('owner')))
            ->inFolder($currentFolder?->id)
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $summaryQuery = AppFile::query();

        return view('appfiles::admin.index', [
            'items' => $items,
            'currentFolder' => $currentFolder,
            'breadcrumbs' => $currentFolder?->breadcrumbChain() ?? [],
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->input('category', 'all'),
                'owner' => $request->input('owner'),
            ],
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'files' => (clone $summaryQuery)->where('is_folder', false)->count(),
                'folders' => (clone $summaryQuery)->where('is_folder', true)->count(),
                'storage' => (clone $summaryQuery)->sum('size_bytes'),
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'maxUploadMb' => $this->files->maxUploadMb(),
            'allowedExtensions' => $this->files->allowedExtensions(),
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $owner = User::query()->findOrFail($validated['owner_user_id']);
        $parent = $this->files->resolveFolderForAdmin($validated['folder'] ?? null);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            return back()->withErrors(['owner_user_id' => __('Folder owner does not match the selected user.')])->withInput();
        }

        if (AppFile::query()
            ->where('owner_user_id', $owner->id)
            ->where('is_folder', true)
            ->where('parent_id', $parent?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($validated['name']))])
            ->exists()) {
            return back()->withErrors(['name' => __('A folder with this name already exists.')])->withInput();
        }

        $folder = $this->files->storeFolder($owner, $validated['name'], $parent, $validated['note'] ?? null);

        log_activity('admin.files.create_folder', 'Created an admin-managed folder.', [
            'subject' => $folder,
            'metadata' => ['owner_user_id' => $owner->id],
        ]);

        return redirect()
            ->route('admin-files.index', array_filter(['folder' => $parent?->id_secure, 'owner' => $owner->id]))
            ->with('status', __('Folder created successfully.'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
                'required',
                File::types($this->files->allowedExtensions())->max($this->files->maxUploadMb() * 1024),
            ],
        ]);

        $owner = User::query()->findOrFail($validated['owner_user_id']);
        $parent = $this->files->resolveFolderForAdmin($validated['folder'] ?? null);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            return back()->withErrors(['owner_user_id' => __('Folder owner does not match the selected user.')])->withInput();
        }
        $stored = $this->files->storeUploads($owner, $request->file('files', []), $parent);

        log_activity('admin.files.upload', 'Uploaded files from the admin manager.', [
            'metadata' => ['count' => $stored->count(), 'owner_user_id' => $owner->id],
        ]);

        return redirect()
            ->route('admin-files.index', array_filter(['folder' => $parent?->id_secure, 'owner' => $owner->id]))
            ->with('status', __('Uploaded :count file(s) successfully.', ['count' => $stored->count()]));
    }

    public function download(AppFile $file)
    {
        abort_if($file->is_folder, 404);
        abort_unless(filled($file->path) && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function preview(AppFile $file)
    {
        abort_if($file->is_folder, 404);
        abort_unless($this->fileIsPreviewable($file), 404);

        return Storage::disk($file->disk)->response($file->path, $file->name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$file->name.'"',
        ]);
    }

    public function pickerImages(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $basePath = rtrim((string) $request->getBaseUrl(), '/');
        $type = $request->string('type')->toString();
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

        $sort = $request->string('sort')->toString();
        $category = $request->string('category')->toString();
        $currentFolder = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        $folders = AppFile::query()
            ->where('owner_user_id', $user->id)
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
                'name' => $folder->name,
                'note' => $folder->note,
                'isFolder' => true,
            ])
            ->values();

        $editorReturnUrl = $normalizeUrl(route('appfiles.editor-callback', [], false));

        $images = AppFile::query()
            ->where('owner_user_id', $user->id)
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
            ->when($sort === 'oldest', fn ($query) => $query->oldest('id'))
            ->when($sort === 'name_asc', fn ($query) => $query->orderBy('name')->orderByDesc('id'))
            ->when($sort === 'name_desc', fn ($query) => $query->orderByDesc('name')->orderByDesc('id'))
            ->when(! in_array($sort, ['oldest', 'name_asc', 'name_desc'], true), fn ($query) => $query->latest('id'))
            ->paginate(24)
            ->through(function (AppFile $file) use ($normalizeUrl, $editorReturnUrl) {
                $previewUrl = $normalizeUrl(route('admin-files.preview', $file, false));
                $fileUrl = $normalizeUrl($this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path)) ?: $previewUrl;
                $editImageUrl = $file->isEditableImage()
                    ? $normalizeUrl(route('admin-files.edit-image', [
                        'file' => $file,
                        'return' => $editorReturnUrl,
                    ], false))
                    : null;

                return [
                    'id' => $file->id,
                    'idSecure' => $file->id_secure,
                    'name' => $file->name,
                    'note' => $file->note,
                    'path' => ltrim((string) ($file->path ?? ''), '/'),
                    'url' => $fileUrl,
                    'previewUrl' => $previewUrl,
                    'embedUrl' => $previewUrl,
                    'downloadUrl' => $normalizeUrl(route('admin-files.download', $file, false)),
                    'size' => $file->humanSize(),
                    'category' => $file->category,
                    'mimeType' => $file->mime_type,
                    'extension' => $file->extension,
                    'isImage' => (bool) $file->is_image,
                    'isEditableImage' => $file->isEditableImage(),
                    'typeLabel' => $file->typeLabel(),
                    'updatedLabel' => $file->updated_at?->format('M d'),
                    'editImageUrl' => $editImageUrl,
                ];
            });

        return response()->json([
            'folders' => $folders,
            'data' => $images->items(),
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
                    'name' => $folder->name,
                ])
                ->values(),
            'current_page' => $images->currentPage(),
            'next_page' => $images->hasMorePages() ? $images->currentPage() + 1 : null,
            'has_more' => $images->hasMorePages(),
        ]);
    }

    public function browse(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403);

        $basePath = rtrim((string) $request->getBaseUrl(), '/');
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

        $perPage = max(1, min(100, (int) $request->integer('per_page', 25)));
        $category = trim((string) $request->input('category', 'all'));
        $owner = trim((string) $request->input('owner', ''));
        $currentFolder = $this->files->resolveFolderForAdmin($request->string('folder')->toString());
        $editorReturnUrl = $normalizeUrl(route('appfiles.editor-callback', [], false));

        $files = AppFile::query()
            ->search($request->string('q')->toString())
            ->when($category !== '' && $category !== 'all', function ($query) use ($category): void {
                if ($category === 'folder') {
                    $query->where('is_folder', true);

                    return;
                }

                $query->where('is_folder', false)->where('category', $category);
            }, fn ($query) => $query->where('is_folder', false))
            ->when($owner !== '', fn ($query) => $query->where('owner_user_id', (int) $owner))
            ->inFolder($currentFolder?->id)
            ->ordered()
            ->paginate($perPage)
            ->through(function (AppFile $file) use ($normalizeUrl, $editorReturnUrl) {
                $previewUrl = $normalizeUrl(route('admin-files.preview', $file, false));
                $fileUrl = $normalizeUrl($this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path)) ?: $previewUrl;
                $editImageUrl = $file->isEditableImage()
                    ? $normalizeUrl(route('admin-files.edit-image', [
                        'file' => $file,
                        'return' => $editorReturnUrl,
                    ], false))
                    : null;

                return [
                    'id' => $file->id,
                    'idSecure' => $file->id_secure,
                    'name' => $file->name,
                    'note' => $file->note,
                    'path' => ltrim((string) ($file->path ?? ''), '/'),
                    'url' => $fileUrl,
                    'previewUrl' => $previewUrl,
                    'embedUrl' => $previewUrl,
                    'downloadUrl' => $normalizeUrl(route('admin-files.download', $file, false)),
                    'size' => $file->humanSize(),
                    'category' => $file->category,
                    'mimeType' => $file->mime_type,
                    'extension' => $file->extension,
                    'isImage' => (bool) $file->is_image,
                    'isEditableImage' => $file->isEditableImage(),
                    'typeLabel' => $file->typeLabel(),
                    'updatedLabel' => $file->updated_at?->format('M d'),
                    'updatedShortLabel' => $file->updated_at?->format('M d'),
                    'editImageUrl' => $editImageUrl,
                ];
            });

        return response()->json([
            'data' => $files->items(),
            'current_page' => $files->currentPage(),
            'next_page' => $files->hasMorePages() ? $files->currentPage() + 1 : null,
            'has_more' => $files->hasMorePages(),
        ]);
    }

    public function pickerUpload(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $type = $request->string('type')->toString();
        $parent = $this->files->resolveFolderForUser($request->string('folder')->toString(), $user);

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', File::types($this->files->allowedExtensions())->max($this->files->maxUploadMb($user) * 1024)],
        ]);

        $stored = $this->files->storeUploads($user, $request->file('files', []), $parent)
            ->filter(function (AppFile $file) use ($type) {
                if ($type === 'video') {
                    return $file->category === 'video';
                }

                if ($type === 'image') {
                    return $file->is_image;
                }

                return true;
            })
            ->values();

        return response()->json([
            'message' => $type === 'video'
                ? __('Uploaded :count video file(s).', ['count' => $stored->count()])
                : ($type === 'image'
                    ? __('Uploaded :count image(s).', ['count' => $stored->count()])
                    : __('Uploaded :count file(s).', ['count' => $stored->count()])),
            'data' => $stored->map(fn (AppFile $file) => $this->serializePickerFile($file, $request))->all(),
        ]);
    }

    public function pickerCreateFolder(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'folder' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $parent = $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if (AppFile::query()
            ->where('owner_user_id', $user->id)
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
                'name' => $folder->name,
                'note' => $folder->note,
                'isFolder' => true,
            ],
        ]);
    }

    public function pickerUploadFromUrl(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        $type = $request->string('type')->toString();

        if (! $this->files->isActionEnabled('upload_from_url')) {
            throw ValidationException::withMessages([
                'remote_url' => __('Upload from URL is currently disabled.'),
            ]);
        }

        $validated = $request->validate([
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'remote_url' => ['required', 'url', 'max:2048'],
            'folder' => ['nullable', 'string'],
        ]);

        $owner = isset($validated['owner_user_id'])
            ? User::query()->findOrFail((int) $validated['owner_user_id'])
            : $user;

        $parent = isset($validated['owner_user_id'])
            ? $this->files->resolveFolderForAdmin($validated['folder'] ?? null)
            : $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'owner_user_id' => __('Folder owner does not match the selected user.'),
            ]);
        }

        try {
            $stored = $this->files->storeRemoteFile($owner, $validated['remote_url'], $parent);
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

        return response()->json([
            'message' => $type === 'video'
                ? __('Imported video successfully.')
                : ($type === 'image'
                    ? __('Imported image successfully.')
                    : __('Imported file successfully.')),
            'data' => $this->serializePickerFile($stored, $request),
        ]);
    }

    public function pickerImportGoogleDrive(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->isActionEnabled('google_drive', $user), 404);

        $validated = $request->validate([
            'file_id' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'max:4000'],
            'type' => ['nullable', 'string', 'max:20'],
            'folder' => ['nullable', 'string'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'resource_key' => ['nullable', 'string', 'max:255'],
        ]);

        $type = (string) ($validated['type'] ?? '');
        $owner = isset($validated['owner_user_id'])
            ? User::query()->findOrFail((int) $validated['owner_user_id'])
            : $user;

        $parent = isset($validated['owner_user_id'])
            ? $this->files->resolveFolderForAdmin($validated['folder'] ?? null)
            : $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'owner_user_id' => __('Folder owner does not match the selected user.'),
            ]);
        }

        try {
            $stored = $this->files->storeGoogleDriveFile($owner, $validated['file_id'], $validated['access_token'], $parent, $validated);
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
            'message' => $type === 'video'
                ? __('Imported video from Google Drive successfully.')
                : ($type === 'image'
                    ? __('Imported image from Google Drive successfully.')
                    : __('Imported file from Google Drive successfully.')),
            'data' => $this->serializePickerFile($stored, $request),
        ]);
    }

    public function pickerImportAdobeExpress(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless($this->files->isActionEnabled('adobe_express', $user), 404);

        $validated = $request->validate([
            'data_url' => ['required', 'string'],
            'folder' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $owner = isset($validated['owner_user_id'])
            ? User::query()->findOrFail((int) $validated['owner_user_id'])
            : $user;

        $parent = isset($validated['owner_user_id'])
            ? $this->files->resolveFolderForAdmin($validated['folder'] ?? null)
            : $this->files->resolveFolderForUser($validated['folder'] ?? null, $user);

        if ($parent && $parent->owner_user_id !== $owner->id) {
            throw ValidationException::withMessages([
                'owner_user_id' => __('Folder owner does not match the selected user.'),
            ]);
        }

        try {
            $stored = $this->files->storeDataUrlFile($owner, $validated['data_url'], $parent, $validated['name'] ?? null);
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
        $user = $request->user();
        abort_unless($user, 403);

        return $this->performOnlineMediaSearch($request, $search);
    }

    public function edit(Request $request, AppFile $file): View
    {
        $backUrl = $this->sanitizeReturnUrl(
            $request,
            route('admin-files.index', array_filter(['folder' => $file->parent?->id_secure, 'owner' => $file->owner_user_id]))
        );
        session(['appfiles.image_editor_return_url' => $backUrl]);

        return $this->renderImageEditor(
            $file,
            route('admin-files.preview', $file),
            route('admin-files.update-image', ['file' => $file, 'return' => $request->query('return')]),
            $backUrl,
            __('Edit image')
        );
    }

    public function updateImage(Request $request, AppFile $file): JsonResponse
    {
        return $this->updateEditedImage(
            $request,
            $file,
            $this->files->maxUploadMb(),
            static fn (AppFile $record) => $record
        );
    }

    public function destroy(Request $request, AppFile $file): RedirectResponse
    {
        $parentSecure = $file->parent?->id_secure;
        $ownerId = $file->owner_user_id;

        $this->files->deleteTree($file);

        log_activity('admin.files.delete', 'Deleted a file manager item from admin.', [
            'metadata' => ['owner_user_id' => $ownerId],
        ]);

        return redirect()
            ->route('admin-files.index', array_filter(['folder' => $parentSecure, 'owner' => $request->input('owner', $ownerId)]))
            ->with('status', __('Item deleted successfully.'));
    }

    public function pickerDestroy(Request $request, string $fileKey): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $file = AppFile::query()
            ->where('owner_user_id', $user->id)
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
        $basePath = rtrim((string) $request->getBaseUrl(), '/');
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

        $previewUrl = $normalizeUrl(route('admin-files.preview', $file, false));
        $editorReturnUrl = $normalizeUrl(route('appfiles.editor-callback', [], false));
        $editImageUrl = $file->isEditableImage()
            ? $normalizeUrl(route('admin-files.edit-image', [
                'file' => $file,
                'return' => $editorReturnUrl,
            ], false))
            : null;
        $fileUrl = $normalizeUrl($this->storageDriverManager->publicUrl((string) $file->disk, (string) $file->path)) ?: $previewUrl;

        return [
            'id' => $file->id,
            'name' => $file->name,
            'note' => $file->note,
            'path' => ltrim((string) ($file->path ?? ''), '/'),
            'url' => $fileUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'size' => $file->humanSize(),
            'category' => $file->category,
            'mimeType' => $file->mime_type,
            'extension' => $file->extension,
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
