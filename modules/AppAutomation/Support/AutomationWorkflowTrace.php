<?php

namespace Modules\AppAutomation\Support;

use Illuminate\Support\Str;

final class AutomationWorkflowTrace
{
    public static function initial(string $cursor, array $metadata = []): array
    {
        return [
            'version' => 1,
            'cursor' => $cursor,
            'events' => [self::event('run.queued', null, $metadata)],
        ];
    }

    public static function append(
        array $trace,
        string $type,
        ?string $nodeId = null,
        array $metadata = [],
        ?string $cursor = null,
    ): array {
        $events = collect((array) ($trace['events'] ?? []))
            ->push(self::event($type, $nodeId, $metadata))
            ->take(-200)
            ->values()
            ->all();

        return [
            ...$trace,
            'version' => 1,
            'cursor' => $cursor ?? ($trace['cursor'] ?? null),
            'events' => $events,
        ];
    }

    protected static function event(string $type, ?string $nodeId, array $metadata): array
    {
        return [
            'at' => now()->toIso8601String(),
            'type' => Str::limit($type, 80, ''),
            'node_id' => $nodeId,
            'metadata' => self::sanitizeMetadata($metadata),
        ];
    }

    protected static function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)
            ->map(function (mixed $value): mixed {
                if (is_string($value)) {
                    return Str::limit($value, 1000, '');
                }

                if (is_scalar($value) || $value === null) {
                    return $value;
                }

                if (is_array($value)) {
                    return collect($value)->take(30)->all();
                }

                return (string) $value;
            })
            ->take(30)
            ->all();
    }
}
