<?php

namespace Modules\AppChannelXProfiles\Services\Publishing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppChannelXProfiles\Services\X\XApiException;
use Modules\AppChannelXProfiles\Services\X\XApiService;
use Modules\AppFiles\Models\AppFile;
use Modules\AppPublishing\Contracts\PostPublisher;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Support\PublishingMediaUrlResolver;

class XPostPublisher implements PostPublisher
{
    public function __construct(
        protected XApiService $x,
        protected PublishingMediaUrlResolver $mediaUrls,
    ) {}

    public function publish(PublishingPost $post, SocialAccount $account): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $caption = trim((string) ($postData['caption'] ?? ''));
        $mediaFiles = $this->resolveMediaFiles((array) ($postData['medias'] ?? []));

        if ($caption === '' && $mediaFiles->isEmpty()) {
            return $this->fail('X requires text or at least one media attachment.');
        }

        if ($mediaFiles->isNotEmpty() && ! $this->supportsMediaUploads($account)) {
            return $this->fail('This X profile is missing the media upload permission. Reconnect the account after enabling the X `media.write` scope.');
        }

        [$accessToken, $refreshToken] = $this->refreshAccessTokenIfAvailable($account);

        try {
            $mediaIds = $mediaFiles->map(fn (AppFile $file): string => $this->x->uploadMedia($accessToken, $file))->all();
            $response = $this->x->createTweet($accessToken, $caption, $mediaIds);
        } catch (XApiException $exception) {
            return $this->fail($exception->getMessage());
        }

        $tweetId = trim((string) data_get($response, 'data.id', ''));
        $username = trim((string) $account->username);

        return [
            'state' => 'published',
            'error' => null,
            'url' => $tweetId !== '' && $username !== ''
                ? 'https://x.com/'.$username.'/status/'.$tweetId
                : ($tweetId !== '' ? 'https://x.com/i/web/status/'.$tweetId : $account->profile_url),
            'remote_post_id' => $tweetId !== '' ? $tweetId : null,
            'response' => [
                ...$response,
                'refresh_token' => $refreshToken,
            ],
        ];
    }

    protected function refreshAccessTokenIfAvailable(SocialAccount $account): array
    {
        $accessToken = trim((string) $account->access_token);
        $refreshToken = trim((string) ($account->refresh_token ?: data_get($account->auth_data, 'refresh_token', '')));

        if ($refreshToken === '') {
            return [$accessToken, $refreshToken];
        }

        $token = $this->x->refreshAccessToken($refreshToken);
        $accessToken = (string) $token['access_token'];
        $refreshToken = (string) ($token['refresh_token'] ?? $refreshToken);

        $account->forceFill([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'scopes' => (string) ($token['scope'] ?: $account->scopes),
            'auth_data' => [
                ...(is_array($account->auth_data) ? $account->auth_data : []),
                'refresh_token' => $refreshToken,
                'refresh_payload' => $token['payload'] ?? [],
            ],
        ])->save();

        return [$accessToken, $refreshToken];
    }

    protected function resolveMediaFiles(array $items): Collection
    {
        $files = $this->mediaUrls->resolveFiles($items)->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($files): ?AppFile {
                $fileId = (int) Arr::get($item, 'id');
                /** @var AppFile|null $file */
                $file = $fileId > 0 ? $files->get($fileId) : null;

                if (! $file || ! filled($file->path) || ! Storage::disk($file->disk)->exists($file->path)) {
                    return null;
                }

                return $file;
            })
            ->filter()
            ->values();
    }

    protected function supportsMediaUploads(SocialAccount $account): bool
    {
        $scopes = collect(preg_split('/[\s,]+/', trim((string) $account->scopes)) ?: [])
            ->map(fn (mixed $scope): string => trim((string) $scope))
            ->filter();

        return $scopes->contains('media.write');
    }

    protected function fail(string $message): array
    {
        return [
            'state' => 'failed',
            'error' => $message,
            'url' => null,
            'remote_post_id' => null,
            'response' => null,
        ];
    }
}
