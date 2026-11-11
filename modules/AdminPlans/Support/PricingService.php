<?php

namespace Modules\AdminPlans\Support;

use Modules\AdminPlans\Models\AdminPlan;

class PricingService
{
    protected array $features = [];

    protected array $subfeatures = [];

    public function add(array $feature): void
    {
        if (isset($feature[0]) && is_array($feature[0])) {
            foreach ($feature as $item) {
                $this->add($item);
            }

            return;
        }

        if (empty($feature['key'])) {
            return;
        }

        $this->features[$feature['key']] = $feature;
    }

    public function addSubFeatures(array $feature): void
    {
        if (isset($feature[0]) && is_array($feature[0])) {
            foreach ($feature as $item) {
                $this->addSubFeatures($item);
            }

            return;
        }

        if (empty($feature['key'])) {
            return;
        }

        $this->subfeatures[$feature['key']] = $feature;
    }

    public function getSubFeatures(?string $parent = null): array
    {
        $subfeatures = array_values(array_filter($this->subfeatures, fn (array $item): bool => $this->isVisible($item)));

        if ($parent !== null) {
            $subfeatures = array_values(array_filter($subfeatures, fn (array $item): bool => ($item['parent'] ?? null) === $parent));
        }

        usort($subfeatures, fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $subfeatures;
    }

    public function all(): array
    {
        $features = array_values(array_filter($this->features, fn (array $item): bool => $this->isVisible($item)));

        usort($features, fn (array $a, array $b): int => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $features;
    }

    public function plansWithFeatures($planId = false): array
    {
        $typeKeys = array_keys(app(PlanService::class)->getTypes());

        $query = AdminPlan::query()->where('status', true);

        if ($planId !== false) {
            $query->whereKey($planId);
        }

        $plans = $query->orderBy('type')->orderBy('position')->orderBy('price')->get();
        $grouped = [];

        foreach ($plans as $plan) {
            $item = [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'currency_symbol' => $plan->currency_symbol,
                'currency_name' => $plan->currency_name,
                'desc' => $plan->desc,
                'trial_day' => (int) $plan->trial_day,
                'free_plan' => (bool) $plan->free_plan,
                'featured' => (bool) $plan->featured,
                'position' => (int) $plan->position,
                'type' => (int) $plan->type,
                'permissions' => is_array($plan->permissions) ? $plan->permissions : [],
                'features' => $this->getListForPlan($plan),
                'model' => $plan,
            ];

            if ($planId !== false) {
                return $item;
            }

            $grouped[(int) $plan->type][] = $item;
        }

        $result = [];
        foreach ($typeKeys as $typeKey) {
            $result[$typeKey] = $grouped[$typeKey] ?? [];
        }

        return $result;
    }

    public function getListForPlan(AdminPlan|array $plan): array
    {
        $permissions = is_array($plan) ? ($plan['permissions'] ?? []) : ($plan->permissions ?? []);

        $build = function (array $features) use (&$build, $permissions): array {
            $list = [];

            foreach ($features as $feature) {
                if (isset($feature['subfeature']) && is_callable($feature['subfeature'])) {
                    $feature['subfeature'] = value($feature['subfeature']);
                }

                $value = $this->getPermissionValue($permissions, $feature['key'] ?? null);
                $subList = [];

                if (! empty($feature['subfeature'])) {
                    $subList = $this->groupSubFeaturesByTab($feature['subfeature'], $permissions, $build);
                }

                $item = $this->render($feature, $permissions, $value);
                $item['subfeature'] = $subList;

                [$item, $standaloneItems] = $this->extractStandaloneSubfeatureGroups($item);

                if ($this->shouldDisplayPricingItem($item)) {
                    $list[] = $item;
                }

                foreach ($standaloneItems as $standaloneItem) {
                    if ($this->shouldDisplayPricingItem($standaloneItem)) {
                        $list[] = $standaloneItem;
                    }
                }
            }

            usort($list, fn (array $a, array $b): int => (($a['feature']['sort'] ?? 0) <=> ($b['feature']['sort'] ?? 0)));

            return $list;
        };

        return $build($this->all());
    }

    public function render(array $feature, array $permissions, mixed $value = null): array
    {
        $shouldCheck = ! isset($feature['check']) || $feature['check'] !== false;
        $isCheck = $shouldCheck && isset($feature['key'])
            ? self::hasPermissionKey($permissions, (string) $feature['key'])
            : true;

        if ($value === null && isset($feature['key'])) {
            $value = $this->getPermissionValue($permissions, (string) $feature['key']);
        }

        $resolvedSubfeatures = $feature['subfeatures'] ?? [];
        $subfeatures = [];
        foreach ($resolvedSubfeatures as $sub) {
            $item = $this->render($sub, $permissions, $permissions[$sub['key']] ?? null);

            if ($this->shouldDisplayPricingItem($item)) {
                $subfeatures[] = $item;
            }
        }

        return [
            'check' => $isCheck,
            'label' => $feature['label'] ?? '',
            'key' => $feature['key'] ?? null,
            'raw' => $value,
            'type' => $feature['type'] ?? 'boolean',
            'display' => $this->formatFeatureValue($feature['type'] ?? 'boolean', $value, $isCheck),
            'subfeature' => $subfeatures,
            'feature' => $feature,
        ];
    }

    protected function extractStandaloneSubfeatureGroups(array $item): array
    {
        $groups = collect($item['subfeature'] ?? []);
        if ($groups->isEmpty()) {
            return [$item, []];
        }

        $standaloneGroups = $groups->filter(fn (array $group): bool => (bool) ($group['standalone'] ?? false))->values();
        if ($standaloneGroups->isEmpty()) {
            return [$item, []];
        }

        $item['subfeature'] = $groups
            ->reject(fn (array $group): bool => (bool) ($group['standalone'] ?? false))
            ->values()
            ->all();

        $standaloneItems = $standaloneGroups->map(function (array $group): array {
            $label = (string) ($group['standalone_label'] ?? $group['tab_name'] ?? '');
            $key = (string) ($group['standalone_key'] ?? $group['tab_id'] ?? '');

            return [
                'check' => true,
                'label' => $label,
                'key' => $key,
                'raw' => null,
                'type' => 'group',
                'display' => null,
                'subfeature' => [$group],
                'feature' => [
                    'sort' => (int) ($group['standalone_sort'] ?? 999),
                    'key' => $key,
                    'label' => $label,
                    'check' => false,
                    'type' => 'group',
                    'raw' => null,
                ],
            ];
        })->all();

        return [$item, $standaloneItems];
    }

    public static function hasPermissionKey(array $permissions, string $key): bool
    {
        if (array_key_exists($key, $permissions)) {
            return ! in_array($permissions[$key], [0, '0', false, null, ''], true);
        }

        foreach ($permissions as $item) {
            if (is_array($item) && ($item['key'] ?? null) === $key) {
                return ! in_array($item['value'] ?? 1, [0, '0', false, null, ''], true);
            }
        }

        return false;
    }

    protected function getPermissionValue(array $permissions, ?string $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $default;
        }

        if (array_key_exists($key, $permissions)) {
            return $permissions[$key];
        }

        foreach ($permissions as $item) {
            if (is_array($item) && ($item['key'] ?? null) === $key) {
                return $item['value'] ?? $default;
            }
        }

        return $default;
    }

    protected function formatFeatureValue(string $type, mixed $value, bool $isCheck = true): ?string
    {
        if (in_array($type, ['boolean', 'group'], true)) {
            return null;
        }

        if (! $isCheck && in_array($value, [null, ''], true)) {
            return '0';
        }

        if (in_array($value, [null, ''], true)) {
            return '0';
        }

        if ((string) $value === '-1') {
            return __('Unlimited');
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 0, '.', ',');
        }

        return (string) $value;
    }

