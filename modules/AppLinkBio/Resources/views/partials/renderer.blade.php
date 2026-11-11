@php
    $preview = (bool) ($preview ?? false);
    $editable = $preview && (bool) ($editable ?? false);
    $theme = $theme ?? \Modules\AppLinkBio\Support\LinkBioTemplateCatalog::find($templateKey ?? 'aurora');
    $blocks = is_array($blocks ?? null) ? $blocks : [];
    $title = trim((string) ($title ?? ''));
    $headline = trim((string) ($headline ?? ''));
    $description = trim((string) ($description ?? ''));
    $brandingText = trim((string) ($brandingText ?? ''));
    $accentColor = trim((string) ($accentColor ?? '#2563eb')) ?: '#2563eb';
    $templateAccentColor = trim((string) data_get($theme, 'theme.accent', data_get($theme, 'theme.button_bg', '#2563eb'))) ?: '#2563eb';

    if (strtolower($accentColor) === '#2563eb') {
        $accentColor = $templateAccentColor;
    }
    $backgroundUrl = trim((string) ($backgroundUrl ?? ''));
    $backgroundOverlay = max(0, min(85, (int) ($backgroundOverlay ?? 28)));
    $backgroundPosition = in_array(($backgroundPosition ?? 'center'), ['top', 'center', 'bottom'], true) ? $backgroundPosition : 'center';
    $backgroundFit = in_array(($backgroundFit ?? 'cover'), ['cover', 'contain', 'pattern'], true) ? $backgroundFit : 'cover';
    $backgroundSize = match ($backgroundFit) {
        'contain' => 'contain',
        'pattern' => '180px auto',
        default => 'cover',
    };
    $backgroundRepeat = $backgroundFit === 'pattern' ? 'repeat' : 'no-repeat';
    $avatarInitials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($title !== '' ? $title : 'LB', 0, 2));
    $contentAlign = ($contentAlign ?? 'left') === 'center' ? 'center' : 'left';
    $avatarRadius = match ($avatarStyle ?? 'circle') {
        'rounded' => '26px',
        'square' => '16px',
        default => '999px',
    };
    $buttonRadius = match ($buttonStyle ?? 'rounded') {
        'pill' => '999px',
        'square' => '16px',
        default => '22px',
    };
    $rootClass = $preview ? 'lb-root lb-root--preview' : 'lb-root';
    $textColor = data_get($theme, 'theme.text', '#ffffff');
    $mutedColor = data_get($theme, 'theme.muted', 'rgba(255,255,255,0.72)');
    $surfaceTextColor = data_get($theme, 'theme.surface_text', $textColor);
    $surfaceMutedColor = data_get($theme, 'theme.surface_muted', $mutedColor);
    $pageSlug = trim((string) ($pageSlug ?? ''));
    $normalizeIconClass = static function (mixed $icon, string $fallback = 'fa-solid fa-link'): string {
        $icon = trim((string) $icon);

        if ($icon === '') {
            return $fallback;
        }

        if (! str_contains($icon, 'fa-')) {
            return $fallback;
        }

        if (! preg_match('/(^|\s)(fa|fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands)(\s|$)/', $icon)) {
            return 'fa-solid '.$icon;
        }

        if (str_contains($icon, 'fa-brands')) {
            return $icon;
        }

        return preg_replace('/(^|\s)(fa|fa-light|fa-regular|fa-thin|fa-duotone)(\s|$)/', ' fa-solid ', $icon) ?: $fallback;
    };
    $resolveVideoEmbedUrl = static function (string $url): ?string {
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
    };
    $extractEmbedIframeUrl = static function (string $content): ?string {
        if (! preg_match('~<iframe\b[^>]*\bsrc=(["\'])(.*?)\1~is', $content, $matches)) {
            return null;
        }

        $src = html_entity_decode(trim((string) ($matches[2] ?? '')), ENT_QUOTES | ENT_HTML5);

        if ($src === '') {
            return null;
        }

        $parts = parse_url($src);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https'], true) ? $src : null;
    };
