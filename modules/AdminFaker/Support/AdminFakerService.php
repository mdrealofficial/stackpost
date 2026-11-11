<?php

namespace Modules\AdminFaker\Support;

use App\Support\Storage\StorageDriverManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\AdminAI\Models\AiUsageLog;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogs\Models\Blog;
use Modules\AdminBlogs\Models\BlogRssImport;
use Modules\AdminBlogs\Models\BlogRssSource;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AdminFaqs\Models\Faq;
use Modules\AdminNotifications\Models\NotificationManual;
use Modules\AdminNotifications\Models\NotificationManualState;
use Modules\AdminPaymentHistory\Models\PaymentHistory;
use Modules\AdminPlans\Models\AdminPlan;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportComment;
use Modules\AdminSupport\Models\SupportLabel;
use Modules\AdminSupport\Models\SupportTicket;
use Modules\AdminSupport\Models\SupportType;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AdminUser\Support\PersonalTeamProvisioner;
use Modules\AppAffiliate\Models\AffiliateCommission;
use Modules\AppAffiliate\Models\AffiliateWithdrawal;
use Modules\AppAffiliate\Support\AffiliateService;
use Modules\AppAiPublishing\Models\AiPublishingPrompt;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppCaptions\Models\CaptionLibraryItem;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppLinkBio\Models\LinkBioEvent;
use Modules\AppLinkBio\Models\LinkBioPage;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppRssSchedules\Models\RssSchedule;

class AdminFakerService
{
    protected const DEMO_MARKER = 'admin-faker';

