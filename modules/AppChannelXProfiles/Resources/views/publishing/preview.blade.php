@php
    $previewCaption = (string) ($composer['caption'] ?: __('Start typing a caption to preview how the post will read.'));
    $accountInitials = str((string) $composerAccount->display_name)->substr(0, 2)->upper()->toString();
@endphp

<div class="mx-auto space-y-4" style="width: min(100%, 21rem);">
    <div class="flex items-center gap-2 text-[0.95rem] font-semibold" style="color: #111111;">
        <i class="fa-brands fa-x-twitter text-[1.05rem]"></i>
        <span>{{ __('X') }}</span>
    </div>

    <div class="overflow-hidden rounded-[1.35rem] border shadow-[0_24px_46px_-34px_rgba(15,23,42,0.28)]" style="border-color: rgba(15,23,42,0.08); background-color: #ffffff;">
        <div class="px-4 py-4">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border text-sm font-semibold" style="border-color: rgba(15,23,42,0.08); background-color: #f8fafc; color: #0f172a;">
                    @if ($composerAccount->avatar_url)
                        <img src="{{ $composerAccount->avatar_url }}" alt="{{ $composerAccount->display_name }}" class="h-full w-full object-cover">
                    @else
                        {{ $accountInitials !== '' ? $accountInitials : 'X' }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-[1rem] font-semibold" style="color: #0f172a;">{{ $composerAccount->display_name }}</p>
                        @if (data_get($composerAccount->metadata, 'verified', false))
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-full text-[10px]" style="background-color: #1d9bf0; color: #fff;">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-[0.8rem]" style="color: #536471;">
                        {{ $composerAccount->username ? '@'.$composerAccount->username : '@xuser' }}
                        <span class="mx-1.5">&middot;</span>
                        {{ now()->format('M j') }}
                    </p>
                </div>
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm" style="color: #536471;">
                    <i class="fa-light fa-ellipsis"></i>
                </button>
            </div>

            <p
                class="mt-3 whitespace-pre-line text-[0.95rem] leading-7"
                style="color: #0f172a;"
                x-text="(String(localPreviewCaption || '').trim() !== '') ? localPreviewCaption : @js($previewCaption)"
            >{{ $previewCaption }}</p>

            <div class="mt-3 overflow-hidden rounded-[1rem] border" style="border-color: rgba(15,23,42,0.08); background-color: #f8fafc;">
                <template x-if="previewMediaUrl() !== '' && previewMediaIsVideo()">
                    <video x-bind:src="previewMediaUrl()" muted playsinline controls class="aspect-[1.15/1] w-full object-cover"></video>
                </template>
                <template x-if="previewMediaUrl() !== '' && !previewMediaIsVideo()">
                    <img x-bind:src="previewMediaUrl()" alt="{{ __('Preview media') }}" class="aspect-[1.15/1] w-full object-cover">
                </template>
                <template x-if="previewMediaUrl() === ''">
                    <div class="flex aspect-[1.15/1] w-full items-center justify-center">
                        <div class="text-center" style="color: #94a3b8;">
                            <i class="fa-light fa-image text-6xl"></i>
                            <p class="mt-4 text-sm">{{ __('Tweet preview') }}</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-4 border-t px-3 py-2 text-sm" style="border-color: rgba(15,23,42,0.08); color: #536471;">
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-comment"></i></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-repeat"></i></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-heart"></i></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-arrow-up-from-bracket"></i></div>
        </div>
    </div>
</div>
