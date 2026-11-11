@php
    $previewPostTarget = strtoupper((string) data_get($composer, 'network_options.'.$composerAccount->provider_key.'.post_to', 'feed'));
    $selectedComposerCampaign = $composerCampaigns->firstWhere('id', (int) ($composer['campaign_id'] ?? 0));
    $previewCaption = (string) ($composer['caption'] ?: __('Start typing a caption to preview how the post will read.'));
    $mediaItems = collect((array) ($composer['media_items'] ?? []))->values();
    $primaryMedia = $mediaItems->first();
    $primaryPreviewUrl = is_array($primaryMedia) ? (string) ($primaryMedia['previewUrl'] ?? '') : '';
    $primaryMimeType = strtolower((string) (is_array($primaryMedia) ? ($primaryMedia['mimeType'] ?? '') : ''));
    $primaryIsVideo = str_starts_with($primaryMimeType, 'video/');
    $hasMedia = $primaryPreviewUrl !== '';
    $previewDate = now()->format('M j');
@endphp

<div class="mx-auto space-y-4" style="width: min(100%, 20rem);">
    <div class="flex items-center gap-2 text-[0.95rem] font-semibold" style="color: #c13584;">
        <i class="fa-brands fa-instagram text-[1.1rem]"></i>
        <span>{{ __('Instagram') }}</span>
    </div>

    <div class="overflow-hidden rounded-[1rem] border shadow-[0_20px_40px_-30px_rgba(193,53,132,0.35)]" style="border-color: rgba(193,53,132,0.22); background-color: #ffffff;">
        <div class="flex items-center gap-3 px-4 py-4">
            <span class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border text-sm font-semibold" style="border-color: rgba(15,23,42,0.08); background-color: #f8fafc; color: #0f172a;">
                @if ($composerAccount->avatar_url)
                    <img src="{{ $composerAccount->avatar_url }}" alt="{{ $composerAccount->display_name }}" class="h-full w-full object-cover">
                @else
                    {{ str($composerAccount->display_name)->substr(0, 2)->upper() }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <p class="truncate text-[1.05rem] font-semibold" style="color: #0f172a;">{{ $composerAccount->display_name }}</p>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]" style="background-color: rgba(193,53,132,0.08); color: #c13584;">{{ $previewPostTarget }}</span>
                </div>
                <p class="mt-1 text-xs" style="color: #94a3b8;">{{ $previewDate }}</p>
            </div>
        </div>

        <div style="background-color: #f1f5f9;">
            <template x-if="previewMediaUrl() !== '' && previewMediaIsVideo()">
                <video x-bind:src="previewMediaUrl()" muted playsinline controls class="aspect-square w-full object-cover"></video>
            </template>
            <template x-if="previewMediaUrl() !== '' && !previewMediaIsVideo()">
                <img x-bind:src="previewMediaUrl()" alt="{{ __('Preview media') }}" class="aspect-square w-full object-cover">
            </template>
            <template x-if="previewMediaUrl() === ''">
                <div class="flex aspect-square w-full items-center justify-center">
                    <div class="text-center" style="color: #94a3b8;">
                        <i class="fa-light fa-images text-6xl"></i>
                        <p class="mt-4 text-sm">{{ __('Image preview') }}</p>
                    </div>
                </div>
            </template>
        </div>

        <div class="px-4 py-4">
            <div class="flex items-center gap-5 text-[1.35rem]" style="color: #0f172a;">
                <i class="fa-light fa-heart"></i>
                <i class="fa-light fa-comment"></i>
                <i class="fa-light fa-paper-plane-top"></i>
            </div>

            <div class="mt-4 space-y-2 text-sm leading-7" style="color: #0f172a;">
                <p class="whitespace-pre-line">
                    <span class="font-semibold">{{ $composerAccount->display_name }}</span>
                    <span x-text="(String(localPreviewCaption || '').trim() !== '') ? localPreviewCaption : @js($previewCaption)">{{ $previewCaption }}</span>
                </p>
                @if ($selectedComposerCampaign)
                    <p class="text-xs font-semibold uppercase tracking-[0.14em]" style="color: #c13584;">#{{ str($selectedComposerCampaign->name)->slug('_') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
