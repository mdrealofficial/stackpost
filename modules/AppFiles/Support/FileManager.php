<?php

namespace Modules\AppFiles\Support;

use App\Support\Http\OutboundUrlPolicy;
use App\Support\Storage\StorageDriverManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\User;
use Modules\AppFiles\Exceptions\FileImportLimitException;
use Modules\AppFiles\Models\AppFile;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use RuntimeException;
use Throwable;

class FileManager
{
    public function __construct(
        protected OptionStore $options,
        protected StorageDriverManager $storageDriverManager,
        protected OutboundUrlPolicy $outboundUrls,
    ) {}

    public function disk(): string
    {
        return $this->storageDriverManager->activeDisk();
    }

    public function allowedExtensions(): array
    {
        $configured = $this->options->get('appfiles_allowed_extensions');
        $extensions = is_string($configured) && trim($configured) !== ''
            ? preg_split('/[\s,]+/', trim($configured)) ?: []
            : (array) config('modules.appfiles.allowed_extensions', []);

        return array_values(array_unique(array_map(
            static fn ($extension) => strtolower(trim((string) $extension)),
            $extensions
        )));
    }

    public function maxUploadMb(?User $user = null): int
    {
        $planLimit = $this->planOwner($user)?->planLimit('max_file_size_mb');

        if (is_numeric($planLimit) && (int) $planLimit > 0) {
            return (int) $planLimit;
        }

        return (int) $this->options->get('appfiles_default_max_upload_mb', config('modules.appfiles.default_max_upload_mb', 20));
    }

    public function maxStorageMb(?User $user = null): ?int
    {
        $planLimit = $this->planOwner($user)?->planLimit('max_storage_size_mb');

        if (is_numeric($planLimit)) {
            $value = (int) $planLimit;

            return $value < 0 ? null : $value;
        }

        return null;
    }

    public function filesEnabled(?User $user = null): bool
    {
        if (! $user) {
            return false;
        }

        $team = TeamWorkspaceAccess::activeTeam($user);

        if ($team && ! TeamWorkspaceAccess::hasPermission($user, 'files.view', $team)) {
            return false;
        }

        return $this->planOwner($user)?->canUsePlanFeature('files') ?? false;
    }

    public function canUpload(?User $user = null): bool
    {
        if (! $this->filesEnabled($user)) {
            return false;
        }

        $team = TeamWorkspaceAccess::activeTeam($user);

        return ! $team || TeamWorkspaceAccess::hasPermission($user, 'files.upload', $team);
    }

    public function imageEditorEnabled(?User $user = null): bool
    {
        if (! $this->filesEnabled($user)) {
            return false;
        }

        $planOwner = $this->planOwner($user);

        if (! $planOwner?->plan) {
            return true;
        }

        return $planOwner->hasPlanFeature('image_editor');
    }

    public function searchMediaOnlineEnabled(?User $user = null): bool
    {
        if (! $this->filesEnabled($user)) {
            return false;
        }

        $planOwner = $this->planOwner($user);

        if (! $planOwner?->plan) {
            return true;
        }

        return $planOwner->hasPlanFeature('search_media_online');
    }

    public function isActionEnabled(string $key, ?User $user = null): bool
    {
        $default = match ($key) {
            'upload_from_url' => (bool) config('modules.appfiles.enable_upload_from_url', true),
            'google_drive' => (bool) config('modules.appfiles.enable_google_drive', false),
            'dropbox' => (bool) config('modules.appfiles.enable_dropbox', false),
            'onedrive' => (bool) config('modules.appfiles.enable_onedrive', false),
            'adobe_express' => (bool) config('modules.appfiles.enable_adobe_express', false),
            'quick_delete_action' => (bool) config('modules.appfiles.enable_quick_delete_action', true),
            default => false,
        };

        $enabled = (string) $this->options->get('appfiles_'.$key, $default ? '1' : '0') === '1';

        if (! $enabled) {
            return false;
        }

        $planOwner = $this->planOwner($user);

        return match ($key) {
            'google_drive' => ! $planOwner || ! $planOwner->plan || $planOwner->hasPlanFeature('file_google_drive'),
            'dropbox' => ! $planOwner || ! $planOwner->plan || $planOwner->hasPlanFeature('file_dropbox'),
            'onedrive' => ! $planOwner || ! $planOwner->plan || $planOwner->hasPlanFeature('file_onedrive'),
            default => true,
        };
    }

    protected function planOwner(?User $user = null): ?User
    {
        if (! $user) {
            return null;
        }

        $ownerUserId = TeamWorkspaceAccess::workspaceOwnerUserId($user);

        if ($ownerUserId <= 0 || $ownerUserId === (int) $user->id) {
            return $user;
        }

        return User::query()->find($ownerUserId) ?: $user;
    }

