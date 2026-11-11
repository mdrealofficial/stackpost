<?php

namespace Modules\AppFiles\Support;

use Illuminate\Support\Facades\Http;
use Modules\AdminSettings\Support\OptionStore;
use RuntimeException;

class OnlineMediaSearchService
{
    public function __construct(
        protected OptionStore $options,
    ) {}

    public function providers(string $type = 'all'): array
    {
        $providers = [];

        if ($this->providerEnabled('unsplash') && $this->providerKey('unsplash') !== '' && $type !== 'video') {
            $providers['unsplash'] = __('Unsplash');
        }

        if ($this->providerEnabled('pexels') && $this->providerKey('pexels') !== '') {
            if ($type !== 'video') {
                $providers['pexels_photo'] = __('Pexels Photo');
            }

            if ($type !== 'image') {
                $providers['pexels_video'] = __('Pexels Video');
            }
        }

        if ($this->providerEnabled('pixabay') && $this->providerKey('pixabay') !== '') {
            if ($type !== 'video') {
                $providers['pixabay_photo'] = __('Pixabay Photo');
            }

            if ($type !== 'image') {
                $providers['pixabay_video'] = __('Pixabay Video');
            }
        }

        return $providers;
    }

    public function search(string $query, string $provider, string $type = 'all', int $page = 1, string $quality = 'standard'): array
    {
        $page = max(1, min(200, $page));
        $query = trim($query);

        if ($query === '') {
            throw new RuntimeException(__('Please enter your keyword.'));
        }

        if (! array_key_exists($provider, $this->providers($type))) {
            throw new RuntimeException(__('No provider enabled.'));
        }

        return match ($provider) {
            'unsplash' => $this->searchUnsplash($query, $page),
            'pexels_photo' => $this->searchPexelsPhoto($query, $page),
            'pexels_video' => $this->searchPexelsVideo($query, $page, $quality),
            'pixabay_photo' => $this->searchPixabayPhoto($query, $page),
            'pixabay_video' => $this->searchPixabayVideo($query, $page, $quality),
            default => [],
        };
    }

