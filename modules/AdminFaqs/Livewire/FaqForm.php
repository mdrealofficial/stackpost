<?php

namespace Modules\AdminFaqs\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\AdminFaqs\Models\Faq;

class FaqForm extends Component
{
    public ?Faq $faq = null;

    public string $title = '';

    public string $slug = '';

    public string $content = '';

    public string $status = '1';

    public ?string $statusMessage = null;

    public bool $isEditing = false;

    public function mount(?Faq $faq = null): void
    {
        $this->faq = $faq?->exists ? $faq : null;
        $this->isEditing = $this->faq !== null;

        if ($this->faq) {
            $this->title = (string) $this->faq->title;
            $this->slug = (string) ($this->faq->slug ?? '');
            $this->content = (string) $this->faq->content;
            $this->status = $this->faq->status ? '1' : '0';
        }
    }

    public function save(): mixed
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('faqs', 'slug')->ignore($this->faq?->id)],
            'content' => ['required', 'string'],
            'status' => ['required', 'in:0,1'],
        ]);

        $payload = [
            'title' => $validated['title'],
            'slug' => Str::slug(trim($validated['slug'] ?: $validated['title'])),
            'content' => $validated['content'],
            'status' => (int) $validated['status'],
            'id_secure' => $this->faq?->id_secure ?: Str::random(32),
            'changed' => time(),
            'created' => $this->faq?->created ?: time(),
        ];

        if ($this->faq) {
            $this->faq->update($payload);

            log_activity('admin.faqs.update', 'Updated an FAQ.', [
                'subject_type' => Faq::class,
                'subject_id' => $this->faq->id,
                'metadata' => [
                    'title' => $this->faq->title,
                    'slug' => $this->faq->slug,
                ],
            ]);

            $this->statusMessage = __('FAQ updated successfully.');

            return null;
        }

        $this->faq = Faq::query()->create($payload);
        $this->isEditing = true;

        log_activity('admin.faqs.create', 'Created an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $this->faq->id,
            'metadata' => [
                'title' => $this->faq->title,
                'slug' => $this->faq->slug,
            ],
        ]);

        return $this->redirect(route('admin-faqs.edit', $this->faq), navigate: true);
    }

    public function delete(): mixed
    {
        abort_unless($this->faq, 404);

        $faqId = $this->faq->id;
        $metadata = [
            'title' => $this->faq->title,
            'slug' => $this->faq->slug,
        ];

        $this->faq->delete();

        log_activity('admin.faqs.delete', 'Deleted an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $faqId,
            'metadata' => $metadata,
        ]);

        return $this->redirect(route('admin-faqs.index'), navigate: true);
    }

    public function render(): View
    {
        return view('adminfaqs::livewire.form')
            ->layout(theme_view('layouts.app', 'app'), [
                'title' => $this->isEditing ? __('Edit FAQ') : __('Create FAQ'),
            ]);
    }
}
