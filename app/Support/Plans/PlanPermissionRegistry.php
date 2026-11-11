<?php

namespace App\Support\Plans;

class PlanPermissionRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $items = [];

    public function register(array $item): static
    {
        $key = trim((string) ($item['key'] ?? ''));

        if ($key === '') {
            throw new \InvalidArgumentException('Plan permission key is required.');
        }

        $existing = $this->items[$key] ?? [];

        $item['key'] = $key;
        $item['type'] = $item['type'] ?? ($existing['type'] ?? 'toggle');
        $item['order'] = (int) ($item['order'] ?? ($existing['order'] ?? 100));
        $item['source'] = $item['source'] ?? ($existing['source'] ?? 'module');
        $item['placement'] = $item['placement'] ?? ($existing['placement'] ?? 'normal');
        $item['fields'] = $this->mergeFields($existing['fields'] ?? [], $item['fields'] ?? []);

        $this->items[$key] = array_merge($existing, $item);

        return $this;
    }

    protected function mergeFields(array $existing, array $incoming): array
    {
        $fields = [];

        foreach (array_merge($existing, $incoming) as $index => $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldKey = trim((string) ($field['key'] ?? ''));

            if ($fieldKey === '') {
                continue;
            }

            $field['_registry_order'] = $field['order'] ?? ($fields[$fieldKey]['_registry_order'] ?? $index);
            $fields[$fieldKey] = array_merge($fields[$fieldKey] ?? [], $field);
        }

        uasort($fields, fn (array $a, array $b): int => ((int) ($a['_registry_order'] ?? 100)) <=> ((int) ($b['_registry_order'] ?? 100)));

        return array_values(array_map(function (array $field): array {
            unset($field['_registry_order']);

            return $field;
        }, $fields));
    }

    public function all(): array
    {
        $items = array_values($this->items);

        usort($items, function (array $a, array $b): int {
            $placementPriority = [
                'top' => 0,
                'normal' => 1,
            ];

            $placementCompare = ($placementPriority[$a['placement'] ?? 'normal'] ?? 1)
                <=> ($placementPriority[$b['placement'] ?? 'normal'] ?? 1);

            if ($placementCompare !== 0) {
                return $placementCompare;
            }

            $sourcePriority = [
                'module' => 0,
                'default' => 1,
            ];

            $sourceCompare = ($sourcePriority[$a['source'] ?? 'module'] ?? 0)
                <=> ($sourcePriority[$b['source'] ?? 'module'] ?? 0);

            if ($sourceCompare !== 0) {
                return $sourceCompare;
            }

            $typePriority = [
                'config' => 0,
                'toggle' => 1,
            ];

            $typeCompare = ($typePriority[$a['type'] ?? 'toggle'] ?? 1)
                <=> ($typePriority[$b['type'] ?? 'toggle'] ?? 1);

            if ($typeCompare !== 0) {
                return $typeCompare;
            }

            return ($a['order'] ?? 100) <=> ($b['order'] ?? 100);
        });

        return $items;
    }
}
