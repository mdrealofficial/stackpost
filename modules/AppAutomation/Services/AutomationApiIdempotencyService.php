<?php

namespace Modules\AppAutomation\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\AppAutomation\Models\AutomationApiIdempotencyKey;
use Modules\AppAutomation\Models\AutomationApiKey;

class AutomationApiIdempotencyService
{
    /**
     * @return array{state: 'reserved'|'replay'|'in_progress'|'payload_conflict', record: AutomationApiIdempotencyKey}
     */
    public function reserve(AutomationApiKey $apiKey, string $idempotencyKey, array $payload): array
    {
        $requestHash = $this->requestHash($payload);

        try {
            $record = AutomationApiIdempotencyKey::query()->create([
                'api_key_id' => (int) $apiKey->id,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => 'processing',
                'status_code' => null,
                'response' => null,
                'expires_at' => now()->addDays((int) config('modules.appautomation.api.idempotency_ttl_days', 7)),
            ]);

            return ['state' => 'reserved', 'record' => $record];
        } catch (QueryException $exception) {
            $record = AutomationApiIdempotencyKey::query()
                ->where('api_key_id', (int) $apiKey->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $record) {
                throw $exception;
            }
        }

        return DB::transaction(function () use ($record, $requestHash): array {
            $record = AutomationApiIdempotencyKey::query()
                ->lockForUpdate()
                ->findOrFail((int) $record->id);

            if (! hash_equals((string) $record->request_hash, $requestHash)) {
                return ['state' => 'payload_conflict', 'record' => $record];
            }

            if (in_array((string) $record->status, ['completed', 'failed'], true)) {
                return ['state' => 'replay', 'record' => $record];
            }

            return ['state' => 'in_progress', 'record' => $record];
        });
    }

    public function complete(AutomationApiIdempotencyKey $record, array $response, int $statusCode): void
    {
        $record->forceFill([
            'status' => $statusCode >= 400 ? 'failed' : 'completed',
            'status_code' => $statusCode,
            'response' => $response,
        ])->save();
    }

    protected function requestHash(array $payload): string
    {
        $canonical = $this->canonicalize($payload);

        return hash('sha256', (string) json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    protected function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
