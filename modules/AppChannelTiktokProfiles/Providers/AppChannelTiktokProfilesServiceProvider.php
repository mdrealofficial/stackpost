<?php

namespace Modules\AppChannelTiktokProfiles\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelTiktokProfiles\Services\Publishing\TiktokPostPublisher;
use Modules\AppChannelTiktokProfiles\Services\Tiktok\TiktokApiService;
use Modules\AppChannelTiktokProfiles\Support\TiktokProfilesChannelDriver;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelTiktokProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchanneltiktokprofiles');
        $this->app->singleton(TiktokApiService::class);
        $this->app->singleton(TiktokPostPublisher::class);
        $this->app->alias(TiktokApiService::class, 'appchannel.tiktok');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchanneltiktokprofiles');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $providerKey = config('modules.appchanneltiktokprofiles.provider_key', 'tiktok');

        $this->app->make(PublishingChannelRegistry::class)
            ->register($providerKey, $this->app->make(TiktokPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register($providerKey, [
                'label' => __('TikTok'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                ],
            ]);
        $this->app->make(PublishingOptionsRegistry::class)
            ->register($providerKey, 'appchanneltiktokprofiles::publishing.options');
        $this->app->make(PublishingPreviewRegistry::class)
            ->register($providerKey, 'appchanneltiktokprofiles::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register($providerKey, fn (array $options): array => [
                'options' => $options,
                'tt_privacy' => (string) ($options['tt_privacy'] ?? ''),
                'tt_allow_comment' => ! empty($options['tt_allow_comment']),
                'tt_allow_duet' => ! empty($options['tt_allow_duet']),
                'tt_allow_stitch' => ! empty($options['tt_allow_stitch']),
                'tt_commercial_content' => ! empty($options['tt_commercial_content']),
                'tt_your_brand' => ! empty($options['tt_your_brand']),
                'tt_branded_content' => ! empty($options['tt_branded_content']),
                'tt_consent' => ! empty($options['tt_consent']),
                'tt_can_post' => array_key_exists('tt_can_post', $options) ? (bool) $options['tt_can_post'] : false,
                'tt_creator_ready' => array_key_exists('tt_creator_ready', $options) ? (bool) $options['tt_creator_ready'] : false,
            ]);
        $this->app->make(PublishingContentValidationRegistry::class)
            ->register($providerKey, function (array $context): ?array {
                $caption = trim((string) ($context['caption'] ?? ''));
                $options = (array) ($context['options'] ?? []);
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));
                $commercialEnabled = ! empty($options['tt_commercial_content']);
                $yourBrand = $commercialEnabled && ! empty($options['tt_your_brand']);
                $brandedContent = $commercialEnabled && ! empty($options['tt_branded_content']);

                if ($caption === '') {
                    return ['target' => 'caption', 'message' => __('TikTok requires a caption.')];
                }

                if (trim((string) ($options['tt_privacy'] ?? '')) === '') {
                    return ['target' => 'tt_privacy', 'message' => __('TikTok requires a privacy setting.')];
                }

                if ($commercialEnabled && ! $yourBrand && ! $brandedContent) {
                    return ['target' => 'tt_your_brand', 'message' => __('Choose at least one commercial disclosure option for TikTok.')];
                }

                if (empty($options['tt_consent'])) {
                    return ['target' => 'tt_consent', 'message' => __('You must confirm TikTok music and disclosure consent before posting.')];
                }

                if (! $media['has_video'] && (! empty($options['tt_allow_duet']) || ! empty($options['tt_allow_stitch']))) {
                    return ['target' => 'tt_allow_duet', 'message' => __('Duet and Stitch are only available for TikTok video posts.')];
                }

                return null;
            });
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register($providerKey, function (array $context): ?string {
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));
                $imageCount = $media['items']->filter(fn (array $item): bool => (bool) data_get($item, 'isImage', false))->count();
                $videoCount = $media['items']->filter(function (array $item): bool {
                    $mimeType = strtolower((string) data_get($item, 'mimeType', ''));
                    $category = strtolower((string) data_get($item, 'category', ''));

                    return str_starts_with($mimeType, 'video/') || $category === 'video';
                })->count();

                return ! $media['has_media']
                    ? __('TikTok requires one video or at least one image.')
                    : (($media['has_video'] && $media['has_image'])
                        ? __('TikTok does not support mixing videos and images in the same post.')
                        : (($videoCount > 1)
                            ? __('TikTok supports exactly one video per post.')
                            : (($videoCount === 1 || ($imageCount >= 1 && $imageCount <= 35))
                                ? null
                                : __('TikTok supports up to 35 images per photo post.'))));
            });

        register_channel_module('tiktok_profile', [
            'provider' => [
                'key' => $providerKey,
                'order' => 25,
                'label' => 'TikTok',
                'icon' => 'fa-brands fa-tiktok',
                'color' => '#111111',
                'description' => 'TikTok API settings for profile connections and future publishing support.',
                'required_fields' => ['client_key', 'client_secret'],
                'account_types' => ['oauth'],
                'settings_view' => 'appchanneltiktokprofiles::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '1'],
                    ['key' => 'client_key', 'label' => 'Client Key', 'type' => 'text', 'default' => ''],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'secret', 'default' => ''],
                    ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'text', 'default' => url('integrations/tiktok/profile'), 'readonly' => true],
                    ['key' => 'permissions', 'label' => 'Permissions', 'type' => 'textarea', 'default' => 'user.info.basic,user.info.profile,user.info.stats,video.list,video.publish,video.upload'],
                ],
                'module' => [
                    'name' => 'AppChannelTiktokProfiles',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 25,
                'provider_key' => $providerKey,
                'label' => 'Profile',
                'title' => 'TikTok Profile',
                'description' => 'Connect TikTok profiles from the shared TikTok OAuth integration config.',
                'icon' => 'fa-brands fa-tiktok',
                'color' => '#111111',
                'categories' => ['Profile'],
                'account_types' => ['oauth'],
                'supports_publishing' => true,
                'connect_label' => 'Profile',
                'driver' => TiktokProfilesChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelTiktokProfiles',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
