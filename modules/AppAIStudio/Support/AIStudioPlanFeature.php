<?php

namespace Modules\AppAIStudio\Support;

use Modules\AdminUser\Models\User;

class AIStudioPlanFeature
{
    public static function enabled(?User $planOwner, string $featureKey): bool
    {
        if (! $planOwner?->hasActivePlan()) {
            return false;
        }

        $permissions = (array) ($planOwner->plan?->permissions ?? []);

        if (array_key_exists($featureKey, $permissions)) {
            return in_array($permissions[$featureKey], [true, 1, '1'], true);
        }

        return static::defaultEnabled($featureKey);
    }

    public static function defaultEnabled(string $featureKey): bool
    {
        foreach (plan_permissions() as $definition) {
            foreach ((array) ($definition['fields'] ?? []) as $field) {
                if (($field['key'] ?? null) === $featureKey) {
                    return in_array($field['default'] ?? false, [true, 1, '1'], true);
                }
            }
        }

        return false;
    }
}
