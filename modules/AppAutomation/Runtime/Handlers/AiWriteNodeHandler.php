<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;
use Modules\AppAutomation\Services\AutomationWorkflowCreditRunner;
use Modules\AppChannels\Models\SocialAccount;

class AiWriteNodeHandler implements AutomationNodeHandler
{
    public function __construct(
        protected AutomationWorkflowCreditRunner $credits,
        protected AiContentStudioService $ai,
    ) {}

    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $payload = $context->payload();
        $config = (array) $context->config();
        $platforms = $this->targetPlatforms($context);
        $brief = $this->brief($payload, $config);

        $result = $this->credits->run(
            $context->run,
            $context->step,
            fn (): array => $this->ai->generatePlatformCaptions($brief, $platforms, [
                'tone' => (string) ($config['tone'] ?? 'professional'),
                'language' => (string) ($config['language'] ?? 'auto'),
                'total_results' => max(1, count($platforms)),
                'approximate_words' => 100,
            ]),
        );

        return AutomationNodeResult::success([
            ...$result,
            'file' => (array) ($payload['file'] ?? []),
            'source' => (array) ($payload['source'] ?? $context->originalInput()),
            'brief' => $brief,
        ]);
    }

    protected function targetPlatforms(AutomationNodeContext $context): array
    {
        $accountIds = collect((array) ($context->definition['nodes'] ?? []))
            ->filter(fn ($node): bool => is_array($node) && ($node['type'] ?? '') === 'create_draft')
            ->flatMap(fn (array $node): array => (array) data_get($node, 'config.account_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $platforms = SocialAccount::query()
            ->where('created_by_user_id', $context->ownerUserId())
            ->whereIn('id', $accountIds)
            ->where('is_active', true)
            ->pluck('provider_key')
            ->map(fn ($provider): string => strtolower(trim((string) $provider)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $platforms !== [] ? $platforms : ['facebook'];
    }

    protected function brief(array $payload, array $config): string
    {
        $file = (array) ($payload['file'] ?? []);
        $source = (array) ($payload['source'] ?? []);
        $parts = array_filter([
            trim((string) ($config['instruction'] ?? 'Create a social post from this asset.')),
            filled($file['name'] ?? null) ? __('Source file: :name', ['name' => $file['name']]) : null,
            filled($file['mimeType'] ?? null) ? __('File type: :type', ['type' => $file['mimeType']]) : null,
            filled($source['description'] ?? null) ? __('Source description: :description', ['description' => $source['description']]) : null,
            filled($source['web_view_link'] ?? null) ? __('Drive reference: :url', ['url' => $source['web_view_link']]) : null,
        ]);

        return trim(implode("\n", $parts));
    }
}
