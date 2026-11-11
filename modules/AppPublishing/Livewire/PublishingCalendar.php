<?php

namespace Modules\AppPublishing\Livewire;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DateTimeInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AdminUser\Models\Team;
use Modules\AppCaptions\Models\CaptionLibraryItem;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppGroups\Models\AccountGroup;
use Modules\AppAIStudio\Models\AIStudioUserSetting;
use Modules\AppAIStudio\Models\AIStudioWorkspaceSetting;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppPublishing\Jobs\PublishScheduledPostJob;
use Modules\AppPublishing\Services\Publishing\PostPublishService;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppPublishing\Support\PublishingMediaValidationRegistry;
use Modules\AppPublishing\Support\PublishingNetworkConfigRegistry;
use Modules\AppPublishing\Support\PublishingNetworkOptionsRegistry;
use Modules\AppPublishing\Support\PublishingValidationFieldTargetRegistry;
use Modules\AppTeams\Models\TeamPostReview;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Publishing')]
class PublishingCalendar extends Component
{
    protected const CALENDAR_VIEWS = ['month', 'week', 'calendar'];

    protected const PORTAL_STATUS_META = [
        'pending' => ['label' => 'Pending'],
        'waiting_approve' => ['label' => 'Waiting approve'],
        'processing' => ['label' => 'Processing'],
        'published' => ['label' => 'Published'],
        'failed' => ['label' => 'Failed'],
        'draft' => ['label' => 'Draft'],
    ];

    #[Url(as: 'view')]
    public string $calendarView = 'month';

