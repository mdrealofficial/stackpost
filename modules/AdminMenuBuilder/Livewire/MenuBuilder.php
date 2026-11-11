<?php

namespace Modules\AdminMenuBuilder\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

#[Title('Menu Builder')]
class MenuBuilder extends Component
{
    protected OptionStore $options;

    public string $activeArea = 'admin';

    /**
     * @var array<string, array<int, array{key:string,label:string,items:array<int, array{key:string,label:string,has_children:bool,children_count:int}>}>>
     */
    public array $menus = [
        'admin' => [],
        'user' => [],
    ];

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(): void
    {
        $this->menus['admin'] = $this->loadArea('admin');
        $this->menus['user'] = $this->loadArea('user');
    }

    public function moveSectionUp(string $area, int $index): void
    {
        $this->moveSection($area, $index, -1);
    }

    public function moveSectionDown(string $area, int $index): void
    {
        $this->moveSection($area, $index, 1);
    }

    public function moveSectionToIndex(string $area, int $fromIndex, int $toIndex): void
    {
        $sections = $this->menus[$area] ?? [];

        if (! isset($sections[$fromIndex])) {
            return;
        }

        $maxTarget = count($sections);
        $toIndex = max(0, min($toIndex, $maxTarget));

        if ($fromIndex === $toIndex || $fromIndex + 1 === $toIndex) {
            return;
        }

        $moved = $sections[$fromIndex];
        array_splice($sections, $fromIndex, 1);

        if ($fromIndex < $toIndex) {
            $toIndex--;
        }

        array_splice($sections, $toIndex, 0, [$moved]);

        $this->menus[$area] = array_values($sections);
    }

    public function moveItemUp(string $area, int $sectionIndex, int $itemIndex): void
    {
        $this->moveItem($area, $sectionIndex, $itemIndex, -1);
    }

    public function moveItemDown(string $area, int $sectionIndex, int $itemIndex): void
    {
        $this->moveItem($area, $sectionIndex, $itemIndex, 1);
    }

    public function moveItemToSection(string $area, int $fromSectionIndex, int $fromItemIndex, int $toSectionIndex, ?int $toItemIndex = null): void
    {
        $sections = $this->menus[$area] ?? [];

        if (! isset($sections[$fromSectionIndex]['items'][$fromItemIndex], $sections[$toSectionIndex]['items'])) {
            return;
        }

        $itemsFrom = $sections[$fromSectionIndex]['items'];
        $moved = $itemsFrom[$fromItemIndex];
        array_splice($itemsFrom, $fromItemIndex, 1);
        $sections[$fromSectionIndex]['items'] = array_values($itemsFrom);

        $targetItems = $sections[$toSectionIndex]['items'];
        $targetIndex = $toItemIndex ?? count($targetItems);

        if ($fromSectionIndex === $toSectionIndex && $fromItemIndex < $targetIndex) {
            $targetIndex--;
        }

        $targetIndex = max(0, min($targetIndex, count($targetItems)));
        array_splice($targetItems, $targetIndex, 0, [$moved]);
        $sections[$toSectionIndex]['items'] = array_values($targetItems);

        $this->menus[$area] = array_values($sections);
    }

    public function resetArea(string $area): void
    {
        $payload = $this->currentOverrides();
        unset($payload[$area]);

        if ($payload === []) {
            $this->options->forget('admin_menu_sidebar_overrides');
        } else {
            $this->options->set('admin_menu_sidebar_overrides', $payload);
        }

        $this->menus['admin'] = $this->loadArea('admin');
        $this->menus['user'] = $this->loadArea('user');
        $this->dispatch('settings-saved');
    }

    public function resetDefaults(): void
    {
        $this->options->forget('admin_menu_sidebar_overrides');
        $this->menus['admin'] = $this->loadArea('admin');
        $this->menus['user'] = $this->loadArea('user');
        $this->dispatch('settings-saved');
    }

    public function save(): void
    {
        $this->validate([
            'menus.admin.*.label' => ['nullable', 'string', 'max:100'],
            'menus.admin.*.items.*.label' => ['required', 'string', 'max:100'],
            'menus.user.*.label' => ['nullable', 'string', 'max:100'],
            'menus.user.*.items.*.label' => ['required', 'string', 'max:100'],
        ]);

        $payload = collect(['admin', 'user'])
            ->mapWithKeys(fn (string $area): array => [
                $area => [
                    'sections' => collect($this->menus[$area] ?? [])
                        ->map(function (array $section): array {
                            return [
                                'key' => (string) ($section['key'] ?? ''),
                                'label' => trim((string) ($section['label'] ?? '')),
                                'items' => collect($section['items'] ?? [])
                                    ->map(fn (array $item): array => [
                                        'key' => (string) ($item['key'] ?? ''),
                                        'label' => trim((string) ($item['label'] ?? '')),
                                    ])
                                    ->filter(fn (array $item): bool => $item['key'] !== '' && $item['label'] !== '')
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->filter(fn (array $section): bool => $section['key'] !== '')
                        ->values()
                        ->all(),
                ],
            ])
            ->all();

        $this->options->set('admin_menu_sidebar_overrides', $payload);
        $this->dispatch('settings-saved');
    }

    /**
     * @return array<int, array{key:string,label:string,items:array<int, array{key:string,label:string,has_children:bool,children_count:int}>}>
     */
    protected function loadArea(string $area): array
    {
        return collect(sidebar_registry()->editableSections($area))
            ->map(function (array $section): array {
                return [
                    'key' => (string) ($section['key'] ?? ''),
                    'label' => (string) ($section['label'] ?? ''),
                    'items' => collect($section['items'] ?? [])
                        ->map(fn (array $item): array => [
                            'key' => (string) ($item['key'] ?? ''),
                            'label' => (string) ($item['label'] ?? ''),
                            'has_children' => ! empty($item['children'] ?? []),
                            'children_count' => count($item['children'] ?? []),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function currentOverrides(): array
    {
        $payload = $this->options->get('admin_menu_sidebar_overrides', []);

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    protected function moveSection(string $area, int $index, int $direction): void
    {
        $sections = $this->menus[$area] ?? [];
        $targetIndex = $index + $direction;

        if (! isset($sections[$index], $sections[$targetIndex])) {
            return;
        }

        [$sections[$index], $sections[$targetIndex]] = [$sections[$targetIndex], $sections[$index]];
        $this->menus[$area] = array_values($sections);
    }

    protected function moveItem(string $area, int $sectionIndex, int $itemIndex, int $direction): void
    {
        $sections = $this->menus[$area] ?? [];

        if (! isset($sections[$sectionIndex]['items'])) {
            return;
        }

        $items = $sections[$sectionIndex]['items'];
        $targetIndex = $itemIndex + $direction;

        if (! isset($items[$itemIndex], $items[$targetIndex])) {
            return;
        }

        [$items[$itemIndex], $items[$targetIndex]] = [$items[$targetIndex], $items[$itemIndex]];
        $sections[$sectionIndex]['items'] = array_values($items);
        $this->menus[$area] = array_values($sections);
    }

    public function render(): View
    {
        return view('adminmenubuilder::livewire.menu-builder')
            ->layout(theme_view('layouts.app', 'app'), [
                'title' => __('Menu Builder'),
            ]);
    }
}
