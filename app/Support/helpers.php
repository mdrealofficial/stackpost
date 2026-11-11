<?php

use App\Support\TimezoneCatalog;
use App\Support\Dashboard\AdminDashboardRegistry;
use App\Support\Dashboard\UserDashboardRegistry;
use App\Support\Navigation\SidebarRegistry;
use App\Support\Navigation\HeaderRegistry;
use App\Support\Plans\PlanPermissionRegistry;
use Laravel\Fortify\Features;
use Modules\AdminLanguages\Support\WorldLanguageCatalog;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AdminUser\Models\AuditLog;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppCredits\Support\CreditActionRegistry;
use Modules\AppCredits\Support\CreditSettings;
use Modules\AppCredits\Support\CreditService;
use Modules\AppCredits\Support\CreditTopupService;
use Modules\AppPublishing\Support\PublishingProviderPaletteRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Modules\AppUrlShorteners\Support\UrlShortenerService;

if (! function_exists('sidebar_registry')) {
    function sidebar_registry(): SidebarRegistry
    {
        return app(SidebarRegistry::class);
    }
}

if (! function_exists('register_sidebar_section')) {
    function register_sidebar_section(string $key, ?string $label = null, int $order = 100): SidebarRegistry
    {
        return sidebar_registry()->section('admin', $key, $label, $order);
    }
}

if (! function_exists('register_sidebar_item')) {
    function register_sidebar_item(string $sectionKey, array $item): SidebarRegistry
    {
        return sidebar_registry()->register('admin', $sectionKey, $item);
    }
}

if (! function_exists('sidebar_sections')) {
    function sidebar_sections(string $area = 'admin'): array
    {
        return sidebar_registry()->sections($area);
    }
}

if (! function_exists('register_user_sidebar_section')) {
    function register_user_sidebar_section(string $key, ?string $label = null, int $order = 100): SidebarRegistry
    {
        return sidebar_registry()->section('user', $key, $label, $order);
    }
}

if (! function_exists('register_user_sidebar_item')) {
    function register_user_sidebar_item(string $sectionKey, array $item): SidebarRegistry
    {
        return sidebar_registry()->register('user', $sectionKey, $item);
    }
}

if (! function_exists('header_registry')) {
    function header_registry(): HeaderRegistry
    {
        return app(HeaderRegistry::class);
    }
}

if (! function_exists('register_header_item')) {
    function register_header_item(array $item, string $area = 'admin'): HeaderRegistry
    {
        return header_registry()->register($area, $item);
    }
}

if (! function_exists('add_to_header')) {
    function add_to_header(array $item, string $area = 'admin'): HeaderRegistry
    {
        return register_header_item($item, $area);
    }
}

if (! function_exists('header_items')) {
    function header_items(string $area = 'admin', ?string $position = null): array
    {
        return header_registry()->items($area, $position);
    }
}

if (! function_exists('admin_dashboard_registry')) {
    function admin_dashboard_registry(): AdminDashboardRegistry
    {
        return app(AdminDashboardRegistry::class);
    }
}

if (! function_exists('register_admin_dashboard_item')) {
    function register_admin_dashboard_item(string $id, array $item = []): AdminDashboardRegistry
    {
        return admin_dashboard_registry()->register($id, $item);
    }
}

if (! function_exists('admin_dashboard_items')) {
    function admin_dashboard_items(?\Illuminate\Contracts\Auth\Authenticatable $user = null, ?string $region = null): array
    {
        return admin_dashboard_registry()->items($user, $region);
    }
}

if (! function_exists('admin_dashboard_layout')) {
    function admin_dashboard_layout(?\Illuminate\Contracts\Auth\Authenticatable $user = null): array
    {
        return admin_dashboard_registry()->layout($user);
    }
}

if (! function_exists('user_dashboard_registry')) {
    function user_dashboard_registry(): UserDashboardRegistry
    {
        return app(UserDashboardRegistry::class);
    }
}

if (! function_exists('register_user_dashboard_item')) {
    function register_user_dashboard_item(string $id, array $item = []): UserDashboardRegistry
    {
        return user_dashboard_registry()->register($id, $item);
    }
}

