@php
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
    <div class="flex items-center gap-2 text-[0.95rem] font-semibold" style="color: #1877f2;">
        <i class="fa-brands fa-square-facebook text-[1.1rem]"></i>
        <span>{{ __('Facebook') }}</span>
    </div>

    <div class="overflow-hidden rounded-[1rem] border shadow-[0_20px_40px_-30px_rgba(24,119,242,0.35)]" style="border-color: rgba(24,119,242,0.22); background-color: #ffffff;">
        <div class="border-b px-4 py-4" style="border-color: rgba(15,23,42,0.08);">
            <div class="flex items-start gap-3">
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
                        @if ($selectedComposerCampaign)
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]" style="background-color: rgba(24,119,242,0.08); color: #1877f2;">{{ $selectedComposerCampaign->name }}</span>
                        @endif
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-xs" style="color: #64748b;">
                        <span>{{ $previewDate }}</span>
                        <span>&middot;</span>
                        <i class="fa-light fa-earth-americas"></i>
                    </div>
                </div>
            </div>

            <p
                class="mt-4 whitespace-pre-line text-sm leading-7"
                style="color: #0f172a;"
                x-text="(String(localPreviewCaption || '').trim() !== '') ? localPreviewCaption : @js($previewCaption)"
            >{{ $previewCaption }}</p>
        </div>

        <div style="background-color: #f1f5f9;">
            <template x-if="previewMediaUrl() !== '' && previewMediaIsVideo()">
                <video x-bind:src="previewMediaUrl()" muted playsinline controls class="aspect-[1.25/1] w-full object-cover"></video>
            </template>
            <template x-if="previewMediaUrl() !== '' && !previewMediaIsVideo()">
                <img x-bind:src="previewMediaUrl()" alt="{{ __('Preview media') }}" class="aspect-[1.25/1] w-full object-cover">
            </template>
            <template x-if="previewMediaUrl() === ''">
                <div class="flex aspect-[1.25/1] w-full items-center justify-center">
                    <div class="text-center" style="color: #94a3b8;">
                        <i class="fa-light fa-images text-6xl"></i>
                        <p class="mt-4 text-sm">{{ __('Image preview') }}</p>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-3 border-t px-3 py-2 text-sm font-semibold" style="border-color: rgba(15,23,42,0.08); color: #334155;">
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-thumbs-up"></i><span>{{ __('Like') }}</span></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-comment"></i><span>{{ __('Comment') }}</span></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-share"></i><span>{{ __('Share') }}</span></div>
        </div>
    </div>
</div>
