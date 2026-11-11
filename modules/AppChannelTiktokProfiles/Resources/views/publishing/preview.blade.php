@php
    $previewCaption = (string) ($composer['caption'] ?: __('Start typing a caption to preview how the post will read.'));
@endphp

<div class="mx-auto space-y-4" style="width: min(100%, 20rem);">
    <div class="flex items-center gap-2 text-[0.95rem] font-semibold" style="color: #111111;">
        <i class="fa-brands fa-tiktok text-[1.1rem]"></i>
        <span>{{ __('TikTok') }}</span>
    </div>

    <div class="overflow-hidden rounded-[1.1rem] border shadow-[0_22px_44px_-30px_rgba(15,23,42,0.38)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: linear-gradient(180deg, #161616 0%, #0b0b0b 100%);">
        <div class="flex items-center gap-3 px-4 py-4">
            <span class="inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border text-sm font-semibold" style="border-color: rgba(255,255,255,0.12); background-color: rgba(255,255,255,0.06); color: #ffffff;">
                @if ($composerAccount->avatar_url)
                    <img src="{{ $composerAccount->avatar_url }}" alt="{{ $composerAccount->display_name }}" class="h-full w-full object-cover">
                @else
                    {{ str($composerAccount->display_name)->substr(0, 2)->upper() }}
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold" style="color: #ffffff;">{{ $composerAccount->display_name }}</p>
                <p class="truncate text-xs" style="color: rgba(255,255,255,0.7);">{{ $composerAccount->username ? '@'.$composerAccount->username : __('TikTok Profile') }}</p>
            </div>
        </div>

        <div class="px-4 pb-4">
            <div class="overflow-hidden rounded-[1rem]" style="background-color: rgba(255,255,255,0.04);">
                <template x-if="previewMediaUrl() !== '' && previewMediaIsVideo()">
                    <video x-bind:src="previewMediaUrl()" muted playsinline controls class="aspect-[9/16] w-full object-cover"></video>
                </template>
                <template x-if="previewMediaUrl() !== '' && !previewMediaIsVideo()">
                    <img x-bind:src="previewMediaUrl()" alt="{{ __('Preview media') }}" class="aspect-[9/16] w-full object-cover">
                </template>
                <template x-if="previewMediaUrl() === ''">
                    <div class="flex aspect-[9/16] w-full items-center justify-center">
                        <div class="text-center" style="color: rgba(255,255,255,0.42);">
                            <i class="fa-light fa-play text-5xl"></i>
                            <p class="mt-4 text-sm">{{ __('Video preview') }}</p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-4 space-y-2">
                <p class="text-sm font-semibold leading-6 whitespace-pre-line" style="color: #ffffff;" x-text="(String(localPreviewCaption || '').trim() !== '') ? localPreviewCaption : @js($previewCaption)">{{ $previewCaption }}</p>
                <div class="flex items-center justify-between text-xs" style="color: rgba(255,255,255,0.62);">
                    <span>{{ __('For You') }}</span>
                    <span>{{ __('TikTok preview') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
