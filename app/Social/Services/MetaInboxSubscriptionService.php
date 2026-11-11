<?php

namespace App\Social\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\AppChannels\Models\SocialAccount;

/**
 * Keeps Meta's provider-side webhook subscription aligned with an Inbox account.
 * Connecting a channel gives us a token for publishing; Inbox also needs the
 * Page/profile to explicitly subscribe to messaging webhook fields.
 */
class MetaInboxSubscriptionService
{
    public function ensure(SocialAccount $account): bool
    {
        if (! in_array($account->provider_key, ['facebook', 'instagram'], true)
            || ! $account->is_active
            || trim((string) $account->external_id) === '') {
            return false;
        }

        $metadata = (array) ($account->metadata ?? []);
        $subscription = (array) ($metadata['inbox_subscription'] ?? []);

        $fields = $account->provider_key === 'facebook'
            ? 'messages,messaging_postbacks,message_deliveries,message_reads,feed'
            : 'messages,comments,mentions';
        $requestedFields = array_values(array_filter(array_map('trim', explode(',', $fields))));
        $storedFields = array_values(array_filter(array_map('trim', explode(',', (string) ($subscription['fields'] ?? '')))));
        if (($subscription['status'] ?? null) === 'active'
            && ! array_diff($requestedFields, $storedFields)) {
            return true;
        }

        $token = trim((string) $account->access_token);
        if ($token === '') {
            return $this->mark($account, 'failed', 'Missing channel access token');
        }

        $graphVersion = (string) (data_get(integration_item_state($account->provider_key), 'values.graph_version') ?: 'v25.0');
        try {
            $response = Http::timeout(20)->asForm()->post(
                'https://graph.facebook.com/'.trim($graphVersion, '/').'/'.rawurlencode((string) $account->external_id).'/subscribed_apps',
                ['subscribed_fields' => $fields, 'access_token' => $token]
            );

            if ($response->successful() && (bool) data_get($response->json(), 'success', false)) {
                return $this->mark($account, 'active', null, $fields);
            }

            return $this->mark($account, 'failed', $this->safeError($response));
        } catch (\Throwable $exception) {
            return $this->mark($account, 'failed', $exception->getMessage());
        }
    }

    protected function mark(SocialAccount $account, string $status, ?string $error, ?string $fields = null): bool
    {
        $metadata = (array) ($account->metadata ?? []);
        $metadata['inbox_subscription'] = array_filter([
            'status' => $status,
            'fields' => $fields ?: data_get($metadata, 'inbox_subscription.fields'),
            'checked_at' => now()->toIso8601String(),
            'error' => $error ? mb_substr(trim($error), 0, 240) : null,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $account->forceFill(['metadata' => $metadata])->saveQuietly();

        return $status === 'active';
    }

    protected function safeError(Response $response): string
    {
        $message = (string) data_get($response->json(), 'error.message', 'Meta webhook subscription failed.');

        return 'HTTP '.$response->status().': '.$message;
    }
}
