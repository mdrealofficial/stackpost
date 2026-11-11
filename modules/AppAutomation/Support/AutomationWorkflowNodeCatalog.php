<?php

namespace Modules\AppAutomation\Support;

use Illuminate\Support\Str;

final class AutomationWorkflowNodeCatalog
{
    public static function definitions(): array
    {
        return [
            'trigger_google_drive' => self::node(
                'New Google Drive file',
                'Starts when a watched Drive folder receives a new supported file.',
                'Trigger',
                'Drive',
                'fa-brands fa-google-drive',
                '#2563eb',
                ['name' => 'Google Drive content workflow', 'connection_id' => '', 'folder_url' => '', 'folder_id' => '', 'file_types' => 'image,video,document'],
                'connection'
            ),
            'trigger_social_engagement' => self::node(
                'New social interaction',
                'One trigger for comments, mentions and direct messages supported by a connected provider.',
                'Trigger',
                'Social',
                'fa-light fa-messages',
                '#7c3aed',
                ['name' => 'Social engagement workflow', 'provider' => '', 'account_ids' => [], 'interaction_types' => ['comment', 'mention', 'dm']],
                'provider'
            ),
            'trigger_webhook' => self::node(
                'Incoming webhook',
                'Starts from the existing authenticated Automation API.',
                'Trigger',
                'Webhook',
                'fa-light fa-webhook',
                '#0f766e',
                ['name' => 'Webhook publishing workflow', 'event_key' => 'content.received'],
                'engine'
            ),
            'condition' => self::node(
                'Filter',
                'Continues only when the incoming field matches the configured rule.',
                'Logic',
                'Filter',
                'fa-light fa-code-branch',
                '#0284c7',
                ['field' => '', 'operator' => 'contains', 'value' => ''],
                'engine'
            ),
            'delay' => self::node(
                'Wait',
                'Pauses a queued run without blocking the worker.',
                'Logic',
                'Delay',
                'fa-light fa-clock',
                '#d97706',
                ['amount' => 5, 'unit' => 'minutes'],
                'engine'
            ),
            'import_drive_file' => self::node(
                'Import to Files',
                'Copies the Drive asset into the current workspace.',
                'Content',
                'Files',
                'fa-light fa-cloud-arrow-down',
                '#2563eb',
                ['destination' => 'automation-imports'],
                'connection'
            ),
            'ai_write' => self::node(
                'AI content writer',
                'Creates platform-ready caption, hashtags and CTA with success-only credit charging.',
                'AI',
                'AI',
                'fa-light fa-wand-magic-sparkles',
                '#7c3aed',
                [
                    'instruction' => 'Create a concise social post from the source content.',
                    'tone' => 'professional',
                    'language' => 'auto',
                    'estimated_monthly_runs' => 100,
                    'monthly_credit_limit' => 100,
                ],
                'engine',
                [
                    'action_key' => 'ai_studio_generate_captions',
                    'default_cost' => 1,
                    'charge_on' => 'successful_ai_output',
                ],
            ),
            'ai_reply' => self::node(
                'AI response',
                'Drafts one contextual response for a comment, mention or DM.',
                'AI',
                'Reply',
                'fa-light fa-sparkles',
                '#7c3aed',
                ['instruction' => 'Answer helpfully using workspace context.', 'confidence_threshold' => 90],
                'provider'
            ),
            'approval' => self::node(
                'Approval gate',
                'Stops external writes until an authorized workspace member confirms the preview.',
                'Safety',
                'Review',
                'fa-light fa-shield-check',
                '#0f766e',
                ['mode' => 'always', 'approver_role' => 'admin'],
                'engine'
            ),
            'create_draft' => self::node(
                'Create publishing draft',
                'Creates a real Publishing draft through the shared publishing workflow.',
                'Publishing',
                'Draft',
                'fa-light fa-file-pen',
                '#ea580c',
                ['account_ids' => [], 'campaign_id' => '', 'label_ids' => []],
                'engine'
            ),
            'schedule_post' => self::node(
                'Schedule after approval',
                'Moves an approved draft into the existing publishing queue.',
                'Publishing',
                'Schedule',
                'fa-light fa-calendar-clock',
                '#ea580c',
                ['strategy' => 'best_time', 'fixed_time' => '09:00', 'timezone' => 'workspace'],
                'engine'
            ),
            'send_response' => self::node(
                'Reply, comment or DM',
                'Sends the matching response back to the social interaction that triggered the run.',
                'Engagement',
                'Respond',
                'fa-light fa-reply',
                '#db2777',
                ['delivery_mode' => 'after_approval'],
                'provider'
            ),
            'notify_team' => self::node(
                'Notify workspace',
                'Creates an internal notification when a run needs attention.',
                'Workspace',
                'Notify',
                'fa-light fa-bell',
                '#0891b2',
                ['message' => 'A workflow run needs review.'],
                'engine'
            ),
            'end' => self::node(
                'End workflow',
                'Finishes this execution path and records its final state.',
                'Finish',
                'End',
                'fa-light fa-square',
                '#64748b',
                [],
                'engine'
            ),
        ];
    }

