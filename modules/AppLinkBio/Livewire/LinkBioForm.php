<?php

namespace Modules\AppLinkBio\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppLinkBio\Models\LinkBioEvent;
use Modules\AppLinkBio\Models\LinkBioPage;
use Modules\AppLinkBio\Support\LinkBioAccess;
use Modules\AppLinkBio\Support\LinkBioTemplateCatalog;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Throwable;

#[Title('Edit Link Bio')]
class LinkBioForm extends Component
{
    public ?int $pageId = null;

    public string $title = '';

    public string $slug = '';

    public string $headline = '';

    public string $description = '';

    public string $accentColor = '#2563eb';

    public string $avatarUrl = '';

    public string $coverUrl = '';

    public string $backgroundUrl = '';

    public int $backgroundOverlay = 28;

    public string $backgroundPosition = 'center';

    public string $backgroundFit = 'cover';

    public string $templateKey = 'aurora';

    public bool $isPublished = false;

    public string $brandingText = 'Powered by Link Bio';

    public bool $canCustomizeBranding = false;

    public string $avatarStyle = 'circle';

    public string $buttonStyle = 'rounded';

    public string $contentAlign = 'left';

    public array $blocks = [];

    public int $activeBlockIndex = 0;

    public string $aiBioBrief = '';

    public string $aiBioTone = 'friendly';

    public string $aiBioLanguage = 'vi';

    public array $aiBioDraft = [];

    public function mount(Request $request, ?LinkBioPage $page = null): void
    {
        $access = app(LinkBioAccess::class);

        abort_unless($access->enabled(auth()->user()), 404);

        $this->canCustomizeBranding = $access->canCustomizeBranding(auth()->user());

        if ($page?->exists) {
            $ownedPage = \Modules\AppTeams\Support\PortalLibraryAccess::query(LinkBioPage::query())
                ->findOrFail($page->id);

            $this->fillFromModel($ownedPage);

            $queryTemplate = trim((string) $request->query('template', ''));
            if ($queryTemplate !== '') {
                $this->templateKey = $this->validTemplateKey($queryTemplate);
            }

            return;
        }

        abort_unless($access->canCreate(auth()->user()), 403);

        $this->templateKey = $this->validTemplateKey((string) $request->query('template', $access->defaultTemplateKey()));
        $this->title = __('My Link Bio');
        $this->slug = $this->uniqueSlug('my-link-bio');
        $this->isPublished = false;
        $this->brandingText = $access->defaultBrandingText();
        $this->blocks = [$this->defaultBlock('links')];
    }

    public function updatedTitle(string $value): void
    {
        if ($this->pageId === null && trim($this->slug) === '') {
            $this->slug = $this->uniqueSlug($value);
        }
    }

    public function setTemplate(string $key): void
    {
        $this->templateKey = $this->validTemplateKey($key);
    }

    public function selectBlock(int $index): void
    {
        if (isset($this->blocks[$index])) {
            $this->activeBlockIndex = $index;
        }
    }

    public function addBlock(string $type): void
    {
        if (! in_array($type, ['links', 'video', 'social', 'header', 'contact', 'gallery', 'embed', 'faq', 'product'], true)) {
            return;
        }

        $this->blocks[] = $this->defaultBlock($type);
        $this->activeBlockIndex = max(0, count($this->blocks) - 1);
    }

    public function moveBlockUp(int $index): void
    {
        if ($index <= 0 || ! isset($this->blocks[$index])) {
            return;
        }

        [$this->blocks[$index - 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index - 1]];
        $this->activeBlockIndex = $index - 1;
    }

    public function moveBlockDown(int $index): void
    {
        if (! isset($this->blocks[$index], $this->blocks[$index + 1])) {
            return;
        }

        [$this->blocks[$index + 1], $this->blocks[$index]] = [$this->blocks[$index], $this->blocks[$index + 1]];
        $this->activeBlockIndex = $index + 1;
    }