    public function googlePickerConfig(?User $user = null): array
    {
        $clientId = trim((string) $this->options->get('appfiles_google_drive_client_id', ''));
        $apiKey = trim((string) $this->options->get('appfiles_google_drive_client_secret', ''));

        return [
            'enabled' => $this->isActionEnabled('google_drive', $user) && $clientId !== '' && $apiKey !== '',
            'clientId' => $clientId,
            'apiKey' => $apiKey,
        ];
    }

    public function dropboxChooserConfig(?User $user = null): array
    {
        $appKey = trim((string) $this->options->get('appfiles_dropbox_app_key', ''));

        return [
            'enabled' => $this->isActionEnabled('dropbox', $user) && $appKey !== '',
            'appKey' => $appKey,
        ];
    }

    public function oneDrivePickerConfig(?User $user = null): array
    {
        $clientId = trim((string) $this->options->get('appfiles_onedrive_client_id', ''));

        return [
            'enabled' => $this->isActionEnabled('onedrive', $user) && $clientId !== '',
            'clientId' => $clientId,
        ];
    }

    public function adobeExpressConfig(?User $user = null): array
    {
        $clientId = trim((string) $this->options->get('appfiles_adobe_express_api_key', ''));
        $appName = trim((string) $this->options->get('appfiles_adobe_express_app_name', ''));

        if ($clientId === '') {
            $clientId = trim((string) $this->options->get('appfiles_adobe_express_client_id', ''));
        }

        if ($appName === '') {
            $appName = trim((string) $this->options->get('appfiles_adobe_express_client_secret', ''));
        }

        $looksLikeApiKey = static fn (string $value): bool => (bool) preg_match('/^[A-Za-z0-9_-]{20,}$/', $value);
        $looksLikeAppName = static fn (string $value): bool => $value !== '' && ! $looksLikeApiKey($value);

        if ($looksLikeAppName($clientId) && $looksLikeApiKey($appName)) {
            [$clientId, $appName] = [$appName, $clientId];
        }

        if ($appName === '') {
            $appName = trim((string) config('app.name', 'Stackposts'));
        }

        return [
            'enabled' => $this->isActionEnabled('adobe_express', $user) && $clientId !== '' && $appName !== '',
            'clientId' => $clientId,
            'appName' => $appName,
        ];
    }

    public function storageUsedBytes(User $user): int
    {
        return (int) AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', false)
            ->sum('size_bytes');
    }

    public function canStoreBytes(User $user, int $bytesToAdd = 0): bool
    {
        $limitMb = $this->maxStorageMb($user);

        if ($limitMb === null) {
            return true;
        }

        return ($this->storageUsedBytes($user) + max(0, $bytesToAdd)) <= ($limitMb * 1024 * 1024);
    }

    public function ensureWithinStorageLimit(User $user, int $bytesToAdd = 0): void
    {
        if ($this->canStoreBytes($user, $bytesToAdd)) {
            return;
        }

        throw new RuntimeException(__('Your current plan has reached the file storage limit.'));
    }

    public function currentTeamId(User $user): ?int
    {
        return TeamWorkspaceAccess::activeTeam($user)?->id
            ?: $user->ownedTeams()->value('id')
            ?: $user->teams()->value('teams.id');
    }

    public function resolveFolderForUser(?string $idSecure, User $user): ?AppFile
    {
        if (blank($idSecure)) {
            return null;
        }

        return AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', true)
            ->where('id_secure', $idSecure)
            ->firstOrFail();
    }

    public function resolveFolderForAdmin(?string $idSecure): ?AppFile
    {
        if (blank($idSecure)) {
            return null;
        }

        return AppFile::query()
            ->where('is_folder', true)
            ->where('id_secure', $idSecure)
            ->firstOrFail();
    }

    public function storeFolder(User $owner, string $name, ?AppFile $parent = null, ?string $note = null): AppFile
    {
        return AppFile::query()->create([
            'owner_user_id' => $owner->id,
            'team_id' => $parent?->team_id ?? $this->currentTeamId($owner),
            'parent_id' => $parent?->id,
            'disk' => $this->disk(),
            'name' => trim($name),
            'path' => null,
            'mime_type' => null,
            'extension' => null,
            'category' => 'folder',
            'size_bytes' => 0,
            'is_folder' => true,
            'is_image' => false,
            'note' => $note,
        ]);
    }

