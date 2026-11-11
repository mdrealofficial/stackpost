@php
    $previewPostTarget = strtoupper((string) data_get($composer, 'network_options.'.$composerAccount->provider_key.'.post_to', 'feed'));
    $selectedComposerCampaign = $composerCampaigns->firstWhere('id', (int) ($composer['campaign_id'] ?? 0));
    $previewCaption = (string) ($composer['caption'] ?: __('Start typing a caption to preview how the post will read.'));
    $mediaItems = collect((array) ($composer['media_items'] ?? []))->values();
@endphp

<div class="mx-auto rounded-[1.15rem] border shadow-[0_24px_44px_-30px_rgba(15,23,42,0.34)]" style="width: min(100%, 20rem); border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
    <div class="h-28 rounded-t-[1.15rem]" style="background: linear-gradient(135deg, {{ $composerProvider['color'] ?? '#2563eb' }} 0%, #0f172a 100%);"></div>
    <div class="space-y-4 p-4">
        <div class="-mt-12 flex items-end gap-3">
            <span class="inline-flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border-4 text-sm font-semibold shadow-lg" style="border-color: var(--theme-surface-base); background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-header-text-color);">
                @if ($composerAccount->avatar_url)
                    <img src="{{ $composerAccount->avatar_url }}" alt="{{ $composerAccount->display_name }}" class="h-full w-full object-cover">
                @else
                    {{ str($composerAccount->display_name)->substr(0, 2)->upper() }}
                @endif
            </span>
            <div class="pb-1">
                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $composerAccount->display_name }}</p>
                <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $composerAccount->username ? '@'.$composerAccount->username : ucfirst($composerAccount->provider_key) }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">{{ $previewPostTarget }}</span>
                @if ($selectedComposerCampaign)
                    <span class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);">{{ $selectedComposerCampaign->name }}</span>
                @endif
            </div>

            <p class="text-sm leading-7 whitespace-pre-line" style="color: var(--theme-header-text-color);">{{ $previewCaption }}</p>

            @if ($mediaItems->isNotEmpty())
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($mediaItems as $mediaItem)
                        <div class="overflow-hidden rounded-[0.95rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                            @if (($mediaItem['isImage'] ?? false) && filled($mediaItem['previewUrl'] ?? null))
                                <img src="{{ $mediaItem['previewUrl'] }}" alt="{{ $mediaItem['name'] ?? 'media' }}" class="h-24 w-full object-cover">
                            @elseif ((str_starts_with((string) ($mediaItem['mimeType'] ?? ''), 'video/') || (($mediaItem['category'] ?? null) === 'video')) && filled($mediaItem['previewUrl'] ?? null))
                                <video src="{{ $mediaItem['previewUrl'] }}" preload="metadata" muted playsinline class="h-24 w-full object-cover"></video>
                            @else
                                <div class="flex h-24 items-center justify-center">
                                    <i class="fa-light fa-circle-play text-2xl" style="color: var(--theme-muted-text-color);"></i>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-[1rem] border border-dashed px-4 py-8 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                    <i class="fa-light fa-image text-3xl" style="color: var(--theme-muted-text-color);"></i>
                    <p class="mt-3 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Choose files from the media browser to preview them here.') }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
