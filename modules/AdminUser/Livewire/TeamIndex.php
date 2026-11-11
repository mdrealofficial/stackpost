<?php

namespace Modules\AdminUser\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminUser\Models\Team;

#[Title('Teams')]
class TeamIndex extends Component
{
    public function render(): View
    {
        return view('adminuser::livewire.teams-index', [
            'teams' => Team::query()
                ->with('owner')
                ->withCount('members')
                ->latest()
                ->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Teams'),
        ]);
    }
}
