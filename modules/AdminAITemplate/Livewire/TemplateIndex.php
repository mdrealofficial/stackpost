<?php

namespace Modules\AdminAITemplate\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\AdminAITemplate\Models\AiTemplate;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;

#[Title('AI Templates')]
class TemplateIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'all')]
    public string $statusFilter = 'all';

    #[Url(as: 'category', except: '')]
    public string $categoryFilter = '';

    #[Url(as: 'create', except: '')]
    public string $createAction = '';

    #[Url(as: 'edit', except: '')]
    public string $editTemplateId = '';

    public ?string $statusMessage = null;

    public string $statusVariant = 'success';

    public bool $showFormModal = false;

    public bool $isEditing = false;

    public ?AiTemplate $editingTemplate = null;

    public array $form = [
        'cate_id' => '',
        'content' => '',
        'status' => '1',
    ];

    public function mount(): void
    {
        if ($this->editTemplateId !== '') {
            $this->openEditModal((int) $this->editTemplateId);
            $this->editTemplateId = '';

            return;
        }

        if ($this->createAction !== '') {
            $this->openCreateModal();
            $this->createAction = '';
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

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetFormState();
        $this->showFormModal = true;
    }

    public function openEditModal(int $templateId): void
    {
        $template = AiTemplate::query()->findOrFail($templateId);

        $this->editingTemplate = $template;
        $this->isEditing = true;
        $this->form = [
            'cate_id' => (string) $template->cate_id,
            'content' => (string) $template->content,
            'status' => $template->status ? '1' : '0',
        ];
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetValidation();
    }

    public function saveTemplate(): void
    {
        $validated = $this->validate($this->rules());

        $payload = [
            'cate_id' => (int) $validated['form']['cate_id'],
            'content' => trim($validated['form']['content']),
            'status' => (int) $validated['form']['status'],
            'id_secure' => $this->editingTemplate?->id_secure ?: Str::random(32),
            'changed' => time(),
            'created' => $this->editingTemplate?->created ?: time(),
        ];

        if ($this->isEditing && $this->editingTemplate) {
            $this->editingTemplate->update($payload);

            log_activity('admin.ai-templates.update', 'Updated an AI template.', [
                'subject_type' => AiTemplate::class,
                'subject_id' => $this->editingTemplate->id,
                'metadata' => [
                    'category_id' => $this->editingTemplate->cate_id,
                    'status' => $this->editingTemplate->status ? 'enabled' : 'disabled',
                ],
            ]);

            $this->statusVariant = 'success';
            $this->statusMessage = __('AI template updated successfully.');
        } else {
            $template = AiTemplate::query()->create($payload);

            log_activity('admin.ai-templates.create', 'Created an AI template.', [
                'subject_type' => AiTemplate::class,
                'subject_id' => $template->id,
                'metadata' => [
                    'category_id' => $template->cate_id,
                    'status' => $template->status ? 'enabled' : 'disabled',
                ],
            ]);

            $this->statusVariant = 'success';
            $this->statusMessage = __('AI template created successfully.');
        }

        $this->showFormModal = false;
        $this->resetFormState();
    }

    public function delete(int $templateId): void
    {
        $template = AiTemplate::query()->findOrFail($templateId);

        $metadata = [
            'category_id' => $template->cate_id,
            'content_preview' => $template->contentPreview(60),
        ];

        $template->delete();

        log_activity('admin.ai-templates.delete', 'Deleted an AI template.', [
            'subject_type' => AiTemplate::class,
            'subject_id' => $templateId,
            'metadata' => $metadata,
        ]);

        if ($this->editingTemplate?->id === $templateId) {
            $this->showFormModal = false;
            $this->resetFormState();
        }

        $this->statusVariant = 'success';
        $this->statusMessage = __('AI template deleted successfully.');

        $this->resetPageIfNeeded();
    }

    public function render(): View
    {
        $query = AiTemplate::query()
            ->with('category:id,name,icon,color')
            ->when($this->search !== '', function ($builder): void {
                $search = trim($this->search);

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('content', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                            $categoryQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('icon', 'like', "%{$search}%");
                        });
                });
            })
            ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->when($this->categoryFilter !== '', fn ($builder) => $builder->where('cate_id', (int) $this->categoryFilter))
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $templates = $query->paginate(15);
        $allTemplates = AiTemplate::query()->get(['status']);

        return view('adminaitemplate::livewire.index', [
            'templates' => $templates,
            'summary' => [
                'total' => $allTemplates->count(),
                'enabled' => $allTemplates->where('status', true)->count(),
                'disabled' => $allTemplates->where('status', false)->count(),
            ],
            'filterCategories' => AiTemplateCategory::query()
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'formCategories' => AiTemplateCategory::query()
                ->orderBy('name')
                ->get(['id', 'name', 'icon', 'color', 'status']),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Templates'),
        ]);
    }

    protected function rules(): array
    {
        return [
            'form.cate_id' => ['required', 'integer', Rule::exists('ai_template_categories', 'id')],
            'form.content' => ['required', 'string', 'max:5000'],
            'form.status' => ['required', Rule::in(['0', '1'])],
        ];
    }

    protected function resetFormState(): void
    {
        $this->editingTemplate = null;
        $this->isEditing = false;
        $this->form = [
            'cate_id' => '',
            'content' => '',
            'status' => '1',
        ];
        $this->resetValidation();
    }

    protected function resetPageIfNeeded(): void
    {
        if ($this->paginators['page'] ?? 1 > 1
            && AiTemplate::query()
                ->with('category:id,name,icon,color')
                ->when($this->search !== '', function ($builder): void {
                    $search = trim($this->search);

                    $builder->where(function ($nested) use ($search): void {
                        $nested
                            ->where('content', 'like', "%{$search}%")
                            ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                                $categoryQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('icon', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($this->statusFilter !== 'all', fn ($builder) => $builder->where('status', (int) $this->statusFilter))
                ->when($this->categoryFilter !== '', fn ($builder) => $builder->where('cate_id', (int) $this->categoryFilter))
                ->paginate(15, ['*'], 'page', $this->getPage())
                ->isEmpty()) {
            $this->previousPage();
        }
    }
}
