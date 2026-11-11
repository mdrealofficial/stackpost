<?php

namespace App\Social\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AppChannels\Models\SocialAccount;
use App\Social\Models\SocialInboxConversation;
use App\Social\Models\SocialInboxMessage;
use App\Social\Models\SocialInboxWebhookEvent;
use App\Social\Support\SocialInboxProviderCapabilities;
use RuntimeException;

class MetaWebhookService
{
    public function acceptsProvider(string $provider): bool
    {
        return SocialInboxProviderCapabilities::supportsInbound($provider);
    }

    public function verify(string $provider, Request $request): ?string
    {
        if ((string) $request->query('hub_mode') !== 'subscribe') {
            return null;
        }

        $token = trim((string) $request->query('hub_verify_token'));

        return $token !== '' && hash_equals($this->webhookToken($provider), $token)
            ? (string) $request->query('hub_challenge')
            : null;
    }

    public function acceptsSignature(string $provider, Request $request): bool
    {
        $secret = $this->appSecret($provider);
        $signature = trim((string) $request->header('X-Hub-Signature-256'));

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function ingest(string $provider, array $payload): int
    {
        if (! $this->acceptsProvider($provider)) {
            throw new RuntimeException(__('This provider does not have a verified Inbox inbound connector.'));
        }

        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $event = SocialInboxWebhookEvent::query()->firstOrCreate(
            ['provider_key' => $provider, 'event_hash' => $hash],
            ['status' => 'received', 'payload' => $payload]
        );

        try {
            return DB::transaction(function () use ($event, $provider, $payload): int {
                $event = SocialInboxWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);
                if (in_array($event->status, ['processed', 'ignored'], true)) {
                    return 0;
                }
                $count = 0;
                foreach ((array) ($payload['entry'] ?? []) as $entry) {
                    foreach ($this->normalizeEntry($provider, (array) $entry) as $item) {
                        $count += $this->store($provider, $item);
                    }
                }

                $event->forceFill(['status' => $count > 0 ? 'processed' : 'ignored', 'processed_at' => now(), 'error_message' => null])->save();

                return $count;
            });
        } catch (\Throwable $exception) {
            $event->forceFill(['status' => 'failed', 'error_message' => Str::limit($exception->getMessage(), 500, '')])->save();
            throw $exception;
        }
    }

    /**
     * Resolve and persist the display name for an inbound participant when Meta
     * does not include it in the webhook payload (Messenger webhooks normally
     * only include the PSID/IGSID).
     */
    public function hydrateParticipant(SocialInboxConversation $conversation): void
    {
        if (filled($conversation->participant_name)) {
            return;
        }

        $account = $conversation->account()->first();
        $senderId = trim((string) $conversation->participant_external_id);
        if (! $account instanceof SocialAccount || $senderId === '') {
            return;
        }

        $profile = $this->resolveSenderProfile($account, $senderId);
        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            return;
        }