    public function storeUploads(User $owner, array $uploads, ?AppFile $parent = null): Collection
    {
        $stored = collect();
        $disk = $this->disk();
        $this->storageDriverManager->ensureDiskReadyForWrites($disk);
        $teamId = $parent?->team_id ?? $this->currentTeamId($owner);
        $totalUploadBytes = collect($uploads)
            ->filter(fn ($upload) => $upload instanceof UploadedFile)
            ->sum(fn (UploadedFile $upload) => (int) ($upload->getSize() ?? 0));

        $this->ensureWithinStorageLimit($owner, $totalUploadBytes);

        foreach ($uploads as $upload) {
            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower((string) $upload->getClientOriginalExtension());
            $mimeType = (string) ($upload->getMimeType() ?: $upload->getClientMimeType());
            $safeName = $this->sanitizeName(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME), $extension);
            $folderPath = trim(implode('/', array_filter([
                'files',
                'user-'.$owner->id,
                now()->format('Y'),
                now()->format('m'),
            ])), '/');
            $storedPath = Storage::disk($disk)->putFileAs($folderPath, $upload, $safeName);
            $storedPath = $this->assertStoredPath($disk, $storedPath, __('The file could not be written to the configured storage disk.'));

            [$width, $height, $isImage] = $this->imageMeta($upload);

            $stored->push(AppFile::query()->create([
                'owner_user_id' => $owner->id,
                'team_id' => $teamId,
                'parent_id' => $parent?->id,
                'disk' => $disk,
                'name' => $upload->getClientOriginalName(),
                'path' => $storedPath,
                'mime_type' => $mimeType,
                'extension' => $extension ?: null,
                'category' => $this->detectCategory($mimeType, $extension),
                'size_bytes' => (int) $upload->getSize(),
                'is_folder' => false,
                'is_image' => $isImage,
                'width' => $width,
                'height' => $height,
            ]));
        }

