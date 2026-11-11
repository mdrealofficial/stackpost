<?php

namespace Modules\AdminAITemplateCategories\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;

#[Title('AI Template Categories')]
class CategoryIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    public ?string $statusMessage = null;

    public bool $showFormModal = false;

    public bool $isEditing = false;

    public ?AiTemplateCategory $editingCategory = null;

    public array $form = [
        'name' => '',
        'desc' => '',
        'icon' => '',
        'color' => 'primary',
        'status' => '1',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetFormState();
        $this->showFormModal = true;
    }

    public function openEditModal(int $categoryId): void
    {
        $category = AiTemplateCategory::query()->findOrFail($categoryId);

        $this->editingCategory = $category;
        $this->isEditing = true;
        $this->form = [
            'name' => (string) $category->name,
            'desc' => (string) ($category->desc ?? ''),
            'icon' => (string) $category->icon,
            'color' => (string) ($category->color ?: 'primary'),
            'status' => $category->status ? '1' : '0',
        ];
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
    }

    public function saveCategory(): void
    {
        $validated = $this->validate($this->rules());

        $payload = [
            'name' => trim($validated['form']['name']),
            'desc' => trim((string) ($validated['form']['desc'] ?? '')),
            'icon' => trim($validated['form']['icon']),
            'color' => $validated['form']['color'],
            'status' => (int) $validated['form']['status'],
            'id_secure' => $this->editingCategory?->id_secure ?: Str::random(32),
            'changed' => time(),
            'created' => $this->editingCategory?->created ?: time(),
        ];

        if ($this->isEditing && $this->editingCategory) {
            $this->editingCategory->update($payload);

            log_activity('admin.ai-template-categories.update', 'Updated an AI template category.', [
                'subject_type' => AiTemplateCategory::class,
                'subject_id' => $this->editingCategory->id,
                'metadata' => [
                    'name' => $this->editingCategory->name,
                    'status' => $this->editingCategory->status ? 'enabled' : 'disabled',
                ],
            ]);

            $this->statusMessage = __('AI template category updated successfully.');
        } else {
            $category = AiTemplateCategory::query()->create($payload);

            log_activity('admin.ai-template-categories.create', 'Created an AI template category.', [
                'subject_type' => AiTemplateCategory::class,
                'subject_id' => $category->id,
                'metadata' => [
                    'name' => $category->name,
                    'status' => $category->status ? 'enabled' : 'disabled',
                ],
            ]);

            $this->statusMessage = __('AI template category created successfully.');
        }

        $this->showFormModal = false;
        $this->resetFormState();
    }

    public function delete(int $categoryId): void
    {
        $category = AiTemplateCategory::query()->findOrFail($categoryId);

        $metadata = [
            'name' => $category->name,
            'icon' => $category->icon,
        ];

        $category->delete();

        log_activity('admin.ai-template-categories.delete', 'Deleted an AI template category.', [
            'subject_type' => AiTemplateCategory::class,
            'subject_id' => $categoryId,
            'metadata' => $metadata,
        ]);

        if ($this->editingCategory?->id === $categoryId) {
            $this->showFormModal = false;
            $this->resetFormState();
        }

        $this->statusMessage = __('AI template category deleted successfully.');

        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = AiTemplateCategory::query()
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('desc', 'like', "%{$search}%")
                        ->orWhere('icon', 'like', "%{$search}%")
                        ->orWhere('color', 'like', "%{$search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $categories = $query->paginate(18);
        $allCategories = AiTemplateCategory::query()->get(['status']);

        return view('adminaitemplatecategories::livewire.index', [
            'categories' => $categories,
            'summary' => [
                'total' => $allCategories->count(),
                'enabled' => $allCategories->where('status', true)->count(),
                'disabled' => $allCategories->where('status', false)->count(),
            ],
            'colorOptions' => $this->colorOptions(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Template Categories'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:100', Rule::unique('ai_template_categories', 'name')->ignore($this->editingCategory?->id)],
            'form.desc' => ['nullable', 'string', 'max:500'],
            'form.icon' => ['required', 'string', 'max:150'],
            'form.color' => ['required', Rule::in(collect($this->colorOptions())->pluck('value')->all())],
            'form.status' => ['required', Rule::in(['0', '1'])],
        ];
    }

    protected function colorOptions(): array
    {
        return [
            ['value' => 'primary', 'label' => 'Primary'],
            ['value' => 'success', 'label' => 'Success'],
            ['value' => 'danger', 'label' => 'Danger'],
            ['value' => 'warning', 'label' => 'Warning'],
            ['value' => 'info', 'label' => 'Info'],
            ['value' => 'dark', 'label' => 'Dark'],
        ];
    }

    protected function resetFormState(): void
    {
        $this->editingCategory = null;
        $this->isEditing = false;
        $this->form = [
            'name' => '',
            'desc' => '',
            'icon' => '',
            'color' => 'primary',
            'status' => '1',
        ];
        $this->resetValidation();
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && AiTemplateCategory::query()
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('desc', 'like', "%{$search}%")
                            ->orWhere('icon', 'like', "%{$search}%")
                            ->orWhere('color', 'like', "%{$search}%");
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->paginate(18, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }
}