@endphp
<style>
    .lb-root{width:100%;display:flex;justify-content:center}
    .lb-root *{box-sizing:border-box}
    .lb-shell{width:min(100%,430px);padding:18px;border-radius:34px;background:var(--lb-shell-bg);box-shadow:0 30px 80px -45px rgba(15,23,42,.35)}
    .lb-device{overflow:hidden;border-radius:28px;background:var(--lb-device-bg);border:1px solid var(--lb-surface-border)}
    .lb-cover{height:132px;background:var(--lb-cover-bg);background-size:cover;background-position:center;position:relative}
    .lb-profile{padding:0 28px 28px;position:relative;text-align:var(--lb-align)}
    .lb-avatar{width:88px;height:88px;border-radius:var(--lb-avatar-radius);overflow:hidden;border:4px solid rgba(255,255,255,.88);background:#fff;box-shadow:0 20px 40px -24px rgba(15,23,42,.45);margin-top:-44px}
    .lb-profile[data-align="center"] .lb-avatar{margin-left:auto;margin-right:auto}
    .lb-title{margin:18px 0 0;font-size:18px;line-height:1.2;font-weight:700;color:var(--lb-text)}
    .lb-headline{margin:10px 0 0;font-size:13px;line-height:1.6;color:var(--lb-muted)}
    .lb-description{margin:8px 0 0;font-size:12px;line-height:1.7;color:var(--lb-muted)}
    .lb-section{margin-top:18px;padding:16px;border-radius:22px;border:1px solid var(--lb-surface-border);background:var(--lb-surface-bg)}
    .lb-section-title{margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--lb-surface-muted)}
    .lb-section-subtitle{margin:0;font-size:12px;line-height:1.6;color:var(--lb-surface-muted)}
    .lb-stack{display:grid;gap:12px;margin-top:14px}
    .lb-card{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:var(--lb-button-radius);border:1px solid var(--lb-surface-border);background:var(--lb-surface-bg);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .lb-card:hover{transform:translateY(-1px);box-shadow:0 18px 34px -26px rgba(15,23,42,.5);border-color:color-mix(in srgb,var(--lb-accent) 42%,var(--lb-surface-border))}
    .lb-card--column{display:block}
    .lb-card-icon{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:color-mix(in srgb, var(--lb-accent) 16%, white 84%);color:var(--lb-accent);flex:0 0 34px;font-size:14px}
    .lb-card-icon i{display:inline-flex;align-items:center;justify-content:center;width:1.25em;height:1.25em;line-height:1}
    .lb-card-thumb{width:54px;height:54px;border-radius:16px;overflow:hidden;background:rgba(255,255,255,.16);flex:0 0 54px}
    .lb-card-thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .lb-card-copy{min-width:0;flex:1}
    .lb-card-label{margin:0;font-size:14px;font-weight:700;color:var(--lb-surface-text);word-break:break-word}
    .lb-card-note{margin:4px 0 0;font-size:12px;line-height:1.55;color:var(--lb-surface-muted);word-break:break-word}
    .lb-card-meta{margin-top:8px;font-size:12px;color:var(--lb-surface-muted)}
    .lb-card-action{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:40px;padding:0 15px;border-radius:999px;background:var(--lb-button-bg);color:var(--lb-button-text);font-size:12px;font-weight:800;text-decoration:none;box-shadow:0 16px 28px -18px color-mix(in srgb,var(--lb-button-bg) 72%,#000);transition:transform .18s ease,box-shadow .18s ease,filter .18s ease}
    .lb-card-action:hover{transform:translateY(-1px);filter:saturate(1.08) brightness(1.03);box-shadow:0 20px 34px -18px color-mix(in srgb,var(--lb-button-bg) 82%,#000)}
    .lb-card-action:active{transform:translateY(0);box-shadow:0 10px 20px -18px color-mix(in srgb,var(--lb-button-bg) 72%,#000)}
    .lb-card-action--disabled{cursor:not-allowed;opacity:.58}
    .lb-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px}
    .lb-gallery-card{overflow:hidden;border-radius:20px;border:1px solid var(--lb-surface-border);background:var(--lb-surface-bg)}
    .lb-gallery-image{height:92px;background:rgba(255,255,255,.12)}
    .lb-gallery-image img{width:100%;height:100%;object-fit:cover;display:block}
    .lb-gallery-copy{padding:12px}
    .lb-input{width:100%;padding:14px 16px;border-radius:18px;border:1px solid var(--lb-surface-border);background:rgba(255,255,255,.55);font-size:13px;color:#0f172a}
    .lb-submit{margin-top:12px;display:inline-flex;align-items:center;justify-content:center;width:100%;padding:14px 18px;border-radius:var(--lb-button-radius);background:var(--lb-button-bg);color:var(--lb-button-text);font-weight:800;box-shadow:0 18px 34px -22px color-mix(in srgb,var(--lb-button-bg) 80%,#000)}
    .lb-rich-content{margin-top:12px;border-radius:18px;border:1px solid var(--lb-surface-border);background:color-mix(in srgb,var(--lb-surface-bg) 72%,transparent);padding:14px 16px;color:var(--lb-surface-text);font-size:13px;line-height:1.65;white-space:pre-wrap;word-break:break-word}
    .lb-video-frame{margin-top:14px;overflow:hidden;border-radius:18px;border:1px solid var(--lb-surface-border);background:#050816;aspect-ratio:16/9}
    .lb-video-frame iframe{width:100%;height:100%;border:0;display:block}
    .lb-embed-frame{margin-top:14px;overflow:hidden;border-radius:18px;border:1px solid var(--lb-surface-border);background:#050816;aspect-ratio:16/9}
    .lb-embed-frame iframe{width:100%;height:100%;border:0;display:block}
    .lb-placeholder{margin-top:12px;padding:16px;border:1px dashed var(--lb-surface-border);border-radius:18px;text-align:center;font-size:12px;color:var(--lb-surface-muted)}
    .lb-inline-cta{margin-top:12px;display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 16px;border-radius:var(--lb-button-radius);background:var(--lb-button-bg);color:var(--lb-button-text);font-size:12px;font-weight:800;text-decoration:none;box-shadow:0 16px 28px -20px color-mix(in srgb,var(--lb-button-bg) 78%,#000)}
    .lb-footer{padding:20px 28px 26px;text-align:center;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:var(--lb-muted)}
    .lb-empty{padding:16px;border:1px dashed var(--lb-surface-border);border-radius:18px;text-align:center;font-size:12px;color:var(--lb-surface-muted)}
    .lb-inline-field{width:100%;border:1px dashed transparent;background:transparent;color:var(--lb-text);font:inherit;letter-spacing:inherit;text-transform:inherit;outline:none;caret-color:var(--lb-accent)}
    .lb-inline-field:hover,.lb-inline-field:focus{border-color:color-mix(in srgb,var(--lb-accent) 52%,transparent);background:color-mix(in srgb,var(--lb-accent) 8%,transparent)}
    .lb-inline-field::placeholder{color:var(--lb-muted);opacity:.72}
    textarea.lb-inline-field{resize:none;overflow:hidden}
    .lb-title.lb-inline-field{display:block;padding:4px 6px;margin:14px -6px -4px;border-radius:10px;color:var(--lb-text)}
    .lb-headline.lb-inline-field,.lb-description.lb-inline-field{display:block;padding:4px 6px;margin-left:-6px;margin-right:-6px;border-radius:10px;color:var(--lb-muted)}
    .lb-section-title.lb-inline-field{display:block;padding:3px 5px;margin:-3px -5px 1px;border-radius:8px;color:var(--lb-surface-muted)}
    .lb-section-subtitle.lb-inline-field{display:block;padding:3px 5px;margin:-3px -5px 0;border-radius:8px;color:var(--lb-surface-muted);text-transform:none;letter-spacing:0}
    .lb-card-label.lb-inline-field,.lb-card-note.lb-inline-field{display:block;padding:3px 5px;margin-left:-5px;margin-right:-5px;border-radius:8px}
    .lb-card-label.lb-inline-field{color:var(--lb-surface-text)}
    .lb-card-note.lb-inline-field{margin-top:1px;color:var(--lb-surface-muted)}
    .lb-card-meta.lb-inline-field{display:block;width:100%;padding:3px 5px;margin:5px -5px -3px;border-radius:8px;color:var(--lb-surface-muted)}
    .lb-root--preview .lb-shell{width:min(100%,640px)}
    .lb-root--preview .lb-device{max-width:100%;margin:0 auto}
</style>
<div class="{{ $rootClass }}">
    <div
        class="lb-shell"
        style="
            --lb-page-bg: {{ data_get($theme, 'theme.page_bg', '#0f172a') }};
            --lb-shell-bg: {{ data_get($theme, 'theme.page_bg', '#0f172a') }};
            --lb-device-bg: {{ data_get($theme, 'theme.shell_bg', '#ffffff') }};
            --lb-surface-bg: {{ data_get($theme, 'theme.surface_bg', 'rgba(255,255,255,0.08)') }};
            --lb-surface-border: {{ data_get($theme, 'theme.surface_border', 'rgba(255,255,255,0.14)') }};
            --lb-text: {{ $textColor }};
            --lb-muted: {{ $mutedColor }};
            --lb-surface-text: {{ $surfaceTextColor }};
            --lb-surface-muted: {{ $surfaceMutedColor }};
            --lb-accent: {{ $accentColor }};
            --lb-button-bg: {{ data_get($theme, 'theme.button_bg', $accentColor) }};
            --lb-button-text: {{ data_get($theme, 'theme.button_text', '#ffffff') }};
            --lb-avatar-radius: {{ $avatarRadius }};
            --lb-button-radius: {{ $buttonRadius }};
            --lb-align: {{ $contentAlign }};
            --lb-background-size: {{ $backgroundSize }};
            --lb-background-position: {{ $backgroundPosition }};
            --lb-background-repeat: {{ $backgroundRepeat }};
            background-color: {{ data_get($theme, 'theme.page_bg', '#0f172a') }};
            @if($backgroundUrl !== '')
                background-image: linear-gradient(rgba(15,23,42,{{ $backgroundOverlay / 100 }}), rgba(15,23,42,{{ $backgroundOverlay / 100 }})), url('{{ $backgroundUrl }}');
                background-size: {{ $backgroundSize }};
                background-position: {{ $backgroundPosition }};
                background-repeat: {{ $backgroundRepeat }};
            @endif
        "
    >
        <div class="lb-device">
            <div class="lb-cover" style="@if(trim((string) ($coverUrl ?? '')) !== '') background-image: linear-gradient(180deg, rgba(15,23,42,0.10), rgba(15,23,42,0.22)), url('{{ $coverUrl }}'); @elseif($backgroundUrl !== '') background: transparent; @else background: {{ data_get($theme, 'preview', 'linear-gradient(135deg, #2563eb, #0ea5e9)') }}; @endif"></div>

            <div class="lb-profile" data-align="{{ $contentAlign }}">
                <div class="lb-avatar">
                    @if (trim((string) ($avatarUrl ?? '')) !== '')
                        <img src="{{ $avatarUrl }}" alt="{{ $title }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;background:linear-gradient(135deg, {{ $accentColor }}, rgba(255,255,255,.75));color:#fff;">
                            {{ $avatarInitials }}
                        </div>
                    @endif
                </div>

                @if ($editable)
                    <input class="lb-title lb-inline-field" wire:model.live.debounce.300ms="title" value="{{ $title }}" placeholder="{{ __('Your title') }}">
                @else
                    <h1 class="lb-title">{{ $title !== '' ? $title : __('Your title') }}</h1>
                @endif

                @if ($editable || $headline !== '')
                    @if ($editable)
                        <input class="lb-headline lb-inline-field" wire:model.live.debounce.300ms="headline" value="{{ $headline }}" placeholder="{{ __('Add a headline') }}">
                    @else
                        <p class="lb-headline">{{ $headline }}</p>
                    @endif
                @endif

                @if ($editable || $description !== '')
                    @if ($editable)
                        <textarea class="lb-description lb-inline-field" wire:model.live.debounce.300ms="description" rows="2" placeholder="{{ __('Add a description') }}">{{ $description }}</textarea>
                    @else
                        <p class="lb-description">{{ $description }}</p>
                    @endif
                @endif

                @forelse ($blocks as $blockIndex => $block)
                    @php
                        $type = (string) data_get($block, 'type', 'links');
                        $items = (array) data_get($block, 'items', []);
                    @endphp
                    @continue(! in_array($type, ['links', 'social', 'contact', 'gallery', 'faq', 'product', 'header', 'embed', 'video'], true))
                    <section class="lb-section" @if($editable) wire:click="selectBlock({{ $blockIndex }})" style="cursor:pointer;" @endif>
                        @if ($editable || trim((string) data_get($block, 'title', '')) !== '')
                            @if ($editable)
                                <input class="lb-section-title lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.title" value="{{ data_get($block, 'title') }}" placeholder="{{ __('Block title') }}">
                            @else
                                <p class="lb-section-title">{{ data_get($block, 'title') }}</p>
                            @endif
                        @endif
                        @if ($editable || trim((string) data_get($block, 'subtitle', '')) !== '')
                            @if ($editable)
                                <input class="lb-section-subtitle lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.subtitle" value="{{ data_get($block, 'subtitle') }}" placeholder="{{ __('Block subtitle') }}">
                            @else
                                <p class="lb-section-subtitle">{{ data_get($block, 'subtitle') }}</p>
                            @endif
                        @endif

                        @if (in_array($type, ['links', 'social', 'contact'], true))
                            <div class="lb-stack">
                                @foreach ($items as $itemIndex => $item)
                                    @php
                                        $itemUrl = trim((string) data_get($item, 'url', ''));
                                        $itemActionUrl = $itemUrl;
                                        if (! $editable && $pageSlug !== '' && $itemUrl !== '') {
                                            $itemActionUrl = route('link-bio.public.click', ['slug' => $pageSlug, 'block' => $blockIndex, 'item' => $itemIndex]);
                                        }
                                        $itemIcon = $normalizeIconClass(data_get($item, 'icon', 'fa-solid fa-link'));
                                    @endphp
                                    <div class="lb-card">
                                        <span class="lb-card-icon">
                                            <i class="{{ $itemIcon }}"></i>
                                        </span>
                                        <div class="lb-card-copy">
                                            @if ($editable)
                                                <input class="lb-card-label lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.label" value="{{ data_get($item, 'label') }}" placeholder="{{ __('Item') }}">
                                            @else
                                                <p class="lb-card-label">{{ data_get($item, 'label', __('Item')) }}</p>
                                            @endif
                                            @if ($editable || trim((string) data_get($item, 'note', '')) !== '')
                                                @if ($editable)
                                                    <input class="lb-card-note lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.note" value="{{ data_get($item, 'note') }}" placeholder="{{ __('Supporting text') }}">
                                                @else
                                                    <p class="lb-card-note">{{ data_get($item, 'note') }}</p>
                                                @endif
                                            @endif
                                        </div>
                                        @if ($itemUrl !== '')
                                            <a class="lb-card-action" href="{{ $itemActionUrl }}" target="_blank" rel="noopener noreferrer" @if($editable) wire:click.stop @endif>{{ __('Open') }}</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif (in_array($type, ['gallery', 'product'], true))
                            <div class="lb-gallery">
                                @foreach ($items as $itemIndex => $item)
                                    <div class="lb-gallery-card">
                                        <div class="lb-gallery-image">
                                            @if (trim((string) data_get($item, 'image', '')) !== '')
                                                <img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'label', __('Item')) }}">
                                            @endif
                                        </div>
                                        <div class="lb-gallery-copy">
                                            @if ($editable)
                                                <input class="lb-card-label lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.label" value="{{ data_get($item, 'label') }}" placeholder="{{ __('Item') }}">
                                            @else
                                                <p class="lb-card-label">{{ data_get($item, 'label', __('Item')) }}</p>
                                            @endif
                                            @if ($editable || trim((string) data_get($item, 'note', '')) !== '')
                                                @if ($editable)
                                                    <input class="lb-card-note lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.note" value="{{ data_get($item, 'note') }}" placeholder="{{ __('Short caption') }}">
                                                @else
                                                    <p class="lb-card-note">{{ data_get($item, 'note') }}</p>
                                                @endif
                                            @endif
                                            @if ($type === 'product' && ($editable || trim((string) data_get($item, 'price', '')) !== ''))
                                                @if ($editable)
                                                    <input class="lb-card-meta lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.price" value="{{ data_get($item, 'price') }}" placeholder="{{ __('Price') }}">
                                                @else
                                                    <div class="lb-card-meta">{{ data_get($item, 'price') }}</div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($type === 'faq')
                            <div class="lb-stack">
                                @foreach ($items as $itemIndex => $item)
                                    <div class="lb-card lb-card--column">
                                        @if ($editable)
                                            <input class="lb-card-label lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.label" value="{{ data_get($item, 'label') }}" placeholder="{{ __('Question') }}">
                                            <input class="lb-card-note lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.items.{{ $itemIndex }}.answer" value="{{ data_get($item, 'answer') }}" placeholder="{{ __('Answer') }}">
                                        @else
                                            <p class="lb-card-label">{{ data_get($item, 'label', __('Question')) }}</p>
                                            @if (trim((string) data_get($item, 'answer', '')) !== '')
                                                <p class="lb-card-note">{{ data_get($item, 'answer') }}</p>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif ($type === 'video')
                            @php
                                $blockContent = trim((string) data_get($block, 'content', ''));
                                $videoUrl = trim((string) data_get($block, 'button_url', ''));
                                $videoEmbedUrl = $resolveVideoEmbedUrl($videoUrl);
                                $videoLabel = trim((string) data_get($block, 'button_label', '')) ?: __('Watch video');
                            @endphp

                            @if ($videoEmbedUrl !== null)
                                <div class="lb-video-frame">
                                    <iframe src="{{ $videoEmbedUrl }}" title="{{ data_get($block, 'title', __('Video')) }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                                </div>
                            @elseif ($videoUrl !== '')
                                <a class="lb-inline-cta" href="{{ $videoUrl }}" target="_blank" rel="noopener noreferrer">{{ $videoLabel }}</a>
                            @else
                                <div class="lb-placeholder">{{ __('Paste a YouTube or Vimeo URL to show the video here.') }}</div>
                            @endif

                            @if ($editable)
                                <textarea class="lb-rich-content lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.content" rows="2" placeholder="{{ __('Optional video caption') }}">{{ $blockContent }}</textarea>
                            @elseif ($blockContent !== '')
                                <div class="lb-rich-content">{{ $blockContent }}</div>
                            @endif
                        @elseif (in_array($type, ['header', 'embed'], true))
                            @php
                                $blockContent = trim((string) data_get($block, 'content', ''));
                                $buttonLabel = trim((string) data_get($block, 'button_label', ''));
                                $buttonUrl = trim((string) data_get($block, 'button_url', ''));
                                $embedIframeUrl = $type === 'embed'
                                    ? ($extractEmbedIframeUrl($blockContent) ?: $resolveVideoEmbedUrl($blockContent))
                                    : null;
                            @endphp

                            @if ($editable)
                                @if ($embedIframeUrl !== null)
                                    <div class="lb-embed-frame">
                                        <iframe src="{{ $embedIframeUrl }}" title="{{ data_get($block, 'title', __('Embedded content')) }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                    </div>
                                @endif
                                <textarea class="lb-rich-content lb-inline-field" wire:focus="selectBlock({{ $blockIndex }})" wire:model.live.debounce.300ms="blocks.{{ $blockIndex }}.content" rows="3" placeholder="{{ match ($type) {
                                    'embed' => __('Paste iframe, embed code, or short fallback text'),
                                    default => __('Write header content'),
                                } }}">{{ $blockContent }}</textarea>
                            @elseif ($blockContent !== '')
                                @if ($embedIframeUrl !== null)
                                    <div class="lb-embed-frame">
                                        <iframe src="{{ $embedIframeUrl }}" title="{{ data_get($block, 'title', __('Embedded content')) }}" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                    </div>
                                @else
                                    <div class="lb-rich-content">{{ $blockContent }}</div>
                                @endif
                            @endif

                            @if ($buttonLabel !== '')
                                <span class="lb-inline-cta">{{ $buttonLabel }}</span>
                            @elseif (! $editable && $blockContent === '')
                                <div class="lb-placeholder">
                                    {{ match ($type) {
                                        'embed' => __('Embed content will appear here.'),
                                        default => __('Header content will appear here.'),
                                    } }}
                                </div>
                            @endif
                        @else
                            <div class="lb-empty" style="margin-top:14px;">{{ __('Content will appear here.') }}</div>
                        @endif
                    </section>
                @empty
                    <section class="lb-section">
                        <div class="lb-empty">{{ __('Add blocks to start building this page.') }}</div>
                    </section>
                @endforelse
            </div>

            @if ($brandingText !== '')
                <div class="lb-footer">{{ $brandingText }}</div>
            @endif
        </div>
    </div>
</div>
