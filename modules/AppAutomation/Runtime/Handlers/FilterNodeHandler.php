<?php

namespace Modules\AppAutomation\Runtime\Handlers;

use Illuminate\Support\Str;
use Modules\AppAutomation\Contracts\AutomationNodeHandler;
use Modules\AppAutomation\Exceptions\AutomationNodeException;
use Modules\AppAutomation\Runtime\AutomationNodeContext;
use Modules\AppAutomation\Runtime\AutomationNodeResult;

class FilterNodeHandler implements AutomationNodeHandler
{
    public function handle(AutomationNodeContext $context): AutomationNodeResult
    {
        $field = trim((string) $context->config('field'));
        $operator = (string) $context->config('operator', 'contains');
        $expected = $context->config('value');

        if ($field === '') {
            throw AutomationNodeException::permanent(__('The Filter node needs a field to inspect.'));
        }

        $sentinel = new \stdClass();
        $actual = $context->value($field, $sentinel);
        $matched = match ($operator) {
            'exists' => $actual !== $sentinel && $actual !== null && $actual !== '',
            'equals' => $actual !== $sentinel && $this->normalize($actual) === $this->normalize($expected),
            'contains' => $actual !== $sentinel && Str::contains(
                Str::lower($this->normalize($actual)),
                Str::lower($this->normalize($expected))
            ),
            default => throw AutomationNodeException::permanent(__('The Filter operator is not supported.')),
        };

        return AutomationNodeResult::success([
            'field' => $field,
            'operator' => $operator,
            'matched' => $matched,
        ], $matched ? 'yes' : 'no');
    }

    protected function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value) || $value === null) {
            return trim((string) $value);
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }
}
