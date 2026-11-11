<?php

namespace Modules\AppAutomation\Services;

use App\Support\Http\OutboundUrlPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Models\AutomationWebhook;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkspaceScope;
use Modules\AppPublishing\Models\PublishingPost;

class AutomationWebhookDispatcher
{
    public function __construct(
        protected OutboundUrlPolicy $outboundUrls,
        protected AutomationWorkspaceScope $workspaces,
        protected AutomationAccess $access,
    ) {}

    public function dispatchForPost(string $event, PublishingPost $post): void
    {
        $post->loadMissing('account');

        $postTeamId = $post->team_id === null ? null : (int) $post->team_id;
        $workspace = $this->workspaces->contextForOwnerAndTeam((int) $post->user_id, $postTeamId);

        if (! $workspace || ! $this->access->enabledForWorkspace($workspace->owner, $workspace->team)) {
            return;
        }

        $webhooks = $this->workspaces->apply(
            AutomationWebhook::query(),
            (int) $post->user_id,
            $postTeamId,
        )
            ->where('is_active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $this->payloadForPost($event, $post);

        foreach ($webhooks as $webhook) {
            if (! $this->supportsEvent($webhook, $event)) {
                continue;
            }

            $this->enqueue($webhook, $event, $payload);
        }
    }

    public const EVENTS = ['post.created', 'post.published', 'post.failed'];

    public function enqueue(AutomationWebhook $webhook, string $event, array $payload): AutomationLog
    {
        $log = AutomationLog::query()->create([
            'user_id' => $webhook->user_id, 'team_id' => $webhook->team_id,
            'webhook_id' => $webhook->id, 'direction' => 'outbound', 'event' => $event,
            'request_id' => (string) Str::uuid(), 'status' => 'queued', 'payload' => $payload,
            'response' => ['attempts' => 0],
        ]);
        $this->queue($log);
        return $log;
    }

    public function queue(AutomationLog $log): void
    {
        try {
            $readiness = app(AutomationQueueReadiness::class);
            $readiness->assertConfigured();
            \Modules\AppAutomation\Jobs\DeliverAutomationWebhook::dispatch((int) $log->id)
                ->onConnection($readiness->connectionName())->onQueue($readiness->queueName())->afterCommit();
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'response' => [...(array) $log->response, 'error' => 'Delivery could not be queued. Check the Automation worker configuration.']]);
            report($e);
        }
    }

    public function retry(AutomationLog $log): bool
    {
        $changed = AutomationLog::whereKey($log->id)->where('direction', 'outbound')->where('status', 'failed')
            ->update(['status' => 'queued']);
        if (!$changed) return false;
        $this->queue($log->fresh());
        return true;
    }

    // The same request ID and payload survive retries; receivers can deduplicate by request ID.
    public function deliver(int $logId, int $attempt = 1): bool
    {
        return \Illuminate\Support\Facades\Cache::lock('automation-webhook-delivery-'.$logId, 90)->block(3, function () use ($logId, $attempt) {
            $log = AutomationLog::find($logId);
            if (!$log || in_array($log->status, ['delivered', 'cancelled', 'failed'], true)) return true;
            $webhook = $log->webhook;
            $workspace = $webhook ? $this->workspaces->contextForOwnerAndTeam((int) $webhook->user_id, $webhook->team_id === null ? null : (int) $webhook->team_id) : null;
            if (!$webhook || !$webhook->is_active || !$workspace || !$this->access->enabledForWorkspace($workspace->owner, $workspace->team)) {
                $log->update(['status' => 'cancelled', 'response' => [...(array) $log->response, 'error' => 'The webhook or its workspace is no longer active.']]);
                return true;
            }
            $meta = (array) $log->response;
            $count = (int) ($meta['attempts'] ?? 0) + 1;
            $log->update(['status' => 'sending']);
            $timestamp = (string) now()->timestamp;
            $body = json_encode((array) $log->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) $webhook->signing_secret);
            $code = null; $error = null; $delivered = false; $retryable = true;
            try {
                $target = $this->outboundUrls->inspect((string) $webhook->url);
                $response = Http::withOptions($target->requestOptions(stream: true))->connectTimeout(5)->timeout(20)
                    ->acceptJson()->withHeaders([
                        'X-Automation-Event' => $log->event,
                        'X-Automation-Request-Id' => $log->request_id,
                        'X-Automation-Timestamp' => $timestamp,
                        'X-Automation-Signature' => $signature,
                    ])->withBody($body, 'application/json')->post($target->url);
                $code = $response->status(); $delivered = $response->successful();
                $retryable = $code === 408 || $code === 429 || $code >= 500;
                $error = $delivered ? null : ($response->redirect() ? 'Redirects are not followed. Use the final destination URL.' : 'Destination returned HTTP '.$code.'.');
                $response->toPsrResponse()->getBody()->close();
                $webhook->update(['last_sent_at' => now()]);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $error = 'The destination could not be reached or timed out.';
            } catch (\Throwable $e) {
                $error = 'The destination is blocked or delivery could not be completed.';
                $retryable = false;
            }
            $again = !$delivered && $retryable && $attempt < 3;
            $history = array_slice([...(array) ($meta['history'] ?? []), ['at' => now()->toIso8601String(), 'status_code' => $code, 'error' => $error]], -10);
            $log->update(['status' => $delivered ? 'delivered' : ($again ? 'retrying' : 'failed'), 'status_code' => $code,
                'response' => ['attempts' => $count, 'delivered' => $delivered, 'error' => $error, 'history' => $history,
                    'next_attempt_at' => $again ? now()->addSeconds($attempt === 1 ? 30 : 120)->toIso8601String() : null]]);
            return !$again;
        });
    }

    protected function supportsEvent(AutomationWebhook $webhook, string $event): bool
    {
        $events = collect((array) $webhook->events)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        return $events === [] || in_array($event, $events, true);
    }

    protected function payloadForPost(string $event, PublishingPost $post): array
    {
        $postData = is_array($post->data) ? $post->data : [];
        $result = is_array($post->result) ? $post->result : [];

        return [
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'post' => [
                'id' => (int) $post->id,
                'id_secure' => (string) ($post->id_secure ?? ''),
                'status' => (int) $post->status,
                'status_key' => match ((int) $post->status) {
                    PublishingPost::STATUS_DRAFT => 'draft',
                    PublishingPost::STATUS_PROCESSING => 'processing',
                    PublishingPost::STATUS_SCHEDULED => 'scheduled',
                    PublishingPost::STATUS_PUBLISHED => 'published',
                    PublishingPost::STATUS_FAILED => 'failed',
                    default => 'unknown',
                },
                'caption' => (string) ($postData['caption'] ?? ''),
                'title' => (string) ($postData['title'] ?? ''),
                'scheduled_at' => $post->time_post ? now()->setTimestamp((int) $post->time_post)->toIso8601String() : null,
                'account_id' => (int) ($post->account_id ?? 0),
                'provider_key' => (string) ($post->social_network ?? ''),
                'remote_post_id' => (string) ($result['remote_post_id'] ?? ''),
                'url' => $result['url'] ?? null,
                'error' => $result['error'] ?? null,
            ],
            'account' => $post->account ? [
                'id' => (int) $post->account->id,
                'display_name' => (string) ($post->account->display_name ?? ''),
                'username' => (string) ($post->account->username ?? ''),
                'provider_key' => (string) ($post->account->provider_key ?? ''),
                'capability_key' => (string) ($post->account->capability_key ?? ''),
            ] : null,
        ];
    }
}
