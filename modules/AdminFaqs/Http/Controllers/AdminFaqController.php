<?php

namespace Modules\AdminFaqs\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\AdminFaqs\Models\Faq;

class AdminFaqController extends Controller
{
    public function index(Request $request): View
    {
        $query = Faq::query()
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $search = trim((string) $request->string('q'));

                $builder->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $faqs = $query->paginate(15)->withQueryString();
        $allFaqs = Faq::query()->get(['status']);

        return view('adminfaqs::index', [
            'faqs' => $faqs,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
            ],
            'summary' => [
                'total' => $allFaqs->count(),
                'enabled' => $allFaqs->where('status', true)->count(),
                'disabled' => $allFaqs->where('status', false)->count(),
            ],
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin-faqs.index', [
            'faq_modal' => 'create',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::query()->create($this->validateFaq($request));

        log_activity('admin.faqs.create', 'Created an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $faq->id,
            'metadata' => [
                'title' => $faq->title,
                'slug' => $faq->slug,
            ],
        ]);

        return redirect()
            ->route('admin-faqs.index')
            ->with('status', __('FAQ created successfully.'));
    }

    public function edit(Faq $faq): RedirectResponse
    {
        return redirect()->route('admin-faqs.index', [
            'faq_modal' => 'edit',
            'faq' => $faq->id,
        ]);
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validateFaq($request, $faq));

        log_activity('admin.faqs.update', 'Updated an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $faq->id,
            'metadata' => [
                'title' => $faq->title,
                'slug' => $faq->slug,
            ],
        ]);

        return redirect()
            ->route('admin-faqs.index')
            ->with('status', __('FAQ updated successfully.'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $metadata = [
            'title' => $faq->title,
            'slug' => $faq->slug,
        ];

        $faq->delete();

        log_activity('admin.faqs.delete', 'Deleted an FAQ.', [
            'subject_type' => Faq::class,
            'subject_id' => $faq->id,
            'metadata' => $metadata,
        ]);

        return redirect()
            ->route('admin-faqs.index')
            ->with('status', __('FAQ deleted successfully.'));
    }

    protected function validateFaq(Request $request, ?Faq $faq = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('faqs', 'slug')->ignore($faq?->id)],
            'content' => ['required', 'string'],
            'status' => ['required', 'boolean'],
        ]);

        $validated['slug'] = Str::slug(trim($validated['slug'] ?: $validated['title']));
        $validated['id_secure'] = $faq?->id_secure ?: Str::random(32);
        $validated['changed'] = time();
        $validated['created'] = $faq?->created ?: time();

        return $validated;
    }

}
