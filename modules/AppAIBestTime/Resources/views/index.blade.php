<x-ui.ai-workspace :title="__('Best Time')" :description="__('Explore posting windows for your connected channels.')" :input-title="__('Choose channels')" :input-description="__('Choose accounts, or leave empty to use workspace posting history.')" :output-title="__('Suggested posting windows')" :output-description="__('Compare the recommendations and their supporting reasons.')" action="suggest">
<x-slot:input>@if ($accounts->isNotEmpty())
                <div class="mt-5">
                    <x-ui.channel-selector
                        name="selectedAccountIds"
                        wire-model="selectedAccountIds"
                        :options="$channelOptions"
                        :network-options="$channelNetworks"
                        :selected="collect($selectedAccountIds)->map(fn ($id) => (string) $id)->all()"
                        :label="__('Channel')"
                        :error="$errors->first('selectedAccountIds') ?: $errors->first('selectedAccountIds.*')"
                        :placeholder="__('Choose one or more accounts')"
                        :empty-label="__('No matching channels found.')"
                        :multiple="true"
                        :live="true"
                        :sync-on-close="false"
                        :show-network-filters="true"
                        :connect-href="route('portal.channels')"
                        :connect-label="__('Connect a channel')"
                    />
                </div>
            @else
                <x-ui.empty class="mt-5" icon="fa-light fa-share-nodes" :title="__('No active channels')" :description="__('Connect channels first, then return here to analyze posting windows.')">
                    <x-ui.button class="mt-4" :href="route('portal.channels')" variant="outline" wire:navigate>{{ __('Open Channels') }}</x-ui.button>
                </x-ui.empty>
            @endif

            <div class="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color: rgba(var(--theme-border-color-rgb), 0.5);">
                <x-ui.button type="button" wire:click="suggest" wire:loading.attr="disabled" wire:target="suggest" :disabled="!($creditPreview['enough'] ?? true)">
                    <i class="fa-light fa-clock"></i>
                    <span wire:loading.remove wire:target="suggest">{{ __('Suggest times') }}</span>
                    <span wire:loading wire:target="suggest">{{ __('Analyzing...') }}</span>
                </x-ui.button>

                <x-ui.ai-credit-summary :preview="$creditPreview" />
            </div>

            @if (!($creditPreview['enough'] ?? true))
                <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ __('Not enough credits remaining for this action.') }}</p>
                @include(theme_view('partials.credit-topup-cta', 'app'))
            @endif</x-slot:input>
<x-slot:resultActions>@if ($result)
                    <span class="rounded-full border px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.16em]" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">
                        {{ count($result) }} {{ __('slots') }}
                    </span>
                @endif</x-slot:resultActions>
<x-slot:results>
<x-ui.card tone="soft" padding="sm" class="mb-4">
    <p class="text-sm leading-6" style="color:var(--theme-muted-text-color)">{{ __('Suggestions use scheduled and published post history, not engagement results. Without history, you will see starter times to test. The score ranks suggestions; it is not a success rate.') }}</p>
</x-ui.card>@if ($result)
                <div class="grid gap-3">
                    @foreach ($result as $slot)
                        <x-ui.card padding="sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $slot['label'] }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-[0.14em]" style="color: var(--theme-muted-text-color);">{{ __('Ranking score') }}</p>
                                </div>
                                <span class="rounded-full border px-3 py-1.5 text-xs font-semibold" style="border-color: rgba(var(--theme-accent-rgb), 0.24); background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);">{{ $slot['confidence'] }}/100</span>
                            </div>
                            <div class="mt-3 h-2.5 overflow-hidden" style="background-color: rgba(var(--theme-border-color-rgb), 0.18);">
                                <div class="h-full transition-all duration-500" style="width: {{ max(0, min(100, (int) ($slot['confidence'] ?? 0))) }}%; background: linear-gradient(90deg, rgba(var(--theme-accent-rgb), 0.55), var(--theme-accent));"></div>
                            </div>
                            @foreach (($slot['reasons'] ?? []) as $reason)
                                <p class="mt-3 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $reason }}</p>
                            @endforeach
                        </x-ui.card>
                    @endforeach
                </div>
            @else
                <x-ui.empty icon="fa-light fa-clock" :title="__('Plan your next posting window')" :description="__('Choose channels and select Suggest times.')" />
            @endif</x-slot:results>
</x-ui.ai-workspace>
