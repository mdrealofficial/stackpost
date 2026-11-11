<?php

namespace Modules\AppChannelInstagramUnofficial\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelInstagramUnofficial\Services\InstagramUnofficialService;
use Modules\AppChannelInstagramUnofficial\Services\Publishing\InstagramUnofficialPostPublisher;
use Modules\AppChannelInstagramUnofficial\Support\InstagramUnofficialChannelDriver;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelInstagramUnofficialServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannelinstagramunofficial');
        $this->app->singleton(InstagramUnofficialService::class);
        $this->app->singleton(InstagramUnofficialPostPublisher::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannelinstagramunofficial');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $providerKey = config('modules.appchannelinstagramunofficial.provider_key', 'instagram_unofficial');

        $this->app->make(PublishingChannelRegistry::class)
            ->register($providerKey, $this->app->make(InstagramUnofficialPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register($providerKey, [
                'label' => __('Instagram'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                    ['key' => 'reels', 'label' => __('Reels')],
                    ['key' => 'stories', 'label' => __('Stories')],
                ],
            ]);
        $this->app->make(PublishingOptionsRegistry::class)
            ->register($providerKey, 'appchannelinstagramprofiles::publishing.options');
        $this->app->make(PublishingPreviewRegistry::class)
            ->register($providerKey, 'appchannelinstagramprofiles::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register($providerKey, fn (array $options): array => $options);
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register($providerKey, function (array $context): ?string {
                $postTo = (string) data_get($context, 'options.post_to', 'feed');
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));

                return match ($postTo) {
                    'reels' => ! $media['has_media']
                        ? __('Instagram Reels requires one video.')
                        : ($media['count'] !== 1 || ! $media['has_video'] || $media['has_image']
                            ? __('Instagram Reels requires exactly one video file.')
                            : null),
                    'stories' => ! $media['has_media']
                        ? __('Instagram Unofficial stories require one image.')
                        : ($media['count'] !== 1 || ! $media['has_image'] || $media['has_video']
                            ? __('Instagram Unofficial stories currently support exactly one image.')
                            : null),
                    default => ! $media['has_media']
                        ? __('Instagram Unofficial publishing requires at least one media item.')
                        : ($media['count'] > 1 && $media['has_video']
                            ? __('Instagram Unofficial carousel currently supports images only.')
                            : null),
                };
            });

        register_channel_module('instagram_unofficial_profile', [
            'provider' => [
                'key' => $providerKey,
                'order' => 21,
                'label' => 'Instagram Unofficial',
                'icon' => 'fa-brands fa-instagram',
                'color' => '#C13584',
                'description' => 'Unofficial Instagram direct-login flow using username and password. Use with caution.',
                'required_fields' => [],
                'account_types' => ['manual'],
                'settings_view' => 'appchannelinstagramunofficial::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '0'],
                ],
                'module' => [
                    'name' => 'AppChannelInstagramUnofficial',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 21,
                'provider_key' => $providerKey,
                'label' => 'Profile',
                'title' => 'Instagram Unofficial Profile',
                'description' => 'Connect Instagram personal profiles using the unofficial login flow.',
                'icon' => 'fa-brands fa-instagram',
                'color' => '#C13584',
                'categories' => ['Profile'],
                'account_types' => ['manual'],
                'supports_publishing' => true,
                'connect_label' => 'Profile',
                'driver' => InstagramUnofficialChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelInstagramUnofficial',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