    public function reorderBlocks(array $order): void
    {
        $currentActiveBlock = $this->blocks[$this->activeBlockIndex] ?? null;
        $indexes = collect($order)
            ->map(fn ($index) => (int) $index)
            ->filter(fn (int $index) => isset($this->blocks[$index]))
            ->unique()
            ->values();

        if ($indexes->count() !== count($this->blocks)) {
            return;
        }

        $this->blocks = $indexes
            ->map(fn (int $index) => $this->blocks[$index])
            ->values()
            ->all();

        if ($currentActiveBlock !== null) {
            $newIndex = collect($this->blocks)->search(fn ($block) => $block === $currentActiveBlock);
            $this->activeBlockIndex = is_int($newIndex) ? $newIndex : 0;
        }
    }

    public function duplicateBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $block = $this->blocks[$index];
        $title = trim((string) data_get($block, 'title', ''));

        if ($title !== '') {
            data_set($block, 'title', $title.' '.__('Copy'));
        }

        array_splice($this->blocks, $index + 1, 0, [$block]);
        $this->blocks = array_values($this->blocks);
        $this->activeBlockIndex = $index + 1;
    }

    public function removeBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
        $this->activeBlockIndex = max(0, min($this->activeBlockIndex, count($this->blocks) - 1));
    }

    public function addBlockItem(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $type = (string) data_get($this->blocks[$index], 'type', 'links');
        $items = data_get($this->blocks[$index], 'items', []);
        $items[] = $this->defaultBlockItem($type);
        data_set($this->blocks, $index.'.items', array_values($items));
        $this->activeBlockIndex = $index;
    }

    public function removeBlockItem(int $blockIndex, int $itemIndex): void
    {
        $items = data_get($this->blocks, $blockIndex.'.items', []);

        if (! isset($items[$itemIndex])) {
            return;
        }

        unset($items[$itemIndex]);
        data_set($this->blocks, $blockIndex.'.items', array_values($items));
    }

    public function setBlockItemIcon(int $blockIndex, int $itemIndex, string $icon): void
    {
        if (! isset($this->blocks[$blockIndex])) {
            return;
        }

        $items = data_get($this->blocks, $blockIndex.'.items', []);

        if (! isset($items[$itemIndex])) {
            return;
        }

        data_set($this->blocks, $blockIndex.'.items.'.$itemIndex.'.icon', trim($icon) ?: 'fa-solid fa-link');
        $this->activeBlockIndex = $blockIndex;
    }

    public function applyBlockPreset(string $preset): void
    {
        $presets = $this->blockPresets();

        if (! isset($presets[$preset])) {
            return;
        }

        $this->blocks = $this->sanitizeBlocks($presets[$preset]['blocks']);
        $this->activeBlockIndex = 0;
    }

    public function generateAiBio(AiContentStudioService $studio): void
    {
        $validated = $this->validate([
            'aiBioBrief' => ['required', 'string', 'min:8', 'max:1200'],
            'aiBioTone' => ['required', Rule::in(['friendly', 'professional', 'creator', 'minimal', 'sales'])],
            'aiBioLanguage' => ['required', Rule::in(['vi', 'en'])],
        ], [], [
            'aiBioBrief' => __('AI bio brief'),
            'aiBioTone' => __('tone'),
            'aiBioLanguage' => __('language'),
        ]);

        try {
            $draft = $studio->generateLinkBioDraft($validated['aiBioBrief'], [
                'tone' => $validated['aiBioTone'],
                'language' => $validated['aiBioLanguage'],
                'current_title' => $this->title,
                'current_headline' => $this->headline,
            ]);

            $this->aiBioDraft = $this->normalizeAiBioDraft($draft);
            $this->dispatch('app-toast', type: 'success', message: __('AI bio draft generated.'));
        } catch (Throwable $exception) {
            $this->aiBioDraft = [];
            $this->addError('aiBioBrief', $exception->getMessage());
        }
    }

    public function applyAiBioDraft(): void
    {
        if ($this->aiBioDraft === []) {
            return;
        }

        $title = trim((string) data_get($this->aiBioDraft, 'title', ''));
        $headline = trim((string) data_get($this->aiBioDraft, 'headline', ''));
        $description = trim((string) data_get($this->aiBioDraft, 'description', ''));
        $blocks = $this->sanitizeBlocks((array) data_get($this->aiBioDraft, 'blocks', []));

        if ($title !== '') {
            $this->title = $title;

            if ($this->pageId === null) {
                $this->slug = $this->uniqueSlug($title);
            }
        }

        $this->headline = $headline;
        $this->description = $description;

        if ($blocks !== []) {
            $this->blocks = $blocks;
            $this->activeBlockIndex = 0;
        }

        $this->dispatch('app-toast', type: 'success', message: __('AI bio draft applied.'));
    }

    public function saveDraft(): void { $this->isPublished = false; $this->save(); }
    public function publishPage(): void { $this->isPublished = true; $this->save(); }

    public function save(): void
    {
        $access = app(LinkBioAccess::class);

        abort_unless($access->enabled(auth()->user()), 404);

        $this->canCustomizeBranding = $access->canCustomizeBranding(auth()->user());

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'alpha_dash',
                Rule::unique('link_bio_pages', 'slug')->ignore($this->pageId),
            ],
            'headline' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:4000'],
            'accentColor' => ['nullable', 'string', 'max:24'],
            'avatarUrl' => ['nullable', 'string', 'max:2048'],
            'coverUrl' => ['nullable', 'string', 'max:2048'],
            'backgroundUrl' => ['nullable', 'string', 'max:2048'],
            'backgroundOverlay' => ['required', 'integer', 'min:0', 'max:85'],
            'backgroundPosition' => ['required', Rule::in(['top', 'center', 'bottom'])],
            'backgroundFit' => ['required', Rule::in(['cover', 'contain', 'pattern'])],
            'templateKey' => ['required', 'string', Rule::in(collect(LinkBioTemplateCatalog::all())->pluck('key')->all())],
            'brandingText' => ['nullable', 'string', 'max:160'],
            'avatarStyle' => ['required', Rule::in(['circle', 'rounded', 'square'])],
            'buttonStyle' => ['required', Rule::in(['pill', 'rounded', 'square'])],
            'contentAlign' => ['required', Rule::in(['left', 'center'])],
        ], [], [
            'templateKey' => __('template'),
            'avatarUrl' => __('avatar image'),
            'coverUrl' => __('cover image'),
            'backgroundUrl' => __('background image'),
        ]);

        $blocks = $this->sanitizeBlocks($this->blocks);

        $payload = [
            'title' => trim($validated['title']),
            'slug' => Str::slug($validated['slug']),
            'headline' => trim((string) ($validated['headline'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'accent_color' => trim((string) ($validated['accentColor'] ?? '')) ?: '#2563eb',
            'avatar_url' => trim((string) ($validated['avatarUrl'] ?? '')) ?: null,
            'cover_url' => trim((string) ($validated['coverUrl'] ?? '')) ?: null,
            'template_key' => $validated['templateKey'],
            'is_published' => $this->isPublished,
            'blocks' => $blocks,
            'settings' => [
                'branding_text' => $this->canCustomizeBranding
                    ? (trim((string) $validated['brandingText']) ?: null)
                    : $access->defaultBrandingText(),
                'avatar_style' => $validated['avatarStyle'],
                'button_style' => $validated['buttonStyle'],
                'content_align' => $validated['contentAlign'],
                'background_image' => trim((string) ($validated['backgroundUrl'] ?? '')) ?: null,
                'background_overlay' => (int) $validated['backgroundOverlay'],
                'background_position' => $validated['backgroundPosition'],
                'background_fit' => $validated['backgroundFit'],
            ],
        ];

        if (LinkBioPage::hasStatusColumn()) {
            $payload['status'] = $this->isPublished ? 'published' : 'draft';
        }

        if ($this->pageId) {
            $page = $this->ownedQuery()->findOrFail($this->pageId);
            $page->update($payload);
            $message = __('Link bio page updated.');
        } else {
            $page = app(LinkBioAccess::class)->createPage($payload + [
                'owner_user_id' => app(LinkBioAccess::class)->workspaceOwnerUserId(auth()->user()),
                'team_id' => app(LinkBioAccess::class)->currentTeamId(auth()->user()),
            ]);
            $this->pageId = (int) $page->id;
            $message = __('Link bio page created.');
        }

        session()->flash('status', $message);
        $this->dispatch('app-toast', type: 'success', message: $message);
        $this->redirectRoute('portal.link-bio.edit', ['page' => $page->id], navigate: true);
    }

    public function render(): View
    {
        abort_unless(app(LinkBioAccess::class)->enabled(auth()->user()), 404);

        $currentTemplate = LinkBioTemplateCatalog::find($this->templateKey);
        $activeBlock = $this->blocks[$this->activeBlockIndex] ?? null;

        return view('applinkbio::livewire.form', [
            'currentTemplate' => $currentTemplate,
            'templateOptions' => LinkBioTemplateCatalog::all(),
            'activeBlock' => $activeBlock,
            'blockAnalytics' => $this->blockAnalytics(),
            'publicUrl' => $this->pageId ? route('link-bio.public.show', ['slug' => $this->slug]) : null,
            'templatesUrl' => route('portal.link-bio.templates', $this->pageId ? ['page' => $this->pageId] : []),
            'isEditing' => $this->pageId !== null,
            'theme' => $currentTemplate,
            'blockPresets' => $this->blockPresets(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->pageId ? __('Edit Link Bio') : __('Create Link Bio'),
        ]);
    }

    protected function fillFromModel(LinkBioPage $page): void
    {
        $this->pageId = (int) $page->id;
        $this->title = (string) $page->title;
        $this->slug = (string) $page->slug;
        $this->headline = (string) ($page->headline ?? '');
        $this->description = (string) ($page->description ?? '');
        $this->accentColor = (string) ($page->accent_color ?: '#2563eb');
        $this->avatarUrl = (string) ($page->avatar_url ?? '');
        $this->coverUrl = (string) ($page->cover_url ?? '');
        $this->backgroundUrl = (string) $page->backgroundImage();
        $this->backgroundOverlay = $page->backgroundOverlay();
        $this->backgroundPosition = $page->backgroundPosition();
        $this->backgroundFit = $page->backgroundFit();
        $this->templateKey = $this->validTemplateKey((string) $page->template_key);
        $this->isPublished = (bool) $page->is_published;
        $this->brandingText = $this->canCustomizeBranding
            ? (string) ($page->brandingText() ?? app(LinkBioAccess::class)->defaultBrandingText())
            : app(LinkBioAccess::class)->defaultBrandingText();
        $this->avatarStyle = $page->avatarStyle();
        $this->buttonStyle = $page->buttonStyle();
        $this->contentAlign = $page->contentAlign();
        $this->blocks = is_array($page->blocks) && $page->blocks !== []
            ? $this->sanitizeBlocks($page->blocks)
            : [$this->defaultBlock('links')];
        $this->blocks = $this->blocks !== [] ? $this->blocks : [$this->defaultBlock('links')];
        $this->activeBlockIndex = 0;
    }

    protected function ownedQuery()
    {
        return \Modules\AppTeams\Support\PortalLibraryAccess::query(LinkBioPage::query());
    }

    protected function validTemplateKey(string $key): string
    {
        return LinkBioTemplateCatalog::find($key)['key'];
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

    protected function sanitizeBlocks(array $blocks): array
    {
        return collect($blocks)
            ->filter(fn ($block) => in_array((string) data_get($block, 'type', 'links'), ['links', 'video', 'social', 'header', 'contact', 'gallery', 'embed', 'faq', 'product'], true))
            ->map(function ($block): array {
                $type = (string) data_get($block, 'type', 'links');
                $items = collect((array) data_get($block, 'items', []))
                    ->map(function ($item) {
                        return [
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
                        ];
                    })
                    ->filter(fn (array $item) => collect($item)->some(fn ($value) => $value !== ''))
                    ->values()
                    ->all();

                return [
                    'type' => $type,
                    'title' => trim((string) data_get($block, 'title', '')),
                    'subtitle' => trim((string) data_get($block, 'subtitle', '')),
                    'content' => $type === 'embed'
                        ? $this->normalizeEmbedContent(trim((string) data_get($block, 'content', '')))
                        : trim((string) data_get($block, 'content', '')),
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

    protected function normalizeAiBioDraft(array $draft): array
    {
        $blocks = $this->sanitizeBlocks((array) data_get($draft, 'blocks', []));

        return [
            'title' => Str::limit(trim((string) data_get($draft, 'title', '')), 80, ''),
            'headline' => Str::limit(trim((string) data_get($draft, 'headline', '')), 160, ''),
            'description' => Str::limit(trim((string) data_get($draft, 'description', '')), 400, ''),
            'blocks' => $blocks,
        ];
    }

    protected function defaultBlock(string $type): array
    {
        $type = trim($type);

        $block = [
            'type' => $type,
            'title' => Str::headline($type),
            'subtitle' => '',
            'content' => '',
            'url' => '',
            'button_label' => '',
            'button_url' => '',
            'enabled' => true,
            'items' => [],
        ];

        if (in_array($type, ['links', 'social', 'contact', 'gallery', 'faq', 'product'], true)) {
            $block['items'] = [$this->defaultBlockItem($type)];
        }

        return $block;
    }

    protected function defaultBlockItem(string $type): array
    {
        return match ($type) {
            'faq' => [
                'label' => __('Question'),
                'answer' => __('Answer'),
                'url' => '',
                'note' => '',
                'icon' => 'fa-solid fa-circle-question',
                'image' => '',
                'value' => '',
                'price' => '',
                'placeholder' => '',
                'field_type' => 'text',
            ],
            'contact' => [
                'label' => __('Contact item'),
                'note' => __('Phone, email, or address'),
                'value' => '',
                'url' => '',
                'icon' => 'fa-solid fa-address-card',
                'image' => '',
                'price' => '',
                'placeholder' => '',
                'answer' => '',
                'field_type' => 'text',
            ],
            'gallery' => [
                'label' => __('Gallery card'),
                'note' => __('Short caption'),
                'image' => '',
                'url' => '',
                'icon' => 'fa-solid fa-images',
                'value' => '',
                'price' => '',
                'placeholder' => '',
                'answer' => '',
                'field_type' => 'text',
            ],
            'product' => [
                'label' => __('Product'),
                'note' => __('Short description'),
                'price' => '$29',
                'image' => '',
                'url' => '',
                'icon' => 'fa-solid fa-bag-shopping',
                'value' => '',
                'placeholder' => '',
                'answer' => '',
                'field_type' => 'text',
            ],
            'social' => [
                'label' => __('Instagram'),
                'note' => __('Primary social profile'),
                'url' => 'https://example.com',
                'icon' => 'fa-solid fa-hashtag',
                'image' => '',
                'value' => '',
                'price' => '',
                'placeholder' => '',
                'answer' => '',
                'field_type' => 'text',
            ],
            default => [
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
            ],
        };
    }

    protected function blockAnalytics(): array
    {
        if (! $this->pageId) {
            return [];
        }

        $pageViews = LinkBioEvent::query()
            ->where('link_bio_page_id', $this->pageId)
            ->where('type', 'view')
            ->count();

        $clicks = LinkBioEvent::query()
            ->where('link_bio_page_id', $this->pageId)
            ->where('type', 'click')
            ->selectRaw('block_index, item_index, count(*) as aggregate')
            ->groupBy('block_index', 'item_index')
            ->get();

        $analytics = ['page_views' => $pageViews, 'blocks' => []];

        foreach ($clicks as $click) {
            $blockIndex = (int) $click->block_index;
            $itemIndex = (int) $click->item_index;
            $count = (int) $click->aggregate;

            data_set($analytics, "blocks.$blockIndex.items.$itemIndex", [
                'clicks' => $count,
                'ctr' => $pageViews > 0 ? round(($count / $pageViews) * 100, 1) : 0,
            ]);
        }

        return $analytics;
    }

    protected function normalizeEmbedContent(string $content): string
    {
        if ($content === '' || str_contains(strtolower($content), '<iframe')) {
            return $content;
        }

        $embedUrl = $this->resolveVideoEmbedUrl($content);

        if ($embedUrl === null) {
            return $content;
        }

        return '<iframe src="'.$embedUrl.'" title="Embedded content" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    }

    protected function resolveVideoEmbedUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (str_contains($host, 'youtu.be') && $path !== '') {
            return 'https://www.youtube.com/embed/'.rawurlencode(explode('/', $path)[0]);
        }

        if (str_contains($host, 'youtube.com')) {
            $videoId = (string) ($query['v'] ?? '');

            if ($videoId === '' && preg_match('~(?:embed|shorts)/([^/?#]+)~', $path, $matches)) {
                $videoId = $matches[1];
            }

            return $videoId !== '' ? 'https://www.youtube.com/embed/'.rawurlencode($videoId) : null;
        }

        if (str_contains($host, 'vimeo.com') && preg_match('~(\d+)~', $path, $matches)) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }

    protected function blockPresets(): array
    {
        return [
            'creator' => [
                'label' => __('Creator'),
                'icon' => 'fa-solid fa-sparkles',
                'blocks' => [
                    $this->presetBlock('header', __('Start here'), __('A quick intro for new visitors.'), __('New videos, products, and useful links are collected here.')),
                    $this->presetBlock('social', __('Social channels'), __('Follow the main platforms.'), '', [
                        $this->presetItem(__('Instagram'), 'https://instagram.com/', __('Daily updates'), 'fa-brands fa-instagram'),
                        $this->presetItem(__('YouTube'), 'https://youtube.com/', __('Long-form videos'), 'fa-brands fa-youtube'),
                    ]),
                    $this->presetBlock('links', __('Quick links'), __('Most requested resources.'), '', [
                        $this->presetItem(__('Latest post'), 'https://example.com', __('Read the newest update'), 'fa-solid fa-link'),
                        $this->presetItem(__('Media kit'), 'https://example.com', __('For collaborations'), 'fa-solid fa-file-lines'),
                    ]),
                    $this->presetBlock('video', __('Featured video'), __('Introduce your work.'), __('Paste a YouTube or Vimeo URL in Video URL.'), [], '', 'Watch video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
                ],
            ],
            'coach' => [
                'label' => __('Coach'),
                'icon' => 'fa-solid fa-user-graduate',
                'blocks' => [
                    $this->presetBlock('header', __('Work with me'), __('Clear next steps for coaching clients.'), __('Book a session, review programs, or ask a question.')),
                    $this->presetBlock('links', __('Programs'), __('Choose the right path.'), '', [
                        $this->presetItem(__('Book consultation'), 'https://example.com', __('Start with a discovery call'), 'fa-solid fa-calendar-check'),
                        $this->presetItem(__('Coaching package'), 'https://example.com', __('View details and pricing'), 'fa-solid fa-chart-line'),
                    ]),
                    $this->presetBlock('faq', __('FAQ'), __('Common questions before booking.'), '', [
                        ['label' => __('How do sessions work?'), 'answer' => __('We start with your goals, then create a practical plan.'), 'url' => '', 'note' => '', 'icon' => 'fa-solid fa-circle-question', 'image' => '', 'value' => '', 'price' => '', 'placeholder' => '', 'field_type' => 'text'],
                    ]),
                    $this->presetBlock('contact', __('Contact'), __('Send a direct request.'), '', [
                        ['label' => __('Email'), 'value' => 'hello@example.com', 'note' => __('Usually replies within 24 hours'), 'url' => 'mailto:hello@example.com', 'icon' => 'fa-solid fa-envelope', 'image' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                ],
            ],
            'shop' => [
                'label' => __('Shop'),
                'icon' => 'fa-solid fa-bag-shopping',
                'blocks' => [
                    $this->presetBlock('product', __('Best sellers'), __('Popular products this week.'), '', [
                        ['label' => __('Product 1'), 'note' => __('Short description'), 'price' => '$29', 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-bag-shopping', 'value' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                        ['label' => __('Product 2'), 'note' => __('Short description'), 'price' => '$49', 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-bag-shopping', 'value' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                    $this->presetBlock('links', __('Shop links'), __('Everything customers need.'), '', [
                        $this->presetItem(__('New arrivals'), 'https://example.com', __('Browse the latest items'), 'fa-solid fa-store'),
                        $this->presetItem(__('Order support'), 'https://example.com', __('Help with your purchase'), 'fa-solid fa-headset'),
                    ]),
                ],
            ],
            'travel' => [
                'label' => __('Travel'),
                'icon' => 'fa-solid fa-plane-departure',
                'blocks' => [
                    $this->presetBlock('gallery', __('Featured trips'), __('Highlights from recent journeys.'), '', [
                        ['label' => __('Mountain escape'), 'note' => __('Weekend guide'), 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-images', 'value' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                        ['label' => __('City itinerary'), 'note' => __('Food, stays, and routes'), 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-images', 'value' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                    $this->presetBlock('links', __('Travel resources'), __('Plan your next trip.'), '', [
                        $this->presetItem(__('Trip planner'), 'https://example.com', __('Download the checklist'), 'fa-solid fa-map'),
                        $this->presetItem(__('Recommended stays'), 'https://example.com', __('Hotels and homestays'), 'fa-solid fa-bed'),
                    ]),
                ],
            ],
            'restaurant' => [
                'label' => __('Restaurant'),
                'icon' => 'fa-solid fa-utensils',
                'blocks' => [
                    $this->presetBlock('header', __('Today at our restaurant'), __('Menu, booking, and contact in one place.'), __('Reserve a table or view current specials.')),
                    $this->presetBlock('links', __('Dining links'), __('Useful actions for guests.'), '', [
                        $this->presetItem(__('View menu'), 'https://example.com', __('Food and drinks'), 'fa-solid fa-utensils'),
                        $this->presetItem(__('Book a table'), 'https://example.com', __('Reserve your spot'), 'fa-solid fa-calendar-check'),
                    ]),
                    $this->presetBlock('contact', __('Location'), __('Opening hours and directions.'), '', [
                        ['label' => __('Address'), 'value' => __('123 Main Street'), 'note' => __('Open daily'), 'url' => 'https://maps.google.com', 'icon' => 'fa-solid fa-location-dot', 'image' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                ],
            ],
            'real_estate' => [
                'label' => __('Real estate'),
                'icon' => 'fa-solid fa-house-building',
                'blocks' => [
                    $this->presetBlock('gallery', __('Featured listings'), __('Properties worth seeing.'), '', [
                        ['label' => __('Apartment listing'), 'note' => __('2 bedrooms, city view'), 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-house', 'value' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                        ['label' => __('Villa listing'), 'note' => __('Family home with garden'), 'image' => '', 'url' => 'https://example.com', 'icon' => 'fa-solid fa-house', 'value' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                    $this->presetBlock('links', __('Buyer links'), __('Move faster with these resources.'), '', [
                        $this->presetItem(__('Schedule viewing'), 'https://example.com', __('Pick a time'), 'fa-solid fa-calendar-check'),
                        $this->presetItem(__('Download brochure'), 'https://example.com', __('PDF property details'), 'fa-solid fa-file-pdf'),
                    ]),
                    $this->presetBlock('contact', __('Agent contact'), __('Ask about availability.'), '', [
                        ['label' => __('Call agent'), 'value' => '+1 555 0100', 'note' => __('Available business hours'), 'url' => 'tel:+15550100', 'icon' => 'fa-solid fa-phone', 'image' => '', 'price' => '', 'placeholder' => '', 'answer' => '', 'field_type' => 'text'],
                    ]),
                ],
            ],
        ];
    }

    protected function presetBlock(string $type, string $title, string $subtitle = '', string $content = '', array $items = [], string $url = '', string $buttonLabel = '', string $buttonUrl = ''): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'content' => $content,
            'url' => $url,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
            'enabled' => true,
            'items' => $items,
        ];
    }

    protected function presetItem(string $label, string $url, string $note, string $icon): array
    {
        return [
            'label' => $label,
            'url' => $url,
            'note' => $note,
            'icon' => $icon,
            'image' => '',
            'value' => '',
            'price' => '',
            'placeholder' => '',
            'answer' => '',
            'field_type' => 'text',
        ];
    }

}
