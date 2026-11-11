<?php

namespace App\Support\Storage;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;

class StorageDriverManager
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function configureDisks(): void
    {
        Config::set('filesystems.disks.amazon', $this->diskConfig('amazon', [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ]));

        Config::set('filesystems.disks.wasabi', $this->diskConfig('wasabi', [
            'key' => env('WASABI_ACCESS_KEY_ID'),
            'secret' => env('WASABI_SECRET_ACCESS_KEY'),
            'region' => env('WASABI_DEFAULT_REGION', 'us-east-1'),
            'bucket' => env('WASABI_BUCKET'),
            'url' => env('WASABI_URL'),
            'endpoint' => env('WASABI_ENDPOINT'),
            'use_path_style_endpoint' => env('WASABI_USE_PATH_STYLE_ENDPOINT', false),
        ]));

        Config::set('filesystems.disks.contabo', $this->diskConfig('contabo', [
            'key' => env('CONTABO_ACCESS_KEY_ID'),
            'secret' => env('CONTABO_SECRET_ACCESS_KEY'),
            'region' => env('CONTABO_DEFAULT_REGION', 'eu-central-1'),
            'bucket' => env('CONTABO_BUCKET'),
            'url' => env('CONTABO_URL'),
            'endpoint' => env('CONTABO_ENDPOINT'),
            'use_path_style_endpoint' => env('CONTABO_USE_PATH_STYLE_ENDPOINT', true),
        ]));
    }

    public function activeDisk(): string
    {
        $disk = trim((string) $this->options->get('appfiles_disk', config('modules.appfiles.disk', 'public')));

        return array_key_exists($disk, $this->diskOptions()) ? $disk : 'public';
    }

    public function diskOptions(): array
    {
        return [
            'public' => __('Local'),
            'amazon' => __('Amazon S3'),
            'wasabi' => __('Wasabi'),
            'contabo' => __('Contabo'),
        ];
    }

    public function publicUrl(string $disk, ?string $path): ?string
    {
        $disk = trim($disk) ?: 'public';
        $path = $this->normalizePath((string) $path, $disk);

        if ($path === '') {
            return null;
        }

        if (! array_key_exists($disk, $this->diskOptions())) {
            return null;
        }

        if ($disk === 'public') {
            return $this->applicationBaseUrl().'/media/public/'.$this->encodePath($path).'/view';
        }

        $configuredUrl = trim((string) config("filesystems.disks.{$disk}.url", ''));

        if ($configuredUrl !== '') {
            try {
                return (string) Storage::disk($disk)->url($path);
            } catch (\Throwable) {
            }
        }

        return $this->applicationBaseUrl().'/media/'.$disk.'/'.$this->encodePath($path).'/view';
    }

    public function diskHealth(?string $disk = null): array
    {
        $disk = trim((string) ($disk ?: $this->activeDisk()));

        if ($disk === '' || $disk === 'local') {
            $disk = 'public';
        }

        $config = (array) config("filesystems.disks.{$disk}", []);
        $provider = $this->diskOptions()[$disk] ?? Str::headline($disk);
        $missing = collect($this->requiredFieldsForDisk($disk))
            ->filter(fn (string $field): bool => trim((string) ($config[$field] ?? '')) === '')
            ->values()
            ->all();

        if ($missing !== []) {
            return [
                'disk' => $disk,
                'provider' => $provider,
                'valid' => false,
                'title' => __('Storage configuration is incomplete'),
                'message' => __(':provider is selected for file storage, but these fields are missing: :fields. Update the storage settings before uploading files.', [
                    'provider' => $provider,
                    'fields' => implode(', ', $missing),
                ]),
                'details' => $missing,
            ];
        }

        $cacheKey = 'storage-disk-health:'.$disk.':'.md5(json_encode([
            'key' => (string) ($config['key'] ?? ''),
            'region' => (string) ($config['region'] ?? ''),
            'bucket' => (string) ($config['bucket'] ?? ''),
            'endpoint' => (string) ($config['endpoint'] ?? ''),
        ]));

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($disk, $provider) {
            try {
                $probePath = '.health/'.Str::uuid()->toString().'.txt';

                if (Storage::disk($disk)->put($probePath, 'ok') === false) {
                    throw new RuntimeException(__('Could not write to the configured storage disk.'));
                }

                if (! Storage::disk($disk)->exists($probePath)) {
                    throw new RuntimeException(__('The storage write check completed, but the file could not be found afterward.'));
                }

                Storage::disk($disk)->delete($probePath);

                return [
                    'disk' => $disk,
                    'provider' => $provider,
                    'valid' => true,
                    'title' => null,
                    'message' => null,
                    'details' => [],
                ];
            } catch (\Throwable $exception) {
                return [
                    'disk' => $disk,
                    'provider' => $provider,
                    'valid' => false,
                    'title' => __('Storage connection failed'),
                    'message' => __('Could not connect to :provider with the current storage settings. Check bucket, region, endpoint, and access keys before uploading files.', [
                        'provider' => $provider,
                    ]),
                    'details' => [$exception->getMessage()],
                ];
            }
        });
    }

    public function ensureDiskReadyForWrites(?string $disk = null): void
    {
        $health = $this->diskHealth($disk);

        if ($health['valid'] ?? false) {
            return;
        }

        throw new RuntimeException((string) ($health['message'] ?? __('The configured storage disk is not ready for file uploads.')));
    }

    public function resolveLocalPath(string $disk, string $path, ?string $suggestedName = null): array
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw new \RuntimeException(__('The requested file could not be found in storage.'));
        }

        if (in_array($disk, ['local', 'public'], true)) {
            return [
                'path' => Storage::disk($disk)->path($path),
                'temporary' => false,
            ];
        }

        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException(__('Could not read the stored file stream.'));
        }

        $extension = strtolower((string) pathinfo((string) $suggestedName, PATHINFO_EXTENSION));
        $filename = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
        $directory = storage_path('app/temp-storage/'.now()->format('Y/m/d'));
        File::ensureDirectoryExists($directory);
        $localPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $target = fopen($localPath, 'wb');

        if (! is_resource($target)) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new \RuntimeException(__('Could not create a temporary local file.'));
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        return [
            'path' => $localPath,
            'temporary' => true,
        ];
    }

    public function deleteIfPresent(?string $disk, ?string $path): void
    {
        $disk = trim((string) $disk);
        $path = trim((string) $path);

        if ($disk === '' || $path === '') {
            return;
        }

        try {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable) {
        }
    }

    protected function diskConfig(string $provider, array $defaults): array
    {
        return [
            'driver' => 's3',
            'key' => (string) $this->options->get("appfiles_{$provider}_access_key_id", $defaults['key'] ?? ''),
            'secret' => (string) $this->options->get("appfiles_{$provider}_secret_access_key", $defaults['secret'] ?? ''),
            'region' => (string) $this->options->get("appfiles_{$provider}_default_region", $defaults['region'] ?? ''),
            'bucket' => (string) $this->options->get("appfiles_{$provider}_bucket", $defaults['bucket'] ?? ''),
            'url' => (string) $this->options->get("appfiles_{$provider}_url", $defaults['url'] ?? ''),
            'endpoint' => (string) $this->options->get("appfiles_{$provider}_endpoint", $defaults['endpoint'] ?? ''),
            'use_path_style_endpoint' => $this->booleanOption(
                "appfiles_{$provider}_use_path_style_endpoint",
                (bool) ($defaults['use_path_style_endpoint'] ?? false)
            ),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ];
    }

    protected function booleanOption(string $key, bool $default = false): bool
    {
        return filter_var($this->options->get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL);
    }

    protected function normalizePath(string $path, string $disk = 'public'): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        $parsedPath = parse_url($path, PHP_URL_PATH) ?: $path;
        $basePath = trim((string) request()->getBaseUrl(), '/');

        $parsedPath = ltrim($parsedPath, '/');

        if (str_ends_with($parsedPath, '/view')) {
            $parsedPath = substr($parsedPath, 0, -5);
        }

        if ($basePath !== '' && str_starts_with($parsedPath, $basePath.'/')) {
            $parsedPath = substr($parsedPath, strlen($basePath) + 1);
        }

        foreach ([
            'media/'.$disk.'/',
            'media/public/',
            'media/amazon/',
            'media/wasabi/',
            'media/contabo/',
            'storage/app/public/',
            'public/storage/',
            'storage/',
            'public/',
        ] as $prefix) {
            if (str_starts_with($parsedPath, $prefix)) {
                return substr($parsedPath, strlen($prefix));
            }
        }

        return $parsedPath;
    }

    protected function encodePath(string $path): string
    {
        return collect(explode('/', ltrim($path, '/')))
            ->map(static fn (string $segment): string => rawurlencode($segment))
            ->implode('/');
    }

    protected function applicationBaseUrl(): string
    {
        try {
            if (app()->bound('request')) {
                $request = request();
                $host = trim((string) $request->getHost());

                if ($host !== '') {
                    return rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');
                }
            }
        } catch (\Throwable) {
        }

        return rtrim((string) config('app.url'), '/');
    }

    protected function requiredFieldsForDisk(string $disk): array
    {
        return match ($disk) {
            'public' => [],
            'amazon' => ['key', 'secret', 'region', 'bucket'],
            'wasabi', 'contabo' => ['key', 'secret', 'region', 'bucket', 'endpoint'],
            default => ['key', 'secret', 'region', 'bucket'],
        };
    }
}