if (! function_exists('user_dashboard_items')) {
    function user_dashboard_items(?\Illuminate\Contracts\Auth\Authenticatable $user = null, ?string $region = null): array
    {
        return user_dashboard_registry()->items($user, $region);
    }
}

if (! function_exists('user_dashboard_layout')) {
    function user_dashboard_layout(?\Illuminate\Contracts\Auth\Authenticatable $user = null): array
    {
        return user_dashboard_registry()->layout($user);
    }
}

if (! function_exists('plan_permission_registry')) {
    function plan_permission_registry(): PlanPermissionRegistry
    {
        return app(PlanPermissionRegistry::class);
    }
}

if (! function_exists('register_plan_permission')) {
    function register_plan_permission(array $item): PlanPermissionRegistry
    {
        return plan_permission_registry()->register($item);
    }
}

if (! function_exists('plan_permissions')) {
    function plan_permissions(): array
    {
        return plan_permission_registry()->all();
    }
}

if (! function_exists('credit_action_registry')) {
    function credit_action_registry(): CreditActionRegistry
    {
        return app(CreditActionRegistry::class);
    }
}

if (! function_exists('register_credit_action')) {
    function register_credit_action(array $item): CreditActionRegistry
    {
        return credit_action_registry()->register($item);
    }
}

if (! function_exists('credit_actions')) {
    function credit_actions(): array
    {
        return credit_action_registry()->all();
    }
}

if (! function_exists('credit_service')) {
    function credit_service(): CreditService
    {
        return app(CreditService::class);
    }
}

if (! function_exists('credit_settings')) {
    function credit_settings(): CreditSettings
    {
        return app(CreditSettings::class);
    }
}

if (! function_exists('credit_topup_service')) {
    function credit_topup_service(): CreditTopupService
    {
        return app(CreditTopupService::class);
    }
}

if (! function_exists('credit_summary')) {
    function credit_summary(?\Modules\AdminUser\Models\User $user): array
    {
        return credit_service()->summary($user);
    }
}

if (! function_exists('consume_credits')) {
    function consume_credits(?\Modules\AdminUser\Models\User $user, string $actionKey, array $attributes = []): \Modules\AppCredits\Models\CreditUsageLog
    {
        return credit_service()->consume($user, $actionKey, $attributes);
    }
}

if (! function_exists('url_shortener_service')) {
    function url_shortener_service(): UrlShortenerService
    {
        return app(UrlShortenerService::class);
    }
}

if (! function_exists('url_shortener_platforms')) {
    function url_shortener_platforms(): array
    {
        return url_shortener_service()->platforms();
    }
}

if (! function_exists('shorten_url')) {
    function shorten_url(string $url, string $platform = ''): ?string
    {
        return url_shortener_service()->shorten($url, $platform);
    }
}

if (! function_exists('shorten_urls_in_content')) {
    function shorten_urls_in_content(?string $content, string $platform = ''): string
    {
        return url_shortener_service()->shortenUrlsInContent($content, $platform);
    }
}

if (! function_exists('url_shortening_enabled')) {
    function url_shortening_enabled(?\Modules\AdminUser\Models\User $user = null): bool
    {
        if (! url_shortener_service()->isConfigured()) {
            return false;
        }

        if (! $user) {
            return true;
        }

        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return $planOwner?->canUsePlanFeature('url_shorteners') ?? false;
    }
}

if (! function_exists('affiliate_service')) {
    function affiliate_service(): AffiliateService
    {
        return app(AffiliateService::class);
    }
}

if (! function_exists('affiliate_enabled')) {
    function affiliate_enabled(): bool
    {
        return affiliate_service()->isEnabled();
    }
}

if (! function_exists('timezone_options')) {
    function timezone_options(): array
    {
        return TimezoneCatalog::all();
    }
}

if (! function_exists('timezone_select_options')) {
    function timezone_select_options(): array
    {
        return TimezoneCatalog::options();
    }
}

if (! function_exists('timezone_label')) {
    function timezone_label(?string $timezone): string
    {
        return TimezoneCatalog::label($timezone);
    }
}

