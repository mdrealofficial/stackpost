<?php

namespace Modules\AppChannelXProfiles\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelXProfiles\Services\Publishing\XPostPublisher;
use Modules\AppChannelXProfiles\Services\X\XApiService;
use Modules\AppChannelXProfiles\Support\XProfilesChannelDriver;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelXProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannelxprofiles');
        $this->app->singleton(XApiService::class);
        $this->app->singleton(XPostPublisher::class);
        $this->app->alias(XApiService::class, 'appchannel.x');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannelxprofiles');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $providerKey = config('modules.appchannelxprofiles.provider_key', 'x');

        $this->app->make(PublishingChannelRegistry::class)
            ->register($providerKey, $this->app->make(XPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register($providerKey, [
                'label' => __('X'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Tweet')],
                ],
            ]);
        $this->app->make(PublishingPreviewRegistry::class)
            ->register($providerKey, 'appchannelxprofiles::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register($providerKey, fn (array $options): array => []);
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register($providerKey, function (array $context): ?string {
                $postType = (string) data_get($context, 'options.x_post_type', 'auto');
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));

                return match ($postType) {
                    'text' => $media['has_media']
                        ? __('X text posts cannot include attached media.')
                        : null,
                    'video' => ! $media['has_video']
                        ? __('X video posts require one attached video.')
                        : ($media['has_image']
                            ? __('X video posts cannot mix images and videos.')
                            : ($media['count'] > 1
                                ? __('X supports only one video per post.')
                                : null)),
                    'media' => $media['has_video']
                        ? __('X media posts currently support images only. Use the video post type for video uploads.')
                        : ($media['count'] > 4
                            ? __('X supports up to four images per post.')
                            : null),
                    default => $media['has_video'] && $media['has_image']
                        ? __('X auto mode cannot mix images and videos.')
                        : ($media['has_video'] && $media['count'] > 1
                            ? __('X auto mode supports only one video per post.')
                            : ($media['has_image'] && $media['count'] > 4
                                ? __('X auto mode supports up to four images per post.')
                                : null)),
                };
            });

        register_channel_module('x_profile', [
            'provider' => [
                'key' => $providerKey,
                'order' => 17,
                'label' => 'X',
                'icon' => 'fa-brands fa-x-twitter',
                'color' => '#111111',
                'description' => 'X API OAuth settings for profile connections and tweet publishing.',
                'required_fields' => ['client_id', 'client_secret'],
                'account_types' => ['oauth'],
                'settings_view' => 'appchannelxprofiles::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '1'],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'default' => ''],
                    ['key' => 'client_secret', 'label' => 'Client Secret', 'type' => 'secret', 'default' => ''],
                    ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'text', 'default' => url('integrations/x/profile'), 'readonly' => true],
                    ['key' => 'permissions', 'label' => 'Permissions', 'type' => 'textarea', 'default' => 'tweet.read tweet.write tweet.moderate.write users.read follows.read follows.write offline.access space.read mute.read mute.write like.read like.write list.read list.write block.read block.write bookmark.read bookmark.write media.write users.email'],
                ],
                'module' => [
                    'name' => 'AppChannelXProfiles',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 17,
                'provider_key' => $providerKey,
                'label' => 'Profile',
                'title' => 'X Profile',
                'description' => 'Connect X profiles from the shared OAuth provider config.',
                'icon' => 'fa-brands fa-x-twitter',
                'color' => '#111111',
                'categories' => ['Profile'],
                'account_types' => ['oauth'],
                'supports_publishing' => true,
                'connect_label' => 'Profile',
                'driver' => XProfilesChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelXProfiles',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
