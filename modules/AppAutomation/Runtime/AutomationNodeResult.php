<?php

namespace Modules\AppAutomation\Runtime;

use Carbon\CarbonInterface;

final readonly class AutomationNodeResult
{
    public const SUCCEEDED = 'succeeded';

    public const WAITING_APPROVAL = 'waiting_approval';

    public const WAITING_DELAY = 'waiting_delay';

    private function __construct(
        public string $status,
        public array $output = [],
        public ?string $branch = null,
        public ?CarbonInterface $resumeAt = null,
    ) {}

    public static function success(array $output = [], ?string $branch = null): self
    {
        return new self(self::SUCCEEDED, $output, $branch);
    }

    public static function waitForApproval(array $preview = []): self
    {
        return new self(self::WAITING_APPROVAL, $preview);
    }

    public static function waitUntil(CarbonInterface $resumeAt, array $output = []): self
    {
        return new self(self::WAITING_DELAY, $output, null, $resumeAt);
    }
}
