<?php

namespace Modules\AppAIAssistant\Support;

use Illuminate\Support\Str;
use Modules\AdminUser\Models\User;
use Modules\AppAIAssistant\Models\AssistantAuditLog;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AssistantAuditService
{
    public function record(User $user, string $actionKey, string $status, array $context = []): AssistantAuditLog
    {
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;
        $summary = credit_service()->summary($planOwner);

        return AssistantAuditLog::query()->create([
            'owner_user_id' => TeamWorkspaceAccess::workspaceOwnerUserId($user),
            'requested_by_user_id' => (int) $user->id,
            'team_id' => $team?->id,
            'idea_id' => isset($context['idea_id']) ? (int) $context['idea_id'] : null,
            'action_key' => Str::limit(trim($actionKey), 120, ''),
            'status' => Str::limit(trim($status), 32, ''),
            'request_key' => filled($context['request_key'] ?? null) ? Str::limit((string) $context['request_key'], 120, '') : null,
            'credit_action' => filled($context['credit_action'] ?? null) ? Str::limit((string) $context['credit_action'], 120, '') : null,
            'credit_cost' => max(0, (int) ($context['credit_cost'] ?? 0)),
            'credits_before' => array_key_exists('credits_before', $context)
                ? $context['credits_before']
                : ($summary['remaining'] ?? null),
            'credits_after' => array_key_exists('credits_after', $context)
                ? $context['credits_after']
                : ($summary['remaining'] ?? null),
            'payload' => $this->sanitize((array) ($context['payload'] ?? [])),
            'result' => $this->sanitize((array) ($context['result'] ?? [])),
            'error_message' => filled($context['error'] ?? null) ? Str::limit((string) $context['error'], 2000, '') : null,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 500, ''),
        ]);
    }

    protected function sanitize(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            $keyName = is_string($key) ? $key : (string) $key;

            if (preg_match('/password|secret|token|api[_-]?key|auth[_-]?data|purchase[_-]?code/i', $keyName)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($item)) {
                $sanitized[$key] = $this->sanitize($item);
            } elseif (is_scalar($item) || $item === null) {
                $sanitized[$key] = is_string($item) ? Str::limit($item, 5000, '') : $item;
            } else {
                $sanitized[$key] = Str::limit((string) $item, 1000, '');
            }
        }

        return $sanitized;
    }
}