    public static function groups(): array
    {
        return [
            ['label' => __('Triggers'), 'types' => ['trigger_google_drive', 'trigger_social_engagement', 'trigger_webhook']],
            ['label' => __('Logic & safety'), 'types' => ['condition', 'delay', 'approval']],
            ['label' => __('AI & content'), 'types' => ['import_drive_file', 'ai_write', 'ai_reply']],
            ['label' => __('Publishing & engagement'), 'types' => ['create_draft', 'schedule_post', 'send_response', 'notify_team']],
            ['label' => __('Finish'), 'types' => ['end']],
        ];
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::definitions());
    }

    public static function defaults(string $type): array
    {
        return (array) data_get(self::definitions(), $type.'.defaults', []);
    }

    public static function isTrigger(string $type): bool
    {
        return Str::startsWith($type, 'trigger_');
    }

    public static function sanitizeConfig(string $type, array $config): array
    {
        $defaults = self::defaults($type);

        return match ($type) {
            'trigger_google_drive' => [
                'name' => self::text($config['name'] ?? $defaults['name'], 160),
                'connection_id' => self::text($config['connection_id'] ?? '', 80),
                'folder_url' => self::text($config['folder_url'] ?? '', 1000),
                'folder_id' => self::text($config['folder_id'] ?? '', 255),
                'file_types' => self::text($config['file_types'] ?? $defaults['file_types'], 120),
            ],
            'trigger_social_engagement' => [
                'name' => self::text($config['name'] ?? $defaults['name'], 160),
                'provider' => self::text($config['provider'] ?? '', 80),
                'account_ids' => collect((array) ($config['account_ids'] ?? []))->map(fn ($value): int => (int) $value)->filter()->unique()->take(30)->values()->all(),
                'interaction_types' => collect((array) ($config['interaction_types'] ?? $defaults['interaction_types']))
                    ->map(fn ($value): string => self::text($value, 24))
                    ->intersect(['comment', 'mention', 'dm'])
                    ->unique()->values()->all(),
            ],
            'trigger_webhook' => [
                'name' => self::text($config['name'] ?? $defaults['name'], 160),
                'event_key' => self::text($config['event_key'] ?? $defaults['event_key'], 80),
            ],
            'condition' => [
                'field' => self::text($config['field'] ?? '', 120),
                'operator' => in_array((string) ($config['operator'] ?? ''), ['contains', 'equals', 'exists'], true) ? (string) $config['operator'] : 'contains',
                'value' => self::text($config['value'] ?? '', 500),
            ],
            'delay' => [
                'amount' => self::delayAmount($config['amount'] ?? 5, (string) ($config['unit'] ?? 'minutes')),
                'unit' => in_array((string) ($config['unit'] ?? ''), ['minutes', 'hours', 'days'], true) ? (string) $config['unit'] : 'minutes',
            ],
            'import_drive_file' => ['destination' => self::text($config['destination'] ?? $defaults['destination'], 180)],
            'ai_write' => [
                'instruction' => self::text($config['instruction'] ?? $defaults['instruction'], 2000),
                'tone' => self::text($config['tone'] ?? $defaults['tone'], 80),
                'language' => self::language($config['language'] ?? $defaults['language']),
                'estimated_monthly_runs' => max(1, min(100000, (int) ($config['estimated_monthly_runs'] ?? $defaults['estimated_monthly_runs']))),
                'monthly_credit_limit' => max(1, min(1000000, (int) ($config['monthly_credit_limit'] ?? $defaults['monthly_credit_limit']))),
            ],
            'ai_reply' => [
                'instruction' => self::text($config['instruction'] ?? $defaults['instruction'], 2000),
                'confidence_threshold' => max(50, min(100, (int) ($config['confidence_threshold'] ?? 90))),
            ],
            'approval' => [
                'mode' => in_array((string) ($config['mode'] ?? ''), ['always', 'low_confidence'], true) ? (string) $config['mode'] : 'always',
                'approver_role' => in_array((string) ($config['approver_role'] ?? ''), ['owner', 'admin'], true) ? (string) $config['approver_role'] : 'admin',
            ],
            'create_draft' => [
                'account_ids' => collect((array) ($config['account_ids'] ?? []))->map(fn ($value): int => (int) $value)->filter()->unique()->take(30)->values()->all(),
                'campaign_id' => self::text($config['campaign_id'] ?? '', 80),
                'label_ids' => collect((array) ($config['label_ids'] ?? []))->map(fn ($value): int => (int) $value)->filter()->unique()->take(30)->values()->all(),
            ],
            'schedule_post' => [
                'strategy' => in_array((string) ($config['strategy'] ?? ''), ['best_time', 'fixed_time', 'next_slot'], true) ? (string) $config['strategy'] : 'best_time',
                'fixed_time' => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($config['fixed_time'] ?? '')) ? (string) $config['fixed_time'] : '09:00',
                'timezone' => self::text($config['timezone'] ?? 'workspace', 80),
            ],
            'send_response' => ['delivery_mode' => 'after_approval'],
            'notify_team' => ['message' => self::text($config['message'] ?? $defaults['message'], 500)],
            default => [],
        };
    }

    public static function creditPolicy(string $type): array
    {
        return (array) data_get(self::definitions(), $type.'.credit', []);
    }

    public static function creditAction(string $type): ?string
    {
        $action = trim((string) data_get(self::definitions(), $type.'.credit.action_key', ''));

        return $action !== '' ? $action : null;
    }

    private static function node(string $label, string $description, string $category, string $badge, string $icon, string $color, array $defaults, string $runtime, array $credit = []): array
    {
        return compact('label', 'description', 'category', 'badge', 'icon', 'color', 'defaults', 'runtime', 'credit');
    }

    private static function text(mixed $value, int $length): string
    {
        return Str::limit(trim((string) $value), $length, '');
    }

    private static function delayAmount(mixed $value, string $unit): int
    {
        $maximum = match ($unit) {
            'days' => 30,
            'hours' => 168,
            default => 10080,
        };

        return max(1, min($maximum, (int) $value));
    }

    private static function language(mixed $value): string
    {
        $language = strtolower(self::text($value, 40));
        $supported = collect(world_languages())->pluck('code')->map(fn ($code): string => strtolower((string) $code));

        return $language === 'auto' || $supported->contains($language) ? $language : 'auto';
    }
}
