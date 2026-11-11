<?php

namespace Modules\AppBulkPosts\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\AdminUser\Models\Team;
use Modules\AppBulkPosts\Models\BulkPostBatch;
use Modules\AppBulkPosts\Models\BulkPostRow;
use Modules\AppBulkPosts\Support\BulkPostAccess;
use Modules\AppBulkPosts\Support\BulkPostCsvService;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppPublishing\Models\PublishingPost;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class ProcessBulkPostBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $batchId, public int $limit = 50) {}

    public function handle(): array
    {
        $lock = Cache::lock('bulk-posts:batch:'.$this->batchId, 300);
        if (! $lock->get()) {
            return ['status' => 'busy', 'message' => __('This import is already processing.')];
        }
        $ownerLock = null;
        try {
            $batch = BulkPostBatch::find($this->batchId);
            if (! $batch || data_get($batch->meta, 'version') !== 2 || ! data_get($batch->meta, 'confirmed_at')) {
                return ['status' => 'blocked', 'message' => __('Review and confirm this file before importing.')];
            }
            $actor = $batch->owner;
            $team = $batch->team_id ? Team::with('owner')->find($batch->team_id) : null;
            if (($batch->team_id && ! $team) || ! BulkPostAccess::allowed($actor, $team)) {
                throw new \RuntimeException(__('Your access to this import has changed.'));
            }
            $owner = $team?->owner ?: $actor;
            $ownerLock = Cache::lock('bulk-posts:owner:'.$owner->id, 300);
            if (! $ownerLock->get()) {
                return ['status' => 'busy', 'message' => __('Another import is running in this workspace.')];
            }
            $accountIds = (array) data_get($batch->meta, 'selected_account_ids', []);
            if (! $accountIds || $batch->rows()->count() > BulkPostAccess::rowLimit($actor, $team) || $batch->rows()->count() * count($accountIds) > 10000) {
                throw new \RuntimeException(__('This import exceeds the current row or post limit.'));
            }
            $accounts = BulkPostAccess::accounts($actor, $team)->whereIn('id', $accountIds)->get()->keyBy('id');
            $batch->update(['status' => 'processing']);
            $attempted = 0;
            foreach ($batch->rows()->where('status', '!=', 'invalid')->orderBy('row_number')->lazyById(100) as $row) {
                foreach ($accountIds as $accountId) {
                    $state = data_get($row->payload, 'targets.'.$accountId.'.status');
                    if (in_array($state, ['created', 'failed'], true)) {
                        continue;
                    }
                    if ($attempted >= max(1, min(100, $this->limit))) {
                        break 2;
                    }
                    $attempted++;
                    try {
                        DB::transaction(function () use ($row, $accountId, $accounts, $owner, $actor, $team, $batch) {
                            $current = BulkPostRow::lockForUpdate()->findOrFail($row->id);
                            if (data_get($current->payload, 'targets.'.$accountId.'.status') === 'created') {
                                return;
                            }
                            $payload = $current->payload;
                            $account = $accounts->get($accountId);
                            if (! $account) {
                                throw new \RuntimeException(__('Channel disconnected, paused or no longer accessible.'));
                            }
                            if (filled($payload['media_url'] ?? null)) {
                                throw new \RuntimeException(__('This row requires media import, which is not supported yet.'));
                            }
                            $scheduled = ($payload['status_mode'] ?? 'draft') === 'schedule';
                            if ($scheduled && ! TeamWorkspaceAccess::hasPermission($actor, 'post.publish', $team)) {
                                throw new \RuntimeException(__('Publishing permission is required to schedule posts.'));
                            }
                            $date = filled($payload['schedule_time_iso'] ?? null) ? Carbon::parse($payload['schedule_time_iso']) : null;
                            if ($scheduled && (! $date || $date->lte(now()))) {
                                throw new \RuntimeException(__('The scheduled time has passed. Import this row again with a future time or as a draft.'));
                            }
                            if ($scheduled && ($issue = app(BulkPostCsvService::class)->contentError($account->provider_key, $payload['caption']))) {
                                throw new \RuntimeException($issue);
                            }
                            $campaign = data_get($batch->meta, 'campaign_id');
                            $labels = (array) data_get($batch->meta, 'label_ids', []);
                            if ($campaign && ! PublishingCampaign::ownedBy($owner->id)->whereKey($campaign)->exists()) {
                                throw new \RuntimeException(__('The selected campaign is no longer available.'));
                            }
                            if (PublishingLabel::ownedBy($owner->id)->whereIn('id', $labels)->count() !== count($labels)) {
                                throw new \RuntimeException(__('A selected label is no longer available.'));
                            }
                            $post = PublishingPost::where('custom_data_3', 'bulk-posts')->where('custom_data_2', (string) $row->id)->where('account_id', $accountId)->first();
                            if (! $post) {
                                $monthlyLimit = (int) $owner->planLimit('max_posts_per_month', -1);
                                if ($monthlyLimit >= 0 && PublishingPost::where('user_id', $owner->id)->where('created', '>=', now()->startOfMonth()->timestamp)->count() >= $monthlyLimit) {
                                    throw new \RuntimeException(__('The monthly post allowance has been reached.'));
                                }
                                $post = PublishingPost::create([
                                    'id_secure' => Str::random(32), 'user_id' => $owner->id, 'team_id' => $batch->team_id, 'campaign' => $campaign, 'labels' => $labels,
                                    'account_id' => $account->id, 'social_network' => $account->provider_key, 'category' => $account->capability_key ?: $account->category ?: $account->provider_key, 'module' => $account->provider_key,
                                    'function' => 'post', 'api_type' => 1, 'type' => 'text', 'method' => 'basic', 'query_id' => null,
                                    'data' => ['title' => Str::limit($payload['caption'], 46), 'caption' => $payload['caption'], 'media_type' => 'text', 'medias' => [], 'link' => $payload['link'] ?? null,
                                        'options' => ['post_to' => 'feed', 'timezone' => $payload['timezone'], 'campaign_id' => $campaign, 'label_ids' => $labels, 'link' => $payload['link'] ?? null, 'bulk_batch_id' => $batch->id, 'bulk_row_id' => $row->id]],
                                    'time_post' => $date?->timestamp ?? now()->timestamp, 'delay' => 0, 'repost_frequency' => 0, 'repost_until' => null, 'result' => null, 'tmp' => null,
                                    'custom_data_1' => (string) $batch->id, 'custom_data_2' => (string) $row->id, 'custom_data_3' => 'bulk-posts',
                                    'status' => $scheduled ? PublishingPost::STATUS_SCHEDULED : PublishingPost::STATUS_DRAFT, 'changed' => now()->timestamp, 'created' => now()->timestamp,
                                ]);
                            }
                            $payload['targets'][$accountId] = ['status' => 'created', 'post_id' => $post->id, 'channel' => $account->display_name ?: $account->provider_key];
                            $current->update(['payload' => $payload, 'publishing_post_id' => $post->id, 'processed_at' => now()]);
                        });
                    } catch (\Throwable $e) {
                        DB::transaction(function () use ($row, $accountId, $accounts, $e) {
                            $current = BulkPostRow::lockForUpdate()->findOrFail($row->id);
                            $payload = $current->payload;
                            if (data_get($payload, 'targets.'.$accountId.'.status') === 'created') {
                                return;
                            }
                            $message = $e instanceof QueryException ? __('The post could not be saved. Retry this destination.') : Str::limit($e->getMessage(), 350);
                            $payload['targets'][$accountId] = ['status' => 'failed', 'error' => $message, 'channel' => $accounts->get($accountId)?->display_name ?: __('Channel :id', ['id' => $accountId])];
                            $current->update(['payload' => $payload, 'processed_at' => now()]);
                        });
                    }
                    $row->refresh();
                }
            }
            $stats = self::summarize($batch);
            $status = $stats['pending_targets'] > 0 ? 'processing' : ($stats['failed_targets'] > 0 ? ($stats['created_posts'] > 0 ? 'partial' : 'failed') : ($stats['invalid_rows'] > 0 ? 'partial' : 'completed'));
            $meta = $batch->meta;
            unset($meta['last_error']);
            $batch->update(['status' => $status, 'stats' => $stats, 'meta' => $meta, 'last_processed_at' => now()]);
            $key = 'publishing:calendar-version:v1:'.$owner->id.':'.(int) $batch->team_id;
            Cache::forever($key, (int) Cache::get($key, 1) + 1);

            return ['status' => $status, 'message' => __('Created :created posts. :failed destinations failed; :invalid rows excluded.', ['created' => $stats['created_posts'], 'failed' => $stats['failed_targets'], 'invalid' => $stats['invalid_rows']])];
        } catch (\Throwable $e) {
            if (isset($batch)) {
                $meta = $batch->meta;
                $meta['last_error'] = Str::limit($e->getMessage(), 350);
                $batch->update(['status' => 'blocked', 'meta' => $meta]);
            }

            return ['status' => 'blocked', 'message' => __('Import paused. Review the error in its details.')];
        } finally {
            $ownerLock?->release();
            $lock->release();
        }
    }

    public static function summarize(BulkPostBatch $batch): array
    {
        $stats = ['total_rows' => 0, 'valid_rows' => 0, 'invalid_rows' => 0, 'created_posts' => 0, 'failed_targets' => 0, 'pending_targets' => 0, 'failed_rows' => 0, 'processed_rows' => 0, 'intended_posts' => 0];
        $ids = (array) data_get($batch->meta, 'selected_account_ids', []);
        foreach ($batch->rows()->get() as $row) {
            $stats['total_rows']++;
            if ($row->status === 'invalid') {
                $stats['invalid_rows']++;

                continue;
            }
            $stats['valid_rows']++;
            $failed = 0;
            $pending = 0;
            foreach ($ids as $id) {
                $state = data_get($row->payload, 'targets.'.$id.'.status');
                if ($state === 'created') {
                    $stats['created_posts']++;
                } elseif ($state === 'failed') {
                    $stats['failed_targets']++;
                    $failed++;
                } else {
                    $stats['pending_targets']++;
                    $pending++;
                }
            }
            $stats['intended_posts'] += count($ids);
            if ($failed) {
                $stats['failed_rows']++;
            }
            if (! $pending) {
                $stats['processed_rows']++;
            }
            $row->update(['status' => $pending ? 'valid' : ($failed ? 'failed' : 'imported')]);
        }

        return $stats;
    }
}
