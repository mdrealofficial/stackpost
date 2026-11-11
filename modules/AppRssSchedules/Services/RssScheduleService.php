<?php

namespace Modules\AppRssSchedules\Services;

use App\Support\Http\OutboundUrlPolicy;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppCredits\Models\CreditUsageLog;
use Modules\AppFiles\Support\FileManager;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppRssSchedules\Models\RssSchedule;
use Modules\AppRssSchedules\Models\RssScheduleHistory;

class RssScheduleService
{
    public const AI_REWRITE_CREDIT_ACTION = 'ai_studio_rss_rewrite';

    public function __construct(
        protected FileManager $files,
        protected AiContentStudioService $aiStudio,
    ) {}

    public function validateFeed(string $url): array
    {
        $response = $this->fetchFeed($url);

        if (! $response->successful()) {
            throw new \RuntimeException(__('Could not fetch this feed URL.'));
        }

        $items = $this->parseFeed($response->body());

        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $root || ! in_array($root->getName(), ['rss', 'feed'], true)) {
            throw new \RuntimeException(__('This URL is reachable but does not appear to be a valid RSS or Atom feed.'));
        }

        return [
            'title' => trim((string) ($root->channel->title ?? $root->title ?? '')),
            'description' => trim((string) ($root->channel->description ?? $root->subtitle ?? '')),
            'items' => $items,
        ];
    }

    protected function fetchFeed(string $url): Response
    {
        $policy = app(OutboundUrlPolicy::class);
        $target = $policy->inspect($url);
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $response = Http::withOptions($target->requestOptions())->connectTimeout(10)->timeout(20)
                ->accept('application/rss+xml, application/xml, text/xml, application/atom+xml')->get($target->url);
            if (! $response->redirect()) {
                if (strlen($response->body()) > 5 * 1024 * 1024) {
                    throw new \RuntimeException('The feed is too large.');
                }

                return $response;
            }
            $target = $policy->resolveRedirect($target, (string) $response->header('Location'));
        }
        throw new \RuntimeException('The feed returned too many redirects.');
    }

    public function parseFeed(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $feed instanceof \SimpleXMLElement) {
            return [];
        }

        $feedTitle = trim((string) ($feed->channel->title ?? $feed->title ?? ''));
        $feedDescription = trim((string) ($feed->channel->description ?? $feed->subtitle ?? ''));

        $rssItems = $feed->xpath('/rss/channel/item') ?: [];
        if ($rssItems !== []) {
            return collect($rssItems)
                ->map(fn ($item) => $this->parseRssItem($item, $feedTitle, $feedDescription))
                ->filter()
                ->sortByDesc(fn (array $item) => (int) ($item['published_at'] ?? 0))
                ->values()
                ->all();
        }

        $feed->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $atomEntries = $feed->xpath('/atom:feed/atom:entry') ?: ($feed->xpath('/feed/entry') ?: []);
        if ($atomEntries !== []) {
            return collect($atomEntries)
                ->map(fn ($item) => $this->parseAtomEntry($item, $feedTitle, $feedDescription))
                ->filter()
                ->sortByDesc(fn (array $item) => (int) ($item['published_at'] ?? 0))
                ->values()
                ->all();
        }

        return [];
    }

    public function calculateNextRun(array $timePosts, array $weekdays, string $timezone, ?int $notBefore = null): ?int
    {
        $now = $notBefore
            ? Carbon::createFromTimestamp($notBefore, $timezone)
            : now($timezone);

        $slots = collect($timePosts)
            ->map(fn ($slot) => trim((string) $slot))
            ->filter()
            ->map(function (string $slot): ?string {
                return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $slot) ? $slot : null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($slots->isEmpty()) {
            return null;
        }

        $weekdayMap = RssSchedule::normalizeWeekdays($weekdays);

        for ($i = 0; $i < 14; $i++) {
            $day = $now->copy()->startOfDay()->addDays($i);
            $dayName = $day->format('D');

            if (! ($weekdayMap[$dayName] ?? false)) {
                continue;
            }

            foreach ($slots as $slot) {
                [$hours, $minutes] = array_map('intval', explode(':', $slot));
                $candidate = $day->copy()->setTime($hours, $minutes);

                if ($candidate->greaterThan($now)) {
                    return $candidate->timestamp;
                }
            }
        }

        return null;
    }

    public function processSchedule(RssSchedule $schedule, bool $force = false, bool $ignoreHistory = false): array
    {
        $lock = Cache::lock('rss-schedule:'.$schedule->id, 600);
        if (! $lock->get()) {
            return ['queued' => 0, 'skipped' => 0, 'status' => 'busy', 'message' => __('This schedule is already being processed.')];
        }
        try {
            $schedule->refresh();
            if ($schedule->isDemo()) {
                return ['queued' => 0, 'skipped' => 0, 'status' => 'demo', 'message' => __('This is a sample schedule. Create a schedule with your own feed to begin.')];
            }
            if (! $force && ! $schedule->shouldRunAt(now()->timestamp)) {
                return ['queued' => 0, 'skipped' => 0, 'status' => 'not_due', 'message' => __('Schedule is not due yet.')];
            }
            try {
                $owner = $schedule->owner;
                if ($schedule->end_at && $schedule->end_at < now()->timestamp) {
                    $result = ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('This schedule has expired. Update its end date before running it.')];
                } elseif ($owner?->plan && ! $owner->hasPlanFeature('rss_schedules')) {
                    $result = ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('RSS schedules are not available on the current workspace plan.')];
                } else {
                    $result = $this->processUnlocked($schedule, $force);
                }
            } catch (\Throwable $exception) {
                report($exception);
                $result = ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('The run could not finish. Check the feed and connected channels, then try again. Any items already created remain in Activity.')];
            }
            $settings = (array) $schedule->fresh()->settings;
            $settings['last_run'] = $result + ['at' => now()->timestamp];
            $settings['run_history'] = array_slice(array_merge((array) ($settings['run_history'] ?? []), [$settings['last_run']]), -20);
            $updates = ['settings' => $settings, 'last_checked_at' => now()->timestamp, 'changed' => now()->timestamp];
            if (($result['status'] ?? '') === 'error') {
                $updates['next_run_at'] = now()->addMinutes(15)->timestamp;
            }
            $schedule->update($updates);

            return $result;
        } finally {
            $lock->release();
        }
    }

    protected function processUnlocked(RssSchedule $schedule, bool $force = false): array
    {
        $schedule->loadMissing('owner');

        $owner = $schedule->owner;
        if (! $owner) {
            return ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('Schedule owner not found.')];
        }

        $now = now()->timestamp;

        if (! $force && ! $schedule->shouldRunAt($now)) {
            return ['queued' => 0, 'skipped' => 0, 'message' => __('Schedule is not due yet.')];
        }

        $response = $this->fetchFeed($schedule->feed_url);

        if (! $response->successful()) {
            $schedule->update([
                'last_checked_at' => $now,
                'changed' => $now,
            ]);

            return ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('Could not fetch the RSS feed. Check its URL or try again later.')];
        }

        $items = collect($this->parseFeed($response->body()));
        if ($items->isEmpty()) {
            $previous = libxml_use_internal_errors(true);
            $root = simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if (! $root || ! in_array($root->getName(), ['rss', 'feed'], true)) {
                return ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('The URL did not return a valid RSS or Atom feed.')];
            }
        }
        $settings = is_array($schedule->settings) ? $schedule->settings : [];
        $feedCursor = is_array($settings['feed_cursor'] ?? null) ? $settings['feed_cursor'] : [];
        $feedBacklog = is_array($settings['feed_backlog'] ?? null) ? $settings['feed_backlog'] : [];
        $accounts = SocialAccount::query()
            ->whereIn('id', collect((array) $schedule->account_ids)->map(fn ($id) => (int) $id)->filter()->all())
            ->where('created_by_user_id', $schedule->user_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($accounts->isEmpty()) {
            return ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('No active destination channels. Edit this schedule and choose a connected channel.')];
        }
        if ($this->calculateNextRun((array) $schedule->time_posts, (array) $schedule->weekdays, (string) ($owner->timezone ?: 'UTC')) === null) {
            return ['queued' => 0, 'skipped' => 0, 'status' => 'error', 'message' => __('Choose valid times and weekdays before running this schedule.')];
        }

        $queued = 0;
        $skipped = 0;
        $matchedAnyItem = false;
        $foundNewItem = false;
        $cursorChanged = false;
        $aiRewriteRuntime = $this->buildAiRewriteRuntimeState($schedule, $owner, $settings, $now);
        foreach ($accounts as $account) {
            $historyHashes = RssScheduleHistory::query()
                ->where('rss_schedule_histories.schedule_id', $schedule->id)
                ->where('rss_schedule_histories.account_id', $account->id)
                ->pluck('rss_schedule_histories.content_hash')
                ->all();

            $matchedItems = $items
                ->filter(function (array $item) use ($settings, &$skipped, &$matchedAnyItem): bool {
                    if (! $this->matchesFilters($item, $settings)) {
                        $skipped++;

                        return false;
                    }

                    $matchedAnyItem = true;

                    return true;
                })
                ->values();

            $accountKey = (string) $account->id;
            $backlogEntries = collect((array) ($feedBacklog[$accountKey] ?? []))
                ->map(fn ($entry) => $this->normalizeBacklogEntry($entry))
                ->filter()
                ->values();

            $backlogHashes = $backlogEntries->pluck('hash')->filter()->values()->all();

            $matchedItems->each(function (array $item) use (&$backlogEntries, &$backlogHashes, $historyHashes): void {
                $hash = $this->contentHash($item);

                if (in_array($hash, $historyHashes, true) || in_array($hash, $backlogHashes, true)) {
                    return;
                }

                $backlogEntries->push([
                    'hash' => $hash,
                    'item' => $this->backlogPayload($item),
                ]);
                $backlogHashes[] = $hash;
            });

            $candidateEntries = $this->candidateEntriesForCursor(
                $backlogEntries,
                (string) ($feedCursor[$accountKey] ?? '')
            );

            foreach ($candidateEntries as $entry) {
                $hash = (string) ($entry['hash'] ?? '');
                $item = (array) ($entry['item'] ?? []);

                if ($hash === '' || $item === []) {
                    $skipped++;

                    continue;
                }

                $existsInHistory = in_array($hash, $historyHashes, true);

                if (! $this->matchesFilters($item, $settings)) {
                    $backlogEntries = $backlogEntries->reject(fn ($entry) => (string) ($entry['hash'] ?? '') === $hash)->values();
                    $feedBacklog[$accountKey] = $backlogEntries->all();
                    $cursorChanged = true;
                    $skipped++;
                    continue;
                }

                if ($existsInHistory) {
                    $backlogEntries = $backlogEntries
                        ->reject(fn ($backlogEntry) => (string) ($backlogEntry['hash'] ?? '') === $hash)
                        ->values();
                    $feedBacklog[$accountKey] = $backlogEntries->all();
                    $cursorChanged = true;
                    $skipped++;

                    continue;
                }

                DB::transaction(function () use ($schedule, $owner, $account, $item, $settings, $now, &$aiRewriteRuntime, $hash): void {
                    $post = $this->queuePublishingPost($schedule, $owner, $account, $item, $settings, $now, $aiRewriteRuntime);
                    RssScheduleHistory::query()->create([
                        'schedule_id' => $schedule->id,
                        'account_id' => $account->id,
                        'publishing_post_id' => $post->id,
                        'external_guid' => $item['guid'] ?? null,
                        'external_url' => $item['link'] ?? null,
                        'content_hash' => $hash,
                        'title' => Str::limit((string) ($item['title'] ?? ''), 500, ''),
                        'queued_at' => $now,
                        'created' => $now,
                        'changed' => $now,
                    ]);
                });

                $queued++;
                $foundNewItem = true;
                $feedCursor[$accountKey] = $hash;
                $backlogEntries = $backlogEntries
                    ->reject(fn ($backlogEntry) => (string) ($backlogEntry['hash'] ?? '') === $hash)
                    ->values();
                $feedBacklog[$accountKey] = $backlogEntries->all();
                $cursorChanged = true;
                break;
            }

            if (! array_key_exists($accountKey, $feedBacklog) || $feedBacklog[$accountKey] !== $backlogEntries->all()) {
                $feedBacklog[$accountKey] = $backlogEntries->all();
                $cursorChanged = true;
            }
        }

        $nextRun = $this->calculateNextRun(
            (array) $schedule->time_posts,
            (array) $schedule->weekdays,
            (string) ($owner->timezone ?: config('app.timezone', 'UTC')),
            $now
        );

        $attributes = [
            'last_checked_at' => $now,
            'last_queued_at' => $queued > 0 ? $now : $schedule->last_queued_at,
            'next_run_at' => $nextRun,
            'changed' => $now,
        ];

        if ($cursorChanged) {
            $settings['feed_cursor'] = $feedCursor;
            $settings['feed_backlog'] = $feedBacklog;
            $attributes['settings'] = $settings;
        }

        if ($schedule->end_at && $nextRun && $nextRun > (int) $schedule->end_at) {
            $attributes['status'] = false;
        }

        $schedule->update($attributes);

        if ($matchedAnyItem && ! $foundNewItem) {
            return [
                'status' => 'no_new_items',
                'queued' => 0,
                'skipped' => $skipped,
                'message' => __('No new RSS items found. The latest feed items are already queued or published.'),
                'ai_rewrite' => [
                    'enabled' => (bool) ($aiRewriteRuntime['enabled'] ?? false),
                    'used_rewrites' => (int) ($aiRewriteRuntime['used_rewrites'] ?? 0),
                    'credits_used' => (int) ($aiRewriteRuntime['credits_used_in_run'] ?? 0),
                    'monthly_credits_used' => (int) ($aiRewriteRuntime['monthly_credits_used'] ?? 0),
                ],
            ];
        }

        return [
            'status' => $queued > 0 ? 'success' : 'no_matches',
            'queued' => $queued,
            'skipped' => $skipped,
            'message' => $queued > 0 ? (($settings['delivery_mode'] ?? 'automatic') === 'draft' ? __('Drafts created for review in Publishing.') : __('New items added to the publishing queue.')) : __('No feed items matched your content rules.'),
            'ai_rewrite' => [
                'enabled' => (bool) ($aiRewriteRuntime['enabled'] ?? false),
                'used_rewrites' => (int) ($aiRewriteRuntime['used_rewrites'] ?? 0),
                'credits_used' => (int) ($aiRewriteRuntime['credits_used_in_run'] ?? 0),
                'monthly_credits_used' => (int) ($aiRewriteRuntime['monthly_credits_used'] ?? 0),
            ],
        ];
    }

    protected function candidateEntriesForCursor(Collection $backlogEntries, string $cursorHash): Collection
    {
        $cursorHash = trim($cursorHash);

        if ($cursorHash === '' || $backlogEntries->isEmpty()) {
            return $backlogEntries;
        }

        $cursorIndex = $backlogEntries->search(fn (array $entry) => (string) ($entry['hash'] ?? '') === $cursorHash);

        if ($cursorIndex === false) {
            return $backlogEntries;
        }

        $remainingEntries = $backlogEntries->slice((int) $cursorIndex + 1)->values();

        return $remainingEntries->isNotEmpty() ? $remainingEntries : $backlogEntries;
    }

    protected function normalizeBacklogEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $hash = trim((string) ($entry['hash'] ?? ''));
        $item = is_array($entry['item'] ?? null) ? $entry['item'] : [];

        if ($hash === '' || $item === []) {
            return null;
        }

        return [
            'hash' => $hash,
            'item' => $item,
        ];
    }

    protected function backlogPayload(array $item): array
    {
        return [
            'feed_title' => (string) ($item['feed_title'] ?? ''),
            'feed_description' => (string) ($item['feed_description'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'link' => (string) ($item['link'] ?? ''),
            'guid' => (string) ($item['guid'] ?? ''),
            'summary' => (string) ($item['summary'] ?? ''),
            'content' => (string) ($item['content'] ?? ''),
            'thumbnail' => (string) ($item['thumbnail'] ?? ''),
            'published_at' => $item['published_at'] ?? null,
        ];
    }

    protected function queuePublishingPost(
        RssSchedule $schedule,
        User $owner,
        SocialAccount $account,
        array $item,
        array $settings,
        int $now,
        array &$aiRewriteRuntime
    ): PublishingPost {
        $link = trim((string) ($item['link'] ?? ''));
        $link = $this->appendReferralCode($link, (string) ($settings['referral_code'] ?? ''));

        if ($link !== '' && ! empty($settings['url_shorten']) && function_exists('url_shortening_enabled') && url_shortening_enabled($owner)) {
            $shortened = shorten_url($link);
            if (is_string($shortened) && trim($shortened) !== '') {
                $link = trim($shortened);
            }
        }

        $caption = $this->buildCaption($item, $settings, $link);
        $rewrite = $this->rewriteCaption($schedule, $owner, $caption, $item, $settings, $account, $aiRewriteRuntime);
        $caption = $rewrite['caption'];

        $mediaItems = $this->resolveMediaItems($owner, $item);
        $campaignId = filled($settings['campaign_id'] ?? null) ? (int) $settings['campaign_id'] : null;
        $labelIds = collect((array) ($settings['label_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        return PublishingPost::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => $schedule->user_id,
            'team_id' => $schedule->team_id,
            'campaign' => $campaignId,
            'labels' => $labelIds,
            'account_id' => $account->id,
            'social_network' => (string) $account->provider_key,
            'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
            'module' => (string) $account->provider_key,
            'function' => 'post',
            'api_type' => 1,
            'type' => $mediaItems === [] ? 'text' : 'media',
            'method' => 'rss_schedule',
            'query_id' => $schedule->id,
            'data' => [
                'title' => trim((string) ($item['title'] ?? '')),
                'caption' => $caption,
                'media_type' => $mediaItems === [] ? 'image' : 'image',
                'medias' => $mediaItems,
                'options' => [
                    'post_to' => 'feed',
                    'timezone' => (string) ($owner->timezone ?: config('app.timezone', 'UTC')),
                    'rss_schedule_id' => $schedule->id,
                    'rss_feed_url' => $schedule->feed_url,
                    'rss_item_url' => $item['link'] ?? null,
                    'rss_item_guid' => $item['guid'] ?? null,
                    'campaign_id' => $campaignId,
                    'label_ids' => $labelIds,
                    'rss_ai_rewrite' => [
                        'enabled' => (bool) ($settings['enable_ai_rewrite'] ?? false),
                        'status' => (string) ($rewrite['status'] ?? 'disabled'),
                        'message' => (string) ($rewrite['message'] ?? ''),
                        'credits_used' => (int) ($rewrite['credits_used'] ?? 0),
                        'unit_cost' => (int) ($rewrite['unit_cost'] ?? 0),
                    ],
                ],
            ],
            'time_post' => $now,
            'delay' => 0,
            'repost_frequency' => 0,
            'status' => ($settings['delivery_mode'] ?? 'automatic') === 'draft' ? PublishingPost::STATUS_DRAFT : PublishingPost::STATUS_SCHEDULED,
            'result' => null,
            'changed' => $now,
            'created' => $now,
        ]);
    }

    protected function buildCaption(array $item, array $settings, string $link): string
    {
        $captionParts = [
            trim((string) ($item['title'] ?? '')),
        ];

        if (! empty($settings['accept_caption'])) {
            $captionParts[] = trim((string) ($item['summary'] ?? $item['content'] ?? ''));
        }

        if (empty($settings['deny_link']) && $link !== '') {
            $captionParts[] = $link;
        }

        return trim(collect($captionParts)->filter()->implode("\n\n"));
    }

    public function previewItem(array $item, array $settings): array
    {
        $link = $this->appendReferralCode((string) ($item['link'] ?? ''), (string) ($settings['referral_code'] ?? ''));

        return ['title' => $item['title'], 'caption' => $this->buildCaption($item, $settings, $link), 'matches' => $this->matchesFilters($item, $settings), 'thumbnail' => $item['thumbnail'] ?? '', 'link' => $link];
    }

    protected function rewriteCaption(
        RssSchedule $schedule,
        User $owner,
        string $caption,
        array $item,
        array $settings,
        SocialAccount $account,
        array &$runtime
    ): array {
        if (! ($settings['enable_ai_rewrite'] ?? false) || $caption === '') {
            return [
                'caption' => $caption,
                'status' => 'disabled',
                'message' => '',
                'credits_used' => 0,
                'unit_cost' => (int) ($runtime['unit_cost'] ?? 0),
            ];
        }

        if (($runtime['max_per_run'] ?? 0) > 0 && (int) ($runtime['used_rewrites'] ?? 0) >= (int) ($runtime['max_per_run'] ?? 0)) {
            return [
                'caption' => $caption,
                'status' => 'skipped_per_run_limit',
                'message' => __('Skipped AI rewrite: per-run limit reached.'),
                'credits_used' => 0,
                'unit_cost' => (int) ($runtime['unit_cost'] ?? 0),
            ];
        }

        $unitCost = (int) ($runtime['unit_cost'] ?? 0);
        $monthlyCreditLimit = (int) ($runtime['monthly_credit_limit'] ?? 0);
        $monthlyCreditsUsed = (int) ($runtime['monthly_credits_used'] ?? 0);

        if ($monthlyCreditLimit > 0 && ($monthlyCreditsUsed + $unitCost) > $monthlyCreditLimit) {
            return [
                'caption' => $caption,
                'status' => 'skipped_monthly_limit',
                'message' => __('Skipped AI rewrite: monthly credit limit reached.'),
                'credits_used' => 0,
                'unit_cost' => $unitCost,
            ];
        }

        if (function_exists('credit_service')) {
            try {
                credit_service()->ensureCanConsume($owner, self::AI_REWRITE_CREDIT_ACTION, unitCost: $unitCost);
            } catch (\Throwable) {
                return [
                    'caption' => $caption,
                    'status' => 'skipped_no_credits',
                    'message' => __('Skipped AI rewrite: not enough credits remaining.'),
                    'credits_used' => 0,
                    'unit_cost' => $unitCost,
                ];
            }
        }

        $rewritePrompt = trim((string) ($settings['ai_rewrite_prompt'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $summary = trim((string) ($item['summary'] ?? ''));
        $content = trim(strip_tags((string) ($item['content'] ?? '')));
        $link = trim((string) ($item['link'] ?? ''));

        try {
            $generated = $this->aiStudio->rewriteSocialCaption($caption, [
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'link' => $link,
            ], [
                'tone' => (string) ($settings['ai_rewrite_tone'] ?? 'professional'),
                'language' => (string) ($settings['ai_rewrite_language'] ?? 'vi'),
                'instruction' => $rewritePrompt,
            ]);

            $rewritten = trim((string) ($generated['caption'] ?? ''));

            if ($rewritten === '') {
                return [
                    'caption' => $caption,
                    'status' => 'fallback_empty',
                    'message' => __('AI rewrite returned an empty result.'),
                    'credits_used' => 0,
                    'unit_cost' => $unitCost,
                ];
            }

            if (function_exists('consume_credits')) {
                consume_credits($owner, self::AI_REWRITE_CREDIT_ACTION, [
                    'feature' => 'rss-schedule.ai-rewrite',
                    'unit_cost' => $unitCost,
                    'metadata' => [
                        'source' => 'rss_schedule_rewrite',
                        'schedule_id' => $schedule->id,
                        'schedule_secure' => $schedule->id_secure,
                        'account_id' => $account->id,
                        'feed_url' => $schedule->feed_url,
                    ],
                ]);
            }

            $runtime['used_rewrites'] = (int) ($runtime['used_rewrites'] ?? 0) + 1;
            $runtime['credits_used_in_run'] = (int) ($runtime['credits_used_in_run'] ?? 0) + $unitCost;
            $runtime['monthly_credits_used'] = (int) ($runtime['monthly_credits_used'] ?? 0) + $unitCost;

            return [
                'caption' => $rewritten,
                'status' => 'rewritten',
                'message' => __('AI rewrite applied.'),
                'credits_used' => $unitCost,
                'unit_cost' => $unitCost,
            ];
        } catch (\Throwable) {
            return [
                'caption' => $caption,
                'status' => 'fallback_error',
                'message' => __('AI rewrite failed. Original caption used.'),
                'credits_used' => 0,
                'unit_cost' => $unitCost,
            ];
        }
    }

    protected function buildAiRewriteRuntimeState(RssSchedule $schedule, User $owner, array $settings, int $now): array
    {
        $enabled = (bool) ($settings['enable_ai_rewrite'] ?? false);
        $unitCost = function_exists('credit_service')
            ? max(0, (int) credit_service()->costFor($owner, self::AI_REWRITE_CREDIT_ACTION))
            : 0;

        return [
            'enabled' => $enabled,
            'unit_cost' => $unitCost,
            'used_rewrites' => 0,
            'credits_used_in_run' => 0,
            'max_per_run' => max(0, (int) ($settings['ai_rewrite_max_per_run'] ?? 0)),
            'monthly_credit_limit' => max(0, (int) ($settings['ai_rewrite_monthly_credit_limit'] ?? 0)),
            'monthly_credits_used' => $enabled ? $this->monthlyAiRewriteCreditsUsed($schedule, $now) : 0,
        ];
    }

    protected function monthlyAiRewriteCreditsUsed(RssSchedule $schedule, int $now): int
    {
        $startOfMonth = Carbon::createFromTimestamp($now)->startOfMonth();

        return (int) CreditUsageLog::query()
            ->where('action_key', self::AI_REWRITE_CREDIT_ACTION)
            ->where('feature', 'rss-schedule.ai-rewrite')
            ->where('created_at', '>=', $startOfMonth)
            ->where('metadata->schedule_id', $schedule->id)
            ->sum('amount');
    }

    protected function appendReferralCode(string $link, string $referralCode): string
    {
        $link = trim($link);
        $referralCode = trim($referralCode);

        if ($link === '' || $referralCode === '') {
            return $link;
        }

        $separator = str_contains($link, '?') ? '&' : '?';

        if (str_contains($link, 'ref=')) {
            return $link;
        }

        return $link.$separator.'ref='.rawurlencode($referralCode);
    }

    protected function resolveMediaItems(User $owner, array $item): array
    {
        $thumbnail = trim((string) ($item['thumbnail'] ?? ''));

        if ($thumbnail === '') {
            return [];
        }

        try {
            $file = $this->files->storeRemoteFile($owner, $thumbnail);

            return [[
                'id' => $file->id,
                'id_secure' => $file->id_secure,
                'name' => $file->name,
                'is_image' => $file->is_image,
            ]];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function matchesFilters(array $item, array $settings): bool
    {
        $haystack = Str::lower(implode("\n", array_filter([
            (string) ($item['title'] ?? ''),
            strip_tags((string) ($item['summary'] ?? '')),
            strip_tags((string) ($item['content'] ?? '')),
        ])));

        $include = collect(preg_split('/[\r\n,]+/', (string) ($settings['include_keywords'] ?? '')) ?: [])
            ->map(fn ($keyword) => Str::lower(trim((string) $keyword)))
            ->filter()
            ->values();

        if ($include->isNotEmpty() && ! $include->contains(fn ($keyword) => str_contains($haystack, $keyword))) {
            return false;
        }

        $ignore = collect(preg_split('/[\r\n,]+/', (string) ($settings['ignore_keywords'] ?? '')) ?: [])
            ->map(fn ($keyword) => Str::lower(trim((string) $keyword)))
            ->filter()
            ->values();

        if ($ignore->contains(fn ($keyword) => str_contains($haystack, $keyword))) {
            return false;
        }

        return true;
    }

    protected function contentHash(array $item): string
    {
        return sha1(implode('|', array_filter([
            (string) ($item['guid'] ?? ''),
            (string) ($item['link'] ?? ''),
            (string) ($item['title'] ?? ''),
        ])));
    }

    protected function parseRssItem(\SimpleXMLElement $item, string $feedTitle, string $feedDescription): ?array
    {
        $content = '';
        $mediaThumbnail = null;
        $namespaces = $item->getNamespaces(true);

        if (isset($namespaces['content'])) {
            $contentNode = $item->children($namespaces['content']);
            $content = trim((string) ($contentNode->encoded ?? ''));
        }

        if (isset($namespaces['media'])) {
            $mediaNode = $item->children($namespaces['media']);
            $mediaThumbnail = trim((string) ($mediaNode->thumbnail->attributes()->url ?? $mediaNode->content->attributes()->url ?? ''));
        }

        $description = trim((string) ($item->description ?? ''));
        $title = trim((string) ($item->title ?? ''));

        if ($title === '') {
            return null;
        }

        return [
            'feed_title' => $feedTitle,
            'feed_description' => $feedDescription,
            'title' => $title,
            'link' => trim((string) ($item->link ?? '')),
            'guid' => trim((string) ($item->guid ?? '')),
            'summary' => strip_tags($description),
            'content' => $content !== '' ? $content : $description,
            'thumbnail' => $mediaThumbnail ?: $this->firstImageFromHtml($content ?: $description),
            'published_at' => ($time = strtotime((string) ($item->pubDate ?? ''))) ? $time : null,
        ];
    }

    protected function parseAtomEntry(\SimpleXMLElement $entry, string $feedTitle, string $feedDescription): ?array
    {
        $title = trim((string) ($entry->title ?? ''));

        if ($title === '') {
            return null;
        }

        $link = '';
        foreach ($entry->link as $node) {
            $attrs = $node->attributes();
            if ((string) ($attrs['rel'] ?? 'alternate') === 'alternate') {
                $link = trim((string) ($attrs['href'] ?? ''));
                break;
            }
        }

        $content = trim((string) ($entry->content ?? ''));
        $summary = trim((string) ($entry->summary ?? ''));

        return [
            'feed_title' => $feedTitle,
            'feed_description' => $feedDescription,
            'title' => $title,
            'link' => $link,
            'guid' => trim((string) ($entry->id ?? '')),
            'summary' => strip_tags($summary),
            'content' => $content !== '' ? $content : $summary,
            'thumbnail' => $this->firstImageFromHtml($content ?: $summary),
            'published_at' => ($time = strtotime((string) ($entry->published ?? $entry->updated ?? ''))) ? $time : null,
        ];
    }

    protected function firstImageFromHtml(string $html): ?string
    {
        if (! preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            return null;
        }

        return trim((string) ($matches[1] ?? '')) ?: null;
    }
}