        $conversation->forceFill(['participant_name' => $name])->save();
        $conversation->messages()
            ->where('direction', 'inbound')
            ->where('sender_external_id', $senderId)
            ->whereNull('sender_name')
            ->update(['sender_name' => $name]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function normalizeEntry(string $provider, array $entry): array
    {
        $accountExternalId = trim((string) ($entry['id'] ?? ''));
        $items = [];

        foreach ((array) ($entry['messaging'] ?? []) as $message) {
            $message = (array) $message;
            $senderId = trim((string) data_get($message, 'sender.id'));
            $recipientId = trim((string) data_get($message, 'recipient.id'));
            $body = trim((string) (data_get($message, 'message.text') ?: data_get($message, 'postback.payload') ?: ''));
            // Echoes describe our own delivery, not a new customer interaction.
            if (data_get($message, 'message.is_echo') || ($accountExternalId !== '' && $senderId === $accountExternalId)) {
                continue;
            }
            $attachments = $this->attachments((array) data_get($message, 'message.attachments', []));

            if ($senderId === '' || ($body === '' && $attachments === [])) {
                continue;
            }

            $items[] = [
                'account_external_id' => $accountExternalId ?: $recipientId,
                'external_message_id' => (string) (data_get($message, 'message.mid') ?: $senderId.':'.data_get($message, 'timestamp')),
                'external_thread_id' => (string) (data_get($message, 'conversation.id') ?: $senderId),
                'interaction_type' => 'message',
                'sender_external_id' => $senderId,
                'sender_name' => null,
                'body' => $body !== '' ? $body : __('Attachment'),
                'occurred_at' => $this->timestamp(data_get($message, 'timestamp')),
                'payload' => ['attachments' => $attachments, 'reply_target' => ['recipient_id' => $senderId]],
            ];
        }

        foreach ((array) ($entry['changes'] ?? []) as $change) {
            $change = (array) $change;
            $value = (array) ($change['value'] ?? []);
            $field = strtolower(trim((string) ($change['field'] ?? 'interaction')));
            $interaction = ($field === 'feed' && ($value['item'] ?? '') === 'comment') || in_array($field, ['comments', 'live_comments'], true)
                ? 'comment' : (in_array($field, ['mention', 'mentions'], true) ? 'mention' : null);
            // Do not turn feed posts, reactions, edits or deletions into customer messages.
            if (! $interaction || ! in_array($value['verb'] ?? 'add', ['add'], true)) {
                continue;
            }
            $body = trim((string) ($value['message'] ?? $value['text'] ?? $value['comment_text'] ?? ''));
            $externalId = (string) ($value['comment_id'] ?? $value['id'] ?? $value['mid'] ?? '');
            $senderId = (string) ($value['from']['id'] ?? $value['sender']['id'] ?? $value['from_id'] ?? '');
            if ($senderId !== '' && $senderId === $accountExternalId) {
                continue;
            }

            if ($body === '' || ($externalId === '' && $senderId === '')) {
                continue;
            }

            $items[] = [
                'account_external_id' => $accountExternalId,
                'external_message_id' => $externalId ?: hash('sha256', json_encode($change)),
                // Keep individual comments separate: two customers on one post are not one conversation.
                'external_thread_id' => $interaction.':'.($externalId ?: hash('sha256', json_encode($change))),
                'interaction_type' => $interaction,
                'sender_external_id' => $senderId ?: null,
                'sender_name' => (string) ($value['from']['name'] ?? $value['sender']['name'] ?? ''),
                'body' => $body,
                'occurred_at' => $this->timestamp($value['created_time'] ?? $value['timestamp'] ?? null),
                'payload' => ['reply_target' => ['comment_id' => $interaction === 'comment' ? $externalId : ($value['comment_id'] ?? null), 'recipient_id' => $senderId], 'post_id' => $value['post_id'] ?? data_get($value, 'media.id'), 'attachments' => []],
            ];
        }

        return $items;
    }

    protected function store(string $provider, array $item): int
    {
        $accounts = SocialAccount::query()
            ->where('provider_key', $provider)
            ->where('external_id', (string) $item['account_external_id'])
            ->where('is_active', true)
            ->orderBy('id')->lockForUpdate()->get();
        $inserted = 0;
        foreach ($accounts as $account) {
            if (SocialInboxMessage::query()->where('social_account_id', $account->id)->where('external_message_id', $item['external_message_id'])->exists()) {
                continue;
            }
            $profile = $item['sender_name'] ? [] : $this->resolveSenderProfile($account, (string) ($item['sender_external_id'] ?? ''));
            $senderName = trim((string) ($item['sender_name'] ?: ($profile['name'] ?? '')));
            $messagePayload = (array) ($item['payload'] ?? []);
            if ($profile !== []) {
                $messagePayload['sender_profile'] = $profile;
            }

            $conversation = SocialInboxConversation::query()->firstOrCreate(
                ['social_account_id' => $account->id, 'external_thread_id' => (string) $item['external_thread_id']],
                [
                    'owner_user_id' => $account->created_by_user_id,
                    'team_id' => null,
                    'provider_key' => $provider,
                    'interaction_type' => $item['interaction_type'],
                    'participant_external_id' => $item['sender_external_id'],
                    'participant_name' => $senderName ?: null,
                    'status' => 'open',
                ]
            );
            $conversation = SocialInboxConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $message = SocialInboxMessage::query()->create([
                'conversation_id' => $conversation->id,
                'social_account_id' => $account->id,
                'provider_key' => $provider,
                'external_message_id' => $item['external_message_id'],
                'direction' => 'inbound',
                'interaction_type' => $item['interaction_type'],
                'delivery_status' => 'received',
                'sender_external_id' => $item['sender_external_id'],
                'sender_name' => $senderName ?: null,
                'body' => $item['body'],
                'payload' => $messagePayload,
                'occurred_at' => $item['occurred_at'],
            ]);

            $changes = [];
            $lastInbound = $conversation->last_inbound_message_id
                ? SocialInboxMessage::find($conversation->last_inbound_message_id) : null;
            if (!$lastInbound || !$lastInbound->occurred_at || $message->occurred_at->gte($lastInbound->occurred_at)) {
                $changes['last_inbound_message_id'] = $message->id;
            }
            if (! $conversation->last_message_at || $message->occurred_at->gte($conversation->last_message_at)) {
                $changes += [
                    'interaction_type' => $item['interaction_type'],
                    'participant_external_id' => $item['sender_external_id'] ?: $conversation->participant_external_id,
                    'participant_name' => $senderName ?: $conversation->participant_name,
                    'last_message_preview' => Str::limit($message->body, 240, ''),
                    'last_message_at' => $message->occurred_at ?: now(),
                    'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
                    'resolved_at' => null,
                ];
            }
            $conversation->forceFill($changes)->save();
            app(\App\Social\Automation\Engine::class)->capture($message);
            $inserted++;
        }

        return $inserted;
    }

    protected function attachments(array $items): array
    {
        return collect($items)->map(function ($item): ?array {
            $url = (string) data_get($item, 'payload.url', '');
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                return null;
            }

            return ['type' => (string) ($item['type'] ?? 'file'), 'url' => $url];
        })->filter()->take(20)->values()->all();
    }

