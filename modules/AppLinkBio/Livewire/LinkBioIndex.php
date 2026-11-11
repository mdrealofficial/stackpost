<?php

namespace Modules\AppLinkBio\Livewire;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppLinkBio\Models\LinkBioEvent;
use Modules\AppLinkBio\Models\LinkBioPage;
use Modules\AppLinkBio\Support\LinkBioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Throwable;

#[Title('Link Bio')]
class LinkBioIndex extends Component
{
    use AIStudioAccess;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public string $aiBioBrief = '';

    public string $aiBioTone = 'professional';

    public string $aiBioLanguage = 'en';

    public array $aiBioDraft = [];

    public function mount(): void
    {
        $this->aiBioTone = (string) $this->aiStudioSetting('default_tone', 'professional');
        $this->aiBioLanguage = (string) $this->aiStudioSetting('default_language', (string) (app()->getLocale() ?: 'en'));
    }

    public function generateAiBio(AiContentStudioService $studio): void
    {
        $access = app(LinkBioAccess::class);

        abort_unless($access->enabled(auth()->user()), 404);
        abort_unless($access->canUseAiBio(auth()->user()), 403);

        $planOwner = $this->aiStudioPlanOwner();

        $validated = $this->validate([
            'aiBioBrief' => ['required', 'string', 'min:8', 'max:1200'],
            'aiBioTone' => ['required', Rule::in(['professional', 'friendly', 'sales', 'educational', 'bold', 'casual'])],
            'aiBioLanguage' => ['required', 'string', 'max:12'],
        ], [], [
            'aiBioBrief' => __('AI bio brief'),
            'aiBioTone' => __('tone'),
            'aiBioLanguage' => __('language'),
        ]);

        try {
            if (function_exists('credit_service')) {
                credit_service()->ensureCanConsume($planOwner, 'link_bio_ai_assistant');
            }

            $this->aiBioDraft = $this->normalizeAiBioDraft($studio->generateLinkBioDraft($validated['aiBioBrief'], [
                'tone' => $validated['aiBioTone'],
                'language' => $validated['aiBioLanguage'],
                ...$this->aiStudioWorkspacePromptConfig(),
            ]));

            if (function_exists('consume_credits')) {
                consume_credits($planOwner, 'link_bio_ai_assistant', [
                    'feature' => 'link-bio.ai-assistant',
                    'metadata' => [
                        'language' => $validated['aiBioLanguage'],
                        'tone' => $validated['aiBioTone'],
                    ],
                ]);
            }

            $this->dispatch('app-toast', type: 'success', message: __('AI bio draft generated.'));
        } catch (Throwable $exception) {
            $this->aiBioDraft = [];
            $this->addError('aiBioBrief', $exception->getMessage());
        }
    }

    public function createFromAiBioDraft(LinkBioAccess $access): void
    {
        abort_unless($access->enabled(auth()->user()), 404);
        abort_unless($access->canUseAiBio(auth()->user()), 403);
        abort_unless($access->canCreate(auth()->user()), 403);

        if ($this->aiBioDraft === []) {
            $this->addError('aiBioBrief', __('Generate an AI bio draft first.'));

            return;
        }

        $title = trim((string) data_get($this->aiBioDraft, 'title', '')) ?: __('My Link Bio');
        $blocks = $this->sanitizeBlocks((array) data_get($this->aiBioDraft, 'blocks', []));

        $defaultStatus = 'draft';

        $page = app(LinkBioAccess::class)->createPage([
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'headline' => trim((string) data_get($this->aiBioDraft, 'headline', '')) ?: null,
            'description' => trim((string) data_get($this->aiBioDraft, 'description', '')) ?: null,
            'accent_color' => '#2563eb',
            'avatar_url' => null,
            'cover_url' => null,
            'template_key' => $access->defaultTemplateKey(),
            'is_published' => $defaultStatus === 'published',
            'blocks' => $blocks !== [] ? $blocks : [$this->defaultBlock('links')],
            'settings' => [
                'branding_text' => $access->defaultBrandingText(),
                'avatar_style' => 'circle',
                'button_style' => 'rounded',
                'content_align' => 'left',
            ],
            'owner_user_id' => $access->workspaceOwnerUserId(auth()->user()),
            'team_id' => $access->currentTeamId(auth()->user()),
        ]);

        if (LinkBioPage::hasStatusColumn()) {
            $page->update(['status' => $defaultStatus]);
        }

        $this->dispatch('app-toast', type: 'success', message: __('AI bio page created.'));
        $this->redirectRoute('portal.link-bio.edit', ['page' => $page->id], navigate: true);
    }

