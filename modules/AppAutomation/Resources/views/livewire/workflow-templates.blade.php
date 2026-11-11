<div class="px-5 py-6 sm:px-8">
    <div class="mx-auto max-w-5xl space-y-6">
        <x-ui.sub-header compact :title="__('Choose your outcome')" :description="__('Start with a focused workflow. Set up the source, content and destinations before activating it.')">
            <x-slot:actions><x-ui.button :href="route('portal.automation.index')" wire:navigate variant="outline" size="sm">{{ __('All workflows') }}</x-ui.button></x-slot:actions>
        </x-ui.sub-header>
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($templates as $template)
                <article class="flex flex-col gap-5 rounded-2xl border p-6" style="border-color:var(--theme-card-border-color);background:var(--theme-surface-base)">
                    <div class="flex items-center justify-between"><i class="{{ $template['icon'] }} text-2xl" style="color:var(--theme-accent)"></i><span class="text-xs" style="color:var(--theme-muted-text-color)">{{ $template['key'] === 'drive_draft' ? __('Start here') : __('Team approval required') }}</span></div>
                    <div><h2 class="text-xl font-semibold">{{ $template['name'] }}</h2><p class="mt-2 text-sm leading-6" style="color:var(--theme-muted-text-color)">{{ $template['description'] }}</p></div>
                    <ol class="flex flex-wrap items-center gap-2 text-xs">@foreach ($template['steps'] as $step)<li class="rounded-lg px-3 py-2" style="background:var(--theme-surface-soft)">{{ $step }}</li>@endforeach</ol>
                    @if ($canManage && $schemaReady)<x-ui.button :href="route('portal.automation.workflows.create', ['template' => $template['key']])" wire:navigate class="mt-auto justify-center">{{ __('Set up workflow') }} <i class="fa-light fa-arrow-right"></i></x-ui.button>@endif
                </article>
            @endforeach
        </div>
        <p class="text-sm leading-6" style="color:var(--theme-muted-text-color)"><i class="fa-light fa-circle-info mr-2"></i>{{ __('Both workflows need a connected Google Drive account and background processing. You can save your setup as a draft at any time.') }} <a class="underline" href="{{ route('portal.automation.index') }}#workflow-readiness">{{ __('Check workspace setup') }}</a></p>
    </div>
</div>
