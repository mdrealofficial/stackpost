@php
    $previewCaption = (string) ($composer['caption'] ?: __('Start typing a caption to preview how the post will read.'));
@endphp

<div class="mx-auto space-y-4" style="width: min(100%, 20rem);">
    <div class="flex items-center gap-2 text-[0.95rem] font-semibold" style="color: #0a66c2;">
        <i class="fa-brands fa-linkedin text-[1.1rem]"></i>
        <span>{{ __('LinkedIn') }}</span>
    </div>

    <div class="overflow-hidden rounded-[1rem] border shadow-[0_20px_40px_-30px_rgba(10,102,194,0.35)]" style="border-color: rgba(10,102,194,0.22); background-color: #ffffff;">
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
                    <p class="truncate text-[1.05rem] font-semibold" style="color: #0f172a;">{{ $composerAccount->display_name }}</p>
                    <div class="mt-1 flex items-center gap-2 text-xs" style="color: #64748b;">
                        <span>{{ now()->format('M j') }}</span>
                        <span>&middot;</span>
                        <span>{{ $composerAccount->category ?: __('Profile') }}</span>
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
                <video x-bind:src="previewMediaUrl()" muted playsinline controls class="aspect-[1.15/1] w-full object-cover"></video>
            </template>
            <template x-if="previewMediaUrl() !== '' && !previewMediaIsVideo()">
                <img x-bind:src="previewMediaUrl()" alt="{{ __('Preview media') }}" class="aspect-[1.15/1] w-full object-cover">
            </template>
            <template x-if="previewMediaUrl() === ''">
                <div class="flex aspect-[1.15/1] w-full items-center justify-center">
                    <div class="text-center" style="color: #94a3b8;">
                        <i class="fa-light fa-images text-6xl"></i>
                        <p class="mt-4 text-sm">{{ __('LinkedIn preview') }}</p>
                    </div>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-4 border-t px-3 py-2 text-sm font-semibold" style="border-color: rgba(15,23,42,0.08); color: #334155;">
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-thumbs-up"></i><span>{{ __('Like') }}</span></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-comment"></i><span>{{ __('Comment') }}</span></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-repeat"></i><span>{{ __('Repost') }}</span></div>
            <div class="flex items-center justify-center gap-2 py-2"><i class="fa-light fa-paper-plane"></i><span>{{ __('Send') }}</span></div>
        </div>
    </div>
</div>
