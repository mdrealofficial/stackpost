<?php

use Modules\AdminAI\Models\AiUsageLog;
use Modules\AdminAI\Support\AiOptionCatalog;

if (! function_exists('log_ai_usage')) {
    function log_ai_usage(array $attributes): AiUsageLog
    {
        return AiUsageLog::query()->create([
            'user_id' => $attributes['user_id'] ?? auth()->id(),
            'provider' => (string) ($attributes['provider'] ?? 'unknown'),
            'capability' => (string) ($attributes['capability'] ?? 'text'),
            'model' => (string) ($attributes['model'] ?? 'unknown'),
            'status' => (string) ($attributes['status'] ?? 'success'),
            'feature' => $attributes['feature'] ?? null,
            'route_name' => $attributes['route_name'] ?? request()?->route()?->getName(),
            'prompt_tokens' => $attributes['prompt_tokens'] ?? null,
            'completion_tokens' => $attributes['completion_tokens'] ?? null,
            'total_tokens' => $attributes['total_tokens'] ?? null,
            'estimated_cost' => $attributes['estimated_cost'] ?? null,
            'latency_ms' => $attributes['latency_ms'] ?? null,
            'error_message' => $attributes['error_message'] ?? null,
            'metadata' => $attributes['metadata'] ?? [],
        ]);
    }
}

if (! function_exists('ai_option_catalog')) {
    function ai_option_catalog(): AiOptionCatalog
    {
        return app(AiOptionCatalog::class);
    }
}
