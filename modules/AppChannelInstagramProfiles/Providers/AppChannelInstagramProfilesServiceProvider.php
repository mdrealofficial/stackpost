<?php

namespace Modules\AppChannelInstagramProfiles\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelInstagramProfiles\Services\Instagram\InstagramApiService;
use Modules\AppChannelInstagramProfiles\Services\Publishing\InstagramPostPublisher;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelInstagramProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannelinstagramprofiles');
        $this->app->singleton(InstagramApiService::class);
        $this->app->singleton(InstagramPostPublisher::class);
        $this->app->alias(InstagramApiService::class, 'appchannel.instagram');

        if (! class_exists('Instagram')) {
            class_alias(\Modules\AppChannelInstagramProfiles\Facades\Instagram::class, 'Instagram');
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannelinstagramprofiles');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->app->make(PublishingChannelRegistry::class)
            ->register('instagram', $this->app->make(InstagramPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register('instagram', [
                'label' => __('Instagram'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                    ['key' => 'reels', 'label' => __('Reels')],
                    ['key' => 'stories', 'label' => __('Stories')],
                ],
            ]);
        $this->app->make(PublishingOptionsRegistry::class)
            ->register('instagram', 'appchannelinstagramprofiles::publishing.options');
        $this->app->make(PublishingPreviewRegistry::class)
            ->register('instagram', 'appchannelinstagramprofiles::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register('instagram', fn (array $options): array => $options);
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register('instagram', function (array $context): ?string {
                $postTo = (string) data_get($context, 'options.post_to', 'feed');
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));

                return match ($postTo) {
                    'reels' => ! $media['has_media']
                        ? __('Instagram Reels requires one video.')
                        : ($media['count'] !== 1 || ! $media['has_video'] || $media['has_image']
                            ? __('Instagram Reels requires exactly one video file.')
                            : null),
                    'stories' => ! $media['has_media']
                        ? __('Instagram Stories requires one image or video.')
                        : ($media['count'] !== 1
                            ? __('Instagram Stories requires exactly one media item.')
                            : null),
                    default => ! $media['has_media']
                        ? __('Instagram publishing requires at least one media item.')
                        : null,
                };
            });

        $providerKey = config('modules.appchannelinstagramprofiles.provider_key', 'instagram');

        register_channel_module('instagram_profile', [
            'provider' => [
                'key' => $providerKey,
                'order' => 20,
                'label' => 'Instagram',
                'icon' => 'fa-brands fa-instagram',
                'color' => '#C13584',
                'description' => 'Instagram Graph API settings for business and creator profile connections.',
                'required_fields' => ['app_id', 'app_secret'],
                'account_types' => ['oauth', 'manual'],
                'settings_view' => 'appchannelinstagramprofiles::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '0'],
                    ['key' => 'app_id', 'label' => 'App ID', 'type' => 'text', 'default' => ''],
                    ['key' => 'app_secret', 'label' => 'App Secret', 'type' => 'secret', 'default' => ''],
                    ['key' => 'graph_version', 'label' => 'Graph version', 'type' => 'text', 'default' => 'v25.0'],
                    ['key' => 'permissions', 'label' => 'Permissions', 'type' => 'textarea', 'default' => 'instagram_basic,instagram_content_publish,instagram_manage_comments,instagram_manage_messages,instagram_manage_insights,pages_read_engagement,pages_show_list,business_management'],
                    ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'text', 'default' => url('integrations/instagram/profile'), 'readonly' => true],
                    ['key' => 'webhook_verify_token', 'label' => 'Inbox webhook verification token', 'type' => 'secret', 'default' => ''],
                    ['key' => 'inbox_webhook_url', 'label' => 'Inbox webhook URL', 'type' => 'text', 'default' => url('webhooks/meta/instagram'), 'readonly' => true],
                ],
                'module' => [
                    'name' => 'AppChannelInstagramProfiles',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 20,
                'provider_key' => $providerKey,
                'label' => 'Profile',
                'title' => 'Instagram Profile',
                'description' => 'Connect Instagram business or creator profiles from the shared Instagram integration config.',
                'icon' => 'fa-brands fa-instagram',
                'color' => '#C13584',
                'categories' => ['Profile'],
                'account_types' => ['oauth', 'manual'],
                'supports_publishing' => true,
                'connect_label' => 'Profile',
                'driver' => \Modules\AppChannelInstagramProfiles\Support\InstagramProfilesChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelInstagramProfiles',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
