<?php

namespace Modules\AdminCache\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;
use Modules\AdminCache\Support\CacheActionRegistry;

#[Title('Cache & session')]
class CacheIndex extends Component
{
    protected CacheActionRegistry $actions;

    public ?string $statusMessage = null;

    public string $statusVariant = 'success';

    public function boot(CacheActionRegistry $actions): void
    {
        $this->actions = $actions;
    }

    public function runAction(string $action): void
    {
        try {
            $message = $this->actions->run($action);

            $this->statusVariant = 'success';
            $this->statusMessage = $message;
        } catch (Throwable $exception) {
            report($exception);

            $this->statusVariant = 'danger';
            $this->statusMessage = $exception->getMessage();
        }

        $this->dispatch('cache-action-finished');
    }

    public function render(): View
    {
        return view('admincache::index', [
            'cacheActions' => $this->actions->gridItems(),
            'optimizeAction' => $this->actions->optimizeItem(),
            'sessionAction' => $this->actions->sessionItem(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Cache & session'),
        ]);
    }
}
