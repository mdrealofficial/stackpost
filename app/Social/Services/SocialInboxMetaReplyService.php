<?php

namespace App\Social\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Modules\AppChannels\Models\SocialAccount;
use App\Social\Models\SocialInboxConversation;
use App\Social\Models\SocialInboxMessage;
use App\Social\Support\SocialInboxProviderCapabilities;
use RuntimeException;

class SocialInboxMetaReplyService
{
    public function send(SocialInboxConversation $conversation, string $body, int $authorUserId, ?string $requestKey = null, ?int $inboundId = null, bool $automated = false, ?UploadedFile $attachment = null): SocialInboxMessage
    {
        $lock = Cache::lock('inbox:reply:'.$conversation->id, 90);
        if (! $lock->get()) {
            throw new RuntimeException(__('A reply is already being sent. Wait for its delivery status.'));
        }
        try {
            $conversation->refresh();
            if ($automated && ($conversation->assigned_user_id || ($conversation->automation_paused_until && $conversation->automation_paused_until->isFuture()))) {
                throw new RuntimeException(__('A team member has taken over this conversation.'));
            }
            if (!$automated) {
                $conversation->update(['automation_paused_until' => now()->addDay()]);
            }
            return $this->sendOnce($conversation, $body, $authorUserId, $requestKey, $inboundId, $automated, $attachment);
        } finally {
            $lock->release();
        }
    }