    protected function groupSubFeaturesByTab(array $subfeatures, array $permissions, callable $build): array
    {
        $grouped = [];
        $other = [];

        foreach ($subfeatures as $sub) {
            $built = $build([$sub])[0] ?? null;

            if (! is_array($built)) {
                continue;
            }

            if (isset($sub['tab_id'], $sub['tab_name'])) {
                $grouped[$sub['tab_id']]['tab_id'] = $sub['tab_id'];
                $grouped[$sub['tab_id']]['tab_name'] = $sub['tab_name'];
                $grouped[$sub['tab_id']]['standalone'] = (bool) ($sub['standalone'] ?? ($grouped[$sub['tab_id']]['standalone'] ?? false));
                $grouped[$sub['tab_id']]['standalone_sort'] = $sub['standalone_sort'] ?? ($grouped[$sub['tab_id']]['standalone_sort'] ?? null);
                $grouped[$sub['tab_id']]['standalone_key'] = $sub['standalone_key'] ?? ($grouped[$sub['tab_id']]['standalone_key'] ?? null);
                $grouped[$sub['tab_id']]['standalone_label'] = $sub['standalone_label'] ?? ($grouped[$sub['tab_id']]['standalone_label'] ?? null);
                $grouped[$sub['tab_id']]['items'][] = $built;
            } else {
                $other[] = $built;
            }
        }

        ksort($grouped);

        $result = [];
        foreach ($grouped as $group) {
            if (empty($group['items'])) {
                continue;
            }

            $result[] = [
                'tab_id' => $group['tab_id'],
                'tab_name' => $group['tab_name'],
                'items' => $group['items'],
                'standalone' => (bool) ($group['standalone'] ?? false),
                'standalone_sort' => $group['standalone_sort'] ?? null,
                'standalone_key' => $group['standalone_key'] ?? null,
                'standalone_label' => $group['standalone_label'] ?? null,
            ];
        }

        if ($other !== []) {
            $result[] = [
                'tab_id' => 'other',
                'tab_name' => __('Other'),
                'items' => $other,
            ];
        }

        return $result;
    }

    protected function isVisible(array $feature): bool
    {
        if (! array_key_exists('visible', $feature)) {
            return true;
        }

        return (bool) value($feature['visible']);
    }

    protected function shouldDisplayPricingItem(array $item): bool
    {
        if (! empty($item['subfeature'] ?? [])) {
            return true;
        }

        if (in_array(($item['type'] ?? 'boolean'), ['boolean', 'group'], true)) {
            return true;
        }

        return in_array(($item['type'] ?? 'boolean'), ['number', 'choice'], true)
            && ! in_array(($item['display'] ?? null), [null, '', '0'], true);
    }
}
