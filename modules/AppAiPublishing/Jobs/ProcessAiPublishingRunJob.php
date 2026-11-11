<?php

namespace Modules\AppAiPublishing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AdminUser\Models\User;
use Modules\AppAiPublishing\Models\AiPublishingOccurrence;
use Modules\AppAiPublishing\Models\AiPublishingPrompt;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use Modules\AppAiPublishing\Support\AiPublishingScheduler;
use Modules\AppAiPublishing\Support\AiPublishingTextGenerator;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppFiles\Support\FileManager;
use Modules\AppFiles\Support\OnlineMediaSearchService;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class ProcessAiPublishingRunJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $runId, public bool $publishImmediately = false) {}

    public function handle(
        AiPublishingTextGenerator $textGenerator,
        AiPublishingScheduler $scheduler,
        AiContentStudioService $studio,
        OnlineMediaSearchService $onlineMediaSearch,
        FileManager $files
    ): array {
        $run = AiPublishingRun::find($this->runId);
        if (! $run) {
            return ['status' => 'missing', 'message' => __('Schedule no longer exists.')];
        }
        $lock = Cache::lock('ai-publishing:run:'.$run->id, 1800);
        if (! $lock->get()) {
            return ['status' => 'busy', 'message' => __('This schedule is already processing.')];
        }
        $ownerLock = null;
        $occurrence = null;
        try {
            $run->refresh();
            if ($run->isDemo()) {
                return ['status' => 'sample', 'message' => __('Edit this sample and save a real schedule first.')];
            }
            if ($run->hasEnded()) {
                $run->update(['status' => 'completed']);

                return ['status' => 'expired', 'message' => __('This schedule has ended. Update its date range first.')];
            }
            if ($run->status === 'paused') {
                return ['status' => 'paused', 'message' => __('Resume this schedule before generating content.')];
            }
            $owner = User::findOrFail($run->workspace_owner_user_id ?: $run->owner_user_id);
            if ($owner->plan && ! $owner->hasPlanFeature('ai_publishing')) {
                throw new \RuntimeException(__('Your plan does not include AI publishing.'));
            }
            if ($run->team_id && ! TeamWorkspaceAccess::teamHasModule(Team::find($run->team_id), 'ai_publishing')) {
                throw new \RuntimeException(__('AI publishing is disabled for this team.'));
            }
            $ownerLock = Cache::lock('ai-publishing:owner:'.$owner->id, 1800);
            if (! $ownerLock->get()) {
                return ['status' => 'busy', 'message' => __('Another AI schedule is using this workspace. Try again shortly.')];
            }
            $occurrence = $run->occurrences()->whereIn('status', ['pending', 'processing', 'failed'])->orderBy('slot_at')->first();
            if ($occurrence && ! $this->publishImmediately) {
                return ['status' => 'attention', 'message' => __('Review the unfinished attempt before retrying.')];
            }
            if (! $occurrence) {
                $slot = $scheduler->nextForRun($run);
                if (! $slot) {
                    throw new \RuntimeException(__('No valid time is available. Check the schedule days and times.'));
                }
                if (! $this->publishImmediately && $slot->gt(now())) {
                    return ['status' => 'not_due', 'message' => __('The next generation time has not arrived yet.')];
                }
                $ids = collect((array) $run->prompt_ids)->map(fn ($id) => (int) $id)->unique()->values();
                if ($ids->isEmpty()) {
                    throw new \RuntimeException(__('Choose at least one active prompt.'));
                }
                $promptId = $ids[(int) data_get($run->stats, 'prompt_cursor', 0) % $ids->count()];
                $prompt = AiPublishingPrompt::ownedBy($owner->id)->where('is_active', true)->find($promptId);
                if (! $prompt || trim($prompt->prompt_text) === '') {
                    throw new \RuntimeException(__('The next prompt is unavailable. Edit the schedule to choose another prompt.'));
                }
                $occurrence = $run->occurrences()->firstOrCreate(['slot_at' => $slot->timestamp], [
                    'owner_user_id' => $owner->id, 'prompt_id' => $promptId,
                    'payload' => ['prompt_text' => $prompt->prompt_text, 'generation_config' => (array) $run->generation_config, 'account_ids' => (array) $run->account_ids, 'post_ids' => [], 'completed_accounts' => []],
                ]);
            }
            if ($occurrence->status === 'success') {
                return ['status' => 'success', 'message' => __('This time slot has already been generated.')];
            }
            $payload = (array) $occurrence->payload;
            $generationConfig = (array) $payload['generation_config'];
            $targetIds = collect((array) $payload['account_ids'])->map(fn ($id) => (int) $id)->unique();
            $keys = publishable_channel_capability_keys($owner);
            $accounts = SocialAccount::where('created_by_user_id', $owner->id)->where('is_active', true)->whereIn('id', $targetIds)
                ->where(fn ($q) => $q->whereIn('capability_key', $keys)->orWhere(fn ($q) => $q->whereNull('capability_key')->whereIn('provider_key', $keys)))->get();
            if ($targetIds->isEmpty() || $accounts->count() !== $targetIds->count()) {
                throw new \RuntimeException(__('A selected channel is inactive or unavailable. Reconnect it before retrying, or skip this attempt.'));
            }
            $mediaSource = (string) ($generationConfig['include_media'] ?? 'disable');
            $textCost = isset($payload['generation']) ? 0 : max(0, credit_service()->costFor($owner, 'ai_publishing_generate_content'));
            $imageCost = $mediaSource === 'ai_image' && ! array_key_exists('medias', $payload) ? max(0, credit_service()->costFor($owner, 'ai_studio_generate_image')) : 0;
            $estimate = $textCost + $imageCost;
            $perRun = (int) data_get($run->generation_config, 'run_budget', 0);
            $monthly = (int) data_get($run->generation_config, 'monthly_budget', 0);
            $monthStart = now($run->timezone())->startOfMonth()->utc();
            $used = (int) $run->occurrences()->where('id', '!=', $occurrence->id)->get()->sum(fn ($attempt) => $attempt->creditsSince($monthStart->timestamp) + max(0, $attempt->credits_reserved - $attempt->credits_spent));
            if ($perRun > 0 && $occurrence->credits_spent + $estimate > $perRun) {
                throw new \RuntimeException(__('This attempt exceeds the per-run AI credit budget.'));
            }
            if ($monthly > 0 && $used + $occurrence->creditsSince($monthStart->timestamp) + $estimate > $monthly) {
                throw new \RuntimeException(__('This schedule has reached its monthly AI credit budget.'));
            }
            $completed = (array) ($payload['completed_accounts'] ?? []);
            $limit = $owner->plan ? (int) $owner->planLimit('max_ai_publishing_posts_per_month', 0) : -1;
            $count = PublishingPost::where('user_id', $owner->id)->where('custom_data_3', 'ai-publishing')->where('created', '>=', $monthStart->timestamp)->whereNull('data->options->ai_publishing_occurrence_id')->count();
            $count += AiPublishingOccurrence::where('owner_user_id', $owner->id)->get()->sum(fn ($attempt) => collect((array) data_get($attempt->payload, 'post_created_at', []))->filter(fn ($at) => (int) $at >= $monthStart->timestamp)->count());
            if ($limit >= 0 && $count + $targetIds->diff($completed)->count() > $limit) {
                throw new \RuntimeException(__('Your monthly AI post allowance is not enough for this attempt.'));
            }
            $occurrence->update(['status' => 'processing', 'error' => null, 'credits_reserved' => $occurrence->credits_spent + $estimate]);
            $run->update(['status' => 'processing']);
            if (! isset($payload['generation'])) {
                credit_service()->ensureCanConsume($owner, 'ai_publishing_generate_content');
                $generation = $textGenerator->generate($payload['prompt_text'], $generationConfig);
                DB::transaction(function () use ($owner, $textCost, $generation, &$payload, $occurrence) {
                    consume_credits($owner, 'ai_publishing_generate_content', ['feature' => 'ai-publishing.generate', 'metadata' => ['run_id' => $this->runId, 'occurrence_id' => $occurrence->id]]);
                    $payload['generation'] = $generation;
                    $payload['credit_charges'][] = ['at' => now()->timestamp, 'amount' => $textCost];
                    $occurrence->update(['payload' => $payload, 'credits_spent' => $occurrence->credits_spent + $textCost]);
                });
            }
            $generation = $payload['generation'];
            if (! array_key_exists('medias', $payload)) {
                $medias = [];
                if ($mediaSource === 'ai_image') {
                    $generated = $studio->generateWorkspaceImage($owner, (string) $generation['image_prompt']);
                    $medias[] = $this->serializeMediaFile($generated['file']);
                } elseif ($mediaSource !== 'disable') {
                    $file = $this->resolveExternalMediaFile($owner, $mediaSource, (string) $generation['image_prompt'], (string) $generation['caption'], (string) $generation['content'], $onlineMediaSearch, $files);
                    if (! $file) {
                        throw new \RuntimeException(__('No suitable media was found. Choose another source or retry this attempt.'));
                    }
                    $medias[] = $this->serializeMediaFile($file);
                }
                $payload['medias'] = $medias;
                if ($imageCost > 0) {
                    $payload['credit_charges'][] = ['at' => now()->timestamp, 'amount' => $imageCost];
                }
                $occurrence->update(['payload' => $payload, 'credits_spent' => $occurrence->credits_spent + $imageCost]);
            }
            foreach ($accounts as $account) {
                if (in_array((int) $account->id, $completed, true)) {
                    continue;
                }
                DB::transaction(function () use ($run, $owner, $account, $generation, $generationConfig, $occurrence, &$payload, &$completed) {
                    $caption = (string) $generation['caption'];
                    $media = (array) $payload['medias'];
                    $draft = ($generationConfig['delivery_mode'] ?? 'automatic') === 'draft';
                    $post = PublishingPost::create([
                        'id_secure' => Str::random(32), 'user_id' => $owner->id, 'team_id' => $run->team_id,
                        'campaign' => $run->campaign_id, 'labels' => (array) $run->label_ids, 'account_id' => $account->id,
                        'social_network' => $account->provider_key, 'category' => $account->capability_key ?: $account->category,
                        'module' => $account->provider_key, 'function' => 'post', 'api_type' => 1,
                        'type' => $media ? 'media' : 'text', 'method' => 'basic', 'time_post' => $occurrence->slot_at,
                        'delay' => 0, 'repost_frequency' => 0,
                        'custom_data_1' => (string) $run->id, 'custom_data_2' => (string) $occurrence->prompt_id, 'custom_data_3' => 'ai-publishing',
                        'status' => $draft ? PublishingPost::STATUS_DRAFT : PublishingPost::STATUS_SCHEDULED,
                        'changed' => now()->timestamp, 'created' => now()->timestamp,
                        'data' => ['title' => Str::limit($caption, 46), 'caption' => $caption, 'content' => $generation['content'],
                            'medias' => $media, 'media_type' => $media ? (data_get($media, '0.category') === 'video' ? 'video' : 'image') : 'text',
                            'options' => ['timezone' => $run->timezone(), 'ai_publishing_run_id' => $run->id, 'ai_publishing_prompt_id' => $occurrence->prompt_id, 'ai_publishing_occurrence_id' => $occurrence->id]],
                    ]);
                    $completed[] = (int) $account->id;
                    $payload['completed_accounts'] = $completed;
                    $payload['post_ids'][] = $post->id;
                    $payload['post_created_at'][(string) $post->id] = now()->timestamp;
                    $occurrence->update(['payload' => $payload]);
                });
            }
            DB::transaction(function () use ($run, $occurrence) {
                $occurrence->update(['status' => 'success', 'credits_reserved' => $occurrence->credits_spent, 'error' => null]);
                $stats = (array) $run->stats;
                $stats['last_slot_at'] = $occurrence->slot_at;
                $stats['prompt_cursor'] = (int) ($stats['prompt_cursor'] ?? 0) + 1;
                $stats['last_error_message'] = null;
                $run->update(['status' => 'queued', 'stats' => $stats, 'last_processed_at' => now()]);
            });
            $this->bumpPublishingCalendarCache($owner->id, (int) $run->team_id);

            return ['status' => 'success', 'message' => __('Generated :count posts. Open Publishing to review their delivery status.', ['count' => count($completed)])];
        } catch (\Throwable $exception) {
            $message = Str::limit(trim($exception->getMessage()), 500);
            if ($occurrence) {
                $occurrence->update(['status' => 'failed', 'error' => $message, 'credits_reserved' => $occurrence->credits_spent]);
            }
            $stats = (array) $run->stats;
            $stats['last_error_message'] = $message;
            $run->update(['status' => 'failed', 'stats' => $stats]);

            return ['status' => 'error', 'message' => $message];
        } finally {
            if ($ownerLock) {
                $ownerLock->release();
            }
            $lock->release();
        }
    }

    protected function serializeMediaFile(AppFile $file): array
    {
        $previewUrl = route('portal.files.preview', $file);

        return [
            'id' => $file->id,
            'idSecure' => $file->id_secure,
            'name' => $file->name,
            'url' => $previewUrl,
            'previewUrl' => $previewUrl,
            'embedUrl' => $previewUrl,
            'size' => $file->humanSize(),
            'category' => $file->category,
            'mimeType' => $file->mime_type,
            'extension' => $file->extension,
            'isImage' => (bool) $file->is_image,
        ];
    }

    protected function resolveExternalMediaFile(
        User $owner,
        string $includeMedia,
        string $imagePrompt,
        string $caption,
        string $content,
        OnlineMediaSearchService $search,
        FileManager $files
    ): ?AppFile {
        if (str_starts_with($includeMedia, 'folder:')) {
            $folderId = (int) Str::after($includeMedia, 'folder:');

            if ($folderId <= 0) {
                return null;
            }

            $recentFileIds = PublishingPost::query()
                ->where('user_id', $owner->id)
                ->latest('id')
                ->limit(50)
                ->get()
                ->flatMap(fn (PublishingPost $post) => collect((array) data_get($post->data, 'medias', []))->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $query = AppFile::query()
                ->ownedBy($owner)
                ->where('parent_id', $folderId)
                ->where('is_folder', false)
                ->where(function ($query): void {
                    $query->where('is_image', true)
                        ->orWhere('mime_type', 'like', 'video/%')
                        ->orWhere('category', 'video');
                });

            if ($recentFileIds !== []) {
                $query->whereNotIn('id', $recentFileIds);
            }

            $file = $query->inRandomOrder()->first();

            if ($file) {
                return $file;
            }

            return AppFile::query()
                ->ownedBy($owner)
                ->where('parent_id', $folderId)
                ->where('is_folder', false)
                ->where(function ($query): void {
                    $query->where('is_image', true)
                        ->orWhere('mime_type', 'like', 'video/%')
                        ->orWhere('category', 'video');
                })
                ->inRandomOrder()
                ->first();
        }

        $provider = trim($includeMedia);

        if ($provider === '') {
            return null;
        }

        foreach ($this->buildSearchKeywords($imagePrompt, $caption, $content) as $keyword) {
            $results = $search->search($keyword, $provider, str_contains($provider, 'video') ? 'video' : 'image');
            $first = collect($results)->first(fn ($item) => filled(data_get($item, 'full')));

            if (! is_array($first)) {
                continue;
            }

            return $files->storeRemoteFile($owner, (string) $first['full']);
        }

        return null;
    }

    protected function buildSearchKeywords(string $imagePrompt, string $caption, string $content): array
    {
        $base = trim($imagePrompt !== '' ? $imagePrompt : ($caption !== '' ? $caption : $content));
        $normalized = Str::of($base)
            ->lower()
            ->replaceMatches('/[^\pL\pN\s]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->value();

        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'your',
            'about', 'have', 'will', 'would', 'could', 'should', 'after', 'before',
            'trong', 'nhung', 'nhá»¯ng', 'cÃ¡c', 'cua', 'cá»§a', 'voi', 'vá»›i', 'cho', 'mot', 'má»™t',
            'ngay', 'ngÃ y', 'sau', 'tren', 'trÃªn', 'giua', 'giá»¯a', 'dua', 'Ä‘Æ°a',
        ];

        $tokens = collect(explode(' ', $normalized))
            ->map(fn ($token) => trim($token))
            ->filter(fn ($token) => $token !== '' && mb_strlen($token) >= 3 && ! in_array($token, $stopWords, true))
            ->unique()
            ->values();

        $candidates = collect();

        if ($normalized !== '') {
            $candidates->push(Str::limit($normalized, 80, ''));
        }

        foreach ([4, 3, 2, 1] as $size) {
            $slice = $tokens->take($size)->implode(' ');

            if ($slice !== '') {
                $candidates->push($slice);
            }
        }

        return $candidates
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function bumpPublishingCalendarCache(int $ownerUserId, int $teamId): void
    {
        $cacheKey = 'publishing:calendar-version:v1:'.$ownerUserId.':'.$teamId;
        Cache::forever($cacheKey, (int) Cache::get($cacheKey, 1) + 1);
    }
}