    protected function sendOnce(SocialInboxConversation $conversation, string $body, int $authorUserId, ?string $requestKey, ?int $inboundId = null, bool $automated = false, ?UploadedFile $attachment = null): SocialInboxMessage
    {
        $body = trim($body);
        if ($body === '' && ! $attachment) {
            throw new RuntimeException(__('Add a message or attachment before sending.'));
        }

        $account = $conversation->account()->first();
        if (! $account instanceof SocialAccount || ! $account->is_active) {
            throw new RuntimeException(__('This channel is no longer connected.'));
        }

        if (! SocialInboxProviderCapabilities::supportsReply((string) $account->provider_key)) {
            throw new RuntimeException(__('Replies are not available for this channel in Social Inbox yet.'));
        }

        $lastInbound = $conversation->messages()
            ->where('direction', 'inbound')
            ->when($inboundId, fn ($query) => $query->whereKey($inboundId))
            ->latest('occurred_at')
            ->first();

        if (! $lastInbound) {
            throw new RuntimeException(__('There is no inbound interaction to reply to.'));
        }

        if ($lastInbound->interaction_type === 'message' && $automated
            && ((int)$conversation->last_inbound_message_id !== (int)$lastInbound->id
                || !$lastInbound->occurred_at || $lastInbound->occurred_at->lt(now()->subHours(24)))) {
            throw new RuntimeException(__('The message is no longer eligible for an automatic reply.'));
        }

        if (! $this->hasReplyTarget($lastInbound)) {
            throw new RuntimeException(__('This interaction has no supported reply target. Open the original conversation in the social network.'));
        }
        if ($attachment && $lastInbound->interaction_type !== 'message') {
            throw new RuntimeException(__('Media attachments are available for private messages. Public comment replies support text only.'));
        }
        $attachmentKey = $attachment ? ':'.$attachment->getClientOriginalName().':'.$attachment->getSize().':'.$attachment->getMimeType() : '';
        $requestKey ??= hash('sha256', $conversation->id.':'.$authorUserId.':'.$lastInbound->id.':'.$body.$attachmentKey);
        if ($attachment && strlen($requestKey) < 64) {
            $requestKey = hash('sha256', $requestKey.$attachmentKey);
        }
        if (strlen($requestKey) > 64 || $requestKey === '') {
            throw new RuntimeException(__('Invalid reply request. Reload the conversation.'));
        }
        $attachmentPayload = $this->storeAttachment($attachment);
        $draft = DB::transaction(function () use ($conversation, $account, $authorUserId, $body, $requestKey, $lastInbound, $attachmentPayload): SocialInboxMessage {
            SocialInboxConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $existing = SocialInboxMessage::query()->where('social_account_id', $account->id)->where('request_key', $requestKey)->first();
            if ($existing) {
                $expectedBody = $body !== '' ? $body : __('Attachment');
                if ($existing->conversation_id != $conversation->id || $existing->author_user_id != $authorUserId || $existing->body !== $expectedBody) {
                    throw new RuntimeException(__('This draft has changed. Reopen the conversation before sending.'));
                }
                if ($existing->delivery_status === 'sent') {
                    return $existing;
                }
                throw new RuntimeException(__('This send attempt already exists. Review its delivery status before starting another reply.'));
            }
            if ($conversation->messages()->where('direction', 'outbound')->whereIn('delivery_status', ['sending', 'unknown'])->exists()) {
                throw new RuntimeException(__('A previous reply has an unconfirmed delivery. Check the social network and reconcile that reply before sending again.'));
            }
            if (trim((string) ($account->access_token ?: data_get($account->auth_data, 'page_payload.access_token'))) === '') {
                throw new RuntimeException(__('Reconnect this account before replying. Its access token is missing.'));
            }

            return SocialInboxMessage::query()->create([
                'conversation_id' => $conversation->id,
                'social_account_id' => $account->id,
                'provider_key' => $account->provider_key,
                'direction' => 'outbound',
                'interaction_type' => $lastInbound->interaction_type,
                'delivery_status' => 'sending',
                'author_user_id' => $authorUserId,
                'body' => $body !== '' ? $body : __('Attachment'),
                'request_key' => $requestKey,
                'occurred_at' => now(),
                'payload' => array_filter([
                    'in_reply_to_message_id' => $lastInbound->id,
                    'attachments' => $attachmentPayload ? [$attachmentPayload] : null,
                ], static fn ($value): bool => $value !== null),
            ]);
        });
        if ($draft->delivery_status === 'sent') {
            return $draft;
        }

        try {
            $response = $this->postReply($account, $lastInbound, $body, $attachmentPayload);
            $externalId = trim((string) ($response['id'] ?? $response['message_id'] ?? ''));

            if ($externalId === '') {
                throw new RuntimeException((string) data_get($response, 'error.message', __('Meta did not return a reply identifier.')));
            }

            DB::transaction(function () use ($conversation, $draft, $externalId, $lastInbound, $response, $body, $attachmentPayload): void {
                $current = SocialInboxConversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $sentPayload = (array) ($draft->payload ?? []);
                $sentPayload['response'] = $response;
                $draft->forceFill([
                    'external_message_id' => $externalId,
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                    'payload' => $sentPayload,
                ])->save();
                $preview = $body !== '' ? $body : (string) ($attachmentPayload['name'] ?? __('Attachment'));
                if (! $current->last_message_at || $draft->occurred_at->gte($current->last_message_at)) {
                    $current->forceFill(['last_message_preview' => $preview, 'last_message_at' => $draft->occurred_at, 'status' => 'pending', 'resolved_at' => null])->save();
                }
            });

            return $draft;
        } catch (\Throwable $exception) {
            $rejected = $exception instanceof RequestException && $exception->response->status() < 500 && $exception->response->status() !== 408;
            $reason = $rejected
                ? __('The network rejected this reply. Check the account connection and permissions before trying again.')
                : __('Delivery could not be confirmed. Check the social network before sending again.');
            $draft->forceFill(['delivery_status' => $rejected ? 'failed' : 'unknown', 'failure_reason' => $reason])->save();
            throw new RuntimeException($reason, previous: $exception);
        }
    }

    public function hasReplyTarget(SocialInboxMessage $inbound): bool
    {
        return $inbound->interaction_type === 'message'
            ? filled(data_get($inbound->payload, 'reply_target.recipient_id', $inbound->sender_external_id))
            : filled(data_get($inbound->payload, 'reply_target.comment_id'));
    }