    /** @return array{name:string,username:string,profile_pic:string} */
    public function resolveSenderProfile(SocialAccount $account, string $senderId): array
    {
        $senderId = trim($senderId);
        $token = trim((string) ($account->access_token ?: data_get($account->auth_data, 'page_payload.access_token')));
        if ($senderId === '' || $token === '') {
            return [];
        }

        $fields = $account->provider_key === 'instagram' ? 'name,username,profile_pic' : 'name,profile_pic';
        $key = 'social-inbox:sender-profile:'.$account->id.':'.$senderId;

        return Cache::remember($key, now()->addDay(), function () use ($account, $senderId, $fields, $token): array {
            $graphVersion = (string) (data_get(integration_item_state($account->provider_key), 'values.graph_version') ?: 'v25.0');
            try {
                $response = Http::timeout(8)->get("https://graph.facebook.com/{$graphVersion}/{$senderId}", [
                    'fields' => $fields,
                    'access_token' => $token,
                ]);
                if (! $response->successful()) {
                    return [];
                }

                $data = (array) $response->json();

                return array_filter([
                    'name' => trim((string) ($data['name'] ?? $data['username'] ?? '')),
                    'username' => trim((string) ($data['username'] ?? '')),
                    'profile_pic' => trim((string) ($data['profile_pic'] ?? '')),
                ], static fn ($value): bool => $value !== '');
            } catch (\Throwable) {
                return [];
            }
        });
    }

    protected function appSecret(string $provider): string
    {
        $state = integration_item_state($provider);

        return trim((string) data_get($state, 'values.app_secret', ''));
    }

    protected function webhookToken(string $provider): string
    {
        $state = integration_item_state($provider);

        return trim((string) data_get($state, 'values.webhook_verify_token', ''));
    }

    protected function timestamp(mixed $value): ?Carbon
    {
        if (is_numeric($value)) {
            // Meta sends Unix timestamps in UTC. Store the instant in the
            // application's configured timezone so Eloquent does not parse a
            // UTC database string as local time on installations using a
            // non-UTC APP_TIMEZONE (which would make automation timestamps
            // appear several hours before the rule activation time).
            $timestamp = abs((float) $value) >= 100000000000
                ? Carbon::createFromTimestampMs((int) $value, 'UTC')
                : Carbon::createFromTimestamp((int) $value, 'UTC');

            return $timestamp->setTimezone((string) config('app.timezone', 'UTC'));
        }

        return filled($value) ? Carbon::parse($value) : now();
    }
}
