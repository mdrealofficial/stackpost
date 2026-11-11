<?php

namespace Modules\AdminCache\Actions\Contracts;

interface CacheAction
{
    public function key(): string;

    public function title(): string;

    public function description(): string;

    public function icon(): string;

    public function buttonLabel(): string;

    public function buttonVariant(): string;

    public function confirmMessage(): string;

    public function confirmTitle(): string;

    public function handle(): string;
}