    protected function postReply(SocialAccount $account, SocialInboxMessage $inbound, string $body, ?array $attachment = null): array
    {
        $token = trim((string) ($account->access_token ?: data_get($account->auth_data, 'page_payload.access_token')));
        $graphVersion = (string) (data_get(integration_item_state($account->provider_key), 'values.graph_version') ?: 'v25.0');
        $target = (array) data_get($inbound->payload, 'reply_target', []);

        if ($token === '') {
            throw new RuntimeException(__('This connected account does not contain a valid Meta access token. Reconnect it before replying.'));
        }

        if ($inbound->interaction_type === 'comment' || $inbound->interaction_type === 'mention') {
            $commentId = trim((string) ($target['comment_id'] ?? ''));
            if ($commentId === '') {
                throw new RuntimeException(__('The original comment identifier is unavailable.'));
            }

            $path = $account->provider_key === 'instagram' ? "{$commentId}/replies" : "{$commentId}/comments";
            $response = Http::asForm()->timeout(30)->post("https://graph.facebook.com/{$graphVersion}/{$path}", [
                'message' => $body,
                'access_token' => $token,
            ]);
        } elseif ($inbound->interaction_type === 'message') {
            $recipientId = trim((string) ($target['recipient_id'] ?? $inbound->sender_external_id));
            if ($recipientId === '') {
                throw new RuntimeException(__('The recipient identifier is unavailable.'));
            }

            $senderId = (string) $account->external_id;
            $request = [
                'recipient' => ['id' => $recipientId],
                'message' => $attachment
                    ? ['attachment' => ['type' => $attachment['type'], 'payload' => ['url' => $attachment['url'], 'is_reusable' => false]]]
                    : ['text' => $body],
                'access_token' => $token,
            ]; 
            if ($account->provider_key === 'facebook') {
                $request['messaging_type'] = 'RESPONSE';
            }
            $response = Http::asJson()->timeout(30)->post("https://graph.facebook.com/{$graphVersion}/{$senderId}/messages", $request);
        } else {
            throw new RuntimeException(__('This interaction type does not support replies yet.'));
        }

        $payload = (array) $response->json();
        $response->throw();

        return $payload;
    }

    /** @return array{type:string,url:string,name:string,mime_type:string}|null */
    protected function storeAttachment(?UploadedFile $attachment): ?array
    {
        if (! $attachment) {
            return null;
        }

        if (! $attachment->isValid() || $attachment->getSize() > 25 * 1024 * 1024) {
            throw new RuntimeException(__('Attachments must be valid files up to 25 MB.'));
        }

        $mime = strtolower((string) $attachment->getMimeType());
        $type = str_starts_with($mime, 'image/') ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));
        $path = $attachment->store('social-inbox/replies', 'public');
        $sourcePath = (string) Storage::disk('public')->path($path);
        $publicStorageRoot = public_path('storage');
        $diskStorageRoot = Storage::disk('public')->path('');
        $publicRootReal = realpath($publicStorageRoot);
        $diskRootReal = realpath($diskStorageRoot);

        // Some shared hosts do not allow the usual public/storage symlink.
        // Mirror the stored file into public/storage so Meta can fetch it.
        if ($publicRootReal === false || $diskRootReal === false || $publicRootReal !== $diskRootReal) {
            $publicPath = $publicStorageRoot.DIRECTORY_SEPARATOR.ltrim($path, '/\\');
            $publicDirectory = dirname($publicPath);
            if (! is_dir($publicDirectory) && ! @mkdir($publicDirectory, 0775, true) && ! is_dir($publicDirectory)) {
                throw new RuntimeException(__('The attachment could not be made available to the social network.'));
            }
            if ($sourcePath !== $publicPath && ! @copy($sourcePath, $publicPath)) {
                throw new RuntimeException(__('The attachment could not be made available to the social network.'));
            }
        }

        // Build URLs from the active web request when available so local
        // subfolder installs and production subdomains both point at the
        // actual public storage path. Queue/CLI sends still use APP_URL.
        // Serve attachments through the application's public storage controller
        // instead of relying on a /storage symlink. This works when the app is
        // installed in a subdirectory (for example /stackposts) and on hosts
        // where the document root does not expose public/storage directly.
        $url = route('media.public', ['disk' => 'public', 'path' => ltrim($path, '/')]);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $url = url($url);
        }
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException(__('The attachment could not be made available to the social network.'));
        }

        return [
            'type' => $type,
            'url' => $url,
            'name' => (string) $attachment->getClientOriginalName(),
            'mime_type' => $mime,
        ];
    }
}
