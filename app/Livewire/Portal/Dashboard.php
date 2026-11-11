<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    #[\Livewire\Attributes\Url(as: 'report_days')]
    public int $reportRange = 30;


    /**
     * @param  array<int, string>  $itemIds
     */
    public function saveLayout(array $itemIds): void
    {
        $payload = Validator::make([
            'item_ids' => $itemIds,
        ], [
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['string'],
        ])->validate();

        save_user_dashboard_layout(auth()->user(), $payload['item_ids']);
    }

    public function render(): View
    {
        $days = in_array($this->reportRange, [30, 90, 365], true) ? $this->reportRange : 30;
        request()->attributes->set('dashboard_report_days', $days);
        try {
            $dashboardItems = $this->arrangeDashboardItems(user_dashboard_items(auth()->user(), 'main'));
        } finally {
            request()->attributes->remove('dashboard_report_days');
        }

        return view(theme_view('livewire.portal.dashboard', 'app'), [
            'welcomeItems' => user_dashboard_items(auth()->user(), 'welcome'),
            'dashboardItems' => $dashboardItems,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Dashboard'),
        ]);
    }

    /**
     * Keep the portal landing order focused even when a user has an older saved layout.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function arrangeDashboardItems(array $items): array
    {
        $priority = [
            'app-publishing.summary' => 0,
            'app-channels.summary' => 1,
        ];

        return collect($items)
            ->values()
            ->map(fn (array $item, int $index): array => array_merge($item, [
                '__dashboard_index' => $index,
            ]))
            ->sort(function (array $a, array $b) use ($priority): int {
                $rankA = $priority[$a['id'] ?? ''] ?? 100;
                $rankB = $priority[$b['id'] ?? ''] ?? 100;

                if ($rankA === $rankB) {
                    return ($a['__dashboard_index'] ?? 0) <=> ($b['__dashboard_index'] ?? 0);
                }

                return $rankA <=> $rankB;
            })
            ->map(function (array $item): array {
                unset($item['__dashboard_index']);

                return $item;
            })
            ->values()
            ->all();
    }
}