        return $stored;
    }

    public function storeRemoteFile(User $owner, string $url, ?AppFile $parent = null): AppFile
    {
        return $this->storeRemoteFileInWorkspace(
            $owner,
            $parent?->team_id ?? $this->currentTeamId($owner),
            $url,
            $parent,
        );
    }

    public function storeRemoteFileInWorkspace(User $owner, ?int $teamId, string $url, ?AppFile $parent = null): AppFile
    {
        $normalizedUrl = $this->normalizeRemoteImportUrl($url);
        $target = $this->outboundUrls->inspect($normalizedUrl);
        $response = null;

        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $response = Http::withOptions($target->requestOptions(stream: true))
                ->connectTimeout(10)
                ->timeout(25)
                ->get($target->url);

            if (! $response->redirect()) {
                break;
            }

            if ($redirects >= 3) {
                throw new RuntimeException(__('The remote file returned too many redirects.'));
            }

            $location = trim((string) $response->header('Location'));
            $response->toPsrResponse()->getBody()->close();
            $target = $this->outboundUrls->resolveRedirect($target, $location);
        }

        if (! $response || ! $response->successful()) {
            throw new RuntimeException(__('Could not fetch the remote file.'));
        }

        $maxBytes = max(1, $this->maxUploadMb($owner)) * 1024 * 1024;
        $contentLength = trim((string) $response->header('Content-Length'));

        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $maxBytes) {
            throw new RuntimeException(__('The remote file is larger than the allowed upload size.'));
        }

        $temporary = tmpfile();

        if (! is_resource($temporary)) {
            throw new RuntimeException(__('The remote file could not be buffered safely.'));
        }

        try {
            $body = $response->toPsrResponse()->getBody();
            $sizeBytes = 0;

            while (! $body->eof()) {
                $chunk = $body->read(8192);

                if ($chunk === '') {
                    break;
                }

                $sizeBytes += strlen($chunk);

                if ($sizeBytes > $maxBytes) {
                    throw new RuntimeException(__('The remote file is larger than the allowed upload size.'));
                }

                if (fwrite($temporary, $chunk) === false) {
                    throw new RuntimeException(__('The remote file could not be buffered safely.'));
                }
            }

            if ($sizeBytes <= 0) {
                throw new RuntimeException(__('The remote file response was empty.'));
            }

            $this->ensureWithinStorageLimit($owner, $sizeBytes);

            $disk = $this->disk();
            $this->storageDriverManager->ensureDiskReadyForWrites($disk);
            $parsedPath = parse_url($target->url, PHP_URL_PATH) ?: parse_url($url, PHP_URL_PATH) ?: '';
            $originalName = Str::limit(urldecode(basename($parsedPath) ?: 'remote-file'), 240, '');
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $mimeType = strtolower(trim((string) Str::before((string) ($response->header('Content-Type') ?: 'application/octet-stream'), ';')));
            $safeName = $this->sanitizeName(pathinfo($originalName, PATHINFO_FILENAME), $extension);
            $folderPath = trim(implode('/', array_filter([
                'files',
                'user-'.$owner->id,
                now()->format('Y'),
                now()->format('m'),
            ])), '/');
            $storedPath = $folderPath.'/'.$safeName;

            rewind($temporary);
            $written = Storage::disk($disk)->put($storedPath, $temporary);
            $this->assertWriteSucceeded($disk, $storedPath, $written, __('The remote file could not be written to the configured storage disk.'));

            return AppFile::query()->create([
                'owner_user_id' => $owner->id,
                'team_id' => $parent?->team_id ?? $teamId,
                'parent_id' => $parent?->id,
                'disk' => $disk,
                'name' => $originalName,
                'path' => $storedPath,
                'mime_type' => $mimeType,
                'extension' => $extension ?: null,
                'category' => $this->detectCategory($mimeType, $extension),
                'size_bytes' => $sizeBytes,
                'is_folder' => false,
                'is_image' => Str::startsWith(strtolower($mimeType), 'image/'),
                'width' => null,
                'height' => null,
            ]);
        } finally {
            fclose($temporary);
        }
    }

    public function normalizeRemoteImportUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        return $this->normalizeOneDriveShareUrl(
            $this->normalizeDropboxShareUrl(
                $this->normalizeGoogleDriveShareUrl($url)
            )
        );
    }

    protected function normalizeGoogleDriveShareUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($host, ['drive.google.com', 'www.drive.google.com', 'docs.google.com', 'www.docs.google.com'], true)) {
            return $url;
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $fileId = null;

        if (preg_match('~/(?:file/d|document/d|presentation/d|spreadsheets/d)/([^/]+)~', $url, $matches)) {
            $fileId = $matches[1] ?? null;
        }

        if (! is_string($fileId) || $fileId === '') {
            $fileId = Arr::get($query, 'id');
        }

        if (! is_string($fileId) || $fileId === '') {
            return $url;
        }

        return 'https://drive.google.com/uc?export=download&id='.$fileId;
    }

    protected function normalizeDropboxShareUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (in_array($host, ['dl.dropboxusercontent.com', 'dl.dropbox.com'], true)) {
            return $url;
        }

        if (! in_array($host, ['dropbox.com', 'www.dropbox.com'], true)) {
            return $url;
        }

        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');

        if ($path === '') {
            return $url;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['dl'], $query['raw']);
        $query['dl'] = '1';
        $query['raw'] = '1';

        return 'https://www.dropbox.com'.$path.'?'.http_build_query($query);
    }

    protected function normalizeOneDriveShareUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if (! in_array($host, ['1drv.ms', 'onedrive.live.com'], true)) {
            return $url;
        }

        $encoded = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');

        return 'https://api.onedrive.com/v1.0/shares/u!'.$encoded.'/root/content';
    }

    public function storeBinaryFile(User $owner, string $originalName, string $mimeType, string $contents, ?AppFile $parent = null, ?string $note = null): AppFile
    {
        if ($contents === '') {
            throw new RuntimeException(__('The generated file payload was empty.'));
        }

        $this->ensureWithinStorageLimit($owner, strlen($contents));

        $disk = $this->disk();
        $this->storageDriverManager->ensureDiskReadyForWrites($disk);
        $teamId = $parent?->team_id ?? $this->currentTeamId($owner);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = $extension !== '' ? $extension : match (strtolower($mimeType)) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $safeName = $this->sanitizeName(pathinfo($originalName, PATHINFO_FILENAME), $extension);
        $folderPath = trim(implode('/', array_filter([
            'files',
            'user-'.$owner->id,
            now()->format('Y'),
            now()->format('m'),
        ])), '/');
        $storedPath = $folderPath.'/'.$safeName;

        $written = Storage::disk($disk)->put($storedPath, $contents);
        $this->assertWriteSucceeded($disk, $storedPath, $written, __('The generated file could not be written to the configured storage disk.'));

        $imageInfo = @getimagesizefromstring($contents);
        $isImage = is_array($imageInfo);

        return AppFile::query()->create([
            'owner_user_id' => $owner->id,
            'team_id' => $teamId,
            'parent_id' => $parent?->id,
            'disk' => $disk,
            'name' => $originalName,
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'extension' => $extension ?: null,
            'category' => $this->detectCategory($mimeType, $extension),
            'size_bytes' => strlen($contents),
            'is_folder' => false,
            'is_image' => $isImage,
            'width' => $isImage && isset($imageInfo[0]) ? (int) $imageInfo[0] : null,
            'height' => $isImage && isset($imageInfo[1]) ? (int) $imageInfo[1] : null,
            'note' => $note,
        ]);
    }

    public function storeDataUrlFile(User $owner, string $dataUrl, ?AppFile $parent = null, ?string $originalName = null, ?string $note = null): AppFile
    {
        $dataUrl = trim($dataUrl);

        if (! preg_match('/^data:(?<mime>[-\w.+\/]+);base64,(?<payload>.+)$/s', $dataUrl, $matches)) {
            throw new RuntimeException(__('The Adobe Express asset payload was invalid.'));
        }

        $mimeType = strtolower(trim((string) ($matches['mime'] ?? 'application/octet-stream')));
        $payload = preg_replace('/\s+/', '', (string) ($matches['payload'] ?? ''));
        $contents = base64_decode($payload, true);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException(__('The Adobe Express asset payload was empty.'));
        }

        $originalName = trim((string) $originalName);

        if ($originalName === '') {
            $extension = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
                default => 'png',
            };

            $originalName = 'adobe-express-'.Str::lower(Str::random(8)).'.'.$extension;
        }

        return $this->storeBinaryFile($owner, $originalName, $mimeType, $contents, $parent, $note);
    }

    public function storeGoogleDriveFile(User $owner, string $fileId, string $accessToken, ?AppFile $parent = null, array $pickerDocument = []): AppFile
    {
        $fileId = trim($fileId);
        $accessToken = trim($accessToken);
        $resourceKey = trim((string) ($pickerDocument['resource_key'] ?? $pickerDocument['resourceKey'] ?? ''));
        $note = filled($pickerDocument['note'] ?? null)
            ? trim((string) $pickerDocument['note'])
            : null;

        if ($fileId === '' || $accessToken === '') {
            throw new RuntimeException(__('Google Drive file information is missing.'));
        }

        $metadataQuery = [
            'fields' => 'id,name,mimeType,size',
            'supportsAllDrives' => 'true',
        ];

        if ($resourceKey !== '') {
            $metadataQuery['resourceKey'] = $resourceKey;
        }

        $metadataResponse = Http::withToken($accessToken)
            ->timeout(25)
            ->get('https://www.googleapis.com/drive/v3/files/'.$fileId, $metadataQuery);

        if (! $metadataResponse->successful()) {
            throw new RuntimeException(__('Could not read the selected Google Drive file.'));
        }

        $metadata = $metadataResponse->json();
        $mimeType = (string) data_get($metadata, 'mimeType', 'application/octet-stream');
        $name = (string) data_get($metadata, 'name', ($pickerDocument['name'] ?? 'google-drive-file'));
        $expectedSizeBytes = $this->parseRemoteByteCount(data_get($metadata, 'size'));
        $hardLimitBytes = $this->googleDriveImportHardLimitBytes($owner, $expectedSizeBytes);

        if (str_starts_with($mimeType, 'application/vnd.google-apps')) {
            $exportMap = [
                'application/vnd.google-apps.document' => [
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'name' => 'document.docx',
                ],
                'application/vnd.google-apps.spreadsheet' => [
                    'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'name' => 'spreadsheet.xlsx',
                ],
                'application/vnd.google-apps.presentation' => [
                    'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'name' => 'presentation.pptx',
                ],
            ];

            $exportTarget = $exportMap[$mimeType] ?? null;

            if (! $exportTarget) {
                throw new RuntimeException(__('This Google Drive file type is not supported by this import yet.'));
            }

            $exportQuery = [
                'mimeType' => $exportTarget['mime'],
            ];

            if ($resourceKey !== '') {
                $exportQuery['resourceKey'] = $resourceKey;
            }

            $normalizedName = $this->normalizeImportedFileName($name !== '' ? $name : $exportTarget['name'], $exportTarget['mime']);
            $tempPath = $this->downloadGoogleDriveFileToTemp(
                accessToken: $accessToken,
                url: 'https://www.googleapis.com/drive/v3/files/'.$fileId.'/export',
                query: $exportQuery,
                hardLimitBytes: $hardLimitBytes,
                failureMessage: __('Could not export the selected Google Drive file.'),
            );

            try {
                return $this->storeDownloadedFile($owner, $normalizedName, $exportTarget['mime'], $tempPath, $parent, $note);
            } finally {
                @unlink($tempPath);
            }
        }

        $downloadQuery = [
            'alt' => 'media',
            'supportsAllDrives' => 'true',
        ];

        if ($resourceKey !== '') {
            $downloadQuery['resourceKey'] = $resourceKey;
        }

        $name = $this->normalizeImportedFileName($name, $mimeType);
        $tempPath = $this->downloadGoogleDriveFileToTemp(
            accessToken: $accessToken,
            url: 'https://www.googleapis.com/drive/v3/files/'.$fileId,
            query: $downloadQuery,
            hardLimitBytes: $hardLimitBytes,
            failureMessage: __('Could not download the selected Google Drive file.'),
        );

        try {
            return $this->storeDownloadedFile($owner, $name, $mimeType, $tempPath, $parent, $note);
        } finally {
            @unlink($tempPath);
        }
    }

    protected function googleDriveImportHardLimitBytes(User $owner, ?int $expectedSizeBytes = null): int
    {
        $maxUploadBytes = $this->megabytesToBytes($this->maxUploadMb($owner));

        if ($maxUploadBytes <= 0) {
            throw new FileImportLimitException(__('Google Drive imports are not available because the upload size limit is zero.'));
        }

        if ($expectedSizeBytes !== null && $expectedSizeBytes > $maxUploadBytes) {
            throw new FileImportLimitException(__('The selected Google Drive file is larger than the allowed upload size.'));
        }

        $storageLimitMb = $this->maxStorageMb($owner);

        if ($storageLimitMb === null) {
            return $maxUploadBytes;
        }

        $remainingStorageBytes = max(
            0,
            $this->megabytesToBytes($storageLimitMb) - $this->storageUsedBytes($owner),
        );

        if ($expectedSizeBytes !== null && $expectedSizeBytes > $remainingStorageBytes) {
            throw new FileImportLimitException(__('Your current plan has reached the file storage limit.'));
        }

        if ($remainingStorageBytes <= 0) {
            throw new FileImportLimitException(__('Your current plan has reached the file storage limit.'));
        }

        return min($maxUploadBytes, $remainingStorageBytes);
    }

    protected function downloadGoogleDriveFileToTemp(
        string $accessToken,
        string $url,
        array $query,
        int $hardLimitBytes,
        string $failureMessage,
    ): string {
        $tempPath = tempnam(sys_get_temp_dir(), 'stackposts-drive-');

        if (! is_string($tempPath) || $tempPath === '') {
            throw new RuntimeException(__('Could not prepare temporary storage for the Google Drive import.'));
        }

        $limitExceeded = false;

        try {
            try {
                $response = Http::withToken($accessToken)
                    ->timeout(60)
                    ->withOptions([
                        'sink' => $tempPath,
                        'progress' => function (
                            int|float $downloadTotal,
                            int|float $downloadedBytes,
                            int|float $uploadTotal,
                            int|float $uploadedBytes,
                        ) use ($hardLimitBytes, &$limitExceeded): void {
                            if ($downloadTotal > $hardLimitBytes || $downloadedBytes > $hardLimitBytes) {
                                $limitExceeded = true;

                                throw new FileImportLimitException(__('The selected Google Drive file is larger than the allowed upload or storage limit.'));
                            }
                        },
                    ])
                    ->get($url, $query);
            } catch (Throwable $exception) {
                if ($limitExceeded) {
                    throw new FileImportLimitException(
                        __('The selected Google Drive file is larger than the allowed upload or storage limit.'),
                        previous: $exception,
                    );
                }

                throw new RuntimeException($failureMessage, previous: $exception);
            }

            if (! $response->successful()) {
                throw new RuntimeException($failureMessage);
            }

            $responseSize = $this->parseRemoteByteCount($response->header('Content-Length'));
            $downloadedSize = is_file($tempPath) ? filesize($tempPath) : false;

            if ($responseSize !== null && $responseSize > $hardLimitBytes) {
                throw new FileImportLimitException(__('The selected Google Drive file is larger than the allowed upload or storage limit.'));
            }

            if (! is_int($downloadedSize) || $downloadedSize <= 0) {
                throw new RuntimeException(__('The selected Google Drive file was empty.'));
            }

            if ($downloadedSize > $hardLimitBytes) {
                throw new FileImportLimitException(__('The selected Google Drive file is larger than the allowed upload or storage limit.'));
            }

            return $tempPath;
        } catch (Throwable $exception) {
            @unlink($tempPath);

            throw $exception;
        }
    }

    protected function storeDownloadedFile(
        User $owner,
        string $originalName,
        string $mimeType,
        string $tempPath,
        ?AppFile $parent = null,
        ?string $note = null,
    ): AppFile {
        $sizeBytes = is_file($tempPath) ? filesize($tempPath) : false;

        if (! is_int($sizeBytes) || $sizeBytes <= 0) {
            throw new RuntimeException(__('The selected Google Drive file was empty.'));
        }

        if ($sizeBytes > $this->megabytesToBytes($this->maxUploadMb($owner))) {
            throw new FileImportLimitException(__('The selected Google Drive file is larger than the allowed upload size.'));
        }

        if (! $this->canStoreBytes($owner, $sizeBytes)) {
            throw new FileImportLimitException(__('Your current plan has reached the file storage limit.'));
        }

        $disk = $this->disk();
        $this->storageDriverManager->ensureDiskReadyForWrites($disk);
        $teamId = $parent?->team_id ?? $this->currentTeamId($owner);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $safeName = $this->sanitizeName(pathinfo($originalName, PATHINFO_FILENAME), $extension);
        $folderPath = trim(implode('/', array_filter([
            'files',
            'user-'.$owner->id,
            now()->format('Y'),
            now()->format('m'),
        ])), '/');
        $storedPath = $folderPath.'/'.$safeName;
        $stream = fopen($tempPath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException(__('Could not read the downloaded Google Drive file.'));
        }

        try {
            $written = Storage::disk($disk)->put($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        $this->assertWriteSucceeded($disk, $storedPath, $written, __('The Google Drive file could not be written to the configured storage disk.'));

        $imageInfo = @getimagesize($tempPath);
        $isImage = is_array($imageInfo);

        try {
            return AppFile::query()->create([
                'owner_user_id' => $owner->id,
                'team_id' => $teamId,
                'parent_id' => $parent?->id,
                'disk' => $disk,
                'name' => $originalName,
                'path' => $storedPath,
                'mime_type' => $mimeType,
                'extension' => $extension ?: null,
                'category' => $this->detectCategory($mimeType, $extension),
                'size_bytes' => $sizeBytes,
                'is_folder' => false,
                'is_image' => $isImage,
                'width' => $isImage && isset($imageInfo[0]) ? (int) $imageInfo[0] : null,
                'height' => $isImage && isset($imageInfo[1]) ? (int) $imageInfo[1] : null,
                'note' => $note,
            ]);
        } catch (Throwable $exception) {
            $this->storageDriverManager->deleteIfPresent($disk, $storedPath);

            throw $exception;
        }
    }

    protected function parseRemoteByteCount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || ! preg_match('/^\d+$/', trim($value))) {
            return null;
        }

        $value = ltrim(trim($value), '0');

        if ($value === '') {
            return 0;
        }

        $max = (string) PHP_INT_MAX;

        if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
            return PHP_INT_MAX;
        }

        return (int) $value;
    }

    protected function megabytesToBytes(int $megabytes): int
    {
        if ($megabytes <= 0) {
            return 0;
        }

        $bytesPerMegabyte = 1024 * 1024;

        return $megabytes > intdiv(PHP_INT_MAX, $bytesPerMegabyte)
            ? PHP_INT_MAX
            : $megabytes * $bytesPerMegabyte;
    }

    public function deleteTree(AppFile $file): void
    {
        $file->loadMissing('children');

        foreach ($file->children as $child) {
            $this->deleteTree($child);
        }

        if (! $file->is_folder && filled($file->path)) {
            $this->storageDriverManager->deleteIfPresent($file->disk, $file->path);
        }

        $file->delete();
    }

    public function editableImageExtensions(): array
    {
        return AppFile::EDITABLE_IMAGE_EXTENSIONS;
    }

    public function replaceImage(AppFile $file, UploadedFile $image): AppFile
    {
        if (! $file->isEditableImage()) {
            throw new RuntimeException(__('This image format is not supported by the editor.'));
        }

        $this->storageDriverManager->ensureDiskReadyForWrites((string) $file->disk);

        $extension = strtolower((string) ($image->getClientOriginalExtension() ?: $file->extension));
        $mimeType = (string) ($image->getMimeType() ?: $image->getClientMimeType() ?: $file->mime_type);
        $normalizedExisting = $this->normalizeEditableExtension((string) $file->extension);
        $normalizedIncoming = $this->normalizeEditableExtension($extension);

        if (! in_array($normalizedIncoming, $this->editableImageExtensions(), true)) {
            throw new RuntimeException(__('The edited image format is not supported.'));
        }

        if ($normalizedExisting !== $normalizedIncoming) {
            throw new RuntimeException(__('The edited image must keep the original file format.'));
        }

        $contents = file_get_contents($image->getRealPath());

        if ($contents === false) {
            throw new RuntimeException(__('Could not read the edited image payload.'));
        }

        $written = Storage::disk($file->disk)->put($file->path, $contents);
        $this->assertWriteSucceeded((string) $file->disk, (string) $file->path, $written, __('The edited image could not be written to the configured storage disk.'));

        [$width, $height, $isImage] = $this->imageMeta($image);

        if (! $isImage) {
            throw new RuntimeException(__('The edited file is not a valid image.'));
        }

        $file->forceFill([
            'mime_type' => $mimeType,
            'extension' => $normalizedIncoming,
            'category' => 'image',
            'size_bytes' => (int) $image->getSize(),
            'is_image' => true,
            'width' => $width,
            'height' => $height,
        ])->save();

        return $file->refresh();
    }

    public function createEditedCopy(AppFile $file, UploadedFile $image): AppFile
    {
        if (! $file->isEditableImage()) {
            throw new RuntimeException(__('This image format is not supported by the editor.'));
        }

        $this->storageDriverManager->ensureDiskReadyForWrites((string) $file->disk);

        $extension = strtolower((string) ($image->getClientOriginalExtension() ?: $file->extension));
        $mimeType = (string) ($image->getMimeType() ?: $image->getClientMimeType() ?: $file->mime_type);
        $normalizedExisting = $this->normalizeEditableExtension((string) $file->extension);
        $normalizedIncoming = $this->normalizeEditableExtension($extension);

        if (! in_array($normalizedIncoming, $this->editableImageExtensions(), true)) {
            throw new RuntimeException(__('The edited image format is not supported.'));
        }

        $folderPath = trim((string) pathinfo((string) $file->path, PATHINFO_DIRNAME), '/.');
        $baseName = pathinfo($file->name, PATHINFO_FILENAME);
        $copyName = $this->uniqueEditedName($file, $baseName, $normalizedIncoming);
        $storedPath = trim($folderPath.'/'.$this->sanitizeName($copyName, $normalizedIncoming), '/');

        $contents = file_get_contents($image->getRealPath());

        if ($contents === false) {
            throw new RuntimeException(__('Could not read the edited image payload.'));
        }

        $written = Storage::disk($file->disk)->put($storedPath, $contents);
        $this->assertWriteSucceeded((string) $file->disk, $storedPath, $written, __('The edited image copy could not be written to the configured storage disk.'));

        [$width, $height, $isImage] = $this->imageMeta($image);

        if (! $isImage) {
            throw new RuntimeException(__('The edited file is not a valid image.'));
        }

        $owner = $file->owner;

        if ($owner) {
            $this->ensureWithinStorageLimit($owner, (int) ($image->getSize() ?? 0));
        }

        return AppFile::query()->create([
            'owner_user_id' => $file->owner_user_id,
            'team_id' => $file->team_id,
            'parent_id' => $file->parent_id,
            'disk' => $file->disk,
            'name' => $copyName.'.'.$normalizedIncoming,
            'path' => $storedPath,
            'mime_type' => $mimeType,
            'extension' => $normalizedIncoming,
            'category' => 'image',
            'size_bytes' => (int) $image->getSize(),
            'is_folder' => false,
            'is_image' => true,
            'width' => $width,
            'height' => $height,
        ]);
    }

    protected function sanitizeName(string $base, string $extension = ''): string
    {
        $slug = Str::slug($base);
        $slug = $slug !== '' ? $slug : 'file';

        return $slug.'-'.Str::lower(Str::random(6)).($extension !== '' ? '.'.$extension : '');
    }

    protected function uniqueEditedName(AppFile $file, string $baseName, string $extension): string
    {
        $cleanBase = trim((string) preg_replace('/\s+/', ' ', $baseName));
        $cleanBase = $cleanBase !== '' ? $cleanBase : 'image';

        for ($index = 1; $index <= 100; $index++) {
            $candidate = $index === 1 ? $cleanBase.' - Edited' : $cleanBase.' - Edited '.$index;

            $exists = AppFile::query()
                ->where('owner_user_id', $file->owner_user_id)
                ->where('parent_id', $file->parent_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate.'.'.$extension)])
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        return $cleanBase.' - Edited '.now()->format('YmdHis');
    }

    protected function detectCategory(?string $mimeType, ?string $extension): string
    {
        $mimeType = strtolower((string) $mimeType);
        $extension = strtolower((string) $extension);

        if (Str::startsWith($mimeType, 'image/')) {
            return 'image';
        }

        if (Str::startsWith($mimeType, 'video/')) {
            return 'video';
        }

        if (Str::startsWith($mimeType, 'audio/')) {
            return 'audio';
        }

        return match ($extension) {
            'pdf' => 'pdf',
            'csv', 'xls', 'xlsx' => 'spreadsheet',
            'doc', 'docx', 'txt' => 'document',
            'zip' => 'archive',
            default => 'other',
        };
    }

    protected function imageMeta(UploadedFile $file): array
    {
        $info = @getimagesize($file->getRealPath());

        if (! is_array($info)) {
            return [null, null, false];
        }

        return [
            isset($info[0]) ? (int) $info[0] : null,
            isset($info[1]) ? (int) $info[1] : null,
            true,
        ];
    }

    protected function normalizeEditableExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    protected function assertStoredPath(string $disk, mixed $storedPath, string $message): string
    {
        $path = is_string($storedPath) ? trim($storedPath) : '';

        if ($path === '') {
            throw new RuntimeException($message);
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException($message);
        }

        return $path;
    }

    protected function assertWriteSucceeded(string $disk, string $path, mixed $result, string $message): void
    {
        if ($result === false || trim($path) === '') {
            throw new RuntimeException($message);
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException($message);
        }
    }

    protected function normalizeImportedFileName(string $name, string $mimeType): string
    {
        $name = trim($name);

        if ($name === '') {
            $name = 'google-drive-file';
        }

        if (pathinfo($name, PATHINFO_EXTENSION) !== '') {
            return $name;
        }

        $extension = match (strtolower($mimeType)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/zip' => 'zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            default => '',
        };

        return $extension !== '' ? $name.'.'.$extension : $name;
    }
}