    protected function searchUnsplash(string $query, int $page = 1): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Client-ID '.$this->providerKey('unsplash'),
        ])->timeout(15)->get('https://api.unsplash.com/search/photos', [
            'query' => $query,
            'per_page' => 24,
            'page' => $page,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->status() === 429
                ? __('This media source is busy. Please try again later.')
                : __('This media source could not complete the search. Try another source or retry.'));
        }

        return collect((array) $response->json('results', []))
            ->map(fn ($item) => [
                'id' => (string) data_get($item, 'id'),
                'thumbnail' => (string) data_get($item, 'urls.small', data_get($item, 'urls.thumb', '')),
                'full' => (string) data_get($item, 'urls.full', ''),
                'source' => 'unsplash',
                'provider' => 'unsplash',
                'mediaType' => 'image',
                'link' => (string) data_get($item, 'links.html', ''),
                'author' => (string) data_get($item, 'user.name', ''),
            ])
            ->filter(fn ($item) => $item['thumbnail'] !== '' && $item['full'] !== '')
            ->values()
            ->all();
    }

    protected function searchPexelsPhoto(string $query, int $page = 1): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->providerKey('pexels'),
        ])->timeout(15)->get('https://api.pexels.com/v1/search', [
            'query' => $query,
            'per_page' => 24,
            'page' => $page,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->status() === 429
                ? __('This media source is busy. Please try again later.')
                : __('This media source could not complete the search. Try another source or retry.'));
        }

        return collect((array) $response->json('photos', []))
            ->map(fn ($item) => [
                'id' => (string) data_get($item, 'id'),
                'thumbnail' => (string) data_get($item, 'src.medium', ''),
                'full' => (string) data_get($item, 'src.original', ''),
                'source' => 'pexels',
                'provider' => 'pexels_photo',
                'mediaType' => 'image',
                'link' => (string) data_get($item, 'url', ''),
                'author' => (string) data_get($item, 'photographer', ''),
            ])
            ->filter(fn ($item) => $item['thumbnail'] !== '' && $item['full'] !== '')
            ->values()
            ->all();
    }

    protected function searchPexelsVideo(string $query, int $page = 1, string $quality = 'standard'): array
    {
        $response = Http::withHeaders([
            'Authorization' => $this->providerKey('pexels'),
        ])->timeout(15)->get('https://api.pexels.com/videos/search', [
            'query' => $query,
            'per_page' => 24,
            'page' => $page,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->status() === 429
                ? __('This media source is busy. Please try again later.')
                : __('This media source could not complete the search. Try another source or retry.'));
        }

        return collect((array) $response->json('videos', []))
            ->map(function ($item) use ($quality) {
                $best = $this->videoVariant((array) data_get($item, 'video_files', []), $quality, 'link');

                return [
                    'id' => (string) data_get($item, 'id'),
                    'thumbnail' => (string) data_get($item, 'image', ''),
                    'full' => (string) data_get($best, 'link', ''),
                    'source' => 'pexels',
                    'provider' => 'pexels_video',
                    'mediaType' => 'video',
                    'width' => (int) data_get($best, 'width', 0),
                    'height' => (int) data_get($best, 'height', 0),
                    'duration' => (int) data_get($item, 'duration', 0),
                    'link' => (string) data_get($item, 'url', ''),
                    'author' => (string) data_get($item, 'user.name', ''),
                ];
            })
            ->filter(fn ($item) => $item['thumbnail'] !== '' && $item['full'] !== '')
            ->values()
            ->all();
    }

    protected function searchPixabayPhoto(string $query, int $page = 1): array
    {
        $response = Http::timeout(15)->get('https://pixabay.com/api/', [
            'key' => $this->providerKey('pixabay'),
            'q' => $query,
            'image_type' => 'photo',
            'per_page' => 24,
            'page' => $page,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->status() === 429
                ? __('This media source is busy. Please try again later.')
                : __('This media source could not complete the search. Try another source or retry.'));
        }

        return collect((array) $response->json('hits', []))
            ->map(fn ($item) => [
                'id' => (string) data_get($item, 'id'),
                'thumbnail' => (string) data_get($item, 'webformatURL', data_get($item, 'previewURL', '')),
                'full' => (string) data_get($item, 'largeImageURL', ''),
                'source' => 'pixabay',
                'provider' => 'pixabay_photo',
                'mediaType' => 'image',
                'link' => (string) data_get($item, 'pageURL', ''),
                'author' => (string) data_get($item, 'user', ''),
            ])
            ->filter(fn ($item) => $item['thumbnail'] !== '' && $item['full'] !== '')
            ->values()
            ->all();
    }

    protected function searchPixabayVideo(string $query, int $page = 1, string $quality = 'standard'): array
    {
        $response = Http::timeout(15)->get('https://pixabay.com/api/videos/', [
            'key' => $this->providerKey('pixabay'),
            'q' => $query,
            'per_page' => 24,
            'page' => $page,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($response->status() === 429
                ? __('This media source is busy. Please try again later.')
                : __('This media source could not complete the search. Try another source or retry.'));
        }

        return collect((array) $response->json('hits', []))
            ->map(function ($item) use ($quality) {
                $best = $this->videoVariant((array) data_get($item, 'videos', []), $quality, 'url');

                return [
                    'id' => (string) data_get($item, 'id'),
                    'thumbnail' => (string) data_get($best, 'thumbnail', ''),
                    'full' => (string) data_get($best, 'url', ''),
                    'source' => 'pixabay',
                    'provider' => 'pixabay_video',
                    'mediaType' => 'video',
                    'width' => (int) data_get($best, 'width', 0),
                    'height' => (int) data_get($best, 'height', 0),
                    'duration' => (int) data_get($item, 'duration', 0),
                    'link' => (string) data_get($item, 'pageURL', ''),
                    'author' => (string) data_get($item, 'user', ''),
                ];
            })
            ->filter(fn ($item) => $item['thumbnail'] !== '' && $item['full'] !== '')
            ->values()
            ->all();
    }

    protected function videoVariant(array $variants, string $quality, string $urlKey): array
    {
        $available = collect($variants)->filter(fn ($file) => filled(data_get($file, $urlKey)))
            ->filter(fn ($file) => !isset($file['file_type']) || $file['file_type'] === 'video/mp4')
            ->sortByDesc(fn ($file) => (int) data_get($file, 'width', 0));

        if ($quality === 'high') {
            return (array) ($available->first() ?? []);
        }

        return (array) ($available->first(fn ($file) => max((int) data_get($file, 'width', 0), (int) data_get($file, 'height', 0)) <= 1920)
            ?? $available->last() ?? []);
    }

    protected function providerEnabled(string $provider): bool
    {
        return match ($provider) {
            'unsplash' => (string) $this->options->get('file_unsplash_status', '0') === '1',
            'pexels' => (string) $this->options->get('file_pexels_status', '0') === '1',
            'pixabay' => (string) $this->options->get('file_pixabay_status', '0') === '1',
            default => false,
        };
    }

    protected function providerKey(string $provider): string
    {
        return trim(match ($provider) {
            'unsplash' => (string) $this->options->get('file_unsplash_access_key', ''),
            'pexels' => (string) $this->options->get('file_pexels_api_key', ''),
            'pixabay' => (string) $this->options->get('file_pixabay_api_key', ''),
            default => '',
        });
    }
}
