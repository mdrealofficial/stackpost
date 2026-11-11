<?php

namespace Modules\AdminDashboard\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class DashboardIndex extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view(theme_view('livewire.admin.dashboard', 'app'), [
            'welcomeItems' => admin_dashboard_items($user, 'welcome'),
            'dashboardItems' => admin_dashboard_items($user, 'main'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Dashboard'),
        ]);
    }
}