if (! function_exists('save_admin_dashboard_layout')) {
    function save_admin_dashboard_layout(?\Illuminate\Contracts\Auth\Authenticatable $user, array $itemIds): void
    {
        admin_dashboard_registry()->saveLayout($user, $itemIds);
    }
}

if (! function_exists('save_user_dashboard_layout')) {
    function save_user_dashboard_layout(?\Illuminate\Contracts\Auth\Authenticatable $user, array $itemIds): void
    {
        user_dashboard_registry()->saveLayout($user, $itemIds);
    }
}

if (! function_exists('fortify_features_from_settings')) {
    function fortify_features_from_settings(): array
    {
        $features = [
            Features::resetPasswords(),
        ];

        if (auth_signup_enabled()) {
            $features[] = Features::registration();
        }

        if (auth_activation_email_enabled()) {
            $features[] = Features::emailVerification();
        }

        $twoFactorEnabled = app(OptionStore::class)->get('auth_two_factor_authentication_status', '1') === '1';

        if ($twoFactorEnabled) {
            $features[] = Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => true,
            ]);
        }

        return $features;
    }
}

if (! function_exists('sync_fortify_features_from_settings')) {
    function sync_fortify_features_from_settings(): array
    {
        $features = fortify_features_from_settings();

        config(['fortify.features' => $features]);

        return $features;
    }
}

if (! function_exists('auth_landing_enabled')) {
    function auth_landing_enabled(): bool
    {
        return app(OptionStore::class)->get('auth_landing_page_status', '1') === '1';
    }
}

if (! function_exists('auth_signup_enabled')) {
    function auth_signup_enabled(): bool
    {
        return app(OptionStore::class)->get('auth_signup_page_status', '1') === '1';
    }
}

if (! function_exists('auth_activation_email_enabled')) {
    function auth_activation_email_enabled(): bool
    {
        return app(OptionStore::class)->get('auth_activation_email_new_user_status', '0') === '1';
    }
}

if (! function_exists('auth_welcome_email_enabled')) {
    function auth_welcome_email_enabled(): bool
    {
        return app(OptionStore::class)->get('auth_welcome_email_new_user_status', '0') === '1';
    }
}

if (! function_exists('log_activity')) {
    function log_activity(string $event, ?string $description = null, array $attributes = []): AuditLog
    {
        $subject = $attributes['subject'] ?? null;
        $metadata = $attributes['metadata'] ?? [];
        $request = request();
        $routeName = $request?->route()?->getName();
        $area = str_starts_with((string) $routeName, 'portal.') ? 'user' : 'admin';

        return AuditLog::query()->create([
            'causer_user_id' => $attributes['causer_user_id'] ?? auth()->id(),
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject ? $subject::class : ($attributes['subject_type'] ?? null),
            'subject_id' => $subject?->getKey() ?? ($attributes['subject_id'] ?? null),
            'route_name' => $routeName,
            'area' => $attributes['area'] ?? $area,
            'ip_address' => $request?->ip(),
            'user_agent' => (string) ($request?->userAgent() ?? ''),
            'metadata' => $metadata,
        ]);
    }
}

if (! function_exists('world_languages')) {
    function world_languages(): array
    {
        return WorldLanguageCatalog::all();
    }
}

if (! function_exists('publishing_provider_tone')) {
    function publishing_provider_tone(?string $providerKey, array $default = ['surface' => 'rgba(99, 102, 241, 0.12)', 'text' => '#4f46e5']): array
    {
        return app(PublishingProviderPaletteRegistry::class)->get((string) $providerKey, $default);
    }
}

if (! function_exists('publishing_provider_chip_style')) {
    function publishing_provider_chip_style(
        ?string $providerKey,
        bool $active = true,
        ?string $inactiveStyle = null
    ): string {
        if (! $active) {
            return $inactiveStyle ?: 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.94); color: var(--theme-header-text-color);';
        }

        $tone = publishing_provider_tone($providerKey);

        return sprintf(
            'border-color: transparent; background-color: %s; color: %s;',
            (string) ($tone['surface'] ?? 'rgba(99, 102, 241, 0.12)'),
            (string) ($tone['text'] ?? '#4f46e5')
        );
    }
}