    public function __construct(
        protected PersonalTeamProvisioner $teamProvisioner,
        protected StorageDriverManager $storageDriverManager,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function seedForFirstUser(bool $clearBeforeSeed = true): array
    {
        $user = $this->resolveFirstUser();

        return $this->seedExistingUser($user, $clearBeforeSeed);
    }

    /**
     * @return array<string, mixed>
     */
    public function clearForFirstUser(): array
    {
        $user = $this->resolveFirstUser();

        return $this->clearByUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function seed(string $email, string $name, bool $clearBeforeSeed = true): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'username' => $this->uniqueUsername($email, $name),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'locale' => 'vi',
                'timezone' => 'Asia/Saigon',
                'role_id' => null,
                'is_super_admin' => false,
            ]);
        }

        return $this->seedExistingUser($user, $clearBeforeSeed);
    }

    /**
     * @return array<string, mixed>
     */
    public function clear(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return [
                'user' => ['email' => $email, 'password_hint' => __('No account found')],
                'counts' => [],
            ];
        }

        return $this->clearByUser($user);
    }

    /**
     * @return array<string, mixed>
     */
    protected function seedExistingUser(User $user, bool $clearBeforeSeed = true): array
    {
        if ($clearBeforeSeed) {
            $this->clearByUser($user);
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?: now(),
            'locale' => $user->locale ?: 'vi',
            'timezone' => $user->timezone ?: 'Asia/Saigon',
        ])->save();

        $this->ensurePlan($user);

        $team = $this->teamProvisioner->ensureForUser($user);
        $team->update([
            'enabled_modules' => ['channels', 'publishing', 'ai_publishing', 'chat', 'link_bio'],
        ]);

        $now = now();
        $counts = [
            'channels' => 0,
            'media_images' => 0,
            'captions' => 0,
            'daily_schedules' => 0,
            'published_posts' => 0,
            'queued_posts' => 0,
            'draft_posts' => 0,
            'failed_posts' => 0,
            'processing_posts' => 0,
            'rss_schedules' => 0,
            'support_tickets' => 0,
            'support_comments' => 0,
            'affiliate_commissions' => 0,
            'affiliate_withdrawals' => 0,
            'blogs' => 0,
            'faqs' => 0,
            'ai_prompts' => 0,
            'ai_publishing_runs' => 0,
            'ai_logs' => 0,
            'global_notifications' => 0,
            'link_bio_pages' => 0,
            'link_bio_events' => 0,
        ];

        $imageFiles = $this->createDemoImages($user);
        $counts['media_images'] = count($imageFiles);

        $accounts = $this->createDemoChannels($user, $imageFiles);
        $counts['channels'] = $accounts->count();

        $this->createCaptionLibrary($user, $team, $counts);
        $this->createLinkBioPages($user, $team, $imageFiles, $counts);
        $this->createDailyPublishingPosts($user, $team, $accounts, $imageFiles, $now, $counts);
        $this->createSupportInbox($user, $team, $counts);
        $this->createAffiliateSnapshot($user, $counts);
        $this->createFaqs($counts);
        [$category, $tags] = $this->createBlogTaxonomy();
        $blogs = $this->createBlogs($user, $category, $tags, $imageFiles, $counts);
        $this->createBlogRssSources($category, $tags, $blogs, $counts);
        [$prompts, $runs] = $this->createAiPublishing($user, $team, $accounts, $imageFiles, $now, $counts);
        $this->createAiLogs($user, $runs, $counts);
        $this->createGlobalNotifications($user, $counts);

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'password_hint' => __('Preserved existing password'),
            ],
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'counts' => $counts,
            'prompt_ids' => $prompts->pluck('id')->all(),
            'run_ids' => $runs->pluck('id')->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function clearByUser(User $user): array
    {
        $deleted = [
            'channels' => 0,
            'media_images' => 0,
            'captions' => 0,
            'daily_schedules' => 0,
            'published_posts' => 0,
            'queued_posts' => 0,
            'draft_posts' => 0,
            'failed_posts' => 0,
            'processing_posts' => 0,
            'rss_schedules' => 0,
            'support_tickets' => 0,
            'support_comments' => 0,
            'affiliate_commissions' => 0,
            'affiliate_withdrawals' => 0,
            'blogs' => 0,
            'faqs' => 0,
            'ai_prompts' => 0,
            'ai_publishing_runs' => 0,
            'ai_logs' => 0,
            'global_notifications' => 0,
            'link_bio_pages' => 0,
            'link_bio_events' => 0,
        ];

        Storage::disk('public')->deleteDirectory('files/demo-faker/public/user-'.$user->id);

        $paths = AppFile::query()
            ->where('owner_user_id', $user->id)
            ->where('path', 'like', 'files/demo-faker/%')
            ->pluck('path')
            ->all();

        foreach ($paths as $path) {
            Storage::disk('public')->delete((string) $path);
        }

        $deleted['media_images'] = AppFile::query()
            ->where('owner_user_id', $user->id)
            ->where('path', 'like', 'files/demo-faker/%')
            ->delete();

        $captionItems = CaptionLibraryItem::query()
            ->ownedBy($user->id)
            ->where('metadata->source', self::DEMO_MARKER)
            ->get(['id']);

        if ($captionItems->isNotEmpty()) {
            CaptionLibraryItem::query()->whereIn('id', $captionItems->pluck('id'))->delete();
        }

        $deleted['captions'] = $captionItems->count();

        $this->clearLinkBioPages($user, $deleted);

        $deleted['channels'] = SocialAccount::query()
            ->where('created_by_user_id', $user->id)
            ->get()
            ->filter(fn (SocialAccount $account): bool => data_get($account->metadata, 'source') === self::DEMO_MARKER)
            ->tap(function ($accounts): void {
                $accounts->each->delete();
            })
            ->count();

        $demoPosts = PublishingPost::query()
            ->where('user_id', $user->id)
            ->where('custom_data_2', self::DEMO_MARKER)
            ->get(['id', 'status']);

        $demoPostIds = $demoPosts->pluck('id');

        if ($demoPostIds->isNotEmpty()) {
            DB::table('team_post_comments')->whereIn('post_id', $demoPostIds)->delete();
            DB::table('team_post_reviews')->whereIn('post_id', $demoPostIds)->delete();
        }

        foreach ($demoPosts as $post) {
            $this->incrementPublishingStatusCount($deleted, (int) $post->status);
        }

        $deleted['daily_schedules'] = PublishingPost::query()
            ->where('user_id', $user->id)
            ->where('custom_data_2', self::DEMO_MARKER)
            ->delete();

        $rssSchedules = RssSchedule::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (RssSchedule $schedule) => data_get($schedule->settings, 'source') === self::DEMO_MARKER);

        if ($rssSchedules->isNotEmpty()) {
            DB::table('rss_schedule_histories')->whereIn('schedule_id', $rssSchedules->pluck('id'))->delete();
            RssSchedule::query()->whereIn('id', $rssSchedules->pluck('id'))->delete();
        }

        $deleted['rss_schedules'] = $rssSchedules->count();

        $supportTicketIds = SupportTicket::query()
            ->where('uid', $user->id)
            ->where('title', 'like', '[DEMO]%')
            ->pluck('id');

        if ($supportTicketIds->isNotEmpty()) {
            $deleted['support_comments'] = SupportComment::query()->whereIn('ticket_id', $supportTicketIds)->delete();
            DB::table('support_map_labels')->whereIn('ticket_id', $supportTicketIds)->delete();
            $deleted['support_tickets'] = SupportTicket::query()->whereIn('id', $supportTicketIds)->delete();
        }

        SupportCategory::query()->where('name', 'like', '[DEMO]%')->delete();
        SupportType::query()->where('name', 'like', '[DEMO]%')->delete();
        SupportLabel::query()->where('name', 'like', '[DEMO]%')->delete();

        $demoCommissions = AffiliateCommission::query()
            ->where('affiliate_user_id', $user->id)
            ->where('meta->source', self::DEMO_MARKER)
            ->get(['id', 'commission', 'status']);

        $demoPaymentHistoryIds = PaymentHistory::query()
            ->where('uid', $user->id)
            ->where('meta->source', self::DEMO_MARKER)
            ->pluck('id');

        if ($demoCommissions->isNotEmpty()) {
            AffiliateCommission::query()->whereIn('id', $demoCommissions->pluck('id'))->delete();
        }

        if ($demoPaymentHistoryIds->isNotEmpty()) {
            PaymentHistory::query()->whereIn('id', $demoPaymentHistoryIds)->delete();
        }

        $demoWithdrawals = AffiliateWithdrawal::query()
            ->where('affiliate_user_id', $user->id)
            ->where('notes', 'Generated by Admin Faker')
            ->get(['id', 'amount', 'status']);

        $approvedCommissionTotal = (float) $demoCommissions
            ->where('status', AffiliateCommission::STATUS_APPROVED)
            ->sum(fn (AffiliateCommission $commission) => (float) $commission->commission);
        $approvedWithdrawalTotal = (float) $demoWithdrawals
            ->where('status', AffiliateWithdrawal::STATUS_APPROVED)
            ->sum(fn (AffiliateWithdrawal $withdrawal) => (float) $withdrawal->amount);

        if ($demoWithdrawals->isNotEmpty()) {
            AffiliateWithdrawal::query()->whereIn('id', $demoWithdrawals->pluck('id'))->delete();
        }

        if ($demoCommissions->isNotEmpty() || $demoWithdrawals->isNotEmpty()) {
            $profile = app(AffiliateService::class)->ensureProfile($user);
            $profile->forceFill([
                'clicks' => max(0, (int) $profile->clicks - 184),
                'conversions' => max(0, (int) $profile->conversions - $demoCommissions->count()),
                'total_approved' => max(0, (float) $profile->total_approved - $approvedCommissionTotal),
                'total_withdrawal' => max(0, (float) $profile->total_withdrawal - $approvedWithdrawalTotal),
                'total_balance' => max(0, (float) $profile->total_balance - 32.00),
            ])->save();
        }

        $deleted['affiliate_commissions'] = $demoCommissions->count();
        $deleted['affiliate_withdrawals'] = $demoWithdrawals->count();

        $sources = BlogRssSource::query()
            ->where('name', 'like', '[DEMO]%')
            ->pluck('id');

        if ($sources->isNotEmpty()) {
            BlogRssImport::query()->whereIn('blog_rss_source_id', $sources)->delete();
            BlogRssSource::query()->whereIn('id', $sources)->delete();
        }

        $blogIds = Blog::query()->where('slug', 'like', 'demo-preview-%')->pluck('id');

        if ($blogIds->isNotEmpty()) {
            DB::table('blog_tag_maps')->whereIn('blog_id', $blogIds)->delete();
            $deleted['blogs'] = Blog::query()->whereIn('id', $blogIds)->delete();
        }

        $tagIds = BlogTag::query()->where('slug', 'like', 'demo-preview-%')->pluck('id');
        if ($tagIds->isNotEmpty()) {
            DB::table('blog_tag_maps')->whereIn('blog_tag_id', $tagIds)->delete();
            BlogTag::query()->whereIn('id', $tagIds)->delete();
        }

        BlogCategory::query()->where('slug', 'like', 'demo-preview-%')->delete();

        $deleted['faqs'] = Faq::query()->where('slug', 'like', 'demo-preview-%')->delete();

        $prompts = AiPublishingPrompt::query()
            ->ownedBy($user->id)
            ->get()
            ->filter(fn (AiPublishingPrompt $prompt) => data_get($prompt->metadata, 'source') === self::DEMO_MARKER);

        if ($prompts->isNotEmpty()) {
            AiPublishingPrompt::query()->whereIn('id', $prompts->pluck('id'))->delete();
        }
        $deleted['ai_prompts'] = $prompts->count();

        $runs = AiPublishingRun::query()
            ->where('owner_user_id', $user->id)
            ->get()
            ->filter(fn (AiPublishingRun $run) => data_get($run->generation_config, 'source') === self::DEMO_MARKER);

        if ($runs->isNotEmpty()) {
            AiPublishingRun::query()->whereIn('id', $runs->pluck('id'))->delete();
        }
        $deleted['ai_publishing_runs'] = $runs->count();

        $aiLogs = AiUsageLog::query()
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn (AiUsageLog $log) => data_get($log->metadata, 'source') === self::DEMO_MARKER);

        if ($aiLogs->isNotEmpty()) {
            AiUsageLog::query()->whereIn('id', $aiLogs->pluck('id'))->delete();
        }
        $deleted['ai_logs'] = $aiLogs->count();

        $globalNotifications = NotificationManual::query()
            ->where('is_global', true)
            ->where('title', 'like', '[DEMO]%')
            ->get(['id']);

        if ($globalNotifications->isNotEmpty()) {
            NotificationManualState::query()
                ->whereIn('notification_manual_id', $globalNotifications->pluck('id'))
                ->delete();

            NotificationManual::query()
                ->whereIn('id', $globalNotifications->pluck('id'))
                ->delete();
        }

        $deleted['global_notifications'] = $globalNotifications->count();

        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'password_hint' => __('Preserved existing password'),
            ],
            'counts' => $deleted,
        ];
    }

    protected function resolveFirstUser(): User
    {
        $user = User::query()->orderBy('id')->first();

        abort_if(! $user, 404, __('No user found for Admin Faker.'));

        return $user;
    }

    protected function ensurePlan(User $user): void
    {
        if ($user->plan_id) {
            return;
        }

        $plan = AdminPlan::query()
            ->where('status', true)
            ->orderByDesc('default_signup_plan')
            ->orderBy('position')
            ->first();

        if (! $plan) {
            return;
        }

        $user->forceFill([
            'plan_id' => $plan->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addDays(max(30, (int) ($plan->trial_day ?: 30))),
        ])->save();
    }

    /**
     * @return array<int, AppFile>
     */
    protected function createDemoImages(User $user): array
    {
        return AppFile::query()
            ->ownedBy($user)
            ->where('is_folder', false)
            ->where('is_image', true)
            ->whereNotNull('path')
            ->where('path', 'not like', 'files/demo-faker/%')
            ->get()
            ->filter(fn (AppFile $file): bool => $this->isUsableImageFile($file))
            ->shuffle()
            ->take(12)
            ->values()
            ->all();
    }

    protected function createDemoChannels(User $user, array $imageFiles)
    {
        $personas = [
            ['name' => 'Stackposts', 'handle' => 'stackposts'],
            ['name' => 'John Smith', 'handle' => 'johnsmith'],
            ['name' => 'Anna Studio', 'handle' => 'annastudio'],
            ['name' => "Sunny & Gannie's", 'handle' => 'sunnygannies'],
            ['name' => 'Blue Harbor', 'handle' => 'blueharbor'],
            ['name' => 'Mia Carter', 'handle' => 'miacarter'],
            ['name' => 'North Peak', 'handle' => 'northpeak'],
            ['name' => 'Olivia Reed', 'handle' => 'oliviareed'],
            ['name' => 'Pixel Garden', 'handle' => 'pixelgarden'],
            ['name' => 'Ethan Walker', 'handle' => 'ethanwalker'],
            ['name' => 'Emma Stone', 'handle' => 'emmastone'],
            ['name' => 'Liam Brooks', 'handle' => 'liambrooks'],
            ['name' => 'Ava Wilson', 'handle' => 'avawilson'],
            ['name' => 'Noah Bennett', 'handle' => 'noahbennett'],
            ['name' => 'Sophia Lane', 'handle' => 'sophialane'],
            ['name' => 'Mason Reed', 'handle' => 'masonreed'],
            ['name' => 'Isabella Hart', 'handle' => 'isabellahart'],
            ['name' => 'Lucas Grant', 'handle' => 'lucasgrant'],
            ['name' => 'Charlotte Hayes', 'handle' => 'charlottehayes'],
            ['name' => 'James Cooper', 'handle' => 'jamescooper'],
            ['name' => 'Amelia Scott', 'handle' => 'ameliascott'],
            ['name' => 'Benjamin Cole', 'handle' => 'benjamincole'],
            ['name' => 'Harper Quinn', 'handle' => 'harperquinn'],
            ['name' => 'Elijah Ross', 'handle' => 'elijahross'],
            ['name' => 'Evelyn Price', 'handle' => 'evelynprice'],
            ['name' => 'Henry Adams', 'handle' => 'henryadams'],
            ['name' => 'Abigail Moore', 'handle' => 'abigailmoore'],
            ['name' => 'Alexander Bell', 'handle' => 'alexanderbell'],
            ['name' => 'Emily Foster', 'handle' => 'emilyfoster'],
            ['name' => 'Daniel Ward', 'handle' => 'danielward'],
            ['name' => 'Luna Creative', 'handle' => 'lunacreative'],
            ['name' => 'Urban Bloom', 'handle' => 'urbanbloom'],
            ['name' => 'Golden Finch', 'handle' => 'goldenfinch'],
            ['name' => 'Rose Atelier', 'handle' => 'roseatelier'],
            ['name' => 'Cedar Studio', 'handle' => 'cedarstudio'],
            ['name' => 'Maple & Co', 'handle' => 'mapleco'],
            ['name' => 'Bright Horizon', 'handle' => 'brighthorizon'],
            ['name' => 'Nora Blake', 'handle' => 'norablake'],
            ['name' => 'Samuel Green', 'handle' => 'samuelgreen'],
            ['name' => 'Chloe Brooks', 'handle' => 'chloebrooks'],
            ['name' => 'Jack Turner', 'handle' => 'jackturner'],
            ['name' => 'Grace Mitchell', 'handle' => 'gracemitchell'],
            ['name' => 'Owen Parker', 'handle' => 'owenparker'],
            ['name' => 'Lily Morgan', 'handle' => 'lilymorgan'],
            ['name' => 'Logan Hill', 'handle' => 'loganhill'],
            ['name' => 'Zoe Carter', 'handle' => 'zoecarter'],
            ['name' => 'Wyatt Hughes', 'handle' => 'wyatthughes'],
            ['name' => 'Aurora House', 'handle' => 'aurorahouse'],
            ['name' => 'Velvet Olive', 'handle' => 'velvetolive'],
            ['name' => 'Nova Harbor', 'handle' => 'novaharbor'],
            ['name' => 'Willow Lane', 'handle' => 'willowlane'],
            ['name' => 'Jasper North', 'handle' => 'jaspernorth'],
            ['name' => 'Hazel & Pine', 'handle' => 'hazelpine'],
            ['name' => 'Mila Harper', 'handle' => 'milaharper'],
            ['name' => 'Leo West', 'handle' => 'leowest'],
            ['name' => 'Ivy Clarke', 'handle' => 'ivyclarke'],
            ['name' => 'Theo James', 'handle' => 'theojames'],
            ['name' => 'Ruby Lane', 'handle' => 'rubylane'],
            ['name' => 'Finn Harper', 'handle' => 'finnharper'],
            ['name' => 'Echo Studio', 'handle' => 'echostudio'],
        ];

        $definitions = collect(publishable_channel_capability_keys($user))
            ->values()
            ->map(function (string $capabilityKey, int $index) use ($personas): ?array {
                $capability = channel_capability($capabilityKey);
                $providerKey = (string) ($capability['provider_key'] ?? '');

                if ($providerKey === '') {
                    return null;
                }

                $persona = $personas[$index % count($personas)];
                $categoryLabel = (string) ($capability['label'] ?? $capability['name'] ?? Str::headline(str_replace('_', ' ', $capabilityKey)));
                $categorySlug = Str::slug($categoryLabel);
                $providerSlug = $this->demoProviderSlug($providerKey);
                $handleBase = $persona['handle'];
                $handleSuffix = $categorySlug !== '' ? '.'.$categorySlug : '';

                return [
                    'provider' => $providerKey,
                    'capability_key' => $capabilityKey,
                    'account_type' => 'oauth',
                    'category' => $categoryLabel,
                    'name' => $persona['name'],
                    'username' => match ($providerKey) {
                        'x', 'reddit', 'telegram', 'vk' => $handleBase.'_'.$providerSlug.($categorySlug !== '' ? '_'.$categorySlug : ''),
                        'linkedin_profile', 'linkedin_page', 'google_business_profile' => $handleBase.'-'.$providerSlug.($categorySlug !== '' ? '-'.$categorySlug : ''),
                        default => $handleBase.$handleSuffix,
                    },
                    'url' => $this->demoProfileUrl($providerKey, $persona['handle'], $categorySlug),
                ];
            })
            ->filter()
            ->values();

        return $definitions->map(function (array $definition, int $index) use ($user, $imageFiles) {
            $image = $this->randomDemoImage($imageFiles);
            $storedAvatar = $image
                ? $this->storeChannelAvatarFromFile($image, $user, $definition['provider'], Str::upper($definition['provider']).'-DEMO-'.$user->id.'-'.($index + 1))
                : null;

            return SocialAccount::query()->create([
                'provider_key' => $definition['provider'],
                'capability_key' => $definition['capability_key'],
                'display_name' => $definition['name'],
                'username' => $definition['username'],
                'external_id' => Str::upper($definition['provider']).'-DEMO-'.$user->id.'-'.($index + 1),
                'category' => $definition['category'],
                'account_type' => $definition['account_type'],
                'profile_url' => $definition['url'],
                'avatar_url' => $storedAvatar['url'] ?? null,
                'avatar_disk' => $storedAvatar['disk'] ?? null,
                'avatar_path' => $storedAvatar['path'] ?? null,
                'metadata' => [
                    'source' => self::DEMO_MARKER,
                    'image_file_id' => $image?->id,
                    'avatar_source' => $storedAvatar ? 'app_file_copy' : 'fallback',
                ],
                'notes' => 'Generated by Admin Faker',
                'is_active' => true,
                'created_by_user_id' => $user->id,
                'connected_at' => now(),
                'last_synced_at' => now(),
            ]);
        });
    }

    protected function createLinkBioPages(User $user, Team $team, array $imageFiles, array &$counts): void
    {
        $imageUrls = collect($imageFiles)
            ->map(fn (AppFile $file): ?string => $this->demoPublicImageUrl($file))
            ->filter()
            ->values()
            ->all();

        $image = fn (int $index): string => (string) ($imageUrls[$index % max(1, count($imageUrls))] ?? '');

        $definitions = [
            [
                'title' => 'Stackposts Bio Page',
                'slug' => 'demo-smartbio-stackposts',
                'headline' => 'Smart links for product updates, demos, and support.',
                'description' => 'A clean SmartBio page that routes visitors to the most important Stackposts resources.',
                'template_key' => 'studio-pro',
                'accent_color' => '#2563eb',
                'published' => true,
                'avatar_url' => $image(0),
                'cover_url' => $image(1),
                'background_image' => $image(2),
                'blocks' => [
                    $this->linkBioBlock('header', 'Start here', 'New visitors', 'Explore features, pricing, and help resources from one focused page.', [], 'View live demo', '#'),
                    $this->linkBioBlock('links', 'Quick Links', 'Useful destinations', '', [
                        $this->linkBioItem('Live demo', '#', 'Try the demo workspace', 'fa-solid fa-arrow-up-right-from-square'),
                        $this->linkBioItem('Documentation', '#', 'Read installation and usage guides', 'fa-solid fa-book-open'),
                        $this->linkBioItem('Contact support', '#', 'Get help from support team', 'fa-solid fa-life-ring'),
                    ]),
                    $this->linkBioBlock('video', 'Product overview', 'Watch before installing', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', [], 'Watch video', 'https://www.youtube.com/'),
                    $this->linkBioBlock('faq', 'Buyer FAQ', 'Common questions', '', [
                        $this->linkBioItem('Does it require Stackposts?', '', '', 'fa-solid fa-circle-question', '', '', 'Yes. SmartBio is an addon built for Stackposts.'),
                        $this->linkBioItem('Can I customize templates?', '', '', 'fa-solid fa-circle-question', '', '', 'Yes. You can edit templates, colors, blocks, media, and public page content.'),
                    ]),
                ],
                'views' => 184,
            ],
            [
                'title' => 'Creator Launch Kit',
                'slug' => 'demo-smartbio-creator',
                'headline' => 'Creator resources, socials, and latest offers in one link.',
                'description' => 'Built for creators who need a fast profile page with clear calls to action.',
                'template_key' => 'aurora',
                'accent_color' => '#e83f6f',
                'published' => true,
                'avatar_url' => $image(3),
                'cover_url' => $image(4),
                'background_image' => $image(4),
                'blocks' => [
                    $this->linkBioBlock('social', 'Social Channels', 'Stay connected', '', [
                        $this->linkBioItem('Instagram', 'https://instagram.com/demo.creator', 'Behind the scenes and reels', 'fa-brands fa-instagram'),
                        $this->linkBioItem('YouTube', 'https://youtube.com/@demo.creator', 'Tutorials and long-form videos', 'fa-brands fa-youtube'),
                        $this->linkBioItem('TikTok', 'https://www.tiktok.com/@demo.creator', 'Short daily clips', 'fa-brands fa-tiktok'),
                    ]),
                    $this->linkBioBlock('links', 'Featured Links', 'Best next steps', '', [
                        $this->linkBioItem('Book a collaboration', 'https://example.com/collab', 'For brands and partners', 'fa-solid fa-handshake'),
                        $this->linkBioItem('Download media kit', 'https://example.com/media-kit', 'Audience stats and packages', 'fa-solid fa-file-arrow-down'),
                    ]),
                    $this->linkBioBlock('gallery', 'Latest Work', 'Recent highlights', '', [
                        $this->linkBioItem('Studio shoot', 'https://example.com/gallery/studio', 'Portrait campaign', 'fa-solid fa-images', $image(5)),
                        $this->linkBioItem('Travel vlog', 'https://example.com/gallery/travel', 'Weekend story set', 'fa-solid fa-images', $image(6)),
                    ]),
                ],
                'views' => 261,
            ],
            [
                'title' => 'Mini Shop Bio',
                'slug' => 'demo-smartbio-shop',
                'headline' => 'Shop featured products and promotions.',
                'description' => 'A compact storefront for sellers who want products, offers, and contact options in one bio link.',
                'template_key' => 'promo-orange',
                'accent_color' => '#ef4444',
                'published' => true,
                'avatar_url' => $image(7),
                'cover_url' => $image(8),
                'background_image' => $image(8),
                'blocks' => [
                    $this->linkBioBlock('product', 'Featured Products', 'Best sellers this week', '', [
                        $this->linkBioItem('Starter bundle', 'https://example.com/products/starter', 'A simple package for new customers', 'fa-solid fa-bag-shopping', $image(9), '$29'),
                        $this->linkBioItem('Pro bundle', 'https://example.com/products/pro', 'More templates and premium support', 'fa-solid fa-bag-shopping', $image(10), '$79'),
                    ]),
                    $this->linkBioBlock('contact', 'Need help choosing?', 'Talk to sales', '', [
                        $this->linkBioItem('Email sales', 'mailto:sales@example.com', 'Usually replies within 24 hours', 'fa-solid fa-envelope', '', '', '', 'sales@example.com'),
                        $this->linkBioItem('WhatsApp', 'https://wa.me/15550100', 'Fast support for pre-sale questions', 'fa-brands fa-whatsapp', '', '', '', '+1 555 0100'),
                    ]),
                ],
                'views' => 142,
            ],
            [
                'title' => 'Coach Booking Page',
                'slug' => 'demo-smartbio-coach',
                'headline' => 'Book coaching, read testimonials, and start with a quick guide.',
                'description' => 'A draft page for a coach or consultant who wants leads and appointment bookings.',
                'template_key' => 'sage-calm',
                'accent_color' => '#15803d',
                'published' => true,
                'avatar_url' => $image(11),
                'cover_url' => $image(0),
                'background_image' => '',
                'blocks' => [
                    $this->linkBioBlock('links', 'Work with me', 'Choose your next step', '', [
                        $this->linkBioItem('Book discovery call', 'https://example.com/book', 'Free 20-minute consultation', 'fa-solid fa-calendar-check'),
                        $this->linkBioItem('Download guide', 'https://example.com/guide', 'Start with a practical checklist', 'fa-solid fa-file-lines'),
                    ]),
                    $this->linkBioBlock('faq', 'Questions', 'Before booking', '', [
                        $this->linkBioItem('Who is this for?', '', '', 'fa-solid fa-circle-question', '', '', 'Founders, creators, and small teams who need a practical growth plan.'),
                    ]),
                ],
                'views' => 96,
            ],
        ];

        foreach ($definitions as $definition) {
            $blocks = array_values($definition['blocks']);
            $page = LinkBioPage::query()->create([
                'owner_user_id' => $user->id,
                'team_id' => $team->id,
                'title' => $definition['title'],
                'slug' => $this->uniqueLinkBioSlug((string) $definition['slug']),
                'headline' => $definition['headline'],
                'description' => $definition['description'],
                'accent_color' => $definition['accent_color'],
                'avatar_url' => $definition['avatar_url'],
                'cover_url' => $definition['cover_url'],
                'template_key' => $definition['template_key'],
                'status' => $definition['published'] ? 'published' : 'draft',
                'is_published' => (bool) $definition['published'],
                'blocks' => $blocks,
                'settings' => [
                    'source' => self::DEMO_MARKER,
                    'branding_text' => 'Powered by SmartBio',
                    'avatar_style' => 'circle',
                    'button_style' => 'rounded',
                    'content_align' => 'left',
                    'background_image' => $definition['background_image'],
                    'background_overlay' => $definition['background_image'] !== '' ? 28 : 0,
                    'background_position' => 'center',
                    'background_fit' => 'cover',
                ],
            ]);

            $counts['link_bio_pages']++;
            $this->createLinkBioDemoEvents($page, (int) $definition['views'], $blocks, $counts);
        }
    }

    protected function clearLinkBioPages(User $user, array &$deleted): void
    {
        $pageIds = LinkBioPage::query()
            ->where('owner_user_id', $user->id)
            ->get(['id', 'slug', 'settings'])
            ->filter(fn (LinkBioPage $page): bool => data_get($page->settings, 'source') === self::DEMO_MARKER || str_starts_with((string) $page->slug, 'demo-smartbio-'))
            ->pluck('id');

        if ($pageIds->isEmpty()) {
            return;
        }

        $deleted['link_bio_events'] = LinkBioEvent::query()
            ->whereIn('link_bio_page_id', $pageIds)
            ->delete();

        $deleted['link_bio_pages'] = LinkBioPage::query()
            ->whereIn('id', $pageIds)
            ->delete();
    }

    protected function createLinkBioDemoEvents(LinkBioPage $page, int $views, array $blocks, array &$counts): void
    {
        if ($views <= 0) {
            return;
        }

        $now = now();

        for ($i = 0; $i < $views; $i++) {
            DB::table('link_bio_events')->insert([
                'link_bio_page_id' => $page->id,
                'type' => 'view',
                'block_index' => null,
                'item_index' => null,
                'url' => null,
                'ip_hash' => hash('sha256', 'demo-view-'.$page->id.'-'.$i),
                'user_agent' => 'AdminFaker Demo Browser',
                'created_at' => $now->copy()->subMinutes(random_int(5, 7200)),
                'updated_at' => $now->copy()->subMinutes(random_int(5, 7200)),
            ]);
            $counts['link_bio_events']++;
        }

        foreach ($this->linkBioClickTargets($blocks) as $targetIndex => $target) {
            $clicks = max(3, (int) floor($views / (6 + $targetIndex)));

            for ($i = 0; $i < $clicks; $i++) {
                DB::table('link_bio_events')->insert([
                    'link_bio_page_id' => $page->id,
                    'type' => 'click',
                    'block_index' => $target['block_index'],
                    'item_index' => $target['item_index'],
                    'url' => $target['url'],
                    'ip_hash' => hash('sha256', 'demo-click-'.$page->id.'-'.$targetIndex.'-'.$i),
                    'user_agent' => 'AdminFaker Demo Browser',
                    'created_at' => $now->copy()->subMinutes(random_int(5, 7200)),
                    'updated_at' => $now->copy()->subMinutes(random_int(5, 7200)),
                ]);
                $counts['link_bio_events']++;
            }
        }
    }

    protected function linkBioBlock(
        string $type,
        string $title,
        string $subtitle = '',
        string $content = '',
        array $items = [],
        string $buttonLabel = '',
        string $buttonUrl = '',
    ): array {
        return [
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'content' => $content,
            'url' => '',
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
            'enabled' => true,
            'items' => $items,
        ];
    }

    protected function linkBioItem(
        string $label,
        string $url = '',
        string $note = '',
        string $icon = 'fa-solid fa-link',
        string $image = '',
        string $price = '',
        string $answer = '',
        string $value = '',
    ): array {
        return [
            'label' => $label,
            'url' => $url,
            'note' => $note,
            'icon' => $icon,
            'image' => $image,
            'value' => $value,
            'price' => $price,
            'placeholder' => '',
            'answer' => $answer,
            'field_type' => 'text',
        ];
    }

    protected function linkBioClickTargets(array $blocks): array
    {
        $targets = [];

        foreach ($blocks as $blockIndex => $block) {
            foreach ((array) data_get($block, 'items', []) as $itemIndex => $item) {
                $url = trim((string) data_get($item, 'url', ''));

                if ($url === '') {
                    continue;
                }

                $targets[] = [
                    'block_index' => $blockIndex,
                    'item_index' => $itemIndex,
                    'url' => $url,
                ];
            }
        }

        return $targets;
    }

    protected function uniqueLinkBioSlug(string $base): string
    {
        $base = Str::slug($base) ?: 'demo-smartbio-page';
        $slug = $base;
        $counter = 2;

        while (LinkBioPage::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    protected function demoPublicImageUrl(?AppFile $file): string
    {
        if (! $file || ! filled($file->path)) {
            return '';
        }

        $disk = (string) ($file->disk ?: 'public');
        $path = trim((string) $file->path);

        return (string) ($this->storageDriverManager->publicUrl($disk, $path) ?: $this->demoImageUrl($file) ?: '');
    }

    protected function createCaptionLibrary(User $user, Team $team, array &$counts): void
    {
        $items = [
            [
                'name' => 'Demo product teaser caption',
                'source_type' => 'manual',
                'status' => 'active',
                'content' => 'Launch week is here. Give your audience one clear reason to care, one proof point, and one next step they can take today.',
                'notes' => 'Short caption for product announcements and feature drops.',
                'tags' => ['launch', 'product', 'social-copy'],
            ],
            [
                'name' => 'Demo case study hook',
                'source_type' => 'ai',
                'status' => 'active',
                'content' => 'A simple workflow turned scattered content into a predictable weekly pipeline. Here is the before, the change, and the result.',
                'notes' => 'Use when promoting results, proof, or operational improvements.',
                'tags' => ['case-study', 'trust', 'b2b'],
            ],
            [
                'name' => 'Demo seasonal campaign draft',
                'source_type' => 'manual',
                'status' => 'draft',
                'content' => 'Seasonal demand is rising. Refresh your message, tighten your CTA, and align the post with the offer before scheduling.',
                'notes' => 'Draft kept for seasonal promo revisions.',
                'tags' => ['seasonal', 'promo'],
            ],
            [
                'name' => 'Demo archived evergreen block',
                'source_type' => 'ai',
                'status' => 'archived',
                'content' => 'Consistency wins when your content bank stays reusable, searchable, and ready for the next campaign sprint.',
                'notes' => 'Archived sample so the workspace is not limited to active content only.',
                'tags' => ['evergreen', 'library'],
            ],
        ];

        foreach ($items as $index => $item) {
            CaptionLibraryItem::query()->create([
                'owner_user_id' => $user->id,
                'team_id' => $team->id,
                'name' => $item['name'],
                'slug' => 'demo-caption-'.($index + 1),
                'source_type' => $item['source_type'],
                'status' => $item['status'],
                'content' => $item['content'],
                'notes' => $item['notes'],
                'tags' => $item['tags'],
                'metadata' => [
                    'source' => self::DEMO_MARKER,
                ],
                'created_at' => now()->subDays(4 - $index),
                'updated_at' => now()->subHours(($index + 1) * 5),
            ]);
        }

        $counts['captions'] += count($items);
    }

    protected function createSupportInbox(User $user, Team $team, array &$counts): void
    {
        $now = time();

        $category = SupportCategory::query()->firstOrCreate(
            ['name' => '[DEMO] Workspace Help'],
            [
                'id_secure' => Str::random(32),
                'icon' => 'fa-light fa-life-ring',
                'color' => '#2563eb',
                'status' => true,
                'changed' => $now,
                'created' => $now,
            ],
        );

        $type = SupportType::query()->firstOrCreate(
            ['name' => '[DEMO] Product Guidance'],
            [
                'id_secure' => Str::random(32),
                'icon' => 'fa-light fa-compass-drafting',
                'color' => '#7c3aed',
                'status' => true,
                'changed' => $now,
                'created' => $now,
            ],
        );

        $label = SupportLabel::query()->firstOrCreate(
            ['name' => '[DEMO] Priority'],
            [
                'id_secure' => Str::random(32),
                'icon' => 'fa-light fa-bolt',
                'color' => '#f59e0b',
                'status' => true,
                'changed' => $now,
                'created' => $now,
            ],
        );

        $tickets = [
            [
                'title' => '[DEMO] Need help aligning the publishing queue',
                'content' => 'I want the demo workspace to show a realistic queue with published and scheduled content. Which modules should I prepare first?',
                'status' => 1,
                'user_read' => true,
                'admin_read' => false,
                'offset' => 7200,
                'comments' => [
                    ['user_id' => $user->id, 'comment' => '<p>I mainly need the buyer-facing pages to look active and coherent.</p>', 'offset' => 5400],
                ],
            ],
            [
                'title' => '[DEMO] RSS automation preview looks empty',
                'content' => 'The RSS page is visible but it does not show enough activity for a sales demo. I need a better baseline state.',
                'status' => 2,
                'user_read' => false,
                'admin_read' => true,
                'offset' => 14400,
                'comments' => [
                    ['user_id' => $user->id, 'comment' => '<p>I would like a couple of queued items and one published outcome.</p>', 'offset' => 12600],
                ],
            ],
        ];

        foreach ($tickets as $index => $item) {
            $ticket = SupportTicket::query()->create([
                'id_secure' => Str::random(32),
                'uid' => $user->id,
                'open_by' => $user->id,
                'team_id' => $team->id,
                'cate_id' => $category->id,
                'type_id' => $type->id,
                'title' => $item['title'],
                'content' => $item['content'],
                'status' => $item['status'],
                'pin' => $index === 0,
                'user_read' => $item['user_read'],
                'admin_read' => $item['admin_read'],
                'changed' => $now - 1800,
                'created' => $now - $item['offset'],
            ]);

            DB::table('support_map_labels')->insert([
                'ticket_id' => $ticket->id,
                'label_id' => $label->id,
            ]);

            foreach ($item['comments'] as $comment) {
                SupportComment::query()->create([
                    'id_secure' => Str::random(32),
                    'ticket_id' => $ticket->id,
                    'user_id' => $comment['user_id'],
                    'comment' => $comment['comment'],
                    'changed' => $now - 1200,
                    'created' => $now - $comment['offset'],
                ]);

                $counts['support_comments']++;
            }

            $counts['support_tickets']++;
        }
    }

    protected function createAffiliateSnapshot(User $user, array &$counts): void
    {
        $affiliate = app(AffiliateService::class);
        $affiliate->ensureReferralCode($user);

        $profile = $affiliate->ensureProfile($user);

        $payments = [
            ['transaction_id' => 'DEMO-AFF-001-'.$user->id, 'amount' => 320.00, 'commission_rate' => 15.00, 'commission' => 48.00, 'status' => AffiliateCommission::STATUS_APPROVED, 'created_offset' => 172800],
            ['transaction_id' => 'DEMO-AFF-002-'.$user->id, 'amount' => 220.00, 'commission_rate' => 15.00, 'commission' => 33.00, 'status' => AffiliateCommission::STATUS_PENDING, 'created_offset' => 86400],
            ['transaction_id' => 'DEMO-AFF-003-'.$user->id, 'amount' => 180.00, 'commission_rate' => 15.00, 'commission' => 27.00, 'status' => AffiliateCommission::STATUS_APPROVED, 'created_offset' => 43200],
        ];

        foreach ($payments as $index => $item) {
            $payment = PaymentHistory::query()->create([
                'id_secure' => Str::random(32),
                'uid' => $user->id,
                'plan_id' => $user->plan_id,
                'from' => 'manual',
                'transaction_id' => $item['transaction_id'],
                'currency' => 'USD',
                'by' => 'admin-faker',
                'amount' => $item['amount'],
                'status' => 1,
                'changed' => time() - $item['created_offset'] + 1200,
                'created' => time() - $item['created_offset'],
                'meta' => [
                    'source' => self::DEMO_MARKER,
                ],
            ]);

            AffiliateCommission::query()->create([
                'id_secure' => Str::random(32),
                'affiliate_user_id' => $user->id,
                'referred_user_id' => $user->id,
                'payment_history_id' => $payment->id,
                'amount' => $item['amount'],
                'commission_rate' => $item['commission_rate'],
                'commission' => $item['commission'],
                'status' => $item['status'],
                'meta' => [
                    'source' => self::DEMO_MARKER,
                    'sample' => $index + 1,
                ],
                'approved_at' => $item['status'] === AffiliateCommission::STATUS_APPROVED ? now()->subHours($index + 5) : null,
                'created_at' => now()->subSeconds($item['created_offset']),
                'updated_at' => now()->subSeconds(max(0, $item['created_offset'] - 1800)),
            ]);

            $counts['affiliate_commissions']++;
        }

        $withdrawals = [
            ['amount' => 18.00, 'payment_method' => 'PayPal', 'payment_details' => 'demo-payout@preview.test', 'status' => AffiliateWithdrawal::STATUS_APPROVED, 'offset' => 28800],
            ['amount' => 25.00, 'payment_method' => 'Bank transfer', 'payment_details' => 'Demo payout review in progress', 'status' => AffiliateWithdrawal::STATUS_PENDING, 'offset' => 10800],
        ];

        foreach ($withdrawals as $withdrawal) {
            AffiliateWithdrawal::query()->create([
                'id_secure' => Str::random(32),
                'affiliate_user_id' => $user->id,
                'amount' => $withdrawal['amount'],
                'payment_method' => $withdrawal['payment_method'],
                'payment_details' => $withdrawal['payment_details'],
                'notes' => 'Generated by Admin Faker',
                'status' => $withdrawal['status'],
                'processed_at' => $withdrawal['status'] === AffiliateWithdrawal::STATUS_APPROVED ? now()->subHours(4) : null,
                'created_at' => now()->subSeconds($withdrawal['offset']),
                'updated_at' => now()->subSeconds(max(0, $withdrawal['offset'] - 1200)),
            ]);

            $counts['affiliate_withdrawals']++;
        }

        $profile->forceFill([
            'clicks' => (int) $profile->clicks + 184,
            'conversions' => (int) $profile->conversions + count($payments),
            'total_approved' => (float) $profile->total_approved + 75.00,
            'total_withdrawal' => (float) $profile->total_withdrawal + 18.00,
            'total_balance' => (float) $profile->total_balance + 32.00,
        ])->save();
    }

    protected function createDailyPublishingPosts(User $user, Team $team, $accounts, array $imageFiles, CarbonInterface $now, array &$counts): void
    {
        $candidateAccounts = $this->preferredDemoPostingAccounts($accounts);
        $timeSlots = [[8, 30], [9, 0], [10, 0], [11, 30], [13, 15], [14, 15], [15, 15], [16, 15]];
        $titleSeeds = [
            'published' => ['published overview', 'published update', 'published local highlight'],
            'queued' => ['queued campaign', 'queued promo', 'queued product teaser'],
            'draft' => ['draft text idea', 'draft local note', 'draft quick update'],
            'processing' => ['processing creative', 'processing caption polish'],
        ];

        foreach (range(-5, 5) as $dayOffset) {
            $postsForDay = random_int(1, 3);

            for ($slotIndex = 0; $slotIndex < $postsForDay; $slotIndex++) {
                $account = $candidateAccounts->random();
                $networkLabel = $this->demoNetworkLabel((string) $account->provider_key);
                [$statusKey, $status] = $this->demoStatusForDayOffset($dayOffset);
                [$hour, $minute] = $timeSlots[array_rand($timeSlots)];
                $timePost = $now->copy()->addDays($dayOffset)->setTime($hour, $minute + ($slotIndex * 5));
                $isTextOnly = random_int(1, 100) <= 25;
                $image = $isTextOnly ? null : $this->randomDemoImage($imageFiles);
                $titleSuffix = $titleSeeds[$statusKey][array_rand($titleSeeds[$statusKey])];
                $title = $networkLabel.' '.$titleSuffix;
                $caption = $this->demoCaptionForStatus($networkLabel, $statusKey, $isTextOnly);
                $result = null;
                $changedAt = $status === PublishingPost::STATUS_PROCESSING
                    ? $timePost->copy()->subMinutes(3)
                    : $timePost->copy()->addMinutes(8);

                if ($status === PublishingPost::STATUS_PUBLISHED) {
                    $result = [
                        'state' => 'published',
                        'url' => $this->demoPublishedPostUrl((string) $account->provider_key, Str::slug($title).'-'.$account->id),
                        'remote_post_id' => 'demo-'.Str::slug((string) $account->provider_key).'-'.$account->id.'-'.$timePost->timestamp,
                    ];
                }

                $this->createDemoPublishingPost(
                    user: $user,
                    team: $team,
                    account: $account,
                    image: $image,
                    title: $title,
                    caption: $caption,
                    status: $status,
                    timePost: $timePost,
                    changedAt: $changedAt,
                    result: $result,
                    module: 'publishing',
                    customData1: 'daily-preview',
                    customData3: 'manual-preview',
                );

                $counts['daily_schedules']++;
                $this->incrementPublishingStatusCount($counts, $status);
            }
        }

        RssSchedule::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => 'Daily RSS Preview Schedule',
            'feed_url' => 'https://example.com/feed.xml',
            'description' => 'Sample RSS automation with a ready queue.',
            'account_ids' => $accounts->pluck('id')->take(2)->values()->all(),
            'settings' => [
                'source' => self::DEMO_MARKER,
                'post_type' => 'caption_image',
                'import_images' => true,
            ],
            'time_posts' => ['09:00', '15:00'],
            'weekdays' => ['Mon' => true, 'Tue' => true, 'Wed' => true, 'Thu' => true, 'Fri' => true, 'Sat' => false, 'Sun' => false],
            'start_at' => $now->copy()->startOfDay()->timestamp,
            'last_checked_at' => $now->copy()->subHour()->timestamp,
            'last_queued_at' => $now->copy()->subMinutes(30)->timestamp,
            'next_run_at' => $now->copy()->addHour()->timestamp,
            'status' => true,
            'changed' => $now->timestamp,
            'created' => $now->timestamp,
        ]);

        RssSchedule::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => 'Empty Starter Schedule',
            'feed_url' => 'https://example.com/starter-feed.xml',
            'description' => 'Blank schedule state for product preview.',
            'account_ids' => [$accounts->first()->id],
            'settings' => [
                'source' => self::DEMO_MARKER,
                'post_type' => 'caption_only',
                'empty_state' => true,
            ],
            'time_posts' => ['10:30'],
            'weekdays' => ['Mon' => false, 'Tue' => false, 'Wed' => false, 'Thu' => false, 'Fri' => false, 'Sat' => true, 'Sun' => false],
            'start_at' => $now->copy()->startOfDay()->timestamp,
            'last_checked_at' => null,
            'last_queued_at' => null,
            'next_run_at' => $now->copy()->addDay()->timestamp,
            'status' => true,
            'changed' => $now->timestamp,
            'created' => $now->timestamp,
        ]);

        $counts['rss_schedules'] += 2;
    }

    protected function createFaqs(array &$counts): void
    {
        foreach ([
            [
                'slug' => 'demo-preview-how-does-daily-scheduling-work',
                'title' => 'How does daily scheduling work?',
                'content' => '<p>The preview workspace includes sample posts spread across multiple days so the buyer can inspect the calendar flow immediately.</p>',
            ],
            [
                'slug' => 'demo-preview-can-i-connect-real-channels-later',
                'title' => 'Can I connect real channels later?',
                'content' => '<p>Yes. Demo channels are isolated placeholders. Real channels can be connected after purchase without keeping the preview data.</p>',
            ],
            [
                'slug' => 'demo-preview-what-is-ai-publishing',
                'title' => 'What is AI publishing?',
                'content' => '<p>AI publishing creates draft or scheduled posts from stored prompts and pushes them into the publishing workspace automatically.</p>',
            ],
            [
                'slug' => 'demo-preview-can-i-review-published-and-failed-posts',
                'title' => 'Can I review published and failed posts?',
                'content' => '<p>Yes. The preview account includes published, queued, failed, draft, and processing posts so the buyer can inspect each state.</p>',
            ],
            [
                'slug' => 'demo-preview-does-rss-work-with-manual-editing',
                'title' => 'Does RSS work with manual editing?',
                'content' => '<p>Yes. RSS schedules can feed content into publishing while your team still edits captions, media, or approval flow before posting.</p>',
            ],
        ] as $faq) {
            Faq::query()->create([
                'id_secure' => Str::random(32),
                'title' => $faq['title'],
                'title_translations' => ['en' => $faq['title'], 'vi' => $faq['title']],
                'slug' => $faq['slug'],
                'content' => $faq['content'],
                'content_translations' => ['en' => $faq['content'], 'vi' => $faq['content']],
                'status' => 1,
                'changed' => time(),
                'created' => time(),
            ]);

            $counts['faqs']++;
        }
    }

    protected function createBlogTaxonomy(): array
    {
        $category = BlogCategory::query()->create([
            'id_secure' => Str::random(32),
            'name' => 'Demo Preview Guides',
            'name_translations' => ['en' => 'Demo Preview Guides', 'vi' => 'Demo Preview Guides'],
            'description' => 'Preview-only editorial content.',
            'description_translations' => ['en' => 'Preview-only editorial content.', 'vi' => 'Preview-only editorial content.'],
            'slug' => 'demo-preview-guides',
            'icon' => 'fa-light fa-sparkles',
            'color' => '#0f766e',
            'status' => 1,
            'sort_order' => 10,
            'changed' => time(),
            'created' => time(),
        ]);

        $tags = collect([
            ['slug' => 'demo-preview-rss', 'name' => 'Demo RSS'],
            ['slug' => 'demo-preview-ai-publishing', 'name' => 'Demo AI Publishing'],
            ['slug' => 'demo-preview-onboarding', 'name' => 'Demo Onboarding'],
        ])->map(function (array $tag) {
            return BlogTag::query()->create([
                'id_secure' => Str::random(32),
                'name' => $tag['name'],
                'name_translations' => ['en' => $tag['name'], 'vi' => $tag['name']],
                'description' => 'Generated by Admin Faker.',
                'description_translations' => ['en' => 'Generated by Admin Faker.', 'vi' => 'Generated by Admin Faker.'],
                'slug' => $tag['slug'],
                'color' => '#2563eb',
                'status' => 1,
                'changed' => time(),
                'created' => time(),
            ]);
        });

        return [$category, $tags];
    }

    protected function createBlogs(User $user, BlogCategory $category, $tags, array $imageFiles, array &$counts)
    {
        $now = time();

        $blogs = collect([
            [
                'slug' => 'demo-preview-getting-started-with-sample-schedules',
                'title' => 'Getting Started With Sample Schedules',
                'excerpt' => 'See how a buyer can inspect a week of ready-made scheduled posts in minutes.',
            ],
            [
                'slug' => 'demo-preview-how-rss-and-blog-automation-fit-together',
                'title' => 'How RSS And Blog Automation Fit Together',
                'excerpt' => 'A simple walkthrough of RSS sources, imports, and publishing outputs inside the preview workspace.',
            ],
            [
                'slug' => 'demo-preview-ai-publishing-for-pre-sales-demos',
                'title' => 'AI Publishing For Pre-Sales Demos',
                'excerpt' => 'Use realistic AI publishing prompts and logs to make the product evaluation concrete.',
            ],
            [
                'slug' => 'demo-preview-how-to-show-a-realistic-content-calendar',
                'title' => 'How To Show A Realistic Content Calendar',
                'excerpt' => 'A preview environment should show more than empty slots. Published, queued, and failed posts give buyers better confidence.',
            ],
            [
                'slug' => 'demo-preview-using-demo-channels-with-image-posts',
                'title' => 'Using Demo Channels With Image Posts',
                'excerpt' => 'Fake channels and image assets help buyers understand workflow without connecting production accounts.',
            ],
            [
                'slug' => 'demo-preview-why-draft-posts-matter-in-a-trial',
                'title' => 'Why Draft Posts Matter In A Trial',
                'excerpt' => 'Draft content demonstrates that the product supports editing, staging, and approvals before publishing.',
            ],
            [
                'slug' => 'demo-preview-what-buyers-look-for-in-rss-automation',
                'title' => 'What Buyers Look For In RSS Automation',
                'excerpt' => 'Prospects usually want to see import history, queued posts, and editable content coming from RSS sources.',
            ],
            [
                'slug' => 'demo-preview-ai-logs-that-build-trust',
                'title' => 'AI Logs That Build Trust',
                'excerpt' => 'Visible AI usage logs help buyers understand cost, model choice, and operational transparency.',
            ],
            [
                'slug' => 'demo-preview-how-to-present-multi-state-post-queues',
                'title' => 'How To Present Multi-State Post Queues',
                'excerpt' => 'A strong demo should include posts across draft, scheduled, processing, published, and failed states.',
            ],
            [
                'slug' => 'demo-preview-faq-and-blog-content-for-onboarding',
                'title' => 'FAQ And Blog Content For Onboarding',
                'excerpt' => 'Seeded FAQs and blogs reduce empty-state friction and make the product feel immediately usable.',
            ],
        ])->values()->map(function (array $blog, int $index) use ($category, $tags, $imageFiles, $now, &$counts) {
            $image = $this->randomDemoImage($imageFiles);
            $content = '<p>'.$blog['excerpt'].'</p><p>This article is generated as demo content so a buyer can review blog, RSS, and publishing modules before upgrading.</p>';

            $entry = Blog::query()->create([
                'id_secure' => Str::random(32),
                'blog_category_id' => $category->id,
                'title' => $blog['title'],
                'title_translations' => ['en' => $blog['title'], 'vi' => $blog['title']],
                'excerpt' => $blog['excerpt'],
                'excerpt_translations' => ['en' => $blog['excerpt'], 'vi' => $blog['excerpt']],
                'content' => $content,
                'content_translations' => ['en' => $content, 'vi' => $content],
                'slug' => $blog['slug'],
                'meta_title' => $blog['title'],
                'meta_description' => $blog['excerpt'],
                'canonical_url' => null,
                'og_image' => $this->demoImageUrl($image),
                'thumbnail' => $this->demoImageUrl($image),
                'status' => 1,
                'published_at' => $now - ($index * 86400),
                'changed' => $now,
                'created' => $now,
            ]);

            $entry->tags()->syncWithoutDetaching($tags->pluck('id')->take(2)->all());
            $counts['blogs']++;

            return $entry;
        });

        return $blogs;
    }

    protected function createBlogRssSources(BlogCategory $category, $tags, $blogs, array &$counts): void
    {
        $source = BlogRssSource::query()->create([
            'id_secure' => Str::random(32),
            'name' => '[DEMO] Marketing Feed',
            'feed_url' => 'https://example.com/blog-feed.xml',
            'blog_category_id' => $category->id,
            'tag_ids' => $tags->pluck('id')->values()->all(),
            'status' => 1,
            'auto_publish' => 1,
            'ai_improve' => 1,
            'ai_auto_translate' => 0,
            'ai_prompt' => 'Keep the tone practical and product-led.',
            'sync_interval_minutes' => 60,
            'max_items_per_run' => 5,
            'last_checked_at' => time() - 3600,
            'last_imported_at' => time() - 1800,
            'changed' => time(),
            'created' => time(),
        ]);

        foreach ($blogs->take(2) as $index => $blog) {
            BlogRssImport::query()->create([
                'blog_rss_source_id' => $source->id,
                'blog_id' => $blog->id,
                'external_guid' => 'demo-guid-'.($index + 1),
                'external_url' => 'https://example.com/articles/demo-'.($index + 1),
                'content_hash' => sha1('demo-import-'.$index),
                'title' => $blog->title,
                'source_published_at' => time() - (($index + 1) * 7200),
                'changed' => time(),
                'created' => time(),
            ]);
        }
    }

    protected function createAiPublishing(User $user, Team $team, $accounts, array $imageFiles, CarbonInterface $now, array &$counts): array
    {
        $prompts = collect([
            ['title' => 'Launch teaser', 'text' => 'Write a short launch teaser with one strong CTA and one hashtag block.'],
            ['title' => 'Trust post', 'text' => 'Create a trust-building post that highlights scheduling, RSS, and AI publishing in one narrative.'],
        ])->map(function (array $prompt) use ($user, &$counts) {
            $model = AiPublishingPrompt::query()->create([
                'owner_user_id' => $user->id,
                'title' => $prompt['title'],
                'prompt_text' => $prompt['text'],
                'metadata' => [
                    'source' => self::DEMO_MARKER,
                ],
                'is_active' => true,
            ]);

            $counts['ai_prompts']++;

            return $model;
        });

        $runs = collect([
            [
                'name' => 'Demo AI Daily Publisher',
                'status' => 'completed',
                'stats' => ['generated_items' => 4, 'created_posts' => 3],
            ],
            [
                'name' => 'Demo AI Queue Builder',
                'status' => 'queued',
                'stats' => ['generated_items' => 0, 'created_posts' => 0],
            ],
        ])->values()->map(function (array $definition, int $index) use ($user, $team, $accounts, $prompts, $imageFiles, $now, &$counts) {
            $run = AiPublishingRun::query()->create([
                'owner_user_id' => $user->id,
                'team_id' => $team->id,
                'workspace_owner_user_id' => $user->id,
                'name' => $definition['name'],
                'campaign_id' => null,
                'label_ids' => [],
                'account_ids' => $accounts->pluck('id')->take(2)->values()->all(),
                'prompt_ids' => $prompts->pluck('id')->values()->all(),
                'schedule_config' => [
                    'timezone' => 'Asia/Saigon',
                    'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'times' => ['08:30', '14:00'],
                ],
                'generation_config' => [
                    'source' => self::DEMO_MARKER,
                    'image_mode' => count($imageFiles) > 0 ? 'uploaded_only' : 'disabled',
                    'voice' => 'brand',
                ],
                'stats' => $definition['stats'],
                'status' => $definition['status'],
                'last_processed_at' => $definition['status'] === 'completed' ? $now->copy()->subMinutes(20) : null,
            ]);

            if ($index === 0) {
                $candidateAccounts = $this->preferredDemoPostingAccounts($accounts);
                $aiDefinitions = [
                    [
                        'status' => PublishingPost::STATUS_PUBLISHED,
                        'time' => $now->copy()->subDays(2)->setTime(13, 15),
                    ],
                    [
                        'status' => PublishingPost::STATUS_SCHEDULED,
                        'time' => $now->copy()->addDays(2)->setTime(11, 30),
                    ],
                ];

                foreach ($aiDefinitions as $definition) {
                    $account = $candidateAccounts->random();
                    $networkLabel = $this->demoNetworkLabel((string) $account->provider_key);
                    $image = random_int(1, 100) <= 35 ? null : $this->randomDemoImage($imageFiles);
                    $result = null;

                    if ($definition['status'] === PublishingPost::STATUS_PUBLISHED) {
                        $result = [
                            'state' => 'published',
                            'url' => $this->demoPublishedPostUrl((string) $account->provider_key, 'ai-'.Str::slug($networkLabel).'-recap'),
                            'remote_post_id' => 'ai-demo-'.$account->provider_key.'-'.$account->id,
                        ];
                    }

                    $this->createDemoPublishingPost(
                        user: $user,
                        team: $team,
                        account: $account,
                        image: $image,
                        title: 'AI '.$networkLabel.' '.($definition['status'] === PublishingPost::STATUS_PUBLISHED ? 'published recap' : 'queued idea'),
                        caption: $definition['status'] === PublishingPost::STATUS_PUBLISHED
                            ? 'AI-generated '.$networkLabel.' post already published to show a completed automation result.'
                            : 'AI-generated '.$networkLabel.' preview copy queued for automatic publishing.',
                        status: $definition['status'],
                        timePost: $definition['time'],
                        changedAt: $definition['status'] === PublishingPost::STATUS_PUBLISHED ? $definition['time']->copy()->addMinutes(3) : $now,
                        result: $result,
                        module: 'ai_publishing',
                        customData1: (string) $run->id,
                        customData3: 'ai-publishing',
                    );

                    $counts['daily_schedules']++;
                    $this->incrementPublishingStatusCount($counts, (int) $definition['status']);
                }
            }

            $counts['ai_publishing_runs']++;

            return $run;
        });

        return [$prompts, $runs];
    }

    protected function createAiLogs(User $user, $runs, array &$counts): void
    {
        $rows = [
            ['provider' => 'openai', 'capability' => 'chat', 'model' => 'gpt-5.4', 'feature' => 'ai-publishing', 'tokens' => [950, 420, 1370], 'cost' => 0.024100, 'latency' => 1420],
            ['provider' => 'openai', 'capability' => 'image', 'model' => 'gpt-image-1', 'feature' => 'ai-publishing-image', 'tokens' => [0, 0, 0], 'cost' => 0.018000, 'latency' => 2140],
            ['provider' => 'openai', 'capability' => 'chat', 'model' => 'gpt-5.4-mini', 'feature' => 'faq-draft', 'tokens' => [320, 210, 530], 'cost' => 0.004200, 'latency' => 680],
            ['provider' => 'openai', 'capability' => 'chat', 'model' => 'gpt-5.4', 'feature' => 'rss-rewrite', 'tokens' => [1200, 560, 1760], 'cost' => 0.031500, 'latency' => 1640],
        ];

        foreach ($rows as $index => $row) {
            AiUsageLog::query()->create([
                'user_id' => $user->id,
                'provider' => $row['provider'],
                'capability' => $row['capability'],
                'model' => $row['model'],
                'status' => 'success',
                'feature' => $row['feature'],
                'route_name' => 'admin-faker.index',
                'prompt_tokens' => $row['tokens'][0],
                'completion_tokens' => $row['tokens'][1],
                'total_tokens' => $row['tokens'][2],
                'estimated_cost' => $row['cost'],
                'latency_ms' => $row['latency'],
                'metadata' => [
                    'source' => self::DEMO_MARKER,
                    'demo_run_id' => $runs->first()?->id,
                    'sample_index' => $index + 1,
                ],
                'created_at' => now()->subMinutes(($index + 1) * 12),
                'updated_at' => now()->subMinutes(($index + 1) * 12),
            ]);

            $counts['ai_logs']++;
        }
    }

    protected function createGlobalNotifications(User $user, array &$counts): void
    {
        $rows = [
            [
                'title' => '[DEMO] New Calendar Activity',
                'message' => 'Fresh sample posts were added so the calendar shows realistic activity across the next few days.',
                'url' => route('portal.publishing.calendar'),
                'type' => 'news',
            ],
            [
                'title' => '[DEMO] Channels Ready For Review',
                'message' => 'Demo social accounts are available with avatars and realistic names so buyers can inspect the workspace faster.',
                'url' => route('portal.channels'),
                'type' => 'news',
            ],
            [
                'title' => '[DEMO] AI Publishing Samples Added',
                'message' => 'A small set of AI-generated posts was added to show how automation feeds into the publishing queue.',
                'url' => route('portal.ai-publishing'),
                'type' => 'info',
            ],
            [
                'title' => '[DEMO] Local SEO Preview Updated',
                'message' => 'Google Business and other demo channels were refreshed to make the trial workspace feel more complete.',
                'url' => route('portal.channels'),
                'type' => 'tip',
            ],
            [
                'title' => '[DEMO] Workspace Review Tip',
                'message' => 'Open the queue, drafts, and calendar together to show buyers how content moves from draft to published state.',
                'url' => route('portal.publishing.calendar'),
                'type' => 'tip',
            ],
        ];

        foreach ($rows as $index => $row) {
            NotificationManual::query()->create([
                'id_secure' => Str::random(32),
                'title' => $row['title'],
                'message' => $row['message'],
                'url' => $row['url'],
                'type' => $row['type'],
                'created_by' => $user->id,
                'is_global' => true,
                'created_at' => now()->subMinutes(($index + 1) * 9),
                'updated_at' => now()->subMinutes(($index + 1) * 9),
            ]);

            $counts['global_notifications']++;
        }
    }

    protected function mediaItem(AppFile $file, User $user): array
    {
        $previewUrl = $this->demoImageUrl($file);

        return [
            'id' => $file->id,
            'idSecure' => $file->id_secure,
            'name' => $file->name,
            'url' => $previewUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'thumbnail' => $previewUrl,
            'size' => $file->humanSize(),
            'category' => $file->category,
            'mimeType' => $file->mime_type,
            'extension' => $file->extension,
            'isImage' => (bool) $file->is_image,
        ];
    }

    protected function randomDemoImage(array $imageFiles): ?AppFile
    {
        if ($imageFiles === []) {
            return null;
        }

        return $imageFiles[array_rand($imageFiles)];
    }

    protected function preferredDemoPostingAccounts($accounts)
    {
        $preferredProviders = ['facebook', 'instagram', 'google_business_profile', 'x', 'reddit'];
        $preferred = $accounts->filter(fn (SocialAccount $account): bool => in_array((string) $account->provider_key, $preferredProviders, true))->values();

        return $preferred->isNotEmpty() ? $preferred : $accounts->values();
    }

    protected function demoStatusForDayOffset(int $dayOffset): array
    {
        if ($dayOffset <= -2) {
            return ['published', PublishingPost::STATUS_PUBLISHED];
        }

        if ($dayOffset === -1) {
            return random_int(1, 100) <= 80
                ? ['published', PublishingPost::STATUS_PUBLISHED]
                : ['processing', PublishingPost::STATUS_PROCESSING];
        }

        if ($dayOffset === 0) {
            $roll = random_int(1, 100);

            if ($roll <= 45) {
                return ['published', PublishingPost::STATUS_PUBLISHED];
            }

            if ($roll <= 80) {
                return ['queued', PublishingPost::STATUS_SCHEDULED];
            }

            return ['processing', PublishingPost::STATUS_PROCESSING];
        }

        $roll = random_int(1, 100);

        if ($roll <= 75) {
            return ['queued', PublishingPost::STATUS_SCHEDULED];
        }

        return ['draft', PublishingPost::STATUS_DRAFT];
    }

    protected function demoCaptionForStatus(string $networkLabel, string $statusKey, bool $isTextOnly): string
    {
        $format = $isTextOnly ? 'text-only' : 'image';

        return match ($statusKey) {
            'published' => 'A published '.$networkLabel.' '.$format.' sample post so the calendar feels active instead of empty.',
            'queued' => 'Queued '.$networkLabel.' '.$format.' content prepared for an upcoming slot in the next few days.',
            'draft' => 'Draft '.$networkLabel.' '.$format.' content waiting for a final edit before it moves into the queue.',
            'processing' => 'Processing '.$networkLabel.' '.$format.' content so the preview also shows work that is still in progress.',
            default => 'Demo '.$networkLabel.' '.$format.' post for preview.',
        };
    }

    protected function storeChannelAvatarFromFile(AppFile $file, User $user, string $providerKey, string $externalId): ?array
    {
        if (! filled($file->path)) {
            return null;
        }

        $url = $this->demoImageUrl($file);

        if (! $url) {
            return null;
        }

        return [
            'disk' => null,
            'path' => null,
            'url' => $url,
        ];
    }

    protected function demoImageUrl(?AppFile $file): ?string
    {
        if (! $file || ! filled($file->path)) {
            return null;
        }

        $sourceDisk = (string) ($file->disk ?: 'public');
        $sourcePath = trim((string) $file->path);

        if ($sourcePath === '' || ! Storage::disk($sourceDisk)->exists($sourcePath)) {
            return null;
        }

        return route('portal.files.preview', $file);
    }

    protected function isUsableImageFile(AppFile $file): bool
    {
        $disk = (string) ($file->disk ?: 'public');
        $path = trim((string) $file->path);
        $extension = strtolower((string) ($file->extension ?: pathinfo($path, PATHINFO_EXTENSION)));
        $mimeType = strtolower(trim((string) ($file->mime_type ?: '')));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return false;
        }

        if ($mimeType !== '' && ! in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return false;
        }

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return false;
        }

        try {
            $size = (int) Storage::disk($disk)->size($path);

            if ($size < 1024 || $size > (20 * 1024 * 1024)) {
                return false;
            }

            $content = Storage::disk($disk)->get($path);

            if ($content === '') {
                return false;
            }

            $dimensions = @getimagesizefromstring($content);

            if ($dimensions === false) {
                return false;
            }

            return (int) ($dimensions[0] ?? 0) >= 200 && (int) ($dimensions[1] ?? 0) >= 200;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function createDemoPublishingPost(
        User $user,
        Team $team,
        SocialAccount $account,
        ?AppFile $image,
        string $title,
        string $caption,
        int $status,
        CarbonInterface $timePost,
        CarbonInterface $changedAt,
        ?array $result,
        string $module,
        string $customData1,
        string $customData3,
    ): void {
        $type = $image ? 'media' : 'text';
        $data = [
            'title' => $title,
            'caption' => $caption,
            'media_type' => $image ? 'image' : 'text',
            'medias' => $image ? [$this->mediaItem($image, $user)] : [],
            'options' => [
                'schedule_mode' => 'specific_days_times',
                'repeat_rule' => 'none',
            ],
            'ai' => [
                'tags' => ['demo', 'preview', $module],
            ],
        ];

        PublishingPost::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => $user->id,
            'team_id' => $team->id,
            'account_id' => $account->id,
            'social_network' => $account->provider_key,
            'category' => 'social_post',
            'module' => $module,
            'function' => 'post',
            'api_type' => 1,
            'type' => $type,
            'method' => 'basic',
            'data' => $data,
            'time_post' => $timePost->timestamp,
            'delay' => 0,
            'repost_frequency' => 0,
            'result' => $result,
            'custom_data_1' => $customData1,
            'custom_data_2' => self::DEMO_MARKER,
            'custom_data_3' => $customData3,
            'status' => $status,
            'changed' => $changedAt->timestamp,
            'created' => $timePost->copy()->subMinutes(20)->timestamp,
        ]);
    }

    protected function demoNetworkLabel(string $providerKey): string
    {
        return match ($providerKey) {
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'x' => 'X',
            'linkedin_profile' => 'LinkedIn',
            'linkedin_page' => 'LinkedIn',
            'instagram' => 'Instagram',
            'instagram_unofficial' => 'Instagram',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'threads' => 'Threads',
            'ok' => 'OK',
            'youtube' => 'YouTube',
            'google_business_profile' => 'Google Business',
            'telegram' => 'Telegram',
            'vk' => 'VK',
            'mastodon' => 'Mastodon',
            'discord' => 'Discord',
            default => Str::headline(str_replace('_', ' ', $providerKey)),
        };
    }

    protected function demoPublishedPostUrl(string $providerKey, string $slug): string
    {
        return match ($providerKey) {
            'facebook' => 'https://facebook.com/demo.brand.fb/posts/'.$slug,
            'tiktok' => 'https://www.tiktok.com/@demo.brand.tt/video/'.$slug,
            'x' => 'https://x.com/demo_brand_x/status/'.$slug,
            'linkedin_profile' => 'https://www.linkedin.com/feed/update/'.$slug,
            'linkedin_page' => 'https://www.linkedin.com/feed/update/'.$slug,
            'instagram' => 'https://instagram.com/p/'.$slug,
            'instagram_unofficial' => 'https://instagram.com/p/'.$slug,
            'pinterest' => 'https://www.pinterest.com/pin/'.$slug,
            'reddit' => 'https://www.reddit.com/r/demo/comments/'.$slug,
            'threads' => 'https://www.threads.com/@demo/post/'.$slug,
            'ok' => 'https://ok.ru/group/'.$slug,
            'youtube' => 'https://www.youtube.com/watch?v='.$slug,
            'google_business_profile' => 'https://www.google.com/maps/place/'.$slug,
            'telegram' => 'https://t.me/'.$slug,
            'vk' => 'https://vk.com/'.$slug,
            'mastodon' => 'https://mastodon.social/@demo/'.$slug,
            'discord' => 'https://discord.com/channels/'.$slug,
            default => 'https://example.com/demo/'.$slug,
        };
    }

    protected function demoProviderSlug(string $providerKey): string
    {
        return str_replace('-', '.', Str::slug($providerKey));
    }

    protected function demoProfileUrl(string $providerKey, string $handleBase = 'demo-brand', string $categorySlug = ''): string
    {
        $dotSuffix = $categorySlug !== '' ? '.'.$categorySlug : '';
        $dashSuffix = $categorySlug !== '' ? '-'.$categorySlug : '';
        $underscoreSuffix = $categorySlug !== '' ? '_'.$categorySlug : '';

        return match ($providerKey) {
            'facebook' => 'https://facebook.com/'.$handleBase.$dotSuffix,
            'tiktok' => 'https://www.tiktok.com/@'.$handleBase.$dotSuffix,
            'x' => 'https://x.com/'.$handleBase.$underscoreSuffix,
            'linkedin_profile' => 'https://www.linkedin.com/in/'.$handleBase.$dashSuffix,
            'linkedin_page' => 'https://www.linkedin.com/company/'.$handleBase.$dashSuffix,
            'instagram', 'instagram_unofficial' => 'https://instagram.com/'.$handleBase.$dotSuffix,
            'pinterest' => 'https://www.pinterest.com/'.$handleBase.$dotSuffix,
            'reddit' => 'https://www.reddit.com/user/'.$handleBase.$underscoreSuffix,
            'threads' => 'https://www.threads.net/@'.$handleBase.$dotSuffix,
            'ok' => 'https://ok.ru/group/'.$handleBase.$dashSuffix,
            'youtube' => 'https://www.youtube.com/@'.$handleBase.$dashSuffix,
            'google_business_profile' => 'https://www.google.com/maps/place/'.$handleBase.$dashSuffix,
            'telegram' => 'https://t.me/'.$handleBase.$underscoreSuffix,
            'vk' => 'https://vk.com/'.$handleBase.$underscoreSuffix,
            'mastodon' => 'https://mastodon.social/@'.$handleBase.$dashSuffix,
            'discord' => 'https://discord.gg/'.$handleBase.$dashSuffix,
            default => 'https://example.com/demo/'.$handleBase.$dotSuffix,
        };
    }

    protected function incrementPublishingStatusCount(array &$counts, int $status): void
    {
        match ($status) {
            PublishingPost::STATUS_PUBLISHED => $counts['published_posts']++,
            PublishingPost::STATUS_SCHEDULED => $counts['queued_posts']++,
            PublishingPost::STATUS_DRAFT => $counts['draft_posts']++,
            PublishingPost::STATUS_FAILED => $counts['failed_posts']++,
            PublishingPost::STATUS_PROCESSING => $counts['processing_posts']++,
            default => null,
        };
    }

    protected function uniqueUsername(string $email, string $name): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        $base = $base !== '' ? str_replace('-', '_', $base) : str_replace('-', '_', Str::slug($name, '_'));
        $base = $base !== '' ? $base : 'preview_user';

        $username = $base;
        $counter = 2;

        while (User::query()->where('username', $username)->exists()) {
            $username = $base.'_'.$counter++;
        }

        return $username;
    }
}
