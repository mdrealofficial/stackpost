<?php

namespace Modules\AppChannelFacebookPages\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelFacebookPages\Services\Facebook\FacebookApiService;
use Modules\AppChannelFacebookPages\Services\Publishing\FacebookPostPublisher;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelFacebookPagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannelfacebookpages');
        $this->app->singleton(FacebookApiService::class);
        $this->app->singleton(FacebookPostPublisher::class);
        $this->app->alias(FacebookApiService::class, 'appchannel.facebook');

        if (! class_exists('Facebook')) {
            class_alias(\Modules\AppChannelFacebookPages\Facades\Facebook::class, 'Facebook');
        }
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannelfacebookpages');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->app->make(PublishingChannelRegistry::class)
            ->register('facebook', $this->app->make(FacebookPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register('facebook', [
                'label' => __('Facebook'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                    ['key' => 'reels', 'label' => __('Reels')],
                    ['key' => 'stories', 'label' => __('Stories')],
                ],
            ]);
        $this->app->make(PublishingOptionsRegistry::class)
            ->register('facebook', 'appchannelfacebookpages::publishing.options');
        $this->app->make(PublishingPreviewRegistry::class)
            ->register('facebook', 'appchannelfacebookpages::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register('facebook', fn (array $options): array => $options);
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register('facebook', function (array $context): ?string {
                $postTo = (string) data_get($context, 'options.post_to', 'feed');
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));

                return match ($postTo) {
                    'reels' => ! $media['has_media']
                        ? __('Facebook Reels requires one video.')
                        : ($media['count'] !== 1 || ! $media['has_video'] || $media['has_image']
                            ? __('Facebook Reels requires exactly one video file.')
                            : null),
                    'stories' => ! $media['has_media']
                        ? __('Facebook Stories requires one image or video.')
                        : ($media['count'] !== 1
                            ? __('Facebook Stories requires exactly one media item.')
                            : null),
                    default => $media['has_video'] && $media['has_image']
                        ? __('Facebook Feed cannot mix images and videos in the same post.')
                        : ($media['has_video'] && $media['count'] > 1
                            ? __('Facebook Feed currently supports only one video per post.')
                            : null),
                };
            });

        $providerKey = config('modules.appchannelfacebookpages.provider_key', 'facebook');

        register_channel_module('facebook_page', [
            'provider' => [
                'key' => $providerKey,
                'order' => 10,
                'label' => 'Facebook',
                'icon' => 'fa-brands fa-square-facebook',
                'color' => '#1877F2',
                'description' => 'Facebook API settings shared by every Facebook-based channel module.',
                'required_fields' => ['app_id', 'app_secret'],
                'account_types' => ['oauth', 'manual'],
                'settings_view' => 'appchannelfacebookpages::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '0'],
                    ['key' => 'app_id', 'label' => 'App ID', 'type' => 'text', 'default' => ''],
                    ['key' => 'app_secret', 'label' => 'App Secret', 'type' => 'secret', 'default' => ''],
                    ['key' => 'graph_version', 'label' => 'Graph version', 'type' => 'text', 'default' => 'v25.0'],
                    ['key' => 'permissions', 'label' => 'Permissions', 'type' => 'textarea', 'default' => 'pages_read_engagement,pages_manage_posts,pages_show_list,business_management,pages_manage_metadata,pages_manage_engagement,pages_read_user_content,pages_messaging,read_insights'],
                    ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'text', 'default' => url('integrations/facebook/page'), 'readonly' => true],
                    ['key' => 'webhook_verify_token', 'label' => 'Inbox webhook verification token', 'type' => 'secret', 'default' => ''],
                    ['key' => 'inbox_webhook_url', 'label' => 'Inbox webhook URL', 'type' => 'text', 'default' => url('webhooks/meta/facebook'), 'readonly' => true],
                ],
                'module' => [
                    'name' => 'AppChannelFacebookPages',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 10,
                'provider_key' => $providerKey,
                'label' => 'Page',
                'title' => 'Facebook Page',
                'description' => 'Connect and operate Facebook pages from the shared Facebook integration config.',
                'icon' => 'fa-brands fa-square-facebook',
                'color' => '#1877F2',
                'categories' => ['Page'],
                'account_types' => ['oauth', 'manual'],
                'supports_publishing' => true,
                'connect_label' => 'Page',
                'driver' => \Modules\AppChannelFacebookPages\Support\FacebookPagesChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelFacebookPages',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
