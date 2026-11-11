@if ($result)
<section class="aic-output">
    <header class="aic-output-heading"><div><span class="aic-output-kicker">{{ __('THE CREATIVE EDIT') }}</span><h2>{{ __('A few ways to say it.') }} <span>{{ count($result['variants'] ?? []) }}</span></h2><p>{{ __('Compare the angles. Keep what sounds like you.') }}</p></div><x-ui.button type="button" size="sm" variant="outline" wire:click="saveAllResults" wire:loading.attr="disabled" wire:target="saveAllResults" :disabled="empty($result['variants'] ?? [])"><i class="fa-light fa-books"></i><span wire:loading.remove wire:target="saveAllResults">{{ __('Save all') }}</span><span wire:loading wire:target="saveAllResults">{{ __('Saving all...') }}</span></x-ui.button></header>
    <div class="aic-output-grid">
    @foreach (($result['variants'] ?? []) as $item)
        @php
            $platformKey = (string) ($item['platform'] ?? '');
            $platformLabel = collect($platformOptions)->firstWhere('key', $platformKey)['label'] ?? \Illuminate\Support\Str::headline($platformKey ?: 'Social post');
            $platformIcon = match ($platformKey) {
                'facebook' => 'fa-brands fa-facebook-f', 'instagram', 'instagram_unofficial' => 'fa-brands fa-instagram',
                'linkedin', 'linkedin_profile', 'linkedin_page' => 'fa-brands fa-linkedin-in', 'x', 'twitter' => 'fa-brands fa-x-twitter',
                'threads' => 'fa-brands fa-threads', 'tiktok' => 'fa-brands fa-tiktok', 'youtube' => 'fa-brands fa-youtube',
                'pinterest' => 'fa-brands fa-pinterest-p', 'reddit' => 'fa-brands fa-reddit-alien', 'telegram' => 'fa-brands fa-telegram',
                default => 'fa-light fa-message',
            };
            $captionText = trim((string) ($item['caption'] ?? ''));
            $hookText = trim((string) ($item['hook'] ?? ''));
            $ctaText = trim((string) ($item['cta'] ?? ''));
            $normalize = fn ($text) => preg_replace('/\s+/u', ' ', trim($text));
            $showHook = $hookText !== '' && !str_contains($normalize($captionText), $normalize($hookText));
            $showCta = $ctaText !== '' && !str_contains($normalize($captionText), $normalize($ctaText));
            $hashtagsText = implode(' ', array_map(fn ($tag) => '#'.ltrim((string) $tag, '#'), $item['hashtags'] ?? []));
            $copyContent = trim(implode("\n\n", array_filter([$showHook ? $hookText : '', $captionText, $showCta ? $ctaText : '', $hashtagsText])));
            $saved = in_array($loop->index, $savedResultIndexes ?? [], true);
        @endphp
        <article class="aic-result-card" wire:key="aic-result-{{ $loop->index }}-{{ substr(md5($copyContent), 0, 10) }}">
            <header class="aic-result-header"><div class="aic-result-platform"><span class="aic-result-icon"><i class="{{ $platformIcon }}" aria-hidden="true"></i></span><div><strong>{{ $platformLabel }}</strong><span>{{ __('Social caption') }}</span></div></div><span class="aic-result-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></header>
            <div class="aic-result-body">
                @if ($showHook)<h3>{{ $hookText }}</h3>@endif
                <div class="aic-result-caption">{{ $captionText }}</div>
                @if ($showCta)<p class="aic-result-cta"><i class="fa-light fa-arrow-turn-down-right" aria-hidden="true"></i><span>{{ $ctaText }}</span></p>@endif
                @if ($hashtagsText)<p class="aic-result-hashtags">{{ $hashtagsText }}</p>@endif
            </div>
            <div class="aic-result-meta"><span>{{ __(':count characters', ['count' => mb_strlen($copyContent)]) }}</span>@if ($saved)<span class="aic-result-saved"><i class="fa-light fa-check"></i> {{ __('Saved') }}</span>@else<span>{{ __('Variation :number', ['number' => $loop->iteration]) }}</span>@endif</div>
            @if (!empty($item['notes']))<details class="aic-result-notes"><summary>{{ __('Writing notes') }}<i class="fa-light fa-chevron-down"></i></summary><p>{{ $item['notes'] }}</p></details>@endif
            <footer class="aic-result-actions">
                        <button type="button" class="aic-result-copy" data-copy="{{ $copyContent }}" data-success="{{ __('Copied') }}" data-label="{{ __('Copy') }}" data-failure="{{ __('Could not copy. Select the caption text to copy it.') }}" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText($el.dataset.copy).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => $dispatch('app-toast', { message: $el.dataset.failure, type: 'error' }))"><i class="fa-light fa-copy"></i><span x-text="copied ? $el.parentElement.dataset.success : $el.parentElement.dataset.label">{{ __('Copy') }}</span></button>
                        <x-ui.button
                            type="button"
                            size="sm"
                            variant="{{ in_array($loop->index, $savedResultIndexes ?? [], true) ? 'outline' : 'primary' }}"
                            wire:click="saveResultCaption({{ $loop->index }})"
                            wire:loading.attr="disabled"
                            wire:target="saveResultCaption({{ $loop->index }})"
                            :disabled="in_array($loop->index, $savedResultIndexes ?? [], true)"
                        >
                            <i class="fa-light fa-book-bookmark"></i>
                            <span wire:loading.remove wire:target="saveResultCaption({{ $loop->index }})">
                                {{ in_array($loop->index, $savedResultIndexes ?? [], true) ? __('Saved to Captions') : __('Save to Captions') }}
                            </span>
                            <span wire:loading wire:target="saveResultCaption({{ $loop->index }})">{{ __('Saving...') }}</span>
                        </x-ui.button>

            </footer>
        </article>
    @endforeach
    </div>
    @if (!empty($result['summary']))<details class="aic-output-context"><summary><i class="fa-light fa-circle-info"></i> {{ __('About these results') }}</summary><p>{{ $result['summary'] }}</p></details>@endif
</section>
@endif