    public function togglePublish(int $pageId): void
    {
        $page = $this->query()->findOrFail($pageId);

        $payload = [
            'is_published' => ! $page->is_published,
        ];

        if (LinkBioPage::hasStatusColumn()) {
            $payload['status'] = ! $page->is_published ? 'published' : 'draft';
        }

        $page->update($payload);

        $this->dispatch('app-toast', type: 'success', message: __('Page status updated.'));
    }

    public function delete(int $pageId): void
    {
        $this->query()->findOrFail($pageId)->delete();
        $this->dispatch('app-toast', type: 'success', message: __('Page deleted.'));
    }

    public function duplicate(int $pageId, LinkBioAccess $access): void
    {
        abort_unless($access->enabled(auth()->user()), 404);
        abort_unless($access->canCreate(auth()->user()), 403);

        $source = $this->query()->findOrFail($pageId);
        $copy = $source->replicate(['slug', 'is_published', 'status']);
        $copy->title = trim((string) $source->title).' '.__('Copy');
        $copy->slug = $this->uniqueSlug($copy->title);
        $copy->is_published = false;

        if (LinkBioPage::hasStatusColumn()) {
            $copy->status = 'draft';
        }

        $access->createPage($copy->getAttributes());

        $this->dispatch('app-toast', type: 'success', message: __('Bio page duplicated.'));
    }