    #[Url(as: 'date')]
    public string $focusDate = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'provider')]
    public string $providerFilter = 'all';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'campaign')]
    public string $campaignFilter = 'all';

    #[Url(as: 'label')]
    public string $labelFilter = 'all';

    #[Url(as: 'edit')]
    public string $edit = '';

    public bool $composerOpen = false;

    public bool $composerMediaBrowserReady = false;

    public array $composer = [];

    public bool $postPreviewOpen = false;

    public array $postPreviewComposer = [];

    public array $postPreviewMeta = [];

    public int $calendarCacheNonce = 1;

    protected PostPublishService $publishService;

    public function boot(PostPublishService $publishService): void
    {
        $this->publishService = $publishService;
    }

    public function mount(): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $requestedView = request()->query('view');

        if (! is_string($requestedView) || $this->normalizeCalendarView($requestedView) === null) {
            $this->calendarView = $this->lastCalendarViewPreference() ?? $this->calendarView;
        }

        if ($this->focusDate === '') {
            $this->focusDate = now($this->currentTimezone())->toDateString();
        }

        $normalizedView = $this->normalizeCalendarView($this->calendarView);

        if ($normalizedView === null) {
            $this->calendarView = 'month';
        } else {
            $this->calendarView = $normalizedView;
        }

        $this->resetComposer();

        if (request()->integer('caption') > 0) {
            $this->openComposer();
            $this->applyComposerLibraryCaption(request()->integer('caption'));
        }

        if ($this->edit !== '') {
            $this->editPost($this->edit);
            $this->edit = '';
        } elseif (request()->boolean('compose') && request()->integer('caption') <= 0) {
            $this->openComposer();
        }
    }

    public function setView(string $view): void
    {
        $view = $this->normalizeCalendarView($view);

        if ($view !== null) {
            if ($this->calendarView === $view) {
                return;
            }

            $this->calendarView = $view;
            $this->rememberCalendarViewPreference($view);
        }
    }

    public function goToday(): void
    {
        $this->focusDate = now($this->currentTimezone())->toDateString();
    }

    public function goPrevious(): void
    {
        $date = $this->focus();

        $this->focusDate = ($this->calendarView === 'week'
            ? $date->copy()->subWeek()
            : $date->copy()->subMonth())
            ->toDateString();
    }

    public function goNext(): void
    {
        $date = $this->focus();

        $this->focusDate = ($this->calendarView === 'week'
            ? $date->copy()->addWeek()
            : $date->copy()->addMonth())
            ->toDateString();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->providerFilter = 'all';
        $this->statusFilter = 'all';
        $this->campaignFilter = 'all';
        $this->labelFilter = 'all';
    }

    public function openComposer(?string $date = null, ?int $accountId = null): void
    {
        if ($date) {
            $targetDate = Carbon::parse($date, $this->currentTimezone())->startOfDay();

            if ($targetDate->lessThan(now($this->currentTimezone())->startOfDay())) {
                $this->dispatch(
                    'app-toast',
                    type: 'warning',
                    title: __('Unavailable slot'),
                    message: __('You can only compose posts for today or future dates.'),
                );

                return;
            }
        }

        $this->resetComposer();
        $this->composerOpen = true;
        $this->dispatch('publishing-composer-scroll-lock');
        if ($date) {
            $this->composer['schedule_mode'] = 'specific_days_times';
            $userNow = now($this->currentTimezone());
            $this->composer['schedule_slots'] = [
                Carbon::parse($date, $this->currentTimezone())
                    ->setTime((int) $userNow->copy()->addHour()->format('H'), 0)
                    ->format('Y-m-d\TH:i'),
            ];
        }

        if ((int) $accountId > 0) {
            $this->composer['account_ids'] = [(string) $accountId];
            $this->composer['preview_account_id'] = (string) $accountId;
            $providerKey = $this->providerKeyForAccountId((int) $accountId);

            if ($providerKey !== '') {
                $this->composer['network_options'] = [
                    $providerKey => $this->defaultNetworkOptionsForProvider($providerKey),
                ];
                $this->composer['media_type'] = $this->inferMediaTypeFromNetworkOptions(
                    $providerKey,
                    (array) data_get($this->composer, 'network_options.'.$providerKey, [])
                );
            }
        }
    }

    public function updatedComposerAccountIds($value): void
    {
        $selectedIds = collect((array) $value)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedIds->isEmpty()) {
            $this->composer['preview_account_id'] = '';
        } else {
            $this->composer['preview_account_id'] = (string) $selectedIds->last();
        }

        $providerKeys = $this->providerKeysForAccountIds($selectedIds->all());
        $existingOptions = is_array($this->composer['network_options'] ?? null) ? $this->composer['network_options'] : [];
        $nextOptions = [];

        foreach ($providerKeys as $providerKey) {
            $nextOptions[$providerKey] = $this->normalizeNetworkOptionsForProvider(
                $providerKey,
                (array) ($existingOptions[$providerKey] ?? [])
            );
        }

        $this->composer['network_options'] = $nextOptions;

        $activeProviderKey = $this->providerKeyForAccountId((int) ($this->composer['preview_account_id'] ?? 0));
        $this->composer['media_type'] = $this->inferMediaTypeFromNetworkOptions(
            $activeProviderKey,
            (array) ($nextOptions[$activeProviderKey] ?? [])
        );
    }

    public function updatedComposerNetworkOptionsPostTo($value): void
    {
        $providerKey = $this->providerKeyForAccountId((int) ($this->composer['preview_account_id'] ?? 0));
        $currentOptions = $providerKey !== ''
            ? (array) data_get($this->composer, 'network_options.'.$providerKey, [])
            : [];

        $this->composer['media_type'] = $this->inferMediaTypeFromNetworkOptions($providerKey, [
            ...$currentOptions,
            'post_to' => $value,
        ]);
    }

    public function updatedComposerScheduleMode($value): void
    {
        $mode = (string) $value;

        if ($mode === 'immediately') {
            $this->composer['schedule_slots'] = [$this->defaultScheduleSlot(now())];
            $this->composer['repeat_rule'] = 'none';
            $this->composer['repeat_until'] = '';
            $this->composer['repeat_days'] = [];
            return;
        }

        if ($mode === 'specific_days_times' && empty($this->composer['schedule_slots'])) {
            $this->composer['schedule_slots'] = [$this->defaultScheduleSlot()];
        }
    }

    public function updatedComposerRepeatRule($value): void
    {
        $rule = (string) $value;

        if (! in_array($rule, ['none', 'weekday', 'weekly_custom'], true)) {
            $this->composer['repeat_rule'] = 'none';

            return;
        }

        if ($rule === 'none') {
            $this->composer['repeat_until'] = '';
            $this->composer['repeat_days'] = [];

            return;
        }

        if (trim((string) ($this->composer['repeat_until'] ?? '')) === '') {
            $this->composer['repeat_until'] = now($this->currentTimezone())->addWeeks(2)->format('Y-m-d');
        }

        if ($rule === 'weekday') {
            $this->composer['repeat_days'] = ['mon', 'tue', 'wed', 'thu', 'fri'];
        }
    }

    public function addScheduleSlot(): void
    {
        $slots = collect((array) ($this->composer['schedule_slots'] ?? []))
            ->values();

        $lastSlot = (string) $slots->last();
        $next = $this->defaultScheduleSlot();

        if ($lastSlot !== '') {
            try {
                $next = Carbon::parse($lastSlot)->addDay()->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                $next = $this->defaultScheduleSlot();
            }
        }

        $slots->push($next);
        $this->composer['schedule_slots'] = $slots->all();
    }

    public function removeScheduleSlot(int $index): void
    {
        $slots = collect((array) ($this->composer['schedule_slots'] ?? []))
            ->values();

        $slots->forget($index);
        $slots = $slots->values();

        if ($slots->isEmpty()) {
            $slots = collect([$this->defaultScheduleSlot()]);
        }

        $this->composer['schedule_slots'] = $slots->all();
    }

    public function closeComposer(): void
    {
        $this->composerOpen = false;
        $this->dispatch('publishing-composer-scroll-unlock');
    }

    public function loadComposerMediaBrowser(): void
    {
        if ($this->composerMediaBrowserReady) {
            return;
        }

        $this->composerMediaBrowserReady = true;
    }

    public function openPostPreview(string $postId): void
    {
        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        $data = is_array($post->data) ? $post->data : [];
        $publishResult = $this->normalizePublishResult($post->result);
        $providerKey = (string) ($post->social_network ?: $post->account?->provider_key ?: '');
        $resolvedPostUrl = $this->resolvePublishedPostUrl(
            $publishResult,
            $providerKey
        );

        $this->postPreviewComposer = $this->composerPayloadFromPost($post, false);
        $this->postPreviewMeta = [
            'post_id' => (string) ($post->id_secure ?: $post->id),
            'account_id' => (string) $post->account_id,
            'provider_key' => $providerKey,
            'title' => (string) ($data['title'] ?? ''),
            'post_url' => (string) ($resolvedPostUrl ?? ''),
            'open_error' => (string) ($publishResult['error'] ?? ''),
            'open_state' => (string) ($publishResult['state'] ?? ''),
        ];
        $this->postPreviewOpen = true;
        $this->dispatch('publishing-post-preview-scroll-lock');
    }

    public function closePostPreview(): void
    {
        $this->postPreviewOpen = false;
        $this->postPreviewComposer = [];
        $this->postPreviewMeta = [];
        $this->dispatch('publishing-post-preview-scroll-unlock');
    }

    public function applyComposerLibraryCaption(int $captionId): void
    {
        $caption = \Modules\AppTeams\Support\PortalLibraryAccess::query(CaptionLibraryItem::query())
            ->where('status', 'active')
            ->find($captionId);

        if (! $caption) {
            $this->addError('composer.caption', __('The selected caption is no longer available.'));
            return;
        }

        $this->composer['caption'] = (string) $caption->content;
        $this->dispatch('publishing-ai-caption-updated', caption: $this->composer['caption'], animate: true);
        $this->resetErrorBag('composer.caption');
    }

    public function generateComposerCaption(AiContentStudioService $studio): void
    {
        $brief = trim((string) ($this->composer['caption'] ?? ''));
        $notes = trim((string) ($this->composer['notes'] ?? ''));
        $seed = $brief !== '' ? $brief : $notes;

        if ($seed === '') {
            $this->addError('composer.caption', __('Add a short brief or internal note before generating captions.'));
            return;
        }

        $platforms = $this->composerAiPlatforms();
        $language = $this->composerAiDefaultLanguage();
        $result = $studio->generatePlatformCaptions($seed, $platforms, [
            'language' => $language,
            'tone' => 'professional',
            'user' => auth()->user(),
        ]);
        $variants = collect((array) ($result['variants'] ?? $result['platforms'] ?? []))
            ->map(fn ($item) => is_array($item) ? $item : [])
            ->values();

        $preferred = $variants
            ->first(fn ($item) => strtolower((string) ($item['platform'] ?? '')) === strtolower((string) ($platforms[0] ?? '')));
        $fallback = $variants->first();

        if (! is_array($preferred)) {
            $preferred = is_array($fallback) ? $fallback : [];
        }

        $selected = $variants
            ->first(fn ($item) => $this->composeComposerCaptionVariant($item) !== '' && trim($this->composeComposerCaptionVariant($item)) !== $seed);

        if (! is_array($selected)) {
            $selected = $preferred;
        }

        $nextCaption = $this->composeComposerCaptionVariant($selected);

        if ($nextCaption === '') {
            $nextCaption = $this->composeComposerCaptionVariant($preferred);
        }

        $this->composer['caption'] = $nextCaption !== '' ? $nextCaption : $this->composer['caption'];
        $this->composer['ai_caption_variants'] = $variants->all();
        $this->composer['ai_tags'] = $studio->suggestTags($this->composer['caption'], ['platforms' => $platforms])['tags'] ?? [];
        $this->dispatch('publishing-ai-caption-updated', caption: $this->composer['caption']);
        $this->resetErrorBag('composer.caption');
    }

    public function applyComposerCaptionVariant(int $index): void
    {
        $item = collect((array) ($this->composer['ai_caption_variants'] ?? []))->values()->get($index);

        if (! is_array($item)) {
            return;
        }

        $caption = $this->composeComposerCaptionVariant($item);

        if ($caption === '') {
            return;
        }

        $this->composer['caption'] = $caption;
        $this->dispatch('publishing-ai-caption-updated', caption: $this->composer['caption']);
    }

    public function saveComposerCaption(AiContentStudioService $studio): void
    {
        $content = trim((string) ($this->composer['caption'] ?? ''));

        if ($content === '') {
            $this->addError('composer.caption', __('Add a caption before saving it to the library.'));
            return;
        }

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $validated = $this->validate([
            'composer.caption_library_name' => ['required', 'string', 'max:120'],
            'composer.caption_library_source_type' => ['required', 'in:manual,ai'],
        ])['composer'];

        $title = trim((string) ($validated['caption_library_name'] ?? ''));
        $caption = CaptionLibraryItem::query()->create([
            'owner_user_id' => (int) $user->id,
            'team_id' => $this->currentTeamId(),
            'name' => $title,
            'slug' => $this->uniqueComposerCaptionSlug($title, (int) $user->id),
            'source_type' => (string) ($validated['caption_library_source_type'] ?? 'manual'),
            'status' => 'active',
            'content' => $content,
            'notes' => trim((string) ($this->composer['notes'] ?? '')) ?: null,
            'tags' => collect((array) ($this->composer['ai_tags'] ?? []))
                ->merge($this->composerAiPlatforms())
                ->map(fn ($tag) => Str::slug((string) $tag))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'metadata' => [
                'saved_from' => 'publishing_composer',
                'saved_at' => now()->toIso8601String(),
            ],
        ]);

        $studio->annotateCaption($caption);
        $this->flushComposerReferenceCache();
        $this->dispatch('app-toast', type: 'success', title: __('Caption saved'), message: __('The caption was added to your caption library.'));
        $this->dispatch('publishing-caption-save-finished');
        $this->resetErrorBag(['composer.caption', 'composer.caption_library_name', 'composer.caption_library_source_type']);
    }

    public function shortenComposerLinks(): void
    {
        $content = (string) ($this->composer['caption'] ?? '');

        if (trim($content) === '') {
            $this->addError('composer.caption', __('Add a caption before shortening links.'));
            return;
        }

        $user = auth()->user();

        if (! url_shortening_enabled($user)) {
            $this->dispatch('app-toast', type: 'error', title: __('URL shortener unavailable'), message: __('Configure a URL shortener before using this action.'));
            return;
        }

        $shortened = shorten_urls_in_content($content);

        if ($shortened === $content) {
            $this->dispatch('app-toast', type: 'warning', title: __('No links shortened'), message: __('No valid links were found or the shortener did not return a new URL.'));
            return;
        }

        $this->composer['caption'] = $shortened;
        $this->dispatch('publishing-ai-caption-updated', caption: $this->composer['caption']);
        $this->dispatch('app-toast', type: 'success', title: __('Links shortened'), message: __('All detected URLs in the caption were replaced with shortened links.'));
        $this->resetErrorBag('composer.caption');
    }

    public function repurposeComposer(AiContentStudioService $studio): void
    {
        $content = trim((string) ($this->composer['caption'] ?? ''));

        if ($content === '') {
            $this->addError('composer.caption', __('Add draft content before repurposing it.'));
            return;
        }

        $result = $studio->repurposeContent($content, $this->composerAiPlatforms(), [
            'language' => $this->composerAiDefaultLanguage(),
            'tone' => 'professional',
            'user' => auth()->user(),
        ]);
        $this->composer['ai_repurpose_items'] = collect((array) ($result['items'] ?? []))->values()->all();
        $this->resetErrorBag('composer.caption');
    }

    public function generateComposerImage(AiContentStudioService $studio): void
    {
        $prompt = trim((string) ($this->composer['caption'] ?? ''));

        if ($prompt === '') {
            $prompt = trim((string) ($this->composer['notes'] ?? ''));
        }

        if ($prompt === '') {
            $this->addError('composer.caption', __('Add a short prompt before generating an image.'));
            return;
        }

        $result = $studio->generateWorkspaceImage(
            auth()->user(),
            $this->composerImagePromptFromSeed($prompt),
            style: 'editorial',
            ratio: '1:1',
        );

        /** @var AppFile|null $file */
        $file = $result['file'] ?? null;

        if (! $file instanceof AppFile) {
            $this->addError('composer.media_items', __('The AI image could not be attached.'));
            return;
        }

        $currentItems = collect((array) ($this->composer['media_items'] ?? []))
            ->filter(fn ($item) => is_array($item))
            ->values();

        $nextItem = $this->composerMediaItemFromFile($file);

        $this->composer['media_items'] = $currentItems
            ->reject(fn (array $item) => (string) ($item['idSecure'] ?? '') === (string) $nextItem['idSecure'])
            ->prepend($nextItem)
            ->unique(fn (array $item) => (string) ($item['idSecure'] ?? $item['id'] ?? ''))
            ->values()
            ->all();

        $this->composer['media_type'] = 'image';
        $this->composer['media_refresh_token'] = (int) ($this->composer['media_refresh_token'] ?? 0) + 1;

        $this->dispatch(
            'publishing-media-updated',
            model: 'composer.media_items',
            item: $nextItem,
            items: $this->composer['media_items']
        );
        $this->dispatch('app-toast', type: 'success', title: __('AI image added'), message: __('The generated image was saved to Files and attached to this post.'));
        $this->resetErrorBag(['composer.caption', 'composer.media_items']);
    }

    public function applyRepurposeVariant(int $index): void
    {
        $item = collect((array) ($this->composer['ai_repurpose_items'] ?? []))->values()->get($index);

        if (! is_array($item) || blank($item['caption'] ?? null)) {
            return;
        }

        $this->composer['caption'] = (string) $item['caption'];
        $this->dispatch('publishing-ai-caption-updated', caption: $this->composer['caption']);
    }

    public function reviewComposer(AiContentStudioService $studio): void
    {
        $content = trim((string) ($this->composer['caption'] ?? ''));

        if ($content === '') {
            $this->addError('composer.caption', __('Add draft content before requesting an AI review.'));
            return;
        }

        $this->composer['ai_review'] = $studio->reviewDraft($content, $this->composerAiPlatforms(), [
            'tone' => 'professional',
            'user' => auth()->user(),
        ]);
        $this->resetErrorBag('composer.caption');
    }

    public function suggestComposerBestTimes(AiContentStudioService $studio): void
    {
        $accountIds = collect((array) ($this->composer['account_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $this->composer['ai_best_times'] = $studio->bestTimeSuggestions(
            $this->workspaceOwnerUserId(),
            $accountIds,
            $this->currentTimezone(),
        );
    }

    public function applyComposerBestTime(int $index): void
    {
        $slot = collect((array) ($this->composer['ai_best_times'] ?? []))->values()->get($index);

        if (! is_array($slot)) {
            return;
        }

        $weekdayMap = [
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
            'sun' => Carbon::SUNDAY,
        ];

        $weekday = strtolower((string) ($slot['weekday'] ?? ''));
        $hour = max(0, min(23, (int) ($slot['hour'] ?? 9)));
        $target = now($this->currentTimezone())->next($weekdayMap[$weekday] ?? Carbon::MONDAY)->setTime($hour, 0);

        $this->composer['schedule_mode'] = 'specific_days_times';
        $this->composer['schedule_slots'] = [$target->format('Y-m-d\TH:i')];
        $this->dispatch('publishing-ai-schedule-updated', slots: $this->composer['schedule_slots']);
    }

    public function editPost(string $postId): void
    {
        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        if (! $this->canEditPost($post)) {
            $this->dispatch('app-toast', type: 'error', title: __('Cannot edit post'), message: __('Only unpublished items can be edited from the calendar.'));
            return;
        }

        $this->resetComposer();
        $this->composer = $this->composerPayloadFromPost($post, true);
        $this->composerOpen = true;
        $this->dispatch('publishing-composer-scroll-lock');
    }

    public function copyPost(string $postId): void
    {
        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        $this->resetComposer();
        $this->composer = $this->composerPayloadFromPost($post, false);
        $this->composerOpen = true;
        $this->dispatch('publishing-composer-scroll-lock');
    }

    public function deletePost(string $postId): void
    {
        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        TeamPostReview::query()->where('post_id', $post->id)->delete();
        $post->delete();
        $this->bumpCalendarCacheNonce();

        $this->dispatch('app-toast', type: 'success', title: __('Post deleted'), message: __('The publishing item has been removed from the queue.'));
    }

    public function deleteRemotePost(string $postId): void
    {
        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        $result = $this->publishService->deleteRemote($post);

        if (($result['state'] ?? null) !== 'deleted') {
            $this->dispatch('app-toast', type: 'error', title: __('Remote delete failed'), message: (string) ($result['error'] ?? __('The social network did not accept the delete request.')));
            return;
        }

        $post->result = [
            ...(is_array($post->result) ? $post->result : []),
            'state' => 'deleted',
            'url' => null,
            'deleted_remote_at' => now()->toIso8601String(),
            'remote_delete_response' => $result['response'] ?? null,
        ];
        $post->status = PublishingPost::STATUS_DRAFT;
        $post->save();
        $this->bumpCalendarCacheNonce();

        $this->dispatch('app-toast', type: 'success', title: __('Deleted on social network'), message: __('The published post has been removed from the social network and reset locally.'));
    }

    public function deleteFilteredPosts(): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $focus = $this->focus();
        if ($this->calendarView === 'week') {
            $rangeStart = $focus->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($this->calendarView === 'calendar') {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        } else {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        }

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $providerRegistry = collect(channel_provider_cards())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['key'] => $provider]);

        $calendarItems = $this->cachedCalendarItems($channels, $providerRegistry, $rangeStart, $rangeEnd);
        $filteredItems = $this->applyCalendarFilters($calendarItems);
        $visibleDateKeys = $this->dateRangeKeys($rangeStart, $rangeEnd);

        $targetPostIds = $filteredItems
            ->whereIn('date', $visibleDateKeys)
            ->pluck('post_int_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($targetPostIds->isEmpty()) {
            $this->dispatch('app-toast', type: 'warning', title: __('No posts to delete'), message: __('No publishing items match the current filters.'));
            return;
        }

        TeamPostReview::query()
            ->whereIn('post_id', $targetPostIds->all())
            ->delete();

        $deleted = PublishingPost::query()
            ->whereIn('id', $targetPostIds->all())
            ->delete();

        $this->bumpCalendarCacheNonce();
        $this->dispatch(
            'app-toast',
            type: 'success',
            title: __('Filtered posts deleted'),
            message: trans_choice(':count publishing item removed.|:count publishing items removed.', $deleted, ['count' => $deleted]),
        );
    }

    public function openDayPosts(string $date): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $focus = $this->focus();
        if ($this->calendarView === 'week') {
            $rangeStart = $focus->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($this->calendarView === 'calendar') {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        } else {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        }

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $providerRegistry = collect(channel_provider_cards())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['key'] => $provider]);

        $calendarItems = $this->cachedCalendarItems($channels, $providerRegistry, $rangeStart, $rangeEnd);
        $filteredItems = $this->applyCalendarFilters($calendarItems);
        $items = $filteredItems
            ->where('date', $date)
            ->values()
            ->map(fn (array $item): array => $this->sanitizeUtf8Value($item))
            ->all();

        $dateLabel = $date;

        try {
            $dateLabel = Carbon::parse($date, $this->currentTimezone())->format('D, M j');
        } catch (\Throwable) {
        }

        $safeItemsJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $safeItems = is_string($safeItemsJson)
            ? (json_decode($safeItemsJson, true) ?: [])
            : [];

        $safeDateLabelJson = json_encode((string) $this->sanitizeUtf8Value($dateLabel), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $safeDateLabel = is_string($safeDateLabelJson)
            ? (string) (json_decode($safeDateLabelJson, true) ?: $date)
            : $date;

        $this->dispatch(
            'publishing-day-posts-modal-open',
            dateLabel: $safeDateLabel,
            items: $safeItems
        );
    }

    public function movePostToDate(string $postId, string $targetDate, bool $changeTime = false, ?string $newTime = null): void
    {
        abort_unless($this->publishingEnabled(), 404);

        $post = $this->resolveManagedPost($postId);

        if (! $post) {
            $this->dispatch('app-toast', type: 'error', title: __('Post not found'), message: __('This publishing item is no longer available.'));
            return;
        }

        if (! $this->canEditPost($post)) {
            $this->dispatch('app-toast', type: 'error', title: __('Cannot move post'), message: __('Only unpublished items can be moved on the calendar.'));
            return;
        }

        try {
            $targetDay = Carbon::parse($targetDate, $this->currentTimezone())->startOfDay();
        } catch (\Throwable) {
            $this->dispatch('app-toast', type: 'error', title: __('Invalid date'), message: __('Please choose a valid target date.'));
            return;
        }

        $now = now($this->currentTimezone());

        if ($targetDay->lessThan($now->copy()->startOfDay())) {
            $this->dispatch('app-toast', type: 'warning', title: __('Choose a valid day'), message: __('You can only move posts to today or a future day.'));
            return;
        }

        $scheduledAt = $post->time_post
            ? Carbon::createFromTimestamp((int) $post->time_post, $this->currentTimezone())
            : now($this->currentTimezone())->addHour()->startOfHour();

        $targetDateTime = $targetDay->copy()->setTime(
            (int) $scheduledAt->format('H'),
            (int) $scheduledAt->format('i')
        );

        if ($changeTime) {
            $timeValue = trim((string) ($newTime ?? ''));

            if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $timeValue)) {
                $this->dispatch('app-toast', type: 'error', title: __('Invalid time'), message: __('Please choose a valid time for the moved post.'));
                return;
            }

            [$hour, $minute] = array_map('intval', explode(':', $timeValue));
            $targetDateTime = $targetDay->copy()->setTime($hour, $minute);
        }

        if ($targetDay->isSameDay($now) && $targetDateTime->lessThanOrEqualTo($now)) {
            $this->dispatch(
                'app-toast',
                type: 'warning',
                title: __('Choose a later time'),
                message: __('For today, the publish time must be later than the current time.')
            );
            return;
        }

        $data = is_array($post->data) ? $post->data : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $options['schedule_mode'] = 'specific_days_times';
        $data['options'] = $options;

        $post->time_post = $targetDateTime->timestamp;
        $post->status = PublishingPost::STATUS_SCHEDULED;
        $post->result = ['state' => 'pending'];
        $post->data = $data;
        $post->save();

        $this->bumpCalendarCacheNonce();

        $this->dispatch(
            'app-toast',
            type: 'success',
            title: __('Post moved'),
            message: __('Scheduled for :date', ['date' => $targetDateTime->format('M j, Y H:i')]),
        );
    }

    public function saveComposer(string $mode = 'scheduled'): void
    {
        logger()->info('publishing.saveComposer.hit', [
            'mode' => $mode,
            'schedule_mode' => (string) ($this->composer['schedule_mode'] ?? ''),
            'account_ids' => collect((array) ($this->composer['account_ids'] ?? []))->values()->all(),
            'preview_account_id' => (string) ($this->composer['preview_account_id'] ?? ''),
            'media_items_count' => count((array) ($this->composer['media_items'] ?? [])),
        ]);

        $validated = $this->validate([
            'composer.account_ids' => ['required', 'array', 'min:1'],
            'composer.account_ids.*' => ['integer'],
            'composer.caption' => ['nullable', 'string', 'max:2200'],
            'composer.media_type' => ['nullable', 'in:image,carousel,reel,story,video'],
            'composer.media_items' => ['nullable', 'array'],
            'composer.network_options' => ['nullable', 'array'],
            'composer.schedule_mode' => ['required', 'in:immediately,specific_days_times'],
            'composer.schedule_slots' => ['nullable', 'array'],
            'composer.schedule_slots.*' => ['nullable', 'string'],
            'composer.repeat_rule' => ['nullable', 'in:none,weekday,weekly_custom'],
            'composer.repeat_until' => ['nullable', 'string'],
            'composer.repeat_days' => ['nullable', 'array'],
            'composer.repeat_days.*' => ['nullable', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'composer.label_ids' => ['nullable', 'array'],
            'composer.campaign_id' => ['nullable', 'integer'],
            'composer.notes' => ['nullable', 'string', 'max:400'],
        ], [
            'composer.account_ids.required' => __('Please select at least one channel.'),
            'composer.account_ids.array' => __('Please select at least one channel.'),
            'composer.account_ids.min' => __('Please select at least one channel.'),
            'composer.media_type.in' => __('Please choose a valid media type.'),
            'composer.schedule_mode.required' => __('Please choose when this post should be published.'),
            'composer.schedule_mode.in' => __('Please choose a valid publishing mode.'),
            'composer.schedule_slots.*.date' => __('One or more schedule times are invalid.'),
            'composer.repeat_rule.in' => __('Please choose a valid repeat pattern.'),
            'composer.repeat_until.date' => __('Please choose a valid repeat end date.'),
            'composer.repeat_days.*.in' => __('One or more repeat days are invalid.'),
            'composer.notes.max' => __('Notes cannot exceed :max characters.'),
        ], [
            'composer.account_ids' => __('channel'),
            'composer.media_type' => __('media type'),
            'composer.schedule_mode' => __('publishing mode'),
            'composer.schedule_slots.*' => __('schedule time'),
        ])['composer'];

        $selectedAccountIds = collect((array) ($validated['account_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();
        $editingPost = null;
        $editingPostId = trim((string) ($this->composer['editing_post_id'] ?? ''));

        if ($editingPostId !== '') {
            $editingPost = $this->resolveManagedPost($editingPostId);

            if (! $editingPost || ! $this->canEditPost($editingPost)) {
                $message = __('This post can no longer be edited.');
                $this->addError('composer.account_ids', $message);
                $this->dispatch('app-toast', type: 'error', title: __('Cannot edit post'), message: $message);
                return;
            }

            if ($selectedAccountIds->count() !== 1) {
                $message = __('Editing a post requires exactly one channel.');
                $this->addError('composer.account_ids', $message);
                $this->dispatch('app-toast', type: 'error', title: __('Cannot edit post'), message: $message);
                return;
            }
        }

        $accounts = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->whereIn('id', $selectedAccountIds->all())
            ->get()
            ->keyBy('id');

        if ($selectedAccountIds->isEmpty() || $accounts->count() !== $selectedAccountIds->count()) {
            $message = __('One or more selected channels are no longer available for publishing.');
            $this->addError('composer.account_ids', $message);
            $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);

            return;
        }

        $campaign = null;

        if (! empty($validated['campaign_id'])) {
            $campaign = PublishingCampaign::query()
                ->ownedBy($this->workspaceOwnerUserId())
                ->find((int) $validated['campaign_id']);
        }

        $labels = PublishingLabel::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->whereIn('status', ['active', 'draft'])
            ->whereIn('id', collect((array) ($validated['label_ids'] ?? []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all())
            ->orderBy('name')
            ->get();

        $aiPayload = [
            'caption_variants' => collect((array) ($this->composer['ai_caption_variants'] ?? []))->values()->all(),
            'repurpose_items' => collect((array) ($this->composer['ai_repurpose_items'] ?? []))->values()->all(),
            'review' => is_array($this->composer['ai_review'] ?? null) ? $this->composer['ai_review'] : [],
            'tags' => collect((array) ($this->composer['ai_tags'] ?? []))->map(fn ($tag) => trim((string) $tag))->filter()->values()->all(),
        ];

        $scheduleMode = (string) ($validated['schedule_mode'] ?? 'immediately');
        $repeatRule = (string) ($validated['repeat_rule'] ?? 'none');
        $repeatUntil = trim((string) ($validated['repeat_until'] ?? ''));
        $repeatDays = collect((array) ($validated['repeat_days'] ?? []))
            ->map(fn ($day) => strtolower(trim((string) $day)))
            ->filter()
            ->values()
            ->all();
        $statusKey = $mode === 'draft' ? 'draft' : 'scheduled';
        $requiresApproval = $mode !== 'draft' && ! $this->canCurrentUserPublishDirectly();
        $publishImmediately = $mode !== 'draft' && $scheduleMode === 'immediately' && ! $requiresApproval;

        if ($scheduleMode === 'specific_days_times' && $repeatRule !== 'none' && $repeatUntil === '') {
            $message = __('Please choose when the repeat schedule should stop.');
            $this->addError('composer.repeat_until', $message);
            $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);

            return;
        }

        if ($scheduleMode === 'specific_days_times' && $repeatRule === 'weekly_custom' && $repeatDays === []) {
            $message = __('Choose at least one weekday for the repeat schedule.');
            $this->addError('composer.repeat_days', $message);
            $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);

            return;
        }

        $scheduleSlots = $this->resolveScheduleSlots(
            $scheduleMode,
            (array) ($validated['schedule_slots'] ?? []),
            $repeatRule,
            $repeatUntil,
            $repeatDays
        );

        if ($scheduleSlots->isEmpty()) {
            $message = __('Please choose at least one schedule time.');
            $this->addError('composer.schedule_slots', $message);
            $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);

            return;
        }

        foreach ($selectedAccountIds as $accountId) {
            $account = $accounts->get((int) $accountId);

            if (! $account) {
                continue;
            }

            $providerOptions = $this->normalizeNetworkOptionsForProvider(
                (string) $account->provider_key,
                (array) data_get($validated, 'network_options.'.$account->provider_key, [])
            );

            $contentValidationError = $this->validateContentSelectionForProvider(
                (string) $account->provider_key,
                $providerOptions
            );

            if ($contentValidationError) {
                $message = $contentValidationError['message'];
                $this->addError(
                    $this->resolveValidationFieldTarget(
                        (string) $account->provider_key,
                        $contentValidationError['target'] ?? '',
                        $contentValidationError['field'] ?? 'composer.caption'
                    ),
                    $message
                );
                $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);
                return;
            }

            $mediaValidationError = $this->validateMediaSelectionForProvider(
                (string) $account->provider_key,
                $providerOptions,
                (array) ($validated['media_items'] ?? [])
            );

            if ($mediaValidationError) {
                $this->addError('composer.media_items', $mediaValidationError);
                $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $mediaValidationError);
                return;
            }
        }

        $nowTimestamp = now()->timestamp;
        $timezone = $this->currentTimezone();
        $immediatePostIds = [];

        if ($editingPost) {
            $account = $accounts->get((int) $selectedAccountIds->first());

            if (! $account) {
                $message = __('The selected channel is no longer available.');
                $this->addError('composer.account_ids', $message);
                $this->dispatch('app-toast', type: 'error', title: __('Publishing failed'), message: $message);
                return;
            }

            $scheduleAt = $scheduleSlots->first();
            $providerOptions = $this->normalizeNetworkOptionsForProvider(
                (string) $account->provider_key,
                (array) data_get($validated, 'network_options.'.$account->provider_key, [])
            );
            $resolvedMediaType = $validated['media_type']
                ?? $this->inferMediaTypeFromNetworkOptions((string) $account->provider_key, $providerOptions);
            $editingPost->fill([
                'campaign' => $campaign?->id,
                'labels' => $labels->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'account_id' => $account->id,
                'social_network' => (string) $account->provider_key,
                'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
                'module' => (string) $account->provider_key,
                'type' => ! empty($validated['media_items']) ? 'media' : 'text',
                'data' => [
                    'title' => trim((string) Str::limit($validated['caption'], 46, '...')),
                    'caption' => trim((string) $validated['caption']),
                    'media_type' => (string) $resolvedMediaType,
                    'medias' => collect((array) ($validated['media_items'] ?? []))->values()->all(),
                    'ai' => $aiPayload,
                    'options' => [
                        ...$providerOptions,
                        'schedule_mode' => $scheduleMode,
                        'schedule_slot_index' => 0,
                        'timezone' => $timezone,
                        'campaign_id' => $campaign?->id,
                        'campaign' => $campaign?->name ?? '',
                        'label_ids' => $labels->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                        'label_names' => $labels->pluck('name')->values()->all(),
                        'notes' => trim((string) ($validated['notes'] ?? '')),
                        'repeat_rule' => $repeatRule,
                        'repeat_until' => $repeatUntil,
                        'repeat_days' => $repeatDays,
                    ],
                ],
                'time_post' => $scheduleAt?->timestamp,
                'status' => $statusKey === 'draft' || $requiresApproval
                    ? PublishingPost::STATUS_DRAFT
                    : PublishingPost::STATUS_SCHEDULED,
                'result' => null,
                'changed' => $nowTimestamp,
            ]);
            $editingPost->save();

            if ($requiresApproval && $editingPost->team_id) {
                TeamPostReview::query()->updateOrCreate(
                    ['post_id' => $editingPost->id],
                    [
                        'team_id' => $editingPost->team_id,
                        'submitted_by_user_id' => (int) auth()->id(),
                        'status' => 'pending',
                        'submitted_at' => now(),
                        'decided_by_user_id' => null,
                        'decided_at' => null,
                        'decision_note' => null,
                        'metadata' => [
                            'requested_status' => PublishingPost::STATUS_SCHEDULED,
                            'schedule_mode' => $scheduleMode,
                            'schedule_slot_index' => 0,
                        ],
                    ],
                );
            } else {
                TeamPostReview::query()->where('post_id', $editingPost->id)->delete();
            }

            if ($publishImmediately) {
                try {
                    PublishScheduledPostJob::dispatchSync($editingPost->id);
                } catch (\Throwable $exception) {
                    $editingPost->refresh();

                    $this->addError(
                        'composer.account_ids',
                        trim((string) data_get($editingPost->result, 'error', '')) !== ''
                            ? trim((string) data_get($editingPost->result, 'error', ''))
                            : trim((string) $exception->getMessage())
                    );

                    $this->dispatch(
                        'app-toast',
                        type: 'danger',
                        title: __('Publishing failed'),
                        message: trim((string) data_get($editingPost->result, 'error', '')) !== ''
                            ? trim((string) data_get($editingPost->result, 'error', ''))
                            : trim((string) $exception->getMessage()),
                    );

                    $this->bumpCalendarCacheNonce();

                    return;
                }
            }
        } else {
            foreach ($selectedAccountIds as $accountId) {
                $account = $accounts->get((int) $accountId);

                if (! $account) {
                    continue;
                }

                foreach ($scheduleSlots as $slotIndex => $scheduleAt) {
                    $providerOptions = $this->normalizeNetworkOptionsForProvider(
                        (string) $account->provider_key,
                        (array) data_get($validated, 'network_options.'.$account->provider_key, [])
                    );
                    $resolvedMediaType = $validated['media_type']
                        ?? $this->inferMediaTypeFromNetworkOptions((string) $account->provider_key, $providerOptions);
                    $post = PublishingPost::query()->create([
                    'id_secure' => Str::random(32),
                    'user_id' => (int) auth()->id(),
                    'team_id' => $this->currentTeamId(),
                    'campaign' => $campaign?->id,
                    'labels' => $labels->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'account_id' => $account->id,
                    'social_network' => (string) $account->provider_key,
                    'category' => (string) ($account->capability_key ?: $account->category ?: $account->provider_key),
                    'module' => (string) $account->provider_key,
                    'function' => 'post',
                    'api_type' => 1,
                    'type' => ! empty($validated['media_items']) ? 'media' : 'text',
                    'method' => 'basic',
                    'query_id' => null,
                    'data' => [
                        'title' => trim((string) Str::limit($validated['caption'], 46, '...')),
                        'caption' => trim((string) $validated['caption']),
                            'media_type' => (string) $resolvedMediaType,
                        'medias' => collect((array) ($validated['media_items'] ?? []))->values()->all(),
                        'ai' => $aiPayload,
                            'options' => [
                            ...$providerOptions,
                            'schedule_mode' => $scheduleMode,
                            'schedule_slot_index' => $slotIndex,
                            'timezone' => $timezone,
                            'campaign_id' => $campaign?->id,
                            'campaign' => $campaign?->name ?? '',
                            'label_ids' => $labels->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                            'label_names' => $labels->pluck('name')->values()->all(),
                            'notes' => trim((string) ($validated['notes'] ?? '')),
                            'repeat_rule' => $repeatRule,
                            'repeat_until' => $repeatUntil,
                            'repeat_days' => $repeatDays,
                        ],
                    ],
                    'time_post' => $scheduleAt->timestamp,
                    'delay' => 0,
                    'repost_frequency' => 0,
                    'repost_until' => null,
                    'result' => null,
                    'tmp' => null,
                    'custom_data_1' => null,
                    'custom_data_2' => null,
                    'custom_data_3' => null,
                    'status' => $statusKey === 'draft' || $requiresApproval
                        ? PublishingPost::STATUS_DRAFT
                        : PublishingPost::STATUS_SCHEDULED,
                    'changed' => $nowTimestamp,
                    'created' => $nowTimestamp,
                ]);

                    if ($requiresApproval && $post->team_id) {
                        TeamPostReview::query()->create([
                        'team_id' => $post->team_id,
                        'post_id' => $post->id,
                        'submitted_by_user_id' => (int) auth()->id(),
                        'status' => 'pending',
                        'submitted_at' => now(),
                        'metadata' => [
                            'requested_status' => PublishingPost::STATUS_SCHEDULED,
                            'schedule_mode' => $scheduleMode,
                            'schedule_slot_index' => $slotIndex,
                        ],
                    ]);
                    }

                    if ($publishImmediately) {
                        $immediatePostIds[] = (int) $post->id;
                    }
                }
            }
        }

        foreach ($immediatePostIds as $postId) {
            try {
                PublishScheduledPostJob::dispatchSync($postId);
            } catch (\Throwable $exception) {
                $failedPost = PublishingPost::query()->find($postId);
                $errorMessage = trim((string) data_get($failedPost?->result, 'error', ''));

                $this->addError(
                    'composer.account_ids',
                    $errorMessage !== '' ? $errorMessage : trim((string) $exception->getMessage())
                );

                $this->dispatch(
                    'app-toast',
                    type: 'danger',
                    title: __('Publishing failed'),
                    message: $errorMessage !== '' ? $errorMessage : trim((string) $exception->getMessage()),
                );

                $this->bumpCalendarCacheNonce();

                return;
            }
        }

        if ($publishImmediately) {
            $immediateFailures = PublishingPost::query()
                ->whereIn('id', $editingPost ? [$editingPost->id] : $immediatePostIds)
                ->where('status', PublishingPost::STATUS_FAILED)
                ->get();

            if ($immediateFailures->isNotEmpty()) {
                $primaryFailure = $immediateFailures->first();
                $primaryError = trim((string) data_get($primaryFailure->result, 'error', ''));

                $this->addError(
                    'composer.account_ids',
                    $primaryError !== ''
                        ? $primaryError
                        : __('Publishing failed for one or more selected channels.')
                );

                $this->bumpCalendarCacheNonce();

                return;
            }
        }

        $this->bumpCalendarCacheNonce();
        $this->composerOpen = false;
        $this->dispatch('publishing-composer-scroll-unlock');
        $this->resetComposer();
        $this->dispatch('app-toast',
            type: 'success',
            title: $editingPost ? __('Post updated') : __('Composer saved'),
            message: $requiresApproval
                ? __('The post has been submitted for approval and will stay in draft until an admin or owner approves it.')
                : ($statusKey === 'draft'
                ? ($editingPost ? __('The post draft has been updated.') : __('The post has been saved to the publishing queue for the selected channels.'))
                : ($publishImmediately
                    ? ($editingPost ? __('The updated post is being published now.') : __('The post is being published now to the selected channels.'))
                    : ($editingPost ? __('The post schedule has been updated.') : __('The post has been scheduled for the selected channels and added to the calendar.'))))
        );
    }

    public function render(): View
    {
        abort_unless($this->publishingEnabled(), 404);

        $focus = $this->focus();
        if ($this->calendarView === 'week') {
            $rangeStart = $focus->copy()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($this->calendarView === 'calendar') {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        } else {
            $rangeStart = $focus->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        }

        $days = collect(CarbonPeriod::create($rangeStart, $rangeEnd))
            ->map(fn (Carbon $day): array => [
                'date' => $day->toDateString(),
                'label' => $day->format('D'),
                'day_number' => $day->format('j'),
                'long_label' => $day->format('D, M j'),
                'is_today' => $day->isToday(),
                'can_compose' => ! $day->lessThan(now($this->currentTimezone())->startOfDay()),
                'is_move_target' => ! $day->lessThan(now($this->currentTimezone())->startOfDay()),
                'is_current_period' => $this->calendarView === 'week'
                    ? true
                    : $day->month === $focus->month,
            ])
            ->values();

        $channels = $this->publishableAccountsQuery()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $providerRegistry = collect(channel_provider_cards())
            ->mapWithKeys(fn (array $provider): array => [(string) $provider['key'] => $provider]);

        $calendarItems = $this->cachedCalendarItems($channels, $providerRegistry, $rangeStart, $rangeEnd);

        $filteredItems = $this->applyCalendarFilters($calendarItems);
        $visibleDateKeys = $this->dateRangeKeys($rangeStart, $rangeEnd);
        $visibleFilteredItems = $filteredItems
            ->whereIn('date', $visibleDateKeys)
            ->values();

        $itemsByDate = $visibleFilteredItems
            ->sortBy('datetime')
            ->groupBy('date');

        $upcomingQueue = $visibleFilteredItems
            ->sortBy('datetime')
            ->take(8)
            ->values();

        $summary = [
            'channels' => $channels->count(),
            'pending' => $filteredItems->where('status_key', 'pending')->count(),
            'waiting_approve' => $filteredItems->where('status_key', 'waiting_approve')->count(),
            'processing' => $filteredItems->where('status_key', 'processing')->count(),
            'published' => $filteredItems->where('status_key', 'published')->count(),
            'failed' => $filteredItems->where('status_key', 'failed')->count(),
            'draft' => $filteredItems->where('status_key', 'draft')->count(),
            'providers' => $channels->pluck('provider_key')->filter()->unique()->count(),
            'today' => $filteredItems->where('date', now($this->currentTimezone())->toDateString())->count(),
        ];

        $providerFilters = $channels
            ->pluck('provider_key')
            ->filter()
            ->unique()
            ->map(fn (string $providerKey): array => [
                'key' => $providerKey,
                'label' => (string) data_get($providerRegistry, $providerKey.'.label', Str::headline($providerKey)),
                'icon' => (string) data_get($providerRegistry, $providerKey.'.icon', 'fa-light fa-share-nodes'),
                'color' => (string) data_get($providerRegistry, $providerKey.'.color', '#2563eb'),
                'count' => $filteredItems->where('provider_key', $providerKey)->count(),
            ])
            ->values();

        $selectedComposerAccountIds = collect((array) ($this->composer['account_ids'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();
        $previewAccountId = (int) ($this->composer['preview_account_id'] ?? 0);
        $resolvedPreviewAccountId = $selectedComposerAccountIds->contains($previewAccountId)
            ? $previewAccountId
            : (int) ($selectedComposerAccountIds->first() ?? 0);

        if ($resolvedPreviewAccountId > 0 && $previewAccountId !== $resolvedPreviewAccountId) {
            $this->composer['preview_account_id'] = (string) $resolvedPreviewAccountId;
        }

        $composerAccount = $channels->firstWhere('id', $resolvedPreviewAccountId);
        $composerProvider = $composerAccount
            ? ($providerRegistry->get((string) $composerAccount->provider_key) ?? [])
            : [];
        $composerNetworkConfig = $this->networkConfigForProvider((string) ($composerAccount?->provider_key ?? ''));
        $composerOptionAccounts = $channels
            ->whereIn('id', $selectedComposerAccountIds->all())
            ->groupBy(fn ($account) => (string) $account->provider_key)
            ->map(fn ($accounts) => $accounts->first())
            ->values();
        $campaigns = $this->composerCampaigns();
        $labels = $this->composerLabels();
        $composerAccountGroups = $this->composerAccountGroups($channels);
        $composerCaptionLibrary = $this->composerOpen ? $this->composerCaptionLibrary() : collect();
        $postPreviewAccountId = (int) ($this->postPreviewMeta['account_id'] ?? 0);
        $postPreviewAccount = $postPreviewAccountId > 0
            ? $channels->firstWhere('id', $postPreviewAccountId)
            : null;
        $postPreviewProvider = $postPreviewAccount
            ? ($providerRegistry->get((string) $postPreviewAccount->provider_key) ?? [])
            : [];

        return view('apppublishing::livewire.calendar', [
            'focus' => $focus,
            'days' => $days,
            'itemsByDate' => $itemsByDate,
            'summary' => $summary,
            'providerFilters' => $providerFilters,
            'campaignFilters' => $campaigns,
            'labelFilters' => $labels,
            'upcomingQueue' => $upcomingQueue,
            'filteredVisibleCount' => $visibleFilteredItems->count(),
            'composerAccounts' => $channels,
            'composerAccountGroups' => $composerAccountGroups,
            'selectedComposerAccounts' => $channels->whereIn('id', $selectedComposerAccountIds->all())->values(),
            'composerOptionAccounts' => $composerOptionAccounts,
            'composerAccount' => $composerAccount,
            'composerProvider' => $composerProvider,
            'composerNetworkConfig' => $composerNetworkConfig,
            'composerCampaigns' => $campaigns,
            'composerLabels' => $labels,
            'composerCaptionLibrary' => $composerCaptionLibrary,
            'postPreviewAccount' => $postPreviewAccount,
            'postPreviewProvider' => $postPreviewProvider,
            'calendarTitle' => $this->calendarView === 'week'
                ? __('Week of :date', ['date' => $focus->copy()->startOfWeek(Carbon::MONDAY)->format('M j, Y')])
                : $focus->format('F Y'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Publishing'),
            'fullWorkspace' => true,
            'fullWorkspacePaddingBottom' => false,
        ]);
    }

    protected function focus(): Carbon
    {
        try {
            return Carbon::parse($this->focusDate, $this->currentTimezone())->startOfDay();
        } catch (\Throwable) {
            return now($this->currentTimezone())->startOfDay();
        }
    }

    protected function databaseCalendarItems(Collection $channels, Collection $providerRegistry): Collection
    {
        $campaignMap = $this->campaignMap();
        $labelMap = $this->labelMap();

        $posts = PublishingPost::query()
            ->where('function', 'post')
            ->whereIn('status', [
                PublishingPost::STATUS_PROCESSING,
                PublishingPost::STATUS_SCHEDULED,
                PublishingPost::STATUS_PUBLISHED,
                PublishingPost::STATUS_FAILED,
            ])
            ->when(
                $this->currentTeamId(),
                fn ($query, $teamId) => $query->where('team_id', $teamId),
                fn ($query) => $query->ownedBy((int) auth()->id())
            )
            ->whereIn('account_id', $channels->pluck('id')->all())
            ->orderBy('time_post')
            ->get();

        $pendingReviews = TeamPostReview::query()
            ->whereIn('post_id', $posts->pluck('id')->all())
            ->where('status', 'pending')
            ->get()
            ->keyBy('post_id');

        return $posts
            ->map(function (PublishingPost $post) use ($channels, $providerRegistry, $campaignMap, $labelMap, $pendingReviews): ?array {
                $channel = $channels->firstWhere('id', (int) $post->account_id);

                if (! $channel) {
                    return null;
                }

                $providerKey = (string) $channel->provider_key;
                $provider = $providerRegistry->get($providerKey, []);
                $datetime = $post->time_post
                    ? Carbon::createFromTimestamp((int) $post->time_post, $this->currentTimezone())
                    : now($this->currentTimezone());
                $date = $datetime->toDateString();
                $time = $datetime->format('H:i');
                $postData = is_array($post->data) ? $post->data : [];
                $postOptions = is_array($postData['options'] ?? null) ? $postData['options'] : [];
                $campaignEntry = $campaignMap[(int) ($post->campaign ?? 0)] ?? null;
                $campaign = trim((string) ($postOptions['campaign'] ?? ($campaignEntry['name'] ?? '')));
                $tags = collect((array) ($postOptions['label_names'] ?? []))
                    ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                    ->map(fn (string $tag): string => trim($tag))
                    ->merge(
                        collect((array) ($post->labels ?? []))
                            ->map(fn ($id) => $labelMap[(int) $id] ?? null)
                            ->filter()
                    )
                    ->take(2)
                    ->values()
                    ->all();

                $publishResult = $this->normalizePublishResult($post->result);
                $resolvedPostUrl = $this->resolvePublishedPostUrl(
                    $publishResult,
                    $providerKey
                );

                $status = $this->portalStatusMeta((int) $post->status, $publishResult, $pendingReviews->has($post->id));
                $normalizedMediaItems = $this->normalizeCalendarMediaItems((array) ($postData['medias'] ?? []));
                $sourceKey = match (true) {
                    (string) ($post->method ?? '') === 'rss_schedule' || filled($postOptions['rss_schedule_id'] ?? null) => 'rss',
                    filled($postOptions['ai_publishing_run_id'] ?? null) || (string) ($post->custom_data_3 ?? '') === 'ai-publishing' => 'ai',
                    default => 'manual',
                };
                $sourceMeta = match ($sourceKey) {
                    'rss' => [
                        'label' => __('RSS'),
                        'surface' => 'rgba(245, 158, 11, 0.14)',
                        'text' => '#d97706',
                    ],
                    'ai' => [
                        'label' => __('AI'),
                        'surface' => 'rgba(99, 102, 241, 0.14)',
                        'text' => '#4f46e5',
                    ],
                    default => [
                        'label' => __('Manual'),
                        'surface' => 'rgba(16, 185, 129, 0.12)',
                        'text' => '#059669',
                    ],
                };

                return [
                    'post_int_id' => (int) $post->id,
                    'post_id' => (string) ($post->id_secure ?: $post->id),
                    'id' => (string) ($post->id_secure ?: $post->id),
                    'date' => $date,
                    'datetime' => $datetime->toDateTimeString(),
                    'time' => $time,
                    'title' => (string) ($postData['title'] ?? Str::limit((string) ($postData['caption'] ?? ''), 46, '...')),
                    'channel' => $channel->display_name,
                    'handle' => $channel->username ? '@'.$channel->username : null,
                    'provider' => (string) ($provider['label'] ?? Str::headline($providerKey)),
                    'provider_key' => $providerKey,
                    'provider_icon' => (string) ($provider['icon'] ?? 'fa-light fa-share-nodes'),
                    'provider_color' => (string) ($provider['color'] ?? '#2563eb'),
                    'tone' => $campaign !== '' ? Str::title($campaign) : __('Composer'),
                    'campaign' => $campaign,
                    'campaign_color' => $campaignEntry['color'] ?? null,
                    'source_key' => $sourceKey,
                    'source_label' => $sourceMeta['label'],
                    'source_surface' => $sourceMeta['surface'],
                    'source_text' => $sourceMeta['text'],
                    'status_key' => $status['key'],
                    'status' => $status['label'],
                    'excerpt' => (string) ($postData['caption'] ?? ''),
                    'avatar_url' => $channel->avatar_url,
                    'profile_url' => $channel->profile_url,
                    'post_url' => $resolvedPostUrl,
                    'open_error' => $publishResult['error'],
                    'open_state' => $publishResult['state'],
                    'media_type' => $this->resolveCalendarCardMediaType($providerKey, $postData, $normalizedMediaItems),
                    'media_items' => $normalizedMediaItems,
                    'tags' => $tags,
                    'campaign_id' => (int) ($post->campaign ?? 0),
                    'label_ids' => collect((array) ($post->labels ?? []))
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->values()
                        ->all(),
                    'engagement' => 0,
                    'approval' => $pendingReviews->has($post->id) ? 1 : 0,
                    'cover_gradient' => $this->coverGradient((string) ($provider['color'] ?? '#2563eb'), crc32((string) ($post->id_secure ?: $post->id))),
                    'initials' => Str::upper(Str::substr($channel->display_name, 0, 2)),
                    'can_edit' => $this->canEditPost($post),
                    'can_delete_remote' => $this->publishService->canDeleteRemote($post)
                        && filled(data_get($post->result, 'remote_post_id'))
                        && ! empty($resolvedPostUrl),
                ];
            })
            ->filter()
            ->values();
    }

    protected function normalizeCalendarMediaItems(array $mediaItems): array
    {
        return collect($mediaItems)
            ->map(function ($item): array {
                $media = is_array($item) ? $item : [];
                $source = trim((string) (
                    $media['previewUrl']
                    ?? $media['preview_url']
                    ?? $media['url']
                    ?? $media['embedUrl']
                    ?? $media['embed_url']
                    ?? $media['thumbnail']
                    ?? $media['thumbnail_url']
                    ?? ''
                ));

                if ($source === '') {
                    $source = $this->resolveCalendarMediaSourceFromFile($media) ?? '';
                }

                if ($source !== '') {
                    $media['url'] = trim((string) ($media['url'] ?? '')) !== '' ? $media['url'] : $source;
                    $media['previewUrl'] = trim((string) ($media['previewUrl'] ?? '')) !== '' ? $media['previewUrl'] : $source;
                    $media['embedUrl'] = trim((string) ($media['embedUrl'] ?? '')) !== '' ? $media['embedUrl'] : $source;
                    $media['thumbnail'] = trim((string) ($media['thumbnail'] ?? '')) !== '' ? $media['thumbnail'] : $source;
                }

                return $media;
            })
            ->values()
            ->all();
    }

    protected function resolveCalendarCardMediaType(string $providerKey, array $postData, array $mediaItems): string
    {
        $primaryMedia = is_array($mediaItems[0] ?? null) ? $mediaItems[0] : [];
        $primaryMime = strtolower((string) ($primaryMedia['mimeType'] ?? $primaryMedia['mime_type'] ?? ''));
        $primaryCategory = strtolower((string) ($primaryMedia['category'] ?? $primaryMedia['type'] ?? ''));
        $storedMediaType = strtolower(trim((string) ($postData['media_type'] ?? '')));
        $providerOptions = is_array($postData['options'] ?? null) ? $postData['options'] : [];
        $linkedinType = strtolower(trim((string) ($providerOptions['linkedin_post_type'] ?? '')));

        return match (true) {
            str_starts_with($primaryMime, 'video/'),
            in_array($primaryCategory, ['video', 'reel'], true),
            in_array($storedMediaType, ['video', 'reel'], true),
            str_starts_with($providerKey, 'linkedin_') && $linkedinType === 'video' => 'VIDEO',
            str_starts_with($primaryMime, 'image/'),
            $primaryCategory === 'image',
            in_array($storedMediaType, ['image', 'carousel'], true),
            str_starts_with($providerKey, 'linkedin_') && in_array($linkedinType, ['image', 'media'], true) => 'IMAGE',
            $storedMediaType !== '' => Str::upper($storedMediaType),
            in_array($linkedinType, ['text', 'link'], true) => Str::upper($linkedinType),
            default => 'TEXT',
        };
    }

    protected function resolveCalendarMediaSourceFromFile(array $media): ?string
    {
        static $fileById = [];
        static $fileBySecure = [];

        $idSecure = trim((string) ($media['idSecure'] ?? $media['id_secure'] ?? $media['file_id_secure'] ?? ''));
        if ($idSecure !== '') {
            if (! array_key_exists($idSecure, $fileBySecure)) {
                $fileBySecure[$idSecure] = AppFile::query()
                    ->where('id_secure', $idSecure)
                    ->first();
            }

            /** @var AppFile|null $file */
            $file = $fileBySecure[$idSecure];
            if ($file) {
                return route('portal.files.preview', $file);
            }
        }

        $fileId = (int) ($media['id'] ?? $media['file_id'] ?? 0);
        if ($fileId > 0) {
            if (! array_key_exists($fileId, $fileById)) {
                $fileById[$fileId] = AppFile::query()->find($fileId);
            }

            /** @var AppFile|null $file */
            $file = $fileById[$fileId];
            if ($file) {
                return route('portal.files.preview', $file);
            }
        }

        return null;
    }

    protected function applyCalendarFilters(Collection $items): Collection
    {
        return $items
            ->when($this->providerFilter !== 'all', fn ($nextItems) => $nextItems->where('provider_key', $this->providerFilter))
            ->when($this->statusFilter !== 'all', fn ($nextItems) => $nextItems->where('status_key', $this->statusFilter))
            ->when($this->campaignFilter !== 'all', function ($nextItems) {
                $targetCampaignId = (int) $this->campaignFilter;

                return $nextItems->filter(fn (array $item): bool => (int) ($item['campaign_id'] ?? 0) === $targetCampaignId);
            })
            ->when($this->labelFilter !== 'all', function ($nextItems) {
                $targetLabelId = (int) $this->labelFilter;

                return $nextItems->filter(function (array $item) use ($targetLabelId): bool {
                    $labelIds = collect((array) ($item['label_ids'] ?? []))
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->values()
                        ->all();

                    return in_array($targetLabelId, $labelIds, true);
                });
            })
            ->when($this->search !== '', function ($nextItems) {
                $needle = Str::lower(trim($this->search));

                return $nextItems->filter(function (array $item) use ($needle): bool {
                    return Str::contains(Str::lower(implode(' ', array_filter([
                        $item['title'],
                        $item['channel'],
                        $item['handle'],
                        $item['provider'],
                        $item['excerpt'],
                    ]))), $needle);
                });
            })
            ->values();
    }

    protected function dateRangeKeys(Carbon $rangeStart, Carbon $rangeEnd): array
    {
        return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
            ->map(fn (Carbon $day): string => $day->toDateString())
            ->values()
            ->all();
    }

    protected function sanitizeUtf8Value(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item) => $this->sanitizeUtf8Value($item))
                ->all();
        }

        if (! is_string($value)) {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $normalized = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,ISO-8859-1,Windows-1252');

        if (is_string($normalized) && mb_check_encoding($normalized, 'UTF-8')) {
            return $normalized;
        }

        $iconvNormalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($iconvNormalized) ? $iconvNormalized : '';
    }

    protected function cachedCalendarItems(
        Collection $channels,
        Collection $providerRegistry,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): Collection {
        $channelIds = $channels->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        $ownerId = $this->workspaceOwnerUserId();
        $teamId = $this->currentTeamId() ?? 0;
        $cacheKey = 'publishing:calendar-items:v3:'.implode(':', [
            $ownerId,
            $teamId,
            $this->publishingCalendarCacheVersion($ownerId, $teamId),
            $this->calendarCacheNonce,
            md5(json_encode($channelIds)),
        ]);

        $cachedItems = Cache::remember(
            $cacheKey,
            now()->addSeconds(30),
            fn (): array => $this->databaseCalendarItems($channels, $providerRegistry)->values()->all()
        );

        return collect($cachedItems);
    }

    protected function campaignMap(): array
    {
        $cacheKey = 'publishing:campaign-map:v1:'.$this->workspaceOwnerUserId();

        return Cache::remember($cacheKey, now()->addMinutes(2), function (): array {
            return PublishingCampaign::query()
                ->ownedBy($this->workspaceOwnerUserId())
                ->get(['id', 'name', 'color'])
                ->mapWithKeys(fn (PublishingCampaign $campaign): array => [
                    (int) $campaign->id => [
                        'name' => (string) $campaign->name,
                        'color' => (string) ($campaign->color ?: '#c9802a'),
                    ],
                ])
                ->all();
        });
    }

    protected function labelMap(): array
    {
        $cacheKey = 'publishing:label-map:v1:'.$this->workspaceOwnerUserId();

        return Cache::remember($cacheKey, now()->addMinutes(2), function (): array {
            return PublishingLabel::query()
                ->ownedBy($this->workspaceOwnerUserId())
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id): array => [(int) $id => (string) $name])
                ->all();
        });
    }

    protected function composerCampaigns(): Collection
    {
        $cacheKey = 'publishing:composer-campaigns:v1:'.$this->workspaceOwnerUserId();

        $cachedItems = Cache::remember($cacheKey, now()->addMinutes(2), function (): array {
            return PublishingCampaign::query()
                ->ownedBy($this->workspaceOwnerUserId())
                ->whereIn('status', ['active', 'draft'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (PublishingCampaign $campaign): array => [
                    'id' => (int) $campaign->id,
                    'name' => (string) $campaign->name,
                    'status' => (string) $campaign->status,
                ])
                ->all();
        });

        return collect($cachedItems)->map(fn (array $item): object => (object) $item)->values();
    }

    protected function composerLabels(): Collection
    {
        $cacheKey = 'publishing:composer-labels:v1:'.$this->workspaceOwnerUserId();

        $cachedItems = Cache::remember($cacheKey, now()->addMinutes(2), function (): array {
            return PublishingLabel::query()
                ->ownedBy($this->workspaceOwnerUserId())
                ->whereIn('status', ['active', 'draft'])
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (PublishingLabel $label): array => [
                    'id' => (int) $label->id,
                    'name' => (string) $label->name,
                    'status' => (string) $label->status,
                ])
                ->all();
        });

        return collect($cachedItems)->map(fn (array $item): object => (object) $item)->values();
    }

    protected function composerAccountGroups(Collection $channels): array
    {
        if (! class_exists(AccountGroup::class)) {
            return [];
        }

        $accessibleAccountIds = $channels
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        if ($accessibleAccountIds === []) {
            return [];
        }

        return \Modules\AppTeams\Support\PortalLibraryAccess::query(AccountGroup::query())
            ->where('status', 'active')
            ->with(['accounts' => fn ($query) => $query
                ->whereIn('social_accounts.id', $accessibleAccountIds)
                ->where('social_accounts.is_active', true)
                ->select('social_accounts.id')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (AccountGroup $group): array {
                $optionKeys = $group->accounts
                    ->pluck('id')
                    ->map(fn ($id): string => (string) $id)
                    ->values()
                    ->all();

                return [
                    'key' => (string) $group->id,
                    'label' => (string) $group->name,
                    'count' => count($optionKeys),
                    'optionKeys' => $optionKeys,
                ];
            })
            ->values()
            ->all();
    }

    protected function composerCaptionLibrary(): Collection
    {
        $cacheKey = 'publishing:composer-caption-library:v2:'.auth()->id().':'.session('portal_team_id',0);

        $cachedItems = Cache::remember($cacheKey, now()->addMinutes(2), function (): array {
            return \Modules\AppTeams\Support\PortalLibraryAccess::query(CaptionLibraryItem::query())
                ->where('status', 'active')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(160)
                ->get()
                ->map(fn (CaptionLibraryItem $caption): array => [
                    'id' => (int) $caption->id,
                    'name' => (string) $caption->name,
                    'content' => (string) $caption->content,
                    'notes' => (string) ($caption->notes ?? ''),
                    'sourceType' => (string) $caption->source_type,
                    'tags' => collect((array) ($caption->tags ?? []))
                        ->map(fn ($tag) => Str::lower(trim((string) $tag)))
                        ->filter()
                        ->values()
                        ->all(),
                    'updatedLabel' => (string) ($caption->updated_at?->diffForHumans() ?? ''),
                ])
                ->values()
                ->all();
        });

        return collect($cachedItems)->values();
    }

    protected function flushComposerReferenceCache(): void
    {
        $ownerId = $this->workspaceOwnerUserId();
        Cache::forget('publishing:composer-caption-library:v1:'.$ownerId);
        Cache::forget('publishing:composer-campaigns:v1:'.$ownerId);
        Cache::forget('publishing:composer-labels:v1:'.$ownerId);
        Cache::forget('publishing:campaign-map:v1:'.$ownerId);
        Cache::forget('publishing:label-map:v1:'.$ownerId);
    }

    protected function bumpCalendarCacheNonce(): void
    {
        $this->calendarCacheNonce++;
        $ownerId = $this->workspaceOwnerUserId();
        $teamId = $this->currentTeamId() ?? 0;
        $cacheKey = 'publishing:calendar-version:v1:'.$ownerId.':'.$teamId;
        Cache::forever($cacheKey, (int) Cache::get($cacheKey, 1) + 1);
    }

    protected function publishingCalendarCacheVersion(int $ownerId, int $teamId): int
    {
        return max(1, (int) Cache::get('publishing:calendar-version:v1:'.$ownerId.':'.$teamId, 1));
    }

    protected function publishableAccountsQuery()
    {
        $publishableCapabilityKeys = publishable_channel_capability_keys(auth()->user());

        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->where(function ($query) use ($publishableCapabilityKeys): void {
                $query->whereIn('capability_key', $publishableCapabilityKeys)
                    ->orWhere(function ($fallbackQuery) use ($publishableCapabilityKeys): void {
                        $fallbackQuery->whereNull('capability_key')
                            ->whereIn('provider_key', $publishableCapabilityKeys);
                    });
            });
    }

    protected function resetComposer(): void
    {
        $this->composer = [
            'editing_post_id' => '',
            'account_ids' => [],
            'preview_account_id' => '',
            'caption' => '',
            'media_type' => 'image',
            'media_items' => [],
            'network_options' => [],
            'schedule_mode' => 'immediately',
            'schedule_slots' => [$this->defaultScheduleSlot()],
            'repeat_rule' => 'none',
            'repeat_until' => '',
            'repeat_days' => [],
            'label_ids' => [],
            'campaign_id' => '',
            'notes' => '',
            'caption_library_name' => '',
            'caption_library_source_type' => 'manual',
            'ai_caption_variants' => [],
            'ai_repurpose_items' => [],
            'ai_review' => [],
            'ai_best_times' => [],
            'ai_tags' => [],
            'review_status' => '',
            'review_badge' => '',
            'review_note' => '',
            'review_submitted_at' => '',
            'review_submitted_by' => '',
            'review_decided_at' => '',
            'review_decided_by' => '',
            'media_refresh_token' => 0,
        ];
        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function defaultScheduleSlot(?DateTimeInterface $dateTime = null): string
    {
        return ($dateTime
            ? Carbon::instance($dateTime instanceof Carbon ? $dateTime : Carbon::parse($dateTime->format('c')))->setTimezone($this->currentTimezone())
            : now($this->currentTimezone())->addHour()->minute(0))
            ->format('Y-m-d\TH:i');
    }

    protected function resolveScheduleSlots(
        string $scheduleMode,
        array $rawSlots,
        string $repeatRule = 'none',
        ?string $repeatUntil = null,
        array $repeatDays = []
    ): Collection
    {
        if ($scheduleMode === 'immediately') {
            return collect([now($this->currentTimezone())]);
        }

        $baseSlots = collect($rawSlots)
            ->map(fn ($slot) => trim((string) $slot))
            ->filter()
            ->map(fn (string $slot): ?Carbon => $this->parseComposerDateTime($slot))
            ->filter()
            ->values();

        return $baseSlots;
    }

    protected function parseComposerDateTime(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timezone = $this->currentTimezone();
        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d',
            'd/m/Y h:i A',
            'd/m/Y H:i',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value, $timezone);

                if ($date instanceof Carbon && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Throwable) {
                //
            }
        }

        try {
            return Carbon::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function coverGradient(string $providerColor, int $seed): string
    {
        $fallbacks = [
            '#0f172a',
            '#1d4ed8',
            '#0f766e',
            '#be123c',
        ];
        $accent = $fallbacks[$seed % count($fallbacks)];

        return sprintf(
            'linear-gradient(135deg, %s 0%%, %s 48%%, rgba(255,255,255,0.92) 140%%)',
            $providerColor,
            $accent
        );
    }

    protected function defaultNetworkOptionsForProvider(string $providerKey): array
    {
        return $this->resolveNetworkOptionsForProvider($providerKey, [])['options'];
    }

    protected function defaultNetworkOptionsForAccount(int $accountId): array
    {
        if ($accountId <= 0) {
            return [];
        }

        return $this->defaultNetworkOptionsForProvider($this->providerKeyForAccountId($accountId));
    }

    protected function networkConfigForProvider(string $providerKey): array
    {
        return app(PublishingNetworkConfigRegistry::class)->get($providerKey, [
            'label' => Str::headline($providerKey ?: 'network'),
            'post_to_options' => [
                ['key' => 'feed', 'label' => __('Feed')],
            ],
        ]);
    }

    protected function normalizeNetworkOptionsForProvider(string $providerKey, array $options): array
    {
        return $this->resolveNetworkOptionsForProvider($providerKey, $options)['options'];
    }

    protected function inferMediaTypeFromNetworkOptions(string $providerKey, array $options): string
    {
        return $this->resolveNetworkOptionsForProvider($providerKey, $options)['media_type'];
    }

    protected function resolveNetworkOptionsForProvider(string $providerKey, array $options): array
    {
        return app(PublishingNetworkOptionsRegistry::class)->resolve(
            $providerKey,
            $options,
            $this->networkConfigForProvider($providerKey),
        );
    }

    protected function validateMediaSelectionForProvider(string $providerKey, array $options, array $mediaItems): ?string
    {
        return app(PublishingMediaValidationRegistry::class)->validate($providerKey, [
            'provider_key' => $providerKey,
            'options' => $options,
            'media_items' => $mediaItems,
            'caption' => trim((string) ($this->composer['caption'] ?? '')),
        ]);
    }

    protected function validateContentSelectionForProvider(string $providerKey, array $options): ?array
    {
        return app(PublishingContentValidationRegistry::class)->validate($providerKey, [
            'provider_key' => $providerKey,
            'options' => $options,
            'caption' => trim((string) ($this->composer['caption'] ?? '')),
            'media_items' => (array) ($this->composer['media_items'] ?? []),
        ]);
    }

    protected function resolveValidationFieldTarget(string $providerKey, string $target, string $fallbackField = 'composer.caption'): string
    {
        if ($target === '') {
            return $fallbackField;
        }

        return app(PublishingValidationFieldTargetRegistry::class)->resolve($providerKey, $target, $fallbackField);
    }

    protected function portalStatusMeta(int $status, array $publishResult, bool $waitingApprove = false): array
    {
        $key = $waitingApprove
            ? 'waiting_approve'
            : match ($status) {
            PublishingPost::STATUS_DRAFT => 'draft',
            PublishingPost::STATUS_PROCESSING => 'processing',
            PublishingPost::STATUS_PUBLISHED => 'published',
            PublishingPost::STATUS_FAILED => 'failed',
            PublishingPost::STATUS_SCHEDULED => $publishResult['state'] === 'published'
                ? 'published'
                : ($publishResult['state'] === 'failed' ? 'failed' : 'pending'),
            default => 'pending',
        };

        return [
            'key' => $key,
            'label' => (string) (self::PORTAL_STATUS_META[$key]['label'] ?? Str::headline($key)),
        ];
    }

    protected function normalizePublishResult(mixed $result): array
    {
        $payload = [];

        if (is_array($result)) {
            $payload = $result;
        } elseif (is_string($result) && trim($result) !== '') {
            $decoded = json_decode($result, true);
            $payload = is_array($decoded) ? $decoded : ['raw' => trim($result)];
        }

        $url = $this->extractPublishUrl($payload);
        $remotePostId = $this->extractRemotePostId($payload);
        $error = $this->extractPublishError($payload);
        $state = strtolower(trim((string) ($payload['state'] ?? data_get($payload, 'data.state', ''))));

        return [
            'url' => $url,
            'remote_post_id' => $remotePostId,
            'error' => $error,
            'state' => $state === 'published'
                ? 'published'
                : ($state === 'failed'
                    ? 'failed'
                    : ($url ? 'published' : ($error ? 'failed' : 'pending'))),
        ];
    }

    protected function resolvePublishedPostUrl(
        array $publishResult,
        string $providerKey
    ): ?string
    {
        $directUrl = trim((string) ($publishResult['url'] ?? ''));

        if ($directUrl !== '') {
            return $directUrl;
        }

        $remotePostId = trim((string) ($publishResult['remote_post_id'] ?? ''));

        if ($remotePostId === '') {
            return null;
        }

        return match ($providerKey) {
            'facebook' => 'https://www.facebook.com/'.$remotePostId,
            default => null,
        };
    }

    protected function extractPublishUrl(array $payload): ?string
    {
        $candidates = collect([
            $payload['url'] ?? null,
            $payload['link'] ?? null,
            $payload['post_url'] ?? null,
            $payload['permalink'] ?? null,
            $payload['permalink_url'] ?? null,
            data_get($payload, 'data.url'),
            data_get($payload, 'data.link'),
            data_get($payload, 'data.post_url'),
            data_get($payload, 'data.permalink'),
            data_get($payload, 'data.permalink_url'),
            data_get($payload, 'response.url'),
            data_get($payload, 'response.link'),
            data_get($payload, 'response.post_url'),
            data_get($payload, 'response.permalink'),
            data_get($payload, 'response.permalink_url'),
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '');

        return $candidates
            ->map(fn (string $value) => trim($value))
            ->first(fn (string $value) => Str::startsWith($value, ['http://', 'https://']));
    }

    protected function extractRemotePostId(array $payload): ?string
    {
        $candidates = collect([
            $payload['remote_post_id'] ?? null,
            $payload['post_id'] ?? null,
            $payload['id'] ?? null,
            data_get($payload, 'data.remote_post_id'),
            data_get($payload, 'data.post_id'),
            data_get($payload, 'data.id'),
            data_get($payload, 'response.remote_post_id'),
            data_get($payload, 'response.post_id'),
            data_get($payload, 'response.id'),
        ])->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '');

        $remoteId = $candidates
            ->map(fn ($value) => trim((string) $value))
            ->first();

        return $remoteId !== '' ? $remoteId : null;
    }

    protected function extractPublishError(array $payload): ?string
    {
        $candidates = collect([
            $payload['error'] ?? null,
            $payload['message'] ?? null,
            $payload['msg'] ?? null,
            data_get($payload, 'data.error'),
            data_get($payload, 'data.message'),
            data_get($payload, 'response.error'),
            data_get($payload, 'response.message'),
            data_get($payload, 'raw'),
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '');

        $message = $candidates
            ->map(fn (string $value) => trim(strip_tags($value)))
            ->first();

        return $message !== '' ? $message : null;
    }

    protected function currentTeamId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $sessionTeamId = (int) session('portal_team_id', 0);

        if ($sessionTeamId > 0) {
            $team = Team::query()
                ->whereKey($sessionTeamId)
                ->where(function ($query) use ($user) {
                    $query->where('owner_user_id', $user->id)
                        ->orWhereHas('members', fn ($memberQuery) => $memberQuery->where('users.id', $user->id));
                })
                ->first();

            if ($team) {
                return $team->id;
            }
        }

        return Team::query()->where('owner_user_id', $user->id)->value('id')
            ?: $user->teams()->value('teams.id');
    }

    protected function normalizeCalendarView(?string $view): ?string
    {
        $view = strtolower(trim((string) $view));

        return in_array($view, self::CALENDAR_VIEWS, true) ? $view : null;
    }

    protected function lastCalendarViewPreference(): ?string
    {
        return $this->normalizeCalendarView((string) session('publishing.calendar_view', ''));
    }

    protected function rememberCalendarViewPreference(string $view): void
    {
        $view = $this->normalizeCalendarView($view);

        if ($view === null) {
            return;
        }

        session(['publishing.calendar_view' => $view]);

    }

    protected function composerAiDefaultLanguage(): string
    {
        $user = auth()->user();

        if ($user) {
            $userLanguage = trim((string) data_get(
                AIStudioUserSetting::query()->forUser((int) $user->id)->value('settings'),
                'default_language',
                '',
            ));

            if ($userLanguage !== '') {
                return $userLanguage;
            }

            $workspaceLanguage = trim((string) data_get(
                AIStudioWorkspaceSetting::query()
                    ->ownedBy($this->workspaceOwnerUserId())
                    ->forTeam(TeamWorkspaceAccess::activeTeam($user)?->id)
                    ->value('settings'),
                'default_language',
                '',
            ));

            if ($workspaceLanguage !== '') {
                return $workspaceLanguage;
            }
        }

        return trim((string) get_option('ai_default_language', 'en')) ?: 'en';
    }

    protected function canCurrentUserPublishDirectly(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $teamId = $this->currentTeamId();

        if (! $teamId) {
            return true;
        }

        $team = Team::query()->find($teamId);

        if (! $team) {
            return true;
        }

        if ((int) $team->owner_user_id === (int) $user->id) {
            return true;
        }

        $member = $team->members()->where('users.id', $user->id)->first();

        if (! $member) {
            return false;
        }

        $permissions = $member->pivot?->permissions;

        if (is_string($permissions) && trim($permissions) !== '') {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($permissions) || $permissions === []) {
            $role = (string) ($member->pivot?->role ?? 'member');
            $permissions = match ($role) {
                'owner' => ['team.manage', 'member.manage', 'post.approve', 'post.publish', 'chat.manage', 'chat.participate'],
                'admin' => ['member.manage', 'post.approve', 'post.publish', 'chat.manage', 'chat.participate'],
                'editor' => ['post.create', 'chat.participate'],
                default => ['post.create', 'chat.participate'],
            };
        }

        return in_array('post.publish', $permissions, true);
    }

    protected function currentTimezone(): string
    {
        $user = auth()->user();

        return (string) ($user?->timezone ?: config('app.timezone', 'UTC'));
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function providerKeyForAccountId(int $accountId): string
    {
        if ($accountId <= 0) {
            return '';
        }

        return (string) SocialAccount::query()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->whereKey($accountId)
            ->value('provider_key');
    }

    protected function providerKeysForAccountIds(array $accountIds): array
    {
        return SocialAccount::query()
            ->where('created_by_user_id', $this->workspaceOwnerUserId())
            ->whereIn('id', collect($accountIds)->map(fn ($id) => (int) $id)->filter()->values()->all())
            ->pluck('provider_key')
            ->map(fn ($providerKey) => strtolower(trim((string) $providerKey)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function publishingEnabled(): bool
    {
        return TeamWorkspaceAccess::teamHasModule(TeamWorkspaceAccess::activeTeam(auth()->user()), 'publishing');
    }

    protected function resolveManagedPost(string $postId): ?PublishingPost
    {
        $query = PublishingPost::query()
            ->where('function', 'post')
            ->with('account');

        $query->when(
            $this->currentTeamId(),
            fn ($builder, $teamId) => $builder->where('team_id', $teamId),
            fn ($builder) => $builder->ownedBy((int) auth()->id())
        );

        return $query
            ->where(function ($builder) use ($postId) {
                $builder->where('id_secure', $postId);

                if (is_numeric($postId)) {
                    $builder->orWhere('id', (int) $postId);
                }
            })
            ->first();
    }

    protected function canEditPost(PublishingPost $post): bool
    {
        return in_array((int) $post->status, [
            PublishingPost::STATUS_DRAFT,
            PublishingPost::STATUS_SCHEDULED,
            PublishingPost::STATUS_FAILED,
        ], true);
    }

    protected function composerPayloadFromPost(PublishingPost $post, bool $editing = false): array
    {
        $data = is_array($post->data) ? $post->data : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $scheduleTimestamp = (int) ($post->time_post ?: now()->timestamp);
        $review = $editing ? $this->reviewSnapshotForPost($post) : [];

        return [
            'editing_post_id' => $editing ? (string) ($post->id_secure ?: $post->id) : '',
            'account_ids' => [(string) $post->account_id],
            'preview_account_id' => (string) $post->account_id,
            'caption' => (string) ($data['caption'] ?? ''),
            'media_type' => (string) ($data['media_type'] ?? 'image'),
            'media_items' => collect((array) ($data['medias'] ?? []))->values()->all(),
            'network_options' => [
                (string) ($post->social_network ?? $post->account?->provider_key ?? '') => $this->normalizeNetworkOptionsForProvider(
                    (string) ($post->social_network ?? $post->account?->provider_key ?? ''),
                    $options
                ),
            ],
            'schedule_mode' => ((string) ($options['schedule_mode'] ?? 'specific_days_times')) === 'immediately' ? 'immediately' : 'specific_days_times',
            'schedule_slots' => [Carbon::createFromTimestamp($scheduleTimestamp, $this->currentTimezone())->format('Y-m-d\TH:i')],
            'repeat_rule' => in_array((string) ($options['repeat_rule'] ?? 'none'), ['none', 'weekday', 'weekly_custom'], true)
                ? (string) ($options['repeat_rule'] ?? 'none')
                : 'none',
            'repeat_until' => filled($options['repeat_until'] ?? null)
                ? Carbon::parse((string) $options['repeat_until'], $this->currentTimezone())->format('Y-m-d')
                : '',
            'repeat_days' => collect((array) ($options['repeat_days'] ?? []))
                ->map(fn ($day) => strtolower(trim((string) $day)))
                ->filter(fn ($day) => in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true))
                ->values()
                ->all(),
            'label_ids' => collect((array) ($options['label_ids'] ?? $post->labels ?? []))->map(fn ($id) => (string) $id)->values()->all(),
            'campaign_id' => filled($post->campaign) ? (string) $post->campaign : '',
            'notes' => (string) ($options['notes'] ?? ''),
            'ai_caption_variants' => collect((array) data_get($data, 'ai.caption_variants', []))->values()->all(),
            'ai_repurpose_items' => collect((array) data_get($data, 'ai.repurpose_items', []))->values()->all(),
            'ai_review' => is_array(data_get($data, 'ai.review')) ? data_get($data, 'ai.review') : [],
            'ai_best_times' => [],
            'ai_tags' => collect((array) data_get($data, 'ai.tags', []))->values()->all(),
            'review_status' => (string) ($review['status'] ?? ''),
            'review_badge' => (string) ($review['badge'] ?? ''),
            'review_note' => (string) ($review['note'] ?? ''),
            'review_submitted_at' => (string) ($review['submitted_at'] ?? ''),
            'review_submitted_by' => (string) ($review['submitted_by'] ?? ''),
            'review_decided_at' => (string) ($review['decided_at'] ?? ''),
            'review_decided_by' => (string) ($review['decided_by'] ?? ''),
            'media_refresh_token' => 0,
        ];
    }

    protected function reviewSnapshotForPost(PublishingPost $post): array
    {
        $review = TeamPostReview::query()
            ->with(['submitter', 'decider'])
            ->where('post_id', $post->id)
            ->first();

        if (! $review) {
            return [];
        }

        return [
            'status' => (string) $review->status,
            'badge' => match ((string) $review->status) {
                'pending' => (string) __('Waiting approval'),
                'rejected' => (string) __('Rejected'),
                'approved' => (string) __('Approved'),
                default => (string) Str::headline((string) $review->status),
            },
            'note' => trim((string) ($review->decision_note ?? '')),
            'submitted_at' => (string) ($review->submitted_at?->format('d/m/Y H:i') ?? ''),
            'submitted_by' => (string) ($review->submitter?->fullname ?? $review->submitter?->name ?? ''),
            'decided_at' => (string) ($review->decided_at?->format('d/m/Y H:i') ?? ''),
            'decided_by' => (string) ($review->decider?->fullname ?? $review->decider?->name ?? ''),
        ];
    }

    protected function selectedComposerPlatforms(): array
    {
        return $this->publishableAccountsQuery()
            ->whereIn('id', collect((array) ($this->composer['account_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->all())
            ->pluck('provider_key')
            ->map(fn ($platform) => strtolower((string) $platform))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function composerAiPlatforms(): array
    {
        $platforms = $this->selectedComposerPlatforms();

        return $platforms !== [] ? $platforms : ['facebook'];
    }

    protected function composeComposerCaptionVariant(array $item): string
    {
        return trim(implode("\n\n", array_filter([
            trim((string) ($item['hook'] ?? '')),
            trim((string) ($item['caption'] ?? '')),
            trim((string) ($item['cta'] ?? '')),
        ])));
    }

    protected function composerCaptionLibraryName(string $content): string
    {
        $title = trim((string) Str::of(Str::before($content, "\n"))->squish());

        if ($title === '') {
            return __('Publishing Caption').' '.now()->format('Y-m-d H:i');
        }

        return Str::limit($title, 80, '');
    }

    protected function uniqueComposerCaptionSlug(string $value, int $userId): string
    {
        $baseSlug = Str::slug($value) ?: 'caption';
        $slug = $baseSlug;
        $counter = 2;

        while (CaptionLibraryItem::query()
            ->ownedBy($userId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    protected function composerImagePromptFromSeed(string $seed): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($seed)));
        $clean = Str::limit($clean, 900, '');

        return trim(implode("\n\n", array_filter([
            'Create a realistic editorial image based directly on this caption or story.',
            'Depict the actual subject, people, setting, conflict, or event described in the text.',
            'Do not create generic social media workflow graphics, dashboards, office desks, phones, laptops, charts, collage layouts, or marketing mockups unless they are explicitly mentioned in the text.',
            'Avoid visible text, headlines, captions, UI panels, watermarks, and split-layout compositions.',
            'Use one strong, coherent scene with believable details and documentary-style visual storytelling.',
            'Story to visualize: '.$clean,
        ])));
    }

    protected function composerMediaItemFromFile(AppFile $file): array
    {
        $previewUrl = route('portal.files.preview', $file);

        return [
            'id' => $file->id,
            'idSecure' => $file->id_secure,
            'name' => (string) $file->name,
            'note' => (string) ($file->note ?? ''),
            'url' => $previewUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'size' => (string) $file->humanSize(),
            'category' => (string) $file->category,
            'mimeType' => (string) $file->mime_type,
            'extension' => (string) $file->extension,
            'isImage' => (bool) $file->is_image,
        ];
    }
}
