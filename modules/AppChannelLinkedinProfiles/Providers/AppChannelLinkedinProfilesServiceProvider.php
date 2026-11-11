<?php

namespace Modules\AppChannelLinkedinProfiles\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AppChannelLinkedinProfiles\Services\Linkedin\LinkedinApiService;
use Modules\AppChannelLinkedinProfiles\Services\Publishing\LinkedinPostPublisher;
use Modules\AppPublishing\Support\PublishingChannelRegistry;
use Modules\AppPublishing\Support\PublishingMediaInspector;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingPreviewRegistry;

class AppChannelLinkedinProfilesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appchannellinkedinprofiles');
        $this->app->singleton(LinkedinApiService::class);
        $this->app->singleton(LinkedinPostPublisher::class);
        $this->app->alias(LinkedinApiService::class, 'appchannel.linkedin');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appchannellinkedinprofiles');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');

        $providerKey = config('modules.appchannellinkedinprofiles.provider_key', 'linkedin_profile');

        $this->app->make(PublishingChannelRegistry::class)
            ->register($providerKey, $this->app->make(LinkedinPostPublisher::class));
        $this->app->make(PublishingNetworkConfigRegistry::class)
            ->register($providerKey, [
                'label' => __('LinkedIn'),
                'post_to_options' => [
                    ['key' => 'feed', 'label' => __('Feed')],
                ],
            ]);
        $this->app->make(PublishingPreviewRegistry::class)
            ->register($providerKey, 'appchannellinkedinprofiles::publishing.preview');
        $this->app->make(PublishingNetworkOptionsRegistry::class)
            ->register($providerKey, fn (array $options): array => []);
        $this->app->make(PublishingMediaValidationRegistry::class)
            ->register($providerKey, function (array $context): ?string {
                $postType = (string) data_get($context, 'options.linkedin_post_type', 'auto');
                $media = PublishingMediaInspector::analyze((array) ($context['media_items'] ?? []));

                return match ($postType) {
                    'text', 'link' => $media['has_media']
                        ? __('LinkedIn text and link posts cannot include attached media.')
                        : null,
                    'video' => ! $media['has_media']
                        ? __('LinkedIn video posts require one video.')
                        : ($media['count'] !== 1 || ! $media['has_video'] || $media['has_image']
                            ? __('LinkedIn video posts require exactly one video file.')
                            : null),
                    'media', 'image' => ! $media['has_media']
                        ? __('LinkedIn image posts require at least one image.')
                        : ($media['has_video']
                            ? __('LinkedIn image posts support images only.')
                            : null),
                    default => $media['has_video'] && $media['has_image']
                        ? __('LinkedIn auto mode cannot mix images and videos in the same post.')
                        : ($media['has_video'] && $media['count'] > 1
                            ? __('LinkedIn auto mode supports only one video per post.')
                            : null),
                };
            });

        register_channel_module('linkedin_profile', [
            'provider' => [
                'key' => $providerKey,
                'order' => 18,
                'label' => 'LinkedIn',
                'icon' => 'fa-brands fa-linkedin',
                'color' => '#0A66C2',
                'description' => 'LinkedIn OAuth settings for member profile connections.',
                'required_fields' => ['app_id', 'app_secret'],
                'account_types' => ['oauth'],
                'settings_view' => 'appchannellinkedinprofiles::integrations.settings',
                'fields' => [
                    ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '0'],
                    ['key' => 'app_id', 'label' => 'App ID', 'type' => 'text', 'default' => ''],
                    ['key' => 'app_secret', 'label' => 'App Secret', 'type' => 'secret', 'default' => ''],
                    ['key' => 'permissions', 'label' => 'Permissions', 'type' => 'textarea', 'default' => 'openid profile email w_member_social'],
                    ['key' => 'callback_url', 'label' => 'Callback URL', 'type' => 'text', 'default' => url('integrations/linkedin/profile'), 'readonly' => true],
                ],
                'module' => [
                    'name' => 'AppChannelLinkedinProfiles',
                    'source' => 'module',
                ],
            ],
            'capability' => [
                'order' => 18,
                'provider_key' => $providerKey,
                'label' => 'Profile',
                'title' => 'LinkedIn Profile',
                'description' => 'Connect a LinkedIn member profile from the shared LinkedIn OAuth config.',
                'icon' => 'fa-brands fa-linkedin',
                'color' => '#0A66C2',
                'categories' => ['Profile'],
                'account_types' => ['oauth'],
                'supports_publishing' => true,
                'connect_label' => 'Profile',
                'driver' => \Modules\AppChannelLinkedinProfiles\Support\LinkedinProfilesChannelDriver::class,
                'module' => [
                    'name' => 'AppChannelLinkedinProfiles',
                    'source' => 'module',
                ],
            ],
        ]);
    }
}
