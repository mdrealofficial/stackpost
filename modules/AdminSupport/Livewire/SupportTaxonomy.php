<?php

namespace Modules\AdminSupport\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportLabel;
use Modules\AdminSupport\Models\SupportType;

class SupportTaxonomy extends Component
{
    use WithPagination;

    public string $resource = 'categories';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'edit', except: '')]
    public string $editId = '';

    public string $name = '';

    public string $icon = '';

    public string $color = '';

    public string $status = '1';

    public ?string $statusMessage = null;

    public function mount(?string $resource = null): void
    {
        $this->resource = $resource ?: (string) request()->route('resource', 'categories');
        $this->resetForm();

        if ($this->editId !== '') {
            $this->loadEditingItem((int) $this->editId);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEditId(string $value): void
    {
        if ($value === '') {
            $this->resetForm();

            return;
        }

        $this->loadEditingItem((int) $value);
    }

    public function edit(int $id): void
    {
        $this->editId = (string) $id;
        $this->loadEditingItem($id);
    }

    public function cancelEdit(): void
    {
        $this->editId = '';
        $this->resetForm();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'in:0,1'],
        ]);

        $config = $this->config();
        $modelClass = $config['model'];
        $editingItem = $this->editingItem();

        $payload = [
            'name' => trim($validated['name']),
            'icon' => trim((string) $validated['icon']) ?: $config['default_icon'],
            'color' => trim((string) $validated['color']) ?: $config['default_color'],
            'status' => (bool) ((int) $validated['status']),
            'changed' => time(),
        ];

        if ($editingItem) {
            $editingItem->update($payload);

            log_activity("admin.support.{$this->resource}.update", "Updated support {$this->resource} item.", [
                'subject_type' => $modelClass,
                'subject_id' => $editingItem->id,
                'metadata' => ['name' => $editingItem->name],
            ]);

            $this->statusMessage = $config['updated_message'];
            $this->cancelEdit();

            return;
        }

        $payload['id_secure'] = Str::random(32);
        $payload['created'] = time();

        /** @var Model $item */
        $item = $modelClass::query()->create($payload);

        log_activity("admin.support.{$this->resource}.create", "Created support {$this->resource} item.", [
            'subject_type' => $modelClass,
            'subject_id' => $item->id,
            'metadata' => ['name' => $item->name],
        ]);

        $this->statusMessage = $config['created_message'];
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $config = $this->config();
        $modelClass = $config['model'];
        /** @var Model $item */
        $item = $modelClass::query()->findOrFail($id);

        $subjectId = $item->id;
        $name = $item->name;
        $item->delete();

        log_activity("admin.support.{$this->resource}.delete", "Deleted support {$this->resource} item.", [
            'subject_type' => $modelClass,
            'subject_id' => $subjectId,
            'metadata' => ['name' => $name],
        ]);

        if ($this->editId === (string) $id) {
            $this->cancelEdit();
        }

        $this->statusMessage = $config['deleted_message'];
        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $config = $this->config();
        $modelClass = $config['model'];

        $items = $modelClass::query()
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('icon', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->orderBy('name')
            ->orderByDesc('id')
            ->paginate(12);

        $allItems = $modelClass::query()->get(['id', 'status']);

        return view('adminsupport::livewire.taxonomy', [
            'resource' => $this->resource,
            'config' => $config,
            'items' => $items,
            'summary' => [
                'total' => $allItems->count(),
                'enabled' => $allItems->where('status', true)->count(),
                'disabled' => $allItems->where('status', false)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $config['title'],
        ]);
    }

    protected function editingItem(): ?Model
    {
        if ($this->editId === '') {
            return null;
        }

        $config = $this->config();
        $modelClass = $config['model'];

        return $modelClass::query()->find((int) $this->editId);
    }

    protected function loadEditingItem(int $id): void
    {
        $config = $this->config();
        $modelClass = $config['model'];
        /** @var Model|null $item */
        $item = $modelClass::query()->find($id);

        if (! $item) {
            $this->cancelEdit();

            return;
        }

        $this->name = (string) $item->name;
        $this->icon = (string) $item->icon;
        $this->color = (string) $item->color;
        $this->status = $item->status ? '1' : '0';
    }

    protected function resetForm(): void
    {
        $config = $this->config();
        $this->name = '';
        $this->icon = $config['default_icon'];
        $this->color = $config['default_color'];
        $this->status = '1';
        $this->resetValidation();
    }

    protected function resetPageIfNeeded(): void
    {
        $config = $this->config();
        $modelClass = $config['model'];

        if (($this->paginators['page'] ?? 1) > 1
            && $modelClass::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('icon', 'like', "%{$search}%")
                            ->orWhere('color', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->paginate(12, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }

    protected function config(): array
    {
        return match ($this->resource) {
            'labels' => [
                'model' => SupportLabel::class,
                'title' => __('Manage Labels'),
                'eyebrow' => __('Support'),
                'description' => __('Create and maintain ticket labels for triage and prioritization.'),
                'singular' => __('label'),
                'plural_label' => __('labels'),
                'icon' => 'fa-light fa-tags',
                'default_icon' => 'fa-light fa-tag',
                'default_color' => '#475569',
                'created_message' => __('Support label created successfully.'),
                'updated_message' => __('Support label updated successfully.'),
                'deleted_message' => __('Support label deleted successfully.'),
            ],
            'types' => [
                'model' => SupportType::class,
                'title' => __('Manage Types'),
                'eyebrow' => __('Support'),
                'description' => __('Create and maintain support ticket types used by the workflow.'),
                'singular' => __('type'),
                'plural_label' => __('types'),
                'icon' => 'fa-light fa-shapes',
                'default_icon' => 'fa-light fa-life-ring',
                'default_color' => '#2563eb',
                'created_message' => __('Support type created successfully.'),
                'updated_message' => __('Support type updated successfully.'),
                'deleted_message' => __('Support type deleted successfully.'),
            ],
            default => [
                'model' => SupportCategory::class,
                'title' => __('Manage Categories'),
                'eyebrow' => __('Support'),
                'description' => __('Create and maintain support categories used to organize tickets.'),
                'singular' => __('category'),
                'plural_label' => __('categories'),
                'icon' => 'fa-light fa-folder-tree',
                'default_icon' => 'fa-light fa-life-ring',
                'default_color' => '#2563eb',
                'created_message' => __('Support category created successfully.'),
                'updated_message' => __('Support category updated successfully.'),
                'deleted_message' => __('Support category deleted successfully.'),
            ],
        };
    }
}
