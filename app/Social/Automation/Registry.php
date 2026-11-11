<?php

namespace App\Social\Automation;

class Registry
{
    private array $modules = [];

    public function register(string $key, string $label, string $interaction, string $rule, string $run): void
    {
        $this->modules[$key] = compact('key', 'label', 'interaction', 'rule', 'run');
    }

    public function all(): array { return $this->modules; }
    public function get(string $key): ?array { return $this->modules[$key] ?? null; }
}