    public function render(LinkBioAccess $access): View
    {
        abort_unless($access->enabled(auth()->user()), 404);

        $baseQuery = $this->query();
        $hasStatusColumn = LinkBioPage::hasStatusColumn();

        $pages = (clone $baseQuery)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($nested): void {
                    $nested
                        ->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%')
                        ->orWhere('headline', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) use ($hasStatusColumn): void {
                if ($this->statusFilter === 'published') {
                    $query->published();

                    return;
                }

                $query->where(function ($nested) use ($hasStatusColumn): void {
                    $nested->where('is_published', false);

                    if ($hasStatusColumn) {
                        $nested->orWhere('status', 'draft');
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $pageIds = $pages->pluck('id')->map(fn ($id) => (int) $id)->all();
        $analytics = $this->analyticsForPages($pageIds);
        $qrCodes = collect($pages)
            ->mapWithKeys(fn (LinkBioPage $page) => [
                (int) $page->id => $this->makeQrCodeSvg(route('link-bio.public.show', ['slug' => $page->slug])),
            ])
            ->all();

        return view('applinkbio::livewire.index', [
            'pages' => $pages,
            'analytics' => $analytics,
            'qrCodes' => $qrCodes,
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'published' => (clone $baseQuery)->published()->count(),
                'draft' => (clone $baseQuery)->where(function ($nested) use ($hasStatusColumn): void {
                    $nested->where('is_published', false);

                    if ($hasStatusColumn) {
                        $nested->orWhere('status', 'draft');
                    }
                })->count(),
                'limit' => $access->maxPages(auth()->user()),
            ],
            'canCreate' => $access->canCreate(auth()->user()),
            'canUseAiBio' => $access->canUseAiBio(auth()->user()),
            'aiBioCreditPreview' => $this->aiStudioCreditPreview('link_bio_ai_assistant'),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Link Bio'),
        ]);
    }

    protected function query()
    {
        abort_unless(app(LinkBioAccess::class)->enabled(auth()->user()), 404);
        $ownerId = app(LinkBioAccess::class)->workspaceOwnerUserId(auth()->user());

        return \Modules\AppTeams\Support\PortalLibraryAccess::query(LinkBioPage::query());
    }

    protected function normalizeAiBioDraft(array $draft): array
    {
        return [
            'title' => Str::limit(trim((string) data_get($draft, 'title', '')), 80, ''),
            'headline' => Str::limit(trim((string) data_get($draft, 'headline', '')), 160, ''),
            'description' => Str::limit(trim((string) data_get($draft, 'description', '')), 400, ''),
            'blocks' => $this->sanitizeBlocks((array) data_get($draft, 'blocks', [])),
        ];
    }

    protected function sanitizeBlocks(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn ($block) => in_array((string) data_get($block, 'type', 'links'), ['links', 'video', 'social', 'header', 'contact', 'gallery', 'embed', 'faq', 'product'], true))
            ->map(function ($block): array {
                $type = (string) data_get($block, 'type', 'links');
                $items = collect((array) data_get($block, 'items', []))
                    ->map(fn ($item) => [
                        'label' => trim((string) data_get($item, 'label', '')),
                        'url' => trim((string) data_get($item, 'url', '')),
                        'note' => trim((string) data_get($item, 'note', '')),
                        'icon' => trim((string) data_get($item, 'icon', '')),
                        'image' => trim((string) data_get($item, 'image', '')),
                        'value' => trim((string) data_get($item, 'value', '')),
                        'price' => trim((string) data_get($item, 'price', '')),
                        'placeholder' => trim((string) data_get($item, 'placeholder', '')),
                        'answer' => trim((string) data_get($item, 'answer', '')),
                        'field_type' => trim((string) data_get($item, 'field_type', 'text')),
                    ])
                    ->filter(fn (array $item) => collect($item)->some(fn ($value) => $value !== ''))
                    ->values()
                    ->all();

                return [
                    'type' => $type,
                    'title' => trim((string) data_get($block, 'title', '')),
                    'subtitle' => trim((string) data_get($block, 'subtitle', '')),
                    'content' => trim((string) data_get($block, 'content', '')),
                    'url' => trim((string) data_get($block, 'url', '')),
                    'button_label' => trim((string) data_get($block, 'button_label', '')),
                    'button_url' => trim((string) data_get($block, 'button_url', '')),
                    'enabled' => (bool) data_get($block, 'enabled', true),
                    'items' => $items,
                ];
            })
            ->filter(fn (array $block) => $block['enabled'] || $block['title'] !== '' || $block['content'] !== '' || $block['items'] !== [])
            ->values()
            ->all();
    }

    protected function defaultBlock(string $type): array
    {
        return [
            'type' => $type,
            'title' => Str::headline($type),
            'subtitle' => '',
            'content' => '',
            'url' => '',
            'button_label' => '',
            'button_url' => '',
            'enabled' => true,
            'items' => [[
                'label' => __('Item'),
                'note' => __('Supporting text'),
                'url' => 'https://example.com',
                'icon' => 'fa-solid fa-link',
                'image' => '',
                'value' => '',
                'price' => '',
                'placeholder' => '',
                'answer' => '',
                'field_type' => 'text',
            ]],
        ];
    }

    protected function uniqueSlug(string $value): string
    {
        $slug = Str::slug($value) ?: 'link-bio';
        $base = $slug;
        $counter = 2;

        while (LinkBioPage::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function analyticsForPages(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        return LinkBioEvent::query()
            ->whereIn('link_bio_page_id', $pageIds)
            ->selectRaw('link_bio_page_id, type, count(*) as aggregate')
            ->groupBy('link_bio_page_id', 'type')
            ->get()
            ->groupBy('link_bio_page_id')
            ->map(function ($events): array {
                $views = (int) optional($events->firstWhere('type', 'view'))->aggregate;
                $clicks = (int) optional($events->firstWhere('type', 'click'))->aggregate;

                return [
                    'views' => $views,
                    'clicks' => $clicks,
                    'ctr' => $views > 0 ? round(($clicks / $views) * 100, 1) : 0,
                ];
            })
            ->all();
    }

    protected function makeQrCodeSvg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                220,
                2,
                null,
                null,
                Fill::uniformColor(new Rgb(15, 23, 42), new Rgb(255, 255, 255))
            ),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($value);
    }
}
