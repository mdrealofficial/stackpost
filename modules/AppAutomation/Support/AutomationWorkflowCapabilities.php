<?php

namespace Modules\AppAutomation\Support;

use Modules\AppAutomation\Services\AutomationWorkflowHandlerRegistry;

final class AutomationWorkflowCapabilities
{
    public static function availableNodeTypes(): array
    {
        $handlers = app(AutomationWorkflowHandlerRegistry::class);
        return array_keys(array_filter(AutomationWorkflowNodeCatalog::definitions(), fn (array $node, string $type): bool =>
            $handlers->supports($type) && $type !== 'trigger_webhook' && ($node['runtime'] ?? '') !== 'provider', ARRAY_FILTER_USE_BOTH));
    }

    public static function templates(): array
    {
        return [
            ['key' => 'drive_draft', 'name' => __('Drive to publishing draft'), 'description' => __('Turn new Drive files into captioned drafts. Review and publish them when you are ready.'), 'steps' => [__('Drive file'), __('AI caption'), __('Publishing draft')], 'icon' => 'fa-light fa-file-pen'],
            ['key' => 'google_drive', 'name' => __('Drive to scheduled post'), 'description' => __('Prepare content from Drive, request team approval, then add approved posts to your calendar.'), 'steps' => [__('Drive file'), __('AI caption'), __('Team approval'), __('Schedule')], 'icon' => 'fa-light fa-calendar-check'],
        ];
    }

    public static function userMessage(string $message): string
    {
        if (str_contains($message, 'worker') || str_contains($message, 'queue connection') || str_contains($message, 'cache driver')) {
            return __('Background processing is not ready. Ask your administrator to finish the Automation setup.');
        }
        if (str_contains($message, 'OAuth client')) {
            return __('Google Drive connection setup is incomplete. Ask your administrator to enable it.');
        }
        return $message;
    }
}
