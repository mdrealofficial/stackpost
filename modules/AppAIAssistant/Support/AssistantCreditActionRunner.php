<?php

namespace Modules\AppAIAssistant\Support;

use Modules\AdminUser\Models\User;
use Modules\AppCredits\Models\CreditReservation;
use Throwable;

class AssistantCreditActionRunner
{
    public function __construct(protected AssistantAuditService $audit) {}

    public function run(
        User $requester,
        User $creditOwner,
        string $creditAction,
        string $requestKey,
        callable $callback,
        array $context = [],
    ): array {
        $before = credit_service()->summary($creditOwner);
        $reservation = credit_service()->reserve($creditOwner, $creditAction, $requestKey, [
            'metadata' => [
                'requested_by_user_id' => (int) $requester->id,
                'assistant_context' => (array) ($context['payload'] ?? []),
            ],
        ]);

        if ($reservation->status === CreditReservation::STATUS_COMMITTED) {
            $cached = (array) data_get($reservation->metadata, 'result_cache', []);

            if ($cached !== []) {
                return [
                    ...$cached,
                    '_credit' => [
                        'charged' => true,
                        'cost' => (int) $reservation->amount,
                        'before' => $before['remaining'] ?? null,
                        'after' => credit_service()->summary($creditOwner)['remaining'] ?? null,
                        'replayed' => true,
                    ],
                ];
            }
        }

        $this->audit->record($requester, (string) ($context['action_key'] ?? $creditAction), 'reserved', [
            ...$context,
            'request_key' => $requestKey,
            'credit_action' => $creditAction,
            'credit_cost' => (int) $reservation->amount,
            'credits_before' => $before['remaining'] ?? null,
        ]);

        try {
            $result = (array) $callback();
            $charged = ($result['source'] ?? 'fallback') === 'ai';

            if ($charged) {
                credit_service()->commitReservation($creditOwner, $requestKey, [
                    'feature' => (string) ($context['feature'] ?? 'ai-assistant'),
                    'metadata' => [
                        'result_cache' => $result,
                        'assistant_action' => (string) ($context['action_key'] ?? $creditAction),
                    ],
                ]);
            } else {
                credit_service()->refundReservation($creditOwner, $requestKey, 'ai_fallback', [
                    'result_source' => (string) ($result['source'] ?? 'fallback'),
                ]);
            }

            $after = credit_service()->summary($creditOwner);
            $this->audit->record($requester, (string) ($context['action_key'] ?? $creditAction), $charged ? 'completed' : 'refunded', [
                ...$context,
                'request_key' => $requestKey,
                'credit_action' => $creditAction,
                'credit_cost' => $charged ? (int) $reservation->amount : 0,
                'credits_before' => $before['remaining'] ?? null,
                'credits_after' => $after['remaining'] ?? null,
                'result' => [
                    'source' => (string) ($result['source'] ?? 'fallback'),
                    'count' => count((array) ($result['items'] ?? $result['variants'] ?? [])),
                ],
            ]);

            return [
                ...$result,
                '_credit' => [
                    'charged' => $charged,
                    'cost' => $charged ? (int) $reservation->amount : 0,
                    'before' => $before['remaining'] ?? null,
                    'after' => $after['remaining'] ?? null,
                    'replayed' => false,
                ],
            ];
        } catch (Throwable $exception) {
            $current = credit_service()->reservation($creditOwner, $requestKey);

            if ($current?->status === CreditReservation::STATUS_RESERVED) {
                credit_service()->refundReservation($creditOwner, $requestKey, 'action_failed', [
                    'error' => $exception->getMessage(),
                ]);
            }

            $this->audit->record($requester, (string) ($context['action_key'] ?? $creditAction), 'failed', [
                ...$context,
                'request_key' => $requestKey,
                'credit_action' => $creditAction,
                'credit_cost' => 0,
                'credits_before' => $before['remaining'] ?? null,
                'credits_after' => credit_service()->summary($creditOwner)['remaining'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
